<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/csrf.php';
$token = csrf_token();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sign In</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet"
          href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <style>
        /* Temporary debug: remove if not needed */
        /* .login-container { position:relative; } */
    </style>
</head>
<body class="login-page-body">
<div class="login-container">
    <div class="login-header">
        <i class="fas fa-calendar-check login-logo"></i>
        <h2>Sign in</h2>
        <p>Select your role to continue</p>
    </div>

    <form action="login_process.php" method="POST" autocomplete="on">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($token); ?>">
        <div class="form-group">
            <input type="text"
                   name="username"
                   class="form-control"
                   placeholder="Username"
                   required
                   autocomplete="username">
        </div>
        <div class="form-group">
            <input type="password"
                   name="password"
                   class="form-control"
                   placeholder="Password"
                   required
                   autocomplete="current-password">
        </div>
        <div class="form-group">
            <select name="role" class="form-control" required>
                <option value="" disabled selected>Select your role...</option>
                <option value="student">Student</option>
                <option value="teacher">Teacher</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Sign In</button>
    </form>

    <div class="login-links mt-3 text-center">
        <a href="register.php">Create account</a>
        <span class="mx-2 text-secondary">|</span>
        <a href="admin_login.php">Admin Login</a>
    </div>
</div>

</body>
</html>