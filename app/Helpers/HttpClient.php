<?php
/**
 * Simple cURL HTTP Client for BaToPay
 */

declare(strict_types=1);

namespace App\Helpers;

final class HttpClient
{
    private int $timeout;

    public function __construct(int $timeout = 20)
    {
        $this->timeout = $timeout;
    }

    /**
     * @return array{http_code: int, body: string, latency_ms: int, error?: string}
     */
    public function request(string $method, string $url, ?array $jsonBody = null, array $headers = []): array
    {
        $ch = curl_init();
        $method = strtoupper($method);

        $opts = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        if ($jsonBody !== null && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($jsonBody, JSON_UNESCAPED_UNICODE);
        }

        curl_setopt_array($ch, $opts);

        $start = microtime(true);
        $body = curl_exec($ch);
        $latency = (int) round((microtime(true) - $start) * 1000);

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return [
                'http_code'  => 0,
                'body'       => '',
                'latency_ms' => $latency,
                'error'      => $error ?: 'cURL error',
            ];
        }

        return [
            'http_code'  => $httpCode,
            'body'       => (string) $body,
            'latency_ms' => $latency,
        ];
    }
}
