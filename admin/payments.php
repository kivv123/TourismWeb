<?php

require '../includes/auth.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) post('payment_id');
    $status = post('payment_status');

    if (in_array($status, ['Pending', 'Paid', 'Failed', 'Refunded'], true)) {
        $pdo = db();
        $s = $pdo->prepare('SELECT booking_id FROM payments WHERE payment_id = ?');
        $s->execute([$id]);
        $bid = $s->fetchColumn();

        if ($bid) {
            $pdo->prepare(
                "UPDATE payments 
                 SET payment_status = ?, 
                     paid_at = IF(? = 'Paid', NOW(), paid_at) 
                 WHERE payment_id = ?"
            )->execute([$status, $status, $id]);

            if ($status === 'Paid') {
                $pdo->prepare("UPDATE bookings SET booking_status = 'Confirmed' WHERE booking_id = ?")->execute([$bid]);
            } elseif (in_array($status, ['Failed', 'Refunded'], true)) {
                $pdo->prepare("UPDATE bookings SET booking_status = 'Pending' WHERE booking_id = ? AND booking_status = 'Confirmed'")->execute([$bid]);
            }
            flash('success', 'Payment updated.');
        } else {
            flash('error', 'Payment record not found.');
        }
    }
    redirect('admin/payments.php');
}

$rows = db()->query(
    'SELECT py.*, b.total_amount, b.booking_status, u.name AS customer_name, p.package_name
     FROM payments py
     JOIN bookings b ON b.booking_id = py.booking_id
     JOIN customers c ON c.customer_id = b.customer_id
     JOIN users u ON u.user_id = c.user_id
     JOIN packages p ON p.package_id = b.package_id
     ORDER BY py.created_at DESC'
)->fetchAll();

$pageTitle = 'Payment verification';
require '../includes/header.php';
?>

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
            <a href="drivers.php" class="dashboard-menu-item">
                <i class="bi bi-car-front"></i>
                <span>Drivers</span>
            </a>
            <a href="customers.php" class="dashboard-menu-item">
                <i class="bi bi-people"></i>
                <span>Customers</span>
            </a>
            <div class="dashboard-menu-divider"></div>
            <a href="payments.php" class="dashboard-menu-item active">
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
            <h2 class="mb-1">Payment Verification</h2>
            <p class="text-muted">Review transaction references and confirm customer payments.</p>
        </div>

        <div class="card shadow-sm">
            <div class="table-responsive" style="max-height: 600px;">
                <table class="table align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-3">Booking</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Method/Ref</th>
                            <th>Payment</th>
                            <th>Booking</th>
                            <th class="text-end pe-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold text-dark">#<?= e($r['booking_id']) ?></div>
                                    <div class="small text-muted"><?= e($r['package_name']) ?></div>
                                </td>
                                <td><?= e($r['customer_name']) ?></td>
                                <td class="fw-bold text-primary"><?= money($r['amount']) ?></td>
                                <td>
                                    <div class="small fw-semibold"><?= e($r['payment_method']) ?></div>
                                    <div class="small text-muted text-truncate" style="max-width: 120px;">
                                        <?= e($r['transaction_reference'] ?: '—') ?>
                                    </div>
                                </td>
                                <td><?= status_badge($r['payment_status']) ?></td>
                                <td><?= status_badge($r['booking_status']) ?></td>
                                <td class="text-end pe-3">
                                    <form method="post" class="d-inline-flex gap-1">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="payment_id" value="<?= e($r['payment_id']) ?>">
                                        <select name="payment_status" class="form-select form-select-sm" style="width: 110px;">
                                            <?php foreach (['Pending', 'Paid', 'Failed', 'Refunded'] as $x): ?>
                                                <option value="<?= e($x) ?>" <?= ($r['payment_status'] === $x) ? 'selected' : '' ?>>
                                                    <?= e($x) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$rows): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-wallet2 fs-1 d-block mb-2 opacity-25"></i>
                                    No payment records found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php require '../includes/footer.php'; ?>