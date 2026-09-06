<?php

require '../includes/auth.php';

require_role('customer');

$cid = get_entity_id(
    'customer',
    (int) current_user()['user_id']
);

$id = (int) ($_GET['id'] ?? 0);

/*
 * Load booking details.
 *
 * The customer can only access their own booking.
 */
$s = db()->prepare(
    'SELECT
        b.*,
        p.package_name,
        h.hotel_name,
        r.room_type,
        d.full_name AS driver_name
     FROM bookings b
     JOIN packages p
        ON p.package_id = b.package_id
     LEFT JOIN hotels h
        ON h.hotel_id = b.hotel_id
     LEFT JOIN rooms r
        ON r.room_id = b.room_id
     LEFT JOIN drivers d
        ON d.driver_id = b.driver_id
     WHERE b.booking_id = ?
       AND b.customer_id = ?'
);

$s->execute([
    $id,
    $cid
]);

$b = $s->fetch();

if (!$b) {

    http_response_code(404);

    exit('Booking not found.');
}

$errors = [];


/*
 * Handle customer actions.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $action = post('action');


    /*
     * Cancel booking.
     */
    if (
        $action === 'cancel' &&
        $b['booking_status'] === 'Pending'
    ) {

        $pdo = db();

        $pdo->beginTransaction();

        $pdo->prepare(
            "UPDATE bookings
             SET booking_status = 'Cancelled',
                 trip_status = 'Cancelled'
             WHERE booking_id = ?
               AND customer_id = ?"
        )->execute([
            $id,
            $cid
        ]);

        /*
         * Return rooms to inventory.
         */
        if ($b['room_id']) {

            $pdo->prepare(
                'UPDATE rooms
                 SET available_rooms =
                     available_rooms + ?
                 WHERE room_id = ?'
            )->execute([
                $b['number_of_rooms'],
                $b['room_id']
            ]);
        }

        $pdo->commit();

        flash(
            'success',
            'Booking cancelled.'
        );

        redirect(
            'customer/booking-details.php?id=' . $id
        );
    }


    /*
     * Submit payment.
     */
    if ($action === 'payment') {

        $method = post('method');

        $reference = post('reference');

        if (
            !in_array(
                $method,
                [
                    'Cash',
                    'Bank Transfer',
                    'Mobile Payment'
                ],
                true
            )
        ) {

            $errors[] =
                'Choose a payment method.';

        } else {

            $q = db()->prepare(
                "SELECT COALESCE(SUM(amount), 0)
                 FROM payments
                 WHERE booking_id = ?
                   AND payment_status IN
                       ('Pending', 'Paid')"
            );

            $q->execute([
                $id
            ]);

            $remaining =
                (float) $b['total_amount']
                -
                (float) $q->fetchColumn();

            if ($remaining <= 0) {

                $errors[] =
                    'This booking already has recorded payment.';

            } else {

                db()->prepare(
                    "INSERT INTO payments (
                        booking_id,
                        amount,
                        payment_method,
                        transaction_reference,
                        payment_status
                     )
                     VALUES (
                        ?,
                        ?,
                        ?,
                        ?,
                        'Pending'
                     )"
                )->execute([
                    $id,
                    $remaining,
                    $method,
                    $reference ?: null
                ]);

                flash(
                    'success',
                    'Payment record submitted for admin verification.'
                );

                redirect(
                    'customer/booking-details.php?id=' . $id
                );
            }
        }
    }
}


/*
 * Load payment records.
 */
$pay = db()->prepare(
    'SELECT *
     FROM payments
     WHERE booking_id = ?
     ORDER BY created_at DESC'
);

$pay->execute([
    $id
]);

$payments = $pay->fetchAll();


/*
 * Check whether this booking has a Paid payment.
 *
 * The receipt will only be available when:
 *
 * Payment = Paid
 * AND
 * Booking = Confirmed
 */
$hasPaidPayment = false;

foreach ($payments as $payment) {

    if ($payment['payment_status'] === 'Paid') {

        $hasPaidPayment = true;

        break;
    }
}


$pageTitle = 'Booking #' . $id;

require '../includes/header.php';

?>

