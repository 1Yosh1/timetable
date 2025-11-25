<?php
namespace App\Core;
use mysqli;
use mysqli_sql_exception;

final class Database {
    private static ?mysqli $instance = null;

    public static function get(): mysqli {
        if (self::$instance === null) {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $host = getenv('DB_HOST') ?: 'localhost';
            $db   = getenv('DB_NAME') ?: 'timetable';
            $user = getenv('DB_USER') ?: 'root';
            $pass = getenv('DB_PASS') ?: '';
            $port = (int)(getenv('DB_PORT') ?: 3306);
            try {
                self::$instance = new mysqli($host, $user, $pass, $db, $port);
                self::$instance->set_charset('utf8mb4');
            } catch (mysqli_sql_exception $e) {
                http_response_code(500);
                die('Database connection failed.');
            }
        }
        return self::$instance;
    }

    public static function close(): void {
        if (self::$instance) {
            self::$instance->close();
            self::$instance = null;
        }
    }
}