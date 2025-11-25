<?php
namespace App\Repositories;
use App\Core\Database;

class ScheduleRepository {
    public function forStudent(int $studentId): array {
        $sql = "SELECT s.id, s.day_of_week, s.timeslot, c.name AS course, r.name AS room
                FROM enrollments e
                JOIN schedules s ON e.course_id = s.course_id
                JOIN courses c ON s.course_id = c.id
                JOIN rooms r ON s.room_id = r.id
                WHERE e.student_id = ?
                ORDER BY FIELD(s.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday'), s.timeslot";
        $stmt = Database::get()->prepare($sql);
        $stmt->bind_param("i",$studentId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function conflictsForStudentCourse(int $studentId, int $courseId): bool {
        $sql = "SELECT 1
                FROM schedules target
                WHERE target.course_id = ?
                  AND EXISTS (
                     SELECT 1 FROM enrollments e
                     JOIN schedules s2 ON e.course_id = s2.course_id
                     WHERE e.student_id = ?
                       AND s2.day_of_week = target.day_of_week
                       AND s2.timeslot = target.timeslot
                  ) LIMIT 1";
        $stmt = Database::get()->prepare($sql);
        $stmt->bind_param("ii",$courseId,$studentId);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }
}
