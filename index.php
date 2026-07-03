<?php
// Esta es la página principal de TecnoShop
// Aquí se muestra el inicio dependiendo si estás logueado o no

require_once __DIR__ . '/src/auth.php';
iniciar_sesion_segura();

$titulo_pagina = 'Inicio';
require_once __DIR__ . '/src/_header.php';

$alerta = mensaje_get();
?>

<?php if ($alerta): ?>
    <?= $alerta ?>
<?php endif; ?>

<section class="bienvenida">
    <h1 class="bienvenida-titulo">Bienvenido a <span class="marca">TecnoShop</span></h1>
    <p class="bienvenida-subtitulo">Tu tienda de hardware y periféricos de confianza.</p>

    <?php if (!sesion_activa()): ?>
    <div class="bienvenida-acciones">
        <a href="/login.php" class="btn btn-primario">Iniciar sesión</a>
        <a href="/registro.php" class="btn btn-secundario">Crear cuenta</a>
    </div>
    <?php elseif ($_SESSION['estado'] === 'activo'): ?>
    <div class="bienvenida-acciones">
        <a href="/catalogo.php" class="btn btn-primario">Ver catálogo</a>
    </div>
    <?php else: ?>
    <p class="alerta alerta-aviso">Tu cuenta está pendiente de aprobación por el administrador.</p>
    <?php endif; ?>
</section>

<section class="destacados">
    <h2 class="seccion-titulo">¿Por qué elegirnos?</h2>
    <div class="tarjetas-info">
        <article class="tarjeta-info">
            <span class="tarjeta-icono">🖥️</span>
            <h3>Gran variedad</h3>
            <p>Monitores, periféricos, componentes y gabinetes de las mejores marcas.</p>
        </article>
        <article class="tarjeta-info">
            <span class="tarjeta-icono">🔒</span>
            <h3>Compra segura</h3>
            <p>Sistema de autenticación robusto para proteger tu información.</p>
        </article>
        <article class="tarjeta-info">
            <span class="tarjeta-icono">📦</span>
            <h3>Pedidos simples</h3>
            <p>Realizá tu pedido en pocos clics con control de stock en tiempo real.</p>
        </article>
    </div>
</section>

<?php require_once __DIR__ . '/src/_footer.php'; ?>
