<?php
require_once "../../session_init.php";
session_start();
require __DIR__ . '/../../config/conexion.php';
if(!isset($_SESSION['id_usuario'])){
    header("Location: ../login.php");
    exit;
}

if(!isset($_SESSION['force_password_change']) || $_SESSION['force_password_change'] !== true){
    header("Location: ../index.php");
    exit;
}

$id_prestamista = $_SESSION['id_usuario'];
$id_prestamista = (int)$id_prestamista;
require_once __DIR__ . "/../session_control.php";
$msg = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_password'])){
    csrf_validate_post();
    $password_actual = $_POST['password_actual'] ?? '';
    $password_nueva = $_POST['password_nueva'] ?? '';
    $password_confirmar = $_POST['password_confirmar'] ?? '';
    $confirmo_cambio = isset($_POST['confirmo_cambio']);

    if($password_actual === '' || $password_nueva === '' || $password_confirmar === ''){
        $error = "Completa todos los campos de contraseña";
    } elseif($password_nueva !== $password_confirmar){
        $error = "La nueva contraseña y la confirmación no coinciden";
    } elseif(!$confirmo_cambio){
        $error = "Debes confirmar el cambio de contraseña";
    } else {
        try {
            $stmtPwd = $conexion->prepare("SELECT contrasena FROM prestamista WHERE id_prestamista = ?");
            $stmtPwd->execute([$id_prestamista]);
            $pwdRow = $stmtPwd->fetch(PDO::FETCH_ASSOC);

            if(!$pwdRow){
                $error = "Usuario no encontrado";
            } elseif(!password_verify($password_actual, $pwdRow['contrasena'])){
                $error = "La contraseña actual es incorrecta";
            } elseif(password_verify($password_nueva, $pwdRow['contrasena'])){
                $error = "La nueva contraseña no puede ser igual a la anterior";
            } else {
                $conexion->beginTransaction();

                $nuevo_hash = password_hash($password_nueva, PASSWORD_DEFAULT);
                $upPwd = $conexion->prepare("UPDATE prestamista SET contrasena = ?, requiere_cambio_password = 0 WHERE id_prestamista = ?");
                $upPwd->execute([$nuevo_hash, $id_prestamista]);

                $log = $conexion->prepare("INSERT INTO trazabilidad (accion, id_registro, descripcion, tabla_afectada, usuario, fecha_evento) VALUES ('UPDATE', ?, ?, 'prestamista', ?, NOW())");
                $descripcion = "Cambio de contraseña (confirmación obligatoria)";
                $log->execute([$id_prestamista, $descripcion, $_SESSION['id_usuario']]);

                $conexion->commit();
                unset($_SESSION['force_password_change']);
                $msg = "Contraseña actualizada correctamente";

                header("Location: ../index.php");
                exit;
            }
        } catch (Exception $e) {
            if($conexion->inTransaction()){
                $conexion->rollBack();
            }
            $error = "Error al actualizar contraseña: " . $e->getMessage();
        }
    }
}

$stmt = $conexion->prepare("SELECT nombre, rol FROM prestamista WHERE id_prestamista = ?");
$stmt->execute([$id_prestamista]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<?php
$pageTitle = 'Cambiar Contraseña - SoloDeudas';
$assetBaseUrl = '../../assets';
$includeInicioCss = true;
require __DIR__ . '/../_head.php';
?>
</head>
<body>
<?php
$navContext = 'pages';
$navActive = 'perfil';
require __DIR__ . '/../_nav.php';
?>

<div class="form-container" style="margin-top:1.5rem">
    <h2>Cambio de contraseña requerido</h2>

    <?php if($msg): ?><p class="alert alert-success"><?php echo htmlspecialchars($msg); ?></p><?php endif; ?>
    <?php if($error): ?><p class="alert alert-error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
        <label>Contraseña actual</label>
        <input type="password" name="password_actual" autocomplete="current-password" required>

        <label>Nueva contraseña</label>
        <input type="password" name="password_nueva" autocomplete="new-password" required>

        <label>Confirmar nueva contraseña</label>
        <input type="password" name="password_confirmar" autocomplete="new-password" required>

        <div style="margin-top: 12px; display:flex; gap:10px; align-items:center;">
            <input type="checkbox" id="confirmo_cambio" name="confirmo_cambio" value="1" required style="width:auto; margin:0;">
            <label for="confirmo_cambio" style="margin:0; font-weight:600;">Confirmo que deseo cambiar mi contraseña</label>
        </div>

        <button type="submit" name="cambiar_password">Actualizar Contraseña</button>
    </form>
</div>

<footer>
    <p>&copy; <?php echo date("Y"); ?> SoloDeudas. Todos los derechos reservados.</p>
</footer>

<script src="../../assets/js/main.js"></script>
</body>
</html>
