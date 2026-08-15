<?php
// ============================================================
// api/strm.php —— withUstrm 媒体库对接网关（内置组件）
// ------------------------------------------------------------
// 把 withUstrm 的 Emby 风格外部接口（/api/external/**）转成 withu
// 播放器/影视库可用的 JSON，并提供服务端流式代理解决跨域播放。
//
// 鉴权：withu 情侣账号登录才可访问（与 watch.php / api/watch.php 一致），
//       API Key 只存 withu 后端，前端拿不到 —— 保证「只能从 withu 访问」。
//
// 用法：
//   GET ?action=info                       → 服务信息
//   GET ?action=counts                     → 媒体类型计数
//   GET ?action=media&type=&keyword=&page=&pageSize= → 媒体列表
//   GET ?action=detail&id=                 → 媒体详情（含剧集）
//   GET ?action=resolve&id=&episode=       → 播放直链 {success,url,type}
//                                           （电影：id=媒体id；剧集：episode=集id）
//   代理端点（播放用，服务端转发，免跨域）：
//   GET ?action=proxy&url=<直链>           → 通用流式代理（支持 Range）
//   GET ?action=proxy_m3u8&url=<m3u8>      → 拉 m3u8 并把分片改写为 proxy_seg
//   GET ?action=proxy_seg&url=<分片>       → 转发分片
// ============================================================

header('Content-Type: application/json; charset=UTF-8');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') exit;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/withu.php';

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

// ---------- 配置：后端地址 + API Key ----------
function strm_backend_base(): string
{
    return rtrim((string)getenv('STRM_BACKEND_URL') ?: 'http://127.0.0.1:8080', '/');
}

function strm_conf_path(): string
{
    // runtime/strm/config/systemconf.json（本仓库部署约定）
    $p = dirname(__DIR__, 2) . '/runtime/strm/config/systemconf.json';
    if (is_file($p)) return $p;
    // 回退：strm 自带的部署目录
    $alt = dirname(__DIR__, 2) . '/strm/runtime/config/systemconf.json';
    return is_file($alt) ? $alt : $p;
}

function strm_api_key(): string
{
    $path = strm_conf_path();
    if (!is_file($path)) return '';
    try {
        $conf = json_decode((string)file_get_contents($path), true);
        if (!is_array($conf)) return '';
        $ext = $conf['external'] ?? [];
        $key = (string)($ext['apiKey'] ?? '');
        return $key;
    } catch (Throwable $e) {
        return '';
    }
}

function strm_ready(): bool
{
    $key = strm_api_key();
    return $key !== '';
}

