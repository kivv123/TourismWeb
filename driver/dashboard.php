<?php require '../includes/auth.php';
require_role('driver');
$did = get_entity_id('driver', (int)current_user()['user_id']);
$s = db()->prepare('SELECT availability_status FROM drivers WHERE driver_id=?');
$s->execute([$did]);
$availability = $s->fetchColumn();
$s = db()->prepare("SELECT trip_status,COUNT(*) c FROM bookings WHERE driver_id=? GROUP BY trip_status");
$s->execute([$did]);
$counts = array_column($s->fetchAll(), 'c', 'trip_status');
$pageTitle = 'Driver dashboard';
require '../includes/header.php'; ?><div class="container py-4">
    <h2>Driver dashboard</h2>
    <p>Availability: <?= status_badge($availability) ?></p>
    <div class="row g-3 mb-4"><?php foreach (['Assigned' => $counts['Assigned'] ?? 0, 'Accepted' => $counts['Accepted'] ?? 0, 'On the Way' => $counts['On the Way'] ?? 0, 'Completed' => $counts['Completed'] ?? 0] as $k => $v): ?><div class="col-6 col-md-3">
                <div class="card stat-card card-body"><small><?= $k ?></small>
                    <h3><?= $v ?></h3>
                </div>
            </div><?php endforeach; ?></div><a class="btn btn-primary" href="trips.php">View assigned trips</a> <a class="btn btn-outline-primary" href="profile.php">Update profile & availability</a>
</div><?php require '../includes/footer.php'; ?>