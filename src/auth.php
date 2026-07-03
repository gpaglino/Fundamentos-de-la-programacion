<?php
// Este archivo hace toda la magia: auth, usuarios, productos, pedidos
// Acá está la lógica central de la app

// Rutas donde guardamos todo en JSON
define('RUTA_USUARIOS',  __DIR__ . '/../config/usuarios.json');
define('RUTA_PEDIDOS',   __DIR__ . '/../config/pedidos.json');
define('RUTA_PRODUCTOS', __DIR__ . '/../config/productos.json');
define('RUTA_FACTURAS',  __DIR__ . '/../config/facturas.json');
define('RUTA_LOG',       __DIR__ . '/../logs/auditoria.log');

// Si no interactuas en 5 minutos, se cierra la sesión
define('TIMEOUT_SESION', 300);


// SESIÓN


function iniciar_sesion_segura(): void {
    // Solo la iniciamos si no está abierta
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,           // Se borra al cerrar navegador
            'httponly' => true,        // JavaScript no puede acceder
            'samesite' => 'Strict',    // No manda cookies a otros sitios
        ]);
        session_start();
    }

    // Chequear timeout por inactividad
    if (isset($_SESSION['ultima_actividad'])) {
        $inactivo = time() - $_SESSION['ultima_actividad'];
        if ($inactivo > TIMEOUT_SESION) {
            // 5 min sin actividad, chau sesión
            session_unset();
            session_destroy();
            header('Location: /login.php?motivo=expirado');
            exit();
        }
    }
    // Actualizar la última actividad
    $_SESSION['ultima_actividad'] = time();
}

function sesion_activa(): bool {
    return session_status() !== PHP_SESSION_NONE
        && isset($_SESSION['id_usuario'], $_SESSION['rol']);
}

function requerir_login(): void {
    iniciar_sesion_segura();
    if (!sesion_activa()) {
        header('Location: /login.php?motivo=acceso');
        exit();
    }
}

function requerir_admin(): void {
    requerir_login();
    if ($_SESSION['rol'] !== 'admin') {
        header('Location: /index.php?error=denegado');
        exit();
    }
}

function requerir_activo(): void {
    requerir_login();
    if ($_SESSION['estado'] !== 'activo') {
        header('Location: /login.php?motivo=pendiente');
        exit();
    }
}


// JSON


function leer_json(string $ruta): array {
    if (!is_readable($ruta)) {
        return [];
    }
    $contenido = file_get_contents($ruta);
    $datos = json_decode($contenido, true);
    return is_array($datos) ? $datos : [];
}

function escribir_json(string $ruta, array $datos): bool {
    $json = json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }
    return file_put_contents($ruta, $json, LOCK_EX) !== false;
}


// BUSCAR USUARIOS


function buscar_usuario_por_username(string $username): ?array {
    foreach (leer_json(RUTA_USUARIOS) as $u) {
        if ($u['username'] === $username) {
            return $u;
        }
    }
    return null;
}

function buscar_usuario_por_id(int $id): ?array {
    foreach (leer_json(RUTA_USUARIOS) as $u) {
        if ((int)$u['id_usuario'] === $id) {
            return $u;
        }
    }
    return null;
}

function buscar_usuario_por_email(string $email): ?array {
    foreach (leer_json(RUTA_USUARIOS) as $u) {
        if ($u['email'] === $email) {
            return $u;
        }
    }
    return null;
}

function buscar_usuario_por_dni(string $dni): ?array {
    foreach (leer_json(RUTA_USUARIOS) as $u) {
        if (isset($u['dni']) && $u['dni'] === $dni) {
            return $u;
        }
    }
    return null;
}

function calcular_edad(string $fecha_nacimiento): int {
    $fecha = new DateTime($fecha_nacimiento);
    $hoy = new DateTime();
    $edad = $hoy->diff($fecha)->y;
    return $edad;
}

function proximo_id_usuario(): int {
    $usuarios = leer_json(RUTA_USUARIOS);
    if (empty($usuarios)) {
        return 1;
    }
    return max(array_column($usuarios, 'id_usuario')) + 1;
}


// USUARIOS


