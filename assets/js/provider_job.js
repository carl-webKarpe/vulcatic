// Vulcator: drive to the customer, fix the tire, collect payment.
(() => {
  const root = document.getElementById('pjob');
  const jobId = Number(root.dataset.jobId);
  const panel = document.getElementById('panel');
  const dir = document.getElementById('directions');
  const map = VMap.create('map', null, 15);
  let custMarker, meMarker, line, fitted = false, lastKey = '', poller, busy = false;

  const icon = {
    check: '<svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>',
    phone: '<svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg>',
    msg: '<svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
  };
  const card = (html, extra = '') => `<div class="sheet-enter rounded-3xl bg-white p-5 shadow-sm ring-1 ring-black/5 ${extra}">${html}</div>`;
  const row = (label, value, strong = false) =>
    `<div class="flex justify-between gap-4 py-1.5 ${strong ? 'border-t border-white/15 pt-3 text-lg font-bold' : 'text-sm'}"><span class="${strong ? '' : 'opacity-70'}">${label}</span><span class="text-right font-semibold">${value}</span></div>`;

  // What the big button does at each status.
  const NEXT = {
    accepted:   ['start',  'Start navigation', 'bg-brand-500 hover:bg-brand-600 shadow-brand-500/30'],
    on_the_way: ['arrive', "I've arrived", 'bg-brand-500 hover:bg-brand-600 shadow-brand-500/30'],
    arrived:    ['fixed',  'Tire fixed, collect payment', 'bg-ok-500 hover:bg-ok-600 shadow-green-500/30'],
  };

  function checklist(j) {
    const s = j.step; // 2 accepted, 3 on the way, 4 arrived, 5 completed, 6 paid
    const items = [
      ['Job accepted', s >= 2, false],
      ['Navigate to customer', s >= 4, s === 2 || s === 3],
      ['Fix the tire', s >= 5, s === 4],
      ['Collect payment', s >= 6, s === 5],
    ];
    return `<ul class="space-y-3">${items.map(([t, done, now]) => `
      <li class="flex items-center gap-3">
        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full ${done ? 'bg-ok-500 text-white' : now ? 'bg-brand-50 ring-2 ring-brand-500' : 'bg-gray-100'}">${done ? icon.check : ''}</span>
        <span class="flex-1 text-sm font-semibold ${done || now ? '' : 'text-gray-400'}">${t}</span>
        ${now ? `<span class="text-xs font-semibold text-brand-500">${s === 3 ? 'On the way' : 'Now'}</span>` : ''}
      </li>`).join('')}</ul>`;
  }

  function customerCard(j) {
    const c = j.customer;
    return card(`
      <div class="flex items-center gap-3">
        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-brand-100 font-bold text-brand-700">${esc(c.initials)}</span>
        <div class="min-w-0 flex-1"><p class="truncate font-bold">${esc(c.name)}</p>
          <p class="truncate text-sm text-gray-500">${esc(j.service)} · ${esc(j.vehicle)}</p></div>
        <span class="rounded-xl bg-green-50 px-3 py-1.5 text-sm font-bold text-green-700 ring-1 ring-green-200">${peso(j.total)}</span>
      </div>
      ${j.address || j.notes || j.municipality ? `<dl class="mt-4 space-y-2 rounded-2xl bg-gray-50 p-4 text-sm">
        ${j.municipality ? `<div class="flex gap-2"><dt class="w-20 shrink-0 text-gray-500">Town</dt><dd class="font-semibold">${esc(j.municipality)}</dd></div>` : ''}
        ${j.address ? `<div class="flex gap-2"><dt class="w-20 shrink-0 text-gray-500">Landmark</dt><dd class="font-semibold">${esc(j.address)}</dd></div>` : ''}
        ${j.notes ? `<div class="flex gap-2"><dt class="w-20 shrink-0 text-gray-500">Notes</dt><dd>${esc(j.notes)}</dd></div>` : ''}</dl>` : ''}
      <div class="mt-4 grid grid-cols-2 gap-2">
        <a href="tel:${esc(c.phone)}" class="flex items-center justify-center gap-2 rounded-xl py-2.5 text-sm font-semibold ring-1 ring-gray-200 hover:bg-gray-50">${icon.phone} Call ${esc(c.first)}</a>
        <a href="sms:${esc(c.phone)}" class="flex items-center justify-center gap-2 rounded-xl py-2.5 text-sm font-semibold ring-1 ring-gray-200 hover:bg-gray-50">${icon.msg} Message</a>
      </div>`);
  }

  function collectView(j) {
    const pay = j.payment;
    const status = !pay
      ? `<p class="text-sm text-white/70">Waiting for ${esc(j.customer.first)} to choose how to pay. You can also collect cash directly.</p>`
      : pay.method === 'gcash'
        ? `<p class="text-sm">${esc(j.customer.first)} paid with <b>GCash</b></p><p class="mt-1 rounded-lg bg-white/10 px-3 py-2 font-mono text-sm">Ref ${esc(pay.reference)}</p><p class="mt-2 text-xs text-white/60">Check your GCash app for this reference before confirming.</p>`
        : `<p class="text-sm">${esc(j.customer.first)} will pay <b>cash</b>.</p>`;
    return card(`<p class="text-lg font-bold">Collect payment</p><p class="text-sm text-gray-500">Job done. Collect from ${esc(j.customer.first)}.</p>
        <div class="mt-4">${checklist(j)}</div>`) +
      `<div class="sheet-enter rounded-3xl bg-ink-900 p-5 text-white">
        <p class="text-xs font-bold uppercase tracking-wide text-white/50">Amount to collect</p>
        ${row('Service total', peso(j.total))}
        <p class="mt-1 text-4xl font-bold text-ok-500">${peso(j.total)}</p>
        <p class="mb-4 text-xs text-white/60">You earn ${peso(j.earning)} after the ${peso(j.platform_fee)} platform fee</p>
        ${status}
      </div>
      <button data-paid class="w-full rounded-2xl bg-ok-500 py-4 font-semibold text-white shadow-lg shadow-green-500/30 hover:bg-ok-600">Mark as payment received</button>`;
  }

  function earningsView(j, today) {
    return `<div class="sheet-enter overflow-hidden rounded-3xl bg-ink-900 text-white">
        <div class="relative flex items-end justify-between bg-white px-5 pb-4 pt-5 text-ink-900">
          <div><p class="text-lg font-bold">Job completed!</p><p class="text-sm font-semibold text-brand-500">Earnings confirmed</p></div>
          <img src="${root.dataset.gecko}" alt="" class="gecko-pop -mb-4 h-24 w-auto">
        </div>
        <div class="p-5 text-center">
          <span class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-ok-500/20 text-ok-500">${icon.check}</span>
          <p class="mt-2 text-sm text-white/70">Payment received</p>
          <p class="text-4xl font-bold text-ok-500">+ ${peso(j.earning)}</p>
          <p class="text-xs text-white/50">Credited to your earnings</p>
        </div>
      </div>` +
      card(`<div class="flex items-center justify-between"><div><p class="text-sm text-gray-500">Today's earnings</p>
        <p class="text-2xl font-bold">${peso(today.earned)}</p></div><span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold">${today.jobs} job${today.jobs === 1 ? '' : 's'}</span></div>`) +
      `<div class="sheet-enter rounded-3xl bg-ink-900 p-5 text-white">
        <p class="mb-2 text-xs font-bold uppercase tracking-wide text-white/50">Job summary</p>
        ${row('Customer', esc(j.customer.name))}${row('Service', esc(j.service))}
        ${j.duration_min ? row('Duration', j.duration_min + ' min') : ''}
        ${row('Gross', peso(j.total))}${row('Platform fee', '− ' + peso(j.platform_fee))}
        ${row('Net earned', `<span class="text-ok-500">${peso(j.earning)}</span>`, true)}
      </div>
      <a href="${VULCATIC.base}dashboard.php" class="block rounded-2xl bg-brand-500 py-4 text-center font-semibold text-white hover:bg-brand-600">Back to dashboard</a>`;
  }

  function render(j, today) {
    if (j.status === 'cancelled') {
      panel.innerHTML = card(`<div class="py-4 text-center"><p class="text-lg font-bold">The customer cancelled</p>
        <p class="mt-1 text-sm text-gray-500">No worries. You're free for the next job.</p>
        <a href="${VULCATIC.base}dashboard.php" class="mt-5 inline-block rounded-xl bg-brand-500 px-6 py-3 font-semibold text-white">Back to dashboard</a></div>`);
      return;
    }
    if (j.status === 'paid') { panel.innerHTML = earningsView(j, today); return; }
    if (j.status === 'completed') { panel.innerHTML = collectView(j); bind(); return; }

    const [action, label, color] = NEXT[j.status];
    panel.innerHTML = customerCard(j) +
      card(`<p class="mb-4 text-sm font-semibold text-gray-500">Job checklist</p>${checklist(j)}`) +
      `<button data-action="${action}" class="w-full rounded-2xl py-4 font-semibold text-white shadow-lg ${color}">${label}</button>`;
    bind();
  }

  async function advance(action, btn) {
    if (busy) return;
    busy = true;
    btn.disabled = true;
    try {
      await api('job_advance.php', { id: jobId, action });
      lastKey = '';
      poller.now();
    } catch (e) {
      toast(e.message, 'error');
      btn.disabled = false;
      lastKey = '';
      poller.now();
    } finally {
      busy = false;
    }
  }

  function bind() {
    panel.querySelector('[data-action]')?.addEventListener('click', (e) => advance(e.target.dataset.action, e.target));
    panel.querySelector('[data-paid]')?.addEventListener('click', (e) => {
      if (confirm('Confirm you received the payment?')) advance('paid', e.target);
    });
  }

  function updateMap(j) {
    const cust = [j.lat, j.lng];
    const me = [j.provider.lat, j.provider.lng];
    dir.href = `https://www.google.com/maps/dir/?api=1&destination=${j.lat},${j.lng}&travelmode=driving`;
    dir.classList.toggle('hidden', !['accepted', 'on_the_way'].includes(j.status));
    dir.classList.toggle('flex', ['accepted', 'on_the_way'].includes(j.status));
    if (!custMarker) custMarker = L.marker(cust, { icon: VMap.icon('me'), zIndexOffset: 1000 }).bindPopup(esc(j.customer.name)).addTo(map);
    if (!meMarker) meMarker = L.marker(me, { icon: VMap.icon('vulc') }).addTo(map);
    else meMarker.setLatLng(me);
    if (!line) line = L.polyline([me, cust], { color: '#E8501E', weight: 4, dashArray: '8 10' }).addTo(map);
    else line.setLatLngs([me, cust]);
    if (!fitted) { VMap.fit(map, [me, cust]); fitted = true; }
  }

  // Send our position while heading to the customer, so they see us move.
  let lastSent = 0, status = '';
  if (navigator.geolocation) {
    navigator.geolocation.watchPosition((p) => {
      if (!['accepted', 'on_the_way'].includes(status) || Date.now() - lastSent < 10000) return;
      lastSent = Date.now();
      api('location.php', { lat: p.coords.latitude, lng: p.coords.longitude }).catch(() => {});
    }, () => {}, { enableHighAccuracy: true, maximumAge: 5000 });
  }

  async function refresh() {
    const { job: j, today } = await api('job_status.php?id=' + jobId);
    status = j.status;
    updateMap(j);
    const key = [j.status, j.payment?.method, j.payment?.reference].join('|');
    if (key !== lastKey && !busy) { lastKey = key; render(j, today); }
    if (['paid', 'cancelled'].includes(j.status)) poller.stop();
  }
  poller = poll(refresh, 4000);
})();
