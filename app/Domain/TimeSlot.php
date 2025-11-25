<?php
namespace App\Domain;

final class TimeSlot {
    private const VALID = [
        '09:00-10:00','10:00-11:00','11:00-12:00','12:00-13:00',
        '13:00-14:00','14:00-15:00','15:00-16:00','16:00-17:00'
    ];

    public static function all(): array {
        return self::VALID;
    }

    public static function isValid(string $slot): bool {
        return in_array($slot, self::VALID, true);
    }
}