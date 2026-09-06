<?php

require '../includes/auth.php';

/*
 * IMPORTANT:
 *
 * This page intentionally does NOT use:
 *
 * require_role('customer');
 *
 * because the QR code must allow a phone to open
 * the receipt without having a login session.
 */


/*
 * Get the booking ID if the customer is opening
 * the receipt from inside the website.
 */
$id = (int) ($_GET['id'] ?? 0);


/*
 * Get the secure receipt token if the customer
 * is opening the receipt from the QR code.
 */
$receiptToken = trim(
    $_GET['code'] ?? ''
);


/*
 * We need either:
 *
 * ?code=SECURE_TOKEN
 *
 * or, for the normal logged-in website button:
 *
 * ?id=10
 */
if ($receiptToken === '' && $id <= 0) {

    http_response_code(400);

    exit('Invalid receipt link.');
}


/*
 * Load the booking.
 *
 * Two possible access methods:
 *
 * 1. Secure receipt token
 * 2. Logged-in customer + booking ID
 */
if ($receiptToken !== '') {

    /*
     * QR CODE ACCESS
     *
     * No login required.
     *
     * The random receipt token identifies the
     * specific booking.
     */
    $query = db()->prepare(
        "SELECT
            b.*,

            p.package_name,
            p.price_per_person,

            d.name AS destination_name,
            d.city AS destination_city,

            h.hotel_name,
            h.city AS hotel_city,

            r.room_type,
            r.price_per_night,

            u.name AS customer_name,
            u.email AS customer_email

         FROM bookings b

         INNER JOIN packages p
            ON p.package_id = b.package_id

         INNER JOIN destinations d
            ON d.destination_id = p.destination_id

         INNER JOIN customers c
            ON c.customer_id = b.customer_id

         INNER JOIN users u
            ON u.user_id = c.user_id

         LEFT JOIN hotels h
            ON h.hotel_id = b.hotel_id

         LEFT JOIN rooms r
            ON r.room_id = b.room_id

         WHERE b.receipt_token = ?
           AND b.booking_status = 'Confirmed'"
    );

    $query->execute([
        $receiptToken
    ]);

} else {

    /*
     * WEBSITE ACCESS
     *
     * If the customer clicks "E-Receipt" from
     * their account, require a customer login.
     */
    require_role('customer');

    $cid = get_entity_id(
        'customer',
        (int) current_user()['user_id']
    );

    if (!$cid) {

        http_response_code(403);

        exit(
            'No customer profile is associated with this account.'
        );
    }

    $query = db()->prepare(
        "SELECT
            b.*,

            p.package_name,
            p.price_per_person,

            d.name AS destination_name,
            d.city AS destination_city,

            h.hotel_name,
            h.city AS hotel_city,

            r.room_type,
            r.price_per_night,

            u.name AS customer_name,
            u.email AS customer_email

         FROM bookings b

         INNER JOIN packages p
            ON p.package_id = b.package_id

         INNER JOIN destinations d
            ON d.destination_id = p.destination_id

         INNER JOIN customers c
            ON c.customer_id = b.customer_id

         INNER JOIN users u
            ON u.user_id = c.user_id

         LEFT JOIN hotels h
            ON h.hotel_id = b.hotel_id

         LEFT JOIN rooms r
            ON r.room_id = b.room_id

         WHERE b.booking_id = ?
           AND b.customer_id = ?
           AND b.booking_status = 'Confirmed'"
    );

    $query->execute([
        $id,
        $cid
    ]);
}


$b = $query->fetch();


/*
 * If the booking doesn't exist, don't show anything.
 */
if (!$b) {

    http_response_code(404);

    exit('Receipt not found or not available.');
}


/*
 * Make sure there is actually a Paid payment.
 *
 * This prevents someone from using a receipt token
 * before payment has been verified.
 */
$paymentQuery = db()->prepare(
    "SELECT
        payment_id,
        amount,
        payment_method,
        transaction_reference,
        payment_status,
        paid_at,
        created_at

     FROM payments

     WHERE booking_id = ?

     ORDER BY created_at DESC"
);

