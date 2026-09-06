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
$occupied = $total - $available;

// Reservation statistics
$stmt = db()->prepare("SELECT hotel_reservation_status, COUNT(*) AS c FROM bookings WHERE hotel_id = ? GROUP BY hotel_reservation_status");
$stmt->execute([$hotelId]);
$state = array_column($stmt->fetchAll(), 'c', 'hotel_reservation_status');

$pageTitle = 'Hotel Dashboard';
require '../includes/header.php';
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
                'Room types' => ['val' => $types, 'color' => 'text-primary'],
                'Total rooms' => ['val' => $total, 'color' => 'text-dark'],
                'Available' => ['val' => $available, 'color' => 'text-success'],
                'Pending' => ['val' => $state['Pending'] ?? 0, 'color' => 'text-warning'],
                'Accepted' => ['val' => $state['Accepted'] ?? 0, 'color' => 'text-info']
            ];
            foreach ($stats as $label => $data): 
            ?>
                <div class="col-6 col-md-4 col-lg">
                    <div class="card stat-card card-body shadow-sm border-0">
                        <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;"><?= e($label) ?></small>
                        <h3 class="mb-0 fw-bold <?= $data['color'] ?>"><?= e((string) $data['val']) ?></h3>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="row g-4">
            <!-- Chart Section -->
            <div class="col-lg-7">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 small fw-bold text-uppercase">Room Occupancy</h5>
                    </div>
                    <div class="card-body">
                        <div style="height: 300px;">
                            <canvas id="hotelChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Info Section -->
            <div class="col-lg-5">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 small fw-bold text-uppercase">Operational Status</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">Your current live room availability status:</p>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span><i class="bi bi-circle-fill text-success me-2" style="font-size: 0.6rem;"></i>Available</span>
                                <span class="fw-bold"><?= $available ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span><i class="bi bi-circle-fill text-danger me-2" style="font-size: 0.6rem;"></i>Occupied/Reserved</span>
                                <span class="fw-bold"><?= $occupied ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span><i class="bi bi-circle-fill text-warning me-2" style="font-size: 0.6rem;"></i>Pending Approval</span>
                                <span class="fw-bold"><?= $state['Pending'] ?? 0 ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('hotelChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar', // You can change this to 'doughnut' or 'pie'
        data: {
            labels: ['Total Rooms', 'Available', 'Occupied', 'Pending Requests'],
            datasets: [{
                label: 'Room Count',
                data: [<?= $total ?>, <?= $available ?>, <?= $occupied ?>, <?= $state['Pending'] ?? 0 ?>],
                backgroundColor: [
                    'rgba(18, 35, 58, 0.1)', // Total
                    'rgba(15, 118, 110, 0.7)', // Available (Teal)
                    'rgba(220, 53, 69, 0.7)',  // Occupied (Red)
                    'rgba(251, 191, 36, 0.7)'  // Pending (Sand)
                ],
                borderColor: [
                    '#12233a',
                    '#0f766e',
                    '#dc3545',
                    '#fbbf24'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
});
</script>

<?php require '../includes/footer.php'; ?>