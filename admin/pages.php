<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Core/Autoloader.php';
require dirname(__DIR__) . '/app/Core/Bootstrap.php';
use App\Core\Bootstrap;
use App\Security\Auth;
use App\Security\Csrf;
use App\Database\Connection;
Bootstrap::init();
Auth::requireLogin();
$pdo = Connection::get();
$msg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Csrf::validateRequest()) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'toggle' && $id) {
        $st = $pdo->prepare('SELECT status FROM pages WHERE id=?');
        $st->execute([$id]);
        $new = $st->fetchColumn() === 'active' ? 'disabled' : 'active';
        $pdo->prepare('UPDATE pages SET status=? WHERE id=?')->execute([$new, $id]);
        $msg = 'وضعیت صفحه تغییر کرد.';
    }
    if ($action === 'delete' && $id) {
        $pdo->prepare('DELETE FROM pages WHERE id=?')->execute([$id]);
        $msg = 'صفحه حذف شد.';
    }
}
$pages = $pdo->query('SELECT p.*, (SELECT COUNT(*) FROM page_fields f WHERE f.page_id=p.id) AS field_count FROM pages p ORDER BY p.id DESC')->fetchAll(PDO::FETCH_ASSOC);
$pageTitle = 'صفحات پرداخت';
include '_layout_header.php';
?>
<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<p><a class="btn btn-primary" href="page-create.php">+ صفحه جدید</a></p>
<table>
<thead><tr><th>عنوان</th><th>Slug</th><th>فیلدها</th><th>وضعیت</th><th></th></tr></thead>
<tbody>
<?php foreach ($pages as $p): ?>
<tr>
<td><?= htmlspecialchars($p['title']) ?></td>
<td><code><?= htmlspecialchars($p['slug']) ?></code></td>
<td><?= (int)$p['field_count'] ?></td>
<td><?= htmlspecialchars($p['status']) ?></td>
<td>
<a href="page-edit.php?id=<?= (int)$p['id'] ?>">ویرایش</a>
<a href="form-builder.php?page_id=<?= (int)$p['id'] ?>">فرم</a>
<form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
<button name="action" value="toggle">toggle</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php include '_layout_footer.php'; ?>
