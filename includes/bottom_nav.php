<?php
// Phone bottom navigation. Set $activeTab ('home', 'shops', 'history', 'profile', 'admin') first.
require_once __DIR__ . '/nav.php';
$activeTab = $activeTab ?? 'home';
if (current_user()['role'] === 'provider') echo '</div>'; // closes the sidebar wrapper from app_header.php
?>
<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white pb-[env(safe-area-inset-bottom)] md:hidden">
  <ul class="mx-auto flex max-w-md justify-around">
    <?php foreach (nav_items(current_user()) as $key => [$label, $href]): ?>
      <li>
        <a href="<?= url($href) ?>" <?= $key === $activeTab ? 'aria-current="page"' : '' ?>
           class="flex flex-col items-center gap-0.5 px-4 py-2 text-[11px] font-medium <?= $key === $activeTab ? 'text-brand-600' : 'text-gray-400' ?>">
          <?= nav_icon($key) ?>
          <?= e($label) ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
</nav>
