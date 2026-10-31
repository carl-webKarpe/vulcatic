<?php
// Live jobs: everything in progress right now. Refreshes itself every 20 seconds.
$rows = db()->query("SELECT j.id, j.job_code, j.status, j.municipality, j.address_text, j.total_amount, j.requested_at, j.vehicle_type,
                            TIMESTAMPDIFF(SECOND, j.requested_at, NOW()) AS age_s, s.name AS service,
                            u.full_name AS customer, u.phone AS customer_phone, p.display_name AS vulcator,
                            (SELECT COUNT(*) FROM job_offers o WHERE o.job_id = j.id AND o.status = 'pending') AS pending_offers
                     FROM jobs j JOIN services s ON s.id = j.service_id JOIN users u ON u.id = j.customer_id
                     LEFT JOIN providers p ON p.id = j.provider_id
                     WHERE j.status IN ('searching','matched','accepted','on_the_way','arrived','completed')
                     ORDER BY FIELD(j.status, 'searching','matched','completed','arrived','on_the_way','accepted'), j.requested_at")->fetchAll();
$groups = [
    'Looking for a vulcator' => ['searching', 'matched'],
    'Vulcator assigned'      => ['accepted', 'on_the_way', 'arrived'],
    'Waiting for payment'    => ['completed'],
];
$headExtra = '<meta http-equiv="refresh" content="20">';

admin_start('live', 'Live jobs', count($rows) . ' job' . (count($rows) === 1 ? '' : 's') . ' in progress · refreshes every 20 seconds (' . date('g:i:s A') . ')', $user);
?>
<?php if (!$rows): ?>
  <p class="<?= CARD ?> py-10 text-center text-gray-400">No jobs in progress right now.</p>
<?php endif; ?>
<?php foreach ($groups as $title => $statuses): $list = array_filter($rows, fn ($r) => in_array($r['status'], $statuses, true)); if (!$list) continue; ?>
  <section>
    <h2 class="mb-2 text-sm font-bold uppercase tracking-wide text-gray-500"><?= e($title) ?> · <?= count($list) ?></h2>
    <div class="grid gap-3 md:grid-cols-2">
      <?php foreach ($list as $j): $late = in_array($j['status'], ['searching', 'matched'], true) && $j['age_s'] > 180; ?>
        <a href="<?= admin_url(['view' => 'job', 'id' => $j['id']]) ?>" class="block rounded-2xl bg-white p-4 shadow-sm ring-1 hover:shadow-md <?= $late ? 'ring-red-300' : 'ring-black/5' ?>">
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0"><p class="truncate font-semibold"><?= e($j['service']) ?> · <?= e(ucfirst($j['vehicle_type'])) ?></p>
              <p class="truncate text-xs text-gray-500"><?= e($j['municipality'] ?: '–') ?><?= $j['address_text'] ? ' · ' . e($j['address_text']) : '' ?></p></div>
            <?= status_chip($j['status']) ?>
          </div>
          <p class="mt-3 text-sm"><?= e($j['customer']) ?> <span class="text-gray-400">→</span> <?= $j['vulcator'] ? e($j['vulcator']) : '<span class="text-gray-400">' . (int) $j['pending_offers'] . ' vulcator(s) notified</span>' ?></p>
          <p class="mt-1 flex justify-between text-xs <?= $late ? 'font-semibold text-red-600' : 'text-gray-500' ?>">
            <span><?= $late ? '⚠️ No taker for ' : 'Started ' ?><?= e(ago($j['requested_at'])) ?></span><span class="font-mono text-gray-400"><?= e($j['job_code']) ?></span></p>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>
<?php if (array_filter($rows, fn ($r) => in_array($r['status'], ['searching', 'matched'], true) && $r['age_s'] > 180)): ?>
  <p class="text-sm text-gray-500">Tip: for requests with no taker, check <a class="font-semibold text-brand-600" href="<?= admin_url(['view' => 'vulcators', 'filter' => 'online']) ?>">who's online</a> in that town, or call the customer to suggest the nearest walk-in shop.</p>
<?php endif; ?>
<?php admin_end();
