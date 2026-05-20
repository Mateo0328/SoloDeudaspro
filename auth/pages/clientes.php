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

$msg = '';
$error = '';

if(($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'){
    csrf_validate_post();
}

// Cambiar estado de cliente
if(isset($_POST['cambiar_estado_cliente'])){
    $id_cliente = filter_input(INPUT_POST, 'id_cliente', FILTER_VALIDATE_INT);
    $nuevo_estado = $_POST['nuevo_estado'] ?? '';
    
    if(!$id_cliente){
        $error = "Cliente inválido.";
    } elseif(!in_array($nuevo_estado, ['activo', 'inactivo'], true)){
        $error = "Estado inválido.";
    } else {
        try {
            $check = $conexion->prepare("SELECT id_prestamista FROM cliente WHERE id_cliente = ?");
            $check->execute([$id_cliente]);
            $owner = $check->fetchColumn();
            
            if($isAdmin || (int)$owner === (int)$id_prestamista){
                $up = $conexion->prepare("UPDATE cliente SET estado = ? WHERE id_cliente = ?");
                $up->execute([$nuevo_estado, $id_cliente]);
                
                // Trazabilidad
                $log = $conexion->prepare("INSERT INTO trazabilidad (accion, id_registro, descripcion, tabla_afectada, usuario, fecha_evento) VALUES ('UPDATE', ?, ?, 'cliente', ?, NOW())");
                $log->execute([$id_cliente, "Cambio de estado a $nuevo_estado", $id_prestamista]);
                
                $msg = "Estado del cliente actualizado correctamente.";
            } else {
                $error = "No tiene permisos para modificar este cliente.";
            }
        } catch (Exception $e) {
            $error = "Error al actualizar el estado.";
        }
    }
}

// Filtros
$f_search = trim($_GET['search'] ?? '');
$f_estado = $_GET['estado'] ?? '';
$f_prestamista = $isAdmin ? (filter_input(INPUT_GET, 'prestamista', FILTER_VALIDATE_INT) ?: null) : null;

// Consulta de clientes
$sql = "SELECT c.*, p.nombre as prestamista_nombre, 
        (SELECT COUNT(*) FROM prestamo WHERE id_cliente = c.id_cliente) as total_prestamos,
        (SELECT SUM(monto_total) FROM prestamo WHERE id_cliente = c.id_cliente) as monto_total_prestado
        FROM cliente c 
        LEFT JOIN prestamista p ON c.id_prestamista = p.id_prestamista
        WHERE 1=1";
$params = [];

if(!$isAdmin){
    $sql .= " AND c.id_prestamista = ?";
    $params[] = $id_prestamista;
} elseif($f_prestamista){
    $sql .= " AND c.id_prestamista = ?";
    $params[] = $f_prestamista;
}

if($f_search !== ''){
    $sql .= " AND (c.nombre LIKE ? OR c.cedula LIKE ? OR c.telefono LIKE ?)";
    $params[] = "%$f_search%";
    $params[] = "%$f_search%";
    $params[] = "%$f_search%";
}

if($f_estado !== ''){
    $sql .= " AND c.estado = ?";
    $params[] = $f_estado;
}

$sql .= " ORDER BY c.nombre ASC";

$q = $conexion->prepare($sql);
$q->execute($params);
$clientes = $q->fetchAll(PDO::FETCH_ASSOC);

// Lista de prestamistas para el filtro de admin
$prestamistas = [];
if($isAdmin){
    $prestamistas = $conexion->query("SELECT id_prestamista, nombre FROM prestamista ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="es">
<?php
$pageTitle = 'Clientes - SoloDeudas';
$assetBaseUrl = '../../assets';
require __DIR__ . '/../_head.php';
?>
</head>
<body>
<?php
$navContext = 'pages';
$navActive = 'clientes';
require __DIR__ . '/../_nav.php';
?>

<section class="section-container">
    <div class="section-header">
        <div class="header-title">
            <i class="fas fa-users"></i>
            <h2>Gestión de Clientes</h2>
        </div>
        <div class="header-actions">
            <a href="agregarCliente.php" class="btn-primary">
                <i class="fas fa-user-plus"></i> Nuevo Cliente
            </a>
            <a href="../index.php" class="btn-secondary">
                <i class="fas fa-home"></i> Inicio
            </a>
        </div>
    </div>

    <?php if($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="card" style="margin-bottom: 20px;">
        <form method="get" class="filter-form">
            <div class="form-group" style="flex: 2;">
                <label><i class="fas fa-search"></i> Buscar</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($f_search); ?>" placeholder="Nombre, Cédula o Teléfono...">
            </div>

            <?php if($isAdmin): ?>
                <div class="form-group">
                    <label><i class="fas fa-user-tie"></i> Prestamista</label>
                    <select name="prestamista">
                        <option value="">Todos</option>
                        <?php foreach($prestamistas as $p): ?>
                            <option value="<?php echo htmlspecialchars($p['id_prestamista']); ?>" <?php echo ($f_prestamista && (int)$f_prestamista === (int)$p['id_prestamista']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label><i class="fas fa-filter"></i> Estado</label>
                <select name="estado">
                    <option value="">Todos</option>
                    <option value="activo" <?php echo $f_estado === 'activo' ? 'selected' : ''; ?>>Activo</option>
                    <option value="inactivo" <?php echo $f_estado === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                </select>
            </div>

            <div class="form-actions" style="display:flex; align-items:flex-end; gap:10px;">
                <button type="submit" class="btn-primary">Filtrar</button>
                <a href="clientes.php" class="btn-secondary">Limpiar</a>
            </div>
        </form>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Cédula</th>
                    <th>Nombre</th>
                    <th>Teléfono</th>
                    <?php if($isAdmin): ?>
                        <th>Prestamista</th>
                    <?php endif; ?>
                    <th>Préstamos</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if($clientes): ?>
                <?php foreach($clientes as $c): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($c['cedula'] ?? 'N/A'); ?></td>
                        <td><strong><?php echo htmlspecialchars($c['nombre']); ?></strong></td>
                        <td><?php echo htmlspecialchars($c['telefono'] ?? 'N/A'); ?></td>
                        <?php if($isAdmin): ?>
                            <td><?php echo htmlspecialchars($c['prestamista_nombre'] ?? 'N/A'); ?></td>
                        <?php endif; ?>
                        <td>
                            <div style="display: flex; flex-direction: column;">
                                <span><i class="fas fa-file-invoice-dollar"></i> <?php echo (int)$c['total_prestamos']; ?></span>
                                <?php if($c['total_prestamos'] > 0): ?>
                                    <small class="text-muted">$<?php echo number_format((float)$c['monto_total_prestado'],2); ?></small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="status-badge <?php echo $c['estado']; ?>">
                                <?php echo ucfirst($c['estado']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="prestamos.php?cliente=<?php echo urlencode($c['id_cliente']); ?>" class="btn-icon btn-info" title="Ver préstamos">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <form method="post" style="display:inline-flex; gap: 5px;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="id_cliente" value="<?php echo htmlspecialchars($c['id_cliente']); ?>">
                                    <input type="hidden" name="cambiar_estado_cliente" value="1">
                                    <select name="nuevo_estado" onchange="this.form.submit()" class="compact-select">
                                        <option value="activo" <?php echo $c['estado'] === 'activo' ? 'selected' : ''; ?>>Activo</option>
                                        <option value="inactivo" <?php echo $c['estado'] === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                                    </select>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="<?php echo $isAdmin ? 7 : 6; ?>" class="text-center">No se encontraron clientes.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<script src="../../assets/js/main.js"></script>
</body>
</html>
