<?php
/**
 * BaToPay Gateway Manager
 */
declare(strict_types=1);

namespace App\Payments;

use App\Database\Connection;
use App\Security\Encryption;
use App\Helpers\Logger;
use PDO;

final class GatewayManager
{
    /** @var array<string, class-string<PaymentGatewayInterface>> */
    private static array $map = [
        'cubepay'  => CubePayGateway::class,
        'blupal'   => BluePalGateway::class,
        'sandbox'  => SandboxGateway::class,
    ];

    public function resolve(string $identifier, ?int $credentialId = null): ?PaymentGatewayInterface
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare('SELECT * FROM payment_gateways WHERE identifier = ? AND status = ? LIMIT 1');
        $stmt->execute([$identifier, 'active']);
        $gw = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$gw) {
            return null;
        }
        if ($credentialId) {
            $cStmt = $pdo->prepare('SELECT * FROM gateway_credentials WHERE id = ? AND gateway_id = ? AND status = ? LIMIT 1');
            $cStmt->execute([$credentialId, $gw['id'], 'active']);
        } else {
            $cStmt = $pdo->prepare('SELECT * FROM gateway_credentials WHERE gateway_id = ? AND status = ? ORDER BY is_default DESC, id ASC LIMIT 1');
            $cStmt->execute([$gw['id'], 'active']);
        }
        $cred = $cStmt->fetch(PDO::FETCH_ASSOC);
        if (!$cred) {
            if ($identifier === 'sandbox') {
                return new SandboxGateway();
            }
            return null;
        }
        return $this->instantiate($identifier, $cred);
    }

    public function resolveForPage(int $pageId, string $identifier, ?int $credentialId = null): ?PaymentGatewayInterface
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT g.* FROM payment_gateways g
             INNER JOIN page_gateways pg ON pg.gateway_id = g.id
             WHERE pg.page_id = ? AND g.identifier = ? AND g.status = ? LIMIT 1'
        );
        $stmt->execute([$pageId, $identifier, 'active']);
        $gw = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$gw) {
            return null;
        }
        return $this->resolve($identifier, $credentialId);
    }

    public function listForPage(int $pageId): array
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT g.id, g.name, g.identifier, g.mode, g.config_status
             FROM payment_gateways g
             INNER JOIN page_gateways pg ON pg.gateway_id = g.id
             WHERE pg.page_id = ? AND g.status = ?
             AND EXISTS (
                 SELECT 1 FROM gateway_credentials c
                 WHERE c.gateway_id = g.id AND c.status = ?
             )
             ORDER BY g.id ASC'
        );
        $stmt->execute([$pageId, 'active', 'active']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createWithFailover(
        int $pageId,
        array $paymentParams,
        ?string $primaryIdentifier = null,
        ?string $secondaryIdentifier = null,
        bool $failoverEnabled = false
    ): array {
        $tried = [];
        $order = [];
        if ($primaryIdentifier) {
            $order[] = $primaryIdentifier;
        }
        if ($failoverEnabled && $secondaryIdentifier && $secondaryIdentifier !== $primaryIdentifier) {
            $order[] = $secondaryIdentifier;
        }
        if (empty($order)) {
            $available = $this->listForPage($pageId);
            foreach ($available as $g) {
                $order[] = $g['identifier'];
            }
        }
        foreach ($order as $identifier) {
            if (in_array($identifier, $tried, true)) {
                continue;
            }
            $tried[] = $identifier;
            $gateway = $this->resolveForPage($pageId, $identifier);
            if (!$gateway) {
                continue;
            }
            $result = $gateway->createPayment($paymentParams);
            $result['gateway_identifier'] = $identifier;
            $result['gateway_name'] = $gateway->getName();
            if (!empty($result['success'])) {
                return $result;
            }
            Logger::warning('Gateway createPayment failed', [
                'gateway' => $identifier,
                'error'   => $result['error'] ?? null,
                'code'    => $result['error_code'] ?? null,
            ]);
            if (!$failoverEnabled) {
                return $result;
            }
        }
        return [
            'success'    => false,
            'error'      => 'No available gateway could create the payment',
            'error_code' => 'GATEWAY_NOT_FOUND',
            'tried'      => $tried,
        ];
    }

    private function instantiate(string $identifier, array $credential): ?PaymentGatewayInterface
    {
        if (!isset(self::$map[$identifier])) {
            return null;
        }
        try {
            $token = Encryption::decrypt($credential['credential_encrypted']);
        } catch (\Throwable $e) {
            Logger::error('Failed to decrypt gateway credential', [
                'credential_id' => $credential['id'] ?? null,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
        $class = self::$map[$identifier];
        return new $class($token);
    }

    public static function register(string $identifier, string $className): void
    {
        if (!is_subclass_of($className, PaymentGatewayInterface::class)) {
            throw new \InvalidArgumentException('Class must implement PaymentGatewayInterface');
        }
        self::$map[$identifier] = $className;
    }
}
