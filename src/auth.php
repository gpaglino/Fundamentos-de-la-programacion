<?php
/**
 * auth.php — Funciones de autenticación, sesión, roles y auditoría.
 * Centraliza toda la lógica de acceso para evitar duplicación entre páginas.
 */

// ── Constantes de rutas ──────────────────────────────────────────────────────
define('RUTA_USUARIOS',  __DIR__ . '/../config/usuarios.json');
define('RUTA_PEDIDOS',   __DIR__ . '/../config/pedidos.json');
define('RUTA_PRODUCTOS', __DIR__ . '/../config/productos.json');
define('RUTA_FACTURAS',  __DIR__ . '/../config/facturas.json');
define('RUTA_LOG',       __DIR__ . '/../logs/auditoria.log');
define('TIMEOUT_SESION', 300); // 5 minutos en segundos

// ── Inicio y control de sesión ───────────────────────────────────────────────

/**
 * Arranca la sesión con configuración segura y verifica inactividad.
 * Debe llamarse al inicio de cada página protegida.
 */
function iniciar_sesion_segura(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }

    // Verificar expiración por inactividad
    if (isset($_SESSION['ultima_actividad'])) {
        $inactivo = time() - $_SESSION['ultima_actividad'];
        if ($inactivo > TIMEOUT_SESION) {
            session_unset();
            session_destroy();
            header('Location: /login.php?motivo=expirado');
            exit();
        }
    }
    $_SESSION['ultima_actividad'] = time();
}

/**
 * Devuelve true si hay una sesión activa con usuario logueado.
 */
function sesion_activa(): bool {
    return session_status() !== PHP_SESSION_NONE
        && isset($_SESSION['id_usuario'], $_SESSION['rol']);
}

/**
 * Redirige al login si no hay sesión activa.
 */
function requerir_login(): void {
    iniciar_sesion_segura();
    if (!sesion_activa()) {
        header('Location: /login.php?motivo=acceso');
        exit();
    }
}

/**
 * Redirige con acceso denegado si el usuario no es admin.
 */
function requerir_admin(): void {
    requerir_login();
    if ($_SESSION['rol'] !== 'admin') {
        header('Location: /index.php?error=denegado');
        exit();
    }
}

/**
 * Redirige si el usuario no está dado de alta (activo).
 */
function requerir_activo(): void {
    requerir_login();
    if ($_SESSION['estado'] !== 'activo') {
        header('Location: /login.php?motivo=pendiente');
        exit();
    }
}

// ── Manejo de JSON ───────────────────────────────────────────────────────────

/**
 * Lee un archivo JSON y retorna su contenido como array asociativo.
 * Devuelve array vacío si el archivo no existe o está malformado.
 */
function leer_json(string $ruta): array {
    if (!is_readable($ruta)) {
        return [];
    }
    $contenido = file_get_contents($ruta);
    $datos = json_decode($contenido, true);
    return is_array($datos) ? $datos : [];
}

/**
 * Escribe un array como JSON en el archivo indicado.
 * Retorna true en éxito, false en error.
 */
function escribir_json(string $ruta, array $datos): bool {
    $json = json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }
    return file_put_contents($ruta, $json, LOCK_EX) !== false;
}

// ── Gestión de usuarios ──────────────────────────────────────────────────────

/**
 * Busca un usuario por su username. Retorna el array del usuario o null.
 */
function buscar_usuario_por_username(string $username): ?array {
    foreach (leer_json(RUTA_USUARIOS) as $u) {
        if ($u['username'] === $username) {
            return $u;
        }
    }
    return null;
}

/**
 * Busca un usuario por su ID. Retorna el array del usuario o null.
 */
function buscar_usuario_por_id(int $id): ?array {
    foreach (leer_json(RUTA_USUARIOS) as $u) {
        if ((int)$u['id_usuario'] === $id) {
            return $u;
        }
    }
    return null;
}

/**
 * Genera el próximo ID de usuario (máximo actual + 1).
 */
function proximo_id_usuario(): int {
    $usuarios = leer_json(RUTA_USUARIOS);
    if (empty($usuarios)) {
        return 1;
    }
    return max(array_column($usuarios, 'id_usuario')) + 1;
}

/**
 * Registra un nuevo usuario con estado "pendiente".
 * Retorna true si se guardó correctamente.
 */
