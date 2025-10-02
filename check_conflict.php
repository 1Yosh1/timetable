<?php
require_once 'db_config.php';
header('Content-Type: application/json');

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
?>