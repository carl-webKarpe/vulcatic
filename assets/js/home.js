// Motorist: 5-step roadside assistance request.
(() => {
  const root = document.getElementById('wizard');
  const form = document.getElementById('request-form');
  const PROBLEMS = JSON.parse(root.dataset.problems);
  const $ = (id) => document.getElementById(id);
  const val = (name) => form.querySelector(`input[name=${name}]:checked`)?.value;
  const VEHICLE_NAMES = { motorcycle: 'Motorcycle', car: 'Car', tricycle: 'Tricycle', van: 'Van', truck: 'Truck', bicycle: 'Bicycle', other: 'Other vehicle' };
  const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

  let step = 1, haveLocation = false, providers = [], shopId = null, loadedFor = '';

  // ---------- Map ----------
  const map = VMap.create('map', null, 13);
  const me = L.marker(VMap.fallback, { icon: VMap.icon('me'), draggable: true, zIndexOffset: 1000 }).addTo(map);
  const layer = L.layerGroup().addTo(map);

  function setMe(latlng, recenter = true) {
    me.setLatLng(latlng);
    haveLocation = true;
    loadedFor = '';
    const { lat, lng } = me.getLatLng();
    const inArea = VMap.inArea([lat, lng]);
    $('area-warning').classList.toggle('hidden', inArea);
    $('loc-status').innerHTML = inArea
      ? `📍 Location set <span class="text-gray-400">(${lat.toFixed(5)}, ${lng.toFixed(5)})</span>`
      : '📍 Location set, but it is outside Siargao.';
    $('loc-status').className = 'mt-2 rounded-xl px-3 py-2 text-sm ' + (inArea ? 'bg-green-50 text-green-800' : 'bg-gray-50 text-gray-600');
    if (recenter) map.setView(latlng, Math.max(map.getZoom(), 15));
    $('map-hint').textContent = 'Not quite right? Drag the orange pin.';
    sync();
  }
  me.on('dragend', () => setMe(me.getLatLng(), false));
  map.on('click', (e) => setMe(e.latlng, false));

  async function locate(showErrors) {
    try { setMe(await VMap.locate()); }
    catch (e) { if (showErrors) { $('loc-status').textContent = e.message; $('loc-status').className = 'mt-2 rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-800'; } }
  }
  $('locate-btn').addEventListener('click', () => locate(true));
  form.querySelector('[data-locate]').addEventListener('click', () => locate(true));
  locate(false);

  // ---------- Nearby help (for step 4) ----------
  function openNow(hours) {
    const m = /^(\w{3})-(\w{3})\s+(\d{1,2}):(\d{2})-(\d{1,2}):(\d{2})$/.exec((hours || '').trim());
    if (!m) return null;
    const from = DAYS.indexOf(m[1]), to = DAYS.indexOf(m[2]);
    if (from < 0 || to < 0) return null;
    const now = new Date(), d = now.getDay(), mins = now.getHours() * 60 + now.getMinutes();
    const dayOk = from <= to ? d >= from && d <= to : d >= from || d <= to;
    return dayOk && mins >= +m[3] * 60 + +m[4] && mins < +m[5] * 60 + +m[6];
  }
  const fmtKm = (d) => (d < 1 ? Math.round(d * 1000) + ' m' : d.toFixed(1) + ' km');

  async function loadNearby() {
    const { lat, lng } = me.getLatLng();
    const sid = PROBLEMS[val('problem')].service_id;
    const key = `${lat},${lng},${sid}`;
    if (key === loadedFor) return;
    loadedFor = key;
    $('shop-list').innerHTML = '<li class="py-4 text-center text-sm text-gray-400">Finding shops near you…</li>';
    try {
      providers = (await api(`nearby.php?lat=${lat}&lng=${lng}&service_id=${sid}`)).providers;
    } catch (e) { providers = []; $('shop-list').innerHTML = `<li class="py-4 text-center text-sm text-red-600">${esc(e.message)}</li>`; return; }
    layer.clearLayers();
    providers.forEach((p) => L.marker([p.lat, p.lng], { icon: VMap.icon(p.can_come ? 'vulc' : p.walk_in ? 'shop' : 'off') })
      .bindPopup(`<b>${esc(p.name)}</b><br>${fmtKm(p.distance_km)} away`).addTo(layer));
    renderHelp();
  }

  function renderHelp() {
    const coming = providers.filter((p) => p.can_come);
    const shops = providers.filter((p) => p.walk_in);
    const from = PROBLEMS[val('problem')].from;
    $('onsite-sub').textContent = coming.length
      ? `${coming.length} vulcator${coming.length > 1 ? 's' : ''} online near you · from ${peso(Math.min(...coming.map((p) => p.price ?? from)))}`
      : 'A vulcator comes to you · none online nearby right now';
    $('walkin-sub').textContent = shops.length ? `${shops.length} shop${shops.length > 1 ? 's' : ''} within 15 km` : 'No shops found nearby';
    $('onsite-warning').classList.toggle('hidden', !(val('mode') === 'onsite' && !coming.length));
    $('shop-picker').classList.toggle('hidden', val('mode') !== 'walk_in');
    if (shopId && !shops.some((s) => s.id === shopId)) shopId = null;
    $('shop-list').innerHTML = shops.map((s) => {
      const open = openNow(s.hours);
      return `<li><label class="wiz-card flex cursor-pointer items-center gap-3 rounded-2xl border-2 border-gray-200 bg-white p-3">
        <input type="radio" name="shop" value="${s.id}" class="sr-only" ${s.id === shopId ? 'checked' : ''}>
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-ok-600 text-xs font-bold text-white">${esc(s.name.split(/\s+/).map((w) => w[0]).slice(0, 2).join('').toUpperCase())}</span>
        <span class="min-w-0 flex-1"><span class="block truncate font-semibold">${esc(s.name)}</span>
          <span class="block truncate text-xs text-gray-500">${fmtKm(s.distance_km)} · ${open === true ? '<b class="text-green-600">Open now</b>' : open === false ? '<b class="text-red-500">Closed now</b>' : esc(s.hours || 'Hours not listed')}${s.rating_count ? ` · ★ ${s.rating.toFixed(1)}` : ''}</span></span>
        <span class="shrink-0 text-right text-sm font-bold">${peso(s.price ?? from)}</span>
      </label></li>`;
    }).join('') || '<li class="rounded-xl bg-gray-50 px-3 py-4 text-center text-sm text-gray-500">No shops found within 15 km.</li>';
    $('shop-list').querySelectorAll('input[name=shop]').forEach((r) => r.addEventListener('change', () => {
      shopId = Number(r.value);
      const s = shops.find((x) => x.id === shopId);
      VMap.fit(map, [[s.lat, s.lng], [me.getLatLng().lat, me.getLatLng().lng]], 15);
      sync();
    }));
  }

  // ---------- Steps ----------
  const vehicle = () => (val('vehicle_group') === 'other' ? val('vehicle_other') : val('vehicle_group'));

  function check(n) {
    if (n === 2) {
      if (!haveLocation) return 'Set your location: tap "Detect my location", or tap the map where you are.';
      const { lat, lng } = me.getLatLng();
      if (!VMap.inArea([lat, lng])) return 'Vulcatic serves Siargao Island only. Move the pin to your spot on Siargao.';
    }
    if (n === 4 && val('mode') === 'walk_in' && !shopId) return 'Pick the shop you want to visit.';
    return null;
  }

  function review() {
    const shop = providers.find((p) => p.id === shopId);
    const from = PROBLEMS[val('problem')].from;
    const rows = [
      ['Vehicle', VEHICLE_NAMES[vehicle()], 1],
      ['Location', [$('address').value.trim() || 'Pin on the map', $('directions').value.trim()].filter(Boolean).join(' · '), 2],
      ['Problem', PROBLEMS[val('problem')].label + ($('details').value.trim() ? ' · ' + $('details').value.trim() : ''), 3],
      ['Help', val('mode') === 'walk_in' ? `Visit ${shop?.name || 'a shop'} (${shop ? fmtKm(shop.distance_km) : ''})` : 'Roadside repair: a vulcator comes to you', 4],
      ['Estimated price', val('mode') === 'walk_in' ? peso(shop?.price ?? from) : 'from ' + peso(from), null],
    ];
    $('review').innerHTML = rows.map(([k, v, s]) => `<div class="flex items-start gap-3 py-3"><dt class="w-24 shrink-0 text-gray-500">${k}</dt>
      <dd class="min-w-0 flex-1 font-medium">${esc(v)}</dd>${s ? `<button type="button" data-goto="${s}" class="shrink-0 text-xs font-semibold text-brand-600">Edit</button>` : ''}</div>`).join('');
    $('review').querySelectorAll('[data-goto]').forEach((b) => b.addEventListener('click', () => show(Number(b.dataset.goto))));
  }

  function show(n) {
    step = n;
    form.querySelectorAll('[data-step]').forEach((p) => p.classList.toggle('hidden', Number(p.dataset.step) !== n));
    form.querySelectorAll('[data-step-dot]').forEach((d) => {
      const i = Number(d.dataset.stepDot);
      d.classList.toggle('is-done', i < n); d.classList.toggle('is-current', i === n);
    });
    document.querySelectorAll('[data-back]').forEach((b) => (b.style.visibility = n === 1 ? 'hidden' : 'visible'));
    document.querySelectorAll('[data-next]').forEach((b) => (b.textContent = n === 5 ? 'Send request' : b.id === 'sticky-btn' ? 'Next →' : 'Next'));
    $('request-error').classList.add('hidden');
    if (n === 2 && !haveLocation) locate(false);
    if (n === 4) loadNearby().then(renderHelp);
    if (n === 5) review();
    sync();
    if (matchMedia('(max-width: 767px)').matches) {
      const top = n === 2 ? 0 : form.getBoundingClientRect().top + scrollY - 96; // 96 = sticky header
      scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
    }
  }

  function sync() {
    $('sticky-step').textContent = `Step ${step} of 5 · ${['Vehicle', 'Location', 'Problem', 'Help', 'Review'][step - 1]}`;
    $('sticky-summary').textContent = [VEHICLE_NAMES[vehicle()], step > 2 ? PROBLEMS[val('problem')].label : null].filter(Boolean).join(' · ');
  }

  function error(msg) {
    $('request-error').textContent = msg;
    $('request-error').classList.remove('hidden');
    if (matchMedia('(max-width: 767px)').matches) toast(msg, 'error');
  }

  async function next() {
    const problem = check(step);
    if (problem) return error(problem);
    if (step < 5) return show(step + 1);
    const btns = document.querySelectorAll('[data-next]');
    btns.forEach((b) => (b.disabled = true));
    const { lat, lng } = me.getLatLng();
    try {
      const res = await api('job_create.php', {
        lat, lng, vehicle_type: vehicle(), problem: val('problem'), details: $('details').value,
        address: $('address').value, directions: $('directions').value, mode: val('mode'), shop_id: shopId,
      });
      location.href = VULCATIC.base + 'job.php?id=' + res.job_id;
    } catch (e) {
      error(e.message);
      btns.forEach((b) => (b.disabled = false));
    }
  }

  document.querySelectorAll('[data-next]').forEach((b) => b.addEventListener('click', next));
  document.querySelectorAll('[data-back]').forEach((b) => b.addEventListener('click', () => step > 1 && show(step - 1)));
  form.addEventListener('submit', (e) => { e.preventDefault(); next(); });
  form.addEventListener('change', (e) => {
    if (e.target.name === 'vehicle_group') $('other-vehicle').classList.toggle('hidden', e.target.value !== 'other');
    if (e.target.name === 'problem') loadedFor = '';
    if (e.target.name === 'mode') { if (e.target.value === 'walk_in') loadNearby(); renderHelp(); }
    sync();
  });
  show(1);
})();