$paymentQuery->execute([
    $b['booking_id']
]);

$payments = $paymentQuery->fetchAll();


$hasPaidPayment = false;

foreach ($payments as $payment) {

    if ($payment['payment_status'] === 'Paid') {

        $hasPaidPayment = true;

        break;
    }
}


/*
 * Receipt must have a confirmed booking
 * AND a paid payment.
 */
if (
    $b['booking_status'] !== 'Confirmed' ||
    !$hasPaidPayment
) {

    http_response_code(403);

    exit(
        'This receipt is not available yet.'
    );
}


/*
 * Receipt number.
 *
 * Example:
 *
 * MH-2026-000010
 */
$receiptNumber =
    'MH-' .
    date('Y') .
    '-' .
    str_pad(
        (string) $b['booking_id'],
        6,
        '0',
        STR_PAD_LEFT
    );


/*
 * Detect the current Wi-Fi IPv4 address.
 *
 * This is for your local XAMPP testing.
 */
function getWifiIPv4(): ?string
{
    $output = shell_exec('ipconfig');

    if (!$output) {
        return null;
    }

    /*
     * Split ipconfig into adapter sections.
     */
    $sections = preg_split(
        '/\r?\n(?=[A-Za-z].*adapter )/i',
        $output
    );

    foreach ($sections as $section) {

        /*
         * Only look at wireless/Wi-Fi adapters.
         */
        if (
            stripos(
                $section,
                'Wireless LAN adapter'
            ) === false &&
            stripos(
                $section,
                'Wi-Fi'
            ) === false &&
            stripos(
                $section,
                'Wireless'
            ) === false
        ) {
            continue;
        }

        /*
         * Find IPv4 address.
         */
        if (preg_match(
            '/IPv4 Address[.\s]*:\s*([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/i',
            $section,
            $matches
        )) {

            return $matches[1];
        }
    }

    return null;
}


/*
 * Create QR code URL.
 *
 * IMPORTANT:
 *
 * The QR contains the random receipt token,
 * NOT the booking ID.
 */
$wifiIP = getWifiIPv4();

if (!$wifiIP) {

    die(
        'Could not detect Wi-Fi IPv4 address.'
    );
}


$receiptUrl =
    'http://' .
    $wifiIP .
    '/TourismWeb/customer/e-receipt.php?code=' .
    urlencode($b['receipt_token']);


/*
 * QR code directory.
 */
$qrDirectory = '../qrcodes';


if (!is_dir($qrDirectory)) {

    mkdir(
        $qrDirectory,
        0755,
        true
    );
}


/*
 * QR image filename.
 *
 * Use booking ID internally for the filename.
 */
$qrFilePath =
    $qrDirectory .
    '/receipt-' .
    $b['booking_id'] .
    '.png';


/*
 * Load PHPQRCode.
 */
require_once '../phpqrcode/qrlib.php';


/*
 * Generate QR code.
 */
QRcode::png(
    $receiptUrl,
    $qrFilePath,
    QR_ECLEVEL_H,
    6,
    2
);


/*
 * Browser URL for the QR image.
 */
$qrImageUrl =
    '../qrcodes/receipt-' .
    $b['booking_id'] .
    '.png';


$pageTitle =
    'E-Receipt #' .
    $receiptNumber;


require '../includes/header.php';

?>

<style>

.receipt-container {
    max-width: 780px;
    margin: 0 auto;
}

.receipt-container .card {
    border-radius: 8px;
}

.receipt-container .card-body {
    padding: 24px !important;
}

.receipt-header {
    border-bottom: 2px solid #dee2e6;
    padding-bottom: 10px !important;
    margin-bottom: 14px !important;
}

.receipt-header h2 {
    font-size: 1.45rem;
}

.receipt-header p {
    font-size: 0.85rem;
}

.receipt-container h5 {
    font-size: 1rem;
    margin-bottom: 6px !important;
}

.receipt-container p {
    font-size: 0.88rem;
    line-height: 1.35;
}

.receipt-container hr {
    margin: 12px 0 !important;
}

