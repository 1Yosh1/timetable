<?php
function requireRole(array $roles): void {
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? null, $roles, true)) {
        header("Location: index.php");
        exit();
    }
}
function regenerateSession(): void {
    if (!isset($_SESSION['__rotated'])) {
        session_regenerate_id(true);
        $_SESSION['__rotated'] = time();
    }
}
