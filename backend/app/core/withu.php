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

// ---------- withUstrm 媒体数据缓存（文件缓存 + 请求内幂等 + 失败回退过期值） ----------
// 外部详情接口单次可达 1~3s，且 php -S 为单 worker：页面上的串行详情请求会拖慢所有页面切换。
// 媒体库元数据（列表/详情）属准静态数据，做短 TTL 缓存；播放解析/健康检查等动态端点不缓存。
if (!function_exists('withu_strm_cache_path')) {
    function withu_strm_cache_path(string $key): string
    {
        return dirname(__DIR__, 2) . '/runtime/strm-cache/' . md5($key) . '.json';
    }
}

if (!function_exists('withu_strm_cache_get')) {
    /** 返回 ['data'=>mixed,'_stale'=>bool] 或 null；过期但未超 24h 的标记 _stale 供失败兜底 */
    function withu_strm_cache_get(string $key): ?array
    {
        $raw = @file_get_contents(withu_strm_cache_path($key));
        if ($raw === false) return null;
        $payload = json_decode($raw, true);
        if (!is_array($payload) || !array_key_exists('data', $payload)) return null;
        $expires = (int)($payload['expires'] ?? 0);
        if ($expires > 0 && time() - $expires > 86400) return null; // 过期太久直接丢弃
        return ['data' => $payload['data'], '_stale' => $expires > 0 && $expires < time()];
    }
}

if (!function_exists('withu_strm_cache_set')) {
    function withu_strm_cache_set(string $key, $data, int $ttl): void
    {
        $file = withu_strm_cache_path($key);
        $dir = dirname($file);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        @file_put_contents($file, json_encode(['expires' => time() + $ttl, 'data' => $data]), LOCK_EX);
    }
}

if (!function_exists('withu_strm_request_cached')) {
    /** 带 TTL 缓存的 GET（仅用于媒体列表/详情等准静态端点）；外部请求失败时回退过期缓存 */
    function withu_strm_request_cached(string $path, array $query = [], int $ttl = 300, ?array $config = null, int $timeout = 10): array
    {
        static $memo = [];
        $key = $path . '?' . http_build_query($query);
        if (isset($memo[$key])) return $memo[$key];
        $hit = withu_strm_cache_get('req:' . $key);
        if ($hit !== null && empty($hit['_stale'])) {
            return $memo[$key] = $hit['data'];
        }
        $r = withu_strm_request($path, $query, $config, $timeout);
        if (!empty($r['success'])) {
            withu_strm_cache_set('req:' . $key, $r, $ttl);
            return $memo[$key] = $r;
        }
        if ($hit !== null) return $memo[$key] = $hit['data']; // 服务异常时兜底过期数据，避免整页变慢/变空
        return $r;
    }
}

// ---------- withUstrm 海报/封面本地化 ----------
// TMDB 刮削出的 posterUrl/backdropUrl 是 CDN 反代直链，浏览器每次都要回源；
// 这里把图片落地到 runtime/strm-posters|strm-backdrops/<id>.jpg，对外统一走
// /api/strm.php?action=img。下载失败保留 CDN 原链，页面不受影响；
// 媒体被刮削移除（对应 strm 视频资源已不存在）后由 withu_strm_images_gc 清理本地文件。

if (!function_exists('withu_strm_img_dir')) {
    /** $type: 'poster'（海报，纵向）| 'backdrop'（封面/背景，横向） */
    function withu_strm_img_dir(string $type): string
    {
        return dirname(__DIR__, 2) . '/runtime/' . ($type === 'backdrop' ? 'strm-backdrops' : 'strm-posters');
    }
}

if (!function_exists('withu_strm_img_file')) {
    function withu_strm_img_file(int $id, string $type): string
    {
        return withu_strm_img_dir($type) . '/' . $id . '.jpg';
    }
}

if (!function_exists('withu_strm_img_url')) {
    function withu_strm_img_url(int $id, string $type): string
    {
        return '/api/strm.php?action=img&id=' . $id . ($type === 'backdrop' ? '&type=backdrop' : '');
    }
}

