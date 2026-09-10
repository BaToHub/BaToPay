<?php
/**
 * BaToPay Payment Service
 * Orchestrates create → verify → mark paid with idempotency & amount checks
 */
declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Payments\GatewayManager;
use App\Helpers\Logger;
use PDO;

final class PaymentService
{
    private GatewayManager $gateways;

    public function __construct(?GatewayManager $gateways = null)
    {
        $this->gateways = $gateways ?? new GatewayManager();
    }

    public function initiate(int $pageId, int $amountToman, string $gatewayIdentifier, array $meta = []): array
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare('SELECT * FROM pages WHERE id = ? AND status = ? LIMIT 1');
        $stmt->execute([$pageId, 'active']);
        $page = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$page) {
            return ['success' => false, 'error' => 'صفحه یافت نشد یا غیرفعال است'];
        }
        if ($amountToman < (int) $page['min_amount']) {
            return ['success' => false, 'error' => 'مبلغ کمتر از حداقل مجاز است'];
        }
        if ($page['max_amount'] !== null && $amountToman > (int) $page['max_amount']) {
            return ['success' => false, 'error' => 'مبلغ بیشتر از حداکثر مجاز است'];
        }

        $orderId = generate_order_id('BT');
        $uuid = generate_uuid();
        $amountRial = toman_to_rial($amountToman);
        $callbackUrl = rtrim((string) config('app.url'), '/') . '/payment/callback';

        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare(
                'INSERT INTO transactions
                (uuid, order_id, page_id, amount, amount_rial, currency, status, user_ip, user_agent, expires_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 60 MINUTE))'
            );
            $ins->execute([$uuid, $orderId, $pageId, $amountToman, $amountRial, 'IRT', 'pending', client_ip(), user_agent()]);
            $txId = (int) $pdo->lastInsertId();

            $createResult = $this->gateways->createWithFailover(
                $pageId,
                [
                    'amount_rial'  => $amountRial,
                    'order_id'     => $orderId,
                    'callback_url' => $callbackUrl,
                    'description'  => $page['title'] . ' - ' . $orderId,
                    'ttl_minutes'  => 60,
                ],
                $page['primary_gateway_id'] ? $this->identifierById((int) $page['primary_gateway_id']) : $gatewayIdentifier,
                $page['secondary_gateway_id'] ? $this->identifierById((int) $page['secondary_gateway_id']) : null,
                (bool) $page['failover_enabled']
            );

            if (empty($createResult['success'])) {
                $pdo->rollBack();
                return ['success' => false, 'error' => $createResult['error'] ?? 'خطا در ایجاد فاکتور درگاه', 'code' => $createResult['error_code'] ?? null];
            }

            $gwId = $this->idByIdentifier($createResult['gateway_identifier'] ?? $gatewayIdentifier);
            $upd = $pdo->prepare(
                'UPDATE transactions SET gateway_id = ?, gateway_invoice_id = ?, authority = ?, final_amount = ?, status = ?, updated_at = NOW() WHERE id = ?'
            );
            $upd->execute([
                $gwId,
                $createResult['invoice_id'] ?? $createResult['authority'] ?? null,
                $createResult['authority'] ?? null,
                $createResult['final_amount'] ?? $createResult['pay_amount'] ?? null,
                'redirected',
                $txId,
            ]);
            $this->logTx($txId, 'created', 'Transaction created and redirected to gateway', [
                'gateway' => $createResult['gateway_identifier'] ?? null,
                'authority' => $createResult['authority'] ?? null,
            ]);
            $pdo->commit();
            return [
                'success' => true,
                'transaction' => ['id' => $txId, 'uuid' => $uuid, 'order_id' => $orderId],
                'payment_link' => $createResult['payment_link'],
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Logger::error('Payment initiate failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'خطای سیستمی در ایجاد پرداخت'];
        }
    }

    public function verifyAndMarkPaid(string $orderIdOrUuid, array $gatewayData, string $source = 'webhook'): array
    {
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM transactions WHERE order_id = ? OR uuid = ? OR authority = ? FOR UPDATE');
            $ref = $gatewayData['authority'] ?? $gatewayData['invoice_id'] ?? $orderIdOrUuid;
            $stmt->execute([$orderIdOrUuid, $orderIdOrUuid, (string) $ref]);
            $tx = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$tx) {
                $stmt2 = $pdo->prepare('SELECT * FROM transactions WHERE authority = ? OR gateway_invoice_id = ? FOR UPDATE');
                $stmt2->execute([(string) $ref, (string) $ref]);
                $tx = $stmt2->fetch(PDO::FETCH_ASSOC);
            }
            if (!$tx) {
                $pdo->rollBack();
                return ['success' => false, 'error' => 'Transaction not found', 'code' => 'TX_NOT_FOUND'];
            }
            if (in_array($tx['status'], ['paid', 'form_pending', 'completed'], true)) {
                $pdo->commit();
                return ['success' => true, 'already' => true, 'transaction' => $tx];
            }
            if (!in_array($tx['status'], ['pending', 'redirected', 'verification_failed'], true)) {
                $pdo->rollBack();
                return ['success' => false, 'error' => 'Invalid transaction status', 'code' => 'TX_INVALID_STATUS'];
            }
            $gwIdentifier = $this->identifierById((int) $tx['gateway_id']);
            if (!$gwIdentifier) {
                $pdo->rollBack();
                return ['success' => false, 'error' => 'Gateway missing', 'code' => 'GATEWAY_NOT_FOUND'];
            }
            $gateway = $this->gateways->resolve($gwIdentifier);
            if (!$gateway) {
                $pdo->rollBack();
                return ['success' => false, 'error' => 'Gateway unavailable', 'code' => 'GATEWAY_DISABLED'];
            }
            $verifyParams = ['authority' => $tx['authority'], 'invoice_id' => $tx['gateway_invoice_id']];
            if (!empty($gatewayData['scenario'])) {
                $verifyParams['scenario'] = $gatewayData['scenario'];
            }
            $verify = $gateway->verifyPayment($verifyParams);
            if (empty($verify['success']) || empty($verify['paid'])) {
                $upd = $pdo->prepare('UPDATE transactions SET status = ?, callback_data = ?, updated_at = NOW() WHERE id = ?');
                $upd->execute(['verification_failed', json_encode(['source' => $source, 'verify' => $verify], JSON_UNESCAPED_UNICODE), $tx['id']]);
                $this->logTx((int) $tx['id'], 'verify_failed', 'Gateway verification failed', ['source' => $source]);
                $pdo->commit();
                return ['success' => false, 'error' => 'Verification failed', 'code' => 'VERIFICATION_FAILED'];
            }
            $expectedRial = (int) $tx['amount_rial'];
            $verifiedAmount = (int) ($verify['final_amount'] ?? $verify['amount'] ?? 0);
            if ($verifiedAmount > 0) {
                $diff = abs($verifiedAmount - $expectedRial);
                if ($verifiedAmount < $expectedRial || $diff > 999) {
                    $upd = $pdo->prepare('UPDATE transactions SET status = ?, callback_data = ?, updated_at = NOW() WHERE id = ?');
                    $upd->execute(['verification_failed', json_encode(['source' => $source, 'expected_rial' => $expectedRial, 'verified' => $verifiedAmount, 'reason' => 'amount_mismatch'], JSON_UNESCAPED_UNICODE), $tx['id']]);
                    $this->logTx((int) $tx['id'], 'amount_mismatch', 'Amount mismatch', ['expected' => $expectedRial, 'got' => $verifiedAmount]);
                    $pdo->commit();
                    return ['success' => false, 'error' => 'Amount mismatch', 'code' => 'AMOUNT_MISMATCH'];
                }
            }
            $upd = $pdo->prepare(
                "UPDATE transactions SET status = ?, final_amount = ?, verified_at = NOW(), paid_at = NOW(), webhook_data = ?, updated_at = NOW()
                 WHERE id = ? AND status IN ('pending','redirected','verification_failed')"
            );
            $upd->execute(['form_pending', $verifiedAmount ?: $tx['final_amount'], json_encode(['source' => $source, 'verify' => $verify['raw'] ?? $verify], JSON_UNESCAPED_UNICODE), $tx['id']]);
            if ($upd->rowCount() === 0) {
                $pdo->commit();
                $fresh = $pdo->prepare('SELECT * FROM transactions WHERE id = ?');
                $fresh->execute([$tx['id']]);
                return ['success' => true, 'already' => true, 'transaction' => $fresh->fetch(PDO::FETCH_ASSOC)];
            }
            $this->logTx((int) $tx['id'], 'paid', 'Payment verified and marked form_pending', ['source' => $source]);
            try {
                $mp = $pdo->prepare('SELECT merchant_id, external_order_id, amount, amount_rial FROM merchant_payments WHERE transaction_id = ? LIMIT 1');
                $mp->execute([(int)$tx['id']]);
                $row = $mp->fetch(PDO::FETCH_ASSOC);
                if ($row && !empty($row['merchant_id'])) {
                    WebhookDispatcher::dispatch((int)$row['merchant_id'], 'payment.paid', [
                        'order_id' => $row['external_order_id'],
                        'amount' => (int)$row['amount_rial'],
                        'amount_toman' => (int)$row['amount'],
                        'transaction_uuid' => $tx['uuid'] ?? null,
                    ]);
                }
            } catch (\Throwable $e) {
            }
            $pdo->commit();
            $fresh = $pdo->prepare('SELECT * FROM transactions WHERE id = ?');
            $fresh->execute([$tx['id']]);
            return ['success' => true, 'transaction' => $fresh->fetch(PDO::FETCH_ASSOC)];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Logger::error('verifyAndMarkPaid failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'System error during verification'];
        }
    }

    private function logTx(int $txId, string $event, string $message, array $data = []): void
    {
        try {
            $pdo = Connection::get();
            $pdo->prepare('INSERT INTO transaction_logs (transaction_id, event, message, data) VALUES (?, ?, ?, ?)')
                ->execute([$txId, $event, $message, $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null]);
        } catch (\Throwable $e) {
        }
    }

    private function identifierById(int $id): ?string
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare('SELECT identifier FROM payment_gateways WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['identifier'] ?? null;
    }

    private function idByIdentifier(string $identifier): ?int
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare('SELECT id FROM payment_gateways WHERE identifier = ?');
        $stmt->execute([$identifier]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['id'] : null;
    }
}
