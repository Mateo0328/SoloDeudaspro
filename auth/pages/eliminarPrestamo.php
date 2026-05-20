<?php
require_once "../../session_init.php";
session_start();
require __DIR__ . '/../../config/conexion.php';
if(!isset($_SESSION['id_usuario'])){
    header("Location: ../login.php");
    exit;
}

$id_prestamista = $_SESSION['id_usuario'];
require_once __DIR__ . "/../session_control.php";
if(isset($_SESSION['force_password_change']) && $_SESSION['force_password_change'] === true){
    header("Location: forzarCambioPassword.php");
    exit;
}
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    csrf_validate_post();
    $id = isset($_POST['id_prestamo']) ? (int)$_POST['id_prestamo'] : 0;
    $q = $conexion->prepare("SELECT id_prestamo FROM prestamo WHERE id_prestamo=? AND id_prestamista=?");
    $q->execute([$id, $id_prestamista]);
    if($q->fetch()){
        $del = $conexion->prepare("DELETE FROM prestamo WHERE id_prestamo=? AND id_prestamista=?");
        $del->execute([$id, $id_prestamista]);
    }
}
header("Location: prestamos.php");
exit;
