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
 * Medicine master save.
 * Writes every Add Medicine input into the existing medicines / batches tables.
 * New columns come from database/migrations/2026_09_30_medicine_form_fields.sql.
 * Uses Manufacturer::query so a Medicine model whitelist cannot drop fields.
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

function columnsOf(string $table): array
{
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }
    try {
        $rows = queryRows(
            'SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ' . sqlStr($table)
        );
    } catch (Throwable $e) {
        $rows = [];
    }
    $set = [];
    foreach ($rows as $row) {
        $name = (string) ($row['COLUMN_NAME'] ?? $row['column_name'] ?? '');
        if ($name !== '') {
            $set[$name] = $row;
        }
    }
    return $cache[$table] = $set;
}

function hasColumn(string $table, string $column): bool
{
    $cols = columnsOf($table);
    return $cols ? isset($cols[$column]) : true;
}

function missingFormColumns(): array
{
    $cols = columnsOf('medicines');
    if (!$cols) {
        return [];
    }
    $need = ['dosage_form', 'rack', 'default_discount', 'discount_type', 'generic_group', 'batch_no', 'opening_qty', 'expiry_date'];
    return array_values(array_filter($need, function ($col) use ($cols) {
        return !isset($cols[$col]);
    }));
}

function pick(array $input, array $keys, $fallback = '')
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $input)) {
            return $input[$key];
        }
    }
    return $fallback;
}

function blank($value): bool
{
    return $value === null || (is_string($value) && trim($value) === '');
}

function sqlIntOrNull($value): string
{
    if (blank($value) || !is_numeric($value)) {
        return 'NULL';
    }
    return (string) (int) $value;
}

function sqlDecOrNull($value): string
{
    if (blank($value) || !is_numeric($value)) {
        return 'NULL';
    }
    return number_format((float) $value, 2, '.', '');
}

function sqlDateOrNull($value): string
{
    $value = trim((string) $value);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', substr($value, 0, 10))) {
        return 'NULL';
    }
    return sqlStr(substr($value, 0, 10));
}

