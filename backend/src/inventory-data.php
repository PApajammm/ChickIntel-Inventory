<?php
declare(strict_types=1);

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
    // Keep item reads scoped to the owner, even when a caller knows another item's ID.
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
