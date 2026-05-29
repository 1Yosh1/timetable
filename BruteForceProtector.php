<?php
declare(strict_types=1);

final class BruteForceProtector {
    private static string $dir = __DIR__ . '/storage/bruteforce';

    public static function check(string $username, int $maxAttempts = 5, int $lockoutTime = 300): void {
        if (!is_dir(self::$dir)) {
            mkdir(self::$dir, 0755, true);
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $key = hash('sha256', $username . '|' . $ip);
        $file = self::$dir . '/' . $key . '.json';

        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && isset($data['attempts'], $data['last_attempt'])) {
                if ($data['attempts'] >= $maxAttempts && (time() - $data['last_attempt']) < $lockoutTime) {
                    $remaining = $lockoutTime - (time() - $data['last_attempt']);
                    http_response_code(429);
                    die("Too many login attempts. Please try again in " . ceil($remaining / 60) . " minutes.");
                }
                if ((time() - $data['last_attempt']) >= $lockoutTime) {
                    // Lockout period expired, reset attempts
                    $data['attempts'] = 0;
                    file_put_contents($file, json_encode($data));
                }
            }
        }
    }

    public static function registerFailure(string $username): void {
        if (!is_dir(self::$dir)) {
            mkdir(self::$dir, 0755, true);
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $key = hash('sha256', $username . '|' . $ip);
        $file = self::$dir . '/' . $key . '.json';

        $attempts = 1;
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && isset($data['attempts'])) {
                $attempts = $data['attempts'] + 1;
            }
        }

        file_put_contents($file, json_encode([
            'attempts' => $attempts,
            'last_attempt' => time()
        ]));
    }

    public static function registerSuccess(string $username): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $key = hash('sha256', $username . '|' . $ip);
        $file = self::$dir . '/' . $key . '.json';
        if (file_exists($file)) {
            unlink($file);
        }
    }
}
