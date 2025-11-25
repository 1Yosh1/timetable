<?php
require_once __DIR__ . '/app/bootstrap.php';
use App\Domain\DayOfWeek;
use App\Domain\TimeSlot;
$weekdays  = DayOfWeek::all();
$timeslots = TimeSlot::all();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: index.php");
    exit();
}
require_once 'db_config.php';
$teacher_id = $_SESSION['user_id'];
$teacher_username = $_SESSION['username'];

// --- DATA FETCHING ---
$courses = [];
$courses_sql = "SELECT id, name FROM courses WHERE teacher_id = ?";
$stmt_courses = $conn->prepare($courses_sql);
$stmt_courses->bind_param("i", $teacher_id);
$stmt_courses->execute();
$courses_result = $stmt_courses->get_result();
while ($course = $courses_result->fetch_assoc()) {
    $students_sql = "SELECT u.id, u.username, u.email FROM enrollments e JOIN users u ON e.student_id = u.id WHERE e.course_id = ?";
    $stmt_students = $conn->prepare($students_sql);
    $stmt_students->bind_param("i", $course['id']);
    $stmt_students->execute();
    $course['students'] = $stmt_students->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $schedules_sql = "SELECT id, day_of_week, timeslot, (SELECT name FROM rooms WHERE id=schedules.room_id) as room_name FROM schedules WHERE course_id = ?";
    $stmt_schedules = $conn->prepare($schedules_sql);
    $stmt_schedules->bind_param("i", $course['id']);
    $stmt_schedules->execute();
    $course['schedules'] = $stmt_schedules->get_result()->fetch_all(MYSQLI_ASSOC);
    $courses[] = $course;
}

