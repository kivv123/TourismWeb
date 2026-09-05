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
$pageTitle = 'Contact messages';
require '../includes/header.php'; ?><div class="container py-5">
    <h2>Contact messages</h2>
    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>From</th>
                        <th>Subject</th>
                        <th>Message</th>
                        <th>Received</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody><?php foreach ($rows as $r): ?><tr class="<?= $r['is_read'] ? '' : 'table-warning' ?>">
                            <td><?= e($r['name']) ?><br><small><?= e($r['email']) ?><br><?= e($r['phone']) ?></small></td>
                            <td><?= e($r['subject']) ?></td>
                            <td><?= e(mb_strimwidth($r['message'], 0, 130, '…')) ?></td>
                            <td><?= e($r['created_at']) ?></td>
                            <td>
                                <form method="post" class="d-flex gap-1"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['message_id'] ?>"><button name="action" value="toggle" class="btn btn-sm btn-outline-primary"><?= $r['is_read'] ? 'Unread' : 'Read' ?></button><button name="action" value="delete" data-confirm="Delete message?" class="btn btn-sm btn-outline-danger">Delete</button></form>
                            </td>
                        </tr><?php endforeach; ?></tbody>
            </table>
        </div>
    </div>
</div><?php require '../includes/footer.php'; ?>