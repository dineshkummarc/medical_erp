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
 * Schedule / Class master.
 * Columns come from database/migrations/2026_09_28_schedule_classes.sql.
 * Uses Manufacturer::query so a missing ScheduleClass model cannot drop columns.
 * Ledger counts are computed live from medicines, batches and sale_items.
 */

function queryRows(string $sql): array
{
    $rows = Manufacturer::query($sql);
    return is_array($rows) ? $rows : [];
}

function sqlStr(string $value): string
{
    return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $value) . "'";
}

function sqlNull(string $value): string
{
    $value = trim($value);
    return $value === '' ? 'NULL' : sqlStr($value);
}

function clip(string $value, int $max): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max);
    }
    return substr($value, 0, $max);
}

function flag($value): int
{
    if (is_bool($value)) {
        return $value ? 1 : 0;
    }
    $text = strtolower(trim((string) $value));
    return in_array($text, ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
}

function statusOf($value): string
{
    return preg_match('/^in/i', trim((string) $value)) ? 'Inactive' : 'Active';
}

function kindOf($value): string
{
    return strtolower(trim((string) $value)) === 'class' ? 'class' : 'schedule';
}

function hasColumn(string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $rows = queryRows(
            'SELECT 1 AS ok FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ' . sqlStr($table) . '
               AND COLUMN_NAME = ' . sqlStr($column) . ' LIMIT 1'
        );
        $cache[$key] = (bool) $rows;
    } catch (Throwable $e) {
        $cache[$key] = false;
    }
    return $cache[$key];
}

function medicines(): array
{
    static $rows = null;
    if ($rows !== null) {
        return $rows;
    }
    if (!hasColumn('medicines', 'id')) {
        return $rows = [];
    }
    $cols = ['id', 'name'];
    foreach (['schedule', 'schedule_id', 'class_id', 'therapeutic_class', 'drug_class', 'class_name', 'mrp', 'unit', 'manufacturer_id'] as $col) {
        if (hasColumn('medicines', $col)) {
            $cols[] = $col;
        }
    }
    try {
        $rows = queryRows('SELECT ' . implode(', ', $cols) . ' FROM medicines');
    } catch (Throwable $e) {
        $rows = [];
    }
    return $rows;
}

function sameText($left, $right): bool
{
    return strtolower(trim((string) $left)) === strtolower(trim((string) $right));
}

function matches(array $row, array $med): bool
{
    $id = (int) ($row['id'] ?? 0);
    $code = (string) ($row['code'] ?? '');
    $name = (string) ($row['name'] ?? '');
    if (($row['kind'] ?? '') === 'class') {
        if (isset($med['class_id']) && (int) $med['class_id'] === $id && $id > 0) {
            return true;
        }
        foreach (['therapeutic_class', 'drug_class', 'class_name'] as $col) {
            if (!array_key_exists($col, $med)) {
                continue;
            }
            $value = trim((string) $med[$col]);
            if ($value !== '' && (sameText($value, $code) || sameText($value, $name))) {
                return true;
            }
        }
        return false;
    }
    if (isset($med['schedule_id']) && (int) $med['schedule_id'] === $id && $id > 0) {
        return true;
    }
    return array_key_exists('schedule', $med) && sameText($med['schedule'] ?? '', $code);
}

function batchMap(): array
{
    $qty = hasColumn('batches', 'quantity') ? 'quantity' : (hasColumn('batches', 'qty') ? 'qty' : '');
    if ($qty === '' || !hasColumn('batches', 'medicine_id')) {
        return [];
    }
    $mrp = hasColumn('batches', 'mrp') ? 'COALESCE(mrp, 0)' : '0';
    try {
        $rows = queryRows(
            'SELECT medicine_id, COUNT(*) AS batch_count, COALESCE(SUM(' . $qty . '), 0) AS stock_qty,
                    COALESCE(SUM(' . $qty . ' * ' . $mrp . '), 0) AS mrp_value
             FROM batches GROUP BY medicine_id'
        );
    } catch (Throwable $e) {
        return [];
    }
    $map = [];
    foreach ($rows as $row) {
        $map[(int) ($row['medicine_id'] ?? 0)] = [
            'batch_count' => (int) ($row['batch_count'] ?? 0),
            'stock_qty' => (float) ($row['stock_qty'] ?? 0),
            'mrp_value' => (float) ($row['mrp_value'] ?? 0),
        ];
    }
    return $map;
}

