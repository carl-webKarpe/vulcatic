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
    wrapper?.querySelectorAll(':scope > p.text-red-600').forEach((p) => p.remove());
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
  el.className = 'flash fixed inset-x-4 top-4 z-[1000] mx-auto max-w-md rounded-xl px-4 py-3 text-sm font-medium shadow-lg ' +
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
