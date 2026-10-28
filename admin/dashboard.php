<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('ADMIN');
$s = $pdo->query("SELECT
    (SELECT COALESCE(SUM(weight_kg),0) FROM waste_deposits WHERE status <> 'REJECTED') AS kg,
    (SELECT COALESCE(SUM(amount),0) FROM transactions_ledger) AS paid,
    (SELECT COUNT(*) FROM waste_deposits WHERE status = 'HUB_VERIFIED') AS pending,
    (SELECT COUNT(*) FROM users WHERE role = 'COLLECTOR') AS collectors")->fetch();
$inv = $pdo->query('SELECT * FROM vw_hub_inventory ORDER BY hub_name, material_type')->fetchAll();
$pageTitle = 'Admin dashboard';
require __DIR__ . '/../includes/header.php';
?>
<h1>Administrator dashboard</h1>
<div class="grid">
  <div class="stat"><b><?= number_format((float)$s['kg'], 2) ?> kg</b><span>Waste collected</span></div>
  <div class="stat"><b><?= ugx($s['paid']) ?></b><span>Paid out to collectors</span></div>
  <div class="stat"><b><?= (int)$s['pending'] ?></b><span>Pending payouts</span></div>
  <div class="stat"><b><?= (int)$s['collectors'] ?></b><span>Registered collectors</span></div>
</div>
<h2>Hub inventory (view: vw_hub_inventory)</h2>
<div class="tablewrap"><table>
  <tr><th>Hub</th><th>Material</th><th>Stock (kg)</th></tr>
  <?php foreach ($inv as $r): ?>
  <tr><td><?= h($r['hub_name']) ?></td><td><?= h($r['material_type']) ?></td><td><?= h($r['stock_kg']) ?></td></tr>
  <?php endforeach; if (!$inv): ?><tr><td colspan="3">No stock yet.</td></tr><?php endif; ?>
</table></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
