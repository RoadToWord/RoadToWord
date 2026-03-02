<?php declare(strict_types=1); ?>
<section class="card" style="padding:24px;">
  <h2 class="card-title">Users</h2>
  <form method="get" class="form" style="margin-bottom:10px;">
    <input class="input" name="q" value="<?= htmlspecialchars((string) ($q ?? ""), ENT_QUOTES) ?>" placeholder="Search username/full name">
  </form>
  <div class="profile-grid">
  <?php foreach (($rows ?? []) as $u): ?>
    <?php
      $vb = strtolower((string) ($u["verified_badge"] ?? ""));
      $viewerName = strtolower((string) (($viewer["username"] ?? "")));
      $isOwner = $viewerName !== "" && $viewerName === strtolower((string) ($u["username"] ?? ""));
      $viewerIsAdmin = !empty($viewer) && ((string) ($viewer["role"] ?? "")) === "admin";
      $isPublic = !array_key_exists("profile_public", $u) || (int) ($u["profile_public"] ?? 1) !== 0;
      $canView = $isPublic || $isOwner || $viewerIsAdmin;
      $profilePath = (string) ($u["profile_image"] ?? "");
      if ($profilePath !== "" && !str_starts_with($profilePath, "uploads/")) {
        $profilePath = "uploads/" . ltrim($profilePath, "/");
      }
    ?>
    <div class="card profile-card">
      <div class="profile-card-top">
        <div class="profile-card-avatar">
          <?php if ($profilePath !== ""): ?>
            <img src="/static/<?= htmlspecialchars($profilePath, ENT_QUOTES) ?>" alt="Profile image">
          <?php else: ?>
            <div class="avatar-fallback"><?= htmlspecialchars(strtoupper(substr((string) ($u["username"] ?? "?"), 0, 1)), ENT_QUOTES) ?></div>
          <?php endif; ?>
        </div>
        <div class="profile-card-meta">
          <?php if ($canView): ?>
            <a class="profile-card-username" href="/@<?= rawurlencode((string) ($u["username"] ?? "")) ?>">@<?= htmlspecialchars((string) ($u["username"] ?? ""), ENT_QUOTES) ?></a>
          <?php else: ?>
            <span class="profile-card-username">@<?= htmlspecialchars((string) ($u["username"] ?? ""), ENT_QUOTES) ?></span>
          <?php endif; ?>
          <?php if (in_array($vb, ["blue", "gold"], true)): ?>
            <img src="/static/<?= htmlspecialchars($vb, ENT_QUOTES) ?>.gif" alt="Verified" class="verify-badge-icon">
          <?php endif; ?>
          <div class="profile-card-name"><?= htmlspecialchars((string) ($u["full_name"] ?? ""), ENT_QUOTES) ?></div>
          <div class="profile-card-role"><?= htmlspecialchars((string) ($u["role"] ?? ""), ENT_QUOTES) ?></div>
        </div>
      </div>
      <div class="profile-card-badges">
        <?php if (!empty($u["special_badge_label"])): ?>
          <span class="pill subtle"><?= htmlspecialchars((string) $u["special_badge_label"], ENT_QUOTES) ?></span>
        <?php endif; ?>
        <?php if (!empty($u["badge"])): ?>
          <span class="pill"><?= htmlspecialchars((string) $u["badge"], ENT_QUOTES) ?></span>
        <?php endif; ?>
        <?php if (!$isPublic && !$isOwner && !$viewerIsAdmin): ?>
          <span class="pill subtle">Private profile</span>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
</section>
