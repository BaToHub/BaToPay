<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Core/Autoloader.php';
require dirname(__DIR__) . '/app/Core/Bootstrap.php';
use App\Core\Bootstrap;
use App\Database\Connection;
use App\Services\PaymentService;
use App\Helpers\Logger;
Bootstrap::init();
if (!Bootstrap::isInstalled()) { http_response_code(503); exit('Not installed'); }
$raw = file_get_contents('php://input');
$json = $raw ? json_decode($raw, true) : null;
$requestData = array_merge($_GET, $_POST);
if (is_array($json)) { $requestData = array_merge($requestData, $json); }
$service = new PaymentService();
$pdo = Connection::get();
$authority = $requestData['authority'] ?? $requestData['Authority'] ?? $requestData['invoice_id'] ?? null;
$orderId = $requestData['order_id'] ?? null;
$tx = null;
if ($authority) {
    $stmt = $pdo->prepare('SELECT * FROM transactions WHERE authority = ? OR gateway_invoice_id = ? LIMIT 1');
    $stmt->execute([(string)$authority, (string)$authority]);
    $tx = $stmt->fetch(PDO::FETCH_ASSOC);
}
if (!$tx && $orderId) {
    $stmt = $pdo->prepare('SELECT * FROM transactions WHERE order_id = ? LIMIT 1');
    $stmt->execute([(string)$orderId]);
    $tx = $stmt->fetch(PDO::FETCH_ASSOC);
}
if (!$tx) {
    Logger::warning('Callback: transaction not found', ['keys' => array_keys($requestData)]);
    if (empty($raw) || isset($_GET['authority']) || isset($_GET['order_id'])) {
        http_response_code(404);
        include __DIR__ . '/errors/404.php';
        exit;
    }
    http_response_code(200);
    echo json_encode(['received' => true, 'status' => 'unknown']);
    exit;
}
$result = $service->verifyAndMarkPaid($tx['order_id'], ['authority' => $tx['authority'], 'invoice_id' => $tx['gateway_invoice_id']], 'callback');
$isBrowser = empty($raw) || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'text/html'));
if (!$isBrowser && $raw) {
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['received' => true, 'success' => !empty($result['success'])]);
    exit;
}
$pageStmt = $pdo->prepare('SELECT slug FROM pages WHERE id = ?');
$pageStmt->execute([$tx['page_id']]);
$slug = $pageStmt->fetchColumn() ?: '';
$fresh = $pdo->prepare('SELECT * FROM transactions WHERE id = ?');
$fresh->execute([$tx['id']]);
$tx = $fresh->fetch(PDO::FETCH_ASSOC) ?: $tx;
if (!empty($result['success']) && in_array($tx['status'], ['form_pending', 'paid', 'completed'], true)) {
    header('Location: /pay/' . $slug . '?tx=' . urlencode($tx['uuid']));
    exit;
}
http_response_code(200);
echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8"><title>وضعیت پرداخت</title></head><body style="font-family:Tahoma;text-align:center;padding:2rem">';
echo '<h1>وضعیت پرداخت</h1><p>' . htmlspecialchars($tx['status'] ?? '') . '</p>';
echo '<p><a href="/pay/' . htmlspecialchars($slug) . '">بازگشت</a></p>';
echo '<p style="color:#888">Powered by BaToHub</p></body></html>';
