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

$auth = new Auth();
$auth->requireLogin();
$auth->requireRole(['user1', 'user2']);
$db = Database::getInstance();
migrate_schema_if_needed();

$error = '';
$success = '';

function withu_remote_update_format_bytes($bytes): string {
    $bytes = (int)$bytes;
    if ($bytes <= 0) {
        return '未知';
    }
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 1) . ' GB';
    }
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }
    return number_format($bytes / 1024, 1) . ' KB';
}

function withu_remote_update_max_upload_bytes(): int {
    $limits = [];
    foreach (['upload_max_filesize', 'post_max_size'] as $iniKey) {
        $value = ini_get($iniKey);
        if ($value !== false) {
            $limits[] = parse_php_size_to_bytes($value);
        }
    }

    $maxBytes = empty($limits) ? 2 * 1024 * 1024 * 1024 : min($limits);
    return max(1, min($maxBytes, 2 * 1024 * 1024 * 1024));
}

function withu_remote_update_source_label(?array $row): string {
    $apkUrl = trim((string)($row['apk_url'] ?? ''));
    return preg_match('#^https?://#i', $apkUrl) ? '外部链接' : '本地上传';
}

function withu_remote_update_download_url(?array $row): string {
    $apkUrl = trim((string)($row['apk_url'] ?? ''));
    if ($apkUrl === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $apkUrl)) {
        return BASE_URL . '/api/app_update_download.php?id=' . (int)$row['id'];
    }
    return upload_url($apkUrl);
}

function withu_remote_update_local_path(?array $row): string {
    $apkUrl = trim((string)($row['apk_url'] ?? ''));
    if ($apkUrl === '' || preg_match('#^https?://#i', $apkUrl) || strpos($apkUrl, '..') !== false) {
        return '';
    }
    return rtrim(UPLOAD_DIR, '/\\') . '/' . ltrim($apkUrl, '/\\');
}

function withu_remote_update_metadata_ok(?array $row): string {
    $apkUrl = trim((string)($row['apk_url'] ?? ''));
    $sha256 = strtolower(trim((string)($row['sha256'] ?? '')));
    if (!preg_match('/^[a-f0-9]{64}$/', $sha256)) {
        return '安装包摘要无效，请重新上传或填写完整的 SHA-256。';
    }
    if (preg_match('#^https?://#i', $apkUrl)) {
        if (!preg_match('#^https://#i', $apkUrl) || strlen($apkUrl) > 512) {
            return '安装包链接必须是 HTTPS 地址，且长度不超过 512 个字符。';
        }
        return '';
    }
    if (!is_file(withu_remote_update_local_path($row))) {
        return '本地上传的安装包文件已丢失，请重新上传。';
    }
    return '';
}