function flag($value): int
{
    if (is_bool($value)) {
        return $value ? 1 : 0;
    }
    $text = strtolower(trim((string) $value));
    return in_array($text, ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
}

function dbStatus($value): string
{
    return preg_match('/^in/i', trim((string) $value)) ? 'inactive' : 'active';
}

function uiStatus($value): string
{
    return preg_match('/^in/i', trim((string) $value)) ? 'Inactive' : 'Active';
}

function discType($value): string
{
    return strtolower(trim((string) $value)) === 'rupee' ? 'rupee' : 'percent';
}

function saveError(Throwable $e): void
{
    $msg = $e->getMessage();
    if (stripos($msg, 'Unknown column') !== false || stripos($msg, "doesn't exist") !== false) {
        Json::error('Could not save every field. Run database/migrations/2026_09_30_medicine_form_fields.sql, then save again.', 500);
    }
    if (stripos($msg, 'Duplicate') !== false && stripos($msg, 'barcode') !== false) {
        Json::error('That barcode is already used by another medicine.', 409);
    }
    Json::error('Could not save the medicine.', 500);
}

function findOrCreateName(string $table, string $name, int $max): int
{
    $name = clip($name, $max);
    if ($name === '') {
        return 0;
    }
    $rows = queryRows('SELECT id FROM `' . $table . '` WHERE name = ' . sqlStr($name) . ' LIMIT 1');
    if ($rows) {
        return (int) $rows[0]['id'];
    }
    queryRows('INSERT INTO `' . $table . '` (name) VALUES (' . sqlStr($name) . ')');
    $rows = queryRows('SELECT id FROM `' . $table . '` WHERE name = ' . sqlStr($name) . ' ORDER BY id DESC LIMIT 1');
    return (int) ($rows[0]['id'] ?? 0);
}

function scheduleId(string $code): int
{
    $code = strtoupper(clip($code, 10));
    if ($code === '' || !columnsOf('schedule_classes')) {
        return 0;
    }
    $kind = isset(columnsOf('schedule_classes')['kind']) ? " AND kind = 'schedule'" : '';
    $rows = queryRows('SELECT id FROM schedule_classes WHERE code = ' . sqlStr($code) . $kind . ' LIMIT 1');
    return (int) ($rows[0]['id'] ?? 0);
}

function firstGroupId(string $chips): int
{
    if ($chips === '' || !columnsOf('generic_groups')) {
        return 0;
    }
    $parts = preg_split('/\s*,\s*/', $chips) ?: [];
    $name = clip((string) ($parts[0] ?? ''), 150);
    if ($name === '') {
        return 0;
    }
    return findOrCreateName('generic_groups', $name, 150);
}

function assertBarcodeFree(string $barcode, int $excludeId): void
{
    if ($barcode === '' || !isset(columnsOf('medicines')['barcode'])) {
        return;
    }
    $rows = queryRows(
        'SELECT id FROM medicines WHERE barcode = ' . sqlStr($barcode)
        . ($excludeId ? ' AND id <> ' . $excludeId : '')
        . ' LIMIT 1'
    );
    if ($rows) {
        Json::error('That barcode is already used by another medicine.', 409);
    }
}

function medicineValues(array $input): array
{
    $name = clip((string) pick($input, ['name']), 200);
    $generic = clip((string) pick($input, ['generic', 'generic_name']), 200);
    $brand = clip((string) pick($input, ['brandRef', 'brand_ref', 'brand', 'brand_name']), 150);
    $composition = clip((string) pick($input, ['composition']), 255);
    $category = clip((string) pick($input, ['category', 'category_name']), 100);
    $manufacturer = clip((string) pick($input, ['manufacturer', 'manufacturer_name']), 150);
    $hsn = clip((string) pick($input, ['hsn', 'hsn_code']), 20);
    $group = clip((string) pick($input, ['genericGroup', 'generic_group', 'substitutes']), 500);
    $barcode = clip((string) pick($input, ['barcode']), 64);
    $form = clip((string) pick($input, ['form', 'dosage_form']), 40);
    $unit = clip((string) pick($input, ['unit']), 30);
    $packSize = clip((string) pick($input, ['packSize', 'pack_size']), 50);
    $schedule = strtoupper(clip((string) pick($input, ['schedule', 'schedule_class']), 10));
    $subUnit = clip((string) pick($input, ['subUnit', 'sub_unit']), 30);
    $rack = clip((string) pick($input, ['rack']), 60);
    $boxUnit = clip((string) pick($input, ['boxUnit', 'box_unit']), 30);
    $batchNo = clip((string) pick($input, ['batchNo', 'batch_no']), 50);
    $expiry = trim((string) pick($input, ['expiry', 'expiry_date']));
    $discountKind = discType(pick($input, ['discountType', 'discount_type'], 'percent'));

    $categoryId = $category !== '' ? findOrCreateName('categories', $category, 100) : 0;
    $manufacturerId = $manufacturer !== '' ? findOrCreateName('manufacturers', $manufacturer, 150) : 0;
    $groupId = firstGroupId($group);
    $schedId = scheduleId($schedule);

    $values = [
        'name' => sqlStr($name),
        'generic_name' => sqlNull($generic),
        'brand_name' => sqlStr($brand),
        'brand_ref' => sqlNull($brand),
        'composition' => sqlNull($composition),
        'category_id' => $categoryId ? (string) $categoryId : 'NULL',
        'manufacturer_id' => $manufacturerId ? (string) $manufacturerId : 'NULL',
        'hsn_code' => sqlNull($hsn),
        'gst_rate' => sqlDecOrNull(pick($input, ['gst', 'gst_rate'])),
        'unit' => sqlStr($unit !== '' ? $unit : 'Strip'),
        'pack_size' => sqlNull($packSize),
        'mrp' => sqlDecOrNull(pick($input, ['mrp'])) === 'NULL' ? '0.00' : sqlDecOrNull(pick($input, ['mrp'])),
        'purchase_rate' => sqlDecOrNull(pick($input, ['purchaseRate', 'purchase_rate'])),
        'wholesale_rate' => sqlDecOrNull(pick($input, ['wholesaleRate', 'wholesale_rate'])),
        'min_stock' => sqlIntOrNull(pick($input, ['minStock', 'min_stock'])),
        'reorder_level' => sqlIntOrNull(pick($input, ['reorderLevel', 'reorder_level'])),
        'schedule_class' => sqlNull($schedule),
        'rx_required' => (string) flag(pick($input, ['rxRequired', 'rx_required'], 0)),
        'status' => sqlStr(dbStatus(pick($input, ['status'], 'Active'))),
        'pack_qty' => blank(pick($input, ['packQty', 'pack_qty'])) ? '1' : (string) max(1, (int) pick($input, ['packQty', 'pack_qty'])),
        'sub_unit' => sqlNull($subUnit),
        'allow_loose_sale' => (string) flag(pick($input, ['allowLoose', 'allow_loose_sale'], 0)),
        'retail_rate' => sqlDecOrNull(pick($input, ['retailRate', 'retail_rate'])) === 'NULL'
            ? (sqlDecOrNull(pick($input, ['mrp'])) === 'NULL' ? '0.00' : sqlDecOrNull(pick($input, ['mrp'])))
            : sqlDecOrNull(pick($input, ['retailRate', 'retail_rate'])),
        'barcode' => sqlNull($barcode),
        'generic_group_id' => $groupId ? (string) $groupId : 'NULL',
        'expiry_alert_days' => sqlIntOrNull(pick($input, ['expiryAlertDays', 'expiry_alert_days'])),
        'box_qty' => sqlIntOrNull(pick($input, ['boxQty', 'box_qty'])),
        'box_unit' => sqlNull($boxUnit),
        'schedule_id' => $schedId ? (string) $schedId : 'NULL',
        'dosage_form' => sqlNull($form),
        'rack' => sqlNull($rack),
        'default_discount' => sqlDecOrNull(pick($input, ['defaultDiscount', 'default_discount'])),
        'discount_type' => sqlStr($discountKind),
        'generic_group' => sqlNull($group),
        'batch_no' => sqlNull($batchNo),
        'opening_qty' => sqlIntOrNull(pick($input, ['openingQty', 'opening_qty'])),
        'expiry_date' => sqlDateOrNull($expiry),
    ];
    return $values;
}

function writeMedicine(int $id, array $values): int
{
    $cols = columnsOf('medicines');
    $names = [];
    $vals = [];
    $sets = [];
    foreach ($values as $col => $sql) {
        if ($cols && !isset($cols[$col])) {
            continue;
        }
        $names[] = '`' . $col . '`';
        $vals[] = $sql;
        $sets[] = '`' . $col . '` = ' . $sql;
    }
    if (!$sets) {
        Json::error('Medicine table has no writable columns.', 500);
    }
    if ($id > 0) {
        queryRows('UPDATE medicines SET ' . implode(', ', $sets) . ' WHERE id = ' . $id);
        return $id;
    }
    queryRows('INSERT INTO medicines (' . implode(', ', $names) . ') VALUES (' . implode(', ', $vals) . ')');
    $found = queryRows('SELECT LAST_INSERT_ID() AS id');
    $newId = (int) ($found[0]['id'] ?? 0);
    if (!$newId) {
        $found = queryRows('SELECT id FROM medicines WHERE name = ' . $values['name'] . ' ORDER BY id DESC LIMIT 1');
        $newId = (int) ($found[0]['id'] ?? 0);
    }
    return $newId;
}

function dateText($value): string
{
    $value = trim((string) $value);
    return preg_match('/^\d{4}-\d{2}-\d{2}/', $value) ? substr($value, 0, 10) : '';
}

function shapeBatch(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'medId' => (int) ($row['medicine_id'] ?? 0),
        'batchNo' => (string) ($row['batch_no'] ?? ''),
        'purchaseDate' => dateText($row['purchase_date'] ?? ''),
        'expiry' => dateText($row['expiry_date'] ?? ''),
        'qty' => (int) ($row['quantity'] ?? 0),
        'reserved' => (int) ($row['reserved'] ?? 0),
        'purchaseRate' => isset($row['purchase_rate']) && $row['purchase_rate'] !== null ? (float) $row['purchase_rate'] : 0,
        'mrp' => isset($row['mrp']) && $row['mrp'] !== null ? (float) $row['mrp'] : 0,
        'looseQty' => (int) ($row['loose_qty'] ?? 0),
    ];
}

