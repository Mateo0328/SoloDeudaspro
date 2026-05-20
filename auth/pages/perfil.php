<?php
require_once "../../session_init.php";
session_start();
require __DIR__ . '/../../config/conexion.php';
if(!isset($_SESSION['id_usuario'])){
    header("Location: ../login.php");
    exit;
}

$id_prestamista = $_SESSION['id_usuario'];
$msg = '';
$error = '';

require_once __DIR__ . "/../session_control.php";

if(isset($_SESSION['force_password_change']) && $_SESSION['force_password_change'] === true){
    header("Location: forzarCambioPassword.php");
    exit;
}

if(($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'){
    csrf_validate_post();
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar'])){
    $nombre = trim($_POST['nombre']);
    $correo = trim($_POST['correo']);

    if(empty($nombre) || empty($correo)){
        $error = "Nombre y correo son obligatorios";
    } elseif(!filter_var($correo, FILTER_VALIDATE_EMAIL)){
        $error = "Correo no válido";
    } else {
        // Validación de correo único
        $check = $conexion->prepare("SELECT id_prestamista FROM prestamista WHERE correo = ? AND id_prestamista != ?");
        $check->execute([$correo, $id_prestamista]);
        if($check->fetch()){
            $error = "El correo ya está en uso por otro usuario";
        } else {
            try {
                $conexion->beginTransaction();

                // Trazabilidad Nivel Dios: Obtener datos antiguos
                $old = $conexion->prepare("SELECT nombre, correo FROM prestamista WHERE id_prestamista = ?");
                $old->execute([$id_prestamista]);
                $oldData = $old->fetch(PDO::FETCH_ASSOC);

                // Verificar si hubo cambios reales
                if($oldData['nombre'] === $nombre && $oldData['correo'] === $correo){
                    $conexion->rollBack(); // Cerrar transacción si no hay cambios
                    $error = "No se realizaron cambios en el perfil";
                } else {
                    // RN03A: Solo datos personales básicos (nombre, correo)
                    $stmt = $conexion->prepare("UPDATE prestamista SET nombre = ?, correo = ? WHERE id_prestamista = ?");
                    $stmt->execute([$nombre, $correo, $id_prestamista]);

                    // RN03C: Registro en historial (trazabilidad) usando ID de usuario
                    $log = $conexion->prepare("INSERT INTO trazabilidad (accion, id_registro, descripcion, tabla_afectada, usuario, fecha_evento) VALUES ('UPDATE', ?, ?, 'prestamista', ?, NOW())");
                    $descripcion = "Nombre: {$oldData['nombre']} -> $nombre | Correo: {$oldData['correo']} -> $correo";
                    $log->execute([$id_prestamista, $descripcion, $_SESSION['id_usuario']]);

                    $conexion->commit();
                    $_SESSION['nombre'] = $nombre; // Actualizar nombre en sesión
                    $msg = "Perfil actualizado correctamente";
                }
            } catch (Exception $e) {
                $conexion->rollBack();
                $error = "Error al actualizar: " . $e->getMessage();
            }
        }
    }
}

$msg_password = '';
$error_password = '';
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_password'])){
    $password_actual = $_POST['password_actual'] ?? '';
    $password_nueva = $_POST['password_nueva'] ?? '';
    $password_confirmar = $_POST['password_confirmar'] ?? '';
    $confirmo_cambio = isset($_POST['confirmo_cambio']);

    if($password_actual === '' || $password_nueva === '' || $password_confirmar === ''){
        $error_password = "Completa todos los campos de contraseña";
    } elseif($password_nueva !== $password_confirmar){
        $error_password = "La nueva contraseña y la confirmación no coinciden";
    } elseif(!$confirmo_cambio){
        $error_password = "Debes confirmar el cambio de contraseña";
    } else {
        try {
            $stmtPwd = $conexion->prepare("SELECT contrasena FROM prestamista WHERE id_prestamista = ?");
            $stmtPwd->execute([$id_prestamista]);
            $pwdRow = $stmtPwd->fetch(PDO::FETCH_ASSOC);

            if(!$pwdRow){
                $error_password = "Usuario no encontrado";
            } elseif(!password_verify($password_actual, $pwdRow['contrasena'])){
                $error_password = "La contraseña actual es incorrecta";
            } elseif(password_verify($password_nueva, $pwdRow['contrasena'])){
                $error_password = "La nueva contraseña no puede ser igual a la anterior";
            } else {
                $conexion->beginTransaction();

                $nuevo_hash = password_hash($password_nueva, PASSWORD_DEFAULT);
                $upPwd = $conexion->prepare("UPDATE prestamista SET contrasena = ? WHERE id_prestamista = ?");
                $upPwd->execute([$nuevo_hash, $id_prestamista]);

                $log = $conexion->prepare("INSERT INTO trazabilidad (accion, id_registro, descripcion, tabla_afectada, usuario, fecha_evento) VALUES ('UPDATE', ?, ?, 'prestamista', ?, NOW())");
                $descripcion = "Cambio de contraseña";
                $log->execute([$id_prestamista, $descripcion, $_SESSION['id_usuario']]);

                $conexion->commit();
                $msg_password = "Contraseña actualizada correctamente";
            }
        } catch (Exception $e) {
            if($conexion->inTransaction()){
                $conexion->rollBack();
            }
            $error_password = "Error al actualizar contraseña: " . $e->getMessage();
        }
    }
}

