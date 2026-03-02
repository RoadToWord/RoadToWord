<?php declare(strict_types=1); ?>
<section class="card" style="padding:24px; max-width:820px; margin:auto;">
  <div class="card-header-row">
    <h2 class="card-title">Call @<?= htmlspecialchars((string) ($peer["username"] ?? ""), ENT_QUOTES) ?></h2>
    <a class="btn btn-secondary" href="/messages/<?= rawurlencode((string) ($peer["username"] ?? "")) ?>">Back to Chat</a>
  </div>

  <p class="muted-text">Free voice call powered by Jitsi Meet. It opens in a new tab.</p>

  <div class="card" style="padding:14px; margin-top:12px;">
    <p style="margin-top:0;"><strong>How to use</strong></p>
    <p class="muted-text">Both users click the call button in the same chat. You will join the same private room automatically.</p>
    <p class="muted-text">If you only want audio, turn your camera off when joining (video starts muted by default).</p>
    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:10px;">
      <a class="btn btn-primary" href="<?= htmlspecialchars((string) ($jitsi_url ?? ""), ENT_QUOTES) ?>" target="_blank" rel="noopener noreferrer">Open Call Room</a>
      <button class="btn btn-secondary" type="button" id="copyCallLinkBtn">Copy Call Link</button>
    </div>
    <p class="muted-text" id="copyCallLinkNote" style="margin-top:8px;"></p>
  </div>
</section>
<script nonce="<?= htmlspecialchars((string) ($csp_nonce ?? ""), ENT_QUOTES) ?>">
(function () {
  var btn = document.getElementById('copyCallLinkBtn');
  var note = document.getElementById('copyCallLinkNote');
  if (!btn) return;
  var url = <?= json_encode((string) ($jitsi_url ?? ""), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  btn.addEventListener('click', async function () {
    try {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        await navigator.clipboard.writeText(url);
      } else {
        var ta = document.createElement('textarea');
        ta.value = url;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        ta.remove();
      }
      if (note) note.textContent = 'Call link copied.';
    } catch (e) {
      if (note) note.textContent = 'Could not copy. Use Open Call Room.';
    }
  });
})();
</script>
