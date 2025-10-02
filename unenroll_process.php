<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    die("Access Denied.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['course_id'])) {
    $student_id = $_SESSION['user_id'];
    $course_id = $_POST['course_id'];

    $stmt = $conn->prepare("DELETE FROM enrollments WHERE student_id = ? AND course_id = ?");
    $stmt->bind_param("ii", $student_id, $course_id);
    $stmt->execute();
    $stmt->close();
}

header("Location: student_dashboard.php#my-courses");
exit();
?>