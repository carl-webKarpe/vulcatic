<?php
// Admin area: sidebar layout and small shared helpers.

const ADMIN_STATUS = [
    'searching'   => ['Pending', 'bg-amber-50 text-amber-700 ring-amber-200'],
    'matched'     => ['Pending (offered)', 'bg-amber-50 text-amber-700 ring-amber-200'],
    'accepted'    => ['Accepted', 'bg-blue-50 text-blue-700 ring-blue-200'],
    'on_the_way'  => ['En route', 'bg-blue-50 text-blue-700 ring-blue-200'],
    'arrived'     => ['Arrived', 'bg-blue-50 text-blue-700 ring-blue-200'],
    'in_progress' => ['In progress', 'bg-purple-50 text-purple-700 ring-purple-200'],
    'completed'   => ['To pay', 'bg-purple-50 text-purple-700 ring-purple-200'],
    'paid'        => ['Completed', 'bg-green-50 text-green-700 ring-green-300'],
    'cancelled'  => ['Cancelled', 'bg-gray-100 text-gray-500 ring-gray-200'],
];

function status_chip(string $status): string
{
    [$label, $cls] = ADMIN_STATUS[$status] ?? [$status, 'bg-gray-100 text-gray-600 ring-gray-200'];
    return '<span class="inline-block whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ' . $cls . '">' . e($label) . '</span>';
}

// admin_url(['view' => 'jobs', 'town' => 'Dapa'])
function admin_url(array $params = []): string
{
    $params = array_filter($params, fn ($v) => $v !== '' && $v !== null);
    return url('admin.php' . ($params ? '?' . http_build_query($params) : ''));
}

function minutes_text(?float $seconds): string
{
    return $seconds === null ? '–' : max(1, (int) round($seconds / 60)) . ' min';
}

function ago(string $datetime): string
{
    $s = time() - strtotime($datetime);
    if ($s < 60) return 'just now';
    if ($s < 3600) return floor($s / 60) . ' min ago';
    if ($s < 86400) return floor($s / 3600) . ' h ago';
    return floor($s / 86400) . ' d ago';
}

// Hidden field so actions return to the page they came from.
function back_field(): string
{
    return '<input type="hidden" name="back" value="' . e($_SERVER['QUERY_STRING'] ?? '') . '">';
}

