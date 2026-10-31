<?php
// All jobs: filter by period, town, status; search; pages; CSV export.
require __DIR__ . '/jobs_query.php';
$f = admin_jobs_filters($_GET);
$per = 25;
$page = max(1, (int) ($_GET['page'] ?? 1));
[$rows, $total] = admin_jobs_query($_GET, $per, ($page - 1) * $per);
$pages = max(1, (int) ceil($total / $per));
$q = fn (array $change) => admin_url(array_merge(['view' => 'jobs'], $f, ['page' => null], $change));

$sum = ['paid' => 0, 'total' => 0.0];
foreach ($rows as $r) if ($r['status'] === 'paid') { $sum['paid']++; $sum['total'] += (float) $r['total_amount']; }

admin_start('jobs', 'All jobs', number_format($total) . ' job' . ($total === 1 ? '' : 's') . ' match these filters', $user,
    '<a href="' . e($q(['export' => 'csv'])) . '" class="rounded-xl bg-white px-4 py-2 text-sm font-semibold shadow-sm ring-1 ring-black/5 hover:bg-gray-50">⬇ Export CSV</a>');
$sel = 'rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm';
?>
<form method="get" class="<?= CARD ?> flex flex-wrap items-end gap-3" role="search">
  <input type="hidden" name="view" value="jobs">
  <?php if ($f['provider']): ?><input type="hidden" name="provider" value="<?= (int) $f['provider'] ?>"><?php endif; ?>
  <?php if ($f['customer']): ?><input type="hidden" name="customer" value="<?= (int) $f['customer'] ?>"><?php endif; ?>
  <label class="min-w-[12rem] flex-1 text-xs font-semibold text-gray-500">Search
    <input name="q" value="<?= e($f['q']) ?>" placeholder="Job code, customer, vulcator or phone" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-normal text-gray-900"></label>
  <label class="text-xs font-semibold text-gray-500">Period
    <select name="days" class="mt-1 block <?= $sel ?>"><?php foreach (JOB_DAY_OPTIONS as $v => $t): ?><option value="<?= $v ?>" <?= $v === $f['days'] ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></label>
  <label class="text-xs font-semibold text-gray-500">Town
    <select name="town" class="mt-1 block <?= $sel ?>"><option value="">All towns</option>
      <?php foreach (array_keys(SIARGAO_TOWNS) as $t): ?><option <?= $t === $f['town'] ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></label>
  <label class="text-xs font-semibold text-gray-500">Status
    <select name="status" class="mt-1 block <?= $sel ?>">
      <?php foreach (['' => 'All statuses', 'searching' => 'Looking for vulcator', 'active' => 'In progress', 'paid' => 'Paid', 'cancelled' => 'Cancelled'] as $v => $t): ?>
        <option value="<?= $v ?>" <?= $v === $f['status'] ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></label>
  <button class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-600">Apply</button>
  <?php if ($f['q'] || $f['town'] || $f['status'] || $f['provider'] || $f['customer'] || $f['days'] !== '30'): ?>
    <a href="<?= admin_url(['view' => 'jobs']) ?>" class="px-2 py-2 text-sm font-semibold text-gray-500 hover:text-gray-800">Clear</a>
  <?php endif; ?>
</form>

<?php if ($f['provider'] || $f['customer']): ?>
  <p class="text-sm text-gray-600">Showing jobs for one <?= $f['provider'] ? 'vulcator' : 'customer' ?> only.</p>
<?php endif; ?>

<section class="<?= CARD ?>">
  <div class="-mx-5 overflow-x-auto px-5">
    <table class="w-full min-w-[760px] text-sm" id="jobs-table">
      <thead class="text-left text-xs uppercase tracking-wide text-gray-400">
        <tr><th class="py-2 font-semibold">Job</th><th class="font-semibold">When</th><th class="font-semibold">Town</th>
            <th class="font-semibold">Customer → Vulcator</th><th class="font-semibold">Status</th><th class="text-right font-semibold">Amount</th></tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        <?php foreach ($rows as $j): ?>
          <tr class="cursor-pointer hover:bg-gray-50" data-href="<?= admin_url(['view' => 'job', 'id' => $j['id']]) ?>">
            <td class="py-2.5"><a href="<?= admin_url(['view' => 'job', 'id' => $j['id']]) ?>" class="font-semibold hover:text-brand-600"><?= e($j['service']) ?></a>
              <p class="font-mono text-[11px] text-gray-400"><?= e($j['job_code']) ?></p></td>
            <td class="text-gray-600"><?= e(date('M j, g:i A', strtotime($j['requested_at']))) ?></td>
            <td><?= e($j['municipality'] ?: '–') ?></td>
            <td><?= e($j['customer']) ?> <span class="text-gray-400">→</span> <?= e($j['vulcator'] ?: '—') ?></td>
            <td><?= status_chip($j['status']) ?></td>
            <td class="text-right tabular-nums <?= $j['status'] === 'cancelled' ? 'text-gray-400 line-through' : 'font-semibold' ?>"><?= e(peso((float) $j['total_amount'])) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" class="py-10 text-center text-gray-400">No jobs match these filters.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm">
    <p class="text-gray-500">Page <?= $page ?> of <?= $pages ?> · this page: <?= $sum['paid'] ?> paid, <?= e(peso($sum['total'])) ?></p>
    <div class="flex gap-2">
      <?php if ($page > 1): ?><a href="<?= e($q(['page' => $page - 1])) ?>" class="rounded-lg px-3 py-1.5 font-semibold ring-1 ring-gray-200 hover:bg-gray-50">← Newer</a><?php endif; ?>
      <?php if ($page < $pages): ?><a href="<?= e($q(['page' => $page + 1])) ?>" class="rounded-lg px-3 py-1.5 font-semibold ring-1 ring-gray-200 hover:bg-gray-50">Older →</a><?php endif; ?>
    </div>
  </div>
</section>
<?php admin_end();
