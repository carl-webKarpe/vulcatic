<?php
// Password resets: open requests, plus a short history.
$open = db()->query("SELECT r.id, r.phone, r.note, r.created_at, r.user_id, u.full_name, u.role FROM password_reset_requests r
                     LEFT JOIN users u ON u.id = r.user_id WHERE r.status = 'open' ORDER BY r.created_at")->fetchAll();
$done = db()->query("SELECT r.phone, r.status, r.handled_at, u.full_name FROM password_reset_requests r
                     LEFT JOIN users u ON u.id = r.user_id WHERE r.status <> 'open' ORDER BY r.handled_at DESC LIMIT 10")->fetchAll();

admin_start('resets', 'Password resets', 'People who forgot their password. Vulcatic can\'t send email or SMS by itself, so you pass on a temporary password.', $user);
require __DIR__ . '/temp_card.php';
?>
<ol class="grid gap-3 text-sm md:grid-cols-3">
  <?php foreach (['Call the number to check it\'s really them.', 'Tap Set temporary password. It\'s shown once.', 'Text or read it to them. They pick a new password at login.'] as $i => $step): ?>
    <li class="flex gap-3 rounded-2xl bg-white p-4 ring-1 ring-black/5"><span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-blue-600 text-xs font-bold text-white"><?= $i + 1 ?></span><?= e($step) ?></li>
  <?php endforeach; ?>
</ol>

<section class="<?= CARD ?>">
  <h2 class="mb-2 font-bold">Open requests</h2>
  <?php if (!$open): ?><p class="py-6 text-center text-sm text-gray-400">No open requests.</p><?php endif; ?>
  <ul class="divide-y divide-gray-100">
    <?php foreach ($open as $r): ?>
      <li class="flex flex-col gap-3 py-3 sm:flex-row sm:items-center">
        <div class="min-w-0 flex-1">
          <p class="font-semibold"><?= $r['user_id'] ? e($r['full_name']) . ' <span class="text-xs font-medium text-gray-500">(' . e($r['role']) . ')</span>' : '<span class="text-gray-500">No account with this number</span>' ?></p>
          <p class="text-sm text-gray-600"><a class="text-brand-600" href="tel:<?= e($r['phone']) ?>"><?= e($r['phone']) ?></a> · <?= e(ago($r['created_at'])) ?><?= $r['note'] ? ' · “' . e($r['note']) . '”' : '' ?></p>
        </div>
        <form method="post" class="flex gap-2">
          <?= csrf_field() ?><?= back_field() ?><input type="hidden" name="reset_id" value="<?= (int) $r['id'] ?>">
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

<?php if ($done): ?>
  <section class="<?= CARD ?>">
    <h2 class="mb-2 font-bold">Recently handled</h2>
    <ul class="divide-y divide-gray-100 text-sm">
      <?php foreach ($done as $r): ?>
        <li class="flex justify-between py-2"><span><?= e($r['full_name'] ?: $r['phone']) ?></span>
          <span class="text-gray-500"><?= $r['status'] === 'done' ? 'Temporary password set' : 'Dismissed' ?> · <?= e(ago($r['handled_at'])) ?></span></li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>
<?php admin_end();
