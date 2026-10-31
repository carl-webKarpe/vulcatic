// Offline shop finder: the saved list of Siargao shops and vulcators,
// sorted by distance from the phone's GPS, with direction and call buttons.
(() => {
  const KEY = 'vulcatic-offline-pack';
  const $ = (id) => document.getElementById(id);
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  let pack = load();
  let me = null;
  const map = VMap.create('map', VMap.fallback, 12);
  const layer = L.layerGroup().addTo(map);
  let meMarker = null;

  function load() {
    try { return JSON.parse(localStorage.getItem(KEY)); } catch (e) { return null; }
  }

  // ---------- Online / offline ----------
  function showNetwork() {
    const online = navigator.onLine;
    $('net-status').textContent = online ? 'Online · list is up to date' : "You're offline · using the saved list";
    $('net-status').className = 'truncate text-sm font-medium ' + (online ? 'text-green-600' : 'text-brand-500');
    $('offline-note').classList.toggle('hidden', online);
    $('back-link').classList.toggle('hidden', !online);
    if (online) refreshPack();
  }
  addEventListener('online', showNetwork);
  addEventListener('offline', showNetwork);

  async function refreshPack() {
    try {
      const res = await fetch('api/offline_pack.php', { headers: { Accept: 'application/json' } });
      if (!res.ok) return;
      pack = await res.json();
      localStorage.setItem(KEY, JSON.stringify(pack));
      render();
    } catch (e) { /* still offline */ }
  }

  // ---------- Geometry ----------
  const rad = Math.PI / 180;
  function km(a, b) {
    const h = Math.sin((b[0] - a[0]) * rad / 2) ** 2 + Math.cos(a[0] * rad) * Math.cos(b[0] * rad) * Math.sin((b[1] - a[1]) * rad / 2) ** 2;
    return 6371 * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
  }
  function bearing(a, b) {
    const y = Math.sin((b[1] - a[1]) * rad) * Math.cos(b[0] * rad);
    const x = Math.cos(a[0] * rad) * Math.sin(b[0] * rad) - Math.sin(a[0] * rad) * Math.cos(b[0] * rad) * Math.cos((b[1] - a[1]) * rad);
    return (Math.atan2(y, x) / rad + 360) % 360;
  }
  const compass = (deg) => ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'][Math.round(deg / 45) % 8];
  const fmtKm = (d) => (d < 1 ? Math.round(d * 1000) + ' m' : d.toFixed(1) + ' km');

  // "Mon-Sun 7:00-19:00" -> open now? (null if we can't tell)
  function openNow(hours) {
    const m = /^(\w{3})-(\w{3})\s+(\d{1,2}):(\d{2})-(\d{1,2}):(\d{2})$/.exec((hours || '').trim());
    if (!m) return null;
    const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    const from = days.indexOf(m[1]), to = days.indexOf(m[2]);
    if (from < 0 || to < 0) return null;
    const now = new Date(), d = now.getDay(), mins = now.getHours() * 60 + now.getMinutes();
    const dayOk = from <= to ? d >= from && d <= to : d >= from || d <= to;
    return dayOk && mins >= +m[3] * 60 + +m[4] && mins < +m[5] * 60 + +m[6];
  }

  // ---------- Render ----------
  function item(p) {
    const d = me ? km(me, [p.lat, p.lng]) : null;
    const b = me ? bearing(me, [p.lat, p.lng]) : 0;
    const open = openNow(p.hours);
    return `<li class="py-3">
      <div class="flex items-start gap-3">
        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full ${p.walk_in ? 'bg-ok-600' : 'bg-ink-900'} text-white" aria-hidden="true">
          ${me ? `<svg viewBox="0 0 24 24" class="h-5 w-5" style="transform: rotate(${b}deg)" fill="currentColor"><path d="M12 2l6 18-6-4-6 4z"/></svg>` : '•'}
        </span>
        <div class="min-w-0 flex-1">
          <p class="truncate font-semibold">${esc(p.name)}</p>
          <p class="text-xs text-gray-500">${esc(p.address || '')}${p.town ? ', ' + esc(p.town) : ''}</p>
          <p class="mt-0.5 text-xs">
            ${open === true ? '<span class="font-semibold text-green-600">Open now</span>' : open === false ? '<span class="font-semibold text-red-500">Closed now</span>' : ''}
            ${p.hours ? `<span class="text-gray-400"> · ${esc(p.hours)}</span>` : ''}
            ${p.rating_count ? `<span class="text-gray-400"> · ★ ${p.rating.toFixed(1)}</span>` : ''}
          </p>
        </div>
        <div class="shrink-0 text-right">
          ${d !== null ? `<p class="font-bold">${fmtKm(d)}</p><p class="text-xs text-gray-500">${compass(b)}</p>` : ''}
        </div>
      </div>
      <div class="mt-2 grid grid-cols-3 gap-2 pl-14">
        <a href="tel:${esc(p.phone)}" class="rounded-lg py-1.5 text-center text-xs font-semibold ring-1 ring-gray-200 hover:bg-gray-50">Call</a>
        <a href="sms:${esc(p.phone)}" class="rounded-lg py-1.5 text-center text-xs font-semibold ring-1 ring-gray-200 hover:bg-gray-50">Text</a>
        <a href="geo:${p.lat},${p.lng}?q=${p.lat},${p.lng}(${encodeURIComponent(p.name)})" class="rounded-lg py-1.5 text-center text-xs font-semibold ring-1 ring-gray-200 hover:bg-gray-50">Maps app</a>
      </div>
    </li>`;
  }

  function render() {
    const all = pack?.providers || [];
    $('empty').classList.toggle('hidden', all.length > 0);
    $('saved-at').textContent = pack?.saved_at ? 'Saved ' + new Date(pack.saved_at).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) : '';
    const byDistance = (a, b) => (me ? km(me, [a.lat, a.lng]) - km(me, [b.lat, b.lng]) : 0);
    const shops = all.filter((p) => p.walk_in).sort(byDistance);
    const vulcs = all.filter((p) => p.onsite && !p.walk_in).sort(byDistance);
    $('shop-list').innerHTML = shops.map(item).join('') || '<li class="py-4 text-center text-gray-400">No saved shops.</li>';
    $('vulc-list').innerHTML = vulcs.map(item).join('') || '<li class="py-4 text-center text-gray-400">No saved vulcators.</li>';

    layer.clearLayers();
    all.forEach((p) => L.marker([p.lat, p.lng], { icon: VMap.icon(p.walk_in ? 'shop' : 'vulc') })
      .bindPopup(`<b>${esc(p.name)}</b><br>${esc(p.town || '')}`).addTo(layer));
    if (me && shops[0]) {
      L.polyline([me, [shops[0].lat, shops[0].lng]], { color: '#16A34A', weight: 4, dashArray: '8 10' }).addTo(layer);
      VMap.fit(map, [me, [shops[0].lat, shops[0].lng]], 15);
    } else if (all.length) {
      VMap.fit(map, all.map((p) => [p.lat, p.lng]), 13);
    }
  }

  async function locate() {
    $('locate-btn').disabled = true;
    try {
      me = await VMap.locate({ maximumAge: 60000 });
      if (!meMarker) meMarker = L.marker(me, { icon: VMap.icon('me'), zIndexOffset: 1000 }).addTo(map);
      else meMarker.setLatLng(me);
      render();
    } catch (e) {
      $('net-status').textContent = e.message;
    } finally {
      $('locate-btn').disabled = false;
    }
  }
  $('locate-btn').addEventListener('click', locate);

  showNetwork();
  render();
  locate();
})();
