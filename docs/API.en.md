# BaToPay API Reference v1

For merchants connecting bots to BaToPay.

## Auth
```
Authorization: Bearer btp_<your_api_key>
```

## Create payment
`POST /api/v1/create-payment.php`

| Field | Required | Description |
|-------|----------|-------------|
| amount | yes | Rial |
| order_id | yes | Unique (max 64) |
| callback_url | no | Notification URL |
| description | no | Max 255 |
| page_slug | no | Page slug |

## Verify
`POST /api/v1/verify-payment.php` — `{ "order_id": "..." }` — check `paid === true`.

## Status
`GET /api/v1/status.php?order_id=...`

## Become a merchant
1. `/merchant/apply.php`
2. Admin approval
3. `/merchant/login.php` → API Key
