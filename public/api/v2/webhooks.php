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
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $st = $pdo->prepare('SELECT id, url, events, status, created_at FROM merchant_webhooks WHERE merchant_id = ? ORDER BY id DESC');
    $st->execute([(int)$merchant['id']]);
    echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
    $url = trim((string)($body['url'] ?? ''));
    $events = trim((string)($body['events'] ?? 'payment.paid'));
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        http_response_code(422); echo json_encode(['success'=>false,'error'=>'invalid_url']); exit;
    }
    $secret = bin2hex(random_bytes(16));
    $pdo->prepare('INSERT INTO merchant_webhooks (merchant_id, url, secret, events, status) VALUES (?,?,?,?,?)')
        ->execute([(int)$merchant['id'], $url, $secret, $events, 'active']);
    echo json_encode(['success'=>true,'id'=>(int)$pdo->lastInsertId(),'secret'=>$secret], JSON_UNESCAPED_UNICODE);
    exit;
}
http_response_code(405); echo json_encode(['success'=>false,'error'=>'method_not_allowed']);
