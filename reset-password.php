<?php
require 'includes/auth.php';
require_once 'includes/password-reset.php';

if (logged_in()) {
    redirect(dashboard_path(current_user()['role']));
}

/*
 * Step 4 - Set the new password. REACHABLE ONLY AFTER A SUCCESSFUL OTP
 * VERIFICATION (see pr_verified_authorization()): the session must hold a
 * flow token whose SHA-256 matches a freshly 'verified' row for the SAME
 * account. There is no customer id in any URL, so nothing can be traded
 * between accounts. The authorization is single-use and expires after
 * 10 minutes.
 */

 $errors = [];

// Success screen after the password was changed.
if (isset($_GET['done'])) {
    if (empty($_SESSION['pwreset_done'])) {
        redirect('login.php');
    }
    unset($_SESSION['pwreset_done']);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Password Reset Successful | Myanmar Horizons</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
        <style>
            body, html { margin: 0; padding: 0; height: 100%; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
            .verify-container { position: relative; min-height: 100vh; display: flex; align-items: center; justify-content: center; background-color: #000; padding: 20px 0; }
            .bg-image { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-image: url('https://images.unsplash.com/photo-1548013146-72479768bada?auto=format&fit=crop&w=1800&q=80'); background-size: cover; background-position: center; filter: blur(10px) brightness(0.6); transform: scale(1.1); z-index: 1; }
            .verify-card { position: relative; z-index: 2; width: 100%; max-width: 450px; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); padding: 40px; margin: 20px; }
            .icon-box { width: 70px; height: 70px; background: #dcfce7; color: #15803d; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
            .btn-back { background: linear-gradient(135deg, #075985, #0f766e); border: none; color: white; padding: 14px; font-weight: 700; border-radius: 12px; transition: transform 0.2s; }
            .btn-back:hover { transform: translateY(-2px); opacity: 0.9; color: white; }
        </style>
    </head>
    <body>
    <div class="verify-container">
        <div class="bg-image"></div>
        <div class="verify-card text-center">
            <div class="icon-box">
                <i class="bi bi-check-circle fa-2x"></i>
            </div>
            <h2 class="fw-bold mb-2">Password Reset Successfully</h2>
            <p class="text-muted mb-4">
                Your password has been changed. You can now log in with your new password.
            </p>
            <a href="<?= url('login.php') ?>" class="btn btn-back w-100">Back to Login</a>
        </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// Hard guard: no verified, unexpired, matching authorization -> restart.
 $row = pr_verified_authorization();
if ($row === null) {
    pwreset_clear();
    flash('info', 'Your password reset session has expired or is no longer valid. Please start again.');
    redirect('forgot-password.php');
}

 $userId = (int) $row['user_id'];
 $resetId = (int) $row['reset_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($new) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $errors[] = 'New password and confirmation do not match.';
    } else {
        try {
            $pdo = db();
            $pdo->beginTransaction();

            // Re-check the authorization INSIDE the transaction so two
            // parallel submissions can never both consume it.
            $stmt = $pdo->prepare(
                "SELECT reset_id FROM password_resets
                 WHERE reset_id = ? AND user_id = ? AND status = 'verified' FOR UPDATE"
            );
            $stmt->execute([$resetId, $userId]);
            if ($stmt->fetch() === false) {
                $pdo->rollBack();
                $errors[] = 'This reset authorization was already used. Please start again.';
            } else {
                // Same hashing system as registration/login.
                $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')
                    ->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
                $pdo->prepare(
                    "UPDATE password_resets SET status = 'used', used_at = NOW()
                     WHERE reset_id = ? AND status = 'verified'"
                )->execute([$resetId]);
                // Any other still-pending OTP for this account dies too.
                $pdo->prepare(
                    "UPDATE password_resets SET status = 'invalidated'
                     WHERE user_id = ? AND status = 'pending'"
                )->execute([$userId]);
                $pdo->commit();

                pwreset_clear();
                $_SESSION['pwreset_done'] = true;
                session_regenerate_id(true);
                redirect('reset-password.php?done=1');
            }
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            $errors[] = 'The password could not be updated. Please try again.';
        }
    }
}

 $row = pr_verified_authorization(); // still valid? (e.g. after an error above)
if ($row === null) {
    pwreset_clear();
    flash('info', 'Your password reset session has expired. Please start again.');
    redirect('forgot-password.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Myanmar Horizons</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body, html { margin: 0; padding: 0; height: 100%; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .verify-container { position: relative; min-height: 100vh; display: flex; align-items: center; justify-content: center; background-color: #000; padding: 20px 0; }
        .bg-image { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-image: url('https://images.unsplash.com/photo-1548013146-72479768bada?auto=format&fit=crop&w=1800&q=80'); background-size: cover; background-position: center; filter: blur(10px) brightness(0.6); transform: scale(1.1); z-index: 1; }
        .verify-card { position: relative; z-index: 2; width: 100%; max-width: 450px; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); padding: 40px; margin: 20px; }
        .icon-box { width: 70px; height: 70px; background: #e0f2fe; color: #075985; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
        .btn-verify { background: linear-gradient(135deg, #075985, #0f766e); border: none; color: white; padding: 14px; font-weight: 700; border-radius: 12px; transition: transform 0.2s; }
        .btn-verify:hover { transform: translateY(-2px); opacity: 0.9; }
    </style>
</head>
<body>

<div class="verify-container">
    <div class="bg-image"></div>

    <div class="verify-card">
        <div class="icon-box">
            <i class="bi bi-key fa-2x"></i>
        </div>

        <h2 class="fw-bold text-center mb-2">Reset Password</h2>
        <p class="text-muted text-center mb-4">Choose a new password for your account.</p>

        <?php foreach ($errors as $err): ?>
            <div class="alert alert-danger py-2 small"><?= e($err) ?></div>
        <?php endforeach; ?>

        <form method="post" class="needs-validation" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label">New Password</label>
                <input
                    type="password"
                    name="new_password"
                    class="form-control"
                    minlength="8"
                    required
                    autofocus
                >
                <div class="form-text">At least 8 characters.</div>
            </div>

            <div class="mb-4">
                <label class="form-label">Confirm New Password</label>
                <input
                    type="password"
                    name="confirm_password"
                    class="form-control"
                    minlength="8"
                    required
                >
            </div>

            <button type="submit" class="btn btn-verify w-100">Reset Password</button>
        </form>
    </div>
</div>

</body>
</html>