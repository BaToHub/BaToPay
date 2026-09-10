<?php
/**
 * BaToPay Logger — never logs secrets
 */
declare(strict_types=1);

namespace App\Helpers;

use App\Core\Bootstrap;
use App\Database\Connection;

final class Logger
{
    private static array $sensitiveKeys = [
        'token', 'api_key', 'apikey', 'password', 'secret', 'credential',
        'authorization', 'bot_token', 'x-api-key',
    ];

    public static function debug(string $message, array $context = []): void { self::write('debug', $message, $context); }
    public static function info(string $message, array $context = []): void { self::write('info', $message, $context); }
    public static function warning(string $message, array $context = []): void { self::write('warning', $message, $context); }
    public static function error(string $message, array $context = []): void { self::write('error', $message, $context); }
    public static function critical(string $message, array $context = []): void { self::write('critical', $message, $context); }

    private static function write(string $level, string $message, array $context): void
    {
        $context = self::sanitize($context);
        $line = sprintf("[%s] %s: %s %s\n", date('Y-m-d H:i:s'), strtoupper($level), $message, $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : '');
        $logDir = Bootstrap::basePath('storage/logs');
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0750, true);
        }
        @file_put_contents($logDir . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
        try {
            if (Bootstrap::isInstalled()) {
                $pdo = Connection::get();
                $pdo->prepare('INSERT INTO system_logs (level, channel, message, context) VALUES (?, ?, ?, ?)')
                    ->execute([$level, 'app', $message, $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : null]);
            }
        } catch (\Throwable $e) {
        }
    }

    private static function sanitize(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $keyLower = strtolower((string) $k);
            $isSensitive = false;
            foreach (self::$sensitiveKeys as $s) {
                if (str_contains($keyLower, $s)) {
                    $isSensitive = true;
                    break;
                }
            }
            if ($isSensitive) {
                $out[$k] = '***REDACTED***';
            } elseif (is_array($v)) {
                $out[$k] = self::sanitize($v);
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }
}
