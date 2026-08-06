<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/withu.php';
require_once __DIR__ . '/../core/MediaDatabase.php';
require_once __DIR__ . '/../core/MediaSchema.php';
require_once __DIR__ . '/../core/MediaRepository.php';
require_once __DIR__ . '/../core/MediaDedupe.php';
$limit = 120;
$candidateId = 0;
$jobId = '';
foreach (array_slice($argv, 1) as $arg) {
    if (strpos($arg, '--limit=') === 0) $limit = max(1, min(500, (int)substr($arg, 8)));
    if (strpos($arg, '--candidate-id=') === 0) $candidateId = max(0, (int)substr($arg, 15));
    if (strpos($arg, '--job-id=') === 0) $jobId = (string)substr($arg, 9);
}

function dedupe_job_path(string $jobId): string
{
    if (!preg_match('/^dedupe-\d{14}-[a-f0-9]{8}$/', $jobId)) throw new InvalidArgumentException('后台去重任务编号不合法。');
    return dirname(ROOT_PATH) . '/runtime/media-dedupe-jobs/' . $jobId . '.json';
}

function dedupe_job_update(string $jobId, array $changes): void
{
    if ($jobId === '') return;
    $path = dedupe_job_path($jobId);
    $raw = @file_get_contents($path);
    $job = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($job)) throw new RuntimeException('后台去重任务记录不存在。');
    $job = array_merge($job, $changes);
    if (@file_put_contents($path, json_encode($job, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX) === false) {
        throw new RuntimeException('无法更新后台去重任务记录。');
    }
}

try {
    dedupe_job_update($jobId, ['status' => 'running', 'pid' => getmypid(), 'started_at' => withu_now(), 'message' => $candidateId > 0 ? '后台 CLI 正在进行 AI 核验。' : '后台 CLI 正在分析全库重复候选。']);
    $dedupe = new MediaDedupe();
    $result = $candidateId > 0 ? $dedupe->reviewCandidateWithAi($candidateId) : $dedupe->analyze($limit);
    $message = $candidateId > 0 ? (string)$result['message'] : ('全库重复分析完成，新增候选 ' . (int)$result['created'] . ' 条。');
    dedupe_job_update($jobId, ['status' => 'success', 'finished_at' => withu_now(), 'message' => $message, 'result' => $result]);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    try {
        dedupe_job_update($jobId, ['status' => 'failed', 'finished_at' => withu_now(), 'message' => mb_substr($e->getMessage(), 0, 500)]);
    } catch (Throwable $jobError) {
        // Keep the original worker error as the CLI failure.
    }
    fwrite(STDERR, 'duplicate analysis failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
