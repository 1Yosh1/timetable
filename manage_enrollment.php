<?php
session_start();
require_once 'db_config.php';

// Set header to return JSON for AJAX requests
header('Content-Type: application/json');

// Security check: ensure a student is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit();
}

// Check if the request is a valid POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'], $_POST['course_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$course_id = $_POST['course_id'];
$action = $_POST['action'];
$stmt = null;

// Use a switch statement to handle the different actions
switch ($action) {
    case 'enroll':
        // Double-check the enrollment count on the server
        $count_query = $conn->prepare("SELECT COUNT(id) as count FROM enrollments WHERE student_id = ?");
        $count_query->bind_param("i", $user_id);
        $count_query->execute();
        $enrollment_count = $count_query->get_result()->fetch_assoc()['count'];
        
        if ($enrollment_count < 5) {
            $stmt = $conn->prepare("INSERT IGNORE INTO enrollments (student_id, course_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $user_id, $course_id);
        } else {
             // This case should not be reached with the new UI, but it's a good safeguard
             echo json_encode(['success' => false, 'message' => 'Enrollment limit reached. Please request approval.']);
             exit();
        }
        break;

    case 'unenroll':
        $stmt = $conn->prepare("DELETE FROM enrollments WHERE student_id = ? AND course_id = ?");
        $stmt->bind_param("ii", $user_id, $course_id);
        break;

    case 'request_approval':
        // This is the new case that was missing
        $stmt = $conn->prepare("INSERT INTO pending_enrollments (student_id, course_id, status) VALUES (?, ?, 'pending')");
        $stmt->bind_param("ii", $user_id, $course_id);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
        exit();
}

// Execute the prepared statement
if ($stmt && $stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    // Provide a more specific error if possible, otherwise generic
    $error_message = $stmt ? $stmt->error : $conn->error;
    echo json_encode(['success' => false, 'message' => 'Database operation failed.']);
}

if($stmt) $stmt->close();
$conn->close();
?>