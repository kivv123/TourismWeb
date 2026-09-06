<?php

require '../includes/auth.php';
require_role('hotel');

$hotelId = get_entity_id('hotel', (int) current_user()['user_id']);

if (!$hotelId) {
    http_response_code(403);
    exit('No hotel profile is associated with this account.');
}

$errors = [];
$edit = null;

if (isset($_GET['edit'])) {
    $roomId = (int) $_GET['edit'];
    $stmt = db()->prepare('SELECT * FROM rooms WHERE room_id = ? AND hotel_id = ?');
    $stmt->execute([$roomId, $hotelId]);
    $edit = $stmt->fetch();
    if (!$edit) {
        flash('error', 'Room not found.');
        redirect('hotel/rooms.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('action');
    $roomId = (int) post('room_id');

    if ($action === 'delete') {
        db()->prepare("UPDATE rooms SET status = 'inactive' WHERE room_id = ? AND hotel_id = ?")
            ->execute([$roomId, $hotelId]);
        flash('success', 'Room deactivated.');
        redirect('hotel/rooms.php');
    }

    $type = post('room_type');
    $description = post('description');
    $capacity = (int) post('capacity');
    $price = (float) post('price_per_night');
    $totalRooms = (int) post('total_rooms');
    $availableRooms = (int) post('available_rooms');
    $status = post('status', 'active');

    if (!$type) $errors[] = 'Please enter a room type.';
    if ($capacity < 1) $errors[] = 'Room capacity must be at least 1.';
    if ($price <= 0) $errors[] = 'Price per night must be greater than 0.';
    if ($totalRooms < 1) $errors[] = 'Total rooms must be at least 1.';
    if ($availableRooms < 0 || $availableRooms > $totalRooms) $errors[] = 'Available rooms must be between 0 and total rooms.';

    if (!$errors) {
        try {
            $pdo = db();
            if ($action === 'save') {
                $stmt = $pdo->prepare('UPDATE rooms SET room_type=?, description=?, capacity=?, price_per_night=?, total_rooms=?, available_rooms=?, status=? WHERE room_id=? AND hotel_id=?');
                $stmt->execute([$type, $description ?: null, $capacity, $price, $totalRooms, $availableRooms, $status, $roomId, $hotelId]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO rooms (hotel_id, room_type, description, capacity, price_per_night, total_rooms, available_rooms, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$hotelId, $type, $description ?: null, $capacity, $price, $totalRooms, $availableRooms, $status]);
            }
            flash('success', 'Room saved successfully.');
            redirect('hotel/rooms.php');
        } catch (Throwable $e) {
            $errors[] = 'Unable to save the room.';
        }
    }
}

$stmt = db()->prepare('SELECT * FROM rooms WHERE hotel_id = ? ORDER BY status, room_type');
$stmt->execute([$hotelId]);
$rows = $stmt->fetchAll();

$pageTitle = 'Room Management';
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
            <a href="rooms.php" class="dashboard-menu-item active">
                <i class="bi bi-door-open"></i>
                <span>Manage Rooms</span>
            </a>
            <a href="bookings.php" class="dashboard-menu-item">
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
            <h2 class="mb-1"><?= $pageTitle ?></h2>
            <p class="text-muted">Manage your room inventory, pricing, and availability.</p>
        </div>

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card card-body shadow-sm border-0">
                    <h5 class="card-title mb-3"><?= $edit ? 'Edit Room' : 'Add Room Type' ?></h5>
                    
                    <?php foreach ($errors as $error): ?>
                        <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
                    <?php endforeach; ?>

                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="<?= $edit ? 'save' : 'create' ?>">
                        <input type="hidden" name="room_id" value="<?= e($edit['room_id'] ?? '') ?>">

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Room Type</label>
                            <input class="form-control" name="room_type" value="<?= e($edit['room_type'] ?? '') ?>" required placeholder="e.g. Deluxe Double Room">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Description</label>
                            <textarea class="form-control" name="description" rows="2"><?= e($edit['description'] ?? '') ?></textarea>
                        </div>

                        <div class="row g-2">
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold">Capacity</label>
                                <input class="form-control" type="number" min="1" name="capacity" value="<?= e($edit['capacity'] ?? 2) ?>" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold">Price / Night</label>
                                <input class="form-control" type="number" step="0.01" name="price_per_night" value="<?= e($edit['price_per_night'] ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="row g-2">
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold">Total Rooms</label>
                                <input class="form-control" type="number" name="total_rooms" value="<?= e($edit['total_rooms'] ?? 1) ?>" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold">Available</label>
                                <input class="form-control" type="number" name="available_rooms" value="<?= e($edit['available_rooms'] ?? 1) ?>" required>
                            </div>
                        </div>

                        <?php if ($edit): ?>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Status</label>
                            <select class="form-select" name="status">
                                <option value="active" <?= $edit['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $edit['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        <?php endif; ?>

                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="bi bi-check-circle me-2"></i>Save Room Type
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card shadow-sm border-0 overflow-hidden" style="flex: 1; min-height: 0;">
                    <div class="table-responsive" style="max-height: 600px;">
                        <table class="table align-middle mb-0">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th class="ps-3">Room Info</th>
                                    <th>Price</th>
                                    <th>Availability</th>
                                    <th class="text-end pe-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $room): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <div class="fw-bold"><?= e($room['room_type']) ?></div>
                                            <div class="small text-muted"><i class="bi bi-people me-1"></i>Cap: <?= e($room['capacity']) ?></div>
                                            <?= status_badge($room['status']) ?>
                                        </td>
                                        <td class="fw-bold text-primary">
                                            <?= money($room['price_per_night']) ?>
                                        </td>
                                        <td>
                                            <span class="badge rounded-pill bg-light text-dark border">
                                                <?= e($room['available_rooms']) ?> / <?= e($room['total_rooms']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-3">
                                            <a class="btn btn-sm btn-outline-primary" href="?edit=<?= e($room['room_id']) ?>">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$rows): ?>
                                    <tr><td colspan="4" class="text-center py-5 text-muted">No rooms added yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require '../includes/footer.php'; ?>
