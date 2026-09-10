<?php
/**
 * BaToPay Payment Gateway Interface
 * All gateways must implement this contract.
 */

declare(strict_types=1);

namespace App\Payments;

interface PaymentGatewayInterface
{
    public function getName(): string;
    public function getIdentifier(): string;
    public function validateConfiguration(): bool;

    /**
     * @param array{
     *   amount_rial: int,
     *   order_id: string,
     *   callback_url: string,
     *   description?: string,
     *   ttl_minutes?: int,
     *   card_number?: string
     * } $params
     * @return array{
     *   success: bool,
     *   authority?: string,
     *   invoice_id?: string|int,
     *   payment_link?: string,
     *   pay_amount?: int,
     *   final_amount?: int,
     *   is_test?: bool,
     *   raw?: array,
     *   error?: string,
     *   error_code?: string
     * }
     */
    public function createPayment(array $params): array;

    /**
     * @param array{authority?: string, invoice_id?: string|int} $params
     * @return array{
     *   success: bool,
     *   status?: string,
     *   amount?: int,
     *   final_amount?: int,
     *   paid?: bool,
     *   raw?: array,
     *   error?: string,
     *   error_code?: string
     * }
     */
    public function verifyPayment(array $params): array;

    public function getPaymentStatus(array $params): array;
    public function handleCallback(array $requestData): array;
    public function handleWebhook(array $payload): array;

    /**
     * @return array{success: bool, latency_ms?: int, message?: string, http_code?: int}
     */
    public function testConnection(): array;
}
