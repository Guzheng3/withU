<?php
// ============================================================
// CzSource —— 厂长资源(4kcz.com) 解析算法库（解析源编码: cz）
// ------------------------------------------------------------
// 移植自 E:\Agent\厂长\厂长资源\02-player\parse.php，统一 cz_ 前缀，
// 与后续其他解析算法(如 xl01/zy)做命名空间区分。
//
// 链路：
//   详情页 /movie/N.html
//     └─ <a href=".../v_play/xxx.html">（1..N 条线路）
//          └─ 播放页含 <iframe class="viframe" src="...py.php?...url=XXX">
//               ├─ url 参数是 http(s) → 直接是 m3u8/MP4 直链
//               └─ url 参数非 http（加密线路）→ 带 Referer 拉 py.php → const mysvg
//
// 代理端点（api/cz.php 内实现）：
//   api=pm3u8&url=<m3u8>   代理 m3u8，分片改写为 api=pseg（跨域）
//   api=pseg&url=<分片>    代理分片，剥 PNG 伪头 → 纯 TS
//   api=play&url=<mp4>     302 直连 OSS MP4
// ============================================================

if (!defined('CZ_CACHE_DIR')) define('CZ_CACHE_DIR', ROOT_PATH . '/runtime/cache/cz/');
define('CZ_UA', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0');
define('CZ_CACHE_TTL', 3600); // 1 小时

/** 通用 GET：返回 ['status'=>int,'headers'=>array,'body'=>string] */
function cz_get(string $url, array $headers = [], int $timeout = 20000): array
{
    $hdr = ['User-Agent: ' . CZ_UA, 'Accept: */*'];
    foreach ($headers as $k => $v) $hdr[] = "$k: $v";

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT_MS     => $timeout,
            CURLOPT_CONNECTTIMEOUT_MS => 10000,
            CURLOPT_HTTPHEADER     => $hdr,
            CURLOPT_ENCODING       => '',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $body   = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($body !== false && strlen((string)$body) > 0) {
            // curl_close() 自 PHP 8.0 起无效果，8.5 起 deprecated，移除避免警告污染 JSON
            return ['status' => $status, 'headers' => [], 'body' => (string)$body];
        }
        // curl 失败（web 环境代理/证书差异）→ 回落 stream
    }

    $ctx = stream_context_create(['http' => [
        'method' => 'GET', 'header' => implode("\r\n", $hdr) . "\r\n",
        'timeout' => $timeout / 1000, 'follow_location' => 1, 'max_redirects' => 5,
        'ignore_errors' => true,
    ], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    $body = @file_get_contents($url, false, $ctx);
    $status = 0;
    $respHeaders = [];
    if (function_exists('http_get_last_response_headers')) {
        $rh = http_get_last_response_headers();
        $respHeaders = is_array($rh) ? $rh : [];
    }
    foreach ($respHeaders as $line) {
        if (is_string($line) && preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) $status = (int)$m[1];
    }
    return ['status' => $status ?: ($body === false ? 0 : 200), 'headers' => $respHeaders, 'body' => (string)$body];
}

/** 相对 URL 补全 */
function cz_absUrl(string $rel, string $base): string
{
    if (preg_match('#^https?://#', $rel)) return $rel;
    if (str_starts_with($rel, '//')) return (str_starts_with($base, 'https') ? 'https:' : 'http:') . $rel;
    $b = parse_url($base);
    $scheme = $b['scheme'] ?? 'https';
    $host = $b['host'] ?? '';
    $port = isset($b['port']) ? ':' . $b['port'] : '';
    if (str_starts_with($rel, '/')) return "$scheme://$host$port$rel";
    $dir = preg_replace('#/[^/]*$#', '', $b['path'] ?? '');
    return "$scheme://$host$port$dir/$rel";
}

/** 从播放页 HTML 提取 viframe src 并解码 url= 参数 */
function cz_parsePlayPage(string $html): array
{
    $m = null;
    if (preg_match('/<iframe[^>]*class=["\']viframe["\'][^>]*src=["\']([^"\']+)["\']/i', $html, $m) ||
        preg_match('/class=["\']viframe["\'][^>]*src=["\']([^"\']+)["\']/i', $html, $m) ||
        preg_match('/<iframe[^>]*src=["\']([^"\']+)["\'][^>]*class=["\']viframe["\']/i', $html, $m)) {
        $iframeSrc = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
    } else {
        preg_match_all('/<iframe[^>]*src=["\']([^"\']+)["\']/i', $html, $all);
        throw new RuntimeException('未找到 viframe，页面 iframe: ' . json_encode(array_slice($all[1] ?? [], 0, 5), JSON_UNESCAPED_UNICODE));
    }
    $src = $iframeSrc;
    if (preg_match('/[?&]url=([^&]+)/', $iframeSrc, $um)) {
        $src = urldecode($um[1]);
    }
    return ['iframeSrc' => $iframeSrc, 'src' => $src];
}



/** 详情页 HTML → 元数据 + 海报（WordPress mibt 模板：4kcz.com） */
function cz_parseDetailMeta(string $html): array
{
    $meta = [
        'poster' => '', 'types' => [], 'area' => '', 'year' => '', 'aka' => '',
        'release' => '', 'director' => '', 'writer' => '', 'actors' => '', 'lang' => '', 'intro' => '',
    ];
    // 主海报：moviedteail_tt 前面的 img（dyimg 容器优先）
    $m = null;
    if (preg_match('/<div[^>]*class=["\'][^"\']*dyimg[^"\']*["\'][^>]*>\s*<img[^>]*src=["\']([^"\']+)/i', $html, $m) ||
        preg_match('/<img[^>]*src=["\']([^"\']+)[^>]*>\s*<\/div>\s*<div class="dytext/i', $html, $m) ||
        preg_match('/<img[^>]*class=["\'][^"\']*lazy[^"\']*["\'][^>]*data-original=["\']([^"\']+)/i', $html, $m) ||
        preg_match('/<img[^>]*src=["\']([^"\']*wp-content\/uploads\/[^"\']+)/i', $html, $m)) {
        $meta['poster'] = trim($m[1] ?? '');
    }
    // 元数据列表 <ul class="moviedteail_list">
    $ul = '';
    if (preg_match('/<ul class=["\']moviedteail_list["\']>([\s\S]*?)<\/ul>/i', $html, $m)) $ul = $m[1];
    if ($ul !== '') {
        $map = [
            'types' => '类型', 'area' => '地区', 'year' => '年份', 'aka' => '又名',
            'release' => '上映', 'director' => '导演', 'writer' => '编剧',
            'actors' => '主演', 'lang' => '语言',
        ];
        foreach ($map as $key => $label) {
            if (!preg_match('/<li>' . $label . '：([\s\S]*?)<\/li>/u', $ul, $lm)) continue;
            $val = trim(preg_replace('/<[^>]+>/', ' ', $lm[1]));
            $val = trim(preg_replace('/\s+/u', ' ', $val));
            $val = html_entity_decode($val, ENT_QUOTES | ENT_HTML5);
            if ($key === 'types') {
                $meta['types'] = array_values(array_filter(array_map('trim', explode(' ', $val)), 'strlen'));
            } else {
                $meta[$key] = $val;
            }
        }
    }
    // 简介：<div class="yp_context">…</div>
    if (preg_match('/<div class=["\']yp_context["\']>([\s\S]*?)<\/div>/i', $html, $m)) {
        $intro = trim(preg_replace('/<[^>]+>/', '', $m[1]));
        $intro = trim(preg_replace('/\s+/u', ' ', $intro));
        $meta['intro'] = html_entity_decode($intro, ENT_QUOTES | ENT_HTML5);
    }
    return $meta;
}

/** 详情页 → 播放页链接列表（完整 URL 形式） */
function cz_detailToVplays(string $detailUrl): array
{
    $page = cz_get($detailUrl, ['Referer' => 'https://www.4kcz.com/']);
    if ($page['status'] !== 200) throw new RuntimeException('详情页 status=' . $page['status']);
    $html = $page['body'];
    preg_match('/<title>([^<]*)<\/title>/i', $html, $tm);
    $title = trim($tm[1] ?? '');
    $vplays = [];
    if (preg_match_all('/href="([^"]*\/v_play\/[^"]+)"/', $html, $m)) {
        foreach ($m[1] as $h) {
            $full = preg_match('#^https?://#', $h) ? $h : cz_absUrl($h, $detailUrl);
            $vplays[$full] = true;
        }
    }
    $vplays = array_keys($vplays);
    if (!$vplays) throw new RuntimeException('详情页未找到 v_play 播放链接');
    return ['title' => $title, 'vplays' => $vplays];
}

/** 播放页 → 播放源直链（m3u8 或 mp4） */
function cz_vplayToM3u8(string $vplayUrl): string
{
    $page = cz_get($vplayUrl, ['Referer' => $vplayUrl]);
    if ($page['status'] !== 200) throw new RuntimeException('播放页 status=' . $page['status']);
    $p = cz_parsePlayPage($page['body']);
    if (preg_match('#^https?://#', $p['src'])) return $p['src'];

    // 加密线路（alist... 等）→ 拉 py.php 服务端渲染的 mysvg
    $py = cz_get($p['iframeSrc'], ['Referer' => $vplayUrl]);
    if ($py['status'] !== 200) throw new RuntimeException('py.php status=' . $py['status']);
    $html = $py['body'];
    if (preg_match('/const\s+mysvg\s*=\s*["\']([^"\']+)["\']/', $html, $m) ||
        preg_match('/mysvg\s*=\s*["\']([^"\']+)["\']/', $html, $m)) {
        $real = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
        if (preg_match('#^https?://#', $real)) return $real;
    }
    throw new RuntimeException('py.php 未提取到 mysvg 直链');
}

/** 通用剥头：找 TS 流起点（0x47 且 188 字节连续对齐） */
function cz_findTsStart(string $b): int
{
    $len = strlen($b);
    $limit = min($len - 400, 512);
    for ($i = 0; $i < $limit; $i++) {
        if (ord($b[$i]) === 0x47 && ord($b[$i + 188]) === 0x47 && ord($b[$i + 376]) === 0x47) return $i;
    }
    return -1;
}
function cz_stripTsHead(string $b): string
{
    $s = cz_findTsStart($b);
    return $s > 0 ? substr($b, $s) : $b;
}

/** m3u8 → 分片列表 [['dur'=>float,'url'=>string], ...] */
function cz_m3u8ToSegments(string $m3u8): array
{
    $r = cz_get($m3u8);
    if ($r['status'] !== 200) throw new RuntimeException('m3u8 status=' . $r['status']);
    $segments = [];
    $dur = 3.0;
    foreach (preg_split('/\r?\n/', $r['body']) as $raw) {
        $t = trim($raw);
        if ($t === '') continue;
        if (str_starts_with($t, '#EXTINF')) {
            if (preg_match('/#EXTINF:\s*([\d.]+)/', $t, $mm)) $dur = (float)($mm[1] ?: 3);
        } elseif (str_starts_with($t, '#')) {
            continue;
        } else {
            $segments[] = ['dur' => $dur, 'url' => cz_absUrl($t, $m3u8)];
        }
    }
    if (!$segments) throw new RuntimeException('m3u8 无分片');
    return $segments;
}

/** 站内搜索：GET /boss1O1?q=<关键词> → [['url'=>,'title'=>], ...] */
function cz_search(string $keyword, string $base = 'https://www.4kcz.com/'): array
{
    $cacheKey = 'search_' . md5($keyword);
    $cached = cz_cache_get($cacheKey);
    if ($cached !== null) return $cached;

    $r = cz_get($base . 'boss1O1?q=' . urlencode($keyword), ['Referer' => $base]);
    if ($r['status'] !== 200) throw new RuntimeException('搜索 status=' . $r['status']);
    $items = [];
    if (preg_match_all('/<a[^>]*href="([^"]*\/movie\/\d+\.html)"[^>]*>([\s\S]*?)<\/a>/i', $r['body'], $m, PREG_SET_ORDER)) {
        $seen = [];
        foreach ($m as $mm) {
            $url = preg_match('#^https?://#', $mm[1]) ? $mm[1] : cz_absUrl($mm[1], $base);
            if (isset($seen[$url])) continue;
            $seen[$url] = true;
            $title = trim(preg_replace('/<[^>]+>/', ' ', $mm[2]));
            $title = trim(preg_replace('/\s+/', ' ', $title));
            if ($title === '') {
                if (preg_match('/<img[^>]*alt=["\']([^"\']+)["\']/i', $mm[2], $im)) $title = trim($im[1]);
            }
            $title = $title !== '' ? html_entity_decode($title, ENT_QUOTES | ENT_HTML5) : $url;
            $items[] = ['url' => $url, 'title' => $title];
        }
    }
    if (!$items) throw new RuntimeException('搜索无结果: ' . $keyword);
    cz_cache_set($cacheKey, $items);
    return $items;
}

/** 一步到位：详情页 URL → ['title'=>, 'vplays'=>[], 'entries'=>[...]] */
function cz_resolveDetail(string $detailUrl, int $maxLines = 12): array
{
    $cacheKey = 'detail_' . md5($detailUrl);
    $cached = cz_cache_get($cacheKey);
    if ($cached !== null) return $cached;

    $d = cz_detailToVplays($detailUrl);
    $entries = [];
    foreach (array_slice($d['vplays'], 0, $maxLines) as $vp) {
        try {
            $m3u8 = cz_vplayToM3u8($vp);
            $entry = ['vplay' => $vp, 'm3u8' => $m3u8];
            if (preg_match('/\.m3u8(\?|$)/i', $m3u8)) {
                $segments = cz_m3u8ToSegments($m3u8);
                $entry['segTotal'] = count($segments);
                $entry['segments'] = $segments;
            } else {
                $entry['kind'] = 'mp4';
            }
            $entries[] = $entry;
        } catch (Throwable $e) {
            $entries[] = ['vplay' => $vp, 'error' => $e->getMessage()];
        }
    }
    $result = ['title' => $d['title'], 'vplays' => $d['vplays'], 'entries' => $entries];
    cz_cache_set($cacheKey, $result);
    return $result;
}

/** 单条线路解析（懒加载） */
function cz_resolveLine(string $vplayUrl): array
{
    $cacheKey = 'line_' . md5($vplayUrl);
    $cached = cz_cache_get($cacheKey);
    if ($cached !== null) return $cached;

    $m3u8 = cz_vplayToM3u8($vplayUrl);
    $entry = ['vplay' => $vplayUrl, 'm3u8' => $m3u8];
    if (preg_match('/\.m3u8(\?|$)/i', $m3u8)) {
        $segments = cz_m3u8ToSegments($m3u8);
        $entry['segTotal'] = count($segments);
        $entry['segments'] = $segments;
    } else {
        $entry['kind'] = 'mp4';
    }
    cz_cache_set($cacheKey, $entry);
    return $entry;
}

// ---------- 缓存 ----------
function cz_cache_get(string $key): ?array
{
    if (!defined('CZ_CACHE_TTL') || CZ_CACHE_TTL <= 0) return null;
    $file = CZ_CACHE_DIR . $key . '.json';
    if (is_file($file) && (time() - filemtime($file)) < CZ_CACHE_TTL) {
        $data = json_decode((string)file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }
    return null;
}
function cz_cache_set(string $key, array $value): void
{
    if (!defined('CZ_CACHE_TTL') || CZ_CACHE_TTL <= 0) return;
    if (!is_dir(CZ_CACHE_DIR)) @mkdir(CZ_CACHE_DIR, 0777, true);
    @file_put_contents(CZ_CACHE_DIR . $key . '.json', json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
