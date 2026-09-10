<?php
/**
 * BaToPay Installer — 7 steps
 */
declare(strict_types=1);

session_start();
error_reporting(E_ALL);
ini_set('display_errors', '1');

$basePath = dirname(__DIR__);
$step = (int)($_GET['step'] ?? 1);
$errors = [];

$appConfigFile = $basePath . '/config/app.php';
$appConfig = file_exists($appConfigFile) ? require $appConfigFile : [];
if (!empty($appConfig['installed']) && $step < 7) {
    header('Location: ../admin/login.php');
    exit;
}

function checkRequirements(): array
{
    $checks = [];
    $checks['php'] = ['label' => 'PHP >= 8.3', 'ok' => version_compare(PHP_VERSION, '8.3.0', '>='), 'value' => PHP_VERSION];
    foreach (['pdo','pdo_mysql','curl','json','openssl','mbstring','fileinfo'] as $ext) {
        $checks[$ext] = ['label' => strtoupper($ext), 'ok' => extension_loaded($ext), 'value' => extension_loaded($ext) ? 'OK' : 'Missing'];
    }
    foreach (['storage','storage/logs','storage/cache','storage/sessions','storage/uploads','config'] as $dir) {
        $path = dirname(__DIR__) . '/' . $dir;
        if (!is_dir($path)) @mkdir($path, 0755, true);
        $checks['w_' . $dir] = ['label' => 'Writable: ' . $dir, 'ok' => is_writable($path), 'value' => is_writable($path) ? 'OK' : 'Not writable'];
    }
    return $checks;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 3) {
        $host = trim($_POST['db_host'] ?? '127.0.0.1');
        $port = (int)($_POST['db_port'] ?? 3306);
        $name = trim($_POST['db_name'] ?? '');
        $user = trim($_POST['db_user'] ?? '');
        $pass = (string)($_POST['db_pass'] ?? '');
        if ($name === '' || $user === '') {
            $errors[] = 'نام دیتابیس و کاربر الزامی است.';
        } else {
            try {
                $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
                $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `{$name}`");
                $schema = file_get_contents($basePath . '/database/schema.sql');
                if ($schema === false || trim($schema) === '') {
                    throw new RuntimeException('schema.sql missing or empty');
                }
                $pdo->exec($schema);
                $dbConfig = "<?php\ndeclare(strict_types=1);\n\nreturn [\n"
                    . "    'host'     => " . var_export($host, true) . ",\n"
                    . "    'port'     => {$port},\n"
                    . "    'database' => " . var_export($name, true) . ",\n"
                    . "    'username' => " . var_export($user, true) . ",\n"
                    . "    'password' => " . var_export($pass, true) . ",\n"
                    . "    'charset'  => 'utf8mb4',\n"
                    . "    'options'  => [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false],\n];\n";
                file_put_contents($basePath . '/config/database.php', $dbConfig);
                header('Location: ?step=4');
                exit;
            } catch (Throwable $e) {
                $errors[] = 'خطای دیتابیس: ' . $e->getMessage();
            }
        }
    }
    if ($step === 4) {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $name = trim($_POST['name'] ?? 'Owner');
        if ($username === '' || $email === '' || strlen($password) < 8) {
            $errors[] = 'نام کاربری، ایمیل و رمز عبور (حداقل ۸ کاراکتر) الزامی است.';
        } else {
            try {
                $db = require $basePath . '/config/database.php';
                $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']);
                $pdo = new PDO($dsn, $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare('INSERT INTO admins (role_id, username, email, password, name, status) VALUES (1, ?, ?, ?, ?, ?)')->execute([$username, $email, $hash, $name, 'active']);
                header('Location: ?step=5');
                exit;
            } catch (Throwable $e) {
                $errors[] = 'خطا در ایجاد ادمین: ' . $e->getMessage();
            }
        }
    }
    if ($step === 5) {
        $siteUrl = rtrim(trim($_POST['site_url'] ?? ''), '/');
        $siteName = trim($_POST['site_name'] ?? 'BaToPay');
        if ($siteUrl === '') {
            $errors[] = 'آدرس سایت الزامی است.';
        } else {
            $key = base64_encode(random_bytes(32));
            $secureDir = $basePath . '/storage/secure';
            if (!is_dir($secureDir)) mkdir($secureDir, 0750, true);
            file_put_contents($secureDir . '/key.php', "<?php\nreturn ['key' => " . var_export($key, true) . "];\n");
            file_put_contents($secureDir . '/.htaccess', "Require all denied\n");
            $appPhp = "<?php\ndeclare(strict_types=1);\n\nreturn [\n"
                . "    'name' => " . var_export($siteName, true) . ",\n"
                . "    'version' => '1.0.0',\n"
                . "    'creator' => 'BaToHub',\n"
                . "    'bot_username' => 'BaToPay_Bot',\n"
                . "    'footer' => 'Powered by BaToHub',\n"
                . "    'timezone' => 'Asia/Tehran',\n"
                . "    'debug' => false,\n"
                . "    'url' => " . var_export($siteUrl, true) . ",\n"
                . "    'csrf_token_name' => '_csrf',\n"
                . "    'installed' => true,\n];\n";
            file_put_contents($basePath . '/config/app.php', $appPhp);
            file_put_contents($basePath . '/config/installed.lock', date('c'));
            header('Location: ?step=6');
            exit;
        }
    }
    if ($step === 6) {
        header('Location: ?step=7');
        exit;
    }
}

