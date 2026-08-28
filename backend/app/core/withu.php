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

// ---------- withUstrm 媒体库对接（外部接口 X-API-Key 模式） ----------
if (!function_exists('withu_strm_normalize_base')) {
    function withu_strm_normalize_base(string $url): string {
        $url = trim($url);
        if ($url === '') return '';
        // 允许用户省略协议，本地内网地址默认按 http 处理
        if (!preg_match('#^https?://#i', $url)) $url = 'http://' . $url;
        return rtrim($url, '/');
    }
}

if (!function_exists('withu_strm_config')) {
    function withu_strm_config(): array {
        $base = withu_strm_normalize_base((string)get_setting('strm_backend_url', ''));
        $key  = trim((string)get_setting('strm_api_key', ''));
        return [
            'enabled'  => get_setting('strm_enabled', '0') === '1',
            'base_url' => $base,
            'api_key'  => $key,
            'ready'    => get_setting('strm_enabled', '0') === '1' && $base !== '' && $key !== '',
        ];
    }
}

/**
 * 调用 withUstrm 外部媒体库接口（/api/external/**，X-API-Key 鉴权）
 * 返回 ['success' => bool, 'status' => int, 'message' => string, 'data' => mixed]
 */
if (!function_exists('withu_strm_request')) {
    function withu_strm_request(string $path, array $query = [], ?array $config = null, int $timeout = 10): array {
        $cfg = $config ?? withu_strm_config();
        if ($cfg['base_url'] === '') {
            return ['success' => false, 'status' => 0, 'message' => '尚未配置 withUstrm 服务地址', 'data' => null];
        }
        $url = $cfg['base_url'] . '/api/external/' . ltrim($path, '/');
        if ($query) $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'X-API-Key: ' . $cfg['api_key']],
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $errno !== 0) {
            return ['success' => false, 'status' => 0, 'message' => '连接 withUstrm 失败：' . ($error ?: ('cURL #' . $errno)), 'data' => null];
        }
        if ($status === 401) {
            return ['success' => false, 'status' => 401, 'message' => 'withUstrm 外部接口未启用或 API Key 无效', 'data' => null];
        }
        if ($status < 200 || $status >= 300) {
            return ['success' => false, 'status' => $status, 'message' => 'withUstrm 返回异常状态 ' . $status, 'data' => null];
        }

        $json = json_decode((string)$body, true);
        if (!is_array($json)) {
            return ['success' => true, 'status' => $status, 'message' => '', 'data' => $body]; // /health 等纯文本端点
        }
        return ['success' => true, 'status' => $status, 'message' => '', 'data' => $json];
    }
}

/** 解析播放 302 重定向（stream/{id} 或 episode/{episodeId}/stream），返回直链或错误 */
if (!function_exists('withu_strm_resolve_play_url')) {
    function withu_strm_resolve_play_url(string $endpointPath): array {
        $cfg = withu_strm_config();
        if (!$cfg['ready']) {
            return ['success' => false, 'message' => '请先在后台完成 withUstrm 对接配置'];
        }
        $url = $cfg['base_url'] . '/api/external/' . ltrim($endpointPath, '/');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['X-API-Key: ' . $cfg['api_key']],
            CURLOPT_NOBODY => true,          // 只取响应头，不在服务器上拉视频流
        ]);
        curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $location = (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);
        if ($status >= 200 && $status < 400 && $location !== '') {
            return ['success' => true, 'message' => '', 'url' => $location];
        }
        return ['success' => false, 'message' => '解析播放地址失败（HTTP ' . $status . '），请确认该媒体的源站仍可访问'];
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

// ---------- 旧内部接口兼容层：供 api/watch.php、watch*.php 等继续复用 ----------
// 旧版对接走 JWT 内部接口（/api/media-library/**），现统一改走外部接口（X-API-Key），
// 并在这里把外部字段名映射回旧命名（name→title、year→releaseYear），业务代码无需感知。
if (!function_exists('withu_strm_backend_base')) {
    function withu_strm_backend_base(): string
    {
        return withu_strm_config()['base_url'];
    }
}

if (!function_exists('withu_strm_map_item')) {
    // 列表项字段映射，保持旧结构供推荐位/房间搜索使用
    function withu_strm_map_item(array $it): array
    {
        $it['title'] = (string)($it['name'] ?? '');
        $it['releaseYear'] = (string)($it['year'] ?? '');
        return $it;
    }
}

if (!function_exists('withu_strm_internal')) {
    /**
     * 兼容旧签名：path 形如 '/<id>' 或 '?page=&pageSize=&mediaType=&keyword='。
     * 返回 ['success'=>bool,'message'=>string,'data'=>mixed]，data 已按旧字段命名。
     */
    function withu_strm_internal(string $path): array
    {
        if (preg_match('#^/(\d+)$#', $path, $m)) {
            $r = withu_strm_request('media/' . (int)$m[1]);
            if (!$r['success']) {
                return ['success' => false, 'message' => (string)$r['message']];
            }
            $d = is_array($r['data']) ? $r['data'] : [];
            $d = withu_strm_map_item($d);
            $eps = [];
            foreach ((array)($d['episodes'] ?? []) as $ep) {
                if (!is_array($ep)) continue;
                $ep['title'] = (string)($ep['sourceFileName'] ?? '');
                $eps[] = $ep;
            }
            $d['episodes'] = $eps;
            return ['success' => true, 'message' => '', 'data' => $d];
        }

        parse_str(ltrim(strtok($path, '?'), '?'), $q);
        $eq = [];
        if (!empty($q['mediaType'])) $eq['type'] = (string)$q['mediaType'];
        if (!empty($q['keyword']))   $eq['keyword'] = (string)$q['keyword'];
        // 旧版用过 size= 作为分页参数别名，一并提供 pageSize 以外的兜底
        $eq['page']     = max(1, (int)($q['page'] ?? 1));
        $eq['pageSize'] = max(1, min(100, (int)($q['pageSize'] ?? ($q['size'] ?? 24))));
        $r = withu_strm_request('media', $eq);
        if (!$r['success']) {
            return ['success' => false, 'message' => (string)$r['message']];
        }
        $d = is_array($r['data']) ? $r['data'] : [];
        $items = [];
        foreach ((array)($d['items'] ?? []) as $it) {
            if (!is_array($it)) continue;
            $items[] = withu_strm_map_item($it);
        }
        $d['items'] = $items;
        return ['success' => true, 'message' => '', 'data' => $d];
    }
}

if (!function_exists('withu_strm_media_fetch')) {
    function withu_strm_media_fetch(int $id): ?array
    {
        if ($id <= 0) return null;
        $r = withu_strm_internal('/' . $id);
        return !empty($r['success']) ? $r['data'] : null;
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

