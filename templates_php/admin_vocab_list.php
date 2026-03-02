<?php declare(strict_types=1); ?>
<section class="card" style="padding:24px;">
  <div class="card-header-row">
    <h2 class="card-title">Admin Vocab</h2>
    <div style="display:flex; gap:8px;">
      <a class="btn btn-secondary" href="/admin/vocab/import">Import JSON</a>
      <a class="btn btn-secondary" href="/admin/vocab/export">Export JSON</a>
      <a class="btn btn-primary" href="/admin/vocab/new">New Word</a>
    </div>
  </div>

  <form method="get" class="form" style="margin-top:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
    <input class="input" name="q" placeholder="Search vocab..." value="<?= htmlspecialchars((string) ($q ?? ""), ENT_QUOTES) ?>" style="max-width:320px;">
    <select class="input" name="level" style="max-width:140px;">
      <option value="">All levels</option>
      <?php foreach (($levels ?? []) as $lv): ?>
        <option value="<?= htmlspecialchars((string) $lv, ENT_QUOTES) ?>" <?= ((string) ($level ?? "") === (string) $lv) ? "selected" : "" ?>><?= htmlspecialchars((string) $lv, ENT_QUOTES) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-secondary" type="submit">Filter</button>
  </form>

  <div class="table-wrapper">
    <table class="table">
      <thead><tr><th>ID</th><th>Level</th><th>Word</th><th>Turkish</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach (($rows ?? []) as $r): ?>
        <tr>
          <td><?= (int) ($r["id"] ?? 0) ?></td>
          <td><?= htmlspecialchars((string) ($r["level"] ?? ""), ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars((string) ($r["word"] ?? ""), ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars((string) ($r["turkish"] ?? ""), ENT_QUOTES) ?></td>
          <td>
            <a class="nav-link" href="/admin/vocab/<?= (int) $r["id"] ?>/edit">Edit</a>
            <form method="post" action="/admin/vocab/<?= (int) $r["id"] ?>/delete" style="display:inline;">
              <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
              <button class="btn btn-danger" type="submit" onclick="return confirm('Delete vocab item?')">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
