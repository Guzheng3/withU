<?php

/** 将资源扫描交给独立 PHP CLI 进程，避免占用 PHP-FCGI 请求。 */
final class MediaTaskLauncher
{
    public function launchScan(int $sourceId): array
    {
        if ($sourceId < 1) throw new InvalidArgumentException('WebDAV来源编号不合法。');
        $db = withu_media_db();
        $source = $db->fetch('SELECT id,name,enabled FROM media_sources WHERE id = :id LIMIT 1', ['id' => $sourceId]);
        if (!$source || !(int)$source['enabled']) throw new RuntimeException('WebDAV来源不存在或已停用。');
        $this->reconcile();
        $running = $db->fetch("SELECT id FROM media_tasks WHERE source_id = :source_id AND task_type = 'scan' AND status IN ('queued','running') LIMIT 1", ['source_id' => $sourceId]);
        if ($running) throw new RuntimeException('该 WebDAV 来源已有扫描任务在运行。');

        $now = date('Y-m-d H:i:s');
        $taskKey = 'scan-' . date('YmdHis') . '-' . bin2hex(random_bytes(8));
        $taskId = (int)$db->insert('media_tasks', [
            'task_key' => $taskKey, 'task_type' => 'scan', 'source_id' => $sourceId,
            'status' => 'queued', 'message' => '等待后台扫描进程启动。', 'created_at' => $now, 'updated_at' => $now,
        ]);
        try {
            $pid = $this->startWorker($taskId);
            $db->update('media_tasks', ['pid' => $pid, 'updated_at' => date('Y-m-d H:i:s'), 'message' => '扫描进程已启动。'], 'id = :id', ['id' => $taskId]);
        } catch (Throwable $e) {
            $db->update('media_tasks', ['status' => 'failed', 'error_message' => mb_substr($e->getMessage(), 0, 1000), 'finished_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $taskId]);
            throw $e;
        }
        return ['task_id' => $taskId, 'message' => '来源“' . (string)$source['name'] . '”已开始后台扫描。'];
    }

    public function listTasks(int $limit = 20): array
    {
        $this->reconcile();
        return withu_media_db()->fetchAll('SELECT t.*,s.name AS source_name FROM media_tasks t LEFT JOIN media_sources s ON s.id = t.source_id ORDER BY t.id DESC LIMIT ' . max(1, min(100, $limit)));
    }

    public function reconcile(): int
    {
        $db = withu_media_db();
        $updated = 0;
        foreach ($db->fetchAll("SELECT * FROM media_tasks WHERE status IN ('queued','running') ORDER BY id ASC LIMIT 50") as $task) {
            $pid = (int)($task['pid'] ?? 0);
            if ($pid > 0 && $this->isProcessRunning($pid)) continue;
            if ($task['status'] === 'queued' && strtotime((string)$task['created_at']) > time() - 120) continue;
            $db->update('media_tasks', ['status' => 'failed', 'error_message' => '后台扫描进程已退出，请重新提交。', 'finished_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int)$task['id']]);
            $updated++;
        }
        return $updated;
    }

    private function startWorker(int $taskId): int
    {
        $php = dirname(ROOT_PATH) . '/tools/php82/php.exe';
        $script = ROOT_PATH . '/scripts/run_media_task.php';
        if (!is_file($php) || !is_file($script)) throw new RuntimeException('资源任务运行环境不完整。');
        $args = ['-c', dirname(ROOT_PATH) . '/dev/php.ini', $script, '--task-id=' . $taskId];
        $ps = '$ErrorActionPreference = \'Stop\'; $p = Start-Process -FilePath ' . $this->psQuote($php)
            . ' -ArgumentList @(' . implode(',', array_map([$this, 'psQuote'], $args)) . ')'
            . ' -WorkingDirectory ' . $this->psQuote(ROOT_PATH) . ' -WindowStyle Hidden -PassThru; [Console]::Out.Write($p.Id)';
        $encoded = base64_encode(mb_convert_encoding($ps, 'UTF-16LE', 'UTF-8'));
        $pipes = [];
        $process = @proc_open(['powershell.exe', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-EncodedCommand', $encoded], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT_PATH, null, ['bypass_shell' => true]);
        if (!is_resource($process)) throw new RuntimeException('无法调用 Windows 后台任务启动器。');
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $exitCode = proc_close($process);
        $pid = (int)trim((string)$stdout);
        if ($exitCode !== 0 || $pid < 1) throw new RuntimeException('后台任务启动失败' . ($stderr !== '' ? '：' . trim($stderr) : '。'));
        return $pid;
    }

    private function isProcessRunning(int $pid): bool
    {
        if (DIRECTORY_SEPARATOR !== '\\') return true;
        $output = []; $exitCode = 1;
        @exec('tasklist /FI "PID eq ' . $pid . '" /FO CSV /NH', $output, $exitCode);
        return $exitCode === 0 && isset($output[0]) && stripos((string)$output[0], 'INFO:') !== 0 && strpos((string)$output[0], (string)$pid) !== false;
    }

    private function psQuote(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
