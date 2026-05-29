<div class="login-container">
    <div class="login-header">
        <i class="fas fa-user-plus login-logo"></i>
        <h2>Create your Account</h2>
        <p>Join as a Student or Teacher</p>
    </div>
    
    <form action="<?php echo htmlspecialchars($baseUri ?? ''); ?>/register_process.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($token ?? ''); ?>">
        <div class="form-group">
            <input type="text" name="username" class="form-control" placeholder="Username" required>
        </div>
        <div class="form-group">
            <input type="email" name="email" class="form-control" placeholder="Email Address" required>
        </div>
        <div class="form-group">
            <input type="password" name="password" class="form-control" placeholder="Password" required>
        </div>
        <div class="form-group">
            <select name="role" class="form-control" required>
                <option value="" disabled selected>Register as...</option>
                <option value="student">Student</option>
                <option value="teacher">Teacher</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Register</button>
    </form>

    <div class="login-links">
        <a href="<?php echo htmlspecialchars($baseUri ?? ''); ?>/index.php">Already have an account? Sign in</a>
    </div>
</div>
