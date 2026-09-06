<?php

require 'includes/auth.php';

require_role('customer');

$customerId = get_entity_id('customer', (int) current_user()['user_id']);

if (!$customerId) {
    http_response_code(403);
    exit('No customer profile is associated with this account.');
}

$packageId = (int) ($_REQUEST['package_id'] ?? 0);

$packageQuery = db()->prepare(
    "SELECT
        p.*,
        d.name AS destination_name,
        d.city AS package_city
     FROM packages p
     INNER JOIN destinations d
        ON d.destination_id = p.destination_id
     WHERE p.package_id = ?
       AND p.status = 'active'
       AND d.status = 'active'"
);

$packageQuery->execute([$packageId]);
$package = $packageQuery->fetch();

if (!$package) {
    flash('error', 'That package or destination is unavailable.');
    redirect('packages.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $start = post('start_date');
    $end = post('end_date');
    $travelers = (int) post('travelers');
    $hotelId = (int) post('hotel_id');
    $roomId = (int) post('room_id');
    $numberOfRooms = (int) post('number_of_rooms');
    $transportRequested = isset($_POST['transport']);
    $pickupLocation = post('pickup_location');
    $specialRequest = post('special_request');

    $pdo = db();

    try {

        if (!$start || !$end) {
            throw new RuntimeException(
                'Please select both start and end dates.'
            );
        }

        $startDate = DateTime::createFromFormat('Y-m-d', $start);
        $endDate = DateTime::createFromFormat('Y-m-d', $end);

        if (
            !$startDate ||
            !$endDate ||
            $startDate->format('Y-m-d') !== $start ||
            $endDate->format('Y-m-d') !== $end
        ) {
            throw new RuntimeException(
                'Please enter valid booking dates.'
            );
        }

        $numberOfNights = (int) $startDate->diff($endDate)->format('%r%a');

        if ($numberOfNights < 1) {
            throw new RuntimeException(
                'End date must be after start date.'
            );
        }

        if ($start < date('Y-m-d')) {
            throw new RuntimeException(
                'Start date cannot be in the past.'
            );
        }

        if (
            $travelers < 1 ||
            $travelers > (int) $package['max_travelers']
        ) {
            throw new RuntimeException(
                'Traveler count is not valid for this package.'
            );
        }

        /*
         * Hotel and room must either both be supplied or both be empty.
         */
        if (
            ($hotelId > 0 && $roomId <= 0) ||
            ($hotelId <= 0 && $roomId > 0)
        ) {
            throw new RuntimeException(
                'Please select both a hotel and a room.'
            );
        }

        if ($hotelId > 0 && $numberOfRooms < 1) {
            throw new RuntimeException(
                'Number of rooms must be at least 1.'
            );
        }

        if (
            $hotelId <= 0 &&
            $roomId <= 0
        ) {
            $numberOfRooms = 0;
        }

        if (
            $transportRequested &&
            !$pickupLocation
        ) {
            throw new RuntimeException(
                'Enter a pickup location for transportation.'
            );
        }

        $pdo->beginTransaction();

        /*
         * IMPORTANT:
         *
         * Package price comes from the database.
         * Hotel price comes from the database.
         * No browser-submitted price is trusted.
         */
        $packagePrice = (float) $package['price_per_person'];

        $packageTotal =
            $packagePrice * $travelers;

        $hotelTotal = 0.0;
        $hotelReservationStatus = 'Not Required';

        $room = null;

        if ($hotelId > 0 && $roomId > 0) {

            /*
             * Validate ALL relationships in one query:
             *
             * room -> hotel
             * hotel -> package city
             * active hotel
             * active room
             *
             * Most importantly, price_per_night is selected directly
             * from the rooms table.
             */
            $roomQuery = $pdo->prepare(
                "SELECT
                    r.room_id,
                    r.hotel_id,
                    r.room_type,
                    r.capacity,
                    r.price_per_night,
                    r.available_rooms,
                    r.total_rooms,
                    h.hotel_name,
                    h.city AS hotel_city
                 FROM rooms r
                 INNER JOIN hotels h
                    ON h.hotel_id = r.hotel_id
                 WHERE r.room_id = ?
                   AND r.hotel_id = ?
                   AND r.status = 'active'
                   AND h.status = 'active'
                   AND LOWER(TRIM(h.city)) =
                       LOWER(TRIM(?))
                 FOR UPDATE"
            );

            $roomQuery->execute([
                $roomId,
                $hotelId,
                $package['package_city']
            ]);

            $room = $roomQuery->fetch();

            if (!$room) {
                throw new RuntimeException(
                    'The selected room does not belong to the selected hotel, or the hotel does not match the package destination.'
                );
            }

            $availableRooms = (int) $room['available_rooms'];
            $roomCapacity = (int) $room['capacity'];

            if ($availableRooms < $numberOfRooms) {
                throw new RuntimeException(
                    'The selected room does not have enough available rooms.'
                );
            }

            if (
                $travelers >
                ($roomCapacity * $numberOfRooms)
            ) {
                throw new RuntimeException(
                    'The selected rooms do not have enough capacity for all travelers.'
                );
            }

            /*
             * THIS is the authoritative hotel price.
             *
             * Never use:
             * $_POST['hotel_total']
             * $_POST['room_price']
             * hidden price fields
             * JavaScript totals
             */
            $pricePerNight =
                (float) $room['price_per_night'];

            if ($pricePerNight <= 0) {
                throw new RuntimeException(
                    'The selected room has an invalid price in the database.'
                );
            }

            /*
             * Existing pricing rule:
             *
             * price per night
             * × number of rooms
             * × number of nights
             */
            $hotelTotal =
                $pricePerNight *
                $numberOfRooms *
                $numberOfNights;

            $hotelReservationStatus = 'Pending';
        }

        /*
         * Preserve the existing transportation calculation.
         */
        $driverTotal =
            $transportRequested ? 30000.0 : 0.0;

        $totalAmount =
            $packageTotal +
            $hotelTotal +
            $driverTotal;

        /*
         * Save the authoritative database values.
         */
        $bookingInsert = $pdo->prepare(
            "INSERT INTO bookings (
                customer_id,
                package_id,
                hotel_id,
                room_id,
                driver_id,
                start_date,
                end_date,
                number_of_travelers,
                number_of_rooms,
                pickup_location,
                special_request,
                package_total,
                hotel_total,
                driver_total,
                total_amount,
                booking_status,
                hotel_reservation_status
            ) VALUES (
                ?,
                ?,
                ?,
                ?,
                NULL,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                'Pending',
                ?
            )"
        );

        $bookingInsert->execute([
            $customerId,
            $packageId,
            $hotelId > 0 ? $hotelId : null,
            $roomId > 0 ? $roomId : null,
            $start,
            $end,
            $travelers,
            $numberOfRooms,
            $pickupLocation ?: null,
            $specialRequest ?: null,
            $packageTotal,
            $hotelTotal,
            $driverTotal,
            $totalAmount,
            $hotelReservationStatus
        ]);

        $bookingId =
            (int) $pdo->lastInsertId();
        $receiptToken = bin2hex(random_bytes(32));

        $updateReceiptToken = $pdo->prepare(
            "UPDATE bookings
            SET receipt_token = ?
            WHERE booking_id = ?"
        );

        $updateReceiptToken->execute([
            $receiptToken,
            $bookingId
        ]);

        /*
         * Reserve room inventory only after the booking record
         * has been successfully created.
         */
        if ($room) {

            $updateRoom = $pdo->prepare(
                "UPDATE rooms
                 SET available_rooms =
                     available_rooms - ?
                 WHERE room_id = ?
                   AND hotel_id = ?
                   AND status = 'active'
                   AND available_rooms >= ?"
            );

            $updateRoom->execute([
                $numberOfRooms,
                $roomId,
                $hotelId,
                $numberOfRooms
            ]);

            if ($updateRoom->rowCount() !== 1) {

                throw new RuntimeException(
                    'The selected room became unavailable. Please try again.'
                );
            }
        }

        $pdo->commit();

        flash(
            'success',
            'Booking request submitted successfully.'
        );

        redirect(
            'customer/booking-details.php?id=' .
            $bookingId
        );

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $errors[] =
            $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Unable to create booking. Please try again.';
    }
}

