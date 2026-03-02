<?php declare(strict_types=1); ?>
<section class="card" style="padding:24px;">
  <div class="card-header-row">
    <h2 class="card-title">Admin Logs</h2>
    <div class="muted-text">
      <?php if ($total_logs !== null): ?>
        Total: <?= (int) $total_logs ?>
      <?php else: ?>
        <a href="/admin/logs?count=1&limit=<?= (int) ($limit ?? 50) ?>" class="nav-link">Compute total count</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="card" style="padding:14px; margin-top:10px; margin-bottom:10px;">
    <form method="get" class="form" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin:0;">
      <label for="lookup_ip" class="muted-text">IP lookup</label>
      <input id="lookup_ip" name="lookup_ip" class="input" placeholder="8.8.8.8 or IPv6" value="<?= htmlspecialchars((string) ($lookup_ip ?? ""), ENT_QUOTES) ?>" style="max-width:260px;">
      <input type="hidden" name="limit" value="<?= (int) ($limit ?? 50) ?>">
      <?php if (!empty($before_id)): ?>
        <input type="hidden" name="before_id" value="<?= (int) $before_id ?>">
      <?php endif; ?>
      <?php if ($total_logs !== null): ?>
        <input type="hidden" name="count" value="1">
      <?php endif; ?>
      <button type="submit" class="btn btn-secondary">Lookup</button>
      <?php if (!empty($lookup_ip)): ?>
        <a href="/admin/logs?limit=<?= (int) ($limit ?? 50) ?><?= $total_logs !== null ? '&count=1' : '' ?>" class="btn btn-secondary">Clear</a>
      <?php endif; ?>
    </form>
    <?php if (!empty($lookup_error)): ?>
      <p class="error-text" style="margin-top:8px;"><?= htmlspecialchars((string) $lookup_error, ENT_QUOTES) ?></p>
    <?php endif; ?>
  </div>

  <?php if (!empty($lookup_result)): ?>
    <div class="card" style="padding:14px; margin-bottom:10px;">
      <div class="card-header-row" style="margin-bottom:8px;">
        <h3 class="card-title" style="margin:0; font-size:1.05rem;">IP Lookup Result</h3>
        <span class="pill subtle"><?= htmlspecialchars((string) ($lookup_result["ip"] ?? ""), ENT_QUOTES) ?></span>
      </div>
      <div class="mini-share" style="flex-wrap:wrap; gap:8px;">
        <?php if (!empty($lookup_result["flag_url"])): ?>
          <span class="pill subtle" style="display:flex; align-items:center; gap:6px;">
            <img src="<?= htmlspecialchars((string) $lookup_result["flag_url"], ENT_QUOTES) ?>" alt="Flag" style="width:20px; height:15px; border-radius:3px;">
            <?= htmlspecialchars((string) (($lookup_result["country_code"] ?? "") ?: "Country"), ENT_QUOTES) ?>
          </span>
        <?php endif; ?>
        <span class="pill subtle">Country: <?= htmlspecialchars((string) (($lookup_result["country"] ?? "") ?: "Unknown"), ENT_QUOTES) ?></span>
        <span class="pill subtle">City: <?= htmlspecialchars((string) (($lookup_result["city"] ?? "") ?: "Unknown"), ENT_QUOTES) ?></span>
        <span class="pill subtle">ASN/Org: <?= htmlspecialchars((string) (($lookup_result["asn_org"] ?? "") ?: "Unknown"), ENT_QUOTES) ?></span>
        <span class="pill subtle">Hostname: <?= htmlspecialchars((string) (($lookup_result["hostname"] ?? "") ?: "Not resolved"), ENT_QUOTES) ?></span>
        <span class="pill">Log entries: <?= $lookup_total !== null ? (int) $lookup_total : count((array) ($lookup_rows ?? [])) ?></span>
      </div>

      <?php if (!empty($lookup_rows)): ?>
        <div class="table-wrapper" style="margin-top:10px;">
          <table class="table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Time</th>
                <th>Path</th>
                <th>Method</th>
                <th>Status</th>
                <th>Referrer</th>
                <th>User Agent</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach (($lookup_rows ?? []) as $r): ?>
              <tr>
                <td><?= (int) ($r["id"] ?? 0) ?></td>
                <td><?= htmlspecialchars((string) ($r["created_at"] ?? ""), ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars((string) (($r["path"] ?? "") ?: "Unknown"), ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars((string) (($r["method"] ?? "") ?: "Unknown"), ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars((string) (($r["status_code"] ?? "") ?: "Unknown"), ENT_QUOTES) ?></td>
                <td style="max-width:220px; word-break:break-word;"><?= htmlspecialchars((string) (($r["referrer"] ?? "") ?: "no referrer"), ENT_QUOTES) ?></td>
                <td style="max-width:320px; word-break:break-word;"><?= htmlspecialchars((string) (($r["user_agent"] ?? "") ?: "Unknown"), ENT_QUOTES) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="muted-text" style="margin-top:8px;">No logs found for this IP.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <form method="get" class="form" style="margin-top:10px; margin-bottom:10px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
    <label for="limit" class="muted-text">Limit</label>
    <input id="limit" name="limit" type="number" min="10" max="200" value="<?= (int) ($limit ?? 50) ?>" class="input" style="width:100px;">
    <?php if (!empty($before_id)): ?>
      <input type="hidden" name="before_id" value="<?= (int) $before_id ?>">
    <?php endif; ?>
    <button type="submit" class="btn btn-secondary">Apply</button>
  </form>

  <div class="table-wrapper">
    <table class="table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Time</th>
          <th>IP</th>
          <th>Path</th>
          <th>Method</th>
          <th>Status</th>
          <th>Referrer</th>
          <th>User Agent</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach (($rows ?? []) as $r): ?>
        <tr>
          <td><?= (int) ($r["id"] ?? 0) ?></td>
          <td><?= htmlspecialchars((string) ($r["created_at"] ?? ""), ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars((string) (($r["ip_address"] ?? "") ?: "Unknown"), ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars((string) (($r["path"] ?? "") ?: "Unknown"), ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars((string) (($r["method"] ?? "") ?: "Unknown"), ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars((string) (($r["status_code"] ?? "") ?: "Unknown"), ENT_QUOTES) ?></td>
          <td style="max-width:220px; word-break:break-word;"><?= htmlspecialchars((string) (($r["referrer"] ?? "") ?: "no referrer"), ENT_QUOTES) ?></td>
          <td style="max-width:320px; word-break:break-word;"><?= htmlspecialchars((string) (($r["user_agent"] ?? "") ?: "Unknown"), ENT_QUOTES) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if (!empty($next_before_id)): ?>
    <div style="margin-top:12px;">
      <a class="btn btn-primary" href="/admin/logs?limit=<?= (int) ($limit ?? 50) ?>&before_id=<?= (int) $next_before_id ?>">Load older logs</a>
    </div>
  <?php endif; ?>
</section>
