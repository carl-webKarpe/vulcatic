// Vulcator dashboard: share location while online and pop up new job requests.
(() => {
  const dash = document.getElementById('dashboard');
  if (dash.dataset.online !== '1') return;

  const modal = document.getElementById('offer-modal');
  const body = document.getElementById('offer-body');
  const timerEl = document.getElementById('offer-timer');
  const errEl = document.getElementById('offer-error');
  let current = null, countdown, offerMap, offerPin, skipped = new Set(), ringer;

  // Share position while online (at most every 20 s).
  let lastSent = 0;
  if (navigator.geolocation) {
    navigator.geolocation.watchPosition((p) => {
      if (Date.now() - lastSent < 20000) return;
      lastSent = Date.now();
      api('location.php', { lat: p.coords.latitude, lng: p.coords.longitude }).catch(() => {});
    }, () => {}, { enableHighAccuracy: true, maximumAge: 15000 });
  }

  function show(o) {
    current = o;
    errEl.classList.add('hidden');
    body.innerHTML = `
      <div class="flex items-center gap-3">
        <span class="grid h-12 w-12 place-items-center rounded-full bg-brand-100 font-bold text-brand-700">${esc(o.initials)}</span>
        <div class="min-w-0 flex-1"><p class="truncate font-bold">${esc(o.customer)}</p>
          <p class="truncate text-sm text-gray-500">${esc(o.service)} · ${esc(o.vehicle)}</p></div>
        <span class="rounded-xl bg-green-50 px-3 py-1.5 text-sm font-bold text-green-700 ring-1 ring-green-200">${peso(o.price)}</span>
      </div>
      <dl class="mt-4 space-y-2 rounded-2xl bg-gray-50 p-4 text-sm">
        <div class="flex gap-2"><dt class="w-20 shrink-0 text-gray-500">Distance</dt><dd class="font-semibold">${o.distance_km} km · about ${o.eta_min} min</dd></div>
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
    Alerts.alert('job', 'New job request nearby!', `${o.service} · ${o.vehicle} · ${o.distance_km} km away`);
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
    if (data.active_job_id) { location.href = VULCATIC.base + 'provider_job.php?id=' + data.active_job_id; return; }
    const next = data.offers.find((o) => !skipped.has(o.key));
    if (current && !data.offers.some((o) => o.key === current.key)) {
      hide(false);
      toast('That request was taken or cancelled.', 'error');
    }
    if (!current && next) show(next);
  }, 4000);
})();
