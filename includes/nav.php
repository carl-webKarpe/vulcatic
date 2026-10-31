<?php
// Navigation tabs for each role, shared by the phone bottom bar and the desktop header.

const NAV_ICONS = [
    'home'    => 'M3 10.5 12 3l9 7.5V21a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z',
    'shops'   => 'M9 4 3 6v14l6-2 6 2 6-2V4l-6 2-6-2zm0 0v14m6-12v14',
    'history' => 'M12 8v4l3 2m6-2a9 9 0 1 1-3-6.7M21 4v5h-5',
    'profile' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm-7 9a7 7 0 0 1 14 0',
    'admin'   => 'M3 3v18h18M7 15l4-4 3 3 5-6',
    'bookings'  => 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2m-6 9l2 2 4-4',
    'customers' => 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2m22 0v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0z',
    'team'      => 'M18 18.7A9 9 0 0 0 21 12M6 18.7A9 9 0 0 1 3 12m9-2a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm-6 10a6 6 0 0 1 12 0',
    'job'       => 'M13 10V3L4 14h7v7l9-11h-7z',
];

// [key => [label, href]]
function nav_items(array $user): array
{
    return match ($user['role']) {
        'provider' => ['home' => ['Home', 'dashboard.php'], 'bookings' => ['Bookings', 'bookings.php'],
                       'history' => ['Earnings', 'history.php'], 'profile' => ['Profile', 'profile.php']],
        'employee' => ['home' => ['My jobs', 'employee.php'], 'shops' => ['Shops', 'shops.php'], 'profile' => ['Profile', 'profile.php']],
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
