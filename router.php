<?php
/**
 * withU 统一路由入口
 * 前台(frontend/) + 后台(backend/app/) 都在 1314 端口
 * 用法: php -S 0.0.0.0:1314 -t /home/gx/MonkeyCode/withu router.php
 */
$uri  = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$path = rawurldecode($path);

$base = __DIR__;
$frontRoot = $base . '/frontend';
$appRoot   = $base . '/backend/app';

// 静态资源 MIME
$mimeTypes = [
    'css' => 'text/css; charset=utf-8', 'js' => 'application/javascript; charset=utf-8',
    'json' => 'application/json', 'svg' => 'image/svg+xml',
    'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
    'gif' => 'image/gif', 'webp' => 'image/webp', 'ico' => 'image/x-icon',
    'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf',
    'otf' => 'font/otf', 'eot' => 'application/vnd.ms-fontobject',
    'mp3' => 'audio/mpeg', 'mp4' => 'video/mp4',
    'lrc' => 'text/plain; charset=utf-8', 'txt' => 'text/plain; charset=utf-8',
    'map' => 'application/json', 'wasm' => 'application/wasm',
    'xml' => 'application/xml',
];

function serveStatic(string $file, array $mime): bool {
    if (!is_file($file)) return false;
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    $ct  = $mime[$ext] ?? 'application/octet-stream';
    // 计算可读的文件大小
    $len = filesize($file);
    header('Content-Type: ' . $ct);
    header('Content-Length: ' . $len);
    header('Cache-Control: max-age=3600, public');
    readfile($file);
    return true;
}

function requirePhp(string $file): bool {
    if (!is_file($file)) return false;
    $_SERVER['SCRIPT_FILENAME'] = $file;
    chdir(dirname($file));
    require $file;
    return true;
}

// ── 后台 /admin-assets/ → /assets/ 映射（必须在 /admin/ 判断之前） ──
if (strpos($path, '/admin-assets/') === 0) {
    $rewritten = '/assets/' . substr($path, strlen('/admin-assets/'));
    if (serveStatic($appRoot . $rewritten, $mimeTypes)) return true;
}

// ── 后台 PHP 路径 ────────────────────────
$isBackend = strpos($path, '/admin') === 0
    || $path === '/login.php' || $path === '/logout.php'
    || $path === '/install.php' || strpos($path, '/install') === 0
    || $path === '/watch.php' || $path === '/watch_play.php' || $path === '/watch_history.php'
    || strpos($path, '/watch.php') === 0 || strpos($path, '/watch_play.php') === 0 || strpos($path, '/watch_history.php') === 0;

if ($isBackend) {
    if ($path === '/login.php' && requirePhp($appRoot . '/login.php')) return true;
    if ($path === '/logout.php' && requirePhp($appRoot . '/logout.php')) return true;
    if ($path === '/install.php' && requirePhp($appRoot . '/install.php')) return true;

    // /admin/ 或 /admin/xxx
    $adminFile = $appRoot . $path;
    // 尝试 PHP 文件
    if (strpos($path, '.php') !== false) {
        if (requirePhp($adminFile)) return true;
    }
    // 尝试 index.php 在目录下
    if (is_dir($adminFile)) {
        if (requirePhp(rtrim($adminFile, '/') . '/index.php')) return true;
    }
    // 尝试 .php 后缀
    if (requirePhp($adminFile . '.php')) return true;
    // 处理 /admin/xxx.php/子路径（PATH_INFO 模式）
    $phpInfoPos = strpos($path, '.php/');
    if ($phpInfoPos !== false) {
        $phpFile = $appRoot . substr($path, 0, $phpInfoPos + 4);
        $pathInfo = substr($path, $phpInfoPos + 4);
        if ($pathInfo === '') $pathInfo = '/';
        if (is_file($phpFile)) {
            $_SERVER['PATH_INFO'] = $pathInfo;
            $_SERVER['ORIG_PATH_INFO'] = $pathInfo;
            if (requirePhp($phpFile)) return true;
        }
    }
    // 后台 404 时 fallback 到 admin/index.php
    if (strpos($path, '/admin') === 0 && requirePhp($appRoot . '/admin/index.php')) return true;
}

// ── 后台静态资源 /assets/ ────────────────
if (strpos($path, '/assets/') === 0) {
    if (serveStatic($appRoot . $path, $mimeTypes)) return true;
}

// ── 后台 API /api/ ───────────────────────
if (strpos($path, '/api/') === 0) {
    $apiFile = $appRoot . $path;
    // 先尝试 .php 后缀
    if (requirePhp($apiFile . '.php')) return true;
    // 再尝试无后缀文件
    if (requirePhp($apiFile)) return true;
}

// ── 后台核心 /config/, /core/ ────────────
if (strpos($path, '/config/') === 0 || strpos($path, '/core/') === 0) {
    if (requirePhp($appRoot . $path)) return true;
    if (requirePhp($appRoot . $path . '.php')) return true;
}

// ── 前台 /ext/ → _external ───────────────
if (strpos($path, '/ext/') === 0) {
    $extFile = $frontRoot . '/_external' . substr($path, 4);
    if (serveStatic($extFile, $mimeTypes)) return true;
    if (requirePhp($extFile)) return true;
}

// ── 前台静态资源 ─────────────────────────
$frontStaticDirs = ['/Style/', '/services/', '/Lovefolder/', '/OwO/', '/assets/', '/favicon.png', '/favicon.ico'];
foreach ($frontStaticDirs as $dir) {
    if (strpos($path, $dir) === 0) {
        // 先尝试 PHP 执行
        if (strpos($path, '.php') !== false && requirePhp($frontRoot . $path)) return true;
        if (serveStatic($frontRoot . $path, $mimeTypes)) return true;
    }
}

// ── 前台 .html → .php 301 永久跳转 ────────
$frontPages = ['about', 'albums', 'articles', 'lovelist', 'messages', 'page', 'timeline', 'album-detail', 'album-detail-private', 'imglist'];
foreach ($frontPages as $page) {
    $htmlPath = '/' . $page . '.html';
    if (strpos($path, $htmlPath) === 0) {
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        $dest = '/' . $page . '.php' . ($qs !== '' ? '?' . $qs : '');
        header('Location: ' . $dest, true, 301);
        return true;
    }
}

// ── 前台 PHP 页面 ────────────────────────
$frontFile = $frontRoot . $path;
if (strpos($path, '.php') !== false) {
    if (requirePhp($frontFile)) return true;
}
// 前台其他静态文件
if (serveStatic($frontFile, $mimeTypes)) return true;

// ── 前台目录索引 ─────────────────────────
if (is_dir($frontFile) || $path === '/' || $path === '') {
    $index = ($path === '/' || $path === '') ? $frontRoot . '/index.php' : rtrim($frontFile, '/') . '/index.php';
    if (requirePhp($index)) return true;
    $htmlIndex = ($path === '/' || $path === '') ? $frontRoot . '/index.html' : rtrim($frontFile, '/') . '/index.html';
    if (serveStatic($htmlIndex, $mimeTypes)) return true;
}

// ── 404 ──────────────────────────────────
http_response_code(404);
header('Content-Type: text/html; charset=UTF-8');
echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>404</title>';
echo '<style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f4f6fb;color:#555;}</style>';
echo '</head><body><div style="text-align:center"><h1 style="color:#e75480;font-size:48px;margin:0;">404</h1>';
echo '<p>页面未找到: ' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '</p>';
echo '<a href="/" style="color:#e75480;">返回首页</a></div></body></html>';
return true;