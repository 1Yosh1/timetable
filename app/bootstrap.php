<?php
// Fail visibly on an old PHP instead of a blank page: display_errors is off in
// production mode, so a lab machine on PHP 7.4 would otherwise show nothing.
// The codebase needs 8.1+ ("never" return type, str_starts_with, enums).
if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('This application requires PHP 8.1 or newer.');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/Support/EnvLoader.php';
App\Support\EnvLoader::load(__DIR__ . '/../.env');

// Attendance dates are written with date(), so the zone must be explicit.
// A php.ini default of UTC turns an evening demo's date into "yesterday".
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Africa/Addis_Ababa');

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

// Errors are logged always, printed only when APP_DEBUG=1 in .env.
// Without the explicit "off" branch, a php.ini that ships display_errors=On
// (XAMPP's default) prints stack traces to the browser in production.
if (getenv('APP_DEBUG') === '1') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
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