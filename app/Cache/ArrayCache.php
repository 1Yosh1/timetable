<?php
namespace App\Cache;

final class ArrayCache {
    private static array $store = [];

    public static function remember(string $key, int $ttlSeconds, callable $resolver) {
        $now = time();
        if (isset(self::$store[$key]) && self::$store[$key]['exp'] > $now) {
            return self::$store[$key]['val'];
        }
        $val = $resolver();
        self::$store[$key] = ['val' => $val, 'exp' => $now + $ttlSeconds];
        return $val;
    }

    public static function clear(): void {
        self::$store = [];
    }
}