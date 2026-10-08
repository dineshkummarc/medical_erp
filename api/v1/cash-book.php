<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    Json::error('Method not allowed.', 405);
}

/**
 * Cash Book — the day's physical-cash story, assembled from the same tables
 * the rest of the app already trusts:
 *   IN   sales.cash_amount (the cash portion of every bill, incl. split payments)
 *        + payments (mode Cash, direction in)   → due collections & receipts
 *   OUT  expenses (mode Cash) + payments (mode Cash, direction out)
 * Opening = every movement strictly before the chosen day, so the book is
 * always in agreement with billing without any manual carry-forward.
 *
 * POST saves the day's physical count (cash_counts) so the page can show the
 * variance between book cash and drawer cash.
 */

function cbDayOk(?string $d): ?string
{
    if (!is_string($d) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return null;
    [$y, $m, $dd] = array_map('intval', explode('-', $d));
    return checkdate($m, $dd, $y) ? $d : null;
}

function cbClip(string $value, int $max): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

$pdo = Tenant::db();

function cbColumnExists(PDO $pdo, string $table, string $column): bool
{
    try {
        return (bool) $pdo->query("SHOW COLUMNS FROM {$table} LIKE " . $pdo->quote($column))->fetch();
    } catch (Throwable $e) {
        return false;
    }
}

try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS cash_counts (
        count_date DATE NOT NULL PRIMARY KEY,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        note VARCHAR(200) NOT NULL DEFAULT \'\',
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
} catch (Throwable $e) { /* physical count is optional */ }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $date = cbDayOk($input['date'] ?? '');
    if (!$date) Json::error('A valid date is required.', 422);
    $amount = round((float) ($input['count'] ?? 0), 2);
    if ($amount < 0) Json::error('Physical count cannot be negative.', 422);
    $note = cbClip((string) ($input['note'] ?? ''), 200);
    try {
        $stmt = $pdo->prepare('INSERT INTO cash_counts (count_date, amount, note) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE amount = VALUES(amount), note = VALUES(note)');
        $stmt->execute([$date, $amount, $note]);
    } catch (Throwable $e) {
        Json::error('Could not save the physical count.', 500);
    }
    Json::ok(['data' => ['date' => $date, 'count' => $amount]]);
}

/* ---- GET: one day's book ---- */
$date = cbDayOk($_GET['date'] ?? '') ?: date('Y-m-d');
$q = $pdo->quote($date);

$movements = [];
$opening = 0.0;

