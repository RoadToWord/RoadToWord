<?php declare(strict_types=1); ?>
<section class="card" style="padding:24px;">
  <div class="card-header-row">
    <h2 class="card-title">Friends</h2>
    <a class="btn btn-secondary" href="/users">Find Users</a>
  </div>

  <?php if (!empty($friends)): ?>
    <div class="table-wrapper" style="margin-top:12px;">
      <table class="table">
        <thead><tr><th>Username</th><th>Full name</th><th>Role</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($friends as $f): ?>
          <tr>
            <td>
              <a class="nav-link" href="/@<?= rawurlencode((string) $f["username"]) ?>">@<?= htmlspecialchars((string) $f["username"], ENT_QUOTES) ?></a>
              <?php $vb = strtolower((string) ($f["verified_badge"] ?? "")); ?>
              <?php if (in_array($vb, ["blue", "gold"], true)): ?>
                <img src="/static/<?= htmlspecialchars($vb, ENT_QUOTES) ?>.gif" alt="Verified" style="width:16px; height:16px; vertical-align:middle;">
              <?php endif; ?>
              <?php if (!empty($f["special_badge_label"])): ?>
                <div class="muted-text" style="font-size:.85rem;"><?= htmlspecialchars((string) $f["special_badge_label"], ENT_QUOTES) ?></div>
              <?php endif; ?>
              <?php if (!empty($f["badge"])): ?>
                <div class="muted-text" style="font-size:.85rem;"><?= htmlspecialchars((string) $f["badge"], ENT_QUOTES) ?></div>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars((string) ($f["full_name"] ?? ""), ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars((string) ($f["role"] ?? ""), ENT_QUOTES) ?></td>
            <td>
              <a class="nav-link" href="/messages/<?= rawurlencode((string) $f["username"]) ?>">Chat</a>
              <form method="post" action="/friends/remove" style="display:inline;">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
                <input type="hidden" name="username" value="<?= htmlspecialchars((string) $f["username"], ENT_QUOTES) ?>">
                <button class="btn btn-danger" type="submit">Remove</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="muted-text" style="margin-top:10px;">No friends yet.</p>
  <?php endif; ?>

  <?php if (!empty($incoming)): ?>
    <h3 class="page-title" style="margin-top:16px;">Incoming Requests</h3>
    <?php foreach ($incoming as $r): ?>
      <div class="card" style="margin-top:8px;">
        <p><strong>@<?= htmlspecialchars((string) $r["username"], ENT_QUOTES) ?></strong> sent you a friend request.</p>
        <form method="post" action="/friends/accept" style="display:inline;">
          <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
          <input type="hidden" name="request_id" value="<?= (int) $r["id"] ?>">
          <button class="btn btn-primary" type="submit">Accept</button>
        </form>
        <form method="post" action="/friends/decline" style="display:inline;">
          <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
          <input type="hidden" name="request_id" value="<?= (int) $r["id"] ?>">
          <button class="btn btn-secondary" type="submit">Decline</button>
        </form>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if (!empty($outgoing)): ?>
    <h3 class="page-title" style="margin-top:16px;">Outgoing Requests</h3>
    <?php foreach ($outgoing as $r): ?>
      <div class="card" style="margin-top:8px;">
        <p>Pending request to <strong>@<?= htmlspecialchars((string) $r["username"], ENT_QUOTES) ?></strong></p>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>