function numOrBlank($value)
{
    if ($value === null || $value === '') {
        return '';
    }
    return is_numeric($value) ? 0 + $value : '';
}

function shapeMedicine(array $row): array
{
    $group = (string) ($row['generic_group'] ?? '');
    if ($group === '' && !empty($row['group_name'])) {
        $group = (string) $row['group_name'];
    }
    $brand = (string) ($row['brand_ref'] ?? '');
    if ($brand === '') {
        $brand = (string) ($row['brand_name'] ?? '');
    }
    $expiry = dateText($row['expiry_date'] ?? '');
    $batchNo = (string) ($row['batch_no'] ?? '');
    $out = [
        'id' => (int) ($row['id'] ?? 0),
        'name' => (string) ($row['name'] ?? ''),
        'generic' => (string) ($row['generic_name'] ?? ''),
        'brandRef' => $brand,
        'composition' => (string) ($row['composition'] ?? ''),
        'category' => (string) ($row['category_name'] ?? ''),
        'manufacturer' => (string) ($row['manufacturer_name'] ?? ''),
        'hsn' => (string) ($row['hsn_code'] ?? ''),
        'gst' => numOrBlank($row['gst_rate'] ?? null),
        'unit' => (string) ($row['unit'] ?? ''),
        'packSize' => (string) ($row['pack_size'] ?? ''),
        'packQty' => isset($row['pack_qty']) ? (int) $row['pack_qty'] : 1,
        'subUnit' => (string) ($row['sub_unit'] ?? ''),
        'allowLoose' => !empty($row['allow_loose_sale']),
        'mrp' => isset($row['mrp']) ? (float) $row['mrp'] : 0,
        'purchaseRate' => numOrBlank($row['purchase_rate'] ?? null),
        'retailRate' => isset($row['retail_rate']) && $row['retail_rate'] !== null ? (float) $row['retail_rate'] : (isset($row['mrp']) ? (float) $row['mrp'] : 0),
        'wholesaleRate' => numOrBlank($row['wholesale_rate'] ?? null),
        'minStock' => numOrBlank($row['min_stock'] ?? null),
        'reorderLevel' => numOrBlank($row['reorder_level'] ?? null),
        'schedule' => (string) ($row['schedule_class'] ?? ''),
        'scheduleId' => isset($row['schedule_id']) && $row['schedule_id'] !== null ? (int) $row['schedule_id'] : '',
        'rxRequired' => !empty($row['rx_required']),
        'barcode' => (string) ($row['barcode'] ?? ''),
        'genericGroupId' => isset($row['generic_group_id']) && $row['generic_group_id'] !== null ? (int) $row['generic_group_id'] : '',
        'expiryAlertDays' => numOrBlank($row['expiry_alert_days'] ?? null),
        'boxQty' => numOrBlank($row['box_qty'] ?? null),
        'boxUnit' => (string) ($row['box_unit'] ?? ''),
        'status' => uiStatus($row['status'] ?? 'active'),
    ];
    if (array_key_exists('dosage_form', $row)) {
        $out['form'] = (string) ($row['dosage_form'] ?? '');
    }
    if (array_key_exists('rack', $row)) {
        $out['rack'] = (string) ($row['rack'] ?? '');
    }
    if (array_key_exists('default_discount', $row)) {
        $out['defaultDiscount'] = numOrBlank($row['default_discount']);
    }
    if (array_key_exists('discount_type', $row)) {
        $out['discountType'] = (string) (($row['discount_type'] ?? '') !== '' ? $row['discount_type'] : 'percent');
    }
    if (array_key_exists('generic_group', $row) || $group !== '') {
        $out['genericGroup'] = $group;
        $out['substitutes'] = $group;
    }
    if (array_key_exists('batch_no', $row)) {
        $out['batchNo'] = $batchNo;
    }
    if (array_key_exists('opening_qty', $row)) {
        $out['openingQty'] = numOrBlank($row['opening_qty']);
    }
    if (array_key_exists('expiry_date', $row)) {
        $out['expiry'] = $expiry;
    }
    return $out;
}

