<div class="login-container">
    <div class="login-header">
        <img src="<?php echo htmlspecialchars($baseUri ?? ''); ?>/images/logo.png" alt="UniMe Logo" class="login-logo-img">
        <h2>Admin Sign In</h2>
        <p>Access the management dashboard</p>
    </div>
    
    <form action="<?php echo htmlspecialchars($baseUri ?? ''); ?>/admin_login_process.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($token ?? ''); ?>">
        <div class="form-group">
            <input type="text" name="username" class="form-control" placeholder="Admin Username" required autocomplete="username">
        </div>
        <div class="form-group">
            <input type="password" name="password" class="form-control" placeholder="Password" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-primary btn-block">Login</button>
    </form>
    <div class="login-links">
        <a href="<?php echo htmlspecialchars($baseUri ?? ''); ?>/index.php">Back to Main Login</a>
    </div>
</div>
