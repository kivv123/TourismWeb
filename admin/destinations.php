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
$pageTitle = 'Destination management';
require '../includes/header.php'; ?><div class="container py-5">
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card card-body">
                <h3><?= $edit ? 'Edit' : 'Add' ?> destination</h3><?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= e($e) ?></div><?php endforeach; ?><form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $edit ? 'save' : 'create' ?>"><input type="hidden" name="id" value="<?= $edit['destination_id'] ?? '' ?>"><?php foreach (['name' => 'Name', 'city' => 'City', 'region' => 'Region', 'best_time_to_visit' => 'Best time to visit', 'estimated_budget' => 'Estimated budget'] as $f => $l): ?><div class="mb-2"><label class="form-label"><?= $l ?></label><input class="form-control" name="<?= $f ?>" value="<?= e($edit[$f] ?? '') ?>" <?= $f === 'estimated_budget' ? 'type="number" min="0"' : '' ?> required></div><?php endforeach; ?><div class="mb-2"><label class="form-label">Description</label><textarea class="form-control" name="description" required><?= e($edit['description'] ?? '') ?></textarea></div>
                    <div class="mb-2"><label class="form-label">Attractions</label><textarea class="form-control" name="attractions"><?= e($edit['attractions'] ?? '') ?></textarea></div>
                    <div class="mb-2"><label class="form-label">Activities</label><textarea class="form-control" name="activities"><?= e($edit['activities'] ?? '') ?></textarea></div><input type="file" name="image" class="form-control mb-2" accept="image/png,image/jpeg,image/webp"><select name="status" class="form-select mb-2">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select><button class="btn btn-primary">Save destination</button>
                </form>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Location</th>
                                <th>Budget</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody><?php foreach ($rows as $r): ?><tr>
                                    <td><?= e($r['name']) ?></td>
                                    <td><?= e($r['city']) ?></td>
                                    <td><?= money($r['estimated_budget']) ?></td>
                                    <td><?= status_badge($r['status']) ?></td>
                                    <td><a class="btn btn-sm btn-outline-primary" href="?edit=<?= $r['destination_id'] ?>">Edit</a></td>
                                </tr><?php endforeach; ?></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div><?php require '../includes/footer.php'; ?>