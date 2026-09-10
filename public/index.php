<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Core/Autoloader.php';
require dirname(__DIR__) . '/app/Core/Bootstrap.php';
use App\Core\Bootstrap;
Bootstrap::init();
if (!Bootstrap::isInstalled()) { header('Location: /install/'); exit; }
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>BaToPay | باتو پی</title>
<link rel="stylesheet" href="/assets/css/public.css">
</head>
<body>
<div class="container" style="max-width:480px;margin:3rem auto;text-align:center">
<h1>BaTo<span style="color:#c9a227">Pay</span></h1>
<p style="color:#666;margin:1rem 0">پلتفرم پرداخت و فرم‌ساز</p>
<p><a href="/merchant/apply.php">درخواست فروشندگی</a> · <a href="/admin/login.php">ورود ادمین</a></p>
<p style="margin-top:2rem;color:#999;font-size:.85rem">Powered by BaToHub · @BaToPay_Bot</p>
</div>
</body>
</html>