function salesMap(): array
{
    if (!hasColumn('sale_items', 'medicine_id') || !hasColumn('sale_items', 'sale_id') || !hasColumn('sales', 'id')) {
        return [];
    }
    $dateCol = hasColumn('sales', 'sale_date') ? 's.sale_date' : (hasColumn('sales', 'created_at') ? 's.created_at' : '');
    if ($dateCol === '') {
        return [];
    }
    $amount = hasColumn('sale_items', 'amount') ? 'COALESCE(SUM(si.amount), 0)' : '0';
    $qty = hasColumn('sale_items', 'qty') ? 'COALESCE(SUM(si.qty), 0)' : '0';
    try {
        $rows = queryRows(
            'SELECT si.medicine_id AS medicine_id, ' . $qty . ' AS qty, ' . $amount . ' AS amount
             FROM sale_items si
             INNER JOIN sales s ON s.id = si.sale_id
             WHERE ' . $dateCol . ' >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
             GROUP BY si.medicine_id'
        );
    } catch (Throwable $e) {
        return [];
    }
    $map = [];
    foreach ($rows as $row) {
        $map[(int) ($row['medicine_id'] ?? 0)] = [
            'qty' => (float) ($row['qty'] ?? 0),
            'amount' => (float) ($row['amount'] ?? 0),
        ];
    }
    return $map;
}

function manufacturerNames(): array
{
    if (!hasColumn('manufacturers', 'id') || !hasColumn('manufacturers', 'name')) {
        return [];
    }
    try {
        $rows = queryRows('SELECT id, name FROM manufacturers');
    } catch (Throwable $e) {
        return [];
    }
    $map = [];
    foreach ($rows as $row) {
        $map[(int) ($row['id'] ?? 0)] = (string) ($row['name'] ?? '');
    }
    return $map;
}

function shape(array $row, array $batches, array $sales): array
{
    $ids = [];
    foreach (medicines() as $med) {
        if (matches($row, $med)) {
            $ids[] = (int) ($med['id'] ?? 0);
        }
    }
    $batchCount = 0;
    $stock = 0.0;
    $mrp = 0.0;
    $saleQty = 0.0;
    $saleAmt = 0.0;
    foreach ($ids as $id) {
        $batch = $batches[$id] ?? null;
        if ($batch) {
            $batchCount += $batch['batch_count'];
            $stock += $batch['stock_qty'];
            $mrp += $batch['mrp_value'];
        }
        $sale = $sales[$id] ?? null;
        if ($sale) {
            $saleQty += $sale['qty'];
            $saleAmt += $sale['amount'];
        }
    }
    return [
        'id' => (int) ($row['id'] ?? 0),
        'kind' => kindOf($row['kind'] ?? 'schedule'),
        'code' => (string) ($row['code'] ?? ''),
        'name' => (string) ($row['name'] ?? ''),
        'description' => (string) ($row['description'] ?? ''),
        'rx_required' => (int) ($row['rx_required'] ?? 0),
        'register_required' => (int) ($row['register_required'] ?? 0),
        'status' => statusOf($row['status'] ?? 'Active'),
        'sort_order' => (int) ($row['sort_order'] ?? 100),
        'medicine_count' => count($ids),
        'batch_count' => $batchCount,
        'stock_qty' => $stock,
        'mrp_value' => $mrp,
        'sales_qty_30' => $saleQty,
        'sales_amount_30' => $saleAmt,
    ];
}