/*
 * Only hotels matching the package destination city
 * are displayed.
 */
$hotelQuery = db()->prepare(
    "SELECT
        hotel_id,
        hotel_name,
        city
     FROM hotels
     WHERE status = 'active'
       AND LOWER(TRIM(city)) =
           LOWER(TRIM(?))
     ORDER BY hotel_name"
);

$hotelQuery->execute([
    $package['package_city']
]);

$hotels = $hotelQuery->fetchAll();

/*
 * Load active rooms from the database.
 *
 * price_per_night is included for display only.
 * The final booking price is recalculated again on the server
 * during POST processing.
 */
$roomsQuery = db()->prepare(
    "SELECT
        r.room_id,
        r.hotel_id,
        r.room_type,
        r.capacity,
        r.price_per_night,
        r.available_rooms
     FROM rooms r
     INNER JOIN hotels h
        ON h.hotel_id = r.hotel_id
     WHERE r.status = 'active'
       AND h.status = 'active'
       AND r.available_rooms > 0
       AND LOWER(TRIM(h.city)) =
           LOWER(TRIM(?))
     ORDER BY
        h.hotel_name,
        r.room_type"
);

$roomsQuery->execute([
    $package['package_city']
]);

$roomRows = $roomsQuery->fetchAll();

$pageTitle =
    'Book ' . $package['package_name'];

