<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/withu.php';
require_once __DIR__ . '/../core/MediaDatabase.php';
require_once __DIR__ . '/../core/MediaSchema.php';
require_once __DIR__ . '/../core/MediaRepository.php';
require_once __DIR__ . '/../core/MediaDedupe.php';

$ids = [];
foreach (array_slice($argv, 1) as $arg) {
    if (strpos($arg, '--ids=') === 0) $ids = array_values(array_filter(array_map('intval', explode(',', substr($arg, 7)))));
}
if (!$ids) {
    $ids = array_column(withu_media_db()->fetchAll("SELECT id FROM media_library WHERE source_url IS NOT NULL AND source_url <> '' ORDER BY id DESC LIMIT 2"), 'id');
}
try {
    $results = (new MediaDedupe())->checkLinks($ids);
    echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'link check failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
