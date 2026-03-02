<?php declare(strict_types=1); ?>
<section class="card" style="padding:24px;">
  <h2 class="card-title"><?= htmlspecialchars((string) ($w["keyword"] ?? ""), ENT_QUOTES) ?></h2>
  <p><strong>Slug:</strong> <?= htmlspecialchars((string) ($w["slug"] ?? ""), ENT_QUOTES) ?></p>
  <?php if (!empty($w["description"])): ?><p><strong>Description:</strong> <?= htmlspecialchars((string) ($w["description"] ?? ""), ENT_QUOTES) ?></p><?php endif; ?>
</section>
