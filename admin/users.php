<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('ADMIN');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['full_name'] ?? ''); $phone = trim($_POST['phone_number'] ?? '');
    $role = $_POST['role'] ?? ''; $pass = $_POST['password'] ?? '';
    if ($name === '' || !preg_match('/^[0-9+]{9,15}$/', $phone) || strlen($pass) < 6 || !in_array($role, ['ADMIN','AGENT','COLLECTOR'], true)) {
        flash('err', 'Check the name, phone number, role and password (at least 6 characters).');
    } else {
        try {
            $pdo->prepare('INSERT INTO users (full_name, phone_number, password_hash, role) VALUES (?, ?, ?, ?)')
                ->execute([$name, $phone, password_hash($pass, PASSWORD_DEFAULT), $role]);
            flash('ok', "User $name created as $role.");
        } catch (PDOException $e) { flash('err', db_message($e)); }
    }
    header('Location: users.php');
    exit;
}
$users = $pdo->query('SELECT user_id, full_name, phone_number, role, wallet_balance, created_at FROM users ORDER BY role, full_name')->fetchAll();
$pageTitle = 'Users';
require __DIR__ . '/../includes/header.php';
?>
<h1>Users</h1>
<div class="card"><h2>Create a user</h2>
  <form method="post" class="stack"><?= csrf_field() ?>
    <label>Full name <input name="full_name" required></label>
    <label>Phone number <input name="phone_number" required></label>
    <label>Role <select name="role"><option>COLLECTOR</option><option>AGENT</option><option>ADMIN</option></select></label>
    <label>Password <input type="password" name="password" minlength="6" required></label>
    <button>Create user</button>
  </form></div>
<div class="tablewrap"><table>
  <tr><th>Name</th><th>Phone</th><th>Role</th><th>Wallet</th><th>Created</th></tr>
  <?php foreach ($users as $r): ?>
  <tr><td><?= h($r['full_name']) ?></td><td><?= h($r['phone_number']) ?></td><td><?= h($r['role']) ?></td><td><?= ugx($r['wallet_balance']) ?></td><td><?= h($r['created_at']) ?></td></tr>
  <?php endforeach; ?>
</table></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
