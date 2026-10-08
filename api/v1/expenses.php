<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

/**
 * Expenses ledger — GET list (?from&to + categories), POST create, DELETE ?id.
 *
 * Schema-defensive build (shared-hosting hardened):
 *  · no FOREIGN KEY constraints — shared hosts routinely refuse them (errno 1005)
 *  · self-heals pre-existing expenses/expense_categories tables that were
 *    created earlier with a different column set: missing columns are ADDed,
 *    nothing is ever dropped or renamed
 *  · every read/write failure surfaces as a JSON error with the driver message,
 *    never as a bare HTTP 500 — the page toast says exactly what to fix
 */

function dayOk(?string $d): ?string
{
    if (!is_string($d) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return null;
    [$y, $m, $dd] = array_map('intval', explode('-', $d));
    return checkdate($m, $dd, $y) ? $d : null;
}

function clip(string $value, int $max): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

function tableExists(PDO $pdo, string $table): bool
{
    try {
        return (bool) $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table))->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function columnSet(PDO $pdo, string $table): array
{
    try {
        $rows = $pdo->query("SHOW COLUMNS FROM {$table}")->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? array_map(fn ($r) => $r['Field'], $rows) : [];
    } catch (Throwable $e) {
        return [];
    }
}

function addColumnIfMissing(PDO $pdo, string $table, string $column, string $ddl): void
{
    if (!in_array($column, columnSet($pdo, $table), true)) {
        try { $pdo->exec("ALTER TABLE {$table} ADD {$ddl}"); } catch (Throwable $e) { /* next read degrades gracefully */ }
    }
}

$pdo = Tenant::db();

/* ---- Provision: create-if-absent, then heal missing columns ---- */
try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS expense_categories (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(60) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
} catch (Throwable $e) { /* verify below */ }
if (!tableExists($pdo, 'expense_categories')) {
    Json::error('Could not create the expense_categories table — the database user needs CREATE rights.', 500);
}
addColumnIfMissing($pdo, 'expense_categories', 'name', "name VARCHAR(60) NOT NULL DEFAULT 'Miscellaneous' AFTER id");

try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS expenses (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        category_id INT UNSIGNED NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        expense_date DATE NULL,
        mode VARCHAR(16) NOT NULL DEFAULT \'Cash\',
        note VARCHAR(200) NOT NULL DEFAULT \'\',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_expenses_date (expense_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
} catch (Throwable $e) { /* verify below */ }
if (!tableExists($pdo, 'expenses')) {
    Json::error('Could not create the expenses table — the database user needs CREATE rights.', 500);
}
addColumnIfMissing($pdo, 'expenses', 'category_id', 'category_id INT UNSIGNED NULL AFTER id');
addColumnIfMissing($pdo, 'expenses', 'amount', 'amount DECIMAL(12,2) NOT NULL DEFAULT 0');
addColumnIfMissing($pdo, 'expenses', 'expense_date', 'expense_date DATE NULL');
addColumnIfMissing($pdo, 'expenses', 'mode', "mode VARCHAR(16) NOT NULL DEFAULT 'Cash'");
addColumnIfMissing($pdo, 'expenses', 'note', "note VARCHAR(200) NOT NULL DEFAULT ''");
addColumnIfMissing($pdo, 'expenses', 'created_at', 'created_at TIMESTAMP NULL DEFAULT NULL');

/* ---- Seed any missing default categories (idempotent) ---- */
try {
    $have = array_map('strtolower', array_map('strval', (array) $pdo->query('SELECT name FROM expense_categories')->fetchAll(PDO::FETCH_COLUMN)));
    $seed = $pdo->prepare('INSERT INTO expense_categories (name) VALUES (?)');
    foreach (['Rent', 'Salary & Wages', 'Electricity', 'Water & Utilities', 'Transport & Freight', 'Packaging', 'Repairs & Maintenance', 'Miscellaneous'] as $name) {
        if (!in_array(strtolower($name), $have, true)) $seed->execute([$name]);
    }
} catch (Throwable $e) { /* seeding is best-effort */ }

function categoryRows(PDO $pdo): array
{
    try {
        $rows = $pdo->query('SELECT id, name FROM expense_categories ORDER BY name ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn ($r) => ['id' => (int) $r['id'], 'name' => $r['name']], $rows ?: []);
    } catch (Throwable $e) {
        return [];
    }
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $to = dayOk($_GET['to'] ?? '') ?: date('Y-m-d');
    $from = dayOk($_GET['from'] ?? '') ?: substr($to, 0, 8) . '01';
    try {
        $stmt = $pdo->prepare("SELECT e.id, e.category_id, e.amount, e.expense_date, e.mode, e.note,
                COALESCE(c.name, 'Uncategorised') AS category
            FROM expenses e LEFT JOIN expense_categories c ON c.id = e.category_id
            WHERE e.expense_date BETWEEN ? AND ?
            ORDER BY e.expense_date DESC, e.id DESC");
        $stmt->execute([$from, $to]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        Json::error('Expenses read failed: ' . $e->getMessage(), 500);
    }
    $data = array_map(fn ($r) => [
        'id' => (int) $r['id'],
        'categoryId' => (int) ($r['category_id'] ?? 0),
        'date' => $r['expense_date'],
        'category' => $r['category'],
        'note' => $r['note'],
        'mode' => $r['mode'],
        'amount' => (float) $r['amount'],
    ], $rows ?: []);
    Json::ok(['data' => $data, 'categories' => categoryRows($pdo), 'range' => ['from' => $from, 'to' => $to]]);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $amount = round((float) ($input['amount'] ?? 0), 2);
    $date = dayOk($input['date'] ?? '') ?: date('Y-m-d');
    $mode = ucfirst(strtolower(clip((string) ($input['mode'] ?? 'Cash'), 10)));
    if (!in_array($mode, ['Cash', 'Bank', 'Upi', 'Cheque'], true)) $mode = 'Cash';
    if ($mode === 'Upi') $mode = 'UPI';
    $note = clip((string) ($input['note'] ?? ''), 200);

    if ($amount <= 0) Json::error('Enter an amount greater than zero.', 422);
    if ($amount > 10000000) Json::error('That amount looks wrong — check the figure.', 422);
    if ($date > date('Y-m-d')) Json::error('Expenses cannot be dated in the future.', 422);

    $categoryId = (int) ($input['categoryId'] ?? 0);
    $newName = clip((string) ($input['categoryName'] ?? ''), 60);
    if ($categoryId <= 0 && $newName === '') Json::error('Pick a category or name a new one.', 422);

    if ($categoryId <= 0) {
        try {
            $sel = $pdo->prepare('SELECT id FROM expense_categories WHERE name = ? LIMIT 1');
            $sel->execute([$newName]);
            $categoryId = (int) $sel->fetchColumn();
            if (!$categoryId) {
                $ins = $pdo->prepare('INSERT INTO expense_categories (name) VALUES (?)');
                $ins->execute([$newName]);
                $categoryId = (int) $pdo->lastInsertId();
            }
        } catch (Throwable $e) {
            Json::error('Could not create the category: ' . $e->getMessage(), 500);
        }
    } else {
        try {
            $sel = $pdo->prepare('SELECT id FROM expense_categories WHERE id = ? LIMIT 1');
            $sel->execute([$categoryId]);
            if (!$sel->fetchColumn()) Json::error('Category not found.', 404);
        } catch (Throwable $e) {
            Json::error('Category lookup failed: ' . $e->getMessage(), 500);
        }
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO expenses (category_id, amount, expense_date, mode, note) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$categoryId, $amount, $date, $mode, $note]);
        $id = (int) $pdo->lastInsertId();
    } catch (Throwable $e) {
        Json::error('Could not save the expense: ' . $e->getMessage(), 500);
    }
    Json::ok(['data' => ['id' => $id, 'categoryId' => $categoryId], 'categories' => categoryRows($pdo)]);
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) Json::error('Missing expense id.', 422);
    try {
        $stmt = $pdo->prepare('DELETE FROM expenses WHERE id = ?');
        $stmt->execute([$id]);
    } catch (Throwable $e) {
        Json::error('Could not delete: ' . $e->getMessage(), 500);
    }
    if ($stmt->rowCount() === 0) Json::error('Expense not found.', 404);
    Json::ok(['data' => ['id' => $id]]);
}

Json::error('Method not allowed.', 405);
