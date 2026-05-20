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
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$msg = '';
$error = '';
$can_edit = true;

require_once __DIR__ . "/../session_control.php";

if(isset($_SESSION['force_password_change']) && $_SESSION['force_password_change'] === true){
    header("Location: forzarCambioPassword.php");
    exit;
}

$qSql = "SELECT p.id_prestamo, p.id_cliente, c.nombre AS cliente, p.monto_total, p.tasa_interes, p.fecha_inicio, p.fecha_vencimiento, p.estado, p.observaciones, p.id_prestamista
                          FROM prestamo p JOIN cliente c ON p.id_cliente = c.id_cliente
                          WHERE p.id_prestamo = ?";
$qParams = [$id];
if(!$isAdmin){
    $qSql .= " AND p.id_prestamista = ?";
    $qParams[] = $id_prestamista;
}
$q = $conexion->prepare($qSql);
$q->execute($qParams);
$prestamo = $q->fetch(PDO::FETCH_ASSOC);
$prestamoOriginal = $prestamo ? $prestamo : null;

if(!$prestamo){
    header("Location: prestamos.php");
    exit;
}

$isVigente = in_array((string)($prestamo['estado'] ?? ''), ['activo','vencido'], true);
$can_edit_prestamo = $can_edit && ($isAdmin || !$isVigente);

if(($prestamo['estado'] ?? '') === 'pagado'){
    $can_edit = false;
    $can_edit_prestamo = false;
    $error = "No se puede modificar un préstamo ya pagado";

    try {
        $log = $conexion->prepare("INSERT INTO trazabilidad (accion, id_registro, descripcion, tabla_afectada, usuario, fecha_evento) VALUES ('UPDATE', ?, ?, 'prestamo', ?, NOW())");
        $descripcion = "Intento de edición no permitido. Estado actual: " . ($prestamo['estado'] ?? '');
        $log->execute([$prestamo['id_prestamo'], $descripcion, $_SESSION['id_usuario']]);
    } catch (Exception $e) {
    }
}

$id_prestamista_prestamo = (int)($prestamo['id_prestamista'] ?? 0);
$clientes_stmt = $conexion->prepare("SELECT id_cliente, nombre FROM cliente WHERE id_prestamista = ? ORDER BY nombre ASC");
$clientes_stmt->execute([$id_prestamista_prestamo]);
$clientes = $clientes_stmt->fetchAll(PDO::FETCH_ASSOC);

try {
    $cstmt = $conexion->prepare("SELECT id_cliente, nombre, cedula, correo, telefono, direccion, contacto_estado FROM cliente WHERE id_cliente = ? LIMIT 1");
    $cstmt->execute([(int)($prestamo['id_cliente'] ?? 0)]);
    $clienteData = $cstmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $clienteData = null;
}

