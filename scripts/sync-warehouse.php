<?php
/**
 * 定时同步「解析脚本仓库」（qingyu_warehouse）到 withU 本地 runtime。
 *
 * 幂等任务：目标目录不存在时浅克隆（--depth 1），已存在时
 * fetch + --ff-only 快进合并。本地如有未推送提交则不动任何文件、
 * 以非零码退出，保证每次同步都可回退、不会覆盖本地改动。
 *
 * 用法（命令行 / 定时任务）：
 *   php scripts/sync-warehouse.php [--repo <git-url|本地路径>] [--branch main]
 *       [--dir <目标目录>] [--git <git 可执行文件>]
 *       [--upstream-report] [--quiet]
 *
 * 每次运行追加一行结果到 runtime/logs/warehouse-sync.log，
 * 并刷新 runtime/qingyu-warehouse-meta.json（页面/其他脚本可读）。
 *
 * 退出码：0 = 成功（含无更新）；1 = 失败（网络、冲突等，定时任务可重试）。
 */

$root = dirname(__DIR__);
$runtime = $root . '/runtime';

/* ---------- 参数 ---------- */
$repo = 'https://github.com/Guzheng3/qingyu_warehouse.git';
$branch = 'main';
$targetDir = $runtime . '/qingyu-warehouse';
$gitBin = null;
$upstreamReport = false;
$quiet = false;

$argv = $argv ?? [];
for ($i = 1; $i < count($argv); $i++) {
    switch ($argv[$i]) {
        case '--repo':   $repo = (string)($argv[++$i] ?? ''); break;
        case '--branch': $branch = (string)($argv[++$i] ?? ''); break;
        case '--dir':    $targetDir = (string)($argv[++$i] ?? ''); break;
        case '--git':    $gitBin = (string)($argv[++$i] ?? ''); break;
        case '--upstream-report': $upstreamReport = true; break;
        case '--quiet':  $quiet = true; break;
        case '--help':
        case '-h':
            fwrite(STDOUT, file_get_contents(__FILE__) === false ? '' : '用法见脚本头部注释。' . PHP_EOL);
            exit(0);
        default:
            fwrite(STDERR, "未知参数：{$argv[$i]}" . PHP_EOL);
            exit(2);
    }
}

