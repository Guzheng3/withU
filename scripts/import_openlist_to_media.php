<?php
/**
 * OpenList/WebDAV -> WithU media_library
 *
 * Usage:
 *   php scripts/import_openlist_to_media.php
 *   php scripts/import_openlist_to_media.php --resolve-direct
 *   php scripts/import_openlist_to_media.php --limit=5000 --time-limit=3600
 *   php scripts/import_openlist_to_media.php --hot-api-base=https://example.com --hot-tv --hot-movie
 *   php scripts/import_openlist_to_media.php --hot-api=https://example.com/api/douban/get-recent-hot-tv/v1
 *   php scripts/import_openlist_to_media.php --douban-token=YOUR_TOKEN
 *   php scripts/import_openlist_to_media.php --no-hot
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must run in CLI.\n");
    exit(1);
}

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

function cli_option(string $name, $default = null) {
    global $argv;
    $prefix = '--' . $name . '=';
    foreach ($argv as $arg) {
        if ($arg === '--' . $name) return true;
        if (strpos($arg, $prefix) === 0) return substr($arg, strlen($prefix));
    }
    return $default;
}

$resolveDirect = (bool)cli_option('resolve-direct', false);
$limit = max(0, (int)cli_option('limit', 0));
$timeLimit = max(0, (int)cli_option('time-limit', 0));
$hotApiBase = trim((string)cli_option('hot-api-base', ''));
$doubanToken = trim((string)cli_option('douban-token', ''));
$doubanApiBase = trim((string)cli_option('douban-api-base', ''));
$hotApis = [];
$hotApi = trim((string)cli_option('hot-api', ''));
if ($hotApi !== '') $hotApis[] = $hotApi;
if ((bool)cli_option('hot-tv', false)) $hotApis[] = '/api/douban/get-recent-hot-tv/v1';
if ((bool)cli_option('hot-movie', false)) $hotApis[] = '/api/douban/get-recent-hot-movie/v1';
$recognizeHot = !(bool)cli_option('no-hot', false);
$useConfiguredHot = $recognizeHot && empty($hotApis);
$start = time();
$seen = 0;
$added = 0;
$updated = 0;
$failed = 0;
$hotMatched = 0;
$hotRecognized = 0;
$lastMessage = '';

try {
    migrate_schema_if_needed();
    if ($hotApiBase !== '' || $doubanToken !== '' || $doubanApiBase !== '') {
        $mainDb = Database::getInstance();
        $pairs = [];
        if ($hotApiBase !== '') $pairs['douban_hot_api_base'] = ['value' => $hotApiBase, 'description' => '豆瓣热榜 API 基础地址'];
        if ($doubanApiBase !== '') $pairs['douban_api_base'] = ['value' => $doubanApiBase, 'description' => 'Just One API 基础地址'];
        if ($doubanToken !== '') $pairs['douban_api_token'] = ['value' => $doubanToken, 'description' => 'Just One API token'];
        foreach ($pairs as $key => $setting) {
            $existing = $mainDb->fetch('SELECT id FROM settings WHERE `key` = :k LIMIT 1', ['k' => $key]);
            if ($existing) $mainDb->update('settings', ['value' => $setting['value']], 'id = :id', ['id' => (int)$existing['id']]);
            else $mainDb->insert('settings', ['key' => $key, 'value' => $setting['value'], 'description' => $setting['description']]);
        }
    }
    if ($useConfiguredHot) {
        $tvPath = trim((string)get_setting('douban_hot_tv_path', '/api/douban/get-recent-hot-tv/v1'));
        $moviePath = trim((string)get_setting('douban_hot_movie_path', '/api/douban/get-recent-hot-movie/v1'));
        if ($tvPath !== '') $hotApis[] = $tvPath;
        if ($moviePath !== '') $hotApis[] = $moviePath;
    }
    $mediaDb = withu_media_db();
    migrate_media_schema_if_needed($mediaDb);
    $hotItems = $recognizeHot ? withu_media_fetch_hot_items($hotApis) : [];
$hotSeries = [];
$seriesKeys = [];
    $client = new OpenListClient($mediaDb);
    fwrite(STDOUT, "withU media import started. resolve_direct=" . ($resolveDirect ? 'yes' : 'no') . ", limit={$limit}, time_limit={$timeLimit}, hot_items=" . count($hotItems) . "\n");
    $client->scanEach(function (array $file, int $count) use (&$seen, &$added, &$updated, &$failed, &$hotMatched, &$lastMessage, &$hotSeries, &$seriesKeys, $hotItems, $resolveDirect, $limit, $timeLimit, $start, $mediaDb) {
        if ($limit > 0 && $seen >= $limit) return false;
        if ($timeLimit > 0 && time() - $start >= $timeLimit) return false;
        $seen++;
        try {
            $result = withu_media_upsert_file($file, $resolveDirect);
            if (!empty($result['changed'])) $added++; else $updated++;
            $media = $result['media'] ?? null;
            if (is_array($media)) {
                $display = withu_media_display_row($media);
                $seriesKey = trim((string)($display['series_key'] ?? ''));
                if ($seriesKey !== '') $seriesKeys[$seriesKey] = true;
            }
            if ($media && $hotItems) {
                $hint = withu_media_hot_match($media, $hotItems);
                if ($hint) {
                    $seriesKey = (string)(withu_media_display_row($media)['series_key'] ?? '');
                    if ($seriesKey !== '' && !isset($hotSeries[$seriesKey])) {
                        $hotSeries[$seriesKey] = $hint;
                        $hotMatched++;
                    }
                }
            }
            $lastMessage = '[' . $seen . '] ' . (string)($file['source_key'] ?? '');
            if ($seen % 100 === 0) {
                fwrite(STDOUT, "processed={$seen} added={$added} updated={$updated} hot_matched={$hotMatched} failed={$failed}\n");
            }
        } catch (Throwable $e) {
            $failed++;
            $lastMessage = '[' . $seen . '] failed: ' . $e->getMessage();
            fwrite(STDERR, $lastMessage . "\n");
        }
        return true;
    });
    $recognizedSeries = 0;
    foreach (array_keys($seriesKeys) as $seriesKey) {
        try {
            $hint = $hotSeries[$seriesKey] ?? [];
            $result = withu_recognize_series($mediaDb, (string)$seriesKey, $hint, false);
            if (!empty($result['success']) && empty($result['skipped'])) $recognizedSeries++;
        } catch (Throwable $e) {
            $failed++;
            fwrite(STDERR, 'series recognize failed [' . $seriesKey . ']: ' . $e->getMessage() . "\n");
        }
    }
    $now = withu_now();
    $mediaDb->query(
        "UPDATE media_scan_state SET last_run_at = :last_run_at, last_message = :message, updated_at = :updated_at WHERE source = 'openlist'",
        ['last_run_at' => $now, 'message' => "processed={$seen}, added={$added}, updated={$updated}, series={$recognizedSeries}, hot_matched={$hotMatched}, failed={$failed}", 'updated_at' => $now]
    );
    fwrite(STDOUT, "done. processed={$seen}, added={$added}, updated={$updated}, series={$recognizedSeries}, hot_matched={$hotMatched}, failed={$failed}\n");
    exit($failed > 0 ? 2 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, "fatal: " . $e->getMessage() . "\n");
    exit(1);
}
