<?php
/**
 * CubePay Gateway Adapter
 * Docs: https://cubevps.ir/smspay/developers.php
 * Base: https://cubevps.ir/smspay
 * Auth: Authorization: Bearer <TOKEN>
 */

declare(strict_types=1);

namespace App\Payments;

use App\Helpers\HttpClient;
use App\Helpers\Logger;

final class CubePayGateway implements PaymentGatewayInterface
{
    private string $token;
    private string $baseUrl;
    private int $timeout;

    public function __construct(string $token, string $baseUrl = 'https://cubevps.ir/smspay', int $timeout = 20)
    {
        $this->token   = $token;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
    }

    public function getName(): string { return 'CubePay'; }
    public function getIdentifier(): string { return 'cubepay'; }
    public function validateConfiguration(): bool { return $this->token !== ''; }

    public function createPayment(array $params): array
    {
        $amount = (int) ($params['amount_rial'] ?? 0);
        $orderId = (string) ($params['order_id'] ?? '');
        $callbackUrl = (string) ($params['callback_url'] ?? '');

        if ($amount < 1000) {
            return ['success' => false, 'error' => 'Minimum amount is 1000 Rial', 'error_code' => 'GATEWAY_INVALID_AMOUNT'];
        }
        if ($orderId === '' || $callbackUrl === '') {
            return ['success' => false, 'error' => 'order_id and callback_url are required', 'error_code' => 'GATEWAY_INVALID_PARAMS'];
        }

        $payload = ['amount' => $amount, 'order_id' => $orderId, 'callback_url' => $callbackUrl];
        if (!empty($params['description'])) {
            $payload['description'] = mb_substr((string) $params['description'], 0, 255);
        }
        if (!empty($params['ttl_minutes'])) {
            $payload['ttl_minutes'] = (int) $params['ttl_minutes'];
        }

        $url = $this->baseUrl . '/api/v1/create-payment.php';
        $response = $this->request('POST', $url, $payload);
        if (!$response['success']) {
            return ['success' => false, 'error' => $response['error'] ?? 'Create payment failed', 'error_code' => $response['error_code'] ?? 'GATEWAY_SERVER_ERROR', 'raw' => $response['body'] ?? null, 'http_code' => $response['http_code'] ?? null];
        }
        $body = $response['body'];
        if (empty($body['success'])) {
            return ['success' => false, 'error' => $body['message'] ?? 'Unknown error from CubePay', 'error_code' => 'GATEWAY_API_ERROR', 'raw' => $body];
        }
        return [
            'success' => true,
            'authority' => $body['authority'] ?? null,
            'payment_link' => $body['payment_link'] ?? null,
            'pay_amount' => isset($body['pay_amount']) ? (int) $body['pay_amount'] : null,
            'final_amount' => isset($body['pay_amount']) ? (int) $body['pay_amount'] : null,
            'is_test' => !empty($body['is_test']),
            'raw' => $body,
        ];
    }

    public function verifyPayment(array $params): array
    {
        $authority = (string) ($params['authority'] ?? '');
        if ($authority === '') {
            return ['success' => false, 'error' => 'authority is required', 'error_code' => 'GATEWAY_INVALID_PARAMS'];
        }
        $url = $this->baseUrl . '/api/v1/verify-payment.php';
        $response = $this->request('POST', $url, ['authority' => $authority]);
        if (!$response['success']) {
            return ['success' => false, 'error' => $response['error'] ?? 'Verify failed', 'error_code' => $response['error_code'] ?? 'GATEWAY_SERVER_ERROR', 'raw' => $response['body'] ?? null];
        }
        $body = $response['body'];
        $paid = !empty($body['success']);
        return [
            'success' => true,
            'paid' => $paid,
            'status' => $paid ? 'paid' : ($body['status'] ?? 'unknown'),
            'amount' => isset($body['amount']) ? (int) $body['amount'] : null,
            'final_amount' => isset($body['pay_amount']) ? (int) $body['pay_amount'] : (isset($body['amount']) ? (int) $body['amount'] : null),
            'raw' => $body,
            'error' => $paid ? null : ($body['message'] ?? null),
        ];
    }

