<?php
// PÁGINA MIS PEDIDOS (MIS_PEDIDOS.PHP)

// Muestra al usuario el historial de todos sus pedidos.
// Para cada pedido se muestran:
// - Estado: pendiente, aprobado, rechazado
// - Productos ordenados con cantidades
// - Información de facturación si fue aprobado
// - Datos bancarios para realizar el pago


require_once __DIR__ . '/src/auth.php';

requerir_activo();

// Traer los pedidos del usuario
$pedidos_usuario = obtener_pedidos_usuario((int)$_SESSION['id_usuario']);
$facturas_usuario = obtener_facturas_usuario((int)$_SESSION['id_usuario']);

// Index rápido para buscar facturas
$facturas_por_pedido = [];
foreach ($facturas_usuario as $factura) {
    $facturas_por_pedido[(int)$factura['id_pedido']] = $factura;
}

$titulo_pagina = 'Mis Pedidos';
require_once __DIR__ . '/src/_header.php';
?>

<section class="mis-pedidos">
    <h1 class="seccion-titulo">Mis Pedidos</h1>
    <p class="bienvenida">Bienvenido, <strong><?= htmlspecialchars($_SESSION['nombre']) ?></strong></p>

    <?php if (empty($pedidos_usuario)): ?>
        <!-- Si el usuario no tiene pedidos, mostramos un mensaje -->
        <p class="sin-datos">No has realizado ningún pedido aún.</p>
        <p class="volver"><a href="/catalogo.php">Ir al catálogo</a></p>
    <?php else: ?>
        <!-- Mostramos cada pedido en una tarjeta -->
        <?php foreach ($pedidos_usuario as $pedido): ?>
            <?php 
            // Obtenemos el estado del pedido
            $estado = $pedido['estado'] ?? 'pendiente';
            // Buscamos la factura asociada al pedido (si existe)
            $factura = $facturas_por_pedido[(int)$pedido['id_pedido']] ?? null;
            // Verificamos si el pago está habilitado (solo cuando el admin aprueba)
            $pago_habilitado = $pedido['pago_habilitado'] ?? false;
            ?>
            
            <!-- Tarjeta del pedido con clase dinámica según el estado -->
            <article class="pedido-card <?= $estado ?>">
                <!-- Encabezado del pedido -->
                <div class="pedido-header">
                    <h2>Pedido #<?= (int)$pedido['id_pedido'] ?></h2>
                    <p class="pedido-fecha">Fecha: <?= htmlspecialchars($pedido['fecha_pedido']) ?></p>
                    <!-- Badge de estado del pedido -->
                    <span class="badge badge-<?= $estado ?>">
                        <?php if ($estado === 'pendiente'): ?>
                            Pendiente de aprobación
                        <?php elseif ($estado === 'aprobado'): ?>
                            Aprobado
                        <?php elseif ($estado === 'rechazado'): ?>
                            Rechazado
                        <?php else: ?>
                            <?= htmlspecialchars($estado) ?>
                        <?php endif; ?>
                    </span>
                </div>

                <!-- Lista de productos en el pedido -->
                <div class="pedido-items">
                    <h3>Productos:</h3>
                    <ul>
                        <?php foreach ($pedido['items'] as $item): ?>
                            <?php $producto = buscar_producto_por_id($item['id_producto']); ?>
                            <li>
                                <?= htmlspecialchars($producto['nombre']) ?> 
                                x <?= (int)$item['cantidad'] ?>
                                - $ <?= number_format((float)$item['precio_unitario'] * (int)$item['cantidad'], 2, ',', '.') ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="pedido-subtotal">Subtotal: $ <?= number_format((float)$pedido['total_pagado'], 2, ',', '.') ?></p>
                </div>

                <!-- Si el pedido está aprobado, mostramos la información de factura -->
                <?php if ($estado === 'aprobado' && $factura): ?>
                    <div class="factura-info">
                        <h3>Factura #<?= (int)$factura['id_factura'] ?></h3>
                        <p>Fecha de emisión: <?= htmlspecialchars($factura['fecha_emision']) ?></p>
                        <p>IVA (21%): $ <?= number_format((float)$factura['iva'], 2, ',', '.') ?></p>
                        <p class="factura-total">Total a pagar: $ <?= number_format((float)$factura['total'], 2, ',', '.') ?></p>
                        <p>Estado de pago: 
                            <strong class="estado-pago <?= $factura['estado_pago'] ?>">
                                <?= $factura['estado_pago'] === 'pendiente' ? 'Pendiente' : 'Pagado' ?>
                            </strong>
                        </p>
                    </div>
                <?php endif; ?>

                <!-- Si el pago está habilitado, mostramos los datos bancarios para transferencia -->
                <?php if ($estado === 'aprobado' && $pago_habilitado): ?>
                    <div class="pago-section">
                        <h3>Método de pago habilitado</h3>
                        <p class="medio-pago">
                            Transferencia bancaria
                        </p>
                        <!-- Datos de la cuenta bancaria para efectuar el pago -->
                        <div class="datos-bancarios">
                            <p><strong>CBU:</strong> 0000003100000000000000</p>
                            <p><strong>Alias:</strong> ESHOP.PAGOS</p>
                            <p><strong>Titular:</strong> ESHOP Argentina S.A.</p>
                            <p><strong>CUIT:</strong> 30-12345678-9</p>
                        </div>
                        <p class="pago-aviso">
                            Una vez realizado el pago, envía el comprobante a admin@eshop.com
                        </p>
                    </div>
                <!-- Si aún está pendiente, mostramos un aviso de espera -->
                <?php elseif ($estado === 'pendiente'): ?>
                    <div class="pedido-aviso">
                        <p> Tu pedido está siendo revisado por el administrador.</p>
                        <p>Recibirás una notificación cuando sea aprobado y se habilite el medio de pago.</p>
                    </div>
                <!-- Si fue rechazado, mostramos un aviso de error -->
                <?php elseif ($estado === 'rechazado'): ?>
                    <div class="pedido-aviso aviso-error">
                        <p>Tu pedido ha sido rechazado.</p>
                        <p>Por favor, contacta al administrador para más información.</p>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>

        <p class="volver"><a href="/catalogo.php">← Volver al catálogo</a></p>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/src/_footer.php'; ?>
