<?php
require_once __DIR__ . '/src/auth.php';

requerir_activo();

$es_admin = $_SESSION['rol'] === 'admin';

// Leer catálogo y agrupar por categoría
$todos_productos = leer_json(RUTA_PRODUCTOS);

$por_categoria = [];
foreach ($todos_productos as $producto) {
    $cat = $producto['categoria'];
    if (!isset($por_categoria[$cat])) {
        $por_categoria[$cat] = [];
    }
    $por_categoria[$cat][] = $producto;
}
ksort($por_categoria); // ordenar categorías alfabéticamente

// Mensajes de resultado de pedido previo
$mensaje_pedido = '';
$tipo_pedido    = '';

$resultado_get = $_GET['pedido'] ?? '';

if ($resultado_get === 'ok') {
    $num = (int)($_GET['numero'] ?? 0);
    $mensaje_pedido = "Pedido #{$num} registrado con éxito. ¡Gracias por tu compra!";
    $tipo_pedido = 'exito';
} elseif ($resultado_get === 'pendiente') {
    $num = (int)($_GET['numero'] ?? 0);
    $mensaje_pedido = "Pedido #{$num} registrado exitosamente. Aguardá la aprobación del administrador para proceder con el pago.";
    $tipo_pedido = 'aviso';
} elseif ($resultado_get === 'vacio') {
    $mensaje_pedido = 'No seleccionaste ningún producto o las cantidades eran cero.';
    $tipo_pedido = 'aviso';
} elseif ($resultado_get === 'error') {
    $detalle = htmlspecialchars(urldecode($_GET['detalle'] ?? 'Error desconocido'));
    $mensaje_pedido = "Error al procesar el pedido: {$detalle}";
    $tipo_pedido = 'error';
}

$titulo_pagina = 'Catálogo';
require_once __DIR__ . '/src/_header.php';
?>

<section class="catalogo">
    <h1 class="seccion-titulo">Catálogo de Productos</h1>

    <?php if ($mensaje_pedido): ?>
        <p class="alerta alerta-<?= $tipo_pedido ?>"><?= $mensaje_pedido ?></p>
    <?php endif; ?>

    <?php if (empty($todos_productos)): ?>
        <p class="sin-datos">No hay productos disponibles en este momento.</p>
    <?php else: ?>

    <?php if (!$es_admin): ?>
    <form method="POST" action="/src/pedidos_procesar.php" id="form-pedido">
    <?php endif; ?>

        <?php foreach ($por_categoria as $categoria => $productos): ?>
        <section class="categoria-seccion">
            <h2 class="categoria-titulo"><?= htmlspecialchars($categoria) ?></h2>

            <div class="productos-grilla">
                <?php foreach ($productos as $p): ?>
                <article class="tarjeta-producto <?= (int)$p['stock'] === 0 ? 'sin-stock' : '' ?>">
                    <h3 class="producto-nombre"><?= htmlspecialchars($p['nombre']) ?></h3>
                    <p class="producto-precio">$ <?= number_format((float)$p['precio'], 2, ',', '.') ?></p>
                    <p class="producto-stock">
                        <?php if ((int)$p['stock'] > 0): ?>
                            <span class="stock-ok">Stock disponible: <?= (int)$p['stock'] ?></span>
                        <?php else: ?>
                            <span class="stock-agotado">Sin stock</span>
                        <?php endif; ?>
                    </p>

                    <?php if (!$es_admin && (int)$p['stock'] > 0): ?>
                    <div class="campo campo-cantidad">
                        <label for="cant_<?= (int)$p['id_producto'] ?>">Cantidad:</label>
                        <input type="number"
                               id="cant_<?= (int)$p['id_producto'] ?>"
                               name="cantidad[<?= (int)$p['id_producto'] ?>]"
                               min="0"
                               max="<?= (int)$p['stock'] ?>"
                               value="0"
                               class="input-cantidad">
                    </div>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endforeach; ?>

        <?php if (!$es_admin): ?>
        <div class="pedido-footer">
            <p class="pedido-aviso">Revisá las cantidades antes de confirmar. El stock se descontará al confirmar.</p>
            <button type="submit" class="btn btn-primario btn-grande"
                    onclick="return validarPedido()">
                Confirmar pedido
            </button>
        </div>
    </form>
        <?php endif; ?>

    <?php endif; ?>
</section>

<script>
/**
 * Validación del lado del cliente: verifica que al menos un producto
 * tenga cantidad mayor a 0 antes de enviar el formulario.
 */
function validarPedido() {
    const inputs = document.querySelectorAll('.input-cantidad');
    let total = 0;
    inputs.forEach(function(inp) {
        total += parseInt(inp.value, 10) || 0;
    });
    if (total === 0) {
        alert('Seleccioná al menos un producto con cantidad mayor a 0.');
        return false;
    }
    return confirm('¿Confirmar el pedido con los productos seleccionados?');
}
</script>

<?php require_once __DIR__ . '/src/_footer.php'; ?>
