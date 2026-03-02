(function () {
  if (!("serviceWorker" in navigator)) return;
  if (!("Notification" in window)) return;
  if (!("PushManager" in window)) return;

  var cfg = window.RTW_PUSH_CONFIG || {};
  var vapidPublicKey = String(cfg.vapidPublicKey || "").trim();
  var csrfToken = String(cfg.csrfToken || "").trim();
  var subscribeEndpoint = String(cfg.subscribeEndpoint || "/api/push/subscribe");
  var ASKED_KEY = "rtw-notify-consent";

  if (!vapidPublicKey || !csrfToken) return;

  function urlBase64ToUint8Array(base64String) {
    var padding = "=".repeat((4 - (base64String.length % 4)) % 4);
    var base64 = (base64String + padding).replace(/-/g, "+").replace(/_/g, "/");
    var rawData = window.atob(base64);
    var outputArray = new Uint8Array(rawData.length);
    for (var i = 0; i < rawData.length; i += 1) {
      outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
  }

  function requestPermissionOnce() {
    if (Notification.permission !== "default") {
      return Promise.resolve(Notification.permission);
    }
    if (localStorage.getItem(ASKED_KEY)) {
      return Promise.resolve(Notification.permission);
    }
    localStorage.setItem(ASKED_KEY, "asked");
    return Notification.requestPermission();
  }

  function ensureRegistration() {
    return navigator.serviceWorker.register("/sw.js", { scope: "/" })
      .then(function () { return navigator.serviceWorker.ready; });
  }

  function postSubscription(subscription) {
    var payload = subscription && subscription.toJSON ? subscription.toJSON() : subscription;
    if (!payload || !payload.endpoint) {
      return Promise.resolve();
    }
    return fetch(subscribeEndpoint, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": csrfToken
      },
      body: JSON.stringify({ subscription: payload })
    }).then(function (res) {
      if (!res.ok) {
        return res.text().then(function (t) {
          throw new Error("Push subscribe failed: " + res.status + " " + t);
        });
      }
      return res;
    });
  }

  function subscribeWithVapid() {
    return ensureRegistration()
      .then(function (reg) {
        if (Notification.permission === "default") {
          return requestPermissionOnce().then(function (perm) {
            if (perm !== "granted") return null;
            return reg;
          });
        }
        if (Notification.permission !== "granted") return null;
        return reg;
      })
      .then(function (reg) {
        if (!reg || !reg.pushManager) return null;
        return reg.pushManager.getSubscription()
          .then(function (sub) {
            if (sub) return sub;
            return reg.pushManager.subscribe({
              userVisibleOnly: true,
              applicationServerKey: urlBase64ToUint8Array(vapidPublicKey)
            });
          });
      })
      .then(function (subscription) {
        if (!subscription) return null;
        return postSubscription(subscription);
      })
      .catch(function (err) {
        try { console.warn("RTW push registration skipped:", err); } catch (_) {}
        return null;
      });
  }

  window.addEventListener("load", function () {
    subscribeWithVapid();
  }, { once: true });

  document.addEventListener("visibilitychange", function () {
    if (document.visibilityState === "visible") {
      subscribeWithVapid();
    }
  });

  navigator.serviceWorker.addEventListener("message", function (event) {
    try {
      var msg = JSON.parse(event.data);
      if (msg && msg.type === "pushsubscriptionchange") {
        subscribeWithVapid();
      }
    } catch (_) {}
  });

  window.RTWPush = {
    subscribeNow: subscribeWithVapid
  };
})();