function registrar_usuario(string $username, string $nombre, string $email, string $password, string $dni, string $fecha_nacimiento): bool {
    $usuarios = leer_json(RUTA_USUARIOS);

    $nuevo = [
        'id_usuario'      => proximo_id_usuario(),
        'username'        => htmlspecialchars($username, ENT_QUOTES, 'UTF-8'),
        'nombre'          => htmlspecialchars($nombre,   ENT_QUOTES, 'UTF-8'),
        'email'           => filter_var($email, FILTER_SANITIZE_EMAIL),
        'password_hash'   => password_hash($password, PASSWORD_BCRYPT),
        'dni'             => htmlspecialchars($dni, ENT_QUOTES, 'UTF-8'),
        'fecha_nacimiento' => $fecha_nacimiento,
        'edad'            => calcular_edad($fecha_nacimiento),
        'rol'             => 'usuario',
        'estado'          => 'pendiente',
        'fecha_registro'  => date('Y-m-d H:i:s'),
    ];

    $usuarios[] = $nuevo;
    return escribir_json(RUTA_USUARIOS, $usuarios);
}

function aprobar_usuario(int $id): ?string {
    $usuarios = leer_json(RUTA_USUARIOS);
    $nombre_aprobado = null;

    foreach ($usuarios as &$u) {
        if ((int)$u['id_usuario'] === $id) {
            $u['estado'] = 'activo';
            $nombre_aprobado = $u['nombre'];
            break;
        }
    }
    unset($u);

    if ($nombre_aprobado !== null) {
        escribir_json(RUTA_USUARIOS, $usuarios);
    }
    return $nombre_aprobado;
}

function rechazar_usuario(int $id): bool {
    $usuarios = leer_json(RUTA_USUARIOS);
    $usuarios_filtrados = array_filter($usuarios, function($u) use ($id) {
        return (int)$u['id_usuario'] !== $id;
    });

    if (count($usuarios_filtrados) === count($usuarios)) {
        return false;
    }

    return escribir_json(RUTA_USUARIOS, array_values($usuarios_filtrados));
}

function eliminar_usuario(int $id): bool {
    $usuarios = leer_json(RUTA_USUARIOS);
    $usuarios_filtrados = array_filter($usuarios, function($u) use ($id) {
        return (int)$u['id_usuario'] !== $id;
    });

    if (count($usuarios_filtrados) === count($usuarios)) {
        return false;
    }

    return escribir_json(RUTA_USUARIOS, array_values($usuarios_filtrados));
}


// LOGIN

function intentar_login(string $username, string $password): array {
    $usuario = buscar_usuario_por_username($username);

    if ($usuario === null || !password_verify($password, $usuario['password_hash'])) {
        registrar_log('LOGIN_FALLIDO', "Intento fallido: '{$username}'");
        return ['ok' => false, 'mensaje' => 'Usuario o contraseña incorrectos.'];
    }

    if ($usuario['estado'] === 'pendiente') {
        registrar_log('LOGIN_PENDIENTE', "Usuario pendiente: '{$username}'");
        return ['ok' => false, 'mensaje' => 'Tu cuenta está pendiente de aprobación.'];
    }

    session_regenerate_id(true);

    $_SESSION['id_usuario']       = $usuario['id_usuario'];
    $_SESSION['username']         = $usuario['username'];
    $_SESSION['nombre']           = $usuario['nombre'];
    $_SESSION['rol']              = $usuario['rol'];
    $_SESSION['estado']           = $usuario['estado'];
    $_SESSION['ultima_actividad'] = time();

    registrar_log('LOGIN_EXITOSO', "'{$username}' ingresó");
    return ['ok' => true, 'mensaje' => 'Bienvenido, ' . $usuario['nombre']];
}

function cerrar_sesion(): void {
    $username = $_SESSION['username'] ?? 'desconocido';
    session_unset();
    session_destroy();
    registrar_log('LOGOUT', "'{$username}' se desconectó");
}

// ============================================================================
// LOG
// ============================================================================

function registrar_log(string $evento, string $mensaje): void {
    $linea = sprintf(
        "[%s] [%s] %s\n",
        date('Y-m-d H:i:s'),
        strtoupper($evento),
        $mensaje
    );
    file_put_contents(RUTA_LOG, $linea, FILE_APPEND | LOCK_EX);
}

