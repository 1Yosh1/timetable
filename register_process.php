<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/csrf.php';
require_once __DIR__ . '/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

if (!verify_csrf($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('CSRF validation failed');
}

$username = trim($_POST['username'] ?? '');
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$role     = $_POST['role'] ?? '';

// Validate fields are not empty
if ($username === '' || $email === '' || $password === '' || $role === '') {
    http_response_code(422);
    exit('All fields are required.');
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    exit('Invalid email address.');
}

// RESTRICT ROLES: Only allow registration as student or teacher (never admin)
if (!in_array($role, ['student', 'teacher'], true)) {
    http_response_code(403);
    exit('Invalid role selected.');
}

// Validate username length and format
if (strlen($username) < 3 || strlen($username) > 50 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    http_response_code(422);
    exit('Username must be 3-50 characters and contain only alphanumeric characters and underscores.');
}

// Validate password length
if (strlen($password) < 6) {
    http_response_code(422);
    exit('Password must be at least 6 characters long.');
}

$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

$stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $username, $email, $hashedPassword, $role);

if ($stmt->execute()) {
    echo "Registration successful. <a href='index.php'>Login here</a>";
} else {
    http_response_code(500);
    echo "Error registering user. The username might already be in use.";
}

$stmt->close();
$conn->close();
?>
