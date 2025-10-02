<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: index.php");
    exit();
}
require_once 'db_config.php';
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Student';

// --- NEW & IMPROVED DATA FETCHING ---
// 1. Get the student's CURRENT schedule to check for conflicts
$current_schedule_query = $conn->prepare("SELECT s.day_of_week, s.timeslot FROM enrollments e JOIN schedules s ON e.course_id = s.course_id WHERE e.student_id = ?");
$current_schedule_query->bind_param("i", $user_id);
$current_schedule_query->execute();
$current_schedule_result = $current_schedule_query->get_result();
$my_schedule = [];
while ($row = $current_schedule_result->fetch_assoc()) {
    $my_schedule[$row['day_of_week']][$row['timeslot']] = true;
}

// 2. Count current approved enrollments
$count_query = $conn->prepare("SELECT COUNT(id) as count FROM enrollments WHERE student_id = ?");
$count_query->bind_param("i", $user_id);
$count_query->execute();
$enrollment_count = $count_query->get_result()->fetch_assoc()['count'];
$enrollment_limit = 5;

// 3. Fetch all available courses and THEIR schedules
$available_courses = [];
$available_query = "SELECT id, name, description FROM courses WHERE id NOT IN (SELECT course_id FROM enrollments WHERE student_id = ?)";
$stmt_available = $conn->prepare($available_query);
$stmt_available->bind_param("i", $user_id);
$stmt_available->execute();
$available_result = $stmt_available->get_result();
while ($course = $available_result->fetch_assoc()) {
    $schedules_sql = "SELECT day_of_week, timeslot FROM schedules WHERE course_id = ?";
    $stmt_schedules = $conn->prepare($schedules_sql);
    $stmt_schedules->bind_param("i", $course['id']);
    $stmt_schedules->execute();
    $course['schedules'] = $stmt_schedules->get_result()->fetch_all(MYSQLI_ASSOC);
    $available_courses[] = $course;
}

// 4. Fetch currently enrolled courses for management tab
$enrolled_query = "SELECT c.id, c.name, u.username as teacher_name FROM enrollments e JOIN courses c ON e.course_id = c.id LEFT JOIN users u ON c.teacher_id = u.id WHERE e.student_id = ?";
$stmt_enrolled = $conn->prepare($enrolled_query);
$stmt_enrolled->bind_param("i", $user_id);
$stmt_enrolled->execute();
$enrolled_courses_list = $stmt_enrolled->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Student Dashboard</h1>
            <a href="logout.php" class="btn btn-danger">Logout</a>
        </div>

        <ul class="nav nav-tabs" id="studentTab" role="tablist">
            <li class="nav-item"><a class="nav-link active" id="schedule-tab" data-toggle="tab" href="#schedule" role="tab">My Schedule</a></li>
            <li class="nav-item"><a class="nav-link" id="my-courses-tab" data-toggle="tab" href="#my-courses" role="tab">My Courses</a></li>
            <li class="nav-item"><a class="nav-link" id="enroll-tab" data-toggle="tab" href="#enroll" role="tab">Enroll in New Course</a></li>
        </ul>

        <div class="tab-content bg-white p-4 border border-top-0" id="studentTabContent">
            <div class="tab-pane fade show active" id="schedule" role="tabpanel">
                <h3 class="mb-3">Your Weekly Schedule</h3>
                <div class="card"><div class="card-body table-responsive">
                    <?php if (empty($my_schedule)): ?>
                        <p class="text-secondary text-center my-3">Your schedule is empty. Enroll in a course to see it here.</p>
                    <?php else: ?>
                        <table class="table table-hover">
                            <thead><tr><th>Course Name</th><th>Timeslot</th><th>Day</th><th>Room</th></tr></thead>
                            <tbody>
                            <?php 
                            // Re-fetch schedule details for display
                            $schedule_display_query = "SELECT c.name, s.timeslot, s.day_of_week, r.name AS room_name FROM enrollments e JOIN courses c ON e.course_id = c.id JOIN schedules s ON c.id = s.course_id JOIN rooms r ON s.room_id = r.id WHERE e.student_id = ? ORDER BY FIELD(s.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), s.timeslot";
                            $stmt_display = $conn->prepare($schedule_display_query);
                            $stmt_display->bind_param("i", $user_id);
                            $stmt_display->execute();
                            $schedule_courses_display = $stmt_display->get_result()->fetch_all(MYSQLI_ASSOC);
                            foreach ($schedule_courses_display as $course): ?>
                                <tr><td><?php echo htmlspecialchars($course['name']); ?></td><td><?php echo htmlspecialchars($course['timeslot']); ?></td><td><?php echo htmlspecialchars($course['day_of_week']); ?></td><td><?php echo htmlspecialchars($course['room_name']); ?></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div></div>
            </div>

            <div class="tab-pane fade" id="my-courses" role="tabpanel">
                <h3 class="mb-3">Manage Your Enrolled Courses</h3>
                <div class="card"><div class="card-body table-responsive">
                    <?php if (empty($enrolled_courses_list)): ?><p class="text-secondary text-center my-3">You are not enrolled in any courses.</p>
                    <?php else: ?>
                    <table class="table table-hover"><thead><tr><th>Course Name</th><th>Teacher</th><th class="text-right">Action</th></tr></thead><tbody>
                        <?php foreach ($enrolled_courses_list as $course): ?>
                            <tr><td><?php echo htmlspecialchars($course['name']); ?></td><td><?php echo htmlspecialchars($course['teacher_name'] ?? 'N/A'); ?></td>
                                <td class="text-right"><button class="btn btn-danger btn-sm unenroll-btn" data-course-id="<?php echo $course['id']; ?>">Unenroll</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody></table>
                    <?php endif; ?>
                </div></div>
            </div>

            <div class="tab-pane fade" id="enroll" role="tabpanel">
                <h3 class="mb-3">Available Courses for Enrollment</h3>
                <?php if ($enrollment_count >= $enrollment_limit): ?>
                    <div class="alert alert-warning">You have reached the maximum of <?php echo $enrollment_limit; ?> courses. To enroll in more, you must request approval from an administrator.</div>
                <?php endif; ?>
                <div class="card"><div class="card-body table-responsive">
                    <table class="table table-hover"><thead><tr><th>Course Name</th><th>Description</th><th class="text-right">Action</th></tr></thead><tbody>
                    <?php foreach ($available_courses as $course): ?>
                        <tr><td><?php echo htmlspecialchars($course['name']); ?></td><td><?php echo htmlspecialchars($course['description']); ?></td>
                            <td class="text-right">
                                <?php
                                $has_conflict = false;
                                foreach ($course['schedules'] as $schedule_slot) {
                                    if (isset($my_schedule[$schedule_slot['day_of_week']][$schedule_slot['timeslot']])) {
                                        $has_conflict = true;
                                        break;
                                    }
                                }
                                if ($has_conflict): ?>
                                    <button class="btn btn-secondary btn-sm" disabled>Conflict</button>
                                <?php elseif ($enrollment_count < $enrollment_limit): ?>
                                    <button class="btn btn-primary btn-sm enroll-btn" data-course-id="<?php echo $course['id']; ?>">Enroll</button>
                                <?php else: ?>
                                    <button class="btn btn-warning btn-sm request-approval-btn" data-course-id="<?php echo $course['id']; ?>">Request Approval</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody></table>
                </div></div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>