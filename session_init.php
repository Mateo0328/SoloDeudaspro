<?php
if (!isset($_SESSION)) {
    $sessionPath = "C:/xampp/htdocs/SoloDeudadPro/tmp";
    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0777, true);
    }
    session_save_path($sessionPath);
}

if(!function_exists('csrf_token')){
    function csrf_token(): string {
        if(session_status() !== PHP_SESSION_ACTIVE){
            return '';
        }
        if(empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])){
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['csrf_token'];
    }
}

if(!function_exists('csrf_validate_post')){
    function csrf_validate_post(): void {
        if(($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'){
            return;
        }
        if(session_status() !== PHP_SESSION_ACTIVE){
            http_response_code(403);
            exit;
        }
        $token = $_POST['csrf_token'] ?? '';
        if(!is_string($token) || $token === '' || empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)){
            http_response_code(403);
            exit;
        }
    }
}
?>
