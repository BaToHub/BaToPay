<?php
declare(strict_types=1);

namespace App\Security;

use App\Database\Connection;
use PDO;

final class Auth
{
    public static function attempt(string $login, string $password): bool
    {
        $pdo = Connection::get();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND success = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
        $stmt->execute([$ip]);
        if ((int)$stmt->fetchColumn() >= 10) {
            return false;
        }
        $q = $pdo->prepare('SELECT * FROM admins WHERE (username = ? OR email = ?) AND status = ? LIMIT 1');
        $q->execute([$login, $login, 'active']);
        $admin = $q->fetch(PDO::FETCH_ASSOC);
        $ok = $admin && password_verify($password, $admin['password']);
        $pdo->prepare('INSERT INTO login_attempts (ip_address, username, success) VALUES (?, ?, ?)')->execute([$ip, $login, $ok ? 1 : 0]);
        if (!$ok) return false;
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$admin['id'];
        $_SESSION['admin_role'] = (int)$admin['role_id'];
        $_SESSION['admin_name'] = $admin['name'] ?: $admin['username'];
        $pdo->prepare('UPDATE admins SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?')->execute([$ip, $admin['id']]);
        self::audit((int)$admin['id'], 'LOGIN', 'admin', (string)$admin['id']);
        return true;
    }

    public static function check(): bool { return !empty($_SESSION['admin_id']); }
    public static function id(): ?int { return isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null; }

    public static function user(): ?array
    {
        if (!self::check()) return null;
        $pdo = Connection::get();
        $stmt = $pdo->prepare('SELECT a.*, r.slug AS role_slug, r.name AS role_name FROM admins a JOIN roles r ON r.id = a.role_id WHERE a.id = ?');
        $stmt->execute([self::id()]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function logout(): void
    {
        if (self::check()) {
            self::audit(self::id(), 'LOGOUT', 'admin', (string)self::id());
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: login.php');
            exit;
        }
    }

    public static function can(string $permissionSlug): bool
    {
        $user = self::user();
        if (!$user) return false;
        if ((int)$user['role_id'] === 1) return true;
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT 1 FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ? AND p.slug = ? LIMIT 1'
        );
        $stmt->execute([(int)$user['role_id'], $permissionSlug]);
        return (bool)$stmt->fetchColumn();
    }

    public static function audit(?int $adminId, string $action, ?string $entityType = null, ?string $entityId = null, $old = null, $new = null): void
    {
        try {
            $pdo = Connection::get();
            $pdo->prepare(
                'INSERT INTO audit_logs (admin_id, action, entity_type, entity_id, old_data, new_data, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $adminId, $action, $entityType, $entityId,
                $old !== null ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
                $new !== null ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            ]);
        } catch (\Throwable $e) {
        }
    }
}
