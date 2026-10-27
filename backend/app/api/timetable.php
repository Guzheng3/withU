<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/withu.php';
require_once __DIR__ . '/../core/Weather.php';

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
    // 云端课表必须是 JSON 对象；旧版本遗留的数组内容直接当作无课表，
    // 避免客户端按 Map 解析时异常。
    if (!is_array($decoded) || array_is_list($decoded)) {
        return null;
    }

    return [
        'content' => $decoded,
        'content_hash' => (string)$row['content_hash'],
        'updated_at' => (string)$row['updated_at'],
    ];
}

function timetable_raw_content(Database $db, int $userId): ?array {
    $row = $db->fetch(
        'SELECT content, content_hash FROM timetables WHERE user_id = :user_id LIMIT 1',
        ['user_id' => $userId]
    );
    return $row ?: null;
}

function timetable_content_hash(array $decoded): string {
    $semanticContent = $decoded;
    unset($semanticContent['packageId'], $semanticContent['exportedAt']);
    $canonical = json_encode(
        $semanticContent,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    return hash('sha256', $canonical === false ? '' : $canonical);
}

function timetable_row_content_hash(?array $row): ?string {
    if (!$row) {
        return null;
    }

    $rawContent = $row['content'] ?? null;
    if (!is_string($rawContent) || $rawContent === '') {
        return null;
    }

    $decoded = json_decode($rawContent, true);
    if (is_array($decoded)) {
        return timetable_content_hash($decoded);
    }

    $storedHash = $row['content_hash'] ?? null;
    return is_string($storedHash) && strlen($storedHash) === 64
        ? $storedHash
        : null;
}

function timetable_upsert_content(
    Database $db,
    int $userId,
    string $content,
    string $contentHash
): void {
    $db->query(
        'INSERT INTO timetables (user_id, content, content_hash, updated_at)
         VALUES (:user_id, :content, :content_hash, :updated_at)
         ON DUPLICATE KEY UPDATE
            content = VALUES(content),
            content_hash = VALUES(content_hash),
            updated_at = VALUES(updated_at)',
        [
            'user_id' => $userId,
            'content' => $content,
            'content_hash' => $contentHash,
            'updated_at' => withu_now(),
        ]
    );
}

function timetable_capture_history(
    Database $db,
    int $userId,
    ?array $currentRow,
    string $changeType
): void {
    $rawContent = $currentRow['content'] ?? null;
    $content = $rawContent === null ? null : (string)$rawContent;
    $contentHash = timetable_row_content_hash($currentRow) ??
        hash('sha256', (string)($content ?? 'null'));

    $decoded = $content === null ? null : json_decode($content, true);
    $package = is_array($decoded) ? $decoded : [];
    $settings = is_array($package['settings'] ?? null) ? $package['settings'] : [];
    // 只接受标量：异常客户端可能把字段写成数组，直接强转会触发
    // “Array to string conversion” 警告并污染 JSON 响应。
    $profileName = $package['profileName'] ?? '';
    $profileName = is_scalar($profileName) ? (string)$profileName : '';
    $semesterStart = $settings['semesterStartDate'] ?? '';
    $semesterStart = is_scalar($semesterStart) ? (string)$semesterStart : '';

    $db->insert('timetable_history', [
        'user_id' => $userId,
        'content' => $content,
        'content_hash' => (string)$contentHash,
        'change_type' => substr($changeType, 0, 32),
        'profile_name' => substr($profileName, 0, 190),
        'course_count' => count((array)($package['courses'] ?? [])),
        'current_week' => is_numeric($package['currentWeek'] ?? null)
            ? (int)$package['currentWeek']
            : 0,
        'semester_start_date' => $semesterStart,
        'created_at' => withu_now(),
    ]);

    $rows = $db->fetchAll(
        'SELECT id FROM timetable_history
         WHERE user_id = :user_id
         ORDER BY id DESC
         LIMIT ' . withu_timetable_history_limit(),
        ['user_id' => $userId]
    );
    if (count($rows) < withu_timetable_history_limit()) {
        return;
    }

    $oldestKeptId = min(array_map('intval', array_column($rows, 'id')));
    $db->query(
        'DELETE FROM timetable_history
         WHERE user_id = :user_id AND id < :oldest_kept_id',
        [
            'user_id' => $userId,
            'oldest_kept_id' => $oldestKeptId,
        ]
    );
}

function timetable_history_respond(Auth $auth, Database $db): void {
    try {
        $user = $auth->getCurrentUser();
        $rows = $user ? $db->fetchAll(
            'SELECT id, content_hash, change_type, profile_name, course_count,
                    current_week, semester_start_date, created_at
             FROM timetable_history
             WHERE user_id = :user_id
             ORDER BY id DESC
             LIMIT ' . withu_timetable_history_limit(),
            ['user_id' => (int)$user['id']]
        ) : [];

        $payload = [
            'success' => true,
            'csrf_token' => csrf_token(),
            'max_entries' => withu_timetable_history_limit(),
            'history' => array_map(
                function (array $row): array {
                    return [
                        'id' => (int)$row['id'],
                        'contentHash' => (string)$row['content_hash'],
                        'changeType' => (string)$row['change_type'],
                        'profileName' => (string)$row['profile_name'],
                        'courseCount' => (int)$row['course_count'],
                        'currentWeek' => (int)$row['current_week'],
                        'semesterStartDate' => (string)$row['semester_start_date'],
                        'createdAt' => (string)$row['created_at'],
                    ];
                },
                $rows
            ),
        ];
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        withu_json_response($payload);
    } catch (Throwable $e) {
        error_log('Timetable history API error: ' . $e->getMessage());
        withu_json_response(['success' => false, 'message' => 'Timetable history temporarily unavailable'], 500);
    }
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
        $payload = timetable_payload($auth, $db);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        withu_json_response($payload);
    } catch (Throwable $e) {
        error_log('Timetable API database error: ' . $e->getMessage());
        withu_json_response(['success' => false, 'message' => 'Timetable temporarily unavailable'], 500);
    }
}

