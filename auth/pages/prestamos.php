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

$f_cliente = filter_input(INPUT_GET, 'cliente', FILTER_VALIDATE_INT) ?: null;
$f_prestamista = $isAdmin ? (filter_input(INPUT_GET, 'prestamista', FILTER_VALIDATE_INT) ?: null) : null;
$f_desde = trim($_GET['desde'] ?? '');
$f_hasta = trim($_GET['hasta'] ?? '');

$f_pagos_desde = trim($_GET['pagos_desde'] ?? '');
$f_pagos_hasta = trim($_GET['pagos_hasta'] ?? '');
$f_pagos_prestamo = filter_input(INPUT_GET, 'pagos_prestamo', FILTER_VALIDATE_INT) ?: null;
$edit_pago = filter_input(INPUT_GET, 'edit_pago', FILTER_VALIDATE_INT) ?: null;

$isValidDate = function(string $d): bool {
    if($d === ''){
        return false;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
};

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    csrf_validate_post();
    if(isset($_POST['aprobar_edicion'])){
        if(!$isAdmin){
            $error = "No autorizado";
        } else {
            $id_edicion = filter_input(INPUT_POST, 'id_edicion', FILTER_VALIDATE_INT);
            if(!$id_edicion){
                $error = "Edición inválida";
            } else {
                try {
                    $conexion->beginTransaction();

                    $es = $conexion->prepare("SELECT e.id_edicion, e.id_prestamo, e.estado, e.cambios, pr.estado AS estado_prestamo
                                              FROM ediciones_prestamo e
                                              JOIN prestamo pr ON pr.id_prestamo = e.id_prestamo
                                              WHERE e.id_edicion = ? LIMIT 1
                                              FOR UPDATE");
                    $es->execute([$id_edicion]);
                    $ed = $es->fetch(PDO::FETCH_ASSOC);
                    if(!$ed){
                        $conexion->rollBack();
                        $error = "Edición no encontrada";
                    } elseif(($ed['estado'] ?? '') !== 'pendiente'){
                        $conexion->rollBack();
                        $error = "La edición ya fue procesada";
                    } else {
                        $cambios = json_decode((string)($ed['cambios'] ?? ''), true);
                        if(!is_array($cambios)){
                            $conexion->rollBack();
                            $error = "La edición tiene datos inválidos";
                        } else {
                            $id_prestamo = (int)$ed['id_prestamo'];
                            $id_cliente = (int)($cambios['id_cliente'] ?? 0);
                            $monto_total = (float)($cambios['monto_total'] ?? 0);
                            $tasa_interes = (float)($cambios['tasa_interes'] ?? 0);
                            $fecha_inicio = (string)($cambios['fecha_inicio'] ?? '');
                            $fecha_vencimiento = (string)($cambios['fecha_vencimiento'] ?? '');
                            $observaciones = (string)($cambios['observaciones'] ?? '');

                            if($monto_total <= 0){
                                $conexion->rollBack();
                                $error = "Monto inválido";
                            } elseif($tasa_interes < 0 || $tasa_interes > 20){
                                $conexion->rollBack();
                                $error = "La tasa de interés no puede ser mayor al 20%";
                            } elseif($fecha_vencimiento <= $fecha_inicio){
                                $conexion->rollBack();
                                $error = "El plazo del préstamo no puede ser negativo";
                            } else {
                                $owner = $conexion->prepare("SELECT id_prestamista FROM prestamo WHERE id_prestamo = ? LIMIT 1");
                                $owner->execute([$id_prestamo]);
                                $id_prestamista_owner = (int)($owner->fetchColumn() ?? 0);
                                if($id_prestamista_owner <= 0){
                                    $conexion->rollBack();
                                    $error = "Préstamo no encontrado";
                                } else {
                                    $clienteCheck = $conexion->prepare("SELECT 1 FROM cliente WHERE id_cliente = ? AND id_prestamista = ? LIMIT 1");
                                    $clienteCheck->execute([$id_cliente, $id_prestamista_owner]);
                                    if(!$clienteCheck->fetchColumn()){
                                        $conexion->rollBack();
                                        $error = "El cliente seleccionado no existe en el sistema";
                                    } else {
                                        $oldStmt = $conexion->prepare("SELECT id_cliente, monto_total, tasa_interes, fecha_inicio, fecha_vencimiento, observaciones FROM prestamo WHERE id_prestamo = ? LIMIT 1");
                                        $oldStmt->execute([$id_prestamo]);
                                        $oldRow = $oldStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                                        $changes = [];
                                        if(isset($oldRow['id_cliente']) && (int)$oldRow['id_cliente'] !== (int)$id_cliente){
                                            $changes[] = "Cliente: {$oldRow['id_cliente']} -> {$id_cliente}";
                                        }
                                        if(isset($oldRow['monto_total']) && (float)$oldRow['monto_total'] !== (float)$monto_total){
                                            $changes[] = "Monto: {$oldRow['monto_total']} -> {$monto_total}";
                                        }
                                        if(isset($oldRow['tasa_interes']) && (float)$oldRow['tasa_interes'] !== (float)$tasa_interes){
                                            $changes[] = "Tasa: {$oldRow['tasa_interes']} -> {$tasa_interes}";
                                        }
                                        if(isset($oldRow['fecha_inicio']) && (string)$oldRow['fecha_inicio'] !== (string)$fecha_inicio){
                                            $changes[] = "Inicio: {$oldRow['fecha_inicio']} -> {$fecha_inicio}";
                                        }
                                        if(isset($oldRow['fecha_vencimiento']) && (string)$oldRow['fecha_vencimiento'] !== (string)$fecha_vencimiento){
                                            $changes[] = "Vence: {$oldRow['fecha_vencimiento']} -> {$fecha_vencimiento}";
                                        }
                                        if(array_key_exists('observaciones', $oldRow) && (string)($oldRow['observaciones'] ?? '') !== (string)$observaciones){
                                            $changes[] = "Obs: " . (string)($oldRow['observaciones'] ?? '') . " -> " . $observaciones;
                                        }

                                        $up = $conexion->prepare("UPDATE prestamo SET id_cliente=?, monto_total=?, tasa_interes=?, fecha_inicio=?, fecha_vencimiento=?, observaciones=? WHERE id_prestamo=?");
                                        $up->execute([$id_cliente, $monto_total, $tasa_interes, $fecha_inicio, $fecha_vencimiento, $observaciones, $id_prestamo]);

                                        $mark = $conexion->prepare("UPDATE ediciones_prestamo SET estado='aprobada', revisado_por=?, fecha_revision=NOW() WHERE id_edicion=? AND estado='pendiente'");
                                        $mark->execute([$id_prestamista, $id_edicion]);
                                        if($mark->rowCount() <= 0){
                                            $conexion->rollBack();
                                            $error = "La edición ya fue procesada";
                                            goto end_aprobar;
                                        }

                                        try {
                                            $descPrestamo = $changes ? ('Edición aprobada: ' . implode(' | ', $changes)) : 'Edición aprobada';
                                            $logPrestamo = $conexion->prepare("INSERT INTO trazabilidad (accion, id_registro, descripcion, tabla_afectada, usuario, fecha_evento) VALUES ('UPDATE', ?, ?, 'prestamo', ?, NOW())");
                                            $logPrestamo->execute([$id_prestamo, $descPrestamo, $id_prestamista]);
                                        } catch (Exception $e) {
                                        }

                                        try {
                                            $log = $conexion->prepare("INSERT INTO trazabilidad (accion, id_registro, descripcion, tabla_afectada, usuario, fecha_evento) VALUES ('UPDATE', ?, 'Edición de préstamo aprobada', 'ediciones_prestamo', ?, NOW())");
                                            $log->execute([$id_edicion, $id_prestamista]);
                                        } catch (Exception $e) {
                                        }

                                        $conexion->commit();
                                        $msg = "Edición aprobada y aplicada";
                                    }
                                }
                            }
                        }
                    }
                end_aprobar:
                } catch (Exception $e) {
                    try {
                        if($conexion->inTransaction()){
                            $conexion->rollBack();
                        }
                    } catch (Exception $e2) {
                    }
                    $error = "Error al aprobar edición";
                }
            }
        }
    }

    if(isset($_POST['rechazar_edicion'])){
        if(!$isAdmin){
            $error = "No autorizado";
        } else {
            $id_edicion = filter_input(INPUT_POST, 'id_edicion', FILTER_VALIDATE_INT);
            $motivo = trim($_POST['motivo'] ?? '');
            if(!$id_edicion){
                $error = "Edición inválida";
            } elseif($motivo === ''){
                $error = "Debes indicar el motivo del rechazo";
            } else {
                try {
                    $conexion->beginTransaction();
                    $mark = $conexion->prepare("UPDATE ediciones_prestamo SET estado='rechazada', revisado_por=?, fecha_revision=NOW(), motivo=? WHERE id_edicion=? AND estado='pendiente'");
                    $mark->execute([$id_prestamista, $motivo, $id_edicion]);
                    if($mark->rowCount() > 0){
                        try {
                            $log = $conexion->prepare("INSERT INTO trazabilidad (accion, id_registro, descripcion, tabla_afectada, usuario, fecha_evento) VALUES ('UPDATE', ?, ?, 'ediciones_prestamo', ?, NOW())");
                            $log->execute([$id_edicion, 'Edición de préstamo rechazada. Motivo: ' . $motivo, $id_prestamista]);
                        } catch (Exception $e) {
                        }
                        $conexion->commit();
                        $msg = "Edición rechazada";
                    } else {
                        $conexion->rollBack();
                        $error = "No se puede rechazar una edición ya procesada";
                    }
                } catch (Exception $e) {
                    try {
                        if($conexion->inTransaction()){
                            $conexion->rollBack();
                        }
                    } catch (Exception $e2) {
                    }
                    $error = "Error al rechazar edición";
                }
            }
        }
    }

    if(isset($_POST['cambiar_estado_prestamo'])){
        if(!$isAdmin){
            $error = "No autorizado";
        } else {
            $id_prestamo = filter_input(INPUT_POST, 'id_prestamo', FILTER_VALIDATE_INT);
            $nuevo_estado = trim($_POST['nuevo_estado'] ?? '');
            $permitidos = ['activo','pagado','vencido','cancelado'];
            if(!$id_prestamo){
                $error = "Préstamo inválido";
            } elseif(!in_array($nuevo_estado, $permitidos, true)){
                $error = "Estado inválido";
            } else {
                try {
                    $st = $conexion->prepare("SELECT estado FROM prestamo WHERE id_prestamo = ? LIMIT 1");
                    $st->execute([$id_prestamo]);
                    $estadoActual = (string)($st->fetchColumn() ?? '');
                    if($estadoActual === ''){
                        $error = "Préstamo no encontrado";
                    } elseif($estadoActual === 'pagado' && $nuevo_estado === 'activo'){
                        $error = "No se puede cambiar un préstamo pagado a estado activo";
                    } else {
                        $up = $conexion->prepare("UPDATE prestamo SET estado = ? WHERE id_prestamo = ?");
                        $up->execute([$nuevo_estado, $id_prestamo]);

                        try {
                            $hist = $conexion->prepare("INSERT INTO historial_estado_prestamo (id_prestamo, estado_anterior, estado_nuevo, fecha_cambio, motivo, usuario_responsable) VALUES (?, ?, ?, NOW(), ?, ?)");
                            $hist->execute([$id_prestamo, $estadoActual, $nuevo_estado, 'Cambio de estado por admin', $id_prestamista]);
                        } catch (Exception $e) {
                        }

                        try {
                            $log = $conexion->prepare("INSERT INTO trazabilidad (accion, id_registro, descripcion, tabla_afectada, usuario, fecha_evento) VALUES ('UPDATE', ?, ?, 'prestamo', ?, NOW())");
                            $desc = "Cambio de estado de préstamo: " . $estadoActual . " -> " . $nuevo_estado;
                            $log->execute([$id_prestamo, $desc, $id_prestamista]);
                        } catch (Exception $e) {
                        }

                        $msg = "Estado del préstamo actualizado";
                    }
                } catch (Exception $e) {
                    $error = "Error al cambiar estado del préstamo";
                }
            }
        }
    }

    if(isset($_POST['confirmar_pago'])){
        if(!$isAdmin){
            $error = "Solo administradores pueden validar pagos";
        } else {
        $id_pago = filter_input(INPUT_POST, 'id_pago', FILTER_VALIDATE_INT);
        if($id_pago){
            try {
                $pstmt = $conexion->prepare("SELECT pa.id_pago, COALESCE(pa.estado_pago,'pendiente') AS estado_pago, pa.confirmado, pr.id_prestamista, pr.id_cliente FROM pago pa JOIN prestamo pr ON pa.id_prestamo = pr.id_prestamo WHERE pa.id_pago = ? LIMIT 1");
                $pstmt->execute([$id_pago]);
                $pagoRow = $pstmt->fetch(PDO::FETCH_ASSOC);
                if(!$pagoRow){
                    $error = "Pago no encontrado";
                } elseif($f_cliente && (int)$pagoRow['id_cliente'] !== (int)$f_cliente){
                    $error = "El pago no corresponde al cliente filtrado";
                } elseif(((string)($pagoRow['estado_pago'] ?? 'pendiente')) !== 'pendiente'){
                    $error = "El pago ya está finalizado";
                } else {
                    $up = $conexion->prepare("UPDATE pago SET estado_pago = 'validado', confirmado = 1, confirmado_por = ?, confirmado_fecha = NOW() WHERE id_pago = ? AND COALESCE(estado_pago,'pendiente') = 'pendiente'");
                    $up->execute([$id_prestamista, $id_pago]);
                    if($up->rowCount() > 0){
                        try {
                            $log = $conexion->prepare("INSERT INTO trazabilidad (accion, id_registro, descripcion, tabla_afectada, usuario, fecha_evento) VALUES ('UPDATE', ?, 'Confirmación de pago', 'pago', ?, NOW())");
                            $log->execute([$id_pago, $id_prestamista]);
                        } catch (Exception $e) {
                        }
                        $msg = "Pago confirmado";
                    } else {
                        $error = "No se pudo confirmar el pago";
                    }
                }
            } catch (Exception $e) {
                $error = "Error al confirmar pago";
            }
        } else {
            $error = "Pago inválido";
        }
        }
    }

    if(isset($_POST['anular_pago']) || isset($_POST['rechazar_pago'])){
        if(!$isAdmin){
            $error = "Solo administradores pueden anular pagos";
        } else {
        $id_pago = filter_input(INPUT_POST, 'id_pago', FILTER_VALIDATE_INT);
        $motivo = trim($_POST['motivo'] ?? '');
        if($id_pago){
            if($motivo === ''){
                $error = "Debes indicar el motivo de la anulación";
            } else {
            try {
                $pstmt = $conexion->prepare("SELECT pa.id_pago, COALESCE(pa.estado_pago,'pendiente') AS estado_pago, pa.confirmado, pr.id_prestamista, pr.id_cliente FROM pago pa JOIN prestamo pr ON pa.id_prestamo = pr.id_prestamo WHERE pa.id_pago = ? LIMIT 1");
                $pstmt->execute([$id_pago]);
                $pagoRow = $pstmt->fetch(PDO::FETCH_ASSOC);
                if(!$pagoRow){
                    $error = "Pago no encontrado";
                } elseif($f_cliente && (int)$pagoRow['id_cliente'] !== (int)$f_cliente){
                    $error = "El pago no corresponde al cliente filtrado";
                } else {
                    $up = $conexion->prepare("UPDATE pago SET estado_pago = 'anulado', anulado_por = ?, anulado_fecha = NOW(), motivo_anulacion = ? WHERE id_pago = ? AND COALESCE(estado_pago,'pendiente') IN ('pendiente','validado') AND COALESCE(estado_pago,'pendiente') <> 'anulado'");
                    $up->execute([$id_prestamista, $motivo, $id_pago]);
                    if($up->rowCount() > 0){
                        try {
                            $log = $conexion->prepare("INSERT INTO trazabilidad (accion, id_registro, descripcion, tabla_afectada, usuario, fecha_evento) VALUES ('UPDATE', ?, ?, 'pago', ?, NOW())");
                            $log->execute([$id_pago, 'Anulación de pago. Motivo: ' . $motivo, $id_prestamista]);
                        } catch (Exception $e) {
                        }
                        $msg = "Pago anulado";
                    } else {
                        $error = "No se pudo anular el pago";
                    }
                }
            } catch (Exception $e) {
                $error = "Error al anular pago";
            }
            }
        } else {
            $error = "Pago inválido";
        }
        }
    }

    if(isset($_POST['registrar_pago'])){
        $id_prestamo = filter_input(INPUT_POST, 'id_prestamo', FILTER_VALIDATE_INT);
        $fecha_pago = trim($_POST['fecha_pago'] ?? '');
        $monto_raw = trim((string)($_POST['monto_pagado'] ?? ''));
        $forma_pago = trim($_POST['forma_pago'] ?? '');
        $recibido_por = trim($_POST['recibido_por'] ?? '');
        $observacion = trim($_POST['observacion'] ?? '');

        if(!$id_prestamo){
            $error = "Préstamo inválido";
        } elseif(!$isValidDate($fecha_pago)){
            $error = "Fecha de pago inválida";
        } elseif($monto_raw === '' || !is_numeric($monto_raw)){
            $error = "El monto del pago es inválido";
        } elseif(($monto_pagado = round((float)$monto_raw, 2)) <= 0){
            $error = "El monto del pago debe ser mayor que 0";
        } else {
            try {
                $conexion->beginTransaction();

                $pr = $conexion->prepare("SELECT id_prestamo, id_cliente, id_prestamista, estado, monto_total FROM prestamo WHERE id_prestamo = ? FOR UPDATE");
                $pr->execute([$id_prestamo]);
                $prestamoRow = $pr->fetch(PDO::FETCH_ASSOC);

                if(!$prestamoRow){
                    $conexion->rollBack();
                    $error = "Préstamo no encontrado";
                } elseif(!$isAdmin && (int)$prestamoRow['id_prestamista'] !== (int)$id_prestamista){
                    $conexion->rollBack();
                    $error = "No autorizado";
                } elseif($f_cliente && (int)$prestamoRow['id_cliente'] !== (int)$f_cliente){
                    $conexion->rollBack();
                    $error = "El préstamo no corresponde al cliente filtrado";
                } elseif(((string)($prestamoRow['estado'] ?? '')) !== 'activo'){
                    $conexion->rollBack();
                    $error = "Solo se pueden registrar pagos en préstamos activos";
                } else {
                    $sum = $conexion->prepare("SELECT COALESCE(SUM(monto_pagado),0) FROM pago WHERE id_prestamo = ? AND COALESCE(estado_pago,'pendiente') NOT IN ('rechazado','anulado') FOR UPDATE");
                    $sum->execute([$id_prestamo]);
                    $totalPagado = round((float)($sum->fetchColumn() ?? 0), 2);

                    $montoTotal = round((float)($prestamoRow['monto_total'] ?? 0), 2);
                    $saldo = round($montoTotal - $totalPagado, 2);

                    if($saldo <= 0){
                        $conexion->rollBack();
                        $error = "El préstamo no tiene saldo pendiente";
                    } elseif($monto_pagado > $saldo){
                        $conexion->rollBack();
                        $error = "El pago no puede superar el saldo del préstamo";
                    } else {
                        $ins = $conexion->prepare("INSERT INTO pago (id_prestamo, fecha_pago, monto_pagado, forma_pago, recibido_por, observacion, registrado_por, confirmado, estado_pago) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'pendiente')");
                        $ins->execute([$id_prestamo, $fecha_pago, $monto_pagado, $forma_pago, $recibido_por, $observacion, $id_prestamista]);
                        $newId = (int)$conexion->lastInsertId();

                        try {
                            $log = $conexion->prepare("INSERT INTO trazabilidad (accion, id_registro, descripcion, tabla_afectada, usuario, fecha_evento) VALUES ('INSERT', ?, 'Registro de pago', 'pago', ?, NOW())");
                            $log->execute([$newId, $id_prestamista]);
                        } catch (Exception $e) {
                        }

                        $conexion->commit();
                        $msg = "Pago registrado";
                    }
                }
            } catch (Exception $e) {
                try {
                    if($conexion->inTransaction()){
                        $conexion->rollBack();
                    }
                } catch (Exception $e2) {
                }
                $error = "Error al registrar pago";
            }
        }
    }

    if(isset($_POST['actualizar_pago'])){
        $id_pago = filter_input(INPUT_POST, 'id_pago', FILTER_VALIDATE_INT);
        $fecha_pago = trim($_POST['fecha_pago'] ?? '');
        $forma_pago = trim($_POST['forma_pago'] ?? '');
        $recibido_por = trim($_POST['recibido_por'] ?? '');
        $observacion = trim($_POST['observacion'] ?? '');

        if(!$id_pago){
            $error = "Pago inválido";
        } elseif(!$isValidDate($fecha_pago)){
            $error = "Fecha de pago inválida";
        } else {
            try {
                $conexion->beginTransaction();

                $pstmt = $conexion->prepare("SELECT pa.id_pago, pa.fecha_pago, pa.forma_pago, pa.recibido_por, pa.observacion, pa.registrado_por,
                                                    COALESCE(pa.estado_pago,'pendiente') AS estado_pago, pa.confirmado,
                                                    pr.id_prestamista, pr.id_cliente, pr.id_prestamo
                                             FROM pago pa
                                             JOIN prestamo pr ON pa.id_prestamo = pr.id_prestamo
                                             WHERE pa.id_pago = ?
                                             FOR UPDATE");
                $pstmt->execute([$id_pago]);
                $pagoRow = $pstmt->fetch(PDO::FETCH_ASSOC);

                if(!$pagoRow){
                    $conexion->rollBack();
                    $error = "Pago no encontrado";
                } elseif((int)($pagoRow['registrado_por'] ?? 0) !== (int)$id_prestamista){
                    $conexion->rollBack();
                    $error = "Solo el usuario que registró el pago puede modificarlo";
                } elseif(!$isAdmin && (int)$pagoRow['id_prestamista'] !== (int)$id_prestamista){
                    $conexion->rollBack();
                    $error = "No autorizado";
                } elseif($f_cliente && (int)$pagoRow['id_cliente'] !== (int)$f_cliente){
                    $conexion->rollBack();
                    $error = "El pago no corresponde al cliente filtrado";
                } elseif($f_pagos_prestamo && (int)$pagoRow['id_prestamo'] !== (int)$f_pagos_prestamo){
                    $conexion->rollBack();
                    $error = "El pago no corresponde al préstamo filtrado";
                } elseif(((string)($pagoRow['estado_pago'] ?? 'pendiente')) !== 'pendiente'){
                    $conexion->rollBack();
                    $error = "El pago no puede modificarse después de validarse o rechazarse";
                } else {
                    $oldFecha = (string)($pagoRow['fecha_pago'] ?? '');
                    $oldForma = (string)($pagoRow['forma_pago'] ?? '');
                    $oldRec = (string)($pagoRow['recibido_por'] ?? '');
                    $oldObs = (string)($pagoRow['observacion'] ?? '');

                    $changes = [];
                    if($oldFecha !== $fecha_pago){
                        $changes[] = "Fecha: {$oldFecha} -> {$fecha_pago}";
                    }
                    if($oldForma !== $forma_pago){
                        $changes[] = "Forma: {$oldForma} -> {$forma_pago}";
                    }
                    if($oldRec !== $recibido_por){
                        $changes[] = "Recibido por: {$oldRec} -> {$recibido_por}";
                    }
                    if($oldObs !== $observacion){
                        $changes[] = "Observación: {$oldObs} -> {$observacion}";
                    }

                    if(!$changes){
                        $conexion->rollBack();
                        $error = "No se realizaron cambios en el pago";
                    } else {
                        $up = $conexion->prepare("UPDATE pago SET fecha_pago = ?, forma_pago = ?, recibido_por = ?, observacion = ? WHERE id_pago = ? AND confirmado = 0 AND COALESCE(estado_pago,'pendiente') = 'pendiente'");
                        $up->execute([$fecha_pago, $forma_pago, $recibido_por, $observacion, $id_pago]);
                        if($up->rowCount() > 0){
                            try {
                                $log = $conexion->prepare("INSERT INTO trazabilidad (accion, id_registro, descripcion, tabla_afectada, usuario, fecha_evento) VALUES ('UPDATE', ?, ?, 'pago', ?, NOW())");
                                $desc = implode(" | ", $changes);
                                $log->execute([$id_pago, $desc, $id_prestamista]);
                            } catch (Exception $e) {
                            }
                            $conexion->commit();
                            $msg = "Pago actualizado";
                        } else {
                            $conexion->rollBack();
                            $error = "No se pudo actualizar el pago";
                        }
                    }
                }
            } catch (Exception $e) {
                try {
                    if($conexion->inTransaction()){
                        $conexion->rollBack();
                    }
                } catch (Exception $e2) {
                }
                $error = "Error al actualizar pago";
            }
        }
    }
}

$clientes_stmt = null;
if($isAdmin){
    $clientes_stmt = $conexion->prepare("SELECT c.id_cliente, c.nombre, p.nombre AS prestamista_nombre, c.id_prestamista FROM cliente c LEFT JOIN prestamista p ON c.id_prestamista = p.id_prestamista ORDER BY p.nombre ASC, c.nombre ASC");
    $clientes_stmt->execute();
} else {
    $clientes_stmt = $conexion->prepare("SELECT id_cliente, nombre FROM cliente WHERE id_prestamista = ? ORDER BY nombre ASC");
    $clientes_stmt->execute([$id_prestamista]);
}
$clientes = $clientes_stmt->fetchAll(PDO::FETCH_ASSOC);

$prestamistas = [];
if($isAdmin){
    $prestStmt = $conexion->prepare("SELECT id_prestamista, nombre FROM prestamista ORDER BY nombre ASC");
    $prestStmt->execute();
    $prestamistas = $prestStmt->fetchAll(PDO::FETCH_ASSOC);
}

$sql = "SELECT p.id_prestamo, c.id_cliente, c.nombre AS cliente, p.monto_total, p.tasa_interes, p.fecha_inicio, p.fecha_vencimiento, p.estado, p.id_prestamista, pr.nombre AS prestamista_nombre
        FROM prestamo p
        JOIN cliente c ON p.id_cliente = c.id_cliente
        LEFT JOIN prestamista pr ON p.id_prestamista = pr.id_prestamista";
$conds = [];
$params = [];

if(!$isAdmin){
    $conds[] = "p.id_prestamista = ?";
    $params[] = $id_prestamista;
} elseif($f_prestamista){
    $conds[] = "p.id_prestamista = ?";
    $params[] = $f_prestamista;
}

if($f_cliente){
    $conds[] = "p.id_cliente = ?";
    $params[] = $f_cliente;
}

if($isValidDate($f_desde)){
    $conds[] = "p.fecha_inicio >= ?";
    $params[] = $f_desde;
}
if($isValidDate($f_hasta)){
    $conds[] = "p.fecha_inicio <= ?";
    $params[] = $f_hasta;
}

if($conds){
    $sql .= " WHERE " . implode(" AND ", $conds);
}
$sql .= " ORDER BY p.fecha_inicio DESC";

$q = $conexion->prepare($sql);
$q->execute($params);
$rows = $q->fetchAll(PDO::FETCH_ASSOC);

$pagos = [];
if($f_cliente || $f_pagos_prestamo){
    $sqlPagos = "SELECT pa.id_pago, pa.fecha_pago, pa.monto_pagado, pa.forma_pago, pa.recibido_por, pa.observacion,
                        COALESCE(pa.estado_pago,'pendiente') AS estado_pago, pa.confirmado, pa.confirmado_por, pa.confirmado_fecha,
                        pa.registrado_por,
                        pr.id_prestamo, pr.estado, pr.id_cliente, pr.id_prestamista
                 FROM pago pa
                 JOIN prestamo pr ON pa.id_prestamo = pr.id_prestamo
                 WHERE 1=1";
    $paramsPagos = [];

    if($f_cliente){
        $sqlPagos .= " AND pr.id_cliente = ?";
        $paramsPagos[] = $f_cliente;
    }
    if($f_pagos_prestamo){
        $sqlPagos .= " AND pr.id_prestamo = ?";
        $paramsPagos[] = $f_pagos_prestamo;
    }

    if(!$isAdmin){
        $sqlPagos .= " AND pr.id_prestamista = ?";
        $paramsPagos[] = $id_prestamista;
    } elseif($f_prestamista){
        $sqlPagos .= " AND pr.id_prestamista = ?";
        $paramsPagos[] = $f_prestamista;
    }
    if($isValidDate($f_pagos_desde)){
        $sqlPagos .= " AND pa.fecha_pago >= ?";
        $paramsPagos[] = $f_pagos_desde;
    }
    if($isValidDate($f_pagos_hasta)){
        $sqlPagos .= " AND pa.fecha_pago <= ?";
        $paramsPagos[] = $f_pagos_hasta;
    }
    $sqlPagos .= " ORDER BY pa.fecha_pago DESC, pa.id_pago DESC";
    $qp = $conexion->prepare($sqlPagos);
    $qp->execute($paramsPagos);
    $pagos = $qp->fetchAll(PDO::FETCH_ASSOC);
}

$prestamos_para_pago = [];
if($f_cliente){
    try {
        $sqlPrestamosPago = "SELECT pr.id_prestamo, pr.estado, pr.monto_total,
                                    COALESCE(SUM(CASE WHEN COALESCE(pa.estado_pago,'pendiente') NOT IN ('rechazado','anulado') THEN pa.monto_pagado ELSE 0 END),0) AS total_pagado
                             FROM prestamo pr
                             LEFT JOIN pago pa ON pa.id_prestamo = pr.id_prestamo
                             WHERE pr.id_cliente = ?";
        $paramsPrestamosPago = [$f_cliente];
        if(!$isAdmin){
            $sqlPrestamosPago .= " AND pr.id_prestamista = ?";
            $paramsPrestamosPago[] = $id_prestamista;
        } elseif($f_prestamista){
            $sqlPrestamosPago .= " AND pr.id_prestamista = ?";
            $paramsPrestamosPago[] = $f_prestamista;
        }
        $sqlPrestamosPago .= " GROUP BY pr.id_prestamo, pr.estado, pr.monto_total
                               ORDER BY pr.fecha_inicio DESC";

        $pp = $conexion->prepare($sqlPrestamosPago);
        $pp->execute($paramsPrestamosPago);
        $prestamos_para_pago = $pp->fetchAll(PDO::FETCH_ASSOC);
        foreach($prestamos_para_pago as &$prp){
            $montoTotal = (float)($prp['monto_total'] ?? 0);
            $totalPagado = (float)($prp['total_pagado'] ?? 0);
            $prp['saldo'] = $montoTotal - $totalPagado;
        }
        unset($prp);
    } catch (Exception $e) {
        $prestamos_para_pago = [];
    }
}

$pago_edit = null;
if($edit_pago && ($f_cliente || $f_pagos_prestamo)){
    try {
        $pstmt = $conexion->prepare("SELECT pa.id_pago, pa.fecha_pago, pa.monto_pagado, pa.forma_pago, pa.recibido_por, pa.observacion, pa.registrado_por, COALESCE(pa.estado_pago,'pendiente') AS estado_pago, pa.confirmado, pr.id_prestamista, pr.id_cliente, pr.id_prestamo FROM pago pa JOIN prestamo pr ON pa.id_prestamo = pr.id_prestamo WHERE pa.id_pago = ? LIMIT 1");
        $pstmt->execute([$edit_pago]);
        $row = $pstmt->fetch(PDO::FETCH_ASSOC);
        $okScope = true;
        if($f_cliente){
            $okScope = $okScope && ((int)$row['id_cliente'] === (int)$f_cliente);
        }
        if($f_pagos_prestamo){
            $okScope = $okScope && ((int)$row['id_prestamo'] === (int)$f_pagos_prestamo);
        }
        if($row && $okScope && (int)($row['registrado_por'] ?? 0) === (int)$id_prestamista && ((string)($row['estado_pago'] ?? 'pendiente')) === 'pendiente'){
            $pago_edit = $row;
        }
    } catch (Exception $e) {
    }
}

$ver_historial = (($_GET['historial'] ?? '') === '1');
$hist_prestamo = filter_input(INPUT_GET, 'hist_prestamo', FILTER_VALIDATE_INT) ?: null;
$hist_desde = trim($_GET['hist_desde'] ?? '');
$hist_hasta = trim($_GET['hist_hasta'] ?? '');

$hist_por_pagina = 30;
$hist_pagina = filter_input(INPUT_GET, 'hist_pagina', FILTER_VALIDATE_INT) ?: 1;
if($hist_pagina < 1) $hist_pagina = 1;
$hist_offset = ($hist_pagina - 1) * $hist_por_pagina;

$hist_rows = [];
$hist_total_paginas = 1;
if($ver_historial){
    $histConds = ["t.tabla_afectada = 'prestamo'", "t.accion = 'UPDATE'"];
    $histParams = [];
    $baseFrom = " FROM trazabilidad t
                  JOIN prestamo pr ON pr.id_prestamo = t.id_registro
                  JOIN cliente c ON c.id_cliente = pr.id_cliente
                  LEFT JOIN prestamista owner ON owner.id_prestamista = pr.id_prestamista
                  LEFT JOIN prestamista resp ON resp.id_prestamista = t.usuario";

    if(!$isAdmin){
        $histConds[] = "pr.id_prestamista = ?";
        $histParams[] = $id_prestamista;
    } elseif($f_prestamista){
        $histConds[] = "pr.id_prestamista = ?";
        $histParams[] = $f_prestamista;
    }

    if($f_cliente){
        $histConds[] = "pr.id_cliente = ?";
        $histParams[] = $f_cliente;
    }

    if($hist_prestamo){
        $histConds[] = "pr.id_prestamo = ?";
        $histParams[] = $hist_prestamo;
    }

    if($isValidDate($hist_desde)){
        $histConds[] = "DATE(t.fecha_evento) >= ?";
        $histParams[] = $hist_desde;
    }
    if($isValidDate($hist_hasta)){
        $histConds[] = "DATE(t.fecha_evento) <= ?";
        $histParams[] = $hist_hasta;
    }

    $where = " WHERE " . implode(" AND ", $histConds);

    try {
        $countStmt = $conexion->prepare("SELECT COUNT(*)" . $baseFrom . $where);
        $countStmt->execute($histParams);
        $hist_total = (int)$countStmt->fetchColumn();
        $hist_total_paginas = max(1, (int)ceil($hist_total / $hist_por_pagina));
    } catch (Exception $e) {
        $hist_total_paginas = 1;
    }

    try {
        $histSql = "SELECT t.fecha_evento, t.descripcion, t.usuario, COALESCE(resp.nombre, CONCAT('ID: ', t.usuario)) AS responsable_nombre,
                           pr.id_prestamo, pr.id_prestamista, COALESCE(owner.nombre,'') AS prestamista_nombre,
                           c.nombre AS cliente
                    " . $baseFrom . $where . "
                    ORDER BY t.fecha_evento ASC
                    LIMIT $hist_por_pagina OFFSET $hist_offset";
        $histStmt = $conexion->prepare($histSql);
        $histStmt->execute($histParams);
        $hist_rows = $histStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $hist_rows = [];
    }
}

$ver_ediciones = $isAdmin && (($_GET['ediciones'] ?? '') === '1');
$ed_estado = trim($_GET['ed_estado'] ?? 'pendiente');
if(!in_array($ed_estado, ['pendiente','aprobada','rechazada'], true)){
    $ed_estado = 'pendiente';
}

$ed_prestamo = filter_input(INPUT_GET, 'ed_prestamo', FILTER_VALIDATE_INT) ?: null;
$ed_por_pagina = 30;
$ed_pagina = filter_input(INPUT_GET, 'ed_pagina', FILTER_VALIDATE_INT) ?: 1;
if($ed_pagina < 1) $ed_pagina = 1;
$ed_offset = ($ed_pagina - 1) * $ed_por_pagina;

$ver_edicion = $ver_ediciones ? (filter_input(INPUT_GET, 'ver_edicion', FILTER_VALIDATE_INT) ?: null) : null;
$ver_edicion_row = null;
$ver_edicion_diff = [];
if($ver_ediciones && $ver_edicion){
    try {
        $qs = $conexion->prepare("SELECT e.id_edicion, e.id_prestamo, e.estado, e.fecha_solicitud, e.cambios, e.motivo,
                                         pr.id_cliente, pr.monto_total, pr.tasa_interes, pr.fecha_inicio, pr.fecha_vencimiento, pr.observaciones,
                                         c.nombre AS cliente, owner.nombre AS prestamista_nombre,
                                         req.nombre AS solicitado_nombre, rev.nombre AS revisado_nombre
                                  FROM ediciones_prestamo e
                                  JOIN prestamo pr ON pr.id_prestamo = e.id_prestamo
                                  JOIN cliente c ON c.id_cliente = pr.id_cliente
                                  LEFT JOIN prestamista owner ON owner.id_prestamista = pr.id_prestamista
                                  LEFT JOIN prestamista req ON req.id_prestamista = e.solicitado_por
                                  LEFT JOIN prestamista rev ON rev.id_prestamista = e.revisado_por
                                  WHERE e.id_edicion = ? LIMIT 1");
        $qs->execute([$ver_edicion]);
        $ver_edicion_row = $qs->fetch(PDO::FETCH_ASSOC) ?: null;
        if($ver_edicion_row){
            $cambios = json_decode((string)($ver_edicion_row['cambios'] ?? ''), true);
            if(is_array($cambios)){
                $map = [
                    'id_cliente' => 'Cliente',
                    'monto_total' => 'Monto',
                    'tasa_interes' => 'Tasa',
                    'fecha_inicio' => 'Inicio',
                    'fecha_vencimiento' => 'Vencimiento',
                    'observaciones' => 'Observaciones'
                ];
                foreach($map as $k => $label){
                    if(array_key_exists($k, $cambios)){
                        $oldVal = (string)($ver_edicion_row[$k] ?? '');
                        $newVal = (string)$cambios[$k];
                        if($oldVal !== $newVal){
                            $ver_edicion_diff[] = ['campo' => $label, 'antes' => $oldVal, 'despues' => $newVal];
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        $ver_edicion_row = null;
        $ver_edicion_diff = [];
    }
}

$ed_rows = [];
$ed_total_paginas = 1;
if($ver_ediciones){
    $edConds = ["e.estado = ?"];
    $edParams = [$ed_estado];
    if($ed_prestamo){
        $edConds[] = "e.id_prestamo = ?";
        $edParams[] = $ed_prestamo;
    }
    $edWhere = " WHERE " . implode(" AND ", $edConds);
    try {
        $cnt = $conexion->prepare("SELECT COUNT(*) FROM ediciones_prestamo e" . $edWhere);
        $cnt->execute($edParams);
        $total = (int)$cnt->fetchColumn();
        $ed_total_paginas = max(1, (int)ceil($total / $ed_por_pagina));
    } catch (Exception $e) {
        $ed_total_paginas = 1;
    }

    try {
        $sqlEd = "SELECT e.id_edicion, e.id_prestamo, e.estado, e.fecha_solicitud, e.fecha_revision, e.motivo,
                         req.nombre AS solicitado_nombre, rev.nombre AS revisado_nombre,
                         c.nombre AS cliente, owner.nombre AS prestamista_nombre
                  FROM ediciones_prestamo e
                  JOIN prestamo pr ON pr.id_prestamo = e.id_prestamo
                  JOIN cliente c ON c.id_cliente = pr.id_cliente
                  LEFT JOIN prestamista owner ON owner.id_prestamista = pr.id_prestamista
                  LEFT JOIN prestamista req ON req.id_prestamista = e.solicitado_por
                  LEFT JOIN prestamista rev ON rev.id_prestamista = e.revisado_por
                  " . $edWhere . "
                  ORDER BY e.fecha_solicitud DESC
                  LIMIT $ed_por_pagina OFFSET $ed_offset";
        $stmtEd = $conexion->prepare($sqlEd);
        $stmtEd->execute($edParams);
        $ed_rows = $stmtEd->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $ed_rows = [];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<?php
$pageTitle = 'Préstamos - SoloDeudas';
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
            <i class="fas fa-hand-holding-usd"></i>
            <h2>Préstamos</h2>
        </div>
        <div class="header-actions">
            <a href="agregarPrestamo.php" class="btn-primary">
                <i class="fas fa-plus"></i> Registrar Préstamo
            </a>
            <a href="agregarCliente.php" class="btn-secondary">
                <i class="fas fa-user-plus"></i> Agregar Cliente
            </a>
            <a href="prestamos.php?historial=1<?php echo $isAdmin && $f_prestamista ? ('&prestamista=' . urlencode((string)$f_prestamista)) : ''; ?><?php echo $f_cliente ? ('&cliente=' . urlencode((string)$f_cliente)) : ''; ?><?php echo $f_desde !== '' ? ('&desde=' . urlencode($f_desde)) : ''; ?><?php echo $f_hasta !== '' ? ('&hasta=' . urlencode($f_hasta)) : ''; ?>" class="btn-info">
                <i class="fas fa-history"></i> Historial de ediciones
            </a>
            <?php if($isAdmin): ?>
                <a href="prestamos.php?ediciones=1" class="btn-warning">
                    <i class="fas fa-tasks"></i> Ediciones pendientes
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="card">
        <form method="get" class="filter-form">
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
                <label><i class="fas fa-user"></i> Cliente</label>
                <select name="cliente">
                    <option value="">Todos</option>
                    <?php foreach($clientes as $c): ?>
                        <?php
                            $label = (string)($c['nombre'] ?? '');
                            if($isAdmin){
                                $prestamistaNombre = trim((string)($c['prestamista_nombre'] ?? ''));
                                if($prestamistaNombre !== ''){
                                    $label .= " — " . $prestamistaNombre;
                                }
                            }
                        ?>
                        <option value="<?php echo htmlspecialchars($c['id_cliente']); ?>" <?php echo ($f_cliente && (int)$f_cliente === (int)$c['id_cliente']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label><i class="fas fa-calendar-alt"></i> Desde (inicio)</label>
                <input type="date" name="desde" value="<?php echo htmlspecialchars($f_desde); ?>">
            </div>

            <div class="form-group">
                <label><i class="fas fa-calendar-alt"></i> Hasta (inicio)</label>
                <input type="date" name="hasta" value="<?php echo htmlspecialchars($f_hasta); ?>">
            </div>

            <div class="form-group">
                <label><i class="fas fa-hashtag"></i> ID préstamo (pago)</label>
                <input type="number" name="pagos_prestamo" value="<?php echo htmlspecialchars((string)($f_pagos_prestamo ?? '')); ?>" placeholder="Ej: 123">
            </div>

            <div class="form-actions" style="grid-column: 1 / -1; display: flex; gap: 10px; justify-content: flex-end; margin-top: 10px;">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-filter"></i> Filtrar
                </button>
                <a href="prestamos.php" class="btn-secondary">
                    <i class="fas fa-sync-alt"></i> Limpiar
                </a>
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
                    <th>Monto</th>
                    <th>Tasa (%)</th>
                    <th>Inicio</th>
                    <th>Vencimiento</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if($rows): ?>
                <?php foreach($rows as $r): ?>
                    <tr class="loan-row">
                        <?php if($isAdmin): ?>
                            <td><?php echo htmlspecialchars($r['prestamista_nombre'] ?? ''); ?></td>
                        <?php endif; ?>
                        <td><strong><?php echo htmlspecialchars($r['cliente']); ?></strong></td>
                        <td>$<?php echo number_format((float)$r['monto_total'],2); ?></td>
                        <td><?php echo htmlspecialchars($r['tasa_interes']); ?>%</td>
                        <td><?php echo htmlspecialchars($r['fecha_inicio']); ?></td>
                        <td><?php echo htmlspecialchars($r['fecha_vencimiento']); ?></td>
                        <td>
                            <span class="status-badge <?php echo htmlspecialchars($r['estado']); ?>">
                                <?php echo ucfirst(htmlspecialchars($r['estado'])); ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <?php if(($r['estado'] ?? '') !== 'pagado'): ?>
                                    <a href="editarPrestamo.php?id=<?php echo urlencode($r['id_prestamo']); ?>" class="btn-icon btn-edit" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php if($isAdmin): ?>
                                    <form method="post" style="display:inline-flex; gap: 5px;">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="id_prestamo" value="<?php echo htmlspecialchars($r['id_prestamo']); ?>">
                                        <select name="nuevo_estado" class="compact-select">
                                            <option value="activo" <?php echo (($r['estado'] ?? '') === 'activo') ? 'selected' : ''; ?>>Activo</option>
                                            <option value="pagado" <?php echo (($r['estado'] ?? '') === 'pagado') ? 'selected' : ''; ?>>Pagado</option>
                                            <option value="vencido" <?php echo (($r['estado'] ?? '') === 'vencido') ? 'selected' : ''; ?>>En mora</option>
                                            <option value="cancelado" <?php echo (($r['estado'] ?? '') === 'cancelado') ? 'selected' : ''; ?>>Cancelado</option>
                                        </select>
                                        <button type="submit" name="cambiar_estado_prestamo" class="btn-icon btn-save" title="Cambiar Estado">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <form method="post" action="eliminarPrestamo.php" style="display:inline" onsubmit="return confirm('¿Está seguro de eliminar este préstamo?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="id_prestamo" value="<?php echo htmlspecialchars($r['id_prestamo']); ?>">
                                    <button type="submit" class="btn-icon btn-delete" title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="<?php echo $isAdmin ? 8 : 7; ?>" class="text-center">No hay préstamos para los filtros seleccionados.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if($f_cliente || $f_pagos_prestamo): ?>
    <section class="section-container" style="margin-top: 20px;">
        <div class="section-header">
            <div class="header-title">
                <i class="fas fa-money-bill-wave"></i>
                <h2>Pagos</h2>
            </div>
        </div>

        <?php if($f_cliente): ?>
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header" style="margin-bottom: 15px;">
                    <h3 style="margin:0;"><i class="fas fa-plus-circle"></i> Registrar Pago</h3>
                </div>
                <?php
                    $prestamos_activos = array_values(array_filter($prestamos_para_pago, function($x){
                        return ((string)($x['estado'] ?? '')) === 'activo' && (float)($x['saldo'] ?? 0) > 0;
                    }));
                ?>
                <?php if($prestamos_activos): ?>
                    <form method="post" class="filter-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="form-group">
                            <label>Préstamo</label>
                            <select name="id_prestamo" required>
                                <?php foreach($prestamos_activos as $prp): ?>
                                    <option value="<?php echo htmlspecialchars((string)$prp['id_prestamo']); ?>">
                                        #<?php echo htmlspecialchars((string)$prp['id_prestamo']); ?> (Saldo: $<?php echo number_format((float)$prp['saldo'],2); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Fecha</label>
                            <input type="date" name="fecha_pago" value="<?php echo htmlspecialchars(date('Y-m-d')); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Monto</label>
                            <input type="number" name="monto_pagado" step="0.01" min="0.01" required>
                        </div>
                        <div class="form-group">
                            <label>Forma</label>
                            <input type="text" name="forma_pago" value="efectivo">
                        </div>
                        <div class="form-group">
                            <label>Recibido por</label>
                            <input type="text" name="recibido_por" value="<?php echo htmlspecialchars($_SESSION['nombre'] ?? ''); ?>">
                        </div>
                        <div class="form-group" style="grid-column: span 2;">
                            <label>Observación</label>
                            <input type="text" name="observacion" placeholder="Opcional">
                        </div>
                        <div class="form-actions" style="grid-column: 1 / -1; display: flex; justify-content: flex-end;">
                            <button type="submit" name="registrar_pago" class="btn-primary">
                                <i class="fas fa-save"></i> Registrar Pago
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-info">No hay préstamos activos con saldo pendiente para registrar pagos.</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if($pago_edit): ?>
            <div class="card" style="margin-bottom: 20px; border: 2px solid var(--primary-color);">
                <div class="card-header" style="margin-bottom: 15px;">
                    <h3 style="margin:0;"><i class="fas fa-edit"></i> Editar Pago #<?php echo htmlspecialchars($pago_edit['id_pago']); ?></h3>
                </div>
                <form method="post" class="filter-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="id_pago" value="<?php echo htmlspecialchars($pago_edit['id_pago']); ?>">
                    <div class="form-group">
                        <label>Fecha</label>
                        <input type="date" name="fecha_pago" value="<?php echo htmlspecialchars($pago_edit['fecha_pago']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Monto (Solo lectura)</label>
                        <input type="number" step="0.01" value="<?php echo htmlspecialchars($pago_edit['monto_pagado']); ?>" readonly style="background:#f8f9fa;">
                    </div>
                    <div class="form-group">
                        <label>Forma</label>
                        <input type="text" name="forma_pago" value="<?php echo htmlspecialchars($pago_edit['forma_pago'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Recibido por</label>
                        <input type="text" name="recibido_por" value="<?php echo htmlspecialchars($pago_edit['recibido_por'] ?? ''); ?>">
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Observación</label>
                        <input type="text" name="observacion" value="<?php echo htmlspecialchars($pago_edit['observacion'] ?? ''); ?>">
                    </div>
                    <div class="form-actions" style="grid-column: 1 / -1; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="submit" name="actualizar_pago" class="btn-primary">
                            <i class="fas fa-save"></i> Guardar Cambios
                        </button>
                        <a href="prestamos.php<?php echo $isAdmin && $f_prestamista ? ('?prestamista=' . urlencode((string)$f_prestamista)) : '?'; ?><?php echo $f_cliente ? (($isAdmin && $f_prestamista ? '&' : '') . 'cliente=' . urlencode((string)$f_cliente)) : ''; ?><?php echo $f_pagos_prestamo ? (($isAdmin && $f_prestamista) || $f_cliente ? '&' : '') . 'pagos_prestamo=' . urlencode((string)$f_pagos_prestamo) : ''; ?><?php echo $f_desde !== '' ? (((($isAdmin && $f_prestamista) || $f_cliente || $f_pagos_prestamo) ? '&' : '') . 'desde=' . urlencode($f_desde)) : ''; ?><?php echo $f_hasta !== '' ? (((($isAdmin && $f_prestamista) || $f_cliente || $f_pagos_prestamo || $f_desde !== '') ? '&' : '') . 'hasta=' . urlencode($f_hasta)) : ''; ?>" class="btn-secondary">
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div class="card" style="margin-bottom: 20px;">
            <form method="get" class="filter-form">
                <?php if($isAdmin): ?>
                    <input type="hidden" name="prestamista" value="<?php echo htmlspecialchars((string)($f_prestamista ?? '')); ?>">
                <?php endif; ?>
                <?php if($f_cliente): ?>
                    <input type="hidden" name="cliente" value="<?php echo htmlspecialchars((string)$f_cliente); ?>">
                <?php endif; ?>
                <input type="hidden" name="desde" value="<?php echo htmlspecialchars($f_desde); ?>">
                <input type="hidden" name="hasta" value="<?php echo htmlspecialchars($f_hasta); ?>">

                <div class="form-group">
                    <label><i class="fas fa-hashtag"></i> ID préstamo</label>
                    <input type="number" name="pagos_prestamo" value="<?php echo htmlspecialchars((string)($f_pagos_prestamo ?? '')); ?>">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-calendar-alt"></i> Desde (pago)</label>
                    <input type="date" name="pagos_desde" value="<?php echo htmlspecialchars($f_pagos_desde); ?>">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-calendar-alt"></i> Hasta (pago)</label>
                    <input type="date" name="pagos_hasta" value="<?php echo htmlspecialchars($f_pagos_hasta); ?>">
                </div>
                <div class="form-actions" style="display:flex; align-items:flex-end; gap:10px;">
                    <button type="submit" class="btn-primary">Filtrar Pagos</button>
                </div>
            </form>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Monto</th>
                        <th>Forma</th>
                        <th>Recibido por</th>
                        <th>Estado pago</th>
                        <th>Préstamo</th>
                        <th>Estado préstamo</th>
                        <?php if($isAdmin): ?>
                            <th>Confirmado por</th>
                            <th>Fecha confirmación</th>
                        <?php endif; ?>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if($pagos): ?>
                    <?php foreach($pagos as $pa): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($pa['fecha_pago']); ?></td>
                            <td><strong>$<?php echo number_format((float)$pa['monto_pagado'],2); ?></strong></td>
                            <td><?php echo htmlspecialchars($pa['forma_pago']); ?></td>
                            <td><?php echo htmlspecialchars($pa['recibido_por']); ?></td>
                            <td>
                                <?php
                                    $estadoPago = (string)($pa['estado_pago'] ?? 'pendiente');
                                    $estadoLabel = $estadoPago;
                                    if($estadoPago === 'pendiente'){
                                        $estadoLabel = 'registrado';
                                    } elseif($estadoPago === 'validado'){
                                        $estadoLabel = 'confirmado';
                                    } elseif($estadoPago === 'rechazado' || $estadoPago === 'anulado'){
                                        $estadoLabel = 'anulado';
                                    }
                                ?>
                                <span class="status-badge <?php echo htmlspecialchars($estadoPago); ?>">
                                    <?php echo ucfirst(htmlspecialchars($estadoLabel)); ?>
                                </span>
                            </td>
                            <td>#<?php echo htmlspecialchars($pa['id_prestamo']); ?></td>
                            <td><?php echo ucfirst(htmlspecialchars($pa['estado'] ?? '')); ?></td>
                            <?php if($isAdmin): ?>
                                <td><?php echo htmlspecialchars((string)($pa['confirmado_por'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string)($pa['confirmado_fecha'] ?? '')); ?></td>
                            <?php endif; ?>
                            <td>
                                <div class="action-buttons">
                                    <?php if($estadoPago === 'pendiente'): ?>
                                        <?php if($isAdmin): ?>
                                            <form method="post" style="display:inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="id_pago" value="<?php echo htmlspecialchars($pa['id_pago']); ?>">
                                                <button type="submit" name="confirmar_pago" class="btn-icon btn-save" title="Confirmar Pago">
                                                    <i class="fas fa-check-circle"></i>
                                                </button>
                                            </form>
                                            <form method="post" style="display:inline-flex; gap: 5px;">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="id_pago" value="<?php echo htmlspecialchars($pa['id_pago']); ?>">
                                                <input type="text" name="motivo" placeholder="Motivo" class="compact-input" required>
                                                <button type="submit" name="anular_pago" class="btn-icon btn-delete" title="Anular Pago">
                                                    <i class="fas fa-times-circle"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if((int)($pa['registrado_por'] ?? 0) === (int)$id_prestamista): ?>
                                            <a href="prestamos.php?edit_pago=<?php echo urlencode((string)$pa['id_pago']); ?><?php echo $isAdmin && $f_prestamista ? ('&prestamista=' . urlencode((string)$f_prestamista)) : ''; ?><?php echo $f_cliente ? ('&cliente=' . urlencode((string)$f_cliente)) : ''; ?><?php echo $f_pagos_prestamo ? ('&pagos_prestamo=' . urlencode((string)$f_pagos_prestamo)) : ''; ?><?php echo $f_desde !== '' ? ('&desde=' . urlencode($f_desde)) : ''; ?><?php echo $f_hasta !== '' ? ('&hasta=' . urlencode($f_hasta)) : ''; ?><?php echo $f_pagos_desde !== '' ? ('&pagos_desde=' . urlencode($f_pagos_desde)) : ''; ?><?php echo $f_pagos_hasta !== '' ? ('&pagos_hasta=' . urlencode($f_pagos_hasta)) : ''; ?>" class="btn-icon btn-edit" title="Editar Detalles">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                    <?php elseif($estadoPago === 'validado'): ?>
                                        <?php if($isAdmin): ?>
                                            <form method="post" style="display:inline-flex; gap: 5px;">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="id_pago" value="<?php echo htmlspecialchars($pa['id_pago']); ?>">
                                                <input type="text" name="motivo" placeholder="Motivo" class="compact-input" required>
                                                <button type="submit" name="anular_pago" class="btn-icon btn-delete" title="Anular Pago">
                                                    <i class="fas fa-times-circle"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="status-badge" style="background:#eee; color:#666;">Bloqueado</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="status-badge" style="background:#eee; color:#666;">Bloqueado</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="<?php echo $isAdmin ? 10 : 8; ?>" class="text-center">No hay pagos con los filtros seleccionados.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php if($ver_historial): ?>
    <section class="section-container" style="margin-top: 20px;">
        <div class="section-header">
            <div class="header-title">
                <i class="fas fa-history"></i>
                <h2>Historial de ediciones de préstamos</h2>
            </div>
        </div>

        <div class="card" style="margin-bottom: 20px;">
            <form method="get" class="filter-form">
                <input type="hidden" name="historial" value="1">
                <?php if($isAdmin && $f_prestamista): ?>
                    <input type="hidden" name="prestamista" value="<?php echo htmlspecialchars((string)$f_prestamista); ?>">
                <?php endif; ?>
                <?php if($f_cliente): ?>
                    <input type="hidden" name="cliente" value="<?php echo htmlspecialchars((string)$f_cliente); ?>">
                <?php endif; ?>
                <?php if($f_desde !== ''): ?>
                    <input type="hidden" name="desde" value="<?php echo htmlspecialchars($f_desde); ?>">
                <?php endif; ?>
                <?php if($f_hasta !== ''): ?>
                    <input type="hidden" name="hasta" value="<?php echo htmlspecialchars($f_hasta); ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label>ID préstamo</label>
                    <input type="number" name="hist_prestamo" value="<?php echo htmlspecialchars((string)($hist_prestamo ?? '')); ?>">
                </div>
                <div class="form-group">
                    <label>Desde</label>
                    <input type="date" name="hist_desde" value="<?php echo htmlspecialchars($hist_desde); ?>">
                </div>
                <div class="form-group">
                    <label>Hasta</label>
                    <input type="date" name="hist_hasta" value="<?php echo htmlspecialchars($hist_hasta); ?>">
                </div>
                <div class="form-actions" style="display:flex; gap:10px; align-items:flex-end;">
                    <button type="submit" class="btn-primary">Filtrar historial</button>
                    <a href="prestamos.php<?php echo $isAdmin && $f_prestamista ? ('?prestamista=' . urlencode((string)$f_prestamista)) : ''; ?><?php echo !$isAdmin && $f_cliente ? ('?cliente=' . urlencode((string)$f_cliente)) : ($isAdmin && $f_prestamista && $f_cliente ? ('&cliente=' . urlencode((string)$f_cliente)) : ($isAdmin && !$f_prestamista && $f_cliente ? ('?cliente=' . urlencode((string)$f_cliente)) : '')); ?>" class="btn-secondary">Cerrar historial</a>
                </div>
            </form>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <?php if($isAdmin): ?>
                            <th>Prestamista</th>
                        <?php endif; ?>
                        <th>Cliente</th>
                        <th>Préstamo</th>
                        <th>Descripción</th>
                        <th>Usuario</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($hist_rows): ?>
                        <?php foreach($hist_rows as $h): ?>
                            <tr>
                                <td style="white-space:nowrap;"><?php echo htmlspecialchars($h['fecha_evento'] ?? ''); ?></td>
                                <?php if($isAdmin): ?>
                                    <td><?php echo htmlspecialchars($h['prestamista_nombre'] ?? ''); ?></td>
                                <?php endif; ?>
                                <td><?php echo htmlspecialchars($h['cliente'] ?? ''); ?></td>
                                <td>#<?php echo htmlspecialchars($h['id_prestamo'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($h['descripcion'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($h['responsable_nombre'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="<?php echo $isAdmin ? 6 : 5; ?>" class="text-center">No hay ediciones registradas con los filtros seleccionados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if($hist_total_paginas > 1): ?>
            <div class="pagination">
                <?php for($i=1; $i <= $hist_total_paginas; $i++): ?>
                    <a href="prestamos.php?historial=1&hist_pagina=<?php echo $i; ?><?php echo $hist_prestamo ? ('&hist_prestamo=' . urlencode((string)$hist_prestamo)) : ''; ?><?php echo $hist_desde !== '' ? ('&hist_desde=' . urlencode($hist_desde)) : ''; ?><?php echo $hist_hasta !== '' ? ('&hist_hasta=' . urlencode($hist_hasta)) : ''; ?><?php echo $isAdmin && $f_prestamista ? ('&prestamista=' . urlencode((string)$f_prestamista)) : ''; ?><?php echo $f_cliente ? ('&cliente=' . urlencode((string)$f_cliente)) : ''; ?><?php echo $f_desde !== '' ? ('&desde=' . urlencode($f_desde)) : ''; ?><?php echo $f_hasta !== '' ? ('&hasta=' . urlencode($f_hasta)) : ''; ?>" class="<?php echo $hist_pagina === $i ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if($ver_ediciones): ?>
    <section class="section-container" style="margin-top: 20px;">
        <div class="section-header">
            <div class="header-title">
                <i class="fas fa-tasks"></i>
                <h2>Ediciones de préstamo</h2>
            </div>
        </div>

        <div class="card" style="margin-bottom: 20px;">
            <form method="get" class="filter-form">
                <input type="hidden" name="ediciones" value="1">
                <div class="form-group">
                    <label>Estado</label>
                    <select name="ed_estado">
                        <option value="pendiente" <?php echo $ed_estado === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                        <option value="aprobada" <?php echo $ed_estado === 'aprobada' ? 'selected' : ''; ?>>Aprobada</option>
                        <option value="rechazada" <?php echo $ed_estado === 'rechazada' ? 'selected' : ''; ?>>Rechazada</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>ID préstamo</label>
                    <input type="number" name="ed_prestamo" value="<?php echo htmlspecialchars((string)($ed_prestamo ?? '')); ?>">
                </div>
                <div class="form-actions" style="display:flex; gap:10px; align-items:flex-end;">
                    <button type="submit" class="btn-primary">Filtrar</button>
                    <a href="prestamos.php" class="btn-secondary">Cerrar</a>
                </div>
            </form>
        </div>

        <?php if($ver_edicion_row): ?>
            <div class="card" style="margin-bottom: 20px; border: 2px solid var(--primary-color);">
                <div class="card-header" style="margin-bottom: 15px;">
                    <h3 style="margin:0;"><i class="fas fa-eye"></i> Cambios de edición #<?php echo htmlspecialchars((string)$ver_edicion_row['id_edicion']); ?></h3>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                    <div><strong>Préstamo:</strong> #<?php echo htmlspecialchars((string)$ver_edicion_row['id_prestamo']); ?></div>
                    <div><strong>Cliente:</strong> <?php echo htmlspecialchars((string)($ver_edicion_row['cliente'] ?? '')); ?></div>
                    <div><strong>Prestamista:</strong> <?php echo htmlspecialchars((string)($ver_edicion_row['prestamista_nombre'] ?? '')); ?></div>
                    <div><strong>Solicitado por:</strong> <?php echo htmlspecialchars((string)($ver_edicion_row['solicitado_nombre'] ?? '')); ?></div>
                    <div><strong>Estado:</strong> <?php echo htmlspecialchars((string)($ver_edicion_row['estado'] ?? '')); ?></div>
                    <div><strong>Fecha solicitud:</strong> <?php echo htmlspecialchars((string)($ver_edicion_row['fecha_solicitud'] ?? '')); ?></div>
                </div>
                <?php if(($ver_edicion_row['estado'] ?? '') === 'rechazada' && (string)($ver_edicion_row['motivo'] ?? '') !== ''): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars((string)$ver_edicion_row['motivo']); ?></div>
                <?php endif; ?>
                <div class="table-container" style="margin-top: 10px;">
                    <table>
                        <thead>
                            <tr>
                                <th>Campo</th>
                                <th>Antes</th>
                                <th>Después</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($ver_edicion_diff): ?>
                                <?php foreach($ver_edicion_diff as $d): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string)$d['campo']); ?></td>
                                        <td><?php echo htmlspecialchars((string)$d['antes']); ?></td>
                                        <td><?php echo htmlspecialchars((string)$d['despues']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center">No se detectaron cambios.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Fecha solicitud</th>
                        <th>Prestamista</th>
                        <th>Cliente</th>
                        <th>Préstamo</th>
                        <th>Solicitado por</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($ed_rows): ?>
                        <?php foreach($ed_rows as $e): ?>
                            <tr>
                                <td style="white-space:nowrap;"><?php echo htmlspecialchars($e['fecha_solicitud'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($e['prestamista_nombre'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($e['cliente'] ?? ''); ?></td>
                                <td>#<?php echo htmlspecialchars($e['id_prestamo'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($e['solicitado_nombre'] ?? ''); ?></td>
                                <td>
                                    <span class="status-badge <?php echo htmlspecialchars($e['estado']); ?>">
                                        <?php echo ucfirst(htmlspecialchars($e['estado'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if(($e['estado'] ?? '') === 'pendiente'): ?>
                                            <a href="prestamos.php?ediciones=1&ver_edicion=<?php echo urlencode((string)$e['id_edicion']); ?>&ed_estado=<?php echo urlencode($ed_estado); ?><?php echo $ed_prestamo ? ('&ed_prestamo=' . urlencode((string)$ed_prestamo)) : ''; ?>" class="btn-icon btn-info" title="Ver cambios">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <form method="post" style="display:inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="id_edicion" value="<?php echo htmlspecialchars($e['id_edicion']); ?>">
                                                <button type="submit" name="aprobar_edicion" class="btn-icon btn-save" title="Aprobar">
                                                    <i class="fas fa-check-circle"></i>
                                                </button>
                                            </form>
                                            <form method="post" style="display:inline-flex; gap: 5px;">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="id_edicion" value="<?php echo htmlspecialchars($e['id_edicion']); ?>">
                                                <input type="text" name="motivo" placeholder="Motivo rechazo" class="compact-input" required>
                                                <button type="submit" name="rechazar_edicion" class="btn-icon btn-delete" title="Rechazar">
                                                    <i class="fas fa-times-circle"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="status-badge" style="background:#eee; color:#666;">Procesada</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center">No hay ediciones para los filtros seleccionados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if($ed_total_paginas > 1): ?>
            <div class="pagination">
                <?php for($i=1; $i <= $ed_total_paginas; $i++): ?>
                    <a href="prestamos.php?ediciones=1&ed_pagina=<?php echo $i; ?>&ed_estado=<?php echo urlencode($ed_estado); ?><?php echo $ed_prestamo ? ('&ed_prestamo=' . urlencode((string)$ed_prestamo)) : ''; ?>" class="<?php echo $ed_pagina === $i ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<footer>
    <p>&copy; <?php echo date("Y"); ?> SoloDeudas. Todos los derechos reservados.</p>
    <p><a href="../index.php">Volver al inicio</a></p>
    </footer>

<script src="../../assets/js/main.js"></script>
</body>
</html>
