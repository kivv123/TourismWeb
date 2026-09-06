<?php require '../includes/auth.php';
require_role('admin');
$edit = null;
$errors = [];
if (isset($_GET['edit'])) {
    $s = db()->prepare('SELECT * FROM destinations WHERE destination_id=?');
    $s->execute([(int)$_GET['edit']]);
    $edit = $s->fetch();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('action');
    $id = (int)post('id');
    if ($action === 'deactivate') db()->prepare("UPDATE destinations SET status='inactive' WHERE destination_id=?")->execute([$id]);
    else {
        foreach (['name', 'city', 'region', 'description', 'attractions', 'activities', 'best_time_to_visit'] as $f) $$f = post($f);
        $budget = (float)post('estimated_budget');
        if (!$name || !$city || !$region || !$description || $budget < 0) $errors[] = 'Complete all required destination fields.';
        else {
            try {
                $image = upload_image('image', 'destinations') ?: ($edit['image'] ?? null);
                $data = [$name, $city, $region, $description, $attractions, $activities, $best_time_to_visit, $budget, $image, post('status', 'active')];
                if ($action === 'save') {
                    $data[] = $id;
                    db()->prepare('UPDATE destinations SET name=?,city=?,region=?,description=?,attractions=?,activities=?,best_time_to_visit=?,estimated_budget=?,image=?,status=? WHERE destination_id=?')->execute($data);
                } else db()->prepare('INSERT INTO destinations(name,city,region,description,attractions,activities,best_time_to_visit,estimated_budget,image,status) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute($data);
                flash('success', 'Destination saved.');
                redirect('admin/destinations.php');
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
    }
}
$rows = db()->query('SELECT * FROM destinations ORDER BY created_at DESC')->fetchAll();
$pageTitle = 'Destination Management';
require '../includes/header.php'; ?>

<div class="dashboard-layout">
    <!-- Admin Sidebar -->
    <aside class="dashboard-sidebar">
        <nav class="dashboard-menu">
            <a href="dashboard.php" class="dashboard-menu-item">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
            <a href="destinations.php" class="dashboard-menu-item active">
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
    <main class="dashboard-content">
        <div class="mb-4">
            <h2 class="mb-1"><?= $pageTitle ?></h2>
            <p class="text-muted">Manage your travel destinations and locations.</p>
        </div>

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card card-body shadow-sm">
                    <h5 class="card-title mb-3"><?= $edit ? 'Edit' : 'Add New' ?> Destination</h5>
                    
                    <?php foreach ($errors as $e): ?>
                        <div class="alert alert-danger py-2"><?= e($e) ?></div>
                    <?php endforeach; ?>

                    <form method="post" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="<?= $edit ? 'save' : 'create' ?>">
                        <input type="hidden" name="id" value="<?= $edit['destination_id'] ?? '' ?>">
                        
                        <div class="row g-2">
                            <?php foreach (['name' => 'Name', 'city' => 'City', 'region' => 'Region'] as $f => $l): ?>
                            <div class="col-12 mb-2">
                                <label class="form-label small"><?= $l ?></label>
                                <input class="form-control" name="<?= $f ?>" value="<?= e($edit[$f] ?? '') ?>" required>
                            </div>
                            <?php endforeach; ?>

                            <div class="col-md-6 mb-2">
                                <label class="form-label small">Best time to visit</label>
                                <input class="form-control" name="best_time_to_visit" value="<?= e($edit['best_time_to_visit'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label small">Estimated budget</label>
                                <input class="form-control" name="estimated_budget" type="number" min="0" step="0.01" value="<?= e($edit['estimated_budget'] ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label small">Description</label>
                            <textarea class="form-control" name="description" rows="3" required><?= e($edit['description'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="mb-2">
                            <label class="form-label small">Image</label>
                            <input type="file" name="image" class="form-control" accept="image/*">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" <?= ($edit['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= ($edit['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>

                        <button class="btn btn-primary w-100">Save Destination</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card shadow-sm">
                    <div class="table-responsive" style="max-height: 600px;">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Destination</th>
                                    <th>Location</th>
                                    <th>Budget</th>
                                    <th>Status</th>
                                    <th class="text-end pe-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td class="ps-3 fw-bold"><?= e($r['name']) ?></td>
                                    <td class="small"><?= e($r['city']) ?>, <?= e($r['region']) ?></td>
                                    <td><?= money($r['estimated_budget']) ?></td>
                                    <td><?= status_badge($r['status']) ?></td>
                                    <td class="text-end pe-3">
                                        <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $r['destination_id'] ?>">Edit</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require '../includes/footer.php'; ?>