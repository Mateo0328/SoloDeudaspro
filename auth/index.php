<?php
// index.php (dashboard)
require_once "../session_init.php";
session_start();
if(!isset($_SESSION['id_usuario'])){
    header("Location: login.php");
    exit;
}
require_once "../config/conexion.php";

$id_prestamista = $_SESSION['id_usuario'];

require_once __DIR__ . "/session_control.php";

if(isset($_SESSION['force_password_change']) && $_SESSION['force_password_change'] === true){
    header("Location: pages/forzarCambioPassword.php");
    exit;
}

// datos usuario
$stmt = $conexion->prepare("SELECT nombre, correo FROM prestamista WHERE id_prestamista = ?");
$stmt->execute([$id_prestamista]);
$user = $stmt->fetch();

// últimos préstamos
$prest = $conexion->prepare("SELECT p.id_prestamo, c.nombre AS cliente, p.monto_total, p.estado, p.fecha_inicio 
    FROM prestamo p JOIN cliente c ON p.id_cliente = c.id_cliente
    WHERE p.id_prestamista = ? ORDER BY p.fecha_inicio DESC LIMIT 5");
$prest->execute([$id_prestamista]);
$prestamos = $prest->fetchAll();

// totales (usa 0 si null)
$tot1 = $conexion->prepare("SELECT COALESCE(SUM(monto_total),0) AS total FROM prestamo WHERE id_prestamista = ?");
$tot1->execute([$id_prestamista]);
$total_prestado = $tot1->fetch()['total'];

$tot2 = $conexion->prepare("SELECT COALESCE(SUM(p.monto_pagado),0) AS total FROM pago p JOIN prestamo pr ON p.id_prestamo = pr.id_prestamo WHERE pr.id_prestamista = ?");
$tot2->execute([$id_prestamista]);
$total_recibido = $tot2->fetch()['total'];

$saldo_pendiente = $total_prestado - $total_recibido;

$rec = $conexion->prepare("SELECT COUNT(*) AS total FROM recordatorio r JOIN prestamo p ON r.id_prestamo = p.id_prestamo WHERE p.id_prestamista = ? AND r.estado = 'pendiente'");
$rec->execute([$id_prestamista]);
$recordatorios = $rec->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="es">
<?php
$pageTitle = 'Dashboard - SoloDeudas';
$assetBaseUrl = '../assets';
$includeChartJs = true;
require __DIR__ . '/_head.php';
?>
</head>
<body>
<?php
$navContext = 'auth';
$navActive = 'inicio';
require __DIR__ . '/_nav.php';
?>

<header class="hero">
    <h1>Controla tus préstamos de forma inteligente</h1>
    <p>Administra, controla y cobra sin perder dinero</p>
    <div class="hero-buttons">
        <a href="pages/agregarCliente.php" class="btn-secondary-hero btn-hero">Agregar Cliente</a>
        <a href="pages/agregarPrestamo.php" class="btn-hero">Registrar Préstamo</a>
    </div>
    <div class="circle circle1"></div>
    <div class="circle circle2"></div>
    <div class="circle circle3"></div>
</header>

<section class="resumen">
    <div class="card animate">
        <div class="card-icon" style="background: #E3F2FD; color: #1565C0;">
            <i class="fas fa-hand-holding-dollar"></i>
        </div>
        <div class="card-info">
            <h3>Total Prestado</h3>
            <p>$<?php echo number_format($total_prestado, 2); ?></p>
        </div>
    </div>
    <div class="card animate">
        <div class="card-icon" style="background: #E8F5E9; color: #2E7D32;">
            <i class="fas fa-piggy-bank"></i>
        </div>
        <div class="card-info">
            <h3>Total Recaudado</h3>
            <p>$<?php echo number_format($total_recibido, 2); ?></p>
        </div>
    </div>
    <div class="card animate">
        <div class="card-icon" style="background: #FFF3E0; color: #E65100;">
            <i class="fas fa-chart-pie"></i>
        </div>
        <div class="card-info">
            <h3>Saldo Pendiente</h3>
            <p>$<?php echo number_format($saldo_pendiente, 2); ?></p>
        </div>
    </div>
    <div class="card animate">
        <div class="card-icon" style="background: #F3E5F5; color: #7B1FA2;">
            <i class="fas fa-bell"></i>
        </div>
        <div class="card-info">
            <h3>Recordatorios</h3>
            <p><?php echo $recordatorios; ?></p>
        </div>
    </div>
</section>

<section class="ultimos-prestamos" style="display: flex; gap: 20px; flex-wrap: wrap;">
    <div class="card" style="flex: 1; min-width: 300px;">
        <h3 style="margin-bottom: 15px;">Estado de Cartera</h3>
        <canvas id="chartCartera" style="max-height: 250px;"></canvas>
    </div>
    <div class="card" style="flex: 2; min-width: 400px;">
        <h3 style="margin-bottom: 15px;">Últimos Préstamos</h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Monto</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($prestamos as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['cliente']); ?></td>
                        <td>$<?php echo number_format($r['monto_total'], 2); ?></td>
                        <td>
                            <span class="status-badge <?php echo htmlspecialchars($r['estado']); ?>">
                                <?php echo ucfirst(htmlspecialchars($r['estado'])); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const currentTheme = localStorage.getItem('theme') || 'light';
    const textColor = currentTheme === 'dark' ? '#f1f5f9' : '#444';

    const ctx = document.getElementById('chartCartera').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Recaudado', 'Pendiente'],
            datasets: [{
                data: [<?php echo $total_recibido; ?>, <?php echo $saldo_pendiente; ?>],
                backgroundColor: ['#2E7D32', '#E65100'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: textColor
                    }
                }
            },
            cutout: '70%'
        }
    });
});
</script>

<footer>
    <p>&copy; <?php echo date("Y"); ?> SoloDeudas. Todos los derechos reservados.</p>
</footer>

<script src="../assets/js/main.js"></script>
</body>
</html>
