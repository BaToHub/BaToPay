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
$err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Csrf::validateRequest()) {
    $title = trim($_POST['title'] ?? '');
    $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_POST['slug'] ?? '')));
    $min = (int)($_POST['min_amount'] ?? 1000);
    $max = (int)($_POST['max_amount'] ?? 500000000);
    if ($title === '' || $slug === '') {
        $err = 'عنوان و slug الزامی است';
    } else {
        try {
            $pdo->prepare('INSERT INTO pages (title, slug, description, status, button_text, min_amount, max_amount, success_message, failure_message) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([$title, $slug, trim($_POST['description'] ?? ''), 'active', trim($_POST['button_text'] ?? 'پرداخت'), $min, $max, 'پرداخت موفق', 'پرداخت ناموفق']);
            header('Location: pages.php');
            exit;
        } catch (Throwable $e) {
            $err = 'خطا — شاید slug تکراری باشد';
        }
    }
}
$pageTitle = 'ساخت صفحه';
include '_layout_header.php';
?>
<?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
<form method="post"><?= csrf_field() ?>
<label>عنوان</label><input name="title" required>
<label>Slug</label><input name="slug" required pattern="[a-z0-9\-]+">
<label>توضیحات</label><input name="description">
<label>متن دکمه</label><input name="button_text" value="پرداخت">
<label>حداقل مبلغ (تومان)</label><input name="min_amount" value="1000">
<label>حداکثر مبلغ</label><input name="max_amount" value="500000000">
<button type="submit" class="btn btn-primary">ایجاد</button>
</form>
<?php include '_layout_footer.php'; ?>
