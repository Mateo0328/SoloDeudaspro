<?php
require_once "../../session_init.php";
session_start();
require __DIR__ . '/../../config/conexion.php';

if(!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin'){
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../session_control.php";

$msg = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cerrar_sesion'])){
    csrf_validate_post();
    $id_sesion = isset($_POST['id_sesion']) ? (int)$_POST['id_sesion'] : 0;
    if($id_sesion > 0){
        try {
            $stmt = $conexion->prepare("SELECT s.id_sesion, s.id_prestamista, p.nombre, p.correo FROM sesiones_usuario s JOIN prestamista p ON p.id_prestamista = s.id_prestamista WHERE s.id_sesion=? AND s.estado='abierta' LIMIT 1");
            $stmt->execute([$id_sesion]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if($row){
                $upd = $conexion->prepare("UPDATE sesiones_usuario SET estado='cerrada', cierre_sesion=NOW(), motivo_cierre='cerrada_por_admin' WHERE id_sesion=?");
                $upd->execute([$id_sesion]);

                $log = $conexion->prepare("INSERT INTO trazabilidad (accion, id_registro, descripcion, tabla_afectada, usuario, fecha_evento) VALUES ('UPDATE', ?, ?, 'sesion', ?, NOW())");
                $descripcion = "Admin cerró sesión. Usuario ID {$row['id_prestamista']} ({$row['correo']})";
                $log->execute([(int)$row['id_prestamista'], $descripcion, $_SESSION['id_usuario']]);

                $msg = "Sesión cerrada correctamente";
            } else {
                $error = "Sesión no encontrada o ya cerrada";
            }
        } catch (Exception $e) {
            $error = "Error al cerrar sesión: " . $e->getMessage();
        }
    } else {
        $error = "Sesión inválida";
    }
}

$q = $conexion->query("SELECT s.id_sesion, s.id_prestamista, p.nombre, p.correo, s.ip_usuario, s.navegador, s.inicio_sesion, s.ultima_actividad, s.estado
                       FROM sesiones_usuario s
                       JOIN prestamista p ON p.id_prestamista = s.id_prestamista
                       WHERE s.estado='abierta'
                       ORDER BY s.ultima_actividad DESC");
$sesiones = $q->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">

<?php
$pageTitle = 'Sesiones Activas - SoloDeudas';
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
            <i class="fas fa-users-cog"></i>
            <h2>Sesiones Activas</h2>
        </div>
        <div class="header-actions">
            <a href="../index.php" class="btn-secondary">
                <i class="fas fa-home"></i> Inicio
            </a>
        </div>
    </div>

    <?php if($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Correo</th>
                    <th>IP</th>
                    <th>Navegador</th>
                    <th>Inicio</th>
                    <th>Última actividad</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if($sesiones): ?>
                <?php foreach($sesiones as $s): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($s['nombre']); ?></strong>
                        <br><small class="text-muted">ID: <?php echo (int)$s['id_prestamista']; ?></small>
                    </td>
                    <td><?php echo htmlspecialchars($s['correo']); ?></td>
                    <td>
                        <span class="badge badge-info">
                            <i class="fas fa-network-wired"></i> <?php echo htmlspecialchars($s['ip_usuario'] ?? ''); ?>
                        </span>
                    </td>
                    <td style="font-size: 0.8rem; color: #666; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($s['navegador'] ?? ''); ?>">
                        <?php echo htmlspecialchars($s['navegador'] ?? ''); ?>
                    </td>
                    <td style="white-space:nowrap;"><?php echo htmlspecialchars($s['inicio_sesion']); ?></td>
                    <td style="white-space:nowrap;">
                        <span class="status-badge activo" style="padding: 4px 8px;">
                            <?php echo htmlspecialchars($s['ultima_actividad']); ?>
                        </span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="id_sesion" value="<?php echo (int)$s['id_sesion']; ?>">
                                <button type="submit" name="cerrar_sesion" class="btn-danger" style="padding: 6px 12px; font-size: 0.8rem;" title="Forzar Cierre de Sesión">
                                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center">No hay sesiones activas.</td>
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
