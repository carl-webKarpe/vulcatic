<?php
// Customers: who uses Vulcatic, how much, and account actions.
$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 60);
$where = "u.role = 'customer'"; $args = [];
if ($q !== '') { $where .= ' AND (u.full_name LIKE ? OR u.phone LIKE ? OR u.email LIKE ?)'; $like = '%' . $q . '%'; $args = [$like, $like, $like]; }
$stmt = db()->prepare("SELECT u.id, u.full_name, u.phone, u.email, u.is_active, u.created_at, u.motorist_type,
                              COUNT(j.id) AS requests, SUM(j.status = 'paid') AS paid, SUM(j.status = 'cancelled') AS cancelled,
                              COALESCE(SUM(CASE WHEN j.status = 'paid' THEN j.total_amount END), 0) AS spent, MAX(j.requested_at) AS last_request
                       FROM users u LEFT JOIN jobs j ON j.customer_id = u.id WHERE $where
                       GROUP BY u.id ORDER BY MAX(j.requested_at) IS NULL, MAX(j.requested_at) DESC, u.created_at DESC");
$stmt->execute($args);
$rows = $stmt->fetchAll();

admin_start('customers', 'Customers', count($rows) . ' customer' . (count($rows) === 1 ? '' : 's') . ' · suspend anyone misusing the service (fake requests, harassment).', $user);
?>
<form method="get" role="search" class="flex gap-2">
  <input type="hidden" name="view" value="customers">
  <input name="q" value="<?= e($q) ?>" placeholder="Search name, phone or email" aria-label="Search customers" class="w-full max-w-md rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm">
  <button class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white">Search</button>
</form>
<section class="<?= CARD ?>">
  <div class="-mx-5 overflow-x-auto px-5">
    <table class="w-full min-w-[760px] text-sm">
      <thead class="text-left text-xs uppercase tracking-wide text-gray-400">
        <tr><th class="py-2 font-semibold">Customer</th><th class="font-semibold">Joined</th><th class="text-right font-semibold">Requests</th>
            <th class="text-right font-semibold">Cancelled</th><th class="text-right font-semibold">Spent</th><th class="font-semibold">Last request</th><th class="text-right font-semibold">Action</th></tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        <?php foreach ($rows as $c): $flag = $c['requests'] >= 3 && $c['cancelled'] / max(1, $c['requests']) >= 0.5; ?>
          <tr>
            <td class="py-2.5"><p class="font-semibold"><?= e($c['full_name']) ?> <?= !$c['is_active'] ? '<span class="ml-1 rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-700 ring-1 ring-red-200">Suspended</span>' : '' ?></p>
              <p class="text-xs text-gray-400"><?= $c['motorist_type'] ? e(MOTORIST_TYPES[$c['motorist_type']]) . ' · ' : '' ?><a href="tel:<?= e($c['phone']) ?>" class="hover:text-brand-600"><?= e($c['phone']) ?></a><?= $c['email'] ? ' · ' . e($c['email']) : '' ?></p></td>
            <td class="text-gray-600"><?= e(date('M j, Y', strtotime($c['created_at']))) ?></td>
            <td class="text-right tabular-nums"><a href="<?= admin_url(['view' => 'jobs', 'customer' => $c['id'], 'days' => 'all']) ?>" class="hover:text-brand-600"><?= (int) $c['requests'] ?></a></td>
            <td class="text-right tabular-nums <?= $flag ? 'font-semibold text-red-600' : '' ?>" <?= $flag ? 'title="Cancels half or more of their requests"' : '' ?>><?= (int) $c['cancelled'] ?><?= $flag ? ' ⚠️' : '' ?></td>
            <td class="text-right tabular-nums"><?= e(peso((float) $c['spent'])) ?></td>
            <td class="text-gray-600"><?= $c['last_request'] ? e(ago($c['last_request'])) : '–' ?></td>
            <td class="text-right">
              <form method="post"><?= csrf_field() ?><?= back_field() ?><input type="hidden" name="customer_id" value="<?= (int) $c['id'] ?>">
                <?php if ($c['is_active']): ?>
                  <button name="action" value="suspend" data-confirm="Suspend <?= e($c['full_name']) ?>? They won't be able to log in." data-confirm-label="Suspend" data-danger
                          class="rounded-lg px-2.5 py-1 text-xs font-semibold text-red-600 ring-1 ring-red-200 hover:bg-red-50">Suspend</button>
                <?php else: ?>
                  <button name="action" value="reactivate" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-green-700 ring-1 ring-green-300 hover:bg-green-50">Reactivate</button>
                <?php endif; ?>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7" class="py-10 text-center text-gray-400">No customers match.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php admin_end();
