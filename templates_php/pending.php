<?php declare(strict_types=1); ?>
<section class="card" style="padding:24px;">
  <h2 class="card-title">Route Pending</h2>
  <p class="muted-text">This route is queued in PHP parity migration:</p>
  <p><code><?= htmlspecialchars((string) ($route ?? ""), ENT_QUOTES) ?></code></p>
</section>

