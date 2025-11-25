<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

if (!verify_csrf($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('CSRF validation failed');
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$role     = $_POST['role'] ?? '';

if ($username === '' || $password === '' || !in_array($role, ['student','teacher','admin'], true)) {
    http_response_code(422);
    exit('Invalid input');
}

$stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username=? AND role=? LIMIT 1");
$stmt->bind_param("ss", $username, $role);
$stmt->execute();
$res = $stmt->get_result();
if ($user = $res->fetch_assoc()) {
    if (password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        header('Location: ' . ($user['role'] === 'admin'
            ? 'admin_dashboard.php'
            : ($user['role'] === 'teacher' ? 'teacher_dashboard.php' : 'student_dashboard.php')));
        exit;
    }
}
http_response_code(401);
exit('Invalid credentials');