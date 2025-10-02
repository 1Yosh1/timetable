<?php
session_start();
if (!isset($_SESSION['admin_id']) && (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin')) {
    header("Location: admin_login.php");
    exit();
}
require_once 'db_config.php';

// Fetch all rooms
$rooms = $conn->query("SELECT id, name FROM rooms ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Fetch all schedule data
$schedule_data = [];
$schedule_sql = "SELECT s.room_id, s.day_of_week, s.timeslot, c.name AS course_name, u.username as teacher_name FROM schedules s JOIN courses c ON s.course_id = c.id LEFT JOIN users u ON c.teacher_id = u.id";
$schedule_result = $conn->query($schedule_sql);
while($row = $schedule_result->fetch_assoc()) {
    $schedule_data[$row['room_id']][$row['day_of_week']][$row['timeslot']] = $row;
}

$weekdays = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"];
$timeslots = ["09:00-10:00", "10:00-11:00", "11:00-12:00", "12:00-13:00", "13:00-14:00", "14:00-15:00", "15:00-16:00", "16:00-17:00"];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Room Bookings Dashboard</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content">
        <h1 class="mb-4">Room Bookings Overview</h1>
        <div class="card">
            <div class="card-body table-responsive">
                <table class="table table-bordered text-center">
                    <thead class="thead-light"><tr><th>Room</th><?php foreach ($weekdays as $day) echo "<th>$day</th>"; ?></tr></thead>
                    <tbody>
                    <?php foreach ($rooms as $room): ?>
                        <tr>
                            <th class="align-middle"><?php echo htmlspecialchars($room['name']); ?></th>
                            <?php foreach ($weekdays as $day): ?>
                                <td>
                                    <?php foreach ($timeslots as $slot): ?>
                                        <?php $is_booked = isset($schedule_data[$room['id']][$day][$slot]); ?>
                                        <div class="p-1 my-1 border rounded <?php echo $is_booked ? 'availability-booked' : 'availability-free'; ?>">
                                            <small><?php echo $slot; ?></small>
                                            <?php if ($is_booked): 
                                                $s = $schedule_data[$room['id']][$day][$slot];?>
                                                <div class="font-weight-bold" style="font-size: 0.8em;"><?php echo htmlspecialchars($s['course_name']); ?><br><small>(<?php echo htmlspecialchars($s['teacher_name'] ?? 'N/A'); ?>)</small></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>