<?php
/**
 * Mini App auth: validate Telegram WebApp initData + verified user in DB
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
require dirname(__DIR__, 2) . '/app/Core/Bootstrap.php';

use App\Core\Bootstrap;
use App\Database\Connection;
use App\Security\Encryption;

Bootstrap::init();
header('Content-Type: application/json; charset=utf-8');

if (!Bootstrap::isInstalled()) {
    echo json_encode(['success' => false, 'message' => 'not_installed']);
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '[]', true) ?: [];
$initData = (string)($body['initData'] ?? '');

if ($initData === '') {
    echo json_encode(['success' => false, 'message' => 'initData خالی است — از داخل تلگرام باز کنید'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = Connection::get();
$tok = $pdo->query("SELECT value FROM settings WHERE group_name='telegram' AND key_name='bot_token' LIMIT 1")->fetchColumn();
$botToken = '';
if ($tok) {
    try { $botToken = Encryption::decrypt((string)$tok); } catch (Throwable $e) { $botToken = ''; }
}

if ($botToken === '') {
    echo json_encode(['success' => false, 'message' => 'ربات پیکربندی نشده'], JSON_UNESCAPED_UNICODE);
    exit;
}

parse_str($initData, $data);
$hash = $data['hash'] ?? '';
unset($data['hash']);
ksort($data);
$check = [];
foreach ($data as $k => $v) {
    $check[] = $k . '=' . $v;
}
$dataCheckString = implode("\n", $check);
$secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
$calculated = bin2hex(hash_hmac('sha256', $dataCheckString, $secretKey, true));

if (!hash_equals($calculated, $hash)) {
    echo json_encode(['success' => false, 'message' => 'امضای تلگرام نامعتبر است'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userJson = $data['user'] ?? '';
$user = json_decode($userJson, true);
$tgId = (int)($user['id'] ?? 0);
if (!$tgId) {
    echo json_encode(['success' => false, 'message' => 'کاربر نامعتبر'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE telegram_id = ? LIMIT 1');
$stmt->execute([$tgId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || $row['status'] !== 'active' || empty($row['phone_verified_at']) || empty($row['captcha_passed_at'])) {
    echo json_encode([
        'success' => true,
        'verified' => false,
        'message' => 'ابتدا در ربات کپچا و شماره موبایل را تکمیل کنید',
        'telegram_id' => $tgId,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => true,
    'verified' => true,
    'telegram_id' => $tgId,
    'phone' => $row['phone'],
    'name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
], JSON_UNESCAPED_UNICODE);
