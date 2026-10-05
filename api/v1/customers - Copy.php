<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';
require dirname(__DIR__, 2) . '/core/Audit.php';
require dirname(__DIR__, 2) . '/models/Manufacturer.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

/**
 * Customer master. Saves and edits the customer form, including business name
 * and customer type (retail, wholesale, Hospital, Clinic, Others).
 * business_name comes from database/migrations/2026_09_28_customer_profile.sql.
 */

function queryRows(string $sql): array
{
    $rows = Manufacturer::query($sql);
    return is_array($rows) ? $rows : [];
}

function sqlStr(string $value): string
{
    return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $value) . "'";
}

function sqlNull(string $value): string
{
    $value = trim($value);
    return $value === '' ? 'NULL' : sqlStr($value);
}

function clip(string $value, int $max): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max);
    }
    return substr($value, 0, $max);
}

function customerType($value): string
{
    $key = strtolower(trim((string) $value));
    $map = [
        'retail' => 'retail',
        'wholesale' => 'wholesale',
        'hospital' => 'Hospital',
        'clinic' => 'Clinic',
        'others' => 'Others',
        'other' => 'Others',
    ];
    return $map[$key] ?? 'retail';
}

function shapeCustomer(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'name' => (string) ($row['name'] ?? ''),
        'business_name' => (string) ($row['business_name'] ?? ''),
        'businessName' => (string) ($row['business_name'] ?? ''),
        'type' => (string) ($row['type'] ?? 'retail'),
        'phone' => (string) ($row['phone'] ?? ''),
        'gstin' => (string) ($row['gstin'] ?? ''),
        'dl_no' => (string) ($row['dl_no'] ?? ''),
        'dlNo' => (string) ($row['dl_no'] ?? ''),
        'address' => (string) ($row['address'] ?? ''),
        'created_at' => (string) ($row['created_at'] ?? ''),
    ];
}

function listRows(): array
{
    $extended = 'SELECT id, name, business_name, type, phone, gstin, dl_no, address, created_at
        FROM customers ORDER BY name ASC, id ASC';
    try {
        return queryRows($extended);
    } catch (Throwable $e) {
        return queryRows('SELECT id, name, type, phone, gstin, dl_no, address, created_at
            FROM customers ORDER BY name ASC, id ASC');
    }
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $rows = array_map('shapeCustomer', listRows());
    } catch (Throwable $e) {
        Json::error('Customers table is not available.', 503);
    }
    Json::ok(['data' => $rows]);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $name = clip((string) ($input['name'] ?? ''), 150);
    $business = clip((string) ($input['business_name'] ?? $input['businessName'] ?? ''), 150);
    $type = customerType($input['type'] ?? 'retail');
    $phone = clip((string) ($input['phone'] ?? ''), 20);
    $gstin = clip((string) ($input['gstin'] ?? ''), 20);
    $dl = clip((string) ($input['dl_no'] ?? $input['dlNo'] ?? ''), 50);
    $address = clip((string) ($input['address'] ?? ''), 255);
    if ($name === '') {
        Json::error('Customer name is required.', 422);
    }
    try {
        try {
            queryRows('INSERT INTO customers (name, business_name, type, phone, gstin, dl_no, address)
                VALUES (' . sqlStr($name) . ', ' . sqlNull($business) . ', ' . sqlStr($type) . ', '
                . sqlNull($phone) . ', ' . sqlNull($gstin) . ', '
                . sqlNull($dl) . ', ' . sqlNull($address) . ')');
        } catch (Throwable $e) {
            queryRows('INSERT INTO customers (name, type, phone, gstin, dl_no, address)
                VALUES (' . sqlStr($name) . ', ' . sqlStr($type) . ', ' . sqlNull($phone) . ', '
                . sqlNull($gstin) . ', ' . sqlNull($dl) . ', ' . sqlNull($address) . ')');
        }
        $found = queryRows('SELECT id FROM customers WHERE name = ' . sqlStr($name) . ' ORDER BY id DESC LIMIT 1');
        $id = (int) ($found[0]['id'] ?? 0);
        if (!$id) {
            Json::error('Could not save the customer.', 500);
        }
    } catch (Throwable $e) {
        Json::error('Could not save the customer.', 500);
    }
    if (class_exists('Audit')) {
        Audit::log('CUSTOMER_CREATE', $name);
    }
    Json::ok([
        'id' => $id,
        'name' => $name,
        'business_name' => $business,
        'address' => $address,
        'phone' => $phone,
        'type' => $type,
    ]);
}

if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int) ($input['id'] ?? $_GET['id'] ?? 0);
    $name = clip((string) ($input['name'] ?? ''), 150);
    $business = clip((string) ($input['business_name'] ?? $input['businessName'] ?? ''), 150);
    $type = customerType($input['type'] ?? 'retail');
    $phone = clip((string) ($input['phone'] ?? ''), 20);
    $gstin = clip((string) ($input['gstin'] ?? ''), 20);
    $dl = clip((string) ($input['dl_no'] ?? $input['dlNo'] ?? ''), 50);
    $address = clip((string) ($input['address'] ?? ''), 255);
    if (!$id) {
        Json::error('Customer not found.', 404);
    }
    if ($name === '') {
        Json::error('Customer name is required.', 422);
    }
    $found = queryRows('SELECT id FROM customers WHERE id = ' . $id . ' LIMIT 1');
    if (!$found) {
        Json::error('Customer not found.', 404);
    }
    try {
        try {
            queryRows('UPDATE customers SET
                name = ' . sqlStr($name) . ',
                business_name = ' . sqlNull($business) . ',
                type = ' . sqlStr($type) . ',
                phone = ' . sqlNull($phone) . ',
                gstin = ' . sqlNull($gstin) . ',
                dl_no = ' . sqlNull($dl) . ',
                address = ' . sqlNull($address) . '
                WHERE id = ' . $id);
        } catch (Throwable $e) {
            queryRows('UPDATE customers SET
                name = ' . sqlStr($name) . ',
                type = ' . sqlStr($type) . ',
                phone = ' . sqlNull($phone) . ',
                gstin = ' . sqlNull($gstin) . ',
                dl_no = ' . sqlNull($dl) . ',
                address = ' . sqlNull($address) . '
                WHERE id = ' . $id);
        }
    } catch (Throwable $e) {
        Json::error('Could not update the customer. Run database/migrations/2026_09_28_customer_profile.sql if the type could not be saved.', 500);
    }
    if (class_exists('Audit')) {
        Audit::log('CUSTOMER_UPDATE', $name);
    }
    Json::ok([
        'id' => $id,
        'name' => $name,
        'business_name' => $business,
        'address' => $address,
        'phone' => $phone,
        'gstin' => $gstin,
        'dl_no' => $dl,
        'type' => $type,
    ]);
}

Json::error('Method not allowed.', 405);
