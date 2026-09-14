<?php
/**
 * 点点滴滴（文章 / 日记）列表数据服务
 *
 * 数据源：后台 articles 表（status='published'，type=article|diary），
 * 由前端 page-articles.js 拉取并渲染到 articles.php 的瀑布流容器。
 *
 * 用法: GET services/article-list.php?page=1&per_page=12
 * 返回: {code:200, data:{articles:[...], counts:{total}, pagination:{page,per_page,has_more}}}
 */

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache');

require_once __DIR__ . '/../inc/config.php';

// 登录态（后端 Auth）：加密文章（is_encrypted=1）对游客整体隐藏
$articleListLoggedIn = false;
try {
    $articleListRoot = dirname(__DIR__, 2) . '/backend/app';
    if (is_file($articleListRoot . '/config/database.php') && is_file($articleListRoot . '/.installed')) {
        require_once $articleListRoot . '/config/config.php';
        require_once $articleListRoot . '/core/Database.php';
        require_once $articleListRoot . '/core/Auth.php';
        $articleListAuth = new Auth();
        $articleListLoggedIn = $articleListAuth->isLoggedIn();
    }
} catch (Throwable $e) {
    // 登录态不可用时按未登录处理（只返回公开文章）
}

$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? 12);
if ($perPage <= 0) { $perPage = 12; }
if ($perPage > 50) { $perPage = 50; }

$emptyPayload = function () use ($page, $perPage) {
    return [
        'code' => 200,
        'data' => [
            'articles'   => [],
            'counts'     => ['total' => 0],
            'pagination' => ['page' => $page, 'per_page' => $perPage, 'has_more' => false],
        ],
    ];
};

if (!isset($db) || !is_object($db)) {
    echo json_encode($emptyPayload(), JSON_UNESCAPED_UNICODE);
    exit;
}

// 后端 helpers：get_setting() / upload_url() 等工具（config 常量已由 inc/config.php 载入）
$helpersFile = dirname(__DIR__, 2) . '/backend/app/core/helpers.php';
if (is_file($helpersFile)) {
    require_once $helpersFile;
}

if (!function_exists('articlelist_avatar_url')) {
    /** 作者头像地址解析：QQ 头像升级 HTTPS，uploads 路径统一补全，其余原样返回 */
    function articlelist_avatar_url(?string $path): string {
        $path = trim((string) $path);
        if ($path === '') {
            return '';
        }
        if (preg_match('#^http://([^/]+\.)?qlogo\.cn/#i', $path)) {
            return 'https://' . substr($path, 7);
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        if (strpos($path, '/uploads/') === 0 || strpos($path, 'uploads/') === 0) {
            return function_exists('upload_url') ? upload_url($path) : $path;
        }
        return $path;
    }
}

// 游客过滤：加密文章整体隐藏（列表与总数口径一致）
// 极老数据库可能没有 is_encrypted 字段，查询失败时回退为不过滤（此类库中也不存在加密文章）
$articleListWhere = "a.status = 'published'" . ($articleListLoggedIn ? '' : " AND (a.is_encrypted = 0 OR a.is_encrypted IS NULL)");
$articleListWhereFallback = "a.status = 'published'";

$totalRow = null;
try {
    $totalRow = $db->fetch("SELECT COUNT(*) AS c FROM articles a WHERE {$articleListWhere}");
} catch (Throwable $e) {
    $articleListWhere = $articleListWhereFallback;
    $totalRow = $db->fetch("SELECT COUNT(*) AS c FROM articles a WHERE {$articleListWhere}");
}
$total    = $totalRow ? (int) $totalRow['c'] : 0;

$rows = [];
try {
    $rows = $db->fetchAll(
        "SELECT a.id, a.type, a.title, a.content, a.is_encrypted, a.views, a.created_at,
                u.nickname AS author_name, u.avatar AS author_avatar, u.gender AS author_gender
         FROM articles a
         LEFT JOIN users u ON u.id = a.user_id
         WHERE {$articleListWhere}
         ORDER BY a.created_at DESC, a.id DESC
         LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage)
    );
} catch (Throwable $e) {
    // 与总数查询同口径的兜底：去掉加密字段条件重试一次
    $rows = $db->fetchAll(
        "SELECT a.id, a.type, a.title, a.content, a.is_encrypted, a.views, a.created_at,
                u.nickname AS author_name, u.avatar AS author_avatar, u.gender AS author_gender
         FROM articles a
         LEFT JOIN users u ON u.id = a.user_id
         WHERE {$articleListWhereFallback}
         ORDER BY a.created_at DESC, a.id DESC
         LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage)
    );
}

// 恋爱 DAY 计数基准：后台设置 love_date，缺省回落到站点配置 startTime
$loveStart = '';
try {
    if (function_exists('get_setting')) {
        $loveStart = trim((string) get_setting('love_date', ''));
    }
} catch (Throwable $e) {
    $loveStart = '';
}
if ($loveStart === '') {
    $cfg = json_decode($withuConfigJson ?? '{}', true);
    $loveStart = trim((string) ($cfg['startTime'] ?? ''));
}
$loveTs = $loveStart !== '' ? strtotime($loveStart) : false;

$monthCn = ['', '一月', '二月', '三月', '四月', '五月', '六月',
            '七月', '八月', '九月', '十月', '十一月', '十二月'];

$articles = [];
foreach ($rows as $r) {
    $created = (string) ($r['created_at'] ?? '');
    $ts      = $created !== '' ? strtotime($created) : false;

    // 摘要：去标签、压缩空白、截断；加密文章不输出内容摘要
    $excerpt = '';
    if (empty($r['is_encrypted'])) {
        $excerpt = html_entity_decode(strip_tags((string) ($r['content'] ?? '')), ENT_QUOTES, 'UTF-8');
        $excerpt = trim(preg_replace('/\s+/u', ' ', $excerpt));
        if (mb_strlen($excerpt) > 120) {
            $excerpt = mb_substr($excerpt, 0, 120) . '…';
        }
    }

    $dayNo = null;
    if ($loveTs && $ts) {
        $dayNo = (int) floor(($ts - $loveTs) / 86400) + 1;
    }

    $articles[] = [
        'id'         => (int) ($r['id'] ?? 0),
        'type'       => (($r['type'] ?? 'article') === 'diary') ? 'diary' : 'article',
        'title'      => (string) ($r['title'] ?? ''),
        'excerpt'    => $excerpt,
        'encrypted'  => !empty($r['is_encrypted']),
        'day'        => $ts ? (int) date('j', $ts) : '',
        'month_cn'   => $ts ? $monthCn[(int) date('n', $ts)] : '',
        'year'       => $ts ? (int) date('Y', $ts) : '',
        'time'       => $ts ? date('H:i', $ts) : '',
        'day_no'     => $dayNo,
        'created_at' => $created,
        'views'      => (int) ($r['views'] ?? 0),
        'author'     => [
            'name'   => (string) ($r['author_name'] ?? ''),
            'avatar' => articlelist_avatar_url($r['author_avatar'] ?? ''),
            'gender' => in_array(($r['author_gender'] ?? ''), ['male', 'female'], true) ? $r['author_gender'] : '',
        ],
    ];
}

echo json_encode([
    'code' => 200,
    'data' => [
        'articles'   => $articles,
        'counts'     => ['total' => $total],
        'pagination' => [
            'page'     => $page,
            'per_page' => $perPage,
            'has_more' => ($page * $perPage) < $total,
        ],
    ],
], JSON_UNESCAPED_UNICODE);
exit;
