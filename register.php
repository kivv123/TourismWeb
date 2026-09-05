<?php
require 'includes/auth.php';
require 'includes/mailer.php'; 

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

    // Validation (Existing validation logic stays here...)
    // ...

    if (!$errors) {
        // Check if email already exists in DB
        $stmt = db()->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'This email address is already registered.';
        } else {
            // Generate code and save registration data to session
            $verificationCode = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            
            $_SESSION['temp_registration'] = [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'address' => $address,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'code' => $verificationCode,
                'expires' => time() + 600 // 10 minutes expiry
            ];

            if (send_verification_email($email, $verificationCode)) {
                redirect('verify.php'); // Redirect to code entry page
            } else {
                $errors[] = 'Failed to send verification email. Please try again later.';
            }
        }
    }
}
$pageTitle = 'Create Account';
require 'includes/header.php'; // <--- THIS LOADS YOUR STYLES
?>
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