<?php
/**
 * 解析脚本仓库（qingyu_warehouse）同步管理的数据层。
 * 供后台「仓库同步管理」页面读取同步状态、同步记录、学校索引，
 * 并在页面上触发一次同步。
 *
 * 数据来源：
 * - runtime/qingyu-warehouse/          同步到本地的解析脚本仓库（git 浅克隆）
 * - runtime/qingyu-warehouse-meta.json 最近一次同步元信息（sync-warehouse.php 写入）
 * - runtime/logs/warehouse-sync.jsonl  结构化同步记录（一行一条 JSON）
 * - runtime/logs/warehouse-sync.log    人读同步日志
 */

if (!function_exists('withu_warehouse_root')) {
    /** withU 仓库根目录（backend/app/core/ 上溯 3 级）。 */
    function withu_warehouse_root(): string
    {
        return dirname(__DIR__, 3);
    }
}

if (!function_exists('withu_warehouse_repo_dir')) {
    /** 本地仓库克隆目录。 */
    function withu_warehouse_repo_dir(): string
    {
        return withu_warehouse_root() . '/runtime/qingyu-warehouse';
    }
}

if (!function_exists('withu_warehouse_logs_dir')) {
    function withu_warehouse_logs_dir(): string
    {
        return withu_warehouse_root() . '/runtime/logs';
    }
}

if (!function_exists('withu_warehouse_is_synced')) {
    function withu_warehouse_is_synced(): bool
    {
        return is_dir(withu_warehouse_repo_dir() . '/.git');
    }
}

if (!function_exists('withu_warehouse_meta')) {
    /** 最近一次同步元信息；未同步时返回默认值。 */
    function withu_warehouse_meta(): array
    {
        $defaults = [
            'last_sync_at' => null,
            'ok' => false,
            'action' => null,
            'head_sha' => null,
            'head_short' => null,
            'remote' => null,
            'branch' => 'main',
            'changed' => false,
            'new_commits' => 0,
        ];
        $path = withu_warehouse_root() . '/runtime/qingyu-warehouse-meta.json';
        if (!is_file($path)) {
            return $defaults;
        }
        $decoded = json_decode((string)file_get_contents($path), true);
        return is_array($decoded) ? array_merge($defaults, $decoded) : $defaults;
    }
}

if (!function_exists('withu_warehouse_records')) {
    /**
     * 结构化同步记录（最新在前）。每条：
     * ts / action(clone|pull) / before / after / new_commits / changed_paths
     */
    function withu_warehouse_records(int $limit = 50): array
    {
        $path = withu_warehouse_logs_dir() . '/warehouse-sync.jsonl';
        if (!is_file($path)) {
            return [];
        }
        $records = [];
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $records[] = $decoded;
            }
        }
        fclose($handle);
        return array_slice(array_reverse($records), 0, $limit);
    }
}

if (!function_exists('withu_warehouse_log_tail')) {
    /** 人读同步日志末尾 N 行。 */
    function withu_warehouse_log_tail(int $lines = 40): array
    {
        $path = withu_warehouse_logs_dir() . '/warehouse-sync.log';
        if (!is_file($path)) {
            return [];
        }
        $raw = file($path, FILE_IGNORE_NEW_LINES);
        if ($raw === false) {
            return [];
        }
        return array_slice($raw, max(0, count($raw) - $lines));
    }
}

if (!function_exists('withu_warehouse_parse_index')) {
    /**
     * 解析 index/root_index.yaml 的 schools 列表（行级定向解析，
     * 只认 schools: 下的 "- id: ..." 条目与 id/name/initial/resource_folder 键）。
     */
    function withu_warehouse_parse_index(string $yaml): array
    {
        $schools = [];
        $current = null;
        $inSchools = false;
        foreach (preg_split('/\r\n|\r|\n/', $yaml) as $line) {
            $trimmed = ltrim($line);
            if (!$inSchools) {
                if (preg_match('/^schools:\s*(?:#.*)?$/', $trimmed) === 1) {
                    $inSchools = true;
                }
                continue;
            }
            if (preg_match('/^-\s*id:\s*"?([^"#\s]+)"?\s*(?:#.*)?$/', $trimmed, $m) === 1) {
                if ($current !== null) {
                    $schools[] = $current;
                }
                $current = [
                    'id' => $m[1],
                    'name' => $m[1],
                    'initial' => '',
                    'resource_folder' => $m[1],
                ];
                continue;
            }
            if ($current !== null
                && preg_match('/^\s{2,}([a-z_]+):\s*"?([^"#]*)"?\s*(?:#.*)?$/', $line, $m) === 1
                && in_array($m[1], ['name', 'initial', 'resource_folder'], true)) {
                $current[$m[1]] = trim($m[2]);
            }
        }
        if ($current !== null) {
            $schools[] = $current;
        }
        return $schools;
    }
}

