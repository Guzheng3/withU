<?php
/**
 * WebP 副本可用性查询服务（前台默认加载 WebP 用）
 *
 * 前端把页面上本站 /uploads/ 图片路径批量发过来，这里逐个检查是否存在
 * 同名 .webp 副本（由上传流程 optimize_uploaded_image 生成），返回可用映射；
 * 「前台默认加载 WebP 副本」开关（settings.front_webp_default）关闭时返回
 * enabled=false，前端将保持加载原图。
 *
 * 用法: POST JSON { "paths": ["/uploads/a.jpg", ...] }
 *       GET  ?paths=/uploads/a.jpg,/uploads/b.png
 * 返回: {code:200, enabled:true, map:{"/uploads/a.jpg":"/uploads/a.webp"}}
 */

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache');

require_once __DIR__ . '/../inc/config.php';

// upload_url() / get_setting() / UPLOAD_DIR 位于后端 helpers（config 常量已由 inc/config.php 载入）
$helpersFile = dirname(__DIR__, 2) . '/backend/app/core/helpers.php';
if (is_file($helpersFile)) {
    require_once $helpersFile;
}

// 开关：后台 → 网站设置 → 上传设置「前台默认加载 WebP 副本」
$enabled = true;
try {
    if (function_exists('get_setting')) {
        $enabled = (string) get_setting('front_webp_default', '1') === '1';
    }
} catch (Throwable $e) {
    $enabled = true;
}

// ── 收集待检查路径 ─────────────────────────────────────────
$paths = [];
$rawBody = file_get_contents('php://input');
if ($rawBody) {
    $json = json_decode($rawBody, true);
    if (is_array($json)) {
        if (isset($json['paths']) && is_array($json['paths'])) {
            $paths = $json['paths'];
        } elseif (isset($json[0])) {
            // 兼容直接传路径数组的形式
            $paths = $json;
        }
    }
}
if (!$paths && isset($_GET['paths'])) {
    $paths = explode(',', (string) $_GET['paths']);
}

$map = [];
if ($enabled && is_array($paths)) {
    $uploadRootReal = realpath(rtrim(UPLOAD_DIR, '/\\'));
    foreach ($paths as $p) {
        $p = trim((string) $p);
        if ($p === '' || count($map) >= 300) {
            continue;
        }
        // 允许完整 URL：去掉 scheme+host，仅保留路径部分
        if (preg_match('#^https?://[^/]+(/.*)$#i', $p, $m)) {
            $p = $m[1];
        }
        if (strpos($p, '/uploads/') !== 0) {
            continue;
        }
        $rel = ltrim(substr($p, strlen('/uploads/')), '/');
        if ($rel === '' || strpos($rel, '..') !== false) {
            continue;
        }
        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        // 仅 jpg/jpeg/png 需要 WebP 副本（原文件本身就是 webp 的无需处理）
        if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            continue;
        }
        $abs = rtrim(UPLOAD_DIR, '/\\') . '/' . $rel;
        // 防目录穿越：真实路径必须仍在 uploads 目录内
        $real = realpath($abs);
        if ($real === false || $uploadRootReal === false || strpos($real, $uploadRootReal) !== 0) {
            continue;
        }
        if (!is_file($real)) {
            continue;
        }
        $pi = pathinfo($real);
        $webpAbs = $pi['dirname'] . DIRECTORY_SEPARATOR . $pi['filename'] . '.webp';
        if (!is_file($webpAbs)) {
            continue;
        }
        // 映射统一使用以 /uploads/ 开头的站内路径（前端按 pathname 匹配）
        $map['/uploads/' . $rel] = '/uploads/' . $rel . '.webp';
    }
}

echo json_encode([
    'code'    => 200,
    'enabled' => $enabled,
    'map'     => $map,
], JSON_UNESCAPED_UNICODE);
exit;
