<?php
namespace App\Services;

use App\Repositories\EnrollmentRepository;
use App\Repositories\ScheduleRepository;
use App\Core\Database;

class EnrollmentService {
    private EnrollmentRepository $enrollRepo;
    private ScheduleRepository $scheduleRepo;
    private int $maxCourses;

    public function __construct(EnrollmentRepository $er, ScheduleRepository $sr, int $maxCourses = 6) {
        $this->enrollRepo = $er;
        $this->scheduleRepo = $sr;
        $this->maxCourses = $maxCourses;
    }

    public function enroll(int $studentId, int $courseId): array {
        if ($this->scheduleRepo->conflictsForStudentCourse($studentId, $courseId)) {
            return ['success'=>false,'message'=>'Schedule conflict detected'];
        }
        if ($this->enrollRepo->countByStudent(Database::get(), $studentId) >= $this->maxCourses) {
            return ['success'=>false,'message'=>'Enrollment limit reached'];
        }
        if (!$this->enrollRepo->create(Database::get(), $studentId, $courseId)) {
            return ['success'=>false,'message'=>'Already enrolled or invalid course'];
        }
        return ['success'=>true,'message'=>'Enrolled'];
    }

    public function unenroll(int $studentId, int $courseId): array {
        return $this->enrollRepo->delete(Database::get(), $studentId, $courseId)
            ? ['success'=>true,'message'=>'Unenrolled']
            : ['success'=>false,'message'=>'Not enrolled'];
    }

    public function requestApproval(int $studentId, int $courseId): array {
        if ($this->enrollRepo->isEnrolled(Database::get(), $studentId, $courseId)) {
            return ['success'=>false,'message'=>'Already enrolled'];
        }
        if ($this->scheduleRepo->conflictsForStudentCourse($studentId, $courseId)) {
            return ['success'=>false,'message'=>'Conflict'];
        }
        if ($this->enrollRepo->createPendingIfAbsent(Database::get(), $studentId, $courseId)) {
            return ['success'=>true,'message'=>'Request submitted'];
        }
        return ['success'=>false,'message'=>'Request failed'];
    }
}