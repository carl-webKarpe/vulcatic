// Employee: list assigned jobs, ring for new ones, accept or decline.
(() => {
  const list = document.getElementById('job-list');
  let known = null;

  function render(jobs) {
    if (!jobs.length) {
      list.innerHTML = '<li class="rounded-2xl bg-gray-50 py-8 text-center text-sm text-gray-400">No jobs assigned right now.</li>';
      return;
    }
    list.innerHTML = jobs.map((j) => `<li class="rounded-2xl p-4 ring-1 ${j.pending ? 'bg-amber-50 ring-amber-200' : 'ring-gray-200'}">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0"><p class="truncate font-semibold">${esc(j.customer)} · ${esc(j.service)}</p>
          <p class="truncate text-xs text-gray-500">${esc(j.vehicle)} · ${j.mode === 'walk_in' ? 'Shop visit' : 'Roadside'}${j.municipality ? ' · ' + esc(j.municipality) : ''}${j.address ? ' · ' + esc(j.address) : ''}</p></div>
        <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-bold ${j.pending ? 'bg-amber-400 text-ink-900' : 'bg-blue-50 text-blue-700'}">${j.pending ? 'New' : esc(j.status_label)}</span>
      </div>
      ${j.pending ? `<div class="mt-3 grid grid-cols-3 gap-2">
          <button data-answer="decline" data-id="${j.id}" class="rounded-xl bg-white py-2.5 text-sm font-semibold text-red-600 ring-1 ring-red-200">Decline</button>
          <a href="${VULCATIC.base}provider_job.php?id=${j.id}" class="rounded-xl bg-white py-2.5 text-center text-sm font-semibold ring-1 ring-gray-200">Details</a>
          <button data-answer="accept" data-id="${j.id}" class="rounded-xl bg-ok-500 py-2.5 text-sm font-semibold text-white">Accept</button></div>`
        : `<a href="${VULCATIC.base}provider_job.php?id=${j.id}" class="mt-3 block rounded-xl bg-brand-500 py-2.5 text-center text-sm font-semibold text-white">Open job</a>`}
    </li>`).join('');
    list.querySelectorAll('[data-answer]').forEach((b) => b.addEventListener('click', async () => {
      b.disabled = true;
      try {
        await api('assignment_respond.php', { id: Number(b.dataset.id), action: b.dataset.answer });
        if (b.dataset.answer === 'accept') { location.href = VULCATIC.base + 'provider_job.php?id=' + b.dataset.id; return; }
        toast('Declined. The shop will reassign it.');
        p.now();
      } catch (e) { toast(e.message, 'error'); p.now(); }
    }));
  }

  const p = poll(async () => {
    const { jobs } = await api('employee_poll.php');
    const pendingIds = jobs.filter((j) => j.pending).map((j) => j.id);
    if (known && pendingIds.some((id) => !known.includes(id))) Alerts.alert('job', 'New job assigned to you', 'Open Vulcatic to accept it.');
    known = pendingIds;
    render(jobs);
  }, 4000);
})();
