<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_login('admin');

// Approve / reject a vulcator
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $status = ($_POST['action'] ?? '') === 'approve' ? 'verified' : 'rejected';
    db()->prepare("UPDATE providers SET verification_status = ? WHERE id = ? AND verification_status = 'pending'")
        ->execute([$status, (int) ($_POST['provider_id'] ?? 0)]);
    flash('success', $status === 'verified' ? 'Vulcator approved.' : 'Vulcator rejected.');
    redirect('admin.php');
}

$pending = db()->query("SELECT p.id, p.display_name, p.provider_type, p.address, p.municipality, p.latitude, p.longitude,
                               p.offers_onsite, p.accepts_walk_in, p.created_at, u.full_name, u.phone
                        FROM providers p JOIN users u ON u.id = p.user_id
                        WHERE p.verification_status = 'pending'
                        ORDER BY p.created_at")->fetchAll();

$greeting  = 'Admin';
$subline   = count($pending) . ' vulcator(s) waiting for approval';
$pageTitle = 'Admin';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/app_header.php';
?>
<main class="mx-auto max-w-5xl px-5 pb-10 pt-5 md:px-8">
  <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5">
    <h2 class="mb-3 text-sm font-semibold text-gray-500">Pending vulcators</h2>
    <?php if (!$pending): ?>
      <p class="py-6 text-center text-sm text-gray-400">Nothing to review.</p>
    <?php endif; ?>
    <ul class="divide-y divide-gray-100">
      <?php foreach ($pending as $p): ?>
        <li class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center">
          <div class="min-w-0 flex-1">
            <p class="font-semibold"><?= e($p['display_name']) ?>
              <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600"><?= $p['provider_type'] === 'shop' ? 'Shop' : 'Skilled local' ?></span></p>
            <p class="text-sm text-gray-500"><?= e($p['full_name']) ?> · <?= e($p['phone']) ?></p>
            <p class="text-sm text-gray-500"><?= e($p['address']) ?><?= $p['municipality'] ? ', ' . e($p['municipality']) : '' ?> ·
              <a class="text-brand-600 hover:underline" target="_blank" rel="noopener"
                 href="https://www.openstreetmap.org/?mlat=<?= e($p['latitude']) ?>&mlon=<?= e($p['longitude']) ?>#map=18/<?= e($p['latitude']) ?>/<?= e($p['longitude']) ?>">view on map</a></p>
            <p class="text-xs text-gray-400"><?= $p['offers_onsite'] ? 'On-site' : '' ?><?= $p['offers_onsite'] && $p['accepts_walk_in'] ? ' · ' : '' ?><?= $p['accepts_walk_in'] ? 'Walk-in' : '' ?></p>
          </div>
          <form method="post" class="flex gap-2">
            <?= csrf_field() ?>
            <input type="hidden" name="provider_id" value="<?= (int) $p['id'] ?>">
            <button name="action" value="approve" class="rounded-lg bg-ok-500 px-4 py-2 text-sm font-semibold text-white hover:bg-ok-600">Approve</button>
            <button name="action" value="reject" class="rounded-lg px-4 py-2 text-sm font-semibold text-red-600 ring-1 ring-red-300 hover:bg-red-50">Reject</button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
