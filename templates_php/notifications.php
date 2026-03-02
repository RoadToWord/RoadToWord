<?php declare(strict_types=1); ?>
<section class="card" style="padding:24px;">
  <h2 class="card-title">Notifications</h2>
  <form method="post" class="form" style="margin-bottom:10px;">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
    <button class="btn btn-secondary" type="submit">Mark all read</button>
  </form>
  <?php foreach (($rows ?? []) as $n): ?>
    <div class="card" style="padding:12px; margin-bottom:8px;">
      <div><?= htmlspecialchars((string) ($n["message"] ?? ""), ENT_QUOTES) ?></div>
      <div class="muted-text"><?= htmlspecialchars((string) ($n["created_at"] ?? ""), ENT_QUOTES) ?> | <?= !empty($n["is_read"]) ? "read" : "unread" ?></div>
    </div>
  <?php endforeach; ?>
</section>

