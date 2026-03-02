<?php declare(strict_types=1); ?>
<section class="card" style="padding:24px; text-align:center;">
  <h2 class="card-title">Private Profile</h2>
  <p class="muted-text">@<?= htmlspecialchars((string) ($username ?? ""), ENT_QUOTES) ?> has a private profile.</p>
  <p class="muted-text">Only the owner and admins can view this page.</p>
  <a class="btn btn-secondary" href="/users">Back to Users</a>
</section>