$rooms = $conn->query("SELECT id, name FROM rooms ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$booked_slots = [];
$booked_sql = "SELECT room_id, day_of_week, timeslot, c.name as course_name 
               FROM schedules s
               LEFT JOIN courses c ON s.course_id = c.id";
$booked_result = $conn->query($booked_sql);
while($row = $booked_result->fetch_assoc()) {
    $booked_slots[$row['room_id']][$row['day_of_week']][$row['timeslot']] = $row['course_name'] ?? 'Booked';
}
$weekdays = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"];
$timeslots = ["09:00-10:00", "10:00-11:00", "11:00-12:00", "12:00-13:00", "13:00-14:00", "14:00-15:00", "15:00-16:00", "16:00-17:00"];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Teacher Dashboard</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="container my-4">
        <div class="mb-4">
            <h1>Teacher Dashboard</h1>
            <p class="text-secondary">Welcome, <?php echo htmlspecialchars($teacher_username); ?>!</p>
        </div>

        <div class="d-flex justify-content-between align-items-end">
            <ul class="nav nav-tabs" id="teacherTab" role="tablist" style="border-bottom: none;">
                <li class="nav-item"><a class="nav-link active" id="courses-tab" data-toggle="tab" href="#courses" role="tab">My Courses</a></li>
                <li class="nav-item"><a class="nav-link" id="take-attendance-tab" data-toggle="tab" href="#take-attendance" role="tab">Take Attendance</a></li>
                <li class="nav-item"><a class="nav-link" id="rooms-tab" data-toggle="tab" href="#rooms" role="tab">Book a Room</a></li>
            </ul>
            <a href="logout.php" class="btn btn-danger mb-1">Logout</a>
        </div>

        <div class="tab-content bg-white p-4 border-top" id="teacherTabContent">
            <div class="tab-pane fade show active" id="courses" role="tabpanel">
                <h3 class="mb-3">Your Assigned Courses</h3>
                <?php if (empty($courses)): ?><p class="text-secondary">You are not assigned to any courses.</p>
                <?php else: foreach ($courses as $course): ?>
                    <div class="card mb-3"><div class="card-header"><h4><?php echo htmlspecialchars($course['name']); ?></h4></div>
                        <div class="card-body">
                            <h5>Enrolled Students (<?php echo count($course['students']); ?>)</h5>
                            <?php if (empty($course['students'])): ?><p class="text-secondary">No students are currently enrolled.</p>
                            <?php else: ?>
                            <div class="table-responsive"><table class="table table-sm table-hover">
                                <thead><tr><th>Name</th><th>Email</th></tr></thead>
                                <tbody>
                                <?php foreach ($course['students'] as $student): ?>
                                    <tr><td><?php echo htmlspecialchars($student['username']); ?></td><td><?php echo htmlspecialchars($student['email']); ?></td></tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>

            <div class="tab-pane fade" id="take-attendance" role="tabpanel">
                 <h3 class="mb-3">Take Attendance</h3>
                <?php if (empty($courses)): ?><p class="text-secondary">You have no courses to take attendance for.</p>
                <?php else: foreach ($courses as $course): ?>
                    <div class="card mb-4"><div class="card-header"><h5><?php echo htmlspecialchars($course['name']); ?></h5></div>
                        <div class="card-body">
                            <?php if (empty($course['schedules'])): ?><p class="text-secondary">No schedule found for this course to take attendance.</p>
                            <?php else: ?>
                                <form action="mark_attendance.php" method="POST">
                                    <div class="form-row align-items-end">
                                        <div class="form-group col-md-5"><label>Select Class Session:</label><select name="schedule_id" class="form-control" required><option value="">-- Select a time --</option><?php foreach($course['schedules'] as $schedule): ?><option value="<?php echo $schedule['id']; ?>"><?php echo htmlspecialchars($schedule['day_of_week'] . " at " . $schedule['timeslot'] . " (" . $schedule['room_name'] . ")"); ?></option><?php endforeach; ?></select></div>
                                        <div class="form-group col-md-4"><label>Date:</label><input type="date" name="attendance_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div>
                                        <div class="form-group col-md-3"><button type="submit" class="btn btn-primary btn-block">Submit Attendance</button></div>
                                    </div>
                                    <?php if (!empty($course['students'])): ?>
                                        <div class="table-responsive"><table class="table table-sm table-hover mt-3">
                                            <thead><tr><th>Student Name</th><th class="text-center">Status</th></tr></thead>
                                            <tbody>
                                            <?php foreach ($course['students'] as $student): ?>
                                                <tr><td><?php echo htmlspecialchars($student['username']); ?></td>
                                                    <td class="text-center"><div class="btn-group btn-group-toggle" data-toggle="buttons"><label class="btn btn-outline-success btn-sm"><input type="radio" name="attendance[<?php echo $student['id']; ?>]" value="present" required> Present</label><label class="btn btn-outline-danger btn-sm"><input type="radio" name="attendance[<?php echo $student['id']; ?>]" value="absent"> Absent</label></div></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table></div>
                                    <?php else: ?>
                                         <p class="text-secondary mt-3">No students enrolled to take attendance for.</p>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>

            <div class="tab-pane fade" id="rooms" role="tabpanel">
                <h3 class="mb-3">Room Availability & Booking</h3>
                <div class="form-group"><input type="text" id="roomSearchInput" class="form-control" placeholder="Search for a room name..."></div>
                 <div class="table-responsive">
                    <table class="table table-bordered timetable-grid text-center" id="roomBookingTable">
                        <thead class="thead-light"><tr><th>Room</th><?php foreach ($weekdays as $day) echo "<th>$day</th>"; ?></tr></thead>
                        <tbody>
                        <?php foreach ($rooms as $room): ?>
                            <tr><th class="align-middle room-name"><?php echo htmlspecialchars($room['name']); ?></th>
                                <?php foreach ($weekdays as $day): ?>
                                    <td>
                                    <?php foreach ($timeslots as $slot): ?>
                                        <?php $is_booked = isset($booked_slots[$room['id']][$day][$slot]); ?>
                                        <div class="p-1 my-1 border rounded <?php echo $is_booked ? 'availability-booked' : 'availability-free'; ?>">
                                            <small><?php echo $slot; ?></small>
                                            <?php if ($is_booked): ?>
                                                <div class="font-weight-bold" style="font-size: 0.8em;"><?php echo htmlspecialchars($booked_slots[$room['id']][$day][$slot]); ?></div>
                                            <?php else: ?>
                                                <form action="book_room_process.php" method="POST" class="d-inline"><input type="hidden" name="room_id" value="<?php echo $room['id']; ?>"><input type="hidden" name="day" value="<?php echo $day; ?>"><input type="hidden" name="timeslot" value="<?php echo $slot; ?>"><button type="submit" class="btn btn-success btn-sm p-0 px-1" style="font-size: 0.7em;">Book</button></form>
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
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>