function listRows(): array
{
    $rows = queryRows('SELECT id, kind, code, name, description, rx_required, register_required, status, sort_order
        FROM schedule_classes ORDER BY kind ASC, sort_order ASC, code ASC, id ASC');
    $batches = batchMap();
    $sales = salesMap();
    return array_map(function (array $row) use ($batches, $sales): array {
        return shape($row, $batches, $sales);
    }, $rows);
}

function findById(int $id): ?array
{
    if ($id < 1) {
        return null;
    }
    foreach (listRows() as $row) {
        if ((int) $row['id'] === $id) {
            return $row;
        }
    }
    return null;
}

function medicineDetails(array $row): array
{
    $makers = manufacturerNames();
    $batches = batchMap();
    $out = [];
    foreach (medicines() as $med) {
        if (!matches($row, $med)) {
            continue;
        }
        $id = (int) ($med['id'] ?? 0);
        $makerId = (int) ($med['manufacturer_id'] ?? 0);
        $out[] = [
            'id' => $id,
            'name' => (string) ($med['name'] ?? ''),
            'manufacturer' => $makers[$makerId] ?? '',
            'schedule' => (string) ($med['schedule'] ?? ''),
            'unit' => (string) ($med['unit'] ?? ''),
            'mrp' => (float) ($med['mrp'] ?? 0),
            'stock' => (float) (($batches[$id]['stock_qty'] ?? 0)),
        ];
    }
    usort($out, function (array $a, array $b): int {
        return strcasecmp($a['name'], $b['name']);
    });
    return $out;
}

function codeTaken(string $kind, string $code, int $excludeId = 0): bool
{
    $rows = queryRows(
        'SELECT id FROM schedule_classes WHERE kind = ' . sqlStr($kind) .
        ' AND LOWER(code) = ' . sqlStr(strtolower($code)) . ' LIMIT 1'
    );
    if (!$rows) {
        return false;
    }
    return (int) ($rows[0]['id'] ?? 0) !== $excludeId;
}

function profileFromInput(array $input): array
{
    $kind = kindOf($input['kind'] ?? 'schedule');
    $code = clip((string) ($input['code'] ?? ''), 20);
    if ($kind === 'schedule') {
        $code = strtoupper($code);
    }
    return [
        'kind' => $kind,
        'code' => $code,
        'name' => clip((string) ($input['name'] ?? ''), 80),
        'description' => clip((string) ($input['description'] ?? ''), 255),
        'rx_required' => flag($input['rx_required'] ?? $input['rxRequired'] ?? 0),
        'register_required' => flag($input['register_required'] ?? $input['registerRequired'] ?? 0),
        'status' => statusOf($input['status'] ?? 'Active'),
        'sort_order' => max(0, (int) ($input['sort_order'] ?? $input['sortOrder'] ?? 100)),
    ];
}

function validateProfile(array $profile, int $excludeId = 0): void
{
    if ($profile['code'] === '') {
        Json::error('Code is required.', 422);
    }
    if ($profile['name'] === '') {
        Json::error('Name is required.', 422);
    }
    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9 .&+\-\/]{0,19}$/', $profile['code'])) {
        Json::error('Code can use letters, numbers and . & + - / only.', 422);
    }
    if (codeTaken($profile['kind'], $profile['code'], $excludeId)) {
        Json::error('That code is already used for this ' . $profile['kind'] . '.', 422);
    }
}

function missingTable(Throwable $e): void
{
    $message = $e->getMessage();
    if (stripos($message, 'schedule_classes') !== false || stripos($message, "doesn't exist") !== false) {
        Json::error('Schedule / Class table is not installed. Run database/migrations/2026_09_28_schedule_classes.sql.', 503);
    }
    Json::error('Could not save the schedule or class.', 500);
}

