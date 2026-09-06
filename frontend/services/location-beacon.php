<?php
/**
 * 情侣实时位置信标
 * POST：已登录用户上报高德定位坐标（按账号角色 user1/user2 存储）
 * GET ：读取双方最新上报坐标（公开展示，与首页头像地名一致）
 * 存储：services/runtime/user-geo.json（临时数据，含时间戳，过期自动视为无效）
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

const GEO_MAX_AGE_SEC = 43200; // 12 小时内的上报视为有效

function geoStoreFile() {
    return __DIR__ . '/runtime/user-geo.json';
}

function geoLoadAll() {
    $file = geoStoreFile();
    if (!is_file($file)) return ['user1' => null, 'user2' => null];
    $raw = @file_get_contents($file);
    $data = $raw ? json_decode($raw, true) : null;
    if (!is_array($data)) $data = [];
    return [
        'user1' => isset($data['user1']) && is_array($data['user1']) ? $data['user1'] : null,
        'user2' => isset($data['user2']) && is_array($data['user2']) ? $data['user2'] : null,
    ];
}

function geoSaveRole($role, $entry) {
    $dir = __DIR__ . '/runtime';
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    if (!is_dir($dir)) return false;
    $all = geoLoadAll();
    $all[$role] = $entry;
    return @file_put_contents(geoStoreFile(), json_encode($all, JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
}

function geoOutEntry($entry) {
    if (!$entry || !isset($entry['lat'], $entry['lng'], $entry['ts'])) return null;
    $age = time() - intval($entry['ts']);
    return [
        'lat' => round(floatval($entry['lat']), 6),
        'lng' => round(floatval($entry['lng']), 6),
        'ts' => intval($entry['ts']),
        'age' => max(0, $age),
        'fresh' => $age <= GEO_MAX_AGE_SEC,
        'source' => $entry['source'] ?? 'amap',
    ];
}

try {
    $rootPath = dirname(__DIR__, 2) . '/backend/app';
    if (!is_file($rootPath . '/config/database.php') || !is_file($rootPath . '/.installed')) {
        echo json_encode(['code' => 503, 'message' => 'not_installed']);
        exit;
    }

    require_once $rootPath . '/config/config.php';
    require_once $rootPath . '/core/Database.php';
    require_once $rootPath . '/core/Auth.php';

    // 高德 IP 定位：解析来访 IP 的城市级位置（真实定位失败时的兜底）
    if (($_GET['action'] ?? '') === 'ip') {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');
        $ip = trim(explode(',', (string)$ip)[0]);
        // 内网/回环地址高德解析不出，直接返回失败
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            echo json_encode(['code' => 404, 'message' => 'ip_not_locatable']);
            exit;
        }
        $key = '';
        try {
            $db = Database::getInstance();
            $row = $db->fetch("SELECT value FROM settings WHERE `key`='amap_weather_key'");
            $key = ($row && !empty($row['value'])) ? $row['value'] : '';
        } catch (Throwable $e) {}
        if ($key === '') {
            echo json_encode(['code' => 404, 'message' => 'no_key']);
            exit;
        }
        $url = 'https://restapi.amap.com/v3/ip?key=' . $key . '&ip=' . urlencode($ip);
        $ctx = @stream_context_create(['http' => ['timeout' => 6]]);
        $raw = @file_get_contents($url, false, $ctx);
        $data = $raw ? json_decode($raw, true) : null;
        $adcode = (is_array($data) && ($data['status'] ?? '') === '1' && !empty($data['adcode'])) ? $data['adcode'] : '';
        if ($adcode === '' || empty($data['rectangle'])) {
            echo json_encode(['code' => 404, 'message' => 'ip_not_located']);
            exit;
        }
        // rectangle "lng1,lat1;lng2,lat2" 取中点作为城市级坐标
        $pts = explode(';', $data['rectangle']);
        $p1 = explode(',', $pts[0]);
        $p2 = explode(',', $pts[1] ?? $pts[0]);
        $lng = (floatval($p1[0]) + floatval($p2[0])) / 2;
        $lat = (floatval($p1[1]) + floatval($p2[1])) / 2;
        echo json_encode([
            'code' => 200,
            'data' => [
                'lng' => $lng,
                'lat' => $lat,
                'province' => is_array($data['province'] ?? null) ? '' : (string)($data['province'] ?? ''),
                'city' => is_array($data['city'] ?? null) ? '' : (string)($data['city'] ?? ''),
                'adcode' => $adcode,
                'source' => 'ip',
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // 上报：必须登录，身份按会话角色（user1/user2）区分
        $auth = new Auth();
        if (!$auth->isLoggedIn() || empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['code' => 401, 'message' => 'unauthorized']);
            exit;
        }

        $role = $_SESSION['role'] ?? null;
        $userId = intval($_SESSION['user_id']);
        if (!in_array($role, ['user1', 'user2'], true)) {
            // 会话缺角色时按 user_id 兜底查一次
            try {
                $db = Database::getInstance();
                $u = $db->fetch("SELECT role FROM users WHERE id = :id AND status = 'active'", ['id' => $userId]);
                $role = ($u && in_array($u['role'], ['user1', 'user2'], true)) ? $u['role'] : null;
            } catch (Throwable $e) {}
        }
        if (!$role) {
            http_response_code(403);
            echo json_encode(['code' => 403, 'message' => 'invalid_role']);
            exit;
        }

        $lat = floatval($_POST['lat'] ?? $_GET['lat'] ?? 0);
        $lng = floatval($_POST['lng'] ?? $_GET['lng'] ?? 0);
        $acc = floatval($_POST['acc'] ?? $_GET['acc'] ?? 0);
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 || ($lat === 0.0 && $lng === 0.0)) {
            http_response_code(400);
            echo json_encode(['code' => 400, 'message' => 'invalid_coords']);
            exit;
        }

        $ok = geoSaveRole($role, [
            'lat' => $lat,
            'lng' => $lng,
            'acc' => $acc,
            'ts' => time(),
            'source' => 'amap',
        ]);
        echo json_encode(['code' => $ok ? 200 : 500, 'message' => $ok ? 'ok' : 'store_failed']);
        exit;
    }

    // GET：返回双方最新位置
    $all = geoLoadAll();
    echo json_encode([
        'code' => 200,
        'data' => [
            'user1' => geoOutEntry($all['user1']),
            'user2' => geoOutEntry($all['user2']),
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['code' => 500, 'message' => 'server_error']);
}
