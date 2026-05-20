<?php
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

$stmtUser = $conexion->prepare("SELECT nombre, correo FROM prestamista WHERE id_prestamista = ?");
$stmtUser->execute([$id_prestamista]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

$getCfg = $conexion->prepare("SELECT notificaciones, idioma, moneda FROM configuracion_usuario WHERE id_prestamista = ?");
$getCfg->execute([$id_prestamista]);
$cfg = $getCfg->fetch(PDO::FETCH_ASSOC);

$msg = '';
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    csrf_validate_post();
    $notificaciones = isset($_POST['notificaciones']) ? 1 : 0;
    $idioma = $_POST['idioma'] ?? 'es';
    $moneda = $_POST['moneda'] ?? 'COP';

    if($cfg){
        $up = $conexion->prepare("UPDATE configuracion_usuario SET notificaciones=?, idioma=?, moneda=? WHERE id_prestamista=?");
        $up->execute([$notificaciones, $idioma, $moneda, $id_prestamista]);
    } else {
        $ins = $conexion->prepare("INSERT INTO configuracion_usuario(id_prestamista, notificaciones, idioma, moneda) VALUES(?,?,?,?)");
        $ins->execute([$id_prestamista, $notificaciones, $idioma, $moneda]);
    }
    $msg = 'Configuración guardada';
    $getCfg->execute([$id_prestamista]);
    $cfg = $getCfg->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<?php
$pageTitle = 'Configuración - SoloDeudas';
$assetBaseUrl = '../../assets';
require __DIR__ . '/../_head.php';
?>
</head>
<body>
<?php
$navContext = 'pages';
$navActive = 'configuracion';
require __DIR__ . '/../_nav.php';
?>

<section class="section-container">
    <div class="section-header">
        <div class="header-title">
            <i class="fas fa-cog"></i>
            <h2>Configuración</h2>
        </div>
        <div class="header-actions">
            <a href="../index.php" class="btn-secondary">
                <i class="fas fa-home"></i> Volver al inicio
            </a>
        </div>
    </div>
    
    <?php if($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

    <div class="card" style="max-width: 600px; margin: 0 auto;">
        <form method="post" class="filter-form" style="display: flex; flex-direction: column; gap: 20px;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="form-group" style="display: flex; align-items: center; gap: 10px;">
                <input type="checkbox" name="notificaciones" id="notificaciones" <?php echo !empty($cfg['notificaciones']) ? 'checked' : ''; ?> style="width: auto;">
                <label for="notificaciones" style="margin-bottom: 0;">Habilitar notificaciones</label>
            </div> 

            <div class="form-group">
                <label><i class="fas fa-language"></i> Idioma</label>
                <select name="idioma">
                    <option value="es" <?php echo ($cfg['idioma'] ?? 'es')==='es'?'selected':''; ?>>Español</option>
                    <option value="en" <?php echo ($cfg['idioma'] ?? 'es')==='en'?'selected':''; ?>>English</option>
                </select>
            </div>

            <div class="form-group">
                <label><i class="fas fa-money-bill-wave"></i> Moneda</label>
                <select name="moneda">
                    <option value="COP" <?php echo ($cfg['moneda'] ?? 'COP')==='COP'?'selected':''; ?>>COP</option>
                    <option value="USD" <?php echo ($cfg['moneda'] ?? 'COP')==='USD'?'selected':''; ?>>USD</option>
                </select>
            </div>

            <div class="form-actions" style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 10px;">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</section>

<footer>
    <p>&copy; <?php echo date("Y"); ?> SoloDeudas. Todos los derechos reservados.</p>
</footer>

<script src="../../assets/js/main.js"></script>
</body>
</html>
