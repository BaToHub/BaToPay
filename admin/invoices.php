<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Core/Autoloader.php';
require dirname(__DIR__) . '/app/Core/Bootstrap.php';
use App\Core\Bootstrap;
use App\Security\Auth;
use App\Security\Csrf;
use App\Database\Connection;
use App\Services\InvoiceService;
Bootstrap::init();
Auth::requireLogin();
$pdo = Connection::get();
$msg = null; $err = null; $created = null;
$pages = $pdo->query("SELECT id, title, slug FROM pages WHERE status='active' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Csrf::validateRequest()) {
    $amount = (int) preg_replace('/\D/', '', (string)($_POST['amount'] ?? '0'));
    $res = (new InvoiceService())->create([
        'amount_toman' => $amount,
        'description' => trim($_POST['description'] ?? 'فاکتور دستی'),
        'page_id' => (int)($_POST['page_id'] ?? 0) ?: null,
        'ttl_minutes' => (int)($_POST['ttl'] ?? 60),
    ]);
    if (!empty($res['success'])) { $created = $res; $msg = 'فاکتور ساخته شد'; }
    else { $err = $res['message'] ?? 'خطا'; }
}
$pageTitle = 'فاکتور دستی';
include '_layout_header.php';
?>
<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
<?php if ($created): ?><p><a href="<?= htmlspecialchars($created['payment_link']) ?>" target="_blank"><?= htmlspecialchars($created['payment_link']) ?></a></p><?php endif; ?>
<form method="post"><?= csrf_field() ?>
<label>مبلغ (تومان)</label><input name="amount" required>
<label>توضیحات</label><input name="description">
<label>صفحه</label><select name="page_id"><option value="">پیش‌فرض</option>
<?php foreach ($pages as $p): ?><option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['title']) ?></option><?php endforeach; ?>
</select>
<label>TTL</label><select name="ttl"><option value="60">۶۰</option><option value="1440">۲۴س</option></select>
<button type="submit">ساخت</button></form>
<?php include '_layout_footer.php'; ?>
