<?php declare(strict_types=1); ?>
<div class="card">
  <div class="card-header-row">
    <h2 class="card-title">Learner: <?= htmlspecialchars((string) ($student ?? ""), ENT_QUOTES) ?></h2>
    <?php if (!empty($badge)): ?>
      <span class="badge-pill">&#x1F396; <?= htmlspecialchars((string) $badge, ENT_QUOTES) ?></span>
    <?php endif; ?>
    <?php if (!empty($road_balance_text)): ?>
      <span class="pill subtle"><?= htmlspecialchars((string) $road_balance_text, ENT_QUOTES) ?></span>
    <?php endif; ?>
    <?php if (isset($lives_remaining)): ?>
      <span class="pill subtle">
        Lives: <?= (int) $lives_remaining ?>/<?= (int) ($lives_max ?? 0) ?>
        <?php if (isset($lives_reset_in)): ?>
          <?php $resetSecs = (int) ($lives_reset_in ?? 0); $resetLabel = floor($resetSecs / 60) . \":\" . str_pad((string) ($resetSecs % 60), 2, \"0\", STR_PAD_LEFT); ?>
          <span id="livesResetCountdown" class="muted-text" style="margin-left:6px;" data-seconds="<?= (int) $lives_reset_in ?>">(reset <?= htmlspecialchars($resetLabel, ENT_QUOTES) ?>)</span>
        <?php endif; ?>
      </span>
    <?php endif; ?>
    <div class="streak-chip">
      &#x1F525; Streak: <?= (int) ($streak ?? 0) ?> | Best: <?= (int) ($best_streak ?? 0) ?>
    </div>
  </div>

  <div class="mini-share" style="margin-bottom:8px;">
    <span class="pill subtle">Daily <?= (int) ($daily_done ?? 0) ?>/<?= (int) ($daily_target ?? 0) ?></span>
    <span class="pill">Daily streak <?= (int) ($daily_streak ?? 0) ?> (best <?= (int) ($best_daily_streak ?? 0) ?>)</span>
  </div>

  <?php if (!empty($streak_message)): ?>
    <div class="streak-card<?= !empty($streak_broke) ? " streak-card--broke" : "" ?>">
      <div>
        <div class="streak-eyebrow">I'm on a</div>
        <div class="streak-count"><?= (int) ($daily_streak ?? 0) ?></div>
        <div class="streak-sub">day learning streak!</div>
        <div class="streak-message"><?= htmlspecialchars((string) $streak_message, ENT_QUOTES) ?></div>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!empty($error)): ?>
    <p class="error-text"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></p>
  <?php endif; ?>

  <?php if (!empty($result)): ?>
    <p class="result-text"><strong><?= htmlspecialchars((string) $result, ENT_QUOTES) ?></strong></p>
    <?php if (isset($pronunciation_score)): ?>
      <p class="muted-text">Pronunciation score: <?= htmlspecialchars((string) $pronunciation_score, ENT_QUOTES) ?>%</p>
    <?php endif; ?>
    <a class="btn btn-secondary btn-full" href="/quiz/<?= rawurlencode((string) $student) ?>?mode=<?= rawurlencode((string) ($base_mode ?? $selected_mode ?? "turkish")) ?>">Next</a>
    <audio id="correctSound" src="/static/correct.mp3" preload="auto"></audio>
    <audio id="wrongSound" src="/static/wrong.mp3" preload="auto"></audio>
  <?php endif; ?>

  <?php if (!empty($word)): ?>
    <h3 class="question-text">
      <?= htmlspecialchars((string) ($question_label ?? "Question"), ENT_QUOTES) ?>:<br>
      <span class="question-word"><?= htmlspecialchars((string) $word, ENT_QUOTES) ?></span>
    </h3>

    <div class="grid-tts">
      <button class="btn btn-secondary btn-full js-listen-en" type="button" data-text="<?= htmlspecialchars((string) $word, ENT_QUOTES) ?>">&#x1F50A; Listen (EN)</button>
      <?php $turkishValue = is_array($extra_info ?? null) ? (string) ($extra_info["turkish"] ?? "") : (string) ($turkish_value ?? ""); ?>
      <?php if (in_array((string) ($selected_mode ?? ""), ["turkish", "typing", "timed"], true)): ?>
        <button class="btn btn-secondary btn-full" disabled>&#x1F50A; Listen (TR)</button>
      <?php else: ?>
        <button class="btn btn-secondary btn-full js-listen-tr" type="button" data-text="<?= htmlspecialchars($turkishValue, ENT_QUOTES) ?>">&#x1F50A; Listen (TR)</button>
      <?php endif; ?>
    </div>

    <button class="btn btn-primary btn-full js-speak-btn" type="button" style="margin-top:10px;">&#x1F3A4; Speak</button>
    <p id="speech-result" style="margin-top:10px; color:var(--ink);"></p>

    <?php if (($selected_mode ?? "") === "typing"): ?>
      <form method="post" class="form" id="answerForm" style="margin-top:10px;">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) ($csrf_token ?? ""), ENT_QUOTES) ?>">
        <input type="hidden" name="word" value="<?= htmlspecialchars((string) $word, ENT_QUOTES) ?>">
        <input type="hidden" name="mode" value="<?= htmlspecialchars((string) ($base_mode ?? $selected_mode ?? "turkish"), ENT_QUOTES) ?>">
        <input type="hidden" name="question_mode" value="<?= htmlspecialchars((string) ($question_mode ?? $selected_mode ?? "turkish"), ENT_QUOTES) ?>">
        <input type="hidden" id="currentMode" value="<?= htmlspecialchars((string) ($question_mode ?? $selected_mode ?? "turkish"), ENT_QUOTES) ?>">
        <input type="hidden" id="speechAnswer" name="spoken_answer" value="">
        <label class="label">Type your answer</label>
        <input class="input" name="typed_answer" placeholder="Enter the Turkish meaning" autofocus>
        <button class="btn btn-primary btn-full" style="margin-top:8px;">Submit</button>
      </form>
    <?php else: ?>
      <?php if (($selected_mode ?? "") === "timed"): ?>
        <div class="timer-banner">&#x23F1; Time left: <span id="countdown"><?= (int) ($timed_seconds ?? 0) ?></span>s</div>
      <?php endif; ?>
      <form method="post" class="options-form" id="answerForm" style="margin-top:10px;">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) ($csrf_token ?? ""), ENT_QUOTES) ?>">
        <input type="hidden" name="word" value="<?= htmlspecialchars((string) $word, ENT_QUOTES) ?>">
        <input type="hidden" name="mode" value="<?= htmlspecialchars((string) ($base_mode ?? $selected_mode ?? "turkish"), ENT_QUOTES) ?>">
        <input type="hidden" name="question_mode" value="<?= htmlspecialchars((string) ($question_mode ?? $selected_mode ?? "turkish"), ENT_QUOTES) ?>">
        <input type="hidden" id="currentMode" value="<?= htmlspecialchars((string) ($question_mode ?? $selected_mode ?? "turkish"), ENT_QUOTES) ?>">
        <input type="hidden" id="speechAnswer" name="spoken_answer" value="">

        <?php foreach (($options ?? []) as $opt): ?>
          <button name="answer" value="<?= htmlspecialchars((string) $opt, ENT_QUOTES) ?>" class="btn btn-option btn-full"><?= htmlspecialchars((string) $opt, ENT_QUOTES) ?></button>
        <?php endforeach; ?>
      </form>
    <?php endif; ?>
  <?php endif; ?>

  <?php if (!empty($extra_info) && is_array($extra_info)): ?>
    <hr>
    <h3 class="card-title">Word Details</h3>
    <p><strong>Word:</strong> <?= htmlspecialchars((string) ($extra_info["word"] ?? ""), ENT_QUOTES) ?></p>
    <p><strong>Turkish:</strong> <?= htmlspecialchars((string) ($extra_info["turkish"] ?? ""), ENT_QUOTES) ?></p>
    <?php if (!empty($extra_info["synonyms"])): ?>
      <p><strong>Synonyms:</strong> <?= htmlspecialchars(implode(", ", (array) $extra_info["synonyms"]), ENT_QUOTES) ?></p>
    <?php endif; ?>
    <?php if (!empty($extra_info["antonyms"])): ?>
      <p><strong>Antonyms:</strong> <?= htmlspecialchars(implode(", ", (array) $extra_info["antonyms"]), ENT_QUOTES) ?></p>
    <?php endif; ?>
    <?php if (!empty($extra_info["definition"])): ?>
      <p><strong>Definition:</strong> <?= htmlspecialchars((string) $extra_info["definition"], ENT_QUOTES) ?></p>
    <?php endif; ?>
    <?php if (!empty($extra_info["example_en"])): ?>
      <p><strong>Example (EN):</strong> <?= htmlspecialchars((string) $extra_info["example_en"], ENT_QUOTES) ?></p>
    <?php endif; ?>
    <?php if (!empty($extra_info["example_tr"])): ?>
      <p><strong>Example (TR):</strong> <?= htmlspecialchars((string) $extra_info["example_tr"], ENT_QUOTES) ?></p>
    <?php endif; ?>
  <?php endif; ?>
