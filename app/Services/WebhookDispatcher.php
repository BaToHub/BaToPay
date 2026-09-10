<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Helpers\HttpClient;
use App\Helpers\Logger;
use PDO;

final class WebhookDispatcher
{
    public static function dispatch(int $merchantId, string $event, array $payload): void
    {
        $pdo = Connection::get();
        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM merchant_webhooks WHERE merchant_id = ? AND status = 'active' AND (events IS NULL OR events LIKE ?)"
            );
            $stmt->execute([$merchantId, '%' . $event . '%']);
            $hooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return;
        }
        foreach ($hooks as $hook) {
            self::deliver($hook, $event, $payload);
        }
    }

    private static function deliver(array $hook, string $event, array $payload): void
    {
        $pdo = Connection::get();
        $body = json_encode([
            'event' => $event,
            'created_at' => date('c'),
            'data' => $payload,
        ], JSON_UNESCAPED_UNICODE);
        $secret = (string)($hook['secret'] ?? '');
        $sig = $secret !== '' ? hash_hmac('sha256', $body ?: '', $secret) : '';
        $headers = [
            'Content-Type: application/json',
            'X-BaToPay-Event: ' . $event,
            'X-BaToPay-Signature: ' . $sig,
        ];
        $client = new HttpClient(10);
        $start = microtime(true);
        $res = $client->request('POST', (string)$hook['url'], $body, $headers);
        $ms = (int)((microtime(true) - $start) * 1000);
        $code = (int)($res['http_code'] ?? 0);
        $ok = $code >= 200 && $code < 300;
        try {
            $pdo->prepare(
                'INSERT INTO merchant_webhook_deliveries (webhook_id, event, payload, response_code, success, latency_ms, created_at)
                 VALUES (?,?,?,?,?,?,NOW())'
            )->execute([$hook['id'], $event, $body, $code, $ok ? 1 : 0, $ms]);
            if (!$ok) {
                $pdo->prepare('UPDATE merchant_webhooks SET fail_count = fail_count + 1, last_fail_at = NOW() WHERE id = ?')->execute([$hook['id']]);
                $pdo->prepare("UPDATE merchant_webhooks SET status = 'disabled' WHERE id = ? AND fail_count >= 20")->execute([$hook['id']]);
            } else {
                $pdo->prepare('UPDATE merchant_webhooks SET fail_count = 0, last_success_at = NOW() WHERE id = ?')->execute([$hook['id']]);
            }
        } catch (\Throwable $e) {
            Logger::error('Webhook delivery log failed', ['error' => $e->getMessage()]);
        }
    }
}