function timetable_partner_payload(Auth $auth, Database $db): array {
    $partner = $auth->getPartner();

    return [
        'success' => true,
        // 客户端在会话过期后会凭 withu_device 自动恢复登录；此时旧的 CSRF token
        // 已失效。每次读取都回传当前 token，App 的周期拉取即可自动换新。
        'csrf_token' => csrf_token(),
        'logged_in' => $auth->isLoggedIn(),
        'partner' => timetable_public_user($partner),
        'partner_timetable' => $partner
            ? timetable_content($db, (int)$partner['id'])
            : null,
    ];
}

function timetable_partner_respond(Auth $auth, Database $db): void {
    try {
        $payload = timetable_partner_payload($auth, $db);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        withu_json_response($payload);
    } catch (Throwable $e) {
        error_log('Timetable API partner read error: ' . $e->getMessage());
        withu_json_response(['success' => false, 'message' => 'Timetable temporarily unavailable'], 500);
    }
}

function timetable_weather_respond(): void {
    $mode = strtolower(trim((string)($_GET['mode'] ?? 'now')));
    if (!in_array($mode, ['now', 'hourly', 'daily', 'lookup'], true)) {
        withu_json_response([
            'success' => false,
            'code' => 400,
            'error' => 'weather_invalid_params',
            'message' => '天气查询模式不正确',
            'server_time' => withu_now(),
        ], 400);
    }

    $cfg = withu_weather_config();
    if (empty($cfg['ready'])) {
        withu_json_response([
            'success' => false,
            'code' => 503,
            'error' => 'weather_not_configured',
            'message' => '天气服务尚未配置',
            'server_time' => withu_now(),
        ], 503);
    }

    $latRaw = $_GET['lat'] ?? null;
    $lngRaw = $_GET['lng'] ?? null;
    $lat = is_numeric($latRaw) ? (float)$latRaw : null;
    $lng = is_numeric($lngRaw) ? (float)$lngRaw : null;

    $options = [
        'cfg' => $cfg,
        'lang' => $_GET['lang'] ?? $cfg['lang'],
        'unit' => $_GET['unit'] ?? $cfg['unit'],
        'label' => trim((string)($_GET['label'] ?? '')),
        'days' => (int)($_GET['days'] ?? 7),
        'hours' => (int)($_GET['hours'] ?? 24),
        'number' => (int)($_GET['number'] ?? 10),
    ];

    if ($mode === 'lookup') {
        $query = trim((string)($_GET['q'] ?? ''));
        if ($query === '' && $lat !== null && $lng !== null) {
            $query = withu_weather_location_param($lat, $lng);
        }
        if ($query === '') {
            withu_json_response([
                'success' => false,
                'code' => 400,
                'error' => 'weather_invalid_params',
                'message' => '缺少查询地点',
                'server_time' => withu_now(),
            ], 400);
        }
        $result = withu_weather_lookup($query, $options);
    } elseif ($mode === 'hourly') {
        $result = withu_weather_hourly($lat, $lng, $options);
    } elseif ($mode === 'daily') {
        $result = withu_weather_daily($lat, $lng, $options);
    } else {
        $result = withu_weather_now($lat, $lng, $options);
    }

    if (empty($result['success'])) {
        $status = (int)($result['http_status'] ?? 502);
        withu_json_response([
            'success' => false,
            'code' => $status,
            'error' => (string)($result['error'] ?? 'weather_upstream_error'),
            'message' => (string)($result['message'] ?? '天气服务暂时不可用'),
            'server_time' => withu_now(),
        ], $status);
    }

    $payload = [
        'success' => true,
        'code' => 200,
        'server_time' => withu_now(),
        'source' => 'qweather',
        'cached' => (bool)($result['cached'] ?? false),
        'stale' => (bool)($result['stale'] ?? false),
        'location' => $result['location'] ?? null,
    ];
    if ($mode === 'lookup') {
        $payload['locations'] = $result['data']['locations'] ?? [];
    } else {
        $payload['data'] = $result['data'] ?? [];
    }
    if (!empty($result['updated_at'])) {
        $payload['updated_at'] = $result['updated_at'];
    }
    withu_json_response($payload);
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
    $token = (string)($body['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    // 会话过期后，App 依靠 withu_device 自动恢复登录，但本地保存的 CSRF token
    // 已经失效。退出登录是幂等操作，且会话由 HttpOnly + SameSite 的 Cookie
    // 承载，已登录时允许在没有有效 CSRF 的情况下退出，否则设备凭证无法吊销。
    if (!csrf_verify($token) && !$auth->isLoggedIn()) {
        withu_json_response(['success' => false, 'message' => '请求已过期，请刷新页面'], 400);
    }
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
    if (!is_array($decoded) || array_is_list($decoded)) {
        withu_json_response(['success' => false, 'message' => 'Invalid timetable content'], 400);
    }
    if (withu_timetable_is_full_backup($decoded)) {
        withu_json_response([
            'success' => false,
            'message' => 'Full backup is not supported; please upload a single timetable',
        ], 400);
    }

    $content = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($content === false || strlen($content) > 2097152) {
        withu_json_response(['success' => false, 'message' => 'Timetable content too large'], 413);
    }

    $contentHash = timetable_content_hash($decoded);
    $currentRow = timetable_raw_content($db, (int)$user['id']);
    if (timetable_row_content_hash($currentRow) === $contentHash) {
        timetable_respond($auth, $db);
    }

    timetable_capture_history($db, (int)$user['id'], $currentRow, 'save');
    timetable_upsert_content($db, (int)$user['id'], $content, $contentHash);

    timetable_respond($auth, $db);
}

if ($action === 'history') {
    timetable_require_couple_user($auth);
    timetable_history_respond($auth, $db);
}

if ($action === 'rollback') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        withu_json_response(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    $user = timetable_require_couple_user($auth);
    $body = withu_json_body();
    withu_require_json_csrf($body);

    $historyId = (int)($body['historyId'] ?? 0);
    if ($historyId <= 0) {
        withu_json_response(['success' => false, 'message' => 'Invalid history entry'], 400);
    }

    $historyRow = $db->fetch(
        'SELECT id, content, content_hash FROM timetable_history
         WHERE id = :id AND user_id = :user_id LIMIT 1',
        [
            'id' => $historyId,
            'user_id' => (int)$user['id'],
        ]
    );
    if (!$historyRow) {
        withu_json_response(['success' => false, 'message' => 'History entry not found'], 404);
    }

    $currentRow = timetable_raw_content($db, (int)$user['id']);
    if ($historyRow['content'] === null) {
        if ($currentRow !== null) {
            timetable_capture_history($db, (int)$user['id'], $currentRow, 'rollback');
            $db->query(
                'DELETE FROM timetables WHERE user_id = :user_id',
                ['user_id' => (int)$user['id']]
            );
        }
        timetable_respond($auth, $db);
    }

    $decoded = json_decode((string)$historyRow['content'], true);
    if (!is_array($decoded) || array_is_list($decoded)) {
        withu_json_response(['success' => false, 'message' => 'Invalid history content'], 409);
    }

    $content = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($content === false || strlen($content) > 2097152) {
        withu_json_response(['success' => false, 'message' => 'Invalid history content'], 409);
    }

    $contentHash = timetable_content_hash($decoded);
    if (timetable_row_content_hash($currentRow) !== $contentHash) {
        timetable_capture_history($db, (int)$user['id'], $currentRow, 'rollback');
        timetable_upsert_content($db, (int)$user['id'], $content, $contentHash);
    }

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
    if (!is_array($decoded) || array_is_list($decoded)) {
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
    timetable_require_couple_user($auth);
    timetable_partner_respond($auth, $db);
}

if ($action === 'bootstrap') {
    timetable_respond($auth, $db);
}

if ($action === 'weather') {
    timetable_require_couple_user($auth);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    timetable_weather_respond();
}

withu_json_response(['success' => false, 'message' => 'Timetable API action not found'], 404);
