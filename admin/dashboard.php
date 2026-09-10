<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Core/Autoloader.php';
require dirname(__DIR__) . '/app/Core/Bootstrap.php';
use App\Core\Bootstrap;
use App\Security\Auth;
use App\Database\Connection;
Bootstrap::init();
Auth::requireLogin();
$pdo = Connection::get();
$totalTx = (int)$pdo->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
$todayTx = (int)$pdo->query("SELECT COUNT(*) FROM transactions WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$paidTx = (int)$pdo->query("SELECT COUNT(*) FROM transactions WHERE status IN ('paid','form_pending','completed')")->fetchColumn();
$failedTx = (int)$pdo->query("SELECT COUNT(*) FROM transactions WHERE status IN ('failed','canceled','expired','verification_failed')")->fetchColumn();
$pendingTx = (int)$pdo->query("SELECT COUNT(*) FROM transactions WHERE status IN ('pending','redirected')")->fetchColumn();
$revenue = (int)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE status IN ('paid','form_pending','completed')")->fetchColumn();
$todayRev = (int)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE status IN ('paid','form_pending','completed') AND DATE(paid_at)=CURDATE()")->fetchColumn();
$last7 = (int)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE status IN ('paid','form_pending','completed') AND paid_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$last30 = (int)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE status IN ('paid','form_pending','completed') AND paid_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
$successRate = $totalTx > 0 ? round(($paidTx / $totalTx) * 100, 1) : 0;
$merchants = 0; $pendingApp = 0;
try {
    $merchants = (int)$pdo->query("SELECT COUNT(*) FROM merchants WHERE status='approved'")->fetchColumn();
    $pendingApp = (int)$pdo->query("SELECT COUNT(*) FROM merchant_applications WHERE status='pending'")->fetchColumn();
} catch (Throwable $e) {}
$pageTitle = 'داشبورد';
include '_layout_header.php';
?>
<div class="stats">
<div class="stat"><div class="label">تراکنش‌ها</div><div class="value"><?= number_format($totalTx) ?></div></div>
<div class="stat"><div class="label">موفق</div><div class="value"><?= number_format($paidTx) ?></div></div>
<div class="stat"><div class="label">در انتظار</div><div class="value"><?= number_format($pendingTx) ?></div></div>
<div class="stat"><div class="label">ناموفق</div><div class="value"><?= number_format($failedTx) ?></div></div>
<div class="stat"><div class="label">درآمد کل</div><div class="value gold"><?= number_format($revenue) ?></div></div>
<div class="stat"><div class="label">امروز</div><div class="value gold"><?= number_format($todayRev) ?></div></div>
<div class="stat"><div class="label">حجم ۷ روز</div><div class="value gold"><?= number_format($last7) ?></div></div>
<div class="stat"><div class="label">حجم ۳۰ روز</div><div class="value gold"><?= number_format($last30) ?></div></div>
<div class="stat"><div class="label">نرخ موفقیت ٪</div><div class="value"><?= $successRate ?></div></div>
<div class="stat"><div class="label">فروشندگان</div><div class="value"><?= number_format($merchants) ?></div></div>
<div class="stat"><div class="label">درخواست معلق</div><div class="value"><?= number_format($pendingApp) ?></div></div>
</div>
<?php include '_layout_footer.php'; ?>
