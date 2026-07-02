<?php
/**
 * _header.php — Encabezado y menú de navegación compartido.
 * Incluir al inicio de cada página pública con:
 *   $titulo_pagina = 'Mi Página';
 *   require_once __DIR__ . '/src/_header.php';
 *
 * Requiere que la sesión ya esté iniciada antes de incluir.
 */

$logueado = sesion_activa();
$es_admin = $logueado && ($_SESSION['rol'] === 'admin');
$es_activo = $logueado && ($_SESSION['estado'] === 'activo');
$nombre_usuario = $logueado ? htmlspecialchars($_SESSION['nombre']) : '';
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
        <a href="/index.php" class="logo-link">
            <!-- Logo SVG generado inline para no depender de imagen externa -->
            <svg class="logo-svg" viewBox="0 0 160 40" xmlns="http://www.w3.org/2000/svg" aria-label="TecnoShop">
                <rect x="0" y="4" width="32" height="32" rx="6" fill="#2563eb"/>
                <text x="16" y="25" text-anchor="middle" fill="#fff" font-size="18" font-weight="bold" font-family="Arial">TS</text>
                <text x="40" y="28" fill="#1e3a8a" font-size="20" font-weight="bold" font-family="Arial">TecnoShop</text>
            </svg>
        </a>

        <?php if ($logueado): ?>
        <span class="usuario-bienvenida"><?= $nombre_usuario ?></span>
        <?php endif; ?>
    </div>

    <nav class="menu-nav">
        <ul class="menu-lista">
            <li><a href="/index.php">Inicio</a></li>

            <?php if (!$logueado): ?>
                <li><a href="/login.php">Iniciar sesión</a></li>
                <li><a href="/registro.php">Registrarse</a></li>

            <?php elseif ($es_admin): ?>
                <li><a href="/catalogo.php">Catálogo</a></li>
                <li><a href="/admin_panel.php">Panel Admin</a></li>
                <li><a href="/logout.php">Cerrar sesión</a></li>

            <?php elseif ($es_activo): ?>
                <li><a href="/catalogo.php">Realizar pedido</a></li>
                <li><a href="/mis_pedidos.php">Mis pedidos</a></li>
                <li><a href="/logout.php">Cerrar sesión</a></li>

            <?php else: ?>
                <!-- Usuario pendiente: solo ve inicio y logout -->
                <li><a href="/logout.php">Cerrar sesión</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>

<main class="contenido-principal">