    public function getPaymentStatus(array $params): array
    {
        $authority = (string) ($params['authority'] ?? '');
        if ($authority === '') {
            return ['success' => false, 'error' => 'authority required', 'error_code' => 'GATEWAY_INVALID_PARAMS'];
        }
        $url = $this->baseUrl . '/status.php?authority=' . urlencode($authority);
        $response = $this->request('GET', $url, null, false);
        if (!$response['success']) {
            return ['success' => false, 'error' => $response['error'] ?? 'Status check failed', 'raw' => $response['body'] ?? null];
        }
        $body = $response['body'];
        return ['success' => true, 'status' => $body['status'] ?? null, 'raw' => $body];
    }

    public function handleCallback(array $requestData): array
    {
        $authority = $requestData['authority'] ?? $requestData['Authority'] ?? null;
        if (!$authority && !empty($requestData['payload'])) {
            $payload = is_string($requestData['payload']) ? json_decode($requestData['payload'], true) : $requestData['payload'];
            $authority = $payload['authority'] ?? null;
        }
        return ['success' => $authority !== null, 'authority' => $authority, 'raw' => $requestData];
    }

    public function handleWebhook(array $payload): array
    {
        return $this->handleCallback($payload);
    }

    public function testConnection(): array
    {
        $start = microtime(true);
        $result = $this->createPayment([
            'amount_rial' => 1000,
            'order_id' => 'test-conn-' . time(),
            'callback_url' => 'https://example.com/callback',
            'description' => 'BaToPay connection test',
            'ttl_minutes' => 5,
        ]);
        $latency = (int) round((microtime(true) - $start) * 1000);
        if ($result['success']) {
            return ['success' => true, 'latency_ms' => $latency, 'message' => 'Connection successful', 'http_code' => 200];
        }
        $err = $result['error'] ?? '';
        $code = $result['error_code'] ?? 'GATEWAY_SERVER_ERROR';
        if (stripos($err, 'unauthor') !== false || stripos($err, 'token') !== false || ($result['http_code'] ?? 0) === 401) {
            $code = 'GATEWAY_AUTH_ERROR';
        }
        return ['success' => false, 'latency_ms' => $latency, 'message' => $err ?: 'Connection failed', 'error_code' => $code, 'http_code' => $result['http_code'] ?? null];
    }

    private function request(string $method, string $url, ?array $body = null, bool $withAuth = true): array
    {
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($withAuth) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }
        try {
            $client = new HttpClient($this->timeout);
            $res = $client->request($method, $url, $body, $headers);
            $decoded = null;
            if (!empty($res['body'])) {
                $decoded = json_decode($res['body'], true);
            }
            if ($res['http_code'] >= 200 && $res['http_code'] < 300) {
                return ['success' => true, 'http_code' => $res['http_code'], 'body' => $decoded ?? [], 'latency' => $res['latency_ms'] ?? null];
            }
            $errorCode = 'GATEWAY_SERVER_ERROR';
            if ($res['http_code'] === 401 || $res['http_code'] === 403) {
                $errorCode = 'GATEWAY_AUTH_ERROR';
            } elseif ($res['http_code'] === 0) {
                $errorCode = 'GATEWAY_TIMEOUT';
            }
            return ['success' => false, 'http_code' => $res['http_code'], 'error' => $decoded['message'] ?? ('HTTP ' . $res['http_code']), 'error_code' => $errorCode, 'body' => $decoded];
        } catch (\Throwable $e) {
            Logger::error('CubePay request failed', ['url' => $url, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage(), 'error_code' => 'GATEWAY_TIMEOUT'];
        }
    }
}
