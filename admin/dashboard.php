<?php
require '../includes/auth.php';

require_role('admin');

$queries = [
    'Customers' => "SELECT COUNT(*) FROM users WHERE role = 'customer'",
    'Drivers' => "SELECT COUNT(*) FROM drivers WHERE status = 'active'",
    'Hotels' => "SELECT COUNT(*) FROM hotels WHERE status = 'active'",
    'Destinations' => "SELECT COUNT(*) FROM destinations WHERE status = 'active'",
    'Packages' => "SELECT COUNT(*) FROM packages WHERE status = 'active'",
    'Bookings' => "SELECT COUNT(*) FROM bookings",
    'Pending Bookings' => "SELECT COUNT(*) FROM bookings WHERE booking_status = 'Pending'",
    'Pending Payments' => "SELECT COUNT(*) FROM payments WHERE payment_status = 'Pending'"
];

$stats = [];

foreach ($queries as $label => $query) {
    $stats[$label] = db()->query($query)->fetchColumn();
}

$recent = db()->query(
    'SELECT
        b.*,
        u.name,
        p.package_name
     FROM bookings b
     JOIN customers c ON c.customer_id = b.customer_id
     JOIN users u ON u.user_id = c.user_id
     JOIN packages p ON p.package_id = b.package_id
     ORDER BY b.created_at DESC
     LIMIT 8'
)->fetchAll();

$pageTitle = 'Admin Dashboard';

require '../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-1">Admin Dashboard</h2>
            <p class="text-muted mb-0">
                Use the <strong>Manage</strong> menu in the navbar to manage the system.
            </p>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <?php foreach ($stats as $label => $value): ?>
            <div class="col-6 col-md-3">
                <div class="card stat-card card-body">
                    <small><?= e($label) ?></small>
                    <h3 class="mb-0"><?= e((string) $value) ?></h3>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <div class="card-header bg-white">
            <h5 class="mb-0">Recent Bookings</h5>
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

                <tbody>
                    <?php foreach ($recent as $booking): ?>
                        <tr>
                            <td><?= e((string) $booking['booking_id']) ?></td>
                            <td><?= e($booking['name']) ?></td>
                            <td><?= e($booking['package_name']) ?></td>
                            <td><?= money($booking['total_amount']) ?></td>
                            <td><?= status_badge($booking['booking_status']) ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$recent): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                No bookings found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>