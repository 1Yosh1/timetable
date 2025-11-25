<?php
namespace App\Support;

class EnvLoader {
    public static function load(string $path): void {
        if (!is_file($path) || !is_readable($path)) return;
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with(trim($line), '#')) continue;
            if (!str_contains($line, '=')) continue;
            [$k,$v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v);
            if ($k === '') continue;
            if (!array_key_exists($k, $_ENV)) {
                $_ENV[$k] = $v;
                putenv("$k=$v");
            }
        }
    }
}