if (!function_exists('withu_strm_img_mime')) {
    /** 按魔数识别图片类型，非图片返回 ''（用于下载校验与 img 响应头） */
    function withu_strm_img_mime(string $bytes): string
    {
        if (strncmp($bytes, "\xFF\xD8\xFF", 3) === 0) return 'image/jpeg';
        if (strncmp($bytes, "\x89PNG", 4) === 0) return 'image/png';
        if (strncmp($bytes, 'GIF8', 4) === 0) return 'image/gif';
        if (strlen($bytes) > 12 && substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP') return 'image/webp';
        return '';
    }
}

if (!function_exists('withu_strm_img_download_jobs')) {
    /**
     * 并行下载图片任务（curl_multi），tmp+rename 原子落盘，避免读到半张图。
     * $jobs: [['id'=>媒体id, 'type'=>'poster|backdrop', 'url'=>远程地址], ...]
     * 返回成功的 "id:type" 集合；非 2xx、非图片内容、超时一律视为失败。
     */
    function withu_strm_img_download_jobs(array $jobs, int $timeoutMs = 8000): array
    {
        $ok = [];
        if (!$jobs) return $ok;
        $mh = curl_multi_init();
        $handles = [];
        foreach ($jobs as $job) {
            $id = (int)($job['id'] ?? 0);
            $type = ($job['type'] ?? '') === 'backdrop' ? 'backdrop' : 'poster';
            $url = trim((string)($job['url'] ?? ''));
            if ($id <= 0 || !preg_match('#^https?://#i', $url)) continue;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => max(1, (int)round($timeoutMs / 1000)),
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => 'withu-strm-img/1.0',
            ]);
            $key = $id . ':' . $type;
            $handles[$key] = ['ch' => $ch, 'id' => $id, 'type' => $type];
            curl_multi_add_handle($mh, $ch);
        }
        if ($handles) {
            do {
                $exec = curl_multi_exec($mh, $active);
                if ($active) curl_multi_select($mh, 0.2);
            } while ($exec === CURLM_OK && $active);
        }
        foreach ($handles as $key => $h) {
            $body = curl_multi_getcontent($h['ch']);
            $code = (int)curl_getinfo($h['ch'], CURLINFO_RESPONSE_CODE);
            if ($code >= 200 && $code < 300 && is_string($body) && strlen($body) >= 1024 && withu_strm_img_mime($body) !== '') {
                $dir = withu_strm_img_dir($h['type']);
                if (!is_dir($dir)) @mkdir($dir, 0775, true);
                $tmp = $dir . '/.' . $h['id'] . '.' . bin2hex(random_bytes(4)) . '.tmp';
                if (@file_put_contents($tmp, $body) !== false && @rename($tmp, withu_strm_img_file($h['id'], $h['type']))) {
                    $ok[$key] = true;
                } else {
                    @unlink($tmp);
                }
            }
            curl_multi_remove_handle($mh, $h['ch']);
            curl_close($h['ch']);
        }
        curl_multi_close($mh);
        return $ok;
    }
}

if (!function_exists('withu_strm_localize_items')) {
    /**
     * 把 items 里的远程海报/封面地址替换为本地地址：本地已有文件直接换链，
     * 缺失的批量下载，下载失败保留 CDN 原链。就地修改 $items。
     * $types 限 'poster'/'backdrop'；$budget 限制单次最多触发的下载数（null 不限）。
     * 返回实际下载成功的文件数。
     */
    function withu_strm_localize_items(array &$items, array $types = ['poster'], ?int $budget = null): int
    {
        // 只使用 withUstrm 本地刮削图片（/api/external 相对地址 → extimg 鉴权转发）；
        // 不使用 CDN 图片：远程热链一律置空，前端走占位图，也不再发起 CDN 下载预热。
        foreach ($items as $i => $item) {
            if (!is_array($item)) continue;
            $id = (int)($item['id'] ?? 0);
            if ($id <= 0) continue;
            foreach ($types as $type) {
                $field = $type === 'backdrop' ? 'backdropUrl' : 'posterUrl';
                $url = trim((string)($item[$field] ?? ''));
                if ($url === '') continue;
                if ($url[0] === '/' && strpos($url, '/api/external/') === 0) {
                    $items[$i][$field] = '/api/strm.php?action=extimg&id=' . $id . '&kind=' . $type;
                    continue;
                }
                if ($url[0] === '/') continue; // 网关自身本地地址，保持不变
                if (preg_match('#^https?://#i', $url)) {
                    $items[$i][$field] = ''; // 不使用 CDN 图片
                }
            }
        }
        return 0;
    }
}

