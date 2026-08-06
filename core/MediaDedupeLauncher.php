<?php

/**
 * Runs potentially long duplicate analysis and optional AI review outside a
 * PHP-FCGI request. Job files live outside the web root and contain no media
 * URLs, parser keys or credentials.
 */
final class MediaDedupeLauncher
{
    public function launchAnalysis(int $limit = 120): array
    {
        return $this->launch('analysis', ['--limit=' . max(1, min(500, $limit))], '全库重复分析');
    }

    public function launchAiReview(int $candidateId): array
    {
        if ($candidateId < 1) throw new InvalidArgumentException('重复候选编号不合法。');
        return $this->launch('ai-review', ['--candidate-id=' . $candidateId], 'AI 核验重复候选');
    }

    /** @return array<int,array<string,mixed>> */
    public function jobs(int $limit = 12): array
    {
        $jobs = [];
        foreach (glob($this->jobDirectory() . DIRECTORY_SEPARATOR . '*.json') ?: [] as $path) {
            $raw = @file_get_contents($path);
            $job = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($job) && !empty($job['id'])) $jobs[] = $job;
        }
        usort($jobs, static fn(array $a, array $b): int => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
        return array_slice($jobs, 0, max(1, min(30, $limit)));
    }

    public function reconcileJobs(): int
    {
        $updated = 0;
        foreach ($this->jobs(30) as $job) {
            if (!in_array((string)($job['status'] ?? ''), ['queued', 'running'], true)) continue;
            $pid = max(0, (int)($job['pid'] ?? 0));
            $createdAt = strtotime((string)($job['created_at'] ?? '')) ?: 0;
            if ($pid > 0 && $this->isProcessRunning($pid)) continue;
            if ((string)$job['status'] === 'queued' && $createdAt > time() - 120) continue;
            $job['status'] = 'failed';
            $job['finished_at'] = withu_now();
            $job['message'] = '后台去重任务未继续运行，可重新提交。';
            $this->writeJob($job);
            $updated++;
        }
        return $updated;
    }

    /** @param array<int,string> $arguments */
    private function launch(string $kind, array $arguments, string $label): array
    {
        $this->reconcileJobs();
        foreach ($this->jobs(30) as $job) {
            if (in_array((string)($job['status'] ?? ''), ['queued', 'running'], true)) {
                throw new RuntimeException('已有去重维护任务在运行，请等待完成后再提交。');
            }
        }
        $id = 'dedupe-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
        $job = [
            'id' => $id, 'kind' => $kind, 'label' => $label, 'arguments' => $arguments,
            'status' => 'queued', 'pid' => 0, 'created_at' => withu_now(), 'message' => '等待后台 CLI 进程启动。',
        ];
        $this->writeJob($job, true);
        try {
            $pid = $this->startWorker($job, $arguments);
            $current = $this->readJob($id) ?: $job;
            $current['pid'] = $pid;
            $current['launched_at'] = withu_now();
            $this->writeJob($current);
        } catch (Throwable $e) {
            $job['status'] = 'failed';
            $job['finished_at'] = withu_now();
            $job['message'] = '后台任务未能启动：' . mb_substr($e->getMessage(), 0, 380);
            $this->writeJob($job);
            throw $e;
        }
        return ['job_id' => $id, 'message' => $label . '已转入后台任务，完成后刷新本页查看结果。'];
    }

    /** @param array<string,mixed> $job @param array<int,string> $arguments */
    private function startWorker(array $job, array $arguments): int
    {
        $php = dirname(ROOT_PATH) . '/tools/php82/php.exe';
        $script = ROOT_PATH . '/scripts/analyze_media_duplicates.php';
        if (!is_file($php) || !is_file($script)) throw new RuntimeException('后台去重运行环境不完整。');
        $id = (string)$job['id'];
        $args = array_merge(['-c', dirname(ROOT_PATH) . '/dev/php.ini', $script], $arguments, ['--job-id=' . $id]);
        $out = $this->jobDirectory() . DIRECTORY_SEPARATOR . $id . '.out.log';
        $err = $this->jobDirectory() . DIRECTORY_SEPARATOR . $id . '.err.log';
        $ps = '$ErrorActionPreference = \'Stop\'; '
            . '$p = Start-Process -FilePath ' . $this->psQuote($php)
            . ' -ArgumentList @(' . implode(',', array_map([$this, 'psQuote'], $args)) . ')'
            . ' -WorkingDirectory ' . $this->psQuote(ROOT_PATH)
            . ' -WindowStyle Hidden -RedirectStandardOutput ' . $this->psQuote($out)
            . ' -RedirectStandardError ' . $this->psQuote($err)
            . ' -PassThru; [Console]::Out.Write($p.Id)';
        $encoded = base64_encode(mb_convert_encoding($ps, 'UTF-16LE', 'UTF-8'));
        $pipes = [];
        $process = @proc_open(['powershell.exe', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-EncodedCommand', $encoded], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT_PATH, null, ['bypass_shell' => true]);
        if (!is_resource($process)) throw new RuntimeException('无法调用 Windows 后台任务启动器。');
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        $pid = (int)trim((string)$stdout);
        if ($exitCode !== 0 || $pid < 1) throw new RuntimeException('后台进程启动失败' . ($stderr !== '' ? '：' . trim($stderr) : '。'));
        return $pid;
    }

    /** @param array<string,mixed> $job */
    private function writeJob(array $job, bool $exclusive = false): void
    {
        $path = $this->jobPath((string)$job['id']);
        $payload = json_encode($job, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        if ($exclusive) {
            $handle = @fopen($path, 'x');
            if (!$handle) throw new RuntimeException('无法创建唯一的后台去重任务记录。');
            try {
                if (fwrite($handle, $payload) === false) throw new RuntimeException('无法写入后台去重任务记录。');
            } finally { fclose($handle); }
            return;
        }
        if (@file_put_contents($path, $payload, LOCK_EX) === false) throw new RuntimeException('无法更新后台去重任务记录。');
    }

    /** @return array<string,mixed>|null */
    private function readJob(string $id): ?array
    {
        $raw = @file_get_contents($this->jobPath($id));
        $job = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($job) ? $job : null;
    }

    private function jobDirectory(): string
    {
        $dir = dirname(ROOT_PATH) . '/runtime/media-dedupe-jobs';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('无法创建后台去重任务目录。');
        return $dir;
    }

    private function jobPath(string $id): string
    {
        if (!preg_match('/^dedupe-\d{14}-[a-f0-9]{8}$/', $id)) throw new InvalidArgumentException('后台去重任务编号不合法。');
        return $this->jobDirectory() . DIRECTORY_SEPARATOR . $id . '.json';
    }

    private function isProcessRunning(int $pid): bool
    {
        if (DIRECTORY_SEPARATOR !== '\\') return true;
        $output = [];
        $exitCode = 1;
        @exec('tasklist /FI "PID eq ' . $pid . '" /FO CSV /NH', $output, $exitCode);
        return $exitCode === 0 && isset($output[0]) && stripos((string)$output[0], 'INFO:') !== 0 && strpos((string)$output[0], (string)$pid) !== false;
    }

    private function psQuote(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
