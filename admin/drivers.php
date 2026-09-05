<?php require '../includes/auth.php';
require_role('admin');
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('action');
    $id = (int)post('driver_id');
    if ($action === 'status') {
        db()->prepare('UPDATE drivers SET status=? WHERE driver_id=?')->execute([post('status'), $id]);
        flash('success', 'Driver status updated.');
        redirect('admin/drivers.php');
    }
    if ($action === 'create') {
        $name = post('name');
        $email = strtolower(post('email'));
        $pass = $_POST['password'] ?? '';
        $phone = post('phone');
        $license = post('license_number');
        $vehicle = post('vehicle_type');
        $reg = post('vehicle_registration');
        $capacity = (int)post('vehicle_capacity');
        if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8 || !$phone || !$license || !$vehicle || !$reg || $capacity < 1) $errors[] = 'Complete valid driver details; password must be 8+ characters.';
        else try {
            $pdo = db();
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO users(name,email,password_hash,role,status) VALUES(?,?,?,'driver','active')")->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
            $uid = $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO drivers(user_id,full_name,phone,license_number,vehicle_type,vehicle_registration,vehicle_capacity,availability_status,status) VALUES(?,?,?,?,?,?,?,'Offline','active')")->execute([$uid, $name, $phone, $license, $vehicle, $reg, $capacity]);
            $pdo->commit();
            flash('success', 'Driver account created.');
            redirect('admin/drivers.php');
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            $errors[] = 'Unable to create driver; email or license may already exist.';
        }
    }
}
$rows = db()->query('SELECT * FROM drivers ORDER BY created_at DESC')->fetchAll();
$pageTitle = 'Driver management';
require '../includes/header.php'; ?><div class="container py-5">
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card card-body">
                <h4>Create driver account</h4><?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= e($e) ?></div><?php endforeach; ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create"><?php foreach (['name' => 'Full name', 'email' => 'Email', 'password' => 'Temporary password', 'phone' => 'Phone', 'license_number' => 'License number', 'vehicle_type' => 'Vehicle type', 'vehicle_registration' => 'Registration', 'vehicle_capacity' => 'Capacity'] as $f => $l): ?><input class="form-control mb-2" name="<?= $f ?>" placeholder="<?= $l ?>" <?= $f === 'password' ? 'type="password"' : '' ?> required><?php endforeach; ?><button class="btn btn-primary">Create driver</button></form>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Driver</th>
                                <th>Vehicle</th>
                                <th>Availability</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody><?php foreach ($rows as $r): ?><tr>
                                    <td><?= e($r['full_name']) ?><br><small><?= e($r['phone']) ?></small></td>
                                    <td><?= e($r['vehicle_type']) ?><br><small><?= e($r['vehicle_registration']) ?></small></td>
                                    <td><?= e($r['availability_status']) ?></td>
                                    <td><?= status_badge($r['status']) ?></td>
                                    <td>
                                        <form method="post" class="d-flex gap-1"><?= csrf_field() ?><input name="action" value="status" type="hidden"><input name="driver_id" value="<?= $r['driver_id'] ?>" type="hidden"><select class="form-select form-select-sm" name="status"><?php foreach (['pending', 'active', 'inactive', 'rejected'] as $x): ?><option <?= $r['status'] === $x ? 'selected' : '' ?>><?= $x ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-primary">Save</button></form>
                                    </td>
                                </tr><?php endforeach; ?></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div><?php require '../includes/footer.php'; ?>