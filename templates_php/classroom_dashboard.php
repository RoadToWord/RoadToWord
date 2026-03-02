<?php declare(strict_types=1); ?>
<section class="card" style="padding:24px;">
  <div class="card-header-row">
    <h2 class="card-title">Classroom Dashboard: <?= htmlspecialchars((string) ($classroom["name"] ?? ""), ENT_QUOTES) ?></h2>
    <a class="btn btn-secondary" href="/classrooms/<?= (int) ($classroom["id"] ?? 0) ?>">Back</a>
  </div>
  <div class="table-wrapper" style="margin-top:12px;">
    <table class="table">
      <thead><tr><th>Student</th><th>Correct</th><th>Wrong</th><th>Accuracy</th></tr></thead>
      <tbody>
      <?php foreach (($rows ?? []) as $r): ?>
        <tr>
          <td><?= htmlspecialchars((string) ($r["name"] ?? ""), ENT_QUOTES) ?></td>
          <td><?= (int) ($r["total_correct"] ?? 0) ?></td>
          <td><?= (int) ($r["total_wrong"] ?? 0) ?></td>
          <td><?= htmlspecialchars((string) ($r["accuracy"] ?? 0), ENT_QUOTES) ?>%</td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
