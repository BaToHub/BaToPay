<?php
/**
 * BaToPay Bootstrap
 */
declare(strict_types=1);

namespace App\Core;

use App\Security\Encryption;
use App\Helpers\Logger;

final class Bootstrap
{
    private static bool $booted = false;
    private static array $config = [];

    public static function init(): void
    {
        if (self::$booted) {
            return;
        }
        self::loadConfig();
        self::setErrorHandling();
        self::setTimezone();
        self::startSession();
        self::loadHelpers();
        self::$booted = true;
    }

    private static function loadConfig(): void
    {
        $basePath = dirname(__DIR__, 2);
        $appConfig = require $basePath . '/config/app.php';
        $dbConfig  = require $basePath . '/config/database.php';
        self::$config = [
            'app' => $appConfig,
            'db'  => $dbConfig,
            'base_path' => $basePath,
        ];
        $keyFile = $basePath . '/storage/secure/key.php';
        if (is_file($keyFile)) {
            $keyData = require $keyFile;
            if (!empty($keyData['key'])) {
                Encryption::setKey($keyData['key']);
            }
        }
    }

    private static function setErrorHandling(): void
    {
        $debug = self::$config['app']['debug'] ?? false;
        if ($debug) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(E_ALL);
            ini_set('display_errors', '0');
            ini_set('log_errors', '1');
        }
        set_exception_handler(function (\Throwable $e) {
            Logger::error('Uncaught exception: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            if (!(self::$config['app']['debug'] ?? false)) {
                http_response_code(500);
                if (php_sapi_name() !== 'cli') {
                    include dirname(__DIR__, 2) . '/public/errors/500.php';
                }
                exit;
            }
            throw $e;
        });
    }

    private static function setTimezone(): void
    {
        date_default_timezone_set(self::$config['app']['timezone'] ?? 'Asia/Tehran');
    }

    private static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name('batopay_sess');
        session_start();
    }

    private static function loadHelpers(): void
    {
        $helpers = dirname(__DIR__) . '/Helpers/functions.php';
        if (is_file($helpers)) {
            require_once $helpers;
        }
    }

    public static function config(?string $key = null, $default = null)
    {
        if ($key === null) {
            return self::$config;
        }
        $parts = explode('.', $key);
        $val = self::$config;
        foreach ($parts as $p) {
            if (!is_array($val) || !array_key_exists($p, $val)) {
                return $default;
            }
            $val = $val[$p];
        }
        return $val;
    }

    public static function isInstalled(): bool
    {
        $lock = self::$config['base_path'] . '/config/installed.lock';
        return is_file($lock);
    }

    public static function basePath(string $append = ''): string
    {
        $base = self::$config['base_path'] ?? dirname(__DIR__, 2);
        return $append === '' ? $base : $base . '/' . ltrim($append, '/');
    }
}
