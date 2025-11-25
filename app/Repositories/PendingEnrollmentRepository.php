<?php
namespace App\Repositories;
use mysqli;

class PendingEnrollmentRepository {
    public function createIfNotExists(mysqli $db, int $studentId, int $courseId): bool {
        $stmt = $db->prepare("INSERT INTO pending_enrollments (student_id, course_id, request_date, status)
                              SELECT ?, ?, NOW(), 'pending'
                              WHERE NOT EXISTS (
                                  SELECT 1 FROM pending_enrollments WHERE student_id=? AND course_id=? AND status='pending'
                              )");
        $stmt->bind_param("iiii", $studentId, $courseId, $studentId, $courseId);
        return $stmt->execute();
    }
}
