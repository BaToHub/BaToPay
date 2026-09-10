<?php
declare(strict_types=1);
require dirname(__DIR__, 3) . '/app/Core/Autoloader.php';
require dirname(__DIR__, 3) . '/app/Core/Bootstrap.php';
use App\Core\Bootstrap;
use App\Services\MerchantApiService;
use App\Database\Connection;
Bootstrap::init();
header('Content-Type: application/json; charset=utf-8');
header('X-API-Version: 2');
$api = new MerchantApiService();
$m = $api->authenticate($_SERVER['HTTP_AUTHORIZATION'] ?? '');
if (!$m) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>['code'=>'unauthorized']]); exit; }
$pdo = Connection::get();
$mid = (int)$m['id'];
$paid = (int)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM merchant_payments WHERE merchant_id=$mid AND status='paid'")->fetchColumn();
$pending = (int)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM merchant_payments WHERE merchant_id=$mid AND status IN ('pending','processing')")->fetchColumn();
$count = (int)$pdo->query("SELECT COUNT(*) FROM merchant_payments WHERE merchant_id=$mid AND status='paid'")->fetchColumn();
echo json_encode(['ok'=>true,'data'=>[
  'currency'=>'IRT',
  'paid_volume_toman'=>$paid,
  'pending_volume_toman'=>$pending,
  'successful_payments'=>$count,
  'note'=>'Informational balance; settlement is external.'
]], JSON_UNESCAPED_UNICODE);
