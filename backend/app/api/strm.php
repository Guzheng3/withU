<?php
// api/strm.php —— withUstrm 媒体库对接网关
// ------------------------------------------------------------
// 通过 withUstrm 外部媒体库接口（/api/external/**，X-API-Key 鉴权）提供媒体列表、
// 详情、播放地址解析与服务端流式代理。连接配置（服务地址 + API Key）保存在主站
// settings 表，后台「withUstrm 媒体库」页可维护。
//
// 鉴权：withu 情侣账号登录才可访问，API Key 只存在服务器端 ——
//       保证「只能从 withu 访问」，前端拿不到任何凭据。
//
// 用法：
//   GET ?action=info                       → 服务信息
//   GET ?action=counts                     → 媒体类型计数
//   GET ?action=media&type=&keyword=&page=&pageSize= → 媒体列表
//   GET ?action=detail&id=                 → 媒体详情（含剧集）
//   GET ?action=resolve&id=&episode=       → 播放直链 {success,url,type}
//   GET ?action=posters                    → 豆瓣海报兜底映射
//   GET ?action=img&id=&type=poster|backdrop → 本地海报/封面（TMDB 已落地图 + 豆瓣兜底图）
//   海报/封面本地化：列表/详情出口把 CDN 直链替换为本地地址（缺失时并行下载，
//   失败保留原链）；media 首页请求触发节流巡检，媒体被刮削移除后清理本地文件。
//   代理端点（播放用，服务端转发，免跨域）：
//   GET ?action=proxy&url=<直链>           → 通用流式代理（支持 Range）
//   GET ?action=proxy_m3u8&url=<m3u8>      → 拉 m3u8 并把分片改写为 proxy_seg
//   GET ?action=proxy_seg&url=<分片>       → 转发分片

header('Content-Type: application/json; charset=UTF-8');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') exit;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/withu.php';

if (!function_exists('withu_run_node_sync')) {
    function withu_run_node_sync(string $cmd, int $timeoutMs = 25000): array
    {
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = @proc_open($cmd, $descriptors, $pipes, dirname(__DIR__, 1));
        if (!is_resource($proc)) return ['code' => -1, 'out' => 'proc_open 失败'];
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $out = ''; $err = '';
        $deadline = microtime(true) + $timeoutMs / 1000;
        while (true) {
            $r = [$pipes[1], $pipes[2]]; $w = null; $e = null;
            $secs = $deadline - microtime(true);
            if ($secs <= 0) break;
            $n = @stream_select($r, $w, $e, (int)$secs, (int)(($secs - floor($secs)) * 1000000));
            if ($n === false || $n === 0) break;
            foreach ($r as $pipe) {
                $chunk = @fread($pipe, 8192);
                if ($chunk === false || $chunk === '') continue;
                if ($pipe === $pipes[1]) $out .= $chunk; else $err .= $chunk;
            }
            $status = proc_get_status($proc);
            if (!$status['running']) break;
        }
        $status = proc_get_status($proc);
        @fclose($pipes[1]); @fclose($pipes[2]);
        @proc_terminate($proc);
        @proc_close($proc);
        return ['code' => (int)($status['exitcode'] ?? -1), 'out' => $out . $err];
    }
}


$auth = new Auth();
try {
    $user = withu_require_couple_user($auth);
} catch (Throwable $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '请先登录'], JSON_UNESCAPED_UNICODE);
    exit;
}

function strm_json($data, int $code = 200)
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ---------- 配置：后端地址 + 外部接口 Key（存 settings 表，后台「withUstrm 媒体库」页可改） ----------
function strm_backend_base(): string
{
    return withu_strm_config()['base_url'];
}

function strm_ready(): bool
{
    return withu_strm_config()['ready'];
}

// ---------- curl 封装 ----------
function strm_curl(string $url, array $headers = [], string $method = 'GET', ?string $body = null): array
{
    $ch = curl_init($url);
    $h = $headers;
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => $h,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 25,
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
    $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    if ($raw === false || $raw === '') {
        return ['code' => 0, 'error' => $err, 'body' => '', 'headers' => []];
    }
    $headerText = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    $headers = [];
    foreach (explode("\r\n", $headerText) as $line) {
        $pos = strpos($line, ':');
        if ($pos > 0) {
            $name = strtolower(trim(substr($line, 0, $pos)));
            $val = trim(substr($line, $pos + 1));
            $headers[$name] = $val;
        }
    }
    return ['code' => $code, 'error' => $err, 'body' => $body, 'headers' => $headers];
}