function registrar_usuario(string $username, string $nombre, string $email, string $password): bool {
    $usuarios = leer_json(RUTA_USUARIOS);

    $nuevo = [
        'id_usuario'      => proximo_id_usuario(),
        'username'        => htmlspecialchars($username, ENT_QUOTES, 'UTF-8'),
        'nombre'          => htmlspecialchars($nombre,   ENT_QUOTES, 'UTF-8'),
        'email'           => filter_var($email, FILTER_SANITIZE_EMAIL),
        'password_hash'   => password_hash($password, PASSWORD_BCRYPT),
        'rol'             => 'usuario',
        'estado'          => 'pendiente',
        'fecha_registro'  => date('Y-m-d H:i:s'),
    ];

    $usuarios[] = $nuevo;
    return escribir_json(RUTA_USUARIOS, $usuarios);
}

/**
 * Cambia el estado de un usuario a "activo" por su ID.
 * Retorna el nombre del usuario aprobado, o null si no se encontró.
 */
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

// ── Autenticación ────────────────────────────────────────────────────────────

/**
 * Intenta hacer login. Registra el evento en auditoría.
 * Retorna array con ['ok' => bool, 'mensaje' => string].
 */
function intentar_login(string $username, string $password): array {
    $usuario = buscar_usuario_por_username($username);

    if ($usuario === null || !password_verify($password, $usuario['password_hash'])) {
        registrar_log('LOGIN_FALLIDO', "Intento fallido para el usuario: '{$username}'");
        return ['ok' => false, 'mensaje' => 'Usuario o contraseña incorrectos.'];
    }

    if ($usuario['estado'] === 'pendiente') {
        registrar_log('LOGIN_PENDIENTE', "Usuario '{$username}' intentó ingresar con cuenta pendiente");
        return ['ok' => false, 'mensaje' => 'Tu cuenta está pendiente de aprobación por el administrador.'];
    }

    // Regenerar ID de sesión para prevenir fijación
    session_regenerate_id(true);

    $_SESSION['id_usuario']       = $usuario['id_usuario'];
    $_SESSION['username']         = $usuario['username'];
    $_SESSION['nombre']           = $usuario['nombre'];
    $_SESSION['rol']              = $usuario['rol'];
    $_SESSION['estado']           = $usuario['estado'];
    $_SESSION['ultima_actividad'] = time();

    registrar_log('LOGIN_EXITOSO', "Usuario '{$username}' inició sesión correctamente");
    return ['ok' => true, 'mensaje' => 'Bienvenido, ' . $usuario['nombre']];
}

/**
 * Cierra la sesión actual de forma segura.
 */
function cerrar_sesion(): void {
    $username = $_SESSION['username'] ?? 'desconocido';
    session_unset();
    session_destroy();
    registrar_log('LOGOUT', "Usuario '{$username}' cerró sesión");
}

// ── Sistema de Logs ──────────────────────────────────────────────────────────

/**
 * Escribe una línea en el archivo de auditoría.
 * Formato: [AAAA-MM-DD HH:MM:SS] [EVENTO] Mensaje detallado.
 */
function registrar_log(string $evento, string $mensaje): void {
    $linea = sprintf(
        "[%s] [%s] %s\n",
        date('Y-m-d H:i:s'),
        strtoupper($evento),
        $mensaje
    );
    file_put_contents(RUTA_LOG, $linea, FILE_APPEND | LOCK_EX);
}

// Productos
function buscar_producto_por_id(int $id): ?array {
    foreach (leer_json(RUTA_PRODUCTOS) as $p) {
        if ((int)$p['id_producto'] === $id) {
            return $p;
        }
    }
    return null;
}

function proximo_id_producto(): int {
    $productos = leer_json(RUTA_PRODUCTOS);
    if (empty($productos)) {
        return 101;
    }
    return max(array_column($productos, 'id_producto')) + 1;
}

function agregar_producto(string $nombre, string $categoria, float $precio, int $stock): bool {
    $productos = leer_json(RUTA_PRODUCTOS);
    $nuevo = [
        'id_producto' => proximo_id_producto(),
        'nombre' => htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'),
        'categoria' => htmlspecialchars($categoria, ENT_QUOTES, 'UTF-8'),
        'precio' => (float)$precio,
        'stock' => (int)$stock,
    ];
    $productos[] = $nuevo;
    return escribir_json(RUTA_PRODUCTOS, $productos);
}

function actualizar_stock_producto(int $id_producto, int $nuevo_stock): bool {
    $productos = leer_json(RUTA_PRODUCTOS);
    foreach ($productos as &$p) {
        if ((int)$p['id_producto'] === $id_producto) {
            $p['stock'] = (int)$nuevo_stock;
            return escribir_json(RUTA_PRODUCTOS, $productos);
        }
    }
    return false;
}

