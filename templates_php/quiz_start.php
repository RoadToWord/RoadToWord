<?php declare(strict_types=1); ?>
<?php $isLoggedIn = !empty($session_username); ?>
<div class="card">
  <div class="card-header-row">
    <h2 class="card-title">Start Practice</h2>
    <?php if (!empty($road_balance_text)): ?>
      <span class="pill subtle"><?= htmlspecialchars((string) $road_balance_text, ENT_QUOTES) ?></span>
    <?php endif; ?>
  </div>

  <?php if (!empty($error)): ?>
    <p class="error-text"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></p>
  <?php endif; ?>

  <form method="post" class="form">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
    <?php if (!empty($referrer_username)): ?>
      <input type="hidden" name="referrer_username" value="<?= htmlspecialchars((string) $referrer_username, ENT_QUOTES) ?>">
      <p class="muted-text">Referred by @<?= htmlspecialchars((string) $referrer_username, ENT_QUOTES) ?></p>
    <?php endif; ?>

    <?php if (!empty($session_role) && $session_role === "student"): ?>
      <label class="label">Username</label>
      <input class="input" name="student" value="<?= htmlspecialchars((string) $session_username, ENT_QUOTES) ?>" readonly>
    <?php else: ?>
      <label class="label">Full Name</label>
      <input class="input" name="full_name" required>
      <label class="label">Username</label>
      <input class="input" name="student" required>
      <label class="label">Password</label>
      <input class="input" name="password" type="password" required>
      <label class="label">Email (optional)</label>
      <input class="input" name="email" type="email">
      <label class="label">Teacher</label>
      <select class="input" name="teacher_id" required>
        <option value="">Select your teacher</option>
        <option value="individual">Individual</option>
        <?php foreach (($teachers ?? []) as $t): ?>
          <option value="<?= (int) ($t["id"] ?? 0) ?>"><?= htmlspecialchars((string) (($t["full_name"] ?? "") !== "" ? $t["full_name"] : $t["username"]), ENT_QUOTES) ?> (Teacher)</option>
        <?php endforeach; ?>
      </select>
      <p class="muted-text">Starting practice creates a learner account if the name is new.</p>
    <?php endif; ?>

    <label class="label" style="margin-top:8px;">Practice Type</label>
    <select name="mode" class="input">
      <?php foreach (($modes ?? []) as $k => $v): ?>
        <option value="<?= htmlspecialchars((string) $k, ENT_QUOTES) ?>"><?= htmlspecialchars((string) $v, ENT_QUOTES) ?></option>
      <?php endforeach; ?>
    </select>

    <?php if (!$isLoggedIn): ?>
      <label class="label consent-line" style="margin-top:10px; display:flex; gap:8px; align-items:flex-start;">
        <input id="signup-consent" type="checkbox" name="signup_consent" style="margin-top:4px;">
        <span>I agree to the <a class="consent-link" href="/tos" target="_blank" rel="noopener">Terms of Service</a> and <a class="consent-link" href="/privpolicy" target="_blank" rel="noopener">Privacy Policy</a>.</span>
      </label>
      <?php if (!empty($turnstile_enabled) && !empty($turnstile_sitekey)): ?>
        <label class="label" style="margin-top:10px;">CAPTCHA</label>
        <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars((string) $turnstile_sitekey, ENT_QUOTES) ?>"></div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($google_enabled) && !$isLoggedIn): ?>
      <a class="btn btn-secondary btn-full js-google-signup" style="margin-top:10px;" href="/login/google" data-base-url="/login/google">Sign Up with Google</a>
    <?php endif; ?>

    <button class="btn btn-primary btn-full" style="margin-top:10px;">Start</button>
  </form>
</div>

<script nonce="<?= htmlspecialchars((string) ($csp_nonce ?? ""), ENT_QUOTES) ?>">
document.addEventListener("DOMContentLoaded", function () {
  var signupConsent = document.getElementById("signup-consent");
  var googleSignupBtn = document.querySelector(".js-google-signup");
  if (googleSignupBtn) {
    googleSignupBtn.addEventListener("click", function (event) {
      if (signupConsent && !signupConsent.checked) {
        event.preventDefault();
        alert("Please agree to the Terms of Service and Privacy Policy.");
        return;
      }
      var baseUrl = googleSignupBtn.dataset.baseUrl || googleSignupBtn.getAttribute("href");
      googleSignupBtn.setAttribute("href", baseUrl + "?agree=1");
    });
  }
});
</script>
<?php if (!$isLoggedIn && !empty($turnstile_enabled) && !empty($turnstile_sitekey)): ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>
