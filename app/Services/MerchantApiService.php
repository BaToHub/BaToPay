<?php
/**
 * Merchant API Service — CubePay-compatible style for third-party bots
 * Auth: Authorization: Bearer btp_xxxxx
 */
declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Payments\GatewayManager;
use PDO;

final class MerchantApiService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Connection::get();
    }

    public function authenticate(?string $bearer): ?array
    {
        if (!$bearer || !str_starts_with($bearer, 'Bearer ')) {
            return null;
        }
        $token = trim(substr($bearer, 7));
        if ($token === '' || strlen($token) < 16) {
            return null;
        }
        $hash = hash('sha256', $token);
        $stmt = $this->pdo->prepare(
            'SELECT t.*, m.status AS merchant_status, m.shop_name
             FROM merchant_api_tokens t
             LEFT JOIN merchants m ON m.id = t.merchant_id
             WHERE t.token_hash = ? AND t.status = ? LIMIT 1'
        );
        $stmt->execute([$hash, 'active']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        if (!empty($row['merchant_id']) && ($row['merchant_status'] ?? '') !== 'approved') {
            return null;
        }
        $this->pdo->prepare('UPDATE merchant_api_tokens SET last_used_at = NOW() WHERE id = ?')->execute([$row['id']]);
        return $row;
    }

    public function createPayment(array $merchant, array $params): array
    {
        $amountRial = (int)($params['amount'] ?? 0);
        $orderId = (string)($params['order_id'] ?? '');
        $callbackUrl = (string)($params['callback_url'] ?? $merchant['callback_url'] ?? '');
        $description = mb_substr((string)($params['description'] ?? ''), 0, 255);
        $pageSlug = (string)($params['page_slug'] ?? '');

        if ($amountRial < 10000) {
            return ['success' => false, 'message' => 'حداقل مبلغ ۱۰۰۰ تومان (۱۰۰۰۰ ریال)'];
        }
        if ($orderId === '' || strlen($orderId) > 64) {
            return ['success' => false, 'message' => 'order_id الزامی است (حداکثر ۶۴ کاراکتر)'];
        }

        $ex = $this->pdo->prepare('SELECT id FROM merchant_payments WHERE merchant_token_id = ? AND external_order_id = ?');
        $ex->execute([$merchant['id'], $orderId]);
        if ($ex->fetch()) {
            return ['success' => false, 'message' => 'order_id تکراری است'];
        }

        $amountToman = (int) floor($amountRial / 10);

        $page = null;
        if ($pageSlug !== '') {
            $ps = $this->pdo->prepare('SELECT * FROM pages WHERE slug = ? AND status = ? LIMIT 1');
            $ps->execute([$pageSlug, 'active']);
            $page = $ps->fetch(PDO::FETCH_ASSOC);
        }
        if (!$page) {
            $page = $this->pdo->query("SELECT * FROM pages WHERE status = 'active' ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        }
        if (!$page) {
            return ['success' => false, 'message' => 'هیچ صفحه پرداخت فعالی وجود ندارد'];
        }

        $uuid = generate_uuid();
        $internalOrder = generate_order_id('API');
        $siteCallback = rtrim((string) config('app.url'), '/') . '/payment/callback';

        $this->pdo->beginTransaction();
        try {
            $ins = $this->pdo->prepare(
                'INSERT INTO transactions (uuid, order_id, page_id, amount, amount_rial, currency, status, user_ip, user_agent, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 60 MINUTE))'
            );
            $ins->execute([
                $uuid, $internalOrder, $page['id'], $amountToman, $amountRial, 'IRT', 'pending',
                client_ip(), user_agent(),
            ]);
            $txId = (int) $this->pdo->lastInsertId();

            $mgr = new GatewayManager();
            $create = $mgr->createWithFailover(
                (int)$page['id'],
                [
                    'amount_rial' => $amountRial,
                    'order_id' => $internalOrder,
                    'callback_url' => $siteCallback,
                    'description' => $description ?: ($page['title'] . ' - ' . $orderId),
                    'ttl_minutes' => 60,
                ],
                null, null, (bool)$page['failover_enabled']
            );

            if (empty($create['success'])) {
                $this->pdo->rollBack();
                return [
                    'success' => false,
                    'message' => $create['error'] ?? 'خطا در ایجاد فاکتور',
                    'error_code' => $create['error_code'] ?? null,
                ];
            }

            $gwIdStmt = $this->pdo->prepare('SELECT id FROM payment_gateways WHERE identifier = ?');
            $gwIdStmt->execute([$create['gateway_identifier'] ?? '']);
            $gwId = $gwIdStmt->fetchColumn() ?: null;

            $this->pdo->prepare(
                'UPDATE transactions SET gateway_id=?, gateway_invoice_id=?, authority=?, final_amount=?, status=? WHERE id=?'
            )->execute([
                $gwId,
                $create['invoice_id'] ?? $create['authority'] ?? null,
                $create['authority'] ?? null,
                $create['final_amount'] ?? $create['pay_amount'] ?? null,
                'redirected',
                $txId,
            ]);

            $this->pdo->prepare(
                'INSERT INTO merchant_payments (merchant_id, merchant_token_id, external_order_id, transaction_id, amount, amount_rial, description, callback_url, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $merchant['merchant_id'] ?? null, $merchant['id'], $orderId, $txId, $amountToman, $amountRial, $description,
                $callbackUrl ?: null, 'pending',
            ]);
            if (!empty($merchant['merchant_id'])) {
                $this->pdo->prepare(
                    'UPDATE merchants SET total_transactions = total_transactions + 1 WHERE id = ?'
                )->execute([$merchant['merchant_id']]);
            }

            $this->pdo->commit();

            $payLink = $create['payment_link'] ?? null;
            $pageLink = rtrim((string)config('app.url'), '/') . '/pay/' . $page['slug'] . '?tx=' . $uuid;

            return [
                'success' => true,
                'authority' => $create['authority'] ?? $uuid,
                'payment_link' => $payLink,
                'page_link' => $pageLink,
                'order_id' => $orderId,
                'internal_order_id' => $internalOrder,
                'amount' => $amountRial,
                'amount_toman' => $amountToman,
                'pay_amount' => $create['pay_amount'] ?? $amountRial,
                'is_test' => !empty($create['is_test']),
            ];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'خطای سیستمی'];
        }
    }

    public function verifyPayment(array $merchant, string $authorityOrOrder): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT mp.*, t.status AS tx_status, t.authority, t.amount, t.amount_rial, t.paid_at, t.uuid
             FROM merchant_payments mp
             LEFT JOIN transactions t ON t.id = mp.transaction_id
             WHERE mp.merchant_token_id = ?
               AND (mp.external_order_id = ? OR t.authority = ? OR t.uuid = ?)
             LIMIT 1'
        );
        $stmt->execute([$merchant['id'], $authorityOrOrder, $authorityOrOrder, $authorityOrOrder]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['success' => false, 'message' => 'پرداخت یافت نشد'];
        }

        $paid = in_array($row['tx_status'], ['paid', 'form_pending', 'completed'], true)
            || $row['status'] === 'paid';

        if ($paid && $row['status'] !== 'paid') {
            $this->pdo->prepare("UPDATE merchant_payments SET status='paid', paid_at=NOW() WHERE id=?")
                ->execute([$row['id']]);
        }

        return [
            'success' => true,
            'paid' => $paid,
            'status' => $paid ? 'paid' : ($row['tx_status'] ?? $row['status']),
            'order_id' => $row['external_order_id'],
            'amount' => (int)$row['amount_rial'],
            'amount_toman' => (int)$row['amount'],
            'paid_at' => $row['paid_at'],
        ];
    }

    public static function generateToken(): string
    {
        return 'btp_' . bin2hex(random_bytes(24));
    }
}