$now = date('Y-m-d H:i:s');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? 'create');

    if (in_array($action, ['enable', 'disable', 'delete'], true)) {
        $releaseId = (int)($_POST['id'] ?? 0);
        $release = $releaseId > 0
            ? $db->fetch('SELECT * FROM `remote_updates` WHERE `id` = :id LIMIT 1', ['id' => $releaseId])
            : null;

        if (!$release) {
            $error = '没有找到要操作的版本。';
        } else {
            if ($action === 'delete') {
                $localPath = withu_remote_update_local_path($release);
                $referenceCount = (int)$db->fetch(
                    'SELECT COUNT(*) AS `total` FROM `remote_updates` WHERE `apk_url` = :apk_url AND `id` <> :id',
                    ['apk_url' => (string)$release['apk_url'], 'id' => $releaseId]
                )['total'];
                $db->delete('remote_updates', 'id = :id', ['id' => $releaseId]);
                if ($localPath !== '' && $referenceCount === 0 && is_file($localPath)) {
                    @unlink($localPath);
                }
                header('Location: /admin/remote_update.php?success=deleted');
                exit;
            }

            $metadataError = withu_remote_update_metadata_ok($release);
            if ($metadataError !== '') {
                $error = $metadataError;
            } elseif ($action === 'enable') {
                $publishedAt = !empty($release['published_at']) ? (string)$release['published_at'] : $now;
                $db->update('remote_updates', ['enabled' => 0], 'enabled = 1');
                $db->update(
                    'remote_updates',
                    ['enabled' => 1, 'published_at' => $publishedAt, 'updated_at' => $now],
                    'id = :id',
                    ['id' => $releaseId]
                );
                header('Location: /admin/remote_update.php?success=enabled');
                exit;
            } else {
                $db->update(
                    'remote_updates',
                    ['enabled' => 0, 'updated_at' => $now],
                    'id = :id',
                    ['id' => $releaseId]
                );
                header('Location: /admin/remote_update.php?success=disabled');
                exit;
            }
        }
    } elseif ($action === 'create') {
        $sourceMode = (string)($_POST['source_mode'] ?? '');
        $version = trim((string)($_POST['version'] ?? ''));
        $title = trim((string)($_POST['title'] ?? ''));
        $body = trim((string)($_POST['body'] ?? ''));
        $apkLink = trim((string)($_POST['apk_link'] ?? ''));
        $apkSha256 = strtolower(trim((string)($_POST['apk_sha256'] ?? '')));
        $forceUpdate = isset($_POST['force_update']) ? 1 : 0;
        $enabled = isset($_POST['enabled']) ? 1 : 0;
        $apkUrl = '';
        $sha256 = '';
        $sizeBytes = 0;
        $savedLocalPath = '';

        if ($version === '') {
            $error = '请填写版本号。';
        } elseif (mb_strlen($version) > 32) {
            $error = '版本号不能超过 32 个字符。';
        } elseif (mb_strlen($title) > 120) {
            $error = '更新标题不能超过 120 个字符。';
        } elseif (mb_strlen($body) > 10000) {
            $error = '更新说明不能超过 10000 个字符。';
        } elseif ($sourceMode !== 'local' && $sourceMode !== 'link') {
            $error = '请选择安装包来源。';
        } elseif ($sourceMode === 'local') {
            $file = $_FILES['apk_file'] ?? null;
            if (!is_array($file) || !isset($file['error'])) {
                $error = '请选择要上传的 APK 文件。';
            } elseif ((int)$file['error'] === UPLOAD_ERR_NO_FILE) {
                $error = '请选择要上传的 APK 文件。';
            } elseif ((int)$file['error'] === UPLOAD_ERR_INI_SIZE || (int)$file['error'] === UPLOAD_ERR_FORM_SIZE) {
                $error = 'APK 文件超过上传大小限制（最大 ' . withu_remote_update_format_bytes(withu_remote_update_max_upload_bytes()) . '）。';
            } elseif ((int)$file['error'] !== UPLOAD_ERR_OK) {
                $error = 'APK 上传失败，错误码 ' . (int)$file['error'] . '。';
            } elseif (strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION)) !== 'apk') {
                $error = '安装包必须是 .apk 文件。';
            } elseif ((int)($file['size'] ?? 0) <= 0 || (int)$file['size'] > withu_remote_update_max_upload_bytes()) {
                $error = 'APK 文件大小无效或超过上传限制。';
            } elseif (!is_uploaded_file((string)$file['tmp_name'])) {
                $error = '无法确认上传文件来源，请重试。';
            } else {
                $uploadDir = UPLOAD_DIR . '/updates';
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                    $error = '无法创建安装包上传目录。';
                } else {
                    $filename = uniqid('apk-', true) . '.apk';
                    $savedLocalPath = rtrim($uploadDir, '/\\') . '/' . $filename;
                    if (!move_uploaded_file((string)$file['tmp_name'], $savedLocalPath)) {
                        $error = 'APK 文件保存失败，请检查服务器写入权限。';
                    } else {
                        $computedSha256 = @hash_file('sha256', $savedLocalPath);
                        if (!is_string($computedSha256) || !preg_match('/^[a-f0-9]{64}$/', $computedSha256)) {
                            @unlink($savedLocalPath);
                            $savedLocalPath = '';
                            $error = '无法计算 APK 的 SHA-256，请重新上传。';
                        } else {
                            $apkUrl = 'updates/' . $filename;
                            $sha256 = strtolower($computedSha256);
                            $sizeBytes = (int)filesize($savedLocalPath);
                        }
                    }
                }
            }
        } else {
            if ($apkLink === '') {
                $error = '请粘贴 APK 下载链接。';
            } elseif (!preg_match('#^https://#i', $apkLink)) {
                $error = 'APK 下载链接必须使用 HTTPS。';
            } elseif (strlen($apkLink) > 512) {
                $error = 'APK 下载链接不能超过 512 个字符。';
            } elseif (($parsedHost = parse_url($apkLink, PHP_URL_HOST)) === false || $parsedHost === null) {
                $error = 'APK 下载链接格式不正确。';
            } elseif (!preg_match('/^[a-f0-9]{64}$/', $apkSha256)) {
                $error = '外部链接必须填写 64 位小写 SHA-256 摘要。';
            } else {
                $apkUrl = $apkLink;
                $sha256 = $apkSha256;
            }
        }

        if ($error === '') {
            $data = [
                'version' => $version,
                'title' => $title !== '' ? $title : '版本更新',
                'body' => $body,
                'apk_url' => $apkUrl,
                'sha256' => $sha256,
                'size_bytes' => $sizeBytes,
                'force_update' => $forceUpdate,
                'enabled' => $enabled,
                'published_at' => $enabled ? $now : null,
                'updated_at' => $now,
            ];
            try {
                if ($enabled) {
                    $db->update('remote_updates', ['enabled' => 0], 'enabled = 1');
                }
                $db->insert('remote_updates', $data);
                header('Location: /admin/remote_update.php?success=' . ($enabled ? 'published' : 'saved'));
                exit;
            } catch (Throwable $e) {
                error_log('Remote update create error: ' . $e->getMessage());
                if ($savedLocalPath !== '' && is_file($savedLocalPath)) {
                    @unlink($savedLocalPath);
                }
                $error = '版本保存失败，请稍后重试。';
            }
        }

        $form = [
            'source_mode' => $sourceMode === 'link' ? 'link' : 'local',
            'version' => $version,
            'title' => $title,
            'body' => $body,
            'apk_link' => $apkLink,
            'apk_sha256' => $apkSha256,
            'force_update' => $forceUpdate,
            'enabled' => $enabled,
        ];
    } else {
        $error = '不支持的操作。';
    }
}

