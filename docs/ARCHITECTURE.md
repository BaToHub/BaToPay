# Architecture

Modular PHP 8.3+ · PDO · public/ DocumentRoot

app/Payments · app/Services · app/Security · app/Telegram
admin/ · merchant/ · install/

Payment: pending → gateway → server verify → paid → webhooks