$stmt = $conexion->prepare("SELECT nombre, correo, rol FROM prestamista WHERE id_prestamista = ?");
$stmt->execute([$id_prestamista]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">

<?php
$pageTitle = 'Perfil - SoloDeudas';
$assetBaseUrl = '../../assets';
require __DIR__ . '/../_head.php';
?>
</head>

<body>
    <?php
    $navContext = 'pages';
    $navActive = 'perfil';
    require __DIR__ . '/../_nav.php';
    ?>

<section class="section-container">
    <div class="section-header">
        <div class="header-title">
            <i class="fas fa-user-circle"></i>
            <h2>Mi Perfil</h2>
        </div>
        <div class="header-actions">
            <a href="../index.php" class="btn-secondary">
                <i class="fas fa-home"></i> Inicio
            </a>
        </div>
    </div>

    <?php if($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if($msg_password): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg_password); ?></div><?php endif; ?>
    <?php if($error_password): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_password); ?></div><?php endif; ?>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px;">
        <div class="card">
            <div class="card-header" style="margin-bottom: 20px;">
                <h3 style="margin:0;"><i class="fas fa-info-circle"></i> Datos Personales</h3>
            </div>
            <form method="POST" class="filter-form" style="display: flex; flex-direction: column; gap: 15px;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-group">
                    <label>Nombre</label>
                    <input type="text" name="nombre" value="<?php echo htmlspecialchars($user['nombre'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Correo</label>
                    <input type="email" name="correo" value="<?php echo htmlspecialchars($user['correo'] ?? ''); ?>" required>
                </div>
                <div class="form-actions" style="display: flex; justify-content: flex-end; margin-top: 10px;">
                    <button type="submit" name="actualizar" class="btn-primary">
                        <i class="fas fa-save"></i> Actualizar Perfil
                    </button>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header" style="margin-bottom: 20px;">
                <h3 style="margin:0;"><i class="fas fa-key"></i> Seguridad</h3>
            </div>
            <form method="POST" class="filter-form" style="display: flex; flex-direction: column; gap: 15px;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-group">
                    <label>Contraseña actual</label>
                    <input type="password" name="password_actual" autocomplete="current-password" required>
                </div>
                <div class="form-group">
                    <label>Nueva contraseña</label>
                    <input type="password" name="password_nueva" autocomplete="new-password" required>
                </div>
                <div class="form-group">
                    <label>Confirmar nueva contraseña</label>
                    <input type="password" name="password_confirmar" autocomplete="new-password" required>
                </div>
                <div class="form-group" style="flex-direction: row; align-items: center; gap: 10px; margin-top: 5px;">
                    <input type="checkbox" id="confirmo_cambio" name="confirmo_cambio" value="1" required style="width:auto;">
                    <label for="confirmo_cambio" style="margin-bottom: 0;">Confirmo el cambio</label>
                </div>
                <div class="form-actions" style="display: flex; justify-content: flex-end; margin-top: 10px;">
                    <button type="submit" name="cambiar_password" class="btn-warning">
                        <i class="fas fa-shield-alt"></i> Cambiar Contraseña
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>
    <footer>
        <p>&copy; <?php echo date("Y"); ?> SoloDeudas. Todos los derechos reservados.</p>
    </footer>

    <script src="../../assets/js/main.js"></script>
</body>

</html>
