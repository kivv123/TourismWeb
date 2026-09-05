<?php require 'includes/header.php';
$id = (int)($_GET['id'] ?? 0);
$s = db()->prepare("SELECT * FROM destinations WHERE destination_id=? AND status='active'");
$s->execute([$id]);
$d = $s->fetch();
if (!$d) {
    http_response_code(404);
    exit('Destination not found.');
}
$pageTitle = $d['name'];
$p = db()->prepare("SELECT * FROM packages WHERE destination_id=? AND status='active'");
$p->execute([$id]);
$packages = $p->fetchAll(); ?><section class="detail-hero">
    <div class="container">
        <p class="mb-1"><?= e($d['city']) ?>, <?= e($d['region']) ?></p>
        <h1><?= e($d['name']) ?></h1>
        <p class="lead"><?= e($d['description']) ?></p>
    </div>
</section>
<div class="container py-5">
    <div class="row g-4">
        <div class="col-lg-8">
            <h3>Highlights</h3>
            <p><?= nl2br(e($d['attractions'] ?: 'Discover local culture and remarkable scenery.')) ?></p>
            <h3>Things to do</h3>
            <p><?= nl2br(e($d['activities'] ?: 'Sightseeing and local experiences.')) ?></p>
        </div>
        <div class="col-lg-4">
            <div class="card card-body">
                <h5>Travel notes</h5>
                <p><b>Best time:</b> <?= e($d['best_time_to_visit'] ?: 'All year') ?></p>
                <p class="mb-0"><b>Estimated budget:</b> <?= money($d['estimated_budget']) ?></p>
            </div>
        </div>
    </div>
    <h3 class="mt-5">Packages in <?= e($d['name']) ?></h3>
    <div class="row g-3"><?php foreach ($packages as $x): ?><div class="col-md-6">
                <div class="card card-body">
                    <h5><?= e($x['package_name']) ?></h5>
                    <p><?= e($x['duration_days']) ?> days · <?= money($x['price_per_person']) ?> per person</p><a class="btn btn-outline-primary" href="<?= url('package-details.php?id=' . $x['package_id']) ?>">View package</a>
                </div>
            </div><?php endforeach; ?></div>
</div><?php require 'includes/footer.php'; ?>