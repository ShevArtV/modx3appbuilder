<?php

return [
    'TestPkg' => [
        'description' => 'Test plugin for TestPkg',
        'content' => 'file:elements/plugins/switch.php',
        'events' => [
            'OnLoadWebDocument',
        ],
    ],
];
