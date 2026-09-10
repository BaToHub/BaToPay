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
$mid = (int)($_GET['merchant_id'] ?? $_POST['merchant_id'] ?? 0);
$msg = null; $newKey = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Csrf::validateRequest()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'create' && $mid) {
        $raw = 'btp_' . bin2hex(random_bytes(24));
        $hash = hash('sha256', $raw);
        $pdo->prepare("INSERT INTO merchant_api_tokens (merchant_id, token_hash, token_prefix, name, status) VALUES (?,?,?,?, 'active')")
            ->execute([$mid, $hash, substr($raw, 0, 12), trim($_POST['name'] ?? 'Admin')]);
        $newKey = $raw;
        $msg = 'کلید ساخته شد (یک‌بار نمایش)';
    }
    if ($action === 'revoke') {
        $pdo->prepare("UPDATE merchant_api_tokens SET status='revoked' WHERE id=?")->execute([(int)($_POST['id']??0)]);
        $msg = 'باطل شد';
    }
}
rows = [];
if ($mid) {
    $st = $pdo->prepare('SELECT * FROM merchant_api_tokens WHERE merchant_id=? ORDER BY id DESC');
    $st->execute([$mid]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
}
$pageTitle = 'Token فروشنده';
include '_layout_header.php';
?>
<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($newKey): ?><code><?= htmlspecialchars($newKey) ?></code><?php endif; ?>
<form method="post"><?= csrf_field() ?><input type="hidden" name="merchant_id" value="<?= $mid ?>">
<input type="hidden" name="action" value="create"><input name="name" value="Default">
<button type="submit">ساخت</button></form>
<table><?php foreach ($rows as $r): ?>
<tr><td><?= htmlspecialchars($r['name']) ?></td><td><code><?= htmlspecialchars($r['token_prefix']) ?>…</code></td><td><?= htmlspecialchars($r['status']) ?></td></tr>
<?php endforeach; ?></table>
<?php include '_layout_footer.php'; ?>
