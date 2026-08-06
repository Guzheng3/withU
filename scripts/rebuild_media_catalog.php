<?php

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }

@set_time_limit(0);
@ini_set('memory_limit', '768M');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/withu.php';
require_once __DIR__ . '/../core/MediaDatabase.php';
require_once __DIR__ . '/../core/MediaSchema.php';
require_once __DIR__ . '/../core/MediaRepository.php';
require_once __DIR__ . '/../core/MediaRecognition.php';
require_once __DIR__ . '/../core/MediaCatalog.php';

$apply = in_array('--apply', $argv, true);
$lockPath = ROOT_PATH . '/runtime/rebuild-media-catalog.lock';
if (!is_dir(dirname($lockPath))) @mkdir(dirname($lockPath), 0775, true);
$lockHandle = @fopen($lockPath, 'c');
if (!$lockHandle || !@flock($lockHandle, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "已有统一资源库重建任务在运行，当前任务退出。\n");
    exit(2);
}
register_shutdown_function(static function () use ($lockHandle): void {
    @flock($lockHandle, LOCK_UN);
    @fclose($lockHandle);
});

function rebuild_media_row_data(array $base, array $item, ?array $source, array $columns): array
{
    $data = $base;
    if ($source) {
        foreach (['source_key', 'source_url', 'file_name', 'file_size', 'file_etag', 'mime_type', 'collection_source_id', 'external_id', 'play_source', 'season_number', 'episode_number', 'episode_title'] as $field) {
            if (array_key_exists($field, $source)) $data[$field] = $source[$field];
        }
    }
    $data['series_key'] = $item['series_key'];
    $data['catalog_key'] = $item['catalog_key'];
    $data['direct_url'] = null;
    $data['last_scanned_at'] = $data['last_scanned_at'] ?? withu_now();
    $data['updated_at'] = withu_now();
    unset($data['id']);
    return array_intersect_key($data, array_flip($columns));
}

function rebuild_source_sort(array $a, array $b): int
{
    return ((int)($b['is_primary'] ?? 0) <=> (int)($a['is_primary'] ?? 0))
        ?: ((int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0));
}

$columns = [
    'source_key', 'source_url', 'direct_url', 'file_name', 'series_key', 'catalog_key', 'series_name',
    'season_number', 'episode_number', 'episode_title', 'media_type_id', 'collection_source_id',
    'external_id', 'source_type_name', 'play_source', 'video_codec', 'audio_codec', 'browser_playback',
    'file_size', 'file_md5', 'file_etag', 'mime_type', 'duration_ms', 'width', 'height', 'resolution',
    'tags', 'douban_id', 'tmdb_id', 'cast_names', 'summary', 'cover_url', 'backdrop_url', 'intro_start_ms',
    'intro_end_ms', 'recognition_status', 'recognition_source', 'recognized_at', 'last_scanned_at', 'folder_created_at',
    'created_at', 'updated_at',
];

