<?php
// Approvals: new vulcators waiting for review, with everything needed to decide.
$pending = db()->query("SELECT p.*, u.full_name, u.phone, u.email, u.created_at AS joined FROM providers p JOIN users u ON u.id = p.user_id
                        WHERE p.verification_status = 'pending' ORDER BY p.created_at")->fetchAll();
$recent = db()->query("SELECT p.id, p.display_name, p.municipality, p.verification_status, p.updated_at FROM providers p
                       WHERE p.verification_status IN ('verified','rejected') ORDER BY p.updated_at DESC LIMIT 6")->fetchAll();
$svc = db()->prepare('SELECT s.name, ps.price FROM provider_services ps JOIN services s ON s.id = ps.service_id WHERE ps.provider_id = ? ORDER BY s.id');

admin_start('approvals', 'Approvals', 'New vulcators need approval before they can go online and receive jobs.', $user);
?>
<div class="rounded-2xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200">
  <b>Before approving:</b> call the number to confirm they're real, check the pin is on Siargao and near the address,
  and that prices look fair. Ask shops for a business permit if you can.
</div>

<?php if (!$pending): ?>
  <p class="<?= CARD ?> py-10 text-center text-gray-400">🎉 No vulcators waiting. New sign-ups will show up here.</p>
<?php endif; ?>

<div class="grid gap-4 lg:grid-cols-2">
  <?php foreach ($pending as $p): $svc->execute([$p['id']]); $services = $svc->fetchAll(); ?>
    <article class="<?= CARD ?>">
      <div class="flex items-start justify-between gap-3">
        <div>
          <p class="text-lg font-bold"><?= e($p['display_name']) ?></p>
          <p class="text-sm text-gray-500"><?= $p['provider_type'] === 'shop' ? 'Vulcanizing shop' : 'Skilled local' ?> · signed up <?= e(ago($p['created_at'])) ?></p>
        </div>
        <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 ring-1 ring-amber-200">Pending</span>
      </div>
      <dl class="mt-4 grid grid-cols-[7rem_1fr] gap-x-3 gap-y-2 text-sm">
        <dt class="text-gray-500">Owner</dt><dd class="font-medium"><?= e($p['full_name']) ?></dd>
        <dt class="text-gray-500">Mobile</dt><dd><a class="font-medium text-brand-600" href="tel:<?= e($p['phone']) ?>"><?= e($p['phone']) ?></a></dd>
        <?php if ($p['email']): ?><dt class="text-gray-500">Email</dt><dd><?= e($p['email']) ?></dd><?php endif; ?>
        <dt class="text-gray-500">Address</dt><dd><?= e($p['address']) ?><?= $p['municipality'] ? ', <b>' . e($p['municipality']) . '</b>' : '' ?></dd>
        <dt class="text-gray-500">Location</dt><dd><a class="text-brand-600 hover:underline" target="_blank" rel="noopener" href="https://www.openstreetmap.org/?mlat=<?= e($p['latitude']) ?>&mlon=<?= e($p['longitude']) ?>#map=17/<?= e($p['latitude']) ?>/<?= e($p['longitude']) ?>">View pin on map ↗</a></dd>
        <dt class="text-gray-500">Offers</dt><dd><?= implode(' · ', array_filter([$p['offers_onsite'] ? 'On-site repair' : '', $p['accepts_walk_in'] ? 'Walk-in' : ''])) ?></dd>
        <?php if ($p['opening_hours']): ?><dt class="text-gray-500">Hours</dt><dd><?= e($p['opening_hours']) ?></dd><?php endif; ?>
        <dt class="text-gray-500">GCash</dt><dd><?= e($p['gcash_number'] ?: 'Not set') ?></dd>
        <dt class="text-gray-500">Services</dt>
        <dd><?= $services ? implode('<br>', array_map(fn ($s) => e($s['name']) . ' · <b>' . e(peso((float) $s['price'])) . '</b>', $services)) : '<span class="text-gray-400">None yet</span>' ?></dd>
      </dl>
      <form method="post" class="mt-5 grid grid-cols-2 gap-2">
        <?= csrf_field() ?><?= back_field() ?><input type="hidden" name="provider_id" value="<?= (int) $p['id'] ?>">
        <button name="action" value="reject" data-confirm="Reject <?= e($p['display_name']) ?>?" data-confirm-label="Reject" data-danger
                class="rounded-xl py-2.5 text-sm font-semibold text-red-600 ring-1 ring-red-200 hover:bg-red-50">Reject</button>
        <button name="action" value="approve" class="rounded-xl bg-ok-500 py-2.5 text-sm font-semibold text-white hover:bg-ok-600">Approve</button>
      </form>
    </article>
  <?php endforeach; ?>
</div>

<?php if ($recent): ?>
  <section class="<?= CARD ?>">
    <h2 class="mb-2 font-bold">Recently decided</h2>
    <ul class="divide-y divide-gray-100 text-sm">
      <?php foreach ($recent as $r): ?>
        <li class="flex items-center justify-between py-2"><span><?= e($r['display_name']) ?> <span class="text-gray-400">· <?= e($r['municipality'] ?: '–') ?></span></span>
          <span class="<?= $r['verification_status'] === 'verified' ? 'text-green-700' : 'text-gray-500' ?> font-semibold"><?= $r['verification_status'] === 'verified' ? 'Approved' : 'Rejected' ?></span></li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>
<?php admin_end();
