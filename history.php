<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/jobs.php';
require __DIR__ . '/includes/charts.php';
$user = require_login();
if ($user['role'] === 'admin') redirect('admin.php');

$isProvider = $user['role'] === 'provider';
$statusLabel = [
    'searching' => ['Searching', 'bg-amber-50 text-amber-700 ring-amber-200'],
    'matched'   => ['Searching', 'bg-amber-50 text-amber-700 ring-amber-200'],
    'accepted'  => ['Accepted', 'bg-blue-50 text-blue-700 ring-blue-200'],
    'on_the_way'=> ['On the way', 'bg-blue-50 text-blue-700 ring-blue-200'],
    'arrived'   => ['Arrived', 'bg-blue-50 text-blue-700 ring-blue-200'],
    'completed' => ['To pay', 'bg-amber-50 text-amber-700 ring-amber-200'],
    'paid'      => ['Done', 'bg-green-50 text-green-700 ring-green-300'],
    'cancelled' => ['Cancelled', 'bg-gray-100 text-gray-500 ring-gray-200'],
];

if ($isProvider) {
    $provider = provider_for_user($user['id']);
    $pid = $provider['id'];

    $stmt = db()->prepare("SELECT
        COALESCE(SUM(CASE WHEN completed_at >= CURDATE() THEN provider_earning END), 0) AS today,
        COALESCE(SUM(CASE WHEN completed_at >= CURDATE() - INTERVAL WEEKDAY(CURDATE()) DAY THEN provider_earning END), 0) AS week,
        COALESCE(SUM(CASE WHEN completed_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN provider_earning END), 0) AS month,
        COALESCE(SUM(provider_earning), 0) AS total,
        COUNT(*) AS jobs
        FROM jobs WHERE provider_id = ? AND status = 'paid'");
    $stmt->execute([$pid]);
    $sum = $stmt->fetch();

    $byDay = last_days(14);
    $stmt = db()->prepare("SELECT DATE(completed_at) d, SUM(provider_earning) s FROM jobs
                           WHERE provider_id = ? AND status = 'paid' AND completed_at >= CURDATE() - INTERVAL 13 DAY
                           GROUP BY DATE(completed_at)");
    $stmt->execute([$pid]);
    foreach ($stmt->fetchAll() as $r) $byDay[$r['d']] = (float) $r['s'];

    $stmt = db()->prepare('SELECT j.id, j.job_code, j.status, j.vehicle_type, j.municipality, j.provider_earning AS amount,
                                  j.requested_at, s.name AS service, u.full_name AS other
                           FROM jobs j JOIN services s ON s.id = j.service_id JOIN users u ON u.id = j.customer_id
                           WHERE j.provider_id = ? ORDER BY j.requested_at DESC LIMIT 50');
    $stmt->execute([$pid]);
    $jobs = $stmt->fetchAll();

    $stmt = db()->prepare('SELECT r.stars, r.comment, r.created_at, u.full_name FROM ratings r
                           JOIN users u ON u.id = r.customer_id WHERE r.provider_id = ? ORDER BY r.created_at DESC LIMIT 10');
    $stmt->execute([$pid]);
    $ratings = $stmt->fetchAll();
} else {
    $stmt = db()->prepare("SELECT COUNT(*) AS total, SUM(status = 'paid') AS done,
                                  COALESCE(SUM(CASE WHEN status = 'paid' THEN total_amount END), 0) AS spent
                           FROM jobs WHERE customer_id = ?");
    $stmt->execute([$user['id']]);
    $sum = $stmt->fetch();

    $stmt = db()->prepare('SELECT j.id, j.job_code, j.status, j.vehicle_type, j.municipality, j.total_amount AS amount,
                                  j.requested_at, s.name AS service, p.display_name AS other
                           FROM jobs j JOIN services s ON s.id = j.service_id LEFT JOIN providers p ON p.id = j.provider_id
                           WHERE j.customer_id = ? ORDER BY j.requested_at DESC LIMIT 50');
    $stmt->execute([$user['id']]);
    $jobs = $stmt->fetchAll();
}

$activeTab = 'history';
$greeting  = $isProvider ? 'Earnings' : 'History';
$subline   = $isProvider ? 'Your jobs, earnings and ratings' : 'Your requests and receipts';
$pageTitle = $greeting;
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/app_header.php';
?>
<main class="mx-auto max-w-6xl space-y-5 px-5 pb-28 pt-5 md:px-8 md:pb-10">
  <?php if ($isProvider): ?>
    <section class="grid grid-cols-2 gap-3 md:grid-cols-4">
      <?php foreach ([['Today', $sum['today']], ['This week', $sum['week']], ['This month', $sum['month']], ['All time', $sum['total']]] as $i => [$label, $v]): ?>
        <div class="rounded-2xl p-4 <?= $i === 0 ? 'bg-ink-900 text-white' : 'bg-white ring-1 ring-black/5' ?>">
          <p class="text-xs <?= $i === 0 ? 'text-white/60' : 'text-gray-500' ?>"><?= $label ?></p>
          <p class="mt-1 text-xl font-bold sm:text-2xl <?= $i === 0 ? 'text-ok-500' : '' ?>"><?= e(peso((float) $v)) ?></p>
        </div>
      <?php endforeach; ?>
    </section>

    <div class="grid gap-5 md:grid-cols-3">
      <section class="min-w-0 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-black/5 md:col-span-2">
        <div class="mb-4 flex items-baseline justify-between">
          <h2 class="font-bold">Net earnings, last 14 days</h2>
          <span class="text-xs text-gray-400">after platform fee</span>
        </div>
        <?= bar_chart(day_rows($byDay), fn ($v) => '₱' . number_format($v), 'Net earnings per day, last 14 days') ?>
      </section>
      <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-black/5">
        <h2 class="font-bold">Your rating</h2>
        <p class="mt-2 text-4xl font-bold"><?= $provider['rating_count'] ? number_format((float) $provider['rating_avg'], 1) : '–' ?>
          <span class="text-2xl text-amber-400">★</span></p>
        <p class="text-sm text-gray-500"><?= (int) $provider['rating_count'] ?> rating<?= $provider['rating_count'] == 1 ? '' : 's' ?> · <?= (int) $sum['jobs'] ?> paid job<?= $sum['jobs'] == 1 ? '' : 's' ?></p>
        <ul class="mt-4 max-h-56 space-y-3 overflow-y-auto">
          <?php foreach ($ratings as $r): ?>
            <li class="rounded-xl bg-gray-50 p-3 text-sm">
              <p class="flex justify-between"><b><?= e(first_name($r['full_name'])) ?></b>
                <span class="text-amber-400" aria-label="<?= (int) $r['stars'] ?> stars"><?= str_repeat('★', (int) $r['stars']) ?><span class="text-gray-300"><?= str_repeat('★', 5 - (int) $r['stars']) ?></span></span></p>
              <?php if ($r['comment']): ?><p class="mt-1 text-gray-600">“<?= e($r['comment']) ?>”</p><?php endif; ?>
            </li>
          <?php endforeach; ?>
          <?php if (!$ratings): ?><li class="text-sm text-gray-400">No ratings yet.</li><?php endif; ?>
        </ul>
      </section>
    </div>
  <?php else: ?>
    <section class="grid grid-cols-3 gap-2 sm:gap-3">
      <div class="rounded-2xl bg-white p-4 ring-1 ring-black/5"><p class="text-xs text-gray-500">Requests</p><p class="mt-1 text-xl font-bold sm:text-2xl"><?= (int) $sum['total'] ?></p></div>
      <div class="rounded-2xl bg-white p-4 ring-1 ring-black/5"><p class="text-xs text-gray-500">Completed</p><p class="mt-1 text-xl font-bold sm:text-2xl"><?= (int) $sum['done'] ?></p></div>
      <div class="rounded-2xl bg-ink-900 p-4 text-white"><p class="text-xs text-white/60">Total spent</p><p class="mt-1 break-all text-lg font-bold sm:text-2xl"><?= e(peso((float) $sum['spent'])) ?></p></div>
    </section>
  <?php endif; ?>

  <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5 md:p-5">
    <h2 class="mb-2 font-bold"><?= $isProvider ? 'Jobs' : 'Your requests' ?></h2>
    <?php if (!$jobs): ?>
      <p class="py-8 text-center text-sm text-gray-400"><?= $isProvider ? 'No jobs yet. Go online to receive requests.' : 'No requests yet. When you need help, tap Find a vulcator on Home.' ?></p>
    <?php else: ?>
      <ul class="divide-y divide-gray-100">
        <?php foreach ($jobs as $j): [$label, $chip] = $statusLabel[$j['status']]; ?>
          <li>
            <a href="<?= url(($isProvider ? 'provider_job.php' : 'job.php') . '?id=' . (int) $j['id']) ?>" class="flex items-center gap-3 rounded-xl px-1 py-3 hover:bg-gray-50">
              <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-gray-100 text-xs font-bold text-gray-600"><?= e($j['other'] ? initials($j['other']) : '?') ?></span>
              <div class="min-w-0 flex-1">
                <p class="truncate font-semibold"><?= e($j['service']) ?><?= $j['other'] ? ' · ' . e($j['other']) : '' ?></p>
                <p class="truncate text-xs text-gray-500"><?= e(date('M j, g:i A', strtotime($j['requested_at']))) ?><?= $j['municipality'] ? ' · ' . e($j['municipality']) : '' ?> · <?= e(ucfirst($j['vehicle_type'])) ?></p>
              </div>
              <div class="shrink-0 text-right">
                <p class="text-sm font-bold <?= $j['status'] === 'cancelled' ? 'text-gray-400 line-through' : '' ?>"><?= e(peso((float) $j['amount'])) ?></p>
                <span class="mt-0.5 inline-block rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1 <?= $chip ?>"><?= $label ?></span>
              </div>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</main>
<?php
require __DIR__ . '/includes/bottom_nav.php';
require __DIR__ . '/includes/footer.php';
