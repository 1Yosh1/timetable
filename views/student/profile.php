<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>My Profile</h1>
        <div>
            <button id="theme-toggle" class="btn btn-outline-secondary" title="Toggle Theme"><i class="fas fa-moon"></i></button>
            <a href="<?php echo htmlspecialchars($baseUri ?? ''); ?>/student_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Your Information</div>
                <div class="card-body">
                    <p><strong>Username:</strong> <?php echo htmlspecialchars($username ?? ''); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($email ?? ''); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Change Password</div>
                <div class="card-body">
                    <form action="<?php echo htmlspecialchars($baseUri ?? ''); ?>/change_password_process.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($token ?? ''); ?>">
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
