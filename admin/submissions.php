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
$msg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Csrf::validateRequest()) {
    $id = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if ($id && in_array($status, ['new','seen','processing','completed','rejected'], true)) {
        $pdo->prepare('UPDATE form_submissions SET status=? WHERE id=?')->execute([$status, $id]);
        $msg = 'وضعیت به‌روز شد.';
    }
}
$rows = $pdo->query('SELECT s.*, p.title AS page_title, t.order_id, t.amount FROM form_submissions s JOIN pages p ON p.id=s.page_id JOIN transactions t ON t.id=s.transaction_id ORDER BY s.id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
$pageTitle = 'فرم‌های تکمیل‌شده';
include '_layout_header.php';
?>
<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<div class="table-wrap"><table>
<thead><tr><th>Order</th><th>صفحه</th><th>مبلغ</th><th>وضعیت</th><th>تاریخ</th><th></th></tr></thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
<td><code><?= htmlspecialchars($r['order_id']) ?></code></td>
<td><?= htmlspecialchars($r['page_title']) ?></td>
<td><?= number_format((int)$r['amount']) ?></td>
<td><span class="badge badge-muted"><?= htmlspecialchars($r['status']) ?></span></td>
<td><?= htmlspecialchars($r['created_at']) ?></td>
<td><a class="btn btn-sm btn-ghost" href="submission-view.php?id=<?= $r['id'] ?>">مشاهده</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php include '_layout_footer.php'; ?>
