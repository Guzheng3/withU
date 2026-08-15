<?php
/**
 * withUstrm 内置组件 - 后台鉴权网关
 *
 * 访问路径：/admin/strm.php/<任意子路径>
 * - 仅允许已登录的情侣账号（user1/user2）访问 —— 这就是「只能从 withu 后台访问」的闸门。
 * - 所有请求（页面 / 静态资源 / API）统一反代到本机 127.0.0.1:3111 的 bridge，
 *   bridge 负责 Nuxt 静态产物与 Spring Boot API 转发。
 * - withUstrm 前端以 baseURL=/admin/strm.php/ 构建，资源路径天然经过本网关。
 */
mb_internal_encoding('UTF-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/helpers.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requireRole(['user1', 'user2']);

$bridge = 'http://127.0.0.1:3111';
$pathInfo = $_SERVER['PATH_INFO'] ?? '/';
if ($pathInfo === '' || $pathInfo[0] !== '/') {
    $pathInfo = '/';
}
$qs = $_SERVER['QUERY_STRING'] ?? '';
$target = $bridge . $pathInfo . ($qs !== '' ? '?' . $qs : '');

// 手动收集请求头（兼容 php -S / 任意 SAPI）
$headers = [];
foreach ($_SERVER as $k => $v) {
    if (strpos($k, 'HTTP_') === 0) {
        $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
        $headers[$name] = $v;
    }
}
if (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] !== '') {
    $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
}
// 过滤 hop-by-hop 与会被 curl 干扰的头
$skip = ['host', 'connection', 'content-length', 'keep-alive', 'proxy-authenticate', 'proxy-authorization', 'te', 'trailer', 'transfer-encoding', 'upgrade', 'expect'];
$forward = [];
foreach ($headers as $name => $val) {
    if (in_array(strtolower($name), $skip, true)) {
        continue;
    }
    $forward[] = $name . ': ' . $val;
}

$ch = curl_init($target);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);
curl_setopt($ch, CURLOPT_HTTPHEADER, $forward);
if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    $body = file_get_contents('php://input');
    if ($body === false) {
        $body = '';
    }
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
}
$resp = curl_exec($ch);
if ($resp === false) {
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    http_response_code($code > 0 ? $code : 502);
    echo json_encode(['code' => 502, 'message' => 'withUstrm 组件未启动: ' . $err]);
    exit;
}
$status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$respHeaders = substr($resp, 0, $headerSize);
$body = substr($resp, $headerSize);

// 透传响应头
$skipResp = ['connection', 'transfer-encoding', 'keep-alive', 'upgrade', 'proxy-authenticate', 'proxy-authorization', 'te', 'trailer', 'content-length', 'content-encoding'];
$lines = preg_split('/\r?\n/', $respHeaders);
foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '') {
        continue;
    }
    if (stripos($line, 'HTTP/') === 0) {
        continue; // 状态码单独设置
    }
    $p = explode(':', $line, 2);
    if (count($p) !== 2) {
        continue;
    }
    $name = trim($p[0]);
    $val  = trim($p[1]);
    if (in_array(strtolower($name), $skipResp, true)) {
        continue;
    }
    header($name . ': ' . $val, false);
}
http_response_code($status);
echo $body;