$checks = $step === 2 ? checkRequirements() : [];
$allOk = $step === 2 ? !in_array(false, array_column($checks, 'ok'), true) : true;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>نصب BaToPay</title>
<style>
:root{--bg:#0a0a0a;--card:#141414;--text:#f5f5f5;--muted:#a3a3a3;--border:#262626;--ok:#22c55e;--err:#ef4444}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Tahoma,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;padding:2rem 1rem}
.wrap{max-width:520px;margin:0 auto}
h1{text-align:center;margin-bottom:.25rem}
.sub{text-align:center;color:var(--muted);margin-bottom:1.5rem}
.card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:1.5rem}
.steps{display:flex;gap:.35rem;margin-bottom:1.5rem;justify-content:center}
.steps span{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.75rem;background:#1a1a1a;border:1px solid var(--border);color:var(--muted)}
.steps span.on{background:#f5f5f5;color:#0a0a0a}
label{display:block;font-size:.85rem;color:var(--muted);margin:0 0 .35rem}
input{width:100%;padding:.7rem .9rem;border:1px solid var(--border);border-radius:8px;background:#0f0f0f;color:var(--text);margin-bottom:.9rem}
.btn{display:block;width:100%;padding:.85rem;border:none;border-radius:10px;background:#f5f5f5;color:#0a0a0a;font-weight:700;text-align:center;text-decoration:none;cursor:pointer}
.err{background:rgba(239,68,68,.12);color:#fca5a5;padding:.75rem;border-radius:8px;margin-bottom:1rem}
.check{display:flex;justify-content:space-between;padding:.5rem 0;border-bottom:1px solid var(--border)}
.ok{color:var(--ok)}.bad{color:var(--err)}
.footer{text-align:center;color:var(--muted);font-size:.8rem;margin-top:1.5rem}
</style>
</head>
<body>
<div class="wrap">
<h1>BaToPay</h1>
<p class="sub">نصب‌کننده | باتو پی</p>
<div class="steps"><?php for ($i=1;$i<=7;$i++): ?><span class="<?= $i===$step?'on':'' ?>"><?= $i ?></span><?php endfor; ?></div>
<?php foreach ($errors as $e): ?><div class="err"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
<div class="card">
<?php if ($step===1): ?>
<p style="margin-bottom:1rem;color:var(--muted)">به نصب BaToPay خوش آمدید.</p>
<a class="btn" href="?step=2">شروع نصب</a>
<?php elseif ($step===2): ?>
<?php foreach ($checks as $c): ?>
<div class="check"><span><?= htmlspecialchars($c['label']) ?></span><span class="<?= $c['ok']?'ok':'bad' ?>"><?= htmlspecialchars($c['value']) ?></span></div>
<?php endforeach; ?>
<?php if ($allOk): ?><a class="btn" href="?step=3" style="margin-top:1rem">ادامه</a><?php else: ?><button class="btn" disabled style="margin-top:1rem">نیازمندی‌ها برآورده نشده</button><?php endif; ?>
<?php elseif ($step===3): ?>
<form method="post">
<label>هاست</label><input name="db_host" value="127.0.0.1" required>
<label>پورت</label><input name="db_port" value="3306" required>
<label>نام دیتابیس</label><input name="db_name" required>
<label>کاربر</label><input name="db_user" required>
<label>رمز</label><input name="db_pass" type="password">
<button class="btn" type="submit">اتصال و ساخت جداول</button>
</form>
<?php elseif ($step===4): ?>
<form method="post">
<label>نام کاربری</label><input name="username" required>
<label>ایمیل</label><input name="email" type="email" required>
<label>نام</label><input name="name" value="Owner">
<label>رمز عبور</label><input name="password" type="password" required minlength="8">
<button class="btn" type="submit">ایجاد Owner</button>
</form>
<?php elseif ($step===5): ?>
<form method="post">
<label>نام سایت</label><input name="site_name" value="BaToPay" required>
<label>آدرس سایت</label><input name="site_url" placeholder="https://pay.example.com" required>
<button class="btn" type="submit">ذخیره</button>
</form>
<?php elseif ($step===6): ?>
<form method="post"><p style="color:var(--muted);margin-bottom:1rem">تلگرام اختیاری</p>
<button class="btn" type="submit">ادامه</button>
<a class="btn" href="?step=7" style="margin-top:.5rem;background:transparent;border:1px solid #262626;color:#f5f5f5">رد کردن</a>
</form>
<?php else: ?>
<p style="text-align:center;margin-bottom:1rem">✓ نصب موفق. پوشه install را حذف کنید.</p>
<a class="btn" href="../admin/login.php">ورود به پنل</a>
<?php endif; ?>
</div>
<div class="footer">Powered by BaToHub · @BaToHub · @BaToPay_Bot</div>
</div>
</body>
</html>
