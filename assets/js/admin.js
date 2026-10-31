// Admin: phone drawer, clickable table rows, requests-by-town map.
(() => {
  // Slide-in menu on phones and tablets
  const drawer = document.getElementById('drawer');
  const backdrop = document.getElementById('drawer-backdrop');
  const openBtn = document.getElementById('drawer-open');
  const setOpen = (open) => {
    drawer?.classList.toggle('-translate-x-full', !open);
    backdrop?.classList.toggle('hidden', !open);
    openBtn?.setAttribute('aria-expanded', String(open));
    document.body.classList.toggle('overflow-hidden', open);
  };
  openBtn?.addEventListener('click', () => setOpen(true));
  backdrop?.addEventListener('click', () => setOpen(false));
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setOpen(false); });

  // Whole table row opens the job (links and buttons inside still work)
  document.querySelectorAll('tr[data-href]').forEach((tr) =>
    tr.addEventListener('click', (e) => { if (!e.target.closest('a, button, form')) location.href = tr.dataset.href; }));

  // Requests-by-town map
  const el = document.getElementById('town-map');
  if (el && window.L) {
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
})();
