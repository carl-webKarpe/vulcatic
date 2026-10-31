<?php
// Bookings: everything a shop / skilled local is handling, plus new requests.
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/jobs.php';
$user = require_login('provider');
$provider = provider_for_user($user['id']);
$pid = $provider['id'];
$isShop = $provider['provider_type'] === 'shop';

$stmt = db()->prepare("SELECT o.id AS offer_id, o.offered_at, j.id, j.job_code, j.mode, j.vehicle_type, j.problem, j.address_text, j.municipality,
                              o.distance_km, u.full_name AS customer, TIMESTAMPDIFF(SECOND, o.offered_at, NOW()) AS age_s
                       FROM job_offers o JOIN jobs j ON j.id = o.job_id JOIN users u ON u.id = j.customer_id
                       WHERE o.provider_id = ? AND o.status = 'pending' AND j.status IN ('searching','matched') ORDER BY o.offered_at");
$stmt->execute([$pid]);
$incoming = array_values(array_filter($stmt->fetchAll(), fn ($o) => $o['age_s'] < offer_timeout($o['mode']) && ($o['mode'] === 'walk_in' || $provider['is_online'])));

$stmt = db()->prepare("SELECT j.id, j.job_code, j.status, j.mode, j.vehicle_type, j.problem, j.address_text, j.municipality, j.requested_at,
                              j.accepted_at, j.total_amount, j.assignment_status, u.full_name AS customer, u.phone, e.full_name AS assignee
                       FROM jobs j JOIN users u ON u.id = j.customer_id LEFT JOIN users e ON e.id = j.assigned_user_id
                       WHERE j.provider_id = ? AND j.status IN ('accepted','on_the_way','arrived','in_progress','completed')
                       ORDER BY FIELD(j.status, 'completed', 'in_progress', 'arrived', 'on_the_way', 'accepted'), j.accepted_at");
$stmt->execute([$pid]);
$active = $stmt->fetchAll();
$employees = $isShop ? shop_employees($pid) : [];
$needsPerson = array_filter($active, fn ($j) => $isShop && $employees && $j['status'] === 'accepted' && !$j['assignee']);

$stmt = db()->prepare("SELECT j.id, j.job_code, j.status, j.mode, j.problem, j.provider_earning, j.completed_at, j.requested_at, u.full_name AS customer, e.full_name AS assignee
                       FROM jobs j JOIN users u ON u.id = j.customer_id LEFT JOIN users e ON e.id = j.assigned_user_id
                       WHERE j.provider_id = ? AND j.status IN ('paid','cancelled') AND j.requested_at >= CURDATE() ORDER BY j.requested_at DESC");
$stmt->execute([$pid]);
$today = $stmt->fetchAll();

$activeTab = 'bookings';
$greeting  = 'Bookings';
$subline   = count($active) . ' active · ' . count($incoming) . ' new request' . (count($incoming) === 1 ? '' : 's');
$pageTitle = 'Bookings';
$useMap    = true;
$statusDot = $provider['is_online'] ? 'online' : 'offline';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/app_header.php';
$card = 'rounded-2xl bg-white p-5 shadow-sm ring-1 ring-black/5';
$chip = fn ($s) => '<span class="whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-bold ' . [
    'accepted' => 'bg-blue-50 text-blue-700', 'on_the_way' => 'bg-blue-50 text-blue-700', 'arrived' => 'bg-purple-50 text-purple-700',
    'in_progress' => 'bg-amber-50 text-amber-700', 'completed' => 'bg-amber-50 text-amber-700', 'paid' => 'bg-green-50 text-green-700', 'cancelled' => 'bg-gray-100 text-gray-500',
][$s] . '">' . e(STATUS_LABELS[$s] . ($s === 'completed' ? ' · to pay' : '')) . '</span>';
?>
<main id="bookings" data-offer-listener data-online="<?= $provider['is_online'] ? '1' : '0' ?>" data-walkin="<?= $provider['verification_status'] === 'verified' && $provider['accepts_walk_in'] ? '1' : '0' ?>"
      data-shares-location="0" data-stay class="mx-auto max-w-6xl space-y-5 px-5 pb-28 pt-5 md:px-8 md:pb-10">

  <?php if ($needsPerson): ?>
    <p class="rounded-2xl bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900 ring-1 ring-amber-200">⚠️ <?= count($needsPerson) ?> booking<?= count($needsPerson) > 1 ? 's need' : ' needs' ?> someone from your team. Open it and tap Assign.</p>
  <?php endif; ?>

  <section class="<?= $card ?>">
    <div class="mb-3 flex items-center justify-between"><h2 class="font-bold">New requests</h2>
      <span class="text-xs text-gray-400"><?= $provider['is_online'] ? 'Roadside on' : 'Roadside off' ?> · <?= $provider['accepts_walk_in'] ? 'Shop visits on' : 'Shop visits off' ?></span></div>
    <?php if (!$incoming): ?><p class="py-4 text-center text-sm text-gray-400">No new requests. They pop up here with a ring.</p><?php endif; ?>
    <ul class="divide-y divide-gray-100">
      <?php foreach ($incoming as $o): ?>
        <li class="flex flex-col gap-3 py-3 sm:flex-row sm:items-center">
          <div class="min-w-0 flex-1">
            <p class="font-semibold"><?= e($o['customer']) ?> · <?= e(PROBLEMS[$o['problem'] ?? 'other'][0]) ?> · <?= e(ucfirst($o['vehicle_type'])) ?></p>
            <p class="text-xs text-gray-500"><?= $o['mode'] === 'walk_in' ? '🏠 Shop visit' : '🛵 Roadside' ?> · <?= e((string) $o['distance_km']) ?> km<?= $o['address_text'] ? ' · ' . e($o['address_text']) : '' ?> · answer within <?= (int) ceil((offer_timeout($o['mode']) - $o['age_s']) / 60) ?> min</p>
          </div>
          <div class="flex gap-2">
            <button data-offer="<?= (int) $o['offer_id'] ?>" data-act="decline" class="rounded-xl px-4 py-2 text-sm font-semibold text-red-600 ring-1 ring-red-200 hover:bg-red-50">Decline</button>
            <button data-offer="<?= (int) $o['offer_id'] ?>" data-act="accept" class="rounded-xl bg-ok-500 px-4 py-2 text-sm font-semibold text-white hover:bg-ok-600">Accept</button>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <section class="<?= $card ?>">
    <h2 class="mb-3 font-bold">Active bookings</h2>
    <?php if (!$active): ?><p class="py-4 text-center text-sm text-gray-400">Nothing in progress.</p><?php endif; ?>
    <div class="grid gap-3 md:grid-cols-2">
      <?php foreach ($active as $j): ?>
        <a href="<?= url('provider_job.php?id=' . (int) $j['id']) ?>" class="block rounded-2xl p-4 ring-1 hover:shadow-md <?= $isShop && $employees && $j['status'] === 'accepted' && !$j['assignee'] ? 'ring-amber-300' : 'ring-gray-200' ?>">
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0"><p class="truncate font-semibold"><?= e($j['customer']) ?></p>
              <p class="truncate text-xs text-gray-500"><?= e(PROBLEMS[$j['problem'] ?? 'other'][0]) ?> · <?= e(ucfirst($j['vehicle_type'])) ?> · <?= $j['mode'] === 'walk_in' ? 'Shop visit' : 'Roadside' ?></p></div>
            <?= $chip($j['status']) ?>
          </div>
          <p class="mt-2 text-xs text-gray-500"><?= e($j['municipality'] ?: '') ?><?= $j['address_text'] ? ' · ' . e($j['address_text']) : '' ?></p>
          <p class="mt-2 flex items-center justify-between text-sm">
            <span><?= $isShop ? ($j['assignee'] ? '👷 ' . e($j['assignee']) . ($j['assignment_status'] === 'pending' ? ' <span class="text-amber-600">(waiting)</span>' : '') : '<span class="text-gray-500">' . ($employees ? 'Not assigned' : 'You') . '</span>') : '' ?></span>
            <b><?= e(peso((float) $j['total_amount'])) ?></b></p>
          <p class="mt-1 font-mono text-[11px] text-gray-400"><?= e($j['job_code']) ?> · accepted <?= e(date('g:i A', strtotime($j['accepted_at']))) ?></p>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="<?= $card ?>">
    <h2 class="mb-2 font-bold">Today's finished bookings</h2>
    <?php if (!$today): ?><p class="py-4 text-center text-sm text-gray-400">None yet today.</p><?php endif; ?>
    <ul class="divide-y divide-gray-100 text-sm">
      <?php foreach ($today as $j): ?>
        <li><a href="<?= url('provider_job.php?id=' . (int) $j['id']) ?>" class="flex items-center justify-between gap-3 py-2.5 hover:bg-gray-50">
          <span class="min-w-0 truncate"><b><?= e($j['customer']) ?></b> · <?= e(PROBLEMS[$j['problem'] ?? 'other'][0]) ?><?= $j['assignee'] ? ' · by ' . e(first_name($j['assignee'])) : '' ?></span>
          <span class="flex shrink-0 items-center gap-2"><?= $j['status'] === 'paid' ? '<b>' . e(peso((float) $j['provider_earning'])) . '</b>' : '' ?><?= $chip($j['status']) ?></span></a></li>
      <?php endforeach; ?>
    </ul>
  </section>
</main>
<?php require __DIR__ . '/includes/offer_modal.php'; ?>
<script>
  // Accept / decline right from the list
  document.querySelectorAll('[data-offer]').forEach((b) => b.addEventListener('click', async () => {
    b.disabled = true;
    try {
      const res = await api('offer_respond.php', { offer_id: Number(b.dataset.offer), action: b.dataset.act });
      if (b.dataset.act === 'accept') { location.href = VULCATIC.base + 'provider_job.php?id=' + res.job_id; return; }
      location.reload();
    } catch (e) { toast(e.message, 'error'); setTimeout(() => location.reload(), 1500); }
  }));
  // Keep the list fresh (unless a popup is open)
  setInterval(() => { if (!document.hidden && document.getElementById('offer-modal').classList.contains('hidden') && !document.querySelector('[role=alertdialog]')) location.reload(); }, 20000);
</script>
<?php
$pageScripts = ['dashboard.js'];
require __DIR__ . '/includes/bottom_nav.php';
require __DIR__ . '/includes/footer.php';
