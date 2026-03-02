<?php declare(strict_types=1); ?>
<?php
$role = (string) ($user["role"] ?? "");
$roleLabel = $role === "admin" ? "Admin" : ($role === "teacher" ? "Teacher" : "Learner");
$profilePath = (string) ($user["profile_image"] ?? "");
if ($profilePath !== "" && !str_starts_with($profilePath, "uploads/")) {
    $profilePath = "uploads/" . ltrim($profilePath, "/");
}
$referralUrl = "https://" . ($_SERVER["HTTP_HOST"] ?? "localhost") . "/?ref=" . rawurlencode((string) ($user["username"] ?? ""));
?>
<div class="card">
  <div class="card-header-row">
    <h2 class="card-title">Account</h2>
    <span class="pill subtle"><?= htmlspecialchars($roleLabel, ENT_QUOTES) ?></span>
  </div>
  <?php if (!empty($message)): ?><p class="result-text"><?= htmlspecialchars((string) $message, ENT_QUOTES) ?></p><?php endif; ?>
  <?php if (!empty($error)): ?><p class="error-text"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></p><?php endif; ?>

  <div class="account-header">
    <div class="account-avatar<?php if (!empty($profile_frame_style) && $profile_frame_style !== "default") { echo " profile-frame-" . htmlspecialchars((string) $profile_frame_style, ENT_QUOTES); } ?>">
      <?php if ($profilePath !== ""): ?>
        <img src="/static/<?= htmlspecialchars($profilePath, ENT_QUOTES) ?>" alt="Profile photo">
      <?php else: ?>
        <div class="avatar-fallback"><?= htmlspecialchars(strtoupper(substr((string) ($user["username"] ?? "?"), 0, 1)), ENT_QUOTES) ?></div>
      <?php endif; ?>
    </div>
    <div class="account-summary">
      <p class="account-name<?php if (!empty($name_glow_enabled)) { echo " name-glow"; } ?>">
        <?= htmlspecialchars((string) ($user["full_name"] ?? ($user["username"] ?? "")), ENT_QUOTES) ?>
        <?php $vb = strtolower((string) ($user["verified_badge"] ?? "")); ?>
        <?php if (in_array($vb, ["blue", "gold"], true)): ?>
          <img class="verify-badge-icon" src="/static/<?= htmlspecialchars($vb, ENT_QUOTES) ?>.gif" alt="Verified">
        <?php endif; ?>
      </p>
      <p class="muted-text"><?= htmlspecialchars((string) (($user["email"] ?? "") !== "" ? $user["email"] : "No email on file"), ENT_QUOTES) ?></p>
    </div>
  </div>

  <?php if (!empty($meta)): ?>
  <div class="streak-card<?php if (!empty($streak_ctx["broke"])) { echo " streak-card--broke"; } ?>">
      <div>
          <div class="streak-eyebrow">I'm on a</div>
          <div class="streak-count"><?= (int) ($meta["daily_streak"] ?? 0) ?></div>
          <div class="streak-sub">day learning streak!</div>
          <div class="streak-message"><?= htmlspecialchars((string) ($streak_ctx["message"] ?? ""), ENT_QUOTES) ?></div>
      </div>
  </div>

  <?php if (!empty($streak_calendar)): ?>
  <div class="streak-calendar">
    <?php foreach ($streak_calendar as $day): ?>
      <div class="streak-day streak-<?= htmlspecialchars((string) ($day["status"] ?? ""), ENT_QUOTES) ?>"
           title="<?= htmlspecialchars((string) ($day["date"] ?? ""), ENT_QUOTES) ?>: <?= htmlspecialchars((string) ($day["label"] ?? ""), ENT_QUOTES) ?>"
           data-label="<?= htmlspecialchars((string) ($day["date"] ?? ""), ENT_QUOTES) ?>: <?= htmlspecialchars((string) ($day["label"] ?? ""), ENT_QUOTES) ?>">
        <span class="streak-day-number"><?= htmlspecialchars((string) substr((string) ($day["date"] ?? ""), -2), ENT_QUOTES) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($cefr_progress)): ?>
  <div class="cefr-map">
    <?php foreach ($cefr_progress as $row): ?>
      <div class="cefr-level cefr-<?= htmlspecialchars((string) ($row["status"] ?? ""), ENT_QUOTES) ?>">
        <div class="cefr-header">
          <span class="cefr-title"><?= htmlspecialchars((string) ($row["level"] ?? ""), ENT_QUOTES) ?></span>
          <span class="cefr-count"><?= (int) ($row["mastered"] ?? 0) ?>/<?= (int) ($row["total"] ?? 0) ?></span>
        </div>
        <div class="cefr-bar">
          <div class="cefr-bar-fill" style="width: <?= htmlspecialchars((string) ($row["pct"] ?? 0), ENT_QUOTES) ?>%;"></div>
        </div>
        <div class="cefr-pct"><?= htmlspecialchars((string) ($row["pct"] ?? 0), ENT_QUOTES) ?>%</div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($streak_freezes !== null): ?>
    <p class="muted-text">Streak freezes left: <?= (int) $streak_freezes ?></p>
  <?php endif; ?>
  <?php if (!empty($road_balance_text)): ?>
    <p class="muted-text">Balance: <?= htmlspecialchars((string) $road_balance_text, ENT_QUOTES) ?></p>
    <p class="muted-text">
      Referral link:
      <a class="small-link js-copy-referral" href="#" data-referral-url="<?= htmlspecialchars($referralUrl, ENT_QUOTES) ?>">Click to copy</a>
    </p>
    <p class="muted-text">Name Glow: <?= !empty($name_glow_enabled) ? "Active" : "Inactive" ?></p>
    <p class="muted-text">Theme Pack: <?= !empty($theme_pack_owned) ? "Unlocked" : "Locked" ?><?php if (!empty($theme_pack_owned)): ?> (<?= htmlspecialchars(ucfirst((string) $preferred_theme), ENT_QUOTES) ?>)<?php endif; ?></p>
    <a class="btn btn-secondary" href="/shop">Go to Shop</a>
  <?php endif; ?>

  <?php if (!empty($daily_missions)): ?>
  <hr>
  <h3 class="card-title">Daily Missions</h3>
  <?php foreach ($daily_missions as $m): ?>
    <p class="muted-text">
      <?= htmlspecialchars((string) ($m["label"] ?? ""), ENT_QUOTES) ?>:
      <?= (int) ($m["progress"] ?? 0) ?>/<?= (int) ($m["target"] ?? 0) ?>
      <?php if (!empty($m["completed"])): ?>(Completed +<?= (int) ($m["reward"] ?? 0) ?> Road)<?php else: ?>(+<?= (int) ($m["reward"] ?? 0) ?> Road)<?php endif; ?>
    </p>
  <?php endforeach; ?>
  <?php endif; ?>

  <?php if (!empty($weekly_progress)): ?>
    <p class="muted-text">
      Weekly Challenge: <?= (int) ($weekly_progress["count"] ?? 0) ?>/<?= (int) ($weekly_progress["target"] ?? 0) ?> questions
      <?php if (!empty($weekly_progress["done"])): ?>(Completed +<?= (int) ($weekly_progress["reward"] ?? 0) ?> Road)<?php else: ?>(+<?= (int) ($weekly_progress["reward"] ?? 0) ?> Road)<?php endif; ?>
    </p>
  <?php endif; ?>

  <?php if (!empty($achievements)): ?>
    <h3 class="card-title">Achievements</h3>
    <div class="mini-share" style="flex-wrap:wrap; gap:6px;">
      <?php foreach ($achievements as $a): ?>
        <span class="pill"><?= htmlspecialchars((string) $a, ENT_QUOTES) ?></span>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php endif; ?>

  <form method="post" class="form" enctype="multipart/form-data">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
    <label class="label">Full Name</label>
    <input class="input" name="full_name" value="<?= htmlspecialchars((string) ($user["full_name"] ?? ""), ENT_QUOTES) ?>" required>

    <label class="label">Username</label>
    <input class="input" name="username" value="<?= htmlspecialchars((string) ($user["username"] ?? ""), ENT_QUOTES) ?>" required>

    <label class="label">Email</label>
    <input class="input" name="email" type="email" value="<?= htmlspecialchars((string) ($user["email"] ?? ""), ENT_QUOTES) ?>">

    <?php $profilePublic = !array_key_exists("profile_public", $user) || (int) ($user["profile_public"] ?? 1) !== 0; ?>
    <label class="label" style="display:flex; align-items:center; gap:10px;">
      <input type="checkbox" name="profile_public" value="1" <?= $profilePublic ? "checked" : "" ?>>
      <span>Public profile (allow others to view @<?= htmlspecialchars((string) ($user["username"] ?? ""), ENT_QUOTES) ?>)</span>
    </label>

    <?php if (($user["role"] ?? "") === "student"): ?>
    <label class="label">Daily Goal</label>
    <input class="input" name="daily_target" type="number" min="1" max="200" value="<?= (int) ($meta["daily_target"] ?? 20) ?>" required>

    <label class="label">Teacher</label>
    <select class="input" name="teacher_id" required>
      <option value="">Select your teacher</option>
      <option value="individual" <?php if (empty($user["teacher_id"])) { echo "selected"; } ?>>Individual</option>
      <?php foreach (($teacher_options ?? []) as $t): ?>
        <option value="<?= (int) ($t["id"] ?? 0) ?>" <?php if (!empty($user["teacher_id"]) && (int) $user["teacher_id"] === (int) ($t["id"] ?? 0)) { echo "selected"; } ?>>
          <?= htmlspecialchars((string) (($t["full_name"] ?? "") !== "" ? $t["full_name"] : $t["username"]), ENT_QUOTES) ?> (Teacher)
        </option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>

    <label class="label">Password</label>
    <input class="input" name="password" type="password" placeholder="Leave blank to keep current">

    <label class="label">Confirm Password</label>
    <input class="input" name="confirm_password" type="password" placeholder="Repeat new password">

    <label class="label">Profile Picture</label>
    <input class="input" type="file" name="profile_image" accept="image/*">

    <button class="btn btn-primary btn-full">Update Account</button>
  </form>

  <div style="margin-top: 18px; display: flex; justify-content: flex-end;">
    <a class="btn btn-secondary" href="/coffee">Coffee</a>
  </div>

  <div style="margin-top: 12px; display: flex; justify-content: flex-start;">
    <a class="btn" href="/account/delete/confirm" style="background:#dc2626; border-color:#b91c1c; color:#fff;">Delete Account</a>
  </div>
