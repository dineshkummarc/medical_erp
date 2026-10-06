<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';
require dirname(__DIR__, 2) . '/models/Manufacturer.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

/**
 * Sale audit — POST /api/v1/sale-audit.php
 * Best-effort ledger for sensitive POS events. Today it records below-cost
 * sales that the cashier consciously allowed; tomorrow: overrides, voids,
 * large discounts. Same auto-create pattern as the refill log.
 */

function qr(string $sql): array
{
    $rows = Manufacturer::query($sql);
    return is_array($rows) ? $rows : [];
}

try {
    qr('CREATE TABLE IF NOT EXISTS sale_audit (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            invoice_no VARCHAR(60) NOT NULL,
            event VARCHAR(40) NOT NULL,
            detail TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ix_event (event)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
} catch (Throwable $e) { /* table creation is best-effort */ }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Json::error('Method not allowed.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$event = strtoupper(substr(trim((string) ($input['event'] ?? '')), 0, 40));
$invoice = substr(trim((string) ($input['invoiceNo'] ?? $input['invoice'] ?? '')), 0, 60);
$detail = (string) ($input['detail'] ?? '');
if (strlen($detail) > 8000) $detail = substr($detail, 0, 8000);

if ($event === '') Json::error('Event name is required.', 422);

$esc = fn(string $s) => "'" . addslashes($s) . "'";
try {
    Manufacturer::query('INSERT INTO sale_audit (invoice_no, event, detail) VALUES (' . $esc($invoice) . ', ' . $esc($event) . ', ' . $esc($detail) . ')');
} catch (Throwable $e) {
    Json::error('Audit write failed.', 500);
}

Json::ok(['data' => ['logged' => true]]);
