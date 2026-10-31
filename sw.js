// Vulcatic service worker: keeps the app usable with weak or no signal.
// - Pages: always from the network; with no signal, show the offline shop finder.
// - CSS/JS/images: saved per version; a new version is downloaded right away.
// - Map tiles: the ones you've already looked at are kept (OpenStreetMap does not
//   allow bulk-downloading tiles, so we never pre-fetch them).
// Bump VERSION whenever offline.html or the precache list changes.

const VERSION = 'v2';
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

  // App files. Pages ask for them with a ?v=<file time> suffix, so:
  // - same version already saved -> use it (instant, works offline)
  // - new version -> download it now (never show old styles after an update)
  // - no signal -> fall back to whatever version was saved last
  if (url.pathname.includes('/assets/') || url.pathname.endsWith('manifest.webmanifest')) {
    event.respondWith((async () => {
      const cache = await caches.open(STATIC);
      if (url.search) {
        const exact = await cache.match(req);
        if (exact) return exact;
      }
      try {
        const res = await fetch(req);
        if (res.ok) {
          await cache.put(req, res.clone());
          if (url.search) trimOldVersions(cache, url);
        }
        return res;
      } catch (e) {
        return (await cache.match(req, { ignoreSearch: true })) || Response.error();
      }
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

// Keep only the newest saved version of each app file.
async function trimOldVersions(cache, url) {
  for (const key of await cache.keys()) {
    const k = new URL(key.url);
    if (k.pathname === url.pathname && k.search !== url.search) await cache.delete(key);
  }
}

async function trimTiles(cache) {
  const keys = await cache.keys();
  for (let i = 0; i < keys.length - MAX_TILES; i++) await cache.delete(keys[i]);
}
