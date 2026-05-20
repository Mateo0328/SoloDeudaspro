<?php
require_once "../../session_init.php";
session_start();
require __DIR__ . '/../../config/conexion.php';

// RN02A / RN03B: Logueado y Administrador
if(!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin'){
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../session_control.php";

if(isset($_SESSION['force_password_change']) && $_SESSION['force_password_change'] === true){
    header("Location: forzarCambioPassword.php");
    exit;
}

$id_editar = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$msg = '';
$error = '';

if($id_editar <= 0){
    header("Location: consultarUsuarios.php");
    exit;
}

// Obtener datos actuales
$stmt = $conexion->prepare("SELECT id_prestamista, nombre, correo, rol, estado FROM prestamista WHERE id_prestamista = ?");
$stmt->execute([$id_editar]);
$user_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$user_to_edit){
    header("Location: consultarUsuarios.php?error=Usuario no encontrado");
    exit;
}

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    csrf_validate_post();
    if(isset($_POST['actualizar'])){
        $nombre = trim($_POST['nombre']);
        $correo = trim($_POST['correo']);
        $rol = $_POST['rol'];
        $estado = $_POST['estado'];

        if(empty($nombre) || empty($correo)){
            $error = "Nombre y correo son obligatorios";
        } elseif(!filter_var($correo, FILTER_VALIDATE_EMAIL)){
            $error = "Correo no válido";
        } else {
            // Validación de correo único
            $check = $conexion->prepare("SELECT id_prestamista FROM prestamista WHERE correo = ? AND id_prestamista != ?");
            $check->execute([$correo, $id_editar]);
            if($check->fetch()){
                $error = "El correo ya está en uso por otro usuario";
            } else {
                try {
                    $conexion->beginTransaction();

                    // RN04B: No se puede activar un usuario que tenga sanciones vigentes.
                    if($estado === 'activo'){
                        $checkSancion = $conexion->prepare("SELECT id_sancion FROM sancion WHERE id_prestamista = ? AND estado = 'vigente' AND fecha_fin >= CURDATE()");
                        $checkSancion->execute([$id_editar]);
                        if($checkSancion->fetch()){
                            throw new Exception("No se puede activar el usuario porque tiene sanciones vigentes.");
                        }
                    }

                    // Trazabilidad Nivel Dios: Obtener datos antiguos
                    $old = $conexion->prepare("SELECT nombre, correo, rol, estado FROM prestamista WHERE id_prestamista = ?");
                    $old->execute([$id_editar]);
                    $oldData = $old->fetch(PDO::FETCH_ASSOC);

                    // Verificar si hubo cambios reales
                    if($oldData['nombre'] === $nombre && $oldData['correo'] === $correo && $oldData['rol'] === $rol && $oldData['estado'] === $estado){
                        $conexion->rollBack(); // Cerrar transacción si no hay cambios
                        $error = "No se realizaron cambios en el usuario";
                    } else {
                        // RN03A: Solo datos personales básicos + rol/estado para admin
                        $stmt = $conexion->prepare("UPDATE prestamista SET nombre = ?, correo = ?, rol = ?, estado = ? WHERE id_prestamista = ?");
                        $stmt->execute([$nombre, $correo, $rol, $estado, $id_editar]);

                        // RN03C: Registro en historial (trazabilidad) usando ID de usuario
                        $log = $conexion->prepare("INSERT INTO trazabilidad (accion, id_registro, descripcion, tabla_afectada, usuario, fecha_evento) VALUES ('UPDATE', ?, ?, 'prestamista', ?, NOW())");
                        $descripcion = "Admin editó ID $id_editar. Nombre: {$oldData['nombre']}->$nombre | Correo: {$oldData['correo']}->$correo | Rol: {$oldData['rol']}->$rol | Estado: {$oldData['estado']}->$estado";
                        $log->execute([$id_editar, $descripcion, $_SESSION['id_usuario']]);

                        $conexion->commit();
                        $msg = "Usuario actualizado correctamente";
                        
                        // Refrescar datos
                        $user_to_edit['nombre'] = $nombre;
                        $user_to_edit['correo'] = $correo;
                        $user_to_edit['rol'] = $rol;
                        $user_to_edit['estado'] = $estado;
                    }
                } catch (Exception $e) {
                    $conexion->rollBack();
                    $error = "Error al actualizar: " . $e->getMessage();
                }
            }
        }
    } elseif(isset($_POST['cambiar_pass'])){
        $new_pass = $_POST['nueva_contrasena'] ?? '';
        $new_pass_confirm = $_POST['confirmar_contrasena'] ?? '';
        $confirmo_reset = isset($_POST['confirmo_reset']);

        if($new_pass === '' || $new_pass_confirm === ''){
            $error = "Completa ambos campos de contraseña";
        } elseif($new_pass !== $new_pass_confirm){
            $error = "La contraseña y la confirmación no coinciden";
        } elseif(!$confirmo_reset){
            $error = "Debes confirmar el restablecimiento de contraseña";
        } elseif(strlen($new_pass) < 8 || !preg_match('/[A-Za-z]/', $new_pass) || !preg_match('/[0-9]/', $new_pass)){
            $error = "La contraseña debe tener al menos 8 caracteres e incluir letras y números";
        } else {
            try {
                $cur = $conexion->prepare("SELECT contrasena FROM prestamista WHERE id_prestamista = ?");
                $cur->execute([$id_editar]);
                $curRow = $cur->fetch(PDO::FETCH_ASSOC);

                if($curRow && password_verify($new_pass, $curRow['contrasena'])){
                    $error = "La nueva contraseña no puede ser igual a la anterior";
                } else {
                $hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $stmt = $conexion->prepare("UPDATE prestamista SET contrasena = ?, requiere_cambio_password = 1, intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id_prestamista = ?");
                $stmt->execute([$hash, $id_editar]);

                // Log trazabilidad
                $log = $conexion->prepare("INSERT INTO trazabilidad (accion, id_registro, descripcion, tabla_afectada, usuario, fecha_evento) VALUES ('UPDATE', ?, 'Contraseña restablecida por admin (requiere confirmación del usuario)', 'prestamista', ?, NOW())");
                $log->execute([$id_editar, $_SESSION['id_usuario']]);

                $msg = "Contraseña restablecida. El usuario deberá cambiarla al iniciar sesión";
                }
            } catch (Exception $e) {
                $error = "Error al actualizar contraseña: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<?php
$pageTitle = 'Editar Usuario - SoloDeudas';
$assetBaseUrl = '../../assets';
$includeInicioCss = true;
require __DIR__ . '/../_head.php';
?>
</head>

<body>
    <?php
    $navContext = 'pages';
    $navActive = 'admin';
    require __DIR__ . '/../_nav.php';
    ?>

    <div class="form-container" style="margin-top:1.5rem">
        <h2>Editar Usuario (Admin)</h2>

        <?php if($msg): ?><p class="alert alert-success"><?php echo htmlspecialchars($msg); ?></p><?php endif; ?>
        <?php if($error): ?><p class="alert alert-error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <label>Nombre</label>
            <input type="text" name="nombre" value="<?php echo htmlspecialchars($user_to_edit['nombre']); ?>" required>

            <label>Correo</label>
            <input type="email" name="correo" value="<?php echo htmlspecialchars($user_to_edit['correo']); ?>" required>

            <label>Rol</label>
            <select name="rol">
                <option value="prestamista" <?php echo $user_to_edit['rol'] === 'prestamista' ? 'selected' : ''; ?>>
                    Prestamista</option>
                <option value="admin" <?php echo $user_to_edit['rol'] === 'admin' ? 'selected' : ''; ?>>Administrador
                </option>
            </select>

            <label>Estado</label>
            <select name="estado">
                <option value="activo" <?php echo $user_to_edit['estado'] === 'activo' ? 'selected' : ''; ?>>Activo
                </option>
                <option value="inactivo" <?php echo $user_to_edit['estado'] === 'inactivo' ? 'selected' : ''; ?>>
                    Inactivo</option>
            </select>

            <button type="submit" name="actualizar">Guardar Cambios</button>
            <button type="button" onclick="location.href='consultarUsuarios.php'"
                style="background:#666;">Cancelar</button>
        </form>

        <hr style="margin: 2rem 0; border: 0; border-top: 1px solid #eee;">

        <h2>Restablecer Contraseña</h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <label>Nueva Contraseña</label>
            <input type="password" name="nueva_contrasena" placeholder="Mínimo 8 caracteres (letras y números)" required>
            <label>Confirmar Contraseña</label>
            <input type="password" name="confirmar_contrasena" placeholder="Repite la contraseña" required>
            <div style="margin-top: 12px; display:flex; gap:10px; align-items:center;">
                <input type="checkbox" id="confirmo_reset" name="confirmo_reset" value="1" required style="width:auto; margin:0;">
                <label for="confirmo_reset" style="margin:0; font-weight:600;">Confirmo el restablecimiento de contraseña</label>
            </div>
            <button type="submit" name="cambiar_pass" style="background: #1B4332; color: white !important;">Actualizar Contraseña</button>
        </form>
        
        <button type="button" onclick="location.href='consultarUsuarios.php'" style="background:#666; margin-top: 20px; width: 100%; color: white !important;">Volver a la lista</button>
    </div>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> SoloDeudas. Todos los derechos reservados.</p>
    </footer>

    <script src="../../assets/js/main.js"></script>
</body>

</html>
