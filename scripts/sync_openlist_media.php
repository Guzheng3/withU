<?php
/**
 * 完整增量同步 OpenList/WebDAV 到 WithU 媒体库。
 * 指纹未变化时只更新时间；扫描完整成功后同步删除远端已不存在的资源。
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "This script must run in CLI.\n"); exit(1); }
@set_time_limit(0);
@ini_set('memory_limit', '512M');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/withu.php';
require_once __DIR__ . '/../core/MediaDatabase.php';
require_once __DIR__ . '/../core/MediaSchema.php';
require_once __DIR__ . '/../core/MediaRepository.php';
require_once __DIR__ . '/../core/OpenList.php';
require_once __DIR__ . '/../core/MediaRecognition.php';

$runtimeDir = dirname(ROOT_PATH) . DIRECTORY_SEPARATOR . 'runtime';
if (!is_dir($runtimeDir)) @mkdir($runtimeDir, 0775, true);
$lockHandle = @fopen($runtimeDir . DIRECTORY_SEPARATOR . 'openlist-sync.lock', 'c');
if (!$lockHandle || !@flock($lockHandle, LOCK_EX | LOCK_NB)) { fwrite(STDOUT, "已有同步任务正在运行，跳过本次执行。\n"); exit(0); }

function sync_openlist_fingerprint(array $file): string
{
    return hash('sha256', implode('|', [(string)($file['source_key'] ?? ''), (string)($file['file_name'] ?? ''), (string)($file['file_size'] ?? ''), (string)($file['file_etag'] ?? ''), (string)($file['last_modified'] ?? '')]));
}

function sync_openlist_remove_media($db, int $mediaId, int $sourceId): void
{
    $db->delete('media_catalog_sources', 'id = :id', ['id' => $sourceId]);
    $otherSource = $db->fetch("SELECT id FROM media_catalog_sources WHERE media_id = :media_id AND status = 'active' LIMIT 1", ['media_id' => $mediaId]);
    if ($otherSource) return;
    $resources = $db->fetchAll('SELECT id FROM media_resources WHERE media_id = :media_id', ['media_id' => $mediaId]);
    foreach ($resources as $resource) {
        $resourceId = (int)$resource['id'];
        $db->delete('media_resource_subtitles', 'resource_id = :id', ['id' => $resourceId]);
        $db->delete('media_resource_segments', 'resource_id = :id', ['id' => $resourceId]);
    }
    $db->delete('media_resources', 'media_id = :media_id', ['media_id' => $mediaId]);
    $db->delete('media_library', 'id = :id', ['id' => $mediaId]);
}

$seen = [];
$changedSeries = [];
$scanned = $added = $changed = $skipped = $removed = $failed = 0;

try {
    migrate_schema_if_needed();
    $db = withu_media_db();
    $client = new OpenListClient($db);
    $client->scanEach(function (array $file) use (&$seen, &$changedSeries, &$scanned, &$added, &$changed, &$skipped, &$failed, $db): bool {
        $sourceKey = trim((string)($file['source_key'] ?? ''));
        if ($sourceKey === '') return true;
        $seen[$sourceKey] = true;
        $scanned++;
        $file['fingerprint'] = sync_openlist_fingerprint($file);
        $existing = $db->fetch('SELECT id,fingerprint FROM media_library WHERE source_key = :source_key LIMIT 1', ['source_key' => $sourceKey]);
        $source = $db->fetch("SELECT id FROM media_catalog_sources WHERE source_kind = 'webdav' AND source_key = :source_key LIMIT 1", ['source_key' => $sourceKey]);
        if ($existing && $source && hash_equals((string)($existing['fingerprint'] ?? ''), $file['fingerprint'])) {
            $db->update('media_library', ['last_scanned_at' => withu_now()], 'id = :id', ['id' => (int)$existing['id']]);
            $skipped++;
            return true;
        }
        try {
            $result = withu_media_upsert_file($file, false, false);
            if ($existing) $changed++; else $added++;
            $media = $result['media'] ?? null;
            if (is_array($media)) {
                $seriesKey = trim((string)(withu_media_display_row($media)['series_key'] ?? ''));
                if ($seriesKey !== '') $changedSeries[$seriesKey] = true;
            }
        } catch (Throwable $e) {
            $failed++;
            fwrite(STDERR, '资源同步失败 [' . $sourceKey . ']: ' . $e->getMessage() . "\n");
        }
        return true;
    });
    if ($client->scanErrorCount() > 0) throw new RuntimeException('WebDAV 目录读取失败，已停止删除阶段，防止误删媒体库。');

    $staleSources = $db->fetchAll("SELECT id,media_id,source_key FROM media_catalog_sources WHERE source_kind = 'webdav' AND status = 'active'");
    foreach ($staleSources as $source) {
        if (isset($seen[(string)$source['source_key']])) continue;
        sync_openlist_remove_media($db, (int)$source['media_id'], (int)$source['id']);
        $removed++;
    }
    $recognized = 0;
    foreach (array_keys($changedSeries) as $seriesKey) {
        try {
            $result = withu_recognize_series($db, $seriesKey, [], false);
            if (!empty($result['success']) && empty($result['skipped'])) $recognized++;
        } catch (Throwable $e) { $failed++; fwrite(STDERR, '影视识别失败 [' . $seriesKey . ']: ' . $e->getMessage() . "\n"); }
    }
    $message = "scanned={$scanned}, added={$added}, changed={$changed}, skipped={$skipped}, removed={$removed}, recognized={$recognized}, failed={$failed}";
    $db->query("UPDATE media_scan_state SET last_run_at = :run_at, last_message = :message, updated_at = :updated_at WHERE source = 'openlist'", ['run_at' => withu_now(), 'message' => $message, 'updated_at' => withu_now()]);
    fwrite(STDOUT, $message . "\n");
    exit($failed > 0 ? 2 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, '增量同步失败：' . $e->getMessage() . "\n");
    exit(1);
} finally {
    if ($lockHandle) { @flock($lockHandle, LOCK_UN); @fclose($lockHandle); }
}
