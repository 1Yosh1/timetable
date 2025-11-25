<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/csrf.php';
require_once __DIR__ . '/app/Domain/DayOfWeek.php';
require_once __DIR__ . '/app/Domain/TimeSlot.php';

use App\Domain\DayOfWeek;
use App\Domain\TimeSlot;

/* ------------------------------------------------------------------
   HTTP / CSRF / AUTH GUARDS
------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

if (!verify_csrf($_POST['csrf_token'] ?? null)) {
    http_response_code(419);
    exit('Invalid CSRF token');
}

$isAdmin = (
    isset($_SESSION['admin_id']) ||
    (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin')
);

if (!$isAdmin) {
    http_response_code(403);
    exit('Access denied');
}

/* ------------------------------------------------------------------
   INPUT
------------------------------------------------------------------ */
$action      = $_POST['action'] ?? '';
$sourcePage  = preg_replace('/[^a-z_]/i','', $_POST['source_page'] ?? '');
$redirectPage = $sourcePage !== '' ? $sourcePage : 'home';

$success = true;
$msg = '';

/* Use existing mysqli connection ($conn) from bootstrap/db_config */
if (!isset($conn) || !$conn instanceof mysqli) {
    http_response_code(500);
    exit('DB connection missing');
}

/* Helper: redirect back */
function admin_redirect(string $page, string $extra = ''): void {
    $q = "admin_dashboard.php?page=" . urlencode($page);
    if ($extra !== '') $q .= "&$extra";
    header("Location: $q");
    exit;
}

