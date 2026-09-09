<?php
require 'includes/auth.php';
require_once 'includes/mailer.php';
require_once 'includes/sms.php';
require_once 'includes/password-reset.php';

if (logged_in()) {
    redirect(dashboard_path(current_user()['role']));
}

/*
 * Step 3 - Enter the 6-digit OTP for the chosen channel.
 *
 * Actions:
 *   verify  - check the code (max 5 attempts, 5 min lifetime)
 *   resend  - issue a NEW code for the SAME channel (60s cooldown,
 *             5 sends per 15 minutes per account)
 *   switch  - drop the current code and go back to method selection
 *             ("Use another method" - required feature)
 */

 $flow = pwreset_flow();
if (
    $flow === null
    || empty($flow['method'])
    || empty($flow['reset_id'])
) {
    redirect('forgot-password.php');
}
if (!empty($flow['verified'])) {
    redirect('reset-password.php');
}

 $errors = [];
 $info = '';

// Seconds until the resend button unlocks (server clock, skew-free).
 $resendIn = max(0, (int) $flow['resend_at'] - time());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('action');
    $flow = pwreset_flow();

    if ($action === 'switch') {
        // Invalidate the pending code so it can never be reused, then
        // return to the method selection page.
        pr_invalidate_pending((int) $flow['user_id']);
        $_SESSION['pwreset']['method'] = null;
        $_SESSION['pwreset']['reset_id'] = null;
        $_SESSION['pwreset']['masked'] = '';
        $_SESSION['pwreset']['resend_at'] = 0;
        redirect('forgot-password.php?step=method');
    }

    if ($action === 'resend') {
        $method = (string) $flow['method'];
        $destination = $method === 'phone'
            ? (string) $flow['methods']['phone']
            : (string) $flow['methods']['email'];

        $issued = pr_issue_otp((int) $flow['user_id'], $method, $destination);

        if (!$issued['ok']) {
            $errors[] = $issued['reason'] === 'cooldown'
                ? 'Please wait ' . max(1, (int) $issued['retry_after']) . ' seconds before requesting a new code.'
                : 'Too many codes were requested. Please try again in a few minutes.';
            $resendIn = max($resendIn, (int) ($issued['retry_after'] ?? 0));
        } elseif (!pr_deliver_otp($method, $destination, $issued['otp'])) {
            pr_invalidate_row((int) $issued['reset_id']);
            $errors[] = 'We could not send the verification code right now. Please use another method or try again later.';
        } else {
            // New code replaces the old one everywhere.
            $_SESSION['pwreset']['reset_id'] = (int) $issued['reset_id'];
            $_SESSION['pwreset']['resend_at'] = (int) $issued['resend_at'];
            $resendIn = PR_RESEND_COOLDOWN;
            $info = 'A new verification code was sent to ' . (string) $flow['masked'] . '.';
        }
    }

    if ($action === 'verify') {
        $code = preg_replace('/\D+/', '', (string) ($_POST['code'] ?? ''));

        if (strlen($code) !== 6) {
            $errors[] = 'Please enter the 6-digit verification code.';
        } else {
            $result = pr_verify_otp((int) $flow['reset_id'], (int) $flow['user_id'], $code);

            if ($result['ok']) {
                // OTP verified once - create the short-lived, single-use
                // reset authorization (token in session, hash in DB).
                $_SESSION['pwreset']['verified'] = true;
                $_SESSION['pwreset']['flow_token'] = (string) $result['flow_token'];
                session_regenerate_id(true);
                redirect('reset-password.php');
            }

            $reason = (string) $result['reason'];
            if ($reason === 'maxattempts') {
                $errors[] = 'Too many incorrect attempts. Please request a new verification code.';
            } elseif ($reason === 'expired') {
                $errors[] = 'This code has expired. Please request a new verification code.';
            } elseif ($reason === 'used') {
                $errors[] = 'This code was already used. Please request a new verification code.';
            } elseif ($reason === 'missing') {
                redirect('forgot-password.php');
            } else {
                $left = (int) ($result['left'] ?? 0);
                $errors[] = 'The verification code is incorrect.'
                    . ($left > 0 ? " {$left} attempt" . ($left === 1 ? '' : 's') . ' remaining.' : '');
            }
        }
    }
}

 $flow = pwreset_flow();
 $methodLabel = ($flow['method'] ?? '') === 'phone' ? 'phone number' : 'email address';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Account | Myanmar Horizons</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= url('/assets/css/style.css') ?>" rel="stylesheet">
    <style>
        body, html { margin: 0; padding: 0; height: 100%; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .verify-container { position: relative; min-height: 100vh; display: flex; align-items: center; justify-content: center; background-color: #000; padding: 20px 0; }
        .bg-image { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-image: url('https://images.unsplash.com/photo-1548013146-72479768bada?auto=format&fit=crop&w=1800&q=80'); background-size: cover; background-position: center; filter: blur(10px) brightness(0.6); transform: scale(1.1); z-index: 1; }
        .verify-card { position: relative; z-index: 2; width: 100%; max-width: 450px; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); padding: 40px; margin: 20px; }
        .icon-box { width: 70px; height: 70px; background: #e0f2fe; color: #075985; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
        .code-input { font-size: 2.5rem; font-weight: 800; letter-spacing: 0.5rem; text-align: center; border: 2px solid #e2e8f0; border-radius: 12px; margin-bottom: 20px; color: #075985; }
        .code-input:focus { border-color: #0f766e; box-shadow: 0 0 0 4px rgba(15, 118, 110, 0.1); }
        .btn-verify { background: linear-gradient(135deg, #075985, #0f766e); border: none; color: white; padding: 14px; font-weight: 700; border-radius: 12px; transition: transform 0.2s; }
        .btn-verify:hover { transform: translateY(-2px); opacity: 0.9; }
        .resend-link { color: #075985; text-decoration: none; font-weight: 600; background: none; border: none; padding: 0; }
        .resend-link:disabled { color: #94a3b8; cursor: not-allowed; }
    </style>
</head>
<body>

<div class="verify-container">
    <div class="bg-image"></div>

    <div class="verify-card text-center">
        <div class="icon-box">
            <i class="bi bi-shield-lock fa-2x"></i>
        </div>

        <h2 class="fw-bold mb-2">Verify Your Account</h2>
        <p class="text-muted mb-1">We sent a verification code to your <?= e($methodLabel) ?>:</p>
        <p class="text-dark fw-bold mb-4"><?= e((string) $flow['masked']) ?></p>

        <?php if (sms_test_mode() && ($flow['method'] ?? '') === 'phone'): ?>
            <div class="alert alert-warning py-2 small">
                <i class="bi bi-triangle me-1"></i>SMS test mode is active
                (config/sms.php). Messages are written to the server log
                logs/sms.log instead of being delivered by a real provider.
            </div>
        <?php endif; ?>

        <?php foreach ($errors as $err): ?>
            <div class="alert alert-danger py-2 small"><?= e($err) ?></div>
        <?php endforeach; ?>
        <?php if ($info !== ''): ?>
            <div class="alert alert-success py-2 small"><?= e($info) ?></div>
        <?php endif; ?>

        <form method="post" class="needs-validation" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="verify">
            <input
                type="text"
                name="code"
                class="form-control code-input"
                maxlength="6"
                inputmode="numeric"
                pattern="[0-9]{6}"
                placeholder="000000"
                required
                autofocus
                autocomplete="off"
            >
            <button type="submit" class="btn btn-verify w-100 mb-3">Verify OTP</button>
        </form>

        <form method="post" class="mb-3">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="resend">
            <button type="submit" class="resend-link" id="resendBtn" <?= $resendIn > 0 ? 'disabled' : '' ?>>
                <i class="bi bi-arrow-clockwise me-1"></i><span id="resendLabel"><?= $resendIn > 0 ? 'Resend available in ' . $resendIn . 's' : 'Resend OTP' ?></span>
            </button>
        </form>

        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="switch">
            <button type="submit" class="resend-link">
                &larr; Use another method
            </button>
        </form>
    </div>
</div>

<script>
(function () {
    var remaining = <?= $resendIn ?>;
    var btn = document.getElementById('resendBtn');
    var label = document.getElementById('resendLabel');
    if (remaining <= 0 || !btn) return;
    btn.disabled = true;
    var timer = setInterval(function () {
        remaining--;
        if (remaining <= 0) {
            clearInterval(timer);
            btn.disabled = false;
            label.textContent = 'Resend OTP';
        } else {
            label.textContent = 'Resend available in ' + remaining + 's';
        }
    }, 1000);
})();
</script>

</body>
</html>