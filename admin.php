<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/jobs.php';
require __DIR__ . '/includes/charts.php';
$user = require_login('admin');

// ---------- Password reset requests ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_id'])) {
    verify_csrf();
    $stmt = db()->prepare("SELECT r.*, u.full_name FROM password_reset_requests r LEFT JOIN users u ON u.id = r.user_id
                           WHERE r.id = ? AND r.status = 'open'");
    $stmt->execute([(int) $_POST['reset_id']]);
    if ($r = $stmt->fetch()) {
        if (($_POST['action'] ?? '') === 'reset' && $r['user_id']) {
            // Easy to read out over the phone: no 0/O or 1/I/l.
            $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            $temp = '';
            for ($i = 0; $i < 8; $i++) $temp .= $chars[random_int(0, strlen($chars) - 1)];
            db()->prepare('UPDATE users SET password_hash = ?, must_change_password = 1, is_active = 1 WHERE id = ?')
                ->execute([password_hash($temp, PASSWORD_DEFAULT), $r['user_id']]);
            db()->prepare('DELETE FROM login_attempts WHERE login = ?')->execute([$r['phone']]);
            // Shown once on the next page load, until the admin closes it.
            $_SESSION['temp_password'] = ['name' => $r['full_name'], 'phone' => $r['phone'], 'password' => substr($temp, 0, 4) . '-' . substr($temp, 4)];
            db()->prepare("UPDATE password_reset_requests SET status = 'done', handled_at = NOW() WHERE user_id = ? AND status = 'open'")->execute([$r['user_id']]);
        } else {
            db()->prepare("UPDATE password_reset_requests SET status = 'dismissed', handled_at = NOW() WHERE id = ?")->execute([$r['id']]);
            flash('success', 'Request dismissed.');
        }
    }
    redirect('admin.php');
}
if (isset($_GET['temp_done'])) {
    unset($_SESSION['temp_password']);
    redirect('admin.php');
}

// ---------- Actions on vulcators ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pid = (int) ($_POST['provider_id'] ?? 0);
    $stmt = db()->prepare('SELECT p.*, u.id AS uid FROM providers p JOIN users u ON u.id = p.user_id WHERE p.id = ?');
    $stmt->execute([$pid]);
    if ($p = $stmt->fetch()) {
        $messages = [
            'approve'    => ["UPDATE providers SET verification_status = 'verified' WHERE id = ?", 'approved'],
            'reject'     => ["UPDATE providers SET verification_status = 'rejected', is_online = 0 WHERE id = ?", 'rejected'],
            'suspend'    => ["UPDATE users SET is_active = 0 WHERE id = (SELECT user_id FROM providers WHERE id = ?)", 'suspended'],
            'reactivate' => ["UPDATE users SET is_active = 1 WHERE id = (SELECT user_id FROM providers WHERE id = ?)", 'reactivated'],
        ];
        $action = $_POST['action'] ?? '';
        if (isset($messages[$action])) {
            db()->prepare($messages[$action][0])->execute([$pid]);
            if (in_array($action, ['suspend', 'reject'], true)) {
                db()->prepare('UPDATE providers SET is_online = 0 WHERE id = ?')->execute([$pid]);
                db()->prepare("UPDATE job_offers SET status = 'expired' WHERE provider_id = ? AND status = 'pending'")->execute([$pid]);
            }
            flash('success', $p['display_name'] . ' ' . $messages[$action][1] . '.');
        }
    }
    redirect('admin.php?' . http_build_query(array_intersect_key($_GET, ['days' => 1, 'town' => 1, 'status' => 1])));
}

// ---------- Filters ----------
$days   = (int) ($_GET['days'] ?? 14);
$days   = in_array($days, [7, 14, 30], true) ? $days : 14;
$town   = array_key_exists($_GET['town'] ?? '', SIARGAO_TOWNS) ? $_GET['town'] : '';
$status = in_array($_GET['status'] ?? '', ['active', 'paid', 'cancelled'], true) ? $_GET['status'] : '';
$since  = "CURDATE() - INTERVAL " . ($days - 1) . " DAY";

