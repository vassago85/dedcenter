<?php

return [

    'install-button' => false,

    'manifest' => [
        'name' => 'DeadCenter Scoring',
        'short_name' => 'DeadCenter',
        'background_color' => '#08142b',
        'display' => 'standalone',
        'description' => 'Offline-first shooting match scoring',
        'theme_color' => '#08142b',
        'orientation' => 'portrait',
        'start_url' => '/score',
        'scope' => '/',
        'icons' => [
            [
                'src' => '/icons/icon-192.png',
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src' => '/icons/icon-512.png',
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src' => '/icons/icon-maskable-512.png',
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'maskable',
            ],
        ],
    ],

    'debug' => true,

    'livewire-app' => false,
];
