<?php
/**
 * login.php — Formulario de autenticación de usuarios.
 */

require_once __DIR__ . '/src/auth.php';
iniciar_sesion_segura();

// Si ya está logueado, redirigir al inicio
if (sesion_activa()) {
    header('Location: /index.php');
    exit();
}

$error  = '';
$exito  = '';

// Procesar formulario POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Completá todos los campos.';
    } else {
        $resultado = intentar_login($username, $password);
        if ($resultado['ok']) {
            header('Location: /index.php');
            exit();
        } else {
            $error = $resultado['mensaje'];
        }
    }
}

$titulo_pagina = 'Iniciar sesión';
require_once __DIR__ . '/src/_header.php';

$alerta_get = mensaje_get();
?>

<?= $alerta_get ?>

<section class="formulario-contenedor">
    <h1 class="formulario-titulo">Iniciar sesión</h1>

    <?php if ($error): ?>
        <p class="alerta alerta-error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST" action="/login.php" class="formulario">
        <div class="campo">
            <label for="username">Usuario</label>
            <input type="text"
                   id="username"
                   name="username"
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                   required
                   autofocus
                   autocomplete="username">
        </div>

        <div class="campo">
            <label for="password">Contraseña</label>
            <input type="password"
                   id="password"
                   name="password"
                   required
                   autocomplete="current-password">
        </div>

        <button type="submit" class="btn btn-primario btn-bloque">Entrar</button>
    </form>

    <p class="formulario-pie">
        ¿No tenés cuenta? <a href="/registro.php">Registrate aquí</a>
    </p>
</section>

<?php require_once __DIR__ . '/src/_footer.php'; ?>
