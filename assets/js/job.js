// Motorist: follow a request from "Pending" to "Completed", chat, pay and rate.
(() => {
  const root = document.getElementById('job');
  const jobId = Number(root.dataset.jobId);
  const panel = document.getElementById('panel');
  const map = VMap.create('map', null, 15);
  let meMarker, vulcMarker, line, fitted = false, lastKey = '', poller;
  let payMethod = 'cash', stars = 0, lastStatus = null, lastMsgId = null, chatOpen = false, unread = 0, messages = [];
  const ALERTS = {
    accepted:    (j) => [j.mode === 'walk_in' ? 'Your visit is confirmed' : 'Help is coming!', `${j.provider.name} accepted your request.`],
    on_the_way:  (j) => ['On the way', `${who(j)} is heading to you${j.eta_min ? `, about ${j.eta_min} min` : ''}.`],
    arrived:     (j) => [j.mode === 'walk_in' ? 'You\'re checked in' : 'Your vulcator has arrived', j.mode === 'walk_in' ? 'The shop will start soon.' : `${who(j)} is with you now.`],
    in_progress: () => ['Repair started', 'Your tire is being fixed.'],
    completed:   () => ['Repair done!', 'Choose how to pay.'],
    paid:        () => ['Payment confirmed', 'Thanks for using Vulcatic!'],
    cancelled:   (j) => ['Request cancelled', j.mode === 'walk_in' ? 'You can pick another shop.' : ''],
  };
  const who = (j) => (j.assignee && j.assignee.status === 'accepted' ? j.assignee.first : j.provider?.name || 'Your vulcator');

  const card = (html, extra = '') => `<div class="sheet-enter rounded-3xl bg-white p-5 shadow-sm ring-1 ring-black/5 ${extra}">${html}</div>`;
  const row = (label, value, strong = false) =>
    `<div class="flex justify-between gap-4 py-1.5 ${strong ? 'border-t border-gray-100 pt-3 text-base font-bold' : 'text-sm'}"><span class="${strong ? '' : 'text-gray-500'}">${label}</span><span class="text-right font-semibold">${value}</span></div>`;
  const icon = {
    check: '<svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>',
    phone: '<svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg>',
    msg: '<svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
    chat: '<svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.4-4 8-9 8a9.9 9.9 0 0 1-4-.8L3 20l1.3-3.9A7.7 7.7 0 0 1 3 12c0-4.4 4-8 9-8s9 3.6 9 8z"/></svg>',
    pin: '<svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l19-9-9 19-2-8z"/></svg>',
  };

  // Timeline with the booking statuses. Shop visits skip "Provider En Route".
  function timeline(j) {
    const steps = [['searching', 'Pending', 'Request sent']];
    steps.push(['accepted', 'Accepted', j.mode === 'walk_in' ? 'Shop confirmed your visit' : 'A vulcator took your request']);
    if (j.mode !== 'walk_in') steps.push(['on_the_way', 'Provider En Route', 'Heading to you']);
    steps.push(['arrived', 'Arrived', j.mode === 'walk_in' ? 'You\'re at the shop' : 'Your vulcator is with you']);
    steps.push(['in_progress', 'Service In Progress', 'Fixing your tire']);
    steps.push(['completed', 'Completed', 'Repair done']);
    const order = ['searching', 'matched', 'accepted', 'on_the_way', 'arrived', 'in_progress', 'completed', 'paid'];
    const at = order.indexOf(j.status);
    const reached = (s) => at >= order.indexOf(s);
    // "Pending" counts as done once someone accepts; the first step not done is the current one.
    const doneFlags = steps.map(([key]) => reached(key) && !(key === 'searching' && ['searching', 'matched'].includes(j.status)));
    const currentIndex = doneFlags.indexOf(false);
    return `<ol>${steps.map(([key, title, sub], i) => {
      const done = doneFlags[i];
      const current = i === currentIndex;
      return `<li class="relative flex gap-3 pb-4 last:pb-0">
        ${i < steps.length - 1 ? `<span class="absolute left-[13px] top-7 h-[calc(100%-1.25rem)] w-0.5 ${done ? 'bg-ok-500' : 'bg-gray-200'}"></span>` : ''}
        <span class="relative z-10 grid h-7 w-7 shrink-0 place-items-center rounded-full text-xs font-bold
          ${done ? 'bg-ok-500 text-white' : current ? 'bg-brand-50 text-brand-600 ring-2 ring-brand-500' : 'bg-gray-100 text-gray-400'}">${done ? icon.check : i + 1}</span>
        <div class="pt-0.5"><p class="text-sm font-semibold ${done || current ? '' : 'text-gray-400'}">${title}</p>
          <p class="text-xs ${current ? 'font-medium text-brand-500' : 'text-gray-400'}">${current ? sub + '…' : done ? sub : 'Waiting'}</p></div>
      </li>`;
    }).join('')}</ol>`;
  }

  function headerCard(j) {
    const tone = { Pending: 'bg-amber-50 text-amber-700 ring-amber-200', Cancelled: 'bg-gray-100 text-gray-600 ring-gray-200' }[j.status_label] || 'bg-green-50 text-green-700 ring-green-200';
    return card(`<div class="flex items-start justify-between gap-3">
        <div class="min-w-0"><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Reference number</p>
          <button type="button" data-copy="${esc(j.code)}" class="whitespace-nowrap font-mono text-base font-bold hover:text-brand-600 sm:text-lg" title="Copy">${esc(j.code)}</button>
          <p class="mt-0.5 text-sm text-gray-500">${esc(j.problem_label)} · ${esc(j.vehicle)} · ${j.mode === 'walk_in' ? 'Shop visit' : 'Roadside repair'}</p></div>
        <span class="shrink-0 rounded-full px-3 py-1 text-xs font-bold ring-1 ${tone}">${esc(j.status_label)}</span>
      </div>`);
  }

  function providerCard(j) {
    const p = j.provider;
    const phone = j.contact_phone || p.phone;
    const assignee = j.assignee && j.assignee.status === 'accepted'
      ? `<p class="mt-3 rounded-xl bg-gray-50 px-3 py-2 text-sm"><b>${esc(j.assignee.name)}</b> from ${esc(p.name)} is handling your request.</p>` : '';
    const eta = j.mode === 'walk_in' ? ''
      : j.status === 'on_the_way' ? (j.eta_min
        ? `<div class="text-right"><p class="text-xl font-bold text-brand-500">${j.eta_min} min</p><p class="text-[11px] text-gray-400">ETA from provider</p></div>`
        : '<div class="text-right"><p class="text-sm font-semibold text-gray-500">On the way</p><p class="text-[11px] text-gray-400">No ETA given</p></div>')
      : j.status === 'arrived' || j.status === 'in_progress' ? '<div class="text-right"><p class="text-xl font-bold text-ok-600">Here</p></div>' : '';
    const visit = j.mode === 'walk_in' ? `<div class="mt-3 rounded-xl bg-gray-50 p-3 text-sm">
        <p class="font-semibold">${esc(p.address || 'Shop location on the map')}</p>
        ${p.hours ? `<p class="text-xs text-gray-500">${esc(p.hours)}</p>` : ''}
        <a target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&destination=${p.lat},${p.lng}" class="mt-2 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600">${icon.pin} Directions to the shop</a></div>` : '';
    return card(`<div class="flex items-center gap-3">
        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-ink-900 font-bold text-white">${esc(p.initials)}</span>
        <div class="min-w-0 flex-1"><p class="truncate font-bold">${esc(p.name)}</p>
          <p class="truncate text-xs text-gray-500">${p.type === 'shop' ? 'Vulcanizing shop' : 'Vulcator'} · ${p.rating_count ? `★ ${p.rating.toFixed(1)} (${p.rating_count})` : 'New'} · ${peso(j.total)}</p></div>
        ${eta}
      </div>${assignee}${visit}
      <div class="mt-4 grid grid-cols-3 gap-2">
        <a href="tel:${esc(phone)}" class="flex items-center justify-center gap-1.5 rounded-xl py-2.5 text-sm font-semibold ring-1 ring-gray-200 hover:bg-gray-50">${icon.phone} Call</a>
        <a href="sms:${esc(phone)}" class="flex items-center justify-center gap-1.5 rounded-xl py-2.5 text-sm font-semibold ring-1 ring-gray-200 hover:bg-gray-50">${icon.msg} Text</a>
        <button type="button" data-chat class="relative flex items-center justify-center gap-1.5 rounded-xl bg-brand-500 py-2.5 text-sm font-semibold text-white hover:bg-brand-600">${icon.chat} Chat
          <span data-unread class="${unread ? '' : 'hidden'} absolute -right-1 -top-1 grid h-5 min-w-[1.25rem] place-items-center rounded-full bg-ink-900 px-1 text-[11px]">${unread}</span></button>
      </div>`);
  }

  function chatPanel(j) {
    const quick = j.mode === 'walk_in' ? ['I\'m on my way to the shop', 'I\'m here', 'Thank you!'] : ['I\'m by the road', 'Please call when you\'re close', 'Thank you!'];
    return `<div id="chat" class="sheet-enter ${chatOpen ? '' : 'hidden'} rounded-3xl bg-white p-4 shadow-sm ring-1 ring-black/5">
      <div class="mb-2 flex items-center justify-between"><p class="font-bold">Chat with ${esc(who(j))}</p>
        <button type="button" data-chat class="text-sm font-semibold text-gray-500">Close</button></div>
      <ul id="chat-list" class="max-h-64 space-y-2 overflow-y-auto rounded-2xl bg-gray-50 p-3 text-sm"></ul>
      <div class="mt-2 flex flex-wrap gap-1.5">${quick.map((q) => `<button type="button" data-quick="${esc(q)}" class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium hover:bg-gray-200">${esc(q)}</button>`).join('')}</div>
      <form id="chat-form" class="mt-2 flex gap-2"><input id="chat-input" maxlength="500" autocomplete="off" placeholder="Type a message…" aria-label="Message"
        class="min-w-0 flex-1 rounded-xl border border-gray-300 px-3 py-2.5 text-sm outline-none focus:border-brand-500">
        <button class="rounded-xl bg-brand-500 px-4 text-sm font-semibold text-white">Send</button></form>
    </div>`;
  }

  function renderMessages() {
    const list = document.getElementById('chat-list');
    if (!list) return;
    list.innerHTML = messages.map((m) => `<li class="flex ${m.mine ? 'justify-end' : 'justify-start'}">
        <span class="max-w-[80%] rounded-2xl px-3 py-2 ${m.mine ? 'bg-brand-500 text-white' : 'bg-white ring-1 ring-gray-200'}">${esc(m.body)}
        <span class="mt-0.5 block text-[10px] ${m.mine ? 'text-white/70' : 'text-gray-400'}">${m.mine ? 'You' : esc(m.name)} · ${esc(m.time)}</span></span></li>`).join('')
      || '<li class="py-4 text-center text-xs text-gray-400">No messages yet. Say hi!</li>';
    list.scrollTop = list.scrollHeight;
  }

  function bindChat() {
    panel.querySelectorAll('[data-chat]').forEach((b) => b.addEventListener('click', () => {
      chatOpen = !chatOpen; unread = 0;
      document.getElementById('chat')?.classList.toggle('hidden', !chatOpen);
      panel.querySelectorAll('[data-unread]').forEach((u) => u.classList.add('hidden'));
      if (chatOpen) { renderMessages(); document.getElementById('chat-input')?.focus(); }
    }));
    const send = async (body) => {
      if (!body.trim()) return;
      try { messages = (await api('message_send.php', { id: jobId, body })).messages; lastMsgId = messages.at(-1)?.id; renderMessages(); }
      catch (e) { toast(e.message, 'error'); }
    };
    document.getElementById('chat-form')?.addEventListener('submit', (e) => { e.preventDefault(); const i = document.getElementById('chat-input'); send(i.value); i.value = ''; });
    panel.querySelectorAll('[data-quick]').forEach((b) => b.addEventListener('click', () => send(b.dataset.quick)));
    panel.querySelectorAll('[data-copy]').forEach((b) => b.addEventListener('click', () => { navigator.clipboard?.writeText(b.dataset.copy); toast('Reference number copied'); }));
    panel.querySelectorAll('[data-cancel]').forEach((b) => b.addEventListener('click', async () => {
      if (!(await confirmSheet({ title: 'Cancel this request?', text: 'The shop or vulcator will be told.', confirm: 'Yes, cancel', cancel: 'Keep it', danger: true }))) return;
      try { await api('job_cancel.php', { id: jobId }); lastKey = ''; poller.now(); } catch (e) { toast(e.message, 'error'); }
    }));
  }

  function render(j, log) {
    const canCancel = ['searching', 'matched', 'accepted', 'on_the_way'].includes(j.status);
    const cancelBtn = canCancel ? '<button data-cancel class="w-full rounded-2xl py-3 text-sm font-semibold text-red-600 ring-1 ring-red-200 hover:bg-red-50">Cancel request</button>' : '';
    let html = headerCard(j);

    if (j.status === 'cancelled') {
      html += card(`<div class="py-2 text-center"><p class="text-lg font-bold">Request cancelled</p>
        <p class="mt-1 text-sm text-gray-500">${esc(log.at(-1)?.note || '')}</p>
        <a href="${VULCATIC.base}home.php" class="mt-5 inline-block rounded-xl bg-brand-500 px-6 py-3 font-semibold text-white">${j.mode === 'walk_in' ? 'Choose another shop' : 'Request again'}</a></div>`);
    } else if (j.status === 'searching' || j.status === 'matched') {
      const waitingFor = j.mode === 'walk_in'
        ? 'Waiting for the shop to confirm your visit…'
        : j.status === 'matched' ? `${j.pending_offers} nearby vulcator${j.pending_offers === 1 ? '' : 's'} got your request.` : 'No vulcator is free nearby yet. We keep trying every few seconds.';
      html += card(`<div class="flex flex-col items-center py-2 text-center">
          <div class="relative grid h-24 w-24 place-items-center">
            <svg class="searching-ring absolute inset-0" viewBox="0 0 100 100"><circle cx="50" cy="50" r="44" fill="none" stroke="#FFE1D4" stroke-width="8"/><path d="M50 6a44 44 0 0 1 44 44" fill="none" stroke="#E8501E" stroke-width="8" stroke-linecap="round"/></svg>
            <img src="${VULCATIC.base}assets/img/gecko-head.png" alt="" class="h-14 w-14">
          </div>
          <p class="mt-3 text-lg font-bold">${j.mode === 'walk_in' ? 'Confirming with the shop' : 'Finding help near you'}</p>
          <p class="mt-1 text-sm text-gray-500">${esc(waitingFor)}</p>
          <p class="mt-2 text-xs text-gray-400">Estimated arrival is shown once a provider gives one.</p></div>`)
        + card(`<p class="mb-4 text-sm font-semibold text-gray-500">Request status</p>${timeline(j)}`) + cancelBtn;
    } else if (['accepted', 'on_the_way', 'arrived', 'in_progress'].includes(j.status)) {
      html += providerCard(j) + chatPanel(j) + card(`<p class="mb-4 text-sm font-semibold text-gray-500">Request status</p>${timeline(j)}`) + cancelBtn;
    } else if (j.status === 'completed') {
      html += payView(j);
    } else if (j.status === 'paid') {
      html += paidView(j);
    }
    panel.innerHTML = html;
    bindPanel(j);
    if (chatOpen) renderMessages();
  }
  function payView(j) {
    const pay = j.payment;
    const summary = card(`
      <p class="text-lg font-bold">Pay for service</p>
      <p class="text-sm text-gray-500">Job completed by ${esc(j.provider.name)}</p>
      <div class="mt-4 rounded-2xl bg-gray-50 p-4">
        <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-400">Service summary</p>
        ${row('Service', esc(j.service))}${row('Vulcator', esc(j.provider.name))}
        ${j.duration_min ? row('Duration', j.duration_min + ' min') : ''}
        ${row('Service fee', peso(j.service_fee))}${row('Convenience fee', peso(j.convenience_fee))}
        ${row('Total', `<span class="text-brand-600">${peso(j.total)}</span>`, true)}
      </div>`);

    if (pay) {
      return summary + card(`<div class="flex items-center gap-3">
          <span class="grid h-10 w-10 place-items-center"><svg class="searching-ring h-8 w-8" viewBox="0 0 100 100"><circle cx="50" cy="50" r="40" fill="none" stroke="#FFE1D4" stroke-width="12"/><path d="M50 10a40 40 0 0 1 40 40" fill="none" stroke="#E8501E" stroke-width="12" stroke-linecap="round"/></svg></span>
          <div><p class="font-semibold">Waiting for ${esc(j.provider.name.split(' ')[0])} to confirm</p>
          <p class="text-sm text-gray-500">${pay.method === 'gcash' ? `GCash · Ref ${esc(pay.reference)}` : `Hand ${peso(j.total)} in cash to your vulcator`}</p></div></div>`);
    }

    const method = (value, title, sub, color, badge, disabled = false) => `
      <label class="role-card flex items-center gap-3 rounded-2xl border-2 border-gray-200 p-3.5 ${disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'}">
        <input type="radio" name="method" value="${value}" class="sr-only" ${payMethod === value ? 'checked' : ''} ${disabled ? 'disabled' : ''}>
        <span class="grid h-10 w-10 place-items-center rounded-xl text-base font-bold text-white ${color}">${badge}</span>
        <span class="flex-1"><span class="block font-semibold">${title}</span><span class="text-xs text-gray-500">${sub}</span></span>
        <span class="role-dot h-5 w-5 rounded-full border-2 border-gray-300"></span>
      </label>`;

    return summary + card(`
      <p class="mb-3 font-bold">Choose payment method</p>
      <div class="space-y-2">
        ${method('cash', 'Cash', 'Pay your vulcator directly', 'bg-ok-600', '₱')}
        ${method('gcash', 'GCash', 'Send to your vulcator\'s GCash', 'bg-blue-600', 'G')}
        ${method('card', 'Credit / Debit card', 'Coming soon', 'bg-purple-600', '▭', true)}
      </div>
      <div id="gcash-box" class="${payMethod === 'gcash' ? '' : 'hidden'} mt-4 rounded-2xl bg-blue-50 p-4 text-sm ring-1 ring-blue-100">
        ${j.provider.gcash ? `<p>Send <b>${peso(j.total)}</b> to <b>${esc(j.provider.name)}</b><br>GCash number: <b class="text-blue-700">${esc(j.provider.gcash)}</b></p>`
          : '<p>Ask your vulcator for their GCash number.</p>'}
        <label class="mt-3 block font-semibold" for="gcash-ref">GCash reference number</label>
        <input id="gcash-ref" inputmode="numeric" maxlength="20" placeholder="13-digit reference no."
               class="mt-1 w-full rounded-xl border border-blue-200 bg-white px-4 py-2.5 outline-none focus:border-blue-500">
      </div>
      <p id="pay-error" class="mt-3 hidden text-sm text-red-600"></p>
      <button data-pay class="mt-4 w-full rounded-2xl bg-brand-500 py-3.5 font-semibold text-white shadow-lg shadow-brand-500/30 hover:bg-brand-600">
        Confirm payment · ${peso(j.total)}</button>`);
  }

  function paidView(j) {
    const pay = j.payment || { method: 'cash' };
    const rating = j.rated
      ? `<p class="mt-2 text-center text-sm font-semibold text-green-600">Thanks for rating ${esc(j.provider.name.split(' ')[0])}!</p>`
      : `<div class="mt-2 flex items-center gap-3 rounded-2xl bg-gray-50 p-3">
          <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-ink-900 text-sm font-bold text-white">${esc(j.provider.initials)}</span>
          <div class="min-w-0 flex-1"><p class="truncate font-semibold">${esc(j.provider.name)}</p><p class="text-xs text-gray-500">How was the service?</p>
            <div class="stars mt-1 flex gap-1 text-2xl" role="radiogroup" aria-label="Rating">
              ${[1, 2, 3, 4, 5].map((n) => `<button type="button" data-star="${n}" aria-label="${n} star${n > 1 ? 's' : ''}" class="leading-none ${n <= stars ? 'text-amber-400' : 'text-gray-300'}">★</button>`).join('')}
            </div></div>
        </div>
        <textarea id="rate-comment" rows="2" maxlength="500" placeholder="Say something nice (optional)" class="mt-3 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand-500"></textarea>
        <button data-rate class="mt-2 w-full rounded-xl bg-ink-900 py-3 text-sm font-semibold text-white disabled:opacity-40" ${stars ? '' : 'disabled'}>Submit rating</button>`;

    return `<div class="sheet-enter overflow-hidden rounded-3xl bg-brand-500 text-white shadow-lg">
        <div class="relative px-5 pb-6 pt-5">
          <p class="text-xs font-bold uppercase tracking-widest text-white/70">Vulcatic</p>
          <p class="text-sm font-semibold">Payment confirmed</p>
          <p class="text-2xl font-bold">Thank you, ${esc(j.customer.first)}!</p>
          <img src="${root.dataset.gecko}" alt="" class="gecko-pop absolute -bottom-2 right-3 h-28 w-auto">
        </div>
        <div class="mx-4 mb-4 rounded-2xl bg-white/15 p-5 text-center ring-1 ring-white/25">
          <span class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-white text-ok-600">${icon.check}</span>
          <p class="mt-2 text-sm font-semibold">Payment successful</p>
          <p class="text-3xl font-bold">${peso(pay.amount ?? j.total)}</p>
          <p class="text-xs text-white/75">Paid with ${pay.method === 'gcash' ? 'GCash' : pay.method === 'card' ? 'card' : 'cash'}</p>
        </div>
      </div>` +
      card(`<p class="font-bold">Rate your vulcator</p>${rating}`) +
      card(`<p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-400">Receipt</p>
        ${row('Job ID', esc(j.code))}${row('Service', esc(j.service))}${row('Vehicle', esc(j.vehicle))}
        ${j.duration_min ? row('Duration', j.duration_min + ' min') : ''}${row('Amount', peso(j.total), true)}
        <a href="${VULCATIC.base}home.php" class="mt-4 block rounded-xl bg-brand-500 py-3 text-center font-semibold text-white hover:bg-brand-600">Back to home</a>`);
  }

  function bindPanel(j) {
    bindChat();
    panel.querySelectorAll('input[name=method]').forEach((r) => r.addEventListener('change', () => {
      payMethod = r.value;
      document.getElementById('gcash-box').classList.toggle('hidden', payMethod !== 'gcash');
    }));
    panel.querySelector('[data-pay]')?.addEventListener('click', async (e) => {
      const err = document.getElementById('pay-error');
      e.target.disabled = true;
      try {
        await api('pay.php', { id: jobId, method: payMethod, reference: document.getElementById('gcash-ref')?.value || '' });
        lastKey = ''; poller.now();
      } catch (ex) { err.textContent = ex.message; err.classList.remove('hidden'); e.target.disabled = false; }
    });

    panel.querySelectorAll('[data-star]').forEach((b) => b.addEventListener('click', () => {
      stars = Number(b.dataset.star);
      panel.querySelectorAll('[data-star]').forEach((s) => s.classList.toggle('text-amber-400', Number(s.dataset.star) <= stars));
      panel.querySelectorAll('[data-star]').forEach((s) => s.classList.toggle('text-gray-300', Number(s.dataset.star) > stars));
      panel.querySelector('[data-rate]').disabled = false;
    }));
    panel.querySelector('[data-rate]')?.addEventListener('click', async (e) => {
      e.target.disabled = true;
      try {
        await api('rate.php', { id: jobId, stars, comment: document.getElementById('rate-comment').value });
        toast('Thanks for your rating!');
        lastKey = ''; poller.now();
      } catch (ex) { toast(ex.message, 'error'); e.target.disabled = false; }
    });
  }


  function updateMap(j) {
    const mePt = [j.lat, j.lng];
    if (!meMarker) meMarker = L.marker(mePt, { icon: VMap.icon('me'), zIndexOffset: 1000 }).addTo(map);
    const p = j.provider;
    if (p && !['paid', 'cancelled'].includes(j.status)) {
      const vp = [p.lat, p.lng];
      if (!vulcMarker) vulcMarker = L.marker(vp, { icon: j.mode === 'walk_in' ? VMap.icon('shop') : VMap.icon('avatar', esc(p.initials)) }).addTo(map);
      else vulcMarker.setLatLng(vp);
      if (!line) line = L.polyline([vp, mePt], { color: j.mode === 'walk_in' ? '#16A34A' : '#E8501E', weight: 4, dashArray: '8 10' }).addTo(map);
      else line.setLatLngs([vp, mePt]);
      if (!fitted) { VMap.fit(map, [vp, mePt]); fitted = true; }
    } else if (!fitted) {
      map.setView(mePt, 16);
    }
  }

  async function refresh() {
    const { job: j, log, messages: msgs } = await api('job_status.php?id=' + jobId);
    if (lastStatus && j.status !== lastStatus && ALERTS[j.status]) Alerts.alert('update', ...ALERTS[j.status](j));
    lastStatus = j.status;
    // New chat messages from the other side
    const newest = msgs.at(-1)?.id ?? null;
    if (lastMsgId !== null && newest !== lastMsgId) {
      const fresh = msgs.filter((m) => m.id > lastMsgId && !m.mine);
      if (fresh.length) { Alerts.alert('update', 'New message', fresh.at(-1).body); if (!chatOpen) unread += fresh.length; }
    }
    lastMsgId = newest ?? lastMsgId ?? 0;
    messages = msgs;
    updateMap(j);
    // Only rebuild the panel when something visible changed, so typing isn't interrupted.
    const key = [j.status, j.pending_offers, j.eta_min, j.assignee?.status, j.payment?.status, j.payment?.method, j.rated].join('|');
    if (key !== lastKey) { lastKey = key; render(j, log); }
    else {
      if (chatOpen) renderMessages();
      panel.querySelectorAll('[data-unread]').forEach((u) => { u.textContent = unread; u.classList.toggle('hidden', !unread); });
    }
    if (j.status === 'cancelled' || (j.status === 'paid' && j.rated)) poller.stop();
  }

  poller = poll(refresh, 3000);
})();
