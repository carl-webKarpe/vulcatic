<?php
// Overview: what needs attention, headline numbers, trends.
$days  = (int) ($_GET['days'] ?? 14);
$days  = in_array($days, [7, 14, 30], true) ? $days : 14;
$since = 'CURDATE() - INTERVAL ' . ($days - 1) . ' DAY';

$k = db()->query("SELECT COUNT(*) AS requests, SUM(status = 'paid') AS paid, SUM(status = 'cancelled') AS cancelled,
        COALESCE(SUM(CASE WHEN status = 'paid' THEN total_amount END), 0) AS gmv,
        COALESCE(SUM(CASE WHEN status = 'paid' THEN platform_fee END), 0) AS fees,
        AVG(CASE WHEN accepted_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, requested_at, accepted_at) END) AS accept_s,
        AVG(CASE WHEN arrived_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, requested_at, arrived_at) END) AS arrive_s
      FROM jobs WHERE requested_at >= $since")->fetch();
$people = db()->query("SELECT
        (SELECT COUNT(*) FROM users WHERE role = 'customer') AS customers,
        (SELECT COUNT(*) FROM users WHERE role = 'customer' AND created_at >= $since) AS new_customers,
        (SELECT COUNT(*) FROM providers WHERE verification_status = 'verified') AS verified,
        (SELECT COUNT(*) FROM providers p JOIN users u ON u.id = p.user_id WHERE p.is_online = 1 AND u.is_active = 1 AND p.verification_status = 'verified') AS online,
        (SELECT AVG(stars) FROM ratings) AS rating")->fetch();
$closed = (int) $k['paid'] + (int) $k['cancelled'];
$completion = $closed ? round($k['paid'] / $closed * 100) : null;

$byDay = last_days($days);
foreach (db()->query("SELECT DATE(requested_at) d, COUNT(*) n FROM jobs WHERE requested_at >= $since GROUP BY DATE(requested_at)") as $r) {
    $byDay[$r['d']] = (int) $r['n'];
}
$stuck = (int) db()->query("SELECT COUNT(*) FROM jobs WHERE status IN ('searching','matched') AND requested_at < NOW() - INTERVAL 3 MINUTE")->fetchColumn();
$counts = admin_counts();
$recent = db()->query("SELECT j.id, j.job_code, j.status, j.municipality, j.total_amount, j.requested_at, s.name AS service,
                              u.full_name AS customer, p.display_name AS vulcator
                       FROM jobs j JOIN services s ON s.id = j.service_id JOIN users u ON u.id = j.customer_id
                       LEFT JOIN providers p ON p.id = j.provider_id ORDER BY j.requested_at DESC LIMIT 8")->fetchAll();

$towns = [];
foreach (SIARGAO_TOWNS as $name => $t) $towns[$name] = ['name' => $name, 'lat' => $t['lat'], 'lng' => $t['lng'], 'requests' => 0, 'paid' => 0, 'vulcators' => 0];
foreach (db()->query("SELECT municipality m, COUNT(*) n, SUM(status = 'paid') paid FROM jobs WHERE requested_at >= $since GROUP BY municipality") as $r) {
    if (isset($towns[$r['m']])) { $towns[$r['m']]['requests'] = (int) $r['n']; $towns[$r['m']]['paid'] = (int) $r['paid']; }
}
foreach (db()->query("SELECT municipality m, COUNT(*) n FROM providers WHERE verification_status = 'verified' GROUP BY municipality") as $r) {
    if (isset($towns[$r['m']])) $towns[$r['m']]['vulcators'] = (int) $r['n'];
}

admin_start('overview', 'Overview', 'Good ' . (date('H') < 12 ? 'morning' : (date('H') < 18 ? 'afternoon' : 'evening')) . ', ' . e(first_name($user['full_name'])) . '. Here\'s Vulcatic on Siargao.',
    $user, pill_group([7 => '7 days', 14 => '14 days', 30 => '30 days'], $days, fn ($d) => admin_url(['days' => $d]), 'Period'));
require __DIR__ . '/temp_card.php';

$attention = array_filter([
    $counts['approvals'] ? ['approvals', $counts['approvals'] . ' vulcator' . ($counts['approvals'] > 1 ? 's' : '') . ' waiting for approval', 'bg-amber-50 text-amber-900 ring-amber-200'] : null,
    $counts['resets'] ? ['resets', $counts['resets'] . ' password reset request' . ($counts['resets'] > 1 ? 's' : ''), 'bg-blue-50 text-blue-900 ring-blue-200'] : null,
    $stuck ? ['live', $stuck . ' request' . ($stuck > 1 ? 's' : '') . ' with no vulcator for over 3 minutes', 'bg-red-50 text-red-800 ring-red-200'] : null,
    $counts['low_reviews'] ? ['reviews', $counts['low_reviews'] . ' low rating' . ($counts['low_reviews'] > 1 ? 's' : '') . ' (3★ or less) in the last 2 weeks', 'bg-gray-50 text-gray-800 ring-gray-200'] : null,
]);
?>
<?php if ($attention): ?>
  <section aria-label="Needs attention" class="grid gap-3 md:grid-cols-2">
    <?php foreach ($attention as [$to, $text, $cls]): ?>
      <a href="<?= admin_url(['view' => $to]) ?>" class="flex items-center justify-between gap-3 rounded-2xl px-4 py-3 text-sm font-semibold ring-1 hover:shadow <?= $cls ?>">
        <span>⚠️ <?= e($text) ?></span><span aria-hidden="true">→</span>
      </a>
    <?php endforeach; ?>
  </section>
<?php else: ?>
  <p class="rounded-2xl bg-green-50 px-4 py-3 text-sm font-semibold text-green-800 ring-1 ring-green-200">✅ Nothing needs your attention right now.</p>
<?php endif; ?>

<section class="grid grid-cols-2 gap-3 md:grid-cols-4">
  <?php foreach ([
      ['Requests', number_format((int) $k['requests']), (int) $k['cancelled'] . ' cancelled', true, ['view' => 'jobs', 'days' => $days > 7 ? 30 : 7]],
      ['Completion rate', $completion === null ? '–' : $completion . '%', (int) $k['paid'] . ' paid jobs', false, null],
      ['Customer payments', peso((float) $k['gmv']), 'paid by customers', false, null],
      ['Platform earnings', peso((float) $k['fees']), (setting('platform_fee_rate', '0.05') * 100) . '% fee', false, ['view' => 'settings']],
      ['Time to accept', minutes_text($k['accept_s'] === null ? null : (float) $k['accept_s']), 'average', false, null],
      ['Time to arrive', minutes_text($k['arrive_s'] === null ? null : (float) $k['arrive_s']), 'from request', false, null],
      ['Vulcators online', $people['online'] . ' / ' . $people['verified'], 'approved vulcators', false, ['view' => 'vulcators', 'filter' => 'online']],
      ['Customers', number_format((int) $people['customers']), '+' . (int) $people['new_customers'] . ' new · ' . ($people['rating'] ? '★ ' . number_format((float) $people['rating'], 1) : 'no ratings'), false, ['view' => 'customers']],
  ] as [$label, $value, $sub, $dark, $link]): ?>
    <<?= $link ? 'a href="' . admin_url($link) . '"' : 'div' ?> class="block rounded-2xl p-4 transition <?= $dark ? 'bg-ink-900 text-white' : 'bg-white ring-1 ring-black/5' ?> <?= $link ? 'hover:shadow-md' : '' ?>">
      <p class="text-xs <?= $dark ? 'text-white/60' : 'text-gray-500' ?>"><?= $label ?></p>
      <p class="mt-1 text-xl font-bold sm:text-2xl"><?= e($value) ?></p>
      <p class="mt-0.5 text-xs <?= $dark ? 'text-white/50' : 'text-gray-400' ?>"><?= e($sub) ?></p>
    </<?= $link ? 'a' : 'div' ?>>
  <?php endforeach; ?>
</section>

<div class="grid gap-5 lg:grid-cols-5">
  <section class="<?= CARD ?> min-w-0 lg:col-span-3">
    <h2 class="mb-4 font-bold">Requests per day</h2>
    <?= bar_chart(day_rows($byDay), fn ($v) => (string) (int) $v, 'Requests per day, last ' . $days . ' days') ?>
  </section>
  <section class="<?= CARD ?> min-w-0 lg:col-span-2">
    <div class="flex items-baseline justify-between"><h2 class="font-bold">Requests by town</h2>
      <a href="<?= admin_url(['view' => 'towns', 'days' => $days]) ?>" class="text-sm font-semibold text-brand-600">Details →</a></div>
    <p class="text-xs text-gray-400">Circle size = requests in this period</p>
    <div id="town-map" class="mt-3 h-64 overflow-hidden rounded-xl" data-towns='<?= e(json_encode(array_values($towns))) ?>'></div>
  </section>
</div>

<section class="<?= CARD ?>">
  <div class="mb-2 flex items-baseline justify-between"><h2 class="font-bold">Latest requests</h2>
    <a href="<?= admin_url(['view' => 'jobs']) ?>" class="text-sm font-semibold text-brand-600">All jobs →</a></div>
  <ul class="divide-y divide-gray-100">
    <?php foreach ($recent as $j): ?>
      <li><a href="<?= admin_url(['view' => 'job', 'id' => $j['id']]) ?>" class="flex items-center gap-3 rounded-xl px-1 py-2.5 hover:bg-gray-50">
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-semibold"><?= e($j['service']) ?> · <?= e($j['customer']) ?> <span class="font-normal text-gray-400">→</span> <?= e($j['vulcator'] ?: '—') ?></p>
          <p class="truncate text-xs text-gray-500"><?= e(ago($j['requested_at'])) ?> · <?= e($j['municipality'] ?: '–') ?> · <span class="font-mono"><?= e($j['job_code']) ?></span></p>
        </div>
        <span class="text-sm font-semibold"><?= e(peso((float) $j['total_amount'])) ?></span>
        <?= status_chip($j['status']) ?>
      </a></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php admin_end();
