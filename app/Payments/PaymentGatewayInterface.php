<?php
declare(strict_types=1);

namespace App\Payments;

interface PaymentGatewayInterface
{
    public function getName(): string;
    public function getIdentifier(): string;
    public function validateConfiguration(): bool;
    public function createPayment(array $params): array;
    public function verifyPayment(array $params): array;
    public function getPaymentStatus(array $params): array;
    public function handleCallback(array $requestData): array;
    public function handleWebhook(array $payload): array;
    public function testConnection(): array;
}
