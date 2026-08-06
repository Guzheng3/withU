<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/withu.php';
require_once __DIR__ . '/../core/MediaDatabase.php';
require_once __DIR__ . '/../core/MediaSchema.php';
require_once __DIR__ . '/../core/MediaRepository.php';
require_once __DIR__ . '/../core/OpenList.php';

migrate_schema_if_needed();
$auth = new Auth();
withu_require_couple_user($auth);
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
$id = (int)($_GET['id'] ?? 0);
$sourceId = (int)($_GET['source_id'] ?? 0);
$mediaDb = withu_media_db();
$media = $mediaDb->fetch('SELECT * FROM media_library WHERE id = :id LIMIT 1', ['id' => $id]);
if (!$media) withu_json_response(['success' => false, 'message' => '媒体不存在'], 404);

try {
    $sourceRow = withu_media_catalog_fetch_source($mediaDb, $id, $sourceId);
    if ($sourceId > 0 && !$sourceRow) withu_json_response(['success' => false, 'message' => '播放来源不存在或已停用'], 404);
    if ($sourceRow) $media = withu_media_catalog_apply_source($media, $sourceRow);
    if (!withu_media_is_openlist_source($media)) {
        withu_json_response(['success' => false, 'message' => '仅允许播放 WebDAV 媒体来源'], 404);
    }
    $source = (new OpenListClient($mediaDb))->resolve($media);
    if ($source === '') withu_json_response(['success' => false, 'message' => '无法获取 WebDAV 直链'], 502);
    withu_json_response([
        'success' => true,
        'url' => $source,
        'type' => withu_media_stream_type($source, (string)($media['mime_type'] ?? '')),
        'cached' => false,
        'parsed' => false,
        'player_mode' => 'direct',
        'player_code' => 'webdav',
        'source_id' => (int)($media['source_id'] ?? 0),
        'source_label' => 'WebDAV',
    ]);
} catch (Throwable $e) {
    withu_json_response(['success' => false, 'message' => $e->getMessage()], 502);
}
