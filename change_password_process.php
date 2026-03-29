<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/csrf.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== 'student') {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Invalid request method.");
}

if (!verify_csrf($_POST['csrf_token'] ?? null)) {
    die("CSRF token validation failed.");
}

require_once 'db_config.php';

$user_id = (int)$_SESSION['user_id'];
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
    header("Location: profile.php?msg=" . urlencode("All password fields are required."));
    exit();
}

if ($new_password !== $confirm_password) {
    header("Location: profile.php?msg=" . urlencode("New passwords do not match."));
    exit();
}

$stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if (!$result || !password_verify($current_password, $result['password'])) {
    header("Location: profile.php?msg=" . urlencode("Incorrect current password."));
    exit();
}

$new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
$update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$update_stmt->bind_param("si", $new_hashed_password, $user_id);

if ($update_stmt->execute()) {
    header("Location: profile.php?ok=1&msg=" . urlencode("Password updated successfully."));
} else {
    header("Location: profile.php?msg=" . urlencode("An error occurred. Could not update password."));
}
exit();