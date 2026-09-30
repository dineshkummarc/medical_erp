<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';
require dirname(__DIR__, 2) . '/models/Medicine.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Json::error('Method not allowed.', 405);
}

/**
 * Last five stock movements for one medicine: sale, purchase, adjustment.
 * Used by the Medicine Master stock modal. Does not replace stock-adjustments.php.
 */

$medId = (int) ($_GET['medicineId'] ?? $_GET['medId'] ?? 0);
if (!$medId || !Medicine::find($medId)) {
    Json::error('Medicine not found.', 404);
}

$pdo = Tenant::db();
$sql = "SELECT kind, event_date, ref_no, party, qty, batch_no, detail, created_at
        FROM (
          SELECT 'sale' AS kind, s.sale_date AS event_date, s.created_at AS created_at,
                 s.invoice_no AS ref_no, COALESCE(c.name, '') AS party,
                 -si.qty AS qty, COALESCE(b.batch_no, '') AS batch_no, '' AS detail
          FROM sale_items si
          JOIN sales s ON s.id = si.sale_id
          LEFT JOIN customers c ON c.id = s.customer_id
          LEFT JOIN batches b ON b.id = si.batch_id
          WHERE si.medicine_id = :id
          UNION ALL
          SELECT 'purchase', p.invoice_date, p.created_at,
                 p.invoice_no, COALESCE(su.name, ''),
                 pi.qty, COALESCE(b.batch_no, ''), ''
          FROM purchase_items pi
          JOIN purchases p ON p.id = pi.purchase_id
          LEFT JOIN suppliers su ON su.id = p.supplier_id
          LEFT JOIN batches b ON b.id = pi.batch_id
          WHERE pi.medicine_id = :id2
          UNION ALL
          SELECT 'adjustment', DATE(sa.created_at), sa.created_at,
                 sa.reason, COALESCE(sa.adjusted_by, ''),
                 sa.qty_change, COALESCE(b.batch_no, ''), COALESCE(sa.notes, '')
          FROM stock_adjustments sa
          LEFT JOIN batches b ON b.id = sa.batch_id
          WHERE sa.medicine_id = :id3
        ) activity
        ORDER BY created_at DESC, event_date DESC
        LIMIT 5";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $medId, 'id2' => $medId, 'id3' => $medId]);
    $rows = $stmt->fetchAll();
} catch (Throwable $e) {
    Json::error('Could not load recent activity.', 503);
}

Json::ok(['data' => array_map(function ($r) {
    $date = $r['event_date'] ?? '';
    return [
        'kind' => (string) ($r['kind'] ?? ''),
        'date' => $date ? substr((string) $date, 0, 10) : '',
        'ref' => (string) ($r['ref_no'] ?? ''),
        'party' => (string) ($r['party'] ?? ''),
        'qty' => (int) ($r['qty'] ?? 0),
        'batch' => (string) ($r['batch_no'] ?? ''),
        'detail' => (string) ($r['detail'] ?? ''),
    ];
}, $rows)]);
