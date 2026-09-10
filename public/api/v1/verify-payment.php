<?php
declare(strict_types=1);
require dirname(__DIR__, 3) . '/app/Core/Autoloader.php';
require dirname(__DIR__, 3) . '/app/Core/Bootstrap.php';
use App\Core\Bootstrap;
use App\Services\MerchantApiService;
Bootstrap::init();
header('Content-Type: application/json; charset=utf-8');
if (!Bootstrap::isInstalled()) { http_response_code(503); echo json_encode(['success'=>false]); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false]); exit; }
$body = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$api = new MerchantApiService();
$m = $api->authenticate($auth);
if (!$m) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
$ref = (string)($body['order_id'] ?? $body['authority'] ?? '');
if ($ref === '') { http_response_code(400); echo json_encode(['success'=>false,'message'=>'order_id required']); exit; }
$result = $api->verifyPayment($m, $ref);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
