<?php
/**
 * POST /api/v1/create-payment.php
 * CubePay-compatible style API for third-party bots
 * Auth: Authorization: Bearer btp_xxxxx
 */
declare(strict_types=1);

require dirname(__DIR__, 3) . '/app/Core/Autoloader.php';
require dirname(__DIR__, 3) . '/app/Core/Bootstrap.php';

use App\Core\Bootstrap;
use App\Services\MerchantApiService;

Bootstrap::init();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!Bootstrap::isInstalled()) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'not_installed'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '[]', true) ?: [];
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

$api = new MerchantApiService();
$merchant = $api->authenticate($auth);
if (!$merchant) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = $api->createPayment($merchant, $body);
http_response_code(!empty($result['success']) ? 200 : 400);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
