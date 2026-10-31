<?php
// Copy this file to config.php and fill in your own values.
// config.php is ignored by git so passwords never get pushed.

return [
    // Local: use the SAME host, port, user and password as your
    // MySQL Workbench connection (Workbench -> Database -> Manage Connections).
    // MySQL Server installed on Windows usually has a root password you chose
    // during install. XAMPP's own MySQL uses root with an empty password.
    // InfinityFree: copy these from Control Panel -> MySQL Databases.
    'db' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'name'     => 'vulcatic',
        'user'     => 'root',
        'password' => '',
    ],

    'app' => [
        'name'     => 'Vulcatic',
        // Leave as 'auto' - the app works out its own folder.
        // Only set it by hand if links break, e.g. '/vulcatic/htdocs'.
        'base_url' => 'auto',
        'debug'    => true,   // set to false on InfinityFree
    ],
];
