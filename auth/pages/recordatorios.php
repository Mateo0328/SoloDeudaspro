<?php
require_once "../../session_init.php";
session_start();
require __DIR__ . '/../../config/conexion.php';
if(!isset($_SESSION['id_usuario'])){
    header("Location: ../login.php");
    exit;
}

$id_prestamista = $_SESSION['id_usuario'];
$isAdmin = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin');

require_once __DIR__ . "/../session_control.php";

if(isset($_SESSION['force_password_change']) && $_SESSION['force_password_change'] === true){
    header("Location: forzarCambioPassword.php");
    exit;
}

$stmtUser = $conexion->prepare("SELECT nombre FROM prestamista WHERE id_prestamista = ?");
$stmtUser->execute([$id_prestamista]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

$f_prestamista = $isAdmin ? (filter_input(INPUT_GET, 'prestamista', FILTER_VALIDATE_INT) ?: null) : null;

$sql = "SELECT r.id_recordatorio, c.nombre AS cliente, r.fecha_programada, r.medio, r.estado, p.id_prestamo, p.id_prestamista, pr.nombre AS prestamista_nombre
        FROM recordatorio r
        JOIN prestamo p ON r.id_prestamo = p.id_prestamo
        JOIN cliente c ON p.id_cliente = c.id_cliente
        LEFT JOIN prestamista pr ON p.id_prestamista = pr.id_prestamista";
$params = [];
if(!$isAdmin){
    $sql .= " WHERE p.id_prestamista = ?";
    $params[] = $id_prestamista;
} elseif($f_prestamista){
    $sql .= " WHERE p.id_prestamista = ?";
    $params[] = $f_prestamista;
}
$sql .= " ORDER BY r.fecha_programada DESC, r.id_recordatorio DESC";
$q = $conexion->prepare($sql);
$q->execute($params);
$rows = $q->fetchAll(PDO::FETCH_ASSOC);

$prestamosSel = null;
if($isAdmin){
    $prestamosSel = $conexion->prepare("SELECT p.id_prestamo, p.id_prestamista, c.nombre AS cliente, pr.nombre AS prestamista_nombre
                                        FROM prestamo p
                                        JOIN cliente c ON p.id_cliente = c.id_cliente
                                        LEFT JOIN prestamista pr ON p.id_prestamista = pr.id_prestamista
                                        ORDER BY p.id_prestamo DESC");
    $prestamosSel->execute();
} else {
    $prestamosSel = $conexion->prepare("SELECT p.id_prestamo, p.id_prestamista, c.nombre AS cliente
                                        FROM prestamo p
                                        JOIN cliente c ON p.id_cliente = c.id_cliente
                                        WHERE p.id_prestamista = ?
                                        ORDER BY p.id_prestamo DESC");
    $prestamosSel->execute([$id_prestamista]);
}
$prestamosList = $prestamosSel->fetchAll(PDO::FETCH_ASSOC);

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    csrf_validate_post();
    if(isset($_POST['crear'])){
        $id_prestamo = (int)$_POST['id_prestamo'];
        $fecha_programada = $_POST['fecha_programada'];
        $medio = $_POST['medio'];
        $chkSql = "SELECT id_prestamo FROM prestamo WHERE id_prestamo=?";
        $chkParams = [$id_prestamo];
        if(!$isAdmin){
            $chkSql .= " AND id_prestamista=?";
            $chkParams[] = $id_prestamista;
        }
        $chk = $conexion->prepare($chkSql);
        $chk->execute($chkParams);
        if($chk->fetchColumn()){
            $ins = $conexion->prepare("CALL sp_crear_recordatorio(?,?,?)");
            $ins->execute([$id_prestamo, $fecha_programada, $medio]);
        }
    }
    if(isset($_POST['completar'])){
        $id_recordatorio = (int)$_POST['id_recordatorio'];
        if($isAdmin){
            $up = $conexion->prepare("UPDATE recordatorio SET estado='completado' WHERE id_recordatorio=?");
            $up->execute([$id_recordatorio]);
        } else {
            $up = $conexion->prepare("UPDATE recordatorio r JOIN prestamo p ON r.id_prestamo=p.id_prestamo SET r.estado='completado' WHERE r.id_recordatorio=? AND p.id_prestamista=?");
            $up->execute([$id_recordatorio, $id_prestamista]);
        }
    }
    if(isset($_POST['reabrir'])){
        $id_recordatorio = (int)$_POST['id_recordatorio'];
        if($isAdmin){
            $up = $conexion->prepare("UPDATE recordatorio SET estado='pendiente' WHERE id_recordatorio=?");
            $up->execute([$id_recordatorio]);
        } else {
            $up = $conexion->prepare("UPDATE recordatorio r JOIN prestamo p ON r.id_prestamo=p.id_prestamo SET r.estado='pendiente' WHERE r.id_recordatorio=? AND p.id_prestamista=?");
            $up->execute([$id_recordatorio, $id_prestamista]);
        }
    }
    if(isset($_POST['eliminar'])){
        $id_recordatorio = (int)$_POST['id_recordatorio'];
        if($isAdmin){
            $del = $conexion->prepare("DELETE FROM recordatorio WHERE id_recordatorio=?");
            $del->execute([$id_recordatorio]);
        } else {
            $del = $conexion->prepare("DELETE r FROM recordatorio r JOIN prestamo p ON r.id_prestamo=p.id_prestamo WHERE r.id_recordatorio=? AND p.id_prestamista=?");
            $del->execute([$id_recordatorio, $id_prestamista]);
        }
    }
    $q->execute($params);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">

<?php
$pageTitle = 'Recordatorios - SoloDeudas';
$assetBaseUrl = '../../assets';
require __DIR__ . '/../_head.php';
?>
</head>

<body>
    <?php
    $navContext = 'pages';
    $navActive = 'recordatorios';
    require __DIR__ . '/../_nav.php';
    ?>

    <section class="section-container">
    <div class="section-header">
        <div class="header-title">
            <i class="fas fa-bell"></i>
            <h2>Recordatorios</h2>
        </div>
        <div class="header-actions">
            <a href="prestamos.php" class="btn-secondary">
                <i class="fas fa-hand-holding-usd"></i> Ir a Préstamos
            </a>
        </div>
    </div>

    <?php if($isAdmin): ?>
        <div class="card" style="margin-bottom: 20px;">
            <form method="get" class="filter-form">
                <div class="form-group">
                    <label><i class="fas fa-user-tie"></i> Prestamista (ID)</label>
                    <input type="number" name="prestamista" value="<?php echo htmlspecialchars((string)($f_prestamista ?? '')); ?>" placeholder="ID prestamista">
                </div>
                <div class="form-actions" style="display:flex; gap:10px; align-items:flex-end;">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                    <a href="recordatorios.php" class="btn-secondary">
                        <i class="fas fa-sync-alt"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <div class="card" style="margin-bottom: 20px;">
        <div class="card-header" style="margin-bottom: 15px;">
            <h3 style="margin:0;"><i class="fas fa-plus-circle"></i> Crear Recordatorio</h3>
        </div>
        <form method="post" class="filter-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="form-group">
                <label>Préstamo</label>
                <select name="id_prestamo" required>
                    <option value="">Selecciona</option>
                    <?php foreach($prestamosList as $p): ?>
                        <?php
                            $label = (string)($p['id_prestamo'] ?? '');
                            $clienteNombre = trim((string)($p['cliente'] ?? ''));
                            if($clienteNombre !== ''){
                                $label .= " — " . $clienteNombre;
                            }
                            if($isAdmin){
                                $prestamistaNombre = trim((string)($p['prestamista_nombre'] ?? ''));
                                if($prestamistaNombre !== ''){
                                    $label .= " — " . $prestamistaNombre;
                                }
                            }
                        ?>
                        <option value="<?php echo htmlspecialchars($p['id_prestamo']); ?>">
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Fecha programada</label>
                <input type="date" name="fecha_programada" required>
            </div>
            <div class="form-group">
                <label>Medio</label>
                <select name="medio" required>
                    <option value="whatsapp">WhatsApp</option>
                    <option value="sms">SMS</option>
                    <option value="email">Email</option>
                </select>
            </div>
            <div class="form-actions" style="display:flex; align-items:flex-end;">
                <button type="submit" name="crear" class="btn-primary">
                    <i class="fas fa-save"></i> Crear Recordatorio
                </button>
            </div>
        </form>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <?php if($isAdmin): ?>
                        <th>Prestamista</th>
                    <?php endif; ?>
                    <th>Cliente</th>
                    <th>Fecha programada</th>
                    <th>Medio</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if($rows): ?>
                    <?php foreach($rows as $r): ?>
                        <tr class="recordatorio-row <?php echo htmlspecialchars($r['estado']); ?>">
                            <?php if($isAdmin): ?>
                                <td><?php echo htmlspecialchars($r['prestamista_nombre'] ?? ''); ?></td>
                            <?php endif; ?>
                            <td><strong><?php echo htmlspecialchars($r['cliente']); ?></strong></td>
                            <td><?php echo htmlspecialchars($r['fecha_programada']); ?></td>
                            <td>
                                <span class="badge badge-info">
                                    <?php 
                                        $icon = 'fa-comment';
                                        if($r['medio'] === 'whatsapp') $icon = 'fa-whatsapp';
                                        if($r['medio'] === 'email') $icon = 'fa-envelope';
                                        if($r['medio'] === 'sms') $icon = 'fa-sms';
                                    ?>
                                    <i class="fab <?php echo $icon; ?> fas"></i> <?php echo ucfirst(htmlspecialchars($r['medio'])); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?php echo htmlspecialchars($r['estado']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($r['estado'])); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <?php if($r['estado']!=='completado'): ?>
                                        <form method="post" style="display:inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="id_recordatorio" value="<?php echo htmlspecialchars($r['id_recordatorio']); ?>">
                                            <button type="submit" name="completar" class="btn-icon btn-save" title="Completar">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" style="display:inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="id_recordatorio" value="<?php echo htmlspecialchars($r['id_recordatorio']); ?>">
                                            <button type="submit" name="reabrir" class="btn-icon btn-info" title="Reabrir">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar este recordatorio?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="id_recordatorio" value="<?php echo htmlspecialchars($r['id_recordatorio']); ?>">
                                        <button type="submit" name="eliminar" class="btn-icon btn-delete" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="<?php echo $isAdmin ? 6 : 5; ?>" class="text-center">No hay recordatorios para los filtros seleccionados.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> SoloDeudas. Todos los derechos reservados.</p>
        <p><a href="../index.php">Volver al inicio</a></p>
    </footer>

    <script src="../../assets/js/main.js"></script>
</body>

</html>
