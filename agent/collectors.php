<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('AGENT', 'ADMIN');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name  = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone_number'] ?? '');
    $pass  = $_POST['password'] ?? '';
    if ($name === '' || !preg_match('/^[0-9+]{9,15}$/', $phone) || strlen($pass) < 6) {
        flash('err', 'Enter a name, a valid phone number and a password of at least 6 characters.');
    } else {
        try {
            $pdo->prepare("INSERT INTO users (full_name, phone_number, password_hash, role) VALUES (?, ?, ?, 'COLLECTOR')")
                ->execute([$name, $phone, password_hash($pass, PASSWORD_DEFAULT)]);
            flash('ok', "Collector $name registered.");
        } catch (PDOException $e) { flash('err', db_message($e)); }
    }
    header('Location: collectors.php');
    exit;
}
$rows = $pdo->query('SELECT * FROM vw_collector_earnings ORDER BY full_name')->fetchAll();
$pageTitle = 'Collectors';
require __DIR__ . '/../includes/header.php';
?>
<h1>Collectors</h1>
<div class="card">
  <h2>Register a collector</h2>
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <label>Full name <input name="full_name" required></label>
    <label>Phone number <input name="phone_number" required placeholder="07XXXXXXXX"></label>
    <label>Password (also used as USSD PIN) <input type="password" name="password" required minlength="6"></label>
    <button>Register</button>
  </form>
</div>
<div class="tablewrap"><table>
  <tr><th>Name</th><th>Deposits</th><th>Total kg</th><th>Wallet</th></tr>
  <?php foreach ($rows as $r): ?>
  <tr><td><?= h($r['full_name']) ?></td><td><?= (int)$r['total_deposits'] ?></td><td><?= h($r['total_kg']) ?></td><td><?= ugx($r['wallet_balance']) ?></td></tr>
  <?php endforeach; ?>
</table></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
