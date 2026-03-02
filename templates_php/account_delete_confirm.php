<?php declare(strict_types=1); ?>
<section class="card" style="padding:24px; max-width:700px; margin:auto;">
  <h2 class="card-title">Delete Account</h2>
  <p class="muted-text">This action is permanent. Type <strong>DELETE</strong> to confirm.</p>
  <?php if (!empty($error)): ?><p class="result-text" style="color:#fca5a5;"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></p><?php endif; ?>
  <form method="post" class="form" style="margin-top:12px;">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
    <label>Type DELETE</label>
    <input class="input" name="confirm_text" autocomplete="off" required>
    <?php if (!empty($turnstile_enabled) && !empty($turnstile_sitekey)): ?>
      <label>CAPTCHA</label>
      <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars((string) $turnstile_sitekey, ENT_QUOTES) ?>"></div>
    <?php endif; ?>
    <div style="display:flex; gap:8px;">
      <button class="btn btn-danger" type="submit" onclick="return confirm('Are you absolutely sure?')" style="margin-top:10px;">Delete Account</button>
      <a class="btn btn-secondary" href="/account">Cancel</a>
    </div>
  </form>
</section>
<?php if (!empty($turnstile_enabled) && !empty($turnstile_sitekey)): ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>
