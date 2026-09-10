<?php
declare(strict_types=1);
require dirname(__DIR__, 3) . '/app/Core/Autoloader.php';
require dirname(__DIR__, 3) . '/app/Core/Bootstrap.php';
use App\Core\Bootstrap;
use App\Services\MerchantApiService;
use App\Security\RateLimiter;
Bootstrap::init();
header('Content-Type: application/json; charset=utf-8');
header('X-API-Version: 2');
if (!Bootstrap::isInstalled()) { http_response_code(503); echo json_encode(['ok'=>false,'error'=>['code'=>'not_installed']]); exit; }
$api = new MerchantApiService();
$merchant = $api->authenticate($_SERVER['HTTP_AUTHORIZATION'] ?? '');
if (!$merchant) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>['code'=>'unauthorized']]); exit; }
$ip = $_SERVER['REMOTE_ADDR'] ?? '0';
if (!RateLimiter::attempt('api:'.$merchant['id'].':'.$ip, 60, 60)) { http_response_code(429); echo json_encode(['ok'=>false,'error'=>['code'=>'rate_limited']]); exit; }
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
    $idem = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ($body['idempotency_key'] ?? null);
    if ($idem) { $body['order_id'] = $body['order_id'] ?? ('idem-'.substr(hash('sha256',(string)$idem),0,24)); }
    $result = $api->createPayment($merchant, $body);
    if (!empty($result['success'])) {
        http_response_code(201);
        echo json_encode(['ok'=>true,'data'=>['id'=>$result['internal_order_id']??null,'order_id'=>$result['order_id']??null,'authority'=>$result['authority']??null,'payment_url'=>$result['payment_link']??null,'amount'=>$result['amount']??null,'status'=>'pending']], JSON_UNESCAPED_UNICODE);
        exit;
    }
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>['code'=>'create_failed','message'=>$result['message']??'Error']], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($method === 'GET') {
    $ref = (string)($_GET['id'] ?? $_GET['order_id'] ?? $_GET['authority'] ?? '');
    if ($ref === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>['code'=>'missing_id']]); exit; }
    echo json_encode(['ok'=>true,'data'=>$api->verifyPayment($merchant, $ref)], JSON_UNESCAPED_UNICODE);
    exit;
}
http_response_code(405);
echo json_encode(['ok'=>false,'error'=>['code'=>'method_not_allowed']]);
