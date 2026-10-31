<?php
// Towns: performance and coverage for each Siargao municipality.
$days  = (int) ($_GET['days'] ?? 30);
$days  = in_array($days, [7, 14, 30, 90], true) ? $days : 30;
$since = 'CURDATE() - INTERVAL ' . ($days - 1) . ' DAY';

$towns = [];
foreach (SIARGAO_TOWNS as $name => $t) {
    $towns[$name] = ['name' => $name, 'phase' => $t['phase'], 'radius' => $t['radius_km'], 'lat' => $t['lat'], 'lng' => $t['lng'],
                     'requests' => 0, 'paid' => 0, 'cancelled' => 0, 'gmv' => 0.0, 'accept_s' => null, 'vulcators' => 0, 'online' => 0, 'shops' => 0];
}
foreach (db()->query("SELECT municipality m, COUNT(*) n, SUM(status = 'paid') paid, SUM(status = 'cancelled') cancelled,
                             COALESCE(SUM(CASE WHEN status = 'paid' THEN total_amount END), 0) gmv,
                             AVG(CASE WHEN accepted_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, requested_at, accepted_at) END) accept_s
                      FROM jobs WHERE requested_at >= $since AND municipality IS NOT NULL GROUP BY municipality") as $r) {
    if (isset($towns[$r['m']])) {
        $towns[$r['m']] = array_merge($towns[$r['m']], ['requests' => (int) $r['n'], 'paid' => (int) $r['paid'],
            'cancelled' => (int) $r['cancelled'], 'gmv' => (float) $r['gmv'], 'accept_s' => $r['accept_s'] === null ? null : (float) $r['accept_s']]);
    }
}
foreach (db()->query("SELECT p.municipality m, COUNT(*) n, SUM(p.is_online = 1 AND u.is_active = 1) online, SUM(p.accepts_walk_in) shops
                      FROM providers p JOIN users u ON u.id = p.user_id WHERE p.verification_status = 'verified' GROUP BY p.municipality") as $r) {
    if (isset($towns[$r['m']])) { $towns[$r['m']]['vulcators'] = (int) $r['n']; $towns[$r['m']]['online'] = (int) $r['online']; $towns[$r['m']]['shops'] = (int) $r['shops']; }
}
$gaps = array_keys(array_filter($towns, fn ($t) => $t['vulcators'] === 0));

admin_start('towns', 'Towns', 'How each Siargao municipality is doing. Search radius and phase are set in includes/siargao.php.', $user,
    pill_group([7 => '7 days', 30 => '30 days', 90 => '90 days'], $days, fn ($d) => admin_url(['view' => 'towns', 'days' => $d]), 'Period'));
?>
<?php if ($gaps): ?>
  <p class="rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200">No approved vulcators yet in: <b><?= e(implode(', ', $gaps)) ?></b>. Recruit there next.</p>
<?php endif; ?>

<div class="grid gap-5 lg:grid-cols-5">
  <section class="<?= CARD ?> min-w-0 lg:col-span-2">
    <h2 class="font-bold">Map</h2>
    <p class="text-xs text-gray-400">Circle size = requests · grey = none yet</p>
    <div id="town-map" class="mt-3 h-80 overflow-hidden rounded-xl" data-towns='<?= e(json_encode(array_values($towns))) ?>'></div>
  </section>
  <section class="<?= CARD ?> min-w-0 lg:col-span-3">
    <h2 class="mb-3 font-bold">By town</h2>
    <div class="-mx-5 overflow-x-auto px-5">
      <table class="w-full min-w-[600px] text-sm">
        <thead class="text-left text-xs uppercase tracking-wide text-gray-400">
          <tr><th class="py-2 font-semibold">Town</th><th class="text-right font-semibold">Requests</th><th class="text-right font-semibold">Completed</th>
              <th class="text-right font-semibold">To accept</th><th class="text-right font-semibold">Payments</th><th class="text-right font-semibold">Vulcators</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php foreach ($towns as $name => $t): $c = $t['paid'] + $t['cancelled']; ?>
            <tr>
              <td class="py-2.5"><a href="<?= admin_url(['view' => 'jobs', 'town' => $name, 'days' => $days > 30 ? 90 : ($days > 7 ? 30 : 7)]) ?>" class="font-semibold hover:text-brand-600"><?= e($name) ?></a>
                <p class="text-[11px] text-gray-400">Phase <?= $t['phase'] ?> · <?= $t['radius'] ?> km radius</p></td>
              <td class="text-right tabular-nums"><?= $t['requests'] ?></td>
              <td class="text-right tabular-nums"><?= $c ? round($t['paid'] / $c * 100) . '%' : '–' ?></td>
              <td class="text-right tabular-nums"><?= minutes_text($t['accept_s']) ?></td>
              <td class="text-right tabular-nums"><?= e(peso($t['gmv'])) ?></td>
              <td class="text-right tabular-nums"><a href="<?= admin_url(['view' => 'vulcators', 'town' => $name]) ?>" class="hover:text-brand-600"><?= $t['vulcators'] ?></a>
                <p class="text-[11px] text-gray-400"><?= $t['online'] ?> online · <?= $t['shops'] ?> shop<?= $t['shops'] === 1 ? '' : 's' ?></p></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
<?php admin_end();
