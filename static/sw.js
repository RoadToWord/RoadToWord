// Bump this when you ship important static changes (CSS/JS/images).
const CACHE_VERSION = 'v7';
const APP_SHELL_CACHE = `rtw-app-shell-${CACHE_VERSION}`;
const PAGES_CACHE = `rtw-pages-${CACHE_VERSION}`;
const STATIC_CACHE = `rtw-static-${CACHE_VERSION}`;
const DATA_CACHE = `rtw-data-${CACHE_VERSION}`;

const APP_SHELL_URLS = [
  '/start',
  '/offline',
  '/login',
  '/manifest.json',
  '/static/style.css',
  '/static/qr.png',
  '/static/correct.mp3',
  '/static/wrong.mp3',
  '/static/icon.png',
  '/static/fallback.jpg',
  '/favicon.ico'
];

const API_MAX_AGE_MS = 5 * 60 * 1000;

async function cachePut(request, response) {
  const cloned = response.clone();
  const body = await cloned.blob();
  const headers = new Headers(cloned.headers);
  headers.set('sw-fetched-on', Date.now().toString());
  const cachedResponse = new Response(body, {
    status: cloned.status,
    statusText: cloned.statusText,
    headers
  });
  const cache = await caches.open(DATA_CACHE);
  await cache.put(request, cachedResponse);
}

function refreshResponse(response) {
  return response.clone().json().then((jsonResponse) => {
    return self.clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then((clients) => {
        clients.forEach((client) => {
          client.postMessage(JSON.stringify({
            type: response.url,
            data: jsonResponse
          }));
        });
      });
  }).catch(() => { });
}

function fetchJson(url) {
  return fetch(url, { credentials: 'include' }).then((resp) => {
    if (!resp.ok) throw new Error('Network error');
    return resp;
  });
}

async function getFreshCache(request) {
  const cached = await caches.match(request);
  if (!cached) return null;
  const ts = cached.headers.get('sw-fetched-on');
  if (!ts) return cached;
  const age = Date.now() - Number(ts);
  if (age > API_MAX_AGE_MS) return null;
  return cached;
}

async function shouldNotify(key, minIntervalMs) {
  const cache = await caches.open(DATA_CACHE);
  const req = new Request(`/sw-notify/${key}`);
  const cached = await cache.match(req);
  if (cached) {
    const ts = cached.headers.get('sw-fetched-on');
    if (ts && Date.now() - Number(ts) < minIntervalMs) {
      return false;
    }
  }
  const headers = new Headers();
  headers.set('sw-fetched-on', Date.now().toString());
  await cache.put(req, new Response('ok', { headers }));
  return true;
}

