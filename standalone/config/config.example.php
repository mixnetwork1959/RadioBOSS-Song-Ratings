<?php

declare(strict_types=1);

// This file documents the generated configuration structure.
// Do not enter real credentials here. Run /setup/ in a browser instead.
return [
    'version' => '1.0.1',
    'app_url' => 'https://radio.example/song-ratings',
    'secret' => 'generated-by-setup',
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'existing_database',
        'user' => 'database_user',
        'password' => 'database_password',
        'table_prefix' => 'rbsr_',
        'table_name' => 'rbsr_song_votes',
    ],
    'admin' => [
        'username' => 'admin',
        'password_hash' => 'generated-by-setup',
    ],
    'stations' => [],
];
