<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('ADMIN');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        if (($_POST['action'] ?? '') === 'add') {
            $agent = (int)($_POST['agent_id'] ?? 0) ?: null;
            $pdo->prepare('INSERT INTO collection_hubs (hub_name, location_description, agent_id) VALUES (?, ?, ?)')
                ->execute([trim($_POST['hub_name'] ?? ''), trim($_POST['location'] ?? ''), $agent]);
            flash('ok', 'Hub added.');
        } else {
            $agent = (int)($_POST['agent_id'] ?? 0) ?: null;
            $pdo->prepare('UPDATE collection_hubs SET agent_id = ? WHERE hub_id = ?')->execute([$agent, (int)$_POST['hub_id']]);
            flash('ok', 'Agent assignment updated.');
        }
    } catch (PDOException $e) { flash('err', db_message($e)); }
    header('Location: hubs.php');
    exit;
}
$agents = $pdo->query("SELECT user_id, full_name FROM users WHERE role='AGENT' ORDER BY full_name")->fetchAll();
$hubs = $pdo->query('SELECT h.*, u.full_name AS agent FROM collection_hubs h LEFT JOIN users u ON u.user_id = h.agent_id ORDER BY h.hub_name')->fetchAll();
$pageTitle = 'Hubs';
require __DIR__ . '/../includes/header.php';
function agent_options(array $agents, $sel = null): string {
    $o = '<option value="">(none)</option>';
    foreach ($agents as $a) { $o .= '<option value="' . (int)$a['user_id'] . '"' . ((int)$sel === (int)$a['user_id'] ? ' selected' : '') . '>' . h($a['full_name']) . '</option>'; }
    return $o;
}
?>
<h1>Collection hubs</h1>
<div class="card"><h2>Add a hub</h2>
  <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="add">
    <label>Hub name <input name="hub_name" required></label>
    <label>Location <input name="location" required></label>
    <label>Agent <select name="agent_id"><?= agent_options($agents) ?></select></label>
    <button>Add hub</button>
  </form></div>
<div class="tablewrap"><table>
  <tr><th>Hub</th><th>Location</th><th>Agent</th></tr>
  <?php foreach ($hubs as $r): ?>
  <tr><td><?= h($r['hub_name']) ?></td><td><?= h($r['location_description']) ?></td>
    <td><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="hub_id" value="<?= (int)$r['hub_id'] ?>">
      <select name="agent_id"><?= agent_options($agents, $r['agent_id']) ?></select> <button class="small">Save</button></form></td></tr>
  <?php endforeach; ?>
</table></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
