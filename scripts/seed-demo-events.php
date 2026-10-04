<?php
/**
 * 一次性迁移：把 frontend/services/map-all.json 的 milestones 冻结快照写入数据库 events 表
 * （后台「纪念事件」管理的数据源）。
 *
 * 幂等：按标题 + 日期判重，已存在则跳过；可重复执行。
 * 用法：php scripts/seed-demo-events.php
 */

require_once dirname(__DIR__) . '/backend/app/config/config.php';
require_once dirname(__DIR__) . '/backend/app/core/Database.php';

$dataFile = __DIR__ . '/demo-events-data.json';
if (!is_file($dataFile)) {
    fwrite(STDERR, "找不到 demo-events-data.json\n");
    exit(1);
}
$events = json_decode((string)file_get_contents($dataFile), true);
if (!is_array($events) || !$events) {
    fwrite(STDERR, "demo-events-data.json 为空或格式错误\n");
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

$inserted = 0;
$skipped = 0;
foreach ($events as $ev) {
    $title = trim((string)($ev['title'] ?? ''));
    $date  = trim((string)($ev['event_date'] ?? ''));
    if ($title === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $skipped++;
        continue;
    }
    if ($db->fetch(
        "SELECT id FROM events WHERE title = :title AND event_date = :date LIMIT 1",
        ['title' => $title, 'date' => $date]
    )) {
        $skipped++;
        continue;
    }
    $now = date('Y-m-d H:i:s');
    $db->insert('events', [
        'user_id'      => $ownerId,
        'title'        => $title,
        'description'  => (string)($ev['description'] ?? ''),
        'event_date'   => $date,
        'icon'         => (string)($ev['icon'] ?? 'heart'),
        'is_important' => !empty($ev['is_important']) ? 1 : 0,
        'is_recurring' => !empty($ev['is_recurring']) ? 1 : 0,
        'sort_order'   => (int)($ev['sort_order'] ?? 0),
        'created_at'   => $now,
        'updated_at'   => $now,
    ]);
    $inserted++;
}

$total = (int)$db->fetch("SELECT COUNT(*) AS c FROM events")['c'];
echo "纪念事件种子完成：新写入 {$inserted} 条（跳过 {$skipped}），events 表现有 {$total} 条\n";
