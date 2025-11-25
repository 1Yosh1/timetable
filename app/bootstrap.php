<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/Support/EnvLoader.php';
App\Support\EnvLoader::load(__DIR__ . '/../.env');

if (!class_exists('App\\Debug\\Inspector')) {
    // autoload fallback
    spl_autoload_register(function($c){
        if (str_starts_with($c,'App\\')) {
            $p = __DIR__ . '/' . str_replace('\\','/',substr($c,4)) . '.php';
            if (is_file($p)) require $p;
        }
    });
}

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Enable debug if APP_DEBUG=1 in .env
if (getenv('APP_DEBUG') === '1') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

if (!function_exists('app_redirect')) {
    function app_redirect(string $to, int $code = 302): never {
        header("Location: $to", true, $code);
        exit();
    }
}
require_once __DIR__ . '/../db_config.php';

header_remove('X-Powered-By');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');