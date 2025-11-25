<?php
namespace App\Http\Controllers;
use App\Services\EnrollmentService;

class EnrollmentController {
    private EnrollmentService $service;
    public function __construct(EnrollmentService $svc) { $this->service = $svc; }

    public function handle(array $post, int $studentId): array {
        $action = $post['action'] ?? '';
        $courseId = (int)($post['course_id'] ?? 0);
        if ($courseId <= 0) return ['success'=>false,'message'=>'Invalid course'];
        if ($action === 'enroll') return $this->service->enroll($studentId,$courseId);
        if ($action === 'unenroll') return $this->service->unenroll($studentId,$courseId);
        if ($action === 'request_approval') return $this->service->requestApproval($studentId, $courseId);
        return ['success'=>false,'message'=>'Unknown action'];
    }
}