if (!function_exists('withu_warehouse_folder_updates')) {
    /**
     * 从同步记录里汇总出「资源文件夹 → 最近一次变更时间」映射。
     * changed_paths 中 resources/<folder>/... 归属到该文件夹，
     * index/root_index.yaml 单独记录为 *index。
     */
    function withu_warehouse_folder_updates(): array
    {
        $updates = [];
        foreach (withu_warehouse_records(200) as $record) {
            $ts = (string)($record['ts'] ?? '');
            $paths = $record['changed_paths'] ?? null;
            if (!is_array($paths)) {
                continue;
            }
            foreach ($paths as $path) {
                $path = (string)$path;
                if ($path === 'index/root_index.yaml') {
                    if (!isset($updates['*index'])) {
                        $updates['*index'] = $ts;
                    }
                    continue;
                }
                if (str_starts_with($path, 'resources/')) {
                    $segments = explode('/', $path);
                    if (isset($segments[1]) && $segments[1] !== '') {
                        $folder = $segments[1];
                        if (!isset($updates[$folder])) {
                            $updates[$folder] = $ts;
                        }
                    }
                }
            }
        }
        return $updates;
    }
}

if (!function_exists('withu_warehouse_schools')) {
    /**
     * 学校列表（索引 + 本地资源补充）：
     * id / name / initial / resource_folder / adapter_count（资源目录内 .js 数）
     * / folder_exists / last_update（该学校资源目录最近一次随同步更新的时间）。
     */
    function withu_warehouse_schools(): array
    {
        $repoDir = withu_warehouse_repo_dir();
        $indexPath = $repoDir . '/index/root_index.yaml';
        if (!is_file($indexPath)) {
            return [];
        }
        $schools = withu_warehouse_parse_index((string)file_get_contents($indexPath));
        $updates = withu_warehouse_folder_updates();

        foreach ($schools as &$school) {
            $folder = (string)($school['resource_folder'] ?? '');
            $folderPath = $repoDir . '/resources/' . $folder;
            $school['folder_exists'] = is_dir($folderPath);
            $school['adapter_count'] = 0;
            if ($school['folder_exists']) {
                $school['adapter_count'] = count(glob($folderPath . '/*.js') ?: []);
            }
            $school['last_update'] = $updates[$folder] ?? null;
        }
        unset($school);

        usort($schools, function (array $a, array $b): int {
            return strcmp((string)($a['initial'] ?? ''), (string)($b['initial'] ?? ''))
                ?: strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });
        return $schools;
    }
}

if (!function_exists('withu_warehouse_git')) {
    /** 在仓库目录里执行 git（PATH 优先，Windows 常见安装位置兜底）。 */
    function withu_warehouse_git(array $args, int $timeoutSec = 30): array
    {
        $candidates = ['git'];
        foreach ([
            'C:\\Program Files\\Git\\cmd\\git.exe',
            'C:\\Program Files\\Git\\bin\\git.exe',
            'C:\\Program Files (x86)\\Git\\cmd\\git.exe',
        ] as $candidate) {
            if (is_file($candidate)) {
                $candidates[] = $candidate;
            }
        }
        $lastError = '';
        foreach ($candidates as $git) {
            $command = $git === 'git' ? ['git', ...$args] : [$git, ...$args];
            $process = @proc_open($command, [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $pipes, withu_warehouse_repo_dir(), null);
            if (!is_resource($process)) {
                $lastError = '无法启动 git 进程';
                continue;
            }
            fclose($pipes[0]);
            $out = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $code = proc_close($process);
            return ['code' => $code, 'out' => trim((string)$out), 'err' => trim((string)$err)];
        }
        return ['code' => -1, 'out' => '', 'err' => $lastError];
    }
}

if (!function_exists('withu_warehouse_head')) {
    /** 当前同步到的提交信息（短哈希|日期|主题|作者），未同步时返回 null。 */
    function withu_warehouse_head(): ?array
    {
        if (!withu_warehouse_is_synced()) {
            return null;
        }
        $result = withu_warehouse_git(['log', '-1', '--format=%h|%ad|%s|%an', '--date=short'], 15);
        if ($result['code'] !== 0 || $result['out'] === '') {
            return null;
        }
        $parts = explode('|', $result['out'], 4);
        return [
            'short' => $parts[0] ?? '',
            'date' => $parts[1] ?? '',
            'subject' => $parts[2] ?? '',
            'author' => $parts[3] ?? '',
        ];
    }
}

if (!function_exists('withu_warehouse_run')) {
    /**
     * 触发一次同步（调用 scripts/sync-warehouse.php）。
     * 返回 ['code' => 退出码, 'output' => 输出文本]。
     */
    function withu_warehouse_run(int $timeoutSec = 180): array
    {
        $root = withu_warehouse_root();
        $php = defined('PHP_BINARY') && PHP_BINARY !== '' ? PHP_BINARY : 'php';
        $ini = $root . '/deploy-local/php.ini';
        $script = $root . '/scripts/sync-warehouse.php';

        $command = [$php];
        if (is_file($ini)) {
            $command[] = '-c';
            $command[] = $ini;
        }
        $command[] = $script;
        $command[] = '--quiet';

        $process = @proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, $root, null);
        if (!is_resource($process)) {
            return ['code' => -1, 'output' => '无法启动同步进程（proc_open 不可用？）'];
        }
        fclose($pipes[0]);
        stream_set_timeout($pipes[1], $timeoutSec);
        $output = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);

        // 过滤 php.ini 叠加导致的 "Module xxx is already loaded" 噪音
        $combined = preg_replace('/^PHP Warning:\s+Module "[^"]+" is already loaded in Unknown on line 0$/m', '', $output . "\n" . $err);
        return ['code' => $code, 'output' => trim((string)$combined)];
    }
}
