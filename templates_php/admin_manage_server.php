<?php declare(strict_types=1); ?>
<section class="card" style="padding:24px;">
  <h2 class="card-title">Manage Server</h2>
  <form method="post" class="form">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
    <label style="display:flex; align-items:center; gap:8px;">
      <input type="checkbox" name="suspended" value="1" <?= !empty($suspended) ? "checked" : "" ?>>
      Suspended
    </label>
    <label style="display:flex; align-items:center; gap:8px;">
      <input type="checkbox" name="destroyed" value="1" <?= !empty($destroyed) ? "checked" : "" ?>>
      Destroyed
    </label>
    <button class="btn btn-primary" type="submit">Save</button>
  </form>
  <pre style="margin-top:12px; white-space:pre-wrap; word-break:break-word;"><?= htmlspecialchars((string) ($sysinfo ?? ""), ENT_QUOTES) ?></pre>
</section>
