<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
require dirname(__DIR__, 2) . '/app/Core/Bootstrap.php';
use App\Core\Bootstrap;
use App\Database\Connection;
use App\Security\Csrf;
Bootstrap::init();
$error = null; $ok = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validateRequest()) {
        $error = 'درخواست نامعتبر است.';
    } else {
        $shop = trim($_POST['shop_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass = (string)($_POST['password'] ?? '');
        $tg = trim($_POST['telegram_username'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $web = trim($_POST['website'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($shop === '' || $email === '' || strlen($pass) < 8) {
            $error = 'نام فروشگاه، ایمیل و رمز (حداقل ۸ کاراکتر) الزامی است.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'ایمیل نامعتبر است.';
        } else {
            try {
                $pdo = Connection::get();
                $ex = $pdo->prepare("SELECT id FROM merchants WHERE email = ? UNION SELECT id FROM merchant_applications WHERE email = ? AND status = 'pending'");
                $ex->execute([$email, $email]);
                if ($ex->fetch()) {
                    $error = 'این ایمیل قبلاً ثبت شده است.';
                } else {
                    $pdo->prepare('INSERT INTO merchant_applications (shop_name, email, password_hash, telegram_username, phone, website, description, status) VALUES (?,?,?,?,?,?,?,?)')
                        ->execute([$shop, $email, password_hash($pass, PASSWORD_DEFAULT), ltrim($tg, '@') ?: null, $phone ?: null, $web ?: null, $desc ?: null, 'pending']);
                    $ok = true;
                }
            } catch (Throwable $e) {
                $error = 'خطای سیستمی. بعداً تلاش کنید.';
            }
        }
    }
}
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>درخواست فروشندگی | BaToPay</title>
<link rel="stylesheet" href="/assets/css/public.css"></head>
<body><div class="container">
<div class="card">
<h1>درخواست فروشندگی</h1>
<p class="muted">پس از تأیید، API Key و پنل دریافت می‌کنید.</p>
<?php if ($ok): ?><div class="alert alert-success">درخواست ثبت شد. پس از بررسی با شما تماس گرفته می‌شود.</div>
<?php else: ?>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post"><?= csrf_field() ?>
<label>نام فروشگاه</label><input name="shop_name" required>
<label>ایمیل</label><input name="email" type="email" required>
<label>رمز عبور</label><input name="password" type="password" required minlength="8">
<label>تلگرام</label><input name="telegram_username" placeholder="@username">
<label>موبایل</label><input name="phone" placeholder="09xxxxxxxxx">
<label>وب‌سایت</label><input name="website">
<label>توضیحات</label><textarea name="description" rows="3"></textarea>
<button type="submit">ارسال درخواست</button>
</form>
<?php endif; ?>
</div>
<p class="footer">Powered by BaToHub · @BaToPay_Bot</p>
</div></body></html>
