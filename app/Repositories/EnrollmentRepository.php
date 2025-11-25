<?php
namespace App\Repositories;
use mysqli;

class EnrollmentRepository {

    public function countByStudent(mysqli $db, int $studentId): int {
        $stmt = $db->prepare("SELECT COUNT(id) c FROM enrollments WHERE student_id=?");
        $stmt->bind_param("i",$studentId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        return (int)($res['c'] ?? 0);
    }

    public function isEnrolled(mysqli $db, int $studentId, int $courseId): bool {
        $stmt = $db->prepare("SELECT 1 FROM enrollments WHERE student_id=? AND course_id=? LIMIT 1");
        $stmt->bind_param("ii",$studentId,$courseId);
        $stmt->execute();
        return (bool)$stmt->get_result()->fetch_row();
    }

    public function create(mysqli $db, int $studentId, int $courseId): bool {
        $stmt = $db->prepare("INSERT INTO enrollments (student_id, course_id) VALUES (?,?)");
        $stmt->bind_param("ii",$studentId,$courseId);
        return $stmt->execute();
    }

    public function delete(mysqli $db, int $studentId, int $courseId): bool {
        $stmt = $db->prepare("DELETE FROM enrollments WHERE student_id=? AND course_id=?");
        $stmt->bind_param("ii",$studentId,$courseId);
        return $stmt->execute();
    }

    public function hasPending(mysqli $db, int $studentId, int $courseId): bool {
        $stmt = $db->prepare("SELECT 1 FROM pending_enrollments WHERE student_id=? AND course_id=? AND status='pending' LIMIT 1");
        $stmt->bind_param("ii",$studentId,$courseId);
        $stmt->execute();
        return (bool)$stmt->get_result()->fetch_row();
    }

    public function createPendingIfAbsent(mysqli $db, int $studentId, int $courseId): bool {
        $stmt = $db->prepare(
            "INSERT INTO pending_enrollments (student_id, course_id, request_date, status)
             SELECT ?, ?, NOW(), 'pending'
             WHERE NOT EXISTS (
                SELECT 1 FROM pending_enrollments WHERE student_id=? AND course_id=? AND status='pending'
             )"
        );
        $stmt->bind_param("iiii",$studentId,$courseId,$studentId,$courseId);
        return $stmt->execute();
    }

    public function getDetailedSchedule(mysqli $db, int $studentId): array {
        $sql = "SELECT c.id AS course_id, c.name AS course_name, s.day_of_week, s.timeslot, r.name AS room_name
                FROM enrollments e
                JOIN courses c ON e.course_id = c.id
                JOIN schedules s ON c.id = s.course_id
                JOIN rooms r ON s.room_id = r.id
                WHERE e.student_id = ?
                ORDER BY FIELD(s.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday'), s.timeslot";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("i",$studentId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getEnrolledCoursesBasic(mysqli $db, int $studentId): array {
        $sql = "SELECT c.id, c.name, u.username AS teacher_name
                FROM enrollments e
                JOIN courses c ON e.course_id = c.id
                LEFT JOIN users u ON c.teacher_id = u.id
                WHERE e.student_id = ?
                ORDER BY c.name";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("i",$studentId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Returns [courseCount, conflictMap]
    public function getCountAndMap(mysqli $db, int $studentId): array {
        $stmt = $db->prepare("SELECT s.day_of_week, s.timeslot
                              FROM enrollments e
                              JOIN schedules s ON e.course_id = s.course_id
                              WHERE e.student_id=?");
        $stmt->bind_param("i",$studentId);
        $stmt->execute();
        $res = $stmt->get_result();
        $map = [];
        while ($r = $res->fetch_assoc()) {
            $map[$r['day_of_week']][$r['timeslot']] = true;
        }
        $stmt2 = $db->prepare("SELECT COUNT(DISTINCT course_id) c FROM enrollments WHERE student_id=?");
        $stmt2->bind_param("i",$studentId);
        $stmt2->execute();
        $count = (int)($stmt2->get_result()->fetch_assoc()['c'] ?? 0);
        return [$count, $map];
    }
}
