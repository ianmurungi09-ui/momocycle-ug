<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('AGENT', 'ADMIN');
$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['deposit_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    try {
        // An agent may only act on deposits of their own hub.
        if ($me['role'] === 'AGENT') {
            $chk = $pdo->prepare('SELECT 1 FROM waste_deposits d JOIN collection_hubs h ON h.hub_id = d.hub_id WHERE d.deposit_id = ? AND h.agent_id = ?');
            $chk->execute([$id, $me['user_id']]);
            if (!$chk->fetch()) { throw new RuntimeException('That deposit does not belong to your hub.'); }
        }
        if ($action === 'pay') {
            $pdo->prepare('CALL sp_process_payout(?)')->execute([$id]);
            flash('ok', "Deposit #$id paid. Wallet credited and ledger entry written.");
        } elseif ($action === 'reject') {
            $pdo->prepare('CALL sp_reject_deposit(?)')->execute([$id]);
            flash('ok', "Deposit #$id rejected and removed from hub stock.");
        }
    } catch (PDOException $e) {
        flash('err', db_message($e));
    } catch (RuntimeException $e) {
        flash('err', $e->getMessage());
    }
    header('Location: pending.php');
    exit;
}

if ($me['role'] === 'AGENT') {
    $q = $pdo->prepare('SELECT p.* FROM vw_pending_payouts p JOIN waste_deposits d ON d.deposit_id = p.deposit_id JOIN collection_hubs h ON h.hub_id = d.hub_id WHERE h.agent_id = ? ORDER BY p.deposit_id');
    $q->execute([$me['user_id']]);
    $rows = $q->fetchAll();
} else {
    $rows = $pdo->query('SELECT * FROM vw_pending_payouts ORDER BY deposit_id')->fetchAll();
}
$pageTitle = 'Payouts';
require __DIR__ . '/../includes/header.php';
?>
<h1>Pending payouts</h1>
<div class="tablewrap"><table>
  <tr><th>#</th><th>Collector</th><th>Phone</th><th>Material</th><th>Kg</th><th>Payout</th><th>Logged</th><th>Action</th></tr>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?= (int)$r['deposit_id'] ?></td><td><?= h($r['full_name']) ?></td><td><?= h($r['phone_number']) ?></td>
    <td><?= h($r['material_type']) ?></td><td><?= h($r['weight_kg']) ?></td><td><?= ugx($r['payout_amount']) ?></td>
    <td><?= h($r['created_at']) ?></td>
    <td>
      <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="deposit_id" value="<?= (int)$r['deposit_id'] ?>">
        <button name="action" value="pay" class="small">Pay</button>
        <button name="action" value="reject" class="small danger" onclick="return confirm('Reject this deposit?')">Reject</button>
      </form>
    </td>
  </tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="8">No pending payouts.</td></tr><?php endif; ?>
</table></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
