<?php
require_once "../session_init.php";
session_start();
require __DIR__ . '/../config/conexion.php';

if(isset($_SESSION['id_usuario'])){
    try {
        $id_prestamista = (int)$_SESSION['id_usuario'];
        $sid = session_id();
        $upd = $conexion->prepare("UPDATE sesiones_usuario SET estado='cerrada', cierre_sesion=NOW(), motivo_cierre='logout' WHERE id_prestamista=? AND session_id=? AND estado='abierta'");
        $upd->execute([$id_prestamista, $sid]);
        $log = $conexion->prepare("INSERT INTO trazabilidad (accion, id_registro, descripcion, tabla_afectada, usuario, fecha_evento) VALUES ('UPDATE', ?, 'Cierre de sesión', 'sesion', ?, NOW())");
        $log->execute([$id_prestamista, $id_prestamista]);
    } catch (Exception $e) {
    }
}
session_unset();
session_destroy();
header("Location: ../landing.php");
exit;
