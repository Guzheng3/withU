<?php
/**
 * 登录状态查询接口（供 withu-site 等跨域前端读取 withU 登录态）
 */
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');

// 跨域支持：仅允许预览域与本地回环读取登录态，并透传凭据（cookie）
$origin = isset($_SERVER['HTTP_ORIGIN']) ? trim((string) $_SERVER['HTTP_ORIGIN']) : '';
$allowed = false;
if ($origin !== '') {
    $host = parse_url($origin, PHP_URL_HOST) ?: '';
    $allowed = $host === '127.0.0.1'
        || $host === 'localhost'
        || str_ends_with($host, '.monkeycode-ai.online');
}
if ($allowed) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if ($allowed) {
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Max-Age: 86400');
    }
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/helpers.php';

try {
    $auth = new Auth();
    $user = $auth->getCurrentUser();
    if ($user) {
        jsonResponse([
            'success'   => true,
            'logged_in' => true,
            'user'      => $user,
        ]);
    }
    jsonResponse([
        'success'   => true,
        'logged_in' => false,
        'user'      => null,
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'success'   => false,
        'logged_in' => false,
        'message'   => '查询登录状态失败',
    ], 500);
}
