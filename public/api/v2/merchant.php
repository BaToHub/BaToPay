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
echo json_encode(['ok'=>true,'data'=>[
  'id'=>(int)$m['id'],
  'shop_name'=>$m['shop_name']??null,
  'status'=>$m['status']??null,
  'email'=>$m['email']??null,
]], JSON_UNESCAPED_UNICODE);
