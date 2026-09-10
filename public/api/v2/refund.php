<?php
declare(strict_types=1);
require dirname(__DIR__, 3) . '/app/Core/Autoloader.php';
require dirname(__DIR__, 3) . '/app/Core/Bootstrap.php';
use App\Core\Bootstrap;
use App\Services\MerchantApiService;
Bootstrap::init();
header('Content-Type: application/json; charset=utf-8');
header('X-API-Version: 2');
$api = new MerchantApiService();
$m = $api->authenticate($_SERVER['HTTP_AUTHORIZATION'] ?? '');
if (!$m) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>['code'=>'unauthorized']]); exit; }
http_response_code(501);
echo json_encode(['ok'=>false,'error'=>[
  'code'=>'NOT_SUPPORTED',
  'message'=>'Automatic refund is not supported by configured gateways. Process refunds with the provider.'
]], JSON_UNESCAPED_UNICODE);
