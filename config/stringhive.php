<?php

declare(strict_types=1);

return [
    'base_url' => env('STRINGHIVE_URL', 'https://www.stringhive.com'),
    'token' => env('STRINGHIVE_TOKEN'),
    'hive' => env('STRINGHIVE_HIVE'),
    'timeout' => 30,

    /*
    |--------------------------------------------------------------------------
    | Excluded files
    |--------------------------------------------------------------------------
    |
    | Glob patterns for files that should be skipped during push and pull.
    | Patterns are matched against the filename (e.g. 'auth.php') and also
    | against the basename of any path component (e.g. 'auth.php' matches
    | 'fr/auth.php' during an all-locale pull).
    |
    | Examples:
    |   'auth.php'       – exclude a single PHP file from every locale
    |   'passwords.php'  – exclude Laravel's password-reset strings
    |   '*.json'         – exclude all JSON locale files
    |
    */
    'exclude' => [
        // 'auth.php',
        // 'passwords.php',
    ],
];
