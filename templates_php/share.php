<?php declare(strict_types=1); ?>
<?php
$r = $report ?? [];
$s = $r["summary"] ?? [];
$studentForCsv = (string) ($r["student"] ?? "");
$csvToken = rtrim(strtr(base64_encode($studentForCsv), '+/', '-_'), '=');
?>
<section class="card" style="padding:24px;">
  <div class="card-header-row">
    <h2 class="card-title">Shared Progress: <?= htmlspecialchars((string) ($r["student"] ?? ""), ENT_QUOTES) ?></h2>
    <a class="btn btn-secondary" href="/share/<?= rawurlencode($csvToken) ?>/csv">Download CSV</a>
  </div>
  <div class="mini-share" style="margin-top:8px;">
    <span class="pill subtle">Correct: <?= (int) ($s["correct"] ?? 0) ?></span>
    <span class="pill subtle">Wrong: <?= (int) ($s["wrong"] ?? 0) ?></span>
    <span class="pill subtle">Accuracy: <?= htmlspecialchars((string) ($s["accuracy"] ?? 0), ENT_QUOTES) ?>%</span>
  </div>

  <div class="table-wrapper" style="margin-top:12px;">
    <table class="table">
      <thead><tr><th>Word</th><th>Correct</th><th>Wrong</th><th>Accuracy</th><th>Interval</th><th>Level</th></tr></thead>
      <tbody>
      <?php foreach (($r["per_word"] ?? []) as $w): ?>
        <tr>
          <td><?= htmlspecialchars((string) ($w["word"] ?? ""), ENT_QUOTES) ?></td>
          <td><?= (int) ($w["correct"] ?? 0) ?></td>
          <td><?= (int) ($w["wrong"] ?? 0) ?></td>
          <td><?= htmlspecialchars((string) ($w["accuracy"] ?? 0), ENT_QUOTES) ?>%</td>
          <td><?= htmlspecialchars((string) ($w["interval"] ?? 0), ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars((string) ($w["level"] ?? ""), ENT_QUOTES) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