// 调用 withUstrm 外部媒体库接口（X-API-Key），并保持旧内部接口的字段命名，
// 使业务代码（media/detail 映射、房间搜索）无需感知数据源切换
function strm_internal(string $path): array
{
    if (preg_match('#^/(\d+)$#', $path, $m)) {
        // 详情：外部 name/year → 旧 title/releaseYear；剧集标题用文件名兜底
        $r = withu_strm_request_cached('media/' . (int)$m[1], [], 300);
        if (!$r['success']) {
            return ['success' => false, 'message' => (string)$r['message'], 'http' => $r['status']];
        }
        $d = is_array($r['data']) ? $r['data'] : [];
        $d['title'] = (string)($d['name'] ?? '');
        $d['releaseYear'] = (string)($d['year'] ?? '');
        // 详情出口本地化海报+封面
        $wrap = [$d];
        withu_strm_localize_items($wrap, ['poster', 'backdrop']);
        $d = $wrap[0];
        // 注意：不能对 ($d['episodes'] ?? []) 表达式直接引用迭代 —— ?? 返回副本，
        // 引用修改不会写回 $d，下游读 title 会拿到空值
        $eps = is_array($d['episodes'] ?? null) ? $d['episodes'] : [];
        foreach ($eps as &$ep) {
            if (!is_array($ep)) continue;
            $ep['title'] = (string)($ep['sourceFileName'] ?? '');
        }
        unset($ep);
        $d['episodes'] = $eps;
        return ['success' => true, 'data' => $d];
    }

    parse_str(ltrim(strtok($path, '?'), '?'), $q);
    $eq = [];
    if (!empty($q['mediaType'])) $eq['type'] = (string)$q['mediaType'];
    if (!empty($q['keyword']))   $eq['keyword'] = (string)$q['keyword'];
    // 兼容旧版 size= 分页别名
    $eq['page']     = max(1, (int)($q['page'] ?? 1));
    $eq['pageSize'] = max(1, min(100, (int)($q['pageSize'] ?? ($q['size'] ?? 24))));
    $r = withu_strm_request_cached('media', $eq, 120);
    if (!$r['success']) {
        return ['success' => false, 'message' => (string)$r['message'], 'http' => $r['status']];
    }
    $d = is_array($r['data']) ? $r['data'] : [];
    // 同上：?? 表达式引用迭代会丢失修改，先落到变量再写回 $d
    $items = is_array($d['items'] ?? null) ? $d['items'] : [];
    foreach ($items as &$it) {
        if (!is_array($it)) continue;
        $it['title'] = (string)($it['name'] ?? '');
        $it['releaseYear'] = (string)($it['year'] ?? '');
    }
    unset($it);
    withu_strm_localize_items($items, ['poster'], 60); // 列表出口本地化海报
    $d['items'] = $items;
    return ['success' => true, 'data' => $d];
}

// 本网关对外的播放直链（代理前缀）
function strm_self_base(): string
{
    $scheme = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://');
    return $scheme . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/api/strm.php';
}

// 判定直链类型
function strm_kind(string $url): string
{
    $p = parse_url($url, PHP_URL_PATH) ?: '';
    if (preg_match('/\.m3u8(\?|$)/i', $p)) return 'm3u8';
    if (preg_match('/\.(mp4|webm|mov|m4v)(\?|$)/i', $p)) return 'mp4';
    return 'other';
}

function strm_fuzzy_contains(string $needle, string $haystack): bool
{
    $needle = trim(mb_strtolower($needle, 'UTF-8'));
    $haystack = mb_strtolower($haystack, 'UTF-8');
    if ($needle === '' || $haystack === '') return false;
    $offset = 0;
    foreach (preg_split('//u', $needle, -1, PREG_SPLIT_NO_EMPTY) as $char) {
        $position = mb_strpos($haystack, $char, $offset, 'UTF-8');
        if ($position === false) return false;
        $offset = $position + mb_strlen($char, 'UTF-8');
    }
    return true;
}