self.addEventListener('install', (event) => {
  console.log('Service Worker installing.');
  event.waitUntil(
    caches.open(APP_SHELL_CACHE)
      .then((cache) => cache.addAll(APP_SHELL_URLS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  console.log('Service Worker activating.');
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(
      keys.filter((k) => !k.startsWith('rtw-') || !k.includes(CACHE_VERSION))
        .map((k) => caches.delete(k))
    )).then(() => self.clients.claim())
  );
});

self.addEventListener('message', (event) => {
  const data = event.data || {};
  if (data.type === 'SKIP_WAITING') {
    self.skipWaiting();
    return;
  }
  if (data.type !== 'notify') return;

  const title = data.title || 'RoadToWord';
  const body = data.body || 'You may want to practice some English!';

  self.registration.showNotification(title, {
    body,
    icon: '/favicon.ico',
    badge: '/favicon.ico',
    tag: data.tag || 'rtw-daily-reminder',
    renotify: false,
  });
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;
  if (url.pathname === '/offline') {
    event.respondWith(
      caches.match('/offline').then((cached) => cached || fetch(req).catch(() => caches.match('/offline')))
    );
    return;
  }
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req)
        .then((resp) => {
          if (resp && resp.ok) {
            const copy = resp.clone();
            caches.open(PAGES_CACHE).then((cache) => cache.put(req, copy));
          }
          return resp;
        })
        .catch(async () => {
          const cached = await caches.match(req);
          return cached || caches.match('/offline');
        })
    );
    return;
  }

  if (req.destination === 'image') {
    event.respondWith(
      caches.match(req).then((cached) => {
        if (cached) return cached;
        return fetch(req).then((resp) => {
          if (resp && resp.ok && resp.type === 'basic') {
            const copy = resp.clone();
            caches.open(STATIC_CACHE).then((cache) => cache.put(req, copy));
          }
          return resp;
        }).catch(() => caches.match('/static/fallback.jpg'));
      })
    );
    return;
  }

  if (url.pathname.startsWith('/static/')) {
    const isCriticalStatic = (
      req.destination === 'style' ||
      req.destination === 'script' ||
      url.pathname.endsWith('.css') ||
      url.pathname.endsWith('.js')
    );

    // Network-first for CSS/JS so style/script updates appear without hard refresh.
    if (isCriticalStatic) {
      event.respondWith(
        caches.open(STATIC_CACHE).then(async (cache) => {
          try {
            const resp = await fetch(req);
            if (resp && resp.ok && resp.type === 'basic') {
              cache.put(req, resp.clone());
            }
            return resp;
          } catch (_) {
            const cached = await cache.match(req);
            return cached || caches.match('/offline');
          }
        })
      );
      return;
    }

    // Stale-while-revalidate for static assets: fast from cache, but keep updating in background.
    event.respondWith(
      caches.open(STATIC_CACHE).then(async (cache) => {
        const cached = await cache.match(req);
        const networkFetch = fetch(req).then((resp) => {
          if (resp && resp.ok && resp.type === 'basic') {
            cache.put(req, resp.clone());
          }
          return resp;
        }).catch(() => null);

        if (cached) {
          event.waitUntil(networkFetch);
          return cached;
        }
        const resp = await networkFetch;
        return resp || caches.match('/offline');
      })
    );
    return;
  }

  if (url.pathname.startsWith('/api/')) {
    event.respondWith(
      getFreshCache(req).then((cached) => cached || fetch(req))
    );
    event.waitUntil(
      fetch(req).then((resp) => {
        if (resp && resp.ok) {
          const copy = resp.clone();
          return cachePut(req, copy).then(() => refreshResponse(resp));
        }
      }).catch(() => { })
    );
    return;
  }

  event.respondWith(
    caches.match(req).then((cached) => {
      if (cached) return cached;
      return fetch(req).then((resp) => {
        if (resp && resp.ok && resp.type === 'basic') {
          const copy = resp.clone();
          caches.open(PAGES_CACHE).then((cache) => cache.put(req, copy));
        }
        return resp;
      }).catch(() => caches.match('/offline'));
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const targetUrl = (event.notification && event.notification.data && event.notification.data.url) || '/';
  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientsArr) => {
      for (const client of clientsArr) {
        if (client.url.includes(targetUrl) || targetUrl === '/') {
          client.focus();
          return;
        }
      }
      return self.clients.openWindow(targetUrl);
    })
  );
});

self.addEventListener('push', (event) => {
  let payload = {};
  try {
    payload = event.data ? event.data.json() : {};
  } catch (_) {
    try {
      payload = { body: event.data ? event.data.text() : '' };
    } catch (__) {
      payload = {};
    }
  }

  const title = payload.title || 'RoadToWord';
  const body = payload.body || 'You have a new notification.';
  const url = payload.url || '/';
  const tag = payload.tag || 'rtw-push';

  event.waitUntil(
    self.registration.showNotification(title, {
      body,
      icon: payload.icon || '/favicon.ico',
      badge: payload.badge || '/favicon.ico',
      tag,
      data: { url },
      renotify: false
    })
  );
});

self.addEventListener('pushsubscriptionchange', (event) => {
  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
      clients.forEach((client) => {
        client.postMessage(JSON.stringify({ type: 'pushsubscriptionchange' }));
      });
    })
  );
});

self.addEventListener('sync', (event) => {
  if (event.tag !== 'syncUpdates') return;
  event.waitUntil(
    fetchJson('/api/latest-alert')
      .then((resp) => cachePut(new Request('/api/latest-alert'), resp.clone()).then(() => resp))
      .then((resp) => refreshResponse(resp).then(() => resp.json()))
      .then((data) => {
        if (data && data.message) {
          return shouldNotify('alert-sync', 10 * 60 * 1000).then((ok) => {
            if (!ok) return;
            return self.registration.showNotification('New alert', {
              body: data.message,
              icon: '/favicon.ico',
              badge: '/favicon.ico',
              tag: 'rtw-alert-sync'
            });
          });
        }
      })
      .catch(() => {
        return shouldNotify('generic-sync', 30 * 60 * 1000).then((ok) => {
          if (!ok) return;
          return self.registration.showNotification('RoadToWord', {
            body: 'New updates may be available.',
            icon: '/favicon.ico',
            badge: '/favicon.ico',
            tag: 'rtw-generic-sync'
          });
        });
      })
  );
});
