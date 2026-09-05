<?php

require '../includes/auth.php';

require_role('hotel');

$userId = (int) current_user()['user_id'];

$hotelId = get_entity_id(
    'hotel',
    $userId
);

if (!$hotelId) {
    http_response_code(403);
    exit('No hotel profile is associated with this account.');
}

/*
 * Room type count.
 */
$stmt = db()->prepare(
    'SELECT COUNT(*)
     FROM rooms
     WHERE hotel_id = ?'
);

$stmt->execute([
    $hotelId
]);

$types =
    (int) $stmt->fetchColumn();

/*
 * Room inventory.
 */
$stmt = db()->prepare(
    'SELECT
        COALESCE(SUM(total_rooms), 0),
        COALESCE(SUM(available_rooms), 0)
     FROM rooms
     WHERE hotel_id = ?'
);

$stmt->execute([
    $hotelId
]);

[
    $total,
    $available
] = $stmt->fetch(
    PDO::FETCH_NUM
);

/*
 * Reservation statistics.
 *
 * hotel_id is obtained from the logged-in hotel's
 * user_id relationship above.
 */
$stmt = db()->prepare(
    "SELECT
        hotel_reservation_status,
        COUNT(*) AS c
     FROM bookings
     WHERE hotel_id = ?
     GROUP BY hotel_reservation_status"
);

$stmt->execute([
    $hotelId
]);

$state =
    array_column(
        $stmt->fetchAll(),
        'c',
        'hotel_reservation_status'
    );

$pageTitle =
    'Hotel dashboard';

require '../includes/header.php';
?>

<div class="container py-4">

    <h2>
        Hotel dashboard
    </h2>

    <div class="row g-3 mb-4">

        <?php foreach (
            [
                'Room types' => $types,
                'Total rooms' => $total,
                'Available' => $available,
                'Pending' => $state['Pending'] ?? 0,
                'Accepted' => $state['Accepted'] ?? 0
            ]
            as $label => $value
        ): ?>

            <div class="col-6 col-lg">

                <div class="card stat-card card-body">

                    <small>
                        <?= e($label) ?>
                    </small>

                    <h3>
                        <?= e((string) $value) ?>
                    </h3>

                </div>

            </div>

        <?php endforeach; ?>

    </div>


    

</div>
<?php require '../includes/footer.php';  //hi this is a change made by zth
?>

