<?php
/**
 * CubePay Webhook / Server Callback
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
require dirname(__DIR__, 2) . '/app/Core/Bootstrap.php';

use App\Core\Bootstrap;
use App\Services\PaymentService;
use App\Helpers\Logger;

Bootstrap::init();
header('Content-Type: application/json; charset=utf-8');

if (!Bootstrap::isInstalled()) {
    http_response_code(503);
    echo json_encode(['error' => 'not_installed']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '[]', true) ?: [];
$payload = array_merge($_GET, $_POST, $payload);

Logger::info('CubePay webhook received', ['keys' => array_keys($payload)]);

$authority = $payload['authority'] ?? $payload['Authority'] ?? null;
$orderId = $payload['order_id'] ?? null;

if (!$authority && !$orderId) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_reference']);
    exit;
}

$service = new PaymentService();
$result = $service->verifyAndMarkPaid(
    (string)($orderId ?: $authority),
    ['authority' => $authority, 'invoice_id' => $authority],
    'webhook_cubepay'
);

http_response_code(200);
echo json_encode([
    'received' => true,
    'success' => !empty($result['success']),
    'already' => !empty($result['already']),
]);
