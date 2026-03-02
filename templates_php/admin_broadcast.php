<?php declare(strict_types=1); ?>
<section class="card" style="padding:24px;">
  <h2 class="card-title">Admin Broadcast</h2>
  <p class="muted-text">Send a notification to all users.</p>
  <?php if (!empty($error)): ?>
    <p class="result-text" style="color:#fca5a5;"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></p>
  <?php endif; ?>
  <form method="post" class="form" style="margin-top:12px;">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
    <textarea name="message" class="input" rows="4" placeholder="Broadcast message..."><?= htmlspecialchars((string) ($message_value ?? ""), ENT_QUOTES) ?></textarea>
    <button type="submit" class="btn btn-primary">Send Broadcast</button>
  </form>
</section>
