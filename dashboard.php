<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/jobs.php';
require __DIR__ . '/includes/charts.php';
$user = require_login('provider');
$provider = provider_for_user($user['id']);
$pid = $provider['id'];

// Skilled locals work one job at a time: go straight to it. Shops use Bookings.
if ($provider['provider_type'] !== 'shop' && ($jobId = active_job_id_for_provider($pid))) {
    redirect('provider_job.php?id=' . $jobId);
}

$stmt = db()->prepare("SELECT
    COUNT(CASE WHEN completed_at >= CURDATE() THEN 1 END) AS jobs_today,
    COALESCE(SUM(CASE WHEN completed_at >= CURDATE() THEN provider_earning END), 0) AS earned_today,
    COUNT(CASE WHEN completed_at >= CURDATE() - INTERVAL WEEKDAY(CURDATE()) DAY THEN 1 END) AS jobs_week,
    COALESCE(SUM(CASE WHEN completed_at >= CURDATE() - INTERVAL WEEKDAY(CURDATE()) DAY THEN provider_earning END), 0) AS earned_week
    FROM jobs WHERE provider_id = ? AND status = 'paid'");
$stmt->execute([$pid]);
$k = $stmt->fetch();

$stmt = db()->prepare("SELECT COUNT(*) AS offers, SUM(status = 'accepted') AS accepted, SUM(status = 'expired') AS missed
                       FROM job_offers WHERE provider_id = ? AND offered_at >= NOW() - INTERVAL 30 DAY");
$stmt->execute([$pid]);
$o = $stmt->fetch();
$acceptRate = $o['offers'] ? round($o['accepted'] / $o['offers'] * 100) : null;

$byDay = last_days(7);
$stmt = db()->prepare("SELECT DATE(completed_at) d, SUM(provider_earning) s FROM jobs
                       WHERE provider_id = ? AND status = 'paid' AND completed_at >= CURDATE() - INTERVAL 6 DAY GROUP BY DATE(completed_at)");
$stmt->execute([$pid]);
foreach ($stmt->fetchAll() as $r) $byDay[$r['d']] = (float) $r['s'];
$rows = [];
foreach ($byDay as $date => $v) $rows[] = ['label' => date('D, M j', strtotime($date)), 'short' => date('D', strtotime($date)), 'value' => $v];

$stmt = db()->prepare('SELECT j.id, j.status, j.vehicle_type, j.provider_earning, j.requested_at, j.municipality,
                              u.full_name AS customer, s.name AS service
                       FROM jobs j JOIN users u ON u.id = j.customer_id JOIN services s ON s.id = j.service_id
                       WHERE j.provider_id = ? ORDER BY j.requested_at DESC LIMIT 5');
$stmt->execute([$pid]);
$recentJobs = $stmt->fetchAll();

$stmt = db()->prepare('SELECT COUNT(*) FROM provider_services WHERE provider_id = ?');
$stmt->execute([$pid]);
$serviceCount = (int) $stmt->fetchColumn();
$checklist = [
    ['Approved by Vulcatic', $provider['verification_status'] === 'verified', null],
    ['Services and prices set', $serviceCount > 0, 'profile.php'],
    ['GCash number added', (bool) $provider['gcash_number'], 'profile.php'],
    ['Location updated today', $provider['last_location_at'] && strtotime($provider['last_location_at']) > strtotime('-1 day'), 'profile.php'],
    ['Sound alerts on', null, null], // checked in the browser
];

$isOnline = (bool) $provider['is_online'];
$verified = $provider['verification_status'] === 'verified';
$radius   = search_radius_for($provider['municipality']);
$hour     = (int) date('G');
$greeting = 'Good ' . ($hour < 12 ? 'morning' : ($hour < 18 ? 'afternoon' : 'evening')) . ', ' . e(first_name($user['full_name'])) . '!';
$subline  = date('l, F j') . ($provider['municipality'] ? ' · ' . $provider['municipality'] : '');
$statusDot = $isOnline ? 'online' : 'offline';
$pageTitle = 'Dashboard';
$useMap    = true;
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/app_header.php';
$card = 'rounded-2xl bg-white p-5 shadow-sm ring-1 ring-black/5';
?>
<main id="dashboard" data-offer-listener data-online="<?= $isOnline ? '1' : '0' ?>" data-walkin="<?= $verified && $provider['accepts_walk_in'] ? '1' : '0' ?>"
      data-shares-location="<?= $provider['provider_type'] === 'individual' ? '1' : '0' ?>" class="mx-auto max-w-6xl space-y-5 px-5 pb-28 pt-5 md:px-8 md:pb-10">

  <!-- Status -->
  <?php if (!$verified): ?>
    <section class="flex flex-col gap-4 rounded-3xl bg-amber-50 p-5 ring-1 ring-amber-200 sm:flex-row sm:items-center">
      <?= logo_full('h-20 shrink-0 self-start') ?>
      <div><p class="text-lg font-bold text-amber-900"><?= $provider['verification_status'] === 'rejected' ? 'Your application was not approved' : 'Almost there! We\'re checking your details' ?></p>
        <p class="text-sm text-amber-800"><?= $provider['verification_status'] === 'rejected' ? 'Please contact Vulcatic support.' : 'An admin may call you to confirm. Meanwhile, set your services, prices and GCash number so you\'re ready.' ?></p></div>
    </section>
  <?php elseif ($isOnline): ?>
    <section id="waiting" class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-ok-600 to-ok-500 p-5 text-white shadow-lg shadow-green-600/20 sm:p-6">
      <div class="absolute -right-10 -top-10 h-40 w-40 rounded-full bg-white/10"></div>
      <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center">
        <span class="relative grid h-16 w-16 shrink-0 place-items-center">
          <span class="absolute inset-0 animate-ping rounded-full bg-white/30"></span>
          <img src="<?= url('assets/img/gecko-head.png') ?>" alt="" class="relative h-16 w-16 rounded-full bg-white/90 p-1">
        </span>
        <div class="flex-1">
          <p class="text-xl font-bold">You're online · waiting for requests</p>
          <p class="text-sm text-white/85">Keep this page open. New requests within <?= e((string) $radius) ?> km pop up here with a ring, and you have <?= (int) setting('offer_timeout_seconds', '60') ?> seconds to answer.</p>
          <button type="button" data-enable-alerts="job" class="mt-3 hidden rounded-xl bg-white px-3 py-1.5 text-xs font-semibold text-ok-600 hover:bg-green-50">🔔 Turn on sound &amp; notifications</button>
        </div>
        <form method="post" action="<?= url('availability.php') ?>" class="shrink-0">
          <?= csrf_field() ?><input type="hidden" name="is_online" value="0"><input type="hidden" name="back" value="dashboard.php">
          <button class="rounded-xl bg-white/15 px-4 py-2 text-sm font-semibold ring-1 ring-white/30 hover:bg-white/25">Go offline</button>
        </form>
      </div>
    </section>
  <?php else: ?>
    <section class="relative overflow-hidden rounded-3xl bg-ink-900 p-5 text-white sm:p-6">
      <div class="absolute -right-10 -top-10 h-40 w-40 rounded-full bg-white/5"></div>
      <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center">
        <?= logo_full('h-20 shrink-0 self-start') ?>
        <div class="flex-1">
          <p class="text-xl font-bold">You're offline</p>
          <p class="text-sm text-white/70">Go online when you're ready to fix tires. Customers within <?= e((string) $radius) ?> km<?= $provider['municipality'] ? ' of ' . e($provider['municipality']) : '' ?> can then reach you.</p>
        </div>
        <form method="post" action="<?= url('availability.php') ?>" class="shrink-0">
          <?= csrf_field() ?><input type="hidden" name="is_online" value="1"><input type="hidden" name="back" value="dashboard.php">
          <button class="btn-shine w-full rounded-2xl bg-ok-500 px-6 py-3 font-semibold text-white shadow-lg shadow-green-500/30 hover:bg-ok-600 sm:w-auto">Go online</button>
        </form>
      </div>
    </section>
  <?php endif; ?>

  <!-- Numbers -->
  <section class="grid grid-cols-2 gap-3 md:grid-cols-4">
    <div class="rounded-2xl bg-ink-900 p-4 text-white"><p class="text-xs text-white/60">Earned today</p>
      <p class="mt-1 text-xl font-bold text-ok-500 sm:text-2xl"><?= e(peso((float) $k['earned_today'])) ?></p><p class="text-xs text-white/50"><?= (int) $k['jobs_today'] ?> job<?= $k['jobs_today'] == 1 ? '' : 's' ?> today</p></div>
    <div class="rounded-2xl bg-white p-4 ring-1 ring-black/5"><p class="text-xs text-gray-500">This week</p>
      <p class="mt-1 text-xl font-bold sm:text-2xl"><?= e(peso((float) $k['earned_week'])) ?></p><p class="text-xs text-gray-400"><?= (int) $k['jobs_week'] ?> paid job<?= $k['jobs_week'] == 1 ? '' : 's' ?></p></div>
    <div class="rounded-2xl bg-white p-4 ring-1 ring-black/5"><p class="text-xs text-gray-500">Acceptance rate</p>
      <p class="mt-1 text-xl font-bold sm:text-2xl"><?= $acceptRate === null ? '–' : $acceptRate . '%' ?></p><p class="text-xs text-gray-400"><?= (int) $o['offers'] ?> request<?= $o['offers'] == 1 ? '' : 's' ?> · 30 days</p></div>
    <div class="rounded-2xl bg-white p-4 ring-1 ring-black/5"><p class="text-xs text-gray-500">Your rating</p>
      <p class="mt-1 text-xl font-bold sm:text-2xl"><?= $provider['rating_count'] ? number_format((float) $provider['rating_avg'], 1) . ' <span class="text-amber-400">★</span>' : '–' ?></p><p class="text-xs text-gray-400"><?= (int) $provider['rating_count'] ?> rating<?= $provider['rating_count'] == 1 ? '' : 's' ?></p></div>
  </section>

  <div class="grid gap-5 lg:grid-cols-5">
    <section class="<?= $card ?> min-w-0 lg:col-span-3">
      <div class="mb-4 flex items-baseline justify-between"><h2 class="font-bold">Earnings, last 7 days</h2>
        <a href="<?= url('history.php') ?>" class="text-sm font-semibold text-brand-600">All earnings →</a></div>
      <?= bar_chart($rows, fn ($v) => '₱' . number_format($v), 'Net earnings per day, last 7 days') ?>
    </section>
    <section class="<?= $card ?> min-w-0 lg:col-span-2">
      <h2 class="font-bold">Your area</h2>
      <p class="text-xs text-gray-400">You get requests inside the circle (<?= e((string) $radius) ?> km)</p>
      <div id="coverage-map" class="mt-3 h-56 overflow-hidden rounded-xl"
           data-lat="<?= e((string) $provider['latitude']) ?>" data-lng="<?= e((string) $provider['longitude']) ?>" data-radius="<?= e((string) $radius) ?>"></div>
      <p class="mt-2 text-xs text-gray-500">Last location update: <?= $provider['last_location_at'] ? e(date('M j, g:i A', strtotime($provider['last_location_at']))) : 'never' ?> ·
        <a href="<?= url('profile.php') ?>" class="font-semibold text-brand-600">Update</a></p>
    </section>
  </div>

  <div class="grid gap-5 lg:grid-cols-5">
    <section class="<?= $card ?> min-w-0 lg:col-span-3">
      <div class="mb-2 flex items-baseline justify-between"><h2 class="font-bold">Recent jobs</h2>
        <a href="<?= url('history.php') ?>" class="text-sm font-semibold text-brand-600">See all →</a></div>
      <?php if (!$recentJobs): ?>
        <p class="py-8 text-center text-sm text-gray-400">No jobs yet. Go online to start receiving requests.</p>
      <?php else: ?>
        <ul class="divide-y divide-gray-100">
          <?php foreach ($recentJobs as $job): $done = $job['status'] === 'paid'; ?>
            <li><a href="<?= url('provider_job.php?id=' . (int) $job['id']) ?>" class="flex items-center gap-3 rounded-xl px-1 py-3 hover:bg-gray-50">
              <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-gray-100 text-sm font-semibold text-gray-600"><?= e(initials($job['customer'])) ?></span>
              <div class="min-w-0 flex-1">
                <p class="truncate font-medium"><?= e($job['customer']) ?></p>
                <p class="truncate text-xs text-gray-500"><?= e($job['service']) ?> · <?= e(ucfirst($job['vehicle_type'])) ?> · <?= e(date('M j, g:i A', strtotime($job['requested_at']))) ?></p>
              </div>
              <div class="text-right"><p class="text-sm font-bold <?= $job['status'] === 'cancelled' ? 'text-gray-400 line-through' : '' ?>"><?= e(peso((float) $job['provider_earning'])) ?></p>
                <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1 <?= $done ? 'bg-green-50 text-green-700 ring-green-300' : 'bg-gray-100 text-gray-500 ring-gray-200' ?>"><?= $done ? 'DONE' : e(strtoupper(str_replace('_', ' ', $job['status']))) ?></span></div>
            </a></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
    <section class="<?= $card ?> min-w-0 lg:col-span-2">
      <h2 class="font-bold">Get more jobs</h2>
      <p class="text-xs text-gray-400">Complete these so customers pick you</p>
      <ul class="mt-3 space-y-2" id="checklist">
        <?php foreach ($checklist as [$label, $ok, $link]): ?>
          <li class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm <?= $ok ? 'bg-green-50' : 'bg-gray-50' ?>" <?= $ok === null ? 'data-check="alerts"' : '' ?>>
            <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full text-xs font-bold <?= $ok ? 'bg-ok-500 text-white' : 'bg-white text-gray-400 ring-1 ring-gray-300' ?>"><?= $ok ? '✓' : '' ?></span>
            <span class="flex-1 <?= $ok ? 'text-green-800' : 'font-medium' ?>"><?= e($label) ?></span>
            <?php if (!$ok && $link): ?><a href="<?= url($link) ?>" class="text-xs font-semibold text-brand-600">Fix →</a><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if ($o['missed']): ?><p class="mt-3 text-xs text-gray-500">You let <?= (int) $o['missed'] ?> request<?= $o['missed'] == 1 ? '' : 's' ?> time out this month. Turn on sound so you don't miss the next one.</p><?php endif; ?>
    </section>
  </div>
</main>

<?php require __DIR__ . '/includes/offer_modal.php'; ?>
<?php
$activeTab   = 'home';
$pageScripts = ['dashboard.js'];
require __DIR__ . '/includes/bottom_nav.php';
require __DIR__ . '/includes/footer.php';
