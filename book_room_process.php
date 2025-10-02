<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    die("Access Denied.");
}

$teacher_id = $_SESSION['user_id'];
$courses = [];

// Handle the final booking confirmation after a course is selected
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['confirm_booking'])) {
    $room_id = $_GET['room_id'];
    $day = $_GET['day'];
    $timeslot = $_GET['timeslot'];
    $course_id = $_GET['course_id'];

    // Final check to prevent double booking
    $check_stmt = $conn->prepare("SELECT id FROM schedules WHERE room_id = ? AND day_of_week = ? AND timeslot = ?");
    $check_stmt->bind_param("iss", $room_id, $day, $timeslot);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        echo "<script>alert('Sorry, this slot was just booked by someone else. Please try again.'); window.location.href='teacher_dashboard.php';</script>";
        exit();
    }
    
    $insert_stmt = $conn->prepare("INSERT INTO schedules (course_id, room_id, day_of_week, timeslot) VALUES (?, ?, ?, ?)");
    $insert_stmt->bind_param("iiss", $course_id, $room_id, $day, $timeslot);

    if ($insert_stmt->execute()) {
        echo "<script>alert('Room booked successfully!'); window.location.href='teacher_dashboard.php';</script>";
    } else {
        echo "<script>alert('Failed to book room.'); window.location.href='teacher_dashboard.php';</script>";
    }
    exit();
} 
// Handle the initial POST from the dashboard and show the confirmation page
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['room_id'])) {
    $room_id = $_POST['room_id'];
    $day = $_POST['day'];
    $timeslot = $_POST['timeslot'];

    // Fetch ONLY the courses assigned to this specific teacher
    $stmt = $conn->prepare("SELECT id, name FROM courses WHERE teacher_id = ?");
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    // Redirect if accessed directly without proper data
    header("Location: teacher_dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Confirm Booking</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="height: 100vh;">
    <div class="container text-center">
        <div class="card mx-auto" style="max-width: 500px;">
            <div class="card-header"><h3>Confirm Your Booking</h3></div>
            <div class="card-body">
                <p>You are booking <strong>Room <?php echo htmlspecialchars($room_id); ?></strong> for <strong><?php echo htmlspecialchars($day); ?></strong> at <strong><?php echo htmlspecialchars($timeslot); ?></strong>.</p>
                
                <?php if(empty($courses)): ?>
                    <div class="alert alert-danger">You have no courses assigned to you. An admin must assign you a course before you can book a room.</div>
                    <a href="teacher_dashboard.php" class="btn btn-secondary btn-block mt-2">Go Back</a>
                <?php else: ?>
                <form action="book_room_process.php" method="GET">
                    <input type="hidden" name="confirm_booking" value="1">
                    <input type="hidden" name="room_id" value="<?php echo htmlspecialchars($room_id); ?>">
                    <input type="hidden" name="day" value="<?php echo htmlspecialchars($day); ?>">
                    <input type="hidden" name="timeslot" value="<?php echo htmlspecialchars($timeslot); ?>">
                    
                    <div class="form-group">
                        <label for="course_id"><strong>Assign this booking to which of your courses?</strong></label>
                        <select name="course_id" id="course_id" class="form-control" required>
                            <option value="">-- Select Your Course --</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Confirm & Book</button>
                    <a href="teacher_dashboard.php" class="btn btn-secondary btn-block mt-2">Cancel</a>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>