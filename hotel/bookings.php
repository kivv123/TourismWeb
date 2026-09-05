<?php require '../includes/auth.php';
require_role('hotel');
$hid = get_entity_id('hotel', (int)current_user()['user_id']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)post('booking_id');
    $action = post('action');
    $s = db()->prepare('SELECT room_id,number_of_rooms FROM bookings WHERE booking_id=? AND hotel_id=? AND hotel_reservation_status=?');
    $s->execute([$id, $hid, 'Pending']);
    $b = $s->fetch();
    if ($b) {
        $state = $action === 'accept' ? 'Accepted' : 'Rejected';
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE bookings SET hotel_reservation_status=? WHERE booking_id=?')->execute([$state, $id]);
        if ($state === 'Rejected') $pdo->prepare('UPDATE rooms SET available_rooms=available_rooms+? WHERE room_id=?')->execute([$b['number_of_rooms'], $b['room_id']]);
        $pdo->commit();
        flash('success', 'Reservation ' . $state . '.');
    }
    redirect('hotel/bookings.php');
}
$s = db()->prepare('SELECT b.*,p.package_name,c.phone,u.name customer_name,r.room_type FROM bookings b JOIN packages p ON p.package_id=b.package_id JOIN customers c ON c.customer_id=b.customer_id JOIN users u ON u.user_id=c.user_id LEFT JOIN rooms r ON r.room_id=b.room_id WHERE b.hotel_id=? ORDER BY b.created_at DESC');
$s->execute([$hid]);
$rows = $s->fetchAll();
$pageTitle = 'Reservations';
require '../includes/header.php'; ?><div class="container py-5">
    <h2>Reservations</h2>
    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Guest</th>
                        <th>Package</th>
                        <th>Stay</th>
                        <th>Room</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody><?php foreach ($rows as $b): ?><tr>
                            <td><?= e($b['customer_name']) ?><br><small><?= e($b['phone']) ?></small></td>
                            <td><?= e($b['package_name']) ?></td>
                            <td><?= e($b['start_date']) ?> → <?= e($b['end_date']) ?></td>
                            <td><?= e($b['room_type']) ?> ×<?= $b['number_of_rooms'] ?></td>
                            <td><?= status_badge($b['hotel_reservation_status']) ?></td>
                            <td><?php if ($b['hotel_reservation_status'] === 'Pending'): ?><form method="post" class="d-flex gap-1"><?= csrf_field() ?><input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>"><button name="action" value="accept" class="btn btn-sm btn-success">Accept</button><button name="action" value="reject" class="btn btn-sm btn-danger">Reject</button></form><?php endif; ?></td>
                        </tr><?php endforeach; ?></tbody>
            </table>
        </div>
    </div>
</div><?php require '../includes/footer.php'; ?>