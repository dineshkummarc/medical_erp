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
 * Refill log — GET /api/v1/refill-logs.php
 * Recent fast-refill events recorded from the POS Quick Refill panel.
 */

function qr(string $sql): array
{
    $rows = Manufacturer::query($sql);
    return is_array($rows) ? $rows : [];
}

try {
    $rows = qr(
        "SELECT l.id, l.customer_id, COALESCE(c.name, l.customer_name) AS customer_name,
                COALESCE(c.phone, '') AS phone, l.items, l.item_count, l.created_at
           FROM refill_logs l LEFT JOIN customers c ON c.id = l.customer_id
          ORDER BY l.id DESC LIMIT 200"
    );

    $out = array_map(function ($r) {
        $items = json_decode((string) ($r['items'] ?? ''), true);
        if (!is_array($items)) $items = [];
        $names = array_map(function ($it) { return trim(($it['name'] ?? '') . ' × ' . ($it['qty'] ?? 1)); }, array_slice($items, 0, 3));
        $preview = implode(', ', array_filter($names));
        if (count($items) > 3) $preview .= ' +' . (count($items) - 3) . ' more';
        return [
            'id' => (int) $r['id'],
            'customer' => (string) $r['customer_name'],
            'phone' => (string) $r['phone'],
            'itemCount' => (int) $r['item_count'],
            'preview' => $preview,
            'items' => $items,
            'ts' => (string) $r['created_at'],
        ];
    }, $rows);

    Json::ok(['data' => $out]);
} catch (\Throwable $e) {
    Json::error('Refill log failed: ' . $e->getMessage(), 500);
}
