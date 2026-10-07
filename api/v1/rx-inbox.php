<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';
require dirname(__DIR__, 2) . '/models/Manufacturer.php';

/**
 * Rx inbox — phone-first prescription intake ("Scan & Send Rx").
 *
 * Customer flow (PUBLIC, no login — they are not users):
 *   POST {image: "data:image/jpeg;base64,…", phone: "98…"} → stores image + inbox row
 *
 * Counter flow (AUTHENTICATED):
 *   GET  ?action=pending            → unclaimed inbox rows (bell + poll feed)
 *   POST {action:'claim'|'dismiss'} → settle a row after attach / bin
 *
 * The table self-creates on first use (same pattern as sale_audit) — no migration.
 * Image filenames are random 24-hex: unguessable URLs, because a publicly served
 * directory cannot authenticate. Keep folder names honest about that trade.
 */

function qrInbox(string $sql): array
{
    $rows = Manufacturer::query($sql);
    return is_array($rows) ? $rows : [];
}

try {
    qrInbox('CREATE TABLE IF NOT EXISTS rx_inbox (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            sender_phone VARCHAR(20) NOT NULL DEFAULT \'\',
            image_path VARCHAR(190) NOT NULL,
            note VARCHAR(190) NOT NULL DEFAULT \'\',
            claimed TINYINT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ix_claimed (claimed)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
} catch (Throwable $e) { /* best-effort */ }

$esc = fn(string $s) => "'" . addslashes($s) . "'";
$method = $_SERVER['REQUEST_METHOD'];

/* ---------- Counter reads ---------- */
if ($method === 'GET') {
    if (!Auth::check()) Json::error('Not authenticated.', 401);
    $action = strtolower(trim((string) ($_GET['action'] ?? 'pending')));
    if ($action !== 'pending') Json::error('Unknown action.', 400);
    try {
        $rows = qrInbox('SELECT id, sender_phone, image_path, note, created_at
                           FROM rx_inbox WHERE claimed = 0 ORDER BY id DESC LIMIT 20');
        $latest = 0;
        foreach ($rows as $r) { $latest = max($latest, (int) $r['id']); }
        Json::ok(['data' => ['inbox' => $rows, 'latest' => $latest]]);
    } catch (Throwable $e) {
        Json::error('Inbox read failed.', 500);
    }
}

if ($method !== 'POST') Json::error('Method not allowed.', 405);

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = strtolower(trim((string) ($input['action'] ?? $_GET['action'] ?? '')));

/* ---------- Public upload (customer's phone — NOT logged in) ---------- */
if ($action === '' || $action === 'upload') {
    $data = (string) ($input['image'] ?? '');
    if (!preg_match('#^data:image/(jpeg|jpg|png|webp);base64,(.+)$#', $data, $m)) {
        Json::error('Send the prescription photo as JPEG/PNG/WebP.', 422);
    }
    $bin = base64_decode($m[2], true);
    if ($bin === false || strlen($bin) < 2000) Json::error('That image is empty or unreadable — retake the photo.', 422);
    if (strlen($bin) > 6 * 1024 * 1024) Json::error('Photo larger than 6 MB — retake it a little closer/zoomed.', 422);

    $dir = dirname(__DIR__, 2) . '/rx-inbox-store';
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0775, true)) Json::error('Storage folder missing — ask the installer to create /rx-inbox-store.', 500);
    }
    $name = bin2hex(random_bytes(12)) . ($m[1] === 'png' ? '.png' : ($m[1] === 'webp' ? '.webp' : '.jpg'));
    if (file_put_contents($dir . '/' . $name, $bin) === false) {
        Json::error('Could not store the photo — check server permissions.', 500);
    }
    $rel = 'rx-inbox-store/' . $name;
    $phone = substr(preg_replace('/\D+/', '', (string) ($input['phone'] ?? '')), 0, 15);
    $note = substr(trim((string) ($input['note'] ?? '')), 0, 190);
    try {
        qrInbox('INSERT INTO rx_inbox (sender_phone, image_path, note, claimed) VALUES ('
            . $esc($phone) . ', ' . $esc($rel) . ', ' . $esc($note) . ', 0)');
        $found = qrInbox('SELECT id FROM rx_inbox WHERE image_path = ' . $esc($rel) . ' ORDER BY id DESC LIMIT 1');
        Json::ok(['data' => ['received' => true, 'id' => (int) ($found[0]['id'] ?? 0)]]);
    } catch (Throwable $e) {
        Json::error('Inbox write failed.', 500);
    }
}

/* ---------- Counter settles a row ---------- */
if (!Auth::check()) Json::error('Not authenticated.', 401);

$id = (int) ($input['id'] ?? 0);
if (!$id) Json::error('Missing inbox id.', 422);

if ($action === 'claim' || $action === 'dismiss') {
    $val = $action === 'claim' ? 1 : 2;
    try {
        qrInbox('UPDATE rx_inbox SET claimed = ' . $val . ' WHERE id = ' . $id);
        Json::ok(['data' => ['id' => $id, 'claimed' => $val]]);
    } catch (Throwable $e) {
        Json::error('Inbox update failed.', 500);
    }
}

Json::error('Unknown action.', 400);
