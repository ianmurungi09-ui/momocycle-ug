<?php
// USSD gateway endpoint. A real aggregator (e.g. Africa's Talking) POSTs
// sessionId, phoneNumber and text; we answer in plain text starting with CON or END.
require_once __DIR__ . '/config/db.php';
header('Content-Type: text/plain; charset=utf-8');

$phone = trim($_POST['phoneNumber'] ?? '');
$text  = trim($_POST['text'] ?? '');
$parts = $text === '' ? [] : explode('*', $text);

if ($phone === '') { echo 'END Missing phone number'; exit; }
if (count($parts) === 0) { echo "CON Welcome to MoMoCycle UG\nEnter your password:"; exit; }

$s = $pdo->prepare("SELECT user_id, full_name, wallet_balance, password_hash FROM users WHERE phone_number = ? AND role = 'COLLECTOR'");
$s->execute([$phone]);
$user = $s->fetch();
if (!$user || !password_verify($parts[0], $user['password_hash'])) { echo 'END Wrong phone number or password.'; exit; }

if (count($parts) === 1) {
    echo "CON Hello {$user['full_name']}\n1. Check balance\n2. Last 3 deposits\n3. Current rates";
    exit;
}
switch ($parts[1]) {
    case '1':
        echo 'END Your balance is ' . ugx_plain($user['wallet_balance']);
        break;
    case '2':
        $q = $pdo->prepare('SELECT material_type, weight_kg, status FROM waste_deposits WHERE collector_id = ? ORDER BY deposit_id DESC LIMIT 3');
        $q->execute([$user['user_id']]);
        $out = "END Recent deposits:\n";
        $n = 0;
        foreach ($q as $r) { $out .= "{$r['material_type']} {$r['weight_kg']}kg {$r['status']}\n"; $n++; }
        echo $n ? rtrim($out) : 'END No deposits yet.';
        break;
    case '3':
        $out = "END Rates per kg:\n";
        foreach ($pdo->query('SELECT material_type, rate_per_kg FROM material_rates ORDER BY material_type') as $r) {
            $out .= "{$r['material_type']}: " . ugx_plain($r['rate_per_kg']) . "\n";
        }
        echo rtrim($out);
        break;
    default:
        echo 'END Invalid option.';
}
function ugx_plain($n): string { return 'UGX ' . number_format((float)$n, 0); }
