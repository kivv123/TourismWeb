<?php
require '../includes/auth.php';
require_role('hotel');

$userId = (int) current_user()['user_id'];
$hotelId = get_entity_id('hotel', $userId);

if (!$hotelId) {
    http_response_code(403);
    exit('No hotel profile is associated with this account.');
}

// Room type count
$stmt = db()->prepare('SELECT COUNT(*) FROM rooms WHERE hotel_id = ?');
$stmt->execute([$hotelId]);
$types = (int) $stmt->fetchColumn();

// Room inventory
$stmt = db()->prepare('SELECT COALESCE(SUM(total_rooms), 0), COALESCE(SUM(available_rooms), 0) FROM rooms WHERE hotel_id = ?');
$stmt->execute([$hotelId]);
[$total, $available] = $stmt->fetch(PDO::FETCH_NUM);

// Reservation statistics
$stmt = db()->prepare("SELECT hotel_reservation_status, COUNT(*) AS c FROM bookings WHERE hotel_id = ? GROUP BY hotel_reservation_status");
$stmt->execute([$hotelId]);
$state = array_column($stmt->fetchAll(), 'c', 'hotel_reservation_status');

$pageTitle = 'Hotel Dashboard';
require '../includes/header.php';
?>

<div class="dashboard-layout">
    <!-- Hotel Sidebar -->
    <aside class="dashboard-sidebar">
        <nav class="dashboard-menu">
            <a href="dashboard.php" class="dashboard-menu-item active">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
            <a href="rooms.php" class="dashboard-menu-item">
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

    <!-- Main Content Area -->
    <main class="dashboard-content">
        <div class="mb-4">
            <h2 class="mb-1">Hotel Dashboard</h2>
            <p class="text-muted">Overview of your hotel operations and room inventory.</p>
        </div>

        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
            <?php 
            $stats = [
                'Room types' => ['val' => $types, 'icon' => 'bi-layers'],
                'Total rooms' => ['val' => $total, 'icon' => 'bi-house-gear'],
                'Available' => ['val' => $available, 'icon' => 'bi-check-circle'],
                'Pending' => ['val' => $state['Pending'] ?? 0, 'icon' => 'bi-clock-history'],
                'Accepted' => ['val' => $state['Accepted'] ?? 0, 'icon' => 'bi-calendar-check']
            ];
            foreach ($stats as $label => $data): 
            ?>
                <div class="col-6 col-md-4 col-lg">
                    <div class="card stat-card card-body shadow-sm border-0">
                        <div class="d-flex align-items-center gap-3">
                            <div>
                                <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;"><?= e($label) ?></small>
                                <h3 class="mb-0 fw-bold"><?= e((string) $data['val']) ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Placeholder for more content (e.g. Recent Bookings Table) -->
        <div class="card shadow-sm border-0" style="flex: 1; min-height: 0;">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 small fw-bold text-uppercase">Operational Status</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Select an option from the sidebar to manage your rooms or view detailed booking requests.</p>
            </div>
        </div>
        
    </main>
</div>

<?php require '../includes/footer.php'; ?>