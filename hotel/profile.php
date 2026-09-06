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
$pageTitle = 'Hotel Profile';
require '../includes/header.php'; ?>

<div class="dashboard-layout">
    <!-- Hotel Sidebar -->
    <aside class="dashboard-sidebar">
        <nav class="dashboard-menu">
            <a href="dashboard.php" class="dashboard-menu-item">
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
            <a href="profile.php" class="dashboard-menu-item active">
                <i class="bi bi-building"></i>
                <span>Hotel Profile</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="dashboard-content">
        <div class="mb-4">
            <h2 class="mb-1">Hotel Profile</h2>
            <p class="text-muted">Update your hotel information and contact details.</p>
        </div>

        <div class="row">
            <div class="col-xl-8">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <?php foreach ($errors as $e): ?>
                            <div class="alert alert-danger py-2 small"><?= e($e) ?></div>
                        <?php endforeach; ?>

                        <form method="post" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            
                            <div class="row g-3">
                                <div class="col-12 mb-2">
                                    <label class="form-label small fw-bold">Hotel Name</label>
                                    <input class="form-control" name="hotel_name" value="<?= e($h['hotel_name']) ?>" required>
                                </div>

                                <?php foreach (['email' => 'Email', 'phone' => 'Phone'] as $f => $label): ?>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label small fw-bold"><?= $label ?></label>
                                    <input class="form-control" name="<?= $f ?>" value="<?= e($h[$f]) ?>" required>
                                </div>
                                <?php endforeach; ?>

                                <?php foreach (['city' => 'City', 'address' => 'Address'] as $f => $label): ?>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label small fw-bold"><?= $label ?></label>
                                    <input class="form-control" name="<?= $f ?>" value="<?= e($h[$f]) ?>" required>
                                </div>
                                <?php endforeach; ?>

                                <div class="col-12 mb-2">
                                    <label class="form-label small fw-bold">Description</label>
                                    <textarea class="form-control" name="description" rows="4"><?= e($h['description']) ?></textarea>
                                </div>

                                <div class="col-12 mb-4">
                                    <label class="form-label small fw-bold">Hotel Cover Image</label>
                                    <?php if ($h['image']): ?>
                                        <div class="mb-2">
                                            <img src="<?= url('uploads/hotels/' . $h['image']) ?>" alt="Preview" class="rounded shadow-sm" style="height: 100px; width: 150px; object-fit: cover;">
                                        </div>
                                    <?php endif; ?>
                                    <input class="form-control" type="file" name="image" accept="image/png,image/jpeg,image/webp">
                                    <div class="form-text small text-muted">Upload a high-quality photo of your hotel exterior or lobby.</div>
                                </div>

                                <div class="col-12">
                                    <button class="btn btn-primary px-4 py-2">
                                        <i class="bi bi-check-circle me-2"></i>Save Profile Changes
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Optional Help Column -->
            <div class="col-xl-4 d-none d-xl-block">
                <div class="card bg-light border-0">
                    <div class="card-body">
                        <h6 class="fw-bold"><i class="bi bi-info-circle me-2"></i>Profile Tips</h6>
                        <p class="small text-muted mb-0">
                            Make sure your email and phone number are correct, as these are used for booking confirmations and customer inquiries.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require '../includes/footer.php'; ?>