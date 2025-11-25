<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once 'app/csrf.php';

use App\Repositories\EnrollmentRepository;
use App\Repositories\ScheduleRepository;
use App\Services\EnrollmentService;
use App\Http\Controllers\EnrollmentController;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Invalid method']); exit;
}
if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success'=>false,'message'=>'CSRF validation failed.']); exit;
}
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit;
}

$controller = new EnrollmentController(
    new EnrollmentService(
        new EnrollmentRepository(),
        new ScheduleRepository(),
        5 // aligned with UI
    )
);
$response = $controller->handle($_POST, (int)$_SESSION['user_id']);
echo json_encode($response);
?>