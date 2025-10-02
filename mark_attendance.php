<?php
session_start();
require_once 'db_config.php';

// Ensure a teacher is logged in
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

    // --- NEW VALIDATION LOGIC ---
    // 1. Get the course_id from the schedule_id
    $stmt_course = $conn->prepare("SELECT course_id FROM schedules WHERE id = ?");
    $stmt_course->bind_param("i", $schedule_id);
    $stmt_course->execute();
    $course_id_result = $stmt_course->get_result()->fetch_assoc();
    if (!$course_id_result) {
        die("Invalid schedule.");
    }
    $course_id = $course_id_result['course_id'];

    // 2. Get a list of all students officially enrolled in this course
    $stmt_enrolled = $conn->prepare("SELECT student_id FROM enrollments WHERE course_id = ?");
    $stmt_enrolled->bind_param("i", $course_id);
    $stmt_enrolled->execute();
    $enrolled_result = $stmt_enrolled->get_result()->fetch_all(MYSQLI_ASSOC);
    $enrolled_student_ids = array_column($enrolled_result, 'student_id');
    // --- END OF VALIDATION LOGIC ---


    // Use a transaction for atomicity: all or nothing.
    $conn->begin_transaction();
    try {
        // Prepare statement for inserting new attendance records
        $insert_stmt = $conn->prepare("
            INSERT INTO attendance (student_id, schedule_id, status, date) VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE status = VALUES(status)
        ");
        
        // You should add a UNIQUE KEY to your attendance table on (student_id, schedule_id, date) for ON DUPLICATE KEY to work.
        // ALTER TABLE attendance ADD UNIQUE KEY `student_schedule_date` (`student_id`, `schedule_id`, `date`);
        
        foreach ($attendance_data as $student_id => $status) {
            // ONLY insert if the student is in the enrolled list
            if (in_array($student_id, $enrolled_student_ids)) {
                $insert_stmt->bind_param("isss", $student_id, $schedule_id, $status, $attendance_date);
                $insert_stmt->execute();
            }
        }

        $conn->commit();
        echo "<script>alert('Attendance marked successfully!'); window.location.href='teacher_dashboard.php';</script>";

    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        echo "<script>alert('Failed to mark attendance. A database error occurred.'); window.location.href='teacher_dashboard.php';</script>";
    }
    $stmt_course->close();
    $stmt_enrolled->close();
    $insert_stmt->close();
}
$conn->close();
?>