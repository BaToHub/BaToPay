# BaToPay

<img src="https://img.shields.io/badge/version-1.0.0-lightgrey" alt="version">
<img src="https://img.shields.io/badge/PHP-8.3%2B-777BB4" alt="php">
<img src="https://img.shields.io/badge/MySQL-8%2B-4479A1" alt="mysql">
<img src="https://img.shields.io/badge/docs-EN%20%7C%20FA-blue" alt="docs">

**Payment platform + form builder + merchant API** by [BaToHub](https://t.me/BaToHub)

Bot: **[@BaToPay_Bot](https://t.me/BaToPay_Bot)** · Footer: **Powered by BaToHub**

| Language | Docs |
|----------|------|
| **English** | This file · [API EN](./docs/API.en.md) |
| **فارسی** | [README.fa.md](./README.fa.md) · [API FA](./docs/API.fa.md) |

## Merchant flow
1. Apply `/merchant/apply.php`
2. Admin approves
3. Seller panel + API Key `btp_...`
4. Connect bot via REST API

## Install (cPanel)
Document root → `public/` · `/install` · delete install · `/admin/login.php`

## API
```
POST /api/v1/create-payment.php
Authorization: Bearer btp_xxx
{"amount":2500000,"order_id":"ord-1"}
```
amount = Rial.

© BaToHub · Powered by BaToHub
