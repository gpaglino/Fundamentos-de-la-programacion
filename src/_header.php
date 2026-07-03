<?php
/**
 * ENCABEZADO Y MENÓ DE NAVEGACIÓN (_HEADER.PHP)
 * Este archivo se incluye al inicio de cada página pública.
 * Contiene:
 * - El DOCTYPE y etiquetas HTML básicas
 * - El logo y título de la página
 * - El menú de navegación (que varía según el estado del usuario)
 * 
 * USO:
 * Incluir en cualquier página como:
 *   $titulo_pagina = 'Mi Página';
 *   require_once __DIR__ . '/src/_header.php';
*/

// Verificamos el estado de la sesión del usuario para mostrar el menú apropiado
$logueado = sesion_activa();
$es_admin = $logueado && ($_SESSION['rol'] === 'admin');
$es_activo = $logueado && ($_SESSION['estado'] === 'activo');
$nombre_usuario = $logueado ? htmlspecialchars($_SESSION['nombre']) : '';
?>
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo_pagina ?? 'TecnoShop') ?> — TecnoShop</title>
    <link rel="stylesheet" href="/assets/css/estilos.css">
</head>
<body>

<header class="sitio-header">
    <div class="header-contenido">
        <!-- Logo que enlaza a la página principal -->
        <a href="/index.php" class="logo-link">
            <!-- Logo SVG creado directamente (sin necesidad de archivo de imagen) -->
            <svg class="logo-svg" viewBox="0 0 160 40" xmlns="http://www.w3.org/2000/svg" aria-label="TecnoShop">
                <!-- Cuadro azul con las iniciales -->
                <rect x="0" y="4" width="32" height="32" rx="6" fill="#2563eb"/>
                <text x="16" y="25" text-anchor="middle" fill="#fff" font-size="18" font-weight="bold" font-family="Arial">TS</text>
                <!-- Nombre de la tienda -->
                <text x="40" y="28" fill="#1e3a8a" font-size="20" font-weight="bold" font-family="Arial">TecnoShop</text>
            </svg>
        </a>

        <!-- Mostramos el nombre del usuario si está logueado -->
        <?php if ($logueado): ?>
        <span class="usuario-bienvenida"><?= $nombre_usuario ?></span>
        <?php endif; ?>
    </div>

    <!-- Menú de navegación que cambia según el tipo de usuario -->
    <nav class="menu-nav">
        <ul class="menu-lista">
            <!-- Inicio está siempre disponible -->
            <li><a href="/index.php">Inicio</a></li>

            <!-- Si NO está logueado, muestra opciones para login/registro -->
            <?php if (!$logueado): ?>
                <li><a href="/login.php">Iniciar sesión</a></li>
                <li><a href="/registro.php">Registrarse</a></li>

            <!-- Si es administrador, muestra opciones de admin -->
            <?php elseif ($es_admin): ?>
                <li><a href="/catalogo.php">Catálogo</a></li>
                <li><a href="/admin_panel.php">Panel Admin</a></li>
                <li><a href="/logout.php">Cerrar sesión</a></li>

            <!-- Si es usuario activo, muestra opciones de comprador -->
            <?php elseif ($es_activo): ?>
                <li><a href="/catalogo.php">Realizar pedido</a></li>
                <li><a href="/mis_pedidos.php">Mis pedidos</a></li>
                <li><a href="/logout.php">Cerrar sesión</a></li>

            <!-- Si está pendiente de aprobación, solo puede logout -->
            <?php else: ?>
                <!-- Usuario pendiente: solo ve inicio y logout -->
                <li><a href="/logout.php">Cerrar sesión</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>

<main class="contenido-principal">
<!-- Aquí se insertará el contenido principal de cada página -->
