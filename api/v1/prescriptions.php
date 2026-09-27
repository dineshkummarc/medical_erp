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
 * Recorded prescriptions. Not the sales report.
 * Tables come from database/migrations/2026_09_27_prescriptions.sql.
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

function dateOnly($value): string
{
    $text = substr((string) $value, 0, 10);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) ? $text : '';
}

function shapeRx(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'rx_no' => (string) ($row['rx_no'] ?? ''),
        'rx_date' => dateOnly($row['rx_date'] ?? ''),
        'customer_id' => isset($row['customer_id']) && $row['customer_id'] !== null ? (int) $row['customer_id'] : null,
        'patient_name' => (string) ($row['patient_name'] ?? ''),
        'doctor_id' => isset($row['doctor_id']) && $row['doctor_id'] !== null ? (int) $row['doctor_id'] : null,
        'doctor_name' => (string) ($row['doctor_name'] ?? ''),
        'specialty' => (string) ($row['specialty'] ?? ''),
        'diagnosis' => (string) ($row['diagnosis'] ?? ''),
        'status' => (string) ($row['status'] ?? 'Recorded'),
        'item_count' => (int) ($row['item_count'] ?? 0),
        'created_at' => (string) ($row['created_at'] ?? ''),
    ];
}

function shapeItem(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'prescription_id' => (int) ($row['prescription_id'] ?? 0),
        'medicine_id' => isset($row['medicine_id']) && $row['medicine_id'] !== null ? (int) $row['medicine_id'] : null,
        'medicine_name' => (string) ($row['medicine_name'] ?? ''),
        'dosage' => (string) ($row['dosage'] ?? ''),
        'frequency' => (string) ($row['frequency'] ?? ''),
        'duration' => (string) ($row['duration'] ?? ''),
        'qty' => (int) ($row['qty'] ?? 1),
        'instructions' => (string) ($row['instructions'] ?? ''),
    ];
}

