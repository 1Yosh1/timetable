<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    die("Access Denied.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schedule_id = $_POST['schedule_id'];
    $attendance_date = $_POST['attendance_date'];
    $attendance_data = isset($_POST['attendance']) ? $_POST['attendance'] : [];

    if (empty($schedule_id) || empty($attendance_date) || empty($attendance_data)) {
        echo "Error: Missing data. Please fill out the form completely.";
        exit();
    }

    $stmt_course = $conn->prepare("SELECT course_id FROM schedules WHERE id = ?");
    $stmt_course->bind_param("i", $schedule_id);
    $stmt_course->execute();
    $course_id_result = $stmt_course->get_result()->fetch_assoc();
    if (!$course_id_result) {
        die("Invalid schedule selected.");
    }
    $course_id = $course_id_result['course_id'];

    $stmt_enrolled = $conn->prepare("SELECT student_id FROM enrollments WHERE course_id = ?");
    $stmt_enrolled->bind_param("i", $course_id);
    $stmt_enrolled->execute();
    $enrolled_result = $stmt_enrolled->get_result()->fetch_all(MYSQLI_ASSOC);
    $enrolled_student_ids = array_column($enrolled_result, 'student_id');


    $conn->begin_transaction();
    try {
        $insert_stmt = $conn->prepare("
            INSERT INTO attendance (student_id, schedule_id, status, date) VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE status = VALUES(status)
        ");
        
        foreach ($attendance_data as $student_id => $status) {
            if (in_array($student_id, $enrolled_student_ids)) {
                $insert_stmt->bind_param("isss", $student_id, $schedule_id, $status, $attendance_date);
                $insert_stmt->execute();
            }
        }

        $conn->commit();
        $msg = urlencode('Attendance marked successfully!');
        header("Location: teacher_dashboard.php?ok=1&msg={$msg}");
        exit();

    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        $msg = urlencode('Failed to mark attendance. A database error occurred.');
        header("Location: teacher_dashboard.php?error=1&msg={$msg}");
        exit();
    }
    $stmt_course->close();
    $stmt_enrolled->close();
    $insert_stmt->close();
}
$conn->close();
?>