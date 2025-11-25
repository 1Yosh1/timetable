<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/csrf.php';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link rel="stylesheet" href="css/login.css">
</head>
<body class="login-page-body">
    <div class="login-container">
        <div class="login-header">
            <h2>User Sign In</h2>
            <p>Select your role and enter credentials</p>
        </div>
        <form action="login_process.php" method="POST">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label for="username">Username</label><br>
                <input type="text" id="username" name="username" required autocomplete="username" class="form-control">
            </div>
            <div class="form-group">
                <label for="password">Password</label><br>
                <input type="password" id="password" name="password" required autocomplete="current-password" class="form-control">
            </div>
            <div class="form-group">
                <label for="role">Role</label><br>
                <select id="role" name="role" class="form-control" required>
                    <option value="student">Student</option>
                    <option value="teacher">Teacher</option>
                </select>
            </div>
            <input type="submit" value="Login" class="btn btn-primary btn-block">
        </form>
        <div style="margin-top:15px; text-align:center;">
            <a href="admin_login.php">Admin Login</a>
        </div>
    </div>
</body>
</html>