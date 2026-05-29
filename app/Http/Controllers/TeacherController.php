<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\View;
use App\Domain\DayOfWeek;
use App\Domain\TimeSlot;
use mysqli_sql_exception;

class TeacherController extends Controller {
    public function index(): void {
        $this->requireRole(['teacher']);

        $conn = Database::get();
        require_once __DIR__ . '/../../csrf.php';
        $token = csrf_token();

        $teacher_id = $_SESSION['user_id'];
        $teacher_username = $_SESSION['username'];

        $stmt = $conn->prepare("SELECT id, name FROM courses WHERE teacher_id = ?");
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $course_ids = array_column($courses, 'id');

        $students_by_course = [];
        $schedules_by_course = [];
        $all_attendance = [];
        $all_announcements = [];

        $weekdays = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"];
        $timeslots = ["09:00-10:00", "10:00-11:00", "11:00-12:00", "12:00-13:00", "13:00-14:00", "14:00-15:00", "15:00-16:00", "16:00-17:00"];

        if (!empty($course_ids)) {
            $placeholders = implode(',', array_fill(0, count($course_ids), '?'));
            $types = str_repeat('i', count($course_ids));

            $students_sql = "SELECT e.course_id, u.id, u.username, u.email FROM enrollments e JOIN users u ON e.student_id = u.id WHERE e.course_id IN ($placeholders)";
            $stmt_students = $conn->prepare($students_sql);
            $stmt_students->bind_param($types, ...$course_ids);
            $stmt_students->execute();
            $all_students = $stmt_students->get_result()->fetch_all(MYSQLI_ASSOC);

            $schedules_sql = "SELECT s.course_id, s.id, s.day_of_week, s.timeslot, r.name as room_name FROM schedules s JOIN rooms r ON s.room_id = r.id WHERE s.course_id IN ($placeholders)";
            $stmt_schedules = $conn->prepare($schedules_sql);
            $stmt_schedules->bind_param($types, ...$course_ids);
            $stmt_schedules->execute();
            $all_schedules = $stmt_schedules->get_result()->fetch_all(MYSQLI_ASSOC);

            foreach ($all_students as $student) {
                $students_by_course[$student['course_id']][] = $student;
            }
            foreach ($all_schedules as $schedule) {
                $schedules_by_course[$schedule['course_id']][] = $schedule;
            }

            foreach ($courses as &$course) {
                $course['students'] = $students_by_course[$course['id']] ?? [];
                $course['schedules'] = $schedules_by_course[$course['id']] ?? [];
            }
            unset($course);

            $attendance_sql = "
                SELECT a.student_id, a.status, a.date, s.course_id, u.username as student_name
                FROM attendance a
                JOIN schedules s ON a.schedule_id = s.id
                JOIN users u ON a.student_id = u.id
                WHERE s.course_id IN ($placeholders)
                ORDER BY s.course_id, u.username, a.date DESC
            ";
            $stmt_attendance = $conn->prepare($attendance_sql);
            $stmt_attendance->bind_param($types, ...$course_ids);
            $stmt_attendance->execute();
            $attendance_results = $stmt_attendance->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach ($attendance_results as $record) {
                $all_attendance[$record['course_id']][] = $record;
            }

            $announcements_sql = "
                SELECT id, course_id, content, created_at
                FROM announcements
                WHERE course_id IN ($placeholders)
                ORDER BY created_at DESC
            ";
            $stmt_announcements = $conn->prepare($announcements_sql);
            $stmt_announcements->bind_param($types, ...$course_ids);
            $stmt_announcements->execute();
            $announcements_results = $stmt_announcements->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach ($announcements_results as $announcement) {
                $all_announcements[$announcement['course_id']][] = $announcement;
            }
        }

        $rooms = $conn->query("SELECT id, name FROM rooms ORDER BY name")->fetch_all(MYSQLI_ASSOC);
        $booked_slots = [];
        $booked_sql = "SELECT room_id, day_of_week, timeslot, c.name as course_name 
                       FROM schedules s
                       LEFT JOIN courses c ON s.course_id = c.id";
        $booked_result = $conn->query($booked_sql);
        while ($row = $booked_result->fetch_assoc()) {
            $booked_slots[$row['room_id']][$row['day_of_week']][$row['timeslot']] = $row['course_name'] ?? 'Booked';
        }

        $stmt_pending = $conn->prepare("
            SELECT ps.id, c.name as course_name, r.name as room_name, ps.day_of_week, ps.timeslot, ps.status, ps.request_date
            FROM pending_schedules ps
            JOIN courses c ON ps.course_id = c.id
            JOIN rooms r ON ps.room_id = r.id
            WHERE ps.teacher_id = ?
            ORDER BY ps.request_date DESC
        ");
        $stmt_pending->bind_param("i", $teacher_id);
        $stmt_pending->execute();
        $my_pending_bookings = $stmt_pending->get_result()->fetch_all(MYSQLI_ASSOC);

        $baseScript = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseUri = rtrim(dirname($baseScript), '/\\');

        $msg = $_GET['msg'] ?? '';
        $ok = isset($_GET['ok']) && $_GET['ok'] == '1';

        View::render('teacher/dashboard', [
            'token' => $token,
            'courses' => $courses,
            'all_attendance' => $all_attendance,
            'all_announcements' => $all_announcements,
            'rooms' => $rooms,
            'booked_slots' => $booked_slots,
            'my_pending_bookings' => $my_pending_bookings,
            'weekdays' => $weekdays,
            'timeslots' => $timeslots,
            'teacher_username' => $teacher_username,
            'baseUri' => $baseUri,
            'title' => 'Teacher Dashboard',
            'bodyClass' => '',
            'msg' => $msg,
            'ok' => $ok
        ]);
    }

    public function handleTasks(): void {
        $this->requireRole(['teacher']);
        
        $conn = Database::get();
        $action = $_POST['action'] ?? '';
        $teacher_id = (int)$_SESSION['user_id'];

        if ($action === 'add_announcement') {
            $this->validateCsrf();
            $course_id = (int)($_POST['course_id'] ?? 0);
            $content = trim($_POST['content'] ?? '');

            if (empty($course_id) || empty($content)) {
                $this->redirect("teacher_dashboard.php?msg=" . urlencode("Content cannot be empty.") . "#announcements");
            }

            $stmt = $conn->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ?");
            $stmt->bind_param("ii", $course_id, $teacher_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                http_response_code(403);
                exit("Authorization failed: You are not assigned to this course.");
            }

            $stmt = $conn->prepare("INSERT INTO announcements (course_id, teacher_id, content) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $course_id, $teacher_id, $content);
            if ($stmt->execute()) {
                $this->redirect("teacher_dashboard.php?ok=1&msg=" . urlencode("Announcement posted."));
            } else {
                $this->redirect("teacher_dashboard.php?msg=" . urlencode("Failed to post announcement."));
            }
        }

        if ($action === 'delete_announcement') {
            $this->validateCsrf();
            $announcement_id = (int)($_POST['announcement_id'] ?? 0);

            if ($announcement_id <= 0) {
                $this->json(['success' => false, 'message' => 'Invalid announcement ID.']);
            }

            $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ? AND teacher_id = ?");
            $stmt->bind_param("ii", $announcement_id, $teacher_id);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $this->json(['success' => true, 'message' => 'Announcement deleted.']);
            } else {
                $this->json(['success' => false, 'message' => 'Could not delete announcement. Permission denied.']);
            }
        }

        if ($action === 'book_room') {
            $this->validateCsrf();
            $course_id = (int)($_POST['course_id'] ?? 0);
            $room_id = (int)($_POST['room_id'] ?? 0);
            $day = $_POST['day'] ?? '';
            $timeslot = $_POST['timeslot'] ?? '';

            if ($course_id <= 0 || $room_id <= 0 || empty($day) || empty($timeslot)) {
                $this->json(['success' => false, 'message' => 'Invalid booking data provided.']);
            }

            $stmt = $conn->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ?");
            $stmt->bind_param("ii", $course_id, $teacher_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                $this->json(['success' => false, 'message' => 'Authorization failed: You do not own this course.']);
            }

            $stmt = $conn->prepare("SELECT id FROM schedules WHERE room_id = ? AND day_of_week = ? AND timeslot = ?");
            $stmt->bind_param("iss", $room_id, $day, $timeslot);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $this->json(['success' => false, 'message' => 'Sorry, this slot was just booked by someone else.']);
            }
            
            $stmt = $conn->prepare("SELECT 1 FROM schedules s JOIN courses c ON s.course_id = c.id WHERE c.teacher_id = ? AND s.day_of_week = ? AND s.timeslot = ?");
            $stmt->bind_param("iss", $teacher_id, $day, $timeslot);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $this->json(['success' => false, 'message' => 'You already have another class scheduled at this time.']);
            }

            $stmt = $conn->prepare("INSERT INTO pending_schedules (course_id, room_id, day_of_week, timeslot, teacher_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iissi", $course_id, $room_id, $day, $timeslot, $teacher_id);

            if ($stmt->execute()) {
                $this->json(['success' => true, 'message' => 'Room booking request submitted for admin approval!']);
            } else {
                $this->json(['success' => false, 'message' => 'A database error occurred while submitting the booking request.']);
            }
        }

