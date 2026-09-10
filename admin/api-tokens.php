<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Core/Autoloader.php';
require dirname(__DIR__) . '/app/Core/Bootstrap.php';
use App\Core\Bootstrap;
use App\Security\Auth;
use App\Security\Csrf;
use App\Database\Connection;
use App\Security\Encryption;
Bootstrap::init();
Auth::requireLogin();
$pdo = Connection::get();
$gwId = (int)($_GET['gateway_id'] ?? $_POST['gateway_id'] ?? 0);
$msg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Csrf::validateRequest()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' && $gwId) {
        $label = trim($_POST['label'] ?? 'Default');
        $token = trim($_POST['api_token'] ?? '');
        if ($token !== '') {
            $enc = Encryption::encrypt($token);
            $pdo->prepare("INSERT INTO gateway_credentials (gateway_id, label, credentials_encrypted, status, is_primary) VALUES (?,?,?, 'active', 0)")
                ->execute([$gwId, $label, $enc]);
            $msg = 'Credential ذخیره شد';
        }
    }
    if ($action === 'primary') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE gateway_credentials SET is_primary=0 WHERE gateway_id=?')->execute([$gwId]);
        $pdo->prepare('UPDATE gateway_credentials SET is_primary=1 WHERE id=? AND gateway_id=?')->execute([$id, $gwId]);
        $msg = 'اصلی تنظیم شد';
    }
}
$creds = [];
if ($gwId) {
    $st = $pdo->prepare('SELECT id, label, status, is_primary, created_at FROM gateway_credentials WHERE gateway_id=? ORDER BY id DESC');
    $st->execute([$gwId]);
    $creds = $st->fetchAll(PDO::FETCH_ASSOC);
}
$pageTitle = 'Token درگاه';
include '_layout_header.php';
?>
<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<form method="post"><?= csrf_field() ?><input type="hidden" name="gateway_id" value="<?= $gwId ?>">
<input type="hidden" name="action" value="add">
<label>برچسب</label><input name="label" value="Default">
<label>API Token</label><input name="api_token" type="password" required>
<button type="submit">افزودن</button></form>
<table><?php foreach ($creds as $c): ?>
<tr><td><?= htmlspecialchars($c['label']) ?></td><td><?= $c['is_primary']?'اصلی':'' ?></td><td><?= htmlspecialchars($c['status']) ?></td>
<td><form method="post" style="display:inline"><?= csrf_field() ?>
<input type="hidden" name="gateway_id" value="<?= $gwId ?>">
<input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
<button name="action" value="primary">اصلی</button></form></td></tr>
<?php endforeach; ?></table>
<?php include '_layout_footer.php'; ?>