$successCode = (string)($_GET['success'] ?? '');
$successMessages = [
    'published' => '新版本已发布，客户端将收到更新提示。',
    'saved' => '版本已保存为草稿，暂未下发给客户端。',
    'enabled' => '该版本已启用，并替换当前生效版本。',
    'disabled' => '该版本已停用。',
    'deleted' => '该版本已删除。',
    '1' => '操作已完成。',
];
if ($successCode !== '' && isset($successMessages[$successCode])) {
    $success = $successMessages[$successCode];
}

$currentRelease = $db->fetch(
    'SELECT * FROM `remote_updates` WHERE `enabled` = 1 ORDER BY `id` DESC LIMIT 1'
);
$releases = $db->fetchAll('SELECT * FROM `remote_updates` ORDER BY `id` DESC LIMIT 30');

$maxUploadBytes = withu_remote_update_max_upload_bytes();
$form = $form ?? [
    'source_mode' => 'local',
    'version' => '',
    'title' => '',
    'body' => '',
    'apk_link' => '',
    'apk_sha256' => '',
    'force_update' => 0,
    'enabled' => 1,
];

$adminPage = 'remote_update';
$adminNarrow = true;
include __DIR__ . '/header.php';
?>

<style>
    .remote-update-source-options {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.65rem;
        margin: 0.35rem 0 1rem;
    }

    .remote-update-source-option {
        position: relative;
        display: flex;
        align-items: flex-start;
        gap: 0.65rem;
        min-height: 84px;
        padding: 0.8rem;
        border: 1px solid rgba(148, 163, 184, 0.45);
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.42);
        cursor: pointer;
    }

    .remote-update-source-option input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .remote-update-source-option:has(input:checked) {
        border-color: var(--v3-pink, #f26d9c);
        box-shadow: inset 0 0 0 1px var(--v3-pink, #f26d9c);
    }

    .remote-update-source-option:focus-within {
        outline: 2px solid rgba(242, 109, 156, 0.28);
        outline-offset: 2px;
    }

    .remote-update-source-option i {
        flex: 0 0 auto;
        margin-top: 0.1rem;
        color: var(--v3-pink-deep, #d94d81);
        font-size: 1.25rem;
    }

    .remote-update-source-copy {
        min-width: 0;
    }

    .remote-update-source-copy strong,
    .remote-update-source-copy small {
        display: block;
    }

    .remote-update-source-copy strong {
        margin-bottom: 0.2rem;
        color: var(--text, #334155);
        font-size: 0.88rem;
    }

    .remote-update-source-copy small {
        color: var(--text-light, #64748b);
        font-size: 0.75rem;
        line-height: 1.5;
    }

    .remote-update-field-group[hidden] {
        display: none;
    }

    .remote-update-current-meta {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.7rem;
        margin-top: 0.8rem;
    }

    .remote-update-meta-item {
        min-width: 0;
        padding: 0.7rem 0.75rem;
        border: 1px solid rgba(148, 163, 184, 0.35);
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.42);
    }

    .remote-update-meta-item span,
    .remote-update-meta-item strong {
        display: block;
    }

    .remote-update-meta-item span {
        margin-bottom: 0.25rem;
        color: var(--text-light, #64748b);
        font-size: 0.74rem;
    }

    .remote-update-meta-item strong {
        overflow: hidden;
        color: var(--text, #334155);
        font-size: 0.84rem;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .remote-update-body {
        margin-top: 0.7rem;
        padding-top: 0.7rem;
        border-top: 1px solid rgba(148, 163, 184, 0.3);
        color: var(--text, #334155);
        font-size: 0.84rem;
        line-height: 1.7;
    }

    .remote-update-history {
        display: flex;
        flex-direction: column;
        gap: 0.7rem;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .remote-update-history-item {
        padding: 0.85rem;
        border: 1px solid rgba(148, 163, 184, 0.35);
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.42);
    }

    .remote-update-history-head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.45rem;
    }

    .remote-update-history-head strong {
        margin-right: auto;
        color: var(--text, #334155);
        font-size: 0.9rem;
    }

    .remote-update-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.2rem 0.5rem;
        border-radius: 999px;
        background: rgba(100, 116, 139, 0.11);
        color: #526070;
        font-size: 0.7rem;
        font-weight: 700;
    }

    .remote-update-pill-active {
        background: rgba(34, 197, 94, 0.12);
        color: #15803d;
    }

    .remote-update-pill-force {
        background: rgba(234, 88, 12, 0.12);
        color: #c2410c;
    }

    .remote-update-history-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.3rem 0.9rem;
        margin-top: 0.4rem;
        color: var(--text-light, #64748b);
        font-size: 0.74rem;
    }

    .remote-update-history-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 0.65rem;
    }

    .remote-update-empty {
        padding: 1.4rem 0.8rem;
        border: 1px dashed rgba(148, 163, 184, 0.45);
        border-radius: 10px;
        color: var(--text-light, #64748b);
        font-size: 0.84rem;
        text-align: center;
    }

    @media (max-width: 560px) {
        .remote-update-source-options,
        .remote-update-current-meta {
            grid-template-columns: 1fr;
        }

        .remote-update-history-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .remote-update-history-actions form,
        .remote-update-history-actions button {
            width: 100%;
        }
    }
</style>

<section class="admin-page-title">
    <h1>远程更新</h1>
    <p>上传 APK 或填写 HTTPS 下载链接；启用后，课表客户端会读取最新版本并提示更新。</p>
</section>

<?php if ($error): ?><div class="admin-alert admin-alert-error"><?php echo e($error); ?></div><?php endif; ?>
<?php if ($success): ?><div class="admin-alert admin-alert-success"><?php echo e($success); ?></div><?php endif; ?>

<section class="admin-grid admin-grid-single">
    <div class="admin-card">
        <div class="admin-card-header">
            <div>
                <div class="admin-card-title">
                    <i class="ti ti-cloud-check" aria-hidden="true"></i>当前生效版本
                    <button type="button" class="admin-help-toggle" title="查看说明" aria-label="查看说明" aria-expanded="false"><i class="ti ti-info-circle"></i></button>
                </div>
            </div>
        </div>
        <div class="admin-card-help">
            <div class="admin-card-subtitle">客户端调用公开接口时返回的信息，只允许一个版本处于启用状态。</div>
        </div>

        <?php if ($currentRelease): ?>
            <div class="remote-update-current-meta">
                <div class="remote-update-meta-item">
                    <span>版本号</span>
                    <strong><?php echo e($currentRelease['version']); ?></strong>
                </div>
                <div class="remote-update-meta-item">
                    <span>更新标题</span>
                    <strong><?php echo e($currentRelease['title']); ?></strong>
                </div>
                <div class="remote-update-meta-item">
                    <span>安装包来源</span>
                    <strong><?php echo e(withu_remote_update_source_label($currentRelease)); ?></strong>
                </div>
                <div class="remote-update-meta-item">
                    <span>安装包大小</span>
                    <strong><?php echo e(withu_remote_update_format_bytes($currentRelease['size_bytes'])); ?></strong>
                </div>
                <div class="remote-update-meta-item">
                    <span>SHA-256</span>
                    <strong title="<?php echo e($currentRelease['sha256']); ?>"><?php echo e(substr((string)$currentRelease['sha256'], 0, 18)); ?>…</strong>
                </div>
                <div class="remote-update-meta-item">
                    <span>发布时间</span>
                    <strong><?php echo e($currentRelease['published_at'] ?: $currentRelease['updated_at']); ?></strong>
                </div>
            </div>
            <div class="remote-update-body">
                <?php echo nl2br(e((string)$currentRelease['body'])); ?>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.75rem;">
                <span class="remote-update-pill remote-update-pill-active"><i class="ti ti-circle-check"></i>正在下发</span>
                <?php if ((int)$currentRelease['force_update'] === 1): ?>
                    <span class="remote-update-pill remote-update-pill-force"><i class="ti ti-alert-triangle"></i>强制更新</span>
                <?php endif; ?>
                <a class="btn" href="<?php echo e(withu_remote_update_download_url($currentRelease)); ?>" target="_blank" rel="noopener">
                    <i class="ti ti-download"></i><span>检查下载地址</span>
                </a>
            </div>
        <?php else: ?>
            <div class="remote-update-empty">
                <i class="ti ti-cloud-off" style="display:block;margin-bottom:.4rem;font-size:1.5rem;"></i>
                暂无生效版本。发布新版本后，客户端才会收到更新提示。
            </div>
        <?php endif; ?>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <div>
                <div class="admin-card-title">
                    <i class="ti ti-cloud-upload" aria-hidden="true"></i>发布新版本
                    <button type="button" class="admin-help-toggle" title="查看说明" aria-label="查看说明" aria-expanded="false"><i class="ti ti-info-circle"></i></button>
                </div>
            </div>
        </div>
        <div class="admin-card-help">
            <div class="admin-card-subtitle">上传 APK 可自动计算摘要；使用外部链接时需要粘贴文件对应的 SHA-256。</div>
        </div>

        <form method="post" enctype="multipart/form-data" novalidate>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo (int)$maxUploadBytes; ?>">

            <div class="remote-update-source-options" role="radiogroup" aria-label="安装包来源">
                <label class="remote-update-source-option">
                    <input type="radio" name="source_mode" value="local" <?php echo $form['source_mode'] === 'local' ? 'checked' : ''; ?>>
                    <i class="ti ti-upload" aria-hidden="true"></i>
                    <span class="remote-update-source-copy">
                        <strong>上传 APK</strong>
                        <small>保存在本站，摘要自动计算；单文件最大 <?php echo e(withu_remote_update_format_bytes($maxUploadBytes)); ?></small>
                    </span>
                </label>
                <label class="remote-update-source-option">
                    <input type="radio" name="source_mode" value="link" <?php echo $form['source_mode'] === 'link' ? 'checked' : ''; ?>>
                    <i class="ti ti-link" aria-hidden="true"></i>
                    <span class="remote-update-source-copy">
                        <strong>粘贴链接</strong>
                        <small>使用 HTTPS 直链，客户端先跳转本站再下载并校验摘要</small>
                    </span>
                </label>
            </div>

            <label class="admin-field">
                版本号
                <input class="admin-input" type="text" name="version" value="<?php echo e($form['version']); ?>" placeholder="例如 1.2.0" maxlength="32">
                <span class="admin-help">客户端只会在远端版本比当前版本更新时弹窗。</span>
            </label>
            <label class="admin-field">
                更新标题
                <input class="admin-input" type="text" name="title" value="<?php echo e($form['title']); ?>" placeholder="例如：课表同步更稳定了" maxlength="120">
            </label>
            <label class="admin-field">
                更新说明
                <textarea class="admin-input" name="body" rows="5" maxlength="10000" placeholder="每行写一条变更说明"><?php echo e($form['body']); ?></textarea>
            </label>

            <div class="remote-update-field-group" id="remote-update-local-fields" <?php echo $form['source_mode'] !== 'local' ? 'hidden' : ''; ?>>
                <label class="admin-field">
                    APK 文件
                    <input class="admin-input" type="file" name="apk_file" accept=".apk,application/vnd.android.package-archive">
                    <span class="admin-help">选择 .apk 文件，保存时自动计算 SHA-256。</span>
                </label>
            </div>

            <div class="remote-update-field-group" id="remote-update-link-fields" <?php echo $form['source_mode'] !== 'link' ? 'hidden' : ''; ?>>
                <label class="admin-field">
                    APK 下载链接
                    <input class="admin-input" type="url" name="apk_link" value="<?php echo e($form['apk_link']); ?>" placeholder="https://example.com/app.apk" maxlength="512">
                    <span class="admin-help">必须是可直接下载 APK 的 HTTPS 链接。</span>
                </label>
                <label class="admin-field">
                    APK SHA-256
                    <input class="admin-input" type="text" name="apk_sha256" value="<?php echo e($form['apk_sha256']); ?>" placeholder="64 位小写十六进制摘要" pattern="[a-f0-9]{64}" autocomplete="off" autocapitalize="off" spellcheck="false">
                    <span class="admin-help">可用 <code>certutil -hashfile app.apk SHA256</code> 或 <code>sha256sum app.apk</code> 获取。</span>
                </label>
            </div>

            <div class="admin-grid" style="grid-template-columns:repeat(2,minmax(0,1fr));gap:.8rem;margin-top:.2rem;">
                <label class="switch">
                    <input type="checkbox" name="force_update" value="1" <?php echo $form['force_update'] ? 'checked' : ''; ?>>
                    <span class="switch-track"><span class="switch-thumb"></span></span>
                    <span class="switch-label">强制更新</span>
                </label>
                <label class="switch">
                    <input type="checkbox" name="enabled" value="1" <?php echo $form['enabled'] ? 'checked' : ''; ?>>
                    <span class="switch-track"><span class="switch-thumb"></span></span>
                    <span class="switch-label">保存后立即启用</span>
                </label>
            </div>

            <div class="admin-page-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="ti ti-device-desktop-check"></i><span><?php echo $form['enabled'] ? '发布版本' : '保存草稿'; ?></span>
                </button>
            </div>
        </form>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <div>
                <div class="admin-card-title">
                    <i class="ti ti-history" aria-hidden="true"></i>历史版本
                    <button type="button" class="admin-help-toggle" title="查看说明" aria-label="查看说明" aria-expanded="false"><i class="ti ti-info-circle"></i></button>
                </div>
            </div>
        </div>
        <div class="admin-card-help">
            <div class="admin-card-subtitle">保留最近 30 条发布记录；可停用当前版本、启用草稿或删除不再使用的版本。</div>
        </div>

        <?php if (!$releases): ?>
            <div class="remote-update-empty">还没有发布记录。</div>
        <?php else: ?>
            <ul class="remote-update-history">
                <?php foreach ($releases as $release): ?>
                    <?php
                        $isActive = (int)($release['enabled'] ?? 0) === 1;
                        $isForce = (int)($release['force_update'] ?? 0) === 1;
                        $historyTitle = trim((string)$release['title']) !== ''
                            ? trim((string)$release['title'])
                            : '版本 ' . $release['version'];
                    ?>
                    <li class="remote-update-history-item">
                        <div class="remote-update-history-head">
                            <strong>v<?php echo e($release['version']); ?> · <?php echo e($historyTitle); ?></strong>
                            <?php if ($isActive): ?>
                                <span class="remote-update-pill remote-update-pill-active"><i class="ti ti-circle-check"></i>生效中</span>
                            <?php else: ?>
                                <span class="remote-update-pill">已停用</span>
                            <?php endif; ?>
                            <?php if ($isForce): ?>
                                <span class="remote-update-pill remote-update-pill-force">强制</span>
                            <?php endif; ?>
                        </div>
                        <div class="remote-update-history-meta">
                            <span><?php echo e(withu_remote_update_source_label($release)); ?></span>
                            <span><?php echo e(withu_remote_update_format_bytes($release['size_bytes'])); ?></span>
                            <span>SHA-256 <?php echo e(substr((string)$release['sha256'], 0, 12)); ?>…</span>
                            <span><?php echo e($release['published_at'] ?: $release['updated_at']); ?></span>
                        </div>
                        <?php if (trim((string)$release['body']) !== ''): ?>
                            <div class="remote-update-body"><?php echo nl2br(e((string)$release['body'])); ?></div>
                        <?php endif; ?>
                        <div class="remote-update-history-actions">
                            <?php if (!$isActive): ?>
                                <form method="post">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="enable">
                                    <input type="hidden" name="id" value="<?php echo (int)$release['id']; ?>">
                                    <button type="submit" class="btn" <?php echo $currentRelease ? 'onclick="return confirm(\'启用后会替换当前生效版本，继续吗？\');"' : ''; ?>>
                                        <i class="ti ti-player-play"></i><span>启用</span>
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="post">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="disable">
                                    <input type="hidden" name="id" value="<?php echo (int)$release['id']; ?>">
                                    <button type="submit" class="btn">
                                        <i class="ti ti-player-pause"></i><span>停用</span>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <form method="post" onsubmit="return confirm('删除后无法恢复，确定删除该版本吗？');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int)$release['id']; ?>">
                                <button type="submit" class="btn" style="color:#b42318;">
                                    <i class="ti ti-trash"></i><span>删除</span>
                                </button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>

<script>
(function () {
    var sourceRadios = document.querySelectorAll('input[name="source_mode"]');
    var localFields = document.getElementById('remote-update-local-fields');
    var linkFields = document.getElementById('remote-update-link-fields');
    if (!sourceRadios.length || !localFields || !linkFields) return;

    function syncSourceFields() {
        var selected = document.querySelector('input[name="source_mode"]:checked');
        var mode = selected && selected.value === 'link' ? 'link' : 'local';
        localFields.hidden = mode !== 'local';
        linkFields.hidden = mode !== 'link';
    }

    sourceRadios.forEach(function (radio) {
        radio.addEventListener('change', syncSourceFields);
    });
    syncSourceFields();
}());
</script>

<?php include __DIR__ . '/footer.php'; ?>
