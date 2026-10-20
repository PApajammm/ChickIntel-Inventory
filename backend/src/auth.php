<?php
declare(strict_types=1);

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
    // Constant-time comparison prevents response timing from revealing token prefixes.
    if (!is_string($expected) || $expected === '' || !is_string($provided) || !hash_equals($expected, $provided)) {
        throw new ApiException('Security token expired. Refresh the page and try again.', 403);
    }
}
