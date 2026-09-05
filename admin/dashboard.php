<?php require '../includes/auth.php';
require_role('admin');
$queries = ['Customers' => "SELECT COUNT(*) FROM users WHERE role='customer'", 'Drivers' => "SELECT COUNT(*) FROM drivers WHERE status='active'", 'Hotels' => "SELECT COUNT(*) FROM hotels WHERE status='active'", 'Destinations' => 'SELECT COUNT(*) FROM destinations WHERE status=\'active\'', 'Packages' => 'SELECT COUNT(*) FROM packages WHERE status=\'active\'', 'Bookings' => 'SELECT COUNT(*) FROM bookings', 'Pending bookings' => "SELECT COUNT(*) FROM bookings WHERE booking_status='Pending'", 'Pending payments' => "SELECT COUNT(*) FROM payments WHERE payment_status='Pending'"];
$stats = [];
foreach ($queries as $k => $q) $stats[$k] = db()->query($q)->fetchColumn();
$recent = db()->query('SELECT b.*,u.name,p.package_name FROM bookings b JOIN customers c ON c.customer_id=b.customer_id JOIN users u ON u.user_id=c.user_id JOIN packages p ON p.package_id=b.package_id ORDER BY b.created_at DESC LIMIT 8')->fetchAll();
$pageTitle = 'Admin dashboard';
require '../includes/header.php'; ?><div class="container py-4">
    <h2>Admin dashboard</h2>
    <div class="row g-3 mb-4"><?php foreach ($stats as $k => $v): ?><div class="col-6 col-md-3">
                <div class="card stat-card card-body"><small><?= $k ?></small>
                    <h3><?= $v ?></h3>
                </div>
            </div><?php endforeach; ?></div>
    <div class="d-flex flex-wrap gap-2 mb-4"><?php foreach (['destinations' => 'Destinations', 'packages' => 'Packages', 'hotels' => 'Hotels', 'drivers' => 'Drivers', 'bookings' => 'Bookings', 'payments' => 'Payments', 'messages' => 'Messages', 'customers' => 'Users'] as $f => $t): ?><a href="<?= $f ?>.php" class="btn btn-outline-primary btn-sm"><?= $t ?></a><?php endforeach; ?></div>
    <div class="card">
        <div class="card-header bg-white">
            <h5 class="mb-0">Recent bookings</h5>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Customer</th>
                        <th>Package</th>
                        <th>Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody><?php foreach ($recent as $b): ?><tr>
                            <td><?= $b['booking_id'] ?></td>
                            <td><?= e($b['name']) ?></td>
                            <td><?= e($b['package_name']) ?></td>
                            <td><?= money($b['total_amount']) ?></td>
                            <td><?= status_badge($b['booking_status']) ?></td>
                        </tr><?php endforeach; ?></tbody>
            </table>
        </div>
    </div>
</div><?php require '../includes/footer.php'; ?>