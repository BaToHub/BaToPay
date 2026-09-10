<?php
/**
 * Telegram Bot Webhook for @BaToPay_Bot
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
require dirname(__DIR__, 2) . '/app/Core/Bootstrap.php';

use App\Core\Bootstrap;
use App\Telegram\BotHandler;
use App\Helpers\Logger;

Bootstrap::init();
header('Content-Type: application/json; charset=utf-8');

if (!Bootstrap::isInstalled()) {
    http_response_code(503);
    echo json_encode(['ok' => false]);
    exit;
}

$raw = file_get_contents('php://input');
$update = json_decode($raw ?: '[]', true);

if (!is_array($update) || empty($update)) {
    http_response_code(200);
    echo json_encode(['ok' => true]);
    exit;
}

try {
    $bot = new BotHandler();
    $bot->handle($update);
} catch (Throwable $e) {
    Logger::error('Telegram webhook error', ['error' => $e->getMessage()]);
}

http_response_code(200);
echo json_encode(['ok' => true]);
