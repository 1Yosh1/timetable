<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/csrf.php';
require_once __DIR__ . '/app/Auth.php';
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

regenerateSession();

require_once 'db_config.php';
$teacher_id = $_SESSION['user_id'];
$teacher_username = $_SESSION['username'];
$token = csrf_token();

$stmt = $conn->prepare("SELECT id, name FROM courses WHERE teacher_id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$course_ids = array_column($courses, 'id');

if (!empty($course_ids)) {
    $placeholders = implode(',', array_fill(0, count($course_ids), '?'));
    $types = str_repeat('i', count($course_ids));
    $students_sql = "SELECT e.course_id, u.id, u.username, u.email FROM enrollments e JOIN users u ON e.student_id = u.id WHERE e.course_id IN ($placeholders)";
    $stmt_students = $conn->prepare($students_sql);
    $stmt_students->bind_param($types, ...$course_ids);
    $stmt_students->execute();
    $all_students = $stmt_students->get_result()->fetch_all(MYSQLI_ASSOC);

    $schedules_sql = "SELECT s.course_id, s.id, s.day_of_week, s.timeslot, r.name as room_name FROM schedules s JOIN rooms r ON s.room_id = r.id WHERE s.course_id IN ($placeholders)";
    $stmt_schedules = $conn->prepare($schedules_sql);
    $stmt_schedules->bind_param($types, ...$course_ids);
    $stmt_schedules->execute();
    $all_schedules = $stmt_schedules->get_result()->fetch_all(MYSQLI_ASSOC);

    $students_by_course = [];
    foreach ($all_students as $student) { $students_by_course[$student['course_id']][] = $student; }
    $schedules_by_course = [];
    foreach ($all_schedules as $schedule) { $schedules_by_course[$schedule['course_id']][] = $schedule; }

    foreach ($courses as &$course) {
        $course['students'] = $students_by_course[$course['id']] ?? [];
        $course['schedules'] = $schedules_by_course[$course['id']] ?? [];
    }
    unset($course);
}

$all_attendance = [];
if (!empty($course_ids)) {
    $attendance_sql = "
        SELECT a.student_id, a.status, a.date, s.course_id, u.username as student_name
        FROM attendance a
        JOIN schedules s ON a.schedule_id = s.id
        JOIN users u ON a.student_id = u.id
        WHERE s.course_id IN ($placeholders)
        ORDER BY s.course_id, u.username, a.date DESC
    ";
    $stmt_attendance = $conn->prepare($attendance_sql);
    $stmt_attendance->bind_param($types, ...$course_ids);
    $stmt_attendance->execute();
    $attendance_results = $stmt_attendance->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($attendance_results as $record) {
        $all_attendance[$record['course_id']][] = $record;
    }

    $all_announcements = [];
    $announcements_sql = "
        SELECT id, course_id, content, created_at
        FROM announcements
        WHERE course_id IN ($placeholders)
        ORDER BY created_at DESC
    ";
    $stmt_announcements = $conn->prepare($announcements_sql);
    $stmt_announcements->bind_param($types, ...$course_ids);
    $stmt_announcements->execute();
    $announcements_results = $stmt_announcements->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($announcements_results as $announcement) { $all_announcements[$announcement['course_id']][] = $announcement; }
}

$stmt_pending_bookings = $conn->prepare(
    "SELECT ps.id, c.name as course_name, r.name as room_name, ps.day_of_week, ps.timeslot, ps.status
     FROM pending_schedules ps
     JOIN courses c ON ps.course_id = c.id
     JOIN rooms r ON ps.room_id = r.id
     WHERE ps.teacher_id = ?
     ORDER BY ps.request_date DESC"
);
$stmt_pending_bookings->bind_param("i", $teacher_id);
$stmt_pending_bookings->execute();
$my_pending_bookings = $stmt_pending_bookings->get_result()->fetch_all(MYSQLI_ASSOC);

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
    <meta name="csrf-token" content="<?php echo htmlspecialchars($token); ?>">
    <title>Teacher Dashboard</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert-toast <?php echo isset($_GET['ok']) ? 'alert-success' : 'alert-danger'; ?>" role="alert">
            <?php echo htmlspecialchars(urldecode($_GET['msg'])); ?>
        </div>
    <?php endif; ?>

    <div class="container my-4">
        <div class="mb-4">
            <h1>Teacher Dashboard</h1>
            <p class="text-secondary">Welcome, <?php echo htmlspecialchars($teacher_username); ?>!</p>
        </div>

        <div class="d-flex justify-content-between align-items-end">
            <ul class="nav nav-tabs" id="teacherTab" role="tablist" style="border-bottom: none;">
                <li class="nav-item"><a class="nav-link active" id="courses-tab" data-toggle="tab" href="#courses" role="tab">My Courses</a></li>
                <li class="nav-item"><a class="nav-link" id="take-attendance-tab" data-toggle="tab" href="#take-attendance" role="tab">Take Attendance</a></li>
                <li class="nav-item"><a class="nav-link" id="view-attendance-tab" data-toggle="tab" href="#view-attendance" role="tab">View Attendance</a></li>
                <li class="nav-item"><a class="nav-link" id="announcements-tab" data-toggle="tab" href="#announcements" role="tab">Announcements</a></li>
                <li class="nav-item"><a class="nav-link" id="rooms-tab" data-toggle="tab" href="#rooms" role="tab">Book a Room</a></li>
            </ul>
            <div>
                <a href="logout.php" class="btn btn-danger mb-1">Logout</a>
            </div>
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
                                    <tr><td><p><?php echo htmlspecialchars($student['username']); ?></p></td><td><p><?php echo htmlspecialchars($student['email']); ?></p></td></tr>
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
                                                <tr><td><p><?php echo htmlspecialchars($student['username']); ?></p></td>
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

            <div class="tab-pane fade" id="view-attendance" role="tabpanel">
                <h3 class="mb-3">Attendance Records</h3>
                <?php if (empty($courses)): ?><p class="text-secondary">You have no courses to view attendance for.</p>
                <?php else: foreach ($courses as $course): ?>
                    <div class="card mb-3">
                        <div class="card-header"><h5><?php echo htmlspecialchars($course['name']); ?></h5></div>
                        <div class="card-body">
                            <?php $course_attendance = $all_attendance[$course['id']] ?? []; ?>
                            <?php if (empty($course_attendance)): ?>
                                <p class="text-secondary">No attendance has been recorded for this course yet.</p>
                            <?php else: ?>
                                <div class="table-responsive" style="max-height: 400px;">
                                    <table class="table table-sm table-hover">
                                        <thead><tr><th>Student</th><th>Date</th><th class="text-center">Status</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($course_attendance as $record): ?>
                                            <tr><td><?php echo htmlspecialchars($record['student_name']); ?></td><td><?php echo htmlspecialchars($record['date']); ?></td><td class="text-center"><span class="status-<?php echo strtolower($record['status']); ?>"><?php echo ucfirst($record['status']); ?></span></td></tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table></div>
                            <?php endif; ?>
                        </div></div>
                <?php endforeach; endif; ?>
            </div>

            <div class="tab-pane fade" id="announcements" role="tabpanel">
                <h3 class="mb-3">Post an Announcement</h3>
                <?php if (empty($courses)): ?><p class="text-secondary">You have no courses to post announcements for.</p>
                <?php else: foreach ($courses as $course): ?>
                    <div class="card mb-4">
                        <div class="card-header"><h5><?php echo htmlspecialchars($course['name']); ?></h5></div>
                        <div class="card-body">
                            <form action="manage_teacher_tasks.php" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($token); ?>">
                                <input type="hidden" name="action" value="add_announcement">
                                <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                <div class="form-group"><textarea name="content" class="form-control" rows="3" placeholder="Write your announcement here..."></textarea></div>
                                <button type="submit" class="btn btn-primary">Post to <?php echo htmlspecialchars($course['name']); ?></button>
                            </form>
                            <hr>
                            <h6>Previous Announcements</h6>
                            <?php $course_announcements = $all_announcements[$course['id']] ?? []; ?>
                            <?php if (empty($course_announcements)): ?><p class="text-secondary small">No announcements posted for this course yet.</p>
                            <?php else: ?>
                                <div class="announcements-list">
                                    <?php foreach ($course_announcements as $announcement): ?>
                                    <div class="announcement-item d-flex justify-content-between align-items-start">
                                        <div>
                                            <p class="mb-1"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p><small class="text-secondary"><?php echo date('F j, Y, g:i a', strtotime($announcement['created_at'])); ?></small>
                                        </div>
                                        <button class="btn btn-outline-danger btn-sm delete-announcement-btn ml-3" data-announcement-id="<?php echo $announcement['id']; ?>">&times;</button>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>

            <div class="tab-pane fade" id="rooms" role="tabpanel">
                <h3 class="mb-3">Your Pending Booking Requests</h3>
                <div class="card mb-4">
                    <div class="card-body table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Course</th>
                                    <th>Room</th>
                                    <th>Day</th>
                                    <th>Timeslot</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($my_pending_bookings)): ?>
                                <tr><td colspan="5" class="text-center text-secondary">No pending booking requests.</td></tr>
                            <?php else: foreach ($my_pending_bookings as $pb): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($pb['course_name']); ?></td>
                                    <td><?php echo htmlspecialchars($pb['room_name']); ?></td>
                                    <td><?php echo htmlspecialchars($pb['day_of_week']); ?></td>
                                    <td><?php echo htmlspecialchars($pb['timeslot']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $pb['status'] === 'approved' ? 'success' : ($pb['status'] === 'denied' ? 'danger' : 'warning'); ?>">
                                            <?php echo ucfirst($pb['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <h3 class="mb-3">Room Availability & Booking</h3>
                <div class="form-group"><input type="text" id="roomSearchInput" class="form-control" placeholder="Search for a room name..."></div>
                 <div class="table-responsive">
                    <table class="table table-bordered timetable-grid text-center" id="roomBookingTable">
                        <thead class="thead-light mb-3"><tr><th>Room</th><?php foreach ($weekdays as $day) echo "<th>$day</th>"; ?></tr></thead>
                        <tbody>
                        <?php foreach ($rooms as $room): ?>
                            <tr class="room-row"><th class="align-middle room-name mb-3"><?php echo htmlspecialchars($room['name']); ?></th>
                                <?php foreach ($weekdays as $day): ?>
                                    <td>
                                    <?php foreach ($timeslots as $slot): ?>
                                        <?php $is_booked = isset($booked_slots[$room['id']][$day][$slot]); ?>
                                        <div class="p-1 my-1 border rounded mb-3 <?php echo $is_booked ? 'availability-booked' : 'availability-free'; ?>">
                                            <small><?php echo $slot; ?></small>
                                            <?php if ($is_booked): ?>
                                                <div class="font-weight-bold" style="font-size: 0.8em;"><?php echo htmlspecialchars($booked_slots[$room['id']][$day][$slot]); ?></div>
                                            <?php else: ?>
                                                <button class="btn btn-success btn-sm p-0 px-1 book-room-btn"
                                                        style="font-size: 0.7em;"
                                                        data-toggle="modal"
                                                        data-target="#bookRoomModal"
                                                        data-room-id="<?php echo $room['id']; ?>"
                                                        data-room-name="<?php echo htmlspecialchars($room['name']); ?>"
                                                        data-day="<?php echo $day; ?>"
                                                        data-timeslot="<?php echo $slot; ?>">Book</button>
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
    
    <!-- Book Room Modal -->
    <div class="modal fade" id="bookRoomModal" tabindex="-1" role="dialog" aria-labelledby="bookRoomModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <form id="bookRoomForm">
            <div class="modal-header">
              <h5 class="modal-title" id="bookRoomModalLabel">Confirm Room Booking</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
              <p>You are booking room <strong id="modal-room-name"></strong> for <strong id="modal-day"></strong> at <strong id="modal-timeslot"></strong>.</p>
              <input type="hidden" name="action" value="book_room">
              <input type="hidden" name="room_id" id="modal-room-id">
              <input type="hidden" name="day" id="modal-form-day">
              <input type="hidden" name="timeslot" id="modal-form-timeslot">
              
              <div class="form-group">
                <label for="modal-course-id">Assign this booking to which of your courses?</label>
                <select name="course_id" id="modal-course-id" class="form-control" required>
                  <option value="">-- Select Your Course --</option>
                  <?php foreach ($courses as $course): ?>
                    <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Confirm & Book</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>