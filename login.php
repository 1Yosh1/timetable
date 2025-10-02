<?php
require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $role = isset($_POST['role']) ? $_POST['role'] : null;

    $sql = "SELECT id, username, password, role FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            if ($role === 'admin' && $user['role'] !== 'admin') {
                echo "Access denied: Not an admin.";
                exit();
            }

            switch ($user['role']) {
                case 'admin':
                    header("Location: admin_dashboard.php");
                    break;
                case 'teacher':
                    header("Location: teacher_dashboard.php");
                    break;
                case 'student':
                default:
                    header("Location: student_dashboard.php");
                    break;
            }
            exit();
        } else {
            echo "Invalid password";
        }
    } else {
        echo "Invalid username";
    }

    $stmt->close();
    $conn->close();
}
?>
