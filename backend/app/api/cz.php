<?php
// ============================================================
// api/cz.php —— 厂长资源(4kcz.com) 解析接口（解析源编码: cz）
// ------------------------------------------------------------
// 用法：
//   GET ?action=search&q=剧名           → {success, list:[{url,title,source:'cz'}]}
//   GET ?action=detail&url=详情页       → {success, data:{title,vplays,source:'cz'}}
//   GET ?action=resolve&url=详情页      → {success, data:{title,entries:[...]}} 全线路
//   GET ?action=resolve_line&url=播放页 → {success, data:{m3u8,kind,...}} 单线路
//   GET ?action=catalog&page=1&q=       → 本地已采集资源库列表
//   GET ?action=collect&q=剧名&max=20   → 主动采集：搜索并逐部拉线路入库
// 代理端点（跨域播放用，均带 CORS）：
//   GET ?api=pm3u8&url=<m3u8>   代理 m3u8（分片改写为 api=pseg）
//   GET ?api=pseg&url=<分片>    代理分片（剥 PNG 伪头 → 纯 TS）
//   GET ?api=play&url=<mp4>     302 直连
// 说明：search/detail/resolve_line 均本地数据库优先，miss 才打源站并回填，
//       避免每次搜索/播放都请求 4kcz.com。
// ============================================================
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: *');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') exit;

require_once __DIR__ . '/../config/config.php';

