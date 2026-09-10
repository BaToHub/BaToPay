<?php
declare(strict_types=1);
require dirname(__DIR__, 3) . '/app/Core/Autoloader.php';
require dirname(__DIR__, 3) . '/app/Core/Bootstrap.php';
use App\Core\Bootstrap;
use App\Database\Connection;
use App\Services\MerchantApiService;
Bootstrap::init();
header('Content-Type: application/json; charset=utf-8');
if (!Bootstrap::isInstalled()) { http_response_code(503); echo json_encode(['success'=>false,'error'=>'not_installed']); exit; }
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!preg_match('/Bearer\s+(\S+)/i', $auth, $m)) { http_response_code(401); echo json_encode(['success'=>false,'error'=>'unauthorized']); exit; }
$svc = new MerchantApiService();
$merchant = $svc->authenticateToken($m[1]);
if (!$merchant) { http_response_code(401); echo json_encode(['success'=>false,'error'=>'invalid_token']); exit; }
$pdo = Connection::get();
$limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
$st = $pdo->prepare('SELECT external_order_id, amount, amount_rial, status, paid_at, created_at FROM merchant_payments WHERE merchant_id = ? ORDER BY id DESC LIMIT ?');
$st->bindValue(1, (int)$merchant['id'], PDO::PARAM_INT);
$st->bindValue(2, $limit, PDO::PARAM_INT);
$st->execute();
rows = $st->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['success'=>true,'data'=>$rows], JSON_UNESCAPED_UNICODE);
