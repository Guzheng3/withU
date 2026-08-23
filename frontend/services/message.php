<?php
/**
 * 留言提交接口
 * 保存留言到 map-all.json
 */
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['Status' => false, 'message' => '请求方式错误']);
    exit;
}

$mapFile = __DIR__ . '/map-all.json';
if (!file_exists($mapFile)) {
    echo json_encode(['Status' => false, 'message' => '数据文件不存在']);
    exit;
}

$data = json_decode(file_get_contents($mapFile), true);
if (!$data) {
    $data = ['messages' => [], 'albums' => [], 'events' => [], 'milestones' => []];
}

$msgs = &$data['messages'];
$maxId = 0;
foreach ($msgs as $x) {
    $id = (int)($x['id'] ?? 0);
    if ($id > $maxId) $maxId = $id;
}

$text = trim((string)($_POST['text'] ?? ''));
if ($text === '') {
    echo json_encode(['Status' => false, 'message' => '留言内容不能为空']);
    exit;
}

$parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null;
$now = time();
$qq = (string)($_POST['qq'] ?? 'anon');

$item = [
    'id' => $maxId + 1,
    'parentId' => $parentId,
    'name' => mb_substr((string)($_POST['name'] ?? '匿名'), 0, 30),
    'qq' => $qq,
    'qq_hash' => $qq === 'anon' ? 'anon' : md5($qq),
    'avatar' => $_POST['avatar'] ?? '',
    'text' => $text,
    'textHtml' => $text,
    'city' => $_POST['city'] ?? '中国',
    'lng' => isset($_POST['lng']) ? (float)$_POST['lng'] : null,
    'lat' => isset($_POST['lat']) ? (float)$_POST['lat'] : null,
    'os' => $_POST['os'] ?? '',
    'browser' => $_POST['browser'] ?? '',
    'weather' => $_POST['weather'] ?? '',
    'weather_icon' => $_POST['weather_icon'] ?? '',
    'timestamp' => $now,
    'timeStr' => date('Y-m-d H:i:s', $now),
    'type' => '',
    'badge' => null,
    'like_count' => 0,
    'replyCount' => 0,
    'reply_to_id' => isset($_POST['reply_to_id']) && $_POST['reply_to_id'] !== '' ? (int)$_POST['reply_to_id'] : null,
];

$msgs[] = $item;

// 更新父留言的回复数
if ($parentId) {
    foreach ($msgs as &$parent) {
        if ((int)($parent['id'] ?? 0) === $parentId) {
            $parent['replyCount'] = ($parent['replyCount'] ?? 0) + 1;
            break;
        }
    }
    unset($parent);
}

file_put_contents($mapFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo json_encode(['Status' => true, 'message' => '留言成功', 'id' => $item['id'], 'pending' => false]);