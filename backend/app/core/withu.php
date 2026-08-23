<?php
/** SITE_NAME 兜底：config/config.php(不入库) 未定义时用默认值，避免前台致命错误 */
if (!defined('SITE_NAME')) {
    define('SITE_NAME', '我们的小情侣网站');
}
/** withU 共享业务辅助函数。 */

// 厂长资源(cz / 4kcz.com) 总开关：默认 false=暂时屏蔽（部署到别处也不显示 cz 源）。
// 本地如需启用，在 config/config.php（安装向导生成、不入 git）里先 define 覆盖为 true 即可。
if (!defined('WITHU_CZ_ENABLED')) define('WITHU_CZ_ENABLED', false);

if (!function_exists('withu_require_couple_user')) {
    function withu_require_couple_user(Auth $auth): array {
        $auth->requireLogin();
        $user = $auth->getCurrentUser();
        if (!$user || !in_array($user['role'] ?? '', ['user1', 'user2'], true)) {
            http_response_code(403);
            exit('仅情侣账号可以使用此功能');
        }
        // GET endpoints only read authentication state; release the session
        // lock so frequent watch polling cannot serialize other requests.
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        return $user;
    }
}

if (!function_exists('withu_json_body')) {
    function withu_json_body(): array {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') return [];
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('withu_json_response')) {
    function withu_json_response(array $payload, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('withu_require_json_csrf')) {
    function withu_require_json_csrf(array $body = []): void {
        $token = (string)($body['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if (!csrf_verify($token)) {
            withu_json_response(['success' => false, 'message' => '请求已过期，请刷新页面'], 400);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
}

if (!function_exists('withu_now')) {
    function withu_now(): string { return date('Y-m-d H:i:s'); }
}

if (!function_exists('withu_token')) {
    function withu_token(int $bytes = 24): string { return bin2hex(random_bytes($bytes)); }
}

if (!function_exists('withu_hash_token')) {
    function withu_hash_token(string $token): string { return hash('sha256', $token); }
}

if (!function_exists('withu_watch_history_min_ms')) {
    function withu_watch_history_min_ms(): int {
        $seconds = (int)get_setting('watch_history_min_sec', '15');
        if ($seconds < 5) $seconds = 5;
        return $seconds * 1000;
    }
}

if (!function_exists('withu_private_location')) {
    function withu_private_location(array $row, bool $isPrivateViewer): array {
        $visibility = (string)($row['location_visibility'] ?? 'private');
        $lat = $row['latitude'] ?? null;
        $lng = $row['longitude'] ?? null;
        if ($isPrivateViewer || $visibility === 'public') {
            return ['name' => $row['location_name'] ?? '', 'latitude' => $lat, 'longitude' => $lng, 'precision' => 'exact'];
        }
        if ($lat !== null && $lng !== null) {
            return ['name' => $row['location_name'] ?? '', 'latitude' => round((float)$lat, 2), 'longitude' => round((float)$lng, 2), 'precision' => 'approximate'];
        }
        return ['name' => $row['location_name'] ?? '', 'latitude' => null, 'longitude' => null, 'precision' => 'hidden'];
    }
}

// ---------- STRM 媒体库（withUstrm）公共辅助 ----------
// withUstrm 后端仅存 SQLite（openlist2strm.db），不依赖 MySQL。
// 本组函数把内部 /api/media-library/**（JWT 鉴权）封装为 PHP 可直接复用的调用，
// 供 index.php / watch.php / watch_history.php / api/watch.php / api/desktop.php 使用。

if (!function_exists('withu_strm_backend_base')) {
    function withu_strm_backend_base(): string
    {
        return rtrim((string)getenv('STRM_BACKEND_URL') ?: 'http://127.0.0.1:8081', '/');
    }
}

if (!function_exists('withu_strm_jwt_path')) {
    function withu_strm_jwt_path(): string
    {
        $p = dirname(__DIR__, 2) . '/runtime/strm/jwt.txt';
        if (is_file($p)) return $p;
        $alt = dirname(__DIR__, 2) . '/strm/runtime/jwt.txt';
        return is_file($alt) ? $alt : $p;
    }
}

if (!function_exists('withu_strm_internal_token')) {
    function withu_strm_internal_token(): string
    {
        $path = withu_strm_jwt_path();
        if (!is_file($path)) return '';
        $secret = trim((string)file_get_contents($path));
        if ($secret === '') return '';
        $b64u = function (string $s): string {
            return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        };
        $header = $b64u(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $now = time();
        $payload = $b64u(json_encode(['sub' => 'withu_admin', 'iat' => $now, 'exp' => $now + 20160 * 60]));
        $sig = $b64u(hash_hmac('sha256', $header . '.' . $payload, $secret, true));
        return $header . '.' . $payload . '.' . $sig;
    }
}

if (!function_exists('withu_strm_curl')) {
    function withu_strm_curl(string $url, array $headers = [], string $method = 'GET', ?string $body = null): array
    {
        $ch = curl_init($url);
        $h = array_merge(['Authorization: Bearer ' . withu_strm_internal_token()], $headers);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => $h,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'withu-strm-bridge/1.0',
        ]);
        if ($method === 'POST' && $body !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['code' => $code, 'error' => $err, 'body' => (string)$raw];
    }
}

if (!function_exists('withu_strm_internal')) {
    // 调用 strm 内部媒体库接口，返回 ['success'=>bool,'message'=>?,'data'=>?]
    function withu_strm_internal(string $path): array
    {
        $url = withu_strm_backend_base() . '/api/media-library' . $path;
        $r = withu_strm_curl($url);
        if ($r['code'] !== 200) {
            return ['success' => false, 'message' => $r['error'] !== '' ? $r['error'] : 'HTTP ' . $r['code']];
        }
        $data = json_decode($r['body'], true);
        if (!is_array($data)) return ['success' => false, 'message' => 'strm 接口返回异常'];
        if (($data['code'] ?? 0) !== 200) return ['success' => false, 'message' => (string)($data['message'] ?? 'strm 接口错误')];
        return ['success' => true, 'data' => $data['data']];
    }
}

if (!function_exists('withu_strm_media_fetch')) {
    function withu_strm_media_fetch(int $id): ?array
    {
        if ($id <= 0) return null;
        $r = withu_strm_internal('/' . $id);
        return $r['success'] ? $r['data'] : null;
    }
}

if (!function_exists('withu_strm_merge_room')) {
    // 用 strm 元数据补齐房间的可播放/展示字段
    function withu_strm_merge_room(array $room): array
    {
        $meta = withu_strm_media_fetch((int)($room['media_id'] ?? 0));
        if (!$meta) return $room;
        $title = (string)($meta['title'] ?? 'strm 媒体');
        $episodeId = (int)($room['source_episode'] ?? 0);
        $ep = null;
        foreach ((array)($meta['episodes'] ?? []) as $e) {
            if ((int)($e['id'] ?? 0) === $episodeId) { $ep = $e; break; }
        }
        if ($episodeId > 0 && !$ep && !empty($meta['episodes'])) $ep = $meta['episodes'][0];
        $epNo = $ep ? (int)($ep['episodeNo'] ?? 0) : 0;
        $merged = [
            'file_name' => $ep ? (string)($ep['sourceFileName'] ?? ('第 ' . $epNo . ' 集')) : $title,
            'series_name' => $title,
            'summary' => (string)($meta['overview'] ?? ''),
            'cover_url' => (string)($meta['posterUrl'] ?? ''),
            'resolution' => '',
            'duration_ms' => 0,
            'rating' => '',
            'cast_names' => '',
            'tags' => '',
            'douban_id' => '',
            'season_number' => 1,
            'episode_number' => $epNo,
            'episode_title' => $ep ? (string)($ep['title'] ?? '') : '',
            'player_mode' => 'direct',
            'player_code' => 'strm',
            'source' => 'strm',
        ];
        foreach ($merged as $k => $v) {
            if (!array_key_exists($k, $room)) $room[$k] = $v;
        }
        return $room;
    }
}

if (!function_exists('withu_strm_room_url')) {
    // 一起看房间的 strm 解析接口 URL（前端 fetch 后拿真实代理直链）
    function withu_strm_room_url(array $room): string
    {
        $url = '/api/strm.php?action=resolve&id=' . (int)($room['media_id'] ?? 0);
        $ep = (int)($room['source_episode'] ?? 0);
        if ($ep > 0) $url .= '&episode=' . $ep;
        return $url;
    }
}