/* ------------------------------------------------------------------
   ACTION HANDLERS
------------------------------------------------------------------ */
switch ($action) {

    /* --------------- USER MANAGEMENT ---------------- */
    case 'add_user':
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? '';

        if ($username === '' || $email === '' || $password === '' || !in_array($role, ['student','teacher'], true)) {
            $success = false; $msg = 'Invalid user data';
            break;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO users (username,email,password,role) VALUES (?,?,?,?)");
        $stmt->bind_param('ssss', $username, $email, $hash, $role);
        try {
            $stmt->execute();
            $msg = 'User added';
        } catch (mysqli_sql_exception $e) {
            $success = false;
            $msg = 'Add failed (duplicate?)';
        }
        break;

    case 'edit_user':
        $userId   = (int)($_POST['user_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $role     = $_POST['role'] ?? '';
        $newPass  = $_POST['password'] ?? '';

        if ($userId <= 0 || $username === '' || $email === '' || !in_array($role,['student','teacher'], true)) {
            $success = false; $msg = 'Invalid edit data';
            break;
        }

        if ($newPass !== '') {
            $hash = password_hash($newPass, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE users SET username=?, email=?, role=?, password=? WHERE id=? AND role!='admin'");
            $stmt->bind_param('ssssi', $username, $email, $role, $hash, $userId);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username=?, email=?, role=? WHERE id=? AND role!='admin'");
            $stmt->bind_param('sssi', $username, $email, $role, $userId);
        }
        $stmt->execute();
        $msg = 'User updated';
        break;

    case 'delete_user':
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId <= 0) { $success = false; $msg='Invalid user id'; break; }
        $stmt = $conn->prepare("DELETE FROM users WHERE id=? AND role!='admin' LIMIT 1");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $msg = $stmt->affected_rows ? 'User deleted' : 'No deletion';
        break;

    /* --------------- COURSE MANAGEMENT ---------------- */
    case 'add_course':
        $name  = trim($_POST['name'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $credits = (int)($_POST['credits'] ?? 0);
        if ($name === '') { $success=false; $msg='Course name required'; break; }
        $stmt = $conn->prepare("INSERT INTO courses (name, description, credits) VALUES (?,?,?)");
        $stmt->bind_param('ssi', $name, $desc, $credits);
        try {
            $stmt->execute();
            $msg = 'Course created';
        } catch (mysqli_sql_exception $e) {
            $success=false; $msg='Create failed';
        }
        break;

    case 'assign_teacher':
        $courseId  = (int)($_POST['course_id'] ?? 0);
        $teacherId = (int)($_POST['teacher_id'] ?? 0);
        if ($courseId <= 0 || $teacherId <= 0) { $success=false; $msg='Invalid IDs'; break; }

        $chk = $conn->prepare("SELECT id FROM users WHERE id=? AND role='teacher' LIMIT 1");
        $chk->bind_param('i', $teacherId);
        $chk->execute();
        if (!$chk->get_result()->fetch_row()) {
            $success=false; $msg='Not a teacher'; break;
        }

        $stmt = $conn->prepare("UPDATE courses SET teacher_id=? WHERE id=?");
        $stmt->bind_param('ii', $teacherId, $courseId);
        $stmt->execute();
        $msg = 'Teacher assigned';
        break;

    /* --------------- SCHEDULE MANAGEMENT ---------------- */
    case 'add_schedule':
        $courseId = (int)($_POST['course_id'] ?? 0);
        $roomId   = (int)($_POST['room_id'] ?? 0);
        $day      = $_POST['day_of_week'] ?? '';
        $slot     = $_POST['timeslot'] ?? '';

        if ($courseId<=0 || $roomId<=0 || !DayOfWeek::isValid($day) || !TimeSlot::isValid($slot)) {
            $success=false; $msg='Invalid schedule data'; break;
        }

        // Room conflict
        $q1 = $conn->prepare("SELECT id FROM schedules WHERE room_id=? AND day_of_week=? AND timeslot=? LIMIT 1");
        $q1->bind_param('iss', $roomId, $day, $slot);
        $q1->execute();
        if ($q1->get_result()->fetch_row()) {
            $success=false; $msg='Room already booked'; break;
        }

        // Course duplicate slot
        $q2 = $conn->prepare("SELECT id FROM schedules WHERE course_id=? AND day_of_week=? AND timeslot=? LIMIT 1");
        $q2->bind_param('iss', $courseId, $day, $slot);
        $q2->execute();
        if ($q2->get_result()->fetch_row()) {
            $success=false; $msg='Course already in that slot'; break;
        }

        // Teacher conflict (if course has teacher)
        $q3 = $conn->prepare(
            "SELECT 1
             FROM schedules s
             JOIN courses c1 ON c1.id = s.course_id
             JOIN courses c2 ON c2.id = ?
             WHERE s.day_of_week=? AND s.timeslot=? AND c1.teacher_id=c2.teacher_id AND c2.teacher_id IS NOT NULL
             LIMIT 1"
        );
        $q3->bind_param('iss', $courseId, $day, $slot);
        $q3->execute();
        if ($q3->get_result()->fetch_row()) {
            $success=false; $msg='Teacher conflict'; break;
        }

        $ins = $conn->prepare("INSERT INTO schedules (course_id, room_id, day_of_week, timeslot) VALUES (?,?,?,?)");
        $ins->bind_param('iiss', $courseId, $roomId, $day, $slot);
        $ins->execute();
        $msg = 'Schedule added';
        break;

    /* --------------- ENROLLMENT REQUESTS ---------------- */
    case 'approve_enrollment':
        $reqId = (int)($_POST['request_id'] ?? 0);
        if ($reqId <= 0) { $success=false; $msg='Invalid request id'; break; }

        // Fetch pending
        $p = $conn->prepare("SELECT student_id, course_id FROM pending_enrollments WHERE id=? AND status='pending' LIMIT 1");
        $p->bind_param('i', $reqId);
        $p->execute();
        $row = $p->get_result()->fetch_assoc();
        if (!$row) { $success=false; $msg='Request not found'; break; }

        $studentId = (int)$row['student_id'];
        $courseId  = (int)$row['course_id'];

        // Enroll (ignore duplicate silently via INSERT IGNORE style approach)
        $en = $conn->prepare("INSERT IGNORE INTO enrollments (student_id, course_id) VALUES (?,?)");
        $en->bind_param('ii', $studentId, $courseId);
        $en->execute();

        $up = $conn->prepare("UPDATE pending_enrollments SET status='approved', processed_at=NOW() WHERE id=?");
        $up->bind_param('i', $reqId);
        $up->execute();

        $msg = 'Enrollment approved';
        break;

    case 'deny_enrollment':
        $reqId = (int)($_POST['request_id'] ?? 0);
        if ($reqId <= 0) { $success=false; $msg='Invalid request id'; break; }
        $up = $conn->prepare("UPDATE pending_enrollments SET status='denied', processed_at=NOW() WHERE id=? AND status='pending'");
        $up->bind_param('i', $reqId);
        $up->execute();
        $msg = 'Enrollment denied';
        break;

    default:
        $success = false;
        $msg = 'Unknown action';
        break;
}

/* ------------------------------------------------------------------
   REDIRECT
------------------------------------------------------------------ */
$statusParam = $success ? 'ok=1' : 'error=1';
$messageParam = 'msg=' . urlencode($msg);
admin_redirect($redirectPage, $statusParam . '&' . $messageParam);