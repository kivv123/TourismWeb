<?php require '../includes/auth.php';
require_role('driver');
$did = get_entity_id('driver', (int)current_user()['user_id']);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)post('booking_id');
    $next = post('trip_status');
    $allowed = ['Accepted', 'On the Way', 'Picked Up', 'Completed', 'Cancelled'];
    $s = db()->prepare('SELECT trip_status FROM bookings WHERE booking_id=? AND driver_id=?');
    $s->execute([$id, $did]);
    $old = $s->fetchColumn();
    $trans = ['Assigned' => ['Accepted', 'Cancelled'], 'Accepted' => ['On the Way', 'Cancelled'], 'On the Way' => ['Picked Up', 'Cancelled'], 'Picked Up' => ['Completed']];
    if (!$old || !isset($trans[$old]) || !in_array($next, $trans[$old], true)) $error = 'Invalid trip status change.';
    else {
        db()->prepare('UPDATE bookings SET trip_status=?, booking_status=IF(?=\'Completed\',\'Completed\',booking_status) WHERE booking_id=? AND driver_id=?')->execute([$next, $next, $id, $did]);
        flash('success', 'Trip status updated.');
        redirect('driver/trips.php');
    }
}
$s = db()->prepare('SELECT b.*,p.package_name,u.name customer_name,c.phone FROM bookings b JOIN packages p ON p.package_id=b.package_id JOIN customers c ON c.customer_id=b.customer_id JOIN users u ON u.user_id=c.user_id WHERE b.driver_id=? ORDER BY b.start_date');
$s->execute([$did]);
$rows = $s->fetchAll();
$pageTitle = 'My trips';
require '../includes/header.php'; ?><div class="container py-5">
    <h2>Assigned trips</h2><?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?><div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Package</th>
                        <th>Dates</th>
                        <th>Pickup</th>
                        <th>Status</th>
                        <th>Update</th>
                    </tr>
                </thead>
                <tbody><?php foreach ($rows as $b): ?><tr>
                            <td><?= e($b['customer_name']) ?><br><small><?= e($b['phone']) ?></small></td>
                            <td><?= e($b['package_name']) ?></td>
                            <td><?= e($b['start_date']) ?> → <?= e($b['end_date']) ?></td>
                            <td><?= e($b['pickup_location'] ?: '—') ?></td>
                            <td><?= status_badge($b['trip_status']) ?></td>
                            <td><?php if (in_array($b['trip_status'], ['Assigned', 'Accepted', 'On the Way', 'Picked Up'])): ?><form method="post" class="d-flex gap-1"><?= csrf_field() ?><input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>"><select name="trip_status" class="form-select form-select-sm"><?php foreach (['Accepted', 'On the Way', 'Picked Up', 'Completed', 'Cancelled'] as $x): ?><option><?= $x ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-primary">Save</button></form><?php endif; ?></td>
                        </tr><?php endforeach; ?></tbody>
            </table>
        </div>
    </div>
</div><?php require '../includes/footer.php'; ?>