// Shop owner, skilled local or shop employee: work a job from Accepted to Completed.
(() => {
  const root = document.getElementById('pjob');
  const jobId = Number(root.dataset.jobId);
  const home = root.dataset.home;
  const panel = document.getElementById('panel');
  const dir = document.getElementById('directions');
  const map = VMap.create('map', null, 15);
  let custMarker, meMarker, line, fitted = false, lastKey = '', poller, busy = false, lastPay = undefined, lastState = null;
  let state = null, chatOpen = false, unread = 0, lastMsgId = null, messages = [];
  const icon = {
    check: '<svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>',
    phone: '<svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg>',
    msg: '<svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
    chat: '<svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.4-4 8-9 8a9.9 9.9 0 0 1-4-.8L3 20l1.3-3.9A7.7 7.7 0 0 1 3 12c0-4.4 4-8 9-8s9 3.6 9 8z"/></svg>',
  };
  const card = (html, extra = '') => `<div class="sheet-enter rounded-3xl bg-white p-5 shadow-sm ring-1 ring-black/5 ${extra}">${html}</div>`;
  const row = (label, value, strong = false) =>
    `<div class="flex justify-between gap-4 py-1.5 ${strong ? 'border-t border-white/15 pt-3 text-lg font-bold' : 'text-sm'}"><span class="${strong ? '' : 'opacity-70'}">${label}</span><span class="text-right font-semibold">${value}</span></div>`;


  // The main button at each status. Shop visits skip the drive.
  function nextStep(j) {
    const walk = j.mode === 'walk_in';
    return {
      accepted:    walk ? ['arrive', 'Customer arrived at the shop', 'bg-brand-500'] : ['start', 'Start trip to customer', 'bg-brand-500'],
      on_the_way:  ['arrive', "I've arrived", 'bg-brand-500'],
      arrived:     ['begin', 'Start repair', 'bg-brand-500'],
      in_progress: ['fixed', 'Repair done, collect payment', 'bg-ok-500'],
    }[j.status];
  }

  function checklist(j) {
    const walk = j.mode === 'walk_in';
    const order = ['accepted', 'on_the_way', 'arrived', 'in_progress', 'completed', 'paid'];
    const at = order.indexOf(j.status);
    const items = [
      ['Accepted', 'accepted'],
      ...(walk ? [] : [['Provider En Route', 'on_the_way']]),
      [walk ? 'Customer arrived' : 'Arrived', 'arrived'],
      ['Service In Progress', 'in_progress'],
      ['Completed', 'completed'],
      ['Payment collected', 'paid'],
    ];
    const reachedFlags = items.map(([, key]) => at >= order.indexOf(key));
    const nextIndex = reachedFlags.indexOf(false);
    return `<ul class="space-y-2.5">${items.map(([t, key], i) => {
      const reached = reachedFlags[i];
      const now = i === nextIndex;
      return `<li class="flex items-center gap-3">
        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full ${reached ? 'bg-ok-500 text-white' : now ? 'bg-brand-50 ring-2 ring-brand-500' : 'bg-gray-100'}">${reached ? icon.check : ''}</span>
        <span class="flex-1 text-sm font-semibold ${reached || now ? '' : 'text-gray-400'}">${t}</span>
        ${now ? '<span class="text-xs font-semibold text-brand-500">Next</span>' : ''}</li>`;
    }).join('')}</ul>`;
  }

  function customerCard(j) {
    const c = j.customer;
    return card(`
      <div class="flex items-center gap-3">
        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-brand-100 font-bold text-brand-700">${esc(c.initials)}</span>
        <div class="min-w-0 flex-1"><p class="truncate font-bold">${esc(c.name)}</p>
          <p class="truncate text-sm text-gray-500">${esc(j.problem_label)} · ${esc(j.vehicle)}${c.type ? ' · ' + esc(c.type) : ''}</p></div>
        <span class="rounded-xl bg-green-50 px-3 py-1.5 text-sm font-bold text-green-700 ring-1 ring-green-200">${peso(j.total)}</span>
      </div>
      <p class="mt-3 inline-block rounded-full px-2.5 py-1 text-xs font-bold ${j.mode === 'walk_in' ? 'bg-green-50 text-green-700' : 'bg-brand-50 text-brand-700'}">${j.mode === 'walk_in' ? 'Shop visit · customer comes to you' : 'Roadside repair · go to the customer'}</p>
      <dl class="mt-3 space-y-2 rounded-2xl bg-gray-50 p-4 text-sm">
        <div class="flex gap-2"><dt class="w-24 shrink-0 text-gray-500">Reference</dt><dd class="font-mono font-semibold">${esc(j.code)}</dd></div>
        ${j.municipality ? `<div class="flex gap-2"><dt class="w-24 shrink-0 text-gray-500">Town</dt><dd class="font-semibold">${esc(j.municipality)}</dd></div>` : ''}
        ${j.address ? `<div class="flex gap-2"><dt class="w-24 shrink-0 text-gray-500">Landmark</dt><dd class="font-semibold">${esc(j.address)}</dd></div>` : ''}
        ${j.directions ? `<div class="flex gap-2"><dt class="w-24 shrink-0 text-gray-500">Directions</dt><dd>${esc(j.directions)}</dd></div>` : ''}
        ${j.notes ? `<div class="flex gap-2"><dt class="w-24 shrink-0 text-gray-500">Notes</dt><dd>${esc(j.notes)}</dd></div>` : ''}
      </dl>
      <div class="mt-4 grid grid-cols-3 gap-2">
        <a href="tel:${esc(c.phone)}" class="flex items-center justify-center gap-1.5 rounded-xl py-2.5 text-sm font-semibold ring-1 ring-gray-200 hover:bg-gray-50">${icon.phone} Call</a>
        <a href="sms:${esc(c.phone)}" class="flex items-center justify-center gap-1.5 rounded-xl py-2.5 text-sm font-semibold ring-1 ring-gray-200 hover:bg-gray-50">${icon.msg} Text</a>
        <button type="button" data-chat class="relative flex items-center justify-center gap-1.5 rounded-xl bg-brand-500 py-2.5 text-sm font-semibold text-white hover:bg-brand-600">${icon.chat} Chat
          <span data-unread class="${unread ? '' : 'hidden'} absolute -right-1 -top-1 grid h-5 min-w-[1.25rem] place-items-center rounded-full bg-ink-900 px-1 text-[11px]">${unread}</span></button>
      </div>`);
  }

  // Shop owners: who does this job?
  function assignCard(j) {
    if (state.role !== 'owner' || !state.is_shop || !['accepted', 'on_the_way', 'arrived'].includes(j.status)) return '';
    const a = j.assignee;
    const opts = state.employees.map((e) => `<option value="${e.id}" ${a && a.id === e.id ? 'selected' : ''}>${esc(e.name)}${e.on_duty ? '' : ' (off duty)'}${e.busy ? ` · ${e.busy} job${e.busy > 1 ? 's' : ''}` : ''}</option>`).join('');
    const status = !a ? '<p class="text-sm text-gray-500">You\'re handling this job yourself.</p>'
      : a.status === 'pending' ? `<p class="text-sm text-amber-700">Waiting for <b>${esc(a.name)}</b> to accept…</p>`
      : `<p class="text-sm text-green-700"><b>${esc(a.name)}</b> accepted and is handling it.</p>`;
    return card(`<p class="font-bold">Assign to your team</p>${status}
      ${state.employees.length ? `<div class="mt-3 flex gap-2"><select id="assign-select" aria-label="Team member" class="min-w-0 flex-1 rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-sm">
        <option value="0">Me (I'll do it myself)</option>${opts}</select>
        <button data-assign class="rounded-xl bg-ink-900 px-4 text-sm font-semibold text-white">Assign</button></div>`
      : `<p class="mt-2 text-xs text-gray-500">Add employees on the <a class="font-semibold text-brand-600" href="${VULCATIC.base}team.php">Team</a> page to assign jobs.</p>`}`);
  }

  // Employees: answer the assignment first.
  function assignmentPrompt(j) {
    return `<div class="sheet-enter rounded-3xl bg-amber-50 p-5 ring-1 ring-amber-200">
      <p class="text-lg font-bold text-amber-900">New job assigned to you</p>
      <p class="text-sm text-amber-800">${esc(j.provider.name)} wants you to handle this ${j.mode === 'walk_in' ? 'shop visit' : 'roadside repair'}.</p>
      <div class="mt-4 grid grid-cols-2 gap-3">
        <button data-assignment="decline" class="rounded-2xl bg-white py-3 font-semibold text-red-600 ring-1 ring-red-200">Decline</button>
        <button data-assignment="accept" class="rounded-2xl bg-ok-500 py-3 font-semibold text-white shadow-lg shadow-green-500/30">Accept job</button>
      </div></div>`;
  }

  function etaCard(j) {
    if (j.status !== 'on_the_way' || !state.can_work) return '';
    return card(`<div class="flex items-center justify-between gap-3"><div><p class="font-bold">Your ETA</p>
      <p class="text-sm text-gray-500">${j.eta_min ? `The customer sees about <b>${j.eta_min} min</b>.` : 'Not shared yet. Let the customer know.'}</p></div></div>
      <div class="mt-3 flex flex-wrap gap-2">${[5, 10, 15, 20, 30, 45].map((m) => `<button data-eta="${m}" class="rounded-full bg-gray-100 px-3 py-1.5 text-sm font-semibold hover:bg-brand-50">${m} min</button>`).join('')}</div>`);
  }

  function chatPanel(j) {
    return `<div id="chat" class="sheet-enter ${chatOpen ? '' : 'hidden'} rounded-3xl bg-white p-4 shadow-sm ring-1 ring-black/5">
      <div class="mb-2 flex items-center justify-between"><p class="font-bold">Chat with ${esc(j.customer.first)}</p>
        <button type="button" data-chat class="text-sm font-semibold text-gray-500">Close</button></div>
      <ul id="chat-list" class="max-h-64 space-y-2 overflow-y-auto rounded-2xl bg-gray-50 p-3 text-sm"></ul>
      <div class="mt-2 flex flex-wrap gap-1.5">${(j.mode === 'walk_in' ? ['We\'re ready for you', 'Please come to the shop', 'Thank you!'] : ['On my way!', 'I\'m close, please stay there', 'I\'m here, where are you?']).map((q) => `<button type="button" data-quick="${esc(q)}" class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium hover:bg-gray-200">${esc(q)}</button>`).join('')}</div>
      <form id="chat-form" class="mt-2 flex gap-2"><input id="chat-input" maxlength="500" autocomplete="off" placeholder="Type a message…" aria-label="Message"
        class="min-w-0 flex-1 rounded-xl border border-gray-300 px-3 py-2.5 text-sm outline-none focus:border-brand-500">
        <button class="rounded-xl bg-brand-500 px-4 text-sm font-semibold text-white">Send</button></form></div>`;
  }

  function renderMessages() {
    const list = document.getElementById('chat-list');
    if (!list) return;
    list.innerHTML = messages.map((m) => `<li class="flex ${m.mine ? 'justify-end' : 'justify-start'}">
        <span class="max-w-[80%] rounded-2xl px-3 py-2 ${m.mine ? 'bg-brand-500 text-white' : 'bg-white ring-1 ring-gray-200'}">${esc(m.body)}
        <span class="mt-0.5 block text-[10px] ${m.mine ? 'text-white/70' : 'text-gray-400'}">${m.mine ? 'You' : esc(m.name)} · ${esc(m.time)}</span></span></li>`).join('')
      || '<li class="py-4 text-center text-xs text-gray-400">No messages yet.</li>';
    list.scrollTop = list.scrollHeight;
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
      (today ? card(`<div class="flex items-center justify-between"><div><p class="text-sm text-gray-500">Today's earnings</p>
        <p class="text-2xl font-bold">${peso(today.earned)}</p></div><span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold">${today.jobs} job${today.jobs === 1 ? '' : 's'}</span></div>`) : '') +
      `<div class="sheet-enter rounded-3xl bg-ink-900 p-5 text-white">
        <p class="mb-2 text-xs font-bold uppercase tracking-wide text-white/50">Job summary</p>
        ${row('Customer', esc(j.customer.name))}${row('Service', esc(j.service))}
        ${j.duration_min ? row('Duration', j.duration_min + ' min') : ''}
        ${row('Gross', peso(j.total))}${row('Platform fee', '− ' + peso(j.platform_fee))}
        ${row('Net earned', `<span class="text-ok-500">${peso(j.earning)}</span>`, true)}
      </div>
      <a href="${VULCATIC.base}${home}" class="block rounded-2xl bg-brand-500 py-4 text-center font-semibold text-white hover:bg-brand-600">Back to dashboard</a>`;
  }

  function render(j, today) {
    if (j.status === 'cancelled') {
      panel.innerHTML = card(`<div class="py-4 text-center"><p class="text-lg font-bold">The customer cancelled</p>
        <p class="mt-1 text-sm text-gray-500">No worries. You're free for the next job.</p>
        <a href="${VULCATIC.base}${home}" class="mt-5 inline-block rounded-xl bg-brand-500 px-6 py-3 font-semibold text-white">Back</a></div>`);
      return;
    }
    if (j.status === 'paid') { panel.innerHTML = earningsView(j, today); return; }
    if (state.role === 'employee' && j.assignee?.status === 'pending') {
      panel.innerHTML = assignmentPrompt(j) + customerCard(j) + chatPanel(j); bind(); return;
    }
    if (j.status === 'completed') { panel.innerHTML = collectView(j) + chatPanel(j); bind(); return; }

    const step = nextStep(j);
    const button = !state.can_work
      ? `<p class="rounded-2xl bg-gray-50 px-4 py-3 text-center text-sm text-gray-600">${j.assignee ? `${esc(j.assignee.name)} will update the status once they accept.` : 'Waiting…'}</p>`
      : `<button data-action="${step[0]}" class="w-full rounded-2xl py-4 font-semibold text-white shadow-lg ${step[2]} hover:opacity-90">${step[1]}</button>`;
    panel.innerHTML = customerCard(j) + chatPanel(j) + assignCard(j) + etaCard(j) +
      card(`<p class="mb-4 text-sm font-semibold text-gray-500">Booking status</p>${checklist(j)}`) + button;
    bind();
  }

  async function advance(action, btn, extra = {}) {
    if (busy) return;
    busy = true;
    if (btn) btn.disabled = true;
    try {
      await api('job_advance.php', { id: jobId, action, ...extra });
    } catch (e) {
      toast(e.message, 'error');
    } finally {
      busy = false; lastKey = ''; poller.now();
    }
  }

  // ETA picker when starting the trip (optional: the customer only sees an ETA if you give one).
  function askEta() {
    return new Promise((resolve) => {
      const wrap = document.createElement('div');
      wrap.className = 'fixed inset-0 z-[1200] flex items-end justify-center bg-ink-900/60 p-3 sm:items-center';
      wrap.innerHTML = `<div class="sheet-enter w-full max-w-sm rounded-3xl bg-white p-6 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="eta-t">
        <p id="eta-t" class="text-lg font-bold">How long until you arrive?</p>
        <p class="mt-1 text-sm text-gray-500">The customer sees this as your ETA. You can change it later.</p>
        <div class="mt-4 grid grid-cols-3 gap-2">${[5, 10, 15, 20, 30, 45].map((m) => `<button data-m="${m}" class="rounded-xl bg-gray-100 py-3 font-semibold hover:bg-brand-50">${m} min</button>`).join('')}</div>
        <button data-m="0" class="mt-3 w-full rounded-xl py-2.5 text-sm font-semibold text-gray-500 ring-1 ring-gray-200">Skip, don't share an ETA</button></div>`;
      wrap.addEventListener('click', (e) => {
        const b = e.target.closest('[data-m]');
        if (b) { wrap.remove(); resolve(Number(b.dataset.m) || null); }
        else if (e.target === wrap) { wrap.remove(); resolve(undefined); }
      });
      document.body.appendChild(wrap);
    });
  }

  function bind() {
    panel.querySelector('[data-action]')?.addEventListener('click', async (e) => {
      const action = e.target.dataset.action;
      if (action === 'start') {
        const eta = await askEta();
        if (eta === undefined) return;
        window.open(dir.href, '_blank', 'noopener');
        return advance('start', e.target, { eta });
      }
      advance(action, e.target);
    });
    panel.querySelectorAll('[data-eta]').forEach((b) => b.addEventListener('click', () => advance('eta', b, { eta: Number(b.dataset.eta) })));
    panel.querySelector('[data-paid]')?.addEventListener('click', async (e) => {
      if (await confirmSheet({ title: 'Payment received?', text: 'Only confirm once the cash is in your hand or the GCash payment shows in your app.', confirm: 'Yes, received' })) advance('paid', e.target);
    });
    panel.querySelector('[data-assign]')?.addEventListener('click', async (e) => {
      e.target.disabled = true;
      try { await api('assign.php', { id: jobId, user_id: Number(document.getElementById('assign-select').value) }); toast('Assignment saved'); }
      catch (ex) { toast(ex.message, 'error'); }
      lastKey = ''; poller.now();
    });
    panel.querySelectorAll('[data-assignment]').forEach((b) => b.addEventListener('click', async () => {
      try {
        await api('assignment_respond.php', { id: jobId, action: b.dataset.assignment });
        if (b.dataset.assignment === 'decline') { location.href = VULCATIC.base + home; return; }
      } catch (ex) { toast(ex.message, 'error'); }
      lastKey = ''; poller.now();
    }));
    // chat
    panel.querySelectorAll('[data-chat]').forEach((b) => b.addEventListener('click', () => {
      chatOpen = !chatOpen; unread = 0;
      document.getElementById('chat')?.classList.toggle('hidden', !chatOpen);
      panel.querySelectorAll('[data-unread]').forEach((u) => u.classList.add('hidden'));
      if (chatOpen) { renderMessages(); document.getElementById('chat-input')?.focus(); }
    }));
    const send = async (body) => {
      if (!body.trim()) return;
      try { messages = (await api('message_send.php', { id: jobId, body })).messages; lastMsgId = messages.at(-1)?.id; renderMessages(); }
      catch (ex) { toast(ex.message, 'error'); }
    };
    document.getElementById('chat-form')?.addEventListener('submit', (e) => { e.preventDefault(); const i = document.getElementById('chat-input'); send(i.value); i.value = ''; });
    panel.querySelectorAll('[data-quick]').forEach((b) => b.addEventListener('click', () => send(b.dataset.quick)));
    if (chatOpen) renderMessages();
  }

  function updateMap(j) {
    const cust = [j.lat, j.lng];
    const me = [j.provider.lat, j.provider.lng];
    const driving = j.mode === 'onsite' && ['accepted', 'on_the_way'].includes(j.status);
    dir.href = `https://www.google.com/maps/dir/?api=1&destination=${j.lat},${j.lng}&travelmode=driving`;
    dir.classList.toggle('hidden', !driving);
    dir.classList.toggle('flex', driving);
    if (!custMarker) custMarker = L.marker(cust, { icon: VMap.icon('me'), zIndexOffset: 1000 }).bindPopup(esc(j.customer.name)).addTo(map);
    if (!meMarker) meMarker = L.marker(me, { icon: VMap.icon(j.provider.type === 'shop' ? 'shop' : 'vulc') }).addTo(map);
    else meMarker.setLatLng(me);
    if (!line) line = L.polyline([me, cust], { color: '#E8501E', weight: 4, dashArray: '8 10' }).addTo(map);
    else line.setLatLngs([me, cust]);
    if (!fitted) { VMap.fit(map, [me, cust]); fitted = true; }
  }

  // Skilled locals share their position while driving, so the customer sees them move.
  let lastSent = 0, status = '';
  if (navigator.geolocation && root.dataset.sharesLocation === '1') {
    navigator.geolocation.watchPosition((p) => {
      if (!['accepted', 'on_the_way'].includes(status) || Date.now() - lastSent < 10000) return;
      lastSent = Date.now();
      api('location.php', { lat: p.coords.latitude, lng: p.coords.longitude }).catch(() => {});
    }, () => {}, { enableHighAccuracy: true, maximumAge: 5000 });
  }

  async function refresh() {
    state = await api('job_status.php?id=' + jobId);
    const { job: j, today } = state;
    const payKey = j.payment ? j.payment.method + (j.payment.reference || '') : null;
    if (lastPay !== undefined && payKey && payKey !== lastPay) Alerts.alert('update', 'Customer paid', j.payment.method === 'gcash' ? `GCash · Ref ${j.payment.reference}` : 'Cash payment');
    if (lastState && lastState !== 'cancelled' && j.status === 'cancelled') Alerts.alert('update', 'Job cancelled', 'The customer cancelled the request.');
    lastPay = payKey; lastState = j.status; status = j.status;
    const newest = state.messages.at(-1)?.id ?? null;
    if (lastMsgId !== null && newest !== lastMsgId) {
      const fresh = state.messages.filter((m) => m.id > lastMsgId && !m.mine);
      if (fresh.length) { Alerts.alert('update', 'Message from ' + j.customer.first, fresh.at(-1).body); if (!chatOpen) unread += fresh.length; }
    }
    lastMsgId = newest ?? lastMsgId ?? 0;
    messages = state.messages;
    updateMap(j);
    const key = [j.status, j.payment?.method, j.payment?.reference, j.eta_min, j.assignee?.id, j.assignee?.status, state.can_work, (state.employees || []).map((e) => e.on_duty + ':' + e.busy).join(',')].join('|');
    if (key !== lastKey && !busy) { lastKey = key; render(j, today); }
    else {
      if (chatOpen) renderMessages();
      panel.querySelectorAll('[data-unread]').forEach((u) => { u.textContent = unread; u.classList.toggle('hidden', !unread); });
    }
    if (['paid', 'cancelled'].includes(j.status)) poller.stop();
  }
  poller = poll(refresh, 4000);
})();
