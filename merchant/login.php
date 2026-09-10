<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Core/Autoloader.php';
require dirname(__DIR__) . '/app/Core/Bootstrap.php';

use App\Core\Bootstrap;
use App\Security\MerchantAuth;
use App\Security\Csrf;

Bootstrap::init();
if (MerchantAuth::check()) { header('Location: dashboard.php'); exit; }
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validateRequest()) $error = 'درخواست نامعتبر.';
    elseif (!MerchantAuth::attempt(trim($_POST['email'] ?? ''), (string)($_POST['password'] ?? '')))
        $error = 'ایمیل/رمز اشتباه یا حساب تأیید نشده است.';
    else { header('Location: dashboard.php'); exit; }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title>ورود فروشنده | BaToPay</title>
<link rel="stylesheet" href="/admin/assets/css/admin.css">
</head>
<body class="login-page">
<div class="login-card">
<h1>BaTo<span style="color:var(--primary)">Pay</span></h1>
<p class="sub">پنل فروشندگان</p>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post">
<?= csrf_field() ?>
<label>ایمیل</label>
<input type="email" name="email" required>
<label>رمز عبور</label>
<input type="password" name="password" required>
<button type="submit" class="btn btn-primary btn-block">ورود</button>
</form>
<p style="text-align:center;margin-top:1rem;font-size:.85rem;color:var(--muted)">
<a href="/merchant/apply.php">درخواست فروشنده شدن</a>
 · <a href="/docs/">مستندات API</a>
</p>
<div class="footer">Powered by BaToHub</div>
</div>
</body>
</html>
