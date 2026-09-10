<?php
declare(strict_types=1);
require dirname(__DIR__, 3) . '/app/Core/Autoloader.php';
require dirname(__DIR__, 3) . '/app/Core/Bootstrap.php';
use App\Core\Bootstrap;
use App\Services\MerchantApiService;
Bootstrap::init();
header('Content-Type: application/json; charset=utf-8');
if (!Bootstrap::isInstalled()) { http_response_code(503); echo json_encode(['success'=>false]); exit; }
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$api = new MerchantApiService();
$m = $api->authenticate($auth);
if (!$m) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
$ref = (string)($_GET['order_id'] ?? $_GET['authority'] ?? '');
if ($ref === '') { http_response_code(400); echo json_encode(['success'=>false,'message'=>'order_id required']); exit; }
echo json_encode($api->verifyPayment($m, $ref), JSON_UNESCAPED_UNICODE);
