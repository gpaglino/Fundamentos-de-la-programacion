<?php
// Aquí se registran usuarios nuevos
// Los datos se guardan seguros y la cuenta queda pendiente de aprobación

require_once __DIR__ . '/src/auth.php';
iniciar_sesion_segura();

if (sesion_activa()) {
    header('Location: /index.php');
    exit();
}

$error  = '';
$exito  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $nombre   = trim($_POST['nombre']   ?? '');
    $email    = trim($_POST['email']    ?? '');
    $dni      = trim($_POST['dni']      ?? '');
    $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';
    $password = $_POST['password']      ?? '';
    $confirma = $_POST['confirma']      ?? '';

    // Checkeamos que todo esté completo
    if ($username === '' || $nombre === '' || $email === '' || $dni === '' || $fecha_nacimiento === '' || $password === '') {
        $error = 'Completá todos los campos.';
    }
    // El email tiene que ser válido
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El correo electrónico no es válido.';
    }
    // La contraseña no puede ser muy corta
    elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    }
    // Las contraseñas deben coincidir
    elseif ($password !== $confirma) {
        $error = 'Las contraseñas no coinciden.';
    }
    // Ver si el usuario ya existe
    elseif (buscar_usuario_por_username($username) !== null) {
        $error = 'Ese nombre de usuario ya está en uso.';
    }
    // Verificar que el email no esté usado
    elseif (buscar_usuario_por_email($email) !== null) {
        $error = 'Ese correo electrónico ya está en uso.';
    }
    // Chequear el DNI
    elseif (buscar_usuario_por_dni($dni) !== null) {
        $error = 'Ese DNI ya está registrado.';
    }
    else {
        // Todo ok, guardar la cuenta
        $guardado = registrar_usuario($username, $nombre, $email, $password, $dni, $fecha_nacimiento);

        if ($guardado) {
            registrar_log('REGISTRO', "Nueva postulación del usuario '{$username}' ({$nombre})");
            $exito = 'Tu cuenta fue creada con éxito. Aguardá la aprobación del administrador.';
        } else {
            $error = 'Ocurrió un error al guardar. Intentá nuevamente.';
        }
    }
}

$titulo_pagina = 'Registrarse';
require_once __DIR__ . '/src/_header.php';
?>

<section class="formulario-contenedor">
    <h1 class="formulario-titulo">Crear cuenta</h1>

    <?php if ($error): ?>
        <p class="alerta alerta-error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if ($exito): ?>
        <p class="alerta alerta-exito"><?= htmlspecialchars($exito) ?></p>
        <p class="formulario-pie"><a href="/login.php">Ir al login</a></p>
    <?php else: ?>

    <form method="POST" action="/registro.php" class="formulario">
        <div class="campo">
            <label for="nombre">Nombre completo</label>
            <input type="text"
                   id="nombre"
                   name="nombre"
                   value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>"
                   required autofocus>
        </div>

        <div class="campo">
            <label for="username">Nombre de usuario</label>
            <input type="text"
                   id="username"
                   name="username"
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                   required autocomplete="username">
        </div>

        <div class="campo">
            <label for="email">Correo electrónico</label>
            <input type="email"
                   id="email"
                   name="email"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   required autocomplete="email">
        </div>

        <div class="campo">
            <label for="dni">DNI</label>
            <input type="text"
                   id="dni"
                   name="dni"
                   value="<?= htmlspecialchars($_POST['dni'] ?? '') ?>"
                   required>
        </div>

        <div class="campo">
            <label for="fecha_nacimiento">Fecha de nacimiento</label>
            <input type="date"
                   id="fecha_nacimiento"
                   name="fecha_nacimiento"
                   value="<?= htmlspecialchars($_POST['fecha_nacimiento'] ?? '') ?>"
                   required>
        </div>

        <div class="campo">
            <label for="password">Contraseña <small>(mínimo 6 caracteres)</small></label>
            <input type="password"
                   id="password"
                   name="password"
                   required
                   autocomplete="new-password">
        </div>

        <div class="campo">
            <label for="confirma">Confirmar contraseña</label>
            <input type="password"
                   id="confirma"
                   name="confirma"
                   required
                   autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-primario btn-bloque">Registrarse</button>
    </form>

    <p class="formulario-pie">
        ¿Ya tenés cuenta? <a href="/login.php">Iniciá sesión</a>
    </p>

    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/src/_footer.php'; ?>
