<?php require 'includes/auth.php';
if (logged_in()) redirect(dashboard_path(current_user()['role']));
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = post('name');
    $email = strtolower(post('email'));
    $phone = post('phone');
    $address = post('address');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if (mb_strlen($name) < 2) $errors[] = 'Enter your full name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (mb_strlen($phone) < 5) $errors[] = 'Enter a valid phone number.';
    if (strlen($password) < 8) $errors[] = 'Password must have at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';
    if (!$errors) {
        try {
            $pdo = db();
            $pdo->beginTransaction();
            $s = $pdo->prepare("INSERT INTO users(name,email,password_hash,role,status) VALUES(?,?,?,'customer','active')");
            $s->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $s = $pdo->prepare('INSERT INTO customers(user_id,phone,address) VALUES(?,?,?)');
            $s->execute([(int)$pdo->lastInsertId(), $phone, $address]);
            $pdo->commit();
            flash('success', 'Your account is ready. Please sign in.');
            redirect('login.php');
        } catch (PDOException $e) {
            if (db()->inTransaction()) db()->rollBack();
            $errors[] = 'This email address is already registered.';
        }
    }
}
$pageTitle = 'Create account';
require 'includes/header.php'; ?><div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body p-4 p-md-5">
                    <h2>Create your traveler account</h2>
                    <p class="text-muted">Register to make and manage bookings.</p><?php foreach ($errors as $x): ?><div class="alert alert-danger"><?= e($x) ?></div><?php endforeach; ?><form method="post" class="needs-validation" novalidate><?= csrf_field() ?><div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Full name</label><input name="name" class="form-control" required value="<?= e($_POST['name'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required value="<?= e($_POST['email'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" required value="<?= e($_POST['phone'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">Address</label><input name="address" class="form-control" required value="<?= e($_POST['address'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">Password</label><input name="password" type="password" minlength="8" class="form-control" required></div>
                            <div class="col-md-6"><label class="form-label">Confirm password</label><input name="confirm_password" type="password" class="form-control" required></div>
                            <div class="col-12 d-grid"><button class="btn btn-primary">Create account</button></div>
                        </div>
                    </form>
                    <p class="mt-3 mb-0">Already registered? <a href="<?= url('login.php') ?>">Log in</a></p>
                </div>
            </div>
        </div>
    </div>
</div><?php require 'includes/footer.php'; ?>