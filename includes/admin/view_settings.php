<?php
// Settings: fees, matching rules and service prices.
$services = db()->query('SELECT s.id, s.name, s.description, s.base_price, s.is_active,
                                (SELECT COUNT(*) FROM provider_services ps WHERE ps.service_id = s.id) AS offered_by
                         FROM services s ORDER BY s.id')->fetchAll();
admin_start('settings', 'Settings', 'Changes apply to new requests right away.', $user);
$input = 'mt-1 block w-full rounded-xl border border-gray-300 px-3 py-2.5 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100';
?>
<div class="grid gap-5 lg:grid-cols-2">
  <form method="post" class="<?= CARD ?>" data-once>
    <?= csrf_field() ?><?= back_field() ?><input type="hidden" name="action" value="save_settings">
    <h2 class="font-bold">Fees &amp; matching</h2>
    <div class="mt-4 grid gap-4 sm:grid-cols-2">
      <label class="text-sm font-semibold">Platform fee (%)
        <input name="platform_fee_pct" inputmode="decimal" value="<?= e((string) round((float) setting('platform_fee_rate', '0.05') * 100, 2)) ?>" class="<?= $input ?>">
        <span class="mt-1 block text-xs font-normal text-gray-500">Taken from each paid job. Vulcators see it before accepting.</span></label>
      <label class="text-sm font-semibold">Convenience fee (₱)
        <input name="convenience_fee" inputmode="decimal" value="<?= e(setting('convenience_fee', '0')) ?>" class="<?= $input ?>">
        <span class="mt-1 block text-xs font-normal text-gray-500">Added to what the customer pays. 0 to turn off.</span></label>
      <label class="text-sm font-semibold">Time to accept (seconds)
        <input name="offer_timeout_seconds" inputmode="numeric" value="<?= e(setting('offer_timeout_seconds', '60')) ?>" class="<?= $input ?>">
        <span class="mt-1 block text-xs font-normal text-gray-500">How long a vulcator has before the request moves on.</span></label>
      <label class="text-sm font-semibold">Fallback search radius (km)
        <input name="default_search_radius_km" inputmode="decimal" value="<?= e(setting('default_search_radius_km', '5')) ?>" class="<?= $input ?>">
        <span class="mt-1 block text-xs font-normal text-gray-500">Used only if a town has no radius of its own.</span></label>
    </div>
    <button class="mt-5 rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-600">Save settings</button>
  </form>

  <section class="<?= CARD ?>">
    <h2 class="font-bold">Service area</h2>
    <p class="text-sm text-gray-500">Set in <code class="rounded bg-gray-100 px-1">includes/siargao.php</code>. Ask your developer to change these.</p>
    <table class="mt-3 w-full text-sm">
      <thead class="text-left text-xs uppercase tracking-wide text-gray-400"><tr><th class="py-1.5 font-semibold">Town</th><th class="font-semibold">Phase</th><th class="text-right font-semibold">Radius</th></tr></thead>
      <tbody class="divide-y divide-gray-100">
        <?php foreach (SIARGAO_TOWNS as $name => $t): ?><tr><td class="py-1.5"><?= e($name) ?></td><td><?= $t['phase'] ?></td><td class="text-right"><?= $t['radius_km'] ?> km</td></tr><?php endforeach; ?>
      </tbody>
    </table>
  </section>
</div>

<form method="post" class="<?= CARD ?>" data-once>
  <?= csrf_field() ?><?= back_field() ?><input type="hidden" name="action" value="save_services">
  <h2 class="font-bold">Services</h2>
  <p class="text-sm text-gray-500">The base price shows as "from ₱…" to customers. Each vulcator sets their own price on their Profile.</p>
  <div class="-mx-5 mt-3 overflow-x-auto px-5">
    <table class="w-full min-w-[520px] text-sm">
      <thead class="text-left text-xs uppercase tracking-wide text-gray-400"><tr><th class="py-2 font-semibold">Service</th><th class="font-semibold">Offered by</th><th class="font-semibold">Base price (₱)</th><th class="font-semibold">Active</th></tr></thead>
      <tbody class="divide-y divide-gray-100">
        <?php foreach ($services as $s): ?>
          <tr>
            <td class="py-2.5"><p class="font-semibold"><?= e($s['name']) ?></p><p class="text-xs text-gray-400"><?= e($s['description']) ?></p></td>
            <td class="text-gray-600"><?= (int) $s['offered_by'] ?> vulcator<?= $s['offered_by'] == 1 ? '' : 's' ?></td>
            <td><input name="base_price[<?= (int) $s['id'] ?>]" inputmode="decimal" value="<?= e(number_format((float) $s['base_price'], 2, '.', '')) ?>"
                       aria-label="Base price for <?= e($s['name']) ?>" class="w-28 rounded-lg border border-gray-300 px-2 py-1.5 text-right"></td>
            <td><input type="checkbox" name="active[<?= (int) $s['id'] ?>]" value="1" aria-label="<?= e($s['name']) ?> active" class="h-4 w-4 accent-brand-500" <?= $s['is_active'] ? 'checked' : '' ?>></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <button class="mt-4 rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-600">Save services</button>
</form>
<?php admin_end();
