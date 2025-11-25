<?php
namespace App\Repositories;
use App\Core\Database;
use mysqli;
use App\Cache\ArrayCache;

class CourseRepository {
    public function allWithTeacher(): array {
        $sql = "SELECT c.id, c.name, c.description, u.username AS teacher
                FROM courses c
                LEFT JOIN users u ON c.teacher_id = u.id
                ORDER BY c.name";
        $res = Database::get()->query($sql);
        return $res->fetch_all(MYSQLI_ASSOC);
    }

    public function getAvailableWithSchedules(mysqli $db, int $studentId): array {
        $sql = "SELECT c.id, c.name, c.description, s.day_of_week, s.timeslot
                FROM courses c
                LEFT JOIN schedules s ON s.course_id = c.id
                WHERE c.id NOT IN (SELECT course_id FROM enrollments WHERE student_id = ?)
                ORDER BY c.name";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $courses = [];
        foreach ($rows as $r) {
            $cid = $r['id'];
            if (!isset($courses[$cid])) {
                $courses[$cid] = [
                    'id' => $cid,
                    'name' => $r['name'],
                    'description' => $r['description'],
                    'schedules' => []
                ];
            }
            if ($r['day_of_week'] && $r['timeslot']) {
                $courses[$cid]['schedules'][] = [
                    'day_of_week' => $r['day_of_week'],
                    'timeslot' => $r['timeslot']
                ];
            }
        }
        return array_values($courses);
    }

    public function getCourseSchedules(mysqli $db, int $courseId): array {
        $stmt = $db->prepare("SELECT day_of_week, timeslot FROM schedules WHERE course_id=?");
        $stmt->bind_param("i", $courseId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function allCached(mysqli $db): array {
        return ArrayCache::remember('courses_all', 30, fn()=> $db->query("SELECT id,name,teacher_id FROM courses")->fetch_all(MYSQLI_ASSOC));
    }
}
