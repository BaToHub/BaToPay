<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Core/Autoloader.php';
require dirname(__DIR__) . '/app/Core/Bootstrap.php';
use App\Core\Bootstrap;
use App\Security\MerchantAuth;
Bootstrap::init();
MerchantAuth::requireLogin();
$pageTitle = 'مستندات اتصال';
include '_header.php';
$base = rtrim((string)config('app.url'), '/');
?>
<div class="form-card" style="max-width:720px">
<p>مستندات کامل توسعه‌دهندگان:</p>
<ul style="margin:1rem 0;line-height:2">
<li><a href="/docs/">مستندات API (فارسی)</a></li>
<li><a href="/docs/en.html">API Documentation (English)</a></li>
</ul>
<p style="color:var(--muted);font-size:.9rem">Base URL: <code><?= htmlspecialchars($base) ?>/api/v1/</code></p>
<pre style="background:#0f0f0f;padding:1rem;border-radius:8px;overflow:auto;font-size:.8rem;margin-top:1rem">POST <?= htmlspecialchars($base) ?>/api/v1/create-payment.php
Authorization: Bearer btp_YOUR_KEY
Content-Type: application/json

{
  "amount": 2500000,
  "order_id": "order-1",
  "callback_url": "https://your-bot.example/callback",
  "description": "Purchase"
}</pre>
<p style="margin-top:1rem;font-size:.85rem;color:var(--muted)"><code>amount</code> به <strong>ریال</strong> است (تومان × ۱۰).</p>
</div>
<?php include '_footer.php'; ?>
