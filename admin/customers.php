<?php
require '../includes/auth.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $id = (int) post('user_id');
    $status = post('status');

    if (in_array($status, ['active', 'inactive'], true)) {
        db()->prepare(
            "UPDATE users 
             SET status=? 
             WHERE user_id=? 
             AND role='customer'"
        )->execute([$status, $id]);
    }

    redirect('admin/customers.php');
}

$q = trim($_GET['q'] ?? '');

$s = db()->prepare(
    "SELECT u.*, c.phone, c.address
     FROM users u
     JOIN customers c ON c.user_id = u.user_id
     WHERE u.role='customer'
     AND (u.name LIKE ? OR u.email LIKE ?)
     ORDER BY u.created_at DESC"
);

$s->execute(["%$q%", "%$q%"]);
$rows = $s->fetchAll();

$pageTitle = 'Customer Management';

require '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">

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

                <a href="rooms.php" class="dashboard-menu-item">
                    <i class="bi bi-door-open"></i>
                    <span>Rooms</span>
                </a>

                <a href="drivers.php" class="dashboard-menu-item">
                    <i class="bi bi-car-front"></i>
                    <span>Drivers</span>
                </a>

                <a href="customers.php" class="dashboard-menu-item active">
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

            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-3">

                <div>
                    <h2 class="mb-1">Customer Management</h2>

                    <p class="text-muted mb-0">
                        Manage registered customers and their account status.
                    </p>
                </div>

            </div>


            <!-- Search -->
            <form method="get" class="mb-4">

                <div class="input-group">

                    <input
                        type="text"
                        class="form-control"
                        name="q"
                        placeholder="Search customer"
                        value="<?= e($q) ?>"
                    >

                    <button
                        type="submit"
                        class="btn btn-outline-primary"
                    >
                        <i class="bi bi-search me-1"></i>
                        Search
                    </button>

                </div>

            </form>


            <!-- Customer Table -->
            <div class="card">

                <div class="card-header bg-white">

                    <h5 class="mb-0">
                        Customers
                    </h5>

                </div>

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Address</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if ($rows): ?>

                                <?php foreach ($rows as $r): ?>

                                    <tr>

                                        <!-- Name -->
                                        <td>
                                            <strong>
                                                <?= e($r['name']) ?>
                                            </strong>

                                            <br>

                                            <small class="text-muted">
                                                <?= e($r['email']) ?>
                                            </small>
                                        </td>


                                        <!-- Contact -->
                                        <td>
                                            <?= e($r['phone']) ?>
                                        </td>


                                        <!-- Address -->
                                        <td>
                                            <?= e($r['address']) ?>
                                        </td>


                                        <!-- Status -->
                                        <td>
                                            <?= status_badge($r['status']) ?>
                                        </td>


                                        <!-- Action -->
                                        <td>

                                            <form
                                                method="post"
                                                class="d-flex gap-2"
                                            >

                                                <?= csrf_field() ?>

                                                <input
                                                    type="hidden"
                                                    name="user_id"
                                                    value="<?= e($r['user_id']) ?>"
                                                >

                                                <select
                                                    class="form-select form-select-sm"
                                                    name="status"
                                                    style="width: 120px;"
                                                >

                                                    <option
                                                        value="active"
                                                        <?= $r['status'] === 'active' ? 'selected' : '' ?>
                                                    >
                                                        Active
                                                    </option>

                                                    <option
                                                        value="inactive"
                                                        <?= $r['status'] === 'inactive' ? 'selected' : '' ?>
                                                    >
                                                        Inactive
                                                    </option>

                                                </select>

                                                <button
                                                    type="submit"
                                                    class="btn btn-sm btn-primary"
                                                >
                                                    Save
                                                </button>

                                            </form>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>
                                    <td
                                        colspan="5"
                                        class="text-center text-muted py-4"
                                    >
                                        <i class="bi bi-people fs-3 d-block mb-2"></i>
                                        No customers found.
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

<?php require '../includes/footer.php'; ?>