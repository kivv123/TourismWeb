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
$pageTitle = 'Driver Management';
require '../includes/header.php'; ?>

<div class="dashboard-layout">
    <!-- Admin Sidebar -->
    <aside class="dashboard-sidebar">
        <nav class="dashboard-menu">
            <a href="dashboard.php" class="dashboard-menu-item">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
            <a href="destinations.php" class="dashboard-menu-item">
                <i class="bi bi-geo-alt"></i>
                <span>Destinations</span>
            </a>
            <a href="packages.php" class="dashboard-menu-item">
                <i class="bi bi-map"></i>
                <span>Packages</span>
            </a>
            <a href="hotels.php" class="dashboard-menu-item">
                <i class="bi bi-building"></i>
                <span>Hotels</span>
            </a>
            <a href="rooms.php" class="dashboard-menu-item">
                <i class="bi bi-door-open"></i>
                <span>Rooms</span>
            </a>
            <a href="drivers.php" class="dashboard-menu-item active">
                <i class="bi bi-car-front"></i>
                <span>Drivers</span>
            </a>
            <a href="customers.php" class="dashboard-menu-item">
                <i class="bi bi-people"></i>
                <span>Customers</span>
            </a>
            <div class="dashboard-menu-divider"></div>
            <a href="payments.php" class="dashboard-menu-item">
                <i class="bi bi-wallet2"></i>
                <span>Payments</span>
            </a>
            <a href="messages.php" class="dashboard-menu-item">
                <i class="bi bi-envelope"></i>
                <span>Messages</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="dashboard-content">
        <div class="mb-4">
            <h2 class="mb-1"><?= $pageTitle ?></h2>
            <p class="text-muted">Register and oversee driver accounts and vehicle logistics.</p>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card card-body shadow-sm">
                    <h5 class="card-title mb-4">Create Driver Account</h5>
                    
                    <?php foreach ($errors as $e): ?>
                        <div class="alert alert-danger py-2"><?= e($e) ?></div>
                    <?php endforeach; ?>

                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="create">
                        
                        <?php 
                        $fields = [
                            'name' => 'Full Name', 
                            'email' => 'Email', 
                            'password' => 'Temp Password', 
                            'phone' => 'Phone', 
                            'license_number' => 'License No.', 
                            'vehicle_type' => 'Vehicle Type', 
                            'vehicle_registration' => 'Registration', 
                            'vehicle_capacity' => 'Capacity (Pax)'
                        ];
                        foreach ($fields as $f => $l): 
                        ?>
                            <div class="mb-2">
                                <label class="form-label small fw-bold"><?= $l ?></label>
                                <input class="form-control form-control-sm" name="<?= $f ?>" 
                                       type="<?= $f === 'password' ? 'password' : ($f === 'vehicle_capacity' ? 'number' : 'text') ?>" 
                                       required>
                            </div>
                        <?php endforeach; ?>

                        <button class="btn btn-primary w-100 py-2 mt-3">
                            <i class="bi bi-person-plus me-2"></i>Create Driver
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th class="ps-3">Driver</th>
                                    <th>Vehicle</th>
                                    <th>Live Status</th>
                                    <th>Account</th>
                                    <th class="text-end pe-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-bold text-dark"><?= e($r['full_name']) ?></div>
                                        <div class="small text-muted"><?= e($r['phone']) ?></div>
                                    </td>
                                    <td>
                                        <div class="small fw-semibold"><?= e($r['vehicle_type']) ?></div>
                                        <div class="small text-muted"><?= e($r['vehicle_registration']) ?> (<?= $r['vehicle_capacity'] ?> pax)</div>
                                    </td>
                                    <td>
                                        <?php if($r['availability_status'] === 'Online'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">Online</span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border">Offline</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= status_badge($r['status']) ?></td>
                                    <td class="text-end pe-3">
                                        <form method="post" class="d-inline-flex gap-1">
                                            <?= csrf_field() ?>
                                            <input name="action" value="status" type="hidden">
                                            <input name="driver_id" value="<?= $r['driver_id'] ?>" type="hidden">
                                            <select class="form-select form-select-sm" name="status" style="width: 100px;">
                                                <?php foreach (['pending', 'active', 'inactive', 'rejected'] as $x): ?>
                                                    <option <?= $r['status'] === $x ? 'selected' : '' ?> value="<?= $x ?>"><?= ucfirst($x) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button class="btn btn-sm btn-primary">Save</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require '../includes/footer.php'; ?>