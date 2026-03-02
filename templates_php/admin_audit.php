<?php declare(strict_types=1); ?>
<section class="card" style="padding:24px;">
  <h2 class="card-title">Admin Audit</h2>
  <p class="muted-text">Recent audit events (latest 300).</p>

  <div class="table-wrapper" style="margin-top:12px;">
    <table class="table">
      <thead>
        <tr>
          <th>Time</th>
          <th>Actor</th>
          <th>Action</th>
          <th>Entity</th>
          <th>ID</th>
          <th>Details</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach (($rows ?? []) as $r): ?>
        <tr>
          <td><?= htmlspecialchars((string) ($r["created_at"] ?? ""), ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars((string) ($r["actor"] ?? "System"), ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars((string) ($r["action"] ?? ""), ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars((string) ($r["entity_type"] ?? ""), ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars((string) ($r["entity_id"] ?? ""), ENT_QUOTES) ?></td>
          <td style="max-width:360px; white-space:pre-wrap; word-break:break-word;"><?= htmlspecialchars((string) ($r["details"] ?? ""), ENT_QUOTES) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
