<?php
require_once __DIR__ . '/auth.php';
requerir_activo();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /catalogo.php');
    exit();
}

$productos_json = leer_json(RUTA_PRODUCTOS);
$pedidos        = leer_json(RUTA_PEDIDOS);

// Construir índice de productos por ID para acceso O(1)
$indice_productos = [];
foreach ($productos_json as $idx => $prod) {
    $indice_productos[(int)$prod['id_producto']] = $idx;
}

// Leer y validar cantidades enviadas
$items_pedido = [];
$total        = 0.0;
$errores      = [];

$cantidades = $_POST['cantidad'] ?? [];

if (!is_array($cantidades) || empty($cantidades)) {
    header('Location: /catalogo.php?pedido=vacio');
    exit();
}

foreach ($cantidades as $id_str => $cant_str) {
    $id_prod  = (int)$id_str;
    $cantidad = (int)$cant_str;

    // Ignorar productos con cantidad 0 o negativa
    if ($cantidad <= 0) {
        continue;
    }

    // Verificar que el producto existe en el índice
    if (!isset($indice_productos[$id_prod])) {
        $errores[] = "Producto ID {$id_prod} no encontrado.";
        continue;
    }

    $idx_real = $indice_productos[$id_prod];
    $producto = $productos_json[$idx_real];

    // Validación de stock en servidor (seguridad real)
    if ($cantidad > (int)$producto['stock']) {
        $errores[] = "Stock insuficiente para \"{$producto['nombre']}\". Disponible: {$producto['stock']}.";
        continue;
    }

    $subtotal = $cantidad * (float)$producto['precio'];
    $total   += $subtotal;

    $items_pedido[] = [
        'id_producto'    => $id_prod,
        'cantidad'       => $cantidad,
        'precio_unitario'=> (float)$producto['precio'],
    ];

    // Descontar del stock
    $productos_json[$idx_real]['stock'] -= $cantidad;
}

// Si hubo errores de validación, redirigir sin guardar
if (!empty($errores)) {
    $msg = urlencode(implode(' | ', $errores));
    header("Location: /catalogo.php?pedido=error&detalle={$msg}");
    exit();
}

if (empty($items_pedido)) {
    header('Location: /catalogo.php?pedido=vacio');
    exit();
}

// Generar ID de pedido
$nuevo_id = empty($pedidos)
    ? 5001
    : max(array_column($pedidos, 'id_pedido')) + 1;

$nuevo_pedido = [
    'id_pedido'        => $nuevo_id,
    'id_usuario'       => (int)$_SESSION['id_usuario'],
    'fecha_pedido'     => date('Y-m-d H:i:s'),
    'estado'           => 'pendiente',
    'items'            => $items_pedido,
    'total_pagado'     => round($total, 2),
    'pago_habilitado'  => false,
];

$pedidos[] = $nuevo_pedido;

// Persistir cambios
escribir_json(RUTA_PEDIDOS,   $pedidos);
escribir_json(RUTA_PRODUCTOS, $productos_json);

registrar_log(
    'PEDIDO_CREADO',
    "Usuario '{$_SESSION['username']}' realizó el pedido #{$nuevo_id} por $" . number_format($total, 2, '.', ',')
);

header('Location: /catalogo.php?pedido=pendiente&numero=' . $nuevo_id);
exit();
