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
$msg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Csrf::validateRequest()) {
    $url = trim($_POST['app_url'] ?? '');
    $botToken = trim($_POST['bot_token'] ?? '');
    $chatId = trim($_POST['admin_chat_id'] ?? '');
    if ($url !== '') {
        $pdo->prepare("INSERT INTO settings (group_name,key_name,value) VALUES ('app','url',?) ON DUPLICATE KEY UPDATE value=VALUES(value)")->execute([$url]);
    }
    if ($botToken !== '') {
        $enc = Encryption::encrypt($botToken);
        $pdo->prepare("INSERT INTO settings (group_name,key_name,value) VALUES ('telegram','bot_token',?) ON DUPLICATE KEY UPDATE value=VALUES(value)")->execute([$enc]);
    }
    if ($chatId !== '') {
        $pdo->prepare("INSERT INTO settings (group_name,key_name,value) VALUES ('telegram','admin_chat_id',?) ON DUPLICATE KEY UPDATE value=VALUES(value)")->execute([$chatId]);
    }
    $msg = 'تنظیمات ذخیره شد.';
}
$settings = $pdo->query("SELECT group_name, key_name, value FROM settings")->fetchAll(PDO::FETCH_ASSOC);
map = [];
foreach ($settings as $s) { $map[$s['group_name'].'.'.$s['key_name']] = $s['value']; }
$pageTitle = 'تنظیمات';
include '_layout_header.php';
?>
<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<form method="post"><?= csrf_field() ?>
<label>APP URL</label>
<input name="app_url" value="<?= htmlspecialchars($map['app.url'] ?? '') ?>">
<label>Telegram Bot Token</label>
<input name="bot_token" placeholder="خالی بگذارید اگر تغییری نیست" type="password">
<label>Admin Chat ID</label>
<input name="admin_chat_id" value="<?= htmlspecialchars($map['telegram.admin_chat_id'] ?? '') ?>">
<button type="submit" class="btn btn-primary">ذخیره</button>
</form>
<?php include '_layout_footer.php'; ?>
