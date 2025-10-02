<?php
session_start();
if (!isset($_SESSION['admin_id']) && (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin')) {
    header("Location: admin_login.php");
    exit();
}
require_once 'db_config.php';

// Fetch all teachers
$teachers_query = $conn->query("SELECT id, username FROM users WHERE role = 'teacher' ORDER BY username");
$teachers = $teachers_query->fetch_all(MYSQLI_ASSOC);

// Fetch all courses with their assigned teacher's ID
$courses_query = $conn->query("SELECT name, teacher_id FROM courses");
$courses = $courses_query->fetch_all(MYSQLI_ASSOC);

// Group courses by teacher_id for easy lookup
$teacher_courses = [];
foreach ($courses as $course) {
    if (!isset($teacher_courses[$course['teacher_id']])) {
        $teacher_courses[$course['teacher_id']] = [];
    }
    $teacher_courses[$course['teacher_id']][] = $course['name'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Teacher Assignments Dashboard</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <?php include 'admin_sidebar.php'; // Use a separate file for the sidebar for consistency ?>
    
    <div class="main-content">
        <h1 class="mb-4">Teacher Assignments</h1>
        <div class="row">
            <?php foreach ($teachers as $teacher): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <i class="fas fa-chalkboard-teacher"></i> <?php echo htmlspecialchars($teacher['username']); ?>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">Assigned Courses:</h5>
                            <?php if (isset($teacher_courses[$teacher['id']]) && !empty($teacher_courses[$teacher['id']])): ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($teacher_courses[$teacher['id']] as $course_name): ?>
                                        <li class="list-group-item"><?php echo htmlspecialchars($course_name); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted">No courses assigned.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>