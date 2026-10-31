<?php
// One job in full: people, money, timeline, which vulcators were offered it.
$job = load_job((int) ($_GET['id'] ?? 0));
if (!$job) { flash('error', 'Job not found.'); redirect('admin.php?view=jobs'); }
$d = job_payload($job);
$stmt = db()->prepare('SELECT status, note, created_at FROM job_status_logs WHERE job_id = ? ORDER BY created_at, id');
$stmt->execute([$job['id']]);
$log = $stmt->fetchAll();
$stmt = db()->prepare('SELECT o.status, o.distance_km, o.offered_at, o.responded_at, p.display_name, p.id AS pid
                       FROM job_offers o JOIN providers p ON p.id = o.provider_id WHERE o.job_id = ? ORDER BY o.offered_at, o.distance_km');
$stmt->execute([$job['id']]);
$offers = $stmt->fetchAll();
$stmt = db()->prepare('SELECT stars, comment, created_at FROM ratings WHERE job_id = ?');
$stmt->execute([$job['id']]);
$rating = $stmt->fetch();
$stmt = db()->prepare('SELECT u.id, u.created_at FROM users u WHERE u.id = ?');
$stmt->execute([$job['customer_id']]);

admin_start('job', $d['service'] . ' · ' . $d['customer']['name'],
    '<a href="' . admin_url(['view' => 'jobs']) . '" class="font-semibold text-brand-600">← All jobs</a> · <span class="font-mono">' . e($d['code']) . '</span>', $user,
    status_chip($job['status']));
$times = [['Requested', $job['requested_at']], ['Accepted', $job['accepted_at']], ['Arrived', $job['arrived_at']], ['Repair started', $job['started_at']], ['Completed', $job['completed_at']]];
?>
<div class="grid gap-5 lg:grid-cols-3">
  <div class="space-y-5 lg:col-span-2">
    <section class="<?= CARD ?> grid gap-5 sm:grid-cols-2">
      <div>
        <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Customer</p>
        <p class="mt-1 font-semibold"><?= e($d['customer']['name']) ?></p>
        <p class="text-sm"><a class="text-brand-600" href="tel:<?= e($d['customer']['phone']) ?>"><?= e($d['customer']['phone']) ?></a> ·
          <a class="text-brand-600" href="<?= admin_url(['view' => 'jobs', 'customer' => $job['customer_id'], 'days' => 'all']) ?>">their jobs</a></p>
      </div>
      <div>
        <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Vulcator</p>
        <?php if ($d['provider']): ?>
          <p class="mt-1 font-semibold"><?= e($d['provider']['name']) ?></p>
          <p class="text-sm"><a class="text-brand-600" href="tel:<?= e($d['provider']['phone']) ?>"><?= e($d['provider']['phone']) ?></a> ·
            <a class="text-brand-600" href="<?= admin_url(['view' => 'jobs', 'provider' => $job['provider_id'], 'days' => 'all']) ?>">their jobs</a></p>
          <?php if ($d['assignee']): ?><p class="text-xs text-gray-500">Done by employee <b><?= e($d['assignee']['name']) ?></b> (<?= e($d['assignee']['status'] ?? '') ?>)</p><?php endif; ?>
        <?php else: ?><p class="mt-1 text-sm text-gray-400">Not assigned</p><?php endif; ?>
      </div>
      <div>
        <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Where</p>
        <p class="mt-1 text-sm"><b><?= e($d['municipality'] ?: '–') ?></b><?= $d['address'] ? ' · ' . e($d['address']) : '' ?></p>
        <a class="text-sm text-brand-600" target="_blank" rel="noopener" href="https://www.openstreetmap.org/?mlat=<?= $d['lat'] ?>&mlon=<?= $d['lng'] ?>#map=17/<?= $d['lat'] ?>/<?= $d['lng'] ?>">Open pin ↗</a>
      </div>
      <div>
        <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Request</p>
        <p class="mt-1 text-sm"><?= e($d['problem_label']) ?> · <?= e($d['vehicle']) ?> · <?= $d['mode'] === 'walk_in' ? 'Shop visit' : 'Roadside' ?></p>
        <?php if ($d['customer']['type']): ?><p class="text-xs text-gray-500"><?= e($d['customer']['type']) ?></p><?php endif; ?>
        <?php if ($d['directions']): ?><p class="text-sm text-gray-500">Directions: <?= e($d['directions']) ?></p><?php endif; ?>
        <?php if ($d['notes']): ?><p class="text-sm text-gray-500">“<?= e($d['notes']) ?>”</p><?php endif; ?>
      </div>
    </section>

    <section class="<?= CARD ?>">
      <h2 class="mb-3 font-bold">Timeline</h2>
      <ol class="space-y-3">
        <?php foreach ($log as $l): ?>
          <li class="flex gap-3 text-sm">
            <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full <?= $l['status'] === 'cancelled' ? 'bg-gray-400' : ($l['status'] === 'paid' ? 'bg-ok-500' : 'bg-brand-500') ?>"></span>
            <div class="flex-1"><p class="font-medium"><?= e($l['note'] ?: $l['status']) ?></p></div>
            <time class="shrink-0 text-xs text-gray-500"><?= e(date('M j, g:i:s A', strtotime($l['created_at']))) ?></time>
          </li>
        <?php endforeach; ?>
        <?php if (!$log): ?><li class="text-sm text-gray-400">No events recorded.</li><?php endif; ?>
      </ol>
    </section>

    <?php $chat = job_messages((int) $job['id'], 0); if ($chat): ?>
      <section class="<?= CARD ?>">
        <h2 class="mb-3 font-bold">Chat (<?= count($chat) ?>)</h2>
        <ul class="max-h-72 space-y-2 overflow-y-auto text-sm">
          <?php foreach ($chat as $m): ?>
            <li class="flex <?= $m['from_customer'] ? 'justify-start' : 'justify-end' ?>"><span class="max-w-[80%] rounded-2xl px-3 py-2 <?= $m['from_customer'] ? 'bg-gray-100' : 'bg-brand-50' ?>">
              <?= e($m['body']) ?><span class="mt-0.5 block text-[10px] text-gray-400"><?= e($m['name']) ?> · <?= e($m['time']) ?></span></span></li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>

    <section class="<?= CARD ?>">
      <h2 class="mb-1 font-bold">Who got the request</h2>
      <p class="mb-3 text-xs text-gray-400">Nearest free vulcators within <?= e((string) search_radius_for($job['municipality'])) ?> km of the customer</p>
      <?php if (!$offers): ?><p class="text-sm text-gray-400">No vulcator was free nearby.</p><?php endif; ?>
      <ul class="divide-y divide-gray-100 text-sm">
        <?php foreach ($offers as $o): ?>
          <li class="flex items-center justify-between py-2">
            <span><?= e($o['display_name']) ?> <span class="text-gray-400">· <?= e((string) $o['distance_km']) ?> km</span></span>
            <span class="font-semibold <?= ['accepted' => 'text-green-700', 'declined' => 'text-red-600', 'expired' => 'text-gray-400', 'pending' => 'text-amber-600'][$o['status']] ?>">
              <?= ucfirst($o['status']) ?><?= $o['responded_at'] ? ' · ' . e(date('g:i A', strtotime($o['responded_at']))) : '' ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  </div>

  <div class="space-y-5">
    <section class="rounded-2xl bg-ink-900 p-5 text-white">
      <p class="text-xs font-bold uppercase tracking-wide text-white/50">Money</p>
      <?php foreach ([['Service fee', $d['service_fee']], ['Convenience fee', $d['convenience_fee']], ['Customer paid', $d['total']], ['Platform fee', $d['platform_fee']], ['Vulcator earned', $d['earning']]] as $i => [$l, $v]): ?>
        <div class="flex justify-between py-1.5 text-sm <?= $i === 2 ? 'border-t border-white/15 pt-2.5 font-bold' : '' ?>"><span class="text-white/70"><?= $l ?></span><span><?= e(peso($v)) ?></span></div>
      <?php endforeach; ?>
      <p class="mt-3 rounded-lg bg-white/10 px-3 py-2 text-sm">
        <?php if ($d['payment']): ?><?= $d['payment']['method'] === 'gcash' ? 'GCash' : ucfirst($d['payment']['method']) ?> · <?= e(ucfirst($d['payment']['status'])) ?>
          <?= $d['payment']['reference'] ? '<br><span class="font-mono text-xs">Ref ' . e($d['payment']['reference']) . '</span>' : '' ?>
        <?php else: ?>No payment yet<?php endif; ?></p>
    </section>
    <section class="<?= CARD ?>">
      <h2 class="mb-2 font-bold">Times</h2>
      <?php foreach ($times as [$l, $t]): ?>
        <div class="flex justify-between py-1 text-sm"><span class="text-gray-500"><?= $l ?></span><span><?= $t ? e(date('M j, g:i A', strtotime($t))) : '–' ?></span></div>
      <?php endforeach; ?>
      <?php if ($job['accepted_at']): ?><p class="mt-2 text-xs text-gray-500">Accepted after <?= minutes_text((float) (strtotime($job['accepted_at']) - strtotime($job['requested_at']))) ?>
        <?= $job['arrived_at'] ? ' · arrived after ' . minutes_text((float) (strtotime($job['arrived_at']) - strtotime($job['requested_at']))) : '' ?></p><?php endif; ?>
      <?php if ($job['cancel_reason']): ?><p class="mt-2 text-sm text-gray-600">Cancelled: <?= e($job['cancel_reason']) ?></p><?php endif; ?>
    </section>
    <section class="<?= CARD ?>">
      <h2 class="mb-2 font-bold">Rating</h2>
      <?php if ($rating): ?>
        <p class="text-2xl text-amber-400"><?= str_repeat('★', (int) $rating['stars']) ?><span class="text-gray-200"><?= str_repeat('★', 5 - (int) $rating['stars']) ?></span></p>
        <?php if ($rating['comment']): ?><p class="mt-1 text-sm text-gray-600">“<?= e($rating['comment']) ?>”</p><?php endif; ?>
      <?php else: ?><p class="text-sm text-gray-400">Not rated.</p><?php endif; ?>
    </section>
  </div>
</div>
<?php admin_end();