if(($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'){
    csrf_validate_post();
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar'])){
    if(($prestamo['estado'] ?? '') === 'pagado'){
        $can_edit = false;
        $can_edit_prestamo = false;
        $error = "No se puede modificar un préstamo ya pagado";
    } else {
        if(!$isAdmin && $isVigente){
            $error = "Solo se pueden modificar datos de contacto del cliente";
            goto end_post;
        }

        $id_cliente = filter_input(INPUT_POST, 'cliente', FILTER_VALIDATE_INT) ?: 0;
        $monto_total_raw = trim((string)($_POST['monto_total'] ?? ''));
        $tasa_interes_raw = trim((string)($_POST['tasa_interes'] ?? ''));
        $fecha_inicio = trim((string)($_POST['fecha_inicio'] ?? ''));
        $fecha_vencimiento = trim((string)($_POST['fecha_vencimiento'] ?? ''));
        $estado = $prestamo['estado'];
        if($isAdmin){
            $estadoPost = trim($_POST['estado'] ?? '');
            $permitidos = ['activo','pendiente','pagado','vencido','cancelado'];
            if(in_array($estadoPost, $permitidos, true)){
                if(($prestamo['estado'] ?? '') === 'pagado' && $estadoPost === 'activo'){
                    $error = "No se puede cambiar un préstamo pagado a estado activo";
                } else {
                    $estado = $estadoPost;
                }
            }
        }
        $observaciones = trim((string)($_POST['observaciones'] ?? ''));

        if($id_cliente <= 0){
            $error = "Cliente inválido";
            goto end_post;
        }

        if($monto_total_raw === '' || !is_numeric($monto_total_raw)){
            $error = "Monto inválido";
            goto end_post;
        }
        $monto_total = (float)$monto_total_raw;
        if($monto_total <= 0){
            $error = "El monto debe ser mayor que 0";
            goto end_post;
        }

        if($tasa_interes_raw === '' || !is_numeric($tasa_interes_raw)){
            $error = "Tasa inválida";
            goto end_post;
        }
        $tasa_interes = (float)$tasa_interes_raw;
        $tasaVal = (float)$tasa_interes;
        if($tasaVal < 0 || $tasaVal > 20){
            $error = "La tasa de interés no puede ser mayor al 20%";
            goto end_post;
        }
        $dtInicio = DateTime::createFromFormat('Y-m-d', $fecha_inicio);
        $dtVence = DateTime::createFromFormat('Y-m-d', $fecha_vencimiento);
        if(!$dtInicio || $dtInicio->format('Y-m-d') !== $fecha_inicio){
            $error = "Fecha de inicio inválida";
            goto end_post;
        }
        if(!$dtVence || $dtVence->format('Y-m-d') !== $fecha_vencimiento){
            $error = "Fecha de vencimiento inválida";
            goto end_post;
        }
        if($dtVence <= $dtInicio){
            $error = "El plazo del préstamo no puede ser negativo";
            goto end_post;
        }

        $clienteCheck = $conexion->prepare("SELECT 1 FROM cliente WHERE id_cliente = ? AND id_prestamista = ? LIMIT 1");
        $clienteCheck->execute([(int)$id_cliente, $id_prestamista_prestamo]);
        if(!$clienteCheck->fetchColumn()){
            $error = "El cliente seleccionado no existe en el sistema";
        } else {
            $cambios = [];
            if($prestamoOriginal){
                if((int)$prestamoOriginal['id_cliente'] !== (int)$id_cliente){
                    $cambios[] = "cliente";
                }
                if((string)$prestamoOriginal['monto_total'] !== (string)$monto_total){
                    $cambios[] = "monto_total";
                }
                if((string)$prestamoOriginal['tasa_interes'] !== (string)$tasa_interes){
                    $cambios[] = "tasa_interes";
                }
                if((string)$prestamoOriginal['fecha_inicio'] !== (string)$fecha_inicio){
                    $cambios[] = "fecha_inicio";
                }
                if((string)$prestamoOriginal['fecha_vencimiento'] !== (string)$fecha_vencimiento){
                    $cambios[] = "fecha_vencimiento";
                }
                if((string)$prestamoOriginal['estado'] !== (string)$estado){
                    $cambios[] = "estado";
                }
                if((string)($prestamoOriginal['observaciones'] ?? '') !== (string)($observaciones ?? '')){
                    $cambios[] = "observaciones";
                }
            }

            if(!$isAdmin){
                if(!$cambios){
                    $error = "No hay cambios para enviar";
                    goto end_post;
                }

                $payload = [
                    "id_cliente" => (int)$id_cliente,
                    "monto_total" => (float)$monto_total,
                    "tasa_interes" => (float)$tasa_interes,
                    "fecha_inicio" => (string)$fecha_inicio,
                    "fecha_vencimiento" => (string)$fecha_vencimiento,
                    "observaciones" => (string)$observaciones
                ];

                try {
                    $ins = $conexion->prepare("INSERT INTO ediciones_prestamo (id_prestamo, solicitado_por, cambios, estado) VALUES (?, ?, ?, 'pendiente')");
                    $ins->execute([$id, $id_prestamista, json_encode($payload, JSON_UNESCAPED_UNICODE)]);
                    $id_edicion = (int)$conexion->lastInsertId();

                    try {
                        $log = $conexion->prepare("INSERT INTO trazabilidad (accion, id_registro, descripcion, tabla_afectada, usuario, fecha_evento) VALUES ('INSERT', ?, ?, 'ediciones_prestamo', ?, NOW())");
                        $descripcion = 'Edición de préstamo solicitada (campos: ' . implode(', ', $cambios) . ')';
                        $log->execute([$id_edicion, $descripcion, $id_prestamista]);
                    } catch (Exception $e) {
                    }

                    $msg = "Edición enviada para aprobación";
                } catch (Exception $e) {
                    $error = "Error al enviar edición para aprobación";
                }
                goto end_post;
            }

            $upSql = "UPDATE prestamo SET id_cliente=?, monto_total=?, tasa_interes=?, fecha_inicio=?, fecha_vencimiento=?, estado=?, observaciones=? WHERE id_prestamo=?";
            $upParams = [(int)$id_cliente, $monto_total, $tasa_interes, $fecha_inicio, $fecha_vencimiento, $estado, $observaciones, $id];
            if(!$isAdmin){
                $upSql .= " AND id_prestamista=?";
                $upParams[] = $id_prestamista;
            }
            $up = $conexion->prepare($upSql);
            $up->execute($upParams);
            $msg = 'Préstamo actualizado';

            if($prestamoOriginal && (string)$prestamoOriginal['estado'] !== (string)$estado){
                try {
                    $hist = $conexion->prepare("INSERT INTO historial_estado_prestamo (id_prestamo, estado_anterior, estado_nuevo, fecha_cambio, motivo, usuario_responsable) VALUES (?, ?, ?, NOW(), ?, ?)");
                    $hist->execute([$id, (string)$prestamoOriginal['estado'], (string)$estado, 'Cambio de estado desde edición', $id_prestamista]);
                } catch (Exception $e) {
                }
            }

            try {
                $descripcion = 'Modificación de préstamo';
                if($cambios){
                    $descripcion .= ' (campos: ' . implode(', ', $cambios) . ')';
                }
                $log = $conexion->prepare("INSERT INTO trazabilidad (accion, id_registro, descripcion, tabla_afectada, usuario, fecha_evento) VALUES ('UPDATE', ?, ?, 'prestamo', ?, NOW())");
                $log->execute([$id, $descripcion, $_SESSION['id_usuario']]);
            } catch (Exception $e) {
            }

            $q->execute($qParams);
            $prestamo = $q->fetch(PDO::FETCH_ASSOC);
            $prestamoOriginal = $prestamo ? $prestamo : $prestamoOriginal;
        }
    }
}
end_post:

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_contacto'])){
    $correo_cliente = trim($_POST['correo_cliente'] ?? '');
    $telefono_cliente = trim($_POST['telefono_cliente'] ?? '');
    $direccion_cliente = trim($_POST['direccion_cliente'] ?? '');
    $contacto_estado = trim($_POST['contacto_estado'] ?? 'vigente');
    if(!in_array($contacto_estado, ['vigente','no_vigente'], true)){
        $error = "Estado de contacto inválido";
        goto end_contacto;
    }

    if($correo_cliente !== '' && !filter_var($correo_cliente, FILTER_VALIDATE_EMAIL)){
        $error = "El correo electrónico no es válido";
    } else {
        try {
            $idCliente = (int)($prestamo['id_cliente'] ?? 0);
            if($idCliente <= 0){
                $error = "No se permiten cambios sin identificar el préstamo";
                goto end_contacto;
            }

            $upSql = "UPDATE cliente SET correo = ?, telefono = ?, direccion = ?, contacto_estado = ? WHERE id_cliente = ?";
            $upParams = [$correo_cliente, $telefono_cliente, $direccion_cliente, $contacto_estado, $idCliente];
            if(!$isAdmin){
                $upSql .= " AND id_prestamista = ?";
                $upParams[] = $id_prestamista_prestamo;
            }

            $up = $conexion->prepare($upSql);
            $up->execute($upParams);

            try {
                $log = $conexion->prepare("INSERT INTO trazabilidad (accion, id_registro, descripcion, tabla_afectada, usuario, fecha_evento) VALUES ('UPDATE', ?, 'Modificación de datos de contacto del cliente', 'cliente', ?, NOW())");
                $log->execute([$idCliente, $_SESSION['id_usuario']]);
            } catch (Exception $e) {
            }

            $msg = "Datos de contacto actualizados";
            try {
                $cstmt = $conexion->prepare("SELECT id_cliente, nombre, cedula, correo, telefono, direccion, contacto_estado FROM cliente WHERE id_cliente = ? LIMIT 1");
                $cstmt->execute([$idCliente]);
                $clienteData = $cstmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
            }
        } catch (Exception $e) {
            $error = "Error al actualizar datos de contacto";
        }
    }
}
end_contacto:

