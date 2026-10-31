<?php
// Customers: motorists this shop / vulcator has served.
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/jobs.php';
$user = require_login('provider');
$provider = provider_for_user($user['id']);
$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 60);

$sql = "SELECT u.id, u.full_name, u.phone, u.motorist_type, COUNT(*) AS jobs, SUM(j.status = 'paid') AS paid,
               COALESCE(SUM(CASE WHEN j.status = 'paid' THEN j.total_amount END), 0) AS spent, MAX(j.requested_at) AS last_job,
               (SELECT ROUND(AVG(r.stars), 1) FROM ratings r WHERE r.provider_id = ? AND r.customer_id = u.id) AS stars
        FROM jobs j JOIN users u ON u.id = j.customer_id
        WHERE j.provider_id = ?" . ($q !== '' ? ' AND (u.full_name LIKE ? OR u.phone LIKE ?)' : '') . "
        GROUP BY u.id ORDER BY MAX(j.requested_at) DESC";
$args = [$provider['id'], $provider['id']];
if ($q !== '') array_push($args, "%$q%", "%$q%");
$stmt = db()->prepare($sql);
$stmt->execute($args);
$rows = $stmt->fetchAll();

$activeTab = 'customers';
$greeting  = 'Customers';
$subline   = count($rows) . ' motorist' . (count($rows) === 1 ? '' : 's') . ' you\'ve served';
$pageTitle = 'Customers';
$statusDot = $provider['is_online'] ? 'online' : 'offline';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/app_header.php';
?>
<main class="mx-auto max-w-6xl space-y-5 px-5 pb-28 pt-5 md:px-8 md:pb-10">
  <form method="get" role="search" class="flex gap-2">
    <input name="q" value="<?= e($q) ?>" placeholder="Search name or phone" aria-label="Search customers" class="w-full max-w-md rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm">
    <button class="rounded-xl bg-brand-500 px-4 py-2 text-sm font-semibold text-white">Search</button>
  </form>
  <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-black/5">
    <?php if (!$rows): ?><p class="py-8 text-center text-sm text-gray-400"><?= $q ? 'No customers match.' : 'Customers you help will be listed here.' ?></p><?php endif; ?>
    <ul class="divide-y divide-gray-100">
      <?php foreach ($rows as $c): ?>
        <li class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center">
          <div class="flex min-w-0 flex-1 items-center gap-3">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-brand-100 text-sm font-bold text-brand-700"><?= e(initials($c['full_name'])) ?></span>
            <div class="min-w-0"><p class="truncate font-semibold"><?= e($c['full_name']) ?>
              <?php if ($c['motorist_type']): ?><span class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600"><?= e(MOTORIST_TYPES[$c['motorist_type']]) ?></span><?php endif; ?></p>
              <p class="truncate text-xs text-gray-500"><?= (int) $c['jobs'] ?> booking<?= $c['jobs'] == 1 ? '' : 's' ?> · last <?= e(date('M j, Y', strtotime($c['last_job']))) ?> · spent <?= e(peso((float) $c['spent'])) ?><?= $c['stars'] ? ' · rated you ★ ' . e($c['stars']) : '' ?></p></div>
          </div>
          <div class="flex gap-2 sm:shrink-0">
            <a href="tel:<?= e($c['phone']) ?>" class="rounded-xl px-3 py-2 text-sm font-semibold ring-1 ring-gray-200 hover:bg-gray-50">Call</a>
            <a href="sms:<?= e($c['phone']) ?>" class="rounded-xl px-3 py-2 text-sm font-semibold ring-1 ring-gray-200 hover:bg-gray-50">Text</a>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
</main>
<?php
require __DIR__ . '/includes/bottom_nav.php';
require __DIR__ . '/includes/footer.php';
