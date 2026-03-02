<?php declare(strict_types=1); ?>
<section class="card" style="padding:24px;">
  <h2 class="card-title">Import Keywords (CSV)</h2>
  <p class="muted-text">CSV columns: `keyword, description` (description optional).</p>
  <?php if (!empty($error)): ?><p class="result-text" style="color:#fca5a5;"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></p><?php endif; ?>
  <form method="post" enctype="multipart/form-data" class="form" style="margin-top:12px;">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
    <input type="file" name="file" class="input" accept=".csv,text/csv" required>
    <div style="display:flex; gap:8px;">
      <button class="btn btn-primary" type="submit">Import</button>
      <a class="btn btn-secondary" href="/admin/keywords">Back</a>
    </div>
  </form>
</section>
