<?php
require_once __DIR__ . '/src/auth.php';
requerir_admin();

$mensaje = '';
$tipo_alerta = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['aprobar_id'])) {
        $id_aprobar = (int)$_POST['aprobar_id'];
        if ($id_aprobar === (int)$_SESSION['id_usuario']) {
            $mensaje = 'No podés modificar tu propio estado.';
            $tipo_alerta = 'error';
        } else {
            $nombre_aprobado = aprobar_usuario($id_aprobar);
            if ($nombre_aprobado !== null) {
                registrar_log('USUARIO_APROBADO', "Admin '{$_SESSION['username']}' aprobó a '{$nombre_aprobado}' (ID: {$id_aprobar})");
                $mensaje = "Usuario \"{$nombre_aprobado}\" aprobado exitosamente.";
                $tipo_alerta = 'exito';
            } else {
                $mensaje = 'No se encontró el usuario o ya estaba activo.';
                $tipo_alerta = 'error';
            }
        }
    }
    
    if (isset($_POST['accion'])) {
        if ($_POST['accion'] === 'agregar_producto') {
            $nombre = trim($_POST['nombre'] ?? '');
            $categoria = trim($_POST['categoria'] ?? '');
            $precio = (float)($_POST['precio'] ?? 0);
            $stock = (int)($_POST['stock'] ?? 0);
            
            if ($nombre === '' || $categoria === '' || $precio <= 0 || $stock < 0) {
                $mensaje = 'Completá todos los campos correctamente.';
                $tipo_alerta = 'error';
            } else {
                $agregado = agregar_producto($nombre, $categoria, $precio, $stock);
                if ($agregado) {
                    registrar_log('PRODUCTO_AGREGADO', "Admin '{$_SESSION['username']}' agregó producto '{$nombre}'");
                    $mensaje = "Producto \"{$nombre}\" agregado exitosamente.";
                    $tipo_alerta = 'exito';
                } else {
                    $mensaje = 'Error al agregar el producto.';
                    $tipo_alerta = 'error';
                }
            }
        }
        
        if ($_POST['accion'] === 'actualizar_stock') {
            $id_producto = (int)$_POST['id_producto'];
            $nuevo_stock = (int)$_POST['nuevo_stock'];
            
            if ($nuevo_stock < 0) {
                $mensaje = 'El stock no puede ser negativo.';
                $tipo_alerta = 'error';
            } else {
                $actualizado = actualizar_stock_producto($id_producto, $nuevo_stock);
                if ($actualizado) {
                    $producto = buscar_producto_por_id($id_producto);
                    registrar_log('STOCK_ACTUALIZADO', "Admin '{$_SESSION['username']}' actualizó stock de '{$producto['nombre']}'");
                    $mensaje = 'Stock actualizado exitosamente.';
                    $tipo_alerta = 'exito';
                } else {
                    $mensaje = 'Error al actualizar el stock.';
                    $tipo_alerta = 'error';
                }
            }
        }
        
        if ($_POST['accion'] === 'eliminar_producto') {
            $id_producto = (int)$_POST['id_producto'];
            $eliminado = eliminar_producto($id_producto);
            if ($eliminado) {
                registrar_log('PRODUCTO_ELIMINADO', "Admin '{$_SESSION['username']}' eliminó producto ID {$id_producto}");
                $mensaje = 'Producto eliminado exitosamente.';
                $tipo_alerta = 'exito';
            } else {
                $mensaje = 'Error al eliminar el producto.';
                $tipo_alerta = 'error';
            }
        }
        
        if ($_POST['accion'] === 'procesar_pedido') {
            $id_pedido = (int)$_POST['id_pedido'];
            $decision = $_POST['decision'] ?? '';
            
            if ($decision === 'aprobar') {
                $actualizado = cambiar_estado_pedido($id_pedido, 'aprobado');
                if ($actualizado) {
                    $pedido = buscar_pedido_por_id($id_pedido);
                    $usuario = buscar_usuario_por_id($pedido['id_usuario']);
                    registrar_log('PEDIDO_APROBADO', "Admin '{$_SESSION['username']}' aprobó pedido #{$id_pedido}");
                    $mensaje = "Pedido #{$id_pedido} aprobado. Factura generada y pago habilitado.";
                    $tipo_alerta = 'exito';
                } else {
                    $mensaje = 'Error al aprobar el pedido.';
                    $tipo_alerta = 'error';
                }
            } elseif ($decision === 'rechazar') {
                $actualizado = cambiar_estado_pedido($id_pedido, 'rechazado');
                if ($actualizado) {
                    $pedido = buscar_pedido_por_id($id_pedido);
                    $usuario = buscar_usuario_por_id($pedido['id_usuario']);
                    registrar_log('PEDIDO_RECHAZADO', "Admin '{$_SESSION['username']}' rechazó pedido #{$id_pedido}");
                    $mensaje = "Pedido #{$id_pedido} rechazado.";
                    $tipo_alerta = 'exito';
                } else {
                    $mensaje = 'Error al rechazar el pedido.';
                    $tipo_alerta = 'error';
                }
            }
        }
        
        if ($_POST['accion'] === 'eliminar_usuario') {
            $id_usuario = (int)$_POST['id_usuario'];
            
            if ($id_usuario === (int)$_SESSION['id_usuario']) {
                $mensaje = 'No podés eliminar tu propio usuario.';
                $tipo_alerta = 'error';
            } else {
                $eliminado = eliminar_usuario($id_usuario);
                if ($eliminado) {
                    registrar_log('USUARIO_ELIMINADO', "Admin '{$_SESSION['username']}' eliminó usuario ID {$id_usuario}");
                    $mensaje = 'Usuario eliminado exitosamente.';
                    $tipo_alerta = 'exito';
                } else {
                    $mensaje = 'Error al eliminar el usuario.';
                    $tipo_alerta = 'error';
                }
            }
        }
    }
}

