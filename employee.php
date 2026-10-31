<?php
// Shop employee: on-duty switch and the jobs the shop assigned to them.
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/jobs.php';
$user = require_login('employee');

$stmt = db()->prepare('SELECT p.*, u.phone AS owner_phone FROM providers p JOIN users u ON u.id = p.user_id WHERE p.id = ?');
$stmt->execute([$user['employer_provider_id']]);
$shop = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $on = ($_POST['on_duty'] ?? '0') === '1' ? 1 : 0;
    db()->prepare('UPDATE users SET on_duty = ? WHERE id = ?')->execute([$on, $user['id']]);
    flash('success', $on ? 'You\'re on duty. ' . $shop['display_name'] . ' can assign you jobs.' : 'You\'re off duty.');
    redirect('employee.php');
}

$stmt = db()->prepare("SELECT COUNT(*) AS jobs, MAX(completed_at) AS last_done FROM jobs WHERE assigned_user_id = ? AND status = 'paid' AND completed_at >= CURDATE()");
$stmt->execute([$user['id']]);
$today = $stmt->fetch();
$stmt = db()->prepare("SELECT j.id, j.job_code, j.problem, j.completed_at, u.full_name AS customer FROM jobs j JOIN users u ON u.id = j.customer_id
                       WHERE j.assigned_user_id = ? AND j.status = 'paid' ORDER BY j.completed_at DESC LIMIT 5");
$stmt->execute([$user['id']]);
$recent = $stmt->fetchAll();

$onDuty = (bool) $user['on_duty'];
$activeTab = 'home';
$greeting  = 'Hi ' . e(first_name($user['full_name'])) . '!';
$subline   = 'Team member · ' . $shop['display_name'];
$statusDot = $onDuty ? 'online' : 'offline';
$pageTitle = 'My jobs';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/app_header.php';
?>
<main id="employee" class="mx-auto max-w-4xl space-y-5 px-5 pb-28 pt-5 md:px-8 md:pb-10">
  <form method="post" class="flex flex-col gap-4 rounded-3xl p-5 sm:flex-row sm:items-center <?= $onDuty ? 'bg-gradient-to-br from-ok-600 to-ok-500 text-white shadow-lg shadow-green-600/20' : 'bg-ink-900 text-white' ?>">
    <?= csrf_field() ?><input type="hidden" name="on_duty" value="<?= $onDuty ? '0' : '1' ?>">
    <?= logo_full('h-16 shrink-0 self-start') ?>
    <div class="flex-1">
      <p class="text-xl font-bold"><?= $onDuty ? 'You\'re on duty' : 'You\'re off duty' ?></p>
      <p class="text-sm text-white/80"><?= $onDuty ? 'Keep this page open. Jobs from ' . e($shop['display_name']) . ' appear here with a ring.' : 'Go on duty when you\'re working so the shop can assign you jobs.' ?></p>
      <?php if ($onDuty): ?><button type="button" data-enable-alerts="job" class="mt-3 hidden rounded-xl bg-white px-3 py-1.5 text-xs font-semibold text-ok-600">🔔 Turn on sound &amp; notifications</button><?php endif; ?>
    </div>
    <button class="shrink-0 rounded-2xl px-5 py-3 font-semibold <?= $onDuty ? 'bg-white/15 ring-1 ring-white/30 hover:bg-white/25' : 'bg-ok-500 text-white shadow-lg shadow-green-500/30 hover:bg-ok-600' ?>"><?= $onDuty ? 'Go off duty' : 'Go on duty' ?></button>
  </form>

  <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-black/5">
    <div class="mb-3 flex items-center justify-between"><h2 class="font-bold">Your jobs</h2><span class="text-xs text-gray-400">updates every few seconds</span></div>
    <ul id="job-list" class="space-y-3"><li class="py-6 text-center text-sm text-gray-400">Loading…</li></ul>
  </section>

  <div class="grid gap-5 md:grid-cols-2">
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-black/5">
      <h2 class="font-bold">Today</h2>
      <p class="mt-1 text-3xl font-bold"><?= (int) $today['jobs'] ?> <span class="text-base font-medium text-gray-500">job<?= $today['jobs'] == 1 ? '' : 's' ?> done</span></p>
      <ul class="mt-3 divide-y divide-gray-100 text-sm">
        <?php foreach ($recent as $r): ?>
          <li><a href="<?= url('provider_job.php?id=' . (int) $r['id']) ?>" class="flex justify-between py-2 hover:text-brand-600"><span><?= e($r['customer']) ?> · <?= e(PROBLEMS[$r['problem'] ?? 'other'][0]) ?></span><span class="text-gray-400"><?= e(date('M j', strtotime($r['completed_at']))) ?></span></a></li>
        <?php endforeach; ?>
      </ul>
    </section>
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-black/5">
      <h2 class="font-bold">Your shop</h2>
      <p class="mt-1 font-semibold"><?= e($shop['display_name']) ?></p>
      <p class="text-sm text-gray-500"><?= e($shop['address']) ?><?= $shop['municipality'] ? ', ' . e($shop['municipality']) : '' ?></p>
      <a href="tel:<?= e($shop['owner_phone']) ?>" class="mt-3 inline-block rounded-xl px-4 py-2 text-sm font-semibold ring-1 ring-gray-200 hover:bg-gray-50">Call the shop</a>
    </section>
  </div>
</main>
<?php
$pageScripts = ['employee.js'];
require __DIR__ . '/includes/bottom_nav.php';
require __DIR__ . '/includes/footer.php';
