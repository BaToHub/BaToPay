<?php
declare(strict_types=1);

use App\Core\Bootstrap;
use App\Security\Csrf;
use App\Security\Encryption;

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (!function_exists('config')) {
    function config(string $key, $default = null) { return Bootstrap::config($key, $default); }
}
if (!function_exists('base_path')) {
    function base_path(string $path = ''): string { return Bootstrap::basePath($path); }
}
if (!function_exists('asset')) {
    function asset(string $path): string {
        return rtrim((string) config('app.url'), '/') . '/assets/' . ltrim($path, '/');
    }
}
if (!function_exists('url')) {
    function url(string $path = ''): string {
        return rtrim((string) config('app.url'), '/') . '/' . ltrim($path, '/');
    }
}
if (!function_exists('redirect')) {
    function redirect(string $to, int $code = 302): never {
        header('Location: ' . $to, true, $code);
        exit;
    }
}
if (!function_exists('json_response')) {
    function json_response(array $data, int $code = 200): never {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
if (!function_exists('toman')) {
    function toman(int|string $amount): string {
        return number_format((int) $amount, 0, '.', ',') . ' تومان';
    }
}
if (!function_exists('toman_to_rial')) {
    function toman_to_rial(int $toman): int { return $toman * 10; }
}
if (!function_exists('rial_to_toman')) {
    function rial_to_toman(int $rial): int { return (int) floor($rial / 10); }
}
if (!function_exists('generate_order_id')) {
    function generate_order_id(string $prefix = 'BT'): string {
        return $prefix . '-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
    }
}
if (!function_exists('generate_uuid')) {
    function generate_uuid(): string {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
if (!function_exists('csrf_token')) {
    function csrf_token(): string { return Csrf::token(); }
}
if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        $name = config('app.csrf_token_name', '_csrf');
        return '<input type="hidden" name="' . e($name) . '" value="' . e(Csrf::token()) . '">';
    }
}
if (!function_exists('old')) {
    function old(string $key, $default = '') { return $_SESSION['_old'][$key] ?? $default; }
}
if (!function_exists('flash')) {
    function flash(string $key, $value = null) {
        if ($value !== null) { $_SESSION['_flash'][$key] = $value; return null; }
        $val = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $val;
    }
}
if (!function_exists('client_ip')) {
    function client_ip(): string {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
if (!function_exists('user_agent')) {
    function user_agent(): string {
        return mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
    }
}
if (!function_exists('mask_credential')) {
    function mask_credential(string $value): string { return Encryption::mask($value); }
}