if (!function_exists('withu_strm_images_gc')) {
    /**
     * 一致性清理：删除不在 $validIds 里的本地海报/封面文件。
     * 必须在「完整媒体列表拉取成功」后调用 —— 列表即刮削结果，
     * id 不在列表代表对应的 strm 视频资源已被移除。返回删除文件数。
     */
    function withu_strm_images_gc(array $validIds): int
    {
        $valid = [];
        foreach ($validIds as $id) {
            $id = (int)$id;
            if ($id > 0) $valid[$id] = true;
        }
        $removed = 0;
        foreach (['poster', 'backdrop'] as $type) {
            $dir = withu_strm_img_dir($type);
            if (!is_dir($dir)) continue;
            foreach ((@scandir($dir) ?: []) as $f) {
                if (!preg_match('/^(\d+)\.jpg$/', $f, $m)) continue;
                if (isset($valid[(int)$m[1]])) continue;
                if (@unlink($dir . '/' . $f)) $removed++;
            }
        }
        return $removed;
    }
}

if (!function_exists('withu_strm_images_maintain')) {
    /**
     * 本地化巡检（节流，默认 60s 一次），挂在媒体列表出口（刮削结果的同步点）上：
     *   1) 全量拉取 withUstrm 媒体列表 —— 任一页失败立即放弃本轮，绝不因服务抖动误删
     *   2) 一致性清理：删除已无对应 strm 媒体的本地海报/封面
     *   3) 预热：本轮列表缺失的图片批量下载（预算内，超出部分留到下轮）
     */
    function withu_strm_images_maintain(int $throttleSec = 60, int $budget = 60): void
    {
        if (!withu_strm_config()['ready']) return;
        $mark = dirname(__DIR__, 2) . '/runtime/strm-img-maintain.json';
        $now = time();
        $last = is_file($mark) ? (int)@file_get_contents($mark) : 0;
        if ($last > 0 && $now - $last < $throttleSec) return;
        @file_put_contents($mark, (string)$now, LOCK_EX); // 先占位再执行，避免并发重复跑

        $items = [];
        $validIds = [];
        for ($page = 1; $page < 200; $page++) {
            $r = withu_strm_request_cached('media', ['page' => $page, 'pageSize' => 100], 120);
            if (empty($r['success']) || !is_array($r['data'])) return;
            $pageItems = is_array($r['data']['items'] ?? null) ? $r['data']['items'] : [];
            if (!$pageItems) break;
            foreach ($pageItems as $it) {
                if (!is_array($it)) continue;
                $items[] = $it;
                $pid = (int)($it['id'] ?? 0);
                if ($pid > 0) $validIds[$pid] = true;
            }
            if (count($items) >= (int)($r['data']['total'] ?? 0)) break;
        }
        withu_strm_images_gc(array_keys($validIds));
        withu_strm_localize_items($items, ['poster', 'backdrop'], $budget);
    }
}

if (!function_exists('withu_strm_map_detail')) {
    /** 详情数据映射：外部 name/year → 旧 title/releaseYear；剧集标题用文件名兜底 */
    function withu_strm_map_detail(array $d): array
    {
        $d = withu_strm_map_item($d);
        $eps = [];
        foreach ((array)($d['episodes'] ?? []) as $ep) {
            if (!is_array($ep)) continue;
            $ep['title'] = (string)($ep['sourceFileName'] ?? '');
            $eps[] = $ep;
        }
        $d['episodes'] = $eps;
        return $d;
    }
}

