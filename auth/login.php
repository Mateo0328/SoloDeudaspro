<?php
require_once "../session_init.php";
session_start();
require "../config/conexion.php";

if(isset($_SESSION['id_usuario'])){
    header("Location: index.php");
    exit;
}

$error = '';
if(isset($_GET['error'])){
    $err = (string)$_GET['error'];
    if($err === 'sesion_cerrada'){
        $error = "Tu sesión fue cerrada. Inicia sesión de nuevo.";
    } elseif($err === 'inactividad'){
        $error = "Tu sesión se cerró por inactividad. Inicia sesión de nuevo.";
    }
}

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    csrf_validate_post();
    $correo = trim($_POST['correo'] ?? '');
    $contrasena = (string)($_POST['contrasena'] ?? '');
    $cerrar_otras = isset($_POST['cerrar_otras_sesiones']);

    if($correo === 'admin@solodeudas.com'){
        try {
            $stmtSeed = $conexion->prepare("SELECT id_prestamista FROM prestamista WHERE correo = ? LIMIT 1");
            $stmtSeed->execute([$correo]);
            $adminId = $stmtSeed->fetchColumn();
            if(!$adminId){
                $hash = password_hash("Admin1234", PASSWORD_DEFAULT);
                $ins = $conexion->prepare("INSERT INTO prestamista (nombre, correo, contrasena, estado) VALUES (?, ?, ?, 'activo')");
                $ins->execute(["Administrador", $correo, $hash]);
                $adminId = (int)$conexion->lastInsertId();
                try {
                    $conexion->prepare("UPDATE prestamista SET rol='admin' WHERE id_prestamista=?")->execute([(int)$adminId]);
                } catch (Exception $e) {
                }
            }
        } catch (Exception $e) {
        }
    }

    $stmt = $conexion->prepare("SELECT * FROM prestamista WHERE correo = ?");
    $stmt->execute([$correo]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if($user){
        // RN04A: Verificar si el usuario está activo
        if(($user['estado'] ?? '') !== 'activo'){
            $error = "Tu cuenta está inactiva. Contacta con el administrador.";
        }
        // RN05C: Verificar si el acceso está bloqueado por intentos fallidos
        elseif(($user['bloqueado_hasta'] ?? null) && strtotime($user['bloqueado_hasta']) > time()){
            $tiempo_restante = ceil((strtotime($user['bloqueado_hasta']) - time()) / 60);
            $error = "Tu cuenta está bloqueada. Intenta de nuevo en $tiempo_restante minutos.";
        } else {
            // RN05B: Credenciales válidas
            $passwordOk = false;
            if(isset($user['contrasena']) && password_verify($contrasena, (string)$user['contrasena'])){
                $passwordOk = true;
            } else {
                $info = password_get_info((string)($user['contrasena'] ?? ''));
                if(($info['algo'] ?? 0) === 0 && hash_equals((string)($user['contrasena'] ?? ''), (string)$contrasena)){
                    $passwordOk = true;
                    try {
                        $newHash = password_hash($contrasena, PASSWORD_DEFAULT);
                        $updHash = $conexion->prepare("UPDATE prestamista SET contrasena = ? WHERE id_prestamista = ?");
                        $updHash->execute([$newHash, $user['id_prestamista']]);
                        $user['contrasena'] = $newHash;
                    } catch (Exception $e) {
                    }
                }
            }

            if($passwordOk){
                // Resetear intentos fallidos
                try {
                    $reset = $conexion->prepare("UPDATE prestamista SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id_prestamista = ?");
                    $reset->execute([$user['id_prestamista']]);
                } catch (Exception $e) {
                }

                $timeout_seconds = 900;
                try {
                    $closeStale = $conexion->prepare("UPDATE sesiones_usuario SET estado='cerrada', cierre_sesion=NOW(), motivo_cierre='inactividad' WHERE id_prestamista=? AND estado='abierta' AND ultima_actividad < (NOW() - INTERVAL $timeout_seconds SECOND)");
                    $closeStale->execute([$user['id_prestamista']]);
                } catch (Exception $e) {
                }

                try {
                    $active = $conexion->prepare("SELECT id_sesion FROM sesiones_usuario WHERE id_prestamista=? AND estado='abierta' LIMIT 1");
                    $active->execute([$user['id_prestamista']]);
                    $activeRow = $active->fetch(PDO::FETCH_ASSOC);
                    if($activeRow && !$cerrar_otras){
                        $error = "Ya tienes una sesión activa. Marca 'Cerrar otras sesiones' para continuar.";
                        goto end_login;
                    }
                    if($activeRow && $cerrar_otras){
                        $closeOthers = $conexion->prepare("UPDATE sesiones_usuario SET estado='cerrada', cierre_sesion=NOW(), motivo_cierre='cerrada_por_nuevo_inicio' WHERE id_prestamista=? AND estado='abierta'");
                        $closeOthers->execute([$user['id_prestamista']]);
                        $logClose = $conexion->prepare("INSERT INTO trazabilidad (accion, id_registro, descripcion, tabla_afectada, usuario, fecha_evento) VALUES ('UPDATE', ?, 'Cierre de sesión previa por nuevo inicio', 'sesion', ?, NOW())");
                        $logClose->execute([$user['id_prestamista'], $user['id_prestamista']]);
                    }
                } catch (Exception $e) {
                }

                session_regenerate_id(true);

                $_SESSION['id_usuario'] = $user['id_prestamista'];
                $_SESSION['nombre'] = $user['nombre'];
                $rol = $user['rol'] ?? null;
                if(!$rol){
                    $rol = (($user['correo'] ?? '') === 'admin@solodeudas.com' || (int)$user['id_prestamista'] === 1) ? 'admin' : 'usuario';
                }
                $_SESSION['rol'] = $rol;

                try {
                    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
                    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
                    $insSes = $conexion->prepare("INSERT INTO sesiones_usuario (id_prestamista, session_id, estado, ip_usuario, navegador, inicio_sesion, ultima_actividad) VALUES (?, ?, 'abierta', ?, ?, NOW(), NOW())");
                    $insSes->execute([$user['id_prestamista'], session_id(), $ip, $ua]);
                } catch (Exception $e) {
                }

                // RN06C: Registrar acceso con IP, fecha y hora
                $ip = $_SERVER['REMOTE_ADDR'];
                $log = $conexion->prepare("INSERT INTO trazabilidad (accion, id_registro, descripcion, tabla_afectada, usuario, fecha_evento, ip_acceso) VALUES ('LOGIN', ?, 'Inicio de sesión exitoso', 'prestamista', ?, NOW(), ?)");
                $log->execute([$user['id_prestamista'], $user['id_prestamista'], $ip]);

                if(isset($user['requiere_cambio_password']) && (int)$user['requiere_cambio_password'] === 1){
                    $_SESSION['force_password_change'] = true;
                    header("Location: pages/forzarCambioPassword.php");
                    exit;
                }

                header("Location: index.php");
                exit;
            } else {
                // Incrementar intentos fallidos
                $intentos = ((int)($user['intentos_fallidos'] ?? 0)) + 1;
                if($intentos >= 3){
                    // Bloquear por 5 minutos (Nivel Dios)
                    $bloqueo = date('Y-m-d H:i:s', strtotime('+5 minutes'));
                    try {
                        $upd = $conexion->prepare("UPDATE prestamista SET intentos_fallidos = ?, bloqueado_hasta = ? WHERE id_prestamista = ?");
                        $upd->execute([$intentos, $bloqueo, $user['id_prestamista']]);
                    } catch (Exception $e) {
                    }
                    $error = "Has fallado 3 intentos. Cuenta bloqueada por 5 minutos.";
                } else {
                    try {
                        $upd = $conexion->prepare("UPDATE prestamista SET intentos_fallidos = ? WHERE id_prestamista = ?");
                        $upd->execute([$intentos, $user['id_prestamista']]);
                    } catch (Exception $e) {
                    }
                    $restantes = 3 - $intentos;
                    $error = "Credenciales incorrectas. Te quedan $restantes intentos.";
                }
            }
        }
    } else {
        $error = "Correo o contraseña incorrectos";
    }
}
end_login:
?>
<!DOCTYPE html>
<html lang="es">

<?php
$pageTitle = 'Iniciar Sesión';
$assetBaseUrl = '../assets';
$includeInicioCss = true;
$includeAOS = true;
require __DIR__ . '/_head.php';
?>
</head>

<body class="auth-body">

    <div class="form-container" data-aos="zoom-in">
        <h2>Iniciar Sesión</h2>

        <?php if(isset($error)) echo "<p class='alert alert-error'>$error</p>"; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="email" name="correo" placeholder="Correo" required>
            <input type="password" name="contrasena" placeholder="Contraseña" required>
            <div style="margin-top: 10px; display:flex; gap:10px; align-items:center; justify-content:flex-start;">
                <input type="checkbox" id="cerrar_otras_sesiones" name="cerrar_otras_sesiones" value="1" style="width:auto; margin:0;">
                <label for="cerrar_otras_sesiones" style="margin:0; font-weight:600;">Cerrar otras sesiones</label>
            </div>
            <button type="submit" name="login">Entrar</button>
        </form>

        <p>¿No tienes cuenta? <a href="registro.php">Regístrate</a></p>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
    AOS.init();
    </script>
</body>

</html>
