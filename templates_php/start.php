<?php declare(strict_types=1); ?>
<?php
$welcomeIpRaw = (string) (($welcome["ip"] ?? "Unknown"));
$welcomeIpChunks = [];
if ($welcomeIpRaw !== "") {
    $welcomeIpChunks = str_split($welcomeIpRaw, 20);
}
?>
<section class="hero-screen">
  <div class="hero-center">
    <?php if (!empty($error)): ?>
      <p class="error-text"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></p>
    <?php endif; ?>

    <h1 class="hero-title">
      Hello <strong class="hero-ip-wrap"><?php foreach (($welcomeIpChunks ?: ["Unknown"]) as $i => $chunk): ?><?= $i > 0 ? "<br>" : "" ?><?= htmlspecialchars((string) $chunk, ENT_QUOTES) ?><?php endforeach; ?></strong>
    </h1>

    <p class="hero-subtitle">
      from <?= htmlspecialchars((string) (($welcome["location"] ?? "Unknown location")), ENT_QUOTES) ?>
      <?php if (!empty($welcome["flag_url"])): ?>
        <img class="hero-flag-img" src="<?= htmlspecialchars((string) $welcome["flag_url"], ENT_QUOTES) ?>" alt="Flag">
      <?php elseif (!empty($welcome["flag"])): ?>
        <span class="hero-flag"><?= htmlspecialchars((string) $welcome["flag"], ENT_QUOTES) ?></span>
      <?php endif; ?>
    </p>

    <p style="margin-top:12px;">
      <a class="btn btn-primary hero-btn" href="/?start=1">Let's begin!</a>
    </p>
  </div>
</section>
