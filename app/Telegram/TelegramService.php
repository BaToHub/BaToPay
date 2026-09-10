<?php
declare(strict_types=1);

namespace App\Telegram;

use App\Helpers\HttpClient;
use App\Helpers\Logger;
use App\Database\Connection;
use App\Security\Encryption;
use PDO;

final class TelegramService
{
    private string $token;
    private string $adminChatId;
    private bool $enabled;

    public function __construct(?string $token = null, ?string $adminChatId = null)
    {
        $pdo = Connection::get();
        $settings = $this->loadSettings($pdo);
        $this->token = $token ?? $this->decryptSetting($settings['bot_token'] ?? '');
        $this->adminChatId = $adminChatId ?? ($settings['admin_chat_id'] ?? '');
        $this->enabled = ($settings['notifications_enabled'] ?? '1') === '1';
    }

    private function loadSettings(PDO $pdo): array
    {
        $stmt = $pdo->query("SELECT key_name, value FROM settings WHERE group_name = 'telegram'");
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    }

    private function decryptSetting(string $value): string
    {
        if ($value === '') return '';
        try {
            return Encryption::decrypt($value);
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function isConfigured(): bool
    {
        return $this->token !== '' && $this->adminChatId !== '';
    }

    public function sendMessage(string $chatId, string $text, ?array $replyMarkup = null): array
    {
        if ($this->token === '') {
            return ['success' => false, 'error' => 'Bot token not set'];
        }
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];
        if ($replyMarkup) {
            $payload['reply_markup'] = json_encode($replyMarkup, JSON_UNESCAPED_UNICODE);
        }
        $url = 'https://api.telegram.org/bot' . $this->token . '/sendMessage';
        $client = new HttpClient(15);
        $res = $client->request('POST', $url, $payload, ['Content-Type: application/json']);
        $body = json_decode($res['body'] ?? '', true);
        return [
            'success' => ($res['http_code'] ?? 0) === 200 && !empty($body['ok']),
            'raw' => $body,
        ];
    }

    public function notifyNewSubmission(array $tx, array $page, array $submissionData): bool
    {
        if (!$this->enabled || !$this->isConfigured()) {
            return false;
        }
        $lines = [];
        $lines[] = "🔔 <b>BaToPay | اطلاعات جدید</b>";
        $lines[] = "";
        $lines[] = "💳 <b>تراکنش:</b>";
        $lines[] = e_tg($tx['order_id'] ?? '');
        $lines[] = "";
        $lines[] = "💰 <b>مبلغ:</b>";
        $lines[] = number_format((int)($tx['amount'] ?? 0)) . ' تومان';
        $lines[] = "";
        $lines[] = "🌐 <b>صفحه:</b>";
        $lines[] = e_tg($page['title'] ?? '');
        $lines[] = "";
        $lines[] = "👤 <b>اطلاعات مشتری:</b>";
        $lines[] = "";
        foreach ($submissionData as $label => $value) {
            if (is_array($value)) $value = implode(', ', $value);
            $lines[] = "<b>" . e_tg((string)$label) . ":</b>";
            $lines[] = e_tg((string)$value);
            $lines[] = "";
        }
        $lines[] = "🕐 <b>زمان:</b>";
        $lines[] = date('Y-m-d H:i:s');
        $text = implode("\n", $lines);
        $baseUrl = rtrim((string)config('app.url'), '/');
        $keyboard = [
            'inline_keyboard' => [[
                ['text' => 'مشاهده تراکنش', 'url' => $baseUrl . '/admin/transaction-view.php?id=' . ($tx['id'] ?? '')],
            ]],
        ];
        $result = $this->sendMessage($this->adminChatId, $text, $keyboard);
        if (!$result['success']) {
            Logger::warning('Telegram notify failed', ['error' => $result['raw'] ?? null]);
        }
        return $result['success'];
    }

    public function handleStart(?string $payload): ?string
    {
        if ($payload === null || $payload === '') {
            return null;
        }
        $slug = preg_replace('/[^a-zA-Z0-9\-_]/', '', $payload);
        if ($slug === '') return null;
        $pdo = Connection::get();
        $stmt = $pdo->prepare('SELECT slug FROM pages WHERE slug = ? AND status = ? LIMIT 1');
        $stmt->execute([$slug, 'active']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        return rtrim((string)config('app.url'), '/') . '/pay/' . $row['slug'];
    }
}

function e_tg(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
