// Admin: requests-by-town map and confirm prompts.
(() => {
  const el = document.getElementById('town-map');
  if (el) {
    const towns = JSON.parse(el.dataset.towns);
    const map = VMap.create(el, [9.88, 126.07], 10);
    const max = Math.max(1, ...towns.map((t) => t.requests));
    towns.forEach((t) => {
      L.circleMarker([t.lat, t.lng], {
        radius: 6 + Math.sqrt(t.requests / max) * 22,
        color: '#fff', weight: 2, fillColor: t.requests ? '#E8501E' : '#9CA3AF', fillOpacity: t.requests ? 0.75 : 0.5,
      }).bindTooltip(`<b>${esc(t.name)}</b><br>${t.requests} requests · ${t.paid} paid<br>${t.vulcators} vulcator${t.vulcators === 1 ? '' : 's'}`)
        .addTo(map);
    });
    VMap.fit(map, towns.map((t) => [t.lat, t.lng]), 11);
  }
  document.querySelectorAll('[data-confirm]').forEach((b) =>
    b.addEventListener('click', (e) => { if (!confirm(b.dataset.confirm)) e.preventDefault(); }));
})();
