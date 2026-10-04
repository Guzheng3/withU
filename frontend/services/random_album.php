<?php
/**
 * 随机相册接口（首页「随机回忆」按钮）
 * 从数据库 albums 表随机取一个可见相册，并按名称匹配前台相册 code（map-all.json）。
 * 返回: {code:200, id, img_code, name} / {code:404, msg}
 */
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache');

require_once __DIR__ . '/../inc/config.php';

$randomAlbumLoggedIn = false;
try {
    $__root = dirname(__DIR__, 2) . '/backend/app';
    if (is_file($__root . '/config/database.php') && is_file($__root . '/.installed')) {
        require_once $__root . '/config/config.php';
        require_once $__root . '/core/Database.php';
        require_once $__root . '/core/Auth.php';
        require_once $__root . '/core/helpers.php';
        $randomAlbumLoggedIn = (new Auth())->isLoggedIn();
    }
} catch (Throwable $e) {
    // 登录态不可用时按未登录处理
}

if (!isset($db) || !is_object($db)) {
    echo json_encode(['code' => 404, 'msg' => '暂无可用相册'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 前台相册 code 注册表（同名匹配）
$albumCodes = [];
$mapFile = __DIR__ . '/map-all.json';
if (is_file($mapFile)) {
    $map = json_decode((string) file_get_contents($mapFile), true);
    foreach ((is_array($map) ? ($map['albums'] ?? []) : []) as $a) {
        if (!empty($a['name']) && !empty($a['code'])) {
            $albumCodes[(string) $a['name']] = (string) $a['code'];
        }
    }
}

try {
    $guestWhere = $randomAlbumLoggedIn ? '' : " WHERE (is_encrypted = 0 OR is_encrypted IS NULL)";
    $rows = $db->fetchAll("SELECT id, name FROM albums{$guestWhere}");
} catch (Throwable $e) {
    $rows = [];
}

$candidates = [];
foreach ($rows as $row) {
    $code = $albumCodes[(string) ($row['name'] ?? '')] ?? '';
    if ($code !== '') {
        $candidates[] = ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'img_code' => $code];
    }
}

if (!$candidates) {
    echo json_encode(['code' => 404, 'msg' => '暂无可用相册'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pick = $candidates[random_int(0, count($candidates) - 1)];
echo json_encode([
    'code'     => 200,
    'id'       => $pick['id'],
    'name'     => $pick['name'],
    'img_code' => $pick['img_code'],
], JSON_UNESCAPED_UNICODE);
