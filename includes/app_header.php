<?php
// White top bar with greeting + avatar, used on logged-in pages.
// Set $greeting (HTML allowed) and $subline before including. $statusDot: 'online' | 'offline' | null
require_once __DIR__ . '/nav.php';
$u = current_user();
$activeTab = $activeTab ?? 'home';
?>
<header class="hero-curve sticky top-0 z-30 bg-white px-5 pb-4 pt-5 shadow-sm md:static md:rounded-none md:px-8">
  <div class="mx-auto flex max-w-6xl items-center justify-between gap-4">
    <div class="min-w-0">
      <h1 class="truncate text-xl font-bold"><?= $greeting ?></h1>
      <p class="truncate text-sm font-medium text-brand-500"><?= e($subline ?? '') ?></p>
    </div>
    <nav class="hidden items-center gap-1 md:flex" aria-label="Main">
      <?php foreach (nav_items($u) as $key => [$label, $href]): ?>
        <a href="<?= url($href) ?>" <?= $key === $activeTab ? 'aria-current="page"' : '' ?>
           class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold transition
                  <?= $key === $activeTab ? 'bg-brand-50 text-brand-600' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-800' ?>">
          <?= nav_icon($key, 'h-4 w-4') ?><?= e($label) ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="flex items-center gap-2">
      <form method="post" action="<?= url('logout.php') ?>" class="hidden md:block">
        <?= csrf_field() ?>
        <button class="whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium text-gray-500 hover:bg-gray-100">Log out</button>
      </form>
      <a href="<?= url('profile.php') ?>" class="relative" aria-label="Your profile">
        <span class="grid h-12 w-12 place-items-center rounded-full bg-brand-100 text-base font-bold text-brand-700 ring-2 ring-brand-500">
          <?= e(initials($u['full_name'])) ?>
        </span>
        <?php if (!empty($statusDot)): ?>
          <span class="absolute -bottom-1 left-1/2 -translate-x-1/2 rounded-full px-1.5 text-[9px] font-bold uppercase text-white
                       <?= $statusDot === 'online' ? 'bg-ok-500' : 'bg-gray-400' ?>"><?= $statusDot ?></span>
        <?php endif; ?>
      </a>
    </div>
  </div>
</header>
