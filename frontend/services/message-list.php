<?php
/**
 * 留言列表接口
 * 从 map-all.json 读取留言数据，支持分页和回复查询
 */
header('Content-Type: application/json; charset=utf-8');

$mapFile = __DIR__ . '/map-all.json';
if (!file_exists($mapFile)) {
    echo json_encode(['code' => 200, 'data' => ['items' => [], 'pagination' => ['has_more' => false]]]);
    exit;
}

$data = json_decode(file_get_contents($mapFile), true);
if (!$data) {
    echo json_encode(['code' => 200, 'data' => ['items' => [], 'pagination' => ['has_more' => false]]]);
    exit;
}

$all = $data['messages'] ?? [];

$action = $_GET['action'] ?? 'list';

// 回复查询
if ($action === 'replies') {
    $parentId = $_GET['parent_id'] ?? '';
    $parent = null;
    foreach ($all as $x) {
        if ((string)$x['id'] === (string)$parentId) {
            $parent = $x;
            break;
        }
    }
    $replies = [];
    foreach ($all as $x) {
        if ((string)($x['parentId'] ?? '') === (string)$parentId) {
            $replyCount = 0;
            foreach ($all as $y) {
                if ((string)($y['parentId'] ?? '') === (string)$x['id']) $replyCount++;
            }
            $x['replyCount'] = $replyCount;
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
usort($tops, function($a, $b) { return (int)$b['id'] - (int)$a['id']; });

$items = array_slice($tops, $offset, $limit);
foreach ($items as &$item) {
    $replyCount = 0;
    foreach ($all as $y) {
        if ((string)($y['parentId'] ?? '') === (string)$item['id']) $replyCount++;
    }
    $item['replyCount'] = $replyCount;
}
unset($item);

echo json_encode([
    'code' => 200,
    'data' => [
        'items' => $items,
        'pagination' => ['has_more' => ($offset + $limit) < count($tops)]
    ]
]);