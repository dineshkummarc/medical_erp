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
 * Medicine ledger (read-only).
 *
 *   ?q=term                → search medicines by name, brand, or batch no (max 12)
 *   ?medicineId=N          → header, KPIs, batch chips and the full movement ledger
 *                            (purchases, sales, returns, adjustments) for one medicine
 *
 * Movement balance is computed chronologically over the whole history, so a
 * filtered view still shows the true running balance after each event.
 */

$pdo = Tenant::db();
$q = trim((string) ($_GET['q'] ?? ''));
$medId = (int) ($_GET['medicineId'] ?? $_GET['medId'] ?? 0);

/* ---------- mode 1: search ---------- */
if ($medId <= 0) {
    if ($q === '') {
        Json::ok(['data' => []]);
    }
    $like = '%' . $q . '%';
    $sql = "SELECT m.id, m.name, m.brand_name, m.unit, m.mrp,
                   (SELECT COUNT(*) FROM batches b WHERE b.medicine_id = m.id) AS batch_count,
                   (SELECT COALESCE(SUM(b2.quantity), 0) FROM batches b2 WHERE b2.medicine_id = m.id) AS on_hand,
                   (SELECT MIN(b3.expiry_date) FROM batches b3 WHERE b3.medicine_id = m.id AND b3.quantity > 0) AS next_expiry
            FROM medicines m
            WHERE m.status = 'active'
              AND (m.name LIKE :q OR m.brand_name LIKE :q2
                   OR EXISTS (SELECT 1 FROM batches bx WHERE bx.medicine_id = m.id AND bx.batch_no LIKE :q3))
            ORDER BY m.name
            LIMIT 12";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['q' => $like, 'q2' => $like, 'q3' => $like]);
        $rows = $stmt->fetchAll();
    } catch (Throwable $e) {
        Json::error('Could not search medicines.', 503);
    }
    Json::ok(['data' => array_map(function ($r) {
        return [
            'id' => (int) $r['id'],
            'name' => (string) $r['name'],
            'brand' => (string) ($r['brand_name'] ?? ''),
            'unit' => (string) ($r['unit'] ?? ''),
            'mrp' => (float) ($r['mrp'] ?? 0),
            'batchCount' => (int) ($r['batch_count'] ?? 0),
            'stock' => (int) ($r['on_hand'] ?? 0),
            'nextExpiry' => $r['next_expiry'] ? substr((string) $r['next_expiry'], 0, 10) : null,
        ];
    }, $rows)]);
}

/* ---------- mode 2: one medicine's ledger ---------- */
if (!Medicine::find($medId)) {
    Json::error('Medicine not found.', 404);
}

