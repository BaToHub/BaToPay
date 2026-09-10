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

$st = $pdo->prepare('SELECT * FROM merchant_payments WHERE merchant_id = ? ORDER BY id DESC LIMIT 100');
$st->execute([$mid]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'پرداخت‌ها';
include '_header.php';
?>
<div class="table-wrap">
<table>
<thead><tr><th>Order ID</th><th>مبلغ (تومان)</th><th>وضعیت</th><th>تاریخ</th><th>پرداخت</th></tr></thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
<td><code><?= htmlspecialchars($r['external_order_id']) ?></code></td>
<td><?= number_format((int)$r['amount']) ?></td>
<td><span class="badge badge-muted"><?= htmlspecialchars($r['status']) ?></span></td>
<td><?= htmlspecialchars($r['created_at']) ?></td>
<td><?= htmlspecialchars($r['paid_at'] ?? '—') ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="5" style="text-align:center;color:var(--muted)">هنوز پرداختی ثبت نشده</td></tr><?php endif; ?>
</tbody>
</table>
</div>
<?php include '_footer.php'; ?>
