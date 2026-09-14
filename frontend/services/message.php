<?php
/**
 * 留言提交接口
 * 留言统一写入数据库 messages 表（原 map-all.json 写入已迁移，JSON 仅作只读冻结快照）
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/message-common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['Status' => false, 'message' => '请求方式错误']);
    exit;
}

$db = withu_message_db();
if (!$db) {
    echo json_encode(['Status' => false, 'message' => '数据库不可用，请稍后再试']);
    exit;
}
withu_message_ensure_schema($db);

$text = trim((string)($_POST['text'] ?? ''));
if ($text === '') {
    echo json_encode(['Status' => false, 'message' => '留言内容不能为空']);
    exit;
}

$qq = (string)($_POST['qq'] ?? 'anon');
$now = date('Y-m-d H:i:s');

$row = [
    'user_id'        => 0,
    'guest_nickname' => mb_substr((string)($_POST['name'] ?? '匿名'), 0, 30),
    'guest_avatar'   => (string)($_POST['avatar'] ?? ''),
    'guest_qq'       => $qq,
    'location'       => mb_substr((string)($_POST['city'] ?? '中国'), 0, 255),
    'content'        => $text,
    'content_html'   => $text,
    'is_public'      => 1,
    'status'         => 'published',
    'created_at'     => $now,
    'parent_id'      => isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null,
    'reply_to_id'    => isset($_POST['reply_to_id']) && $_POST['reply_to_id'] !== '' ? (int)$_POST['reply_to_id'] : null,
    'lng'            => isset($_POST['lng']) && $_POST['lng'] !== '' ? (float)$_POST['lng'] : null,
    'lat'            => isset($_POST['lat']) && $_POST['lat'] !== '' ? (float)$_POST['lat'] : null,
    'os'             => mb_substr((string)($_POST['os'] ?? ''), 0, 100),
    'browser'        => mb_substr((string)($_POST['browser'] ?? ''), 0, 100),
    'weather'        => mb_substr((string)($_POST['weather'] ?? ''), 0, 100),
    'weather_icon'   => mb_substr((string)($_POST['weather_icon'] ?? ''), 0, 100),
    'like_count'     => 0,
    'msg_type'       => '',
    'badge'          => null,
];

try {
    $newId = $db->insert('messages', $row);
} catch (Throwable $e) {
    echo json_encode(['Status' => false, 'message' => '留言保存失败，请稍后再试']);
    exit;
}

echo json_encode(['Status' => true, 'message' => '留言成功', 'id' => (int)$newId, 'pending' => false]);