// ---------- curl 封装 ----------
function strm_curl(string $url, array $headers = [], string $method = 'GET', ?string $body = null): array
{
    $ch = curl_init($url);
    $h = array_merge(['X-API-Key: ' . strm_api_key()], $headers);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,   // 不跟随 302，由本网关自己拿 Location
        CURLOPT_HEADER => true,            // 拿到响应头（含 302 Location）
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
    // curl_close auto-released in PHP 8+
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

// 拉取 strm 外部接口 JSON
function strm_ext(string $path): array
{
    $url = strm_backend_base() . '/api/external' . $path;
    $r = strm_curl($url);
    if ($r['code'] !== 200) {
        $msg = $r['error'] !== '' ? $r['error'] : ($r['body'] !== '' ? $r['body'] : 'HTTP ' . $r['code']);
        return ['success' => false, 'message' => $msg, 'http' => $r['code']];
    }
    $data = json_decode($r['body'], true);
    if (!is_array($data)) {
        return ['success' => false, 'message' => '接口返回异常'];
    }
    return ['success' => true, 'data' => $data];
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

// 解析某播放端点（302 → 真实地址），返回给播放器的 {success,url,type}
function strm_resolve(string $externalPath, string $displayName): void
{
    $url = strm_backend_base() . '/api/external' . $externalPath;
    $r = strm_curl($url);
    $loc = (string)($r['headers']['location'] ?? '');
    if (($r['code'] !== 302 && $r['code'] !== 200) || $loc === '') {
        strm_json(['success' => false, 'message' => '播放地址解析失败（HTTP ' . $r['code'] . '）：' . ($r['error'] !== '' ? $r['error'] : '请检查 strm 后端/OpenList 配置')], 502);
    }
    // 302 Location 可能是相对路径
    if (!preg_match('#^https?://#i', $loc)) {
        $base = strm_backend_base();
        $loc = $base . ($loc[0] === '/' ? $loc : '/' . $loc);
    }
    $kind = strm_kind($loc);
    $self = strm_self_base();
    if ($kind === 'm3u8') {
        strm_json(['success' => true, 'source' => 'strm', 'url' => $self . '?action=proxy_m3u8&url=' . rawurlencode($loc), 'type' => 'm3u8', 'name' => $displayName]);
    }
    // 非 m3u8：走通用流式代理（服务端转发，免跨域 + 支持 Range）
    strm_json(['success' => true, 'source' => 'strm', 'url' => $self . '?action=proxy&url=' . rawurlencode($loc), 'type' => 'mp4', 'name' => $displayName]);
}

$action = (string)($_GET['action'] ?? 'info');

// ---------- 流式代理端点 ----------
if ($action === 'proxy') {
    $target = trim((string)($_GET['url'] ?? ''));
    if ($target === '' || !preg_match('#^https?://#i', $target)) strm_json(['code' => 400, 'msg' => '无效地址'], 400);
    $ch = curl_init($target);
    $reqHeaders = ['User-Agent: withu-strm-bridge/1.0', 'Accept: */*'];
    if (isset($_SERVER['HTTP_RANGE'])) $reqHeaders[] = 'Range: ' . $_SERVER['HTTP_RANGE'];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADER => false,
        CURLOPT_HTTPHEADER => $reqHeaders,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 600,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    // 需要拿上游的 Content-Type / Content-Length / Content-Range / Accept-Ranges
    $statusLine = 200;
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $headerLine) use (&$statusLine, &$respHeaders) {
        $trim = trim($headerLine);
        if ($trim === '') return strlen($headerLine);
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $trim, $m)) {
            $statusLine = (int)$m[1];
        } elseif (strpos($headerLine, ':') > 0) {
            $pos = strpos($headerLine, ':');
            $name = strtolower(trim(substr($headerLine, 0, $pos)));
            $val = trim(substr($headerLine, $pos + 1));
            if (in_array($name, ['content-type', 'content-length', 'content-range', 'accept-ranges', 'content-disposition'], true)) {
                $respHeaders[$name] = $val;
            }
        }
        return strlen($headerLine);
    });
    // 清空现有输出缓冲
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($statusLine);
    if (!empty($respHeaders['content-type'])) header('Content-Type: ' . $respHeaders['content-type']);
    if (!empty($respHeaders['content-range'])) header('Content-Range: ' . $respHeaders['content-range']);
    if (!empty($respHeaders['accept-ranges'])) header('Accept-Ranges: ' . $respHeaders['accept-ranges']);
    if (!empty($respHeaders['content-disposition'])) header('Content-Disposition: ' . $respHeaders['content-disposition']);
    if (!empty($respHeaders['content-length'])) header('Content-Length: ' . $respHeaders['content-length']);
    header('Cache-Control: no-store');
    header('Access-Control-Allow-Origin: *');
    curl_exec($ch);
    // curl_close auto-released in PHP 8+
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
    $ch = curl_init($seg);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADER => false,
        CURLOPT_HTTPHEADER => ['User-Agent: withu-strm-bridge/1.0', 'Accept: */*'],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $ct = 'application/octet-stream';
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $headerLine) use (&$ct) {
        $trim = trim($headerLine);
        if (stripos($trim, 'Content-Type:') === 0) {
            $ct = trim(substr($trim, 13));
        }
        return strlen($headerLine);
    });
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: ' . $ct);
    header('Cache-Control: max-age=3600');
    header('Access-Control-Allow-Origin: *');
    curl_exec($ch);
    // curl_close auto-released in PHP 8+
    exit;
}

// ---------- 业务接口 ----------
if (!strm_ready()) {
    strm_json(['success' => false, 'message' => 'strm 组件未就绪（external API 未启用或配置缺失），请先在后台「媒体库 STRM」系统设置中启用外部媒体库接口'], 503);
}

switch ($action) {
    case 'info': {
        $r = strm_ext('/info');
        strm_json($r['success'] ? ['success' => true, 'data' => $r['data']] : $r, $r['success'] ? 200 : 502);
    }
    case 'counts': {
        $r = strm_ext('/counts');
        strm_json($r['success'] ? ['success' => true, 'data' => $r['data']] : $r, $r['success'] ? 200 : 502);
    }
    case 'media': {
        $q = [];
        $type = trim((string)($_GET['type'] ?? ''));
        if ($type !== '' && in_array($type, ['movie', 'tv'], true)) $q[] = 'type=' . rawurlencode($type);
        $kw = trim((string)($_GET['keyword'] ?? ''));
        if ($kw !== '') $q[] = 'keyword=' . rawurlencode($kw);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $pageSize = max(1, min(100, (int)($_GET['pageSize'] ?? 24)));
        $q[] = 'page=' . $page;
        $q[] = 'pageSize=' . $pageSize;
        $r = strm_ext('/media?' . implode('&', $q));
        strm_json($r['success'] ? ['success' => true, 'data' => $r['data']] : $r, $r['success'] ? 200 : 502);
    }
    case 'detail': {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) strm_json(['success' => false, 'message' => '缺少 id'], 400);
        $r = strm_ext('/media/' . $id);
        strm_json($r['success'] ? ['success' => true, 'data' => $r['data']] : $r, $r['success'] ? 200 : 502);
    }
    case 'resolve': {
        $id = (int)($_GET['id'] ?? 0);
        $episode = (int)($_GET['episode'] ?? 0);
        if ($id <= 0) strm_json(['success' => false, 'message' => '缺少 id'], 400);
        if ($episode > 0) {
            strm_resolve('/episode/' . $episode . '/stream', '第 ' . $episode . ' 集');
        } else {
            strm_resolve('/stream/' . $id, '');
        }
    }
    default:
        strm_json(['success' => false, 'message' => '未知操作'], 400);
}
