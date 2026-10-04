<?php
/**
 * 一次性迁移：把前台清单页（lovelist.php）原硬编码的 demo 心愿写入数据库。
 *
 * 数据来源 scripts/demo-lovelist-data.json（由原静态卡片解析而来）：
 *   标题 / 完成状态 / 完成日期 / 达成地点与坐标 / 备注 / 图集
 * 保留原心愿 id（前台卡片 id="event-N" 与搜索服务的 id 语义一致）。
 * 幂等：按 id 判重，已存在则跳过；可重复执行。
 * 用法：php scripts/seed-demo-lovelist.php
 */

require_once dirname(__DIR__) . '/backend/app/config/config.php';
require_once dirname(__DIR__) . '/backend/app/core/Database.php';

$dataFile = __DIR__ . '/demo-lovelist-data.json';
if (!is_file($dataFile)) {
    fwrite(STDERR, "找不到 demo-lovelist-data.json\n");
    exit(1);
}
$items = json_decode((string)file_get_contents($dataFile), true);
if (!is_array($items) || !$items) {
    fwrite(STDERR, "demo-lovelist-data.json 为空或格式错误\n");
    exit(1);
}

try {
    $db = Database::getInstance();
} catch (Throwable $e) {
    fwrite(STDERR, "数据库不可用：{$e->getMessage()}\n");
    exit(1);
}

$owner = $db->fetch("SELECT id FROM users WHERE role='user1' ORDER BY id ASC LIMIT 1");
if (!$owner) {
    $owner = $db->fetch("SELECT id FROM users ORDER BY id ASC LIMIT 1");
}
if (!$owner) {
    fwrite(STDERR, "users 表没有账号，请先完成安装注册后再执行种子\n");
    exit(1);
}
$ownerId = (int)$owner['id'];

$insertedItems = 0;
$insertedImages = 0;
$skipped = 0;

foreach ($items as $item) {
    $id = (int)($item['id'] ?? 0);
    $title = trim((string)($item['title'] ?? ''));
    if ($id <= 0 || $title === '') {
        $skipped++;
        continue;
    }
    if ($db->fetch("SELECT id FROM love_list_items WHERE id = :id LIMIT 1", ['id' => $id])) {
        $skipped++;
        continue;
    }

    $doneDate = (string)($item['done_date'] ?? '');
    $doneDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $doneDate) ? $doneDate : null;
    $createdAt = ($doneDate ? $doneDate : '2024-05-01') . ' 12:00:00';

    $db->insert('love_list_items', [
        'id'            => $id,
        'user_id'       => $ownerId,
        'title'         => $title,
        'is_done'       => !empty($item['is_done']) ? 1 : 0,
        'done_date'     => $doneDate,
        'location_name' => (string)($item['location_name'] ?? ''),
        'latitude'      => isset($item['lat']) && $item['lat'] !== null ? (float)$item['lat'] : null,
        'longitude'     => isset($item['lng']) && $item['lng'] !== null ? (float)$item['lng'] : null,
        'note'          => (string)($item['note'] ?? ''),
        'sort_order'    => (int)($item['sort_order'] ?? 0),
        'created_at'    => $createdAt,
        'updated_at'    => $createdAt,
    ]);
    $insertedItems++;

    $images  = is_array($item['images'] ?? null) ? $item['images'] : [];
    $thumbs  = is_array($item['thumbs'] ?? null) ? $item['thumbs'] : [];
    foreach ($images as $idx => $imagePath) {
        $imagePath = trim((string)$imagePath);
        if ($imagePath === '' || $imagePath === '0') {
            continue;
        }
        $thumbPath = trim((string)($thumbs[$idx] ?? ''));
        $db->insert('love_list_item_images', [
            'item_id'        => $id,
            'image_path'     => $imagePath,
            'thumbnail_path' => $thumbPath !== '' ? $thumbPath : null,
            'sort_order'     => $idx,
            'created_at'     => $createdAt,
        ]);
        $insertedImages++;
    }
}

$itemTotal = (int)$db->fetch("SELECT COUNT(*) AS c FROM love_list_items")['c'];
$imageTotal = (int)$db->fetch("SELECT COUNT(*) AS c FROM love_list_item_images")['c'];
echo "清单种子完成：心愿新写入 {$insertedItems} 条（跳过 {$skipped}），图片 {$insertedImages} 张；"
    . "表内现有心愿 {$itemTotal} 条、图片 {$imageTotal} 张\n";
