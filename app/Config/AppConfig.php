<?php
namespace App\Config;

final class AppConfig
{
    public const STUDENT_ENROLLMENT_LIMIT = 5;
    private static array $cache = [];

    public static function get(string $key, $default = null)
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }
        $val = $_ENV[$key] ?? getenv($key);
        if ($val === false || $val === null || $val === '') {
            $val = $default;
        }
        return self::$cache[$key] = $val;
    }

    public static function maxCourses(): int
    {
        return (int) self::get('MAX_COURSES', 6);
    }

    public static function appName(): string
    {
        return (string) self::get('APP_NAME', 'Timetable');
    }

    public static function debug(): bool
    {
        return self::get('APP_DEBUG', '0') === '1';
    }
}
