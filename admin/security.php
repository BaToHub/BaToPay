<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Core/Autoloader.php';
require dirname(__DIR__) . '/app/Core/Bootstrap.php';
use App\Core\Bootstrap;
use App\Security\Auth;
use App\Database\Connection;
Bootstrap::init();
Auth::requireLogin();
$pdo = Connection::get();
$loginFails = []; $audit = []; $sys = [];
try {
  $loginFails = $pdo->query("SELECT ip_address, COUNT(*) AS c, MAX(created_at) AS last_at FROM login_attempts WHERE success = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY ip_address ORDER BY c DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
  $audit = $pdo->query('SELECT a.*, ad.username FROM audit_logs a LEFT JOIN admins ad ON ad.id=a.admin_id ORDER BY a.id DESC LIMIT 40')->fetchAll(PDO::FETCH_ASSOC);
  $sys = $pdo->query("SELECT * FROM system_logs WHERE level IN ('error','warning','critical') ORDER BY id DESC LIMIT 40")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
$pageTitle = 'مرکز امنیت';
include '_layout_header.php';
?>
<div class="stats">
<div class="stat"><div class="label">IPهای مشکوک (۷ روز)</div><div class="value"><?= count($loginFails) ?></div></div>
<div class="stat"><div class="label">Audit</div><div class="value"><?= count($audit) ?></div></div>
<div class="stat"><div class="label">خطاهای سیستم</div><div class="value"><?= count($sys) ?></div></div>
</div>
<h3 style="margin:1rem 0">تلاش‌های ناموفق</h3>
<div class="table-wrap"><table><thead><tr><th>IP</th><th>تعداد</th><th>آخرین</th></tr></thead><tbody>
<?php foreach ($loginFails as $r): ?>
<tr><td><?= htmlspecialchars($r['ip_address']??'') ?></td><td><?= (int)$r['c'] ?></td><td><?= htmlspecialchars($r['last_at']??'') ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php include '_layout_footer.php'; ?>
