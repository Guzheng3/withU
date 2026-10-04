<?php
/**
 * 随机文章接口（首页「随机点滴」按钮）
 * 从数据库 articles 表随机取一篇当前访客可见的已发布文章。
 * 返回: {code:200, id, title} / {code:404, msg}
 */
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache');

require_once __DIR__ . '/../inc/config.php';

$randomArticleLoggedIn = false;
try {
    $__root = dirname(__DIR__, 2) . '/backend/app';
    if (is_file($__root . '/config/database.php') && is_file($__root . '/.installed')) {
        require_once $__root . '/config/config.php';
        require_once $__root . '/core/Database.php';
        require_once $__root . '/core/Auth.php';
        require_once $__root . '/core/helpers.php';
        $randomArticleLoggedIn = (new Auth())->isLoggedIn();
    }
} catch (Throwable $e) {
    // 登录态不可用时按未登录处理
}

if (!isset($db) || !is_object($db)) {
    echo json_encode(['code' => 404, 'msg' => '暂无可用文章'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 游客仅公开文章；加密/隐藏文章不参与随机
    $guestWhere = $randomArticleLoggedIn ? '' : " AND (a.is_encrypted = 0 OR a.is_encrypted IS NULL)";
    $rows = $db->fetchAll(
        "SELECT a.id, a.title FROM articles a
         WHERE a.status = 'published'{$guestWhere}"
    );
} catch (Throwable $e) {
    $rows = [];
}

if (!$rows) {
    echo json_encode(['code' => 404, 'msg' => '暂无可用文章'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pick = $rows[random_int(0, count($rows) - 1)];
echo json_encode([
    'code'  => 200,
    'id'    => (int) $pick['id'],
    'title' => (string) $pick['title'],
], JSON_UNESCAPED_UNICODE);
