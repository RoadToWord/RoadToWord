<?php declare(strict_types=1); ?>
<section class="card" style="padding:24px;">
  <div class="card-header-row">
    <h2 class="card-title">Admin Keywords</h2>
    <div style="display:flex; gap:8px;">
      <a class="btn btn-secondary" href="/admin/keywords/import">Import CSV</a>
      <a class="btn btn-primary" href="/admin/keywords/new">New Keyword</a>
    </div>
  </div>

  <div class="table-wrapper" style="margin-top:12px;">
    <table class="table">
      <thead><tr><th>ID</th><th>Keyword</th><th>Slug</th><th>Description</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach (($rows ?? []) as $r): ?>
        <tr>
          <td><?= (int) ($r["id"] ?? 0) ?></td>
          <td><?= htmlspecialchars((string) ($r["keyword"] ?? ""), ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars((string) ($r["slug"] ?? ""), ENT_QUOTES) ?></td>
          <td style="max-width:360px; word-break:break-word;"><?= htmlspecialchars((string) ($r["description"] ?? ""), ENT_QUOTES) ?></td>
          <td>
            <a class="nav-link" href="/admin/keywords/<?= (int) $r["id"] ?>/edit">Edit</a>
            <form method="post" action="/admin/keywords/<?= (int) $r["id"] ?>/delete" style="display:inline;">
              <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
              <button type="submit" class="btn btn-danger" onclick="return confirm('Delete keyword?')">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
