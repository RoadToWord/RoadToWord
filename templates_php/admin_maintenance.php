<?php declare(strict_types=1); ?>
<section class="card" style="padding:24px;">
  <h2 class="card-title">Maintenance Mode</h2>
  <p class="muted-text">Current: <strong><?= !empty($maintenance) ? "ON" : "OFF" ?></strong></p>
  <form method="post" class="form">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
    <input type="hidden" name="enabled" value="<?= !empty($maintenance) ? "0" : "1" ?>">
    <button class="btn <?= !empty($maintenance) ? 'btn-danger' : 'btn-primary' ?>" type="submit">
      <?= !empty($maintenance) ? "Disable" : "Enable" ?> Maintenance
    </button>
  </form>
</section>
