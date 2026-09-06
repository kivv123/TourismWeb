<?php require '../includes/auth.php';
require '../includes/mailer.php';
require_role('admin');

if (!function_exists('generate_temporary_password')) {
    function generate_temporary_password(): string
    {
        $sets = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnpqrstuvwxyz', '23456789', '!@#$%&*'];
        $all = implode('', $sets);
        $chars = [];
        foreach ($sets as $set) {
            $chars[] = $set[random_int(0, strlen($set) - 1)];
        }
        while (count($chars) < 12) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }
        shuffle($chars);
        return implode('', $chars);
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('action');
    $id = (int)post('hotel_id');
    if ($action === 'status') {
        db()->prepare('UPDATE hotels SET status=? WHERE hotel_id=?')->execute([post('status'), $id]);
        flash('success', 'Hotel status updated.');
        redirect('admin/hotels.php');
    }
    if ($action === 'create') {
        $name = post('hotel_name');
        $email = strtolower(post('email'));
        $pass = $_POST['password'] ?? '';
        $city = post('city');
        $address = post('address');
        $phone = post('phone');
        if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8 || !$city || !$address || !$phone) {
            $errors[] = 'Complete all details; password must be 8+ characters.';
        } else {
            $dup = db()->prepare('SELECT user_id FROM users WHERE email = ?');
            $dup->execute([$email]);
            if ($dup->fetch()) {
                $errors[] = 'Email already exists.';
            } else try {
                $pdo = db();
                $pdo->beginTransaction();
                $pdo->prepare("INSERT INTO users(name,email,password_hash,role,status,must_change_password) VALUES(?,?,?,'hotel','active',1)")
                    ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
                $uid = $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO hotels(user_id,hotel_name,address,city,phone,email,status) VALUES(?,?,?,?,?,?,'active')")
                    ->execute([$uid, $name, $address, $city, $phone, $email]);
                $pdo->commit();
                send_hotel_account_email($email, $name, $email, $pass);
                flash('success', 'Hotel created and credentials emailed.');
                redirect('admin/hotels.php');
            } catch (Throwable $e) {
                if (db()->inTransaction()) db()->rollBack();
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
    if ($action === 'resend') {
        $userId = (int)post('user_id');
        $stmt = db()->prepare("SELECT u.user_id, u.email, h.hotel_name FROM users u JOIN hotels h ON h.user_id = u.user_id WHERE u.user_id = ?");
        $stmt->execute([$userId]);
        if ($hotelUser = $stmt->fetch()) {
            $tempPass = generate_temporary_password();
            db()->prepare('UPDATE users SET password_hash = ?, must_change_password = 1 WHERE user_id = ?')
                ->execute([password_hash($tempPass, PASSWORD_DEFAULT), $hotelUser['user_id']]);
            send_hotel_account_email($hotelUser['email'], $hotelUser['hotel_name'], $hotelUser['email'], $tempPass);
            flash('success', 'New credentials emailed.');
        }
        redirect('admin/hotels.php');
    }
}
$rows = db()->query('SELECT h.*,u.status user_status,u.must_change_password FROM hotels h LEFT JOIN users u ON u.user_id=h.user_id ORDER BY h.created_at DESC')->fetchAll();
$pageTitle = 'Hotel Management';
require '../includes/header.php'; ?>

<div class="dashboard-layout">
    <aside class="dashboard-sidebar">
        <nav class="dashboard-menu">
            <a href="dashboard.php" class="dashboard-menu-item"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
            <a href="destinations.php" class="dashboard-menu-item"><i class="bi bi-geo-alt"></i><span>Destinations</span></a>
            <a href="packages.php" class="dashboard-menu-item"><i class="bi bi-map"></i><span>Packages</span></a>
            <a href="hotels.php" class="dashboard-menu-item active"><i class="bi bi-building"></i><span>Hotels</span></a>
            <a href="rooms.php" class="dashboard-menu-item"><i class="bi bi-door-open"></i><span>Rooms</span></a>
            <a href="drivers.php" class="dashboard-menu-item"><i class="bi bi-car-front"></i><span>Drivers</span></a>
            <a href="customers.php" class="dashboard-menu-item"><i class="bi bi-people"></i><span>Customers</span></a>
            <div class="dashboard-menu-divider"></div>
            <a href="payments.php" class="dashboard-menu-item"><i class="bi bi-wallet2"></i><span>Payments</span></a>
            <a href="messages.php" class="dashboard-menu-item"><i class="bi bi-envelope"></i><span>Messages</span></a>
        </nav>
    </aside>

    <main class="dashboard-content">
        <div class="mb-4">
            <h2 class="h3"><?= $pageTitle ?></h2>
            <p class="text-muted">Register and manage hotel partner accounts.</p>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card card-body shadow-sm">
                    <h5 class="mb-3">Create Hotel</h5>
                    <?php foreach ($errors as $e): ?><div class="alert alert-danger small"><?= e($e) ?></div><?php endforeach; ?>
                    <form method="post">
                        <?= csrf_field() ?><input type="hidden" name="action" value="create">
                        <?php foreach (['hotel_name' => 'Hotel Name', 'email' => 'Email', 'password' => 'Password', 'city' => 'City', 'address' => 'Address', 'phone' => 'Phone'] as $f => $l): ?>
                            <div class="mb-2">
                                <label class="form-label small fw-bold"><?= $l ?></label>
                                <input class="form-control form-control-sm" name="<?= $f ?>" type="<?= $f==='password'?'password':($f==='email'?'email':'text') ?>" required>
                            </div>
                        <?php endforeach; ?>
                        <button class="btn btn-primary btn-sm w-100 mt-2">Create Account</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th class="ps-3">Hotel</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th class="text-end pe-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-bold"><?= e($r['hotel_name']) ?></div>
                                        <div class="small text-muted"><?= e($r['email']) ?></div>
                                    </td>
                                    <td class="small"><?= e($r['city']) ?></td>
                                    <td><?= status_badge($r['status']) ?></td>
                                    <td class="text-end pe-3">
                                        <form method="post" class="d-inline-flex gap-1">
                                            <?= csrf_field() ?><input name="action" value="status" type="hidden"><input name="hotel_id" value="<?= $r['hotel_id'] ?>" type="hidden">
                                            <select class="form-select form-select-sm" name="status" style="width:90px">
                                                <?php foreach(['pending','active','inactive'] as $s): ?><option <?= $r['status']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
                                            </select>
                                            <button class="btn btn-sm btn-primary">Ok</button>
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