</div>

<script nonce="<?= htmlspecialchars((string) ($csp_nonce ?? ""), ENT_QUOTES) ?>">
document.addEventListener("DOMContentLoaded", function () {
  const correctSound = document.getElementById("correctSound");
  const wrongSound = document.getElementById("wrongSound");
  const resultEl = document.querySelector(".result-text");
  const speakBtn = document.querySelector(".js-speak-btn");
  const speechResult = document.getElementById("speech-result");
  const speechAnswer = document.getElementById("speechAnswer");
  const listenEnBtn = document.querySelector(".js-listen-en");
  const listenTrBtn = document.querySelector(".js-listen-tr");

  function unlockAudio() {
    [correctSound, wrongSound].forEach(function (audio) {
      if (!audio) return;
      try {
        audio.muted = true;
        const p = audio.play();
        if (p && typeof p.then === "function") {
          p.then(function () {
            audio.pause();
            audio.currentTime = 0;
            audio.muted = false;
          }).catch(function () {
            audio.muted = false;
          });
        } else {
          audio.pause();
          audio.currentTime = 0;
          audio.muted = false;
        }
      } catch (e) {}
    });
  }

  document.addEventListener("pointerdown", unlockAudio, { once: true });

  function safePlay(audio) {
    if (!audio) return;
    try {
      audio.currentTime = 0;
      const p = audio.play();
      if (p && typeof p.catch === "function") {
        p.catch(function () {});
      }
    } catch (e) {}
  }

  if (resultEl) {
    const text = (resultEl.textContent || "").trim().toLowerCase();
    if (text.startsWith("wrong") || text.startsWith("time is up")) {
      safePlay(wrongSound);
    } else if (text.startsWith("correct")) {
      safePlay(correctSound);
    }
  }

  function speakText(text, lang) {
    if (!("speechSynthesis" in window) || !text) return;
    try {
      const utterance = new SpeechSynthesisUtterance(text);
      utterance.lang = lang || "en-US";
      utterance.rate = 0.95;
      window.speechSynthesis.cancel();
      window.speechSynthesis.speak(utterance);
    } catch (e) {}
  }

  if (listenEnBtn) {
    listenEnBtn.addEventListener("click", function () {
      speakText(listenEnBtn.dataset.text || "", "en-US");
    });
  }

  if (listenTrBtn) {
    listenTrBtn.addEventListener("click", function () {
      speakText(listenTrBtn.dataset.text || "", "tr-TR");
    });
  }

  function startListening() {
    const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!Recognition) {
      if (speechResult) speechResult.textContent = "Speech recognition is not supported in this browser.";
      return;
    }
    const recognition = new Recognition();
    recognition.lang = "en-US";
    recognition.interimResults = false;
    recognition.maxAlternatives = 1;

    if (speechResult) speechResult.textContent = "Listening...";

    recognition.onresult = function (event) {
      const transcript = ((event.results && event.results[0] && event.results[0][0] && event.results[0][0].transcript) || "").trim();
      if (speechAnswer) speechAnswer.value = transcript;
      if (speechResult) speechResult.textContent = transcript ? ("You said: " + transcript) : "No speech detected.";
    };

    recognition.onerror = function (event) {
      const code = (event && event.error) ? event.error : "unknown";
      if (speechResult) speechResult.textContent = "Speech error: " + code;
    };

    recognition.onend = function () {};
    try {
      recognition.start();
    } catch (e) {
      if (speechResult) speechResult.textContent = "Speech error: start-failed";
    }
  }

  if (speakBtn) {
    speakBtn.addEventListener("click", function () {
      startListening();
    });
  }

  const countdownEl = document.getElementById("countdown");
  const livesResetEl = document.getElementById("livesResetCountdown");

  function formatMmSs(totalSeconds) {
    const safe = Math.max(0, parseInt(totalSeconds, 10) || 0);
    const mm = Math.floor(safe / 60);
    const ss = safe % 60;
    return mm + ":" + String(ss).padStart(2, "0");
  }

  if (livesResetEl) {
    let left = parseInt(livesResetEl.dataset.seconds || "0", 10);
    const tickLives = function () {
      livesResetEl.textContent = "(reset " + formatMmSs(left) + ")";
      if (left > 0) {
        left -= 1;
      }
    };
    tickLives();
    setInterval(tickLives, 1000);
  }

  if (countdownEl) {
    let timeLeft = parseInt(countdownEl.innerText, 10);
    const form = document.getElementById("answerForm");
    const timer = setInterval(function () {
      timeLeft -= 1;
      if (timeLeft <= 0) {
        countdownEl.innerText = "0";
        clearInterval(timer);
        if (form) form.submit();
      } else {
        countdownEl.innerText = String(timeLeft);
      }
    }, 1000);
  }
});
</script>
