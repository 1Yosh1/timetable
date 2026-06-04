<div class="login-container">
    <div class="login-header">
        <img src="<?php echo htmlspecialchars($baseUri ?? ''); ?>/images/logo.png" alt="UniMe Logo" class="login-logo-img">
        <h2>Sign in</h2>
        <p>Select your role to continue</p>
    </div>

    <form action="<?php echo htmlspecialchars($baseUri ?? ''); ?>/login_process.php" method="POST" autocomplete="on">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($token ?? ''); ?>">
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
        <a href="<?php echo htmlspecialchars($baseUri ?? ''); ?>/register.php">Create account</a>
        <span class="mx-2 text-secondary">|</span>
        <a href="<?php echo htmlspecialchars($baseUri ?? ''); ?>/admin_login.php">Admin Login</a>
    </div>
</div>
