<?php
use App\Security\Auth;
$user = Auth::user();
$current = basename($_SERVER['PHP_SELF'] ?? '');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= htmlspecialchars($pageTitle ?? 'پنل') ?> | BaToPay</title>
<link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<div class="layout">
<aside class="sidebar">
<div class="brand">BaTo<span>Pay</span></div>
<nav>
<div class="nav-label">اصلی</div>
<a href="dashboard.php" class="<?= $current==='dashboard.php'?'active':'' ?>">داشبورد</a>
<a href="pages.php" class="<?= in_array($current,['pages.php','page-create.php','page-edit.php','form-builder.php'],true)?'active':'' ?>">صفحات و فرم</a>
<a href="invoices.php" class="<?= $current==='invoices.php'?'active':'' ?>">فاکتور دستی</a>
<a href="transactions.php" class="<?= in_array($current,['transactions.php','transaction-view.php'],true)?'active':'' ?>">تراکنش‌ها</a>
<a href="submissions.php" class="<?= in_array($current,['submissions.php','submission-view.php'],true)?'active':'' ?>">ارسال‌های فرم</a>
<div class="nav-label">درگاه و فروشنده</div>
<a href="gateways.php" class="<?= in_array($current,['gateways.php','gateway-edit.php','api-tokens.php'],true)?'active':'' ?>">درگاه‌ها</a>
<a href="merchants.php" class="<?= $current==='merchants.php'?'active':'' ?>">فروشندگان</a>
<a href="merchant-tokens.php" class="<?= $current==='merchant-tokens.php'?'active':'' ?>">API ربات‌ها</a>
<div class="nav-label">سیستم</div>
<a href="settings.php" class="<?= $current==='settings.php'?'active':'' ?>">تنظیمات</a>
<a href="logs.php" class="<?= $current==='logs.php'?'active':'' ?>">لاگ‌ها</a>
<a href="logout.php">خروج</a>
</nav>
</aside>
<main class="main">
<div class="topbar">
<h2><?= htmlspecialchars($pageTitle ?? '') ?></h2>
<span style="color:var(--muted);font-size:.85rem"><?= htmlspecialchars($user['name'] ?? $user['email'] ?? '') ?></span>
</div>
