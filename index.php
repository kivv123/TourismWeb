<?php $pageTitle = 'Discover Myanmar';
require 'includes/header.php';
$destinations = db()->query("SELECT * FROM destinations WHERE status='active' ORDER BY popularity DESC LIMIT 3")->fetchAll();
$packages = db()->query("SELECT p.*,d.name destination_name FROM packages p JOIN destinations d ON d.destination_id=p.destination_id WHERE p.status='active' ORDER BY p.created_at DESC LIMIT 3")->fetchAll(); ?>
<section class="hero">
    <div class="container">
        <div class="col-lg-8">
            <p class="text-warning fw-bold text-uppercase">Explore Myanmar with confidence</p>
            <h1>Discover Your Next Adventure</h1>
            <p class="lead mb-4">Curated journeys, welcoming stays and reliable local transport — all in one place.</p>
            <form class="search-panel row g-2" action="<?= url('destinations.php') ?>" method="get">
                <div class="col-md-9"><input class="form-control form-control-lg border-0" name="q" placeholder="Search Bagan, Inle Lake, Yangon…"></div>
                <div class="col-md-3 d-grid"><button class="btn btn-warning btn-lg fw-semibold"><i class="bi bi-search me-1"></i>Search</button></div>
            </form>
        </div>
    </div>
</section>
<section class="container py-5">
    <div class="d-flex justify-content-between align-items-end mb-4">
        <div>
            <p class="section-kicker mb-1">Inspiring places</p>
            <h2>Popular destinations</h2>
        </div><a href="<?= url('destinations.php') ?>" class="btn btn-outline-primary">View all</a>
    </div>
    <div class="row g-4"><?php foreach ($destinations as $d): ?><div class="col-md-4">
                <div class="card min-vh-card"><?php if ($d['image']): ?><img src="<?= url($d['image']) ?>" class="card-img-top" alt="<?= e($d['name']) ?>"><?php else: ?><div class="placeholder-image"><i class="bi bi-geo-alt"></i></div><?php endif; ?><div class="card-body d-flex flex-column"><small class="text-muted"><i class="bi bi-pin-map"></i> <?= e($d['city']) ?>, <?= e($d['region']) ?></small>
                        <h4 class="mt-2"><?= e($d['name']) ?></h4>
                        <p class="text-muted"><?= e(mb_strimwidth($d['description'], 0, 115, '…')) ?></p><a class="btn btn-primary mt-auto" href="<?= url('destination-details.php?id=' . $d['destination_id']) ?>">View details</a>
                    </div>
                </div>
            </div><?php endforeach; ?></div>
</section>
<section class="bg-white py-5">
    <div class="container">
        <p class="section-kicker text-center mb-1">Travel made simple</p>
        <h2 class="text-center mb-4">Popular travel packages</h2>
        <div class="row g-4"><?php foreach ($packages as $p): ?><div class="col-md-4">
                    <div class="card min-vh-card"><?php if ($p['image']): ?><img src="<?= url($p['image']) ?>" class="card-img-top" alt="<?= e($p['package_name']) ?>"><?php else: ?><div class="placeholder-image"><i class="bi bi-airplane"></i></div><?php endif; ?><div class="card-body d-flex flex-column"><span class="badge text-bg-light align-self-start"><?= e($p['package_type']) ?></span>
                            <h4 class="mt-2"><?= e($p['package_name']) ?></h4>
                            <p class="text-muted mb-2"><?= e($p['destination_name']) ?> · <?= e($p['duration_days']) ?> days</p>
                            <h5 class="text-primary mb-3">From <?= money($p['price_per_person']) ?></h5><a class="btn btn-primary mt-auto" href="<?= url('package-details.php?id=' . $p['package_id']) ?>">View package</a>
                        </div>
                    </div>
                </div><?php endforeach; ?></div>
    </div>
</section>
<section class="container py-5">
    <p class="section-kicker text-center">Why Myanmar Horizons</p>
    <div class="row g-3 text-center"><?php foreach ([['shield-check', 'Trusted service'], ['wallet2', 'Fair package prices'], ['car-front', 'Professional drivers'], ['buildings', 'Quality hotels'], ['calendar-check', 'Easy booking'], ['headset', 'Human support']] as [$i, $t]): ?><div class="col-6 col-md-2"><i class="bi bi-<?= $i ?> fs-2 text-primary"></i>
                <p class="fw-semibold mb-0 mt-2"><?= $t ?></p>
            </div><?php endforeach; ?></div>
</section>
<section class="detail-hero text-center">
    <div class="container">
        <h2>Start your journey today</h2>
        <p class="lead">Find the Myanmar experience that fits your pace.</p><a href="<?= url('packages.php') ?>" class="btn btn-warning btn-lg">Explore packages</a>
    </div>
</section><?php require 'includes/footer.php'; ?>