<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('ADMIN');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $pdo->prepare('UPDATE material_rates SET rate_per_kg = ? WHERE material_type = ?')
            ->execute([(float)($_POST['rate'] ?? 0), $_POST['material'] ?? '']);
        flash('ok', 'Rate updated. It applies to new deposits only.');
    } catch (PDOException $e) { flash('err', db_message($e)); }
    header('Location: rates.php');
    exit;
}
$rates = $pdo->query('SELECT * FROM material_rates ORDER BY material_type')->fetchAll();
$pageTitle = 'Rates';
require __DIR__ . '/../includes/header.php';
?>
<h1>Payout rates (UGX per kg)</h1>
<div class="tablewrap"><table>
  <tr><th>Material</th><th>Current rate</th><th>Change</th><th>Updated</th></tr>
  <?php foreach ($rates as $r): ?>
  <tr><td><?= h($r['material_type']) ?></td><td><?= ugx($r['rate_per_kg']) ?></td>
    <td><form method="post" class="inline"><?= csrf_field() ?>
        <input type="hidden" name="material" value="<?= h($r['material_type']) ?>">
        <input type="number" name="rate" step="0.01" min="0.01" value="<?= h($r['rate_per_kg']) ?>" style="width:110px" required>
        <button class="small">Save</button></form></td>
    <td><?= h($r['updated_at']) ?></td></tr>
  <?php endforeach; ?>
</table></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
