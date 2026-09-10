<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Core/Autoloader.php';
require dirname(__DIR__) . '/app/Core/Bootstrap.php';
use App\Core\Bootstrap;
use App\Security\Auth;
use App\Security\Csrf;
use App\Database\Connection;
use App\Payments\GatewayManager;
Bootstrap::init();
Auth::requireLogin();
$pdo = Connection::get();
$msg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Csrf::validateRequest()) {
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'toggle' && $id) {
        $pdo->prepare("UPDATE payment_gateways SET status = IF(status='active','inactive','active') WHERE id=?")->execute([$id]);
        $msg = 'وضعیت به‌روز شد';
    }
    if ($action === 'test' && $id) {
        $g = $pdo->prepare('SELECT identifier FROM payment_gateways WHERE id=?');
        $g->execute([$id]);
        $ident = $g->fetchColumn();
        if ($ident) {
            $mgr = new GatewayManager();
            $gw = $mgr->resolve((string)$ident);
            if ($gw) {
                $result = $gw->testConnection();
                $pdo->prepare('UPDATE payment_gateways SET last_test_result=?, last_test_at=NOW(), avg_latency_ms=? WHERE id=?')
                    ->execute([!empty($result['success']) ? 'success' : 'failed', $result['latency_ms'] ?? null, $id]);
                $msg = !empty($result['success']) ? ('✓ موفق — ' . ($result['latency_ms'] ?? '?') . 'ms') : ('✕ ' . ($result['message'] ?? 'fail'));
            } else {
                $msg = 'درگاه یا credential در دسترس نیست';
            }
        }
    }
}
$gateways = $pdo->query('SELECT * FROM payment_gateways ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$pageTitle = 'درگاه‌های پرداخت';
include '_layout_header.php';
?>
<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<table>
<thead><tr><th>نام</th><th>شناسه</th><th>وضعیت</th><th>تست</th><th></th></tr></thead>
<tbody>
<?php foreach ($gateways as $g): ?>
<tr>
<td><?= htmlspecialchars($g['name']) ?></td>
<td><code><?= htmlspecialchars($g['identifier']) ?></code></td>
<td><?= htmlspecialchars($g['status']) ?></td>
<td><?= htmlspecialchars($g['last_test_result'] ?? '—') ?></td>
<td>
<form method="post" style="display:inline"><?= csrf_field() ?>
<input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
<button name="action" value="toggle">toggle</button>
<button name="action" value="test">تست</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php include '_layout_footer.php'; ?>
