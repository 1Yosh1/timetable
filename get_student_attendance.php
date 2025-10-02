<?php
require_once 'db_config.php';
header('Content-Type: application/json');

$response = ['success' => false, 'data' => []];

if (isset($_GET['student_id'], $_GET['course_id'])) {
    $student_id = $_GET['student_id'];
    $course_id = $_GET['course_id'];

    $stmt = $conn->prepare("
        SELECT status, COUNT(id) as count FROM attendance
        WHERE student_id = ? 
        AND schedule_id IN (SELECT id FROM schedules WHERE course_id = ?)
        GROUP BY status
    ");
    $stmt->bind_param("ii", $student_id, $course_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        $response['success'] = true;
        $response['data'] = $result->fetch_all(MYSQLI_ASSOC);
    }
}

echo json_encode($response);
?>