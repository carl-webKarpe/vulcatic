// Customer home: live map of nearby help + the request form.
(() => {
  const map = VMap.create('map', null, 13);
  const me = L.marker(VMap.fallback, { icon: VMap.icon('me'), draggable: true, zIndexOffset: 1000 }).addTo(map);
  const layer = L.layerGroup().addTo(map);
  let haveLocation = false;
  let lastFetch = 0;

  const list = document.getElementById('nearby-list');
  const count = document.getElementById('online-count');
  const hint = document.getElementById('map-hint');

  function setMe(latlng, recenter = true) {
    me.setLatLng(latlng);
    haveLocation = true;
    const ll = me.getLatLng();
    areaBox.classList.toggle('hidden', VMap.inArea([ll.lat, ll.lng]));
    if (recenter) map.setView(latlng, Math.max(map.getZoom(), 15));
    loadNearby();
  }

  const areaBox = document.getElementById('area-warning');
  me.on('dragend', () => { setMe(me.getLatLng(), false); });
  map.on('click', (e) => setMe(e.latlng, false));

  async function useMyLocation() {
    try {
      const pos = await VMap.locate();
      setMe(pos);
      hint.textContent = VMap.inArea(pos) ? 'Not quite right? Drag the orange pin.' : 'You seem to be outside Siargao. Drag the pin to your spot.';
    } catch (e) {
      hint.textContent = e.message;
    }
  }
  document.getElementById('locate-btn').addEventListener('click', useMyLocation);
  useMyLocation();

  async function loadNearby() {
    const stamp = ++lastFetch;
    const { lat, lng } = me.getLatLng();
    try {
      const data = await api(`nearby.php?lat=${lat}&lng=${lng}`);
      if (stamp !== lastFetch) return;
      render(data.providers);
      // Remember nearby shops for when the signal drops (used by offline mode).
      try {
        localStorage.setItem('vulcatic-shops', JSON.stringify({ at: Date.now(), center: [lat, lng],
          shops: data.providers.filter((p) => p.walk_in) }));
      } catch (e) {}
    } catch (e) {
      list.innerHTML = `<li class="py-6 text-center text-red-600">${esc(e.message)}</li>`;
    }
  }

  function render(providers) {
    layer.clearLayers();
    const coming = providers.filter((p) => p.can_come);
    count.textContent = coming.length ? `${coming.length} vulcator${coming.length > 1 ? 's' : ''} online` : 'None online nearby';
    count.className = 'shrink-0 rounded-full px-3 py-1 text-xs font-semibold ' +
      (coming.length ? 'bg-green-50 text-green-700 ring-1 ring-green-200' : 'bg-amber-50 text-amber-700 ring-1 ring-amber-200');

    providers.forEach((p) => {
      const kind = p.online && p.onsite ? 'vulc' : p.walk_in ? 'shop' : 'off';
      L.marker([p.lat, p.lng], { icon: VMap.icon(kind) })
        .bindPopup(`<b>${esc(p.name)}</b><br>${p.distance_km} km away${p.hours ? '<br>' + esc(p.hours) : ''}`)
        .addTo(layer);
    });

    if (!providers.length) {
      list.innerHTML = '<li class="py-6 text-center text-gray-400">No vulcators or shops within 15 km yet.</li>';
      return;
    }
    list.innerHTML = providers.slice(0, 8).map((p) => `
      <li class="flex items-center gap-3 py-3">
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full text-xs font-bold text-white ${p.online && p.onsite ? 'bg-ink-900' : 'bg-ok-600'}">
          ${esc(p.name.split(/\s+/).map((w) => w[0]).slice(0, 2).join('').toUpperCase())}
        </span>
        <div class="min-w-0 flex-1">
          <p class="truncate font-semibold">${esc(p.name)}</p>
          <p class="truncate text-xs text-gray-500">
            ${p.can_come ? '<span class="font-semibold text-green-600">Online · can come to you</span>' : p.walk_in ? 'Walk-in shop' : 'Offline'}
            ${p.rating_count ? ` · ★ ${p.rating.toFixed(1)}` : ''}${p.hours ? ' · ' + esc(p.hours) : ''}
          </p>
        </div>
        <span class="shrink-0 text-xs font-semibold text-gray-600">${p.distance_km < 1 ? Math.round(p.distance_km * 1000) + ' m' : p.distance_km.toFixed(1) + ' km'}</span>
      </li>`).join('');
  }

  // Submit the request
  const form = document.getElementById('request-form');
  const errorBox = document.getElementById('request-error');
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    errorBox.classList.add('hidden');
    if (!haveLocation) {
      errorBox.textContent = 'Set your location first: tap "Use my location" or tap the map.';
      errorBox.classList.remove('hidden');
      return;
    }
    const btn = document.getElementById('request-btn');
    btn.disabled = true;
    const fd = new FormData(form);
    const { lat, lng } = me.getLatLng();
    try {
      const res = await api('job_create.php', {
        lat, lng,
        service_id: Number(fd.get('service_id')),
        vehicle_type: fd.get('vehicle_type'),
        address: fd.get('address'),
        notes: fd.get('notes'),
      });
      location.href = VULCATIC.base + 'job.php?id=' + res.job_id;
    } catch (err) {
      errorBox.textContent = err.message;
      errorBox.classList.remove('hidden');
      btn.disabled = false;
    }
  });
})();
