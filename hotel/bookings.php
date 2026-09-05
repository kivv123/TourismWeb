<?php

require '../includes/auth.php';

require_role('hotel');

$userId = (int) current_user()['user_id'];

/*
 * Resolve the currently logged-in Hotel account to its
 * hotel record through hotels.user_id.
 */
$hotelId = get_entity_id(
    'hotel',
    $userId
);

if (!$hotelId) {
    http_response_code(403);
    exit('No hotel profile is associated with this account.');
}

/*
 * Accept/reject reservation.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $bookingId =
        (int) post('booking_id');

    $action =
        post('action');

    if (
        $bookingId < 1 ||
        !in_array(
            $action,
            ['accept', 'reject'],
            true
        )
    ) {

        flash(
            'error',
            'Invalid reservation action.'
        );

        redirect('hotel/bookings.php');
    }

    $pdo = db();

    try {

        $pdo->beginTransaction();

        /*
         * Verify that this booking belongs to the currently
         * logged-in hotel account.
         *
         * hotels.user_id = logged-in user_id
         * hotels.hotel_id = booking.hotel_id
         */
        $reservationQuery = $pdo->prepare(
            "SELECT
                b.booking_id,
                b.hotel_id,
                b.room_id,
                b.number_of_rooms,
                b.booking_status,
                b.hotel_reservation_status,
                r.available_rooms,
                r.total_rooms
             FROM bookings b
             INNER JOIN hotels h
                ON h.hotel_id = b.hotel_id
             LEFT JOIN rooms r
                ON r.room_id = b.room_id
               AND r.hotel_id = b.hotel_id
             WHERE b.booking_id = ?
               AND b.hotel_id = ?
               AND h.hotel_id = ?
               AND h.user_id = ?
             FOR UPDATE"
        );

        $reservationQuery->execute([
            $bookingId,
            $hotelId,
            $hotelId,
            $userId
        ]);

        $booking =
            $reservationQuery->fetch();

        if (!$booking) {

            throw new RuntimeException(
                'Reservation not found for your hotel.'
            );
        }

        if (
            $booking['hotel_reservation_status'] !==
            'Pending'
        ) {

            throw new RuntimeException(
                'This reservation has already been processed.'
            );
        }

        if (
            $booking['booking_status'] ===
            'Cancelled'
        ) {

            throw new RuntimeException(
                'Cancelled bookings cannot be accepted or rejected.'
            );
        }

        $newStatus =
            $action === 'accept'
                ? 'Accepted'
                : 'Rejected';

        /*
         * Rooms are reserved when the customer creates
         * the booking.
         *
         * If the hotel rejects the reservation, return the
         * reserved rooms to inventory.
         */
        if (
            $newStatus === 'Rejected' &&
            $booking['room_id'] !== null &&
            (int) $booking['number_of_rooms'] > 0
        ) {

            $roomUpdate = $pdo->prepare(
                "UPDATE rooms
                 SET available_rooms =
                     LEAST(
                         total_rooms,
                         available_rooms + ?
                     )
                 WHERE room_id = ?
                   AND hotel_id = ?"
            );

            $roomUpdate->execute([
                (int) $booking['number_of_rooms'],
                (int) $booking['room_id'],
                $hotelId
            ]);

            if ($roomUpdate->rowCount() !== 1) {

                throw new RuntimeException(
                    'Unable to restore the rejected room inventory.'
                );
            }
        }

        /*
         * Update only this hotel's reservation.
         */
        $update = $pdo->prepare(
            "UPDATE bookings
             SET hotel_reservation_status = ?
             WHERE booking_id = ?
               AND hotel_id = ?
               AND hotel_reservation_status = 'Pending'"
        );

        $update->execute([
            $newStatus,
            $bookingId,
            $hotelId
        ]);

        if ($update->rowCount() !== 1) {

            throw new RuntimeException(
                'Reservation status could not be updated.'
            );
        }

        $pdo->commit();

        flash(
            'success',
            'Reservation ' .
            strtolower($newStatus) .
            ' successfully.'
        );

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        flash(
            'error',
            $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Unable to update reservation.'
        );
    }

    redirect('hotel/bookings.php');
}

