<?php
declare(strict_types=1);

namespace App\Payments;

final class SandboxGateway implements PaymentGatewayInterface
{
    public function getName(): string { return 'Sandbox'; }
    public function getIdentifier(): string { return 'sandbox'; }
    public function validateConfiguration(): bool { return true; }

    public function createPayment(array $params): array
    {
        $authority = 'SBX-' . strtoupper(bin2hex(random_bytes(8)));
        $amount = (int)($params['amount_rial'] ?? 0);
        $base = rtrim((string)(function_exists('config') ? config('app.url') : ''), '/');
        $link = $base . '/sandbox/pay.php?authority=' . urlencode($authority);
        return [
            'success' => true,
            'authority' => $authority,
            'invoice_id' => $authority,
            'payment_link' => $link,
            'pay_amount' => $amount,
            'final_amount' => $amount,
            'is_test' => true,
            'raw' => ['sandbox' => true],
        ];
    }

    public function verifyPayment(array $params): array
    {
        $scenario = strtoupper((string)($params['scenario'] ?? 'SUCCESS'));
        if ($scenario === 'FAILED') {
            return ['success' => false, 'paid' => false, 'status' => 'failed', 'error' => 'Sandbox FAILED'];
        }
        if ($scenario === 'PENDING') {
            return ['success' => false, 'paid' => false, 'status' => 'pending', 'error' => 'Sandbox PENDING'];
        }
        if ($scenario === 'EXPIRED') {
            return ['success' => false, 'paid' => false, 'status' => 'expired', 'error' => 'Sandbox EXPIRED'];
        }
        $amount = (int)($params['amount'] ?? $params['amount_rial'] ?? 0);
        return [
            'success' => true,
            'paid' => true,
            'status' => 'paid',
            'amount' => $amount,
            'final_amount' => $amount,
            'raw' => ['sandbox' => true, 'scenario' => $scenario],
        ];
    }

    public function getPaymentStatus(array $params): array { return $this->verifyPayment($params); }

    public function handleCallback(array $requestData): array
    {
        return [
            'authority' => $requestData['authority'] ?? null,
            'invoice_id' => $requestData['authority'] ?? null,
            'scenario' => $requestData['scenario'] ?? 'SUCCESS',
        ];
    }

    public function handleWebhook(array $payload): array { return $this->handleCallback($payload); }

    public function testConnection(): array
    {
        return ['success' => true, 'latency_ms' => 1, 'message' => 'Sandbox always OK', 'http_code' => 200];
    }
}
