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
$status = $_GET['status'] ?? '';
$sql = 'SELECT t.*, p.title AS page_title FROM transactions t LEFT JOIN pages p ON p.id=t.page_id';
$params = [];
if ($status !== '') { $sql .= ' WHERE t.status = ?'; $params[] = $status; }
$sql .= ' ORDER BY t.id DESC LIMIT 150';
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
$pageTitle = 'تراکنش‌ها';
include '_layout_header.php';
?>
<div class="table-wrap"><table>
<thead><tr><th>Order</th><th>صفحه</th><th>مبلغ</th><th>وضعیت</th><th>تاریخ</th><th></th></tr></thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
<td><code><?= htmlspecialchars($r['order_id']) ?></code></td>
<td><?= htmlspecialchars($r['page_title'] ?? '') ?></td>
<td><?= number_format((int)$r['amount']) ?></td>
<td><?= htmlspecialchars($r['status']) ?></td>
<td><?= htmlspecialchars($r['created_at']) ?></td>
<td><a class="btn btn-sm btn-ghost" href="transaction-view.php?id=<?= (int)$r['id'] ?>">جزئیات</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php include '_layout_footer.php'; ?>
