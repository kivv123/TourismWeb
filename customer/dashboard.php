<?php require '../includes/auth.php';
require_role('customer');
$cid = get_entity_id('customer', (int)current_user()['user_id']);
$s = db()->prepare("SELECT booking_status,COUNT(*) c FROM bookings WHERE customer_id=? GROUP BY booking_status");
$s->execute([$cid]);
$counts = array_column($s->fetchAll(), 'c', 'booking_status');
$s = db()->prepare('SELECT b.*,p.package_name FROM bookings b JOIN packages p ON p.package_id=b.package_id WHERE b.customer_id=? ORDER BY b.created_at DESC LIMIT 5');
$s->execute([$cid]);
$recent = $s->fetchAll();
$pageTitle = 'Customer dashboard';
require '../includes/header.php'; ?><div class="container py-4">
    <h2>Welcome, <?= e(current_user()['name']) ?></h2>
    <p class="text-muted">Plan, follow and manage your journeys.</p>
    <div class="row g-3 mb-4"><?php foreach (['Total' => array_sum($counts), 'Pending' => $counts['Pending'] ?? 0, 'Confirmed' => $counts['Confirmed'] ?? 0, 'Completed' => $counts['Completed'] ?? 0, 'Cancelled' => $counts['Cancelled'] ?? 0] as $k => $v): ?><div class="col-6 col-lg">
                <div class="card stat-card card-body"><small><?= $k ?></small>
                    <h3 class="mb-0"><?= $v ?></h3>
                </div>
            </div><?php endforeach; ?></div>
    <div class="d-flex gap-2 flex-wrap mb-4"><a class="btn btn-primary" href="<?= url('destinations.php') ?>">Explore destinations</a><a class="btn btn-outline-primary" href="<?= url('packages.php') ?>">Explore packages</a><a class="btn btn-outline-secondary" href="profile.php">My profile</a></div>
    <div class="card">
        <div class="card-header bg-white">
            <h5 class="mb-0">Recent bookings</h5>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Package</th>
                        <th>Dates</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody><?php foreach ($recent as $b): ?><tr>
                            <td><?= e($b['package_name']) ?></td>
                            <td><?= e($b['start_date']) ?> → <?= e($b['end_date']) ?></td>
                            <td><?= money($b['total_amount']) ?></td>
                            <td><?= status_badge($b['booking_status']) ?></td>
                            <td><a href="booking-details.php?id=<?= $b['booking_id'] ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                        </tr><?php endforeach;
                            if (!$recent): ?><tr>
                            <td colspan="5" class="text-center text-muted py-4">No bookings yet. <a href="<?= url('packages.php') ?>">Find a package</a>.</td>
                        </tr><?php endif; ?></tbody>
            </table>
        </div>
    </div>
</div><?php require '../includes/footer.php'; ?>