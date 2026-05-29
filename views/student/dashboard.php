<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Student Dashboard</h1>
        <div>
            <button id="theme-toggle" class="btn btn-outline-secondary" title="Toggle Theme"><i class="fas fa-moon"></i></button>
            <a href="<?php echo htmlspecialchars($baseUri ?? ''); ?>/profile.php" class="btn btn-secondary">My Profile</a>
            <a href="<?php echo htmlspecialchars($baseUri ?? ''); ?>/logout.php" class="btn btn-danger">Logout</a>
        </div>
    </div>

    <ul class="nav nav-tabs" id="studentTab" role="tablist">
        <li class="nav-item"><a class="nav-link active" id="schedule-tab" data-toggle="tab" href="#schedule" role="tab">My Schedule</a></li>
        <li class="nav-item"><a class="nav-link" id="my-courses-tab" data-toggle="tab" href="#my-courses" role="tab">My Courses</a></li>
        <li class="nav-item"><a class="nav-link" id="enroll-tab" data-toggle="tab" href="#enroll" role="tab">Enroll in New Course</a></li>
        <li class="nav-item"><a class="nav-link" id="attendance-tab" data-toggle="tab" href="#attendance" role="tab">My Attendance</a></li>
        <li class="nav-item"><a class="nav-link" id="announcements-tab" data-toggle="tab" href="#announcements" role="tab">Announcements</a></li>
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
                                <td ><?php echo htmlspecialchars($course['timeslot']); ?></td>
                                <td ><?php echo htmlspecialchars($course['day_of_week']); ?></td>
                                <td ><?php echo htmlspecialchars($course['room_name']); ?></td>
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
            <?php if (isset($enrollment_count) && isset($enrollment_limit) && $enrollment_count >= $enrollment_limit): ?>
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
                            <?php elseif (isset($enrollment_count) && isset($enrollment_limit) && $enrollment_count < $enrollment_limit): ?>
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

        <div class="tab-pane fade" id="attendance" role="tabpanel">
            <h3 class="mb-3">Your Attendance Records</h3>
            <?php if (empty($my_attendance_by_course)): ?>
                <p class="text-secondary text-center my-3">No attendance has been recorded for you yet.</p>
            <?php else: ?>
                <?php foreach ($my_attendance_by_course as $course_name => $records): ?>
                    <div class="card mb-3">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5><?php echo htmlspecialchars($course_name); ?></h5>
                                <?php
                                    $total = count($records);
                                    $present = count(array_filter($records, fn($r) => $r['status'] === 'present'));
                                    $percentage = $total > 0 ? round(($present / $total) * 100) : 0;
                                ?>
                                <span class="font-weight-bold">Attendance: <?php echo $percentage; ?>%</span>
                            </div>
                        </div>
                        <div class="card-body table-responsive" style="max-height: 300px;">
                            <table class="table table-sm table-hover">
                                <thead><tr><th>Date</th><th class="text-center">Status</th></tr></thead>
                                <tbody>
                                <?php foreach ($records as $record): ?>
                                    <tr><td><?php echo htmlspecialchars($record['date']); ?></td><td class="text-center"><span class="status-<?php echo strtolower($record['status']); ?>"><?php echo ucfirst($record['status']); ?></span></td></tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="tab-pane fade" id="announcements" role="tabpanel">
            <h3 class="mb-3">Course Announcements</h3>
            <?php if (empty($my_announcements)): ?>
                <p class="text-secondary text-center my-3">No announcements from your teachers yet.</p>
            <?php else: ?>
                <div class="list-group">
                <?php foreach ($my_announcements as $ann): ?>
                    <div class="list-group-item list-group-item-action flex-column align-items-start">
                        <div class="d-flex w-100 justify-content-between">
                            <h5 class="mb-1"><?php echo htmlspecialchars($ann['course_name']); ?></h5>
                            <small class="text-muted"><?php echo date('F j, Y', strtotime($ann['created_at'])); ?></small>
                        </div>
                        <p class="mb-1"><?php echo nl2br(htmlspecialchars($ann['content'])); ?></p>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
