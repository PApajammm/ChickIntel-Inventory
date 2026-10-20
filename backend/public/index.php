<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/src/http.php';
require_once dirname(__DIR__) . '/src/database.php';
require_once dirname(__DIR__) . '/src/request-validation.php';
require_once dirname(__DIR__) . '/src/auth.php';
require_once dirname(__DIR__) . '/src/inventory-data.php';
require_once dirname(__DIR__) . '/src/router.php';

$config = require dirname(__DIR__) . '/config.php';
configureHttp($_SERVER['HTTP_ORIGIN'] ?? '', $config['allowed_origins'] ?? []);

session_name((string) ($config['session_name'] ?? 'chickintel_session'));
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_start();

try {
    $pdo = connectDatabase($config['database']);
    dispatch($pdo);
} catch (ApiException $exception) {
    respond(['error' => ['message' => $exception->getMessage()]], $exception->status);
} catch (Throwable $exception) {
    error_log((string) $exception);
    respond(['error' => ['message' => 'The server could not complete this request.']], 500);
}
