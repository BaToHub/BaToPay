<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
require dirname(__DIR__, 2) . '/app/Core/Bootstrap.php';
use App\Core\Bootstrap;
use App\Services\PaymentService;
Bootstrap::init();
if (!Bootstrap::isInstalled()) { http_response_code(503); exit('Not installed'); }
$authority = preg_replace('/[^A-Za-z0-9\-]/', '', (string)($_GET['authority'] ?? ''));
$msg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $authority !== '') {
    $scenario = strtoupper((string)($_POST['scenario'] ?? 'SUCCESS'));
    $service = new PaymentService();
    $result = $service->verifyAndMarkPaid($authority, ['authority'=>$authority,'invoice_id'=>$authority,'scenario'=>$scenario], 'sandbox');
    $msg = !empty($result['success']) ? 'Sandbox: پرداخت موفق ثبت شد.' : ('Sandbox: '.($result['error'] ?? 'ناموفق'));
}
?><!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sandbox | BaToPay</title><link rel="stylesheet" href="/assets/css/app.css"></head><body><div class="container"><div class="logo">BaTo<span>Pay</span> Sandbox</div><div class="card"><p class="muted">محیط تست — بدون درگاه واقعی</p><?php if ($msg): ?><div class="alert alert-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?><p><code><?= htmlspecialchars($authority) ?></code></p><form method="post"><button class="btn" name="scenario" value="SUCCESS" type="submit">SUCCESS</button><button class="btn" name="scenario" value="FAILED" type="submit">FAILED</button><button class="btn" name="scenario" value="PENDING" type="submit">PENDING</button></form></div><div class="footer">Powered by BaToHub · Support @BaTo_Help</div></div></body></html>
