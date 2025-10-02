<?php
session_start();
require_once 'db_config.php';

// Ensure a student is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['course_id'])) {
    $student_id = $_SESSION['user_id'];
    $course_id = $_POST['course_id'];

    // Prepare the SQL to prevent SQL injection
    $stmt = $conn->prepare("INSERT INTO enrollments (student_id, course_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $student_id, $course_id);

    // Execute the statement and check for errors
    if ($stmt->execute()) {
        // Redirect back to the dashboard with a success message
        echo "<script>alert('Enrollment successful!'); window.location.href='student_dashboard.php#my-courses';</script>";
    } else {
        // If the enrollment failed (e.g., already enrolled), show an error
        echo "<script>alert('Enrollment failed. You may already be enrolled in this course.'); window.location.href='student_dashboard.php#available-courses';</script>";
    }

    $stmt->close();
}
$conn->close();
?>