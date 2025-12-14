<?php
$config = [
    'env_file' => realpath(__DIR__ . '/../.env'),
    'auth' => [
        // Хэш сгенерирован через password_hash('YraF2015', PASSWORD_DEFAULT)
        'password_hash' => '$2y$12$rl51pvdszajVYYNtw3dB4OtqCbBWF/JfezTmlAn.ZetNOIBB1j/FS',
    ],
    'db' => [
        'host' => null,
        'name' => null,
        'user' => null,
        'pass' => null,
        'charset' => 'utf8mb4',
    ],
];

if (file_exists($config['env_file'])) {
    $lines = file($config['env_file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === 'DB_HOST') {
            $config['db']['host'] = $value;
        }
        if ($key === 'DB_NAME') {
            $config['db']['name'] = $value;
        }
        if ($key === 'DB_USER') {
            $config['db']['user'] = $value;
        }
        if ($key === 'DB_PASS') {
            $config['db']['pass'] = $value;
        }
        if ($key === 'APP_TIMEZONE') {
            date_default_timezone_set($value);
        }
        if ($key === 'APP_PASSWORD_HASH') {
            $config['auth']['password_hash'] = $value;
        }
    }
}

if (!date_default_timezone_get()) {
    date_default_timezone_set('UTC');
}
