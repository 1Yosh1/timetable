<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\View;
use App\Config\AppConfig;
use App\Repositories\EnrollmentRepository;
use App\Repositories\CourseRepository;
use App\Repositories\ScheduleRepository;
use App\Services\EnrollmentService;
use App\Http\Controllers\EnrollmentController;

class StudentController extends Controller {
    public function index(): void {
        $this->requireRole(['student']);

        $conn = Database::get();
        require_once __DIR__ . '/../../csrf.php';
        $token = csrf_token();

        $user_id = (int)$_SESSION['user_id'];
        $username = $_SESSION['username'] ?? 'Student';

        $enrollRepo = new EnrollmentRepository();
        $courseRepo = new CourseRepository();

        $my_detailed_schedule = $enrollRepo->getDetailedSchedule($conn, $user_id);
        $my_schedule = [];
        foreach ($my_detailed_schedule as $row) {
            $my_schedule[$row['day_of_week']][$row['timeslot']] = true;
        }

        $enrollment_count = $enrollRepo->countByStudent($conn, $user_id);
        $enrollment_limit = AppConfig::maxCourses();
        $available_courses = $courseRepo->getAvailableWithSchedules($conn, $user_id);
        $enrolled_courses_list = $enrollRepo->getEnrolledCoursesBasic($conn, $user_id);

        $stmt_att = $conn->prepare(
            "SELECT c.name as course_name, a.date, a.status
             FROM attendance a
             JOIN schedules s ON a.schedule_id = s.id
             JOIN courses c ON s.course_id = c.id
             WHERE a.student_id = ?
             ORDER BY c.name, a.date DESC"
        );
        $stmt_att->bind_param("i", $user_id);
        $stmt_att->execute();
        $attendance_results = $stmt_att->get_result()->fetch_all(MYSQLI_ASSOC);
        $my_attendance_by_course = [];
        foreach ($attendance_results as $record) {
            $my_attendance_by_course[$record['course_name']][] = $record;
        }

        $my_announcements = [];
        $enrolled_course_ids = array_column($enrolled_courses_list, 'id');
        if (!empty($enrolled_course_ids)) {
            $placeholders = implode(',', array_fill(0, count($enrolled_course_ids), '?'));
            $types = str_repeat('i', count($enrolled_course_ids));
            $ann_sql = "SELECT an.content, an.created_at, c.name as course_name
                        FROM announcements an JOIN courses c ON an.course_id = c.id
                        WHERE an.course_id IN ($placeholders) ORDER BY an.created_at DESC";
            $stmt_ann = $conn->prepare($ann_sql);
            $stmt_ann->bind_param($types, ...$enrolled_course_ids);
            $stmt_ann->execute();
            $my_announcements = $stmt_ann->get_result()->fetch_all(MYSQLI_ASSOC);
        }

        $baseScript = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseUri = rtrim(dirname($baseScript), '/\\');

        $msg = $_GET['msg'] ?? '';
        $ok = isset($_GET['ok']) && $_GET['ok'] == '1';

        View::render('student/dashboard', [
            'token' => $token,
            'my_detailed_schedule' => $my_detailed_schedule,
            'my_schedule' => $my_schedule,
            'enrollment_count' => $enrollment_count,
            'enrollment_limit' => $enrollment_limit,
            'available_courses' => $available_courses,
            'enrolled_courses_list' => $enrolled_courses_list,
            'my_attendance_by_course' => $my_attendance_by_course,
            'my_announcements' => $my_announcements,
            'username' => $username,
            'baseUri' => $baseUri,
            'title' => 'Student Dashboard',
            'bodyClass' => '',
            'msg' => $msg,
            'ok' => $ok
        ]);
    }

    public function showProfile(): void {
        $this->requireRole(['student']);

        require_once __DIR__ . '/../../csrf.php';
        $token = csrf_token();
        $username = $_SESSION['username'] ?? 'Student';
        $email = $_SESSION['email'] ?? 'No email on file';

        $baseScript = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseUri = rtrim(dirname($baseScript), '/\\');

        $msg = $_GET['msg'] ?? '';
        $ok = isset($_GET['ok']) && $_GET['ok'] == '1';

        View::render('student/profile', [
            'token' => $token,
            'username' => $username,
            'email' => $email,
            'baseUri' => $baseUri,
            'title' => 'My Profile',
            'bodyClass' => '',
            'msg' => $msg,
            'ok' => $ok
        ]);
    }

    public function handleEnrollment(): void {
        $this->requireRole(['student']);
        $this->validateCsrf();

        $controller = new EnrollmentController(
            new EnrollmentService(
                new EnrollmentRepository(),
                new ScheduleRepository(),
                AppConfig::maxCourses()
            )
        );
        $response = $controller->handle($_POST, (int)$_SESSION['user_id']);
        $this->json($response);
    }

    public function changePassword(): void {
        $this->requireRole(['student']);
        $this->validateCsrf();

        $conn = Database::get();

        $user_id = (int)$_SESSION['user_id'];
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $this->redirect("profile.php?msg=" . urlencode("All password fields are required."));
        }

        if ($new_password !== $confirm_password) {
            $this->redirect("profile.php?msg=" . urlencode("New passwords do not match."));
        }

        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if (!$result || !password_verify($current_password, $result['password'])) {
            $this->redirect("profile.php?msg=" . urlencode("Incorrect current password."));
        }

        $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update_stmt->bind_param("si", $new_hashed_password, $user_id);

        if ($update_stmt->execute()) {
            $this->redirect("profile.php?ok=1&msg=" . urlencode("Password updated successfully."));
        } else {
            $this->redirect("profile.php?msg=" . urlencode("An error occurred. Could not update password."));
        }
    }
}
