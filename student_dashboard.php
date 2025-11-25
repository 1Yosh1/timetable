<?php
require_once __DIR__ . '/app/bootstrap.php';  // ensures session + autoload + db
require_once __DIR__ . '/app/csrf.php';
require_once __DIR__ . '/app/Auth.php';
require_once __DIR__ . '/app/Config/AppConfig.php';
require_once __DIR__ . '/app/Repositories/EnrollmentRepository.php';
require_once __DIR__ . '/app/Repositories/CourseRepository.php';

use App\Domain\DayOfWeek;
use App\Domain\TimeSlot;
$weekdays  = DayOfWeek::all();
$timeslots = TimeSlot::all();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== 'student') {
    header("Location: index.php");
    exit();
}

requireRole(['student']);
regenerateSession();

use App\Config\AppConfig;
use App\Repositories\EnrollmentRepository;
use App\Repositories\CourseRepository;

$token    = csrf_token();
$user_id  = (int) $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Student';

$enrollRepo = new EnrollmentRepository();
$courseRepo = new CourseRepository();

// Single detailed schedule fetch (used for display + conflict map)
$my_detailed_schedule = $enrollRepo->getDetailedSchedule($conn, $user_id);

// Build conflict map day->timeslot boolean
$my_schedule = [];
foreach ($my_detailed_schedule as $row) {
    $my_schedule[$row['day_of_week']][$row['timeslot']] = true;
}

// Enrollment count + limit
$enrollment_count = $enrollRepo->countByStudent($conn, $user_id);
$enrollment_limit = AppConfig::maxCourses();

// Batch load available courses incl. schedules
$available_courses = $courseRepo->getAvailableWithSchedules($conn, $user_id);

// Enrolled courses (basic listing)
$enrolled_courses_list = $enrollRepo->getEnrolledCoursesBasic($conn, $user_id);
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($token); ?>">
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
                    <?php if (empty($my_detailed_schedule)): ?>
                        <p class="text-secondary text-center my-3">Your schedule is empty. Enroll in a course to see it here.</p>
                    <?php else: ?>
                        <table class="table table-hover">
                            <thead><tr><th>Course Name</th><th>Timeslot</th><th>Day</th><th>Room</th></tr></thead>
                            <tbody>
                            <?php foreach ($my_detailed_schedule as $course): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                    <td><?php echo htmlspecialchars($course['timeslot']); ?></td>
                                    <td><?php echo htmlspecialchars($course['day_of_week']); ?></td>
                                    <td><?php echo htmlspecialchars($course['room_name']); ?></td>
                                </tr>
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
                        <tr>
                            <td><?php echo htmlspecialchars($course['name']); ?></td>
                            <td><?php echo htmlspecialchars($course['description']); ?></td>
                            <td class="text-right">
                                <?php
                                $has_conflict = false;
                                foreach ($course['schedules'] as $slot) {
                                    if (isset($my_schedule[$slot['day_of_week']][$slot['timeslot']])) { $has_conflict = true; break; }
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