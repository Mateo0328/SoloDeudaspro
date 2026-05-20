<?php
// index.php - Redirección a la landing page pública o al dashboard interno
require_once "session_init.php";
session_start();

if(isset($_SESSION['id_usuario'])){
    header("Location: auth/index.php");
} else {
    header("Location: landing.php");
}
exit;
?>