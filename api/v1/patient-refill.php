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
 * Quick Refill event log — POST /api/v1/patient-refill.php
 * Body: { customer_id, customer_name, items: [{medId, name, qty, unit}] }
 * Records every refill so "Last Visit" and refill frequency can be reported later.
 * Table is created on first use — no migration needed.
 */

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) $payload = [];

$customerId = (int) ($payload['customer_id'] ?? 0);
$customerName = substr(trim((string) ($payload['customer_name'] ?? '')), 0, 150);
$items = $payload['items'] ?? [];
if (!$customerId || !is_array($items) || !count($items)) {
    Json::error('customer_id and at least one item are required.', 422);
}

function sqlStr(string $v): string
{
    return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $v) . "'";
}

Manufacturer::query(
    "CREATE TABLE IF NOT EXISTS `refill_logs` (
       `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
       `customer_id` int(10) UNSIGNED NOT NULL,
       `customer_name` varchar(150) NOT NULL DEFAULT '',
       `items` text NOT NULL,
       `item_count` int(11) NOT NULL DEFAULT 0,
       `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
       PRIMARY KEY (`id`),
       KEY `customer_id` (`customer_id`)
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$clean = [];
foreach ($items as $it) {
    if (!is_array($it)) continue;
    $clean[] = [
        'medId' => (int) ($it['medId'] ?? 0),
        'name' => substr(trim((string) ($it['name'] ?? '')), 0, 200),
        'qty' => (int) ($it['qty'] ?? 1),
        'unit' => substr((string) ($it['unit'] ?? 'pack'), 0, 10),
    ];
}
if (!$clean) Json::error('No usable items in payload.', 422);

Manufacturer::query(
    "INSERT INTO refill_logs (customer_id, customer_name, items, item_count)
     VALUES ($customerId, " . sqlStr($customerName) . ", " . sqlStr(json_encode($clean, JSON_UNESCAPED_UNICODE)) . ", " . count($clean) . ")"
);

if (class_exists('Audit') && method_exists('Audit', 'log')) {
    try { Audit::log('REFILL', 'customers', $customerId, $customerName . ' — ' . count($clean) . ' item(s) refilled from POS'); } catch (\Throwable $e) { /* audit optional */ }
}

Json::ok(['data' => ['recorded' => true, 'item_count' => count($clean)]]);
