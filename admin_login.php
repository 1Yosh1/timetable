<?php
require_once 'app/bootstrap.php';
require_once 'app/csrf.php';
$token = csrf_token();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Login</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body class="login-page-body">
    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-user-shield login-logo"></i>
            <h2>Admin Sign In</h2>
            <p>Access the management dashboard</p>
        </div>
        
        <form action="admin_login_process.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($token); ?>">
            <div class="form-group">
                <input type="text" name="username" class="form-control" placeholder="Admin Username" required autocomplete="username">
            </div>
            <div class="form-group">
                <input type="password" name="password" class="form-control" placeholder="Password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Login</button>
        </form>
        <div class="login-links">
            <a href="index.php">Back to Main Login</a>
        </div>
    </div>
</body>
</html>