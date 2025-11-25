<?php
// Attendance overview removed. Redirect to admin dashboard (requests or home).
header("Location: admin_dashboard.php?page=home", true, 302);
exit();
