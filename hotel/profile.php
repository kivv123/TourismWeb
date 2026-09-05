<?php require '../includes/auth.php';
require_role('hotel');
$hid = get_entity_id('hotel', (int)current_user()['user_id']);
$s = db()->prepare('SELECT * FROM hotels WHERE hotel_id=?');
$s->execute([$hid]);
$h = $s->fetch();
if (!$h) exit('Hotel profile unavailable.');
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fields = ['hotel_name', 'description', 'address', 'city', 'phone', 'email'];
    foreach ($fields as $f) $$f = post($f);
    if (!$hotel_name || !$address || !$city || !$phone || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Complete all required fields.';
    if (!$errors) {
        try {
            $image = upload_image('image', 'hotels') ?: $h['image'];
            db()->prepare('UPDATE hotels SET hotel_name=?,description=?,address=?,city=?,phone=?,email=?,image=? WHERE hotel_id=?')->execute([$hotel_name, $description, $address, $city, $phone, $email, $image, $hid]);
            flash('success', 'Hotel profile updated.');
            redirect('hotel/profile.php');
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}
$pageTitle = 'Hotel profile';
require '../includes/header.php'; ?><div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card card-body">
                <h2>Hotel profile</h2><?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= e($e) ?></div><?php endforeach; ?><form method="post" enctype="multipart/form-data"><?= csrf_field() ?><div class="row g-3"><?php foreach (['hotel_name' => 'Hotel name', 'address' => 'Address', 'city' => 'City', 'phone' => 'Phone', 'email' => 'Email'] as $f => $label): ?><div class="col-md-6"><label class="form-label"><?= $label ?></label><input class="form-control" name="<?= $f ?>" value="<?= e($h[$f]) ?>" required></div><?php endforeach; ?><div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="4"><?= e($h['description']) ?></textarea></div>
                        <div class="col-12"><label class="form-label">Image</label><input class="form-control" type="file" name="image" accept="image/png,image/jpeg,image/webp"></div>
                        <div><button class="btn btn-primary">Save changes</button></div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div><?php require '../includes/footer.php'; ?>