// 由外部 stream 端点的 302 Location 拿直链并返回给播放器的 {success,url,type}
function strm_resolve_internal(int $mediaId, ?int $episodeId, string $displayName): void
{
    $endpoint = $episodeId && $episodeId > 0
        ? ('episode/' . (int)$episodeId . '/stream')
        : ('stream/' . (int)$mediaId);
    $r = withu_strm_resolve_play_url($endpoint);
    if (!$r['success'] || empty($r['url'])) {
        strm_json(['success' => false, 'message' => '播放地址解析失败：' . ($r['message'] ?? '直链为空，请检查 OpenList 配置与刮削任务')], 502);
    }
    $loc = (string)$r['url'];
    $kind = strm_kind($loc);
    $self = strm_self_base();
    if ($kind === 'm3u8') {
        strm_json(['success' => true, 'source' => 'strm', 'url' => $self . '?action=proxy_m3u8&url=' . rawurlencode($loc), 'type' => 'm3u8', 'name' => $displayName]);
    }
    strm_json(['success' => true, 'source' => 'strm', 'url' => $loc, 'type' => 'mp4', 'name' => $displayName]);
}

$action = (string)($_GET['action'] ?? 'info');

// ---------- 流式代理端点 ----------
if ($action === 'extimg') {
    // 外部库本地图片（刮削时与 strm 同目录保存）经网关鉴权转发给浏览器
    $imgId = (int)($_GET['id'] ?? 0);
    $imgKind = ($_GET['kind'] ?? '') === 'backdrop' ? 'backdrop' : 'poster';
    if ($imgId <= 0) strm_json(['code' => 400, 'msg' => '缺少 id'], 400);
    $r = strm_curl(strm_backend_base() . '/api/external/media/' . $imgId . '/' . $imgKind, ['Accept: image/jpeg', 'X-API-Key: ' . withu_strm_config()['api_key']], 'GET');
    while (ob_get_level() > 0) ob_end_clean();
    if ($r['code'] !== 200 || $r['body'] === '') {
        http_response_code(404);
        header('Content-Type: image/gif');
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'); // 1px 透明图
        exit;
    }
    header('Content-Type: image/jpeg');
    header('Cache-Control: max-age=86400, public');
    echo $r['body'];
    exit;
}

