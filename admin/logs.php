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

$audit = $pdo->query('SELECT a.*, ad.username FROM audit_logs a LEFT JOIN admins ad ON ad.id=a.admin_id ORDER BY a.id DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC);
$sys = $pdo->query('SELECT * FROM system_logs ORDER BY id DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'لاگ‌ها';
include '_layout_header.php';
?>
<h3 style="margin-bottom:1rem">Audit Log</h3>
<div class="table-wrap" style="margin-bottom:2rem">
<table>
<thead><tr><th>ادمین</th><th>عملیات</th><th>موجودیت</th><th>IP</th><th>زمان</th></tr></thead>
<tbody>
<?php foreach ($audit as $r): ?>
<tr>
<td><?= htmlspecialchars($r['username'] ?? '—') ?></td>
<td><?= htmlspecialchars($r['action']) ?></td>
<td><?= htmlspecialchars(($r['entity_type'] ?? '').' #'.($r['entity_id'] ?? '')) ?></td>
<td><?= htmlspecialchars($r['ip_address'] ?? '') ?></td>
<td><?= htmlspecialchars($r['created_at']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<h3 style="margin-bottom:1rem">System Log</h3>
<div class="table-wrap">
<table>
<thead><tr><th>سطح</th><th>پیام</th><th>زمان</th></tr></thead>
<tbody>
<?php foreach ($sys as $r): ?>
<tr>
<td><span class="badge badge-muted"><?= htmlspecialchars($r['level']) ?></span></td>
<td><?= htmlspecialchars(mb_substr($r['message'], 0, 120)) ?></td>
<td><?= htmlspecialchars($r['created_at']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php include '_layout_footer.php'; ?>
