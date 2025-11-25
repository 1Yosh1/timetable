<?php
namespace App\Domain;

final class DayOfWeek {
    private const VALID = ['Monday','Tuesday','Wednesday','Thursday','Friday'];

    public static function all(): array {
        return self::VALID;
    }

    public static function isValid(string $day): bool {
        return in_array($day, self::VALID, true);
    }
}