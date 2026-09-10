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
$msg = null; $err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Csrf::validateRequest()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $url = trim($_POST['url'] ?? '');
        $events = trim($_POST['events'] ?? 'payment.paid,payment.failed');
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            $err = 'URL نامعتبر';
        } else {
            $secret = bin2hex(random_bytes(16));
            try {
                $pdo->prepare('INSERT INTO merchant_webhooks (merchant_id, url, secret, events, status) VALUES (?,?,?,?,?)')->execute([$mid, $url, $secret, $events, 'active']);
                $msg = 'Webhook اضافه شد. Secret: ' . $secret;
            } catch (Throwable $e) { $err = 'خطا در ذخیره'; }
        }
    }
    if ($action === 'disable') {
        $pdo->prepare("UPDATE merchant_webhooks SET status='disabled' WHERE id=? AND merchant_id=?")->execute([(int)($_POST['id']??0), $mid]);
        $msg = 'غیرفعال شد';
    }
}
$rows = [];
try {
    $st = $pdo->prepare('SELECT * FROM merchant_webhooks WHERE merchant_id = ? ORDER BY id DESC');
    $st->execute([$mid]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
$pageTitle = 'Webhookها';
include '_header.php';
?>
<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create">
<label>URL</label><input name="url" required>
<label>رویدادها</label><input name="events" value="payment.paid,payment.failed,payment.expired">
<button type="submit">افزودن</button></form>
<table><?php foreach ($rows as $r): ?><tr><td><?= htmlspecialchars($r['url']) ?></td><td><?= htmlspecialchars($r['status']) ?></td></tr><?php endforeach; ?></table>
<?php include '_footer.php'; ?>
