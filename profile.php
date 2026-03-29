<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/csrf.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== 'student') {
    header("Location: index.php");
    exit();
}

$token = csrf_token();
$user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Student';
$email = $_SESSION['email'] ?? 'No email on file';

?>
<!DOCTYPE html>
<html>
<head>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($token); ?>">
    <title>My Profile</title>
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>My Profile</h1>
            <div>
                <button id="theme-toggle" class="btn btn-outline-secondary" title="Toggle Theme"><i class="fas fa-moon"></i></button>
                <a href="student_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Your Information</div>
                    <div class="card-body">
                        <p><strong>Username:</strong> <?php echo htmlspecialchars($username); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Change Password</div>
                    <div class="card-body">
                        <form action="change_password_process.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($token); ?>">
                            <div class="form-group"><label for="current_password">Current Password</label><input type="password" name="current_password" id="current_password" class="form-control" required></div>
                            <div class="form-group"><label for="new_password">New Password</label><input type="password" name="new_password" id="new_password" class="form-control" required></div>
                            <div class="form-group"><label for="confirm_password">Confirm New Password</label><input type="password" name="confirm_password" id="confirm_password" class="form-control" required></div>
                            <button type="submit" class="btn btn-primary">Update Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>