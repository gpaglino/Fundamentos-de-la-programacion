<?php
// Cerrar la sesión del usuario
// Es lo más rápido: se borra todo y volvemos al inicio

require_once __DIR__ . '/src/auth.php';

iniciar_sesion_segura();

if (sesion_activa()) {
    cerrar_sesion();
}

header('Location: /index.php');
exit();
