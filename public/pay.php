<?php
/**
 * BaToPay Public Payment Page
 * Route: /pay/{slug}
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/Autoloader.php';
require dirname(__DIR__) . '/app/Core/Bootstrap.php';

use App\Core\Bootstrap;
use App\Database\Connection;
use App\Payments\GatewayManager;
use App\Services\PaymentService;
use App\Security\Csrf;

Bootstrap::init();

if (!Bootstrap::isInstalled()) {
    header('Location: /install/');
    exit;
}

$slug = $_GET['slug'] ?? '';
$slug = preg_replace('/[^a-zA-Z0-9\-_]/', '', $slug);
if ($slug === '') {
    http_response_code(404);
    include __DIR__ . '/errors/404.php';
    exit;
}

$pdo = Connection::get();
$stmt = $pdo->prepare('SELECT * FROM pages WHERE slug = ? AND status = ? LIMIT 1');
$stmt->execute([$slug, 'active']);
$page = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$page) {
    http_response_code(404);
    include __DIR__ . '/errors/404.php';
    exit;
}

$gatewayManager = new GatewayManager();
$gateways = $gatewayManager->listForPage((int)$page['id']);

$error = null;
$step = 'payment';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay') {
    if (!Csrf::validateRequest()) {
        $error = 'درخواست نامعتبر است. صفحه را رفرش کنید.';
    } else {
        $amount = (int) preg_replace('/\D/', '', (string)($_POST['amount'] ?? '0'));
        $gwId = (string)($_POST['gateway'] ?? '');
        if ($amount <= 0) {
            $error = 'مبلغ معتبر وارد کنید.';
        } elseif ($gwId === '') {
            $error = 'درگاه پرداخت را انتخاب کنید.';
        } else {
            $service = new PaymentService();
            $result = $service->initiate((int)$page['id'], $amount, $gwId);
            if (!empty($result['success']) && !empty($result['payment_link'])) {
                header('Location: ' . $result['payment_link']);
                exit;
            }
            $error = $result['error'] ?? 'خطا در ایجاد پرداخت';
        }
    }
}

$txUuid = $_GET['tx'] ?? '';
$transaction = null;
if ($txUuid !== '') {
    $tStmt = $pdo->prepare('SELECT * FROM transactions WHERE uuid = ? AND page_id = ? LIMIT 1');
    $tStmt->execute([$txUuid, $page['id']]);
    $transaction = $tStmt->fetch(PDO::FETCH_ASSOC);
    if ($transaction && in_array($transaction['status'], ['paid', 'form_pending'], true)) {
        $step = 'form';
    } elseif ($transaction && $transaction['status'] === 'completed') {
        $step = 'success';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_form') {
    if (!Csrf::validateRequest()) {
        $error = 'درخواست نامعتبر است.';
    } elseif (!$transaction || !in_array($transaction['status'], ['paid', 'form_pending'], true)) {
        $error = 'تراکنش معتبر نیست.';
    } else {
        $ex = $pdo->prepare('SELECT id FROM form_submissions WHERE transaction_id = ?');
        $ex->execute([$transaction['id']]);
        if ($ex->fetch()) {
            $step = 'success';
        } else {
            $fStmt = $pdo->prepare('SELECT * FROM page_fields WHERE page_id = ? AND status = ? ORDER BY sort_order ASC');
            $fStmt->execute([$page['id'], 'active']);
            $fields = $fStmt->fetchAll(PDO::FETCH_ASSOC);
            $data = [];
            $values = [];
            $valid = true;
            foreach ($fields as $field) {
                $name = $field['name'];
                $val = $_POST['field'][$name] ?? '';
                if (is_array($val)) $val = implode(', ', $val);
                $val = trim((string)$val);
                if ($field['required'] && $val === '') {
                    $error = 'فیلد «' . $field['label'] . '» الزامی است.';
                    $valid = false;
                    break;
                }
                if ($field['type'] === 'email' && $val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
                    $error = 'ایمیل نامعتبر است.';
                    $valid = false;
                    break;
                }
                $data[$field['label']] = $val;
                $values[] = ['field_id' => $field['id'], 'field_name' => $name, 'field_label' => $field['label'], 'value' => $val];
            }
            if ($valid) {
                $pdo->beginTransaction();
                try {
                    $ins = $pdo->prepare('INSERT INTO form_submissions (transaction_id, page_id, status, user_ip, user_agent, data) VALUES (?, ?, ?, ?, ?, ?)');
                    $ins->execute([$transaction['id'], $page['id'], 'new', client_ip(), user_agent(), json_encode($data, JSON_UNESCAPED_UNICODE)]);
                    $subId = (int)$pdo->lastInsertId();
                    $vins = $pdo->prepare('INSERT INTO form_submission_values (submission_id, field_id, field_name, field_label, value) VALUES (?, ?, ?, ?, ?)');
                    foreach ($values as $v) {
                        $vins->execute([$subId, $v['field_id'], $v['field_name'], $v['field_label'], $v['value']]);
                    }
                    $pdo->prepare('UPDATE transactions SET status = ?, updated_at = NOW() WHERE id = ?')->execute(['completed', $transaction['id']]);
                    $pdo->commit();
                    try {
                        $tg = new \App\Telegram\TelegramService();
                        $tg->notifyNewSubmission($transaction, $page, $data);
                    } catch (\Throwable $e) {}
                    $step = 'success';
                    $transaction['status'] = 'completed';
                } catch (\Throwable $e) {
                    $pdo->rollBack();
                    $error = 'خطا در ذخیره اطلاعات.';
                }
            } else {
                $step = 'form';
            }
        }
    }
}

$fields = [];
if ($step === 'form') {
    $fStmt = $pdo->prepare('SELECT * FROM page_fields WHERE page_id = ? AND status = ? ORDER BY sort_order ASC');
    $fStmt->execute([$page['id'], 'active']);
    $fields = $fStmt->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = $page['meta_title'] ?: $page['title'];
$metaDesc = $page['meta_description'] ?: ($page['description'] ?? '');
$minAmount = (int)$page['min_amount'];
$maxAmount = $page['max_amount'] !== null ? (int)$page['max_amount'] : null;
$buttonText = $page['button_text'] ?: 'پرداخت';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | BaToPay</title>
    <?php if ($metaDesc): ?><meta name="description" content="<?= e($metaDesc) ?>"><?php endif; ?>
    <link rel="stylesheet" href="/assets/css/public.css">
</head>
<body class="pay-page">
<div class="container">
    <div class="card">
        <?php if ($page['logo']): ?>
            <img src="<?= e($page['logo']) ?>" alt="" class="logo">
        <?php endif; ?>
        <h1><?= e($page['title']) ?></h1>
        <?php if ($page['description']): ?>
            <p class="desc"><?= e($page['description']) ?></p>
        <?php endif; ?>
        <div class="progress">
            <span class="<?= $step === 'payment' ? 'active' : 'done' ?>">1. پرداخت</span>
            <span class="<?= $step === 'form' ? 'active' : ($step === 'success' ? 'done' : '') ?>">2. تکمیل اطلاعات</span>
            <span class="<?= $step === 'success' ? 'active' : '' ?>">3. ثبت درخواست</span>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($step === 'payment'): ?>
            <?php if (empty($gateways)): ?>
                <div class="alert alert-error">در حال حاضر درگاه فعالی برای این صفحه وجود ندارد.</div>
            <?php else: ?>
            <form method="post" class="pay-form" id="payForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="pay">
                <div class="form-group">
                    <label for="amount">مبلغ (تومان)</label>
                    <input type="text" inputmode="numeric" name="amount" id="amount" required
                           placeholder="<?= number_format($minAmount) ?>"
                           data-min="<?= $minAmount ?>"
                           <?= $maxAmount ? 'data-max="'.$maxAmount.'"' : '' ?>
                           autocomplete="off">
                    <small>حداقل: <?= toman($minAmount) ?><?= $maxAmount ? ' | حداکثر: '.toman($maxAmount) : '' ?></small>
                </div>
                <div class="form-group">
                    <label>درگاه پرداخت</label>
                    <div class="gateway-list">
                        <?php foreach ($gateways as $i => $gw): ?>
                        <label class="gateway-item">
                            <input type="radio" name="gateway" value="<?= e($gw['identifier']) ?>" <?= $i === 0 ? 'checked' : '' ?> required>
                            <span><?= e($gw['name']) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block"><?= e($buttonText) ?></button>
            </form>
            <?php endif; ?>
        <?php elseif ($step === 'form'): ?>
            <div class="alert alert-success">پرداخت با موفقیت انجام شد. لطفاً فرم زیر را تکمیل کنید.</div>
            <form method="post" class="form-fields">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="submit_form">
                <?php foreach ($fields as $field): ?>
                <div class="form-group">
                    <label for="f_<?= e($field['name']) ?>">
                        <?= e($field['label']) ?>
                        <?php if ($field['required']): ?><span class="req">*</span><?php endif; ?>
                    </label>
                    <?php
                    $type = $field['type'];
                    $name = 'field[' . $field['name'] . ']';
                    $ph = $field['placeholder'] ?? '';
                    $req = $field['required'] ? 'required' : '';
                    if (in_array($type, ['text','phone','email','telegram_username','number','date','time','hidden'], true)):
                        $inputType = $type === 'phone' ? 'tel' : ($type === 'number' ? 'number' : ($type === 'hidden' ? 'hidden' : 'text'));
                        if ($type === 'email') $inputType = 'email';
                        if ($type === 'date') $inputType = 'date';
                        if ($type === 'time') $inputType = 'time';
                    ?>
                        <input type="<?= $inputType ?>" name="<?= e($name) ?>" id="f_<?= e($field['name']) ?>"
                               placeholder="<?= e($ph) ?>" <?= $req ?>
                               value="<?= e($field['default_value'] ?? '') ?>">
                    <?php elseif ($type === 'textarea'): ?>
                        <textarea name="<?= e($name) ?>" id="f_<?= e($field['name']) ?>" placeholder="<?= e($ph) ?>" <?= $req ?> rows="3"><?= e($field['default_value'] ?? '') ?></textarea>
                    <?php else: ?>
                        <input type="text" name="<?= e($name) ?>" id="f_<?= e($field['name']) ?>" placeholder="<?= e($ph) ?>" <?= $req ?>>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <button type="submit" class="btn btn-primary btn-block">ثبت اطلاعات</button>
            </form>
        <?php elseif ($step === 'success'): ?>
            <div class="success-box">
                <div class="success-icon">✓</div>
                <h2>درخواست شما ثبت شد</h2>
                <p><?= e($page['success_message'] ?: 'از خرید شما متشکریم. اطلاعات با موفقیت ثبت شد.') ?></p>
                <?php if ($transaction): ?>
                <p class="order-id">شماره پیگیری: <strong><?= e($transaction['order_id']) ?></strong></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <footer class="footer">Powered by BaToHub</footer>
</div>
<script src="/assets/js/public.js"></script>
</body>
</html>
