// Vulcator dashboard: share location while online and pop up new job requests.
(() => {
  const dash = document.querySelector('[data-offer-listener]');

  // "Your area" map: where you are and the circle you get requests from.
  const area = document.getElementById('coverage-map');
  if (area && window.L) {
    const pos = [Number(area.dataset.lat), Number(area.dataset.lng)];
    const m = VMap.create(area, pos, 13);
    const circle = L.circle(pos, { radius: Number(area.dataset.radius) * 1000, color: '#E8501E', weight: 2, fillColor: '#E8501E', fillOpacity: 0.08 }).addTo(m);
    L.marker(pos, { icon: VMap.icon('vulc') }).addTo(m);
    m.fitBounds(circle.getBounds(), { padding: [10, 10] });
  }

  // Checklist: tick "Sound alerts on" once the browser allows notifications.
  const alertItem = document.querySelector('[data-check=alerts]');
  if (alertItem && typeof Alerts !== 'undefined' && Alerts.enabled()) {
    alertItem.classList.replace('bg-gray-50', 'bg-green-50');
    const dot = alertItem.querySelector('span');
    dot.className = 'grid h-6 w-6 shrink-0 place-items-center rounded-full bg-ok-500 text-xs font-bold text-white';
    dot.textContent = '✓';
  }

  // Roadside requests need the switch on; shop-visit bookings come in whenever the shop takes walk-ins.
  if (dash.dataset.online !== '1' && dash.dataset.walkin !== '1') return;

  const modal = document.getElementById('offer-modal');
  const body = document.getElementById('offer-body');
  const timerEl = document.getElementById('offer-timer');
  const errEl = document.getElementById('offer-error');
  let current = null, countdown, offerMap, offerPin, skipped = new Set(), ringer;

  // Share position while online (at most every 20 s).
  let lastSent = 0;
  // Skilled locals move around; a shop stays where it is.
  if (navigator.geolocation && dash.dataset.online === '1' && dash.dataset.sharesLocation === '1') {
    navigator.geolocation.watchPosition((p) => {
      if (Date.now() - lastSent < 20000) return;
      lastSent = Date.now();
      api('location.php', { lat: p.coords.latitude, lng: p.coords.longitude }).catch(() => {});
    }, () => {}, { enableHighAccuracy: true, maximumAge: 15000 });
  }

  function show(o) {
    current = o;
    errEl.classList.add('hidden');
    document.getElementById('offer-title').lastChild.textContent = o.mode === 'walk_in' ? ' New shop visit booking!' : ' New roadside request nearby!';
    body.innerHTML = `
      <p class="mb-3 inline-block rounded-full px-2.5 py-1 text-xs font-bold ${o.mode === 'walk_in' ? 'bg-green-50 text-green-700' : 'bg-brand-50 text-brand-700'}">
        ${o.mode === 'walk_in' ? 'Customer will come to your shop' : 'Go to the customer'}</p>
      <div class="flex items-center gap-3">
        <span class="grid h-12 w-12 place-items-center rounded-full bg-brand-100 font-bold text-brand-700">${esc(o.initials)}</span>
        <div class="min-w-0 flex-1"><p class="truncate font-bold">${esc(o.customer)}</p>
          <p class="truncate text-sm text-gray-500">${esc(o.service)} · ${esc(o.vehicle)}${o.customer_type ? ' · ' + esc(o.customer_type) : ''}</p></div>
        <span class="rounded-xl bg-green-50 px-3 py-1.5 text-sm font-bold text-green-700 ring-1 ring-green-200">${peso(o.price)}</span>
      </div>
      <dl class="mt-4 space-y-2 rounded-2xl bg-gray-50 p-4 text-sm">
        <div class="flex gap-2"><dt class="w-20 shrink-0 text-gray-500">Distance</dt><dd class="font-semibold">${o.distance_km} km away</dd></div>
        ${o.directions ? `<div class="flex gap-2"><dt class="w-20 shrink-0 text-gray-500">Directions</dt><dd>${esc(o.directions)}</dd></div>` : ''}
        ${o.municipality ? `<div class="flex gap-2"><dt class="w-20 shrink-0 text-gray-500">Town</dt><dd class="font-semibold">${esc(o.municipality)}</dd></div>` : ''}
        ${o.address ? `<div class="flex gap-2"><dt class="w-20 shrink-0 text-gray-500">Landmark</dt><dd class="font-semibold">${esc(o.address)}</dd></div>` : ''}
        ${o.notes ? `<div class="flex gap-2"><dt class="w-20 shrink-0 text-gray-500">Notes</dt><dd>${esc(o.notes)}</dd></div>` : ''}
      </dl>`;
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    if (!offerMap) {
      offerMap = VMap.create('offer-map', [o.lat, o.lng], 16);
      offerPin = L.marker([o.lat, o.lng], { icon: VMap.icon('me') }).addTo(offerMap);
    } else {
      offerMap.invalidateSize();
      offerMap.setView([o.lat, o.lng], 16);
      offerPin.setLatLng([o.lat, o.lng]);
    }
    setTimeout(() => offerMap.invalidateSize(), 50);

    let left = o.seconds_left;
    clearInterval(countdown);
    const tick = () => {
      timerEl.textContent = left + 's';
      if (left-- <= 0) hide(true);
    };
    tick();
    countdown = setInterval(tick, 1000);
    Alerts.alert('job', o.mode === 'walk_in' ? 'New shop visit booking' : 'New job request nearby!', `${o.service} · ${o.vehicle} · ${o.distance_km} km away`);
    clearInterval(ringer);
    ringer = setInterval(() => Alerts.ring('job'), 5000);
  }

  function hide(skip) {
    if (skip && current) skipped.add(current.key);
    clearInterval(countdown);
    clearInterval(ringer);
    current = null;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }

  async function respond(action) {
    if (!current) return;
    const btns = modal.querySelectorAll('button');
    btns.forEach((b) => (b.disabled = true));
    try {
      const res = await api('offer_respond.php', { offer_id: current.offer_id, action });
      if (action === 'accept') { location.href = VULCATIC.base + 'provider_job.php?id=' + res.job_id; return; }
      hide(true);
    } catch (e) {
      errEl.textContent = e.message;
      errEl.classList.remove('hidden');
      setTimeout(() => hide(true), 1800);
    } finally {
      btns.forEach((b) => (b.disabled = false));
    }
  }
  document.getElementById('offer-accept').addEventListener('click', () => respond('accept'));
  document.getElementById('offer-decline').addEventListener('click', () => respond('decline'));

  poll(async () => {
    const data = await api('provider_poll.php');
    if (data.active_job_id && !('stay' in dash.dataset)) { location.href = VULCATIC.base + 'provider_job.php?id=' + data.active_job_id; return; }
    const next = data.offers.find((o) => !skipped.has(o.key));
    if (current && !data.offers.some((o) => o.key === current.key)) {
      hide(false);
      toast('That request was taken or cancelled.', 'error');
    }
    if (!current && next) show(next);
  }, 4000);
})();