.receipt-container .row {
    --bs-gutter-y: 8px;
}

.receipt-total {
    font-size: 1.15rem;
}

.qr-code {
    width: 140px;
    height: 140px;
    object-fit: contain;
}

.qr-section {
    margin-top: 18px !important;
    padding-top: 12px !important;
}

.payment-box {
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 10px !important;
    margin-bottom: 8px !important;
}

.payment-box .row {
    --bs-gutter-y: 5px;
}

.price-row {
    margin-bottom: 4px !important;
}

@media print {

    @page {
        margin: 8mm;
    }

    body {
        background: white !important;
        font-size: 11px;
    }

    body * {
        visibility: hidden;
    }

    .receipt-container,
    .receipt-container * {
        visibility: visible;
    }

    .receipt-container {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        max-width: none;
        margin: 0;
    }

    .receipt-container .card {
        border: none !important;
        box-shadow: none !important;
    }

    .receipt-container .card-body {
        padding: 5px !important;
    }

    .receipt-header {
        padding-bottom: 6px !important;
        margin-bottom: 8px !important;
    }

    .receipt-header h2 {
        font-size: 1.25rem;
    }

    .receipt-container h5 {
        font-size: 0.9rem;
        margin-bottom: 4px !important;
    }

    .receipt-container p {
        font-size: 10px;
        line-height: 1.25;
    }

    .receipt-container hr {
        margin: 7px 0 !important;
    }

    .receipt-container .row {
        --bs-gutter-y: 4px;
    }

    .receipt-total {
        font-size: 1rem;
    }

    .qr-code {
        width: 110px;
        height: 110px;
    }

    .qr-section {
        margin-top: 8px !important;
        padding-top: 6px !important;
    }

    .no-print {
        display: none !important;
    }

    .payment-box {
        padding: 6px !important;
        margin-bottom: 5px !important;
    }
}

</style>


