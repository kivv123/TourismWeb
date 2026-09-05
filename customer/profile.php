<?php require '../includes/auth.php';
require_role('customer');
$cid = get_entity_id('customer', (int)current_user()['user_id']);
$s = db()->prepare('SELECT u.name,u.email,c.phone,c.address FROM users u JOIN customers c ON c.user_id=u.user_id WHERE c.customer_id=?');
$s->execute([$cid]);
$me = $s->fetch();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = post('name');
    $phone = post('phone');
    $address = post('address');
    $email = strtolower(post('email'));
    if (!$name || !$phone || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Provide a name, valid email and phone.';
    if (!$errors) {
        try {
            $pdo = db();
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE users SET name=?,email=? WHERE user_id=?')->execute([$name, $email, current_user()['user_id']]);
            $pdo->prepare('UPDATE customers SET phone=?,address=? WHERE customer_id=?')->execute([$phone, $address, $cid]);
            $pdo->commit();
            refresh_session_user((int)current_user()['user_id']);
            flash('success', 'Profile updated.');
            redirect('customer/profile.php');
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            $errors[] = 'Email is already used by another account.';
        }
    }
}
$pageTitle = 'My profile';
require '../includes/header.php'; ?><div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card card-body p-4">
                <h2>My profile</h2><?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= e($e) ?></div><?php endforeach; ?><form method="post"><?= csrf_field() ?><div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Name</label><input name="name" class="form-control" required value="<?= e($me['name']) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required value="<?= e($me['email']) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" required value="<?= e($me['phone']) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Address</label><input name="address" class="form-control" value="<?= e($me['address']) ?>"></div>
                        <div><button class="btn btn-primary">Save profile</button></div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div><?php require '../includes/footer.php'; ?>