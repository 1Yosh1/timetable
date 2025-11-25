<?php
namespace App\Repositories;
use mysqli;

class UserRepository {

    public function getById(mysqli $db, int $id): ?array {
        $stmt = $db->prepare("SELECT id, username, email, role FROM users WHERE id=? LIMIT 1");
        $stmt->bind_param("i",$id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    public function getByRole(mysqli $db, string $role): array {
        $stmt = $db->prepare("SELECT id, username, email, role FROM users WHERE role=? ORDER BY username");
        $stmt->bind_param("s",$role);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function listNonAdmin(mysqli $db): array {
        return $db->query("SELECT id, username, email, role FROM users WHERE role!='admin' ORDER BY role, username")
                  ->fetch_all(MYSQLI_ASSOC);
    }

    public function listTeachers(mysqli $db): array {
        return $this->getByRole($db,'teacher');
    }

    public function listStudents(mysqli $db): array {
        return $this->getByRole($db,'student');
    }

    public function assignTeacherToCourse(mysqli $db, int $courseId, int $teacherId): bool {
        $stmt = $db->prepare("UPDATE courses SET teacher_id=? WHERE id=?");
        $stmt->bind_param("ii",$teacherId,$courseId);
        return $stmt->execute();
    }
}
