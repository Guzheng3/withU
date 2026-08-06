<?php
header('Content-Type: text/plain; charset=UTF-8');
@set_time_limit(0);
@ini_set('max_execution_time', '0');
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
// Do not hold the PHP session file lock while OpenList resolves the signed
// source URL and the browser starts streaming it.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
$db = withu_media_db();
$id = (int)($_GET['id'] ?? 0);
$mode = strtolower(trim((string)($_GET['mode'] ?? 'direct')));
$media = $db->fetch('SELECT * FROM media_library WHERE id = :id LIMIT 1', ['id' => $id]);
if (!$media) withu_json_response(['success' => false, 'message' => '媒体不存在'], 404);

$webdav = rtrim((string)get_setting('openlist_webdav_url', ''), '/');
$source = (string)($media['source_url'] ?? '');
$sourceHost = (string)(parse_url($source, PHP_URL_HOST) ?: '');
$openlistHost = (string)(parse_url($webdav, PHP_URL_HOST) ?: '');
$isOpenListSource = $webdav !== '' && (strpos($source, $webdav) === 0 || ($sourceHost !== '' && $sourceHost === $openlistHost));
if (!$isOpenListSource) {
    $direct = withu_media_url($media);
    if ($direct === '') withu_json_response(['success' => false, 'message' => '媒体地址为空'], 404);
    header('Location: ' . $direct, true, 302);
    exit;
}

// Always resolve from the stored WebDAV path at play time. Signed URLs are
// intentionally never treated as durable media-library addresses.
$client = new OpenListClient($db);
$direct = $client->resolve($media);
if ($direct === '') withu_json_response(['success' => false, 'message' => '无法获取 OpenList 直链'], 502);

// OpenList/Synctv-style playback keeps the signed OpenList URL as the media
// source. No server-side decoding or FFmpeg cache is involved for either the
// direct or legacy "compatible" mode; the browser receives the original stream.
$db->update('media_library', ['direct_url' => null, 'browser_playback' => 'direct', 'updated_at' => withu_now()], 'id = :id', ['id' => $id]);
header('X-WithU-Playback: openlist-direct');
header('Location: ' . $direct, true, 302);
exit;
