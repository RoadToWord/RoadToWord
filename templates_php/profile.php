<?php declare(strict_types=1); ?>
<?php
$profilePath = (string) ($u["profile_image"] ?? "");
if ($profilePath !== "" && !str_starts_with($profilePath, "uploads/")) {
    $profilePath = "uploads/" . ltrim($profilePath, "/");
}
?>
<div class="card portfolio-card">
  <div class="portfolio-header">
    <div class="portfolio-identity">
      <?php if ($profilePath !== ""): ?>
        <img class="portfolio-avatar<?php if (!empty($profile_frame_style) && $profile_frame_style !== "default") { echo " profile-frame-" . htmlspecialchars((string) $profile_frame_style, ENT_QUOTES); } ?>"
             src="/static/<?= htmlspecialchars($profilePath, ENT_QUOTES) ?>" alt="Profile image">
      <?php else: ?>
        <div class="portfolio-avatar placeholder<?php if (!empty($profile_frame_style) && $profile_frame_style !== "default") { echo " profile-frame-" . htmlspecialchars((string) $profile_frame_style, ENT_QUOTES); } ?>">
          <?= htmlspecialchars(substr((string) (($u["full_name"] ?? $u["username"] ?? "?")), 0, 1), ENT_QUOTES) ?>
        </div>
      <?php endif; ?>
      <div class="portfolio-title">
        <h2 class="card-title<?php if (!empty($name_glow_enabled)) { echo " name-glow"; } ?>">
          @<?= htmlspecialchars((string) ($u["username"] ?? ""), ENT_QUOTES) ?>
          <?php $vb = strtolower((string) ($u["verified_badge"] ?? "")); ?>
          <?php if (in_array($vb, ["blue", "gold"], true)): ?>
            <img class="verify-badge-icon" src="/static/<?= htmlspecialchars($vb, ENT_QUOTES) ?>.gif" alt="Verified">
          <?php endif; ?>
        </h2>
        <?php if (!empty($u["full_name"])): ?><p class="muted-text"><?= htmlspecialchars((string) $u["full_name"], ENT_QUOTES) ?></p><?php endif; ?>
      </div>
    </div>
    <div class="portfolio-badges">
      <span class="role-pill"><?= htmlspecialchars((string) ($u["role"] ?? "student"), ENT_QUOTES) ?></span>
      <?php if (!empty($road_balance_text)): ?>
        <span class="pill subtle"><?= htmlspecialchars((string) $road_balance_text, ENT_QUOTES) ?></span>
      <?php endif; ?>
      <?php if (!empty($badge)): ?>
        <span class="badge-pill badge-portfolio">
          <span class="badge-icon" aria-hidden="true"></span>
          <?= htmlspecialchars((string) $badge, ENT_QUOTES) ?>
        </span>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($report)): ?>
    <div class="streak-card<?php if (!empty($report["daily_broke"])) { echo " streak-card--broke"; } ?>">
        <div>
            <div class="streak-eyebrow">I'm on a</div>
            <div class="streak-count"><?= (int) ($report["daily_streak"] ?? 0) ?></div>
            <div class="streak-sub">day learning streak!</div>
            <div class="streak-message"><?= htmlspecialchars((string) ($report["daily_message"] ?? ""), ENT_QUOTES) ?></div>
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
    <?php if (!empty($daily_missions)): ?>
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
    <div class="portfolio-grid">
      <div class="portfolio-metric">
        <span class="metric-label">Accuracy</span>
        <span class="metric-value"><?= htmlspecialchars((string) ($report["accuracy"] ?? 0), ENT_QUOTES) ?>%</span>
      </div>
      <div class="portfolio-metric">
        <span class="metric-label">Total correct</span>
        <span class="metric-value"><?= (int) ($report["total_correct"] ?? 0) ?></span>
      </div>
      <div class="portfolio-metric">
        <span class="metric-label">Total wrong</span>
        <span class="metric-value"><?= (int) ($report["total_wrong"] ?? 0) ?></span>
      </div>
      <div class="portfolio-metric">
        <span class="metric-label">Streak</span>
        <span class="metric-value"><?= (int) ($report["streak"] ?? 0) ?> (best <?= (int) ($report["best_streak"] ?? 0) ?>)</span>
      </div>
      <div class="portfolio-metric">
        <span class="metric-label">Daily goal</span>
        <span class="metric-value"><?= (int) ($report["daily_done"] ?? 0) ?>/<?= (int) ($report["daily_target"] ?? 0) ?></span>
      </div>
      <div class="portfolio-metric">
        <span class="metric-label">Daily streak</span>
        <span class="metric-value"><?= (int) ($report["daily_streak"] ?? 0) ?> (best <?= (int) ($report["best_daily_streak"] ?? 0) ?>)</span>
      </div>
    </div>
  <?php else: ?>
    <p class="muted-text">Public portfolio.</p>
  <?php endif; ?>
</div>
