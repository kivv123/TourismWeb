<?php
require '../includes/auth.php';

require_role('admin');

$queries = [
    'Customers' => "SELECT COUNT(*) FROM users WHERE role = 'customer'",
    'Drivers' => "SELECT COUNT(*) FROM drivers WHERE status = 'active'",
    'Hotels' => "SELECT COUNT(*) FROM hotels WHERE status = 'active'",
    'Destinations' => "SELECT COUNT(*) FROM destinations WHERE status = 'active'",
    'Packages' => "SELECT COUNT(*) FROM packages WHERE status = 'active'",
    'Bookings' => "SELECT COUNT(*) FROM bookings",
    'Pending Bookings' => "SELECT COUNT(*) FROM bookings WHERE booking_status = 'Pending'",
    'Pending Payments' => "SELECT COUNT(*) FROM payments WHERE payment_status = 'Pending'"
];

$stats = [];

foreach ($queries as $label => $query) {
    $stats[$label] = db()->query($query)->fetchColumn();
}

$recent = db()->query(
    'SELECT
        b.*,
        u.name,
        p.package_name
     FROM bookings b
     JOIN customers c ON c.customer_id = b.customer_id
     JOIN users u ON u.user_id = c.user_id
     JOIN packages p ON p.package_id = b.package_id
     ORDER BY b.created_at DESC
     LIMIT 8'
)->fetchAll();

$paymentChart = db()->query(
    "SELECT
        pay.payment_id,
        pay.booking_id,
        pay.amount,
        pay.payment_method,
        pay.payment_status,
        pay.transaction_reference,
        pay.paid_at,
        u.name AS customer_name,
        u.email AS customer_email,
        p.package_name,
        b.start_date,
        b.end_date
     FROM payments pay
     JOIN bookings b
        ON b.booking_id = pay.booking_id
     JOIN customers c
        ON c.customer_id = b.customer_id
     JOIN users u
        ON u.user_id = c.user_id
     JOIN packages p
        ON p.package_id = b.package_id
     WHERE pay.payment_status = 'Paid'
     ORDER BY pay.paid_at ASC, pay.payment_id ASC"
)->fetchAll();


$paymentLabels = [];
$paymentValues = [];
$paymentDetails = [];

foreach ($paymentChart as $payment) {

    // Name shown on the X-axis
    $paymentLabels[] = $payment['customer_name'];

    // Individual payment amount
    $paymentValues[] = (float) $payment['amount'];

    // Additional information for Chart.js tooltip
    $paymentDetails[] = [
        'payment_id' => $payment['payment_id'],
        'booking_id' => $payment['booking_id'],
        'customer_name' => $payment['customer_name'],
        'customer_email' => $payment['customer_email'],
        'package_name' => $payment['package_name'],
        'amount' => (float) $payment['amount'],
        'payment_method' => $payment['payment_method'],
        'payment_status' => $payment['payment_status'],
        'transaction_reference' => $payment['transaction_reference'],
        'paid_at' => $payment['paid_at'],
        'start_date' => $payment['start_date'],
        'end_date' => $payment['end_date']
    ];
}



$pageTitle = 'Admin Dashboard';

require '../includes/header.php';
?>

        <div class="container-fluid">
            <div class="row">

            <!-- Admin Sidebar -->
            <aside class="dashboard-sidebar">

                <nav class="dashboard-menu">

                    <a href="dashboard.php" class="dashboard-menu-item active">
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

                    <a href="rooms.php" class="dashboard-menu-item">
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
                <main class="col-md-9 col-lg-10 p-4">

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 class="mb-1">Admin Dashboard</h2>
                            <p class="text-muted mb-0">
                                Manage your travel booking system from the dashboard.
                            </p>
                        </div>
                    </div>

                    <!-- KEEP ALL YOUR EXISTING CONTENT BELOW -->

                    <div class="row g-3 mb-4">
                        <?php foreach ($stats as $label => $value): ?>
                            <div class="col-6 col-md-3">
                                <div class="card stat-card card-body">
                                    <small><?= e($label) ?></small>
                                    <h3 class="mb-0"><?= e((string) $value) ?></h3>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="container py-4">
                        <h5 class="mb-3">Payment Revenue</h5>

                        <div style="height: 350px;">
                            <canvas id="myChart"></canvas>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Recent Bookings</h5>
                        </div>

                        <div class="table-responsive" style="max-height: 600px;">
                            <table class="table mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Customer</th>
                                        <th>Package</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach ($recent as $booking): ?>
                                        <tr>
                                            <td><?= e((string) $booking['booking_id']) ?></td>
                                            <td><?= e($booking['name']) ?></td>
                                            <td><?= e($booking['package_name']) ?></td>
                                            <td><?= money($booking['total_amount']) ?></td>
                                            <td><?= status_badge($booking['booking_status']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php if (!$recent): ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">
                                                No bookings found.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </main>

            </div>
        </div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    const paymentLabels = <?= json_encode($paymentLabels) ?>;
    const paymentValues = <?= json_encode($paymentValues) ?>;
    const paymentDetails = <?= json_encode($paymentDetails) ?>;

    console.log("Payment labels:", paymentLabels);
    console.log("Payment values:", paymentValues);
    console.log("Payment details:", paymentDetails);

    const ctx = document.getElementById("myChart");

    new Chart(ctx, {
        type: "line",

        data: {
            labels: paymentLabels,

            datasets: [{
                label: "Revenue per Payment",

                data: paymentValues,

                borderColor: "#16a34a",
                backgroundColor: "rgba(22, 163, 74, 0.15)",

                borderWidth: 2,
                fill: true,
                tension: 0.3,

                pointRadius: 6,
                pointHoverRadius: 9,

                pointBackgroundColor: "#16a34a",
                pointBorderColor: "#ffffff",
                pointBorderWidth: 2
            }]

        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            interaction: {
                mode: "nearest",
                intersect: true
            },

            plugins: {

                legend: {
                    display: true
                },

                tooltip: {

                    callbacks: {

                        title: function(context) {
                            const index = context[0].dataIndex;
                            const payment = paymentDetails[index];

                            return payment.customer_name;
                        },

                        label: function(context) {
                            const index = context.dataIndex;
                            const payment = paymentDetails[index];

                            return [
                                "Revenue: " + Number(payment.amount).toLocaleString(),
                                "Package: " + payment.package_name,
                                "Payment Method: " + payment.payment_method,
                                "Status: " + payment.payment_status
                            ];
                        },

                        afterLabel: function(context) {
                            const index = context.dataIndex;
                            const payment = paymentDetails[index];

                            return [
                                "Payment ID: #" + payment.payment_id,
                                "Booking ID: #" + payment.booking_id,
                                "Email: " + payment.customer_email,
                                "Paid: " + payment.paid_at
                            ];
                        }
                    }
                }
            },

            scales: {

                y: {
                    beginAtZero: true,

                    title: {
                        display: true,
                        text: "Revenue"
                    },

                    ticks: {
                        callback: function(value) {
                            return Number(value).toLocaleString();
                        }
                    }
                },

                x: {
                    title: {
                        display: true,
                        text: "Customer"
                    }
                }
            }
        }
    });
</script>



<?php require '../includes/footer.php'; ?>