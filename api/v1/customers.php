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
 * Customer master. Saves the Add Customer modal, including category and business name.
 * Extra columns come from database/migrations/2026_09_28_customer_profile.sql.
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

function orgType($value): string
{
    $text = trim((string) $value);
    foreach (['Hospital', 'Clinic', 'Others'] as $allowed) {
        if (strcasecmp($text, $allowed) === 0) {
            return $allowed;
        }
    }
    return '';
}

function saleType($value): string
{
    return strcasecmp(trim((string) $value), 'wholesale') === 0 ? 'wholesale' : 'retail';
}

function shapeCustomer(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'name' => (string) ($row['name'] ?? ''),
        'business_name' => (string) ($row['business_name'] ?? ''),
        'businessName' => (string) ($row['business_name'] ?? ''),
        'type' => (string) ($row['type'] ?? 'retail'),
        'org_type' => (string) ($row['org_type'] ?? ''),
        'orgType' => (string) ($row['org_type'] ?? ''),
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
    $extended = 'SELECT id, name, business_name, type, org_type, phone, gstin, dl_no, address, created_at
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
    $org = orgType($input['org_type'] ?? $input['orgType'] ?? $input['category'] ?? '');
    $type = saleType($input['type'] ?? 'retail');
    $phone = clip((string) ($input['phone'] ?? ''), 20);
    $gstin = clip((string) ($input['gstin'] ?? ''), 20);
    $dl = clip((string) ($input['dl_no'] ?? $input['dlNo'] ?? ''), 50);
    $address = clip((string) ($input['address'] ?? ''), 255);
    if ($name === '') {
        Json::error('Customer name is required.', 422);
    }
    try {
        try {
            queryRows('INSERT INTO customers (name, business_name, type, org_type, phone, gstin, dl_no, address)
                VALUES (' . sqlStr($name) . ', ' . sqlNull($business) . ', ' . sqlStr($type) . ', '
                . sqlNull($org) . ', ' . sqlNull($phone) . ', ' . sqlNull($gstin) . ', '
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
        'org_type' => $org,
        'address' => $address,
        'phone' => $phone,
        'type' => $type,
    ]);
}

Json::error('Method not allowed.', 405);
