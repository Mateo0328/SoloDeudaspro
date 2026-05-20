<?php
require_once "../../session_init.php";
session_start();
require __DIR__ . '/../../config/conexion.php';

// RN02A: La consulta de usuarios requiere estar logueado.
if(!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin'){
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../session_control.php";

if(isset($_SESSION['force_password_change']) && $_SESSION['force_password_change'] === true){
    header("Location: forzarCambioPassword.php");
    exit;
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where = "";
$params = [];

if(!empty($search)){
    $where = " WHERE nombre LIKE ? OR correo LIKE ? OR id_prestamista = ?";
    $params = ["%$search%", "%$search%", $search];
}

// Configuración de Paginación
$usuarios_por_pagina = 20;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if($pagina_actual < 1) $pagina_actual = 1;
$offset = ($pagina_actual - 1) * $usuarios_por_pagina;

// Contar total de usuarios para la paginación
$count_query = "SELECT COUNT(*) FROM prestamista $where";
$count_stmt = $conexion->prepare($count_query);
$count_stmt->execute($params);
$total_usuarios = $count_stmt->fetchColumn();
$total_paginas = ceil($total_usuarios / $usuarios_por_pagina);

$query = "SELECT id_prestamista, nombre, correo, rol, estado, fecha_creacion FROM prestamista $where ORDER BY id_prestamista DESC LIMIT $usuarios_por_pagina OFFSET $offset";
$stmt = $conexion->prepare($query);
$stmt->execute($params);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<?php
$pageTitle = 'Gestión de Usuarios - SoloDeudas';
$assetBaseUrl = '../../assets';
require __DIR__ . '/../_head.php';
?>
    <style>
        .table-container {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-top: 1rem;
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        th, td {
            padding: 1rem;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            color: var(--primario);
            font-weight: 600;
        }
        tr:hover {
            background: #f1f1f1;
        }
        .badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-active { background: #d4edda; color: #155724; }
        .badge-inactive { background: #f8d7da; color: #721c24; }
        .btn-edit {
            background: #1B4332;
            color: white !important;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.85rem;
            display: inline-block;
            transition: opacity 0.2s;
        }
        .btn-edit:hover {
            opacity: 0.9;
            color: white !important;
        }
        .search-box {
            display: flex;
            gap: 10px;
            margin-bottom: 1rem;
        }
        .search-box input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .search-box button {
            margin-top: 0;
            width: auto;
            padding: 0 20px;
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
        .pagination a:hover:not(.active) {
            background: #f1f1f1;
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
            <i class="fas fa-user-shield"></i>
            <h2>Gestión de Usuarios</h2>
        </div>
        <div class="header-actions">
            <a href="../index.php" class="btn-secondary">
                <i class="fas fa-home"></i> Inicio
            </a>
        </div>
    </div>
    
    <div class="card" style="margin-bottom: 20px;">
        <form class="filter-form" method="GET">
            <div class="form-group" style="flex: 2;">
                <label><i class="fas fa-search"></i> Buscar</label>
                <input type="text" name="search" placeholder="Nombre, correo o ID..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="form-actions" style="display:flex; align-items:flex-end; gap:10px;">
                <button type="submit" class="btn-primary">Buscar</button>
                <?php if(!empty($search)): ?>
                    <a href="consultarUsuarios.php" class="btn-secondary">Limpiar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $counter = $offset + 1;
                foreach($usuarios as $u): 
                ?>
                <tr>
                    <td><?php echo $counter++; ?></td>
                    <td><code>#<?php echo $u['id_prestamista']; ?></code></td>
                    <td><strong><?php echo htmlspecialchars($u['nombre']); ?></strong></td>
                    <td><?php echo htmlspecialchars($u['correo']); ?></td>
                    <td>
                        <span class="badge badge-info">
                            <?php echo strtoupper(htmlspecialchars($u['rol'])); ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-badge <?php echo htmlspecialchars($u['estado']); ?>">
                            <?php echo ucfirst($u['estado']); ?>
                        </span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <a href="editarUsuario.php?id=<?php echo $u['id_prestamista']; ?>" class="btn-icon btn-edit" title="Editar / Reset Password">
                                <i class="fas fa-user-edit"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($usuarios)): ?>
                <tr>
                    <td colspan="7" class="text-center">No se encontraron usuarios.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if($total_paginas > 1): ?>
    <div class="pagination">
        <?php for($i = 1; $i <= $total_paginas; $i++): ?>
            <a href="?pagina=<?php echo $i; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" 
               class="<?php echo $pagina_actual === $i ? 'active' : ''; ?>">
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
