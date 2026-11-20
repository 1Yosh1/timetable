<?php
require_once __DIR__ . '/app/bootstrap.php'; //session, .env, db_config

// Called by the admin schedule form's live warning. Admin-only: room
// availability is not public information, and the endpoint used to answer
// to any anonymous request.
$is_admin = isset($_SESSION['admin_id'])
    || (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin');

header('Content-Type: application/json');
if (!$is_admin) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

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

echo json_encode($response);
