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
$pageId = (int)($_GET['page_id'] ?? $_POST['page_id'] ?? 0);
$msg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Csrf::validateRequest()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' && $pageId) {
        $label = trim($_POST['label'] ?? '');
        $name = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($_POST['name'] ?? '')));
        $type = $_POST['type'] ?? 'text';
        if ($label && $name) {
            $pdo->prepare('INSERT INTO page_fields (page_id,label,name,type,required,sort_order,status) VALUES (?,?,?,?,?,?,?)')
                ->execute([$pageId, $label, $name, $type, isset($_POST['required']) ? 1 : 0, (int)($_POST['sort_order'] ?? 0), 'active']);
            $msg = 'فیلد اضافه شد';
        }
    }
    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM page_fields WHERE id=? AND page_id=?')->execute([(int)($_POST['id']??0), $pageId]);
        $msg = 'حذف شد';
    }
}
$page = null;
if ($pageId) {
    $st = $pdo->prepare('SELECT * FROM pages WHERE id=?');
    $st->execute([$pageId]);
    $page = $st->fetch(PDO::FETCH_ASSOC);
}
$fields = [];
if ($pageId) {
    $st = $pdo->prepare('SELECT * FROM page_fields WHERE page_id=? ORDER BY sort_order, id');
    $st->execute([$pageId]);
    $fields = $st->fetchAll(PDO::FETCH_ASSOC);
}
$pageTitle = 'فرم‌ساز';
include '_layout_header.php';
?>
<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if (!$page): ?><p>صفحه یافت نشد. از <a href="pages.php">لیست صفحات</a> انتخاب کنید.</p><?php else: ?>
<h3><?= htmlspecialchars($page['title']) ?></h3>
<table><?php foreach ($fields as $f): ?>
<tr><td><?= htmlspecialchars($f['label']) ?></td><td><code><?= htmlspecialchars($f['name']) ?></code></td><td><?= htmlspecialchars($f['type']) ?></td>
<td><form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="page_id" value="<?= $pageId ?>">
<input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
<button name="action" value="delete">حذف</button></form></td></tr>
<?php endforeach; ?></table>
<form method="post"><?= csrf_field() ?><input type="hidden" name="page_id" value="<?= $pageId ?>">
<input type="hidden" name="action" value="add">
<label>برچسب</label><input name="label" required>
<label>name</label><input name="name" required pattern="[a-z0-9_]+">
<label>نوع</label><select name="type"><option>text</option><option>textarea</option><option>email</option><option>tel</option><option>number</option><option>select</option></select>
<label><input type="checkbox" name="required"> اجباری</label>
<button type="submit">افزودن فیلد</button></form>
<?php endif; ?>
<?php include '_layout_footer.php'; ?>