<div class="container py-3">

    <div class="receipt-container">

        <div class="card shadow-sm">

            <div class="card-body">

                <!-- RECEIPT HEADER -->

                <div class="receipt-header d-flex justify-content-between align-items-start">

                    <div>

                        <h2 class="mb-0">
                            Myanmar Horizons
                        </h2>

                        <p class="text-muted mb-0">
                            E-Receipt
                        </p>

                    </div>

                    <div class="text-end">

                        <strong>
                            <?= e($receiptNumber) ?>
                        </strong>

                        <br>

                        <small class="text-muted">
                            <?= e($b['created_at']) ?>
                        </small>

                    </div>

                </div>


                <!-- CUSTOMER + BOOKING -->

                <div class="row">

                    <div class="col-md-6">

                        <strong>
                            Customer
                        </strong>

                        <br>

                        <?= e($b['customer_name']) ?>

                        <small class="text-muted">
                            (<?= e($b['customer_email']) ?>)
                        </small>

                    </div>


                    <div class="col-md-6">

                        <strong>
                            Booking
                        </strong>

                        <br>

                        #<?= e($b['booking_id']) ?>

                        &nbsp;

                        <?= status_badge($b['booking_status']) ?>

                    </div>

                </div>


                <hr>


                <!-- TRIP DETAILS -->

                <h5>
                    Trip Details
                </h5>

                <div class="row">

                    <div class="col-md-6">

                        <strong>Package:</strong>

                        <?= e($b['package_name']) ?>

                    </div>


                    <div class="col-md-6">

                        <strong>Destination:</strong>

                        <?= e($b['destination_name']) ?>

                        <?php if (!empty($b['destination_city'])): ?>

                            — <?= e($b['destination_city']) ?>

                        <?php endif; ?>

                    </div>


                    <div class="col-md-3">

                        <strong>Start:</strong>

                        <?= e($b['start_date']) ?>

                    </div>


                    <div class="col-md-3">

                        <strong>End:</strong>

                        <?= e($b['end_date']) ?>

                    </div>


                    <div class="col-md-3">

                        <strong>Travelers:</strong>

                        <?= e($b['number_of_travelers']) ?>

                    </div>


                    <div class="col-md-3">

                        <strong>Rooms:</strong>

                        <?= e($b['number_of_rooms']) ?>

                    </div>

                </div>


                <hr>


                <!-- ACCOMMODATION -->

                <h5>
                    Accommodation
                </h5>

                <?php if (!empty($b['hotel_name'])): ?>

                    <p class="mb-0">

                        <strong>Hotel:</strong>

                        <?= e($b['hotel_name']) ?>

                        <?php if (!empty($b['hotel_city'])): ?>

                            — <?= e($b['hotel_city']) ?>

                        <?php endif; ?>

                        <?php if (!empty($b['room_type'])): ?>

                            &nbsp; | &nbsp;

                            <strong>Room:</strong>

                            <?= e($b['room_type']) ?>

                        <?php endif; ?>

                    </p>

                <?php else: ?>

                    <p class="text-muted mb-0">
                        No hotel requested.
                    </p>

                <?php endif; ?>


                <hr>


                <!-- PAYMENT -->

                <h5>
                    Payment
                </h5>

                <?php foreach ($payments as $payment): ?>

                    <?php if ($payment['payment_status'] === 'Paid'): ?>

                        <div class="payment-box">

                            <div class="row">

                                <div class="col-md-3">

                                    <strong>Amount:</strong><br>

                                    <?= money($payment['amount']) ?>

                                </div>


                                <div class="col-md-3">

                                    <strong>Method:</strong><br>

                                    <?= e($payment['payment_method']) ?>

                                </div>


                                <div class="col-md-3">

                                    <strong>Reference:</strong><br>

                                    <?= e(
                                        $payment['transaction_reference']
                                        ?: '—'
                                    ) ?>

                                </div>


                                <div class="col-md-3">

                                    <strong>Status:</strong><br>

                                    <?= status_badge(
                                        $payment['payment_status']
                                    ) ?>

                                </div>

                            </div>

                        </div>

                    <?php endif; ?>

                <?php endforeach; ?>


                <hr>


                <!-- PRICE SUMMARY -->

                <h5>
                    Price Summary
                </h5>

                <div class="price-row d-flex justify-content-between">

                    <span>
                        Package
                    </span>

                    <strong>
                        <?= money($b['package_total']) ?>
                    </strong>

                </div>


                <div class="price-row d-flex justify-content-between">

                    <span>
                        Hotel
                    </span>

                    <strong>
                        <?= money($b['hotel_total']) ?>
                    </strong>

                </div>


                <div class="price-row d-flex justify-content-between">

                    <span>
                        Transportation
                    </span>

                    <strong>
                        <?= money($b['driver_total']) ?>
                    </strong>

                </div>


                <hr>


                <div class="d-flex justify-content-between receipt-total">

                    <strong>
                        Total Paid
                    </strong>

                    <strong>
                        <?= money($b['total_amount']) ?>
                    </strong>

                </div>


                <!-- SPECIAL REQUEST -->

                <?php if (!empty($b['special_request'])): ?>

                    <hr>

                    <h5>
                        Special Request
                    </h5>

                    <p class="mb-0">
                        <?= nl2br(
                            e($b['special_request'])
                        ) ?>
                    </p>

                <?php endif; ?>


                <!-- QR CODE -->

                <div class="text-center qr-section border-top">

                    <h5>
                        Scan to View Receipt
                    </h5>

                    <img
                        src="<?= e($qrImageUrl) ?>"
                        alt="Receipt QR Code"
                        class="qr-code"
                    >

                    <p class="small text-muted mt-1 mb-0">
                        Scan this QR code with your phone to open this receipt.
                    </p>

                </div>


                <!-- BUTTONS -->

                <div class="text-center mt-3 no-print">

                    <button
                        type="button"
                        class="btn btn-primary"
                        onclick="window.print()"
                    >
                        Print Receipt
                    </button>

                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        onclick="window.history.back()"
                    >
                        Back
                    </button>

                </div>

            </div>

        </div>

    </div>

</div>


<?php require '../includes/footer.php'; ?>

