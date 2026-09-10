<?php
declare(strict_types=1);

namespace App\Security;

use App\Database\Connection;
use PDO;

/** Simple file-backed rate limiter for login, API, payment create. */
final class RateLimiter
{
    public static function attempt(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $now = time();
        $dir = dirname(__DIR__, 2) . '/storage/cache/ratelimit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $file = $dir . '/' . hash('sha256', $key) . '.json';
        $data = ['count' => 0, 'start' => $now];
        if (is_file($file)) {
            $raw = @file_get_contents($file);
            $decoded = $raw ? json_decode($raw, true) : null;
            if (is_array($decoded) && isset($decoded['start'], $decoded['count'])) {
                if (($now - (int)$decoded['start']) <= $windowSeconds) {
                    $data = $decoded;
                }
            }
        }
        if (($now - (int)$data['start']) > $windowSeconds) {
            $data = ['count' => 0, 'start' => $now];
        }
        $data['count'] = (int)$data['count'] + 1;
        @file_put_contents($file, json_encode($data), LOCK_EX);
        return $data['count'] <= $maxAttempts;
    }

    public static function tooMany(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        return !self::attempt($key, $maxAttempts, $windowSeconds);
    }
}