function eliminar_producto(int $id_producto): bool {
    $productos = leer_json(RUTA_PRODUCTOS);
    $productos = array_filter($productos, function($p) use ($id_producto) {
        return (int)$p['id_producto'] !== $id_producto;
    });
    return escribir_json(RUTA_PRODUCTOS, array_values($productos));
}

// Pedidos
function buscar_pedido_por_id(int $id): ?array {
    foreach (leer_json(RUTA_PEDIDOS) as $p) {
        if ((int)$p['id_pedido'] === $id) {
            return $p;
        }
    }
    return null;
}

function cambiar_estado_pedido(int $id_pedido, string $nuevo_estado): bool {
    $pedidos = leer_json(RUTA_PEDIDOS);
    $pedido_modificado = null;

    foreach ($pedidos as &$p) {
        if ((int)$p['id_pedido'] === $id_pedido) {
            $p['estado'] = $nuevo_estado;
            if ($nuevo_estado === 'aprobado') {
                $p['fecha_aprobacion'] = date('Y-m-d H:i:s');
                $p['pago_habilitado'] = true;
                $p['medio_pago'] = 'transferencia';
                $pedido_modificado = $p;
            }
            escribir_json(RUTA_PEDIDOS, $pedidos);
            if ($nuevo_estado === 'aprobado' && $pedido_modificado !== null) {
                generar_factura($pedido_modificado);
            }
            return true;
        }
    }
    return false;
}

// Facturas
function generar_factura(array $pedido): bool {
    $facturas = leer_json(RUTA_FACTURAS);
    $nuevo_id = empty($facturas) ? 1001 : max(array_column($facturas, 'id_factura')) + 1;
    $usuario = buscar_usuario_por_id($pedido['id_usuario']);
    
    $factura = [
        'id_factura' => $nuevo_id,
        'id_pedido' => (int)$pedido['id_pedido'],
        'id_usuario' => (int)$pedido['id_usuario'],
        'nombre_cliente' => $usuario['nombre'],
        'email_cliente' => $usuario['email'],
        'fecha_emision' => date('Y-m-d H:i:s'),
        'items' => $pedido['items'],
        'subtotal' => (float)$pedido['total_pagado'],
        'iva' => round((float)$pedido['total_pagado'] * 0.21, 2),
        'total' => round((float)$pedido['total_pagado'] * 1.21, 2),
        'estado_pago' => 'pendiente',
    ];
    
    $facturas[] = $factura;
    return escribir_json(RUTA_FACTURAS, $facturas);
}

function buscar_factura_por_id(int $id): ?array {
    foreach (leer_json(RUTA_FACTURAS) as $f) {
        if ((int)$f['id_factura'] === $id) {
            return $f;
        }
    }
    return null;
}

function obtener_facturas_usuario(int $id_usuario): array {
    $facturas = leer_json(RUTA_FACTURAS);
    return array_filter($facturas, function($f) use ($id_usuario) {
        return (int)$f['id_usuario'] === $id_usuario;
    });
}

function obtener_pedidos_usuario(int $id_usuario): array {
    $pedidos = leer_json(RUTA_PEDIDOS);
    return array_filter($pedidos, function($p) use ($id_usuario) {
        return (int)$p['id_usuario'] === $id_usuario;
    });
}

function obtener_pedidos_pendientes(): array {
    $pedidos = leer_json(RUTA_PEDIDOS);
    return array_filter($pedidos, function($p) {
        return ($p['estado'] ?? 'pendiente') === 'pendiente';
    });
}

function mensaje_get(): string {
    $motivo = $_GET['motivo'] ?? '';
    $error = $_GET['error'] ?? '';

    if ($motivo === 'expirado') {
        return '<p class="alerta alerta-aviso">Tu sesión expiró por inactividad. Volvé a iniciar sesión.</p>';
    }
    if ($motivo === 'acceso') {
        return '<p class="alerta alerta-aviso">Debés iniciar sesión para acceder a esa sección.</p>';
    }
    if ($motivo === 'pendiente') {
        return '<p class="alerta alerta-aviso">Tu cuenta está pendiente de aprobación.</p>';
    }
    if ($error === 'denegado') {
        return '<p class="alerta alerta-error">Acceso denegado. No tenés permisos para esa sección.</p>';
    }
    return '';
}
