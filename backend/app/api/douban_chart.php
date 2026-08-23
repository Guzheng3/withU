<?php
// ============================================================
// api/douban_chart.php —— 豆瓣新剧/新电影榜单（首页用，带缓存）
// ------------------------------------------------------------
//   GET ?type=movie|tv&limit=12 → {success, type, list:[{title,url,cover,id,rate,episodes_info,source:'cz'}]}
// 数据源：https://movie.douban.com/j/search_subjects（实测可直连，无需鉴权）
// 缓存：runtime/cache/douban_chart_<type>.json，1 小时
// ============================================================
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');
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

// 榜单为公开内容，不强制登录；浏览器访问 watch.php 时已带登录态
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

$type = (string)($_GET['type'] ?? 'movie');
if (!in_array($type, ['movie', 'tv'], true)) {
    withu_json_response(['success' => false, 'message' => 'type 仅支持 movie/tv'], 400);
}
$limit = max(1, min(30, (int)($_GET['limit'] ?? 12)));

$cacheDir = ROOT_PATH . '/runtime/cache/douban/';
$cacheFile = $cacheDir . 'chart_' . $type . '.json';
if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
    $cached = json_decode((string)file_get_contents($cacheFile), true);
    if (is_array($cached) && ($cached['type'] ?? '') === $type) {
        $cached['cached'] = true;
        withu_json_response($cached);
    }
}

// 电影 tag=最新（新片），剧集 tag=热门（新剧/热播）
// 豆瓣对 PHP curl/stream 指纹返回登录跳转页，改由 node 桥脚本抓取并写缓存
$cmd = 'node ' . escapeshellarg(ROOT_PATH . '/scripts/douban_chart_fetch.cjs') . ' ' . escapeshellarg($type) . ' ' . $limit;
$nodeRet = withu_run_node_sync($cmd, 25000);
$fresh = null;
if (is_file($cacheFile)) {
    $fresh = json_decode((string)file_get_contents($cacheFile), true);
}
if (is_array($fresh) && ($fresh['type'] ?? '') === $type && !empty($fresh['list'])) {
    $list = $fresh['list'];
} else {
    withu_json_response(['success' => false, 'message' => '豆瓣榜单暂时无法获取'], 502);
}
if (!$list) {
    withu_json_response(['success' => false, 'message' => '豆瓣榜单无数据'], 502);
}

$result = ['success' => true, 'type' => $type, 'list' => $list, 'cached' => false, 'fetched_at' => date('Y-m-d H:i:s')];
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0777, true);
@file_put_contents($cacheFile, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
withu_json_response($result);