try {
    /* Sales — the cash slice of each bill (credit leftovers never appear here,
       they arrive later through the payments table as collections). */
    $rows = $pdo->query("SELECT s.invoice_no AS ref, COALESCE(c.name, 'Walk-in') AS party,
                s.cash_amount AS amount, s.created_at AS stamp
            FROM sales s LEFT JOIN customers c ON c.id = s.customer_id
            WHERE DATE(s.sale_date) = $q AND COALESCE(s.cash_amount, 0) > 0
            ORDER BY s.id ASC");
    foreach ($rows as $r) {
        $movements[] = ['time' => substr((string) $r['stamp'], 11, 5), 'type' => 'sale', 'ref' => $r['ref'], 'party' => $r['party'], 'note' => '', 'direction' => 'in', 'amount' => (float) $r['amount']];
    }
    $opening += (float) $pdo->query("SELECT COALESCE(SUM(cash_amount), 0) FROM sales WHERE DATE(sale_date) < $q")->fetchColumn();
} catch (Throwable $e) { /* sales table shape differs — continue with the rest */ }

try {
    /* Payments — collections and payouts in hard cash only.
       direction/receipt_no are additive columns: used only when really present. */
    $hasDir = cbColumnExists($pdo, 'payments', 'direction');
    $hasReceipt = cbColumnExists($pdo, 'payments', 'receipt_no');
    $hasStamp = cbColumnExists($pdo, 'payments', 'created_at');
    $stampExpr = $hasStamp ? 'p.created_at' : 'NULL';
    $dirExpr = $hasDir ? "COALESCE(p.direction, IF(p.party_type = 'supplier', 'out', 'in'))" : "IF(p.party_type = 'supplier', 'out', 'in')";
    $rcptExpr = $hasReceipt ? 'p.receipt_no' : 'NULL';
    $rows = $pdo->query("SELECT p.id, p.note, p.amount, p.mode, p.party_type, {$stampExpr} AS stamp,
                {$dirExpr} AS dir, {$rcptExpr} AS receipt_no,
                COALESCE(c.name, s.name, '') AS party
            FROM payments p
            LEFT JOIN customers c ON p.party_type = 'customer' AND c.id = p.party_id
            LEFT JOIN suppliers  s ON p.party_type = 'supplier' AND s.id = p.party_id
            WHERE p.payment_date = $q AND LOWER(p.mode) = 'cash'
            ORDER BY p.id ASC");
    foreach ($rows as $r) {
        $dir = $r['dir'] === 'out' ? 'out' : 'in';
        $type = $dir === 'in'
            ? ($r['party_type'] === 'customer' ? 'collection' : 'receipt')
            : ($r['party_type'] === 'supplier' ? 'supplier-pay' : 'payment');
        $movements[] = ['time' => strlen((string) ($r['stamp'] ?? '')) > 10 ? substr((string) $r['stamp'], 11, 5) : '', 'type' => $type,
            'ref' => $r['receipt_no'] ?: ('PM-' . str_pad((string) $r['id'], 5, '0', STR_PAD_LEFT)),
            'party' => $r['party'] !== '' ? $r['party'] : $r['note'], 'note' => $r['party'] !== '' ? $r['note'] : '',
            'direction' => $dir, 'amount' => (float) $r['amount']];
    }
    $dirExprOpen = $hasDir ? "COALESCE(direction, IF(party_type = 'supplier', 'out', 'in'))" : "IF(party_type = 'supplier', 'out', 'in')";
    $opening += (float) $pdo->query("SELECT COALESCE(SUM(CASE WHEN {$dirExprOpen} = 'in' THEN amount ELSE -amount END), 0)
        FROM payments WHERE payment_date < $q AND LOWER(mode) = 'cash'")->fetchColumn();
} catch (Throwable $e) { /* payments table missing — collections just stay out */ }

try {
    /* Expenses paid in cash. */
    $rows = $pdo->query("SELECT e.id, e.note, e.amount, e.created_at AS stamp, c.name AS category
            FROM expenses e JOIN expense_categories c ON c.id = e.category_id
            WHERE e.expense_date = $q AND LOWER(e.mode) = 'cash'
            ORDER BY e.id ASC");
    foreach ($rows as $r) {
        $movements[] = ['time' => substr((string) $r['stamp'], 11, 5), 'type' => 'expense', 'ref' => $r['category'], 'party' => $r['note'], 'note' => '', 'direction' => 'out', 'amount' => (float) $r['amount']];
    }
    $opening -= (float) $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date < $q AND LOWER(mode) = 'cash'")->fetchColumn();
} catch (Throwable $e) { /* expenses tables not provisioned yet */ }

usort($movements, fn ($a, $b) => strcmp($a['time'], $b['time']));

$cashIn = 0.0;
$cashOut = 0.0;
foreach ($movements as $m) {
    if ($m['direction'] === 'in') $cashIn += $m['amount'];
    else $cashOut += $m['amount'];
}

$physical = null;
try {
    $stmt = $pdo->prepare('SELECT amount, note, updated_at FROM cash_counts WHERE count_date = ? LIMIT 1');
    $stmt->execute([$date]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $physical = ['count' => (float) $row['amount'], 'note' => $row['note'], 'savedAt' => $row['updated_at']];
} catch (Throwable $e) { /* optional */ }

Json::ok([
    'data' => [
        'date' => $date,
        'isToday' => $date === date('Y-m-d'),
        'opening' => round($opening, 2),
        'cashIn' => round($cashIn, 2),
        'cashOut' => round($cashOut, 2),
        'closing' => round($opening + $cashIn - $cashOut, 2),
        'movements' => $movements,
        'physical' => $physical,
    ],
]);