</div>
<script nonce="<?= htmlspecialchars((string) ($csp_nonce ?? ""), ENT_QUOTES) ?>">
  (function () {
    const trigger = document.querySelector(".js-copy-referral");
    if (!trigger) return;

    function showCopiedToast() {
      const el = document.createElement("div");
      el.className = "egg-toast";
      el.textContent = "Copied link";
      document.body.appendChild(el);
      requestAnimationFrame(() => el.classList.add("show"));
      setTimeout(() => {
        el.classList.remove("show");
        setTimeout(() => el.remove(), 200);
      }, 1400);
    }

    async function copyText(text) {
      if (!text) return false;
      try {
        await navigator.clipboard.writeText(text);
        return true;
      } catch (_) {
        try {
          const ta = document.createElement("textarea");
          ta.value = text;
          ta.style.position = "fixed";
          ta.style.opacity = "0";
          document.body.appendChild(ta);
          ta.focus();
          ta.select();
          const ok = document.execCommand("copy");
          ta.remove();
          return !!ok;
        } catch (_) {
          return false;
        }
      }
    }

    trigger.addEventListener("click", async (e) => {
      e.preventDefault();
      const url = trigger.getAttribute("data-referral-url") || "";
      const ok = await copyText(url);
      if (ok) showCopiedToast();
    });
  })();
</script>
