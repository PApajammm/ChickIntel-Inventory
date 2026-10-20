<?php
declare(strict_types=1);

$config = [
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'chickintel_inventory',
        'username' => 'root',
        'password' => '',
    ],
    'allowed_origins' => [
        'http://127.0.0.1:4200',
        'http://localhost:4200',
    ],
    'session_name' => 'chickintel_session',
];

$localConfigPath = __DIR__ . '/config.local.php';
if (is_file($localConfigPath)) {
    $localConfig = require $localConfigPath;
    if (is_array($localConfig)) {
        $config = array_replace_recursive($config, $localConfig);
    }
}

return $config;
