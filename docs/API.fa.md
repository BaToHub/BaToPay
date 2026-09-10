# مرجع API باتو پی — نسخه ۱

## احراز هویت
```
Authorization: Bearer btp_<کلید>
```

## ساخت پرداخت
`POST /api/v1/create-payment.php`

| فیلد | الزامی | توضیح |
|------|--------|--------|
| amount | بله | ریال |
| order_id | بله | یکتا |
| callback_url | خیر | اطلاع‌رسانی |

## تأیید
`POST /api/v1/verify-payment.php` — فقط `paid: true` معتبر است.

## فروشنده شدن
`/merchant/apply.php` → تأیید ادمین → `/merchant/login.php` → API Key
