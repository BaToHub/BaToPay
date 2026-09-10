# باتو پی (BaToPay)

پلتفرم **پرداخت + فرم‌ساز** محصول [BaToHub](https://t.me/BaToHub)

ربات رسمی: [@BaToPay_Bot](https://t.me/BaToPay_Bot)  
فوتر ثابت: **Powered by BaToHub**

---

## معرفی

BaToPay روی **یک دیتابیس و یک پنل** این‌ها را یکپارچه می‌کند:

| کانال | نقش |
|--------|-----|
| **صفحات وب پرداخت** | `/pay/{slug}` |
| **ربات تلگرام** | کپچا + شماره ایران + دیپ‌لینک |
| **مینی‌اپ تلگرام** | همان کاربران ربات |
| **API مرچنت** | شبیه CubePay برای ربات‌های دیگر |

---

## نصب سی‌پنل

1. دیتابیس MySQL بسازید.
2. Document Root را روی `public/` بگذارید.
3. به `/install` بروید و مراحل را کامل کنید.
4. پوشه `install` را حذف کنید.
5. ورود: `/admin/login.php`

---

## ربات (@BaToPay_Bot)

1. `/start` → کپچای ۴ رقمی
2. ارسال شماره موبایل ایران (09xxxxxxxxx)
3. دیپ‌لینک: `https://t.me/BaToPay_Bot?start=vpn`

وب‌هوک:

```bash
curl "https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://YOUR_DOMAIN/telegram/webhook.php"
```

---

## مینی‌اپ

`https://YOUR_DOMAIN/miniapp/` — اعتبارسنجی initData + احراز هویت مشترک با ربات.

---

## API برای ربات‌های دیگر

پنل → API ربات‌ها → ساخت توکن `btp_...`

```http
POST /api/v1/create-payment.php
Authorization: Bearer btp_xxxxxxxx
Content-Type: application/json

{
  "amount": 2500000,
  "order_id": "order-1029",
  "callback_url": "https://your-bot.example/callback",
  "page_slug": "vpn"
}
```

مبلغ به **ریال**. تأیید: `POST /api/v1/verify-payment.php`

---

## درگاه‌ها

CubePay و BluePal از پنل — وب‌هوک `/webhook/cubepay` و `/webhook/blupal`

تومان در سیستم؛ ریال برای درگاه (×۱۰).

---

© BaToHub — فوتر: **Powered by BaToHub**
