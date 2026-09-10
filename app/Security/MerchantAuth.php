<?php
declare(strict_types=1);

namespace App\Security;

use App\Database\Connection;
use PDO;

final class MerchantAuth
{
    public static function attempt(string $email, string $password): bool
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare("SELECT * FROM merchants WHERE email = ? AND status = 'approved' LIMIT 1");
        $stmt->execute([trim($email)]);
        $m = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$m || !password_verify($password, $m['password'])) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['merchant_id'] = (int)$m['id'];
        $_SESSION['merchant_name'] = $m['shop_name'];
        $pdo->prepare('UPDATE merchants SET last_login_at = NOW() WHERE id = ?')->execute([$m['id']]);
        return true;
    }

    public static function check(): bool
    {
        return !empty($_SESSION['merchant_id']);
    }

    public static function id(): ?int
    {
        return isset($_SESSION['merchant_id']) ? (int)$_SESSION['merchant_id'] : null;
    }

    public static function user(): ?array
    {
        if (!self::check()) return null;
        $pdo = Connection::get();
        $s = $pdo->prepare('SELECT * FROM merchants WHERE id = ? AND status = ?');
        $s->execute([self::id(), 'approved']);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: login.php');
            exit;
        }
        if (!self::user()) {
            self::logout();
            header('Location: login.php');
            exit;
        }
    }

    public static function logout(): void
    {
        unset($_SESSION['merchant_id'], $_SESSION['merchant_name']);
    }
}
