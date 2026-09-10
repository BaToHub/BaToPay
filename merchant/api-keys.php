<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Core/Autoloader.php';
require dirname(__DIR__) . '/app/Core/Bootstrap.php';
use App\Core\Bootstrap;
use App\Security\MerchantAuth;
use App\Security\Csrf;
use App\Database\Connection;
Bootstrap::init();
MerchantAuth::requireLogin();
$pdo = Connection::get();
$mid = MerchantAuth::id();
$msg = null; $newKey = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Csrf::validateRequest()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $raw = 'btp_' . bin2hex(random_bytes(24));
        $hash = hash('sha256', $raw);
        $name = trim($_POST['name'] ?? 'Key') ?: 'Key';
        $pdo->prepare("INSERT INTO merchant_api_tokens (merchant_id, token_hash, token_prefix, name, status) VALUES (?,?,?,?, 'active')")
            ->execute([$mid, $hash, substr($raw, 0, 12), $name]);
        $newKey = $raw;
        $msg = 'کلید جدید ساخته شد — فقط یک‌بار نمایش داده می‌شود.';
    }
    if ($action === 'revoke') {
        $pdo->prepare("UPDATE merchant_api_tokens SET status='revoked' WHERE id=? AND merchant_id=?")->execute([(int)($_POST['id']??0), $mid]);
        $msg = 'باطل شد';
    }
}
rows = $pdo->prepare('SELECT id, name, token_prefix, status, created_at, last_used_at FROM merchant_api_tokens WHERE merchant_id=? ORDER BY id DESC');
$rows->execute([$mid]);
rows = $rows->fetchAll(PDO::FETCH_ASSOC);
$pageTitle = 'API Keys';
include '_header.php';
?>
<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($newKey): ?><div class="alert alert-success"><code><?= htmlspecialchars($newKey) ?></code></div><?php endif; ?>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create">
<label>نام</label><input name="name" value="Default">
<button type="submit">ساخت کلید</button></form>
<table><?php foreach ($rows as $r): ?>
<tr><td><?= htmlspecialchars($r['name']) ?></td><td><code><?= htmlspecialchars($r['token_prefix']) ?>…</code></td>
<td><?= htmlspecialchars($r['status']) ?></td>
<td><?php if ($r['status']==='active'): ?><form method="post" style="display:inline"><?= csrf_field() ?>
<input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
<button name="action" value="revoke">باطل</button></form><?php endif; ?></td></tr>
<?php endforeach; ?></table>
<?php include '_footer.php'; ?>
