<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/withu.php';
require_once __DIR__ . '/../core/MediaDatabase.php';
require_once __DIR__ . '/../core/MediaSchema.php';
require_once __DIR__ . '/../core/MediaRepository.php';
require_once __DIR__ . '/../core/OpenList.php';
require_once __DIR__ . '/../core/MediaRecognition.php';

$auth = new Auth();
$user = withu_require_couple_user($auth);
$db = Database::getInstance();
$error = '';
$success = '';
$mediaDb = null;
try {
    $mediaDb = withu_media_db();
} catch (Throwable $e) {
    $error = '影视资源库不可用：' . $e->getMessage();
}

function media_admin_count_label(?int $count): string
{
    if ($count === null || $count <= 0) {
        return '0';
    }
    return (string)$count;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? 'save';
    if ($action === 'save') {
        $allSettings = [];
        foreach ($db->fetchAll('SELECT id, `key`, `value` FROM settings') as $row) {
            $allSettings[$row['key']] = $row;
        }
        foreach (['openlist_webdav_url', 'openlist_webdav_username', 'openlist_webdav_password', 'openlist_root_path'] as $key) {
            $value = trim((string)($_POST[$key] ?? ''));
            if (isset($allSettings[$key])) {
                $db->update('settings', ['value' => $value, 'updated_at' => withu_now()], 'id = :id', ['id' => $allSettings[$key]['id']]);
            } else {
                $db->insert('settings', ['key' => $key, 'value' => $value, 'updated_at' => withu_now()]);
            }
        }
        $success = 'OpenList 配置已保存';
    } elseif ($action === 'scan') {
        try {
            $scanLimit = max(1, (int)($_POST['scan_limit'] ?? 250));
            $scanTimeLimit = max(5, min(60, (int)($_POST['scan_time_limit'] ?? 35)));
            $startedAt = time();
            $added = 0;
            $updated = 0;
            $processed = 0;
            $matched = 0;
            $matchFailed = 0;
            $seriesKeys = [];
            (new OpenListClient($mediaDb ?: withu_media_db()))->scanEach(function (array $file, int $count) use (&$added, &$updated, &$processed, &$seriesKeys, $scanLimit, $scanTimeLimit, $startedAt): bool {
                if ($count > $scanLimit) {
                    return false;
                }
                if ((time() - $startedAt) >= $scanTimeLimit) {
                    return false;
                }
                $result = withu_media_upsert_file($file, false);
                if (!empty($result['changed'])) {
                    $added++;
                } else {
                    $updated++;
                }
                $media = $result['media'] ?? null;
                if (is_array($media)) {
                    $display = withu_media_display_row($media);
                    $seriesKey = trim((string)($display['series_key'] ?? ''));
                    if ($seriesKey !== '') $seriesKeys[$seriesKey] = true;
                }
                $processed = $count;
                return true;
            });
            foreach (array_keys($seriesKeys) as $seriesKey) {
                try {
                    $recognition = withu_recognize_series($mediaDb ?: withu_media_db(), (string)$seriesKey, [], false);
                    if (!empty($recognition['success']) && empty($recognition['skipped'])) $matched++;
                } catch (Throwable $e) {
                    $matchFailed++;
                }
            }
            $success = "刷新完成：新增 {$added} 个，更新 {$updated} 个，已处理 {$processed} 条；自动匹配 {$matched} 组" . ($matchFailed > 0 ? "，失败 {$matchFailed} 组" : '') . "。";
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    } elseif ($action === 'add') {
        $url = trim((string)($_POST['source_url'] ?? ''));
        $name = trim((string)($_POST['file_name'] ?? basename(parse_url($url, PHP_URL_PATH) ?: '未命名视频')));
        if (!preg_match('#^https?://#i', $url)) {
            $error = '请填写有效的视频直链';
        } else {
            $now = withu_now();
            $mediaId = (int)withu_media_db()->insert('media_library', [
                'source_key' => $url,
                'source_url' => $url,
                'direct_url' => '',
                'file_name' => $name,
                'recognition_status' => 'pending',
                'last_scanned_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $freshMedia = withu_media_fetch($mediaId);
            if ($freshMedia) {
                withu_recognize_media(withu_media_db(), $freshMedia, true);
            }
            $success = '视频已加入媒体库';
        }
    }
}

$settings = [];
foreach ($db->fetchAll('SELECT `key`, `value` FROM settings') as $row) {
    $settings[$row['key']] = $row['value'];
}

$stats = [
    'media_count' => 0,
    'recognized_count' => 0,
    'pending_count' => 0,
    'series_count' => 0,
];
$scanState = [];

try {
    if ($mediaDb) {
        $stats['media_count'] = (int)($mediaDb->fetch("SELECT COUNT(*) AS c FROM media_library")['c'] ?? 0);
        $stats['recognized_count'] = (int)($mediaDb->fetch("SELECT COUNT(*) AS c FROM media_library WHERE recognition_status = 'recognized'")['c'] ?? 0);
        $stats['pending_count'] = (int)($mediaDb->fetch("SELECT COUNT(*) AS c FROM media_library WHERE recognition_status = 'pending'")['c'] ?? 0);
        $stats['series_count'] = (int)($mediaDb->fetch("SELECT COUNT(DISTINCT series_key) AS c FROM media_library WHERE series_key IS NOT NULL AND series_key <> ''")['c'] ?? 0);
        $scanState = $mediaDb->fetch("SELECT * FROM media_scan_state WHERE source = 'openlist' LIMIT 1") ?: [];
    }
} catch (Throwable $e) {
    if ($error === '') {
        $error = '影视资源库不可用：' . $e->getMessage();
    }
}

$adminPage = 'media';
include __DIR__ . '/header.php';
?>
<section class="admin-page-title">
    <h1>媒体配置与 OpenList</h1>
</section>

<?php if ($error): ?>
<div class="admin-alert admin-alert-error"><?php echo e($error); ?></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="admin-alert admin-alert-success"><?php echo e($success); ?></div>
<?php endif; ?>

<section class="media-console">
    <div class="media-hero">
        <div class="media-hero-card">
            <div class="media-kicker">media control</div>
            <div class="media-hero-title">资源库状态面板</div>
            <div class="media-hero-desc">
                当前媒体资源库承接 OpenList 扫描、识别和播放入口。
                这里先看总量和状态，再去扫描或补录，避免表格里找状态。
            </div>

            <div class="media-stat-grid">
                <div class="media-stat"><div class="media-stat-value"><?php echo media_admin_count_label($stats['media_count']); ?></div><div class="media-stat-label">资源总数</div></div>
                <div class="media-stat"><div class="media-stat-value"><?php echo media_admin_count_label($stats['recognized_count']); ?></div><div class="media-stat-label">已识别</div></div>
                <div class="media-stat"><div class="media-stat-value"><?php echo media_admin_count_label($stats['pending_count']); ?></div><div class="media-stat-label">待识别</div></div>
                <div class="media-stat"><div class="media-stat-value"><?php echo media_admin_count_label($stats['series_count']); ?></div><div class="media-stat-label">分组数量</div></div>
            </div>

            <div class="media-chip-row">
                <span class="media-chip <?php echo ($stats['pending_count'] > 0) ? 'is-warning' : 'is-success'; ?>">
                    <i class="fas fa-folder-open"></i>
                    <?php echo $stats['pending_count'] > 0 ? '有待识别资源' : '全部已识别'; ?>
                </span>
                <span class="media-chip is-muted">
                    <i class="fas fa-database"></i>
                    库：withu_media
                </span>
                <span class="media-chip is-muted">
                    <i class="fas fa-cloud-arrow-down"></i>
                    直链播放时才请求，不落库
                </span>
            </div>

            <div class="media-scan-state">
                <strong style="font-size:.9rem;">扫描状态</strong>
                <div style="color:var(--text-light);font-size:.85rem;">
                    <?php if (!empty($scanState)): ?>
                        上次运行：<?php echo e($scanState['last_run_at'] ?? '未记录'); ?><br>
                        最近消息：<?php echo e($scanState['last_message'] ?? '无'); ?><br>
                        游标：<?php echo e($scanState['cursor_path'] ?? '无'); ?>
                    <?php else: ?>
                        暂无扫描状态记录
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="media-panel">
            <div class="media-openlist-heading">
                <div>
                    <strong>OpenList 配置</strong>
                    <span>已保存的连接信息默认隐藏</span>
                </div>
                <button type="button" class="btn btn-secondary media-openlist-toggle" data-openlist-toggle aria-expanded="false"><i class="fas fa-plus"></i><span>添加 OpenList</span></button>
            </div>
            <div class="media-openlist-editor" data-openlist-editor hidden>
                <label>粘贴配置后一键识别</label>
                <textarea data-openlist-paste rows="6" placeholder="协议:HTTP&#10;地址:111.170.35.125&#10;端口:5244&#10;账号:wybmhyk7.17&#10;密码:12580&#10;路径:/dav"></textarea>
                <div class="media-openlist-editor-actions"><button type="button" class="btn btn-primary" data-openlist-parse><i class="fas fa-wand-magic-sparkles"></i>一键识别</button><span data-openlist-parse-status></span></div>
            </div>
            <div class="media-form-grid">
                <div>
                    <label>OpenList 地址</label>
                    <input type="hidden" id="openlistWebdavUrl" name="openlist_webdav_url" form="openlist-save-form" value="<?php echo e($settings['openlist_webdav_url'] ?? ''); ?>">
                    <input type="text" data-openlist-preview value="<?php echo e($settings['openlist_webdav_url'] ?? ''); ?>" placeholder="识别后生成，例如 http://example.com:5244/dav" readonly>
                </div>
                <div>
                    <label>用户名</label>
                    <input type="hidden" id="openlistWebdavUsername" name="openlist_webdav_username" form="openlist-save-form" value="<?php echo e($settings['openlist_webdav_username'] ?? ''); ?>">
                    <input type="text" data-openlist-username-preview value="<?php echo e($settings['openlist_webdav_username'] ?? ''); ?>" readonly>
                </div>
                <div>
                    <label>密码</label>
                    <input type="hidden" id="openlistWebdavPassword" name="openlist_webdav_password" form="openlist-save-form" value="<?php echo e($settings['openlist_webdav_password'] ?? ''); ?>">
                    <input type="text" data-openlist-password-preview value="<?php echo !empty($settings['openlist_webdav_password']) ? '••••••••' : ''; ?>" readonly>
                </div>
                <div>
                    <label>根目录</label>
                    <input type="hidden" id="openlistRootPath" name="openlist_root_path" form="openlist-save-form" value="<?php echo e($settings['openlist_root_path'] ?? '/'); ?>">
                    <input type="text" data-openlist-path-preview value="<?php echo e($settings['openlist_root_path'] ?? '/'); ?>" readonly>
                </div>
            </div>
            <p style="color:var(--text-light);font-size:.85rem;line-height:1.6;margin:.85rem 0 0;">
                默认热榜仍以国内豆瓣数据为主。OpenList 这边只保存路径，不保存过期签名直链。
            </p>
            <p style="color:var(--text-light);font-size:.8rem;line-height:1.65;margin:.45rem 0 0;">
                网页端扫描只做分批刷新，每次最多 250 条，避免大库卡死。完整导入建议直接跑 CLI：<code>php scripts/import_openlist_to_media.php --no-hot</code>
            </p>
            <div class="media-actions">
                <a class="btn btn-secondary" href="/admin/media_catalog.php"><i class="fas fa-layer-group"></i> 影视资源库</a>
                <a class="btn btn-secondary" href="/admin/media_resources.php"><i class="fas fa-list"></i> 资源列表</a>
                <form id="openlist-save-form" method="post">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="save">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> 保存配置</button>
                </form>
                <form method="post">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="scan">
                    <input type="hidden" name="scan_limit" value="250">
                    <input type="hidden" name="scan_time_limit" value="35">
                    <button class="btn btn-secondary" type="submit"><i class="fas fa-rotate"></i> 分批刷新</button>
                </form>
                <button class="btn btn-secondary" type="submit" form="add-media-form"><i class="fas fa-plus"></i> 手动添加</button>
            </div>
            <div style="margin-top:1rem;">
                <label>视频直链</label>
                <input form="add-media-form" name="source_url" placeholder="https://...">
            </div>
            <div style="margin-top:.7rem;">
                <label>显示名称</label>
                <input form="add-media-form" name="file_name" placeholder="影片名称">
            </div>
            <form id="add-media-form" method="post" style="display:none;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add">
            </form>
        </div>
    </div>

    <div class="media-grid">
    </div>

</section>

<script>
(() => {
    const editor = document.querySelector('[data-openlist-editor]');
    const toggle = document.querySelector('[data-openlist-toggle]');
    const paste = document.querySelector('[data-openlist-paste]');
    const parse = document.querySelector('[data-openlist-parse]');
    const status = document.querySelector('[data-openlist-parse-status]');
    const fields = {
        url: document.querySelector('#openlistWebdavUrl'),
        username: document.querySelector('#openlistWebdavUsername'),
        password: document.querySelector('#openlistWebdavPassword'),
        path: document.querySelector('#openlistRootPath'),
        urlPreview: document.querySelector('[data-openlist-preview]'),
        usernamePreview: document.querySelector('[data-openlist-username-preview]'),
        passwordPreview: document.querySelector('[data-openlist-password-preview]'),
        pathPreview: document.querySelector('[data-openlist-path-preview]')
    };
    if (!editor || !toggle || !paste || !parse) return;
    toggle.addEventListener('click', () => {
        const opening = editor.hidden;
        editor.hidden = !opening;
        toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
        toggle.querySelector('i')?.classList.toggle('fa-plus', !opening);
        toggle.querySelector('i')?.classList.toggle('fa-minus', opening);
        toggle.querySelector('span').textContent = opening ? '收起配置' : '添加 OpenList';
        if (opening) paste.focus();
    });
    function valueFor(lines, names) {
        const key = names.join('|');
        const match = lines.find(item => new RegExp('^\\s*(?:' + key + ')\\s*[:：]\\s*(.*)$', 'i').test(item));
        if (!match) return '';
        return match.replace(new RegExp('^\\s*(?:' + key + ')\\s*[:：]\\s*', 'i'), '').trim();
    }
    function parseConfig() {
        const lines = paste.value.split(/\r?\n/).map(line => line.trim()).filter(Boolean);
        const rawUrl = valueFor(lines, ['URL', '地址', '链接', 'OpenList地址']);
        let protocol = valueFor(lines, ['协议', 'Protocol']).toLowerCase().replace(/:$/, '') || 'http';
        let host = valueFor(lines, ['地址', '主机', 'Host']);
        const port = valueFor(lines, ['端口', 'Port']);
        const path = valueFor(lines, ['路径', '根目录', 'Path', 'Root']) || '/';
        if ((!host || !protocol) && rawUrl) {
            try {
                const parsed = new URL(rawUrl.includes('://') ? rawUrl : 'http://' + rawUrl);
                protocol = parsed.protocol.replace(':', '');
                host = parsed.hostname;
                fields.path.value = parsed.pathname || '/';
            } catch (error) {}
        }
        if (!host) host = rawUrl.replace(/^https?:\/\//i, '').split('/')[0].split(':')[0];
        const username = valueFor(lines, ['账号', '用户名', '用户', 'Username', 'User']);
        const password = valueFor(lines, ['密码', 'Password', 'Pass']);
        if (!host) { status.textContent = '未识别到地址，请检查格式。'; return; }
        const url = protocol + '://' + host + (port ? ':' + port : '') + (path.startsWith('/') ? path : '/' + path);
        fields.url.value = url;
        fields.username.value = username;
        fields.password.value = password;
        fields.path.value = path.startsWith('/') ? path : '/' + path;
        fields.urlPreview.value = url;
        fields.usernamePreview.value = username;
        fields.passwordPreview.value = password ? '••••••••' : '';
        fields.pathPreview.value = fields.path.value;
        status.textContent = '已识别，请点击“保存配置”。';
    }
    parse.addEventListener('click', parseConfig);
    paste.addEventListener('paste', () => window.setTimeout(parseConfig, 0));
})();
</script>

<?php include __DIR__ . '/footer.php'; ?>
