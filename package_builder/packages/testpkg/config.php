<?php

return [
    'name' => 'TestPkg',
    'name_lower' => 'testpkg',
    'name_short' => 'tp',
    'version' => '1.1.0',
    'release' => 'beta',
    'php_version' => '8.1',

    'paths' => [
        'core' => 'core/components/testpkg/',
        'assets' => 'assets/components/testpkg/',
    ],

    'elements' => [
        'category' => 'TestPkg',
        'snippets' => 'elements/snippets.php',
        'chunks' => 'elements/chunks.php',
        'plugins' => 'elements/plugins.php',
        'settings' => 'elements/settings.php',
        'events' => 'elements/events.php',
    ],

    'static' => [
        'chunks' => false,
        'snippets' => false,
        'plugins' => false,
    ],

    'build' => [
        'download' => false,
        'install' => false,
        'update' => [
            'chunks' => true,
            'snippets' => true,
            'plugins' => true,
            'settings' => false,
            'events' => true,
        ],
    ],
];