if (!function_exists('withu_strm_media_fetch_multi')) {
    /**
     * 并行拉取多个媒体详情（curl_multi），命中缓存的直接返回。
     * 返回 [媒体id => 详情数组|null]；失败且有旧缓存时回退旧值。
     */
    function withu_strm_media_fetch_multi(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $out = [];
        if (!$ids) return $out;

        $missing = [];
        foreach ($ids as $id) {
            $hit = withu_strm_cache_get('detail:' . $id);
            if ($hit !== null && empty($hit['_stale'])) {
                $out[$id] = $hit['data'];
            } else {
                $missing[$id] = $hit; // 记录旧缓存，失败时兜底
            }
        }
        if (!$missing) return $out;

        $cfg = withu_strm_config();
        if ($cfg['base_url'] === '' || $cfg['api_key'] === '') {
            foreach (array_keys($missing) as $id) $out[$id] = null;
            return $out;
        }

        $mh = curl_multi_init();
        $handles = [];
        $fresh = [];
        foreach (array_keys($missing) as $id) {
            $ch = curl_init($cfg['base_url'] . '/api/external/media/' . $id);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'X-API-Key: ' . $cfg['api_key']],
                CURLOPT_FOLLOWLOCATION => false,
            ]);
            $handles[$id] = $ch;
            curl_multi_add_handle($mh, $ch);
        }
        do {
            $exec = curl_multi_exec($mh, $active);
            if ($active) curl_multi_select($mh, 0.2);
        } while ($exec === CURLM_OK && $active);

        foreach ($handles as $id => $ch) {
            $body = curl_multi_getcontent($ch);
            if (is_string($body) && $body !== '' && (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 200) {
                $json = json_decode($body, true);
                if (is_array($json)) $fresh[$id] = withu_strm_map_detail($json);
            }
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
        // 批量本地化后再写缓存：缓存里保存本地地址，后续命中不再依赖 CDN
        withu_strm_localize_items($fresh, ['poster'], 40);
        foreach ($fresh as $id => $data) {
            withu_strm_cache_set('detail:' . $id, $data, 300);
        }
        foreach (array_keys($missing) as $id) {
            if (isset($fresh[$id])) {
                $out[$id] = $fresh[$id];
            } elseif (is_array($missing[$id])) {
                $out[$id] = $missing[$id]['data']; // 回退过期缓存
            } else {
                $out[$id] = null;
            }
        }
        return $out;
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
            $r = withu_strm_request_cached('media/' . (int)$m[1], [], 300);
            if (!$r['success']) {
                return ['success' => false, 'message' => (string)$r['message']];
            }
            $d = is_array($r['data']) ? $r['data'] : [];
            $d = withu_strm_map_detail($d);
            // 详情出口本地化海报+封面（播放页两处都要用）
            $wrap = [$d];
            withu_strm_localize_items($wrap, ['poster', 'backdrop']);
            $d = $wrap[0];
            return ['success' => true, 'message' => '', 'data' => $d];
        }

        parse_str(ltrim(strtok($path, '?'), '?'), $q);
        $eq = [];
        if (!empty($q['mediaType'])) $eq['type'] = (string)$q['mediaType'];
        if (!empty($q['keyword']))   $eq['keyword'] = (string)$q['keyword'];
        // 旧版用过 size= 作为分页参数别名，一并提供 pageSize 以外的兜底
        $eq['page']     = max(1, (int)($q['page'] ?? 1));
        $eq['pageSize'] = max(1, min(100, (int)($q['pageSize'] ?? ($q['size'] ?? 24))));
        $r = withu_strm_request_cached('media', $eq, 120);
        if (!$r['success']) {
            return ['success' => false, 'message' => (string)$r['message']];
        }
        $d = is_array($r['data']) ? $r['data'] : [];
        $items = [];
        foreach ((array)($d['items'] ?? []) as $it) {
            if (!is_array($it)) continue;
            $items[] = withu_strm_map_item($it);
        }
        withu_strm_localize_items($items, ['poster'], 60); // 列表出口只需海报
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

