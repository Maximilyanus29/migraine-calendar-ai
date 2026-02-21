<?php

declare(strict_types=1);

return [
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('DB_PORT') ?: 5432),
        'name' => getenv('DB_NAME') ?: 'migraine',
        'user' => getenv('DB_USER') ?: 'migraine',
        'password' => getenv('DB_PASSWORD') ?: 'migraine',
    ],
    'timezone' => getenv('APP_TZ') ?: 'Europe/Moscow',
    'default_user' => [
        'name' => getenv('DEFAULT_USER_NAME') ?: 'Demo User',
        'email' => getenv('DEFAULT_USER_EMAIL') ?: 'demo@example.com',
        'password' => getenv('DEFAULT_USER_PASSWORD') ?: 'password',
    ],
];
