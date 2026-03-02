<?php declare(strict_types=1);
$session = $_SESSION ?? [];
$role = $session["role"] ?? null;
$loggedIn = !empty($session["username"]);
function roleLabel($role): string {
  if (!$role) {
    return "";
  }
  if ($role === "student") {
    return "Learner";
  }
  return ucfirst((string) $role);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <!-- Author: Baran CETIN -->
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="/favicon.ico">
  <link rel="shortcut icon" href="/favicon.ico">
  <link rel="manifest" href="/manifest.json">
  <meta name="mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <meta name="application-name" content="RoadToWord" />
  <meta name="apple-mobile-web-app-title" content="RoadToWord" />
  <meta name="msapplication-starturl" content="/start" />
  <meta name="theme-color" content="#09090b" />
  <meta name="description" content="RoadToWord helps learners practice English vocabulary step by step with daily practice, streaks, and progress tracking." />
  <?php if (!empty($seo_keywords)): ?>
  <meta name="keywords" content="<?= htmlspecialchars((string) $seo_keywords, ENT_QUOTES) ?>">
  <?php endif; ?>
  <title>RoadToWord</title>
  <link rel="stylesheet" href="/static/style.css">
  <script nonce="<?= htmlspecialchars((string) ($csp_nonce ?? ""), ENT_QUOTES) ?>">
    (function() {
      document.documentElement.classList.add("dark");
    })();
  </script>
  <script nonce="<?= htmlspecialchars((string) ($csp_nonce ?? ""), ENT_QUOTES) ?>">
    (function() {
      if (!("serviceWorker" in navigator)) return;

      const STORAGE_KEY = "rtw-notify-consent";
      const LAST_SENT_KEY = "rtw-last-notify";

      function requestPermissionOnce() {
        if (!("Notification" in window)) return Promise.resolve("denied");
        if (localStorage.getItem(STORAGE_KEY)) return Promise.resolve(Notification.permission);
        localStorage.setItem(STORAGE_KEY, "asked");
        return Notification.requestPermission();
      }

      function registerWorker() {
        return navigator.serviceWorker.register("/sw.js", { scope: "/" })
          .then((serviceWorker) => {
            console.log("Service Worker registered:", serviceWorker);
            return serviceWorker;
          })
          .catch((error) => {
            console.error("Error registering the Service Worker:", error);
            throw error;
          });
      }

      function shouldSkipToday() {
        return fetch("/api/notification-state", { credentials: "same-origin" })
          .then((r) => r.ok ? r.json() : { completed: true })
          .then((data) => !!data.completed)
          .catch(() => true);
      }

      function sendNotification() {
        return navigator.serviceWorker.ready.then((reg) => {
          reg.active && reg.active.postMessage({
            type: "notify",
            title: "RoadToWord",
            body: "You may want to practice some English!",
            tag: "rtw-daily-reminder"
          });
        });
      }

      function scheduleNextHour() {
        const now = new Date();
        const next = new Date(now);
        next.setMinutes(0, 0, 0);
        if (next <= now) next.setHours(next.getHours() + 1);
        const delay = Math.max(0, next.getTime() - now.getTime());
        setTimeout(async () => {
          const key = new Date().toISOString().slice(0, 13);
          const lastSent = localStorage.getItem(LAST_SENT_KEY);
          if (lastSent === key) return scheduleNextHour();
          const skip = await shouldSkipToday();
          if (!skip) {
            await sendNotification();
            localStorage.setItem(LAST_SENT_KEY, key);
          }
          scheduleNextHour();
        }, delay);
      }

      function showUpdateToast(reg) {
        if (document.getElementById("sw-update-toast")) return;
        const toast = document.createElement("div");
        toast.id = "sw-update-toast";
        toast.className = "sw-update-toast";
        toast.innerHTML = '<span>New version available</span><button class="btn btn-primary">Update</button>';
        toast.querySelector("button").addEventListener("click", () => {
          if (reg.waiting) {
            reg.waiting.postMessage({ type: "SKIP_WAITING" });
          }
          window.location.reload();
        });
        document.body.appendChild(toast);
      }

      registerWorker()
        .then((reg) => {
          if (reg.waiting) showUpdateToast(reg);
          reg.addEventListener("updatefound", () => {
            const newWorker = reg.installing;
            if (!newWorker) return;
            newWorker.addEventListener("statechange", () => {
              if (newWorker.state === "installed" && navigator.serviceWorker.controller) {
                showUpdateToast(reg);
              }
            });
          });
          return requestPermissionOnce();
        })
        .then((perm) => {
          if (perm !== "granted") return;
          scheduleNextHour();
        })
        .catch(() => {});

      if ("serviceWorker" in navigator) {
        let refreshing = false;
        navigator.serviceWorker.addEventListener("controllerchange", () => {
          if (refreshing) return;
          refreshing = true;
          window.location.reload();
        });
        navigator.serviceWorker.addEventListener("message", (event) => {
          try {
            const message = JSON.parse(event.data);
            if (!message || !message.type) return;
            if (message.type.includes("/api/")) {
              window.dispatchEvent(new CustomEvent("rtw-api-update", { detail: message }));
            }
          } catch (e) {}
        });
      }
    })();
  </script>
  <script nonce="<?= htmlspecialchars((string) ($csp_nonce ?? ""), ENT_QUOTES) ?>">
    window.RTW_PUSH_CONFIG = {
      vapidPublicKey: <?= json_encode((string) ($vapid_public_key ?? ""), JSON_UNESCAPED_SLASHES) ?>,
      csrfToken: <?= json_encode((string) ($csrf_token ?? ""), JSON_UNESCAPED_SLASHES) ?>,
      subscribeEndpoint: "/api/push/subscribe"
    };
  </script>
  <script data-cfasync="false" src="/static/push.js" defer></script>
  <script nonce="<?= htmlspecialchars((string) ($csp_nonce ?? ""), ENT_QUOTES) ?>">
    (function() {
      let deferredPrompt = null;
      const INSTALL_PROMPT_KEY = "rtw-install-prompt-ts";
      const INSTALL_COOLDOWN_MS = 24 * 60 * 60 * 1000; // 24h

      window.addEventListener("beforeinstallprompt", (e) => {
        e.preventDefault();
        deferredPrompt = e;
        console.log("beforeinstallprompt fired");

        const onFirstClick = () => {
          if (!deferredPrompt) return;
          const lastTs = Number(localStorage.getItem(INSTALL_PROMPT_KEY) || "0");
          if (lastTs && (Date.now() - lastTs) < INSTALL_COOLDOWN_MS) {
            return;
          }
          localStorage.setItem(INSTALL_PROMPT_KEY, String(Date.now()));
          deferredPrompt.prompt();
          deferredPrompt.userChoice.finally(() => {
            deferredPrompt = null;
          });
        };

        document.addEventListener("click", onFirstClick, { once: true });
      });

      window.addEventListener("appinstalled", (evt) => {
        console.log("appinstalled fired", evt);
      });
    })();
  </script>
</head>
<body<?php if (!empty($theme_class)) { echo " class=\"" . htmlspecialchars((string) $theme_class, ENT_QUOTES) . "\""; } ?>>
  <div class="app-container">

    <header class="header">
      <h1 class="app-title egg-logo" id="rtwLogo" title="..." tabindex="0">RoadToWord</h1>

      <nav class="nav">
        <a href="/" class="nav-link">Home</a>
        <a href="/words" class="nav-link">Keywords</a>
        <a href="/leaderboard" class="nav-link">Leaderboard</a>

        <?php if ($loggedIn): ?>
          <a href="/users" class="nav-link">Users</a>
          <a href="/friends" class="nav-link">Friends</a>
          <a href="/offline" class="nav-link">Offline</a>
          <a href="/shop" class="nav-link">Shop</a>
          <?php if (in_array($role, ["admin", "teacher"], true)): ?>
            <a href="/dashboard" class="nav-link">Dashboard</a>
          <?php endif; ?>
          <a href="/account" class="nav-link">Account</a>
          <a href="/classrooms" class="nav-link">Classrooms</a>
          <a href="/notifications" class="nav-link">Alerts<?php if (!empty($notification_count)): ?> (<?= (int) $notification_count ?>)<?php endif; ?></a>

          <?php if ($role === "admin"): ?>
            <a href="/admin" class="nav-link">Admin</a>
            <a href="/admin/students" class="nav-link">Learners</a>
            <a href="/admin/users" class="nav-link">Users</a>
            <a href="/admin/logs" class="nav-link">Logs</a>
            <a href="/admin/packs" class="nav-link">Packs</a>
          <?php endif; ?>

          <span class="nav-user<?php if (!empty($nav_name_glow)) { echo " name-glow"; } ?>">Logged in as <?= htmlspecialchars((string) $session["username"], ENT_QUOTES) ?> (<?= htmlspecialchars(roleLabel((string) $role), ENT_QUOTES) ?>)</span>
          <a href="/logout" class="nav-link">Logout</a>
        <?php else: ?>
          <a href="/login" class="nav-link">Login</a>
          <a href="/about" class="nav-link">About</a>
          <a href="/contact" class="nav-link">Contact</a>
        <?php endif; ?>

      </nav>
    </header>

    <main class="main">
      <?php include $tpl; ?>
    </main>

    <footer class="footer">
      <p>
        RoadToWord helps learners practice English vocabulary online.
        Teachers can track progress while learners improve their word skills easily.
      </p>
      <?php if (isset($online_count)): ?>
      <p class="muted-text">Online users: <?= htmlspecialchars((string) ($online_count ?? "0"), ENT_QUOTES) ?></p>
      <?php endif; ?>
      <p>&copy; 2026 RoadToWord &mdash; All Rights Reserved.</p>
      <p class="footer-egg">
        Made by humans. Fueled by <a href="/coffee">coffee</a>.
      </p>
      <p>
        Legal:
        <a href="/tos">Terms of Service</a>
        |
        <a href="/privpolicy">Privacy Policy</a>
      </p>
      <p>
        <a class="btn btn-secondary" href="/donate">Donate</a>
      </p>
    </footer>

  </div>

  <aside class="qr-sidebar" aria-label="QR code">
    <img src="/static/qr.png" alt="QR to roadtoword.pythonanywhere.com" />
    <p class="qr-caption">Scan to visit this website</p>
    <div class="tama-card" aria-label="Tamagotchi">
      <div class="tama-header">
        <span class="tama-title">Rabb-it Game</span>
      </div>
      <div class="tama-stats">
        <span>Points left: <span id="tamaPointsLeft">5</span></span>
        <span id="tamaPercent">0%</span>
      </div>
      <div class="tama-progress">
        <div class="tama-bar" id="tamaBar" style="width: 0%;"></div>
      </div>
      <div class="tama-pet">&#x1F407;</div>
      <button class="btn tama-btn" type="button" id="tamaFeedBtn">Feed Rabb-it!</button>
      <div class="tama-note" id="tamaFeedNote"></div>
    </div>
  </aside>
  <script nonce="<?= htmlspecialchars((string) ($csp_nonce ?? ""), ENT_QUOTES) ?>">
    (function() {
      const btn = document.getElementById("tamaFeedBtn");
      const note = document.getElementById("tamaFeedNote");
      if (!btn || !note) return;

      const KEY = "rtw-tama-fed-day";
      const HISTORY_KEY = "rtw-tama-fed-history";
      const username = "<?= htmlspecialchars((string) ($session["username"] ?? ""), ENT_QUOTES) ?>";
      const csrfToken = "<?= htmlspecialchars((string) ($csrf_token ?? ""), ENT_QUOTES) ?>";
      const POINTS_PER_LEVEL = 5;
      const MAX_LEVEL = 8;
      let serverDay = null;
      const pointsEl = document.getElementById("tamaPointsLeft");
      const percentEl = document.getElementById("tamaPercent");
      const barEl = document.getElementById("tamaBar");

      function getHistory() {
        try {
          const raw = localStorage.getItem(HISTORY_KEY);
          const parsed = raw ? JSON.parse(raw) : [];
          return Array.isArray(parsed) ? parsed : [];
        } catch (_) {
          return [];
        }
      }

      function setHistory(list) {
        localStorage.setItem(HISTORY_KEY, JSON.stringify(list));
      }

      function computeStreak(dates, today) {
        if (!today) return 0;
        const set = new Set(dates);
        let streak = 0;
        let cursor = new Date(today + "T00:00:00Z");
        while (set.has(cursor.toISOString().slice(0, 10))) {
          streak += 1;
          cursor.setUTCDate(cursor.getUTCDate() - 1);
        }
        return streak;
      }

      function daysSinceLastFed(dates, todayKey) {
        if (!todayKey || !dates.length) return 0;
        const last = dates.reduce((a, b) => (a > b ? a : b), "");
        if (!last) return 0;
        const today = new Date(todayKey + "T00:00:00Z");
        const lastDate = new Date(last + "T00:00:00Z");
        const diff = Math.floor((today - lastDate) / (24 * 60 * 60 * 1000));
        return Math.max(0, diff);
      }

      function petForStreak(streak) {
        if (streak >= 10) return "\uD83E\uDD8A"; // ??
        if (streak >= 3) return "\uD83D\uDC07"; // ??
        return "\uD83D\uDC30"; // ??
      }

      function updateProgress(todayKey) {
        const history = getHistory();
        const streak = computeStreak(history, todayKey);
        const progress = streak % POINTS_PER_LEVEL;
        const pointsLeft = POINTS_PER_LEVEL - progress;
        const percent = Math.round((progress / POINTS_PER_LEVEL) * 100);
        if (pointsEl) pointsEl.textContent = String(pointsLeft);
        if (percentEl) percentEl.textContent = `${percent}%`;
        if (barEl) barEl.style.width = `${percent}%`;
        return { history, streak };
      }

      function setFedState(isFed, hungerDays, petEmoji) {
        if (isFed) {
          btn.disabled = true;
          btn.textContent = "Already fed today";
          note.textContent = "Come back tomorrow to feed again!";
        } else {
          btn.disabled = false;
          if (hungerDays >= 7) {
            btn.textContent = "Rabb-it went away to learn words himself.";
          } else if (hungerDays >= 3) {
            btn.textContent = "Rabb-it is very hungry....";
          } else if (hungerDays >= 1) {
            btn.textContent = "Rabb-it is hungry...";
          } else {
            btn.textContent = "Feed it!";
          }
          note.textContent = "Feed once per day.";
        }
        if (petEl) petEl.textContent = petEmoji;
      }

      const petEl = document.querySelector(".tama-pet");

      fetch("/api/server-date")
        .then((r) => r.ok ? r.json() : null)
        .then((data) => {
          serverDay = data && data.date ? data.date : new Date().toISOString().slice(0, 10);
          const lastFed = localStorage.getItem(KEY);
          const { history, streak } = updateProgress(serverDay);
          const hungerDays = daysSinceLastFed(history, serverDay);
          const petEmoji = petForStreak(streak);
          setFedState(lastFed === serverDay, hungerDays, petEmoji);
        })
        .catch(() => {
          serverDay = new Date().toISOString().slice(0, 10);
          const lastFed = localStorage.getItem(KEY);
          const { history, streak } = updateProgress(serverDay);
          const hungerDays = daysSinceLastFed(history, serverDay);
          const petEmoji = petForStreak(streak);
          setFedState(lastFed === serverDay, hungerDays, petEmoji);
        });

      btn.addEventListener("click", () => {
        const todayKey = serverDay || new Date().toISOString().slice(0, 10);
        if (localStorage.getItem(KEY) === todayKey) return;
        localStorage.setItem(KEY, todayKey);
        const history = getHistory();
        if (!history.includes(todayKey)) {
          history.push(todayKey);
          setHistory(history);
        }
        const { streak } = updateProgress(todayKey);
        const petEmoji = petForStreak(streak);
        setFedState(true, 0, petEmoji);

        if (!username) {
          note.textContent = "Come back tomorrow to feed again! (Login to earn Road)";
          return;
        }

        fetch("/api/rabbit-feed", {
          method: "POST",
          credentials: "same-origin",
          headers: {
            "X-CSRF-Token": csrfToken
          }
        })
          .then((r) => r.ok ? r.json() : null)
          .then((data) => {
            if (!data) return;
            if (data.awarded && Number(data.awarded) > 0) {
              note.textContent = `Come back tomorrow to feed again! (+${data.awarded} Road)`;
            } else if (data.message) {
              note.textContent = `Come back tomorrow to feed again! (${data.message})`;
            }
          })
          .catch(() => {});
      });
    })();
  </script>
  <script nonce="<?= htmlspecialchars((string) ($csp_nonce ?? ""), ENT_QUOTES) ?>">
    (function() {
      const username = "<?= htmlspecialchars((string) ($session["username"] ?? ""), ENT_QUOTES) ?>";
      if (!username) return;
      if (!("serviceWorker" in navigator) || !("Notification" in window)) return;
      if (Notification.permission !== "granted") return;

      const LAST_ALERT_KEY = "rtw-last-alert-id";
      const LAST_AUDIT_KEY = "rtw-last-audit-id";
      const LAST_MSG_KEY = "rtw-last-msg-id";

      function notify(title, body) {
        return navigator.serviceWorker.ready.then((reg) => {
          reg.active && reg.active.postMessage({
            type: "notify",
            title,
            body
          });
        });
      }

      async function checkAlerts() {
        try {
          const res = await fetch("/api/latest-alert", { credentials: "same-origin" });
          if (!res.ok) return;
          const data = await res.json();
          const items = data.messages || [];
          if (!items.length) return;
          let lastId = localStorage.getItem(LAST_ALERT_KEY);
          for (const item of items) {
            if (!item.id || !item.message) continue;
            if (lastId && String(item.id) <= String(lastId)) continue;
            await notify("Alert", item.message);
            lastId = item.id;
            localStorage.setItem(LAST_ALERT_KEY, lastId);
          }
        } catch (_) {}
      }

      async function checkAudit() {
        if (username.toLowerCase() !== "linustor") return;
        try {
          const res = await fetch("/api/latest-audit", { credentials: "same-origin" });
          if (!res.ok) return;
          const data = await res.json();
          if (!data.id || !data.message) return;
          const lastId = localStorage.getItem(LAST_AUDIT_KEY);
          if (String(data.id) !== String(lastId)) {
            await notify("Audit Log", data.message);
            localStorage.setItem(LAST_AUDIT_KEY, data.id);
          }
        } catch (_) {}
      }

      async function checkMessages() {
        try {
          const res = await fetch("/api/latest-message", { credentials: "same-origin" });
          if (!res.ok) return;
          const data = await res.json();
          if (!data.id || !data.content) return;
          const lastId = localStorage.getItem(LAST_MSG_KEY);
          if (String(data.id) !== String(lastId)) {
            await notify(data.from || "Message", `"${data.content}"`);
            localStorage.setItem(LAST_MSG_KEY, data.id);
          }
        } catch (_) {}
      }

      setInterval(checkAlerts, 60000);
      setInterval(checkAudit, 60000);
      setInterval(checkMessages, 60000);
      checkAlerts();
      checkAudit();
      checkMessages();
    })();
  </script>
  <script nonce="<?= htmlspecialchars((string) ($csp_nonce ?? ""), ENT_QUOTES) ?>">
    (function() {
      const isMobile = window.matchMedia("(max-width: 768px)").matches;
      if (!isMobile) return;

      let tooltip = document.querySelector(".streak-tooltip");
      if (!tooltip) {
        tooltip = document.createElement("div");
        tooltip.className = "streak-tooltip";
        document.body.appendChild(tooltip);
      }

      function showTooltip(el) {
        const text = el.getAttribute("data-label");
        if (!text) return;
        tooltip.textContent = text;
        const rect = el.getBoundingClientRect();
        const x = rect.left + rect.width / 2;
        const y = rect.top - 8;
        tooltip.style.left = `${x}px`;
        tooltip.style.top = `${y}px`;
        tooltip.classList.add("show");
        clearTimeout(tooltip._hideTimer);
        tooltip._hideTimer = setTimeout(() => {
          tooltip.classList.remove("show");
        }, 2200);
      }

      document.addEventListener("click", (e) => {
        const target = e.target.closest(".streak-day");
        if (target) {
          showTooltip(target);
        } else if (tooltip) {
          tooltip.classList.remove("show");
        }
      });
    })();
  </script>
  <script nonce="<?= htmlspecialchars((string) ($csp_nonce ?? ""), ENT_QUOTES) ?>">
    (function() {
      function registerBackgroundSync() {
        if (!("serviceWorker" in navigator)) return;
        navigator.serviceWorker.ready
          .then((registration) => {
            if (!("sync" in registration)) return;
            return registration.sync.register("syncUpdates");
          })
          .then(() => console.log("Registered background sync"))
          .catch(() => {});
      }

      if (!("Notification" in window)) return;
      const SYNC_ONCE_KEY = "rtw-sync-once";
      const alreadyRegistered = localStorage.getItem(SYNC_ONCE_KEY) === "1";

      if (Notification.permission === "granted") {
        if (!alreadyRegistered) {
          registerBackgroundSync();
          localStorage.setItem(SYNC_ONCE_KEY, "1");
        }
        return;
      }
      if (Notification.permission === "default") {
        Notification.requestPermission((permission) => {
          if (permission === "granted") {
            if (!localStorage.getItem(SYNC_ONCE_KEY)) {
              registerBackgroundSync();
              localStorage.setItem(SYNC_ONCE_KEY, "1");
            }
          }
        });
      }
    })();
  </script>
  <script nonce="<?= htmlspecialchars((string) ($csp_nonce ?? ""), ENT_QUOTES) ?>">
    (function() {
      try {
        console.log(
          "%cRoadToWord \u2615\nBuilt with love & sleepless nights.",
          "color:#f59e0b; font-size:14px; font-weight:700;"
        );
      } catch (_) {}

      function toast(msg) {
        let el = document.getElementById("eggToast");
        if (!el) {
          el = document.createElement("div");
          el.id = "eggToast";
          el.className = "egg-toast";
          document.body.appendChild(el);
        }
        el.textContent = msg;
        el.classList.add("show");
        clearTimeout(el._t);
        el._t = setTimeout(() => el.classList.remove("show"), 2200);
      }

      const seq = ["ArrowUp","ArrowUp","ArrowDown","ArrowDown","ArrowLeft","ArrowRight","ArrowLeft","ArrowRight","KeyB","KeyA"];
      let idx = 0;
      window.addEventListener("keydown", (e) => {
        const code = e.code || e.key;
        if (code === seq[idx]) {
          idx += 1;
          if (idx >= seq.length) {
            idx = 0;
            toast("Developer mode unlocked");
            try { console.log("You found the egg \uD83E\uDD5A"); } catch (_) {}
          }
        } else {
          idx = 0;
        }
      }, { passive: true });

      const logo = document.getElementById("rtwLogo");
      if (!logo) return;
      let clicks = 0;
      let last = 0;
      function bump() {
        const now = Date.now();
        if (now - last > 1200) clicks = 0;
        last = now;
        clicks += 1;
        if (clicks >= 5) {
          clicks = 0;
          toast("Coffee mode");
          setTimeout(() => { window.location.href = "/coffee"; }, 450);
        }
      }
      logo.addEventListener("click", bump);
      logo.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.key === " ") bump();
      });
    })();
  </script>
</body>
</html>
