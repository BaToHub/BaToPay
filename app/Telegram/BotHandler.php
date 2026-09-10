<?php
/**
 * BaToPay Official Bot — @BaToPay_Bot
 * captcha + IR phone auth, payment pages, manual invoice, track order
 */
declare(strict_types=1);

namespace App\Telegram;

use App\Database\Connection;
use App\Helpers\HttpClient;
use App\Helpers\Logger;
use App\Security\Encryption;
use App\Services\InvoiceService;
use PDO;

final class BotHandler
{
    private string $token;
    private PDO $pdo;
    private string $apiBase;

    public function __construct(?string $token = null)
    {
        $this->pdo = Connection::get();
        $settings = $this->loadTelegramSettings();
        $this->token = $token ?? $this->decrypt($settings['bot_token'] ?? '');
        $this->apiBase = 'https://api.telegram.org/bot' . $this->token;
    }

    private function loadTelegramSettings(): array
    {
        try {
            $stmt = $this->pdo->query("SELECT key_name, value FROM settings WHERE group_name = 'telegram'");
            return $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function decrypt(string $v): string
    {
        if ($v === '') return '';
        try { return Encryption::decrypt($v); } catch (\Throwable $e) { return ''; }
    }

    public function handle(array $update): void
    {
        if ($this->token === '') { Logger::error('Bot token empty'); return; }
        if (!empty($update['callback_query'])) { $this->onCallback($update['callback_query']); return; }
        $message = $update['message'] ?? null;
        if (!$message) return;
        $chatId = (int)($message['chat']['id'] ?? 0);
        $from = $message['from'] ?? [];
        $tgId = (int)($from['id'] ?? 0);
        $text = trim((string)($message['text'] ?? ''));
        $contact = $message['contact'] ?? null;
        $this->upsertUser($from);
        if ($contact) { $this->onContact($chatId, $tgId, $contact); return; }
        if (str_starts_with($text, '/start')) {
            $this->onStart($chatId, $tgId, $from, trim(substr($text, 6)));
            return;
        }
        $session = $this->getSession($tgId);
        $state = $session['state'] ?? 'start';
        if ($state === 'await_captcha') { $this->onCaptchaAnswer($chatId, $tgId, $text, $session); return; }
        if ($state === 'await_phone') { $this->send($chatId, "📱 لطفاً با دکمه «ارسال شماره موبایل» شماره ایران خود را بفرستید."); return; }
        if ($state === 'inv_amount') { $this->onInvoiceAmount($chatId, $tgId, $text, $session); return; }
        if ($state === 'inv_desc') { $this->onInvoiceDesc($chatId, $tgId, $text, $session); return; }
        if ($state === 'check_order') { $this->onCheckOrder($chatId, $tgId, $text); return; }
        if ($text === '/menu' || $text === 'منو' || $text === '🏠 منو') {
            $user = $this->getUser($tgId);
            if ($user && $this->isVerified($user)) { $this->sendMainMenu($chatId, $user); }
            else { $this->onStart($chatId, $tgId, $from, ''); }
            return;
        }
        if ($text === '/help' || $text === 'راهنما') { $this->sendHelp($chatId); return; }
        $user = $this->getUser($tgId);
        if ($user && $this->isVerified($user)) { $this->sendMainMenu($chatId, $user); }
        else { $this->onStart($chatId, $tgId, $from, ''); }
    }

    private function isVerified(array $user): bool
    {
        return ($user['status'] ?? '') === 'active' && !empty($user['phone_verified_at']) && !empty($user['captcha_passed_at']);
    }

    private function onStart(int $chatId, int $tgId, array $from, string $payload): void
    {
        $user = $this->getUser($tgId);
        if ($user && $this->isVerified($user)) {
            if ($payload !== '') { $this->openPageLink($chatId, $payload); return; }
            $this->sendMainMenu($chatId, $user); return;
        }
        if (!$user || empty($user['captcha_passed_at'])) {
            $code = (string) random_int(1000, 9999);
            $this->setSession($tgId, 'await_captcha', $code, ['start_payload' => $payload]);
            $this->send($chatId, "✨ <b>به BaToPay خوش آمدید</b>\n——————————————\nکد امنیتی:\n\n🔐 <b>{$code}</b>\n\n<i>کد را ارسال کنید.</i>");
            return;
        }
        $this->requestPhone($chatId, $tgId, $payload);
    }

    private function onCaptchaAnswer(int $chatId, int $tgId, string $text, array $session): void
    {
        $expected = (string)($session['captcha_code'] ?? '');
        $expires = $session['captcha_expires_at'] ?? null;
        $attempts = (int)($session['captcha_attempts'] ?? 0);
        if ($expires && strtotime($expires) < time()) { $this->send($chatId, "⏱ کد منقضی شد. /start"); $this->setSession($tgId, 'start', null); return; }
        if ($attempts >= 5) { $this->send($chatId, "⛔ تلاش بیش از حد."); return; }
        $answer = preg_replace('/\D/', '', $text);
        if ($answer !== $expected) {
            $this->pdo->prepare('UPDATE bot_sessions SET captcha_attempts = captcha_attempts + 1 WHERE telegram_id = ?')->execute([$tgId]);
            $this->send($chatId, "❌ کد اشتباه است."); return;
        }
        $this->pdo->prepare('UPDATE users SET captcha_passed_at = NOW(), status = ? WHERE telegram_id = ?')->execute(['pending', $tgId]);
        $payload = '';
        if (!empty($session['payload'])) { $p = json_decode($session['payload'], true); $payload = $p['start_payload'] ?? ''; }
        $this->requestPhone($chatId, $tgId, $payload);
    }

    private function requestPhone(int $chatId, int $tgId, string $payload): void
    {
        $this->setSession($tgId, 'await_phone', null, ['start_payload' => $payload]);
        $this->api('sendMessage', [
            'chat_id' => $chatId,
            'text' => "📱 <b>تأیید شماره موبایل ایران</b>\nفقط <code>09xxxxxxxxx</code>",
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['keyboard' => [[['text' => '📱 ارسال شماره موبایل', 'request_contact' => true]]], 'resize_keyboard' => true, 'one_time_keyboard' => true], JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function onContact(int $chatId, int $tgId, array $contact): void
    {
        $contactUserId = (int)($contact['user_id'] ?? 0);
        if ($contactUserId && $contactUserId !== $tgId) { $this->send($chatId, "⚠️ فقط شماره خودتان."); return; }
        $phone = $this->normalizeIranPhone((string)($contact['phone_number'] ?? ''));
        if ($phone === null) { $this->send($chatId, "❌ شماره معتبر ایران نیست."); return; }
        $this->pdo->prepare('UPDATE users SET phone = ?, phone_verified_at = NOW(), status = ?, updated_at = NOW() WHERE telegram_id = ?')->execute([$phone, 'active', $tgId]);
        $this->setSession($tgId, 'verified', null);
        $session = $this->getSession($tgId);
        $payload = '';
        if (!empty($session['payload'])) { $p = json_decode($session['payload'], true); $payload = $p['start_payload'] ?? ''; }
        $this->api('sendMessage', ['chat_id' => $chatId, 'text' => "✅ <b>احراز هویت موفق</b>\n<code>{$phone}</code>", 'parse_mode' => 'HTML', 'reply_markup' => json_encode(['remove_keyboard' => true])]);
        if ($payload !== '') { $this->openPageLink($chatId, $payload); }
        else { $this->sendMainMenu($chatId, $this->getUser($tgId) ?: []); }
    }

    private function sendMainMenu(int $chatId, array $user): void
    {
        $base = rtrim((string) config('app.url'), '/');
        $name = htmlspecialchars($user['first_name'] ?? 'کاربر');
        $merchant = $this->getMerchantByTelegram((int)($user['telegram_id'] ?? 0));
        $rows = [
            [['text' => '💳 صفحات پرداخت', 'callback_data' => 'menu_pages']],
            [['text' => '📱 مینی‌اپ', 'web_app' => ['url' => $base . '/miniapp/']]],
            [['text' => '🔎 پیگیری سفارش', 'callback_data' => 'menu_track']],
            [['text' => '❓ راهنما', 'callback_data' => 'menu_help']],
            [['text' => '🆘 پشتیبانی', 'url' => 'https://t.me/BaTo_Help']],
            [['text' => '🐞 گزارش باگ / ایده', 'url' => 'https://t.me/DatPHP']],
        ];
        if ($merchant) {
            array_unshift($rows, [['text' => '🧾 فاکتور دستی (فروشنده)', 'callback_data' => 'menu_invoice']]);
            array_unshift($rows, [['text' => '📊 پنل فروشنده', 'url' => $base . '/merchant/login.php']]);
        }
        $this->api('sendMessage', [
            'chat_id' => $chatId,
            'text' => "🌟 <b>سلام {$name}</b>\nبه <b>BaToPay</b> خوش آمدید.",
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function onCallback(array $callback): void
    {
        $id = $callback['id'] ?? '';
        $data = (string)($callback['data'] ?? '');
        $message = $callback['message'] ?? [];
        $chatId = (int)($message['chat']['id'] ?? 0);
        $tgId = (int)(($callback['from']['id'] ?? 0));
        $this->api('answerCallbackQuery', ['callback_query_id' => $id]);
        $user = $this->getUser($tgId);
        if (!$user || !$this->isVerified($user)) { $this->send($chatId, "ابتدا /start را بزنید."); return; }
        match ($data) {
            'menu_pages' => $this->listPages($chatId),
            'menu_track' => $this->askTrack($chatId, $tgId),
            'menu_help' => $this->sendHelp($chatId),
            'menu_invoice' => $this->startInvoice($chatId, $tgId),
            'menu_home' => $this->sendMainMenu($chatId, $user),
            default => null,
        };
        if (str_starts_with($data, 'pay_')) { $this->openPageLink($chatId, substr($data, 4)); }
    }

    private function listPages(int $chatId): void
    {
        $pages = $this->pdo->query("SELECT title, slug FROM pages WHERE status = 'active' ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
        if (!$pages) { $this->send($chatId, "صفحه فعالی نیست."); return; }
        $rows = [];
        foreach ($pages as $p) { $rows[] = [['text' => '💳 ' . $p['title'], 'callback_data' => 'pay_' . $p['slug']]]; }
        $rows[] = [['text' => '🏠 بازگشت', 'callback_data' => 'menu_home']];
        $this->api('sendMessage', ['chat_id' => $chatId, 'text' => "💳 <b>صفحات پرداخت</b>", 'parse_mode' => 'HTML', 'reply_markup' => json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE)]);
    }

    private function openPageLink(int $chatId, string $slug): void
    {
        $slug = preg_replace('/[^a-zA-Z0-9\-_]/', '', $slug);
        $stmt = $this->pdo->prepare("SELECT title, slug FROM pages WHERE slug = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$slug]);
        $page = $stmt->fetch(PDO::FETCH_ASSOC);
        $base = rtrim((string) config('app.url'), '/');
        if (!$page) { $this->send($chatId, "صفحه یافت نشد."); return; }
        $url = $base . '/pay/' . $page['slug'];
        $mini = $base . '/miniapp/?page=' . urlencode($page['slug']);
        $title = htmlspecialchars($page['title']);
        $this->api('sendMessage', [
            'chat_id' => $chatId,
            'text' => "✨ <b>{$title}</b>",
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => [
                [['text' => '💳 صفحه پرداخت', 'url' => $url]],
                [['text' => '📱 مینی‌اپ', 'web_app' => ['url' => $mini]]],
                [['text' => '🏠 منو', 'callback_data' => 'menu_home']],
            ]], JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function askTrack(int $chatId, int $tgId): void
    {
        $this->setSession($tgId, 'check_order', null);
        $this->send($chatId, "🔎 order_id را ارسال کنید:");
    }

    private function onCheckOrder(int $chatId, int $tgId, string $text): void
    {
        $ref = trim($text);
        $this->setSession($tgId, 'verified', null);
        $stmt = $this->pdo->prepare('SELECT mp.status, mp.amount, mp.paid_at, mp.external_order_id, t.status AS tx_status FROM merchant_payments mp LEFT JOIN transactions t ON t.id = mp.transaction_id WHERE mp.external_order_id = ? OR t.order_id = ? OR t.uuid = ? ORDER BY mp.id DESC LIMIT 1');
        $stmt->execute([$ref, $ref, $ref]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $stmt = $this->pdo->prepare('SELECT status AS tx_status, amount, paid_at, order_id AS external_order_id FROM transactions WHERE order_id = ? OR uuid = ? LIMIT 1');
            $stmt->execute([$ref, $ref]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if (!$row) { $this->send($chatId, "❌ یافت نشد."); return; }
        $paid = in_array($row['tx_status'] ?? '', ['paid', 'form_pending', 'completed'], true) || ($row['status'] ?? '') === 'paid';
        $status = $paid ? '✅ پرداخت شده' : ('⏳ ' . ($row['tx_status'] ?? $row['status'] ?? '?'));
        $this->send($chatId, "📋 <b>وضعیت</b>\n<code>" . htmlspecialchars($row['external_order_id'] ?? $ref) . "</code>\nمبلغ: <b>" . number_format((int)($row['amount'] ?? 0)) . "</b>\n{$status}");
    }

    private function startInvoice(int $chatId, int $tgId): void
    {
        $m = $this->getMerchantByTelegram($tgId);
        if (!$m) { $this->send($chatId, "فقط فروشندگان تأییدشده."); return; }
        $this->setSession($tgId, 'inv_amount', null, ['merchant_id' => $m['id']]);
        $this->send($chatId, "🧾 مبلغ به تومان (فقط عدد):");
    }

    private function onInvoiceAmount(int $chatId, int $tgId, string $text, array $session): void
    {
        $amount = (int) preg_replace('/\D/', '', $text);
        if ($amount < 1000) { $this->send($chatId, "حداقل ۱۰۰۰ تومان."); return; }
        $payload = !empty($session['payload']) ? (json_decode($session['payload'], true) ?: []) : [];
        $payload['amount'] = $amount;
        $this->setSession($tgId, 'inv_desc', null, $payload);
        $this->send($chatId, "توضیحات (یا -):");
    }

    private function onInvoiceDesc(int $chatId, int $tgId, string $text, array $session): void
    {
        $payload = !empty($session['payload']) ? (json_decode($session['payload'], true) ?: []) : [];
        $amount = (int)($payload['amount'] ?? 0);
        $merchantId = (int)($payload['merchant_id'] ?? 0);
        $desc = ($text === '-' || $text === '') ? 'فاکتور دستی' : mb_substr($text, 0, 200);
        $this->setSession($tgId, 'verified', null);
        if ($amount < 1000 || !$merchantId) { $this->send($chatId, "خطا."); return; }
        try {
            $res = (new InvoiceService())->create(['amount_toman' => $amount, 'description' => $desc, 'merchant_id' => $merchantId, 'ttl_minutes' => 60]);
        } catch (\Throwable $e) {
            $this->send($chatId, "خطا در ساخت فاکتور."); return;
        }
        if (empty($res['success'])) { $this->send($chatId, "❌ " . htmlspecialchars($res['message'] ?? 'خطا')); return; }
        $link = $res['payment_link'] ?? '';
        $this->api('sendMessage', [
            'chat_id' => $chatId,
            'text' => "✅ <b>فاکتور آماده</b>\nمبلغ: <b>" . number_format($amount) . "</b> تومان",
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => [[['text' => '🔗 لینک پرداخت', 'url' => $link]], [['text' => '🏠 منو', 'callback_data' => 'menu_home']]]], JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function sendHelp(int $chatId): void
    {
        $this->send($chatId, "❓ <b>راهنما</b>\n/start · /menu\n🆘 @BaTo_Help\n🐞 @DatPHP\n📢 @BaToHub\n🤖 @BaToPay_Bot\n<i>Powered by BaToHub</i>");
    }

    private function getMerchantByTelegram(int $tgId): ?array
    {
        if (!$tgId) return null;
        $s = $this->pdo->prepare("SELECT * FROM merchants WHERE telegram_id = ? AND status = 'approved' LIMIT 1");
        $s->execute([$tgId]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function normalizeIranPhone(string $raw): ?string
    {
        $p = preg_replace('/\D+/', '', $raw);
        if (str_starts_with($p, '0098')) $p = '0' . substr($p, 4);
        elseif (str_starts_with($p, '98')) $p = '0' . substr($p, 2);
        return preg_match('/^09\d{9}$/', $p) ? $p : null;
    }

    private function upsertUser(array $from): void
    {
        $tgId = (int)($from['id'] ?? 0);
        if (!$tgId) return;
        $this->pdo->prepare('INSERT INTO users (telegram_id, username, first_name, last_name, language_code, last_seen_at) VALUES (?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE username = VALUES(username), first_name = VALUES(first_name), last_name = VALUES(last_name), language_code = VALUES(language_code), last_seen_at = NOW()')
            ->execute([$tgId, $from['username'] ?? null, $from['first_name'] ?? null, $from['last_name'] ?? null, $from['language_code'] ?? 'fa']);
    }

    private function getUser(int $tgId): ?array
    {
        $s = $this->pdo->prepare('SELECT * FROM users WHERE telegram_id = ? LIMIT 1');
        $s->execute([$tgId]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function getSession(int $tgId): array
    {
        $s = $this->pdo->prepare('SELECT * FROM bot_sessions WHERE telegram_id = ? LIMIT 1');
        $s->execute([$tgId]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function setSession(int $tgId, string $state, ?string $captcha, array $payload = []): void
    {
        $expires = $captcha ? date('Y-m-d H:i:s', time() + 300) : null;
        $json = $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null;
        $this->pdo->prepare('INSERT INTO bot_sessions (telegram_id, state, captcha_code, captcha_expires_at, captcha_attempts, payload) VALUES (?, ?, ?, ?, 0, ?) ON DUPLICATE KEY UPDATE state = VALUES(state), captcha_code = VALUES(captcha_code), captcha_expires_at = VALUES(captcha_expires_at), captcha_attempts = IF(VALUES(captcha_code) IS NULL, captcha_attempts, 0), payload = VALUES(payload), updated_at = NOW()')
            ->execute([$tgId, $state, $captcha, $expires, $json]);
    }

    private function send(int $chatId, string $html): void
    {
        $this->api('sendMessage', ['chat_id' => $chatId, 'text' => $html, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true]);
    }

    private function api(string $method, array $params): array
    {
        $client = new HttpClient(15);
        $res = $client->request('POST', $this->apiBase . '/' . $method, $params, ['Content-Type: application/json']);
        return json_decode($res['body'] ?? '{}', true) ?: [];
    }
}
