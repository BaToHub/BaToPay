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
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'approve' && $id) {
        $app = $pdo->prepare('SELECT * FROM merchant_applications WHERE id=?');
        $app->execute([$id]);
        $a = $app->fetch(PDO::FETCH_ASSOC);
        if ($a) {
            $pdo->prepare("INSERT INTO merchants (name, email, phone, telegram_id, status, approved_at) VALUES (?,?,?,?, 'approved', NOW())")
                ->execute([$a['business_name'] ?? $a['name'] ?? 'Merchant', $a['email'] ?? null, $a['phone'] ?? null, $a['telegram_id'] ?? null]);
            $mid = (int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE merchant_applications SET status='approved', reviewed_at=NOW() WHERE id=?")->execute([$id]);
            $raw = 'btp_' . bin2hex(random_bytes(24));
            $hash = hash('sha256', $raw);
            $pdo->prepare("INSERT INTO merchant_api_tokens (merchant_id, token_hash, token_prefix, name, status) VALUES (?,?,?,?, 'active')")
                ->execute([$mid, $hash, substr($raw, 0, 12), 'Default']);
            $msg = 'تأیید شد. API Key (یک‌بار): ' . $raw;
        }
    }
    if ($action === 'reject' && $id) {
        $pdo->prepare("UPDATE merchant_applications SET status='rejected', reviewed_at=NOW() WHERE id=?")->execute([$id]);
        $msg = 'رد شد';
    }
}
$apps = [];
try { $apps = $pdo->query("SELECT * FROM merchant_applications ORDER BY id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
$merchants = [];
try { $merchants = $pdo->query("SELECT * FROM merchants ORDER BY id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
$pageTitle = 'فروشندگان';
include '_layout_header.php';
?>
<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<h3>درخواست‌ها</h3>
<table><?php foreach ($apps as $a): ?>
<tr><td><?= htmlspecialchars($a['business_name'] ?? $a['name'] ?? '') ?></td><td><?= htmlspecialchars($a['status']) ?></td>
<td><?php if (($a['status']??'')==='pending'): ?>
<form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
<button name="action" value="approve">تأیید</button>
<button name="action" value="reject">رد</button></form>
<?php endif; ?></td></tr><?php endforeach; ?></table>
<h3>فروشندگان تأییدشده</h3>
<table><?php foreach ($merchants as $m): ?>
<tr><td><?= htmlspecialchars($m['name']) ?></td><td><?= htmlspecialchars($m['status']) ?></td><td><?= htmlspecialchars($m['email'] ?? '') ?></td></tr>
<?php endforeach; ?></table>
<?php include '_layout_footer.php'; ?>