$todos_usuarios = leer_json(RUTA_USUARIOS);
$pendientes = [];
$activos = [];

foreach ($todos_usuarios as $u) {
    if ($u['estado'] === 'pendiente') {
        $pendientes[] = $u;
    } elseif ((int)$u['id_usuario'] !== (int)$_SESSION['id_usuario']) {
        $activos[] = $u;
    }
}

$todos_productos = leer_json(RUTA_PRODUCTOS);
$pedidos_pendientes = obtener_pedidos_pendientes();

$titulo_pagina = 'Panel Administrador';
require_once __DIR__ . '/src/_header.php';
?>

<section class="admin-panel">
    <h1 class="seccion-titulo">Panel de Administración</h1>
    <p class="admin-bienvenida">Sesión activa: <strong><?= htmlspecialchars($_SESSION['nombre']) ?></strong></p>

    <?php if ($mensaje): ?>
        <p class="alerta alerta-<?= $tipo_alerta ?>"><?= htmlspecialchars($mensaje) ?></p>
    <?php endif; ?>

    <article class="admin-seccion">
        <h2 class="admin-subtitulo">Postulaciones pendientes (<?= count($pendientes) ?>)</h2>

        <?php if (empty($pendientes)): ?>
            <p class="sin-datos">No hay usuarios pendientes de aprobación.</p>
        <?php else: ?>
        <table class="tabla-datos">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Usuario</th>
                    <th>Email</th>
                    <th>Fecha registro</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pendientes as $u): ?>
                <tr>
                    <td><?= (int)$u['id_usuario'] ?></td>
                    <td><?= htmlspecialchars($u['nombre']) ?></td>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= htmlspecialchars($u['fecha_registro']) ?></td>
                    <td>
                        <form method="POST" action="/admin_panel.php" onsubmit="return confirm('¿Aprobar a <?= htmlspecialchars($u['nombre'], ENT_QUOTES) ?>?')">
                            <input type="hidden" name="aprobar_id" value="<?= (int)$u['id_usuario'] ?>">
                            <button type="submit" class="btn btn-aprobar">Dar de alta</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </article>

    <article class="admin-seccion">
        <h2 class="admin-subtitulo">Usuarios activos (<?= count($activos) ?>)</h2>

        <?php if (empty($activos)): ?>
            <p class="sin-datos">No hay otros usuarios activos aún.</p>
        <?php else: ?>
        <table class="tabla-datos">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Usuario</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Fecha registro</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($activos as $u): ?>
                <tr>
                    <td><?= (int)$u['id_usuario'] ?></td>
                    <td><?= htmlspecialchars($u['nombre']) ?></td>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><span class="badge badge-<?= $u['rol'] ?>"><?= htmlspecialchars($u['rol']) ?></span></td>
                    <td><?= htmlspecialchars($u['fecha_registro']) ?></td>
                    <td>
                        <form method="POST" action="/admin_panel.php" onsubmit="return confirm('¿Eliminar usuario <?= htmlspecialchars($u['nombre'], ENT_QUOTES) ?>?')">
                            <input type="hidden" name="accion" value="eliminar_usuario">
                            <input type="hidden" name="id_usuario" value="<?= (int)$u['id_usuario'] ?>">
                            <button type="submit" class="btn btn-chico btn-eliminar">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </article>

    <article class="admin-seccion">
        <h2 class="admin-subtitulo">Gestión de Productos (<?= count($todos_productos) ?>)</h2>

        <div class="producto-formulario">
            <h3>Agregar nuevo producto</h3>
            <form method="POST" action="/admin_panel.php" class="formulario-inline">
                <input type="hidden" name="accion" value="agregar_producto">
                <input type="text" name="nombre" placeholder="Nombre del producto" required>
                <input type="text" name="categoria" placeholder="Categoría" required>
                <input type="number" name="precio" placeholder="Precio" step="0.01" min="0.01" required>
                <input type="number" name="stock" placeholder="Stock" min="0" required>
                <button type="submit" class="btn btn-primario">+ Agregar</button>
            </form>
        </div>

        <?php if (empty($todos_productos)): ?>
            <p class="sin-datos">No hay productos en el catálogo.</p>
        <?php else: ?>
        <table class="tabla-datos">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Categoría</th>
                    <th>Precio</th>
                    <th>Stock</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($todos_productos as $p): ?>
                <tr>
                    <td><?= (int)$p['id_producto'] ?></td>
                    <td><?= htmlspecialchars($p['nombre']) ?></td>
                    <td><?= htmlspecialchars($p['categoria']) ?></td>
                    <td>$ <?= number_format((float)$p['precio'], 2, ',', '.') ?></td>
                    <td><?= (int)$p['stock'] ?></td>
                    <td>
                        <form method="POST" action="/admin_panel.php" class="formulario-inline">
                            <input type="hidden" name="accion" value="actualizar_stock">
                            <input type="hidden" name="id_producto" value="<?= (int)$p['id_producto'] ?>">
                            <input type="number" name="nuevo_stock" value="<?= (int)$p['stock'] ?>" min="0" size="5">
                            <button type="submit" class="btn btn-chico btn-actualizar">Actualizar</button>
                        </form>
                        <form method="POST" action="/admin_panel.php" class="formulario-inline" onsubmit="return confirm('¿Eliminar producto <?= htmlspecialchars($p['nombre'], ENT_QUOTES) ?>?')">
                            <input type="hidden" name="accion" value="eliminar_producto">
                            <input type="hidden" name="id_producto" value="<?= (int)$p['id_producto'] ?>">
                            <button type="submit" class="btn btn-chico btn-eliminar">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </article>

    <article class="admin-seccion">
        <h2 class="admin-subtitulo">Pedidos Pendientes de Aprobación (<?= count($pedidos_pendientes) ?>)</h2>

        <?php if (empty($pedidos_pendientes)): ?>
            <p class="sin-datos">No hay pedidos pendientes de aprobación.</p>
        <?php else: ?>
        <?php foreach ($pedidos_pendientes as $pedido): ?>
            <?php $usuario = buscar_usuario_por_id($pedido['id_usuario']); ?>
            <div class="pedido-card">
                <div class="pedido-header">
                    <h3>Pedido #<?= (int)$pedido['id_pedido'] ?></h3>
                    <p class="pedido-info">
                        Cliente: <strong><?= htmlspecialchars($usuario['nombre']) ?></strong> (<?= htmlspecialchars($usuario['username']) ?>)
                    </p>
                    <p class="pedido-info">Fecha: <?= htmlspecialchars($pedido['fecha_pedido']) ?></p>
                    <p class="pedido-total">Total: $ <?= number_format((float)$pedido['total_pagado'], 2, ',', '.') ?></p>
                </div>
                <div class="pedido-items">
                    <h4>Items:</h4>
                    <ul>
                        <?php foreach ($pedido['items'] as $item): ?>
                            <?php $producto = buscar_producto_por_id($item['id_producto']); ?>
                            <li>
                                <?= htmlspecialchars($producto['nombre']) ?> x <?= (int)$item['cantidad'] ?>
                                - $ <?= number_format((float)$item['precio_unitario'] * (int)$item['cantidad'], 2, ',', '.') ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="pedido-acciones">
                    <form method="POST" action="/admin_panel.php" class="formulario-inline">
                        <input type="hidden" name="accion" value="procesar_pedido">
                        <input type="hidden" name="id_pedido" value="<?= (int)$pedido['id_pedido'] ?>">
                        <input type="hidden" name="decision" value="aprobar">
                        <button type="submit" class="btn btn-aprobar" onclick="return confirm('¿Aprobar este pedido? Se generará la factura y se habilitará el pago.')">
                            Aprobar pedido
                        </button>
                    </form>
                    <form method="POST" action="/admin_panel.php" class="formulario-inline">
                        <input type="hidden" name="accion" value="procesar_pedido">
                        <input type="hidden" name="id_pedido" value="<?= (int)$pedido['id_pedido'] ?>">
                        <input type="hidden" name="decision" value="rechazar">
                        <button type="submit" class="btn btn-rechazar" onclick="return confirm('¿Rechazar este pedido?')">
                            Rechazar pedido
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </article>

    <article class="admin-seccion">
        <h2 class="admin-subtitulo">Registro de auditoría (últimas 20 entradas)</h2>
        <?php
        $log_contenido = is_readable(RUTA_LOG) ? file(RUTA_LOG, FILE_IGNORE_NEW_LINES) : [];
        $ultimas_lineas = array_slice(array_reverse($log_contenido), 0, 20);
        ?>
        <?php if (empty($ultimas_lineas)): ?>
            <p class="sin-datos">El log de auditoría está vacío.</p>
        <?php else: ?>
        <div class="log-contenedor">
            <?php foreach ($ultimas_lineas as $linea): ?>
                <div class="log-linea"><?= htmlspecialchars($linea) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </article>
</section>

<?php require_once __DIR__ . '/src/_footer.php'; ?>