function medicineSelect(): string
{
    return 'SELECT m.*, c.name AS category_name, mf.name AS manufacturer_name, gg.name AS group_name
        FROM medicines m
        LEFT JOIN categories c ON c.id = m.category_id
        LEFT JOIN manufacturers mf ON mf.id = m.manufacturer_id
        LEFT JOIN generic_groups gg ON gg.id = m.generic_group_id';
}

function loadMedicine(int $id): ?array
{
    $rows = queryRows(medicineSelect() . ' WHERE m.id = ' . $id . ' LIMIT 1');
    return $rows[0] ?? null;
}

function loadBatches(): array
{
    $loose = isset(columnsOf('batches')['loose_qty']) ? 'loose_qty' : '0 AS loose_qty';
    $rows = queryRows('SELECT id, medicine_id, batch_no, purchase_date, expiry_date, quantity, reserved, purchase_rate, mrp, ' . $loose . ' FROM batches');
    return array_map('shapeBatch', $rows);
}

function openingBatchRow(int $medId, string $batchNo): ?array
{
    if ($batchNo !== '') {
        $rows = queryRows('SELECT * FROM batches WHERE medicine_id = ' . $medId . ' AND batch_no = ' . sqlStr($batchNo) . ' ORDER BY id ASC LIMIT 1');
        if ($rows) {
            return $rows[0];
        }
    }
    return null;
}

