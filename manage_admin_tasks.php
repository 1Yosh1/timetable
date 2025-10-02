<?php
session_start();
require_once 'db_config.php';
if (!isset($_SESSION['admin_id']) && (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin')) {
    die("Access Denied.");
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    header("Location: admin_dashboard.php");
    exit();
}
$action = $_POST['action'];
$stmt = null;
switch ($action) {
    case 'add_user':
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $_POST['username'], $_POST['email'], $password, $_POST['role']);
        break;
    case 'edit_user':
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE users SET username=?, email=?, role=?, password=? WHERE id=?");
            $stmt->bind_param("ssssi", $_POST['username'], $_POST['email'], $_POST['role'], $password, $_POST['user_id']);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username=?, email=?, role=? WHERE id=?");
            $stmt->bind_param("sssi", $_POST['username'], $_POST['email'], $_POST['role'], $_POST['user_id']);
        }
        break;
    case 'delete_user':
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $_POST['user_id']);
        break;
    case 'add_course':
        $stmt = $conn->prepare("INSERT INTO courses (name, description, credits) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $_POST['name'], $_POST['description'], $_POST['credits']);
        break;
    case 'edit_course':
        $stmt = $conn->prepare("UPDATE courses SET name = ?, description = ?, credits = ? WHERE id = ?");
        $stmt->bind_param("ssii", $_POST['name'], $_POST['description'], $_POST['credits'], $_POST['course_id']);
        break;
    case 'delete_course':
        $stmt = $conn->prepare("DELETE FROM courses WHERE id = ?");
        $stmt->bind_param("i", $_POST['course_id']);
        break;
    case 'assign_teacher':
        $stmt = $conn->prepare("UPDATE courses SET teacher_id = ? WHERE id = ?");
        $stmt->bind_param("ii", $_POST['teacher_id'], $_POST['course_id']);
        break;
    case 'add_schedule':
        $stmt = $conn->prepare("INSERT INTO schedules (course_id, room_id, day_of_week, timeslot) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $_POST['course_id'], $_POST['room_id'], $_POST['day_of_week'], $_POST['timeslot']);
        break;
    case 'approve_enrollment':
        $req_stmt = $conn->prepare("SELECT student_id, course_id FROM pending_enrollments WHERE id = ?");
        $req_stmt->bind_param("i", $_POST['request_id']);
        $req_stmt->execute();
        $request = $req_stmt->get_result()->fetch_assoc();
        if ($request) {
            $conn->begin_transaction();
            try {
                $enroll_stmt = $conn->prepare("INSERT IGNORE INTO enrollments (student_id, course_id) VALUES (?, ?)");
                $enroll_stmt->bind_param("ii", $request['student_id'], $request['course_id']);
                $enroll_stmt->execute();
                $update_stmt = $conn->prepare("UPDATE pending_enrollments SET status = 'approved' WHERE id = ?");
                $update_stmt->bind_param("i", $_POST['request_id']);
                $update_stmt->execute();
                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
            }
        }
        break;
    case 'deny_enrollment':
        $stmt = $conn->prepare("UPDATE pending_enrollments SET status = 'denied' WHERE id = ?");
        $stmt->bind_param("i", $_POST['request_id']);
        break;
}
if ($stmt) {
    $stmt->execute();
    $stmt->close();
}
$redirect_page = isset($_POST['source_page']) ? $_POST['source_page'] : 'home';
header("Location: admin_dashboard.php?page=" . urlencode($redirect_page));
$conn->close();
exit();
?>