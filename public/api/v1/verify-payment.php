<?php
/**
 * POST /api/v1/verify-payment.php
 * Body: { "authority": "..." } or { "order_id": "..." }
 */
declare(strict_types=1);

require dirname(__DIR__, 3) . '/app/Core/Autoloader.php';
require dirname(__DIR__, 3) . '/app/Core/Bootstrap.php';

use App\Core\Bootstrap;
use App\Services\MerchantApiService;

Bootstrap::init();
header('Content-Type: application/json; charset=utf-8');

if (!Bootstrap::isInstalled()) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'not_installed']);
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '[]', true) ?: [];
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

$api = new MerchantApiService();
$merchant = $api->authenticate($auth);
if (!$merchant) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$ref = (string)($body['authority'] ?? $body['order_id'] ?? '');
if ($ref === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'authority or order_id required']);
    exit;
}

$result = $api->verifyPayment($merchant, $ref);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