try {
    // header + stock KPIs
    $mStmt = $pdo->prepare(
        "SELECT m.id, m.name, m.brand_name, m.unit, m.mrp, m.min_stock, m.reorder_level,
                (SELECT COUNT(*) FROM batches b WHERE b.medicine_id = m.id) AS batch_count,
                (SELECT COALESCE(SUM(b2.quantity), 0) FROM batches b2 WHERE b2.medicine_id = m.id) AS on_hand,
                (SELECT COALESCE(SUM(b4.quantity * b4.purchase_rate), 0) FROM batches b4 WHERE b4.medicine_id = m.id) AS stock_value,
                (SELECT MIN(b3.expiry_date) FROM batches b3 WHERE b3.medicine_id = m.id AND b3.quantity > 0) AS next_expiry
         FROM medicines m WHERE m.id = :id"
    );
    $mStmt->execute(['id' => $medId]);
    $med = $mStmt->fetch();

    // batch chips
    $bStmt = $pdo->prepare(
        "SELECT id, batch_no, purchase_date, expiry_date, quantity, purchase_rate, mrp
         FROM batches WHERE medicine_id = :id
         ORDER BY (expiry_date >= CURDATE()) DESC, expiry_date ASC, id ASC"
    );
    $bStmt->execute(['id' => $medId]);
    $batchRows = $bStmt->fetchAll();

    // sold in last 30 days
    $s30 = $pdo->prepare(
        "SELECT COALESCE(SUM(si.qty), 0) AS qty
         FROM sale_items si JOIN sales s ON s.id = si.sale_id
         WHERE si.medicine_id = :id AND s.sale_date >= (CURDATE() - INTERVAL 30 DAY)"
    );
    $s30->execute(['id' => $medId]);
    $sold30 = (int) ($s30->fetch()['qty'] ?? 0);

    // last purchase
    $lp = $pdo->prepare(
        "SELECT p.invoice_date, p.invoice_no, SUM(pi.qty + pi.free_qty) AS qty, MAX(pi.rate) AS rate
         FROM purchase_items pi JOIN purchases p ON p.id = pi.purchase_id
         WHERE pi.medicine_id = :id
         GROUP BY p.id, p.invoice_date, p.invoice_no
         ORDER BY p.invoice_date DESC, p.id DESC LIMIT 1"
    );
    $lp->execute(['id' => $medId]);
    $lastPurchase = $lp->fetch();

    // movement ledger
    $sql = "SELECT kind, src_id, event_date, created_at, ref_no, party, qty_in, qty_out, rate, amount, batch_id, batch_no, note
            FROM (
              SELECT 'purchase' AS kind, pi.id AS src_id, p.invoice_date AS event_date, p.created_at AS created_at,
                     p.invoice_no AS ref_no, COALESCE(su.name, '') AS party,
                     (pi.qty + pi.free_qty) AS qty_in, 0 AS qty_out,
                     pi.rate AS rate, pi.amount AS amount,
                     pi.batch_id AS batch_id, COALESCE(b.batch_no, '') AS batch_no, '' AS note
              FROM purchase_items pi
              JOIN purchases p ON p.id = pi.purchase_id
              LEFT JOIN suppliers su ON su.id = p.supplier_id
              LEFT JOIN batches b ON b.id = pi.batch_id
              WHERE pi.medicine_id = :id1
              UNION ALL
              SELECT 'sale', si.id, s.sale_date, s.created_at,
                     s.invoice_no, COALESCE(c.name, 'Walk-in'),
                     0, si.qty, si.rate, si.amount,
                     si.batch_id, COALESCE(b.batch_no, ''), ''
              FROM sale_items si
              JOIN sales s ON s.id = si.sale_id
              LEFT JOIN customers c ON c.id = s.customer_id
              LEFT JOIN batches b ON b.id = si.batch_id
              WHERE si.medicine_id = :id2
              UNION ALL
              SELECT 'sale_return', sri.id, sr.return_date, sr.created_at,
                     sr.return_no, COALESCE(c.name, 'Walk-in'),
                     sri.qty, 0, sri.rate, sri.amount,
                     sri.batch_id, COALESCE(b.batch_no, ''),
                     CONCAT(sr.reason, CASE WHEN sr.note IS NULL OR sr.note = '' THEN '' ELSE CONCAT(' — ', sr.note) END)
              FROM sales_return_items sri
              JOIN sales_returns sr ON sr.id = sri.return_id
              LEFT JOIN sales s ON s.id = sr.sale_id
              LEFT JOIN customers c ON c.id = s.customer_id
              LEFT JOIN batches b ON b.id = sri.batch_id
              WHERE sri.medicine_id = :id3
              UNION ALL
              SELECT 'purchase_return', pri.id, pr.return_date, pr.created_at,
                     pr.return_no, COALESCE(su.name, ''),
                     0, pri.qty, pri.rate, pri.amount,
                     pri.batch_id, COALESCE(b.batch_no, ''),
                     CONCAT(pr.reason, CASE WHEN pr.note IS NULL OR pr.note = '' THEN '' ELSE CONCAT(' — ', pr.note) END)
              FROM purchase_return_items pri
              JOIN purchase_returns pr ON pr.id = pri.return_id
              LEFT JOIN purchases p ON p.id = pr.purchase_id
              LEFT JOIN suppliers su ON su.id = p.supplier_id
              LEFT JOIN batches b ON b.id = pri.batch_id
              WHERE pri.medicine_id = :id4
              UNION ALL
              SELECT 'adjustment', sa.id, DATE(sa.created_at), sa.created_at,
                     CONCAT('ADJ-', sa.id), COALESCE(sa.adjusted_by, ''),
                     GREATEST(sa.qty_change, 0), GREATEST(-sa.qty_change, 0),
                     COALESCE(b.purchase_rate, 0), ABS(sa.qty_change) * COALESCE(b.purchase_rate, 0),
                     sa.batch_id, COALESCE(b.batch_no, ''),
                     CONCAT(sa.reason, CASE WHEN sa.notes IS NULL OR sa.notes = '' THEN '' ELSE CONCAT(' — ', sa.notes) END)
              FROM stock_adjustments sa
              LEFT JOIN batches b ON b.id = sa.batch_id
              WHERE sa.medicine_id = :id5
            ) ledger
            ORDER BY event_date ASC, created_at ASC, src_id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id1' => $medId, 'id2' => $medId, 'id3' => $medId, 'id4' => $medId, 'id5' => $medId]);
    $rows = $stmt->fetchAll();
} catch (Throwable $e) {
    Json::error('Could not load the medicine ledger.', 503);
}

