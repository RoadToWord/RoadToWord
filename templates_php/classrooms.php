<?php declare(strict_types=1); ?>
<section class="card" style="padding:24px;">
  <div class="card-header-row">
    <h2 class="card-title">Classrooms</h2>
    <a class="btn btn-secondary" href="/admin">Admin</a>
  </div>
  <?php if (!empty($error)): ?><p class="result-text" style="color:#fca5a5;"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></p><?php endif; ?>
  <?php if (!empty($message)): ?><p class="result-text"><?= htmlspecialchars((string) $message, ENT_QUOTES) ?></p><?php endif; ?>
  <form method="post" class="form" style="margin-top:12px;">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
    <input type="hidden" name="action" value="create">
    <label>New classroom</label>
    <input class="input" name="name" placeholder="Classroom name">
    <button class="btn btn-primary" type="submit">Create Classroom</button>
  </form>
  <div class="table-wrapper" style="margin-top:14px;">
    <table class="table">
      <thead><tr><th>Name</th><th>Owner</th><th>Members</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach (($rows ?? []) as $r): ?>
        <tr>
          <td><?= htmlspecialchars((string) ($r["name"] ?? ""), ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars((string) (($r["owner_username"] ?? "") ?: "System"), ENT_QUOTES) ?></td>
          <td><?= (int) ($r["member_count"] ?? 0) ?></td>
          <td>
            <a class="nav-link" href="/classrooms/<?= (int) ($r["id"] ?? 0) ?>">Open</a>
            <a class="nav-link" href="/classrooms/<?= (int) ($r["id"] ?? 0) ?>/dashboard">Dashboard</a>
            <form method="post" style="display:inline;">
              <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="classroom_id" value="<?= (int) ($r["id"] ?? 0) ?>">
              <button class="btn btn-danger" type="submit" onclick="return confirm('Delete classroom?')">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