require 'includes/header.php';
?>

<div class="container py-5">

    <div class="row g-4">

        <div class="col-lg-8">

            <h2>
                Book <?= e($package['package_name']) ?>
            </h2>

            <p class="text-muted">
                Destination:
                <?= e($package['destination_name']) ?>
                (<?= e($package['package_city']) ?>)
            </p>

            <?php foreach ($errors as $error): ?>

                <div class="alert alert-danger">
                    <?= e($error) ?>
                </div>

            <?php endforeach; ?>

            <form
                method="post"
                class="card card-body needs-validation"
                novalidate
            >

                <?= csrf_field() ?>

                <input
                    type="hidden"
                    name="package_id"
                    value="<?= $packageId ?>"
                >

                <div class="row g-3">

                    <div class="col-md-6">

                        <label class="form-label">
                            Start Date
                        </label>

                        <input
                            type="date"
                            name="start_date"
                            class="form-control"
                            min="<?= e(date('Y-m-d')) ?>"
                            value="<?= e($_POST['start_date'] ?? '') ?>"
                            required
                        >

                    </div>

                    <div class="col-md-6">

                        <label class="form-label">
                            End Date
                        </label>

                        <input
                            type="date"
                            name="end_date"
                            class="form-control"
                            min="<?= e(date('Y-m-d', strtotime('+1 day'))) ?>"
                            value="<?= e($_POST['end_date'] ?? '') ?>"
                            required
                        >

                    </div>

                    <div class="col-md-6">

                        <label class="form-label">
                            Number of Travelers
                        </label>

                        <input
                            type="number"
                            name="travelers"
                            min="1"
                            max="<?= e($package['max_travelers']) ?>"
                            value="<?= e($_POST['travelers'] ?? '1') ?>"
                            class="form-control"
                            required
                        >

                    </div>

                    <div class="col-md-6">

                        <label class="form-label">
                            Hotel
                        </label>

                        <select
                            class="form-select"
                            name="hotel_id"
                            id="hotel"
                        >

                            <option value="">
                                No hotel required
                            </option>

                            <?php foreach ($hotels as $hotel): ?>

                                <option
                                    value="<?= e($hotel['hotel_id']) ?>"
                                    <?= (
                                        (int) (
                                            $_POST['hotel_id'] ?? 0
                                        ) ===
                                        (int) $hotel['hotel_id']
                                    ) ? 'selected' : '' ?>
                                >
                                    <?= e($hotel['hotel_name']) ?>
                                    —
                                    <?= e($hotel['city']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                        <?php if (!$hotels): ?>

                            <small class="text-warning">
                                No hotels are currently available
                                in this destination.
                            </small>

                        <?php endif; ?>

                    </div>

                    <div class="col-md-6">

                        <label class="form-label">
                            Room Type
                        </label>

                        <select
                            class="form-select"
                            name="room_id"
                            id="room"
                        >

                            <option value="">
                                Select a hotel first
                            </option>

                            <?php foreach ($roomRows as $room): ?>

                                <option
                                    value="<?= e($room['room_id']) ?>"
                                    data-hotel="<?= e($room['hotel_id']) ?>"
                                    data-price="<?= e((string) $room['price_per_night']) ?>"
                                    data-capacity="<?= e($room['capacity']) ?>"
                                    data-available="<?= e($room['available_rooms']) ?>"
                                >
                                    <?= e($room['room_type']) ?>
                                    —
                                    <?= money($room['price_per_night']) ?>/night
                                    (<?= e($room['available_rooms']) ?> available,
                                    capacity <?= e($room['capacity']) ?>)
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="col-md-6">

                        <label class="form-label">
                            Number of Rooms
                        </label>

                        <input
                            type="number"
                            name="number_of_rooms"
                            min="1"
                            value="<?= e($_POST['number_of_rooms'] ?? '1') ?>"
                            class="form-control"
                        >

                    </div>

                    <div class="col-12">

                        <div class="form-check">

                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="transport"
                                id="transport"
                                <?= isset($_POST['transport']) ? 'checked' : '' ?>
                            >

                            <label
                                class="form-check-label"
                                for="transport"
                            >
                                Request transportation
                                (+<?= money(30000) ?> coordination fee)
                            </label>

                        </div>

                    </div>

                    <div class="col-12">

                        <label class="form-label">
                            Pickup Location
                        </label>

                        <input
                            class="form-control"
                            name="pickup_location"
                            placeholder="Required when transportation is selected"
                            value="<?= e($_POST['pickup_location'] ?? '') ?>"
                        >

                    </div>

                    <div class="col-12">

                        <label class="form-label">
                            Special Request
                        </label>

                        <textarea
                            class="form-control"
                            name="special_request"
                            rows="3"
                        ><?= e($_POST['special_request'] ?? '') ?></textarea>

                    </div>

                    <div class="col-12">

                        <button
                            type="submit"
                            class="btn btn-warning"
                        >
                            Submit Booking Request
                        </button>

                    </div>

                </div>

            </form>

        </div>

        <aside class="col-lg-4">

            <div class="card card-body booking-summary">

                <h4>
                    <?= e($package['package_name']) ?>
                </h4>

                <p>
                    <?= e($package['duration_days']) ?>
                    days /
                    <?= e($package['duration_nights']) ?>
                    nights
                </p>

                <h3 class="text-primary">
                    <?= money($package['price_per_person']) ?>
                </h3>

                <small>
                    Per person
                </small>

                <hr>

                <div class="d-flex justify-content-between">

                    <span>
                        Hotel Amount
                    </span>

                    <b id="hotel-total-preview">
                        0 MMK
                    </b>

                </div>

                <small class="text-muted">
                    Final hotel amount is calculated from
                    the database when the booking is submitted.
                </small>

            </div>

        </aside>

    </div>

</div>

<script>
const hotelSelect =
    document.getElementById('hotel');

const roomSelect =
    document.getElementById('room');

const roomsInput =
    document.querySelector(
        'input[name="number_of_rooms"]'
    );

const startInput =
    document.querySelector(
        'input[name="start_date"]'
    );

const endInput =
    document.querySelector(
        'input[name="end_date"]'
    );

const hotelTotalPreview =
    document.getElementById(
        'hotel-total-preview'
    );

function getNights() {

    if (
        !startInput.value ||
        !endInput.value
    ) {
        return 0;
    }

    const start =
        new Date(
            startInput.value + 'T00:00:00'
        );

    const end =
        new Date(
            endInput.value + 'T00:00:00'
        );

    const difference =
        end.getTime() -
        start.getTime();

    if (difference <= 0) {
        return 0;
    }

    return Math.floor(
        difference / 86400000
    );
}

function updateRooms() {

    const hotelId =
        hotelSelect.value;

    const currentRoomId =
        roomSelect.value;

    roomSelect.value = '';

    for (
        const option of roomSelect.options
    ) {

        if (!option.dataset.hotel) {
            continue;
        }

        const belongsToHotel =
            hotelId !== '' &&
            option.dataset.hotel === hotelId;

        option.hidden =
            !belongsToHotel;

        option.disabled =
            !belongsToHotel;
    }

    if (currentRoomId !== '') {

        const matchingOption =
            Array.from(
                roomSelect.options
            ).find(
                option =>
                    option.value === currentRoomId &&
                    option.dataset.hotel === hotelId
            );

        if (matchingOption) {
            roomSelect.value =
                currentRoomId;
        }
    }

    updateHotelTotalPreview();
}

function updateHotelTotalPreview() {

    const selectedOption =
        roomSelect.options[
            roomSelect.selectedIndex
        ];

    if (
        !selectedOption ||
        !selectedOption.dataset.price
    ) {
        hotelTotalPreview.textContent =
            '0 MMK';

        return;
    }

    const price =
        Number(
            selectedOption.dataset.price
        );

    const rooms =
        Number(
            roomsInput.value || 0
        );

    const nights =
        getNights();

    const total =
        price *
        rooms *
        nights;

    hotelTotalPreview.textContent =
        new Intl.NumberFormat(
            'en-US'
        ).format(total) +
        ' MMK';
}

hotelSelect.addEventListener(
    'change',
    updateRooms
);

roomSelect.addEventListener(
    'change',
    updateHotelTotalPreview
);

roomsInput.addEventListener(
    'input',
    updateHotelTotalPreview
);

startInput.addEventListener(
    'change',
    updateHotelTotalPreview
);

endInput.addEventListener(
    'change',
    updateHotelTotalPreview
);

updateRooms();
updateHotelTotalPreview();
</script>

<?php require 'includes/footer.php'; ?>