// Vulcatic service worker: keeps the app usable with weak or no signal.
// - Pages: always from the network; with no signal, show the offline shop finder.
// - CSS/JS/images: served from the phone, refreshed in the background.
// - Map tiles: the ones you've already looked at are kept (OpenStreetMap does not
//   allow bulk-downloading tiles, so we never pre-fetch them).
// Bump VERSION whenever offline.html or the precache list changes.

const VERSION = 'v1';
const STATIC = `vulcatic-static-${VERSION}`;
const TILES = 'vulcatic-tiles';
const MAX_TILES = 1500;

const PRECACHE = [
  'offline.html',
  'manifest.webmanifest',
  'assets/css/app.css',
  'assets/js/app.js',
  'assets/js/map.js',
  'assets/js/offline.js',
  'assets/vendor/leaflet/leaflet.css',
  'assets/vendor/leaflet/leaflet.js',
  'assets/img/gecko.webp',
  'assets/img/gecko-head.png',
  'assets/img/favicon.png',
  'assets/img/icon-192.png',
].map((p) => new URL(p, self.registration.scope).href);

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(STATIC).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    for (const key of await caches.keys()) {
      if (key.startsWith('vulcatic-static-') && key !== STATIC) await caches.delete(key);
    }
    await self.clients.claim();
  })());
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);

  // Map tiles: cache first, keep what was viewed.
  if (url.hostname.endsWith('tile.openstreetmap.org')) {
    event.respondWith(tile(req));
    return;
  }
  if (url.origin !== self.location.origin) return;

  // Page loads: network, falling back to the offline shop finder.
  if (req.mode === 'navigate') {
    event.respondWith(fetch(req).catch(async () =>
      (await caches.match(new URL('offline.html', self.registration.scope).href)) || Response.error()));
    return;
  }

  // App files: stale-while-revalidate (the ?v= version suffix is ignored for matching).
  if (url.pathname.includes('/assets/') || url.pathname.endsWith('manifest.webmanifest')) {
    event.respondWith((async () => {
      const cache = await caches.open(STATIC);
      const hit = await cache.match(req, { ignoreSearch: true });
      const fresh = fetch(req).then((res) => {
        if (res.ok) cache.put(new Request(url.origin + url.pathname), res.clone());
        return res;
      }).catch(() => hit);
      return hit || fresh;
    })());
  }
  // Everything else (API calls) goes straight to the network.
});

async function tile(req) {
  const cache = await caches.open(TILES);
  const hit = await cache.match(req);
  if (hit) return hit;
  try {
    const res = await fetch(req);
    if (res.ok || res.type === 'opaque') {
      await cache.put(req, res.clone());
      trimTiles(cache);
    }
    return res;
  } catch (e) {
    return new Response('', { status: 504 });
  }
}

async function trimTiles(cache) {
  const keys = await cache.keys();
  for (let i = 0; i < keys.length - MAX_TILES; i++) await cache.delete(keys[i]);
}
