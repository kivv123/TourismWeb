<?php

require '../includes/auth.php';

require_role('customer');

$cid = get_entity_id(
    'customer',
    (int) current_user()['user_id']
);

/*
 * Load customer's bookings.
 *
 * We also load the payment status so that
 * the E-Receipt button is shown only when
 * the payment is Paid.
 */
$s = db()->prepare(
    "SELECT
        b.*,
        p.package_name,
        h.hotel_name,
        py.payment_status
     FROM bookings b
     JOIN packages p
        ON p.package_id = b.package_id
     LEFT JOIN hotels h
        ON h.hotel_id = b.hotel_id
     LEFT JOIN payments py
        ON py.booking_id = b.booking_id
     WHERE b.customer_id = ?
     ORDER BY b.created_at DESC"
);

$s->execute([$cid]);

$rows = $s->fetchAll();

$pageTitle = 'My bookings';

require '../includes/header.php';
?>

<div class="container py-5">

    <div class="d-flex justify-content-between">

        <h2>My bookings</h2>

        <a
            class="btn btn-primary"
            href="<?= url('packages.php') ?>"
        >
            New booking
        </a>

    </div>

    <div class="card mt-3">
        

        <div class="table-responsive">

            <table class="table align-middle mb-0">

                <thead>

                    <tr>

                        <th>#</th>

                        <th>Package</th>

                        <th>Stay</th>

                        <th>Hotel</th>

                        <th>Total</th>

                        <th>Payment</th>

                        <th>Status</th>

                        <th></th>

                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($rows as $b): ?>

                        <tr>

                            <td>
                                #<?= e($b['booking_id']) ?>
                            </td>

                            <td>
                                <?= e($b['package_name']) ?>
                            </td>

                            <td>
                                <?= e($b['start_date']) ?>
                                →
                                <?= e($b['end_date']) ?>
                            </td>

                            <td>
                                <?= e(
                                    $b['hotel_name'] ?: '—'
                                ) ?>
                            </td>

                            <td>
                                <?= money($b['total_amount']) ?>
                            </td>

                            <td>

                                <?php if ($b['payment_status']): ?>

                                    <?= status_badge(
                                        $b['payment_status']
                                    ) ?>

                                <?php else: ?>

                                    <span class="text-muted">
                                        No payment
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>
                                <?= status_badge(
                                    $b['booking_status']
                                ) ?>
                            </td>

                            <td>

                                <div class="d-flex gap-1">

                                    <a
                                        class="btn btn-sm btn-outline-primary"
                                        href="booking-details.php?id=<?= e($b['booking_id']) ?>"
                                    >
                                        Details
                                    </a>

                                    <?php
                                    /*
                                     * E-Receipt is available ONLY when:
                                     *
                                     * Payment = Paid
                                     * AND
                                     * Booking = Confirmed
                                     */
                                    ?>

                                    <?php if (
                                        $b['payment_status'] === 'Paid' &&
                                        $b['booking_status'] === 'Confirmed'
                                    ): ?>

                                        <a
                                            class="btn btn-sm btn-success"
                                            href="e-receipt.php?id=<?= e($b['booking_id']) ?>"
                                        >
                                            E-Receipt
                                        </a>

                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    <?php if (!$rows): ?>

                        <tr>

                            <td
                                colspan="8"
                                class="text-center py-4"
                            >
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