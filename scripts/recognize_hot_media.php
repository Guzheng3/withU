<?php
/**
 * Recognize only already-indexed OpenList series that appear in the configured
 * Chinese domestic Douban hot TV/movie lists. This deliberately does not scan WebDAV and does
 * not resolve playback direct links.
 *
 * Usage:
 *   php scripts/recognize_hot_media.php
 *   php scripts/recognize_hot_media.php --limit=1000
 *   php scripts/recognize_hot_media.php --limit=5000
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must run in CLI.\n");
    exit(1);
}

@set_time_limit(0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/withu.php';
require_once __DIR__ . '/../core/MediaDatabase.php';
require_once __DIR__ . '/../core/MediaSchema.php';
require_once __DIR__ . '/../core/MediaRepository.php';
require_once __DIR__ . '/../core/MediaRecognition.php';

function hot_cli_option(string $name, $default = null) {
    global $argv;
    $prefix = '--' . $name . '=';
    foreach ($argv as $arg) {
        if ($arg === '--' . $name) return true;
        if (strpos($arg, $prefix) === 0) return substr($arg, strlen($prefix));
    }
    return $default;
}

$limit = max(1, min(10000, (int)hot_cli_option('limit', 2000)));

try {
    $hotItems = withu_douban_fetch_domestic_hot();
    if (!$hotItems) {
        fwrite(STDERR, "No domestic Douban hot items were returned. Check the network connection and retry later.\n");
        exit(3);
    }

    $db = withu_media_db();
    $series = $db->fetchAll(
        "SELECT series_key, MIN(id) AS media_id
         FROM media_library
         WHERE recognition_status = 'pending' AND series_key IS NOT NULL AND series_key <> ''
         GROUP BY series_key
         ORDER BY MIN(id) ASC
         LIMIT {$limit}"
    );
    $matched = 0;
    $recognized = 0;
    $failed = 0;
    foreach ($series as $item) {
        $media = $db->fetch('SELECT * FROM media_library WHERE id = :id LIMIT 1', ['id' => (int)$item['media_id']]);
        if (!$media) continue;
        $hint = withu_media_hot_match(withu_media_display_row($media), $hotItems);
        if (!$hint) continue;
        $matched++;
        try {
            $result = withu_recognize_series($db, (string)$item['series_key'], $hint, false);
            if (!empty($result['success'])) $recognized++;
        } catch (Throwable $e) {
            $failed++;
            fwrite(STDERR, 'recognize failed [' . (string)$item['series_key'] . ']: ' . $e->getMessage() . "\n");
        }
    }
    fwrite(STDOUT, "done. indexed_series=" . count($series) . ", hot_items=" . count($hotItems) . ", matched={$matched}, recognized={$recognized}, failed={$failed}\n");
    exit($failed > 0 ? 2 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, 'fatal: ' . $e->getMessage() . "\n");
    exit(1);
}
