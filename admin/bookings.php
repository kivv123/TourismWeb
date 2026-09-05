<?php require '../includes/auth.php';
require_role('admin');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)post('booking_id');
    $action = post('action');
    $pdo = db();
    if ($action === 'status') {
        $status = post('booking_status');
        if (in_array($status, ['Pending', 'Confirmed', 'Cancelled', 'Completed'], true)) {
            $pdo->prepare('UPDATE bookings SET booking_status=? WHERE booking_id=?')->execute([$status, $id]);
            flash('success', 'Booking status updated.');
        }
    }
    if ($action === 'driver') {
        $driver = (int)post('driver_id');
        $s = $pdo->prepare("SELECT * FROM drivers WHERE driver_id=? AND status='active' AND availability_status='Available'");
        $s->execute([$driver]);
        $d = $s->fetch();
        $s = $pdo->prepare('SELECT start_date,end_date FROM bookings WHERE booking_id=?');
        $s->execute([$id]);
        $b = $s->fetch();
        $overlap = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE driver_id=? AND trip_status NOT IN ('Completed','Cancelled','Unassigned') AND start_date < ? AND end_date > ?");
        $overlap->execute([$driver, $b['end_date'], $b['start_date']]);
        if (!$d || $overlap->fetchColumn()) $error = 'Driver is unavailable or has an overlapping trip.';
        else {
            $pdo->prepare("UPDATE bookings SET driver_id=?,trip_status='Assigned' WHERE booking_id=?")->execute([$driver, $id]);
            $pdo->prepare("UPDATE drivers SET availability_status='Busy' WHERE driver_id=?")->execute([$driver]);
            flash('success', 'Driver assigned.');
        }
    }
    if ($action === 'cancel') {
        $s = $pdo->prepare('SELECT room_id,number_of_rooms FROM bookings WHERE booking_id=?');
        $s->execute([$id]);
        $b = $s->fetch();
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE bookings SET booking_status='Cancelled',trip_status='Cancelled' WHERE booking_id=?")->execute([$id]);
        if ($b && $b['room_id']) $pdo->prepare('UPDATE rooms SET available_rooms=available_rooms+? WHERE room_id=?')->execute([$b['number_of_rooms'], $b['room_id']]);
        $pdo->commit();
        flash('success', 'Booking cancelled.');
    }
    if (!$error && $action !== 'driver') redirect('admin/bookings.php');
}
$drivers = db()->query("SELECT driver_id,full_name,vehicle_type FROM drivers WHERE status='active' AND availability_status='Available' ORDER BY full_name")->fetchAll();
$q = trim($_GET['q'] ?? '');
$sql = 'SELECT b.*,u.name customer_name,p.package_name,h.hotel_name,d.full_name driver_name FROM bookings b JOIN customers c ON c.customer_id=b.customer_id JOIN users u ON u.user_id=c.user_id JOIN packages p ON p.package_id=b.package_id LEFT JOIN hotels h ON h.hotel_id=b.hotel_id LEFT JOIN drivers d ON d.driver_id=b.driver_id';
$a = [];
if ($q !== '') {
    $sql .= ' WHERE u.name LIKE ? OR p.package_name LIKE ? OR b.booking_id=?';
    $a = ["%$q%", "%$q%", (int)$q];
}
$sql .= ' ORDER BY b.created_at DESC';
$s = $pdo->prepare($sql);
$s->execute($a);
$rows = $s->fetchAll();
$pageTitle = 'Booking management';
require '../includes/header.php'; ?><div class="container py-5">
    <h2>Booking management</h2>
    <form class="mb-3" method="get">
        <div class="input-group"><input class="form-control" name="q" placeholder="Booking number, customer or package" value="<?= e($q) ?>"><button class="btn btn-outline-primary">Search</button></div>
    </form><?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?><div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th># / Customer</th>
                        <th>Package</th>
                        <th>Dates</th>
                        <th>Hotel</th>
                        <th>Status</th>
                        <th>Driver</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody><?php foreach ($rows as $b): ?><tr>
                            <td>#<?= $b['booking_id'] ?><br><?= e($b['customer_name']) ?></td>
                            <td><?= e($b['package_name']) ?><br><small><?= money($b['total_amount']) ?></small></td>
                            <td><?= e($b['start_date']) ?><br><?= e($b['end_date']) ?></td>
                            <td><?= e($b['hotel_name'] ?: '—') ?><br><?= status_badge($b['hotel_reservation_status']) ?></td>
                            <td><?= status_badge($b['booking_status']) ?><form method="post" class="mt-1"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>"><select name="booking_status" class="form-select form-select-sm">
                                        <option>Pending</option>
                                        <option>Confirmed</option>
                                        <option>Cancelled</option>
                                        <option>Completed</option>
                                    </select><button class="btn btn-sm btn-outline-primary mt-1">Set</button></form>
                            </td>
                            <td><?= e($b['driver_name'] ?: 'Not assigned') ?><br><?= status_badge($b['trip_status']) ?><?php if (!$b['driver_id'] && $b['pickup_location']): ?><form method="post" class="mt-1"><?= csrf_field() ?><input type="hidden" name="action" value="driver"><input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>"><select name="driver_id" class="form-select form-select-sm"><?php foreach ($drivers as $d): ?><option value="<?= $d['driver_id'] ?>"><?= e($d['full_name']) ?> (<?= e($d['vehicle_type']) ?>)</option><?php endforeach; ?></select><button class="btn btn-sm btn-primary mt-1">Assign</button></form><?php endif; ?></td>
                            <td><?php if ($b['booking_status'] !== 'Cancelled'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>"><button data-confirm="Cancel this booking?" class="btn btn-sm btn-outline-danger">Cancel</button></form><?php endif; ?></td>
                        </tr><?php endforeach; ?></tbody>
            </table>
        </div>
    </div>
</div><?php require '../includes/footer.php'; ?>