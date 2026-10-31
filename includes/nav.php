<?php
// Navigation tabs for each role, shared by the phone bottom bar and the desktop header.

const NAV_ICONS = [
    'home'    => 'M3 10.5 12 3l9 7.5V21a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z',
    'shops'   => 'M9 4 3 6v14l6-2 6 2 6-2V4l-6 2-6-2zm0 0v14m6-12v14',
    'history' => 'M12 8v4l3 2m6-2a9 9 0 1 1-3-6.7M21 4v5h-5',
    'profile' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm-7 9a7 7 0 0 1 14 0',
    'admin'   => 'M3 3v18h18M7 15l4-4 3 3 5-6',
];

// [key => [label, href]]
function nav_items(array $user): array
{
    return match ($user['role']) {
        'provider' => ['home' => ['Home', 'dashboard.php'], 'shops' => ['Shops', 'shops.php'],
                       'history' => ['Earnings', 'history.php'], 'profile' => ['Profile', 'profile.php']],
        'admin'    => ['admin' => ['Admin', 'admin.php'], 'profile' => ['Profile', 'profile.php']],
        default    => ['home' => ['Home', 'home.php'], 'shops' => ['Shops', 'shops.php'],
                       'history' => ['History', 'history.php'], 'profile' => ['Profile', 'profile.php']],
    };
}

function nav_icon(string $key, string $class = 'h-6 w-6'): string
{
    return '<svg viewBox="0 0 24 24" class="' . $class . '" fill="none" stroke="currentColor" stroke-width="2"'
         . ' stroke-linecap="round" stroke-linejoin="round"><path d="' . NAV_ICONS[$key] . '"/></svg>';
}