        $this->redirect("teacher_dashboard.php");
    }

    public function markAttendance(): void {
        $this->requireRole(['teacher']);
        
        $conn = Database::get();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $schedule_id = $_POST['schedule_id'];
            $attendance_date = $_POST['attendance_date'];
            $attendance_data = isset($_POST['attendance']) ? $_POST['attendance'] : [];

            if (empty($schedule_id) || empty($attendance_date) || empty($attendance_data)) {
                $this->redirect('teacher_dashboard.php?msg=' . urlencode('Missing data. Please fill out the form completely.'));
            }

            $stmt_course = $conn->prepare("SELECT course_id FROM schedules WHERE id = ?");
            $stmt_course->bind_param("i", $schedule_id);
            $stmt_course->execute();
            $course_id_result = $stmt_course->get_result()->fetch_assoc();
            if (!$course_id_result) {
                $this->redirect('teacher_dashboard.php?msg=' . urlencode('Invalid schedule selected.'));
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
                $this->redirect('teacher_dashboard.php?ok=1&msg=' . urlencode('Attendance marked successfully!'));

            } catch (mysqli_sql_exception $exception) {
                $conn->rollback();
                $this->redirect('teacher_dashboard.php?error=1&msg=' . urlencode('Failed to mark attendance. A database error occurred.'));
            }
        }
        $this->redirect('teacher_dashboard.php');
    }

    public function checkConflict(): void {
        $this->requireRole(['teacher', 'admin']);
        $conn = Database::get();
        $response = ['conflict' => false];

        if (isset($_GET['room_id'], $_GET['day'], $_GET['timeslot'])) {
            $room_id = $_GET['room_id'];
            $day = $_GET['day'];
            $timeslot = $_GET['timeslot'];

            $stmt = $conn->prepare("SELECT c.name FROM schedules s JOIN courses c ON s.course_id = c.id WHERE s.room_id = ? AND s.day_of_week = ? AND s.timeslot = ?");
            $stmt->bind_param("iss", $room_id, $day, $timeslot);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $response['conflict'] = true;
                $response['course'] = $result->fetch_assoc()['name'];
            }
        }
        $this->json($response);
    }
}
