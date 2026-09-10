# BaToPay

**v1.0.0** — Self-hosted Payment + Form Builder Platform

| | |
|--|--|
| Brand | [@BaToHub](https://t.me/BaToHub) |
| Bot | [@BaToPay_Bot](https://t.me/BaToPay_Bot) |
| Support | [@BaTo_Help](https://t.me/BaTo_Help) |
| Bugs / Ideas | [@DatPHP](https://t.me/DatPHP) |

**فارسی:** [README.fa.md](./README.fa.md)

## Why BaToPay?

BaToPay lets you accept payments on dedicated pages, collect post-payment forms, expose a merchant API for bots, and operate everything from Admin + Merchant panels — without Laravel or heavy front-end frameworks.

## Features

- Dynamic payment pages + form builder
- Merchant apply → approve → API key (`btp_…`)
- API v1 (stable) + API v2 (payments, transactions, balance, webhooks)
- Gateways: CubePay, BluePal, Sandbox
- Server-side payment verification (idempotent, `FOR UPDATE`)
- Outbound webhooks with HMAC signature
- Telegram bot + Mini App (captcha + Iranian phone)
- Admin: gateways, merchants, invoices, security center, analytics
- Merchant: dashboard, invoices, API keys, webhooks
- Web installer (7 steps)
- AES-256-GCM secrets, CSRF, rate limits, hashed API keys

## Requirements

- PHP 8.3+ (`pdo_mysql`, `curl`, `openssl`, `mbstring`, `json`)
- MySQL 8+
- HTTPS for Telegram webhook / Mini App

## Installation

1. Create MySQL database + user
2. Upload source; **DocumentRoot = `public/`**
3. Open `/install` → complete steps → remove/lock `install/`
4. Admin: `/admin/login.php`
5. Merchants: `/merchant/apply.php`
6. Set Telegram webhook: `https://YOUR_DOMAIN/telegram/webhook.php`

Details: [docs/INSTALLATION.md](./docs/INSTALLATION.md)

## API (quick)

```http
POST /api/v1/create-payment.php
Authorization: Bearer btp_...
Content-Type: application/json

{"amount":2500000,"order_id":"ord-1001","description":"Order"}
```

`amount` is **Rial**. Verify with `POST /api/v1/verify-payment.php`.

API v2: `/api/v2/payments.php`, `/api/v2/transactions.php`, `/api/v2/balance.php`, `/api/v2/webhooks.php`

Full docs: [docs/API.en.md](./docs/API.en.md) · [docs/API.fa.md](./docs/API.fa.md)

## Architecture

See [docs/ARCHITECTURE.md](./docs/ARCHITECTURE.md)

```
public/     HTTP entry (DocumentRoot)
admin/      Platform operators
merchant/   Sellers
app/        Core, Payments, Services, Security, Telegram
database/schema.sql
install/
```

## Security

[docs/SECURITY.md](./docs/SECURITY.md)

- PDO prepared statements
- CSRF on panels
- Encrypted gateway credentials
- API keys stored as SHA-256
- Payment verify never trusts browser alone

## Sandbox

Gateway `sandbox` — [docs/SANDBOX.md](./docs/SANDBOX.md)

## Webhooks

[docs/WEBHOOKS.md](./docs/WEBHOOKS.md)

`X-BaToPay-Signature` = HMAC-SHA256(body, secret)

## License

Proprietary · © BaToHub

**Powered by BaToHub**
