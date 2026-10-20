<?php
declare(strict_types=1);

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
