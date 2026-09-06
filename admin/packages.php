<?php require '../includes/auth.php';
require_role('admin');
$edit = null;
$errors = [];
if (isset($_GET['edit'])) {
    $s = db()->prepare('SELECT * FROM packages WHERE package_id=?');
    $s->execute([(int)$_GET['edit']]);
    $edit = $s->fetch();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('action');
    $id = (int)post('id');
    if ($action === 'deactivate') db()->prepare("UPDATE packages SET status='inactive' WHERE package_id=?")->execute([$id]);
    else {
        $dest = (int)post('destination_id');
        $name = post('package_name');
        $description = post('description');
        $days = (int)post('duration_days');
        $nights = (int)post('duration_nights');
        $price = (float)post('price_per_person');
        $max = (int)post('max_travelers');
        $type = post('package_type');
        if (!$dest || !$name || !$description || $days < 1 || $nights < 0 || $price <= 0 || $max < 1 || !$type) $errors[] = 'Complete valid package details.';
        else {
            try {
                $image = upload_image('image', 'packages') ?: ($edit['image'] ?? null);
                $data = [$dest, $name, $description, $days, $nights, $price, $max, $type, post('included_services'), post('excluded_services'), post('itinerary'), $image, post('status', 'active')];
                if ($action === 'save') {
                    $data[] = $id;
                    db()->prepare('UPDATE packages SET destination_id=?,package_name=?,description=?,duration_days=?,duration_nights=?,price_per_person=?,max_travelers=?,package_type=?,included_services=?,excluded_services=?,itinerary=?,image=?,status=? WHERE package_id=?')->execute($data);
                } else db()->prepare('INSERT INTO packages(destination_id,package_name,description,duration_days,duration_nights,price_per_person,max_travelers,package_type,included_services,excluded_services,itinerary,image,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute($data);
                flash('success', 'Package saved.');
                redirect('admin/packages.php');
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
    }
}
$dests = db()->query("SELECT destination_id,name FROM destinations WHERE status='active' ORDER BY name")->fetchAll();
$rows = db()->query('SELECT p.*,d.name destination_name FROM packages p JOIN destinations d ON d.destination_id=p.destination_id ORDER BY p.created_at DESC')->fetchAll();
$pageTitle = 'Package Management';
require '../includes/header.php'; ?>

<div class="dashboard-layout">
    <aside class="dashboard-sidebar">
        <nav class="dashboard-menu">
            <a href="dashboard.php" class="dashboard-menu-item"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
            <a href="destinations.php" class="dashboard-menu-item"><i class="bi bi-geo-alt"></i><span>Destinations</span></a>
            <a href="packages.php" class="dashboard-menu-item active"><i class="bi bi-map"></i><span>Packages</span></a>
            <a href="hotels.php" class="dashboard-menu-item"><i class="bi bi-building"></i><span>Hotels</span></a>
            <a href="rooms.php" class="dashboard-menu-item"><i class="bi bi-door-open"></i><span>Rooms</span></a>
            <a href="drivers.php" class="dashboard-menu-item"><i class="bi bi-car-front"></i><span>Drivers</span></a>
            <a href="customers.php" class="dashboard-menu-item"><i class="bi bi-people"></i><span>Customers</span></a>
            <div class="dashboard-menu-divider"></div>
            <a href="payments.php" class="dashboard-menu-item"><i class="bi bi-wallet2"></i><span>Payments</span></a>
            <a href="messages.php" class="dashboard-menu-item"><i class="bi bi-envelope"></i><span>Messages</span></a>
        </nav>
    </aside>

    <main class="dashboard-content">
        <div class="mb-4">
            <h2 class="h3"><?= $pageTitle ?></h2>
            <p class="text-muted">Create and manage travel packages for customers.</p>
        </div>

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card card-body shadow-sm">
                    <h5 class="mb-3"><?= $edit ? 'Edit' : 'Add' ?> Package</h5>
                    <?php foreach ($errors as $e): ?><div class="alert alert-danger py-2 small"><?= e($e) ?></div><?php endforeach; ?>
                    
                    <form method="post" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="<?= $edit ? 'save' : 'create' ?>">
                        <input type="hidden" name="id" value="<?= $edit['package_id'] ?? '' ?>">
                        
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Destination</label>
                            <select class="form-select form-select-sm" name="destination_id" required>
                                <option value="">Select Destination</option>
                                <?php foreach ($dests as $d): ?>
                                    <option value="<?= $d['destination_id'] ?>" <?= ($edit['destination_id'] ?? 0) == $d['destination_id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-2">
                            <label class="form-label small fw-bold">Package Name</label>
                            <input class="form-control form-control-sm" name="package_name" value="<?= e($edit['package_name'] ?? '') ?>" required>
                        </div>

                        <div class="row g-2">
                            <div class="col-6 mb-2">
                                <label class="form-label small fw-bold">Days</label>
                                <input type="number" class="form-control form-control-sm" name="duration_days" value="<?= e($edit['duration_days'] ?? '') ?>" required>
                            </div>
                            <div class="col-6 mb-2">
                                <label class="form-label small fw-bold">Nights</label>
                                <input type="number" class="form-control form-control-sm" name="duration_nights" value="<?= e($edit['duration_nights'] ?? '') ?>" required>
                            </div>
                            <div class="col-6 mb-2">
                                <label class="form-label small fw-bold">Price / Person</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" name="price_per_person" value="<?= e($edit['price_per_person'] ?? '') ?>" required>
                            </div>
                            <div class="col-6 mb-2">
                                <label class="form-label small fw-bold">Max Travelers</label>
                                <input type="number" class="form-control form-control-sm" name="max_travelers" value="<?= e($edit['max_travelers'] ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label small fw-bold">Package Type</label>
                            <input class="form-control form-control-sm" name="package_type" placeholder="e.g. Luxury, Adventure" value="<?= e($edit['package_type'] ?? '') ?>" required>
                        </div>

                        <div class="mb-2">
                            <label class="form-label small fw-bold">Description</label>
                            <textarea class="form-control form-control-sm" name="description" rows="2" required><?= e($edit['description'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-2">
                            <label class="form-label small fw-bold">Itinerary</label>
                            <textarea class="form-control form-control-sm" name="itinerary" rows="2"><?= e($edit['itinerary'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-2">
                            <label class="form-label small fw-bold">Image</label>
                            <input type="file" class="form-control form-control-sm" name="image" accept="image/*">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="active" <?= ($edit['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= ($edit['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>

                        <button class="btn btn-primary w-100">Save Package</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card shadow-sm">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th class="ps-3">Package</th>
                                    <th>Destination</th>
                                    <th>Price</th>
                                    <th class="text-end pe-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-bold"><?= e($r['package_name']) ?></div>
                                        <?= status_badge($r['status']) ?>
                                    </td>
                                    <td class="small"><?= e($r['destination_name']) ?></td>
                                    <td class="fw-bold text-primary"><?= money($r['price_per_person']) ?></td>
                                    <td class="text-end pe-3">
                                        <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $r['package_id'] ?>">Edit</a>
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