// Numbers shown as badges in the sidebar.
function admin_counts(): array
{
    return db()->query("SELECT
        (SELECT COUNT(*) FROM providers WHERE verification_status = 'pending') AS approvals,
        (SELECT COUNT(*) FROM password_reset_requests WHERE status = 'open') AS resets,
        (SELECT COUNT(*) FROM jobs WHERE status IN ('searching','matched','accepted','on_the_way','arrived','in_progress','completed')) AS live,
        (SELECT COUNT(*) FROM providers) AS vulcators,
        (SELECT COUNT(*) FROM users WHERE role = 'customer') AS customers,
        (SELECT COUNT(*) FROM ratings WHERE stars <= 3 AND created_at > NOW() - INTERVAL 14 DAY) AS low_reviews")->fetch();
}

function admin_nav(): array
{
    // [section => [view => [label, icon path, badge key, badge style]]]
    return [
        '' => [
            'overview' => ['Overview', 'M3 13h8V3H3zm10 8h8V11h-8zM3 21h8v-6H3zm10-18v6h8V3z', null, ''],
        ],
        'Action needed' => [
            'approvals' => ['Approvals', 'M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z', 'approvals', 'bg-amber-400 text-ink-900'],
            'resets'    => ['Password resets', 'M15 7a2 2 0 0 1 2 2m4 0a6 6 0 0 1-7.7 5.7L11 17H9v2H7v2H4a1 1 0 0 1-1-1v-2.6a1 1 0 0 1 .3-.7l6-6A6 6 0 1 1 21 9z', 'resets', 'bg-blue-400 text-ink-900'],
        ],
        'Operations' => [
            'live'  => ['Live jobs', 'M13 10V3L4 14h7v7l9-11h-7z', 'live', 'bg-brand-500 text-white'],
            'jobs'  => ['All jobs', 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2m-6 9l2 2 4-4', null, ''],
            'towns' => ['Towns', 'M9 4 3 6v14l6-2 6 2 6-2V4l-6 2-6-2zm0 0v14m6-12v14', null, ''],
        ],
        'People' => [
            'vulcators' => ['Vulcators', 'M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z', 'vulcators', 'bg-white/15 text-white'],
            'customers' => ['Customers', 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2m22 0v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0z', 'customers', 'bg-white/15 text-white'],
            'reviews'   => ['Reviews', 'M12 2l3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z', 'low_reviews', 'bg-red-400 text-white'],
        ],
        'System' => [
            'settings' => ['Settings', 'M10.3 4.3c.4-1.8 3-1.8 3.4 0a1.7 1.7 0 0 0 2.6 1.1c1.5-.9 3.3.8 2.4 2.4a1.7 1.7 0 0 0 1 2.5c1.8.5 1.8 3 0 3.5a1.7 1.7 0 0 0-1 2.5c.9 1.6-.9 3.3-2.4 2.4a1.7 1.7 0 0 0-2.6 1c-.4 1.9-3 1.9-3.4 0a1.7 1.7 0 0 0-2.6-1c-1.5.9-3.3-.8-2.4-2.4a1.7 1.7 0 0 0-1-2.5c-1.8-.5-1.8-3 0-3.5a1.7 1.7 0 0 0 1-2.5c-.9-1.6.9-3.3 2.4-2.4a1.7 1.7 0 0 0 2.6-1zM15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z', null, ''],
        ],
    ];
}

function admin_icon(string $path, string $class = 'h-5 w-5'): string
{
    return '<svg viewBox="0 0 24 24" class="' . $class . ' shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . $path . '"/></svg>';
}

function admin_sidebar(string $view, array $counts, array $user): string
{
    ob_start(); ?>
    <div class="flex h-full flex-col">
      <a href="<?= admin_url() ?>" class="group flex shrink-0 items-center gap-3 px-5 pb-3 pt-4">
        <?= logo_full('h-12 origin-bottom transition group-hover:-rotate-6') ?>
        <span><span class="block text-lg font-bold leading-tight">Vulcatic</span>
              <span class="text-xs font-medium text-white/50">Admin · Siargao Island</span></span>
      </a>
      <nav class="admin-nav flex-1 space-y-3 overflow-y-auto px-3 pb-3" aria-label="Admin">
        <?php foreach (admin_nav() as $section => $items): ?>
          <div>
            <?php if ($section): ?><p class="px-3 pb-1 text-[11px] font-bold uppercase tracking-widest text-white/35"><?= e($section) ?></p><?php endif; ?>
            <ul class="space-y-0.5">
              <?php foreach ($items as $key => [$label, $icon, $badgeKey, $badgeCls]):
                  $active = $key === $view || ($view === 'job' && $key === 'jobs');
                  $n = $badgeKey ? (int) $counts[$badgeKey] : 0; ?>
                <li>
                  <a href="<?= admin_url($key === 'overview' ? [] : ['view' => $key]) ?>" <?= $active ? 'aria-current="page"' : '' ?>
                     class="flex items-center gap-3 rounded-xl px-3 py-[7px] text-sm font-medium transition
                            <?= $active ? 'bg-white text-ink-900 shadow' : 'text-white/75 hover:bg-white/10 hover:text-white' ?>">
                    <?= admin_icon($icon) ?>
                    <span class="flex-1"><?= e($label) ?></span>
                    <?php if ($n > 0): ?>
                      <span class="min-w-[1.5rem] rounded-full px-1.5 py-0.5 text-center text-[11px] font-bold <?= $active ? 'bg-ink-900 text-white' : $badgeCls ?>"><?= $n ?></span>
                    <?php endif; ?>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endforeach; ?>
      </nav>
      <div class="shrink-0 border-t border-white/10 p-3">
        <div class="flex items-center gap-3 rounded-xl px-2 py-2">
          <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-brand-500 text-sm font-bold"><?= e(initials($user['full_name'])) ?></span>
          <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold"><?= e($user['full_name']) ?></p><p class="truncate text-xs text-white/50"><?= e($user['phone']) ?></p></div>
        </div>
        <div class="mt-1 grid grid-cols-2 gap-2">
          <a href="<?= url('profile.php') ?>" class="rounded-lg py-2 text-center text-xs font-semibold text-white/80 ring-1 ring-white/15 hover:bg-white/10">My profile</a>
          <form method="post" action="<?= url('logout.php') ?>"><?= csrf_field() ?>
            <button class="w-full rounded-lg py-2 text-xs font-semibold text-white/80 ring-1 ring-white/15 hover:bg-white/10">Log out</button></form>
        </div>
      </div>
    </div>
    <?php return ob_get_clean();
}

function admin_start(string $view, string $title, string $subtitle, array $user, string $actions = ''): void
{
    global $useMap, $headExtra;
    $pageTitle = $title . ' · Admin';
    $useMap = true;
    $counts = admin_counts();
    require __DIR__ . '/../header.php';
    $sidebar = admin_sidebar($view, $counts, $user); ?>
    <div class="min-h-screen lg:pl-64">
      <!-- Desktop sidebar -->
      <aside class="fixed inset-y-0 left-0 z-40 hidden w-64 bg-ink-900 text-white lg:block"><?= $sidebar ?></aside>

      <!-- Phone / tablet: top bar + slide-in drawer -->
      <div class="sticky top-0 z-30 flex items-center justify-between gap-3 bg-ink-900 px-4 py-3 text-white lg:hidden">
        <button type="button" id="drawer-open" class="grid h-10 w-10 place-items-center rounded-xl hover:bg-white/10" aria-label="Open menu" aria-controls="drawer" aria-expanded="false">
          <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
        <p class="min-w-0 flex-1 truncate font-semibold"><?= e($title) ?></p>
        <?php $alerts = (int) $counts['approvals'] + (int) $counts['resets']; if ($alerts): ?>
          <span class="rounded-full bg-amber-400 px-2 py-0.5 text-xs font-bold text-ink-900" title="Items needing action"><?= $alerts ?></span>
        <?php endif; ?>
      </div>
      <div id="drawer-backdrop" class="fixed inset-0 z-40 hidden bg-black/50 lg:hidden"></div>
      <aside id="drawer" class="drawer fixed inset-y-0 left-0 z-50 w-72 max-w-[85vw] -translate-x-full bg-ink-900 text-white transition-transform duration-300 lg:hidden" aria-label="Admin menu">
        <?= $sidebar ?>
      </aside>

      <main class="mx-auto max-w-6xl space-y-5 px-4 py-6 md:px-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
          <div><h1 class="text-2xl font-bold"><?= e($title) ?></h1><p class="text-sm text-gray-500"><?= $subtitle ?></p></div>
          <?= $actions ?>
        </div>
    <?php
}

function admin_end(array $scripts = []): void
{
    echo '</main></div>';
    $pageScripts = array_merge(['admin.js'], $scripts);
    require __DIR__ . '/../footer.php';
}

// A row of buttons that switch a period/filter, e.g. 7 / 14 / 30 days.
function pill_group(array $options, $current, callable $href, string $label): string
{
    $h = '<div class="inline-flex max-w-full overflow-x-auto rounded-xl bg-white p-1 shadow-sm ring-1 ring-black/5" role="group" aria-label="' . e($label) . '">';
    foreach ($options as $value => $text) {
        $on = (string) $value === (string) $current;
        $h .= '<a href="' . e($href($value)) . '" class="rounded-lg px-3 py-1.5 text-sm font-semibold ' . ($on ? 'bg-brand-500 text-white' : 'text-gray-600 hover:bg-gray-100') . '">' . e($text) . '</a>';
    }
    return $h . '</div>';
}

const CARD = 'rounded-2xl bg-white p-5 shadow-sm ring-1 ring-black/5';
