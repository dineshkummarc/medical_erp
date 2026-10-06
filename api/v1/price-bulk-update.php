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
    $action = (string) ($payload['action'] ?? 'adjust');  // adjust | flat | derive

    if (!count($ids)) Json::error('No medicines selected.', 422);

    $roundExpr = function (string $raw) use ($mode) {
        if ($mode === '1') return "ROUND($raw, 0)";
        if ($mode === '0.5') return "ROUND($raw * 2) / 2";
        return "ROUND($raw, 2)";
    };

    if ($action === 'derive') {
        // "Set wholesale = MRP × N%" — pct carries the percent OF MRP here.
        if ($pct < 1 || $pct > 100) Json::error('Percent of MRP must be between 1 and 100.', 422);
        $factor = $pct / 100;
        $expr = function (string $col) use ($factor, $roundExpr) {
            return $roundExpr("$col * " . $factor);
        };
        $cols = ["wholesale_rate = " . $expr('mrp')];
    } else {
        if ($pct > -0.0001 && $pct < 0.0001) Json::error('Amount must be non-zero.', 422);
        if (abs($pct) > 100000) Json::error('Amount is implausibly large — check the value.', 422);
    }

    // Flat rupee moves can floor a cheap line to zero — count them so the
    // cashier hears about it instead of finding out on a price tag.
    $floored = 0;
    if ($action === 'flat' && $pct < 0) {
        $list0 = implode(',', $ids);
        $worse = 0;
        foreach (['mrp', 'retail_rate', 'wholesale_rate'] as $c) {
            if (($c === 'mrp' && !empty($targets['mrp'])) || ($c === 'retail_rate' && !empty($targets['retail'])) || ($c === 'wholesale_rate' && !empty($targets['wholesale']))) {
                $r0 = Manufacturer::query("SELECT COUNT(*) AS n FROM medicines WHERE id IN ($list0) AND $c + $pct <= 0 AND status = 'active'");
                $worse += (int) ($r0[0]['n'] ?? 0);
            }
        }
        $floored = $worse;
    }

    $factor = 1 + ($pct / 100);

    if ($action !== 'derive') {
        $cols = [];
        if ($action === 'flat') {
            // Signed flat rupee delta; negative results clamp to ₹0 (and are reported).
            $delta = number_format($pct, 2, '.', '');
            $flatExpr = function (string $col) use ($delta, $roundExpr) {
                return $roundExpr("GREATEST($col + $delta, 0)");
            };
            if (!empty($targets['mrp'])) $cols[] = "mrp = " . $flatExpr('mrp');
            if (!empty($targets['retail'])) $cols[] = "retail_rate = " . $flatExpr('retail_rate');
            if (!empty($targets['wholesale'])) $cols[] = "wholesale_rate = " . $flatExpr('wholesale_rate');
        } else {
            $expr = function (string $col) use ($factor, $roundExpr) {
                return $roundExpr("$col * " . $factor);
            };
            if (!empty($targets['mrp'])) $cols[] = "mrp = " . $expr('mrp');
            if (!empty($targets['retail'])) $cols[] = "retail_rate = " . $expr('retail_rate');
            if (!empty($targets['wholesale'])) $cols[] = "wholesale_rate = " . $expr('wholesale_rate');
        }
        if (!count($cols)) Json::error('Pick at least one price to update.', 422);
    }

    $list = implode(',', $ids);
    $where = "id IN ($list) AND status = 'active'";

    $countRows = Manufacturer::query("SELECT COUNT(*) AS n FROM medicines WHERE $where");
    $matched = (int) ($countRows[0]['n'] ?? 0);

    Manufacturer::query("UPDATE medicines SET " . implode(', ', $cols) . " WHERE $where");

    if (class_exists('Audit') && method_exists('Audit', 'log')) {
        try {
            if ($action === 'derive') {
                $note = 'wholesale = MRP x ' . $pct . '% on ' . $matched . ' medicine(s)';
            } elseif ($action === 'flat') {
                $note = 'flat ' . ($pct > 0 ? '+' : '') . '₹' . number_format($pct, 2) . ' on ' . $matched . ' medicine(s)' . ($floored ? ' · ' . $floored . ' price(s) floored to 0' : '');
            } else {
                $note = ($pct > 0 ? '+' : '') . $pct . '% on ' . $matched . ' medicine(s)';
            }
            Audit::log('PRICE_BULK_UPDATE', $note);
        } catch (\Throwable $e) { /* optional */ }
    }

    Json::ok(['data' => ['matched' => $matched, 'pct' => $pct, 'floored' => $floored]]);
} catch (\Throwable $e) {
    Json::error('Bulk update failed: ' . $e->getMessage(), 500);
}
