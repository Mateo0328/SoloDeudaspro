<?php
if(!isset($_SESSION['id_usuario'])){
    return;
}

$id_prestamista = (int)$_SESSION['id_usuario'];
$session_id = session_id();
$timeout_seconds = 900;

try {
    $closeStale = $conexion->prepare("UPDATE sesiones_usuario SET estado='cerrada', cierre_sesion=NOW(), motivo_cierre='inactividad' WHERE id_prestamista=? AND estado='abierta' AND ultima_actividad < (NOW() - INTERVAL $timeout_seconds SECOND)");
    $closeStale->execute([$id_prestamista]);
} catch (Exception $e) {
}

$current = null;
try {
    $stmt = $conexion->prepare("SELECT id_sesion, estado, ultima_actividad, TIMESTAMPDIFF(SECOND, ultima_actividad, NOW()) AS idle_seconds FROM sesiones_usuario WHERE id_prestamista=? AND session_id=? ORDER BY id_sesion DESC LIMIT 1");
    $stmt->execute([$id_prestamista, $session_id]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}

if(!$current || ($current['estado'] ?? '') !== 'abierta'){
    if(isset($_SESSION['id_usuario'])){
        session_unset();
        session_destroy();
    }
    $isPages = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/auth/pages/') !== false;
    $login = $isPages ? '../login.php' : 'login.php';
    header("Location: " . $login . "?error=sesion_cerrada");
    exit;
}

$idleSeconds = isset($current['idle_seconds']) ? (int)$current['idle_seconds'] : null;
if($idleSeconds !== null && $idleSeconds > $timeout_seconds){
    try {
        $upd = $conexion->prepare("UPDATE sesiones_usuario SET estado='cerrada', cierre_sesion=NOW(), motivo_cierre='inactividad' WHERE id_sesion=?");
        $upd->execute([$current['id_sesion']]);
        $log = $conexion->prepare("INSERT INTO trazabilidad (accion, id_registro, descripcion, tabla_afectada, usuario, fecha_evento) VALUES ('UPDATE', ?, 'Cierre automático por inactividad', 'sesion', ?, NOW())");
        $log->execute([$id_prestamista, $id_prestamista]);
    } catch (Exception $e) {
    }

    session_unset();
    session_destroy();
    $isPages = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/auth/pages/') !== false;
    $login = $isPages ? '../login.php' : 'login.php';
    header("Location: " . $login . "?error=inactividad");
    exit;
}

try {
    $touch = $conexion->prepare("UPDATE sesiones_usuario SET ultima_actividad=NOW() WHERE id_sesion=?");
    $touch->execute([$current['id_sesion']]);
} catch (Exception $e) {
}
