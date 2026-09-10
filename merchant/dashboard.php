<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Core/Autoloader.php';
require dirname(__DIR__) . '/app/Core/Bootstrap.php';

use App\Core\Bootstrap;
use App\Security\MerchantAuth;
use App\Database\Connection;

Bootstrap::init();
MerchantAuth::requireLogin();
$pdo = Connection::get();
$mid = MerchantAuth::id();
$m = MerchantAuth::user();

$st = $pdo->prepare('SELECT COUNT(*) FROM merchant_payments WHERE merchant_id = ?');
$st->execute([$mid]);
$total = (int)$st->fetchColumn();

$st = $pdo->prepare("SELECT COUNT(*) FROM merchant_payments WHERE merchant_id = ? AND status = 'paid'");
$st->execute([$mid]);
$paid = (int)$st->fetchColumn();

$st = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM merchant_payments WHERE merchant_id = ? AND status = 'paid'");
$st->execute([$mid]);
$revenue = (int)$st->fetchColumn();

$st = $pdo->prepare("SELECT COUNT(*) FROM merchant_api_tokens WHERE merchant_id = ? AND status = 'active'");
$st->execute([$mid]);
$keys = (int)$st->fetchColumn();

$pageTitle = 'داشبورد';
include '_header.php';
?>
<div class="stats">
<div class="stat"><div class="label">کل پرداخت‌ها</div><div class="value"><?= number_format($total) ?></div></div>
<div class="stat"><div class="label">موفق</div><div class="value"><?= number_format($paid) ?></div></div>
<div class="stat"><div class="label">مجموع موفق (تومان)</div><div class="value gold"><?= number_format($revenue) ?></div></div>
<div class="stat"><div class="label">API Key فعال</div><div class="value"><?= number_format($keys) ?></div></div>
</div>
<div class="form-card">
<p style="margin-bottom:.75rem"><strong>فروشگاه:</strong> <?= htmlspecialchars($m['shop_name']) ?></p>
<p style="margin-bottom:1rem"><span class="badge badge-ok">تأیید شده</span></p>
<div style="display:flex;flex-wrap:wrap;gap:.5rem">
<a class="btn btn-primary" href="invoices.php">ساخت فاکتور دستی</a>
<a class="btn btn-ghost" href="api-keys.php">مدیریت API Key</a>
<a class="btn btn-ghost" href="payments.php">مشاهده پرداخت‌ها</a>
</div>
</div>
<?php include '_footer.php'; ?>
