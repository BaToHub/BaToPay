# BaToPay

**v1.0.0** · PHP 8.3+ · MySQL 8+ · Payment + Form Builder Platform

| | |
|--|--|
| Brand | [BaToHub](https://t.me/BaToHub) |
| Bot | [@BaToPay_Bot](https://t.me/BaToPay_Bot) |
| Support | [@BaTo_Help](https://t.me/BaTo_Help) |
| Bugs / Ideas | [@DatPHP](https://t.me/DatPHP) |

**فارسی:** [README.fa.md](./README.fa.md) · **API:** [docs/API.en.md](./docs/API.en.md)

## What is BaToPay?

Self-hosted payment platform: dynamic pages, post-payment forms, merchant API, Telegram bot + Mini App.

## Install

Document root = `public/` · `/install` · remove install · `/admin/login.php`

## API

```
POST /api/v1/create-payment.php
Authorization: Bearer btp_...
{"amount":2500000,"order_id":"ord-1"}
```

amount = Rial. v2: `POST /api/v2/payments.php`

## Gateways

cubepay · blupal · sandbox (test)

## Security

Server-side verify, idempotent callbacks, hashed API keys, CSRF, rate limits.

## Telegram

@BaToPay_Bot · Support @BaTo_Help · Ideas @DatPHP · Channel @BaToHub

© BaToHub · Powered by BaToHub
