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
$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT s.*, p.title AS page_title, t.order_id, t.amount FROM form_submissions s JOIN pages p ON p.id=s.page_id JOIN transactions t ON t.id=s.transaction_id WHERE s.id=?');
$st->execute([$id]);
$sub = $st->fetch(PDO::FETCH_ASSOC);
if (!$sub) { header('Location: submissions.php'); exit; }
$data = json_decode($sub['data_json'] ?? '{}', true) ?: [];
$pageTitle = 'مشاهده فرم';
include '_layout_header.php';
?>
<p>Order: <code><?= htmlspecialchars($sub['order_id']) ?></code> — <?= number_format((int)$sub['amount']) ?> تومان</p>
<table><?php foreach ($data as $k=>$v): ?>
<tr><th><?= htmlspecialchars((string)$k) ?></th><td><?= htmlspecialchars(is_array($v)?implode(', ',$v):(string)$v) ?></td></tr>
<?php endforeach; ?></table>
<p><a href="submissions.php">بازگشت</a></p>
<?php include '_layout_footer.php'; ?>