function saveOpeningBatch(int $medId, array $input, ?array $previous, bool $isNew): ?array
{
    $batchNo = clip((string) pick($input, ['batchNo', 'batch_no']), 50);
    $qtyRaw = pick($input, ['openingQty', 'opening_qty'], '');
    $hasQty = !blank($qtyRaw) && is_numeric($qtyRaw);
    $expiry = dateText(pick($input, ['expiry', 'expiry_date'], ''));
    $oldNo = clip((string) ($previous['batch_no'] ?? ''), 50);
    if ($batchNo === '' && !$hasQty && $expiry === '' && $oldNo === '') {
        return null;
    }
    if ($batchNo === '') {
        $batchNo = $oldNo !== '' ? $oldNo : ('OP' . date('ymd') . $medId);
        if (isset(columnsOf('medicines')['batch_no'])) {
            queryRows('UPDATE medicines SET batch_no = ' . sqlStr($batchNo) . ' WHERE id = ' . $medId);
        }
    }
    $existing = openingBatchRow($medId, $oldNo !== '' ? $oldNo : $batchNo);
    if (!$existing && $oldNo !== '' && $oldNo !== $batchNo) {
        $existing = openingBatchRow($medId, $batchNo);
    }
    $rate = sqlDecOrNull(pick($input, ['purchaseRate', 'purchase_rate']));
    $mrp = sqlDecOrNull(pick($input, ['mrp']));
    if ($rate === 'NULL') {
        $rate = '0.00';
    }
    if ($mrp === 'NULL') {
        $mrp = '0.00';
    }
    $storedQty = $previous['opening_qty'] ?? null;
    $qtyChanged = $isNew || ($hasQty && (string) (int) $storedQty !== (string) (int) $qtyRaw);
    $expiryNullable = true;
    $expCols = columnsOf('batches');
    if ($expCols && isset($expCols['expiry_date'])) {
        $expiryNullable = strtoupper((string) ($expCols['expiry_date']['IS_NULLABLE'] ?? '')) === 'YES';
    }
    if ($existing) {
        $sets = ['batch_no = ' . sqlStr($batchNo), 'purchase_rate = ' . $rate, 'mrp = ' . $mrp];
        if ($qtyChanged) {
            $sets[] = 'quantity = ' . (int) $qtyRaw;
        }
        if ($expiry !== '') {
            $sets[] = 'expiry_date = ' . sqlStr($expiry);
        } elseif ($expiryNullable) {
            $sets[] = 'expiry_date = NULL';
        }
        queryRows('UPDATE batches SET ' . implode(', ', $sets) . ' WHERE id = ' . (int) $existing['id']);
        $rows = queryRows('SELECT * FROM batches WHERE id = ' . (int) $existing['id'] . ' LIMIT 1');
        return $rows ? shapeBatch($rows[0]) : null;
    }
    if ($expiry === '' && !$expiryNullable) {
        Json::error('Could not save the batch without an expiry. Run database/migrations/2026_09_30_medicine_form_fields.sql, then save again.', 500);
    }
    $qty = $hasQty ? (int) $qtyRaw : 0;
    queryRows(
        'INSERT INTO batches (medicine_id, batch_no, expiry_date, quantity, purchase_rate, mrp, purchase_date)
         VALUES (' . $medId . ', ' . sqlStr($batchNo) . ', ' . ($expiry !== '' ? sqlStr($expiry) : 'NULL') . ', '
        . $qty . ', ' . $rate . ', ' . $mrp . ', ' . sqlStr(date('Y-m-d')) . ')'
    );
    $rows = queryRows('SELECT * FROM batches WHERE medicine_id = ' . $medId . ' AND batch_no = ' . sqlStr($batchNo) . ' ORDER BY id DESC LIMIT 1');
    return $rows ? shapeBatch($rows[0]) : null;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $rows = array_map('shapeMedicine', queryRows(medicineSelect() . ' ORDER BY m.name ASC, m.id ASC'));
        $batches = loadBatches();
    } catch (Throwable $e) {
        Json::error('Medicines table is not available.', 503);
    }
    Json::ok(['data' => $rows, 'batches' => $batches]);
}

