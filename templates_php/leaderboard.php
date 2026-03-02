<?php declare(strict_types=1); ?>
<section class="card" style="padding:24px;">
  <h2 class="card-title">Leaderboard</h2>
  <table class="table" style="width:100%;">
    <thead>
      <tr><th>#</th><th>User</th><th>Badge</th><th>Road</th><th>Daily streak</th></tr>
    </thead>
    <tbody>
      <?php foreach (($rows ?? []) as $r): ?>
        <tr>
          <td><?= (int) ($r["rank"] ?? 0) ?></td>
          <td>
            <a href="/@<?= rawurlencode((string) ($r["username"] ?? "")) ?>"><?= htmlspecialchars((string) ($r["username"] ?? ""), ENT_QUOTES) ?></a>
            <?php $vb = strtolower((string) ($r["verified_badge"] ?? "")); ?>
            <?php if (in_array($vb, ["blue", "gold"], true)): ?>
              <img src="/static/<?= htmlspecialchars($vb, ENT_QUOTES) ?>.gif" alt="Verified" style="width:16px; height:16px; object-fit:contain;">
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars((string) ($r["badge"] ?? ""), ENT_QUOTES) ?></td>
          <td><?= (int) ($r["road_tokens"] ?? 0) ?></td>
          <td><?= (int) ($r["daily_streak"] ?? 0) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
