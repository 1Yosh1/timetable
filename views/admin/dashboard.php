<div class="sidebar">
    <h3 class="sidebar-header">Admin Panel</h3>
    <a href="<?php echo htmlspecialchars($baseUri ?? ''); ?>/admin_dashboard.php?page=home" class="<?php echo ($current_sub_page=='home')?'active':''; ?>"><i class="fas fa-home"></i> Home</a>
    <a href="#managementSubmenu" data-toggle="collapse" aria-expanded="<?php echo $is_management_active?'true':'false'; ?>" class="dropdown-toggle"><i class="fas fa-tasks"></i> Management</a>
    <ul class="collapse list-unstyled <?php echo $is_management_active?'show':''; ?>" id="managementSubmenu">
        <li><a href="<?php echo htmlspecialchars($baseUri ?? ''); ?>/admin_dashboard.php?page=users"     class="<?php echo ($current_sub_page=='users')?'active':''; ?>">Users</a></li>
        <li><a href="<?php echo htmlspecialchars($baseUri ?? ''); ?>/admin_dashboard.php?page=courses"   class="<?php echo ($current_sub_page=='courses')?'active':''; ?>">Courses</a></li>
        <li><a href="<?php echo htmlspecialchars($baseUri ?? ''); ?>/admin_dashboard.php?page=schedules" class="<?php echo ($current_sub_page=='schedules')?'active':''; ?>">Schedules</a></li>
        <li><a href="<?php echo htmlspecialchars($baseUri ?? ''); ?>/admin_dashboard.php?page=requests"  class="<?php echo ($current_sub_page=='requests')?'active':''; ?>">Enrollment Requests</a></li>
        <li><a href="<?php echo htmlspecialchars($baseUri ?? ''); ?>/admin_dashboard.php?page=schedule_requests" class="<?php echo ($current_sub_page=='schedule_requests')?'active':''; ?>">Schedule Requests</a></li>
        <li><a href="<?php echo htmlspecialchars($baseUri ?? ''); ?>/admin_dashboard.php?page=reports"  class="<?php echo ($current_sub_page=='reports')?'active':''; ?>">Reports</a></li>
    </ul>
    <hr style="border-color:#404249;">
    <a href="<?php echo htmlspecialchars($baseUri ?? ''); ?>/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
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
            <form action="<?php echo htmlspecialchars($baseUri ?? ''); ?>/manage_admin_tasks.php" method="POST" class="form-inline">
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
                                <form action="<?php echo htmlspecialchars($baseUri ?? ''); ?>/manage_admin_tasks.php" method="POST" class="delete-form m-0">
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
            <form action="<?php echo htmlspecialchars($baseUri ?? ''); ?>/manage_admin_tasks.php" method="POST">
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
                            <form action="<?php echo htmlspecialchars($baseUri ?? ''); ?>/manage_admin_tasks.php" method="POST" class="form-inline justify-content-center">
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
    <?php if (isset($conflictFlag) && $conflictFlag): ?><div class="alert alert-danger">Schedule conflict: room/time already in use.</div><?php endif; ?>
    <div class="card mb-3">
        <div class="card-header">Create New Class Schedule</div>
        <div class="card-body">
            <form action="<?php echo htmlspecialchars($baseUri ?? ''); ?>/manage_admin_tasks.php" method="POST" class="form-row align-items-end">
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
                            <form action="<?php echo htmlspecialchars($baseUri ?? ''); ?>/manage_admin_tasks.php" method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                <input type="hidden" name="action" value="approve_enrollment">
                                <input type="hidden" name="source_page" value="requests">
                                <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                <button class="btn btn-success btn-sm">Approve</button>
                            </form>
                            <form action="<?php echo htmlspecialchars($baseUri ?? ''); ?>/manage_admin_tasks.php" method="POST" class="d-inline delete-form">
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

<?php elseif ($page === 'schedule_requests'): ?>
    <h1 class="mb-4">Pending Schedule Requests</h1>
    <div class="card">
        <div class="card-header">Schedule Requests</div>
        <div class="card-body table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Course</th>
                        <th>Room</th>
                        <th>Day</th>
                        <th>Timeslot</th>
                        <th>Requested By</th>
                        <th>Student Conflicts</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($pending_schedules)): ?>
                    <tr><td colspan="7" class="text-center text-secondary">No pending schedule requests.</td></tr>
                <?php else: foreach ($pending_schedules as $req): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($req['course_name']); ?></td>
                        <td><?php echo htmlspecialchars($req['room_name']); ?></td>
                        <td><?php echo htmlspecialchars($req['day_of_week']); ?></td>
                        <td><?php echo htmlspecialchars($req['timeslot']); ?></td>
                        <td><?php echo htmlspecialchars($req['teacher_name']); ?></td>
                        <td>
                            <?php if (empty($req['conflicts'])): ?>
                                <span class="text-success"><i class="fas fa-check-circle"></i> No conflicts</span>
                            <?php else: ?>
                                <div class="text-danger small">
                                    <?php foreach ($req['conflicts'] as $c): ?>
                                        <div>⚠️ Conflict: <?php echo $c['student_count']; ?> student(s) also in <strong><?php echo htmlspecialchars($c['course_name']); ?></strong></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-right">
                            <form action="<?php echo htmlspecialchars($baseUri ?? ''); ?>/manage_admin_tasks.php" method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                <input type="hidden" name="action" value="approve_schedule">
                                <input type="hidden" name="source_page" value="schedule_requests">
                                <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                <button class="btn btn-success btn-sm">Approve</button>
                            </form>
                            <form action="<?php echo htmlspecialchars($baseUri ?? ''); ?>/manage_admin_tasks.php" method="POST" class="d-inline delete-form">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                <input type="hidden" name="action" value="deny_schedule">
                                <input type="hidden" name="source_page" value="schedule_requests">
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

<?php elseif ($page === 'reports'): ?>
    <h1 class="mb-4">System Reports</h1>
    <div class="row">
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">Course Enrollment Statistics</div>
                <div class="card-body table-responsive" style="max-height: 400px;">
                    <table class="table table-hover">
                        <thead><tr><th>Course Name</th><th class="text-center">Enrollments</th></tr></thead>
                        <tbody>
                        <?php foreach ($enrollment_stats as $stat): ?>
                            <tr><td><?php echo htmlspecialchars($stat['name']); ?></td><td class="text-center"><?php echo $stat['enrollment_count']; ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">Room Utilization</div>
                <div class="card-body table-responsive" style="max-height: 400px;">
                    <table class="table table-hover">
                        <thead><tr><th>Room Name</th><th class="text-center">Utilization</th></tr></thead>
                        <tbody>
                        <?php foreach ($room_utilization as $stat):
                            $percentage = $total_slots_per_room > 0 ? round(($stat['booked_slots'] / $total_slots_per_room) * 100) : 0;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($stat['name']); ?></td>
                                <td class="text-center">
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar" role="progressbar" style="width: <?php echo $percentage; ?>%;" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"><?php echo $percentage; ?>%</div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document"><div class="modal-content">
      <form action="<?php echo htmlspecialchars($baseUri ?? ''); ?>/manage_admin_tasks.php" method="POST">
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

<div id="adminTabContent"></div>
