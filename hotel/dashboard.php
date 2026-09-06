<?php
require '../includes/auth.php';
require_role('hotel');

$userId = (int) current_user()['user_id'];
$hotelId = get_entity_id('hotel', $userId);

if (!$hotelId) {
    http_response_code(403);
    exit('No hotel profile is associated with this account.');
}

// 1. Room Stats
$stmt = db()->prepare('SELECT COUNT(*) FROM rooms WHERE hotel_id = ?');
$stmt->execute([$hotelId]);
$types = (int) $stmt->fetchColumn();

$stmt = db()->prepare('SELECT COALESCE(SUM(total_rooms), 0), COALESCE(SUM(available_rooms), 0) FROM rooms WHERE hotel_id = ?');
$stmt->execute([$hotelId]);
[$total, $available] = $stmt->fetch(PDO::FETCH_NUM);
$occupied = $total - $available;

// 2. Reservation Stats
$stmt = db()->prepare("SELECT hotel_reservation_status, COUNT(*) AS c FROM bookings WHERE hotel_id = ? GROUP BY hotel_reservation_status");
$stmt->execute([$hotelId]);
$state = array_column($stmt->fetchAll(), 'c', 'hotel_reservation_status');

// 3. Financial Data (Monthly Revenue for Current Year)
$stmt = db()->prepare("
    SELECT 
        MONTH(created_at) as month, 
        SUM(hotel_total) as revenue 
    FROM bookings 
    WHERE hotel_id = ? 
    AND hotel_reservation_status = 'Accepted' 
    AND YEAR(created_at) = YEAR(CURRENT_DATE)
    GROUP BY MONTH(created_at)
    ORDER BY MONTH(created_at)
");
$stmt->execute([$hotelId]);
$monthlyRevenue = array_fill(1, 12, 0);
foreach ($stmt->fetchAll() as $row) {
    $monthlyRevenue[(int)$row['month']] = (float)$row['revenue'];
}
$totalRevenue = array_sum($monthlyRevenue);

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
            <p class="text-muted">Overview of your hotel operations and financial performance.</p>
        </div>

        <!-- Row 1: Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg">
                <div class="card stat-card card-body shadow-sm border-0">
                    <small class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem;">Total Revenue (YTD)</small>
                    <h4 class="mb-0 fw-bold text-teal"><?= money($totalRevenue) ?></h4>
                </div>
            </div>
            <div class="col-6 col-lg">
                <div class="card stat-card card-body shadow-sm border-0">
                    <small class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem;">Available Rooms</small>
                    <h4 class="mb-0 fw-bold text-success"><?= $available ?></h4>
                </div>
            </div>
            <div class="col-6 col-lg">
                <div class="card stat-card card-body shadow-sm border-0">
                    <small class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem;">Pending Requests</small>
                    <h4 class="mb-0 fw-bold text-warning"><?= $state['Pending'] ?? 0 ?></h4>
                </div>
            </div>
            <div class="col-6 col-lg">
                <div class="card stat-card card-body shadow-sm border-0">
                    <small class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem;">Total Capacity</small>
                    <h4 class="mb-0 fw-bold text-dark"><?= $total ?></h4>
                </div>
            </div>
        </div>

        <!-- Row 2: Charts -->
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3 border-0">
                        <h6 class="mb-0 fw-bold text-uppercase" style="font-size: 0.75rem;">Revenue Analytics (<?= date('Y') ?>)</h6>
                    </div>
                    <div class="card-body">
                        <div style="height: 300px;">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3 border-0">
                        <h6 class="mb-0 fw-bold text-uppercase" style="font-size: 0.75rem;">Room Occupancy Status</h6>
                    </div>
                    <div class="card-body">
                        <div style="height: 300px;">
                            <canvas id="occupancyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 3: Operational Status List -->
        <div class="card shadow-sm border-0 mb-5">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="mb-0 fw-bold text-uppercase" style="font-size: 0.75rem;">Operational Details</h6>
            </div>
            <div class="card-body pt-0">
                <div class="row">
                    <div class="col-md-6">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span><i class="bi bi-circle-fill text-success me-2 small"></i>Available Rooms</span>
                                <span class="fw-bold"><?= $available ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span><i class="bi bi-circle-fill text-danger me-2 small"></i>Occupied/Booked</span>
                                <span class="fw-bold"><?= $occupied ?></span>
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span><i class="bi bi-circle-fill text-warning me-2 small"></i>Pending Approvals</span>
                                <span class="fw-bold"><?= $state['Pending'] ?? 0 ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span><i class="bi bi-circle-fill text-info me-2 small"></i>Total Types Offered</span>
                                <span class="fw-bold"><?= $types ?></span>
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
    // 1. Line Revenue Chart with Progressive Animation
    const revCtx = document.getElementById('revenueChart').getContext('2d');
    
    // Animation configuration for the line chart
    const totalDuration = 2000;
    const delayBetweenPoints = totalDuration / 12;
    const previousY = (ctx) => ctx.index === 0 ? ctx.chart.scales.y.getPixelForValue(100) : ctx.chart.getDatasetMeta(ctx.datasetIndex).data[ctx.index - 1].getProps(['y'], true).y;

    new Chart(revCtx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [{
                label: 'Revenue',
                data: <?= json_encode(array_values($monthlyRevenue)) ?>,
                borderColor: '#0f766e',
                backgroundColor: 'rgba(15, 118, 110, 0.05)',
                fill: true,
                tension: 0.4,
                borderWidth: 3,
                pointRadius: 4,
                pointBackgroundColor: '#0f766e'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animations: {
                x: {
                    type: 'number',
                    easing: 'linear',
                    duration: delayBetweenPoints,
                    from: NaN, 
                    delay(ctx) {
                        if (ctx.type !== 'data' || ctx.xStarted) return 0;
                        ctx.xStarted = true;
                        return ctx.index * delayBetweenPoints;
                    }
                },
                y: {
                    type: 'number',
                    easing: 'linear',
                    duration: delayBetweenPoints,
                    from: previousY,
                    delay(ctx) {
                        if (ctx.type !== 'data' || ctx.yStarted) return 0;
                        ctx.yStarted = true;
                        return ctx.index * delayBetweenPoints;
                    }
                }
            },
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f0f0f0' } },
                x: { grid: { display: false } }
            }
        }
    });

    // 2. Bar Occupancy Chart with Delayed Stagger Animation
    const occCtx = document.getElementById('occupancyChart').getContext('2d');
    new Chart(occCtx, {
        type: 'bar',
        data: {
            labels: ['Total', 'Available', 'Occupied', 'Pending'],
            datasets: [{
                data: [<?= $total ?>, <?= $available ?>, <?= $occupied ?>, <?= $state['Pending'] ?? 0 ?>],
                backgroundColor: ['#12233a', '#0f766e', '#dc3545', '#fbbf24'],
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                delay: (context) => {
                    let delay = 0;
                    if (context.type === 'data' && context.mode === 'default') {
                        delay = context.dataIndex * 300; // 300ms delay between each bar
                    }
                    return delay;
                },
            },
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f0f0f0' } },
                x: { grid: { display: false } }
            }
        }
    });
});
</script>

<?php require '../includes/footer.php'; ?>