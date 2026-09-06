<?php require_once __DIR__ . '/auth.php';
 $pageTitle = $pageTitle ?? 'Myanmar Horizons'; ?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="icon" type="image/png" href="<?= url('../assets/img/logo.jpg') ?>">
    <title><?= e($pageTitle) ?> | Myanmar Horizons</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= url('/assets/css/style.css') ?>" rel="stylesheet">
</head>

<body>
    <?php require __DIR__ . '/navbar.php'; ?>
    <main><?php if ($m = flash('success')): ?><div class="container pt-3">
                <div class="alert alert-success"><?= e($m) ?></div>
            </div><?php endif;
                if ($m = flash('error')): ?><div class="container pt-3">
                <div class="alert alert-danger"><?= e($m) ?></div>
            </div><?php endif;
                if ($m = flash('warning')): ?><div class="container pt-3">
                <div class="alert alert-warning"><?= e($m) ?></div>
            </div><?php endif; ?>