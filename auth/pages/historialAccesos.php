<?php
require_once "../../session_init.php";
session_start();
require __DIR__ . '/../../config/conexion.php';

// RN06B: Solo administradores pueden consultar el historial de accesos.
if(!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin'){
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../session_control.php";

if(isset($_SESSION['force_password_change']) && $_SESSION['force_password_change'] === true){
    header("Location: forzarCambioPassword.php");
    exit;
}

$query = "SELECT t.fecha_evento, t.ip_acceso, p.nombre, p.correo 
          FROM trazabilidad t 
          JOIN prestamista p ON t.id_registro = p.id_prestamista 
          WHERE t.accion = 'LOGIN' 
          ORDER BY t.fecha_evento DESC";
$stmt = $conexion->query($query);
$accesos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<?php
$pageTitle = 'Historial de Accesos - SoloDeudas';
$assetBaseUrl = '../../assets';
require __DIR__ . '/../_head.php';
?>
</head>
<body>
<?php
$navContext = 'pages';
$navActive = 'admin';
require __DIR__ . '/../_nav.php';
?>

<section class="section-container">
    <div class="section-header">
        <div class="header-title">
            <i class="fas fa-sign-in-alt"></i>
            <h2>Historial de Accesos al Sistema</h2>
        </div>
        <div class="header-actions">
            <a href="../index.php" class="btn-secondary">
                <i class="fas fa-home"></i> Inicio
            </a>
        </div>
    </div>
    
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Usuario</th>
                    <th>Correo</th>
                    <th>Fecha y Hora</th>
                    <th>Dirección IP</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $counter = 1;
                foreach($accesos as $acceso): 
                ?>
                <tr>
                    <td><?php echo $counter++; ?></td>
                    <td><strong><?php echo htmlspecialchars($acceso['nombre']); ?></strong></td>
                    <td><?php echo htmlspecialchars($acceso['correo']); ?></td>
                    <td><?php echo $acceso['fecha_evento']; ?></td>
                    <td>
                        <span class="badge badge-info">
                            <i class="fas fa-network-wired"></i> <?php echo htmlspecialchars($acceso['ip_acceso'] ?? 'N/D'); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($accesos)): ?>
                <tr>
                    <td colspan="5" class="text-center">No hay registros de acceso aún.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<footer>
    <p>&copy; <?php echo date("Y"); ?> SoloDeudas. Todos los derechos reservados.</p>
</footer>

<script src="../../assets/js/main.js"></script>
</body>
</html>
