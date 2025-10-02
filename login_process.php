<?php
session_start();
require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $role = $_POST['role'];

    // Prepare statement to select the user by username and role
    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ? AND role = ?");
    $stmt->bind_param("ss", $username, $role);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Verify the password
        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['username'] = $user['username']; // Added for personalization

            // Redirect based on role
            if ($user['role'] === 'teacher') {
                header("Location: teacher_dashboard.php");
            } else if ($user['role'] === 'student') {
                header("Location: student_dashboard.php");
            } else if ($user['role'] === 'admin') {
                header("Location: admin_dashboard.php");
            }
            exit();
        }
    }

    // If login fails, redirect back with an error message
    // You can enhance this by adding a GET parameter to show an error on the login page
    echo "Invalid credentials or role selected. <a href='index.php'>Try again</a>.";
    $stmt->close();
}
$conn->close();
?>