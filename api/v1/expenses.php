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
 * Self-provisions its two small tables on first call (wholesale.php precedent),
 * so no separate migration run is required.
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

$pdo = Tenant::db();

try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS expense_categories (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(60) NOT NULL UNIQUE,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $pdo->exec('CREATE TABLE IF NOT EXISTS expenses (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        category_id INT UNSIGNED NOT NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        expense_date DATE NOT NULL,
        mode VARCHAR(16) NOT NULL DEFAULT \'Cash\',
        note VARCHAR(200) NOT NULL DEFAULT \'\',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_expenses_date (expense_date),
        CONSTRAINT fk_expenses_category FOREIGN KEY (category_id) REFERENCES expense_categories (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
} catch (Throwable $e) {
    Json::error('Expense tables could not be prepared.', 500);
}

/* Seed the category list once — renames stay user-owned afterwards. */
try {
    $count = (int) $pdo->query('SELECT COUNT(*) FROM expense_categories')->fetchColumn();
    if ($count === 0) {
        $seed = $pdo->prepare('INSERT IGNORE INTO expense_categories (name) VALUES (?)');
        foreach (['Rent', 'Salary & Wages', 'Electricity', 'Water & Utilities', 'Transport & Freight', 'Packaging', 'Repairs & Maintenance', 'Miscellaneous'] as $name) {
            $seed->execute([$name]);
        }
    }
} catch (Throwable $e) { /* seeding is best-effort */ }

function categoryRows(PDO $pdo): array
{
    $rows = $pdo->query('SELECT id, name FROM expense_categories ORDER BY name ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC);
    return array_map(fn ($r) => ['id' => (int) $r['id'], 'name' => $r['name']], $rows);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $to = dayOk($_GET['to'] ?? '') ?: date('Y-m-d');
    $from = dayOk($_GET['from'] ?? '') ?: substr($to, 0, 8) . '01';
    $stmt = $pdo->prepare('SELECT e.id, e.category_id, e.amount, e.expense_date, e.mode, e.note, c.name AS category
        FROM expenses e JOIN expense_categories c ON c.id = e.category_id
        WHERE e.expense_date BETWEEN ? AND ?
        ORDER BY e.expense_date DESC, e.id DESC');
    $stmt->execute([$from, $to]);
    $data = array_map(fn ($r) => [
        'id' => (int) $r['id'],
        'categoryId' => (int) $r['category_id'],
        'date' => $r['expense_date'],
        'category' => $r['category'],
        'note' => $r['note'],
        'mode' => $r['mode'],
        'amount' => (float) $r['amount'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
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

    try {
        $pdo->beginTransaction();
        if ($categoryId <= 0) {
            $ins = $pdo->prepare('INSERT IGNORE INTO expense_categories (name) VALUES (?)');
            $ins->execute([$newName]);
            $sel = $pdo->prepare('SELECT id FROM expense_categories WHERE name = ? LIMIT 1');
            $sel->execute([$newName]);
            $categoryId = (int) $sel->fetchColumn();
        } else {
            $sel = $pdo->prepare('SELECT id FROM expense_categories WHERE id = ? LIMIT 1');
            $sel->execute([$categoryId]);
            if (!$sel->fetchColumn()) Json::error('Category not found.', 404);
        }
        $stmt = $pdo->prepare('INSERT INTO expenses (category_id, amount, expense_date, mode, note) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$categoryId, $amount, $date, $mode, $note]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
    } catch (JsonException $e) {
        throw $e;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        Json::error('Could not save the expense.', 500);
    }
    Json::ok(['data' => ['id' => $id, 'categoryId' => $categoryId], 'categories' => categoryRows($pdo)]);
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) Json::error('Missing expense id.', 422);
    $stmt = $pdo->prepare('DELETE FROM expenses WHERE id = ?');
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) Json::error('Expense not found.', 404);
    Json::ok(['data' => ['id' => $id]]);
}

Json::error('Method not allowed.', 405);
