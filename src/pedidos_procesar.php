<?php
// ============================================================================
// PROCESADOR DE PEDIDOS (PEDIDOS_PROCESAR.PHP)
// ============================================================================
// Script que recibe el formulario de compra desde el catálogo.
// Valida que el usuario tenga stock suficiente de cada producto.
// Decrementa el stock después de validar.
// Crea un nuevo pedido en estado 'pendiente' esperando aprobación del admin.
// Registra el evento en el log de auditoría.
// ============================================================================

require_once __DIR__ . '/auth.php';

// Solo usuarios con cuenta aprobada pueden realizar pedidos
requerir_activo();

// Solo aceptamos peticiones POST (envío de formulario)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /catalogo.php');
    exit();
}

// Leemos los datos actuales de productos y pedidos desde los archivos JSON
$productos_json = leer_json(RUTA_PRODUCTOS);
$pedidos        = leer_json(RUTA_PEDIDOS);

// Creamos un índice de productos para búsqueda rápida por ID
$indice_productos = [];
foreach ($productos_json as $idx => $prod) {
    $indice_productos[(int)$prod['id_producto']] = $idx;
}

// Variables para almacenar los items del pedido y validaciones
$items_pedido = [];
$total        = 0.0;
$errores      = [];

// Obtenemos las cantidades del formulario POST
$cantidades = $_POST['cantidad'] ?? [];

// Validamos que se hayan seleccionado productos
if (!is_array($cantidades) || empty($cantidades)) {
    header('Location: /catalogo.php?pedido=vacio');
    exit();
}

// Procesamos cada producto seleccionado
foreach ($cantidades as $id_str => $cant_str) {
    $id_prod  = (int)$id_str;
    $cantidad = (int)$cant_str;

    // Ignoramos productos con cantidad 0 o negativa
    if ($cantidad <= 0) {
        continue;
    }

    // Verificamos que el producto existe en la base de datos
    if (!isset($indice_productos[$id_prod])) {
        $errores[] = "Producto ID {$id_prod} no encontrado.";
        continue;
    }

    // Obtenemos los datos del producto
    $idx_real = $indice_productos[$id_prod];
    $producto = $productos_json[$idx_real];

    // Validamos el stock EN EL SERVIDOR (esto es seguridad, no confiamos en el cliente)
    if ($cantidad > (int)$producto['stock']) {
        $errores[] = "Stock insuficiente para \"{$producto['nombre']}\". Disponible: {$producto['stock']}.";
        continue;
    }

    // Calculamos el subtotal de este producto
    $subtotal = $cantidad * (float)$producto['precio'];
    $total   += $subtotal;

    // Agregamos el item al pedido
    $items_pedido[] = [
        'id_producto'    => $id_prod,
        'cantidad'       => $cantidad,
        'precio_unitario'=> (float)$producto['precio'],
    ];

    // Decrementamos el stock (esto es muy importante!)
    $productos_json[$idx_real]['stock'] -= $cantidad;
}

// Si hubo errores de validación, redirigimos sin guardar nada
if (!empty($errores)) {
    $msg = urlencode(implode(' | ', $errores));
    header("Location: /catalogo.php?pedido=error&detalle={$msg}");
    exit();
}

// Si no hay items en el pedido, lo consideramos vacío
if (empty($items_pedido)) {
    header('Location: /catalogo.php?pedido=vacio');
    exit();
}

// Generamos un ID único para el nuevo pedido
// Si no hay pedidos, comenzamos en 5001; sino usamos el ID máximo + 1
$nuevo_id = empty($pedidos)
    ? 5001
    : max(array_column($pedidos, 'id_pedido')) + 1;

// Creamos el nuevo pedido con todos los datos
$nuevo_pedido = [
    'id_pedido'        => $nuevo_id,
    'id_usuario'       => (int)$_SESSION['id_usuario'],
    'fecha_pedido'     => date('Y-m-d H:i:s'),
    'estado'           => 'pendiente',  // Espera aprobación del admin
    'items'            => $items_pedido,
    'total_pagado'     => round($total, 2),
    'pago_habilitado'  => false,  // Se habilita cuando el admin lo aprueba
];

// Agregamos el nuevo pedido a la lista
$pedidos[] = $nuevo_pedido;

// Guardamos los cambios en los archivos JSON
escribir_json(RUTA_PEDIDOS,   $pedidos);
escribir_json(RUTA_PRODUCTOS, $productos_json);

// Registramos el evento en el log de auditoría
registrar_log(
    'PEDIDO_CREADO',
    "Usuario '{$_SESSION['username']}' realizó el pedido #{$nuevo_id} por $" . number_format($total, 2, '.', ',')
);

header('Location: /catalogo.php?pedido=pendiente&numero=' . $nuevo_id);
exit();
