<?php
session_start();
if (!isset($_SESSION['admin_id']) && (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin')) {
    header("Location: admin_login.php");
    exit();
}
require_once 'db_config.php';

// Data for Overall Attendance Chart
$overall_query = $conn->query("SELECT status, COUNT(id) as count FROM attendance GROUP BY status");
$overall_data = $overall_query->fetch_all(MYSQLI_ASSOC);

// Data for Detailed Attendance Table
$attendance_records_sql = "
    SELECT u.username, c.name as course_name, a.date, a.status 
    FROM attendance a
    JOIN users u ON a.student_id = u.id
    JOIN schedules s ON a.schedule_id = s.id
    JOIN courses c ON s.course_id = c.id
    ORDER BY a.date DESC, u.username
";
$attendance_records_result = $conn->query($attendance_records_sql);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Attendance Dashboard</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css">
</head>
<body>
    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content">
        <h1 class="mb-4">Attendance Overview</h1>
        <div class="row">
            <div class="col-lg-4">
                <div class="card"><div class="card-header">Overall Attendance Rate</div><div class="card-body"><canvas id="overallAttendanceChart"></canvas></div></div>
            </div>
            <div class="col-lg-8">
                 <div class="card"><div class="card-header">All Attendance Records</div><div class="card-body">
                    <table id="attendanceTable" class="table table-striped table-bordered" style="width:100%">
                        <thead><tr><th>Student</th><th>Course</th><th>Date</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php while($row = $attendance_records_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td><?php echo htmlspecialchars($row['course_name']); ?></td>
                                <td><?php echo $row['date']; ?></td>
                                <td class="<?php echo $row['status'] == 'present' ? 'text-success' : 'text-danger'; ?>"><?php echo ucfirst($row['status']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div></div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.25/js/dataTables.bootstrap4.min.js"></script>
    <script>
        // Initialize DataTables for search and pagination
        $(document).ready(function() {
            $('#attendanceTable').DataTable();
        });

        // Chart.js logic
        const overallAttendanceCanvas = document.getElementById('overallAttendanceChart');
        if (overallAttendanceCanvas) {
            const data = <?php echo json_encode($overall_data); ?>;
            let present = 0, absent = 0;
            data.forEach(item => {
                if(item.status === 'present') present = item.count;
                if(item.status === 'absent') absent = item.count;
            });

            new Chart(overallAttendanceCanvas, {
                type: 'doughnut',
                data: {
                    labels: ['Present', 'Absent'],
                    datasets: [{ data: [present, absent], backgroundColor: ['rgba(75, 192, 192, 0.7)', 'rgba(255, 99, 132, 0.7)'] }]
                }
            });
        }
    </script>
</body>
</html>