<?php
// Copy this file to config.php and fill in your own values.
// config.php is ignored by git so passwords never get pushed.

return [
    // Local (XAMPP): host 'localhost', user 'root', empty password.
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
        // Folder the site lives in, no trailing slash.
        // XAMPP (htdocs/vulcatic): '/vulcatic'   InfinityFree (htdocs root): ''
        'base_url' => '/vulcatic',
        'debug'    => true,   // set to false on InfinityFree
    ],
];