<div class="container py-5">

    <a
        href="bookings.php"
        class="text-decoration-none"
    >
        ← My bookings
    </a>


    <div class="row g-4 mt-1">


        <!-- BOOKING INFORMATION -->

        <div class="col-lg-7">

            <div class="card card-body">

                <h2>
                    <?= e($b['package_name']) ?>
                </h2>

                <p>
                    <?= e($b['start_date']) ?>
                    to
                    <?= e($b['end_date']) ?>

                    ·

                    <?= e($b['number_of_travelers']) ?>
                    travelers
                </p>

                <hr>


                <p>

                    <b>Accommodation:</b>

                    <?= e(
                        $b['hotel_name']
                            ?: 'Not requested'
                    ) ?>

                    <?= e(
                        $b['room_type']
                            ? '— ' . $b['room_type']
                            : ''
                    ) ?>

                    <br>


                    <b>Hotel reservation:</b>

                    <?= status_badge(
                        $b['hotel_reservation_status']
                    ) ?>

                    <br>


                    <b>Driver:</b>

                    <?= e(
                        $b['driver_name']
                            ?: 'Not assigned'
                    ) ?>

                    ·

                    <?= status_badge(
                        $b['trip_status']
                    ) ?>

                </p>


                <!-- BOOKING STATUS -->

                <p>

                    <b>Booking:</b>

                    <?= status_badge(
                        $b['booking_status']
                    ) ?>

                </p>


                <!-- E-RECEIPT BUTTON -->

                <?php if (
                    $b['booking_status'] === 'Confirmed'
                    &&
                    $hasPaidPayment
                ): ?>

                    <div class="mt-3">

                        <a
                            href="e-receipt.php?id=<?= e($b['booking_id']) ?>"
                            class="btn btn-success"
                        >
                            View E-Receipt
                        </a>

                    </div>

                <?php endif; ?>


                <!-- CANCEL BOOKING -->

                <?php if (
                    $b['booking_status'] === 'Pending'
                ): ?>

                    <form
                        method="post"
                        class="mt-2"
                    >

                        <?= csrf_field() ?>

                        <input
                            name="action"
                            value="cancel"
                            type="hidden"
                        >

                        <button
                            data-confirm="Cancel this booking?"
                            class="btn btn-outline-danger"
                        >
                            Cancel booking
                        </button>

                    </form>

                <?php endif; ?>

            </div>

        </div>


        <!-- PRICE SUMMARY -->

        <div class="col-lg-5">

            <div class="card card-body">

                <h4>
                    Price summary
                </h4>


                <div
                    class="d-flex justify-content-between"
                >

                    <span>
                        Package
                    </span>

                    <b>
                        <?= money(
                            $b['package_total']
                        ) ?>
                    </b>

                </div>


                <div
                    class="d-flex justify-content-between"
                >

                    <span>
                        Hotel
                    </span>

                    <b>
                        <?= money(
                            $b['hotel_total']
                        ) ?>
                    </b>

                </div>


                <div
                    class="d-flex justify-content-between"
                >

                    <span>
                        Transport
                    </span>

                    <b>
                        <?= money(
                            $b['driver_total']
                        ) ?>
                    </b>

                </div>


                <hr>


                <div
                    class="d-flex justify-content-between fs-5"
                >

                    <b>
                        Total
                    </b>

                    <b>
                        <?= money(
                            $b['total_amount']
                        ) ?>
                    </b>

                </div>

            </div>

        </div>

    </div>


    <!-- PAYMENT SECTION -->

    <div class="row mt-2">


        <!-- PAYMENT RECORDS -->

        <div class="col-lg-7">

            <div class="card card-body">

                <h4>
                    Payment records
                </h4>


                <?php foreach (
                    $payments as $x
                ): ?>

                    <p
                        class="border-bottom pb-2 mb-2"
                    >

                        <?= money(
                            $x['amount']
                        ) ?>

                        ·

                        <?= e(
                            $x['payment_method']
                        ) ?>

                        <?= status_badge(
                            $x['payment_status']
                        ) ?>

                        <br>

                        <small>

                            <?= e(
                                $x['transaction_reference']
                                    ?: 'No reference'
                            ) ?>

                        </small>

                    </p>

                <?php endforeach; ?>


                <?php if (!$payments): ?>

                    <p class="text-muted">

                        No payment recorded.

                    </p>

                <?php endif; ?>

            </div>

        </div>


        <!-- RECORD PAYMENT -->

        <?php if (
            $b['booking_status'] !== 'Cancelled'
        ): ?>

            <div class="col-lg-5">

                <div class="card card-body">

                    <h4>
                        Record payment
                    </h4>


                    <?php foreach (
                        $errors as $error
                    ): ?>

                        <div
                            class="alert alert-danger"
                        >

                            <?= e($error) ?>

                        </div>

                    <?php endforeach; ?>


                    <form method="post">

                        <?= csrf_field() ?>


                        <input
                            name="action"
                            value="payment"
                            type="hidden"
                        >


                        <select
                            class="form-select mb-2"
                            name="method"
                            required
                        >

                            <option value="">
                                Payment method
                            </option>

                            <option>
                                Cash
                            </option>

                            <option>
                                Bank Transfer
                            </option>

                            <option>
                                Mobile Payment
                            </option>

                        </select>


                        <input
                            class="form-control mb-2"
                            name="reference"
                            placeholder="Transaction reference (if any)"
                        >


                        <button
                            class="btn btn-primary"
                        >
                            Submit for verification
                        </button>

                    </form>

                </div>

            </div>

        <?php endif; ?>

    </div>

</div>


<?php require '../includes/footer.php'; ?>