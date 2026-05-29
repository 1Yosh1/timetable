<?php
session_start();
require_once 'db_config.php';
require_once 'BruteForceProtector.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    BruteForceProtector::check($username);

    $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ? AND role = 'admin'");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($admin_id, $hashed_password);
        $stmt->fetch();

        if (password_verify($password, $hashed_password)) {
            BruteForceProtector::registerSuccess($username);
            $_SESSION['admin_id'] = $admin_id;
            header("Location: admin_dashboard.php");
            exit();
        }
    }

    BruteForceProtector::registerFailure($username);
    echo "Invalid admin credentials.";
    $stmt->close();
}
$conn->close();
?>