function syncMedicineSchedule(string $from, string $to, int $id): void
{
    if ($from === '' || sameText($from, $to)) {
        if ($id > 0 && hasColumn('medicines', 'schedule_id') && hasColumn('medicines', 'schedule')) {
            queryRows('UPDATE medicines SET schedule_id = ' . $id . ' WHERE schedule = ' . sqlStr($to) . ' AND (schedule_id IS NULL OR schedule_id = 0)');
        }
        return;
    }
    if (hasColumn('medicines', 'schedule')) {
        $set = 'schedule = ' . sqlStr($to);
        if (hasColumn('medicines', 'schedule_id')) {
            $set .= ', schedule_id = ' . $id;
        }
        queryRows('UPDATE medicines SET ' . $set . ' WHERE schedule = ' . sqlStr($from));
    } elseif (hasColumn('medicines', 'schedule_id')) {
        queryRows('UPDATE medicines SET schedule_id = ' . $id . ' WHERE schedule_id = ' . $id);
    }
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $rows = listRows();
    } catch (Throwable $e) {
        Json::error('Schedule / Class table is not installed. Run database/migrations/2026_09_28_schedule_classes.sql.', 503);
    }
    $id = (int) ($_GET['id'] ?? 0);
    if ($id > 0) {
        foreach ($rows as $row) {
            if ((int) $row['id'] === $id) {
                $row['medicines'] = medicineDetails($row);
                Json::ok(['data' => $row]);
            }
        }
        Json::error('Schedule or class not found.', 404);
    }
    Json::ok(['data' => $rows]);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $profile = profileFromInput($input);
    validateProfile($profile);
    try {
        queryRows('INSERT INTO schedule_classes (kind, code, name, description, rx_required, register_required, status, sort_order)
            VALUES (' . sqlStr($profile['kind']) . ', '
            . sqlStr($profile['code']) . ', '
            . sqlStr($profile['name']) . ', '
            . sqlNull($profile['description']) . ', '
            . $profile['rx_required'] . ', '
            . $profile['register_required'] . ', '
            . sqlStr($profile['status']) . ', '
            . $profile['sort_order'] . ')');
        $found = queryRows(
            'SELECT id FROM schedule_classes WHERE kind = ' . sqlStr($profile['kind']) .
            ' AND code = ' . sqlStr($profile['code']) . ' ORDER BY id DESC LIMIT 1'
        );
        $id = (int) ($found[0]['id'] ?? 0);
        if (!$id) {
            Json::error('Could not save the schedule or class.', 500);
        }
        if ($profile['kind'] === 'schedule') {
            syncMedicineSchedule($profile['code'], $profile['code'], $id);
        }
    } catch (Throwable $e) {
        missingTable($e);
    }
    if (class_exists('Audit')) {
        Audit::log('SCHEDULE_CLASS_CREATE', $profile['kind'] . ' ' . $profile['code']);
    }
    Json::ok(['id' => $id, 'code' => $profile['code'], 'kind' => $profile['kind']]);
}

if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int) ($input['id'] ?? $_GET['id'] ?? 0);
    $current = $id ? findById($id) : null;
    if (!$current) {
        Json::error('Schedule or class not found.', 404);
    }
    $profile = profileFromInput($input);
    validateProfile($profile, $id);
    try {
        queryRows('UPDATE schedule_classes SET
            kind = ' . sqlStr($profile['kind']) . ',
            code = ' . sqlStr($profile['code']) . ',
            name = ' . sqlStr($profile['name']) . ',
            description = ' . sqlNull($profile['description']) . ',
            rx_required = ' . $profile['rx_required'] . ',
            register_required = ' . $profile['register_required'] . ',
            status = ' . sqlStr($profile['status']) . ',
            sort_order = ' . $profile['sort_order'] . '
            WHERE id = ' . $id);
        if ($current['kind'] === 'schedule' && $profile['kind'] === 'schedule') {
            syncMedicineSchedule((string) $current['code'], $profile['code'], $id);
        }
    } catch (Throwable $e) {
        missingTable($e);
    }
    if (class_exists('Audit')) {
        Audit::log('SCHEDULE_CLASS_UPDATE', $profile['kind'] . ' ' . $profile['code']);
    }
    Json::ok(['id' => $id, 'code' => $profile['code'], 'kind' => $profile['kind']]);
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    $row = $id ? findById($id) : null;
    if (!$row) {
        Json::error('Schedule or class not found.', 404);
    }
    if ((int) $row['medicine_count'] > 0) {
        Json::error($row['code'] . ' is assigned to one or more medicines and cannot be deleted. Mark it inactive, or move those medicines first.', 409);
    }
    try {
        queryRows('DELETE FROM schedule_classes WHERE id = ' . $id);
    } catch (Throwable $e) {
        Json::error('This row is still linked and cannot be deleted.', 409);
    }
    if (class_exists('Audit')) {
        Audit::log('SCHEDULE_CLASS_DELETE', $row['kind'] . ' ' . $row['code']);
    }
    Json::ok();
}

Json::error('Method not allowed.', 405);
