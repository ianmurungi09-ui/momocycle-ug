<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('ADMIN');
$daily   = $pdo->query('SELECT * FROM vw_daily_collection_summary ORDER BY collection_date DESC, hub_name, material_type')->fetchAll();
$earn    = $pdo->query('SELECT * FROM vw_collector_earnings ORDER BY wallet_balance DESC')->fetchAll();
$ledger  = $pdo->query('SELECT * FROM transactions_ledger ORDER BY ledger_id DESC LIMIT 50')->fetchAll();
$audit   = $pdo->query('SELECT * FROM audit_log ORDER BY audit_id DESC LIMIT 50')->fetchAll();
$pageTitle = 'Reports';
require __DIR__ . '/../includes/header.php';
?>
<h1>Reports</h1>
<h2>Daily collection (view: vw_daily_collection_summary)</h2>
<div class="tablewrap"><table>
  <tr><th>Date</th><th>Hub</th><th>Material</th><th>Total kg</th><th>Payouts</th><th>Drop-offs</th></tr>
  <?php foreach ($daily as $r): ?>
  <tr><td><?= h($r['collection_date']) ?></td><td><?= h($r['hub_name']) ?></td><td><?= h($r['material_type']) ?></td>
      <td><?= h($r['total_weight_kg']) ?></td><td><?= ugx($r['total_payout_ugx']) ?></td><td><?= (int)$r['total_dropoffs'] ?></td></tr>
  <?php endforeach; if (!$daily): ?><tr><td colspan="6">No data.</td></tr><?php endif; ?>
</table></div>

<h2>Collector earnings (view: vw_collector_earnings)</h2>
<div class="tablewrap"><table>
  <tr><th>Collector</th><th>Deposits</th><th>Total kg</th><th>Wallet</th></tr>
  <?php foreach ($earn as $r): ?>
  <tr><td><?= h($r['full_name']) ?></td><td><?= (int)$r['total_deposits'] ?></td><td><?= h($r['total_kg']) ?></td><td><?= ugx($r['wallet_balance']) ?></td></tr>
  <?php endforeach; ?>
</table></div>

<h2>Transaction ledger (latest 50)</h2>
<div class="tablewrap"><table>
  <tr><th>Reference</th><th>Deposit</th><th>Phone</th><th>Amount</th><th>Status</th><th>Time</th></tr>
  <?php foreach ($ledger as $r): ?>
  <tr><td><?= h($r['transaction_reference']) ?></td><td>#<?= (int)$r['deposit_id'] ?></td><td><?= h($r['recipient_phone']) ?></td>
      <td><?= ugx($r['amount']) ?></td><td><?= h($r['status']) ?></td><td><?= h($r['logged_at']) ?></td></tr>
  <?php endforeach; if (!$ledger): ?><tr><td colspan="6">No payments yet.</td></tr><?php endif; ?>
</table></div>

<h2>Audit log, written by a trigger (latest 50)</h2>
<div class="tablewrap"><table>
  <tr><th>#</th><th>Table</th><th>Record</th><th>Action</th><th>From</th><th>To</th><th>Time</th></tr>
  <?php foreach ($audit as $r): ?>
  <tr><td><?= (int)$r['audit_id'] ?></td><td><?= h($r['table_name']) ?></td><td><?= (int)$r['record_id'] ?></td><td><?= h($r['action']) ?></td>
      <td><?= h($r['old_value']) ?></td><td><?= h($r['new_value']) ?></td><td><?= h($r['changed_at']) ?></td></tr>
  <?php endforeach; if (!$audit): ?><tr><td colspan="7">No changes logged yet.</td></tr><?php endif; ?>
</table></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