try {
    migrate_schema_if_needed();
    $mediaDb = withu_media_db();
    $oldRows = $mediaDb->fetchAll('SELECT * FROM media_library ORDER BY id ASC');
    $allSources = $mediaDb->fetchAll('SELECT * FROM media_catalog_sources ORDER BY media_id ASC, is_primary DESC, id ASC');
    $sourcesByMedia = [];
    foreach ($allSources as $source) $sourcesByMedia[(int)$source['media_id']][] = $source;

    $groups = [];
    $synthetic = [];
    foreach ($oldRows as $row) {
        $oldId = (int)$row['id'];
        $sources = $sourcesByMedia[$oldId] ?? [];
        usort($sources, 'rebuild_source_sort');
        if (!$sources) {
            $item = withu_media_catalog_prepare($row);
            $groups[(string)$item['catalog_key']][] = ['old_id' => $oldId, 'base' => $row, 'source' => null, 'item' => $item];
            $synthetic[$oldId] = $row;
            continue;
        }
        foreach ($sources as $source) {
            $candidate = array_merge($row, $source);
            $item = withu_media_catalog_prepare($candidate);
            $groups[(string)$item['catalog_key']][] = ['old_id' => $oldId, 'base' => $row, 'source' => $source, 'item' => $item];
        }
    }

    $duplicateCount = 0;
    $largestGroup = 0;
    foreach ($groups as $entries) {
        $duplicateCount += max(0, count($entries) - 1);
        $largestGroup = max($largestGroup, count($entries));
    }
    fwrite(STDOUT, '原主记录=' . count($oldRows) . '，来源=' . count($allSources) . '，重建分集=' . count($groups) . '，同分集来源归并=' . $duplicateCount . '，最大来源数=' . $largestGroup . "\n");
    if (!$apply) {
        fwrite(STDOUT, "预览模式：不会修改数据库。确认备份后使用 --apply 执行。\n");
        exit(0);
    }

    $pdo = $mediaDb->getPDO();
    $pdo->beginTransaction();
    $mediaDb->query('DROP TABLE IF EXISTS media_library_rebuild');
    $mediaDb->query('CREATE TABLE media_library_rebuild LIKE media_library');

    $claimedOldIds = [];
    $oldToTarget = [];
    $targetIds = [];
    $groupTargets = [];
    $sourceTargets = [];
    $preferredKeyByOld = [];
    $preferredPrimaryByOld = [];
    foreach ($groups as $catalogKey => $entries) {
        foreach ($entries as $entry) {
            $oldId = (int)$entry['old_id'];
            $isPrimary = !empty($entry['source']['is_primary']);
            if (!isset($preferredKeyByOld[$oldId]) || ($isPrimary && empty($preferredPrimaryByOld[$oldId]))) {
                $preferredKeyByOld[$oldId] = $catalogKey;
                $preferredPrimaryByOld[$oldId] = $isPrimary;
            }
        }
    }
    $preferredKeys = array_values(array_unique(array_values($preferredKeyByOld)));
    $orderedGroups = [];
    foreach ($preferredKeys as $preferredKey) if (isset($groups[$preferredKey])) $orderedGroups[$preferredKey] = $groups[$preferredKey];
    foreach ($groups as $catalogKey => $entries) if (!isset($orderedGroups[$catalogKey])) $orderedGroups[$catalogKey] = $entries;

    foreach ($orderedGroups as $catalogKey => $entries) {
        usort($entries, static function (array $a, array $b): int {
            return ((int)($b['source']['is_primary'] ?? 0) <=> (int)($a['source']['is_primary'] ?? 0))
                ?: ((int)$a['old_id'] <=> (int)$b['old_id'])
                ?: ((int)($a['source']['id'] ?? 0) <=> (int)($b['source']['id'] ?? 0));
        });
        $chosen = $entries[0];
        $explicitId = null;
        foreach ($entries as $entry) {
            $oldId = (int)$entry['old_id'];
            if (($preferredKeyByOld[$oldId] ?? null) === $catalogKey && !isset($claimedOldIds[$oldId])) {
                $explicitId = $oldId;
                break;
            }
        }
        $data = rebuild_media_row_data($chosen['base'], $chosen['item'], $chosen['source'], $columns);
        if ($explicitId !== null) {
            $data['id'] = $explicitId;
            $claimedOldIds[$explicitId] = true;
        }
        $targetId = (int)$mediaDb->insert('media_library_rebuild', $data);
        if ($explicitId !== null) $targetId = $explicitId;
        $targetIds[$targetId] = true;
        $groupTargets[$catalogKey] = $targetId;
        foreach ($entries as $entry) {
            $oldId = (int)$entry['old_id'];
            if (($preferredKeyByOld[$oldId] ?? null) === $catalogKey) $oldToTarget[$oldId] = $targetId;
            if (!empty($entry['source']['id'])) $sourceTargets[(int)$entry['source']['id']] = $targetId;
        }
        $primarySource = null;
        foreach ($entries as $entry) if (!empty($entry['source']['is_primary'])) { $primarySource = (int)$entry['source']['id']; break; }
        if (!$primarySource && !empty($entries[0]['source']['id'])) $primarySource = (int)$entries[0]['source']['id'];
        $groupTargets[$catalogKey] = ['target_id' => $targetId, 'primary_source_id' => $primarySource];
    }
    foreach ($preferredKeyByOld as $oldId => $preferredKey) {
        if (isset($oldToTarget[$oldId], $groupTargets[$preferredKey])) continue;
        if (isset($groupTargets[$preferredKey]['target_id'])) $oldToTarget[$oldId] = (int)$groupTargets[$preferredKey]['target_id'];
    }

    foreach ($sourceTargets as $sourceId => $targetId) {
        $mediaDb->update('media_catalog_sources', ['media_id' => $targetId, 'updated_at' => withu_now()], 'id = :id', ['id' => $sourceId]);
    }
    foreach ($groupTargets as $target) {
        $targetId = (int)$target['target_id'];
        $mediaDb->update('media_catalog_sources', ['is_primary' => 0], 'media_id = :media_id', ['media_id' => $targetId]);
        $sourceId = (int)($target['primary_source_id'] ?? 0);
        if ($sourceId > 0) $mediaDb->update('media_catalog_sources', ['is_primary' => 1], 'id = :id AND media_id = :media_id', ['id' => $sourceId, 'media_id' => $targetId]);
        else {
            $first = $mediaDb->fetch('SELECT id FROM media_catalog_sources WHERE media_id = :media_id ORDER BY id ASC LIMIT 1', ['media_id' => $targetId]);
            if ($first) $mediaDb->update('media_catalog_sources', ['is_primary' => 1], 'id = :id', ['id' => (int)$first['id']]);
        }
    }

    $mainDb = Database::getInstance();
    foreach ($oldToTarget as $oldId => $targetId) {
        if ($oldId === $targetId) continue;
        try { $mainDb->update('watch_rooms', ['media_id' => $targetId], 'media_id = :media_id', ['media_id' => $oldId]); } catch (Throwable $e) {}
        try { $mainDb->update('watch_history', ['media_id' => $targetId], 'media_id = :media_id', ['media_id' => $oldId]); } catch (Throwable $e) {}
        $checks = $mediaDb->fetchAll('SELECT id,url_hash FROM media_link_checks WHERE media_id = :media_id', ['media_id' => $oldId]);
        foreach ($checks as $check) {
            $same = $mediaDb->fetch('SELECT id FROM media_link_checks WHERE media_id = :media_id AND url_hash = :url_hash LIMIT 1', ['media_id' => $targetId, 'url_hash' => $check['url_hash']]);
            if ($same) $mediaDb->delete('media_link_checks', 'id = :id', ['id' => (int)$check['id']]);
            else $mediaDb->update('media_link_checks', ['media_id' => $targetId], 'id = :id', ['id' => (int)$check['id']]);
        }
    }

    $oldTable = 'media_library_before_rebuild';
    $mediaDb->query('DROP TABLE IF EXISTS `' . $oldTable . '`');
    $mediaDb->query('RENAME TABLE media_library TO `' . $oldTable . '`, media_library_rebuild TO media_library');
    $mediaDb->query('DROP TABLE `' . $oldTable . '`');
    foreach ($synthetic as $oldId => $row) {
        $targetId = (int)($oldToTarget[$oldId] ?? 0);
        if ($targetId > 0) withu_media_catalog_attach_source($mediaDb, $row, $targetId, withu_media_catalog_source_kind($row));
    }
    if ($pdo->inTransaction()) $pdo->commit();
    fwrite(STDOUT, '完成：统一分集=' . count($groups) . '，保留主记录=' . count($targetIds) . '，来源全部重新挂接。' . "\n");
    exit(0);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, '重建失败：' . $e->getMessage() . PHP_EOL);
    exit(1);
}
