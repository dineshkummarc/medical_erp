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
 * Quick Refill lookup — GET /api/v1/patient-lookup.php?mobile=9876543210
 * Finds a retail customer by phone and returns profile, last visit, prescribing
 * doctor and the previous prescription (items of the latest sale) for one-click
 * refill in the POS side panel.
 */

function qr(string $sql): array
{
    $rows = Manufacturer::query($sql);
    return is_array($rows) ? $rows : [];
}

function qStr(string $value): string
{
    return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $value) . "'";
}

function pluralUnit(string $unit, int $qty): string
{
    $nice = trim($unit) === '' ? 'pack' : strtolower(trim($unit));
    if (preg_match('/^(g|mg|mcg|ml|iu|kg)$/i', $nice)) {
        return $nice;
    }
    if ($qty === 1) {
        $one = preg_replace('/s$/', '', $nice);
        return $one === '' ? $nice : $one;
    }
    return preg_match('/s$/i', $nice) ? $nice : $nice . 's';
}

$mobile = preg_replace('/[^0-9]/', '', (string) ($_GET['mobile'] ?? ''));
if (strlen($mobile) < 6) {
    Json::error('Enter at least 6 digits of the customer mobile number.', 422);
}

$needle = qStr('%' . $mobile);
$customers = qr(
    "SELECT id, name, phone FROM customers
      WHERE phone IS NOT NULL AND REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') LIKE $needle
      ORDER BY id DESC LIMIT 1"
);

if (!$customers) {
    Json::ok(['data' => ['found' => false, 'customer' => null]]);
}

$c = $customers[0];
$customerId = (int) $c['id'];

$sales = qr(
    "SELECT s.id, s.sale_date, COALESCE(d.name, '') AS doctor_name
       FROM sales s LEFT JOIN doctors d ON d.id = s.doctor_id
      WHERE s.customer_id = $customerId
      ORDER BY s.sale_date DESC, s.id DESC LIMIT 20"
);

$lastSale = $sales[0] ?? null;
$lastVisit = $lastSale ? (string) $lastSale['sale_date'] : null;
$daysSince = $lastVisit !== null ? (int) floor((strtotime(date('Y-m-d')) - strtotime($lastVisit)) / 86400) : null;
$doctorName = $lastSale ? (string) $lastSale['doctor_name'] : '';

$items = [];
if ($lastSale) {
    $saleId = (int) $lastSale['id'];
    $rows = qr(
        "SELECT si.medicine_id, COALESCE(m.name, 'Removed medicine') AS medicine_name,
                si.qty, si.unit_sold, si.pack_qty_at_sale
           FROM sale_items si LEFT JOIN medicines m ON m.id = si.medicine_id
          WHERE si.sale_id = $saleId ORDER BY si.id"
    );
    $units = [];
    if ($rows) {
        $list = implode(',', array_map(fn($r) => (int) $r['medicine_id'], $rows));
        foreach (qr("SELECT id, unit, sub_unit, pack_qty FROM medicines WHERE id IN ($list)") as $m) {
            $units[(int) $m['id']] = $m;
        }
    }
    foreach ($rows as $r) {
        $m = $units[(int) $r['medicine_id']] ?? null;
        $n = (int) $r['qty'];
        $isLoose = ($r['unit_sold'] ?? 'pack') === 'loose';
        $unitName = $isLoose
            ? strtolower(trim((string) ($m['sub_unit'] ?? 'piece'))) ?: 'piece'
            : strtolower(trim((string) ($m['unit'] ?? 'pack'))) ?: 'pack';
        $items[] = [
            'medId' => (int) $r['medicine_id'],
            'name' => (string) $r['medicine_name'],
            'qty' => $n,
            'unit' => $isLoose ? 'loose' : 'pack',
            'qtyLabel' => $n . ' ' . pluralUnit($unitName, $n),
            'packQty' => (int) ($r['pack_qty_at_sale'] ?? ($m['pack_qty'] ?? 1)),
        ];
    }
}

$name = trim((string) $c['name']);
$parts = preg_split('/\s+/', $name);
$initials = strtoupper(
    mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1)
);
if ($initials === '') $initials = 'P';

Json::ok(['data' => [
    'found' => true,
    'customer' => [
        'id' => $customerId,
        'name' => $name,
        'phone' => (string) $c['phone'],
        'initials' => $initials,
        'purchases' => count($sales),
    ],
    'lastVisit' => $lastVisit,
    'daysSince' => $daysSince,
    'doctor' => $doctorName,
    'items' => $items,
]]);
