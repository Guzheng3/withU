<?php

require_once __DIR__ . '/MediaCatalog.php';

function withu_media_db()
{
    migrate_media_schema_if_needed();
    return MediaDatabase::getInstance();
}

function withu_media_fetch(int $id): ?array
{
    if ($id <= 0) return null;
    try {
        return withu_media_db()->fetch('SELECT * FROM media_library WHERE id = :id LIMIT 1', ['id' => $id]);
    } catch (Throwable $e) {
        return null;
    }
}

function withu_media_exists(int $id): bool
{
    return withu_media_fetch($id) !== null;
}

function withu_media_fetch_many(array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($id) { return $id > 0; })));
    if (!$ids) return [];
    $placeholders = [];
    $params = [];
    foreach ($ids as $index => $id) {
        $key = 'id' . $index;
        $placeholders[] = ':' . $key;
        $params[$key] = $id;
    }
    try {
        $rows = withu_media_db()->fetchAll('SELECT * FROM media_library WHERE id IN (' . implode(',', $placeholders) . ')', $params);
    } catch (Throwable $e) {
        return [];
    }
    $map = [];
    foreach ($rows as $row) $map[(int)$row['id']] = $row;
    return $map;
}

function withu_media_merge_room(array $room): array
{
    $media = withu_media_fetch((int)($room['media_id'] ?? 0));
    if (!$media) return $room;
    $media = withu_media_catalog_resolve_row(withu_media_db(), $media, 0);
    foreach ($media as $key => $value) {
        if (!array_key_exists($key, $room)) $room[$key] = $value;
    }
    return $room;
}

function withu_media_guess_type_id(array $media): int
{
    if (!empty($media['media_type_id'])) return max(1, (int)$media['media_type_id']);
    $path = mb_strtolower((string)($media['source_key'] ?? '') . ' ' . (string)($media['series_name'] ?? '') . ' ' . (string)($media['file_name'] ?? ''));
    if (preg_match('/动漫|动画|anime/u', $path)) return 3;
    if (preg_match('/综艺|show|variety/u', $path)) return 4;
    if (!empty($media['episode_number']) || preg_match('/电视剧|剧集|series|tv|s\d{1,2}e\d{1,4}/iu', $path)) return 2;
    return 1;
}

function withu_media_episode_sort(array $a, array $b): int
{
    $season = (int)($a['season_number'] ?? 0) <=> (int)($b['season_number'] ?? 0);
    if ($season !== 0) return $season;
    $episode = (int)($a['episode_number'] ?? 0) <=> (int)($b['episode_number'] ?? 0);
    if ($episode !== 0) return $episode;
    return (int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0);
}

function withu_media_upsert_file(array $file, bool $resolveDirect = false, bool $recognize = false): array
{
    $db = withu_media_db();
    $now = withu_now();
    $identified = withu_media_identify($file);
    foreach (['resolution', 'video_codec', 'audio_codec', 'width', 'height', 'duration_ms'] as $field) {
        if (array_key_exists($field, $file) && $file[$field] !== null && $file[$field] !== '') $identified[$field] = $file[$field];
    }
    $sourceKey = (string)($file['source_key'] ?? '');
    $base = array_merge([
        'source_key' => $sourceKey,
        'source_url' => (string)($file['source_url'] ?? ''),
        'file_name' => (string)($file['file_name'] ?? basename($sourceKey)),
        'file_size' => isset($file['file_size']) ? (int)$file['file_size'] : null,
        'file_etag' => (string)($file['file_etag'] ?? ''),
        'folder_created_at' => trim((string)($file['folder_created_at'] ?? '')) ?: null,
        'last_scanned_at' => $now,
        'updated_at' => $now,
        'recognized_at' => null,
    ], $identified);
    foreach (['source_id', 'resource_id', 'source_path', 'folder_path', 'file_extension', 'last_modified', 'fingerprint', 'mime_type'] as $field) {
        if (array_key_exists($field, $file)) $base[$field] = $file[$field];
    }
    // Scanning only indexes WebDAV paths. It must never resolve or persist a
    // temporary direct URL, but the local filename recognition is immediately
    // available to the resource library.
    $base['direct_url'] = null;
    $result = withu_media_catalog_upsert($db, $base, 'webdav');
    $media = $result['media'] ?? null;
    if ($media) {
        if (!empty($file['resource_id'])) {
            $db->update('media_resources', [
                'media_id' => (int)$result['id'], 'media_type_id' => (int)($identified['media_type_id'] ?? 1),
                'title' => (string)($identified['series_name'] ?? ''), 'season_number' => $identified['season_number'] ?? null,
                'episode_number' => $identified['episode_number'] ?? null, 'resolution' => $identified['resolution'] ?? null,
                'video_codec' => $identified['video_codec'] ?? null, 'audio_codec' => $identified['audio_codec'] ?? null,
                'metadata_json' => $identified['metadata_json'] ?? null, 'recognition_status' => $identified['recognition_status'] ?? 'pending',
                'updated_at' => $now,
            ], 'id = :id', ['id' => (int)$file['resource_id']]);
            $db->query('UPDATE media_catalog_sources SET source_id = :source_id, resource_id = :resource_id, source_path = :source_path, folder_path = :folder_path, fingerprint = :fingerprint, updated_at = :updated_at WHERE media_id = :media_id AND source_key = :source_key', [
                'source_id' => (int)($file['source_id'] ?? 0), 'resource_id' => (int)$file['resource_id'],
                'source_path' => (string)($file['source_path'] ?? $sourceKey), 'folder_path' => (string)($file['folder_path'] ?? '/'),
                'fingerprint' => (string)($file['fingerprint'] ?? ''), 'updated_at' => $now,
                'media_id' => (int)$result['id'], 'source_key' => $sourceKey,
            ]);
        }
        $media = withu_media_display_row($media);
        if ($recognize && !empty($media['series_key'])) {
            withu_recognize_series($db, (string)$media['series_key'], [], true);
            $media = $db->fetch('SELECT * FROM media_library WHERE id = :id LIMIT 1', ['id' => (int)$result['id']]) ?: $media;
        }
    }
    return ['id' => (int)$result['id'], 'changed' => !empty($result['changed']), 'media' => $media, 'merged' => !empty($result['merged'])];
}