function wu_log(string $message): void
{
    global $runtime;
    $dir = $runtime . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    @file_put_contents(
        $dir . '/warehouse-sync.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

/**
 * 追加结构化同步记录（JSONL，一行一条），供后台管理界面读取。
 * 字段：ts / action(clone|pull) / before / after / new_commits / changed_paths。
 */
function wu_record(array $record): void
{
    global $runtime;
    $dir = $runtime . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    $record['ts'] = date('Y-m-d H:i:s');
    @file_put_contents(
        $dir . '/warehouse-sync.jsonl',
        json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

function wu_say(string $message, bool $quiet): void
{
    if (!$quiet) {
        fwrite(STDOUT, $message . PHP_EOL);
    }
    wu_log($message);
}

/** 找 git：--git 参数 → PATH → Windows 常见安装位置。 */
function wu_find_git(?string $explicit): ?string
{
    if ($explicit !== null && $explicit !== '') {
        return is_file($explicit) ? $explicit : null;
    }
    $candidates = [
        'C:\\Program Files\\Git\\cmd\\git.exe',
        'C:\\Program Files\\Git\\bin\\git.exe',
        'C:\\Program Files (x86)\\Git\\cmd\\git.exe',
    ];
    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }
    return null; // 交给 proc_open 用 PATH 里的 git
}

/** 执行命令并抓取输出；$command 用数组形式避免 shell 转义问题。 */
function wu_run(array $command, ?string $cwd, int $timeoutSec = 300): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes, $cwd, null);
    if (!is_resource($process)) {
        return ['code' => -1, 'out' => '', 'err' => '无法启动进程：' . implode(' ', $command)];
    }
    fclose($pipes[0]);

    $deadline = microtime(true) + $timeoutSec;
    $out = '';
    $err = '';
    while (true) {
        $status = proc_get_status($process);
        $read = [$pipes[1], $pipes[2]];
        $write = null;
        $except = null;
        $secs = max(0, min(1, $deadline - microtime(true)));
        $changed = @stream_select($read, $write, $except, (int)$secs, (int)(($secs - (int)$secs) * 1e6));
        if ($changed === false) {
            break;
        }
        foreach ($read as $stream) {
            $chunk = fread($stream, 8192);
            if ($chunk === false) {
                continue;
            }
            if ($stream === $pipes[1]) {
                $out .= $chunk;
            } else {
                $err .= $chunk;
            }
        }
        if (microtime(true) >= $deadline) {
            proc_terminate($process);
            fclose($pipes[1]);
            fclose($pipes[2]);
            return ['code' => -1, 'out' => $out, 'err' => $err . ' [超时 ' . $timeoutSec . 's]'];
        }
        if ($status['running'] === false) {
            break;
        }
    }
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    return ['code' => $code, 'out' => trim($out), 'err' => trim($err)];
}

/** 把 run 结果包装成统一返回，失败即记日志。 */
function wu_git(array $args, ?string $cwd, int $timeoutSec = 300): array
{
    global $gitBin;
    $command = $gitBin !== null ? [$gitBin, ...$args] : ['git', ...$args];
    $result = wu_run($command, $cwd, $timeoutSec);
    return $result;
}

function wu_fail(string $message, bool $quiet): never
{
    wu_say('失败：' . $message, $quiet);
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$gitBin = wu_find_git($gitBin);
$logDir = $runtime . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0777, true);
}

if (!is_dir($runtime)) {
    @mkdir($runtime, 0777, true);
}

