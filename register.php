<?php
require 'includes/auth.php';

if (logged_in()) {
    redirect(dashboard_path(current_user()['role']));
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $name = post('name');
    $email = strtolower(post('email'));
    $phone = post('phone');
    $address = post('address');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (mb_strlen($name) < 2) {
        $errors[] = 'Please enter your full name.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (mb_strlen($phone) < 5) {
        $errors[] = 'Please enter a valid phone number.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must contain at least 8 characters.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        try {
            $pdo = db();
            $pdo->beginTransaction();

            /*
             * Public registration is always customer-only.
             * No browser-supplied role is read or trusted.
             */
            $stmt = $pdo->prepare(
                "INSERT INTO users (name, email, password_hash, role, status)
                 VALUES (?, ?, ?, 'customer', 'active')"
            );

            $stmt->execute([
                $name,
                $email,
                password_hash($password, PASSWORD_DEFAULT)
            ]);

            $userId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare(
                'INSERT INTO customers (user_id, phone, address)
                 VALUES (?, ?, ?)'
            );

            $stmt->execute([$userId, $phone, $address]);

            $pdo->commit();

            flash('success', 'Your customer account is ready. Please log in.');
            redirect('login.php');
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }

            $errors[] = 'Registration failed. This email address may already be registered.';
        }
    }
}

$pageTitle = 'Create Account';
require 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body p-4 p-md-5">
                    <h2>Create Your Traveler Account</h2>
                    <p class="text-muted">
                        Register to discover destinations and manage travel bookings.
                    </p>

                    <?php foreach ($errors as $error): ?>
                        <div class="alert alert-danger"><?= e($error) ?></div>
                    <?php endforeach; ?>

                    <form method="post" class="needs-validation" novalidate>
                        <?= csrf_field() ?>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    name="name"
                                    required
                                    value="<?= e($_POST['name'] ?? '') ?>"
                                >
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input
                                    type="email"
                                    class="form-control"
                                    name="email"
                                    required
                                    value="<?= e($_POST['email'] ?? '') ?>"
                                >
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    name="phone"
                                    required
                                    value="<?= e($_POST['phone'] ?? '') ?>"
                                >
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Address</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    name="address"
                                    value="<?= e($_POST['address'] ?? '') ?>"
                                >
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Password</label>
                                <input
                                    type="password"
                                    class="form-control"
                                    name="password"
                                    minlength="8"
                                    required
                                >
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Confirm Password</label>
                                <input
                                    type="password"
                                    class="form-control"
                                    name="confirm_password"
                                    minlength="8"
                                    required
                                >
                            </div>

                            <div class="col-12">
                                <button class="btn btn-primary w-100">
                                    Create Customer Account
                                </button>
                            </div>
                        </div>
                    </form>

                    <p class="mt-3 mb-0">
                        Already registered?
                        <a href="<?= url('login.php') ?>">Log in</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>