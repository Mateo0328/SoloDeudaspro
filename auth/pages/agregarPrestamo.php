<?php
// pages/agregarPrestamo.php
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

$clientes_stmt = null;
if($isAdmin){
    $clientes_stmt = $conexion->prepare("SELECT c.id_cliente, c.nombre, p.nombre AS prestamista_nombre FROM cliente c LEFT JOIN prestamista p ON c.id_prestamista = p.id_prestamista ORDER BY p.nombre ASC, c.nombre ASC");
    $clientes_stmt->execute();
} else {
    $clientes_stmt = $conexion->prepare("SELECT id_cliente, nombre FROM cliente WHERE id_prestamista = ? ORDER BY nombre ASC");
    $clientes_stmt->execute([$id_prestamista]);
}
$clientes = $clientes_stmt->fetchAll();

$success = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar'])){
    csrf_validate_post();
    try {

        $id_cliente = filter_input(INPUT_POST, 'cliente', FILTER_VALIDATE_INT);
        $monto_total = (float)($_POST['monto_total'] ?? 0);
        $tasa_interes = (float)($_POST['tasa_interes'] ?? 0);
        $fecha_inicio = trim($_POST['fecha_inicio'] ?? '');
        $fecha_vencimiento = trim($_POST['fecha_vencimiento'] ?? '');
        $observaciones = trim($_POST['observaciones'] ?? '');

        // VALIDACIONES

        if(!$id_cliente){
            throw new Exception("Debes seleccionar un cliente válido");
        }

        $id_prestamista_prestamo = (int)$id_prestamista;
        if($isAdmin){
            $clienteRowStmt = $conexion->prepare("SELECT id_prestamista FROM cliente WHERE id_cliente = ? LIMIT 1");
            $clienteRowStmt->execute([$id_cliente]);
            $clienteRow = $clienteRowStmt->fetch(PDO::FETCH_ASSOC);
            if(!$clienteRow){
                throw new Exception("El cliente seleccionado no existe en el sistema");
            }
            $id_prestamista_prestamo = (int)($clienteRow['id_prestamista'] ?? 0);
            if($id_prestamista_prestamo <= 0){
                throw new Exception("El cliente seleccionado no tiene un prestamista asociado");
            }
        } else {
            $clienteExiste = $conexion->prepare("SELECT 1 FROM cliente WHERE id_cliente = ? AND id_prestamista = ? LIMIT 1");
            $clienteExiste->execute([$id_cliente, $id_prestamista]);
            if(!$clienteExiste->fetchColumn()){
                throw new Exception("El cliente seleccionado no existe en el sistema");
            }
        }

        if($monto_total <= 0){
            throw new Exception("El monto debe ser mayor que 0");
        }

        if($tasa_interes < 0 || $tasa_interes > 20){
            throw new Exception("La tasa de interés no puede ser mayor al 20%");
        }

        if($fecha_vencimiento <= $fecha_inicio){
            throw new Exception("La fecha de vencimiento debe ser mayor que la fecha de inicio");
        }

        $stmt = $conexion->prepare("INSERT INTO prestamo (id_cliente, id_prestamista, monto_total, tasa_interes, fecha_inicio, fecha_vencimiento, estado, observaciones) VALUES (?, ?, ?, ?, ?, ?, 'pendiente', ?)");
        $stmt->execute([$id_cliente, $id_prestamista_prestamo, $monto_total, $tasa_interes, $fecha_inicio, $fecha_vencimiento, $observaciones]);

        $id_prestamo = (int)$conexion->lastInsertId();

        try {
            $descripcion = $isAdmin ? ("Registro de préstamo (admin) para prestamista " . $id_prestamista_prestamo) : "Registro de préstamo";
            $log = $conexion->prepare("INSERT INTO trazabilidad (accion, id_registro, descripcion, tabla_afectada, usuario, fecha_evento) VALUES ('INSERT', ?, ?, 'prestamo', ?, NOW())");
            $log->execute([$id_prestamo, $descripcion, $id_prestamista]);
        } catch (Exception $e) {
        }

        $success = "Préstamo creado en estado pendiente";

    } catch (Exception $e) {
        $error = "Error al agregar préstamo: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<?php
$pageTitle = 'Agregar Préstamo - SoloDeudas';
$assetBaseUrl = '../../assets';
$includeAOS = true;
require __DIR__ . '/../_head.php';
?>
</head>

<body>

<?php
$navContext = 'pages';
$navActive = 'prestamos';
require __DIR__ . '/../_nav.php';
?>

<section class="section-container">
    <div class="section-header">
        <div class="header-title">
            <i class="fas fa-plus-circle"></i>
            <h2>Agregar Préstamo</h2>
        </div>
        <div class="header-actions">
            <a href="prestamos.php" class="btn-secondary">
                <i class="fas fa-hand-holding-usd"></i> Volver a Préstamos
            </a>
        </div>
    </div>

    <?php if($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="card" style="max-width: 800px; margin: 0 auto;">
        <form method="post" class="filter-form" style="display: flex; flex-direction: column; gap: 20px;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="form-group">
                <label><i class="fas fa-user"></i> Cliente</label>
                <select name="cliente" required>
                    <option value="">Selecciona un cliente</option>
                    <?php foreach($clientes as $c): ?>
                        <option value="<?php echo htmlspecialchars($c['id_cliente']); ?>">
                            <?php
                                $label = (string)($c['nombre'] ?? '');
                                if($isAdmin){
                                    $prestamistaNombre = trim((string)($c['prestamista_nombre'] ?? ''));
                                    if($prestamistaNombre !== ''){
                                        $label .= " — " . $prestamistaNombre;
                                    }
                                }
                                echo htmlspecialchars($label);
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label><i class="fas fa-dollar-sign"></i> Monto Total</label>
                    <input type="number" name="monto_total" step="0.01" min="1" required placeholder="0.00">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-percentage"></i> Tasa de Interés (%)</label>
                    <input type="number" name="tasa_interes" step="0.01" max="20" min="0" required placeholder="0.00">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label><i class="fas fa-calendar-alt"></i> Fecha de Inicio</label>
                    <input type="date" name="fecha_inicio" required value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-calendar-check"></i> Fecha de Vencimiento</label>
                    <input type="date" name="fecha_vencimiento" required>
                </div>
            </div>

            <div class="form-group">
                <label><i class="fas fa-sticky-note"></i> Observaciones</label>
                <textarea name="observaciones" rows="3" placeholder="Detalles adicionales del préstamo..."></textarea>
            </div>

            <div class="form-actions" style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 10px;">
                <button type="submit" name="agregar" class="btn-primary">
                    <i class="fas fa-save"></i> Registrar Préstamo
                </button>
            </div>
        </form>
    </div>
</section>

<footer>
<p>SoloDeudas &copy; <?php echo date("Y"); ?></p>
</footer>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

<script>
AOS.init({
duration:900,
once:true
});
</script>

</body>
</html>