if (!defined('WITHU_CZ_ENABLED')) define('WITHU_CZ_ENABLED', false); // 代码默认屏蔽，可在 config/config.php 覆盖启用
// 厂长资源(cz)总开关：WITHU_CZ_ENABLED=false 时整个接口暂时屏蔽
if (!defined('WITHU_CZ_ENABLED') || !WITHU_CZ_ENABLED) {
    http_response_code(404);
    echo json_encode(['success' => false, 'code' => 404, 'message' => 'cz 源已暂时屏蔽'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
require_once __DIR__ . '/../core/CzSource.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/CzCatalog.php';

function cz_json($data, int $code = 200)
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ---------- 代理端点 ----------
if (isset($_GET['api'])) {
    $api = $_GET['api'];
    $self = 'cz.php';
    $scheme = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = $scheme . $host . '/api/' . $self;
    switch ($api) {
        case 'pm3u8': {
            $m3u8 = trim((string)($_GET['url'] ?? ''));
            if ($m3u8 === '') cz_json(['code' => 404, 'msg' => '缺少url'], 404);
            try {
                $r = cz_get($m3u8);
            } catch (Throwable $e) {
                cz_json(['code' => 502, 'msg' => 'm3u8 拉取失败: ' . $e->getMessage()], 502);
            }
            if (empty($r['body'])) cz_json(['code' => 502, 'msg' => 'm3u8 拉取失败'], 502);
            header('Content-Type: application/vnd.apple.mpegurl; charset=utf-8');
            header('Cache-Control: no-store');
            $out = '';
            foreach (preg_split('/\r?\n/', $r['body']) as $raw) {
                $t = trim($raw);
                if ($t === '' || str_starts_with($t, '#')) { $out .= $raw . "\n"; continue; }
                $out .= $base . '?api=pseg&url=' . rawurlencode(cz_absUrl($t, $m3u8)) . "\n";
            }
            echo $out;
            exit;
        }
        case 'pseg': {
            $seg = trim((string)($_GET['url'] ?? ''));
            if ($seg === '') cz_json(['code' => 404, 'msg' => '缺少url'], 404);
            $segHdr = [];
            if (strpos($seg, 'chaoxing.com') !== false) $segHdr[] = 'Referer: https://www.chaoxing.com/';
            try {
                $r = cz_get($seg, $segHdr);
            } catch (Throwable $e) {
                cz_json(['code' => 502, 'msg' => '分片拉取失败: ' . $e->getMessage()], 502);
            }
            if (empty($r['body'])) cz_json(['code' => 502, 'msg' => '分片拉取失败'], 502);
            $ts = cz_stripTsHead($r['body']);
            header('Content-Type: video/mp2t');
            header('Cache-Control: max-age=3600');
            echo $ts;
            exit;
        }
        case 'play': {
            $url = trim((string)($_GET['url'] ?? ''));
            if ($url === '') cz_json(['code' => 404, 'msg' => '缺少url'], 404);
            header('Location: ' . $url, true, 302);
            exit;
        }
    }
    cz_json(['code' => 404, 'msg' => '未知代理端点'], 404);
}

// ---------- 业务接口 ----------
$action = (string)($_GET['action'] ?? 'search');
$db = Database::getInstance();
try {
    switch ($action) {
        case 'search': {
            $q = trim((string)($_GET['q'] ?? ''));
            if ($q === '') cz_json(['success' => false, 'message' => '缺少关键词'], 400);
            // 1) 本地库优先
            $local = withu_cz_catalog_search_local($db, $q, 30);
            if ($local) {
                $list = [];
                foreach ($local as $row) {
                    $list[] = [
                        'url' => (string)$row['detail_url'],
                        'title' => (string)$row['title'],
                        'source' => 'cz',
                        'cached' => true,
                        'line_count' => (int)$row['line_count'],
                        'poster' => (string)($row['poster'] ?? ''),
                        'year' => (string)($row['year'] ?? ''),
                        'types' => !empty($row['types_json']) ? (json_decode((string)$row['types_json'], true) ?: []) : [],
                        'rating' => (string)($row['rating'] ?? ''),
                        'episode_status' => (string)($row['episode_status'] ?? ''),
                    ];
                }
                cz_json(['success' => true, 'source' => 'cz', 'list' => $list, 'cached' => true]);
            }
            // 2) miss → 源站搜索 → 资源标题入库（线路按需 detail 时再拉）
            $list = cz_search($q);
            $saved = [];
            foreach ($list as $item) {
                $url = withu_cz_norm_url((string)($item['url'] ?? ''));
                if ($url === '') continue;
                try {
                    $r = withu_cz_catalog_upsert($db, (string)($item['title'] ?? $url), $url, []);
                    $saved[] = ['url' => $url, 'title' => (string)($item['title'] ?? $url), 'source' => 'cz', 'cached' => false, 'line_count' => 0];
                } catch (Throwable $e) {
                    $saved[] = ['url' => $url, 'title' => (string)($item['title'] ?? $url), 'source' => 'cz', 'cached' => false];
                }
            }
            cz_json(['success' => true, 'source' => 'cz', 'list' => $saved]);
        }
        case 'detail': {
            $url = trim((string)($_GET['url'] ?? ''));
            if ($url === '' || strpos($url, '4kcz.com') === false) {
                cz_json(['success' => false, 'message' => '请输入 4kcz.com 详情页或播放页网址'], 400);
            }
            $d = withu_cz_catalog_refresh_detail($db, $url, (($_GET['force'] ?? '') === '1'));
            $meta = [];
            if (!empty($d['meta'])) {
                $meta = $d['meta'];
            } else {
                $res = withu_cz_catalog_get_by_url($db, $url);
                if ($res) {
                    $meta = [
                        'poster' => (string)($res['poster'] ?? ''),
                        'director' => (string)($res['director'] ?? ''),
                        'writer' => (string)($res['writer'] ?? ''),
                        'actors' => (string)($res['actors'] ?? ''),
                        'year' => (string)($res['year'] ?? ''),
                        'area' => (string)($res['area'] ?? ''),
                        'types' => !empty($res['types_json']) ? (json_decode((string)$res['types_json'], true) ?: []) : [],
                        'aka' => (string)($res['aka'] ?? ''),
                        'release' => (string)($res['release'] ?? ''),
                        'lang' => (string)($res['lang'] ?? ''),
                        'intro' => (string)($res['intro'] ?? ''),
                        'rating' => (string)($res['rating'] ?? ''),
                        'episode_status' => (string)($res['episode_status'] ?? ''),
                    ];
                }
            }
            cz_json(['success' => true, 'source' => 'cz', 'data' => [
                'title' => $d['title'], 'vplays' => $d['vplays'], 'url' => withu_cz_norm_url($url),
                'cached' => !empty($d['cached']), 'resource_id' => (int)($d['resource_id'] ?? 0),
                'meta' => $meta,
            ]]);
        }
        case 'resolve': {
            $url = trim((string)($_GET['url'] ?? ''));
            if ($url === '' || strpos($url, '4kcz.com') === false) {
                cz_json(['success' => false, 'message' => '请输入 4kcz.com 详情页或播放页网址'], 400);
            }
            $r = cz_resolveDetail($url, (int)($_GET['lines'] ?? 12));
            cz_json(['success' => true, 'source' => 'cz', 'data' => $r]);
        }
        case 'resolve_line': {
            $url = trim((string)($_GET['url'] ?? ''));
            if ($url === '' || strpos($url, '4kcz.com') === false) {
                cz_json(['success' => false, 'message' => '请输入 4kcz.com 播放页网址'], 400);
            }
            $url = withu_cz_norm_url($url);
            $fromPlayer = (($_GET['from'] ?? '') === 'player');
            $resourceId = (int)($_GET['resource_id'] ?? 0);
            if ($resourceId <= 0) {
                $res = withu_cz_catalog_get_by_url($db, $url);
                $resourceId = $res ? (int)$res['id'] : 0;
            }
            // 1) 本地线路缓存（2h）命中即用；miss 才打源站并入库（resource_id 允许为 0）
            $entry = withu_cz_catalog_get_line($db, $url, 7200);
            if (!$entry) {
                try {
                    $entry = cz_resolveLine($url);
                } catch (Throwable $err) {
                    $entry = ['vplay' => $url, 'error' => $err->getMessage()];
                }
                withu_cz_catalog_save_line($db, $resourceId, $url, $entry);
            }
            // 播放器直链模式（WithU 原播放器 resolvePlaybackSource 期望 {success,url,type}）
            if ($fromPlayer) {
                $m3u8 = (string)($entry['m3u8'] ?? '');
                if ($m3u8 === '') {
                    cz_json(['success' => false, 'message' => (string)($entry['error'] ?? '未解析到直链')]);
                }
                $scheme = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://');
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $proxyBase = $scheme . $host . '/api/cz.php';
                if (preg_match('/\.m3u8(\?|$)/i', $m3u8)) {
                    cz_json(['success' => true, 'source' => 'cz', 'url' => $proxyBase . '?api=pm3u8&url=' . rawurlencode($m3u8), 'type' => 'm3u8']);
                }
                cz_json(['success' => true, 'source' => 'cz', 'url' => $proxyBase . '?api=play&url=' . rawurlencode($m3u8), 'type' => 'mp4']);
            }
            cz_json(['success' => true, 'source' => 'cz', 'data' => $entry]);
        }
        case 'catalog': {
            $page = (int)($_GET['page'] ?? 1);
            $per = (int)($_GET['per_page'] ?? 20);
            $q = trim((string)($_GET['q'] ?? ''));
            $c = withu_cz_catalog_list($db, $page, $per, $q);
            cz_json(['success' => true, 'source' => 'cz', 'data' => $c]);
        }
        case 'collect': {
            $q = trim((string)($_GET['q'] ?? ''));
            $max = (int)($_GET['max'] ?? 20);
            if ($q === '') cz_json(['success' => false, 'message' => '缺少关键词'], 400);
            $r = withu_cz_catalog_collect($db, $q, $max);
            cz_json(['success' => true, 'source' => 'cz', 'data' => $r]);
        }
        default:
            cz_json(['success' => false, 'message' => '未知操作'], 400);
    }
} catch (Throwable $e) {
    cz_json(['success' => false, 'message' => $e->getMessage()], 502);
}
