<?php
/**
 * 留言列表接口
 * 从数据库 messages 表读取留言（库不可用时回读 map-all.json 冻结快照），支持分页和回复查询
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/message-common.php';

$all = withu_message_fetch_all();
if ($all === null) {
    // 数据库不可用：回读迁移前的 JSON 快照，保持站点可用
    $all = withu_message_json_fallback();
}

$action = $_GET['action'] ?? 'list';

// 回复查询
if ($action === 'replies') {
    $parentId = $_GET['parent_id'] ?? '';
    $parent = null;
    $replies = [];
    foreach ($all as $x) {
        if ((string)$x['id'] === (string)$parentId) {
            $parent = $x;
        }
        if ((string)($x['parentId'] ?? '') === (string)$parentId) {
            $replies[] = $x;
        }
    }
    echo json_encode(['code' => 200, 'data' => ['parent' => $parent, 'replies' => $replies]]);
    exit;
}

// 留言列表（只返回顶层留言，不含子回复）
$offset = max(0, (int)($_GET['offset'] ?? 0));
$limit = max(1, min(50, (int)($_GET['limit'] ?? 20)));

$tops = [];
foreach ($all as $x) {
    if (empty($x['parentId'])) {
        $tops[] = $x;
    }
}
// 按 id 降序
usort($tops, function ($a, $b) {
    return (int)$b['id'] - (int)$a['id'];
});

$items = array_slice($tops, $offset, $limit);

echo json_encode([
    'code' => 200,
    'data' => [
        'items' => $items,
        'pagination' => ['has_more' => ($offset + $limit) < count($tops)]
    ]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
