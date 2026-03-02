<?php declare(strict_types=1); ?>
<?php
$item = $item ?? [];
$synText = is_array($item["synonyms"] ?? null) ? implode(", ", $item["synonyms"]) : (string) ($item["synonyms"] ?? "");
$antText = is_array($item["antonyms"] ?? null) ? implode(", ", $item["antonyms"]) : (string) ($item["antonyms"] ?? "");
?>
<section class="card" style="padding:24px;">
  <h2 class="card-title"><?= ($mode ?? "new") === "edit" ? "Edit Vocab" : "New Vocab" ?></h2>
  <?php if (!empty($error)): ?><p class="result-text" style="color:#fca5a5;"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></p><?php endif; ?>
  <form method="post" class="form" style="margin-top:12px;">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
    <label>Level</label>
    <input class="input" name="level" value="<?= htmlspecialchars((string) ($item["level"] ?? "A1"), ENT_QUOTES) ?>">
    <label>Word</label>
    <input class="input" name="word" value="<?= htmlspecialchars((string) ($item["word"] ?? ""), ENT_QUOTES) ?>" required>
    <label>Turkish</label>
    <input class="input" name="turkish" value="<?= htmlspecialchars((string) ($item["turkish"] ?? ""), ENT_QUOTES) ?>" required>
    <label>Definition</label>
    <textarea class="input" name="definition" rows="3"><?= htmlspecialchars((string) ($item["definition"] ?? ""), ENT_QUOTES) ?></textarea>
    <label>Example (EN)</label>
    <textarea class="input" name="example_en" rows="2"><?= htmlspecialchars((string) ($item["example_en"] ?? ""), ENT_QUOTES) ?></textarea>
    <label>Example (TR)</label>
    <textarea class="input" name="example_tr" rows="2"><?= htmlspecialchars((string) ($item["example_tr"] ?? ""), ENT_QUOTES) ?></textarea>
    <label>Synonyms (comma-separated)</label>
    <input class="input" name="synonyms" value="<?= htmlspecialchars((string) $synText, ENT_QUOTES) ?>">
    <label>Antonyms (comma-separated)</label>
    <input class="input" name="antonyms" value="<?= htmlspecialchars((string) $antText, ENT_QUOTES) ?>">
    <div style="display:flex; gap:8px;">
      <button class="btn btn-primary" type="submit">Save</button>
      <a class="btn btn-secondary" href="/admin/vocab">Back</a>
    </div>
  </form>
</section>