// ---------- KPIs for the period ----------
$k = db()->query("SELECT COUNT(*) AS requests,
        SUM(status = 'paid') AS paid,
        SUM(status = 'cancelled') AS cancelled,
        COALESCE(SUM(CASE WHEN status = 'paid' THEN total_amount END), 0) AS gmv,
        COALESCE(SUM(CASE WHEN status = 'paid' THEN platform_fee END), 0) AS fees,
        AVG(CASE WHEN accepted_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, requested_at, accepted_at) END) AS accept_s,
        AVG(CASE WHEN arrived_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, requested_at, arrived_at) END) AS arrive_s
      FROM jobs WHERE requested_at >= $since")->fetch();
$people = db()->query("SELECT
        (SELECT COUNT(*) FROM users WHERE role = 'customer') AS customers,
        (SELECT COUNT(*) FROM providers WHERE verification_status = 'verified') AS verified,
        (SELECT COUNT(*) FROM providers p JOIN users u ON u.id = p.user_id WHERE p.is_online = 1 AND u.is_active = 1 AND p.verification_status = 'verified') AS online,
        (SELECT COUNT(*) FROM providers WHERE verification_status = 'pending') AS pending,
        (SELECT AVG(stars) FROM ratings) AS rating")->fetch();
$closed = (int) $k['paid'] + (int) $k['cancelled'];
$completion = $closed ? round($k['paid'] / $closed * 100) : null;
$mins = fn ($s) => $s === null ? '–' : max(1, (int) round($s / 60)) . ' min';

// ---------- Requests per day ----------
$byDay = last_days($days);
foreach (db()->query("SELECT DATE(requested_at) d, COUNT(*) n FROM jobs WHERE requested_at >= $since GROUP BY DATE(requested_at)") as $r) {
    $byDay[$r['d']] = (int) $r['n'];
}

// ---------- Per town ----------
$towns = [];
foreach (SIARGAO_TOWNS as $name => $t) {
    $towns[$name] = ['phase' => $t['phase'], 'lat' => $t['lat'], 'lng' => $t['lng'], 'requests' => 0, 'paid' => 0,
                     'cancelled' => 0, 'gmv' => 0.0, 'accept_s' => null, 'vulcators' => 0, 'online' => 0];
}
foreach (db()->query("SELECT municipality m, COUNT(*) n, SUM(status = 'paid') paid, SUM(status = 'cancelled') cancelled,
                             COALESCE(SUM(CASE WHEN status = 'paid' THEN total_amount END), 0) gmv,
                             AVG(CASE WHEN accepted_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, requested_at, accepted_at) END) accept_s
                      FROM jobs WHERE requested_at >= $since AND municipality IS NOT NULL GROUP BY municipality") as $r) {
    if (isset($towns[$r['m']])) {
        $towns[$r['m']] = array_merge($towns[$r['m']], ['requests' => (int) $r['n'], 'paid' => (int) $r['paid'],
            'cancelled' => (int) $r['cancelled'], 'gmv' => (float) $r['gmv'], 'accept_s' => $r['accept_s']]);
    }
}
foreach (db()->query("SELECT p.municipality m, COUNT(*) n, SUM(p.is_online = 1 AND u.is_active = 1) online FROM providers p
                      JOIN users u ON u.id = p.user_id WHERE p.verification_status = 'verified' GROUP BY p.municipality") as $r) {
    if (isset($towns[$r['m']])) { $towns[$r['m']]['vulcators'] = (int) $r['n']; $towns[$r['m']]['online'] = (int) $r['online']; }
}

// ---------- Vulcators ----------
$vulcators = db()->query("SELECT p.id, p.display_name, p.provider_type, p.municipality, p.address, p.verification_status, p.is_online,
                                 p.rating_avg, p.rating_count, p.latitude, p.longitude, p.created_at, u.full_name, u.phone, u.is_active,
                                 (SELECT COUNT(*) FROM jobs j WHERE j.provider_id = p.id AND j.status = 'paid') AS jobs,
                                 (SELECT COALESCE(SUM(provider_earning), 0) FROM jobs j WHERE j.provider_id = p.id AND j.status = 'paid') AS earned
                          FROM providers p JOIN users u ON u.id = p.user_id
                          ORDER BY FIELD(p.verification_status, 'pending', 'verified', 'rejected'), p.municipality, p.display_name")->fetchAll();
$pending = array_filter($vulcators, fn ($v) => $v['verification_status'] === 'pending');
$resets = db()->query("SELECT r.id, r.phone, r.note, r.created_at, r.user_id, u.full_name, u.role FROM password_reset_requests r
                       LEFT JOIN users u ON u.id = r.user_id WHERE r.status = 'open' ORDER BY r.created_at")->fetchAll();
$temp = $_SESSION['temp_password'] ?? null;

// ---------- Recent jobs ----------
$where = ['1 = 1']; $args = [];
if ($town)   { $where[] = 'j.municipality = ?'; $args[] = $town; }
if ($status === 'active')    $where[] = "j.status IN ('searching','matched','accepted','on_the_way','arrived','completed')";
if ($status === 'paid')      $where[] = "j.status = 'paid'";
if ($status === 'cancelled') $where[] = "j.status = 'cancelled'";
$stmt = db()->prepare('SELECT j.id, j.job_code, j.status, j.municipality, j.total_amount, j.requested_at, s.name AS service,
                              u.full_name AS customer, p.display_name AS vulcator
                       FROM jobs j JOIN services s ON s.id = j.service_id JOIN users u ON u.id = j.customer_id
                       LEFT JOIN providers p ON p.id = j.provider_id
                       WHERE ' . implode(' AND ', $where) . ' ORDER BY j.requested_at DESC LIMIT 25');
$stmt->execute($args);
$recent = $stmt->fetchAll();

$chip = [
    'searching' => 'bg-amber-50 text-amber-700 ring-amber-200', 'matched' => 'bg-amber-50 text-amber-700 ring-amber-200',
    'accepted' => 'bg-blue-50 text-blue-700 ring-blue-200', 'on_the_way' => 'bg-blue-50 text-blue-700 ring-blue-200',
    'arrived' => 'bg-blue-50 text-blue-700 ring-blue-200', 'completed' => 'bg-amber-50 text-amber-700 ring-amber-200',
    'paid' => 'bg-green-50 text-green-700 ring-green-300', 'cancelled' => 'bg-gray-100 text-gray-500 ring-gray-200',
];
$q = fn (array $change) => '?' . http_build_query(array_merge(['days' => $days, 'town' => $town, 'status' => $status], $change));

$activeTab = 'admin';
$greeting  = 'Admin dashboard';
$subline   = 'Vulcatic · Siargao Island';
$pageTitle = 'Admin';
$useMap    = true;
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/app_header.php';
$card = 'rounded-2xl bg-white p-5 shadow-sm ring-1 ring-black/5';
?>
<main class="mx-auto max-w-6xl space-y-5 px-5 pb-28 pt-5 md:px-8 md:pb-10">
  <!-- Period -->
  <div class="flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-gray-500">Showing the last <b><?= $days ?> days</b></p>
    <div class="inline-flex rounded-xl bg-white p-1 shadow-sm ring-1 ring-black/5" role="group" aria-label="Period">
      <?php foreach ([7, 14, 30] as $d): ?>
        <a href="<?= e($q(['days' => $d])) ?>" class="rounded-lg px-3 py-1.5 text-sm font-semibold <?= $d === $days ? 'bg-brand-500 text-white' : 'text-gray-600 hover:bg-gray-100' ?>"><?= $d ?> days</a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if ($temp): ?>
    <section class="rounded-2xl bg-ink-900 p-5 text-white">
      <p class="text-sm text-white/70">Temporary password for <b class="text-white"><?= e($temp['name']) ?></b> · <?= e($temp['phone']) ?></p>
      <p class="mt-2 font-mono text-3xl font-bold tracking-widest text-ok-500"><?= e($temp['password']) ?></p>
      <p class="mt-2 text-sm text-white/70">Call or text it to them now (without the dash is fine). They'll choose a new password when they log in.
        This won't be shown again.</p>
      <div class="mt-4 flex flex-wrap gap-2">
        <a href="sms:<?= e($temp['phone']) ?>?body=<?= rawurlencode('Vulcatic: your temporary password is ' . str_replace('-', '', $temp['password']) . '. Log in and choose a new password.') ?>"
           class="rounded-xl bg-white px-4 py-2 text-sm font-semibold text-ink-900">Text it</a>
        <a href="tel:<?= e($temp['phone']) ?>" class="rounded-xl bg-white/10 px-4 py-2 text-sm font-semibold ring-1 ring-white/20">Call</a>
        <a href="<?= url('admin.php?temp_done=1') ?>" class="rounded-xl px-4 py-2 text-sm font-semibold text-white/70 hover:text-white">Done, close this</a>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($resets): ?>
    <section class="rounded-2xl bg-blue-50 p-5 ring-1 ring-blue-200">
      <h2 class="font-bold text-blue-900"><?= count($resets) ?> password reset request<?= count($resets) > 1 ? 's' : '' ?></h2>
      <p class="text-sm text-blue-800/80">Check it's really them (call the number) before setting a temporary password.</p>
      <ul class="mt-3 divide-y divide-blue-200">
        <?php foreach ($resets as $r): ?>
          <li class="flex flex-col gap-3 py-3 sm:flex-row sm:items-center">
            <div class="min-w-0 flex-1">
              <p class="font-semibold"><?= $r['user_id'] ? e($r['full_name']) . ' <span class="text-xs font-medium text-gray-500">(' . e($r['role']) . ')</span>' : '<span class="text-gray-500">No account with this number</span>' ?></p>
              <p class="text-sm text-gray-600"><?= e($r['phone']) ?> · <?= e(date('M j, g:i A', strtotime($r['created_at']))) ?><?= $r['note'] ? ' · “' . e($r['note']) . '”' : '' ?></p>
            </div>
            <form method="post" class="flex gap-2">
              <?= csrf_field() ?><input type="hidden" name="reset_id" value="<?= (int) $r['id'] ?>">
              <?php if ($r['user_id']): ?>
                <button name="action" value="reset" data-confirm="Set a temporary password for <?= e($r['full_name']) ?>?" data-confirm-label="Set password"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Set temporary password</button>
              <?php endif; ?>
              <button name="action" value="dismiss" class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50">Dismiss</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

  <?php if ($pending): ?>
    <section class="rounded-2xl bg-amber-50 p-5 ring-1 ring-amber-200">
      <h2 class="font-bold text-amber-900"><?= count($pending) ?> vulcator<?= count($pending) > 1 ? 's' : '' ?> waiting for approval</h2>
      <ul class="mt-3 divide-y divide-amber-200">
        <?php foreach ($pending as $p): ?>
          <li class="flex flex-col gap-3 py-3 sm:flex-row sm:items-center">
            <div class="min-w-0 flex-1">
              <p class="font-semibold"><?= e($p['display_name']) ?> <span class="ml-1 rounded bg-white px-1.5 py-0.5 text-xs font-medium text-gray-600"><?= $p['provider_type'] === 'shop' ? 'Shop' : 'Skilled local' ?></span></p>
              <p class="text-sm text-gray-600"><?= e($p['full_name']) ?> · <?= e($p['phone']) ?> · <?= e($p['address']) ?><?= $p['municipality'] ? ', ' . e($p['municipality']) : '' ?> ·
                <a class="text-brand-600 hover:underline" target="_blank" rel="noopener" href="https://www.openstreetmap.org/?mlat=<?= e($p['latitude']) ?>&mlon=<?= e($p['longitude']) ?>#map=18/<?= e($p['latitude']) ?>/<?= e($p['longitude']) ?>">view on map</a></p>
            </div>
            <form method="post" class="flex gap-2">
              <?= csrf_field() ?><input type="hidden" name="provider_id" value="<?= (int) $p['id'] ?>">
              <button name="action" value="approve" class="rounded-lg bg-ok-500 px-4 py-2 text-sm font-semibold text-white hover:bg-ok-600">Approve</button>
              <button name="action" value="reject" class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-red-600 ring-1 ring-red-300 hover:bg-red-50">Reject</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

  <!-- KPIs -->
  <section class="grid grid-cols-2 gap-3 md:grid-cols-4">
    <?php foreach ([
        ['Requests', number_format((int) $k['requests']), ($k['cancelled'] ?? 0) . ' cancelled', true],
        ['Completion rate', $completion === null ? '–' : $completion . '%', (int) $k['paid'] . ' paid jobs', false],
        ['Customer payments', peso((float) $k['gmv']), 'paid by customers', false],
        ['Platform earnings', peso((float) $k['fees']), (setting('platform_fee_rate', '0.05') * 100) . '% fee', false],
        ['Time to accept', $mins($k['accept_s']), 'average', false],
        ['Time to arrive', $mins($k['arrive_s']), 'from request', false],
        ['Vulcators online', $people['online'] . ' / ' . $people['verified'], $people['pending'] . ' pending approval', false],
        ['Customers', number_format((int) $people['customers']), $people['rating'] ? '★ ' . number_format((float) $people['rating'], 1) . ' avg rating' : 'no ratings yet', false],
    ] as [$label, $value, $sub, $dark]): ?>
      <div class="rounded-2xl p-4 <?= $dark ? 'bg-ink-900 text-white' : 'bg-white ring-1 ring-black/5' ?>">
        <p class="text-xs <?= $dark ? 'text-white/60' : 'text-gray-500' ?>"><?= $label ?></p>
        <p class="mt-1 text-2xl font-bold"><?= e($value) ?></p>
        <p class="mt-0.5 text-xs <?= $dark ? 'text-white/50' : 'text-gray-400' ?>"><?= e($sub) ?></p>
      </div>
    <?php endforeach; ?>
  </section>

  <div class="grid gap-5 lg:grid-cols-5">
    <section class="<?= $card ?> min-w-0 lg:col-span-3">
      <h2 class="mb-4 font-bold">Requests per day</h2>
      <?= bar_chart(day_rows($byDay), fn ($v) => (string) (int) $v, 'Requests per day, last ' . $days . ' days') ?>
    </section>
    <section class="<?= $card ?> min-w-0 lg:col-span-2">
      <h2 class="font-bold">Requests by town</h2>
      <p class="text-xs text-gray-400">Circle size = requests in this period</p>
      <div id="town-map" class="mt-3 h-64 overflow-hidden rounded-xl"
           data-towns='<?= e(json_encode(array_map(fn ($n, $t) => ['name' => $n, 'lat' => $t['lat'], 'lng' => $t['lng'], 'requests' => $t['requests'], 'paid' => $t['paid'], 'vulcators' => $t['vulcators']], array_keys($towns), $towns))) ?>'></div>
    </section>
  </div>

  <!-- Per town -->
  <section class="<?= $card ?>">
    <h2 class="mb-3 font-bold">Towns</h2>
    <div class="-mx-5 overflow-x-auto px-5">
      <table class="w-full min-w-[640px] text-sm">
        <thead class="text-left text-xs uppercase tracking-wide text-gray-400">
          <tr><th class="py-2 font-semibold">Town</th><th class="font-semibold">Phase</th><th class="text-right font-semibold">Requests</th>
              <th class="text-right font-semibold">Completed</th><th class="text-right font-semibold">Time to accept</th>
              <th class="text-right font-semibold">Payments</th><th class="text-right font-semibold">Vulcators</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php foreach ($towns as $name => $t): $c = $t['paid'] + $t['cancelled']; ?>
            <tr>
              <td class="py-2.5 font-semibold"><a href="<?= e($q(['town' => $name])) ?>#jobs" class="hover:text-brand-600"><?= e($name) ?></a></td>
              <td><span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600"><?= $t['phase'] ?></span></td>
              <td class="text-right tabular-nums"><?= $t['requests'] ?></td>
              <td class="text-right tabular-nums"><?= $c ? round($t['paid'] / $c * 100) . '%' : '–' ?></td>
              <td class="text-right tabular-nums"><?= $mins($t['accept_s']) ?></td>
              <td class="text-right tabular-nums"><?= e(peso($t['gmv'])) ?></td>
              <td class="text-right tabular-nums"><?= $t['vulcators'] ?> <span class="text-xs text-gray-400">(<?= $t['online'] ?> online)</span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php $gaps = array_keys(array_filter($towns, fn ($t) => $t['vulcators'] === 0)); if ($gaps): ?>
      <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 ring-1 ring-amber-200">No approved vulcators yet in: <b><?= e(implode(', ', $gaps)) ?></b>. Recruit there next.</p>
    <?php endif; ?>
  </section>

  <!-- Vulcators -->
  <section class="<?= $card ?>">
    <h2 class="mb-3 font-bold">Vulcators</h2>
    <div class="-mx-5 overflow-x-auto px-5">
      <table class="w-full min-w-[760px] text-sm">
        <thead class="text-left text-xs uppercase tracking-wide text-gray-400">
          <tr><th class="py-2 font-semibold">Name</th><th class="font-semibold">Town</th><th class="font-semibold">Status</th>
              <th class="text-right font-semibold">Rating</th><th class="text-right font-semibold">Paid jobs</th>
              <th class="text-right font-semibold">Earned</th><th class="text-right font-semibold">Action</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php foreach ($vulcators as $v):
              $state = !$v['is_active'] ? ['Suspended', 'bg-red-50 text-red-700 ring-red-200']
                    : ($v['verification_status'] === 'pending' ? ['Pending', 'bg-amber-50 text-amber-700 ring-amber-200']
                    : ($v['verification_status'] === 'rejected' ? ['Rejected', 'bg-gray-100 text-gray-500 ring-gray-200']
                    : ($v['is_online'] ? ['Online', 'bg-green-50 text-green-700 ring-green-300'] : ['Offline', 'bg-gray-100 text-gray-600 ring-gray-200']))); ?>
            <tr>
              <td class="py-2.5"><p class="font-semibold"><?= e($v['display_name']) ?></p>
                <p class="text-xs text-gray-400"><?= $v['provider_type'] === 'shop' ? 'Shop' : 'Skilled local' ?> · <?= e($v['phone']) ?></p></td>
              <td><?= e($v['municipality'] ?: '–') ?></td>
              <td><span class="rounded-full px-2 py-0.5 text-xs font-semibold ring-1 <?= $state[1] ?>"><?= $state[0] ?></span></td>
              <td class="text-right tabular-nums"><?= $v['rating_count'] ? '★ ' . number_format((float) $v['rating_avg'], 1) . ' <span class="text-xs text-gray-400">(' . (int) $v['rating_count'] . ')</span>' : '–' ?></td>
              <td class="text-right tabular-nums"><?= (int) $v['jobs'] ?></td>
              <td class="text-right tabular-nums"><?= e(peso((float) $v['earned'])) ?></td>
              <td class="text-right">
                <form method="post" class="inline-flex gap-1">
                  <?= csrf_field() ?><input type="hidden" name="provider_id" value="<?= (int) $v['id'] ?>">
                  <?php if ($v['verification_status'] === 'pending'): ?>
                    <button name="action" value="approve" class="rounded-lg bg-ok-500 px-2.5 py-1 text-xs font-semibold text-white hover:bg-ok-600">Approve</button>
                    <button name="action" value="reject" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-red-600 ring-1 ring-red-200 hover:bg-red-50">Reject</button>
                  <?php elseif ($v['is_active']): ?>
                    <button name="action" value="suspend" data-confirm="Suspend <?= e($v['display_name']) ?>? They won't be able to log in." data-confirm-label="Suspend" data-danger class="rounded-lg px-2.5 py-1 text-xs font-semibold text-red-600 ring-1 ring-red-200 hover:bg-red-50">Suspend</button>
                  <?php else: ?>
                    <button name="action" value="reactivate" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-green-700 ring-1 ring-green-300 hover:bg-green-50">Reactivate</button>
                  <?php endif; ?>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <!-- Recent jobs -->
  <section id="jobs" class="<?= $card ?> scroll-mt-4">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
      <h2 class="font-bold">Recent jobs<?= $town ? ' · ' . e($town) : '' ?></h2>
      <form method="get" class="flex flex-wrap gap-2 text-sm">
        <input type="hidden" name="days" value="<?= $days ?>">
        <select name="town" aria-label="Town" class="rounded-lg border border-gray-300 px-2 py-1.5" onchange="this.form.submit()">
          <option value="">All towns</option>
          <?php foreach (array_keys(SIARGAO_TOWNS) as $name): ?><option <?= $name === $town ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
        </select>
        <select name="status" aria-label="Status" class="rounded-lg border border-gray-300 px-2 py-1.5" onchange="this.form.submit()">
          <?php foreach (['' => 'All statuses', 'active' => 'In progress', 'paid' => 'Paid', 'cancelled' => 'Cancelled'] as $v => $t): ?>
            <option value="<?= $v ?>" <?= $v === $status ? 'selected' : '' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
        <noscript><button class="rounded-lg bg-gray-100 px-3">Filter</button></noscript>
      </form>
    </div>
    <div class="-mx-5 overflow-x-auto px-5">
      <table class="w-full min-w-[700px] text-sm">
        <thead class="text-left text-xs uppercase tracking-wide text-gray-400">
          <tr><th class="py-2 font-semibold">Job</th><th class="font-semibold">When</th><th class="font-semibold">Town</th>
              <th class="font-semibold">Customer → Vulcator</th><th class="font-semibold">Status</th><th class="text-right font-semibold">Amount</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php foreach ($recent as $j): ?>
            <tr>
              <td class="py-2.5"><p class="font-semibold"><?= e($j['service']) ?></p><p class="font-mono text-[11px] text-gray-400"><?= e($j['job_code']) ?></p></td>
              <td class="text-gray-600"><?= e(date('M j, g:i A', strtotime($j['requested_at']))) ?></td>
              <td><?= e($j['municipality'] ?: '–') ?></td>
              <td><?= e($j['customer']) ?> <span class="text-gray-400">→</span> <?= e($j['vulcator'] ?: '—') ?></td>
              <td><span class="rounded-full px-2 py-0.5 text-xs font-semibold ring-1 <?= $chip[$j['status']] ?>"><?= e(ucwords(str_replace('_', ' ', $j['status']))) ?></span></td>
              <td class="text-right tabular-nums <?= $j['status'] === 'cancelled' ? 'text-gray-400 line-through' : 'font-semibold' ?>"><?= e(peso((float) $j['total_amount'])) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$recent): ?><tr><td colspan="6" class="py-8 text-center text-gray-400">No jobs match these filters.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
<?php
$pageScripts = ['admin.js'];
require __DIR__ . '/includes/bottom_nav.php';
require __DIR__ . '/includes/footer.php';
