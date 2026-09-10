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
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM pages WHERE id=?');
$st->execute([$id]);
$page = $st->fetch(PDO::FETCH_ASSOC);
if (!$page) { header('Location: pages.php'); exit; }
$msg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Csrf::validateRequest()) {
    $title = trim($_POST['title'] ?? '');
    $min = (int)($_POST['min_amount'] ?? 1000);
    $max = (int)($_POST['max_amount'] ?? 500000000);
    $pdo->prepare('UPDATE pages SET title=?, description=?, button_text=?, min_amount=?, max_amount=?, success_message=?, failure_message=? WHERE id=?')
        ->execute([$title, trim($_POST['description']??''), trim($_POST['button_text']??'پرداخت'), $min, $max, trim($_POST['success_message']??''), trim($_POST['failure_message']??''), $id]);
    $msg = 'ذخیره شد';
    $st->execute([$id]); $page = $st->fetch(PDO::FETCH_ASSOC);
}
$pageTitle = 'ویرایش صفحه';
include '_layout_header.php';
?>
<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
<label>عنوان</label><input name="title" value="<?= htmlspecialchars($page['title']) ?>" required>
<label>توضیحات</label><input name="description" value="<?= htmlspecialchars($page['description'] ?? '') ?>">
<label>متن دکمه</label><input name="button_text" value="<?= htmlspecialchars($page['button_text'] ?? 'پرداخت') ?>">
<label>حداقل</label><input name="min_amount" value="<?= (int)$page['min_amount'] ?>">
<label>حداکثر</label><input name="max_amount" value="<?= (int)$page['max_amount'] ?>">
<label>پیام موفقیت</label><input name="success_message" value="<?= htmlspecialchars($page['success_message'] ?? '') ?>">
<label>پیام خطا</label><input name="failure_message" value="<?= htmlspecialchars($page['failure_message'] ?? '') ?>">
<button type="submit">ذخیره</button>
</form>
<p><a href="form-builder.php?page_id=<?= $id ?>">مدیریت فیلدهای فرم</a></p>
<?php include '_layout_footer.php'; ?>
