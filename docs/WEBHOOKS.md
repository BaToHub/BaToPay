# Webhooks

Events: payment.paid · payment.failed · payment.expired

Signature: `X-BaToPay-Signature` = HMAC-SHA256(body, secret)

Manage: merchant panel or `POST /api/v2/webhooks.php`
