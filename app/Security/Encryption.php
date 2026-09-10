<?php
/**
 * BaToPay Encryption Service
 * AES-256-GCM for credentials
 */
declare(strict_types=1);

namespace App\Security;

final class Encryption
{
    private static ?string $key = null;
    private const CIPHER = 'aes-256-gcm';
    private const TAG_LENGTH = 16;

    public static function setKey(string $key): void
    {
        if (strlen($key) === 32) {
            self::$key = $key;
        } else {
            $decoded = base64_decode($key, true);
            if ($decoded !== false && strlen($decoded) === 32) {
                self::$key = $decoded;
            } else {
                self::$key = hash('sha256', $key, true);
            }
        }
    }

    public static function hasKey(): bool
    {
        return self::$key !== null && strlen(self::$key) === 32;
    }

    public static function generateKey(): string
    {
        return base64_encode(random_bytes(32));
    }

    public static function encrypt(string $plaintext): string
    {
        if (!self::hasKey()) {
            throw new \RuntimeException('Encryption key is not set.');
        }
        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            self::$key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );
        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed.');
        }
        return base64_encode($iv . $tag . $ciphertext);
    }

    public static function decrypt(string $payload): string
    {
        if (!self::hasKey()) {
            throw new \RuntimeException('Encryption key is not set.');
        }
        $data = base64_decode($payload, true);
        if ($data === false) {
            throw new \RuntimeException('Invalid encrypted payload.');
        }
        $ivLen = openssl_cipher_iv_length(self::CIPHER);
        if (strlen($data) < $ivLen + self::TAG_LENGTH) {
            throw new \RuntimeException('Encrypted payload too short.');
        }
        $iv = substr($data, 0, $ivLen);
        $tag = substr($data, $ivLen, self::TAG_LENGTH);
        $ciphertext = substr($data, $ivLen + self::TAG_LENGTH);
        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            self::$key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed.');
        }
        return $plaintext;
    }

    public static function mask(string $value, int $visibleStart = 6, int $visibleEnd = 4): string
    {
        $len = mb_strlen($value);
        if ($len <= $visibleStart + $visibleEnd) {
            return str_repeat('*', $len);
        }
        $start = mb_substr($value, 0, $visibleStart);
        $end   = mb_substr($value, -$visibleEnd);
        $middle = str_repeat('*', max(8, $len - $visibleStart - $visibleEnd));
        return $start . $middle . $end;
    }
}