/* ---------- 1. 克隆或拉取 ---------- */
$gitDir = $targetDir . '/.git';
if (is_dir($targetDir)) {
    if (!is_dir($gitDir)) {
        return wu_fail("目标目录 {$targetDir} 已存在但不是 git 仓库，请检查后手动处理（不覆盖现有文件）。", $quiet);
    }

    // 防止误同步到别的仓库：远端不是 qingyu_warehouse 时只警告不中断
    $remoteResult = wu_git(['-C', $targetDir, 'remote', 'get-url', 'origin'], null, 30);
    if ($remoteResult['code'] === 0 && stripos($remoteResult['out'], 'qingyu_warehouse') === false) {
        wu_say("警告：{$targetDir} 的 origin 不是 qingyu_warehouse（" . $remoteResult['out'] . '），继续按配置同步。', $quiet);
    }

    $beforeResult = wu_git(['-C', $targetDir, 'rev-parse', 'HEAD'], null, 30);
    if ($beforeResult['code'] !== 0) {
        return wu_fail('读取当前 HEAD 失败：' . $beforeResult['err'], $quiet);
    }
    $before = trim($beforeResult['out']);

    $fetchResult = wu_git(['-C', $targetDir, 'fetch', 'origin', $branch], null, 300);
    if ($fetchResult['code'] !== 0) {
        return wu_fail('git fetch 失败：' . $fetchResult['err'], $quiet);
    }

    $afterResult = wu_git(['-C', $targetDir, 'rev-parse', "origin/{$branch}"], null, 30);
    if ($afterResult['code'] !== 0) {
        return wu_fail('读取远端分支失败：' . $afterResult['err'], $quiet);
    }
    $after = trim($afterResult['out']);

    if ($before === $after) {
        $meta = [
            'last_sync_at' => date('Y-m-d H:i:s'),
            'ok' => true,
            'action' => 'pull',
            'head_sha' => $after,
            'head_short' => substr($after, 0, 7),
            'remote' => $repo,
            'branch' => $branch,
            'changed' => false,
            'new_commits' => 0,
        ];
        file_put_contents($runtime . '/qingyu-warehouse-meta.json', json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        wu_record([
            'action' => 'pull',
            'before' => $before,
            'after' => $after,
            'new_commits' => 0,
            'changed_paths' => [],
        ]);
        wu_say('仓库已是最新：' . substr($after, 0, 7) . '（' . $branch . '）', $quiet);
        exit(0);
    }

    $mergeResult = wu_git(['-C', $targetDir, 'merge', '--ff-only', "origin/{$branch}"], null, 120);
    if ($mergeResult['code'] !== 0) {
        return wu_fail("快进合并失败（本地可能有未推送提交）：" . $mergeResult['err'], $quiet);
    }

    $countResult = wu_git(['-C', $targetDir, 'rev-list', '--count', "{$before}..{$after}"], null, 30);
    $newCommits = $countResult['code'] === 0 ? (int)trim($countResult['out']) : 0;

    // 变更文件清单：管理界面据此展示「哪些学校/资源在这次同步中更新」
    $diffResult = wu_git(['-C', $targetDir, 'diff', '--name-only', $before, $after], null, 60);
    $changedPaths = $diffResult['code'] === 0
        ? array_values(array_filter(array_map('trim', explode("\n", $diffResult['out'])), fn(string $p): bool => $p !== ''))
        : [];
    wu_record([
        'action' => 'pull',
        'before' => $before,
        'after' => $after,
        'new_commits' => $newCommits,
        'changed_paths' => $changedPaths,
    ]);
    $message = sprintf(
        '同步完成：%s → %s（新增 %d 个提交，%d 个文件变更）',
        substr($before, 0, 7),
        substr($after, 0, 7),
        $newCommits,
        count($changedPaths)
    );
} else {
    $parent = dirname($targetDir);
    if (!is_dir($parent)) {
        @mkdir($parent, 0777, true);
    }
    $cloneResult = wu_git(['clone', '--depth', '1', '--branch', $branch, $repo, $targetDir], null, 600);
    if ($cloneResult['code'] !== 0) {
        return wu_fail('git clone 失败：' . $cloneResult['err'], $quiet);
    }
    $headResult = wu_git(['-C', $targetDir, 'rev-parse', 'HEAD'], null, 30);
    $after = $headResult['code'] === 0 ? trim($headResult['out']) : '';
    $message = '首次克隆完成：' . substr($after, 0, 7) . '（' . $branch . '）';
    $newCommits = 0;
    $before = '';
    wu_record([
        'action' => 'clone',
        'before' => '',
        'after' => $after,
        'new_commits' => 0,
        'changed_paths' => null, // 首次克隆不枚举文件
    ]);
}

/* ---------- 2. 可选：上游（shiguang_warehouse）变化报告 ---------- */
if ($upstreamReport) {
    $python = wu_run(['python', '--version'], null, 15);
    if ($python['code'] === 0) {
        $report = wu_git(
            ['python', 'scripts/sync_upstream.py', '--dry-run'],
            $targetDir,
            300
        );
        if ($report['code'] === 0) {
            wu_say('上游报告：' . PHP_EOL . $report['out'], $quiet);
        } else {
            wu_say('上游 dry-run 未通过（仅报告，不影响本次同步）：' . PHP_EOL . $report['err'], $quiet);
        }
    } else {
        wu_say('未找到 python，跳过上游报告（仅报告，不影响本次同步）。', $quiet);
    }
}

/* ---------- 3. 落盘元信息 ---------- */
$meta = [
    'last_sync_at' => date('Y-m-d H:i:s'),
    'ok' => true,
    'action' => $before === '' ? 'clone' : 'pull',
    'head_sha' => $after,
    'head_short' => substr($after, 0, 7),
    'remote' => $repo,
    'branch' => $branch,
    'changed' => $before !== '' && $before !== $after,
    'new_commits' => $newCommits,
];
file_put_contents($runtime . '/qingyu-warehouse-meta.json', json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

wu_say($message, $quiet);
exit(0);
