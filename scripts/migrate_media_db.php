<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/MediaDatabase.php';
require_once __DIR__ . '/../core/MediaSchema.php';

try {
    migrate_media_schema_if_needed();
    echo "withu_media schema ready: " . withu_media_db_name() . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, "media schema failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
