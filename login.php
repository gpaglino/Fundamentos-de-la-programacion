<?php
// Página para que los usuarios inicien sesión

require_once __DIR__ . '/src/auth.php';
iniciar_sesion_segura();

// Si ya estás logueado, no tiene sentido que estés acá
if (sesion_activa()) {
    header('Location: /index.php');
    exit();
}

$error  = '';
$exito  = '';

// Si el usuario envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Primero vemos que no estén vacíos
    if ($username === '' || $password === '') {
        $error = 'Completá todos los campos.';
    } else {
        // Intentamos el login
        $resultado = intentar_login($username, $password);
        if ($resultado['ok']) {
            // Listo! Entramos
            header('Location: /index.php');
            exit();
        } else {
            // Algo salió mal
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
        <!-- Campo para el nombre de usuario -->
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

        <!-- Campo para la contraseña -->
        <div class="campo">
            <label for="password">Contraseña</label>
            <input type="password"
                   id="password"
                   name="password"
                   required
                   autocomplete="current-password">
        </div>

        <!-- Botón para enviar el formulario -->
        <button type="submit" class="btn btn-primario btn-bloque">Entrar</button>
    </form>

    <p class="formulario-pie">
        ¿No tenés cuenta? <a href="/registro.php">Registrate aquí</a>
    </p>
</section>

<?php require_once __DIR__ . '/src/_footer.php'; ?>
