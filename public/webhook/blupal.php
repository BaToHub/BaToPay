<?php
/**
 * BluePal Webhook
 * event: payment.completed
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

Logger::info('BluePal webhook received', [
    'event' => $payload['event'] ?? null,
    'invoice_id' => $payload['invoice_id'] ?? null,
]);

if (($payload['event'] ?? '') !== 'payment.completed' || strtoupper((string)($payload['status'] ?? '')) !== 'PAID') {
    http_response_code(200);
    echo json_encode(['received' => true, 'ignored' => true]);
    exit;
}

$invoiceId = $payload['invoice_id'] ?? null;
if ($invoiceId === null) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_invoice_id']);
    exit;
}

$service = new PaymentService();
$result = $service->verifyAndMarkPaid(
    (string)$invoiceId,
    [
        'invoice_id' => $invoiceId,
        'authority' => (string)$invoiceId,
        'amount' => $payload['amount'] ?? null,
        'final_amount' => $payload['final_amount'] ?? null,
    ],
    'webhook_blupal'
);

http_response_code(200);
echo json_encode([
    'received' => true,
    'success' => !empty($result['success']),
    'already' => !empty($result['already']),
]);
