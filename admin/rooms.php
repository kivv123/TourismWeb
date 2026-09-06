<?php require '../includes/auth.php';
require_role('admin');
$rows = db()->query('SELECT r.*,h.hotel_name FROM rooms r JOIN hotels h ON h.hotel_id=r.hotel_id ORDER BY h.hotel_name,r.room_type')->fetchAll();
$pageTitle = 'All rooms';
require '../includes/header.php'; ?>

<div class="dashboard-layout">
    <!-- Admin Sidebar -->
    <aside class="dashboard-sidebar">
        <nav class="dashboard-menu">
            <a href="dashboard.php" class="dashboard-menu-item">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
            <a href="destinations.php" class="dashboard-menu-item">
                <i class="bi bi-geo-alt"></i>
                <span>Destinations</span>
            </a>
            <a href="packages.php" class="dashboard-menu-item">
                <i class="bi bi-map"></i>
                <span>Packages</span>
            </a>
            <a href="hotels.php" class="dashboard-menu-item">
                <i class="bi bi-building"></i>
                <span>Hotels</span>
            </a>
            <a href="rooms.php" class="dashboard-menu-item active">
                <i class="bi bi-door-open"></i>
                <span>Rooms</span>
            </a>
            <a href="drivers.php" class="dashboard-menu-item">
                <i class="bi bi-car-front"></i>
                <span>Drivers</span>
            </a>
            <a href="customers.php" class="dashboard-menu-item">
                <i class="bi bi-people"></i>
                <span>Customers</span>
            </a>
            <div class="dashboard-menu-divider"></div>
            <a href="payments.php" class="dashboard-menu-item">
                <i class="bi bi-wallet2"></i>
                <span>Payments</span>
            </a>
            <a href="messages.php" class="dashboard-menu-item">
                <i class="bi bi-envelope"></i>
                <span>Messages</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="dashboard-content">
        <div class="mb-4">
            <h2 class="mb-1">Hotel Rooms</h2>
            <p class="text-muted">Overview of all room types and availability across partner hotels.</p>
        </div>

        <div class="card shadow-sm">
           <div class="table-responsive" style="max-height: 600px;">
                <table class="table align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-3">Hotel</th>
                            <th>Room Type</th>
                            <th>Capacity</th>
                            <th>Price/Night</th>
                            <th>Availability</th>
                            <th class="pe-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                        <tr>
                            <td class="ps-3 fw-bold text-dark"><?= e($r['hotel_name']) ?></td>
                            <td><?= e($r['room_type']) ?></td>
                            <td>
                                <i class="bi bi-people me-1"></i><?= $r['capacity'] ?>
                            </td>
                            <td class="fw-bold text-primary"><?= money($r['price_per_night']) ?></td>
                            <td>
                                <span class="badge rounded-pill bg-light text-dark border">
                                    <?= $r['available_rooms'] ?> / <?= $r['total_rooms'] ?> Left
                                </span>
                            </td>
                            <td class="pe-3"><?= status_badge($r['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No rooms found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php require '../includes/footer.php'; ?>