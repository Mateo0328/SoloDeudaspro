<?php
$navContext = isset($navContext) && is_string($navContext) && $navContext !== '' ? $navContext : 'auth';
$navActive = isset($navActive) && is_string($navActive) ? $navActive : '';
$showClientes = isset($showClientes) ? (bool)$showClientes : true;
$showAdmin = isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
$nombreUsuario = isset($_SESSION['nombre']) && is_string($_SESSION['nombre']) ? $_SESSION['nombre'] : '';

$links = [
    'inicio' => $navContext === 'pages' ? '../index.php' : 'index.php',
    'prestamos' => $navContext === 'pages' ? 'prestamos.php' : 'pages/prestamos.php',
    'clientes' => $navContext === 'pages' ? 'clientes.php' : 'pages/clientes.php',
    'recordatorios' => $navContext === 'pages' ? 'recordatorios.php' : 'pages/recordatorios.php',
    'configuracion' => $navContext === 'pages' ? 'configuracion.php' : 'pages/configuracion.php',
    'perfil' => $navContext === 'pages' ? 'perfil.php' : 'pages/perfil.php',
    'logout' => $navContext === 'pages' ? '../logout.php' : 'logout.php',
    'admin' => [
        'usuarios' => $navContext === 'pages' ? 'consultarUsuarios.php' : 'pages/consultarUsuarios.php',
        'accesos' => $navContext === 'pages' ? 'historialAccesos.php' : 'pages/historialAccesos.php',
        'trazabilidad' => $navContext === 'pages' ? 'trazabilidad.php' : 'pages/trazabilidad.php',
        'sesiones' => $navContext === 'pages' ? 'sesiones.php' : 'pages/sesiones.php',
    ],
];
?>
<nav class="navbar">
    <div class="logo">SD</div>
    <ul class="nav-links">
        <li><a href="<?php echo htmlspecialchars($links['inicio'], ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $navActive === 'inicio' ? 'active' : ''; ?>">Inicio</a></li>
        <li><a href="<?php echo htmlspecialchars($links['prestamos'], ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $navActive === 'prestamos' ? 'active' : ''; ?>">Préstamos</a></li>
        <?php if($showClientes): ?>
            <li><a href="<?php echo htmlspecialchars($links['clientes'], ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $navActive === 'clientes' ? 'active' : ''; ?>">Clientes</a></li>
        <?php endif; ?>
        <li><a href="<?php echo htmlspecialchars($links['recordatorios'], ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $navActive === 'recordatorios' ? 'active' : ''; ?>">Recordatorios</a></li>
        <li><a href="<?php echo htmlspecialchars($links['configuracion'], ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $navActive === 'configuracion' ? 'active' : ''; ?>">Configuración</a></li>
        <?php if($showAdmin): ?>
            <li class="dropdown">
                <a href="#" class="dropdown-toggle <?php echo $navActive === 'admin' ? 'active' : ''; ?>">Administración</a>
                <ul class="dropdown-menu">
                    <li><a href="<?php echo htmlspecialchars($links['admin']['usuarios'], ENT_QUOTES, 'UTF-8'); ?>">Gestión de Usuarios</a></li>
                    <li><a href="<?php echo htmlspecialchars($links['admin']['accesos'], ENT_QUOTES, 'UTF-8'); ?>">Historial de Accesos</a></li>
                    <li><a href="<?php echo htmlspecialchars($links['admin']['trazabilidad'], ENT_QUOTES, 'UTF-8'); ?>">Trazabilidad (Auditoría)</a></li>
                    <li><a href="<?php echo htmlspecialchars($links['admin']['sesiones'], ENT_QUOTES, 'UTF-8'); ?>">Sesiones</a></li>
                </ul>
            </li>
        <?php endif; ?>
    </ul>
    <div class="user-menu">
        <span><?php echo htmlspecialchars($nombreUsuario, ENT_QUOTES, 'UTF-8'); ?><?php echo $showAdmin ? ' (Admin)' : ''; ?></span>
        <ul>
            <li><a href="<?php echo htmlspecialchars($links['perfil'], ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $navActive === 'perfil' ? 'active' : ''; ?>">Perfil</a></li>
            <li><a href="<?php echo htmlspecialchars($links['logout'], ENT_QUOTES, 'UTF-8'); ?>">Cerrar sesión</a></li>
        </ul>
    </div>
</nav>