// ============================================================================
// FUNCIONES PARA GESTIONAR PRODUCTOS
// ============================================================================

/**
 * Busca un producto por su ID.
 */
function buscar_producto_por_id(int $id): ?array {
    foreach (leer_json(RUTA_PRODUCTOS) as $p) {
        if ((int)$p['id_producto'] === $id) {
            return $p;
        }
    }
    return null;
}

/**
 * Genera el siguiente ID disponible para un nuevo producto.
 * Los IDs de productos comienzan en 101 para distinguirlos de usuarios.
 */
function proximo_id_producto(): int {
    $productos = leer_json(RUTA_PRODUCTOS);
    if (empty($productos)) {
        return 101;  // Comenzamos en 101 para productos
    }
    // Obtenemos el ID máximo y le sumamos 1
    return max(array_column($productos, 'id_producto')) + 1;
}

/**
 * Agrega un nuevo producto al catálogo.
 * Solo los administradores pueden hacer esto.
 */
function agregar_producto(string $nombre, string $categoria, float $precio, int $stock): bool {
    $productos = leer_json(RUTA_PRODUCTOS);
    // Creamos el nuevo producto
    $nuevo = [
        'id_producto' => proximo_id_producto(),
        'nombre' => htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'),
        'categoria' => htmlspecialchars($categoria, ENT_QUOTES, 'UTF-8'),
        'precio' => (float)$precio,
        'stock' => (int)$stock,
    ];
    // Lo añadimos a la lista
    $productos[] = $nuevo;
    // Guardamos todos los productos
    return escribir_json(RUTA_PRODUCTOS, $productos);
}

/**
 * Actualiza el stock de un producto.
 * Se usa cuando un admin ajusta manualmente o cuando se crea un pedido.
 */
function actualizar_stock_producto(int $id_producto, int $nuevo_stock): bool {
    $productos = leer_json(RUTA_PRODUCTOS);
    // Buscamos el producto y actualizamos su stock
    foreach ($productos as &$p) {
        if ((int)$p['id_producto'] === $id_producto) {
            $p['stock'] = (int)$nuevo_stock;
            // Guardamos los cambios
            return escribir_json(RUTA_PRODUCTOS, $productos);
        }
    }
    return false;
}

/**
 * Elimina un producto del catálogo.
 * Solo administradores pueden hacer esto.
 */
function eliminar_producto(int $id_producto): bool {
    $productos = leer_json(RUTA_PRODUCTOS);
    // Filtramos para remover el producto con el ID especificado
    $productos = array_filter($productos, function($p) use ($id_producto) {
        return (int)$p['id_producto'] !== $id_producto;
    });
    // Guardamos sin el producto eliminado
    return escribir_json(RUTA_PRODUCTOS, array_values($productos));
}

// ============================================================================
// FUNCIONES PARA GESTIONAR PEDIDOS
// ============================================================================

/**
 * Busca un pedido por su ID.
 */
function buscar_pedido_por_id(int $id): ?array {
    foreach (leer_json(RUTA_PEDIDOS) as $p) {
        if ((int)$p['id_pedido'] === $id) {
            return $p;
        }
    }
    return null;
}

/**
 * Cambia el estado de un pedido (pendiente -> aprobado -> rechazado).
 * Cuando se aprueba, automáticamente se genera una factura.
 */
function cambiar_estado_pedido(int $id_pedido, string $nuevo_estado): bool {
    $pedidos = leer_json(RUTA_PEDIDOS);
    $pedido_modificado = null;

    // Buscamos el pedido y cambiamos su estado
    foreach ($pedidos as &$p) {
        if ((int)$p['id_pedido'] === $id_pedido) {
            $p['estado'] = $nuevo_estado;
            // Si aprobamos el pedido, configuramos los datos de pago
            if ($nuevo_estado === 'aprobado') {
                $p['fecha_aprobacion'] = date('Y-m-d H:i:s');
                $p['pago_habilitado'] = true;
                $p['medio_pago'] = 'transferencia';
                $pedido_modificado = $p;
            }
            escribir_json(RUTA_PEDIDOS, $pedidos);
            // Si fue aprobado, generamos la factura automáticamente
            if ($nuevo_estado === 'aprobado' && $pedido_modificado !== null) {
                generar_factura($pedido_modificado);
            }
            return true;
        }
    }
    return false;
}

