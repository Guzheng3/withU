<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/withu.php';

migrate_schema_if_needed();

$auth = new Auth();
$db = Database::getInstance();
$action = (string)($_GET['action'] ?? $_POST['action'] ?? 'bootstrap');

function timetable_require_couple_user(Auth $auth): array {
    if (!$auth->isLoggedIn()) {
        withu_json_response(['success' => false, 'message' => 'Login required'], 401);
    }
    $user = $auth->getCurrentUser();
    if (!$user || !in_array((string)($user['role'] ?? ''), ['user1', 'user2'], true)) {
        withu_json_response(['success' => false, 'message' => 'Couple account required'], 403);
    }
    return $user;
}

function timetable_public_user(?array $user): ?array {
    if (!$user) {
        return null;
    }
    return [
        'id' => (int)($user['id'] ?? 0),
        'username' => (string)($user['username'] ?? ''),
        'nickname' => (string)($user['nickname'] ?? ''),
        'role' => (string)($user['role'] ?? ''),
        'avatar' => ($user['avatar'] ?? null) !== null ? (string)$user['avatar'] : null,
    ];
}

function timetable_content(Database $db, int $userId): ?array {
    $row = $db->fetch(
        'SELECT content, content_hash, updated_at FROM timetables WHERE user_id = :user_id LIMIT 1',
        ['user_id' => $userId]
    );
    if (!$row) {
        return null;
    }

    $decoded = json_decode((string)$row['content'], true);
    if (!is_array($decoded)) {
        return null;
    }

    return [
        'content' => $decoded,
        'content_hash' => (string)$row['content_hash'],
        'updated_at' => (string)$row['updated_at'],
    ];
}

function timetable_payload(Auth $auth, Database $db): array {
    $user = $auth->getCurrentUser();
    $partner = null;
    $mine = null;
    $partnerTimetable = null;

    if ($user && in_array((string)($user['role'] ?? ''), ['user1', 'user2'], true)) {
        $partner = $auth->getPartner();
        $mine = timetable_content($db, (int)$user['id']);
        if ($partner) {
            $partnerTimetable = timetable_content($db, (int)$partner['id']);
        }
    }

    return [
        'success' => true,
        'server_time' => withu_now(),
        'csrf_token' => csrf_token(),
        'logged_in' => (bool)$user,
        'user' => timetable_public_user($user),
        'partner' => timetable_public_user($partner),
        'timetable' => $mine,
        'partner_timetable' => $partnerTimetable,
    ];
}

function timetable_respond(Auth $auth, Database $db): void {
    try {
        withu_json_response(timetable_payload($auth, $db));
    } catch (Throwable $e) {
        error_log('Timetable API database error: ' . $e->getMessage());
        withu_json_response(['success' => false, 'message' => 'Timetable temporarily unavailable'], 500);
    }
}

function timetable_partner_payload(Auth $auth, Database $db): array {
    $partner = $auth->getPartner();

    return [
        'success' => true,
        'partner' => timetable_public_user($partner),
        'partner_timetable' => $partner
            ? timetable_content($db, (int)$partner['id'])
            : null,
    ];
}

function timetable_partner_respond(Auth $auth, Database $db): void {
    try {
        withu_json_response(timetable_partner_payload($auth, $db));
    } catch (Throwable $e) {
        error_log('Timetable API partner read error: ' . $e->getMessage());
        withu_json_response(['success' => false, 'message' => 'Timetable temporarily unavailable'], 500);
    }
}

if ($action === 'login') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        withu_json_response(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    $body = withu_json_body();
    $username = trim((string)($body['username'] ?? ''));
    $password = (string)($body['password'] ?? '');
    if ($username === '' || $password === '') {
        withu_json_response(['success' => false, 'message' => 'Username and password required'], 400);
    }
    if (!$auth->login($username, $password)) {
        withu_json_response(['success' => false, 'message' => 'Invalid username or password'], 401);
    }
    timetable_respond($auth, $db);
}

if ($action === 'logout') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        withu_json_response(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    $body = withu_json_body();
    withu_require_json_csrf($body);
    $auth->logout();
    withu_json_response(['success' => true]);
}

if ($action === 'save') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        withu_json_response(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    $user = timetable_require_couple_user($auth);
    $body = withu_json_body();
    withu_require_json_csrf($body);

    $rawContent = $body['content'] ?? null;
    if (is_string($rawContent)) {
        $decoded = json_decode($rawContent, true);
    } else {
        $decoded = $rawContent;
    }
    if (!is_array($decoded)) {
        withu_json_response(['success' => false, 'message' => 'Invalid timetable content'], 400);
    }

    $content = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($content === false || strlen($content) > 2097152) {
        withu_json_response(['success' => false, 'message' => 'Timetable content too large'], 413);
    }

    $contentHash = hash('sha256', $content);
    $db->query(
        'INSERT INTO timetables (user_id, content, content_hash, updated_at)
         VALUES (:user_id, :content, :content_hash, :updated_at)
         ON DUPLICATE KEY UPDATE
            content = VALUES(content),
            content_hash = VALUES(content_hash),
            updated_at = VALUES(updated_at)',
        [
            'user_id' => (int)$user['id'],
            'content' => $content,
            'content_hash' => $contentHash,
            'updated_at' => withu_now(),
        ]
    );

    timetable_respond($auth, $db);
}

if ($action === 'save_settings') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        withu_json_response(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    $user = timetable_require_couple_user($auth);
    $body = withu_json_body();
    withu_require_json_csrf($body);

    $rawContent = $body['content'] ?? null;
    if (is_string($rawContent)) {
        $decoded = json_decode($rawContent, true);
    } else {
        $decoded = $rawContent;
    }
    if (!is_array($decoded)) {
        withu_json_response(['success' => false, 'message' => 'Invalid settings content'], 400);
    }

    $content = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($content === false || strlen($content) > 1048576) {
        withu_json_response(['success' => false, 'message' => 'Settings content too large'], 413);
    }

    $contentHash = hash('sha256', $content);
    $db->query(
        'INSERT INTO user_settings (user_id, content, content_hash, updated_at)
         VALUES (:user_id, :content, :content_hash, :updated_at)
         ON DUPLICATE KEY UPDATE
            content = VALUES(content),
            content_hash = VALUES(content_hash),
            updated_at = VALUES(updated_at)',
        [
            'user_id' => (int)$user['id'],
            'content' => $content,
            'content_hash' => $contentHash,
            'updated_at' => withu_now(),
        ]
    );

    withu_json_response(['success' => true]);
}

if ($action === 'partner') {
    $user = timetable_require_couple_user($auth);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    timetable_partner_respond($auth, $db);
}

if ($action === 'bootstrap') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    timetable_respond($auth, $db);
}

withu_json_response(['success' => false, 'message' => 'Timetable API action not found'], 404);
