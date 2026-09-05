<?php require '../includes/auth.php';
require_role('driver');
$did = get_entity_id('driver', (int)current_user()['user_id']);
$s = db()->prepare('SELECT d.*,u.email FROM drivers d JOIN users u ON u.user_id=d.user_id WHERE d.driver_id=?');
$s->execute([$did]);
$d = $s->fetch();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $phone = post('phone');
    $vehicle = post('vehicle_type');
    $reg = post('vehicle_registration');
    $cap = (int)post('vehicle_capacity');
    $availability = post('availability_status');
    if (!$phone || !$vehicle || !$reg || $cap < 1 || !in_array($availability, ['Available', 'Busy', 'Offline'], true)) $errors[] = 'Provide valid profile values.';
    if (!$errors) {
        try {
            $image = upload_image('profile_image', 'drivers') ?: $d['profile_image'];
            db()->prepare('UPDATE drivers SET phone=?,vehicle_type=?,vehicle_registration=?,vehicle_capacity=?,availability_status=?,profile_image=? WHERE driver_id=?')->execute([$phone, $vehicle, $reg, $cap, $availability, $image, $did]);
            flash('success', 'Driver profile updated.');
            redirect('driver/profile.php');
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}
$pageTitle = 'Driver profile';
require '../includes/header.php'; ?><div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card card-body">
                <h2><?= e($d['full_name']) ?></h2><?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= e($e) ?></div><?php endforeach; ?><form method="post" enctype="multipart/form-data"><?= csrf_field() ?><div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= e($d['phone']) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Availability</label><select class="form-select" name="availability_status"><?php foreach (['Available', 'Busy', 'Offline'] as $x): ?><option <?= $d['availability_status'] === $x ? 'selected' : '' ?>><?= $x ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">Vehicle type</label><input class="form-control" name="vehicle_type" value="<?= e($d['vehicle_type']) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Registration</label><input class="form-control" name="vehicle_registration" value="<?= e($d['vehicle_registration']) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Capacity</label><input type="number" class="form-control" name="vehicle_capacity" value="<?= $d['vehicle_capacity'] ?>"></div>
                        <div class="col-md-6"><label class="form-label">Profile photo</label><input type="file" class="form-control" name="profile_image" accept="image/png,image/jpeg,image/webp"></div>
                        <div><button class="btn btn-primary">Save profile</button></div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div><?php require '../includes/footer.php'; ?>