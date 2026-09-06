<?php require '../includes/auth.php';
require '../includes/mailer.php';
require_role('admin');

if (!function_exists('generate_temporary_password')) {
    /**
     * 12-character temporary password for the "resend credentials" action.
     * Guarantees upper + lower + digit + symbol and skips visually
     * ambiguous characters (0/O, 1/l/I). Satisfies the 8+ character rule.
     */
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
            $errors[] = 'Complete valid hotel account details; password must be 8+ characters.';
        } else {
            // Explicit duplicate check: existing accounts are never
            // overwritten and never receive a second credentials email.
            $dup = db()->prepare('SELECT user_id FROM users WHERE email = ?');
            $dup->execute([$email]);
            if ($dup->fetch()) {
                $errors[] = 'Unable to create hotel; email may already exist.';
            } else try {
                $pdo = db();
                $pdo->beginTransaction();
                // must_change_password=1 marks the emailed password as temporary.
                $pdo->prepare("INSERT INTO users(name,email,password_hash,role,status,must_change_password) VALUES(?,?,?,'hotel','active',1)")
                    ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
                $uid = $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO hotels(user_id,hotel_name,address,city,phone,email,status) VALUES(?,?,?,?,?,?,'active')")
                    ->execute([$uid, $name, $address, $city, $phone, $email]);
                $pdo->commit();

                // The account now exists; an email failure must never roll
                // it back. Failures are logged server-side (logs/mail.log).
                if (send_hotel_account_email($email, $name, $email, $pass)) {
                    flash('success', 'Hotel account created. Login credentials were emailed to ' . $email . '.');
                } else {
                    flash('warning', 'Hotel account created successfully, but the email could not be sent. Use "Resend credentials" in the hotel list to try again.');
                }
                redirect('admin/hotels.php');
            } catch (Throwable $e) {
                if (db()->inTransaction()) db()->rollBack();
                $errors[] = 'Unable to create hotel; email may already exist.';
            }
        }
    }
    if ($action === 'resend') {
        // Generates a NEW temporary password, stores only its hash and
        // emails it. The plain password is never shown in the admin UI
        // and never stored in the database.
        $userId = (int)post('user_id');
        $stmt = db()->prepare("SELECT u.user_id, u.email, h.hotel_name FROM users u JOIN hotels h ON h.user_id = u.user_id WHERE u.user_id = ? AND u.role = 'hotel'");
        $stmt->execute([$userId]);
        if ($hotelUser = $stmt->fetch()) {
            $tempPass = generate_temporary_password();
            db()->prepare('UPDATE users SET password_hash = ?, must_change_password = 1 WHERE user_id = ?')
                ->execute([password_hash($tempPass, PASSWORD_DEFAULT), $hotelUser['user_id']]);
            if (send_hotel_account_email($hotelUser['email'], $hotelUser['hotel_name'], $hotelUser['email'], $tempPass)) {
                flash('success', 'A new temporary password was emailed to ' . $hotelUser['email'] . '.');
            } else {
                flash('warning', 'A new temporary password was generated for ' . $hotelUser['email'] . ', but the email could not be sent. Check logs/mail.log and try again.');
            }
        } else {
            flash('error', 'No hotel account was found for this entry.');
        }
        redirect('admin/hotels.php');
    }
}
 $rows = db()->query('SELECT h.*,u.status user_status,u.must_change_password FROM hotels h LEFT JOIN users u ON u.user_id=h.user_id ORDER BY h.created_at DESC')->fetchAll();
 $pageTitle = 'Hotel management';
require '../includes/header.php'; ?><div class="container py-5">
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card card-body">
                <h4>Create hotel account</h4><?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= e($e) ?></div><?php endforeach; ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create"><?php foreach (['hotel_name' => 'Hotel name', 'email' => 'Email', 'password' => 'Temporary password', 'city' => 'City', 'address' => 'Address', 'phone' => 'Phone'] as $f => $l): ?><input class="form-control mb-2" name="<?= $f ?>" placeholder="<?= $l ?>" <?= $f === 'password' ? 'type="password"' : '' ?> required><?php endforeach; ?><button class="btn btn-primary">Create hotel</button><div class="form-text mt-2">The hotel receives these login credentials by email and must change the password at first login.</div></form>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Hotel</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody><?php foreach ($rows as $r): ?><tr>
                                    <td><?= e($r['hotel_name']) ?><?= !empty($r['must_change_password']) ? ' <span class="badge bg-warning text-dark" title="The temporary password has not been changed yet.">temp password</span>' : '' ?><br><small><?= e($r['email']) ?></small></td>
                                    <td><?= e($r['city']) ?></td>
                                    <td><?= status_badge($r['status']) ?></td>
                                    <td>
                                        <form method="post" class="d-flex gap-1"><?= csrf_field() ?><input name="action" value="status" type="hidden"><input name="hotel_id" value="<?= $r['hotel_id'] ?>" type="hidden"><select class="form-select form-select-sm" name="status"><?php foreach (['pending', 'active', 'inactive', 'rejected'] as $x): ?><option <?= $r['status'] === $x ? 'selected' : '' ?>><?= $x ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-primary">Save</button></form>
                                        <?php if (!empty($r['user_id'])): ?><form method="post" class="mt-1"><?= csrf_field() ?><input type="hidden" name="action" value="resend"><input type="hidden" name="user_id" value="<?= (int)$r['user_id'] ?>"><button class="btn btn-sm btn-outline-secondary" onclick="return confirm('Email a new temporary password to this hotel?')">Resend credentials</button></form><?php endif; ?>
                                    </td>
                                </tr><?php endforeach; ?></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div><?php require '../includes/footer.php'; ?>