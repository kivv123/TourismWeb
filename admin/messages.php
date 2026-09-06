<?php require '../includes/auth.php';
require_role('admin');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)post('id');
    if (post('action') === 'delete') db()->prepare('DELETE FROM contact_messages WHERE message_id=?')->execute([$id]);
    else db()->prepare('UPDATE contact_messages SET is_read=NOT is_read WHERE message_id=?')->execute([$id]);
    redirect('admin/messages.php');
}
$rows = db()->query('SELECT * FROM contact_messages ORDER BY created_at DESC')->fetchAll();
$pageTitle = 'Contact Messages';
require '../includes/header.php'; ?>

<div class="dashboard-layout">
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
            <a href="customers.php" class="dashboard-menu-item">
                <i class="bi bi-people"></i>
                <span>Customers</span>
            </a>
            <div class="dashboard-menu-divider"></div>
            <a href="payments.php" class="dashboard-menu-item">
                <i class="bi bi-wallet2"></i>
                <span>Payments</span>
            </a>
            <a href="messages.php" class="dashboard-menu-item active">
                <i class="bi bi-envelope"></i>
                <span>Messages</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="dashboard-content">
        <div class="mb-4">
            <h2 class="mb-1">Contact Messages</h2>
            <p class="text-muted">Inquiries and messages from website visitors.</p>
        </div>

        <div class="card shadow-sm">
            <div class="table-responsive" style="max-height: 600px;">
                <table class="table align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-3">Sender</th>
                            <th>Subject</th>
                            <th>Message Preview</th>
                            <th>Received</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                        <tr class="<?= $r['is_read'] ? '' : 'bg-light border-start border-4 border-primary' ?>">
                            <td class="ps-3">
                                <div class="fw-bold"><?= e($r['name']) ?></div>
                                <div class="small text-muted"><?= e($r['email']) ?></div>
                                <div class="small text-muted"><?= e($r['phone']) ?></div>
                            </td>
                            <td>
                                <span class="fw-semibold"><?= e($r['subject']) ?></span>
                                <?php if (!$r['is_read']): ?>
                                    <span class="badge bg-primary ms-1">New</span>
                                <?php endif; ?>
                            </td>
                            <td class="small" style="max-width: 300px;">
                                <?= e(mb_strimwidth($r['message'], 0, 100, '…')) ?>
                            </td>
                            <td class="small text-muted">
                                <?= date('M d, Y', strtotime($r['created_at'])) ?><br>
                                <?= date('H:i', strtotime($r['created_at'])) ?>
                            </td>
                            <td class="text-end pe-3">
                                <form method="post" class="d-inline-flex gap-1">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= $r['message_id'] ?>">
                                    <button name="action" value="toggle" class="btn btn-sm <?= $r['is_read'] ? 'btn-light border' : 'btn-primary' ?>" title="Mark as <?= $r['is_read'] ? 'Unread' : 'Read' ?>">
                                        <i class="bi <?= $r['is_read'] ? 'bi-envelope' : 'bi-envelope-open' ?>"></i>
                                    </button>
                                    <button name="action" value="delete" data-confirm="Delete message?" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="bi bi-chat-dots fs-1 d-block mb-2 opacity-25"></i>
                                No messages received.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php require '../includes/footer.php'; ?>