<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\View;
use App\Domain\DayOfWeek;
use App\Domain\TimeSlot;
use mysqli_sql_exception;

class AdminController extends Controller {
    public function index(): void {
        $this->requireRole(['admin']);

        $conn = Database::get();
        require_once __DIR__ . '/../../csrf.php';
        $csrf = csrf_token();
        $page = $_GET['page'] ?? 'home';
        $conflictFlag = isset($_GET['conflict']) && $_GET['conflict'] == '1';

        $users    = $conn->query("SELECT id, username, email, role FROM users WHERE role != 'admin' ORDER BY role, username")->fetch_all(MYSQLI_ASSOC);
        $teachers = $conn->query("SELECT id, username FROM users WHERE role = 'teacher' ORDER BY username")->fetch_all(MYSQLI_ASSOC);
        $students = $conn->query("SELECT id, username FROM users WHERE role = 'student' ORDER BY username")->fetch_all(MYSQLI_ASSOC);
        $courses  = $conn->query("SELECT c.id, c.name, c.description, c.credits, u.username AS teacher_name FROM courses c LEFT JOIN users u ON c.teacher_id = u.id ORDER BY c.name")->fetch_all(MYSQLI_ASSOC);
        $rooms    = $conn->query("SELECT id, name FROM rooms ORDER BY name")->fetch_all(MYSQLI_ASSOC);

        $weekdays  = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"];
        $timeslots = ["09:00-10:00", "10:00-11:00", "11:00-12:00", "12:00-13:00", "13:00-14:00", "14:00-15:00", "15:00-16:00", "16:00-17:00"];

        $current_page_name = 'admin_dashboard.php';
        $current_sub_page  = $page;
        $management_pages  = ['users', 'courses', 'schedules', 'requests', 'reports'];
        $is_management_active = in_array($current_sub_page, $management_pages, true);

        $schedule_data = [];
        $pending_requests = [];
        $schedules = [];
        $enrollment_stats = [];
        $room_utilization = [];
        $total_slots_per_room = count($weekdays) * count($timeslots);

        if ($page === 'home') {
            $schedule_sql = "SELECT s.day_of_week, s.timeslot, c.name AS course_name, r.name AS room_name, u.username as teacher_name
                             FROM schedules s
                             JOIN courses c ON s.course_id = c.id
                             JOIN rooms r ON s.room_id = r.id
                             LEFT JOIN users u on c.teacher_id = u.id";
            $schedule_result = $conn->query($schedule_sql);
            while ($row = $schedule_result->fetch_assoc()) {
                $schedule_data[$row['day_of_week']][$row['timeslot']][] = $row;
            }
        }

        if ($page === 'requests') {
            $pending_requests = $conn->query(
                "SELECT pr.id, u.username, c.name AS course_name, pr.request_date
                 FROM pending_enrollments pr
                 JOIN users u ON pr.student_id = u.id
                 JOIN courses c ON pr.course_id = c.id
                 WHERE pr.status='pending'
                 ORDER BY pr.request_date ASC"
            )->fetch_all(MYSQLI_ASSOC);
        }

        if ($page === 'schedules') {
            $schedules = $conn->query(
                "SELECT s.id, c.name AS course_name, r.name AS room_name, s.day_of_week, s.timeslot
                 FROM schedules s
                 JOIN courses c ON s.course_id = c.id
                 JOIN rooms r ON s.room_id = r.id
                 ORDER BY FIELD(s.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday'), s.timeslot, c.name"
            )->fetch_all(MYSQLI_ASSOC);
        }

        if ($page === 'reports') {
            $enrollment_stats = $conn->query(
                "SELECT c.name, COUNT(e.student_id) as enrollment_count
                 FROM courses c
                 LEFT JOIN enrollments e ON c.id = e.course_id
                 GROUP BY c.id
                 ORDER BY enrollment_count DESC, c.name ASC"
            )->fetch_all(MYSQLI_ASSOC);

            $room_utilization = $conn->query("SELECT r.name, COUNT(s.id) as booked_slots FROM rooms r LEFT JOIN schedules s ON r.id = s.room_id GROUP BY r.id ORDER BY r.name ASC")->fetch_all(MYSQLI_ASSOC);
        }

        $baseScript = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseUri = rtrim(dirname($baseScript), '/\\');

        $msg = $_GET['msg'] ?? '';
        $ok = isset($_GET['ok']) && $_GET['ok'] == '1';

        View::render('admin/dashboard', [
            'csrf' => $csrf,
            'page' => $page,
            'conflictFlag' => $conflictFlag,
            'users' => $users,
            'teachers' => $teachers,
            'students' => $students,
            'courses' => $courses,
            'rooms' => $rooms,
            'weekdays' => $weekdays,
            'timeslots' => $timeslots,
            'is_management_active' => $is_management_active,
            'current_sub_page' => $current_sub_page,
            'schedule_data' => $schedule_data,
            'pending_requests' => $pending_requests,
            'schedules' => $schedules,
            'enrollment_stats' => $enrollment_stats,
            'room_utilization' => $room_utilization,
            'total_slots_per_room' => $total_slots_per_room,
            'baseUri' => $baseUri,
            'title' => 'Admin Dashboard',
            'bodyClass' => '',
            'msg' => $msg,
            'ok' => $ok
        ]);
    }

    public function handleTasks(): void {
        $this->requireRole(['admin']);
        $this->validateCsrf();

        $conn = Database::get();

        $action      = $_POST['action'] ?? '';
        $sourcePage  = preg_replace('/[^a-z_]/i','', $_POST['source_page'] ?? '');
        $redirectPage = $sourcePage !== '' ? $sourcePage : 'home';

        $success = true;
        $msg = '';

        switch ($action) {
            case 'add_user':
                $username = trim($_POST['username'] ?? '');
                $email    = trim($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';
                $role     = $_POST['role'] ?? '';

                if ($username === '' || $email === '' || $password === '' || !in_array($role, ['student', 'teacher'], true)) {
                    $success = false;
                    $msg = 'Invalid user data';
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

                if ($userId <= 0 || $username === '' || $email === '' || !in_array($role, ['student', 'teacher'], true)) {
                    $success = false;
                    $msg = 'Invalid edit data';
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
                if ($userId <= 0) {
                    $success = false;
                    $msg = 'Invalid user id';
                    break;
                }
                $stmt = $conn->prepare("DELETE FROM users WHERE id=? AND role!='admin' LIMIT 1");
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $msg = $stmt->affected_rows ? 'User deleted' : 'No deletion';
                break;

            case 'add_course':
                $name  = trim($_POST['name'] ?? '');
                $desc  = trim($_POST['description'] ?? '');
                $credits = (int)($_POST['credits'] ?? 0);
                if ($name === '') {
                    $success = false;
                    $msg = 'Course name required';
                    break;
                }
                $stmt = $conn->prepare("INSERT INTO courses (name, description, credits) VALUES (?,?,?)");
                $stmt->bind_param('ssi', $name, $desc, $credits);
                try {
                    $stmt->execute();
                    $msg = 'Course created';
                } catch (mysqli_sql_exception $e) {
                    $success = false;
                    $msg = 'Create failed';
                }
                break;

            case 'assign_teacher':
                $courseId  = (int)($_POST['course_id'] ?? 0);
                $teacherId = (int)($_POST['teacher_id'] ?? 0);
                if ($courseId <= 0 || $teacherId <= 0) {
                    $success = false;
                    $msg = 'Invalid IDs';
                    break;
                }

                $chk = $conn->prepare("SELECT id FROM users WHERE id=? AND role='teacher' LIMIT 1");
                $chk->bind_param('i', $teacherId);
                $chk->execute();
                if (!$chk->get_result()->fetch_row()) {
                    $success = false;
                    $msg = 'Not a teacher';
                    break;
                }

                $stmt = $conn->prepare("UPDATE courses SET teacher_id=? WHERE id=?");
                $stmt->bind_param('ii', $teacherId, $courseId);
                $stmt->execute();
                $msg = 'Teacher assigned';
                break;

            case 'add_schedule':
                $courseId = (int)($_POST['course_id'] ?? 0);
                $roomId   = (int)($_POST['room_id'] ?? 0);
                $day      = $_POST['day_of_week'] ?? '';
                $slot     = $_POST['timeslot'] ?? '';

                require_once __DIR__ . '/../Domain/DayOfWeek.php';
                require_once __DIR__ . '/../Domain/TimeSlot.php';

                if ($courseId <= 0 || $roomId <= 0 || !DayOfWeek::isValid($day) || !TimeSlot::isValid($slot)) {
                    $success = false;
                    $msg = 'Invalid schedule data';
                    break;
                }

                $q1 = $conn->prepare("SELECT id FROM schedules WHERE room_id=? AND day_of_week=? AND timeslot=? LIMIT 1");
                $q1->bind_param('iss', $roomId, $day, $slot);
                $q1->execute();
                if ($q1->get_result()->fetch_row()) {
                    $this->redirect('admin_dashboard.php?page=' . urlencode($redirectPage) . '&conflict=1');
                }

                $q2 = $conn->prepare("SELECT id FROM schedules WHERE course_id=? AND day_of_week=? AND timeslot=? LIMIT 1");
                $q2->bind_param('iss', $courseId, $day, $slot);
                $q2->execute();
                if ($q2->get_result()->fetch_row()) {
                    $success = false;
                    $msg = 'Course already in that slot';
                    break;
                }

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
                    $success = false;
                    $msg = 'Teacher conflict';
                    break;
                }

                $ins = $conn->prepare("INSERT INTO schedules (course_id, room_id, day_of_week, timeslot) VALUES (?,?,?,?)");
                $ins->bind_param('iiss', $courseId, $roomId, $day, $slot);
                $ins->execute();
                $msg = 'Schedule added';
                break;

            case 'approve_enrollment':
                $reqId = (int)($_POST['request_id'] ?? 0);
                if ($reqId <= 0) {
                    $success = false;
                    $msg = 'Invalid request id';
                    break;
                }

                $p = $conn->prepare("SELECT student_id, course_id FROM pending_enrollments WHERE id=? AND status='pending' LIMIT 1");
                $p->bind_param('i', $reqId);
                $p->execute();
                $row = $p->get_result()->fetch_assoc();
                if (!$row) {
                    $success = false;
                    $msg = 'Request not found';
                    break;
                }

                $studentId = (int)$row['student_id'];
                $courseId  = (int)$row['course_id'];

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
                if ($reqId <= 0) {
                    $success = false;
                    $msg = 'Invalid request id';
                    break;
                }
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

        $statusParam = $success ? 'ok=1' : 'error=1';
        $this->redirect('admin_dashboard.php?page=' . urlencode($redirectPage) . '&' . $statusParam . '&msg=' . urlencode($msg));
    }
}
