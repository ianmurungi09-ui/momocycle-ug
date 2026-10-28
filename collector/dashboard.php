<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('COLLECTOR');
$me = current_user();

$w = $pdo->prepare('SELECT wallet_balance, phone_number FROM users WHERE user_id = ?');
$w->execute([$me['user_id']]);
$wallet = $w->fetch();

$d = $pdo->prepare('SELECT d.deposit_id, d.material_type, d.weight_kg, d.payout_amount, d.status, d.created_at, h.hub_name
                    FROM waste_deposits d JOIN collection_hubs h ON h.hub_id = d.hub_id
                    WHERE d.collector_id = ? ORDER BY d.deposit_id DESC');
$d->execute([$me['user_id']]);
$deposits = $d->fetchAll();

$l = $pdo->prepare('SELECT t.* FROM transactions_ledger t JOIN waste_deposits d ON d.deposit_id = t.deposit_id
                    WHERE d.collector_id = ? ORDER BY t.ledger_id DESC');
$l->execute([$me['user_id']]);
$ledger = $l->fetchAll();

$pageTitle = 'My wallet';
require __DIR__ . '/../includes/header.php';
?>
<h1>Hello, <?= h($me['full_name']) ?></h1>
<div class="grid">
  <div class="stat"><b><?= ugx($wallet['wallet_balance']) ?></b><span>Wallet balance</span></div>
  <div class="stat"><b><?= count($deposits) ?></b><span>Total deposits</span></div>
</div>
<h2>My deposits</h2>
<div class="tablewrap"><table>
  <tr><th>#</th><th>Hub</th><th>Material</th><th>Kg</th><th>Payout</th><th>Status</th><th>Date</th></tr>
  <?php foreach ($deposits as $r): ?>
  <tr><td><?= (int)$r['deposit_id'] ?></td><td><?= h($r['hub_name']) ?></td><td><?= h($r['material_type']) ?></td>
      <td><?= h($r['weight_kg']) ?></td><td><?= ugx($r['payout_amount']) ?></td>
      <td><span class="badge <?= h($r['status']) ?>"><?= h($r['status']) ?></span></td><td><?= h($r['created_at']) ?></td></tr>
  <?php endforeach; if (!$deposits): ?><tr><td colspan="7">No deposits yet.</td></tr><?php endif; ?>
</table></div>
<h2>Payment history</h2>
<div class="tablewrap"><table>
  <tr><th>Reference</th><th>Deposit</th><th>Amount</th><th>Status</th><th>Date</th></tr>
  <?php foreach ($ledger as $r): ?>
  <tr><td><?= h($r['transaction_reference']) ?></td><td>#<?= (int)$r['deposit_id'] ?></td><td><?= ugx($r['amount']) ?></td><td><?= h($r['status']) ?></td><td><?= h($r['logged_at']) ?></td></tr>
  <?php endforeach; if (!$ledger): ?><tr><td colspan="5">No payments yet.</td></tr><?php endif; ?>
</table></div>
<p class="muted">Payments are simulated: a ledger entry stands in for the mobile money transfer.</p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
