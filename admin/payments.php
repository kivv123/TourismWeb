<?php

require '../includes/auth.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $id = (int) post('payment_id');
    $status = post('payment_status');

    if (
        in_array(
            $status,
            ['Pending', 'Paid', 'Failed', 'Refunded'],
            true
        )
    ) {

        $pdo = db();

        /*
         * Find the booking connected to this payment.
         */
        $s = $pdo->prepare(
            'SELECT booking_id
             FROM payments
             WHERE payment_id = ?'
        );

        $s->execute([$id]);

        $bid = $s->fetchColumn();

        if ($bid) {

            /*
             * Update payment status.
             *
             * paid_at is set when payment becomes Paid.
             */
            $pdo->prepare(
                "UPDATE payments
                 SET payment_status = ?,
                     paid_at = IF(? = 'Paid', NOW(), paid_at)
                 WHERE payment_id = ?"
            )->execute([
                $status,
                $status,
                $id
            ]);

            /*
             * Keep booking status synchronized
             * with the payment status.
             */
            if ($status === 'Paid') {

                /*
                 * Payment is confirmed.
                 * Booking becomes Confirmed.
                 */
                $pdo->prepare(
                    "UPDATE bookings
                     SET booking_status = 'Confirmed'
                     WHERE booking_id = ?"
                )->execute([
                    $bid
                ]);

            } elseif (
                in_array(
                    $status,
                    ['Failed', 'Refunded'],
                    true
                )
            ) {

                /*
                 * Payment is no longer valid.
                 * Remove the Confirmed status so that
                 * the customer cannot receive an official
                 * paid receipt.
                 */
                $pdo->prepare(
                    "UPDATE bookings
                     SET booking_status = 'Pending'
                     WHERE booking_id = ?
                       AND booking_status = 'Confirmed'"
                )->execute([
                    $bid
                ]);
            }

            flash(
                'success',
                'Payment updated.'
            );

        } else {

            flash(
                'error',
                'Payment record not found.'
            );
        }
    }

    redirect('admin/payments.php');
}


/*
 * Load payment records.
 */
$rows = db()->query(
    'SELECT
        py.*,
        b.total_amount,
        b.booking_status,
        u.name AS customer_name,
        p.package_name
     FROM payments py
     JOIN bookings b
        ON b.booking_id = py.booking_id
     JOIN customers c
        ON c.customer_id = b.customer_id
     JOIN users u
        ON u.user_id = c.user_id
     JOIN packages p
        ON p.package_id = b.package_id
     ORDER BY py.created_at DESC'
)->fetchAll();

$pageTitle = 'Payment verification';

require '../includes/header.php';
?>

<div class="container py-5">

    <h2>Payment verification</h2>

    <div class="card">

        <div class="table-responsive">

            <table class="table align-middle mb-0">

                <thead>

                    <tr>

                        <th>Booking</th>

                        <th>Customer</th>

                        <th>Amount</th>

                        <th>Method/reference</th>

                        <th>Payment</th>

                        <th>Booking</th>

                        <th></th>

                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($rows as $r): ?>

                        <tr>

                            <td>
                                #<?= e($r['booking_id']) ?>
                                <br>
                                <?= e($r['package_name']) ?>
                            </td>

                            <td>
                                <?= e($r['customer_name']) ?>
                            </td>

                            <td>
                                <?= money($r['amount']) ?>
                            </td>

                            <td>

                                <?= e($r['payment_method']) ?>

                                <br>

                                <small>
                                    <?= e(
                                        $r['transaction_reference']
                                            ?: '—'
                                    ) ?>
                                </small>

                            </td>

                            <td>
                                <?= status_badge(
                                    $r['payment_status']
                                ) ?>
                            </td>

                            <td>
                                <?= status_badge(
                                    $r['booking_status']
                                ) ?>
                            </td>

                            <td>

                                <form
                                    method="post"
                                    class="d-flex gap-1"
                                >

                                    <?= csrf_field() ?>

                                    <input
                                        type="hidden"
                                        name="payment_id"
                                        value="<?= e(
                                            $r['payment_id']
                                        ) ?>"
                                    >

                                    <select
                                        name="payment_status"
                                        class="form-select form-select-sm"
                                    >

                                        <?php foreach (
                                            [
                                                'Pending',
                                                'Paid',
                                                'Failed',
                                                'Refunded'
                                            ] as $x
                                        ): ?>

                                            <option
                                                value="<?= e($x) ?>"
                                                <?= (
                                                    $r['payment_status']
                                                    === $x
                                                )
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                <?= e($x) ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-primary"
                                    >
                                        Save
                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    <?php if (!$rows): ?>

                        <tr>

                            <td
                                colspan="7"
                                class="text-center py-4"
                            >
                                No payment records yet.
                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<?php require '../includes/footer.php'; ?>