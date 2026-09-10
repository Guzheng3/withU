<?php
error_reporting(E_ALL & ~E_DEPRECATED);
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/withu.php';
require_once __DIR__ . '/../core/warehouse_sync.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requireRole(['user1', 'user2']);
$db = Database::getInstance();

/* ---------- 立即同步（POST + CSRF） ---------- */
$syncNotice = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (string)($_POST['action'] ?? '') === 'sync_now') {
    require_csrf();
    $result = withu_warehouse_run();
    $syncNotice = [
        'ok' => $result['code'] === 0,
        'text' => $result['output'] !== ''
            ? $result['output']
            : ($result['code'] === 0 ? '同步完成，仓库已是最新。' : '同步失败（无输出）。'),
    ];
}

/* ---------- 数据 ---------- */
$meta = withu_warehouse_meta();
$synced = withu_warehouse_is_synced();
$records = withu_warehouse_records(20);
$logTail = withu_warehouse_log_tail(40);
$schools = $synced ? withu_warehouse_schools() : [];
$head = $synced ? withu_warehouse_head() : null;
$totalAdapters = array_sum(array_map(fn(array $s): int => (int)$s['adapter_count'], $schools));

$adminPage = 'warehouse_sync';
include __DIR__ . '/header.php';
?>

<style>
.ws-grid { display: grid; gap: 1rem; }
.ws-stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1px; margin-bottom: 1rem; border-radius: var(--v3-radius-sm); background: var(--v3-border); overflow: hidden; }
.ws-stat { display: flex; flex-direction: column; gap: 0.15rem; padding: 0.7rem 0.8rem; background: var(--v3-surface); }
.ws-stat-value { font-size: 1.05rem; font-weight: 750; line-height: 1.3; letter-spacing: -0.02em; color: var(--v3-text); font-variant-numeric: tabular-nums; word-break: break-all; }
.ws-stat-value-sm { font-size: 0.82rem; font-weight: 650; }
.ws-stat-label { font-size: 0.7rem; color: var(--v3-text-3); }
.ws-head { display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 1rem; }
.ws-head-icon { flex: 0 0 38px; width: 38px; height: 38px; display: grid; place-items: center; border-radius: 12px; background: var(--v3-pink-glass); color: var(--v3-pink-deep); font-size: 19px; }
.ws-head-title { margin: 0; font-size: 1rem; font-weight: 700; color: var(--v3-text); }
.ws-head-sub { margin: 0.15rem 0 0; font-size: 0.8rem; line-height: 1.55; color: var(--v3-text-3); }
.ws-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; }
.ws-tbl-wrap { max-height: 560px; overflow: auto; border: 1px solid var(--v3-border-light); border-radius: var(--v3-radius-sm); }
.ws-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
.ws-table th { position: sticky; top: 0; z-index: 1; background: var(--v3-surface-soft); text-align: left; padding: 0.5rem 0.7rem; font-size: 0.72rem; font-weight: 650; color: var(--v3-text-2); white-space: nowrap; border-bottom: 1px solid var(--v3-border); }
.ws-table td { padding: 0.5rem 0.7rem; border-bottom: 1px solid var(--v3-border-light); vertical-align: top; }
.ws-table tr:last-child td { border-bottom: none; }
.ws-table tr:hover td { background: var(--v3-surface-soft); }
.ws-code { padding: 0.05rem 0.35rem; border-radius: 5px; background: var(--v3-surface-soft); font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 0.92em; word-break: break-all; }
.ws-badge { display: inline-flex; align-items: center; padding: 0.08rem 0.5rem; border-radius: 999px; font-size: 0.7rem; font-weight: 650; white-space: nowrap; }
.ws-badge-ok { background: rgba(16, 185, 129, 0.12); color: #047857; }
.ws-badge-empty { background: rgba(148, 163, 184, 0.16); color: #64748b; }
.ws-badge-info { background: rgba(59, 130, 246, 0.12); color: #1d4ed8; }
.ws-badge-warn { background: rgba(245, 158, 11, 0.14); color: #b45309; }
.ws-search { width: 100%; max-width: 340px; box-sizing: border-box; padding: 0.45rem 0.7rem; border: 1px solid var(--v3-border); border-radius: 999px; background: var(--v3-surface); color: var(--v3-text); font-size: 0.8rem; outline: none; }
.ws-search:focus { border-color: var(--v3-pink); box-shadow: 0 0 0 3px rgba(242, 109, 156, 0.12); }
.ws-empty { display: flex; flex-direction: column; align-items: center; gap: 0.4rem; padding: 2rem 1rem; border: 1px dashed var(--v3-border); border-radius: var(--v3-radius-sm); background: var(--v3-surface-soft); text-align: center; }
.ws-empty i { font-size: 26px; color: var(--v3-text-3); }
.ws-empty strong { font-size: 0.9rem; font-weight: 650; color: var(--v3-text-2); }
.ws-empty span { max-width: 44ch; font-size: 0.78rem; line-height: 1.6; color: var(--v3-text-3); }
.ws-log { margin: 0; padding: 0.7rem 0.85rem; max-height: 260px; overflow: auto; border: 1px solid var(--v3-border-light); border-radius: var(--v3-radius-sm); background: var(--v3-surface-soft); font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 0.74rem; line-height: 1.65; color: var(--v3-text-2); white-space: pre-wrap; }
.ws-path-chips { display: flex; flex-wrap: wrap; gap: 0.3rem; }
.ws-path-chip { padding: 0.06rem 0.45rem; border-radius: 999px; background: var(--v3-surface-soft); color: var(--v3-text-2); font-size: 0.7rem; white-space: nowrap; }
</style>

<section class="admin-page-title">
    <h1>仓库同步管理 · 解析脚本仓库</h1>
    <p>可视化管理 qingyu_warehouse（教务适配 / 解析脚本仓库）的本地同步：查看同步状态、更新记录与学校适配器；同步后的解析脚本可配合 <a href="/admin/timetable_settings.php">课表设置</a> 使用。</p>
</section>

<section class="admin-grid">
    <div class="admin-card ws-grid" style="grid-column: 1 / -1;">
        <div class="ws-head">
            <span class="ws-head-icon"><i class="ti ti-cloud-download" aria-hidden="true"></i></span>
            <div style="flex: 1 1 auto; min-width: 0;">
                <h2 class="ws-head-title">同步状态</h2>
                <p class="ws-head-sub">仓库：<code class="ws-code"><?php echo e((string)($meta['remote'] ?? 'https://github.com/Guzheng3/qingyu_warehouse.git')); ?></code> · 分支 <code class="ws-code"><?php echo e((string)($meta['branch'] ?? 'main')); ?></code></p>
            </div>
            <div class="ws-actions">
                <form method="post" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='同步中…';">
                    <input type="hidden" name="action" value="sync_now">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-refresh" aria-hidden="true"></i>立即同步
                    </button>
                </form>
                <a class="btn btn-secondary" href="/admin/timetable_settings.php">
                    <i class="ti ti-calendar-time" aria-hidden="true"></i>课表设置
                </a>
            </div>
        </div>

        <?php if ($syncNotice): ?>
        <div class="admin-alert admin-alert-<?php echo $syncNotice['ok'] ? 'success' : 'error'; ?>">
            <i class="ti ti-<?php echo $syncNotice['ok'] ? 'circle-check' : 'alert-triangle'; ?>" aria-hidden="true"></i>
            <?php echo e($syncNotice['text']); ?>
        </div>
        <?php endif; ?>

        <?php if (!$synced): ?>
        <div class="ws-empty">
            <i class="ti ti-cloud-off" aria-hidden="true"></i>
            <strong>尚未同步解析脚本仓库</strong>
            <span>点击「立即同步」会把 qingyu_warehouse 克隆到本地 runtime/qingyu-warehouse/（首次较慢，取决于网络）。同步成功后这里会展示学校列表与更新记录。</span>
        </div>
        <?php else: ?>
        <div class="ws-stat-grid">
            <div class="ws-stat">
                <span class="ws-stat-value"><?php echo e((string)($meta['last_sync_at'] ?? '-')); ?></span>
                <span class="ws-stat-label">最近同步</span>
            </div>
            <div class="ws-stat">
                <span class="ws-stat-value ws-stat-value-sm"><?php echo e((string)($head['short'] ?? ($meta['head_short'] ?? '-'))); ?></span>
                <span class="ws-stat-label">当前提交<?php if ($head && $head['date']): ?>（<?php echo e($head['date']); ?>）<?php endif; ?></span>
            </div>
            <div class="ws-stat">
                <span class="ws-stat-value"><?php echo count($schools); ?></span>
                <span class="ws-stat-label">学校 / 系统</span>
            </div>
            <div class="ws-stat">
                <span class="ws-stat-value"><?php echo (int)$totalAdapters; ?></span>
                <span class="ws-stat-label">适配脚本</span>
            </div>
            <div class="ws-stat">
                <span class="ws-stat-value"><?php echo count(withu_warehouse_records(10000)); ?></span>
                <span class="ws-stat-label">同步记录</span>
            </div>
        </div>

        <?php if ($head && $head['subject']): ?>
        <div class="admin-alert admin-alert-info" style="margin-bottom:0;">
            <i class="ti ti-git-commit" aria-hidden="true"></i>
            HEAD：<?php echo e($head['subject']); ?>（<?php echo e($head['author'] ?? '未知作者'); ?>）
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="admin-card" style="grid-column: 1 / -1;">
        <div class="admin-card-header">
            <div class="admin-card-title">
                <i class="ti ti-history" aria-hidden="true"></i>同步记录
                <?php if ($records): ?><span class="ws-badge ws-badge-info">最近 <?php echo count($records); ?> 条</span><?php endif; ?>
            </div>
        </div>

        <?php if (!$records): ?>
        <div class="ws-empty">
            <i class="ti ti-history-off" aria-hidden="true"></i>
            <strong>暂无同步记录</strong>
            <span>每次手动或定时同步都会在这里留下结构化记录（时间、提交变化、涉及的学校资源文件）。</span>
        </div>
        <?php else: ?>
        <div class="ws-tbl-wrap" style="max-height:340px;">
            <table class="ws-table">
                <thead>
                    <tr>
                        <th>时间</th>
                        <th>类型</th>
                        <th>提交变化</th>
                        <th>新增提交</th>
                        <th>涉及资源</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $record):
                        $action = (string)($record['action'] ?? '');
                        $paths = $record['changed_paths'] ?? null;
                        $pathCount = is_array($paths) ? count($paths) : 0;
                        $folders = [];
                        if (is_array($paths)) {
                            foreach ($paths as $path) {
                                if (str_starts_with((string)$path, 'resources/')) {
                                    $segments = explode('/', (string)$path);
                                    $folders[$segments[1] ?? ''] = true;
                                } elseif ((string)$path === 'index/root_index.yaml') {
                                    $folders['索引'] = true;
                                }
                            }
                            $folders = array_keys($folders);
                        }
                    ?>
                    <tr>
                        <td style="white-space:nowrap;"><?php echo e((string)($record['ts'] ?? '-')); ?></td>
                        <td>
                            <?php if ($action === 'clone'): ?>
                            <span class="ws-badge ws-badge-info">首次克隆</span>
                            <?php elseif ((int)($record['new_commits'] ?? 0) > 0): ?>
                            <span class="ws-badge ws-badge-ok">有更新</span>
                            <?php else: ?>
                            <span class="ws-badge ws-badge-empty">无变化</span>
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap;">
                            <?php if ($action === 'clone'): ?>
                            <code class="ws-code"><?php echo e(substr((string)($record['after'] ?? ''), 0, 7)); ?></code>
                            <?php else: ?>
                            <code class="ws-code"><?php echo e(substr((string)($record['before'] ?? ''), 0, 7)); ?></code>
                            → <code class="ws-code"><?php echo e(substr((string)($record['after'] ?? ''), 0, 7)); ?></code>
                            <?php endif; ?>
                        </td>
                        <td><?php echo (int)($record['new_commits'] ?? 0); ?></td>
                        <td title="<?php echo e(is_array($paths) ? implode("\n", $paths) : ''); ?>">
                            <?php if ($pathCount === 0): ?>
                            <span class="ws-badge ws-badge-empty">无</span>
                            <?php else: ?>
                            <div class="ws-path-chips">
                                <?php foreach (array_slice($folders, 0, 4) as $folder): ?>
                                <span class="ws-path-chip"><?php echo e($folder); ?></span>
                                <?php endforeach; ?>
                                <?php if (count($folders) > 4): ?><span class="ws-path-chip">+<?php echo count($folders) - 4; ?></span><?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($logTail): ?>
        <details style="margin-top:0.8rem;">
            <summary style="cursor:pointer;font-size:0.78rem;color:var(--v3-text-2);">查看同步日志原文（最近 <?php echo count($logTail); ?> 行）</summary>
            <pre class="ws-log" style="margin-top:0.5rem;"><?php echo e(implode("\n", $logTail)); ?></pre>
        </details>
        <?php endif; ?>
    </div>

    <div class="admin-card" style="grid-column: 1 / -1;">
        <div class="admin-card-header">
            <div class="admin-card-title">
                <i class="ti ti-school" aria-hidden="true"></i>学校与适配器
                <?php if ($schools): ?><span class="ws-badge ws-badge-info"><?php echo count($schools); ?> 所</span><?php endif; ?>
            </div>
            <input type="search" class="ws-search" id="ws-school-search" placeholder="搜索学校 / 编号 / 文件夹…" aria-label="搜索学校">
        </div>

        <?php if (!$synced): ?>
        <div class="ws-empty">
            <i class="ti ti-school" aria-hidden="true"></i>
            <strong>同步后显示学校列表</strong>
            <span>学校与适配器来自仓库的 index/root_index.yaml 与 resources/ 目录，先执行一次同步。</span>
        </div>
        <?php elseif (!$schools): ?>
        <div class="ws-empty">
            <i class="ti ti-file-alert" aria-hidden="true"></i>
            <strong>未能读取学校索引</strong>
            <span>本地仓库缺少 index/root_index.yaml，请检查同步是否完整。</span>
        </div>
        <?php else: ?>
        <div class="ws-tbl-wrap">
            <table class="ws-table" id="ws-school-table">
                <thead>
                    <tr>
                        <th>学校 / 系统</th>
                        <th>资源文件夹</th>
                        <th>适配脚本</th>
                        <th>最近更新</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schools as $school):
                        $name = (string)($school['name'] ?? $school['id']);
                        $folder = (string)($school['resource_folder'] ?? '');
                        $lastUpdate = (string)($school['last_update'] ?? '');
                    ?>
                    <tr data-search="<?php echo e(mb_strtolower($name . ' ' . $school['id'] . ' ' . $folder)); ?>">
                        <td>
                            <strong><?php echo e($name); ?></strong>
                            <div style="margin-top:2px;"><code class="ws-code"><?php echo e($school['id']); ?></code></div>
                        </td>
                        <td>
                            <?php if ($school['folder_exists']): ?>
                            <a href="https://github.com/Guzheng3/qingyu_warehouse/tree/main/resources/<?php echo e(rawurlencode($folder)); ?>" target="_blank" rel="noopener" style="color:var(--v3-pink-deep);text-decoration:none;">
                                <code class="ws-code">resources/<?php echo e($folder); ?></code>
                            </a>
                            <?php else: ?>
                            <span class="ws-badge ws-badge-warn">目录缺失</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int)$school['adapter_count'] > 0): ?>
                            <span class="ws-badge ws-badge-ok"><?php echo (int)$school['adapter_count']; ?> 个脚本</span>
                            <?php else: ?>
                            <span class="ws-badge ws-badge-empty">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap;">
                            <?php if ($lastUpdate !== ''): ?>
                            <span title="该学校资源目录最近一次随同步更新的时间"><?php echo e($lastUpdate); ?></span>
                            <?php else: ?>
                            <span class="ws-badge ws-badge-empty">同步以来未更新</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</section>

<script>
(function () {
    var input = document.getElementById('ws-school-search');
    var table = document.getElementById('ws-school-table');
    if (!input || !table) { return; }
    input.addEventListener('input', function () {
        var keyword = input.value.trim().toLowerCase();
        var rows = table.querySelectorAll('tbody tr');
        var visible = 0;
        rows.forEach(function (row) {
            var haystack = row.getAttribute('data-search') || '';
            var show = keyword === '' || haystack.indexOf(keyword) !== -1;
            row.style.display = show ? '' : 'none';
            if (show) { visible++; }
        });
        var emptyRow = table.querySelector('tbody tr[data-ws-empty]');
        if (!emptyRow && visible === 0) {
            emptyRow = document.createElement('tr');
            emptyRow.setAttribute('data-ws-empty', '1');
            emptyRow.innerHTML = '<td colspan="4" style="text-align:center;color:var(--v3-text-3);padding:1.2rem;">没有匹配的学校</td>';
            table.querySelector('tbody').appendChild(emptyRow);
        }
        if (emptyRow) { emptyRow.style.display = visible === 0 ? '' : 'none'; }
    });
})();
</script>

<?php include __DIR__ . '/footer.php'; ?>