/*
 * IMPORTANT:
 *
 * Reservation loading uses:
 *
 * logged-in user_id
 *       ↓
 * hotels.user_id
 *       ↓
 * hotels.hotel_id
 *       ↓
 * bookings.hotel_id
 *
 * Therefore Hotel A cannot see Hotel B bookings.
 */
$reservationQuery = db()->prepare(
    "SELECT
        b.*,
        p.package_name,
        c.phone,
        u.name AS customer_name,
        h.hotel_name,
        r.room_type,
        r.price_per_night
     FROM bookings b
     INNER JOIN hotels h
        ON h.hotel_id = b.hotel_id
       AND h.user_id = ?
     INNER JOIN packages p
        ON p.package_id = b.package_id
     INNER JOIN customers c
        ON c.customer_id = b.customer_id
     INNER JOIN users u
        ON u.user_id = c.user_id
     LEFT JOIN rooms r
        ON r.room_id = b.room_id
       AND r.hotel_id = b.hotel_id
     WHERE b.hotel_id = ?
     ORDER BY b.created_at DESC"
);

$reservationQuery->execute([
    $userId,
    $hotelId
]);

$rows =
    $reservationQuery->fetchAll();

$pageTitle = 'Reservations';

require '../includes/header.php';
?>

<div class="container py-5">

    <h2>
        Reservations
    </h2>

    <?php if ($message = flash('success')): ?>

        <div class="alert alert-success">
            <?= e($message) ?>
        </div>

    <?php endif; ?>

    <?php if ($message = flash('error')): ?>

        <div class="alert alert-danger">
            <?= e($message) ?>
        </div>

    <?php endif; ?>

    <div class="card">

        <div class="table-responsive">

            <table class="table align-middle mb-0">

                <thead>

                    <tr>

                        <th>Guest</th>

                        <th>Package</th>

                        <th>Stay</th>

                        <th>Room</th>

                        <th>Hotel Amount</th>

                        <th>Total</th>

                        <th>Status</th>

                        <th></th>

                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($rows as $booking): ?>

                        <tr>

                            <td>

                                <?= e(
                                    $booking['customer_name']
                                ) ?>

                                <br>

                                <small>
                                    <?= e(
                                        $booking['phone']
                                    ) ?>
                                </small>

                            </td>

                            <td>
                                <?= e(
                                    $booking['package_name']
                                ) ?>
                            </td>

                            <td>

                                <?= e(
                                    $booking['start_date']
                                ) ?>

                                →

                                <?= e(
                                    $booking['end_date']
                                ) ?>

                                <br>

                                <small>
                                    <?= e(
                                        $booking['number_of_travelers']
                                    ) ?>
                                    traveler(s)
                                </small>

                            </td>

                            <td>

                                <?= e(
                                    $booking['room_type']
                                    ?: '—'
                                ) ?>

                                <?php if (
                                    (int) $booking[
                                        'number_of_rooms'
                                    ] > 0
                                ): ?>

                                    ×<?= e(
                                        $booking[
                                            'number_of_rooms'
                                        ]
                                    ) ?>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?= money(
                                    $booking['hotel_total']
                                ) ?>

                            </td>

                            <td>

                                <?= money(
                                    $booking['total_amount']
                                ) ?>

                            </td>

                            <td>

                                <?= status_badge(
                                    $booking[
                                        'hotel_reservation_status'
                                    ]
                                ) ?>

                            </td>

                            <td>

                                <?php if (
                                    $booking[
                                        'hotel_reservation_status'
                                    ] === 'Pending'
                                ): ?>

                                    <form
                                        method="post"
                                        class="d-flex gap-1"
                                    >

                                        <?= csrf_field() ?>

                                        <input
                                            type="hidden"
                                            name="booking_id"
                                            value="<?= e(
                                                $booking[
                                                    'booking_id'
                                                ]
                                            ) ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="action"
                                            value="accept"
                                            class="btn btn-sm btn-success"
                                        >
                                            Accept
                                        </button>

                                        <button
                                            type="submit"
                                            name="action"
                                            value="reject"
                                            class="btn btn-sm btn-danger"
                                        >
                                            Reject
                                        </button>

                                    </form>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    <?php if (!$rows): ?>

                        <tr>

                            <td
                                colspan="8"
                                class="text-center py-4"
                            >
                                No reservations found.
                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<?php require '../includes/footer.php'; ?>