<?php
require_once __DIR__ . '/auth.php';
$u = current_user();
$pageTitle = $pageTitle ?? 'MoMoCycle UG';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle) ?> | MoMoCycle UG</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css">
</head>
<body>
<header class="topbar">
  <div class="brand">♻ MoMoCycle UG</div>
  <?php if ($u): ?>
  <nav>
    <?php if ($u['role'] === 'ADMIN'): ?>
      <a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a>
      <a href="<?= BASE_URL ?>/admin/reports.php">Reports</a>
      <a href="<?= BASE_URL ?>/admin/rates.php">Rates</a>
      <a href="<?= BASE_URL ?>/admin/hubs.php">Hubs</a>
      <a href="<?= BASE_URL ?>/admin/users.php">Users</a>
    <?php endif; ?>
    <?php if (in_array($u['role'], ['ADMIN','AGENT'], true)): ?>
      <a href="<?= BASE_URL ?>/agent/log-deposit.php">Log deposit</a>
      <a href="<?= BASE_URL ?>/agent/pending.php">Payouts</a>
      <a href="<?= BASE_URL ?>/agent/collectors.php">Collectors</a>
    <?php endif; ?>
    <?php if ($u['role'] === 'COLLECTOR'): ?>
      <a href="<?= BASE_URL ?>/collector/dashboard.php">My wallet</a>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>/ussd-simulator.php">USSD</a>
    <a href="<?= BASE_URL ?>/logout.php">Logout (<?= h($u['full_name']) ?>, <?= h($u['role']) ?>)</a>
  </nav>
  <?php endif; ?>
</header>
<main class="container">
<?php if (!empty($_SESSION['flash'])): [$ft, $fm] = $_SESSION['flash']; unset($_SESSION['flash']); ?>
  <div class="alert <?= h($ft) ?>"><?= h($fm) ?></div>
<?php endif; ?>
