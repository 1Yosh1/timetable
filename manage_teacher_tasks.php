<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once 'app/csrf.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    die("Invalid request method.");
}

if (!verify_csrf($_POST['csrf_token'] ?? null)) {
    die("CSRF token validation failed.");
}

require_once 'db_config.php';

$action = $_POST['action'] ?? '';
$teacher_id = (int)$_SESSION['user_id'];

if ($action === 'add_announcement') {
    $course_id = (int)($_POST['course_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');

    if (empty($course_id) || empty($content)) {
        header("Location: teacher_dashboard.php?msg=" . urlencode("Content cannot be empty.") . "#announcements");
        exit();
    }

    $stmt = $conn->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $course_id, $teacher_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        die("Authorization failed: You are not assigned to this course.");
    }

    $stmt = $conn->prepare("INSERT INTO announcements (course_id, teacher_id, content) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $course_id, $teacher_id, $content);
    if ($stmt->execute()) {
        header("Location: teacher_dashboard.php?ok=1&msg=" . urlencode("Announcement posted."));
    } else {
        header("Location: teacher_dashboard.php?msg=" . urlencode("Failed to post announcement."));
    }
    exit();
}

if ($action === 'delete_announcement') {
    header('Content-Type: application/json');
    $announcement_id = (int)($_POST['announcement_id'] ?? 0);

    if ($announcement_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid announcement ID.']);
        exit;
    }

    // The WHERE clause ensures a teacher can only delete their own announcements.
    $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $announcement_id, $teacher_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Announcement deleted.']);
    } else {
        // This can happen if the announcement doesn't exist or doesn't belong to the teacher.
        echo json_encode(['success' => false, 'message' => 'Could not delete announcement. Permission denied.']);
    }
    exit;
}

if ($action === 'book_room') {
    header('Content-Type: application/json');
    $course_id = (int)($_POST['course_id'] ?? 0);
    $room_id = (int)($_POST['room_id'] ?? 0);
    $day = $_POST['day'] ?? '';
    $timeslot = $_POST['timeslot'] ?? '';

    if ($course_id <= 0 || $room_id <= 0 || empty($day) || empty($timeslot)) {
        echo json_encode(['success' => false, 'message' => 'Invalid booking data provided.']);
        exit;
    }

    // 1. Authorization: Check if teacher owns the course
    $stmt = $conn->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $course_id, $teacher_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Authorization failed: You do not own this course.']);
        exit;
    }

    // 2. Conflict Check (to prevent race conditions)
    $stmt = $conn->prepare("SELECT id FROM schedules WHERE room_id = ? AND day_of_week = ? AND timeslot = ?");
    $stmt->bind_param("iss", $room_id, $day, $timeslot);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Sorry, this slot was just booked by someone else.']);
        exit;
    }
    
    // 3. Teacher conflict check
    $stmt = $conn->prepare(
        "SELECT 1 FROM schedules s JOIN courses c ON s.course_id = c.id WHERE c.teacher_id = ? AND s.day_of_week = ? AND s.timeslot = ?"
    );
    $stmt->bind_param("iss", $teacher_id, $day, $timeslot);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'You already have another class scheduled at this time.']);
        exit;
    }

    // 4. Insert the pending schedule request
    $stmt = $conn->prepare("INSERT INTO pending_schedules (course_id, room_id, day_of_week, timeslot, teacher_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iissi", $course_id, $room_id, $day, $timeslot, $teacher_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Room booking request submitted for admin approval!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'A database error occurred while submitting the booking request.']);
    }
    exit;
}

header("Location: teacher_dashboard.php");
exit();