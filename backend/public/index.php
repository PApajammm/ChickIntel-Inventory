<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

$config = require dirname(__DIR__) . '/config.php';
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = $config['allowed_origins'] ?? [];

if ($origin !== '' && !in_array($origin, $allowedOrigins, true)) {
    respond(['error' => ['message' => 'Origin is not allowed.']], 403);
}

if ($origin !== '') {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}

header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('Access-Control-Max-Age: 600');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

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

function connectDatabase(array $database): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $database['host'],
        $database['port'],
        $database['name'],
    );
    return new PDO($dsn, $database['username'], $database['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function dispatch(PDO $pdo): never
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $route = trim((string) ($_GET['route'] ?? ''), '/');

    if ($method === 'GET' && $route === 'health') {
        respond(['data' => ['status' => 'ok']]);
    }

    if ($method === 'GET' && $route === 'auth/csrf') {
        respond(['data' => ['csrfToken' => csrfToken()]]);
    }

    if ($method === 'POST' && $route === 'auth/register') {
        requireCsrf();
        registerUser($pdo, requestBody());
    }

    if ($method === 'POST' && $route === 'auth/login') {
        requireCsrf();
        loginUser($pdo, requestBody());
    }

    if ($method === 'POST' && $route === 'auth/logout') {
        requireCsrf();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $cookie = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $cookie['path'],
                'domain' => $cookie['domain'],
                'secure' => $cookie['secure'],
                'httponly' => $cookie['httponly'],
                'samesite' => 'Lax',
            ]);
        }
        session_destroy();
        respond(['data' => ['success' => true]]);
    }

    if ($method === 'GET' && $route === 'auth/me') {
        $userId = authenticatedUserId();
        $statement = $pdo->prepare('SELECT id, full_name, email FROM users WHERE id = ?');
        $statement->execute([$userId]);
        $user = $statement->fetch();
        if (!$user) {
            unset($_SESSION['user_id']);
            throw new ApiException('Your session is no longer valid. Sign in again.', 401);
        }
        respond(['data' => ['user' => formatUser($user), 'csrfToken' => csrfToken()]]);
    }

    if ($route === 'inventory' && $method === 'GET') {
        $userId = authenticatedUserId();
        $statement = $pdo->prepare(
            'SELECT id, name, category, quantity, unit, unit_price, expiration_date, notes, reference_quantity
             FROM inventory_items WHERE user_id = ? ORDER BY created_at DESC, id DESC',
        );
        $statement->execute([$userId]);
        respond(['data' => array_map('formatItem', $statement->fetchAll())]);
    }

    if ($route === 'inventory' && $method === 'POST') {
        $userId = authenticatedUserId();
        $input = validateItem(requestBody());
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare(
                'INSERT INTO inventory_items (user_id, name, category, quantity, unit, unit_price, expiration_date, notes, reference_quantity)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            );
            $statement->execute([
                $userId,
                $input['name'],
                $input['category'],
                $input['quantity'],
                $input['unit'],
                $input['unitPrice'],
                $input['expirationDate'],
                $input['notes'],
                max((float) $input['quantity'], 1),
            ]);
            $itemId = (int) $pdo->lastInsertId();
            addHistory($pdo, $userId, $itemId, $input['name'], 'Item added', $input['quantity'], $input['notes']);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
        respond(['data' => findItem($pdo, $userId, $itemId)], 201);
    }

    if (preg_match('#^inventory/(\d+)$#', $route, $matches) === 1 && $method === 'PATCH') {
        $userId = authenticatedUserId();
        $itemId = (int) $matches[1];
        $input = validateItem(requestBody());
        $pdo->beginTransaction();
        try {
            findItem($pdo, $userId, $itemId);
            $statement = $pdo->prepare(
                'UPDATE inventory_items
                 SET name = ?, category = ?, quantity = ?, unit = ?, unit_price = ?, expiration_date = ?, notes = ?
                 WHERE id = ? AND user_id = ?',
            );
            $statement->execute([
                $input['name'],
                $input['category'],
                $input['quantity'],
                $input['unit'],
                $input['unitPrice'],
                $input['expirationDate'],
                $input['notes'],
                $itemId,
                $userId,
            ]);
            addHistory($pdo, $userId, $itemId, $input['name'], 'Item updated', null, $input['notes']);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
        respond(['data' => findItem($pdo, $userId, $itemId)]);
    }

    if (preg_match('#^inventory/(\d+)/restock$#', $route, $matches) === 1 && $method === 'POST') {
        $userId = authenticatedUserId();
        $itemId = (int) $matches[1];
        $input = requestBody();
        $quantity = validateNumber($input['quantity'] ?? null, 'Restock quantity', 0.001);
        $notes = optionalText($input['notes'] ?? null, 'Notes', 1000);
        $pdo->beginTransaction();
        try {
            $item = findItem($pdo, $userId, $itemId);
            $statement = $pdo->prepare('UPDATE inventory_items SET quantity = quantity + ? WHERE id = ? AND user_id = ?');
            $statement->execute([$quantity, $itemId, $userId]);
            addHistory($pdo, $userId, $itemId, $item['name'], 'Item restocked', $quantity, $notes);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
        respond(['data' => findItem($pdo, $userId, $itemId)]);
    }

    if (preg_match('#^inventory/(\d+)$#', $route, $matches) === 1 && $method === 'DELETE') {
        $userId = authenticatedUserId();
        $itemId = (int) $matches[1];
        $pdo->beginTransaction();
        try {
            $item = findItem($pdo, $userId, $itemId);
            addHistory($pdo, $userId, null, $item['name'], 'Item deleted', -(float) $item['quantity'], null);
            $statement = $pdo->prepare('DELETE FROM inventory_items WHERE id = ? AND user_id = ?');
            $statement->execute([$itemId, $userId]);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
        respond(['data' => ['success' => true]]);
    }

    if ($method === 'GET' && $route === 'history') {
        $userId = authenticatedUserId();
        $statement = $pdo->prepare(
            'SELECT id, item_name, action, quantity_change, created_at, notes
             FROM inventory_history WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 500',
        );
        $statement->execute([$userId]);
        $entries = array_map(static fn (array $entry): array => [
            'id' => (int) $entry['id'],
            'itemName' => $entry['item_name'],
            'action' => $entry['action'],
            'quantityChange' => $entry['quantity_change'] === null ? null : (float) $entry['quantity_change'],
            'createdAt' => date(DATE_ATOM, strtotime($entry['created_at'])),
            'notes' => $entry['notes'],
        ], $statement->fetchAll());
        respond(['data' => $entries]);
    }

    throw new ApiException('Endpoint not found.', 404);
}

function registerUser(PDO $pdo, array $input): never
{
    $name = trim((string) ($input['fullName'] ?? ''));
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    $password = $input['password'] ?? null;

    if ($name === '' || strlen($name) > 100) {
        throw new ApiException('Enter a name of 1 to 100 characters.', 422);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        throw new ApiException('Enter a valid email address.', 422);
    }
    if (!is_string($password) || strlen($password) < 8 || strlen($password) > 200) {
        throw new ApiException('Password must be between 8 and 200 characters.', 422);
    }

    try {
        $statement = $pdo->prepare('INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)');
        $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            throw new ApiException('An account with this email already exists.', 409);
        }
        throw $exception;
    }

    establishSession((int) $pdo->lastInsertId());
    respond([
        'data' => [
            'user' => ['id' => (int) $_SESSION['user_id'], 'fullName' => $name, 'email' => $email],
            'csrfToken' => csrfToken(),
        ],
    ], 201);
}

function loginUser(PDO $pdo, array $input): never
{
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    $password = $input['password'] ?? null;
    if ($email === '' || !is_string($password)) {
        throw new ApiException('Enter your email address and password.', 422);
    }

    $statement = $pdo->prepare('SELECT id, full_name, email, password_hash FROM users WHERE email = ?');
    $statement->execute([$email]);
    $user = $statement->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) {
        throw new ApiException('Email or password is incorrect.', 401);
    }

    establishSession((int) $user['id']);
    respond([
        'data' => [
            'user' => formatUser($user),
            'csrfToken' => csrfToken(),
        ],
    ]);
}

function establishSession(int $userId): void
{
    if (!session_regenerate_id(true)) {
        throw new ApiException('Could not establish a secure session. Try again.', 500);
    }
    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function formatUser(array $user): array
{
    return [
        'id' => (int) $user['id'],
        'fullName' => $user['full_name'],
        'email' => $user['email'],
    ];
}

function formatItem(array $item): array
{
    return [
        'id' => (int) $item['id'],
        'name' => $item['name'],
        'category' => $item['category'],
        'quantity' => (float) $item['quantity'],
        'unit' => $item['unit'],
        'unitPrice' => $item['unit_price'] === null ? null : (float) $item['unit_price'],
        'expirationDate' => $item['expiration_date'],
        'notes' => $item['notes'],
        'referenceQuantity' => (float) $item['reference_quantity'],
    ];
}

function findItem(PDO $pdo, int $userId, int $itemId): array
{
    $statement = $pdo->prepare(
        'SELECT id, name, category, quantity, unit, unit_price, expiration_date, notes, reference_quantity
         FROM inventory_items WHERE id = ? AND user_id = ?',
    );
    $statement->execute([$itemId, $userId]);
    $item = $statement->fetch();
    if (!$item) {
        throw new ApiException('Inventory item not found.', 404);
    }
    return formatItem($item);
}

function addHistory(
    PDO $pdo,
    int $userId,
    ?int $itemId,
    string $itemName,
    string $action,
    int|float|string|null $quantityChange,
    ?string $notes,
): void {
    $statement = $pdo->prepare(
        'INSERT INTO inventory_history (user_id, item_id, item_name, action, quantity_change, notes)
         VALUES (?, ?, ?, ?, ?, ?)',
    );
    $statement->execute([$userId, $itemId, $itemName, $action, $quantityChange, $notes]);
}

function validateItem(array $input): array
{
    $name = requiredText($input['name'] ?? null, 'Item name', 120);
    $category = requiredText($input['category'] ?? null, 'Category', 80);
    $unit = requiredText($input['unit'] ?? null, 'Unit', 30);
    $quantity = validateNumber($input['quantity'] ?? null, 'Quantity', 0);
    $unitPrice = isset($input['unitPrice']) && $input['unitPrice'] !== ''
        ? validateNumber($input['unitPrice'], 'Unit price', 0)
        : null;
    $expirationDate = optionalDate($input['expirationDate'] ?? null);
    $notes = optionalText($input['notes'] ?? null, 'Notes', 1000);

    return compact('name', 'category', 'unit', 'quantity', 'unitPrice', 'expirationDate', 'notes');
}

function requiredText(mixed $value, string $field, int $maxLength): string
{
    if (!is_string($value)) {
        throw new ApiException($field . ' is required.', 422);
    }
    $value = trim($value);
    if ($value === '' || strlen($value) > $maxLength) {
        throw new ApiException($field . ' must be between 1 and ' . $maxLength . ' characters.', 422);
    }
    return $value;
}

function optionalText(mixed $value, string $field, int $maxLength): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    if (!is_string($value) || strlen($value) > $maxLength) {
        throw new ApiException($field . ' cannot exceed ' . $maxLength . ' characters.', 422);
    }
    return trim($value) === '' ? null : trim($value);
}

function validateNumber(mixed $value, string $field, float $minimum): float
{
    if (!is_numeric($value) || !is_finite((float) $value) || (float) $value < $minimum || (float) $value > 999999999) {
        throw new ApiException($field . ' must be a valid number of at least ' . $minimum . '.', 422);
    }
    return (float) $value;
}

function optionalDate(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    if (!is_string($value) || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts) !== 1
        || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
        throw new ApiException('Enter a valid expiration date.', 422);
    }
    return $value;
}

function requestBody(): array
{
    $rawBody = file_get_contents('php://input');
    try {
        $body = json_decode($rawBody === false ? '' : $rawBody, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new ApiException('Request body must be valid JSON.', 400);
    }
    if (!is_array($body)) {
        throw new ApiException('Request body must be a JSON object.', 400);
    }
    return $body;
}

function authenticatedUserId(): int
{
    $userId = $_SESSION['user_id'] ?? null;
    if (!is_int($userId) || $userId <= 0) {
        throw new ApiException('Sign in to continue.', 401);
    }
    return $userId;
}

function csrfToken(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf_token'];
}

function requireCsrf(): void
{
    $expected = $_SESSION['csrf_token'] ?? '';
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($expected) || $expected === '' || !is_string($provided) || !hash_equals($expected, $provided)) {
        throw new ApiException('Security token expired. Refresh the page and try again.', 403);
    }
}

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

final class ApiException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status)
    {
        parent::__construct($message);
    }
}
