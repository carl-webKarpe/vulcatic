<?php
// Vulcators: everyone who fixes tires, with filters and account actions.
$filter = in_array($_GET['filter'] ?? '', ['online', 'pending', 'suspended', 'rejected'], true) ? $_GET['filter'] : '';
$town = array_key_exists($_GET['town'] ?? '', SIARGAO_TOWNS) ? $_GET['town'] : '';
$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 60);

$where = ['1 = 1']; $args = [];
if ($town) { $where[] = 'p.municipality = ?'; $args[] = $town; }
if ($q !== '') { $where[] = '(p.display_name LIKE ? OR u.full_name LIKE ? OR u.phone LIKE ?)'; $like = '%' . $q . '%'; array_push($args, $like, $like, $like); }
$where[] = match ($filter) {
    'online'    => "p.is_online = 1 AND u.is_active = 1 AND p.verification_status = 'verified'",
    'pending'   => "p.verification_status = 'pending'",
    'rejected'  => "p.verification_status = 'rejected'",
    'suspended' => 'u.is_active = 0',
    default     => '1 = 1',
};
$stmt = db()->prepare("SELECT p.id, p.display_name, p.provider_type, p.municipality, p.verification_status, p.is_online, p.offers_onsite, p.accepts_walk_in,
                              p.rating_avg, p.rating_count, p.last_location_at, p.created_at, u.full_name, u.phone, u.is_active,
                              (SELECT COUNT(*) FROM jobs j WHERE j.provider_id = p.id AND j.status = 'paid') AS jobs,
                              (SELECT COALESCE(SUM(provider_earning), 0) FROM jobs j WHERE j.provider_id = p.id AND j.status = 'paid') AS earned,
                              (SELECT COUNT(*) FROM job_offers o WHERE o.provider_id = p.id) AS offers,
                              (SELECT COUNT(*) FROM job_offers o WHERE o.provider_id = p.id AND o.status = 'accepted') AS accepted
                       FROM providers p JOIN users u ON u.id = p.user_id WHERE " . implode(' AND ', $where) . "
                       ORDER BY FIELD(p.verification_status, 'pending', 'verified', 'rejected'), p.is_online DESC, p.municipality, p.display_name");
$stmt->execute($args);
$rows = $stmt->fetchAll();
$link = fn (array $change) => admin_url(array_merge(['view' => 'vulcators', 'filter' => $filter, 'town' => $town, 'q' => $q], $change));

admin_start('vulcators', 'Vulcators', count($rows) . ' shown · approve, suspend or reactivate. Suspended vulcators can\'t log in.', $user);
?>
<div class="flex flex-wrap items-center gap-3">
  <?= pill_group(['' => 'All', 'online' => 'Online now', 'pending' => 'Pending', 'suspended' => 'Suspended', 'rejected' => 'Rejected'], $filter, fn ($v) => $link(['filter' => $v]), 'Show') ?>
  <form method="get" class="flex flex-1 flex-wrap gap-2" role="search">
    <input type="hidden" name="view" value="vulcators"><input type="hidden" name="filter" value="<?= e($filter) ?>">
    <select name="town" aria-label="Town" class="rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm" onchange="this.form.submit()">
      <option value="">All towns</option><?php foreach (array_keys(SIARGAO_TOWNS) as $t): ?><option <?= $t === $town ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select>
    <input name="q" value="<?= e($q) ?>" placeholder="Search name or phone" aria-label="Search" class="min-w-[10rem] flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm">
  </form>
</div>

<section class="<?= CARD ?>">
  <div class="-mx-5 overflow-x-auto px-5">
    <table class="w-full min-w-[820px] text-sm">
      <thead class="text-left text-xs uppercase tracking-wide text-gray-400">
        <tr><th class="py-2 font-semibold">Vulcator</th><th class="font-semibold">Town</th><th class="font-semibold">Status</th>
            <th class="text-right font-semibold">Rating</th><th class="text-right font-semibold">Accept rate</th>
            <th class="text-right font-semibold">Paid jobs</th><th class="text-right font-semibold">Earned</th><th class="text-right font-semibold">Action</th></tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        <?php foreach ($rows as $v):
            $state = !$v['is_active'] ? ['Suspended', 'bg-red-50 text-red-700 ring-red-200']
                  : ($v['verification_status'] === 'pending' ? ['Pending', 'bg-amber-50 text-amber-700 ring-amber-200']
                  : ($v['verification_status'] === 'rejected' ? ['Rejected', 'bg-gray-100 text-gray-500 ring-gray-200']
                  : ($v['is_online'] ? ['Online', 'bg-green-50 text-green-700 ring-green-300'] : ['Offline', 'bg-gray-100 text-gray-600 ring-gray-200']))); ?>
          <tr>
            <td class="py-2.5"><p class="font-semibold"><?= e($v['display_name']) ?></p>
              <p class="text-xs text-gray-400"><?= $v['provider_type'] === 'shop' ? 'Shop' : 'Skilled local' ?> · <a href="tel:<?= e($v['phone']) ?>" class="hover:text-brand-600"><?= e($v['phone']) ?></a>
                · <?= implode(' + ', array_filter([$v['offers_onsite'] ? 'on-site' : '', $v['accepts_walk_in'] ? 'walk-in' : ''])) ?></p></td>
            <td><?= e($v['municipality'] ?: '–') ?></td>
            <td><span class="rounded-full px-2 py-0.5 text-xs font-semibold ring-1 <?= $state[1] ?>"><?= $state[0] ?></span>
              <?php if ($v['last_location_at'] && $v['is_online']): ?><p class="text-[11px] text-gray-400">seen <?= e(ago($v['last_location_at'])) ?></p><?php endif; ?></td>
            <td class="text-right tabular-nums"><?= $v['rating_count'] ? '★ ' . number_format((float) $v['rating_avg'], 1) . ' <span class="text-xs text-gray-400">(' . (int) $v['rating_count'] . ')</span>' : '–' ?></td>
            <td class="text-right tabular-nums"><?= $v['offers'] ? round($v['accepted'] / $v['offers'] * 100) . '%' : '–' ?> <span class="text-xs text-gray-400">(<?= (int) $v['offers'] ?>)</span></td>
            <td class="text-right tabular-nums"><a href="<?= admin_url(['view' => 'jobs', 'provider' => $v['id'], 'days' => 'all']) ?>" class="hover:text-brand-600"><?= (int) $v['jobs'] ?></a></td>
            <td class="text-right tabular-nums"><?= e(peso((float) $v['earned'])) ?></td>
            <td class="text-right">
              <form method="post" class="inline-flex gap-1">
                <?= csrf_field() ?><?= back_field() ?><input type="hidden" name="provider_id" value="<?= (int) $v['id'] ?>">
                <?php if ($v['verification_status'] === 'pending'): ?>
                  <a href="<?= admin_url(['view' => 'approvals']) ?>" class="rounded-lg bg-amber-400 px-2.5 py-1 text-xs font-semibold text-ink-900">Review</a>
                <?php elseif ($v['is_active']): ?>
                  <button name="action" value="suspend" data-confirm="Suspend <?= e($v['display_name']) ?>? They won't be able to log in." data-confirm-label="Suspend" data-danger
                          class="rounded-lg px-2.5 py-1 text-xs font-semibold text-red-600 ring-1 ring-red-200 hover:bg-red-50">Suspend</button>
                <?php else: ?>
                  <button name="action" value="reactivate" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-green-700 ring-1 ring-green-300 hover:bg-green-50">Reactivate</button>
                <?php endif; ?>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="8" class="py-10 text-center text-gray-400">No vulcators match.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php admin_end();
