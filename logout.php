<?php
/**
 * logout.php — Cierra la sesión activa y redirige al inicio.
 */

require_once __DIR__ . '/src/auth.php';

iniciar_sesion_segura();

if (sesion_activa()) {
    cerrar_sesion();
}

header('Location: /index.php');
exit();
