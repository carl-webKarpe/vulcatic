<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_login('provider');

$stmt = db()->prepare('SELECT * FROM providers WHERE user_id = ?');
$stmt->execute([$user['id']]);
$provider = $stmt->fetch();

// Availability toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if ($provider['verification_status'] !== 'verified') {
        flash('error', 'Your account is still being verified. You can go online once approved.');
    } else {
        $online = ($_POST['is_online'] ?? '0') === '1' ? 1 : 0;
        db()->prepare('UPDATE providers SET is_online = ? WHERE id = ?')->execute([$online, $provider['id']]);
        flash('success', $online ? 'You are now online and can receive jobs.' : 'You are now offline.');
    }
    redirect('dashboard.php');
}

$stmt = db()->prepare("SELECT COUNT(*) AS jobs_today, COALESCE(SUM(provider_earning), 0) AS earned_today
                       FROM jobs
                       WHERE provider_id = ? AND status IN ('completed','paid') AND completed_at >= CURDATE()");
$stmt->execute([$provider['id']]);
$today = $stmt->fetch();

$stmt = db()->prepare('SELECT j.job_code, j.status, j.vehicle_type, j.provider_earning,
                              u.full_name AS customer, s.name AS service
                       FROM jobs j
                       JOIN users u    ON u.id = j.customer_id
                       JOIN services s ON s.id = j.service_id
                       WHERE j.provider_id = ?
                       ORDER BY j.requested_at DESC
                       LIMIT 5');
$stmt->execute([$provider['id']]);
$recentJobs = $stmt->fetchAll();

$isOnline  = (bool) $provider['is_online'];
$greeting  = 'Welcome back, ' . e(first_name($user['full_name'])) . '!';
$subline   = $isOnline ? 'You are available for jobs' : 'You are offline';
$statusDot = $isOnline ? 'online' : 'offline';
$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/app_header.php';
?>
<main class="mx-auto max-w-5xl space-y-5 px-5 pb-28 pt-5 md:px-8 md:pb-10">
  <?php if ($provider['verification_status'] === 'pending'): ?>
    <p class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">
      <strong>Verification pending.</strong> An admin will review your details. You can go online once you're approved.
    </p>
  <?php elseif ($provider['verification_status'] === 'rejected'): ?>
    <p class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200">
      Your application was not approved. Please contact support.
    </p>
  <?php endif; ?>

  <div class="grid gap-5 md:grid-cols-2">
    <section class="grid grid-cols-2 gap-3">
      <div class="rounded-2xl bg-ink-900 p-4 text-white">
        <p class="text-3xl font-bold"><?= (int) $today['jobs_today'] ?></p>
        <p class="text-sm text-white/70">Jobs today</p>
      </div>
      <div class="rounded-2xl bg-ink-900 p-4 text-white">
        <p class="text-3xl font-bold text-ok-500"><?= e(peso((float) $today['earned_today'])) ?></p>
        <p class="text-sm text-white/70">Earned today</p>
      </div>
    </section>

    <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5">
      <h2 class="mb-3 text-sm font-semibold text-gray-500">Availability</h2>
      <form method="post" class="flex items-center gap-4">
        <?= csrf_field() ?>
        <input type="hidden" name="is_online" value="<?= $isOnline ? '0' : '1' ?>">
        <button type="submit" role="switch" aria-checked="<?= $isOnline ? 'true' : 'false' ?>"
                class="relative h-7 w-12 shrink-0 rounded-full transition <?= $isOnline ? 'bg-ok-500' : 'bg-red-400' ?>">
          <span class="absolute top-1 h-5 w-5 rounded-full bg-white shadow transition-all <?= $isOnline ? 'left-6' : 'left-1' ?>"></span>
          <span class="sr-only">Toggle availability</span>
        </button>
        <div>
          <p class="font-semibold"><?= $isOnline ? 'Currently online' : 'Currently offline' ?></p>
          <p class="text-sm text-gray-500"><?= $isOnline ? 'Accepting new jobs' : 'Offline at the moment' ?></p>
        </div>
      </form>
    </section>
  </div>

  <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5">
    <h2 class="mb-3 text-sm font-semibold text-gray-500">Recent jobs</h2>
    <?php if (!$recentJobs): ?>
      <p class="py-6 text-center text-sm text-gray-400">No jobs yet. Go online to start receiving requests.</p>
    <?php else: ?>
      <ul class="divide-y divide-gray-100">
        <?php foreach ($recentJobs as $job): ?>
          <?php $done = in_array($job['status'], ['completed', 'paid'], true); ?>
          <li class="flex items-center gap-3 py-3">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-gray-100 text-sm font-semibold text-gray-600">
              <?= e(initials($job['customer'])) ?>
            </span>
            <div class="min-w-0 flex-1">
              <p class="truncate font-medium"><?= e($job['customer']) ?></p>
              <p class="truncate text-xs text-gray-500"><?= e($job['service']) ?> · <?= e(ucfirst($job['vehicle_type'])) ?> · <?= e(peso((float) $job['provider_earning'])) ?></p>
            </div>
            <span class="rounded-full px-3 py-1 text-xs font-semibold ring-1
                         <?= $done ? 'bg-green-50 text-green-700 ring-green-300' : 'bg-amber-50 text-amber-700 ring-amber-300' ?>">
              <?= $done ? 'DONE' : e(strtoupper(str_replace('_', ' ', $job['status']))) ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</main>
<?php
$activeTab = 'home';
require __DIR__ . '/includes/bottom_nav.php';
require __DIR__ . '/includes/footer.php';
