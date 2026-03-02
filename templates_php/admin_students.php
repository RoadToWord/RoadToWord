<?php declare(strict_types=1); ?>
<section class="card" style="padding:24px;">
  <div class="card-header-row">
    <h2 class="card-title">Admin Students</h2>
    <div style="display:flex; gap:8px;">
      <form method="post" action="/admin/students/reset_all" style="display:inline;">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
        <button class="btn btn-secondary" type="submit" onclick="return confirm('Reset all student progress?')">Reset All</button>
      </form>
      <form method="post" action="/admin/students/purge_all" style="display:inline;">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
        <button class="btn btn-danger" type="submit" onclick="return confirm('Purge ALL students?')">Purge All</button>
      </form>
    </div>
  </div>
  <div class="table-wrapper" style="margin-top:12px;">
    <table class="table">
      <thead><tr><th>Name</th><th>Correct</th><th>Wrong</th><th>Accuracy</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach (($rows ?? []) as $r): ?>
        <tr>
          <td><?= htmlspecialchars((string) ($r["name"] ?? ""), ENT_QUOTES) ?></td>
          <td><?= (int) ($r["total_correct"] ?? 0) ?></td>
          <td><?= (int) ($r["total_wrong"] ?? 0) ?></td>
          <td><?= htmlspecialchars((string) ($r["accuracy"] ?? 0), ENT_QUOTES) ?>%</td>
          <td>
            <a class="nav-link" href="/admin/students/<?= rawurlencode((string) ($r["name"] ?? "")) ?>/edit">Edit</a>
            <form method="post" action="/admin/students/<?= rawurlencode((string) ($r["name"] ?? "")) ?>/reset" style="display:inline;">
              <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
              <button class="btn btn-danger" type="submit" onclick="return confirm('Reset this student?')">Reset</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