function listRows(): array
{
    $sql = 'SELECT id, rx_no, rx_date, customer_id, patient_name, doctor_id, doctor_name, specialty,
                   diagnosis, status, item_count, created_at
            FROM v_prescriptions';
    try {
        return queryRows($sql);
    } catch (Throwable $e) {
        $fallback = 'SELECT p.id AS id, p.rx_no AS rx_no, p.rx_date AS rx_date, p.customer_id AS customer_id,
                p.patient_name AS patient_name, p.doctor_id AS doctor_id,
                COALESCE(d.name, \'\') AS doctor_name, COALESCE(d.specialty, \'\') AS specialty,
                COALESCE(p.diagnosis, \'\') AS diagnosis, p.status AS status, p.created_at AS created_at,
                COALESCE(it.item_count, 0) AS item_count
             FROM prescriptions p
             LEFT JOIN doctors d ON d.id = p.doctor_id
             LEFT JOIN (
               SELECT prescription_id, COUNT(*) AS item_count
               FROM prescription_items GROUP BY prescription_id
             ) it ON it.prescription_id = p.id';
        return queryRows($fallback);
    }
}

function itemRows(int $id): array
{
    return queryRows('SELECT id, prescription_id, medicine_id, medicine_name, dosage, frequency, duration, qty, instructions
        FROM prescription_items WHERE prescription_id = ' . $id . ' ORDER BY id ASC');
}

function nextRxNo(): string
{
    $rows = queryRows('SELECT rx_no FROM prescriptions ORDER BY id DESC LIMIT 1');
    $n = 1;
    if ($rows && preg_match('/(\d+)$/', (string) ($rows[0]['rx_no'] ?? ''), $m)) {
        $n = ((int) $m[1]) + 1;
    }
    return 'RX-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $rows = array_map('shapeRx', listRows());
    } catch (Throwable $e) {
        Json::error('Prescription tables are not installed. Run database/migrations/2026_09_27_prescriptions.sql.', 503);
    }
    usort($rows, function (array $a, array $b): int {
        $date = strcmp($b['rx_date'], $a['rx_date']);
        return $date !== 0 ? $date : ($b['id'] <=> $a['id']);
    });
    $id = (int) ($_GET['id'] ?? 0);
    if ($id > 0) {
        $rx = null;
        foreach ($rows as $row) {
            if ((int) $row['id'] === $id) {
                $rx = $row;
                break;
            }
        }
        if (!$rx) {
            Json::error('Prescription not found.', 404);
        }
        try {
            $items = array_map('shapeItem', itemRows($id));
        } catch (Throwable $e) {
            $items = [];
        }
        Json::ok(['data' => ['prescription' => $rx, 'items' => $items]]);
    }
    Json::ok(['data' => ['ledger' => $rows]]);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $patient = clip((string) ($input['patient_name'] ?? $input['patientName'] ?? ''), 150);
    $date = dateOnly($input['rx_date'] ?? $input['date'] ?? '');
    $diagnosis = clip((string) ($input['diagnosis'] ?? $input['notes'] ?? ''), 255);
    $doctorId = (int) ($input['doctor_id'] ?? $input['doctorId'] ?? 0);
    $customerId = (int) ($input['customer_id'] ?? $input['customerId'] ?? 0);
    $items = $input['items'] ?? [];
    if (!is_array($items)) {
        $items = [];
    }

    if ($patient === '') {
        Json::error('Patient name is required.', 422);
    }
    if ($date === '') {
        Json::error('Prescription date is required.', 422);
    }

    $lines = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $name = clip((string) ($item['medicine_name'] ?? $item['medicineName'] ?? $item['name'] ?? ''), 200);
        if ($name === '') {
            continue;
        }
        $qty = (int) ($item['qty'] ?? 1);
        if ($qty < 1) {
            $qty = 1;
        }
        if ($qty > 9999) {
            $qty = 9999;
        }
        $lines[] = [
            'medicine_id' => (int) ($item['medicine_id'] ?? $item['medicineId'] ?? 0),
            'medicine_name' => $name,
            'dosage' => clip((string) ($item['dosage'] ?? ''), 80),
            'frequency' => clip((string) ($item['frequency'] ?? ''), 80),
            'duration' => clip((string) ($item['duration'] ?? ''), 80),
            'qty' => $qty,
            'instructions' => clip((string) ($item['instructions'] ?? ''), 255),
        ];
    }
    if (!$lines) {
        Json::error('Add at least one medicine.', 422);
    }

    try {
        $rxNo = nextRxNo();
        queryRows('INSERT INTO prescriptions (rx_no, rx_date, customer_id, patient_name, doctor_id, diagnosis, status)
            VALUES (' . sqlStr($rxNo) . ', ' . sqlStr($date) . ', '
            . ($customerId > 0 ? $customerId : 'NULL') . ', '
            . sqlStr($patient) . ', '
            . ($doctorId > 0 ? $doctorId : 'NULL') . ', '
            . sqlNull($diagnosis) . ", 'Recorded')");
        $found = queryRows('SELECT id FROM prescriptions WHERE rx_no = ' . sqlStr($rxNo) . ' LIMIT 1');
        $id = (int) ($found[0]['id'] ?? 0);
        if (!$id) {
            Json::error('Could not save the prescription.', 500);
        }
        foreach ($lines as $line) {
            queryRows('INSERT INTO prescription_items (prescription_id, medicine_id, medicine_name, dosage, frequency, duration, qty, instructions)
                VALUES (' . $id . ', '
                . ($line['medicine_id'] > 0 ? $line['medicine_id'] : 'NULL') . ', '
                . sqlStr($line['medicine_name']) . ', '
                . sqlNull($line['dosage']) . ', '
                . sqlNull($line['frequency']) . ', '
                . sqlNull($line['duration']) . ', '
                . $line['qty'] . ', '
                . sqlNull($line['instructions']) . ')');
        }
    } catch (Throwable $e) {
        Json::error('Prescription tables are not installed. Run database/migrations/2026_09_27_prescriptions.sql.', 503);
    }

    Json::ok(['id' => $id, 'rx_no' => $rxNo]);
}

if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int) ($input['id'] ?? $_GET['id'] ?? 0);
    $status = trim((string) ($input['status'] ?? ''));
    $allowed = ['Recorded', 'Dispensed', 'Cancelled'];
    if (!$id) {
        Json::error('Prescription not found.', 404);
    }
    if (!in_array($status, $allowed, true)) {
        Json::error('Status must be Recorded, Dispensed, or Cancelled.', 422);
    }
    try {
        queryRows('UPDATE prescriptions SET status = ' . sqlStr($status) . ' WHERE id = ' . $id);
    } catch (Throwable $e) {
        Json::error('Prescription tables are not installed. Run database/migrations/2026_09_27_prescriptions.sql.', 503);
    }
    Json::ok(['id' => $id, 'status' => $status]);
}

Json::error('Method not allowed.', 405);
