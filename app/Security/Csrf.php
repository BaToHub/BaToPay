<?php
/**
 * BaToPay CSRF Protection
 */

declare(strict_types=1);

namespace App\Security;

use App\Core\Bootstrap;

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::SESSION_KEY];
    }

    public static function validate(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }
        $sessionToken = $_SESSION[self::SESSION_KEY] ?? '';
        return hash_equals($sessionToken, $token);
    }

    public static function validateRequest(): bool
    {
        $name = Bootstrap::config('app.csrf_token_name', '_csrf');
        $token = $_POST[$name] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        return self::validate($token);
    }

    public static function regenerate(): void
    {
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
    }
}
