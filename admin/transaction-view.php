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
$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT t.*, p.title AS page_title FROM transactions t LEFT JOIN pages p ON p.id=t.page_id WHERE t.id=?');
$st->execute([$id]);
$tx = $st->fetch(PDO::FETCH_ASSOC);
if (!$tx) { header('Location: transactions.php'); exit; }
$pageTitle = 'جزئیات تراکنش';
include '_layout_header.php';
?>
<table>
<tr><th>Order</th><td><code><?= htmlspecialchars($tx['order_id']) ?></code></td></tr>
<tr><th>UUID</th><td><code><?= htmlspecialchars($tx['uuid']) ?></code></td></tr>
<tr><th>مبلغ</th><td><?= number_format((int)$tx['amount']) ?> تومان</td></tr>
<tr><th>وضعیت</th><td><?= htmlspecialchars($tx['status']) ?></td></tr>
<tr><th>صفحه</th><td><?= htmlspecialchars($tx['page_title'] ?? '') ?></td></tr>
<tr><th>Authority</th><td><?= htmlspecialchars($tx['authority'] ?? '') ?></td></tr>
<tr><th>ایجاد</th><td><?= htmlspecialchars($tx['created_at']) ?></td></tr>
<tr><th>پرداخت</th><td><?= htmlspecialchars($tx['paid_at'] ?? '—') ?></td></tr>
</table>
<p><a href="transactions.php">بازگشت</a></p>
<?php include '_layout_footer.php'; ?>
