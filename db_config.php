<?php
declare(strict_types=1);

if (!extension_loaded('mysqli')) {
    http_response_code(500);
    die('MySQLi extension not loaded.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
$db   = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'timetable';
$user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root';
$pass = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '';
$port = (int)($_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: 3306);

try {
    $conn = new mysqli($host, $user, $pass, $db, $port);
    $conn->set_charset('utf8mb4');
} catch (\mysqli_sql_exception $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('Database connection error.');
}

if (!isset($conn) || $conn->connect_errno) {
    error_log('DB not connected.');
}