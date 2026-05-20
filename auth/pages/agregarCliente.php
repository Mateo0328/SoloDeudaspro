<?php
// pages/agregarCliente.php
require_once "../../session_init.php";
session_start();
require __DIR__ . '/../../config/conexion.php';
if(!isset($_SESSION['id_usuario'])){
    header("Location: ../login.php");
    exit;
}

$id_prestamista = $_SESSION['id_usuario'];
require_once __DIR__ . "/../session_control.php";
if(isset($_SESSION['force_password_change']) && $_SESSION['force_password_change'] === true){
    header("Location: forzarCambioPassword.php");
    exit;
}

$success = '';
$error = '';
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar'])){
    csrf_validate_post();
    try {
        $cedula = trim($_POST['cedula'] ?? '');
        $nombre = trim($_POST['nombre']);
        $correo = filter_var($_POST['correo'], FILTER_SANITIZE_EMAIL);
        $telefono = trim($_POST['telefono']);
        $direccion = trim($_POST['direccion']);

        if($cedula === ''){
            throw new Exception("La cédula es obligatoria");
        }

        $dup = $conexion->prepare("SELECT 1 FROM cliente WHERE cedula = ? LIMIT 1");
        $dup->execute([$cedula]);
        if($dup->fetchColumn()){
            throw new Exception("Ya existe un cliente registrado con esa cédula");
        }

        $ins = $conexion->prepare("INSERT INTO cliente (id_prestamista, cedula, nombre, correo, telefono, direccion, estado) VALUES (?, ?, ?, ?, ?, ?, 'activo')");
        $ins->execute([$id_prestamista, $cedula, $nombre, $correo, $telefono, $direccion]);
        $id_cliente = (int)$conexion->lastInsertId();

        try {
            $log = $conexion->prepare("INSERT INTO trazabilidad (accion, id_registro, descripcion, tabla_afectada, usuario, fecha_evento) VALUES ('INSERT', ?, 'Cliente creado', 'cliente', ?, NOW())");
            $log->execute([$id_cliente, $id_prestamista]);
        } catch (Exception $e) {
        }

        $success = "Cliente agregado correctamente";
    } catch (Exception $e) {
        $error = "Error al agregar cliente: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<?php
$pageTitle = 'Agregar Cliente - SoloDeudas';
$assetBaseUrl = '../../assets';
$includeAOS = true;
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
            <i class="fas fa-user-plus"></i>
            <h2>Agregar Cliente</h2>
        </div>
        <div class="header-actions">
            <a href="clientes.php" class="btn-secondary">
                <i class="fas fa-users"></i> Volver a Clientes
            </a>
        </div>
    </div>
    
    <?php if($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="card" style="max-width: 600px; margin: 0 auto;">
        <form method="post" class="filter-form" style="display: flex; flex-direction: column; gap: 20px;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="form-group">
                <label>Cédula / ID</label>
                <input type="text" name="cedula" required placeholder="Ej: 123456789">
            </div>
            <div class="form-group">
                <label>Nombre Completo</label>
                <input type="text" name="nombre" required placeholder="Ej: Juan Pérez">
            </div>
            <div class="form-group">
                <label>Correo electrónico</label>
                <input type="email" name="correo" required placeholder="correo@ejemplo.com">
            </div>
            <div class="form-group">
                <label>Teléfono</label>
                <input type="text" name="telefono" required placeholder="Ej: 300 123 4567">
            </div>
            <div class="form-group">
                <label>Dirección</label>
                <textarea name="direccion" rows="3" required placeholder="Dirección de residencia"></textarea>
            </div>
            <div class="form-actions" style="display: flex; justify-content: flex-end; margin-top: 10px;">
                <button type="submit" name="agregar" class="btn-primary">
                    <i class="fas fa-save"></i> Guardar Cliente
                </button>
            </div>
        </form>
    </div>
</section>
<footer>
    <p>SoloDeudas &copy; <?php echo date("Y"); ?></p>
</footer>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>AOS.init({duration:900, once:true});</script>
</body>
</html>
