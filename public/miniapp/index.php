<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
require dirname(__DIR__, 2) . '/app/Core/Bootstrap.php';
use App\Core\Bootstrap;
Bootstrap::init();
if (!Bootstrap::isInstalled()) { http_response_code(503); exit('Not installed'); }
$pageSlug = preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($_GET['page'] ?? ''));
$siteName = config('app.name', 'BaToPay');
$baseUrl = rtrim((string)config('app.url'), '/');
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title><?= htmlspecialchars($siteName) ?></title>
<script src="https://telegram.org/js/telegram-web-app.js"></script>
<link rel="stylesheet" href="/assets/css/public.css">
<style>
body{background:var(--tg-theme-bg-color,#f7f6f2);color:var(--tg-theme-text-color,#1c1917);padding:1rem}
.card{background:var(--tg-theme-secondary-bg-color,#fff);border-radius:14px;padding:1.25rem;margin-bottom:1rem;border:1px solid #e7e5e4}
.btn{background:var(--tg-theme-button-color,#d4a012);color:var(--tg-theme-button-text-color,#1c1917);border:none;padding:.85rem;border-radius:12px;width:100%;font-weight:700}
</style>
</head>
<body>
<div class="card">
<h1>BaToPay</h1>
<p class="muted">مینی‌اپ پرداخت</p>
<?php if ($pageSlug): ?>
<p>صفحه: <code><?= htmlspecialchars($pageSlug) ?></code></p>
<a class="btn" href="<?= htmlspecialchars($baseUrl . '/pay/' . $pageSlug) ?>">رفتن به پرداخت</a>
<?php else: ?>
<p>از ربات @BaToPay_Bot یک صفحه پرداخت انتخاب کنید.</p>
<?php endif; ?>
</div>
<p class="footer">Powered by BaToHub</p>
<script>
if (window.Telegram && Telegram.WebApp) { Telegram.WebApp.ready(); Telegram.WebApp.expand(); }
</script>
</body></html>