// running balance, chronological, over full history
$balance = 0;
$entries = [];
foreach ($rows as $r) {
    $in = (int) ($r['qty_in'] ?? 0);
    $out = (int) ($r['qty_out'] ?? 0);
    $balance += $in - $out;
    $kind = (string) $r['kind'];
    $ref = (string) ($r['ref_no'] ?? '');
    $srcPage = [
        'purchase' => 'purchase-invoices.php?invoice=' . rawurlencode($ref),
        'sale' => 'sales-invoices.php?invoice=' . rawurlencode($ref),
        'sale_return' => 'sales-return.php?ref=' . rawurlencode($ref),
        'purchase_return' => 'purchase-return.php?ref=' . rawurlencode($ref),
        'adjustment' => 'stock-adjustment.php',
    ][$kind];
    $entries[] = [
        'kind' => $kind,
        'date' => $r['event_date'] ? substr((string) $r['event_date'], 0, 10) : '',
        'ref' => $ref,
        'party' => (string) ($r['party'] ?? ''),
        'batch' => (string) ($r['batch_no'] ?? ''),
        'batchId' => $r['batch_id'] !== null ? (int) $r['batch_id'] : null,
        'in' => $in,
        'out' => $out,
        'balance' => $balance,
        'rate' => (float) ($r['rate'] ?? 0),
        'value' => (float) ($r['amount'] ?? 0),
        'note' => (string) ($r['note'] ?? ''),
        'src' => $srcPage,
    ];
}

$nearest = $med['next_expiry'] ? substr((string) $med['next_expiry'], 0, 10) : null;

Json::ok(['data' => [
    'medicine' => [
        'id' => (int) $med['id'],
        'name' => (string) $med['name'],
        'brand' => (string) ($med['brand_name'] ?? ''),
        'unit' => (string) ($med['unit'] ?? ''),
        'mrp' => (float) ($med['mrp'] ?? 0),
        'minStock' => (int) ($med['min_stock'] ?? 0),
        'reorderLevel' => (int) ($med['reorder_level'] ?? 0),
        'batchCount' => (int) ($med['batch_count'] ?? 0),
        'stock' => (int) ($med['on_hand'] ?? 0),
        'stockValue' => (float) ($med['stock_value'] ?? 0),
        'nextExpiry' => $nearest,
    ],
    'kpis' => [
        'currentStock' => (int) ($med['on_hand'] ?? 0),
        'sold30' => $sold30,
        'lastPurchase' => $lastPurchase ? [
            'date' => substr((string) $lastPurchase['invoice_date'], 0, 10),
            'ref' => (string) $lastPurchase['invoice_no'],
            'qty' => (int) $lastPurchase['qty'],
            'rate' => (float) $lastPurchase['rate'],
        ] : null,
        'stockValue' => (float) ($med['stock_value'] ?? 0),
    ],
    'batches' => array_map(function ($b) {
        return [
            'id' => (int) $b['id'],
            'batchNo' => (string) $b['batch_no'],
            'purchaseDate' => $b['purchase_date'] ? substr((string) $b['purchase_date'], 0, 10) : null,
            'expiry' => $b['expiry_date'] ? substr((string) $b['expiry_date'], 0, 10) : null,
            'qty' => (int) $b['quantity'],
            'purchaseRate' => (float) $b['purchase_rate'],
            'mrp' => (float) $b['mrp'],
        ];
    }, $batchRows),
    // newest first for display; each row already carries its running balance
    'entries' => array_reverse($entries),
]]);
