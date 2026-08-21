<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/withu.php';
require_once __DIR__ . '/../core/MediaRecognition.php';
require_once __DIR__ . '/../core/MediaDatabase.php';
require_once __DIR__ . '/../core/MediaSchema.php';
require_once __DIR__ . '/../core/MediaRepository.php';

migrate_schema_if_needed();
$auth = new Auth();
withu_require_couple_user($auth);

$action = (string)($_GET['action'] ?? 'detail');
if ($action !== 'detail') {
    withu_json_response(['success' => false, 'message' => '未知操作'], 400);
}

$id = trim((string)($_GET['id'] ?? $_GET['douban_id'] ?? ''));
if (!preg_match('/^[0-9]{4,12}$/', $id)) {
    withu_json_response(['success' => false, 'message' => '缺少有效的豆瓣 ID'], 400);
}

$data = withu_douban_metadata('', $id);
if (!$data) {
    withu_json_response(['success' => false, 'message' => '豆瓣资料暂时无法获取'], 502);
}

// Keep a stable API name for clients that call the image a poster.
$data['poster_url'] = (string)($data['cover_url'] ?? '');
withu_json_response(['success' => true, 'data' => $data]);