if ($method === 'POST' || $method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int) ($input['id'] ?? $_GET['id'] ?? 0);
    $isNew = $method === 'POST' || $id === 0;
    $name = clip((string) pick($input, ['name']), 200);
    if ($name === '') {
        Json::error('Medicine name is required.', 422);
    }
    $missing = missingFormColumns();
    if ($missing) {
        Json::error('Medicine table is missing ' . implode(', ', $missing) . '. Run database/migrations/2026_09_30_medicine_form_fields.sql, then save again.', 500);
    }
    $previous = null;
    if (!$isNew) {
        $previous = loadMedicine($id);
        if (!$previous) {
            Json::error('Medicine not found.', 404);
        }
    }
    $barcode = clip((string) pick($input, ['barcode']), 64);
    assertBarcodeFree($barcode, $isNew ? 0 : $id);
    $expiryText = dateText(pick($input, ['expiry', 'expiry_date'], ''));
    $hasBatchInput = !blank(pick($input, ['batchNo', 'batch_no'], ''))
        || (!blank(pick($input, ['openingQty', 'opening_qty'], '')) && is_numeric(pick($input, ['openingQty', 'opening_qty'], '')))
        || $expiryText !== '';
    $expCols = columnsOf('batches');
    if ($hasBatchInput && $expiryText === '' && $expCols && isset($expCols['expiry_date']) && strtoupper((string) ($expCols['expiry_date']['IS_NULLABLE'] ?? '')) !== 'YES') {
        Json::error('Could not save the batch without an expiry. Run database/migrations/2026_09_30_medicine_form_fields.sql, then save again.', 500);
    }
    try {
        $values = medicineValues($input);
        $savedId = writeMedicine($isNew ? 0 : $id, $values);
        if (!$savedId) {
            Json::error('Could not save the medicine.', 500);
        }
        $batch = saveOpeningBatch($savedId, $input, $previous, $isNew);
        $row = loadMedicine($savedId);
    } catch (Throwable $e) {
        saveError($e);
    }
    if (!$row) {
        Json::error('Could not save the medicine.', 500);
    }
    if (class_exists('Audit')) {
        Audit::log($isNew ? 'MEDICINE_CREATE' : 'MEDICINE_UPDATE', $name);
    }
    $medicine = shapeMedicine($row);
    if ($batch && $medicine['batchNo'] === '' && $batch['batchNo'] !== '') {
        $medicine['batchNo'] = $batch['batchNo'];
    }
    if ($batch && $medicine['expiry'] === '' && $batch['expiry'] !== '') {
        $medicine['expiry'] = $batch['expiry'];
    }
    Json::ok([
        'id' => $savedId,
        'data' => $medicine,
        'medicine' => $medicine,
        'batch' => $batch,
    ]);
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = (int) ($input['id'] ?? 0);
    }
    if (!$id || !loadMedicine($id)) {
        Json::error('Medicine not found.', 404);
    }
    try {
        $used = queryRows('SELECT id FROM sale_items WHERE medicine_id = ' . $id . ' LIMIT 1');
        if ($used) {
            Json::error('This medicine is on a sale and cannot be deleted.', 409);
        }
        $used = queryRows('SELECT id FROM purchase_items WHERE medicine_id = ' . $id . ' LIMIT 1');
        if ($used) {
            Json::error('This medicine is on a purchase and cannot be deleted.', 409);
        }
        queryRows('DELETE FROM medicines WHERE id = ' . $id);
    } catch (Throwable $e) {
        Json::error('This medicine is in use and cannot be deleted.', 409);
    }
    if (class_exists('Audit')) {
        Audit::log('MEDICINE_DELETE', (string) $id);
    }
    Json::ok(['id' => $id]);
}

Json::error('Method not allowed.', 405);
