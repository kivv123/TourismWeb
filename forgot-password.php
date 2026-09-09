<?php
require 'includes/auth.php';
require_once 'includes/mailer.php';
require_once 'includes/sms.php';
require_once 'includes/password-reset.php';

if (logged_in()) {
    redirect(dashboard_path(current_user()['role']));
}

/*
 * Step 1 - Identify the customer account (email OR phone).
 * Step 2 - Choose the verification method (only methods the account has).
 *
 * The lookup happens entirely on the server; no account id is ever read
 * from the URL. Unknown identifiers get the SAME neutral response as
 * known ones (account-enumeration protection).
 */

 $errors = [];
 $notice = '';
 $step = 'identify';

 $flow = pwreset_flow();
if (($flow['method'] ?? null) !== null && ($flow['reset_id'] ?? null) !== null) {
    // An OTP is already in flight for this flow - send them to verify page.
    redirect('verify-otp.php');
}
if (isset($_GET['step']) && $_GET['step'] === 'method' && $flow !== null) {
    $step = 'method';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('action');

    if ($action === 'identify') {
        // Light brute-force brake on the identifier form itself:
        // max 10 lookups per 15 minutes per session.
        $attempts = array_values(array_filter(
            (array) ($_SESSION['fp_attempts'] ?? []),
            fn($t) => (time() - (int) $t) < 900
        ));
        $attempts[] = time();
        $_SESSION['fp_attempts'] = $attempts;
        if (count($attempts) > 10) {
            $errors[] = 'Too many attempts. Please wait a few minutes and try again.';
        } else {
            $identifier = post('identifier');

            $account = pr_find_customer_by_identifier($identifier);
            if ($account === null) {
                // Neutral answer: reveals nothing about whether the
                // email/phone exists. No state is created.
                $notice = 'If an account matches the information provided, you will be able to continue with account recovery.';
            } else {
                $methods = pr_available_methods($account);
                if (!$methods['phone'] && !$methods['email']) {
                    // Identified, but nothing to send an OTP to.
                    pwreset_clear();
                    $notice = 'No available recovery method was found for this account. Please contact support.';
                } else {
                    pwreset_start((int) $account['user_id'], [
                        'phone' => trim((string) $account['phone']),
                        'email' => strtolower(trim((string) $account['email'])),
                    ]);
                    redirect('forgot-password.php?step=method');
                }
            }
        }
    }

    if ($action === 'send') {
        $flow = pwreset_flow();
        if ($flow === null) {
            redirect('forgot-password.php');
        }

        $method = post('method');
        if (!in_array($method, ['phone', 'email'], true) || empty($flow['methods'][$method])) {
            $errors[] = 'Please choose one of the available verification methods.';
        } else {
            $destination = $method === 'phone'
                ? (string) $flow['methods']['phone']
                : (string) $flow['methods']['email'];

            $issued = pr_issue_otp((int) $flow['user_id'], $method, $destination);

            if (!$issued['ok']) {
                $errors[] = $issued['reason'] === 'cooldown'
                    ? 'Please wait ' . (int) ceil($issued['retry_after'] / 5) * 5 . ' seconds before requesting a new code.'
                    : 'Too many codes were requested. Please try again in a few minutes.';
            } else {
                if (!pr_deliver_otp($method, $destination, $issued['otp'])) {
                    // Provider failure: never pretend the code was sent,
                    // never leave an unsendable pending row behind.
                    pr_invalidate_row((int) $issued['reset_id']);
                    $errors[] = 'We could not send the verification code right now. Please choose another method or try again later.';
                } else {
                    $_SESSION['pwreset']['method'] = $method;
                    $_SESSION['pwreset']['reset_id'] = (int) $issued['reset_id'];
                    $_SESSION['pwreset']['masked'] = $method === 'phone'
                        ? mask_phone($destination)
                        : mask_email($destination);
                    $_SESSION['pwreset']['resend_at'] = (int) $issued['resend_at'];
                    $_SESSION['pwreset']['verified'] = false;
                    $_SESSION['pwreset']['flow_token'] = null;
                    redirect('verify-otp.php');
                }
            }
        }

        $step = 'method';
        $flow = pwreset_flow();
    }
}

 $pageTitle = 'Forgot Password';
require 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card">
                <div class="card-body p-4 p-md-5">

                <?php if ($step === 'identify'): ?>
                    <h2>Forgot Your Password?</h2>
                    <p class="text-muted">
                        Enter your registered email address or phone number and we will
                        help you recover your account.
                    </p>

                    <?php foreach ($errors as $err): ?>
                        <div class="alert alert-danger"><?= e($err) ?></div>
                    <?php endforeach; ?>
                    <?php if ($notice !== ''): ?>
                        <div class="alert alert-info"><?= e($notice) ?></div>
                    <?php endif; ?>

                    <form method="post" class="needs-validation" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="identify">

                        <div class="mb-3">
                            <label class="form-label">Email or Phone Number</label>
                            <input
                                type="text"
                                name="identifier"
                                class="form-control"
                                placeholder="customer@gmail.com or 09xxxxxxxxx"
                                value="<?= e($_POST['identifier'] ?? '') ?>"
                                required
                            >
                        </div>

                        <button class="btn btn-primary w-100">Continue</button>
                    </form>

                    <p class="mt-3 mb-0">
                        <a href="<?= url('login.php') ?>">&larr; Back to login</a>
                    </p>

                <?php else: ?>
                    <?php $methods = $flow['methods']; ?>
                    <h2>Choose Verification Method</h2>
                    <p class="text-muted">
                        How would you like to receive your verification code?
                        Only one code is needed to reset your password.
                    </p>

                    <?php foreach ($errors as $err): ?>
                        <div class="alert alert-danger"><?= e($err) ?></div>
                    <?php endforeach; ?>

                    <?php if (!empty($methods['phone'])): ?>
                        <form method="post" class="card mb-3">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="send">
                            <input type="hidden" name="method" value="phone">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><i class="bi bi-phone me-1"></i>Phone Number</strong><br>
                                    <span class="text-muted"><?= e(mask_phone((string) $methods['phone'])) ?></span>
                                </div>
                                <button class="btn btn-primary">Use Phone</button>
                            </div>
                        </form>
                    <?php endif; ?>

                    <?php if (!empty($methods['email'])): ?>
                        <form method="post" class="card mb-3">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="send">
                            <input type="hidden" name="method" value="email">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><i class="bi bi-envelope me-1"></i>Email</strong><br>
                                    <span class="text-muted"><?= e(mask_email((string) $methods['email'])) ?></span>
                                </div>
                                <button class="btn btn-primary">Use Email</button>
                            </div>
                        </form>
                    <?php endif; ?>

                    <?php if (empty($methods['phone']) && empty($methods['email'])): ?>
                        <div class="alert alert-warning">
                            No available recovery method was found for this account. Please contact support.
                        </div>
                    <?php endif; ?>

                    <div class="mt-2">
                        <a href="<?= url('forgot-password.php') ?>">&larr; Use a different account</a>
                    </div>

                <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>