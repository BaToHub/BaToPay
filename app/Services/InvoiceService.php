<?php
/**
 * Manual invoice creation for platform admin and merchants
 */
declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Payments\GatewayManager;
use PDO;

final class InvoiceService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Connection::get();
    }

    public function create(array $data): array
    {
        $amountToman = (int)($data['amount_toman'] ?? 0);
        if ($amountToman < 1000) {
            return ['success' => false, 'message' => 'حداقل مبلغ ۱۰۰۰ تومان است.'];
        }
        $amountRial = $amountToman * 10;
        $description = mb_substr((string)($data['description'] ?? 'فاکتور دستی'), 0, 255);
        $merchantId = isset($data['merchant_id']) ? (int)$data['merchant_id'] : null;
        $externalOrder = (string)($data['external_order_id'] ?? ('INV-' . date('YmdHis') . '-' . random_int(100, 999)));
        $ttl = max(15, min(1440, (int)($data['ttl_minutes'] ?? 60)));

        $page = null;
        if (!empty($data['page_id'])) {
            $st = $this->pdo->prepare("SELECT * FROM pages WHERE id = ? AND status = 'active'");
            $st->execute([(int)$data['page_id']]);
            $page = $st->fetch(PDO::FETCH_ASSOC);
        }
        if (!$page) {
            $page = $this->pdo->query("SELECT * FROM pages WHERE status = 'active' ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        }
        if (!$page) {
            return ['success' => false, 'message' => 'صفحه پرداخت فعالی وجود ندارد.'];
        }

        $uuid = function_exists('generate_uuid') ? generate_uuid() : bin2hex(random_bytes(16));
        $orderId = function_exists('generate_order_id') ? generate_order_id('INV') : ('INV' . time());
        $siteCallback = rtrim((string) config('app.url'), '/') . '/payment/callback';
        $ip = function_exists('client_ip') ? client_ip() : ($_SERVER['REMOTE_ADDR'] ?? null);
        $ua = function_exists('user_agent') ? user_agent() : ($_SERVER['HTTP_USER_AGENT'] ?? null);

        $this->pdo->beginTransaction();
        try {
            $ins = $this->pdo->prepare(
                'INSERT INTO transactions (uuid, order_id, page_id, amount, amount_rial, currency, status, user_ip, user_agent, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))'
            );
            $ins->execute([$uuid, $orderId, $page['id'], $amountToman, $amountRial, 'IRT', 'pending', $ip, $ua, $ttl]);
            $txId = (int)$this->pdo->lastInsertId();

            $mgr = new GatewayManager();
            $create = $mgr->createWithFailover(
                (int)$page['id'],
                [
                    'amount_rial' => $amountRial,
                    'order_id' => $orderId,
                    'callback_url' => $siteCallback,
                    'description' => $description,
                    'ttl_minutes' => $ttl,
                ],
                null, null, (bool)($page['failover_enabled'] ?? true)
            );

            if (empty($create['success'])) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => $create['error'] ?? 'خطا در ایجاد فاکتور در درگاه'];
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

            if ($merchantId) {
                $tok = $this->pdo->prepare("SELECT id FROM merchant_api_tokens WHERE merchant_id = ? AND status = 'active' LIMIT 1");
                $tok->execute([$merchantId]);
                $tokenId = $tok->fetchColumn() ?: null;
                if ($tokenId) {
                    $this->pdo->prepare(
                        'INSERT INTO merchant_payments (merchant_id, merchant_token_id, external_order_id, transaction_id, amount, amount_rial, description, callback_url, status)
                         VALUES (?,?,?,?,?,?,?,?,?)'
                    )->execute([
                        $merchantId, $tokenId, $externalOrder, $txId, $amountToman, $amountRial,
                        $description, $data['callback_url'] ?? null, 'pending',
                    ]);
                }
            }

            $this->pdo->commit();
            $pageLink = rtrim((string)config('app.url'), '/') . '/pay/' . $page['slug'] . '?tx=' . $uuid;
            $payLink = $create['payment_link'] ?? $pageLink;

            return [
                'success' => true,
                'uuid' => $uuid,
                'order_id' => $orderId,
                'external_order_id' => $externalOrder,
                'payment_link' => $payLink,
                'page_link' => $pageLink,
                'amount_toman' => $amountToman,
                'amount_rial' => $amountRial,
                'authority' => $create['authority'] ?? $uuid,
            ];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'خطای سیستمی در ساخت فاکتور'];
        }
    }
}
