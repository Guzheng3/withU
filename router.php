<?php
/**
 * withU 统一路由入口
 * 前台(frontend/) + 后台(backend/app/) 都在 1314 端口
 * 用法: php -S 0.0.0.0:1314 -t /home/gx/MonkeyCode/withu router.php
 */
$uri  = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$path = rawurldecode($path);

// ── 路径安全校验 ─────────────────────────
// 必须在 rawurldecode 之后检查，否则 %2e%2e%2f 这类编码可以绕过；
// 反斜杠在 Windows 上同样是路径分隔符，NUL 字节用于截断路径，一并拒绝。
if (!is_string($path) || $path === '') {
    withu_router_404('/');
}
if (strpbrk($path, "\\\0") !== false
    || preg_match('#(?:^|/)\.{1,2}(?:/|$)#', $path) === 1) {
    withu_router_404($path);
    return true;
}

// PHP's built-in server closes HTML responses with EOF. Some SSH tunnels do
// not forward that half-close, so give browsers an explicit response length.
ob_start(function (string $output): string {
    if (headers_sent()) {
        return $output;
    }

    $hasLength = false;
    foreach (headers_list() as $header) {
        if (stripos($header, 'Content-Length:') === 0) {
            $hasLength = true;
            break;
        }
    }
    if (!$hasLength) {
        header('Content-Length: ' . strlen($output), true);
    }
    return $output;
});

// ── 遗留目录 backend/：不对公网暴露（含已停用的 Node 服务） ──
if ($path === '/backend' || strpos($path, '/backend/') === 0) {
    withu_router_404($path);
    return true;
}

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

/**
 * 允许被当作静态文件直读的目录白名单（realpath 归一化后的绝对路径）。
 * 只有前台资源目录与后台上传/静态资源目录允许 readfile，
 * 其余目录（config/、core/、admin/、api/ …）即使被路径构造命中也不会输出源码。
 */
function withu_static_roots(): array {
    static $roots = null;
    if ($roots === null) {
        $roots = [];
        foreach ([__DIR__ . '/frontend', __DIR__ . '/backend/app/assets', __DIR__ . '/backend/app/uploads'] as $dir) {
            $real = realpath($dir);
            if ($real !== false) {
                $roots[] = rtrim($real, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            }
        }
    }
    return $roots;
}

function serveStatic(string $file, array $mime): bool {
    if (!is_file($file)) return false;
    // realpath 收敛后再比对白名单，避免未归一化路径或符号链接读到敏感文件
    $real = realpath($file);
    if ($real === false) return false;
    $allowed = false;
    foreach (withu_static_roots() as $root) {
        if (strncmp($real, $root, strlen($root)) === 0) {
            $allowed = true;
            break;
        }
    }
    if (!$allowed) return false;
    $ext = pathinfo($real, PATHINFO_EXTENSION);
    $ct  = $mime[$ext] ?? 'application/octet-stream';
    // 计算可读的文件大小
    $len = filesize($real);
    header('Content-Type: ' . $ct);
    header('Content-Length: ' . $len);
    header('Cache-Control: max-age=3600, public');
    readfile($real);
    return true;
}

function withu_router_404(string $path): void {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>404</title>';
    echo '<style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f4f6fb;color:#555;}</style>';
    echo '</head><body><div style="text-align:center"><h1 style="color:#e75480;font-size:48px;margin:0;">404</h1>';
    echo '<p>页面未找到: ' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<a href="/" style="color:#e75480;">返回首页</a></div></body></html>';
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

// ── 后台上传媒体 /uploads/ → backend/app/uploads（不存在则走后续前台路由） ──
if (strpos($path, '/uploads/') === 0) {
    if (serveStatic($appRoot . $path, $mimeTypes)) return true;
}

// ── 后台 PHP 路径 ────────────────────────
$isBackend = strpos($path, '/admin') === 0
    || $path === '/login.php' || $path === '/logout.php'
    || $path === '/password_reset.php'
    || $path === '/install.php' || strpos($path, '/install') === 0
    // 影视库与播放页位于 backend/app，需要显式路由到后台目录
    || preg_match('#^/(watch|watch_play|watch_history|player|cz_player|events|travel)\.php$#', $path) === 1;

if ($isBackend) {
    if ($path === '/login.php' && requirePhp($appRoot . '/login.php')) return true;
    if ($path === '/logout.php' && requirePhp($appRoot . '/logout.php')) return true;
    if ($path === '/password_reset.php' && requirePhp($appRoot . '/password_reset.php')) return true;
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
    $extRelative = substr($path, 4);
    $extFile = $frontRoot . '/ext/' . $extRelative;
    if (!is_file($extFile)) {
        $extFile = $frontRoot . '/_external/' . $extRelative;
    }
    if (serveStatic($extFile, $mimeTypes)) return true;
    if (requirePhp($extFile)) return true;
}

// ── 数据快照文件：仅供服务端 PHP 读取，禁止直接下载 ─────────
$privateDataFiles = ['/services/map-all.json', '/services/album-photos.json'];
if (in_array($path, $privateDataFiles, true)) {
    withu_router_404($path);
    return true;
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
withu_router_404($path);
return true;
