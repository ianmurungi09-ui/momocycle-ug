<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'USSD simulator';
require __DIR__ . '/includes/header.php';
?>
<h1>USSD simulator</h1>
<p class="muted">Mimics a feature-phone session. It sends the same fields a real USSD gateway sends to <code>ussd.php</code>. Try phone 0700000003, password Pass1234.</p>
<div class="phone">
  <input id="phone" value="0700000003" style="width:100%;margin-bottom:8px">
  <div class="screen" id="screen">Press DIAL *165*99#</div>
  <input id="reply" placeholder="Your reply" style="width:100%;margin-bottom:8px" disabled>
  <button id="dial" type="button">Dial *165*99#</button>
  <button id="send" type="button" disabled>Send</button>
</div>
<script>
let text = '', active = false;
const screen = document.getElementById('screen'), reply = document.getElementById('reply');
const sendBtn = document.getElementById('send'), dialBtn = document.getElementById('dial');
async function call() {
  const body = new URLSearchParams({ sessionId: 'sim', phoneNumber: document.getElementById('phone').value, text });
  const r = await fetch('<?= BASE_URL ?>/ussd.php', { method: 'POST', body });
  const t = await r.text();
  screen.textContent = t.replace(/^(CON|END) ?/, '');
  active = t.startsWith('CON');
  reply.disabled = sendBtn.disabled = !active;
  reply.value = '';
  if (active) reply.focus(); else dialBtn.disabled = false;
}
dialBtn.onclick = () => { text = ''; dialBtn.disabled = true; call(); };
sendBtn.onclick = () => { const v = reply.value.trim(); if (!v) return; text = text ? text + '*' + v : v; call(); };
reply.addEventListener('keydown', e => { if (e.key === 'Enter') sendBtn.click(); });
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
