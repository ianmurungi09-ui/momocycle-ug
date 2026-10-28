<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('AGENT', 'ADMIN');
$me = current_user();

// Hubs this person may use: an agent only sees their own hub, admin sees all.
if ($me['role'] === 'AGENT') {
    $s = $pdo->prepare('SELECT hub_id, hub_name FROM collection_hubs WHERE agent_id = ?');
    $s->execute([$me['user_id']]);
    $hubs = $s->fetchAll();
} else {
    $hubs = $pdo->query('SELECT hub_id, hub_name FROM collection_hubs ORDER BY hub_name')->fetchAll();
}
$collectors = $pdo->query("SELECT user_id, full_name, phone_number FROM users WHERE role='COLLECTOR' ORDER BY full_name")->fetchAll();
$rates = $pdo->query('SELECT material_type, rate_per_kg FROM material_rates ORDER BY material_type')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $hubId = (int)($_POST['hub_id'] ?? 0);
    $allowed = array_column($hubs, 'hub_id');
    if (!in_array($hubId, array_map('intval', $allowed), true)) {
        flash('err', 'You are not allowed to log deposits for that hub.');
    } else {
        try {
            // The stored procedure owns the whole transaction: PHP does NOT open its own.
            $stmt = $pdo->prepare('CALL sp_log_deposit(?, ?, ?, ?, ?, @new_id)');
            $stmt->execute([
                (int)($_POST['collector_id'] ?? 0),
                $hubId,
                $me['user_id'],
                $_POST['material_type'] ?? '',
                (float)($_POST['weight_kg'] ?? 0),
            ]);
            $stmt->closeCursor();
            $id = (int)$pdo->query('SELECT @new_id AS id')->fetch()['id'];
            $d = $pdo->prepare('SELECT payout_amount FROM waste_deposits WHERE deposit_id = ?');
            $d->execute([$id]);
            flash('ok', "Deposit #$id recorded. Payout of " . ugx($d->fetch()['payout_amount']) . ' is waiting on the Payouts page.');
        } catch (PDOException $e) {
            flash('err', db_message($e));
        }
    }
    header('Location: log-deposit.php');
    exit;
}

$pageTitle = 'Log deposit';
require __DIR__ . '/../includes/header.php';
?>
<h1>Log a waste deposit</h1>
<div class="card">
<?php if (!$hubs): ?>
  <div class="alert err">No hub is assigned to you yet. Ask the administrator to assign you to a hub.</div>
<?php else: ?>
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <label>Collector
      <select name="collector_id" required>
        <?php foreach ($collectors as $c): ?>
          <option value="<?= (int)$c['user_id'] ?>"><?= h($c['full_name']) ?> (<?= h($c['phone_number']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Hub
      <select name="hub_id" required>
        <?php foreach ($hubs as $hb): ?><option value="<?= (int)$hb['hub_id'] ?>"><?= h($hb['hub_name']) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>Material
      <select name="material_type" required>
        <?php foreach ($rates as $r): ?>
          <option value="<?= h($r['material_type']) ?>"><?= h($r['material_type']) ?> (<?= ugx($r['rate_per_kg']) ?>/kg)</option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Weight (kg) <input type="number" name="weight_kg" step="0.01" min="0.01" required></label>
    <button>Record deposit</button>
  </form>
  <p class="muted">The payout is calculated by the database from the current rate for the material.</p>
<?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
