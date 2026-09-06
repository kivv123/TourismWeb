<?php

require '../includes/auth.php';
require_role('hotel');

$userId = (int) current_user()['user_id'];
$hotelId = get_entity_id('hotel', $userId);

if (!$hotelId) {
    http_response_code(403);
    exit('No hotel profile is associated with this account.');
}

/*
 * Accept/reject reservation logic
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $bookingId = (int) post('booking_id');
    $action = post('action');

    if ($bookingId < 1 || !in_array($action, ['accept', 'reject'], true)) {
        flash('error', 'Invalid reservation action.');
        redirect('hotel/bookings.php');
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $reservationQuery = $pdo->prepare(
            "SELECT b.*, r.available_rooms, r.total_rooms
             FROM bookings b
             INNER JOIN hotels h ON h.hotel_id = b.hotel_id
             LEFT JOIN rooms r ON r.room_id = b.room_id AND r.hotel_id = b.hotel_id
             WHERE b.booking_id = ? AND b.hotel_id = ? AND h.user_id = ?
             FOR UPDATE"
        );
        $reservationQuery->execute([$bookingId, $hotelId, $userId]);
        $booking = $reservationQuery->fetch();

        if (!$booking) throw new RuntimeException('Reservation not found.');
        if ($booking['hotel_reservation_status'] !== 'Pending') throw new RuntimeException('Already processed.');
        if ($booking['booking_status'] === 'Cancelled') throw new RuntimeException('Cancelled booking.');

        $newStatus = $action === 'accept' ? 'Accepted' : 'Rejected';

        if ($newStatus === 'Rejected' && $booking['room_id'] !== null && (int)$booking['number_of_rooms'] > 0) {
            $pdo->prepare("UPDATE rooms SET available_rooms = LEAST(total_rooms, available_rooms + ?) WHERE room_id = ? AND hotel_id = ?")
                ->execute([(int)$booking['number_of_rooms'], (int)$booking['room_id'], $hotelId]);
        }

        $pdo->prepare("UPDATE bookings SET hotel_reservation_status = ? WHERE booking_id = ? AND hotel_id = ?")
            ->execute([$newStatus, $bookingId, $hotelId]);

        $pdo->commit();
        flash('success', 'Reservation ' . strtolower($newStatus) . ' successfully.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', $e->getMessage());
    }
    redirect('hotel/bookings.php');
}

/*
 * Load reservations
 */
$reservationQuery = db()->prepare(
    "SELECT b.*, p.package_name, c.phone, u.name AS customer_name, r.room_type
     FROM bookings b
     INNER JOIN hotels h ON h.hotel_id = b.hotel_id AND h.user_id = ?
     INNER JOIN packages p ON p.package_id = b.package_id
     INNER JOIN customers c ON c.customer_id = b.customer_id
     INNER JOIN users u ON u.user_id = c.user_id
     LEFT JOIN rooms r ON r.room_id = b.room_id AND r.hotel_id = b.hotel_id
     WHERE b.hotel_id = ?
     ORDER BY b.created_at DESC"
);
$reservationQuery->execute([$userId, $hotelId]);
$rows = $reservationQuery->fetchAll();

$pageTitle = 'Reservations';
require '../includes/header.php';
?>

<div class="dashboard-layout">
    <!-- Hotel Sidebar -->
    <aside class="dashboard-sidebar">
        <nav class="dashboard-menu">
            <a href="dashboard.php" class="dashboard-menu-item">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
            <a href="rooms.php" class="dashboard-menu-item">
                <i class="bi bi-door-open"></i>
                <span>Manage Rooms</span>
            </a>
            <a href="bookings.php" class="dashboard-menu-item active">
                <i class="bi bi-calendar-check"></i>
                <span>Reservations</span>
            </a>
            <div class="dashboard-menu-divider"></div>
            <a href="profile.php" class="dashboard-menu-item">
                <i class="bi bi-building"></i>
                <span>Hotel Profile</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="dashboard-content">
        <div class="mb-4">
            <h2 class="mb-1">Reservations</h2>
            <p class="text-muted">Manage incoming booking requests and stay schedules.</p>
        </div>

        <div class="card shadow-sm overflow-hidden">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-3">Guest</th>
                            <th>Stay Details</th>
                            <th>Room Info</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $booking): ?>
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold"><?= e($booking['customer_name']) ?></div>
                                    <div class="small text-muted"><?= e($booking['phone']) ?></div>
                                </td>
                                <td class="small">
                                    <div class="fw-semibold text-dark"><?= e($booking['package_name']) ?></div>
                                    <div class="text-muted">
                                        <?= e($booking['start_date']) ?> → <?= e($booking['end_date']) ?>
                                    </div>
                                    <div><?= e($booking['number_of_travelers']) ?> traveler(s)</div>
                                </td>
                                <td class="small">
                                    <?= e($booking['room_type'] ?: '—') ?>
                                    <?php if ((int)$booking['number_of_rooms'] > 0): ?>
                                        <span class="badge bg-light text-dark border">×<?= e($booking['number_of_rooms']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-primary"><?= money($booking['hotel_total']) ?></div>
                                    <div class="text-muted small" style="font-size: 0.7rem;">Total: <?= money($booking['total_amount']) ?></div>
                                </td>
                                <td><?= status_badge($booking['hotel_reservation_status']) ?></td>
                                <td class="text-end pe-3">
                                    <?php if ($booking['hotel_reservation_status'] === 'Pending'): ?>
                                        <form method="post" class="d-inline-flex gap-1">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="booking_id" value="<?= e($booking['booking_id']) ?>">
                                            <button type="submit" name="action" value="accept" class="btn btn-sm btn-success px-3">Accept</button>
                                            <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger px-3">Reject</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$rows): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-calendar-x fs-1 d-block mb-2 opacity-25"></i>
                                    No reservations found.
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