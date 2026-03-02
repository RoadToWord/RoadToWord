<?php declare(strict_types=1); ?>
<section class="card" style="padding:24px;">
  <h2 class="card-title">Admin Users</h2>
  <p class="muted-text">Manage badges and Road grants.</p>

  <div class="table-wrapper" style="margin-top:12px;">
    <table class="table">
      <thead>
        <tr>
          <th>ID</th>
          <th>User</th>
          <th>Role</th>
          <th>Road</th>
          <th>Badge</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach (($rows ?? []) as $u): ?>
        <?php $vb = strtolower((string) ($u["verified_badge"] ?? "")); ?>
        <tr>
          <td><?= (int) ($u["id"] ?? 0) ?></td>
          <td>
            <a href="/@<?= rawurlencode((string) ($u["username"] ?? "")) ?>">@<?= htmlspecialchars((string) ($u["username"] ?? ""), ENT_QUOTES) ?></a>
            <?php if (in_array($vb, ["blue", "gold"], true)): ?>
              <img src="/static/<?= htmlspecialchars($vb, ENT_QUOTES) ?>.gif" alt="Verified" style="width:16px; height:16px; vertical-align:middle;">
            <?php endif; ?>
            <div class="muted-text"><?= htmlspecialchars((string) ($u["full_name"] ?? ""), ENT_QUOTES) ?></div>
          </td>
          <td><?= htmlspecialchars((string) ($u["role"] ?? ""), ENT_QUOTES) ?></td>
          <td><?= (int) ($u["road_tokens"] ?? 0) ?></td>
          <td><?= htmlspecialchars((string) (($u["verified_badge"] ?? "") ?: "none"), ENT_QUOTES) ?></td>
          <td>
            <?php if (!empty($is_founder)): ?>
              <form method="post" action="/admin/users/<?= (int) $u["id"] ?>/verify" style="display:inline-block; margin-right:8px;">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
                <select name="verified_badge" class="input" style="width:auto; display:inline-block; padding:4px 8px;">
                  <option value="none">No badge</option>
                  <option value="blue" <?= $vb === "blue" ? "selected" : "" ?>>Blue</option>
                  <option value="gold" <?= $vb === "gold" ? "selected" : "" ?>>Gold</option>
                </select>
                <button type="submit" class="btn btn-secondary">Set</button>
              </form>

              <?php if ((string) ($u["role"] ?? "") === "student"): ?>
                <form method="post" action="/admin/users/<?= (int) $u["id"] ?>/grant-road" style="display:inline-block;">
                  <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
                  <input type="number" min="1" max="1000000" name="road_amount" value="100" class="input" style="width:100px; display:inline-block; padding:4px 8px;">
                  <button type="submit" class="btn btn-primary">Grant Road</button>
                </form>
              <?php endif; ?>
            <?php else: ?>
              <span class="muted-text">Founder-only actions</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
