<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Core/Autoloader.php';
require dirname(__DIR__) . '/app/Core/Bootstrap.php';

use App\Core\Bootstrap;
use App\Security\Auth;
use App\Security\Csrf;

Bootstrap::init();
if (Auth::check()) { header('Location: dashboard.php'); exit; }
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validateRequest()) {
        $error = 'درخواست نامعتبر است.';
    } elseif (!Auth::attempt(trim($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''))) {
        $error = 'ایمیل یا رمز عبور اشتباه است.';
    } else {
        header('Location: dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title>ورود | BaToPay</title>
<link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="login-page">
<div class="login-card">
<h1>BaTo<span style="color:var(--primary)">Pay</span></h1>
<p class="sub">ورود مدیر سیستم</p>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post">
<?= csrf_field() ?>
<label>ایمیل</label>
<input type="email" name="email" required autocomplete="username">
<label>رمز عبور</label>
<input type="password" name="password" required autocomplete="current-password">
<button type="submit" class="btn btn-primary btn-block">ورود</button>
</form>
<div class="footer">Powered by BaToHub</div>
</div>
</body>
</html>
