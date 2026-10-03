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
 * Bulk price update — POST /api/v1/price-bulk-update.php
 * Body: {
 *   ids: [1,2,3],            // selected medicines (already matched client-side)
 *   pct: 5 | -5,             // signed percentage (non-zero)
 *   round: "none" | "1" | "0.5",
 *   targets: { mrp: 1, retail: 1, wholesale: 0 }
 * }
 * One atomic-ish UPDATE — far safer than posting medicine saves in a loop.
 */

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) $payload = [];

try {
    $ids = [];
    foreach (($payload['ids'] ?? []) as $v) {
        $id = (int) $v;
        if ($id > 0) $ids[] = $id;
    }
    $ids = array_values(array_unique($ids));

    $pct = (float) ($payload['pct'] ?? 0);
    $mode = (string) ($payload['round'] ?? 'none');       // none | 1 | 0.5
    $targets = $payload['targets'] ?? [];

    if (!count($ids)) Json::error('No medicines selected.', 422);
    if ($pct > -0.0001 && $pct < 0.0001) Json::error('Percent change must be non-zero.', 422);

    $factor = 1 + ($pct / 100);
    $expr = function (string $col) use ($factor, $mode) {
        $raw = "$col * " . $factor;
        if ($mode === '1') return "ROUND($raw, 0)";
        if ($mode === '0.5') return "ROUND($raw * 2) / 2";
        return "ROUND($raw, 2)";
    };

    $cols = [];
    if (!empty($targets['mrp'])) $cols[] = "mrp = " . $expr('mrp');
    if (!empty($targets['retail'])) $cols[] = "retail_rate = " . $expr('retail_rate');
    if (!empty($targets['wholesale'])) $cols[] = "wholesale_rate = " . $expr('wholesale_rate');
    if (!count($cols)) Json::error('Pick at least one price to update.', 422);

    $list = implode(',', $ids);
    $where = "id IN ($list) AND status = 'active'";

    $countRows = Manufacturer::query("SELECT COUNT(*) AS n FROM medicines WHERE $where");
    $matched = (int) ($countRows[0]['n'] ?? 0);

    Manufacturer::query("UPDATE medicines SET " . implode(', ', $cols) . " WHERE $where");

    if (class_exists('Audit') && method_exists('Audit', 'log')) {
        try { Audit::log('PRICE_BULK_UPDATE', ($pct > 0 ? '+' : '') . $pct . '% on ' . $matched . ' medicine(s)'); } catch (\Throwable $e) { /* optional */ }
    }

    Json::ok(['data' => ['matched' => $matched, 'pct' => $pct]]);
} catch (\Throwable $e) {
    Json::error('Bulk update failed: ' . $e->getMessage(), 500);
}
