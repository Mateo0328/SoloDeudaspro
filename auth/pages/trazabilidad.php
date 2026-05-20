<?php
require_once "../../session_init.php";
session_start();
require __DIR__ . '/../../config/conexion.php';

// RN03B: Solo administradores pueden ver la trazabilidad
if(!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin'){
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../session_control.php";

if(isset($_SESSION['force_password_change']) && $_SESSION['force_password_change'] === true){
    header("Location: forzarCambioPassword.php");
    exit;
}

// Configuración de Paginación
$logs_por_pagina = 20;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if($pagina_actual < 1) $pagina_actual = 1;
$offset = ($pagina_actual - 1) * $logs_por_pagina;

// Contar total de registros (excluyendo LOGIN para que sea solo auditoría de datos si se prefiere, 
// o incluir todo. Según RF3, se enfoca en cambios de datos)
$count_query = "SELECT COUNT(*) FROM trazabilidad WHERE accion != 'LOGIN'";
$total_logs = $conexion->query($count_query)->fetchColumn();
$total_paginas = ceil($total_logs / $logs_por_pagina);

// Consulta de trazabilidad unida con el nombre del responsable
// Nota: En la tabla trazabilidad guardamos el ID en 'usuario' según la última mejora
$query = "SELECT t.*, p.nombre as responsable_nombre 
          FROM trazabilidad t 
          LEFT JOIN prestamista p ON t.usuario = p.id_prestamista 
          WHERE t.accion != 'LOGIN'
          ORDER BY t.fecha_evento DESC 
          LIMIT $logs_por_pagina OFFSET $offset";
$stmt = $conexion->query($query);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">

<?php
$pageTitle = 'Trazabilidad - SoloDeudas';
$assetBaseUrl = '../../assets';
require __DIR__ . '/../_head.php';
?>
    <style>
    .table-container {
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        margin-top: 1rem;
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }

    th,
    td {
        padding: 1rem;
        border-bottom: 1px solid #eee;
        font-size: 0.9rem;
    }

    th {
        background: #f8f9fa;
        color: var(--primario);
        font-weight: 600;
    }

    .accion-badge {
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .INSERT {
        background: #d4edda;
        color: #155724;
    }

    .UPDATE {
        background: #fff3cd;
        color: #856404;
    }

    .DELETE {
        background: #f8d7da;
        color: #721c24;
    }

    .pagination {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin-top: 1.5rem;
    }

    .pagination a {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        text-decoration: none;
        color: #1B4332;
        background: white;
    }

    .pagination a.active {
        background: #1B4332;
        color: white;
        border-color: #1B4332;
    }
    </style>
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
            <i class="fas fa-history"></i>
            <h2>Historial de Cambios (Trazabilidad)</h2>
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
                    <th>Fecha</th>
                    <th>Acción</th>
                    <th>Tabla</th>
                    <th>ID Registro</th>
                    <th>Descripción</th>
                    <th>Responsable</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($logs as $log): ?>
                <tr>
                    <td style="white-space: nowrap;"><?php echo $log['fecha_evento']; ?></td>
                    <td>
                        <span class="status-badge <?php echo strtolower($log['accion']); ?>" style="font-size: 0.75rem;">
                            <?php echo $log['accion']; ?>
                        </span>
                    </td>
                    <td><code style="background: #f1f1f1; padding: 2px 6px; border-radius: 4px;"><?php echo htmlspecialchars($log['tabla_afectada']); ?></code></td>
                    <td>#<?php echo $log['id_registro']; ?></td>
                    <td style="max-width: 400px; word-wrap: break-word;">
                        <?php echo htmlspecialchars($log['descripcion']); ?>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($log['responsable_nombre'] ?? 'ID: '.$log['usuario']); ?></strong>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($logs)): ?>
                <tr>
                    <td colspan="6" class="text-center">No hay registros de cambios aún.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if($total_paginas > 1): ?>
    <div class="pagination">
        <?php for($i = 1; $i <= $total_paginas; $i++): ?>
        <a href="?pagina=<?php echo $i; ?>" class="<?php echo $pagina_actual === $i ? 'active' : ''; ?>">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</section>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> SoloDeudas. Todos los derechos reservados.</p>
    </footer>

    <script src="../../assets/js/main.js"></script>
</body>

</html>
