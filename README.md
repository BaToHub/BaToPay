# BaToPay | باتو پی

**Production-Ready Payment + Form Builder Platform**

| | |
|---|---|
| **Brand** | BaToPay / باتو پی |
| **Creator** | BaToHub |
| **Bot** | [@BaToPay_Bot](https://t.me/BaToPay_Bot) |
| **Footer** | Powered by BaToHub |
| **Stack** | PHP 8.3+ · MySQL 8 · PDO · Vanilla JS · No Framework |

---

## English Documentation

### What is BaToPay?

BaToPay lets you create **dynamic payment pages**. A customer enters an amount, pays via **CubePay** or **BluePal**, then fills a **custom form**. Submissions are stored and sent to admin via **Telegram**.

Everything (pages, form fields, gateways, tokens) is managed from the **Admin Panel** — no code changes.

### Requirements

- PHP **8.3+**
- MySQL **8+**
- Extensions: `pdo`, `pdo_mysql`, `curl`, `json`, `openssl`, `mbstring`, `fileinfo`
- Apache with `mod_rewrite` (or Nginx equivalent)
- HTTPS recommended for production

### cPanel Installation

1. Create a MySQL database and user in cPanel.
2. Upload the project (ZIP or Git clone) to `public_html` (or a subdomain folder).
3. **Point document root to the `public/` folder** (cPanel → Domains → Document Root), **or** keep project root and rely on root `.htaccess` redirect to `public/`.
4. Set permissions:
   ```bash
   chmod -R 755 storage
   chmod -R 755 config
   ```
5. Visit: `https://your-domain.com/install`
6. Complete installer steps (Requirements → Database → Admin → Settings → Telegram).
7. **Delete or protect the `install/` directory** after setup.
8. Login at `/admin/login.php`

### Gateway Setup

#### CubePay
- Docs: https://cubevps.ir/smspay/developers.php
- Get token from CubePay seller bot
- Admin → Gateways → Tokens → Add credential (Bearer token)
- Webhook/Callback URL: `https://your-domain.com/webhook/cubepay`
- Also: `https://your-domain.com/payment/callback`
- Amount unit: **Rial** (min 1000)

#### BluePal
- Docs: http://blupal.net/documentation
- Create API Key in BluePal dashboard
- Set Webhook URL on the API Key to: `https://your-domain.com/webhook/blupal`
- Admin → Gateways → Tokens → Add credential (API Key)
- Amount unit: **Rial** (min 100000)

### Currency

- User enters **Toman**
- System stores Toman as integer
- Sends **Rial = Toman × 10** to gateways
- Never uses FLOAT for money

### Payment Flow

```
Page → Amount + Gateway → Internal Transaction (pending)
→ Gateway createPayment → Redirect
→ Webhook/Callback → Server verifyPayment
→ Amount check → Atomic PAID / form_pending
→ Show Form → Submit → Telegram notify → Success
```

### Security

- PDO prepared statements
- CSRF tokens
- XSS escaping
- AES-256-GCM encrypted credentials
- Rate-limited login
- Secure sessions (HttpOnly, SameSite)
- Idempotent payment verification
- Audit log

### Admin Features

- Dashboard (stats + gateway health)
- Dynamic pages (`/pay/{slug}`)
- Form builder (text, select, file, …)
- Multi-credential gateways
- Enable/Disable gateways
- Connection test
- Primary / Secondary + Failover
- Transactions & Submissions
- Telegram notifications
- Settings & Audit logs

### Project Structure

```
BaToPay/
├── public/          # Web root
│   ├── pay.php
│   ├── callback.php
│   └── webhook/
├── admin/           # Admin panel
├── app/
│   ├── Payments/    # CubePay + BluePal adapters
│   ├── Services/
│   ├── Security/
│   └── Telegram/
├── config/
├── database/schema.sql
├── install/
└── storage/
```

### API / Webhooks

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/pay/{slug}` | GET/POST | Payment page |
| `/payment/callback` | GET/POST | Browser + server callback |
| `/webhook/cubepay` | POST | CubePay webhook |
| `/webhook/blupal` | POST | BluePal webhook (`payment.completed`) |

### Telegram Deep Link

```
https://t.me/BaToPay_Bot?start=vpn
```
Redirects user to the page with slug `vpn`.

### Production Checklist

- [ ] HTTPS enabled
- [ ] `install/` removed
- [ ] `display_errors = Off`
- [ ] Encryption key in `storage/secure/`
- [ ] Gateways tested
- [ ] Webhooks reachable from internet
- [ ] File permissions locked down

---

## مستندات فارسی

### BaToPay چیست؟

پلتفرم **پرداخت + فرم‌ساز** حرفه‌ای. کاربر وارد صفحه اختصاصی می‌شود، مبلغ را وارد می‌کند، از درگاه **CubePay** یا **BluePal** پرداخت می‌کند، سپس فرم سفارشی را پر می‌کند. اطلاعات در دیتابیس ذخیره و از طریق **تلگرام** به ادمین ارسال می‌شود.

### نصب روی هاست سی‌پنل

1. در cPanel یک دیتابیس MySQL و کاربر بسازید.
2. فایل‌های پروژه را در `public_html` (یا ساب‌دامین) آپلود کنید.
3. Document Root را روی پوشه `public/` تنظیم کنید.
4. دسترسی نوشتن به `storage` و `config` بدهید.
5. به آدرس `/install` بروید و مراحل نصب را کامل کنید.
6. بعد از نصب، پوشه `install` را حذف کنید.
7. ورود به پنل: `/admin/login.php`

### تنظیم درگاه‌ها

از پنل ادمین → **درگاه‌ها** → **Tokenها**:

- **CubePay**: توکن Bearer را وارد کنید.
- **BluePal**: API Key را وارد کنید.

Webhookها:

- CubePay: `https://دامنه-شما/webhook/cubepay`
- BluePal: `https://دامنه-شما/webhook/blupal` (در پنل BluePal هم ثبت شود)

### واحد پول

- نمایش و ورود کاربر: **تومان**
- ارسال به درگاه: **ریال** (×۱۰)
- ذخیره در دیتابیس: عدد صحیح (بدون اعشار)

### امنیت

- Prepared Statements
- CSRF
- رمزنگاری Tokenها با AES-256-GCM
- محدودیت نرخ ورود
- تأیید پرداخت فقط سمت سرور (Idempotent)

### ساخت صفحه پرداخت

1. پنل → صفحات → صفحه جدید
2. عنوان، اسلاگ، حداقل مبلغ، درگاه‌های مجاز
3. فرم‌ساز: فیلدهای دلخواه اضافه کنید
4. لینک عمومی: `https://دامنه/pay/اسلاگ`

### پشتیبانی

- ربات رسمی: [@BaToPay_Bot](https://t.me/BaToPay_Bot)
- سازنده: [@BaToHub](https://t.me/BaToHub)

---

© BaToHub — Footer must remain: **Powered by BaToHub**
