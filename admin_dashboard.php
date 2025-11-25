<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once 'app/csrf.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (
    !(
        isset($_SESSION['admin_id'])
        || (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin')
    )
) {
    header("Location: admin_login.php");
    exit();
}
require_once 'db_config.php';
use App\Domain\DayOfWeek;
use App\Domain\TimeSlot;
$weekdays  = DayOfWeek::all();
$timeslots = TimeSlot::all();

$csrf = csrf_token();
$page = $_GET['page'] ?? 'home';
$conflictFlag = isset($_GET['conflict']) && $_GET['conflict'] == '1';

$users    = $conn->query("SELECT id, username, email, role FROM users WHERE role != 'admin' ORDER BY role, username")->fetch_all(MYSQLI_ASSOC);
$teachers = $conn->query("SELECT id, username FROM users WHERE role = 'teacher' ORDER BY username")->fetch_all(MYSQLI_ASSOC);
$students = $conn->query("SELECT id, username FROM users WHERE role = 'student' ORDER BY username")->fetch_all(MYSQLI_ASSOC);
$courses  = $conn->query("SELECT c.id, c.name, c.description, c.credits, u.username AS teacher_name FROM courses c LEFT JOIN users u ON c.teacher_id = u.id ORDER BY c.name")->fetch_all(MYSQLI_ASSOC);
$rooms    = $conn->query("SELECT id, name FROM rooms ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$weekdays  = ["Monday","Tuesday","Wednesday","Thursday","Friday"];
$timeslots = ["09:00-10:00","10:00-11:00","11:00-12:00","12:00-13:00","13:00-14:00","14:00-15:00","15:00-16:00","16:00-17:00"];

$current_page_name = basename($_SERVER['PHP_SELF']);
$current_sub_page  = $_GET['page'] ?? 'home';
$management_pages  = ['users','courses','schedules','requests'];
$is_management_active = ($current_page_name === 'admin_dashboard.php' && in_array($current_sub_page, $management_pages, true));

if ($page === 'home') {
    $schedule_data = [];
    $schedule_sql = "SELECT s.day_of_week, s.timeslot, c.name AS course_name, r.name AS room_name, u.username as teacher_name
                     FROM schedules s
                     JOIN courses c ON s.course_id = c.id
                     JOIN rooms r ON s.room_id = r.id
                     LEFT JOIN users u on c.teacher_id = u.id";
    $schedule_result = $conn->query($schedule_sql);
    while ($row = $schedule_result->fetch_assoc()) {
        $schedule_data[$row['day_of_week']][$row['timeslot']][] = $row;
    }
}

if ($page === 'requests') {
    $pending_requests = $conn->query(
        "SELECT pr.id, u.username, c.name AS course_name, pr.request_date
         FROM pending_enrollments pr
         JOIN users u ON pr.student_id = u.id
         JOIN courses c ON pr.course_id = c.id
         WHERE pr.status='pending'
         ORDER BY pr.request_date ASC"
    )->fetch_all(MYSQLI_ASSOC);
}

if ($page === 'schedules') {
    $schedules = $conn->query(
        "SELECT s.id, c.name AS course_name, r.name AS room_name, s.day_of_week, s.timeslot
         FROM schedules s
         JOIN courses c ON s.course_id = c.id
         JOIN rooms r ON s.room_id = r.id
         ORDER BY FIELD(s.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday'), s.timeslot, c.name"
    )->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <!-- Bootstrap & Icons -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- App stylesheet -->
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
<div class="sidebar">
    <h3 class="sidebar-header">Admin Panel</h3>
    <a href="admin_dashboard.php?page=home" class="<?php echo ($current_sub_page=='home')?'active':''; ?>"><i class="fas fa-home"></i> Home</a>
    <a href="#managementSubmenu" data-toggle="collapse" aria-expanded="<?php echo $is_management_active?'true':'false'; ?>" class="dropdown-toggle"><i class="fas fa-tasks"></i> Management</a>
    <ul class="collapse list-unstyled <?php echo $is_management_active?'show':''; ?>" id="managementSubmenu">
        <li><a href="admin_dashboard.php?page=users"     class="<?php echo ($current_sub_page=='users')?'active':''; ?>">Users</a></li>
        <li><a href="admin_dashboard.php?page=courses"   class="<?php echo ($current_sub_page=='courses')?'active':''; ?>">Courses</a></li>
        <li><a href="admin_dashboard.php?page=schedules" class="<?php echo ($current_sub_page=='schedules')?'active':''; ?>">Schedules</a></li>
        <li><a href="admin_dashboard.php?page=requests"  class="<?php echo ($current_sub_page=='requests')?'active':''; ?>">Enrollment Requests</a></li>
    </ul>
    <hr style="border-color:#404249;">
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>
<div class="main-content">
<?php if ($page === 'home'): ?>
    <h1 class="mb-4">Dashboard Home</h1>
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4"><div class="card"><div class="card-body"><h3><?php echo count($teachers); ?></h3><p class="text-secondary">Total Teachers</p></div></div></div>
        <div class="col-xl-3 col-md-6 mb-4"><div class="card"><div class="card-body"><h3><?php echo count($students); ?></h3><p class="text-secondary">Total Students</p></div></div></div>
        <div class="col-xl-3 col-md-6 mb-4"><div class="card"><div class="card-body"><h3><?php echo count($courses); ?></h3><p class="text-secondary">Total Courses</p></div></div></div>
        <div class="col-xl-3 col-md-6 mb-4"><div class="card"><div class="card-body"><h3><?php echo count($rooms); ?></h3><p class="text-secondary">Total Rooms</p></div></div></div>
    </div>
    <div class="card">
        <div class="card-header">Master Timetable</div>
        <div class="card-body table-responsive">
            <table class="table table-bordered text-center">
                <thead><tr><th>Time</th><?php foreach($weekdays as $d) echo "<th>$d</th>"; ?></tr></thead>
                <tbody>
                <?php foreach ($timeslots as $slot): ?>
                    <tr>
                        <th><?php echo $slot; ?></th>
                        <?php foreach ($weekdays as $day): ?>
                            <td>
                                <?php
                                if (isset($schedule_data[$day][$slot])) {
                                    foreach ($schedule_data[$day][$slot] as $s) {
                                        echo "<div class='slot-item'><strong>".htmlspecialchars($s['course_name'])."</strong><br><small>".htmlspecialchars($s['room_name'])."</small></div>";
                                    }
                                }
                                ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($page === 'users'): ?>
    <h1 class="mb-4">User Management</h1>
    <div class="card mb-4">
        <div class="card-header">Add New User</div>
        <div class="card-body">
            <form action="manage_admin_tasks.php" method="POST" class="form-inline">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                <input type="hidden" name="action" value="add_user">
                <input type="hidden" name="source_page" value="users">
                <input type="text" name="username" class="form-control mb-2 mr-sm-2" placeholder="Username" required>
                <input type="email" name="email" class="form-control mb-2 mr-sm-2" placeholder="Email" required>
                <input type="password" name="password" class="form-control mb-2 mr-sm-2" placeholder="Password" required>
                <select name="role" class="form-control mb-2 mr-sm-2" required>
                    <option value="student">Student</option>
                    <option value="teacher">Teacher</option>
                </select>
                <button type="submit" class="btn btn-primary mb-2">Add User</button>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-header">Existing Users</div>
        <div class="card-body table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Username</th><th>Email</th><th>Role</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($user['role'])); ?></td>
                        <td class="text-right">
                            <div class="btn-group">
                                <button class="btn btn-info btn-sm edit-user-btn"
                                        data-toggle="modal"
                                        data-target="#editUserModal"
                                        data-id="<?php echo $user['id']; ?>"
                                        data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                        data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                        data-role="<?php echo $user['role']; ?>">Edit</button>
                                <form action="manage_admin_tasks.php" method="POST" class="delete-form m-0">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="source_page" value="users">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach;?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($page === 'courses'): ?>
    <h1 class="mb-4">Course Management</h1>
    <div class="card mb-4">
        <div class="card-header">Create New Course</div>
        <div class="card-body">
            <form action="manage_admin_tasks.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                <input type="hidden" name="action" value="add_course">
                <input type="hidden" name="source_page" value="courses">
                <div class="form-row">
                    <div class="form-group col-md-6"><label>Course Name</label><input type="text" name="name" class="form-control" required></div>
                    <div class="form-group col-md-6"><label>Credits</label><input type="number" name="credits" class="form-control"></div>
                </div>
                <div class="form-group"><label>Description</label><textarea name="description" class="form-control"></textarea></div>
                <button type="submit" class="btn btn-primary">Create Course</button>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-header">Existing Courses & Teacher Assignment</div>
        <div class="card-body table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Course</th><th>Teacher</th><th class="text-center">Assign Teacher</th></tr></thead>
                <tbody>
                <?php foreach ($courses as $course): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($course['name']); ?></td>
                        <td><?php echo htmlspecialchars($course['teacher_name'] ?? 'Not Assigned'); ?></td>
                        <td class="text-center">
                            <form action="manage_admin_tasks.php" method="POST" class="form-inline justify-content-center">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                <input type="hidden" name="action" value="assign_teacher">
                                <input type="hidden" name="source_page" value="courses">
                                <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                <select name="teacher_id" class="form-control form-control-sm mr-2" required>
                                    <option value="">Select</option>
                                    <?php foreach ($teachers as $t) { echo "<option value='{$t['id']}'>".htmlspecialchars($t['username'])."</option>"; } ?>
                                </select>
                                <button class="btn btn-primary btn-sm">Assign</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach;?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($page === 'schedules'): ?>
    <h1 class="mb-4">Schedule Management</h1>
    <?php if ($conflictFlag): ?><div class="alert alert-danger">Schedule conflict: room/time already in use.</div><?php endif; ?>
    <div class="card mb-3">
        <div class="card-header">Create New Class Schedule</div>
        <div class="card-body">
            <form action="manage_admin_tasks.php" method="POST" class="form-row align-items-end">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                <input type="hidden" name="action" value="add_schedule">
                <input type="hidden" name="source_page" value="schedules">
                <div class="form-group col-md-3">
                    <label>Course</label>
                    <select name="course_id" class="form-control schedule-input" required>
                        <option value="">Select Course</option>
                        <?php foreach($courses as $c) { echo "<option value='{$c['id']}'>".htmlspecialchars($c['name'])."</option>"; } ?>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label>Room</label>
                    <select name="room_id" class="form-control schedule-input" required>
                        <option value="">Select Room</option>
                        <?php foreach($rooms as $r) { echo "<option value='{$r['id']}'>".htmlspecialchars($r['name'])."</option>"; } ?>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <label>Day</label>
                    <select name="day_of_week" class="form-control schedule-input" required>
                        <option value="">Select Day</option>
                        <?php foreach ($weekdays as $d) echo "<option value=\"$d\">$d</option>"; ?>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <label>Timeslot</label>
                    <select name="timeslot" class="form-control schedule-input" required>
                        <option value="">Select Slot</option>
                        <?php foreach ($timeslots as $t) echo "<option value=\"$t\">$t</option>"; ?>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary btn-block">Create</button>
                </div>
                <div class="col-12">
                    <div id="conflict-warning" class="mt-2 text-danger small"></div>
                </div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-header">Existing Schedules</div>
        <div class="card-body table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Course</th><th>Room</th><th>Day</th><th>Timeslot</th></tr></thead>
                <tbody>
                <?php if (empty($schedules)): ?>
                    <tr><td colspan="4" class="text-center text-secondary">No schedules created.</td></tr>
                <?php else: foreach ($schedules as $s): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($s['course_name']); ?></td>
                        <td><?php echo htmlspecialchars($s['room_name']); ?></td>
                        <td><?php echo htmlspecialchars($s['day_of_week']); ?></td>
                        <td><?php echo htmlspecialchars($s['timeslot']); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($page === 'requests'): ?>
    <h1 class="mb-4">Pending Enrollment Requests</h1>
    <div class="card">
        <div class="card-header">Requests</div>
        <div class="card-body table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Student</th><th>Course</th><th>Requested At</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php if (empty($pending_requests)): ?>
                    <tr><td colspan="4" class="text-center text-secondary">No pending requests.</td></tr>
                <?php else: foreach ($pending_requests as $req): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($req['username']); ?></td>
                        <td><?php echo htmlspecialchars($req['course_name']); ?></td>
                        <td><?php echo htmlspecialchars($req['request_date']); ?></td>
                        <td class="text-right">
                            <form action="manage_admin_tasks.php" method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                <input type="hidden" name="action" value="approve_enrollment">
                                <input type="hidden" name="source_page" value="requests">
                                <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                <button class="btn btn-success btn-sm">Approve</button>
                            </form>
                            <form action="manage_admin_tasks.php" method="POST" class="d-inline delete-form">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                <input type="hidden" name="action" value="deny_enrollment">
                                <input type="hidden" name="source_page" value="requests">
                                <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                <button class="btn btn-danger btn-sm">Deny</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document"><div class="modal-content">
      <form action="manage_admin_tasks.php" method="POST">
          <div class="modal-header">
              <h5 class="modal-title">Edit User</h5>
              <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
          </div>
          <div class="modal-body">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
              <input type="hidden" name="action" value="edit_user">
              <input type="hidden" name="source_page" value="users">
              <input type="hidden" name="user_id" id="edit-user-id">
              <div class="form-group"><label>Username</label><input type="text" name="username" id="edit-username" class="form-control" required></div>
              <div class="form-group"><label>Email</label><input type="email" name="email" id="edit-email" class="form-control" required></div>
              <div class="form-group"><label>Role</label>
                  <select name="role" id="edit-role" class="form-control" required>
                      <option value="student">Student</option>
                      <option value="teacher">Teacher</option>
                  </select>
              </div>
              <div class="form-group"><label>New Password (optional)</label><input type="password" name="password" class="form-control"></div>
          </div>
          <div class="modal-footer">
              <button type="submit" class="btn btn-primary">Save Changes</button>
              <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          </div>
      </form>
  </div></div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="js/script.js"></script>
</body>
</html>