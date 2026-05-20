<?php
$pageTitle = isset($pageTitle) && is_string($pageTitle) && trim($pageTitle) !== '' ? $pageTitle : 'SoloDeudas';
$assetBaseUrl = isset($assetBaseUrl) && is_string($assetBaseUrl) && trim($assetBaseUrl) !== '' ? rtrim($assetBaseUrl, '/') : '../assets';
$includeInicioCss = isset($includeInicioCss) ? (bool)$includeInicioCss : false;
$includeAOS = isset($includeAOS) ? (bool)$includeAOS : false;
$includeChartJs = isset($includeChartJs) ? (bool)$includeChartJs : false;
?>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php if($includeChartJs): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php endif; ?>
<?php if($includeAOS): ?>
<link rel="stylesheet" href="https://unpkg.com/aos@2.3.1/dist/aos.css">
<?php endif; ?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($assetBaseUrl, ENT_QUOTES, 'UTF-8'); ?>/css/main.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/main.css'); ?>">
<?php if($includeInicioCss): ?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($assetBaseUrl, ENT_QUOTES, 'UTF-8'); ?>/css/inicio.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/inicio.css'); ?>">
<?php endif; ?>
