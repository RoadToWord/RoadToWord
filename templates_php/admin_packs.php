<?php declare(strict_types=1); ?>
<section class="card" style="padding:24px;">
  <h2 class="card-title">Admin Packs</h2>
  <p class="muted-text">Export content packs by CEFR level or import additional vocab pack JSON.</p>

  <?php if (!empty($error)): ?><p class="result-text" style="color:#fca5a5;"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></p><?php endif; ?>
  <?php if (!empty($message)): ?><p class="result-text"><?= htmlspecialchars((string) $message, ENT_QUOTES) ?></p><?php endif; ?>

  <div class="card" style="margin-top:12px; padding:16px;">
    <h3 class="page-title" style="margin:0 0 10px 0;">Export Pack</h3>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
      <a class="btn btn-secondary" href="/admin/packs?action=export&level=all">Export All</a>
      <?php foreach (($levels ?? []) as $lv): ?>
        <a class="btn btn-secondary" href="/admin/packs?action=export&level=<?= urlencode((string) $lv) ?>">Export <?= htmlspecialchars((string) $lv, ENT_QUOTES) ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card" style="margin-top:12px; padding:16px;">
    <h3 class="page-title" style="margin:0 0 10px 0;">Import Pack</h3>
    <form method="post" enctype="multipart/form-data" class="form">
      <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
      <input type="hidden" name="action" value="import">
      <input type="file" name="file" class="input" accept=".json,application/json" required>
      <button class="btn btn-primary" type="submit">Import Pack</button>
    </form>
  </div>
</section>