if ($action === 'proxy') {
    $target = trim((string)($_GET['url'] ?? ''));
    if ($target === '' || !preg_match('#^https?://#i', $target)) strm_json(['code' => 400, 'msg' => '无效地址'], 400);
    // 用 PHP stream 转发：stream_get_meta_data 拿真实响应头（规避 php -S 下 curl HEADERFUNCTION 失效），fpassthru 流式输出（支持大文件/不占内存）
    $range = isset($_SERVER['HTTP_RANGE']) ? trim($_SERVER['HTTP_RANGE']) : '';
    $ctx = stream_context_create(['http' => [
        'method' => 'GET',
        'header' => "User-Agent: withu-strm-bridge/1.0\r\nAccept: */*\r\n" . ($range !== '' ? "Range: $range\r\n" : ''),
        'follow_location' => 1,
        'max_redirects' => 4,
        'ignore_errors' => true,
        'timeout' => 600,
    ], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    $fp = @fopen($target, 'rb', false, $ctx);
    if ($fp === false) strm_json(['code' => 502, 'msg' => '上游连接失败'], 502);
    $meta = stream_get_meta_data($fp);
    $status = 200;
    $upHeaders = [];
    foreach (($meta['wrapper_data'] ?? []) as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', trim($line), $m)) {
            $status = (int)$m[1];
        } elseif (strpos($line, ':') > 0) {
            $pos = strpos($line, ':');
            $name = strtolower(trim(substr($line, 0, $pos)));
            $val = trim(substr($line, $pos + 1));
            if (in_array($name, ['content-type', 'content-length', 'content-range', 'accept-ranges', 'content-disposition'], true)) {
                $upHeaders[$name] = $val;
            }
        }
    }
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($status);
    if (!empty($upHeaders['content-type'])) header('Content-Type: ' . $upHeaders['content-type']);
    if (!empty($upHeaders['content-range'])) header('Content-Range: ' . $upHeaders['content-range']);
    if (!empty($upHeaders['accept-ranges'])) header('Accept-Ranges: ' . $upHeaders['accept-ranges']);
    if (!empty($upHeaders['content-disposition'])) header('Content-Disposition: ' . $upHeaders['content-disposition']);
    if (!empty($upHeaders['content-length'])) header('Content-Length: ' . $upHeaders['content-length']);
    header('Cache-Control: no-store');
    header('Access-Control-Allow-Origin: *');
    fpassthru($fp);
    fclose($fp);
    exit;
}

if ($action === 'proxy_m3u8') {
    $m3u8 = trim((string)($_GET['url'] ?? ''));
    if ($m3u8 === '') strm_json(['code' => 400, 'msg' => '缺少url'], 400);
    $r = strm_curl($m3u8, [], 'GET');
    if ($r['code'] !== 200 || $r['body'] === '') strm_json(['code' => 502, 'msg' => 'm3u8 拉取失败'], 502);
    header('Content-Type: application/vnd.apple.mpegurl; charset=utf-8');
    header('Cache-Control: no-store');
    $self = strm_self_base();
    $out = '';
    $base = preg_replace('#/[^/]*$#', '/', $m3u8);
    foreach (preg_split('/\r?\n/', $r['body']) as $raw) {
        $t = trim($raw);
        if ($t === '' || str_starts_with($t, '#')) { $out .= $raw . "\n"; continue; }
        $abs = preg_match('#^https?://#i', $t) ? $t : $base . $t;
        $out .= $self . '?action=proxy_seg&url=' . rawurlencode($abs) . "\n";
    }
    echo $out;
    exit;
}

if ($action === 'proxy_seg') {
    $seg = trim((string)($_GET['url'] ?? ''));
    if ($seg === '') strm_json(['code' => 400, 'msg' => '缺少url'], 400);
    $ctx = stream_context_create(['http' => [
        'method' => 'GET',
        'header' => "User-Agent: withu-strm-bridge/1.0\r\nAccept: */*\r\n",
        'follow_location' => 1,
        'max_redirects' => 4,
        'ignore_errors' => true,
        'timeout' => 120,
    ], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    $fp = @fopen($seg, 'rb', false, $ctx);
    if ($fp === false) strm_json(['code' => 502, 'msg' => '分片连接失败'], 502);
    $meta = stream_get_meta_data($fp);
    $ct = 'application/octet-stream';
    foreach (($meta['wrapper_data'] ?? []) as $line) {
        if (stripos($line, 'Content-Type:') === 0) {
            $ct = trim(substr($line, 13));
        }
    }
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: ' . $ct);
    header('Cache-Control: max-age=3600');
    header('Access-Control-Allow-Origin: *');
    fpassthru($fp);
    fclose($fp);
    exit;
}

// ---------- 业务接口 ----------
if (!strm_ready()) {
    strm_json(['success' => false, 'message' => 'strm 组件未就绪（缺少内部 JWT），请先启动 withUstrm 组件'], 503);
}

switch ($action) {
    case 'info': {
        strm_json(['success' => true, 'data' => [
            'serverName' => 'withUstrm',
            'version' => 'builtin',
            'baseUrl' => strm_backend_base(),
            'authEnabled' => false,
            'supportedMediaTypes' => ['movie', 'tv'],
        ]]);
    }
    case 'counts': {
        $tot = ['total' => 0, 'movie' => 0, 'series' => 0];
        $q = function (string $type) {
            $r = strm_internal('?page=1&pageSize=1' . ($type !== '' ? '&mediaType=' . rawurlencode($type) : ''));
            return $r['success'] ? (int)($r['data']['total'] ?? 0) : 0;
        };
        $tot['total'] = $q('');
        $tot['movie'] = $q('movie');
        $tot['series'] = $q('tv');
        strm_json(['success' => true, 'data' => $tot]);
    }
    case 'media': {
        $q = [];
        $type = trim((string)($_GET['type'] ?? ''));
        if ($type !== '' && in_array($type, ['movie', 'tv'], true)) $q[] = 'mediaType=' . rawurlencode($type);
        $kw = trim((string)($_GET['keyword'] ?? ''));
        if ($kw !== '') $q[] = 'keyword=' . rawurlencode($kw);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $pageSize = max(1, min(100, (int)($_GET['pageSize'] ?? 24)));
        $q[] = 'page=' . $page;
        $q[] = 'pageSize=' . $pageSize;
        // 列表出口即刮削结果的同步点：首页请求时做本地化巡检（节流 60s）——
        // 全量对账清理已移除媒体的本地海报/封面，并预热缺失图片
        if ($page === 1) withu_strm_images_maintain();
        $r = strm_internal('?' . implode('&', $q));
        if (!$r['success']) {
            strm_json(['success' => false, 'message' => $r['message'] ?? '媒体列表获取失败'], 502);
        }
        $d = $r['data'] ?? [];
        if ($kw !== '' && empty($d['items'])) {
            $fallback = strm_internal('?page=1&pageSize=100');
            $fallbackItems = $fallback['success'] ? ($fallback['data']['items'] ?? []) : [];
            $d['items'] = array_values(array_filter($fallbackItems, function ($item) use ($kw) {
                return strm_fuzzy_contains($kw, (string)($item['title'] ?? ''))
                    || strm_fuzzy_contains($kw, (string)($item['originalTitle'] ?? ''));
            }));
            $d['total'] = count($d['items']);
        }
        $items = [];
        foreach (($d['items'] ?? []) as $it) {
            $pid = (int)($it['id'] ?? 0);
            $poster = (string)($it['posterUrl'] ?? '');
            // 豆瓣海报兜底：strm 未刮削 TMDB 时，用本地 runtime/strm-posters/<id>.jpg
            if ($poster === '' && $pid > 0) {
                $imgFile = dirname(__DIR__, 2) . '/runtime/strm-posters/' . $pid . '.jpg';
                if (is_file($imgFile)) $poster = '/api/strm.php?action=img&id=' . $pid;
            }
            $items[] = [
                'id' => $pid,
                'name' => (string)($it['title'] ?? ''),
                'type' => (string)($it['mediaType'] ?? ''),
                'mediaType' => (string)($it['mediaType'] ?? ''),
                'originalTitle' => (string)($it['originalTitle'] ?? ''),
                'year' => (string)($it['releaseYear'] ?? ''),
                'posterUrl' => $poster,
                'backdropUrl' => (string)($it['backdropUrl'] ?? ''),
                'voteAverage' => $it['voteAverage'] ?? null,
                'tmdbId' => $it['tmdbId'] ?? null,
                'episodeCount' => (int)($it['episodeCount'] ?? 0),
            ];
        }
        strm_json(['success' => true, 'data' => [
            'total' => (int)($d['total'] ?? count($items)),
            'page' => (int)($d['page'] ?? $page),
            'pageSize' => (int)($d['pageSize'] ?? $pageSize),
            'items' => $items,
        ]]);
    }
    case 'credits': {
        // 外部媒体库接口暂不提供演员表（旧版走内部 TMDB 测试端点，需 JWT 已废弃）。
        // 返回空列表，前端 loadStrmCast 会静默跳过演员行展示。
        strm_json(['success' => true, 'data' => ['cast' => []]]);
    }
    case 'detail': {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) strm_json(['success' => false, 'message' => '缺少 id'], 400);
        $r = strm_internal('/' . $id);
        if (!$r['success']) {
            strm_json(['success' => false, 'message' => $r['message'] ?? '详情获取失败'], 502);
        }
        $d = $r['data'] ?? [];
        $eps = [];
        foreach (($d['episodes'] ?? []) as $ep) {
            $eps[] = [
                'id' => (int)($ep['id'] ?? 0),
                'episodeNo' => (int)($ep['episodeNo'] ?? 0),
                'sourceFileName' => (string)($ep['sourceFileName'] ?? ''),
                'sourcePath' => (string)($ep['sourcePath'] ?? ''),
            ];
        }
        strm_json(['success' => true, 'data' => [
            'id' => (int)($d['id'] ?? $id),
            'name' => (string)($d['title'] ?? ''),
            'type' => (string)($d['mediaType'] ?? ''),
            'mediaType' => (string)($d['mediaType'] ?? ''),
            'originalTitle' => (string)($d['originalTitle'] ?? ''),
            'year' => (string)($d['releaseYear'] ?? ''),
            'overview' => (string)($d['overview'] ?? ''),
            'posterUrl' => (string)($d['posterUrl'] ?? ''),
            'backdropUrl' => (string)($d['backdropUrl'] ?? ''),
            'voteAverage' => $d['voteAverage'] ?? null,
            'tmdbId' => $d['tmdbId'] ?? null,
            'scrapeStatus' => (string)($d['scrapeStatus'] ?? ''),
            'episodes' => $eps,
        ]]);
    }
    case 'resolve': {
        $id = (int)($_GET['id'] ?? 0);
        $episode = (int)($_GET['episode'] ?? 0);
        if ($id <= 0) strm_json(['success' => false, 'message' => '缺少 id'], 400);
        strm_resolve_internal($id, $episode, $episode > 0 ? '第 ' . $episode . ' 集' : '');
    }
    case 'posters': {
        // 豆瓣海报兜底：列出无海报媒体 → node 桥搜豆瓣补图（存 runtime/strm-posters/<id>.jpg）→ 返回映射
        $r = strm_internal('?page=1&pageSize=100');
        if (!$r['success']) {
            strm_json(['success' => false, 'message' => $r['message'] ?? '媒体列表获取失败'], 502);
        }
        $items = $r['data']['items'] ?? [];
        $need = [];
        foreach ($items as $it) {
            $pid = (int)($it['id'] ?? 0);
            $poster = (string)($it['posterUrl'] ?? '');
            $imgFile = dirname(__DIR__, 2) . '/runtime/strm-posters/' . $pid . '.jpg';
            if ($pid > 0 && $poster === '' && !is_file($imgFile)) {
                $need[] = ['id' => $pid, 'title' => (string)($it['title'] ?? '')];
            }
        }
        $map = [];
        if (count($need) > 0) {
            $json = json_encode($need, JSON_UNESCAPED_UNICODE);
            $cmd = 'node ' . escapeshellarg(ROOT_PATH . '/scripts/strm_poster_fetch.cjs') . ' ' . escapeshellarg(base64_encode($json));
            $rr = withu_run_node_sync($cmd, 60000);
            $out = json_decode($rr['out'] ?? '', true);
            foreach (($out['results'] ?? []) as $res) {
                $rid = (int)($res['id'] ?? 0);
                if ($rid > 0 && !empty($res['ok'])) $map[$rid] = 1;
            }
        }
        // 输出全部无海报媒体的可访问图 URL（已生成 or 等待下次）
        $outMap = [];
        foreach ($items as $it) {
            $pid = (int)($it['id'] ?? 0);
            $poster = (string)($it['posterUrl'] ?? '');
            $imgFile = dirname(__DIR__, 2) . '/runtime/strm-posters/' . $pid . '.jpg';
            if ($pid > 0 && $poster === '' && is_file($imgFile)) {
                $outMap[$pid] = '/api/strm.php?action=img&id=' . $pid;
            }
        }
        strm_json(['success' => true, 'data' => $outMap]);
    }
    case 'img': {
        $id = (int)($_GET['id'] ?? 0);
        $type = ($_GET['type'] ?? '') === 'backdrop' ? 'backdrop' : 'poster';
        $file = withu_strm_img_file($id, $type);
        if (!is_file($file)) strm_json(['success' => false, 'message' => '海报不存在'], 404);
        $bytes = (string)file_get_contents($file);
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: ' . (withu_strm_img_mime($bytes) ?: 'image/jpeg'));
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: max-age=86400');
        echo $bytes;
        exit;
    }
    default:
        strm_json(['success' => false, 'message' => '未知操作'], 400);
}
