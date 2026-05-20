<?php
// landing.php (página pública)
require_once "session_init.php";
session_start();
if(isset($_SESSION['id_usuario'])){
    header("Location: auth/index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SoloDeudas!</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/landing.css?v=<?php echo filemtime(__DIR__ . '/assets/css/landing.css'); ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

</head>
<body>
<nav class="navbar">
    <div class="logo">SD</div>
    <ul class="nav-links">
        <li><a href="#beneficios">Beneficios</a></li>
        <li><a href="#como-funciona">Cómo funciona</a></li>
        <li><a href="#contacto">Contacto</a></li>
    </ul>
</nav>

<section class="hero">
    <h1>Controla tus préstamos de forma inteligente</h1>
    <p>Administra, controla y cobra sin perder dinero</p>
    <div class="hero-buttons">
        <a href="auth/registro.php" class="btn-secondary-hero btn-hero">Registrarme</a>
        <a href="auth/login.php" class="btn-hero">Iniciar Sesión</a>
    </div>
    <div class="circle circle1"></div>
    <div class="circle circle2"></div>
    <div class="circle circle3"></div>
</section>

<section id="beneficios" class="section-beneficios">
    <h2>Beneficios de SoloDeudas</h2>
    <p>Mantén tus préstamos organizados, recibe recordatorios y visualiza tu estado financiero de forma clara.</p>
    <div class="resumen">
        <div class="card">
            <div class="card-icon" style="background: #E3F2FD; color: #1565C0; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 1.5rem;">
                <i class="fas fa-hand-holding-dollar"></i>
            </div>
            <h3>Control total</h3>
            <p>Registra préstamos y pagos con rapidez y precisión.</p>
        </div>
        <div class="card">
            <div class="card-icon" style="background: #F3E5F5; color: #7B1FA2; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 1.5rem;">
                <i class="fas fa-bell"></i>
            </div>
            <h3>Recordatorios</h3>
            <p>No olvides cobrar, recibe avisos automáticos de vencimiento.</p>
        </div>
        <div class="card">
            <div class="card-icon" style="background: #E8F5E9; color: #2E7D32; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 1.5rem;">
                <i class="fas fa-chart-line"></i>
            </div>
            <h3>Reportes</h3>
            <p>Visualizaciones claras del dinero prestado y recaudado.</p>
        </div>
    </div>
</section>

<section id="como-funciona" class="section-como-funciona">
    <h2>Cómo funciona</h2>
    <div class="resumen">
        <div class="card">
            <div style="font-size: 2rem; color: var(--verde-oscuro); margin-bottom: 10px; font-weight: 700;">01</div>
            <h3>Registra un cliente</h3>
            <p>Guarda sus datos básicos y mantén su historial centralizado.</p>
        </div>
        <div class="card">
            <div style="font-size: 2rem; color: var(--verde-oscuro); margin-bottom: 10px; font-weight: 700;">02</div>
            <h3>Crea un préstamo</h3>
            <p>Define monto, tasa de interés y fecha de vencimiento en segundos.</p>
        </div>
        <div class="card">
            <div style="font-size: 2rem; color: var(--verde-oscuro); margin-bottom: 10px; font-weight: 700;">03</div>
            <h3>Monitorea pagos</h3>
            <p>Registra abonos, calcula saldos y visualiza tus ganancias.</p>
        </div>
    </div>
</section>

<section id="contacto" class="section-contacto">
    <h2>Contacto</h2>
    <p>Correo: soporte@solodeudas.com | Tel: +57 300 123 4567</p>
</section>

<footer>
    &copy; <?php echo date("Y"); ?> SoloDeudas. Todos los derechos reservados.
</footer>

<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
