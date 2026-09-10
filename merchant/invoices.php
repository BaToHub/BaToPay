<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Core/Autoloader.php';
require dirname(__DIR__) . '/app/Core/Bootstrap.php';
use App\Core\Bootstrap;
use App\Security\MerchantAuth;
use App\Security\Csrf;
use App\Database\Connection;
use App\Services\InvoiceService;
Bootstrap::init();
MerchantAuth::requireLogin();
$pdo = Connection::get();
$mid = MerchantAuth::id();
$msg = null; $err = null; $created = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Csrf::validateRequest()) {
    $amount = (int) preg_replace('/\D/', '', (string)($_POST['amount'] ?? '0'));
    $desc = trim($_POST['description'] ?? '');
    $order = trim($_POST['order_id'] ?? '');
    $ttl = (int)($_POST['ttl'] ?? 60);
    $res = (new InvoiceService())->create([
        'amount_toman' => $amount,
        'description' => $desc ?: ('فاکتور فروشنده #' . $mid),
        'merchant_id' => $mid,
        'external_order_id' => $order !== '' ? $order : null,
        'ttl_minutes' => $ttl,
    ]);
    if (!empty($res['success'])) { $created = $res; $msg = 'فاکتور ساخته شد.'; }
    else { $err = $res['message'] ?? 'خطا'; }
}
$pageTitle = 'فاکتور دستی';
include '_header.php';
?>
<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
<?php if ($created): ?>
<div class="form-card"><p><strong><?= number_format((int)$created['amount_toman']) ?></strong> تومان</p>
<p style="word-break:break-all"><a href="<?= htmlspecialchars($created['payment_link']) ?>" target="_blank"><?= htmlspecialchars($created['payment_link']) ?></a></p></div>
<?php endif; ?>
<form method="post"><?= csrf_field() ?>
<label>مبلغ (تومان)</label><input name="amount" required>
<label>شناسه سفارش</label><input name="order_id">
<label>توضیحات</label><input name="description">
<label>TTL</label><select name="ttl"><option value="60">۶۰</option><option value="180">۱۸۰</option><option value="1440">۲۴س</option></select>
<button type="submit">ساخت فاکتور</button>
</form>
<?php include '_footer.php'; ?>
