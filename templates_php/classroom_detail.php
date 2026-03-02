<?php declare(strict_types=1); ?>
<section class="card" style="padding:24px;">
  <div class="card-header-row">
    <h2 class="card-title">Classroom: <?= htmlspecialchars((string) ($classroom["name"] ?? ""), ENT_QUOTES) ?></h2>
    <a class="btn btn-secondary" href="/classrooms">Back</a>
  </div>
  <p class="muted-text">Owner: <?= htmlspecialchars((string) (($classroom["owner_username"] ?? "") ?: "System"), ENT_QUOTES) ?></p>
  <?php if (!empty($error)): ?><p class="result-text" style="color:#fca5a5;"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></p><?php endif; ?>
  <?php if (!empty($message)): ?><p class="result-text"><?= htmlspecialchars((string) $message, ENT_QUOTES) ?></p><?php endif; ?>

  <div class="card" style="margin-top:12px; padding:16px;">
    <h3 class="page-title" style="margin:0 0 8px 0;">Members</h3>
    <form method="post" class="form" style="margin-bottom:10px;">
      <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
      <input type="hidden" name="action" value="add_student">
      <input class="input" name="student_name" placeholder="Learner username/name">
      <button class="btn btn-primary" type="submit">Add Learner</button>
    </form>
    <ul style="margin:0; padding-left:18px;">
      <?php foreach (($members ?? []) as $m): ?>
        <li style="margin-bottom:6px;">
          <?= htmlspecialchars((string) ($m["name"] ?? ""), ENT_QUOTES) ?>
          <form method="post" style="display:inline;">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
            <input type="hidden" name="action" value="remove_student">
            <input type="hidden" name="membership_id" value="<?= (int) ($m["membership_id"] ?? 0) ?>">
            <button class="btn btn-danger" type="submit">Remove</button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>

  <div class="card" style="margin-top:12px; padding:16px;">
    <h3 class="page-title" style="margin:0 0 8px 0;">Assignments</h3>
    <form method="post" class="form">
      <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
      <input type="hidden" name="action" value="create_assignment">
      <input class="input" name="title" placeholder="Assignment title" required>
      <textarea class="input" name="description" rows="2" placeholder="Description (optional)"></textarea>
      <input class="input" type="date" name="due_date">
      <input class="input" name="level" placeholder="Level (e.g. A1)">
      <button class="btn btn-primary" type="submit">Create Assignment</button>
    </form>
    <ul style="margin-top:10px; padding-left:18px;">
      <?php foreach (($assignments ?? []) as $a): ?>
        <li style="margin-bottom:6px;">
          <strong><?= htmlspecialchars((string) ($a["title"] ?? ""), ENT_QUOTES) ?></strong>
          <?php if (!empty($a["due_date"])): ?><span class="muted-text">(due <?= htmlspecialchars((string) $a["due_date"], ENT_QUOTES) ?>)</span><?php endif; ?>
          <form method="post" style="display:inline;">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
            <input type="hidden" name="action" value="delete_assignment">
            <input type="hidden" name="assignment_id" value="<?= (int) ($a["id"] ?? 0) ?>">
            <button class="btn btn-danger" type="submit">Delete</button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
