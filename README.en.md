# BaToPay

**Payment + Form Builder Platform** by [BaToHub](https://t.me/BaToHub)

Official bot: [@BaToPay_Bot](https://t.me/BaToPay_Bot)  
Footer: **Powered by BaToHub**

---

## Overview

BaToPay is a production-ready platform (PHP 8.3+, MySQL 8, no heavy frameworks) that unifies:

| Channel | Role |
|---------|------|
| **Web payment pages** | `/pay/{slug}` — amount → gateway → form |
| **Telegram Bot** | Auth (captcha + Iranian phone) + deep links |
| **Telegram Mini App** | Same users & DB as the bot |
| **Public Merchant API** | CubePay-style API so **other bots** can create/verify payments |

One database. One admin panel. Three frontends.

---

## Requirements

- PHP 8.3+
- MySQL 8+
- Extensions: `pdo`, `pdo_mysql`, `curl`, `json`, `openssl`, `mbstring`, `fileinfo`
- HTTPS (required for Telegram webhook & Mini App)

---

## cPanel installation

1. Create a MySQL database + user in cPanel.
2. Upload the project; set **Document Root** to the `public/` directory.
3. Ensure `storage/` and `config/` are writable.
4. Open `https://your-domain.com/install` and complete the wizard.
5. **Delete or lock** the `install/` folder.
6. Log in at `/admin/login.php`

---

## Telegram Bot (@BaToPay_Bot)

### Auth flow

1. `/start` → 4-digit captcha
2. Iranian mobile via `request_contact` (09xxxxxxxxx)
3. User stored in shared `users` table

### Deep links

```
https://t.me/BaToPay_Bot?start=vpn
```

### Webhook

```bash
curl "https://api.telegram.org/bot<BOT_TOKEN>/setWebhook?url=https://YOUR_DOMAIN/telegram/webhook.php"
```

---

## Telegram Mini App

URL: `https://YOUR_DOMAIN/miniapp/`

Validates `initData` HMAC; requires bot captcha + phone verification.

---

## Merchant API (CubePay-style for other bots)

Admin → Merchant API Tokens → create `btp_...` token.

### Create payment

```http
POST /api/v1/create-payment.php
Authorization: Bearer btp_xxxxxxxx
Content-Type: application/json

{
  "amount": 2500000,
  "order_id": "order-1029",
  "callback_url": "https://your-bot.example/callback",
  "description": "VPN 1 month",
  "page_slug": "vpn"
}
```

`amount` is in **Rial**.

### Verify

```http
POST /api/v1/verify-payment.php
Authorization: Bearer btp_xxxxxxxx
Content-Type: application/json

{ "order_id": "order-1029" }
```

### Status

```http
GET /api/v1/status.php?order_id=order-1029
Authorization: Bearer btp_xxxxxxxx
```

---

## Gateways

- **CubePay** — https://cubevps.ir/smspay/developers.php — webhook `/webhook/cubepay`
- **BluePal** — http://blupal.net/documentation — webhook `/webhook/blupal`

UI/DB: Toman (integer). APIs: Rial = Toman × 10.

---

## Security

PDO, CSRF, XSS, AES-256-GCM secrets, SHA-256 merchant tokens, idempotent verify, Mini App HMAC, Iranian phone checks.

---

© BaToHub — Footer: **Powered by BaToHub**
