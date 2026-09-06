<?php require '../includes/auth.php';
require_role('hotel');

 $userId = (int) current_user()['user_id'];
 $errors = [];

// Shown as an alert when the hotel is still using its temporary password.
 $stmt = db()->prepare('SELECT must_change_password FROM users WHERE user_id = ?');
 $stmt->execute([$userId]);
 $forced = (bool) ($stmt->fetchColumn() ?: false);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $stmt = db()->prepare('SELECT password_hash FROM users WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    if (!$row) {
        $errors[] = 'Account unavailable.';
    } elseif (!password_verify($current, $row['password_hash'])) {
        $errors[] = 'The current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $errors[] = 'New password and confirmation do not match.';
    } elseif ($current === $new) {
        $errors[] = 'The new password must be different from the current password.';
    } else {
        // Store only the hash and clear the temporary-password flag.
        db()->prepare('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE user_id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
        refresh_session_user($userId);
        flash('success', 'Password changed successfully. Welcome to your hotel dashboard.');
        redirect('hotel/dashboard.php');
    }
}

 $pageTitle = 'Set a new password';
require '../includes/header.php'; ?><div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card">
                <div class="card-body p-4 p-md-5">
                    <h2>Set a new password</h2>
                    <?php if ($forced): ?>
                        <div class="alert alert-info">
                            For security, you must replace the temporary password emailed to you with your own password before using the dashboard.
                        </div>
                    <?php endif; ?>
                    <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
                    <form method="post" class="needs-validation" novalidate>
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label">Current (temporary) password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New password</label>
                            <input type="password" name="new_password" class="form-control" minlength="8" required>
                            <div class="form-text">At least 8 characters.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm new password</label>
                            <input type="password" name="confirm_password" class="form-control" minlength="8" required>
                        </div>
                        <button class="btn btn-primary w-100">Save new password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div><?php require '../includes/footer.php'; ?>