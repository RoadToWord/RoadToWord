<?php declare(strict_types=1); ?>
<section class="card" style="padding:24px;">
  <div class="card-header-row">
    <h2 class="card-title">Chat with @<?= htmlspecialchars((string) ($peer["username"] ?? ""), ENT_QUOTES) ?></h2>
    <div style="display:flex; gap:8px;">
      <?php if (!empty($call_url)): ?>
        <a class="btn btn-primary" href="<?= htmlspecialchars((string) $call_url, ENT_QUOTES) ?>">Call</a>
      <?php endif; ?>
      <a class="btn btn-secondary" href="/friends">Back</a>
    </div>
  </div>
  <?php if (!empty($error)): ?><p class="error-text"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></p><?php endif; ?>

  <div class="chat-box">
    <?php if (!empty($messages)): ?>
      <?php foreach ($messages as $m): ?>
        <div class="chat-row <?= !empty($m["from_me"]) ? "me" : "them" ?>">
          <?php $bubbleClass = (!empty($m["from_me"]) && !empty($chat_bubble_style) && $chat_bubble_style !== "default") ? " chat-bubble-" . htmlspecialchars((string) $chat_bubble_style, ENT_QUOTES) : ""; ?>
          <div class="chat-bubble<?= $bubbleClass ?>"><?= htmlspecialchars((string) ($m["content"] ?? ""), ENT_QUOTES) ?></div>
          <?php if (!empty($m["attachment"])): ?>
            <?php if (!empty($m["attachment"]["expired"])): ?>
              <div class="muted-text" style="font-size:0.85rem;">Attachment expired</div>
            <?php else: ?>
              <a class="small-link" href="<?= htmlspecialchars((string) $m["attachment"]["url"], ENT_QUOTES) ?>">Attachment: <?= htmlspecialchars((string) $m["attachment"]["name"], ENT_QUOTES) ?></a>
            <?php endif; ?>
          <?php endif; ?>
          <div class="chat-time"><?= htmlspecialchars((string) ($m["created_at"] ?? ""), ENT_QUOTES) ?></div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="muted-text">No messages yet.</p>
    <?php endif; ?>
  </div>

  <form method="post" class="form" style="margin-top:12px;">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
    <textarea class="input chat-textarea" name="content" rows="2" placeholder="Type a message..."></textarea>
    <button class="btn btn-primary btn-full">Send</button>
  </form>
</section>
