<?php declare(strict_types=1); ?>
<section class="card" style="padding:24px;">
  <h2 class="card-title"><?= ($mode ?? "new") === "edit" ? "Edit Keyword" : "New Keyword" ?></h2>
  <?php if (!empty($error)): ?><p class="result-text" style="color:#fca5a5;"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></p><?php endif; ?>
  <form method="post" class="form" style="margin-top:12px;">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
    <label>Keyword</label>
    <input class="input" name="keyword" value="<?= htmlspecialchars((string) (($item["keyword"] ?? "")), ENT_QUOTES) ?>" required>
    <label>Description</label>
    <textarea class="input" name="description" rows="5" required><?= htmlspecialchars((string) (($item["description"] ?? "")), ENT_QUOTES) ?></textarea>
    <div style="display:flex; gap:8px;">
      <button class="btn btn-primary" type="submit">Save</button>
      <a class="btn btn-secondary" href="/admin/keywords">Back</a>
    </div>
  </form>
</section>