$historialEstado = [];
try {
    $hs = $conexion->prepare("SELECT estado_anterior, estado_nuevo, fecha_cambio, motivo, usuario_responsable FROM historial_estado_prestamo WHERE id_prestamo = ? ORDER BY fecha_cambio DESC, id_historial DESC LIMIT 20");
    $hs->execute([$id]);
    $historialEstado = $hs->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}
?>
<!DOCTYPE html>
<html lang="es">
<?php
$pageTitle = 'Editar Préstamo - SoloDeudas';
$assetBaseUrl = '../../assets';
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
            <i class="fas fa-edit"></i>
            <h2>Editar Préstamo #<?php echo htmlspecialchars($id); ?></h2>
        </div>
        <div class="header-actions">
            <a href="prestamos.php" class="btn-secondary">
                <i class="fas fa-hand-holding-usd"></i> Volver a Préstamos
            </a>
        </div>
    </div>
    
    <?php if($msg): ?><div class="alert alert-success"><?php echo $msg; ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if(!$isAdmin && $isVigente): ?><div class="alert alert-info">Préstamo vigente: solo puedes actualizar los datos de contacto del cliente.</div><?php endif; ?>

    <div class="card" style="margin-bottom: 20px;">
        <div class="card-header" style="margin-bottom: 15px;">
            <h3 style="margin:0;"><i class="fas fa-info-circle"></i> Información del Préstamo</h3>
        </div>
        <form method="post" class="filter-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="form-group">
                <label>Cliente</label>
                <select name="cliente" required <?php echo !$can_edit_prestamo ? 'disabled' : ''; ?>>
                    <?php foreach($clientes as $c): ?>
                        <option value="<?php echo htmlspecialchars($c['id_cliente']); ?>" <?php echo ($c['id_cliente']==$prestamo['id_cliente'])?'selected':''; ?>><?php echo htmlspecialchars($c['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label>Monto Total</label>
                    <input type="number" name="monto_total" step="0.01" required value="<?php echo htmlspecialchars($prestamo['monto_total']); ?>" <?php echo !$can_edit_prestamo ? 'disabled' : ''; ?>>
                </div>
                <div class="form-group">
                    <label>Tasa de Interés (%)</label>
                    <input type="number" name="tasa_interes" step="0.01" required value="<?php echo htmlspecialchars($prestamo['tasa_interes']); ?>" <?php echo !$can_edit_prestamo ? 'disabled' : ''; ?>>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label>Fecha de Inicio</label>
                    <input type="date" name="fecha_inicio" required value="<?php echo htmlspecialchars($prestamo['fecha_inicio']); ?>" <?php echo !$can_edit_prestamo ? 'disabled' : ''; ?>>
                </div>
                <div class="form-group">
                    <label>Fecha de Vencimiento</label>
                    <input type="date" name="fecha_vencimiento" required value="<?php echo htmlspecialchars($prestamo['fecha_vencimiento']); ?>" <?php echo !$can_edit_prestamo ? 'disabled' : ''; ?>>
                </div>
            </div>

            <div class="form-group">
                <label>Estado</label>
                <select name="estado" required <?php echo (!$isAdmin || !$can_edit_prestamo) ? 'disabled' : ''; ?>>
                    <?php foreach(['activo','pendiente','pagado','vencido','cancelado'] as $st): ?>
                        <option value="<?php echo $st; ?>" <?php echo ($prestamo['estado']===$st)?'selected':''; ?>><?php echo ucfirst($st); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Observaciones</label>
                <textarea name="observaciones" rows="3" <?php echo !$can_edit_prestamo ? 'disabled' : ''; ?>><?php echo htmlspecialchars($prestamo['observaciones']); ?></textarea>
            </div>

            <?php if($can_edit_prestamo): ?>
                <div class="form-actions" style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 10px;">
                    <button type="submit" name="actualizar" class="btn-primary">
                        <i class="fas fa-save"></i> <?php echo $isAdmin ? 'Guardar cambios' : 'Enviar para aprobación'; ?>
                    </button>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <div class="card" style="margin-bottom: 20px;">
        <div class="card-header" style="margin-bottom: 15px;">
            <h3 style="margin:0;"><i class="fas fa-address-card"></i> Datos de contacto del cliente</h3>
        </div>
        <form method="post" class="filter-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="form-group">
                <label>Correo</label>
                <input type="email" name="correo_cliente" value="<?php echo htmlspecialchars((string)($clienteData['correo'] ?? '')); ?>" required>
            </div>
            <div class="form-group">
                <label>Teléfono</label>
                <input type="text" name="telefono_cliente" value="<?php echo htmlspecialchars((string)($clienteData['telefono'] ?? '')); ?>" required>
            </div>
            <div class="form-group">
                <label>Estado de contacto</label>
                <select name="contacto_estado" required>
                    <?php $contactoEstado = (string)($clienteData['contacto_estado'] ?? 'vigente'); ?>
                    <option value="vigente" <?php echo $contactoEstado === 'vigente' ? 'selected' : ''; ?>>Vigente</option>
                    <option value="no_vigente" <?php echo $contactoEstado === 'no_vigente' ? 'selected' : ''; ?>>No vigente</option>
                </select>
            </div>
            <div class="form-group" style="grid-column: span 2;">
                <label>Dirección</label>
                <textarea name="direccion_cliente" rows="2" required><?php echo htmlspecialchars((string)($clienteData['direccion'] ?? '')); ?></textarea>
            </div>
            <div class="form-actions" style="grid-column: 1 / -1; display: flex; justify-content: flex-end; margin-top: 10px;">
                <button type="submit" name="actualizar_contacto" class="btn-secondary">
                    <i class="fas fa-user-edit"></i> Guardar contacto
                </button>
            </div>
        </form>
    </div>

    <?php if($historialEstado): ?>
        <div class="card">
            <div class="card-header" style="margin-bottom: 15px;">
                <h3 style="margin:0;"><i class="fas fa-history"></i> Historial de estado</h3>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Anterior</th>
                            <th>Nuevo</th>
                            <th>Motivo</th>
                            <th>Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($historialEstado as $h): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($h['fecha_cambio'] ?? ''); ?></td>
                                <td>
                                    <span class="status-badge <?php echo htmlspecialchars($h['estado_anterior']); ?>">
                                        <?php echo ucfirst(htmlspecialchars($h['estado_anterior'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo htmlspecialchars($h['estado_nuevo']); ?>">
                                        <?php echo ucfirst(htmlspecialchars($h['estado_nuevo'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($h['motivo'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($h['usuario_responsable'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</section>

<footer>
    <p>&copy; <?php echo date("Y"); ?> SoloDeudas. Todos los derechos reservados.</p>
</footer>

<script src="../../assets/js/main.js"></script>
</body>
</html>
