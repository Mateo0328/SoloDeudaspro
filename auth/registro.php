<?php
require_once "../session_init.php";
session_start();
require "../config/conexion.php";

if(($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'){
    csrf_validate_post();
}

if(isset($_POST['registro'])){
    $nombre = trim($_POST['nombre']);
    $correo = trim($_POST['correo']);
    $contrasena = $_POST['contrasena'];

    // RN01A: Datos completos
    if(empty($nombre) || empty($correo) || empty($contrasena)){
        $error = "Todos los campos son obligatorios";
    } 
    // Validación de formato de correo
    elseif(!filter_var($correo, FILTER_VALIDATE_EMAIL)){
        $error = "El correo electrónico no es válido";
    }
    // RN01C: Contraseña (mínimo 8 caracteres, letras y números)
    elseif(strlen($contrasena) < 8 || !preg_match('/[A-Za-z]/', $contrasena) || !preg_match('/[0-9]/', $contrasena)){
        $error = "La contraseña debe tener al menos 8 caracteres e incluir letras y números";
    }
    else {
        // RN01B: Correo único
        $verificar = $conexion->prepare("SELECT id_prestamista FROM prestamista WHERE correo=?");
        $verificar->execute([$correo]);

        if($verificar->fetch()){
            $error = "Este correo ya está registrado";
        } else {
            $hash = password_hash($contrasena, PASSWORD_DEFAULT);
            $stmt = $conexion->prepare("INSERT INTO prestamista(nombre, correo, contrasena) VALUES(?,?,?)");
            $stmt->execute([$nombre, $correo, $hash]);

            header("Location: login.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<?php
$pageTitle = 'Registro';
$assetBaseUrl = '../assets';
$includeInicioCss = true;
require __DIR__ . '/_head.php';
?>
</head>

<body class="auth-body">

    <div class="form-container">
        <h2>Registrarse</h2>

        <?php if(isset($error)) echo "<p class='alert alert-error'>$error</p>"; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="text" name="nombre" placeholder="Nombre completo" required>
            <input type="email" name="correo" placeholder="Correo" required>
            <input type="password" name="contrasena" placeholder="Contraseña" required>
            <button type="submit" name="registro">Registrarse</button>
        </form>

        <a href="login.php">Volver</a>
    </div>

</body>

</html>
