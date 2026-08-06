<?php

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
@set_time_limit(0);
@ini_set('memory_limit', '512M');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/withu.php';
require_once __DIR__ . '/../core/MediaDatabase.php';
require_once __DIR__ . '/../core/MediaSchema.php';
require_once __DIR__ . '/../core/MediaSource.php';
require_once __DIR__ . '/../core/MediaTranscode.php';
require_once __DIR__ . '/../core/MediaScanner.php';
require_once __DIR__ . '/../core/MediaCatalog.php';
require_once __DIR__ . '/../core/MediaRepository.php';
require_once __DIR__ . '/../core/MediaRecognition.php';

$taskId = 0;
foreach ($argv as $arg) if (strpos($arg, '--task-id=') === 0) $taskId = (int)substr($arg, 10);
if ($taskId < 1) { fwrite(STDERR, "--task-id is required\n"); exit(2); }

$db = null;
try {
    $db = MediaDatabase::getInstance();
    migrate_media_schema_if_needed($db);
    $task = $db->fetch('SELECT * FROM media_tasks WHERE id = :id LIMIT 1', ['id' => $taskId]);
    if (!$task || $task['task_type'] !== 'scan') throw new RuntimeException('媒体任务不存在或类型不支持。');
    $source = $db->fetch('SELECT * FROM media_sources WHERE id = :id AND enabled = 1 LIMIT 1', ['id' => (int)$task['source_id']]);
    if (!$source) throw new RuntimeException('扫描来源不存在或已停用。');
    $scanner = new MediaScanner($db, $source, $taskId);
    $result = $scanner->run();
    fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(0);
} catch (Throwable $e) {
    if ($db && $taskId > 0) {
        try { $db->update('media_tasks', ['status' => 'failed', 'error_message' => mb_substr($e->getMessage(), 0, 1000), 'finished_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $taskId]); } catch (Throwable $ignored) {}
    }
    fwrite(STDERR, 'media task failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
