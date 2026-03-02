<?php declare(strict_types=1); ?>
<section class="card" style="padding:24px; max-width:720px; margin:auto;">
  <h2 class="card-title">Rabb-it</h2>
  <p class="muted-text">A tiny easter egg. Feed Rabb-it once per day to earn <strong>+2 Roads</strong>.</p>

  <?php if (empty($user)): ?>
    <p class="muted-text">Login required to feed Rabb-it.</p>
    <a class="btn btn-secondary" href="/login">Login</a>
  <?php else: ?>
    <p class="muted-text">
      Current balance:
      <strong><?= (int) (($meta["road_tokens"] ?? 0)) ?> Road<?= ((int) (($meta["road_tokens"] ?? 0)) === 1 ? "" : "s") ?></strong>
    </p>
    <button id="rabbitFeedBtn" class="btn <?= !empty($already_fed) ? "btn-secondary" : "btn-primary" ?>" <?= !empty($already_fed) ? "disabled" : "" ?>>
      <?= !empty($already_fed) ? "Already fed today" : "Feed Rabb-it" ?>
    </button>
    <p id="rabbitFeedResult" class="result-text" style="margin-top:10px;"></p>

    <script nonce="<?= htmlspecialchars((string) ($csp_nonce ?? ""), ENT_QUOTES) ?>">
      (function () {
        var btn = document.getElementById('rabbitFeedBtn');
        var out = document.getElementById('rabbitFeedResult');
        if (!btn || btn.disabled) return;
        btn.addEventListener('click', function () {
          btn.disabled = true;
          fetch('/api/rabbit-feed', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
            },
            body: '_csrf_token=' + encodeURIComponent('<?= htmlspecialchars((string) App\Security::csrfToken(), ENT_QUOTES) ?>')
          }).then(function (r) { return r.json(); })
            .then(function (data) {
              out.textContent = (data && data.message) ? data.message : 'Done.';
              if (!(data && data.ok)) {
                btn.disabled = false;
              } else {
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-secondary');
                btn.textContent = 'Already fed today';
              }
            })
            .catch(function () {
              out.textContent = 'Network error.';
              btn.disabled = false;
            });
        });
      })();
    </script>
  <?php endif; ?>
</section>
