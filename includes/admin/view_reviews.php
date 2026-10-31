<?php
// Reviews: what customers say. Low ratings come first so problems get noticed.
$show = ($_GET['show'] ?? '') === 'low' ? 'low' : 'all';
$stats = db()->query('SELECT COUNT(*) n, AVG(stars) avg FROM ratings')->fetch();
$dist = array_fill_keys([5, 4, 3, 2, 1], 0);
foreach (db()->query('SELECT stars, COUNT(*) n FROM ratings GROUP BY stars') as $r) $dist[(int) $r['stars']] = (int) $r['n'];
$max = max(1, ...array_values($dist));
$rows = db()->query('SELECT r.stars, r.comment, r.created_at, r.job_id, u.full_name AS customer, p.display_name AS vulcator, j.municipality
                     FROM ratings r JOIN users u ON u.id = r.customer_id JOIN providers p ON p.id = r.provider_id JOIN jobs j ON j.id = r.job_id '
                     . ($show === 'low' ? 'WHERE r.stars <= 3 ' : '') . 'ORDER BY r.created_at DESC LIMIT 60')->fetchAll();

admin_start('reviews', 'Reviews', ($stats['n'] ? number_format((float) $stats['avg'], 2) . ' average from ' . (int) $stats['n'] . ' ratings' : 'No ratings yet'), $user,
    pill_group(['all' => 'All', 'low' => '3★ or less'], $show, fn ($v) => admin_url(['view' => 'reviews', 'show' => $v === 'all' ? null : $v]), 'Show'));
?>
<div class="grid gap-5 lg:grid-cols-3">
  <section class="<?= CARD ?>">
    <p class="text-5xl font-bold"><?= $stats['n'] ? number_format((float) $stats['avg'], 1) : '–' ?> <span class="text-3xl text-amber-400">★</span></p>
    <p class="text-sm text-gray-500"><?= (int) $stats['n'] ?> ratings</p>
    <ul class="mt-5 space-y-2" aria-label="Ratings by stars">
      <?php foreach ($dist as $s => $n): ?>
        <li class="flex items-center gap-3 text-sm">
          <span class="w-8 shrink-0 text-gray-600"><?= $s ?>★</span>
          <span class="h-2.5 flex-1 overflow-hidden rounded-full bg-gray-100"><span class="block h-full rounded-full <?= $s <= 2 ? 'bg-red-400' : ($s === 3 ? 'bg-amber-400' : 'bg-brand-500') ?>" style="width: <?= round($n / $max * 100) ?>%"></span></span>
          <span class="w-8 shrink-0 text-right tabular-nums text-gray-600"><?= $n ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
  <section class="<?= CARD ?> lg:col-span-2">
    <h2 class="mb-2 font-bold"><?= $show === 'low' ? 'Low ratings' : 'Latest reviews' ?></h2>
    <?php if (!$rows): ?><p class="py-8 text-center text-sm text-gray-400">Nothing here.</p><?php endif; ?>
    <ul class="divide-y divide-gray-100">
      <?php foreach ($rows as $r): ?>
        <li class="py-3 <?= $r['stars'] <= 2 ? 'rounded-xl bg-red-50/60 px-3' : '' ?>">
          <div class="flex items-center justify-between gap-3">
            <p class="text-sm"><b><?= e($r['customer']) ?></b> <span class="text-gray-400">rated</span> <b><?= e($r['vulcator']) ?></b></p>
            <span class="shrink-0 text-amber-400" aria-label="<?= (int) $r['stars'] ?> stars"><?= str_repeat('★', (int) $r['stars']) ?><span class="text-gray-200"><?= str_repeat('★', 5 - (int) $r['stars']) ?></span></span>
          </div>
          <?php if ($r['comment']): ?><p class="mt-1 text-sm text-gray-700">“<?= e($r['comment']) ?>”</p><?php endif; ?>
          <p class="mt-1 text-xs text-gray-400"><?= e(ago($r['created_at'])) ?> · <?= e($r['municipality'] ?: '–') ?> ·
            <a class="text-brand-600" href="<?= admin_url(['view' => 'job', 'id' => $r['job_id']]) ?>">View job</a></p>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>
<?php admin_end();
