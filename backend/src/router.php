<?php
declare(strict_types=1);

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
        // The item and its history row must succeed or fail together.
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
