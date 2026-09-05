<?php

require '../includes/auth.php';

require_role('hotel');

$hotelId = get_entity_id(
    'hotel',
    (int) current_user()['user_id']
);

if (!$hotelId) {
    http_response_code(403);
    exit('No hotel profile is associated with this account.');
}

$errors = [];
$edit = null;

/*
 * Load the room being edited only if it belongs
 * to the currently logged-in hotel.
 */
if (isset($_GET['edit'])) {

    $roomId = (int) $_GET['edit'];

    $stmt = db()->prepare(
        'SELECT *
         FROM rooms
         WHERE room_id = ?
           AND hotel_id = ?'
    );

    $stmt->execute([
        $roomId,
        $hotelId
    ]);

    $edit = $stmt->fetch();

    if (!$edit) {
        flash(
            'error',
            'Room not found or does not belong to your hotel.'
        );

        redirect('hotel/rooms.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $action = post('action');
    $roomId = (int) post('room_id');

    /*
     * Delete/deactivate room.
     */
    if ($action === 'delete') {

        if ($roomId < 1) {
            flash(
                'error',
                'Invalid room.'
            );

            redirect('hotel/rooms.php');
        }

        $stmt = db()->prepare(
            "UPDATE rooms
             SET status = 'inactive'
             WHERE room_id = ?
               AND hotel_id = ?"
        );

        $stmt->execute([
            $roomId,
            $hotelId
        ]);

        if ($stmt->rowCount() !== 1) {
            flash(
                'error',
                'Room not found or could not be updated.'
            );
        } else {
            flash(
                'success',
                'Room deactivated.'
            );
        }

        redirect('hotel/rooms.php');
    }

    $type = post('room_type');
    $description = post('description');
    $capacity = (int) post('capacity');
    $price = (float) post('price_per_night');
    $totalRooms = (int) post('total_rooms');
    $availableRooms = (int) post('available_rooms');

    $status = post(
        'status',
        'active'
    );

    /*
     * Validate status explicitly.
     */
    if (
        !in_array(
            $status,
            ['active', 'inactive'],
            true
        )
    ) {
        $status = 'active';
    }

    if (!$type) {
        $errors[] =
            'Please enter a room type.';
    }

    if ($capacity < 1) {
        $errors[] =
            'Room capacity must be at least 1.';
    }

    if ($price <= 0) {
        $errors[] =
            'Price per night must be greater than 0.';
    }

    if ($totalRooms < 1) {
        $errors[] =
            'Total rooms must be at least 1.';
    }

    if (
        $availableRooms < 0 ||
        $availableRooms > $totalRooms
    ) {
        $errors[] =
            'Available rooms must be between 0 and total rooms.';
    }

    if (!$errors) {

        try {

            $pdo = db();

            if ($action === 'save') {

                if ($roomId < 1) {
                    throw new RuntimeException(
                        'Invalid room.'
                    );
                }

                /*
                 * The hotel_id condition prevents one hotel
                 * from editing another hotel's room.
                 */
                $stmt = $pdo->prepare(
                    'UPDATE rooms
                     SET
                        room_type = ?,
                        description = ?,
                        capacity = ?,
                        price_per_night = ?,
                        total_rooms = ?,
                        available_rooms = ?,
                        status = ?
                     WHERE room_id = ?
                       AND hotel_id = ?'
                );

                $stmt->execute([
                    $type,
                    $description ?: null,
                    $capacity,
                    $price,
                    $totalRooms,
                    $availableRooms,
                    $status,
                    $roomId,
                    $hotelId
                ]);

            } else {

                /*
                 * IMPORTANT:
                 *
                 * price_per_night is inserted directly from
                 * the validated Hotel form field into the
                 * correct database column.
                 */
                $stmt = $pdo->prepare(
                    'INSERT INTO rooms (
                        hotel_id,
                        room_type,
                        description,
                        capacity,
                        price_per_night,
                        total_rooms,
                        available_rooms,
                        status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );

                $stmt->execute([
                    $hotelId,
                    $type,
                    $description ?: null,
                    $capacity,
                    $price,
                    $totalRooms,
                    $availableRooms,
                    $status
                ]);
            }

            flash(
                'success',
                'Room saved successfully.'
            );

            redirect('hotel/rooms.php');

        } catch (Throwable $e) {

            $errors[] =
                'Unable to save the room. Please check the entered values and try again.';
        }
    }
}

/*
 * Only display rooms owned by the logged-in hotel.
 */
$stmt = db()->prepare(
    'SELECT *
     FROM rooms
     WHERE hotel_id = ?
     ORDER BY status, room_type'
);

$stmt->execute([
    $hotelId
]);

$rows = $stmt->fetchAll();

$pageTitle = 'Room management';

require '../includes/header.php';
?>

<div class="container py-5">

    <div class="row g-4">

        <div class="col-lg-5">

            <div class="card card-body">

                <h3>
                    <?= $edit
                        ? 'Edit room'
                        : 'Add room type' ?>
                </h3>

                <?php foreach ($errors as $error): ?>

                    <div class="alert alert-danger">
                        <?= e($error) ?>
                    </div>

                <?php endforeach; ?>

                <form method="post">

                    <?= csrf_field() ?>

                    <input
                        type="hidden"
                        name="action"
                        value="<?= $edit
                            ? 'save'
                            : 'create' ?>"
                    >

                    <input
                        type="hidden"
                        name="room_id"
                        value="<?= e(
                            $edit['room_id'] ?? ''
                        ) ?>"
                    >

                    <div class="mb-2">

                        <label class="form-label">
                            Room type
                        </label>

                        <input
                            class="form-control"
                            name="room_type"
                            required
                            value="<?= e(
                                $edit['room_type'] ?? ''
                            ) ?>"
                        >

                    </div>

                    <div class="mb-2">

                        <label class="form-label">
                            Description
                        </label>

                        <textarea
                            class="form-control"
                            name="description"
                        ><?= e(
                            $edit['description'] ?? ''
                        ) ?></textarea>

                    </div>

                    <div class="row g-2">

                        <div class="col">

                            <label class="form-label">
                                Capacity
                            </label>

                            <input
                                class="form-control"
                                type="number"
                                min="1"
                                name="capacity"
                                value="<?= e(
                                    $edit['capacity'] ?? 2
                                ) ?>"
                                required
                            >

                        </div>

                        <div class="col">

                            <label class="form-label">
                                Price/night
                            </label>

                            <input
                                class="form-control"
                                type="number"
                                min="1"
                                step="0.01"
                                name="price_per_night"
                                value="<?= e(
                                    $edit['price_per_night'] ?? ''
                                ) ?>"
                                required
                            >

                        </div>

                    </div>

                    <div class="row g-2 mt-1">

                        <div class="col">

                            <label class="form-label">
                                Total rooms
                            </label>

                            <input
                                class="form-control"
                                type="number"
                                min="1"
                                name="total_rooms"
                                value="<?= e(
                                    $edit['total_rooms'] ?? 1
                                ) ?>"
                                required
                            >

                        </div>

                        <div class="col">

                            <label class="form-label">
                                Available
                            </label>

                            <input
                                class="form-control"
                                type="number"
                                min="0"
                                name="available_rooms"
                                value="<?= e(
                                    $edit['available_rooms'] ?? 1
                                ) ?>"
                                required
                            >

                        </div>

                    </div>

                    <?php if ($edit): ?>

                        <div class="mt-2">

                            <select
                                class="form-select"
                                name="status"
                            >

                                <option
                                    value="active"
                                    <?= $edit['status'] === 'active'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Active
                                </option>

                                <option
                                    value="inactive"
                                    <?= $edit['status'] === 'inactive'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Inactive
                                </option>

                            </select>

                        </div>

                    <?php endif; ?>

                    <button
                        type="submit"
                        class="btn btn-primary mt-3"
                    >
                        Save room
                    </button>

                </form>

            </div>

        </div>

        <div class="col-lg-7">

            <div class="card">

                <div class="table-responsive">

                    <table class="table mb-0">

                        <thead>

                            <tr>
                                <th>Type</th>
                                <th>Capacity</th>
                                <th>Price</th>
                                <th>Available</th>
                                <th></th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($rows as $room): ?>

                                <tr>

                                    <td>

                                        <?= e(
                                            $room['room_type']
                                        ) ?>

                                        <br>

                                        <small>
                                            <?= status_badge(
                                                $room['status']
                                            ) ?>
                                        </small>

                                    </td>

                                    <td>
                                        <?= e(
                                            $room['capacity']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= money(
                                            $room['price_per_night']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= e(
                                            $room['available_rooms']
                                        ) ?>
                                        /
                                        <?= e(
                                            $room['total_rooms']
                                        ) ?>
                                    </td>

                                    <td>

                                        <a
                                            class="btn btn-sm btn-outline-primary"
                                            href="?edit=<?= e(
                                                $room['room_id']
                                            ) ?>"
                                        >
                                            Edit
                                        </a>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            <?php if (!$rows): ?>

                                <tr>

                                    <td
                                        colspan="5"
                                        class="text-center py-4"
                                    >
                                        No rooms found.
                                    </td>

                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>

</div>

<?php require '../includes/footer.php'; ?>