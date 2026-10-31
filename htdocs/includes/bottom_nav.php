<?php
// Bottom navigation from the mockups. Set $activeTab before including.
$activeTab = $activeTab ?? 'home';
$homeLink = home_for(current_user());
$tabs = [
    'home'    => ['Home',    $homeLink,    'M3 10.5 12 3l9 7.5V21a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z'],
    'map'     => ['Map',     $homeLink,    'M9 4 3 6v14l6-2 6 2 6-2V4l-6 2-6-2zm0 0v14m6-12v14'],
    'history' => ['History', $homeLink,    'M12 8v4l3 2m6-2a9 9 0 1 1-3-6.7M21 4v5h-5'],
    'profile' => ['Profile', $homeLink,    'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm-7 9a7 7 0 0 1 14 0'],
];
?>
<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white pb-[env(safe-area-inset-bottom)] md:hidden">
  <ul class="mx-auto flex max-w-md justify-around">
    <?php foreach ($tabs as $key => [$label, $href, $path]): ?>
      <li>
        <a href="<?= url($href) ?>" class="flex flex-col items-center gap-0.5 px-4 py-2 text-[11px] font-medium
                  <?= $key === $activeTab ? 'text-ink-900' : 'text-gray-400' ?>">
          <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round"><path d="<?= $path ?>"/></svg>
          <?= e($label) ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
</nav>
