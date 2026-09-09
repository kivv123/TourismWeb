<?php
require 'includes/auth.php';

if (logged_in()) {
    redirect(dashboard_path(current_user()['role']));
}

 $error = '';
 $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = strtolower(post('email'));
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare(
        'SELECT user_id, name, email, password_hash, role, status, must_change_password
         FROM users
         WHERE email = ?'
    );

    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (
        $user &&
        $user['status'] === 'active' &&
        password_verify($password, $user['password_hash'])
    ) {
        session_regenerate_id(true);

        unset($user['password_hash']);

        $_SESSION['user'] = $user;

        // First login with admin-issued temporary credentials:
        // force the hotel to set its own password before anything else.
        if (!empty($user['must_change_password']) && $user['role'] === 'hotel') {
            redirect('hotel/change-password.php');
        }

        // Role comes from MySQL, never from the login form.
        redirect(dashboard_path($user['role']));
    }

    $error = 'Invalid email/password, or this account is not active.';
}

 $pageTitle = 'Login';
require 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card">
                <div class="card-body p-4 p-md-5">
                    <h2>Welcome Back</h2>
                    <p class="text-muted">
                        Log in to your Myanmar Horizons account.
                    </p>

                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <?= e($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" class="needs-validation" novalidate>
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input
                                type="email"
                                name="email"
                                id="email"
                                class="form-control"
                                value="<?= e($email) ?>"
                                required
                            >
                        </div>

                        <div
                            id="admin-message"
                            class="alert alert-info py-2 d-none"
                        >
                            <i class="bi bi-shield-lock me-1"></i>
                            Admin account detected. Enter your admin password.
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input
                                type="password"
                                name="password"
                                class="form-control"
                                required
                            >
                        </div>

                        <button class="btn btn-primary w-100">
                            Log In
                        </button>
                    </form>

                    <p class="mt-3 mb-2">
                        New user?
                        <a href="<?= url('register.php') ?>">
                            Create an account
                        </a>
                    </p>

                    <p class="mb-0">
                        <a href="<?= url('forgot-password.php') ?>" class="small">
                            Forgot Password?
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const emailInput = document.getElementById('email');
const adminMessage = document.getElementById('admin-message');

function showAdminMessage() {
    const isAdminEmail =
        emailInput.value.trim().toLowerCase() === 'admin@tourism.local';

    adminMessage.classList.toggle('d-none', !isAdminEmail);
}

emailInput.addEventListener('input', showAdminMessage);
showAdminMessage();
</script>

<?php require 'includes/footer.php'; ?>