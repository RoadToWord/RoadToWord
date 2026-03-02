<?php declare(strict_types=1); ?>
<section class="card" style="padding:24px;">
  <h2 class="card-title">Login</h2>
  <?php if (!empty($error)): ?>
    <p class="error-text"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></p>
  <?php endif; ?>
  <form method="post" class="form">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
    <label class="label">Username</label>
    <input class="input" name="username" required>

    <label class="label">Password</label>
    <input class="input" name="password" type="password" required>

    <?php if (!empty($turnstile_enabled) && !empty($turnstile_sitekey)): ?>
      <label class="label">CAPTCHA</label>
      <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars((string) $turnstile_sitekey, ENT_QUOTES) ?>"></div>
    <?php endif; ?>
    <button class="btn btn-primary btn-full" style="margin-top:10px;" type="submit">Login</button>
  </form>
  <?php if (!empty($google_enabled)): ?>
    <div class="card" style="margin-top:12px; padding:12px;">
      <form method="post" action="/login/google" class="form" style="margin:0;">
        <label class="login-consent-row">
          <input type="checkbox" name="agree" value="1" id="google-login-agree">
          <span>I agree to the <a href="/tos" target="_blank" rel="noopener">Terms of Service</a> and <a href="/privpolicy" target="_blank" rel="noopener">Privacy Policy</a>.</span>
        </label>
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
        <?php if (!empty($turnstile_enabled) && !empty($turnstile_sitekey)): ?>
          <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars((string) $turnstile_sitekey, ENT_QUOTES) ?>"></div>
        <?php endif; ?>
        <button id="google-login-btn" class="btn btn-secondary btn-full" type="submit" style="margin-top:10px;">Login / Sign Up with Google</button>
      </form>
    </div>
  <?php endif; ?>
</section>
<?php if (!empty($turnstile_enabled) && !empty($turnstile_sitekey)): ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>
