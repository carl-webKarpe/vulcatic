// Shared JavaScript for every page.

// Show / hide password buttons: <button data-toggle-password="inputId">
document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
  btn.addEventListener('click', () => {
    const input = document.getElementById(btn.dataset.togglePassword);
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.textContent = show ? 'Hide' : 'Show';
  });
});

// Stop double submits on slow connections.
document.querySelectorAll('form[data-once]').forEach((form) => {
  form.addEventListener('submit', () => {
    form.querySelectorAll('button[type=submit]').forEach((b) => {
      b.disabled = true;
      b.classList.add('opacity-60');
    });
  });
});

// Hide a field's error message as soon as the user starts fixing it.
document.querySelectorAll('form input').forEach((input) => {
  input.addEventListener('input', () => {
    const wrapper = input.closest('div:has(> p.text-red-600), fieldset');
    // Only messages printed by the server; live error boxes (with an id) are managed by their page.
    wrapper?.querySelectorAll(':scope > p.text-red-600:not([id])').forEach((p) => p.remove());
  });
});

// ---------- API helpers used by the live pages ----------
const VULCATIC = {
  base: document.querySelector('meta[name=base-url]')?.content || '/',
  csrf: document.querySelector('meta[name=csrf-token]')?.content || '',
};

// api('job_status.php?id=3') -> GET;  api('pay.php', {id: 3}) -> POST JSON
async function api(path, data) {
  const opts = data === undefined
    ? { headers: { Accept: 'application/json' } }
    : { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': VULCATIC.csrf }, body: JSON.stringify(data) };
  const res = await fetch(VULCATIC.base + 'api/' + path, opts);
  const json = await res.json().catch(() => ({ error: 'The server sent an unexpected reply.' }));
  if (res.status === 401) { location.href = VULCATIC.base + 'login.php'; }
  if (!res.ok) { const err = new Error(json.error || 'Something went wrong.'); err.status = res.status; throw err; }
  return json;
}

// Run fn every `ms`, pausing while the tab is hidden and backing off on errors.
function poll(fn, ms) {
  let timer, wait = ms, stopped = false;
  const run = async () => {
    if (stopped) return;
    if (document.hidden) { timer = setTimeout(run, 1000); return; }
    try { await fn(); wait = ms; } catch (e) { console.warn(e); wait = Math.min(wait * 2, 30000); }
    if (!stopped) timer = setTimeout(run, wait);
  };
  run();
  return { stop() { stopped = true; clearTimeout(timer); }, now() { clearTimeout(timer); run(); } };
}

// Small toast message at the top of the screen.
function toast(message, type = 'success') {
  const el = document.createElement('div');
  el.className = 'flash fixed inset-x-4 bottom-40 z-[1100] mx-auto max-w-md md:bottom-auto md:top-4 rounded-xl px-4 py-3 text-sm font-medium shadow-lg ' +
    (type === 'error' ? 'bg-red-50 text-red-700 ring-1 ring-red-200' : 'bg-green-50 text-green-700 ring-1 ring-green-200');
  el.setAttribute('role', 'status');
  el.textContent = message;
  document.body.appendChild(el);
  setTimeout(() => el.remove(), 5000);
}

const peso = (n) => '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

// ---------- Offline mode ----------
// Register the service worker so the app opens even with no signal.
if ('serviceWorker' in navigator) {
  addEventListener('load', () => {
    navigator.serviceWorker.register(VULCATIC.base + 'sw.js', { scope: VULCATIC.base }).catch(() => {});
  });
}

// Show a bar linking to the offline shop finder whenever the signal drops.
(() => {
  const bar = document.createElement('a');
  bar.href = VULCATIC.base + 'offline.html';
  bar.className = 'fixed inset-x-3 bottom-20 z-[900] hidden items-center justify-between gap-3 rounded-2xl bg-ink-900 px-4 py-3 text-sm font-semibold text-white shadow-2xl md:bottom-4 md:left-auto md:right-4 md:w-96';
  bar.innerHTML = '<span>📶 You\'re offline. Nearby shops are saved on your phone.</span><span class="shrink-0 text-brand-500">Open →</span>';
  document.body.appendChild(bar);
  const update = () => { bar.classList.toggle('hidden', navigator.onLine); bar.classList.toggle('flex', !navigator.onLine); };
  addEventListener('online', update);
  addEventListener('offline', update);
  update();
})();

// Keep the list of Siargao shops on the phone (refreshed every 6 hours while logged in).
(async () => {
  const KEY = 'vulcatic-offline-pack';
  try {
    const saved = JSON.parse(localStorage.getItem(KEY) || 'null');
    if (document.querySelector('meta[name=signed-in]')?.content !== '1') return;
    if (!navigator.onLine || (saved && Date.now() - new Date(saved.saved_at).getTime() < 6 * 3600 * 1000)) return;
    const res = await fetch(VULCATIC.base + 'api/offline_pack.php', { headers: { Accept: 'application/json' } });
    if (res.ok) localStorage.setItem(KEY, JSON.stringify(await res.json()));
  } catch (e) { /* not logged in or no signal: try again next time */ }
})();

// ---------- In-app confirmation sheet (instead of the browser's grey confirm box) ----------
// const ok = await confirmSheet({ title: 'Cancel request?', text: '…', confirm: 'Yes, cancel', danger: true });
function confirmSheet({ title, text = '', confirm = 'OK', cancel = 'Go back', danger = false }) {
  return new Promise((resolve) => {
    const wrap = document.createElement('div');
    wrap.className = 'fixed inset-0 z-[1200] flex items-end justify-center bg-ink-900/60 p-3 sm:items-center';
    wrap.setAttribute('role', 'alertdialog');
    wrap.setAttribute('aria-modal', 'true');
    wrap.innerHTML = `
      <div class="sheet-enter w-full max-w-sm rounded-3xl bg-white p-6 shadow-2xl">
        <p class="text-lg font-bold" id="cs-title">${esc(title)}</p>
        ${text ? `<p class="mt-1 text-sm text-gray-500">${esc(text)}</p>` : ''}
        <div class="mt-6 grid grid-cols-2 gap-3">
          <button data-no class="rounded-2xl py-3 font-semibold ring-1 ring-gray-200 hover:bg-gray-50">${esc(cancel)}</button>
          <button data-yes class="rounded-2xl py-3 font-semibold text-white ${danger ? 'bg-red-600 hover:bg-red-700' : 'bg-brand-500 hover:bg-brand-600'}">${esc(confirm)}</button>
        </div>
      </div>`;
    wrap.setAttribute('aria-labelledby', 'cs-title');
    const done = (v) => { wrap.remove(); document.removeEventListener('keydown', onKey); resolve(v); };
    const onKey = (e) => { if (e.key === 'Escape') done(false); };
    wrap.addEventListener('click', (e) => { if (e.target === wrap) done(false); });
    wrap.querySelector('[data-no]').addEventListener('click', () => done(false));
    wrap.querySelector('[data-yes]').addEventListener('click', () => done(true));
    document.addEventListener('keydown', onKey);
    document.body.appendChild(wrap);
    wrap.querySelector('[data-yes]').focus();
  });
}

// Buttons with data-confirm="Question?" ask first, then submit their form.
document.addEventListener('click', async (e) => {
  const btn = e.target.closest('button[data-confirm]');
  if (!btn || btn.dataset.confirmed) return;
  e.preventDefault();
  if (await confirmSheet({ title: btn.dataset.confirm, confirm: btn.dataset.confirmLabel || 'Yes', danger: 'danger' in btn.dataset })) {
    btn.dataset.confirmed = '1';
    btn.form ? btn.form.requestSubmit(btn) : btn.click();
  }
});

// ---------- Alerts: ring, vibrate and phone notification ----------
// Browsers only allow sound after the user has tapped the page once, so we
// unlock audio on the first tap and remember whether alerts are switched on.
const Alerts = (() => {
  let ctx = null, titleTimer = null;
  const baseTitle = document.title;
  const unlock = () => {
    try { ctx = ctx || new (window.AudioContext || window.webkitAudioContext)(); if (ctx.state === 'suspended') ctx.resume(); } catch (e) {}
  };
  addEventListener('pointerdown', unlock, { once: true });
  addEventListener('keydown', unlock, { once: true });

  function tone(freq, start, dur) {
    const o = ctx.createOscillator(), g = ctx.createGain();
    o.type = 'sine'; o.frequency.value = freq;
    g.gain.setValueAtTime(0.0001, ctx.currentTime + start);
    g.gain.exponentialRampToValueAtTime(0.35, ctx.currentTime + start + 0.02);
    g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + start + dur);
    o.connect(g).connect(ctx.destination);
    o.start(ctx.currentTime + start); o.stop(ctx.currentTime + start + dur + 0.05);
  }

  return {
    // kind: 'job' (urgent, for vulcators) or 'update' (gentle, for customers)
    ring(kind = 'update') {
      try {
        if (ctx && ctx.state === 'running') {
          if (kind === 'job') { tone(880, 0, 0.18); tone(1175, 0.22, 0.18); tone(880, 0.5, 0.18); tone(1175, 0.72, 0.25); }
          else { tone(660, 0, 0.15); tone(990, 0.18, 0.3); }
        }
      } catch (e) {}
      // Browsers only allow vibration after the user has tapped the page.
      try { if (navigator.userActivation?.hasBeenActive) navigator.vibrate?.(kind === 'job' ? [300, 120, 300, 120, 300] : [150, 80, 150]); } catch (e) {}
    },
    // Phone/desktop notification, only when the page isn't on screen.
    notify(title, body) {
      if (!document.hidden) return;
      try {
        if ('Notification' in window && Notification.permission === 'granted') {
          const n = new Notification(title, { body, icon: VULCATIC.base + 'assets/img/icon-192.png', tag: 'vulcatic', renotify: true });
          n.onclick = () => { focus(); n.close(); };
        }
      } catch (e) {}
      clearInterval(titleTimer);
      let on = false;
      titleTimer = setInterval(() => { document.title = (on = !on) ? '🔔 ' + title : baseTitle; }, 1000);
      addEventListener('focus', () => { clearInterval(titleTimer); document.title = baseTitle; }, { once: true });
    },
    alert(kind, title, body) { this.ring(kind); this.notify(title, body); },
    canNotify: () => 'Notification' in window,
    enabled: () => 'Notification' in window && Notification.permission === 'granted',
    async enable() { unlock(); if (!('Notification' in window)) return false; return (await Notification.requestPermission()) === 'granted'; },
  };
})();

// <button data-enable-alerts> turns alerts on; hidden once allowed.
document.querySelectorAll('[data-enable-alerts]').forEach((b) => {
  const sync = () => b.classList.toggle('hidden', !Alerts.canNotify() || Alerts.enabled() || Notification.permission === 'denied');
  sync();
  b.addEventListener('click', async () => {
    const ok = await Alerts.enable();
    Alerts.ring(b.dataset.enableAlerts || 'update');
    toast(ok ? 'Alerts are on. You\'ll hear a ring for new updates.' : 'Notifications are blocked. You\'ll still hear a ring while this page is open.', ok ? 'success' : 'error');
    sync();
  });
});
