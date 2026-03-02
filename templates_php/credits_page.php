<?php declare(strict_types=1); ?>
<section class="card" style="padding:24px;">
  <h2 class="card-title">Credits</h2>
  <pre style="white-space:pre-wrap; word-break:break-word;"><?php
    $p = BASE_DIR . "/static/credits.txt";
    echo htmlspecialchars(is_file($p) ? (string) file_get_contents($p) : "credits.txt not found", ENT_QUOTES);
  ?></pre>
</section>
