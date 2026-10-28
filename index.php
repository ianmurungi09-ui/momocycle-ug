<?php
require_once __DIR__ . '/includes/auth.php';

if ($u = current_user()) { header('Location: ' . home_for($u['role'])); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $phone = trim($_POST['phone'] ?? '');
    $stmt = $pdo->prepare('SELECT user_id, full_name, role, password_hash FROM users WHERE phone_number = ?');
    $stmt->execute([$phone]);
    $row = $stmt->fetch();
    if ($row && password_verify($_POST['password'] ?? '', $row['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = ['user_id' => (int)$row['user_id'], 'full_name' => $row['full_name'], 'role' => $row['role']];
        header('Location: ' . home_for($row['role']));
        exit;
    }
    $error = 'Wrong phone number or password.';
}
$pageTitle = 'Login';
require __DIR__ . '/includes/header.php';
?>
<div class="card" style="max-width:480px;margin:auto">
  <h1>Sign in</h1>
  <p class="muted">Circular-economy waste tracking and mobile money payouts.</p>
  <?php if ($error): ?><div class="alert err"><?= h($error) ?></div><?php endif; ?>
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <label>Phone number <input name="phone" required autofocus placeholder="0700000002"></label>
    <label>Password <input type="password" name="password" required></label>
    <button>Login</button>
  </form>
  <p class="muted">Demo users (password <b>Pass1234</b>): Admin 0700000001 · Agent 0700000002 · Collector 0700000003</p>
  <p><a href="<?= BASE_URL ?>/ussd-simulator.php">Open the USSD simulator</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
