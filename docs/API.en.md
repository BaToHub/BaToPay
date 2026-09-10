# BaToPay API

@BaToPay_Bot · @BaToHub · Support @BaTo_Help · Bugs @DatPHP

## Auth
`Authorization: Bearer btp_<key>`

## v1 Create
`POST /api/v1/create-payment.php` — amount in Rial

## v1 Verify
`POST /api/v1/verify-payment.php` — trust paid:true only

## v2
`POST /api/v2/payments.php` · `GET /api/v2/payments.php?order_id=`
Optional: Idempotency-Key

## Webhooks
X-BaToPay-Signature = HMAC-SHA256(body, secret)

## Sandbox
Gateway identifier: sandbox
