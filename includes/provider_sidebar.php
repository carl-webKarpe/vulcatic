<?php
// Vulcator sidebar (desktop). Phones keep the bottom tabs.
// Shows the online switch, navigation and the vulcator's rating.

function provider_sidebar(array $user, string $activeTab): string
{
    $p = provider_for_user($user['id']);
    $jobId = active_job_id_for_provider($p['id']);
    $online = (bool) $p['is_online'];
    $verified = $p['verification_status'] === 'verified';
    $here = basename($_SERVER['SCRIPT_NAME']);
    $items = [
        'Work' => array_filter([
            'home'  => ['Dashboard', 'dashboard.php', NAV_ICONS['home']],
            'job'   => $jobId ? ['Current job', 'provider_job.php?id=' . $jobId, 'M13 10V3L4 14h7v7l9-11h-7z'] : null,
            'shops' => ['Nearby shops', 'shops.php', NAV_ICONS['shops']],
        ]),
        'Money' => ['history' => ['Earnings & ratings', 'history.php', 'M12 8c-1.7 0-3 .9-3 2s1.3 2 3 2 3 .9 3 2-1.3 2-3 2m0-8c1.1 0 2.1.4 2.6 1M12 8V7m0 1v8m0 0v1m0-1c-1.1 0-2.1-.4-2.6-1M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z']],
        'Account' => ['profile' => ['My shop & profile', 'profile.php', NAV_ICONS['profile']]],
    ];
    ob_start(); ?>
    <aside class="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col bg-ink-900 text-white lg:flex" aria-label="Vulcator menu">
      <a href="<?= url('dashboard.php') ?>" class="group flex shrink-0 items-center gap-3 px-5 pb-3 pt-4">
        <?= logo_full('h-12 origin-bottom transition group-hover:-rotate-6') ?>
        <span><span class="block text-lg font-bold leading-tight">Vulcatic</span><span class="text-xs font-medium text-white/50">Vulcator · <?= e($p['municipality'] ?: 'Siargao') ?></span></span>
      </a>

      <!-- Availability: always one click away -->
      <form method="post" action="<?= url('availability.php') ?>" class="mx-3 mb-4 shrink-0 rounded-2xl p-3 <?= $online ? 'bg-ok-500/15 ring-1 ring-ok-500/40' : 'bg-white/5 ring-1 ring-white/10' ?>">
        <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($here) ?>">
        <input type="hidden" name="is_online" value="<?= $online ? '0' : '1' ?>">
        <div class="flex items-center gap-3">
          <span class="relative flex h-3 w-3 shrink-0">
            <?php if ($online): ?><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-ok-500 opacity-60"></span><?php endif; ?>
            <span class="relative inline-flex h-3 w-3 rounded-full <?= $online ? 'bg-ok-500' : 'bg-gray-500' ?>"></span>
          </span>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-bold"><?= $online ? 'You\'re online' : 'You\'re offline' ?></p>
            <p class="truncate text-xs text-white/60"><?= !$verified ? 'Waiting for approval' : ($online ? 'Getting job requests' : 'Not getting requests') ?></p>
          </div>
          <button type="submit" role="switch" aria-checked="<?= $online ? 'true' : 'false' ?>" <?= $verified ? '' : 'disabled' ?>
                  aria-label="<?= $online ? 'Go offline' : 'Go online' ?>"
                  class="relative h-7 w-12 shrink-0 rounded-full transition disabled:opacity-40 <?= $online ? 'bg-ok-500' : 'bg-white/20' ?>">
            <span class="absolute top-1 h-5 w-5 rounded-full bg-white shadow transition-all <?= $online ? 'left-6' : 'left-1' ?>"></span>
          </button>
        </div>
      </form>

      <nav class="admin-nav flex-1 space-y-3 overflow-y-auto px-3 pb-3">
        <?php foreach ($items as $section => $links): ?>
          <div>
            <p class="px-3 pb-1 text-[11px] font-bold uppercase tracking-widest text-white/35"><?= e($section) ?></p>
            <ul class="space-y-0.5">
              <?php foreach ($links as $key => [$label, $href, $icon]): $active = $key === $activeTab; ?>
                <li><a href="<?= url($href) ?>" <?= $active ? 'aria-current="page"' : '' ?>
                       class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition <?= $active ? 'bg-white text-ink-900 shadow' : 'text-white/75 hover:bg-white/10 hover:text-white' ?>">
                  <svg viewBox="0 0 24 24" class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="<?= $icon ?>"/></svg>
                  <span class="flex-1"><?= e($label) ?></span>
                  <?php if ($key === 'job'): ?><span class="relative flex h-2.5 w-2.5"><span class="absolute h-full w-full animate-ping rounded-full bg-brand-500"></span><span class="relative h-2.5 w-2.5 rounded-full bg-brand-500"></span></span><?php endif; ?>
                </a></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endforeach; ?>
      </nav>

      <div class="shrink-0 border-t border-white/10 p-3">
        <a href="<?= url('profile.php') ?>" class="flex items-center gap-3 rounded-xl px-2 py-2 hover:bg-white/5">
          <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-brand-500 text-sm font-bold"><?= e(initials($user['full_name'])) ?></span>
          <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold"><?= e($p['display_name']) ?></p>
            <p class="truncate text-xs text-white/60"><?= $p['rating_count'] ? '<span class="text-amber-400">★</span> ' . number_format((float) $p['rating_avg'], 1) . ' · ' . (int) $p['rating_count'] . ' ratings' : 'No ratings yet' ?></p></div>
        </a>
        <form method="post" action="<?= url('logout.php') ?>" class="mt-1"><?= csrf_field() ?>
          <button class="w-full rounded-lg py-2 text-xs font-semibold text-white/70 ring-1 ring-white/15 hover:bg-white/10">Log out</button></form>
      </div>
    </aside>
    <?php return ob_get_clean();
}
