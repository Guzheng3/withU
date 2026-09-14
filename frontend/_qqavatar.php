<?php
/**
 * QQ 头像接口：/_qqavatar.php?qq=<QQ号>&s=<尺寸>
 * 服务端代理腾讯头像（q1.qlogo.cn）并本地缓存后同源输出：
 * 访客浏览器不再直连腾讯（部分网络/代理环境下 qlogo 慢或不通，会导致裂图）。
 * 服务端拉取失败时降级为 302，让浏览器自行尝试直连。
 * 站内引用：page-messages.js 的留言头像构建与默认头像（qq=10000 为腾讯官方号，必有头像）。
 */
$qq = isset($_GET['qq']) ? preg_replace('/\D/', '', (string) $_GET['qq']) : '';
$s = isset($_GET['s']) ? (int) $_GET['s'] : 100;
if (!in_array($s, [40, 50, 100, 140, 640], true)) {
    $s = 100;
}
if ($qq === '' || strlen($qq) > 12) {
    $qq = '10000'; // 与前端默认头像保持一致
}

$ttl = 86400 * 7;
$cacheDir = sys_get_temp_dir() . '/withu-qq-avatar';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}
$cacheFile = $cacheDir . '/' . md5($qq . '|' . $s) . '.img';

/** 按魔数识别 Content-Type */
function withu_qqavatar_mime(string $bytes): ?string
{
    $head = substr($bytes, 0, 4);
    if (strncmp($head, "\x89PNG", 4) === 0) return 'image/png';
    if (strncmp($head, 'GIF8', 4) === 0) return 'image/gif';
    if (strncmp($head, "\xFF\xD8", 2) === 0) return 'image/jpeg';
    if (strncmp($bytes, 'RIFF', 4) === 0 && substr($bytes, 8, 4) === 'WEBP') return 'image/webp';
    return null;
}

function withu_qqavatar_serve(string $bytes, string $mime): void
{
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . strlen($bytes));
    header('Cache-Control: public, max-age=86400');
    echo $bytes;
}

// 1) 新鲜缓存直接出
if (is_file($cacheFile) && time() - (int) filemtime($cacheFile) < $ttl) {
    $bytes = (string) file_get_contents($cacheFile);
    $mime = withu_qqavatar_mime($bytes);
    if ($mime !== null) {
        withu_qqavatar_serve($bytes, $mime);
        exit;
    }
}

// 2) 服务端代理拉取（浏览器不直连腾讯）
$ctx = stream_context_create(['http' => ['timeout' => 5, 'header' => "User-Agent: Mozilla/5.0\r\n"]]);
$bytes = (string) @file_get_contents('https://q1.qlogo.cn/g?b=qq&nk=' . $qq . '&s=' . $s, false, $ctx);
$mime = $bytes !== '' ? withu_qqavatar_mime($bytes) : null;
if ($mime !== null && strlen($bytes) > 100) {
    @file_put_contents($cacheFile, $bytes);
    withu_qqavatar_serve($bytes, $mime);
    exit;
}

// 3) 失败兜底：有过期缓存就用过期的；再不行 302 让浏览器直连试运气
if (is_file($cacheFile)) {
    $bytes = (string) file_get_contents($cacheFile);
    $mime = withu_qqavatar_mime($bytes);
    if ($mime !== null) {
        withu_qqavatar_serve($bytes, $mime);
        exit;
    }
}
header('Cache-Control: public, max-age=300');
header('Location: https://q1.qlogo.cn/g?b=qq&nk=' . $qq . '&s=' . $s, true, 302);
