<?php
/**
 * withUstrm 内置组件 - 后台鉴权网关
 *
 * 访问路径：/admin/strm.php/<任意子路径>
 * - 仅允许已登录的情侣账号（user1/user2）访问 —— 这就是「只能从 withu 后台访问」的闸门。
 * - 所有请求（页面 / 静态资源 / API）统一反代到本机 127.0.0.1:3112 的 bridge，
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

$bridge = 'http://127.0.0.1:3112';
// 读取内部共享密钥（与 bridge 校验一致），仅 withU 后台网关持有
$bridgeSecret = '';
foreach (['E:/Agent/withu/runtime/strm/bridge-secret.txt', dirname(__DIR__, 2) . '/runtime/strm/bridge-secret.txt', dirname(__DIR__, 2) . '/strm/runtime/bridge-secret.txt'] as $__bf) {
    if (is_file($__bf)) { $bridgeSecret = trim((string)file_get_contents($__bf)); if ($bridgeSecret !== '') break; }
}
$pathInfo = $_SERVER['PATH_INFO'] ?? '/';
if ($pathInfo === '' || $pathInfo[0] !== '/') {
    $pathInfo = '/';
}
$qs = $_SERVER['QUERY_STRING'] ?? '';
$target = $bridge . $pathInfo . ($qs !== '' ? '?' . $qs : '');

// ---- 内部 JWT：让 iframe 内的 withUstrm 前端免二次登录（仅 withu 后台可达） ----
function strm_internal_token(): string
{
    $path = dirname(__DIR__, 2) . '/runtime/strm/jwt.txt';
    if (!is_file($path)) return '';
    $secret = trim((string)file_get_contents($path));
    if ($secret === '') return '';
    $b64u = function (string $s): string {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    };
    $header = $b64u(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $now = time();
    $payload = $b64u(json_encode(['sub' => 'withu_admin', 'iat' => $now, 'exp' => $now + 20160 * 60]));
    $sig = $b64u(hash_hmac('sha256', $header . '.' . $payload, $secret, true));
    return $header . '.' . $payload . '.' . $sig;
}

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
// 注入内部共享密钥 —— 只有 withU 网关能拿到，bridge 据此放行
if ($bridgeSecret !== '') {
    $forward[] = 'X-Withu-Bridge-Secret: ' . $bridgeSecret;
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
// 若是 HTML 页面（withUstrm Nuxt 首屏），注入免登录 JWT
$ct = '';
foreach ($lines as $line) {
    if (stripos($line, 'content-type:') === 0) { $ct = strtolower(trim(substr($line, 13))); break; }
}
$jwtTok = strm_internal_token();
if ($jwtTok !== '' && strpos($ct, 'text/html') !== false && strpos($body, '</head>') !== false) {
    $esc = json_encode($jwtTok);
    $script = '<script>'
        . 'try{'
        . '(function(){'
        . '  var tok = ' . $esc . ';'
        . '  var cur = localStorage.getItem("auth_token");'
        . '  var need = true;'
        . '  if (cur) { try { var p = JSON.parse(atob(cur.split(".")[1].replace(/-/g,"+").replace(/_/g,"/"))); if (p && p.exp && p.exp * 1000 > Date.now()) need = false; } catch(e){} }'
        . '  if (need) {'
        . '    localStorage.setItem("auth_storage_type","local");'
        . '    localStorage.setItem("auth_token", tok);'
        . '    localStorage.setItem("auth_userInfo",JSON.stringify({username:"withu_admin"}));'
        . '    location.reload();'
        . '  }'
        . '})();'
        . '}catch(e){}'
        . '</script>';
    $body = str_replace('</head>', $script . '</head>', $body);
}

http_response_code($status);
echo $body;
