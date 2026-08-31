<?php
/**
 * 一次性迁移：把 frontend/services/map-all.json 的 messages 冻结快照写入数据库 messages 表。
 * 幂等：以留言原 id 为主键判重，已存在则跳过；可重复执行。
 * 用法：php scripts/migrate-map-messages-to-db.php
 */

require_once __DIR__ . '/../frontend/services/message-common.php';

$db = withu_message_db();
if (!$db) {
    fwrite(STDERR, "数据库不可用（未安装或连接失败）\n");
    exit(1);
}
withu_message_ensure_schema($db);

$mapFile = __DIR__ . '/../frontend/services/map-all.json';
if (!is_file($mapFile)) {
    fwrite(STDERR, "找不到 map-all.json\n");
    exit(1);
}
$data = json_decode((string)file_get_contents($mapFile), true);
$msgs = is_array($data) ? ($data['messages'] ?? []) : [];
if (!$msgs) {
    echo "JSON 中没有留言，无需迁移\n";
    exit(0);
}

$migrated = 0;
$skipped = 0;
$maxId = 0;
foreach ($msgs as $x) {
    $id = (int)($x['id'] ?? 0);
    if ($id <= 0) { $skipped++; continue; }
    $maxId = max($maxId, $id);

    $exists = $db->fetch("SELECT id FROM messages WHERE id = :id", ['id' => $id]);
    if ($exists) { $skipped++; continue; }

    $coords = (array)($x['coords'] ?? []);
    $time = (int)($x['time'] ?? 0);
    $badge = isset($x['badge']) && is_array($x['badge']) ? json_encode($x['badge'], JSON_UNESCAPED_UNICODE) : null;

    $db->insert('messages', [
        'id'             => $id,
        'user_id'        => 0,
        'guest_nickname' => mb_substr((string)($x['name'] ?? '匿名'), 0, 30),
        'guest_avatar'   => (string)($x['avatar'] ?? ''),
        'guest_qq'       => '',
        'location'       => mb_substr((string)($x['city'] ?? '中国'), 0, 255),
        'content'        => (string)($x['text'] ?? ''),
        'content_html'   => (string)($x['textHtml'] ?? ($x['text'] ?? '')),
        'is_public'      => 1,
        'status'         => 'published',
        'created_at'     => $time > 0 ? date('Y-m-d H:i:s', $time) : date('Y-m-d H:i:s'),
        'parent_id'      => !empty($x['parentId']) ? (int)$x['parentId'] : null,
        'reply_to_id'    => null,
        'lng'            => isset($coords[0]) && $coords[0] !== null ? (float)$coords[0] : null,
        'lat'            => isset($coords[1]) && $coords[1] !== null ? (float)$coords[1] : null,
        'os'             => mb_substr((string)($x['os'] ?? ''), 0, 100),
        'browser'        => mb_substr((string)($x['browser'] ?? ''), 0, 100),
        'weather'        => '',
        'weather_icon'   => '',
        'like_count'     => (int)($x['like_count'] ?? 0),
        'msg_type'       => mb_substr((string)($x['type'] ?? ''), 0, 20),
        'old_type'       => mb_substr((string)($x['oldType'] ?? ''), 0, 20),
        'badge'          => $badge,
    ]);
    $migrated++;
}

if ($maxId > 0) {
    $db->query("ALTER TABLE `messages` AUTO_INCREMENT = " . ((int)$maxId + 1));
}

$total = (int)$db->fetch("SELECT COUNT(*) AS c FROM messages")['c'];
echo "迁移完成：新写入 {$migrated} 条，跳过（已存在/无效）{$skipped} 条，表内总数 {$total} 条，AUTO_INCREMENT 设为 " . ($maxId + 1) . "\n";
