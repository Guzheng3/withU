<?php
/**
 * 恋爱清单搜索接口（lovelist.php 的搜索 / 状态筛选）
 *
 * 入参（POST，兼容 GET）：
 *   search_info   关键词（匹配心愿标题 / 备注 / 达成地点）
 *   status_filter 'Success'=仅已完成，'Fail'=仅未完成，缺省=全部
 * 返回：心愿数组（页面 page-lovelist.js 直接渲染）
 *   [{id, eventname, icon, finish_date, city, lng, lat, remark, imgurl:[]}]
 */
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache');

require_once __DIR__ . '/../inc/config.php';

try {
    $__root = dirname(__DIR__, 2) . '/backend/app';
    if (is_file($__root . '/config/database.php') && is_file($__root . '/.installed')) {
        require_once $__root . '/config/config.php';
        require_once $__root . '/core/Database.php';
        require_once $__root . '/core/helpers.php';
    }
} catch (Throwable $e) {
    // 数据库不可用时返回空结果
}

if (!isset($db) || !is_object($db)) {
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

$keyword = trim((string)($_POST['search_info'] ?? ($_GET['search_info'] ?? '')));
$status  = trim((string)($_POST['status_filter'] ?? ($_GET['status_filter'] ?? '')));

$where = [];
$params = [];

// 状态筛选：Success=已完成 / Fail=未完成
if (strcasecmp($status, 'Success') === 0) {
    $where[] = 'is_done = 1';
} elseif (strcasecmp($status, 'Fail') === 0) {
    $where[] = 'is_done = 0';
}

if ($keyword !== '') {
    // LIKE 通配符转义，避免用户输入 % _ 影响匹配；
    // 原生预处理下同一命名占位符不可复用，故使用三个独立占位符
    $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword);
    $where[] = "(title LIKE :kw1 ESCAPE '\\\\' OR note LIKE :kw2 ESCAPE '\\\\' OR location_name LIKE :kw3 ESCAPE '\\\\')";
    $params['kw1'] = $params['kw2'] = $params['kw3'] = '%' . $escaped . '%';
}

$sql = "SELECT * FROM love_list_items"
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
    . " ORDER BY sort_order ASC, id DESC";

try {
    $items = $db->fetchAll($sql, $params);
} catch (Throwable $e) {
    $items = [];
}

// 图集
$imagesByItem = [];
$ids = array_column($items, 'id');
if ($ids) {
    try {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        foreach ($db->fetchAll(
            "SELECT item_id, image_path, thumbnail_path FROM love_list_item_images
             WHERE item_id IN ($ph) ORDER BY item_id ASC, sort_order ASC, id ASC",
            $ids
        ) as $row) {
            $path = (string)($row['thumbnail_path'] ?: $row['image_path']);
            if ($path === '') {
                continue;
            }
            $imagesByItem[(int)$row['item_id']][] = upload_url($path);
        }
    } catch (Throwable $e) {
        $imagesByItem = [];
    }
}

$out = [];
foreach ($items as $row) {
    $id = (int)$row['id'];
    $out[] = [
        'id'          => $id,
        'eventname'   => (string)$row['title'],
        'icon'        => !empty($row['is_done']) ? 1 : 0,
        'finish_date' => (string)($row['done_date'] ?? ''),
        'time'        => (string)($row['done_date'] ?? ''),
        'city'        => (string)($row['location_name'] ?? ''),
        'place'       => (string)($row['location_name'] ?? ''),
        'lng'         => $row['longitude'] !== null ? (float)$row['longitude'] : null,
        'lat'         => $row['latitude'] !== null ? (float)$row['latitude'] : null,
        'remark'      => (string)($row['note'] ?? ''),
        'note'        => (string)($row['note'] ?? ''),
        'imgurl'      => $imagesByItem[$id] ?? [],
    ];
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
