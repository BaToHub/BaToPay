<?php
declare(strict_types=1);
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>مستندات API | BaToPay</title>
<link rel="stylesheet" href="/assets/css/public.css">
</head><body><div class="container" style="max-width:720px">
<div class="card">
<h1>مستندات API فروشندگان</h1>
<p class="muted">اتصال ربات / سرویس شما به BaToPay</p>
<h2>احراز هویت</h2>
<pre>Authorization: Bearer btp_YOUR_API_KEY</pre>
<h2>ساخت پرداخت</h2>
<pre>POST /api/v1/create-payment.php
{
  "amount": 2500000,
  "order_id": "order-1",
  "callback_url": "https://your.example/callback",
  "description": "خرید"
}</pre>
<p><code>amount</code> به <strong>ریال</strong> است (تومان × ۱۰).</p>
<h2>وضعیت</h2>
<pre>GET /api/v1/status.php?order_id=order-1</pre>
<h2>تأیید</h2>
<pre>POST /api/v1/verify-payment.php
{"order_id":"order-1"}</pre>
<p><a href="en.html">English docs</a> · پشتیبانی: @BaTo_Help · باگ: @DatPHP · کانال: @BaToHub</p>
</div>
<p class="footer">Powered by BaToHub · @BaToPay_Bot</p>
</div></body></html>
