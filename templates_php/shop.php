<?php declare(strict_types=1); ?>
<div class="card">
  <div class="card-header-row">
    <h2 class="card-title">Shop</h2>
    <span class="pill subtle"><?= htmlspecialchars((string) ($road_balance_text ?? ""), ENT_QUOTES) ?></span>
  </div>

  <?php if (!empty($message)): ?><p class="result-text"><?= htmlspecialchars((string) $message, ENT_QUOTES) ?></p><?php endif; ?>
  <?php if (!empty($error)): ?><p class="error-text"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></p><?php endif; ?>

  <?php if (!empty($is_student)): ?>
    <p class="muted-text">Streak freezes: <?= (int) ($streak_freezes ?? 0) ?></p>
  <?php else: ?>
    <p class="muted-text">Shop purchases are available for learner accounts.</p>
  <?php endif; ?>

  <div style="display:grid; gap:12px; margin-top:14px;">
    <?php foreach (($products ?? []) as $item): ?>
      <div class="card" style="margin:0;">
        <div class="card-header-row">
          <h3 class="card-title" style="margin:0; font-size:1.2rem;"><?= htmlspecialchars((string) ($item["name"] ?? ""), ENT_QUOTES) ?></h3>
          <span class="pill"><?= (int) ($item["cost"] ?? 0) ?> <?= ((int) ($item["cost"] ?? 0) === 1) ? "Road" : "Roads" ?></span>
        </div>
        <p class="muted-text"><?= htmlspecialchars((string) ($item["desc"] ?? ""), ENT_QUOTES) ?></p>
        <?php if (($item["id"] ?? "") === "name_glow" && !empty($name_glow_enabled)): ?>
          <p class="muted-text">Status: Active</p>
        <?php endif; ?>
        <?php if (($item["id"] ?? "") === "theme_pack" && !empty($theme_pack_owned)): ?>
          <p class="muted-text">Status: Unlocked (<?= htmlspecialchars(ucfirst((string) ($preferred_theme ?? "default")), ENT_QUOTES) ?>)</p>
        <?php endif; ?>
        <?php if (($item["id"] ?? "") === "frame_cyan" && in_array("cyan", $owned_profile_frames ?? [], true)): ?>
          <p class="muted-text">Status: Owned<?php if (($profile_frame_style ?? "") === "cyan") { echo " (Equipped)"; } ?></p>
        <?php endif; ?>
        <?php if (($item["id"] ?? "") === "frame_gold" && in_array("gold", $owned_profile_frames ?? [], true)): ?>
          <p class="muted-text">Status: Owned<?php if (($profile_frame_style ?? "") === "gold") { echo " (Equipped)"; } ?></p>
        <?php endif; ?>
        <?php if (($item["id"] ?? "") === "bubble_ocean" && in_array("ocean", $owned_chat_bubbles ?? [], true)): ?>
          <p class="muted-text">Status: Owned<?php if (($chat_bubble_style ?? "") === "ocean") { echo " (Equipped)"; } ?></p>
        <?php endif; ?>
        <?php if (($item["id"] ?? "") === "bubble_sunset" && in_array("sunset", $owned_chat_bubbles ?? [], true)): ?>
          <p class="muted-text">Status: Owned<?php if (($chat_bubble_style ?? "") === "sunset") { echo " (Equipped)"; } ?></p>
        <?php endif; ?>
        <?php if (!empty($item["active"]) && !empty($is_student)): ?>
          <form method="post" class="form" style="margin-top:8px;">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
            <input type="hidden" name="item" value="<?= htmlspecialchars((string) $item["id"], ENT_QUOTES) ?>">
            <button class="btn btn-primary">Buy</button>
          </form>
        <?php elseif (!empty($item["active"])): ?>
          <button class="btn btn-secondary" disabled>Login as learner to buy</button>
        <?php else: ?>
          <button class="btn btn-secondary" disabled>Coming soon</button>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if (!empty($is_student) && !empty($theme_pack_owned)): ?>
    <div class="card" style="margin-top:12px;">
      <h3 class="card-title" style="margin:0 0 8px 0; font-size:1.2rem;">Equip Theme</h3>
      <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <?php foreach (["default", "aurora", "ocean"] as $theme_name): ?>
          <form method="post" class="form" style="margin:0;">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
            <input type="hidden" name="item" value="equip_theme:<?= htmlspecialchars($theme_name, ENT_QUOTES) ?>">
            <button class="btn <?= ($preferred_theme ?? "default") === $theme_name ? "btn-primary" : "btn-secondary" ?>"><?= htmlspecialchars(ucfirst($theme_name), ENT_QUOTES) ?></button>
          </form>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!empty($is_student)): ?>
    <div class="card" style="margin-top:12px;">
      <h3 class="card-title" style="margin:0 0 8px 0; font-size:1.2rem;">Equip Profile Frame</h3>
      <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <?php foreach (($owned_profile_frames ?? ["default"]) as $frame_name): ?>
          <form method="post" class="form" style="margin:0;">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
            <input type="hidden" name="item" value="equip_frame:<?= htmlspecialchars((string) $frame_name, ENT_QUOTES) ?>">
            <button class="btn <?= ($profile_frame_style ?? "default") === $frame_name ? "btn-primary" : "btn-secondary" ?>"><?= htmlspecialchars(ucfirst((string) $frame_name), ENT_QUOTES) ?></button>
          </form>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card" style="margin-top:12px;">
      <h3 class="card-title" style="margin:0 0 8px 0; font-size:1.2rem;">Equip Chat Bubble</h3>
      <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <?php foreach (($owned_chat_bubbles ?? ["default"]) as $bubble_name): ?>
          <form method="post" class="form" style="margin:0;">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
            <input type="hidden" name="item" value="equip_bubble:<?= htmlspecialchars((string) $bubble_name, ENT_QUOTES) ?>">
            <button class="btn <?= ($chat_bubble_style ?? "default") === $bubble_name ? "btn-primary" : "btn-secondary" ?>"><?= htmlspecialchars(ucfirst((string) $bubble_name), ENT_QUOTES) ?></button>
          </form>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