/**
 * Genera una factura cuando un pedido es aprobado.
 * La factura incluye IVA (21%) y el monto total a pagar.
 */
function generar_factura(array $pedido): bool {
    $facturas = leer_json(RUTA_FACTURAS);
    // Generamos un ID único para la factura
    $nuevo_id = empty($facturas) ? 1001 : max(array_column($facturas, 'id_factura')) + 1;
    // Buscamos los datos del cliente
    $usuario = buscar_usuario_por_id($pedido['id_usuario']);
    
    // Creamos la factura con todos los detalles
    $factura = [
        'id_factura' => $nuevo_id,
        'id_pedido' => (int)$pedido['id_pedido'],
        'id_usuario' => (int)$pedido['id_usuario'],
        'nombre_cliente' => $usuario['nombre'],
        'email_cliente' => $usuario['email'],
        'fecha_emision' => date('Y-m-d H:i:s'),
        'items' => $pedido['items'],
        'subtotal' => (float)$pedido['total_pagado'],
        'iva' => round((float)$pedido['total_pagado'] * 0.21, 2),  // 21% de IVA
        'total' => round((float)$pedido['total_pagado'] * 1.21, 2),  // Subtotal + IVA
        'estado_pago' => 'pendiente',  // Aún no ha pagado
    ];
    
    // Añadimos la factura a la lista
    $facturas[] = $factura;
    // Guardamos todas las facturas
    return escribir_json(RUTA_FACTURAS, $facturas);
}

/**
 * Busca una factura por su ID.
 */
function buscar_factura_por_id(int $id): ?array {
    foreach (leer_json(RUTA_FACTURAS) as $f) {
        if ((int)$f['id_factura'] === $id) {
            return $f;
        }
    }
    return null;
}

/**
 * Obtiene todas las facturas de un usuario.
 */
function obtener_facturas_usuario(int $id_usuario): array {
    $facturas = leer_json(RUTA_FACTURAS);
    return array_filter($facturas, function($f) use ($id_usuario) {
        return (int)$f['id_usuario'] === $id_usuario;
    });
}

/**
 * Obtiene todos los pedidos de un usuario.
 */
function obtener_pedidos_usuario(int $id_usuario): array {
    $pedidos = leer_json(RUTA_PEDIDOS);
    return array_filter($pedidos, function($p) use ($id_usuario) {
        return (int)$p['id_usuario'] === $id_usuario;
    });
}

/**
 * Obtiene todos los pedidos que están pendientes de aprobación.
 * Los administradores usan esta función para ver cuáles pedidos deben revisar.
 */
function obtener_pedidos_pendientes(): array {
    $pedidos = leer_json(RUTA_PEDIDOS);
    return array_filter($pedidos, function($p) {
        return ($p['estado'] ?? 'pendiente') === 'pendiente';
    });
}

// ============================================================================
// FUNCIONES AUXILIARES PARA MENSAJES
// ============================================================================

/**
 * Genera mensajes de alerta basados en los parámetros GET de la URL.
 * Se usa para mostrar mensajes de error o información al usuario después de redireccionamientos.
 */
function mensaje_get(): string {
    $motivo = $_GET['motivo'] ?? '';
    $error = $_GET['error'] ?? '';

    // Sesion expirada por inactividad
    if ($motivo === 'expirado') {
        return '<p class="alerta alerta-aviso">Tu sesión expiró por inactividad. Volvé a iniciar sesión.</p>';
    }
    // Usuario no logueado intenta acceder a sección protegida
    if ($motivo === 'acceso') {
        return '<p class="alerta alerta-aviso">Debés iniciar sesión para acceder a esa sección.</p>';
    }
    // Cuenta pendiente de aprobación
    if ($motivo === 'pendiente') {
        return '<p class="alerta alerta-aviso">Tu cuenta está pendiente de aprobación.</p>';
    }
    // Acceso denegado (sin permisos)
    if ($error === 'denegado') {
        return '<p class="alerta alerta-error">Acceso denegado. No tenés permisos para esa sección.</p>';
    }
    return '';
}
