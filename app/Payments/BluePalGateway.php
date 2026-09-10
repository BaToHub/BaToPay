<?php
/**
 * BluePal (blupal) Gateway Adapter
 * Docs: http://blupal.net/documentation
 * Base: https://blupal.net/api
 * Auth: X-API-Key: YOUR_API_KEY
 */

declare(strict_types=1);

namespace App\Payments;

use App\Helpers\HttpClient;
use App\Helpers\Logger;

final class BluePalGateway implements PaymentGatewayInterface
{
    private string $apiKey;
    private string $baseUrl;
    private int $timeout;

    public function __construct(string $apiKey, string $baseUrl = 'https://blupal.net/api', int $timeout = 20)
    {
        $this->apiKey  = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
    }

    public function getName(): string { return 'BluePal'; }
    public function getIdentifier(): string { return 'blupal'; }
    public function validateConfiguration(): bool { return $this->apiKey !== ''; }

    public function createPayment(array $params): array
    {
        $amount = (int) ($params['amount_rial'] ?? 0);
        if ($amount < 100000) {
            return ['success' => false, 'error' => 'Minimum amount is 100000 Rial', 'error_code' => 'GATEWAY_INVALID_AMOUNT'];
        }
        $payload = ['amount' => $amount];
        if (!empty($params['card_number'])) {
            $payload['card_number'] = (string) $params['card_number'];
        }
        $url = $this->baseUrl . '/v1/invoices/create';
        $response = $this->request('POST', $url, $payload);
        if (!$response['success']) {
            return ['success' => false, 'error' => $response['error'] ?? 'Create invoice failed', 'error_code' => $response['error_code'] ?? 'GATEWAY_SERVER_ERROR', 'raw' => $response['body'] ?? null, 'http_code' => $response['http_code'] ?? null];
        }
        $body = $response['body'];
        if (empty($body['success'])) {
            return ['success' => false, 'error' => $body['message'] ?? $body['error'] ?? 'Unknown error from BluePal', 'error_code' => 'GATEWAY_API_ERROR', 'raw' => $body];
        }
        return [
            'success' => true,
            'invoice_id' => $body['invoice_id'] ?? null,
            'authority' => (string) ($body['invoice_id'] ?? ''),
            'payment_link' => $body['payment_link'] ?? null,
            'pay_amount' => isset($body['final_amount']) ? (int) $body['final_amount'] : (isset($body['amount']) ? (int) $body['amount'] : null),
            'final_amount' => isset($body['final_amount']) ? (int) $body['final_amount'] : null,
            'is_test' => isset($body['mode']) && $body['mode'] === 'sandbox',
            'raw' => $body,
        ];
    }

    public function verifyPayment(array $params): array
    {
        $invoiceId = $params['invoice_id'] ?? $params['authority'] ?? null;
        if ($invoiceId === null || $invoiceId === '') {
            return ['success' => false, 'error' => 'invoice_id is required', 'error_code' => 'GATEWAY_INVALID_PARAMS'];
        }
        return $this->getPaymentStatus(['invoice_id' => $invoiceId]);
    }

    public function getPaymentStatus(array $params): array
    {
        $invoiceId = $params['invoice_id'] ?? $params['authority'] ?? null;
        if ($invoiceId === null || $invoiceId === '') {
            return ['success' => false, 'error' => 'invoice_id is required', 'error_code' => 'GATEWAY_INVALID_PARAMS'];
        }
        $url = $this->baseUrl . '/v1/invoices/' . urlencode((string) $invoiceId);
        $response = $this->request('GET', $url);
        if (!$response['success']) {
            return ['success' => false, 'error' => $response['error'] ?? 'Status check failed', 'error_code' => $response['error_code'] ?? 'GATEWAY_SERVER_ERROR', 'raw' => $response['body'] ?? null];
        }
        $body = $response['body'];
        if (empty($body['success'])) {
            return ['success' => false, 'error' => $body['error'] ?? 'Invoice not found', 'error_code' => 'GATEWAY_NOT_FOUND', 'raw' => $body];
        }
        $status = strtoupper((string) ($body['status'] ?? ''));
        $paid = $status === 'PAID';
        return [
            'success' => true,
            'paid' => $paid,
            'status' => strtolower($status),
            'amount' => isset($body['amount']) ? (int) $body['amount'] : null,
            'final_amount' => isset($body['final_amount']) ? (int) $body['final_amount'] : null,
            'raw' => $body,
        ];
    }

    public function handleCallback(array $requestData): array
    {
        $invoiceId = $requestData['invoice_id'] ?? $requestData['invoiceId'] ?? null;
        return ['success' => $invoiceId !== null, 'invoice_id' => $invoiceId, 'authority' => $invoiceId !== null ? (string) $invoiceId : null, 'raw' => $requestData];
    }

    public function handleWebhook(array $payload): array
    {
        $event = $payload['event'] ?? null;
        $status = strtoupper((string) ($payload['status'] ?? ''));
        $invoiceId = $payload['invoice_id'] ?? null;
        if ($event !== 'payment.completed' || $status !== 'PAID' || $invoiceId === null) {
            return ['success' => false, 'error' => 'Invalid webhook payload', 'error_code' => 'GATEWAY_INVALID_WEBHOOK', 'raw' => $payload];
        }
        return [
            'success' => true,
            'paid' => true,
            'status' => 'paid',
            'invoice_id' => $invoiceId,
            'authority' => (string) $invoiceId,
            'amount' => isset($payload['amount']) ? (int) $payload['amount'] : null,
            'final_amount' => isset($payload['final_amount']) ? (int) $payload['final_amount'] : null,
            'raw' => $payload,
        ];
    }

    public function testConnection(): array
    {
        $start = microtime(true);
        $result = $this->createPayment(['amount_rial' => 100000]);
        $latency = (int) round((microtime(true) - $start) * 1000);
        if ($result['success']) {
            return ['success' => true, 'latency_ms' => $latency, 'message' => 'Connection successful', 'http_code' => 200];
        }
        $err = $result['error'] ?? '';
        $code = $result['error_code'] ?? 'GATEWAY_SERVER_ERROR';
        if (stripos($err, 'unauthor') !== false || stripos($err, 'api key') !== false || ($result['http_code'] ?? 0) === 401) {
            $code = 'GATEWAY_AUTH_ERROR';
        }
        return ['success' => false, 'latency_ms' => $latency, 'message' => $err ?: 'Connection failed', 'error_code' => $code, 'http_code' => $result['http_code'] ?? null];
    }

    private function request(string $method, string $url, ?array $body = null): array
    {
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'X-API-Key: ' . $this->apiKey];
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
            return ['success' => false, 'http_code' => $res['http_code'], 'error' => $decoded['message'] ?? $decoded['error'] ?? ('HTTP ' . $res['http_code']), 'error_code' => $errorCode, 'body' => $decoded];
        } catch (\Throwable $e) {
            Logger::error('BluePal request failed', ['url' => $url, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage(), 'error_code' => 'GATEWAY_TIMEOUT'];
        }
    }
}
