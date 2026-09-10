<?php
use App\Security\MerchantAuth;
$m = MerchantAuth::user();
$cur = basename($_SERVER['PHP_SELF'] ?? '');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title><?= htmlspecialchars($pageTitle ?? 'پنل فروشنده') ?> | BaToPay</title>
<link rel="stylesheet" href="/admin/assets/css/admin.css">
</head>
<body>
<div class="layout">
<aside class="sidebar">
<div class="brand">BaTo<span>Pay</span> · فروشنده</div>
<nav>
<a href="dashboard.php" class="<?= $cur==='dashboard.php'?'active':'' ?>">داشبورد</a>
<a href="invoices.php" class="<?= $cur==='invoices.php'?'active':'' ?>">فاکتور دستی</a>
<a href="payments.php" class="<?= $cur==='payments.php'?'active':'' ?>">پرداخت‌ها</a>
<a href="api-keys.php" class="<?= $cur==='api-keys.php'?'active':'' ?>">API Key</a>
<a href="docs.php" class="<?= $cur==='docs.php'?'active':'' ?>">مستندات</a>
<a href="logout.php">خروج</a>
</nav>
</aside>
<main class="main">
<div class="topbar">
<h2><?= htmlspecialchars($pageTitle ?? '') ?></h2>
<span style="color:var(--muted);font-size:.85rem"><?= htmlspecialchars($m['shop_name'] ?? '') ?></span>
</div>
