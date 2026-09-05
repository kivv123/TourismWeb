<?php require '../includes/auth.php';
require_role('admin');
$rows = db()->query('SELECT r.*,h.hotel_name FROM rooms r JOIN hotels h ON h.hotel_id=r.hotel_id ORDER BY h.hotel_name,r.room_type')->fetchAll();
$pageTitle = 'All rooms';
require '../includes/header.php'; ?><div class="container py-5">
    <h2>Hotel rooms</h2>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Hotel</th>
                        <th>Room</th>
                        <th>Capacity</th>
                        <th>Price/night</th>
                        <th>Availability</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody><?php foreach ($rows as $r): ?><tr>
                            <td><?= e($r['hotel_name']) ?></td>
                            <td><?= e($r['room_type']) ?></td>
                            <td><?= $r['capacity'] ?></td>
                            <td><?= money($r['price_per_night']) ?></td>
                            <td><?= $r['available_rooms'] ?>/<?= $r['total_rooms'] ?></td>
                            <td><?= status_badge($r['status']) ?></td>
                        </tr><?php endforeach; ?></tbody>
            </table>
        </div>
    </div>
</div><?php require '../includes/footer.php'; ?>