<?php declare(strict_types=1); ?>
<?php $item = $item ?? []; $allowed = $item["allowed_modes"] ?? []; ?>
<section class="card" style="padding:24px;">
  <h2 class="card-title">Edit Student: <?= htmlspecialchars((string) ($item["name"] ?? ""), ENT_QUOTES) ?></h2>
  <?php if (!empty($error)): ?><p class="result-text" style="color:#fca5a5;"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></p><?php endif; ?>
  <form method="post" class="form">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
    <label>Allowed Modes</label>
    <div class="card" style="padding:12px;">
      <?php foreach (($modes ?? []) as $key => $label): ?>
        <label style="display:flex; align-items:center; gap:8px; margin:4px 0;">
          <input type="checkbox" name="allowed_modes[]" value="<?= htmlspecialchars((string) $key, ENT_QUOTES) ?>" <?= in_array($key, $allowed, true) ? "checked" : "" ?>>
          <?= htmlspecialchars((string) $label, ENT_QUOTES) ?>
        </label>
      <?php endforeach; ?>
    </div>
    <label>Streak mode</label>
    <select class="input" name="streak_mode">
      <option value="strict" <?= (($item["streak_mode"] ?? "") === "strict") ? "selected" : "" ?>>Strict</option>
      <option value="lenient" <?= (($item["streak_mode"] ?? "") === "lenient") ? "selected" : "" ?>>Lenient</option>
    </select>
    <label>Daily target</label>
    <input class="input" type="number" min="1" max="200" name="daily_target" value="<?= (int) ($item["daily_target"] ?? 20) ?>">
    <div style="display:flex; gap:8px;">
      <button class="btn btn-primary" type="submit">Save</button>
      <a class="btn btn-secondary" href="/admin/students">Back</a>
    </div>
  </form>
</section>
