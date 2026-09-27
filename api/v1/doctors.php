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
 * Doctors directory. Saves the Add Doctor modal.
 * Extra columns come from database/migrations/2026_09_27_doctors.sql.
 * Does not use Doctor::create, so a column whitelist cannot drop email, clinic, address or status.
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

function textLen(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function statusOf($value): string
{
    return preg_match('/^in/i', trim((string) $value)) ? 'Inactive' : 'Active';
}

function shapeDoctor(array $row): array
{
    $reg = (string) ($row['reg_no'] ?? '');
    $status = trim((string) ($row['status'] ?? ''));
    if ($status === '') {
        $status = 'Active';
    }
    return [
        'id' => (int) ($row['id'] ?? 0),
        'name' => (string) ($row['name'] ?? ''),
        'specialty' => (string) ($row['specialty'] ?? ''),
        'phone' => (string) ($row['phone'] ?? ''),
        'reg_no' => $reg,
        'regNo' => $reg,
        'email' => (string) ($row['email'] ?? ''),
        'clinic' => (string) ($row['clinic'] ?? ''),
        'address' => (string) ($row['address'] ?? ''),
        'status' => $status,
        'created_at' => (string) ($row['created_at'] ?? ''),
    ];
}

function listRows(): array
{
    $extended = 'SELECT id, name, specialty, phone, reg_no, email, clinic, address, status, created_at
        FROM doctors ORDER BY name ASC, id ASC';
    try {
        return queryRows($extended);
    } catch (Throwable $e) {
        return queryRows('SELECT id, name, specialty, phone, reg_no, created_at FROM doctors ORDER BY name ASC, id ASC');
    }
}

function findById(int $id): ?array
{
    foreach (listRows() as $row) {
        if ((int) ($row['id'] ?? 0) === $id) {
            return $row;
        }
    }
    return null;
}

function regTaken(string $reg, int $excludeId = 0): bool
{
    if ($reg === '') {
        return false;
    }
    $rows = queryRows('SELECT id FROM doctors WHERE reg_no = ' . sqlStr($reg) . ' LIMIT 1');
    if (!$rows) {
        return false;
    }
    return (int) ($rows[0]['id'] ?? 0) !== $excludeId;
}

function profileFromInput(array $input): array
{
    $name = clip((string) ($input['name'] ?? ''), 150);
    $reg = clip((string) ($input['reg_no'] ?? $input['regNo'] ?? $input['registration'] ?? ''), 50);
    $specialty = clip((string) ($input['specialty'] ?? $input['specialization'] ?? ''), 100);
    $phone = clip((string) ($input['phone'] ?? $input['mobile'] ?? ''), 32);
    $email = clip((string) ($input['email'] ?? ''), 160);
    $clinic = clip((string) ($input['clinic'] ?? $input['hospital'] ?? $input['clinic_name'] ?? ''), 150);
    $address = clip((string) ($input['address'] ?? ''), 255);
    $status = statusOf($input['status'] ?? 'Active');

    return compact('name', 'reg', 'specialty', 'phone', 'email', 'clinic', 'address', 'status');
}

function validateProfile(array $profile, int $excludeId = 0): void
{
    if ($profile['name'] === '') {
        Json::error('Doctor name is required.', 422);
    }
    if ($profile['reg'] === '') {
        Json::error('Registration number is required.', 422);
    }
    if ($profile['email'] !== '' && !filter_var($profile['email'], FILTER_VALIDATE_EMAIL)) {
        Json::error('Enter a valid email address.', 422);
    }
    if (textLen($profile['phone']) > 32) {
        Json::error('Mobile is too long.', 422);
    }
    if (regTaken($profile['reg'], $excludeId)) {
        Json::error('A doctor with this registration number already exists.', 422);
    }
}

function missingTable(Throwable $e): void
{
    $message = $e->getMessage();
    if (stripos($message, 'email') !== false || stripos($message, 'clinic') !== false || stripos($message, 'Unknown column') !== false) {
        Json::error('Doctor profile columns are not installed. Run database/migrations/2026_09_27_doctors.sql.', 503);
    }
    Json::error('Could not save the doctor.', 500);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $rows = array_map('shapeDoctor', listRows());
    } catch (Throwable $e) {
        Json::error('Doctors table is not available.', 503);
    }
    $id = (int) ($_GET['id'] ?? 0);
    if ($id > 0) {
        foreach ($rows as $row) {
            if ((int) $row['id'] === $id) {
                Json::ok(['data' => $row]);
            }
        }
        Json::error('Doctor not found.', 404);
    }
    Json::ok(['data' => $rows]);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $profile = profileFromInput($input);
    validateProfile($profile);
    try {
        queryRows('INSERT INTO doctors (name, specialty, phone, reg_no, email, clinic, address, status)
            VALUES (' . sqlStr($profile['name']) . ', '
            . sqlNull($profile['specialty']) . ', '
            . sqlNull($profile['phone']) . ', '
            . sqlStr($profile['reg']) . ', '
            . sqlNull($profile['email']) . ', '
            . sqlNull($profile['clinic']) . ', '
            . sqlNull($profile['address']) . ', '
            . sqlStr($profile['status']) . ')');
        $found = queryRows('SELECT id FROM doctors WHERE reg_no = ' . sqlStr($profile['reg']) . ' ORDER BY id DESC LIMIT 1');
        $id = (int) ($found[0]['id'] ?? 0);
        if (!$id) {
            Json::error('Could not save the doctor.', 500);
        }
    } catch (Throwable $e) {
        missingTable($e);
    }
    if (class_exists('Audit')) {
        Audit::log('DOCTOR_CREATE', $profile['name']);
    }
    Json::ok(['id' => $id, 'name' => $profile['name']]);
}

if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int) ($input['id'] ?? $_GET['id'] ?? 0);
    if (!$id || !findById($id)) {
        Json::error('Doctor not found.', 404);
    }
    $profile = profileFromInput($input);
    validateProfile($profile, $id);
    try {
        queryRows('UPDATE doctors SET
            name = ' . sqlStr($profile['name']) . ',
            specialty = ' . sqlNull($profile['specialty']) . ',
            phone = ' . sqlNull($profile['phone']) . ',
            reg_no = ' . sqlStr($profile['reg']) . ',
            email = ' . sqlNull($profile['email']) . ',
            clinic = ' . sqlNull($profile['clinic']) . ',
            address = ' . sqlNull($profile['address']) . ',
            status = ' . sqlStr($profile['status']) . '
            WHERE id = ' . $id);
    } catch (Throwable $e) {
        missingTable($e);
    }
    if (class_exists('Audit')) {
        Audit::log('DOCTOR_UPDATE', $profile['name']);
    }
    Json::ok(['id' => $id]);
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    $row = $id ? findById($id) : null;
    if (!$row) {
        Json::error('Doctor not found.', 404);
    }
    try {
        queryRows('DELETE FROM doctors WHERE id = ' . $id);
    } catch (Throwable $e) {
        Json::error('This doctor is still linked to a record and cannot be deleted. Mark them inactive instead.', 409);
    }
    if (class_exists('Audit')) {
        Audit::log('DOCTOR_DELETE', (string) ($row['name'] ?? ''));
    }
    Json::ok();
}

Json::error('Method not allowed.', 405);
