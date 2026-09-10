<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/withu.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requireRole(['user1', 'user2']);
$db = Database::getInstance();

/* ============================================================
 * 课表 JSON 导入（粘贴 / 选择文件）
 * 哈希算法、历史保留与写入方式与 api/timetable.php 的 action=save 完全一致，
 * 保证「App 回传」与「后台导入」两条路径互相去重、可互相回滚。
 * ============================================================ */

function withu_tt_content_hash(array $decoded): string
{
    $semanticContent = $decoded;
    unset($semanticContent['packageId'], $semanticContent['exportedAt']);
    $canonical = json_encode($semanticContent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return hash('sha256', $canonical === false ? '' : $canonical);
}

function withu_tt_raw_content(Database $db, int $userId): ?array
{
    $row = $db->fetch(
        'SELECT content, content_hash FROM timetables WHERE user_id = :user_id LIMIT 1',
        ['user_id' => $userId]
    );
    return $row ?: null;
}

function withu_tt_row_hash(?array $row): ?string
{
    if (!$row) {
        return null;
    }
    $rawContent = $row['content'] ?? null;
    if (!is_string($rawContent) || $rawContent === '') {
        return null;
    }
    $decoded = json_decode($rawContent, true);
    if (is_array($decoded)) {
        return withu_tt_content_hash($decoded);
    }
    $storedHash = $row['content_hash'] ?? null;
    return is_string($storedHash) && strlen($storedHash) === 64 ? $storedHash : null;
}

function withu_tt_upsert_content(Database $db, int $userId, string $content, string $contentHash): void
{
    $db->query(
        'INSERT INTO timetables (user_id, content, content_hash, updated_at)
         VALUES (:user_id, :content, :content_hash, :updated_at)
         ON DUPLICATE KEY UPDATE
            content = VALUES(content),
            content_hash = VALUES(content_hash),
            updated_at = VALUES(updated_at)',
        [
            'user_id' => $userId,
            'content' => $content,
            'content_hash' => $contentHash,
            'updated_at' => withu_now(),
        ]
    );
}

function withu_tt_capture_history(Database $db, int $userId, ?array $currentRow, string $changeType): void
{
    $rawContent = $currentRow['content'] ?? null;
    $content = $rawContent === null ? null : (string)$rawContent;
    $contentHash = withu_tt_row_hash($currentRow) ?? hash('sha256', (string)($content ?? 'null'));

    $decoded = $content === null ? null : json_decode($content, true);
    $package = is_array($decoded) ? $decoded : [];
    $settings = is_array($package['settings'] ?? null) ? $package['settings'] : [];

    $db->insert('timetable_history', [
        'user_id' => $userId,
        'content' => $content,
        'content_hash' => (string)$contentHash,
        'change_type' => substr($changeType, 0, 32),
        'profile_name' => substr((string)($package['profileName'] ?? ''), 0, 190),
        'course_count' => count((array)($package['courses'] ?? [])),
        'current_week' => is_numeric($package['currentWeek'] ?? null) ? (int)$package['currentWeek'] : 0,
        'semester_start_date' => (string)($settings['semesterStartDate'] ?? ''),
        'created_at' => withu_now(),
    ]);

    $rows = $db->fetchAll(
        'SELECT id FROM timetable_history
         WHERE user_id = :user_id
         ORDER BY id DESC
         LIMIT 13',
        ['user_id' => $userId]
    );
    if (count($rows) < 13) {
        return;
    }
    $oldestKeptId = min(array_map('intval', array_column($rows, 'id')));
    $db->query(
        'DELETE FROM timetable_history
         WHERE user_id = :user_id AND id < :oldest_kept_id',
        ['user_id' => $userId, 'oldest_kept_id' => $oldestKeptId]
    );
}

/**
 * 校验并写入一份课表 JSON。
 *
 * @return array{ok:bool,message:string,user_id?:int,unchanged?:bool}
 */
function withu_tt_import(Database $db, string $rawJson, $targetUserId, ?string $csrfToken): array
{
    if (!csrf_verify($csrfToken)) {
        return ['ok' => false, 'message' => '表单已过期，请刷新页面后重新提交。'];
    }

    $userId = (int)$targetUserId;
    $target = $userId > 0 ? $db->fetch(
        "SELECT id, nickname, username FROM users
         WHERE id = :id AND status = 'active' AND role IN ('user1','user2') LIMIT 1",
        ['id' => $userId]
    ) : null;
    if (!$target) {
        return ['ok' => false, 'message' => '导入目标账号无效，请重新选择。'];
    }

    $rawJson = trim($rawJson);
    if ($rawJson === '') {
        return ['ok' => false, 'message' => '请先粘贴课表 JSON 内容，或选择一个 .json 文件。'];
    }

    $decoded = json_decode($rawJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['ok' => false, 'message' => 'JSON 解析失败：' . json_last_error_msg() . '。请确认粘贴的是完整 JSON。'];
    }
    if (!is_array($decoded)) {
        return ['ok' => false, 'message' => '顶层必须是 JSON 对象（{ ... }）。'];
    }
    if (array_is_list($decoded)) {
        return ['ok' => false, 'message' => '顶层必须是 JSON 对象，当前是 JSON 数组（[ ... ]）。'];
    }

    $knownKeys = ['courses', 'settings', 'timeSchemes', 'profileName'];
    if (!array_intersect($knownKeys, array_keys($decoded))) {
        return ['ok' => false, 'message' => '这看起来不是课表包：至少需要 courses / settings / timeSchemes / profileName 之一。'];
    }

    $content = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($content === false) {
        return ['ok' => false, 'message' => '内容无法规范化编码，请检查 JSON 中是否含有非法字符。'];
    }
    if (strlen($content) > 2097152) {
        return ['ok' => false, 'message' => '内容超过 2 MB 上限，已拒绝导入。'];
    }

    $contentHash = withu_tt_content_hash($decoded);
    $currentRow = withu_tt_raw_content($db, $userId);
    if (withu_tt_row_hash($currentRow) === $contentHash) {
        return [
            'ok' => true,
            'user_id' => $userId,
            'unchanged' => true,
            'message' => '内容与该账号当前课表一致，未做改动。',
        ];
    }

    withu_tt_capture_history($db, $userId, $currentRow, 'admin_import');
    withu_tt_upsert_content($db, $userId, $content, $contentHash);

    $name = (string)($target['nickname'] ?: $target['username']);
    return [
        'ok' => true,
        'user_id' => $userId,
        'message' => '已导入到「' . $name . '」的课表' . ($currentRow ? '，原内容已写入历史（App 内可回滚）。' : '。'),
    ];
}

function withu_tt_users(Database $db, Auth $auth): array
{
    $current = $auth->getCurrentUser();
    $partner = $auth->getPartner();
    $ids = [];
    if ($current) $ids[] = (int)$current['id'];
    if ($partner) $ids[] = (int)$partner['id'];
    $ids = array_values(array_unique(array_filter($ids)));
    if (!$ids) return [];

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $users = $db->fetchAll(
        "SELECT id, username, nickname, role, avatar FROM users
         WHERE id IN ($placeholders) AND status = 'active'
         ORDER BY FIELD(role, 'user1', 'user2'), id ASC",
        $ids
    );
    foreach ($users as &$user) {
        $uid = (int)$user['id'];
        $user['timetable_row'] = $db->fetch(
            'SELECT content, content_hash, updated_at FROM timetables WHERE user_id = :uid LIMIT 1',
            ['uid' => $uid]
        );
        $user['settings_row'] = $db->fetch(
            'SELECT content, content_hash, updated_at FROM user_settings WHERE user_id = :uid LIMIT 1',
            ['uid' => $uid]
        );
        $user['timetable'] = null;
        $user['settings'] = null;
        if ($user['timetable_row']) {
            $decoded = json_decode((string)$user['timetable_row']['content'], true);
            $user['timetable'] = is_array($decoded) ? $decoded : null;
        }
        if ($user['settings_row']) {
            $decoded = json_decode((string)$user['settings_row']['content'], true);
            $user['settings'] = is_array($decoded) ? $decoded : null;
        }
    }
    unset($user);
    return $users;
}

function withu_tt_hash8(?string $hash): string
{
    $hash = trim((string)$hash);
    return $hash === '' ? '-' : substr($hash, 0, 8);
}

function withu_tt_size(?string $content): string
{
    $bytes = strlen((string)$content);
    if ($bytes < 1024) return $bytes . ' B';
    return round($bytes / 1024, 1) . ' KB';
}

function withu_tt_iso_local($value): string
{
    if (!is_string($value) || trim($value) === '') return '-';
    try {
        $date = new DateTime($value);
        $date->setTimezone(new DateTimeZone('Asia/Shanghai'));
        return $date->format('Y-m-d H:i');
    } catch (Exception $e) {
        return '-';
    }
}

function withu_tt_millis_date($value): string
{
    if (!is_numeric($value)) return '-';
    $millis = (int)$value;
    if ($millis <= 0) return '-';
    return date('Y-m-d', intdiv($millis, 1000));
}

function withu_tt_health(array $content): array
{
    $warnings = [];
    $expected = [
        'app' => 'mikcb',
        'packageType' => 'transfer',
        'schemaVersion' => 1,
        'scope' => 'current_timetable',
        'channel' => 'file',
    ];
    foreach ($expected as $key => $expectedValue) {
        $actual = $content[$key] ?? null;
        if ($actual !== $expectedValue) {
            $shown = is_scalar($actual) ? var_export($actual, true) : (is_array($actual) ? '非标量' : '缺失');
            $warnings[] = $key . ' 为 ' . $shown . '，预期 ' . var_export($expectedValue, true);
        }
    }

    $packageId = $content['packageId'] ?? null;
    $packageIdOk = $packageId === 'withu-couple-timetable'
        || (is_string($packageId) && preg_match('/^transfer-\d+$/', $packageId) === 1);
    if (!$packageIdOk) {
        $shown = is_scalar($packageId) ? var_export($packageId, true) : (is_array($packageId) ? '非标量' : '缺失');
        $warnings[] = 'packageId 为 ' . $shown . '，预期 withu-couple-timetable 或 transfer-时间戳';
    }

    $settings = $content['settings'] ?? null;
    if (!is_array($settings)) {
        $warnings[] = '缺少 settings 对象，App 回传格式异常';
    } else {
        $keys = array_keys($settings);
        $requiredKeys = ['sections', 'activeTimeSchemeId', 'semesterWeekCount', 'semesterStartDate'];
        $missing = array_values(array_diff($requiredKeys, $keys));
        if ($missing) {
            $warnings[] = 'settings 缺少核心键：' . implode('、', $missing);
        }
    }
    return $warnings;
}

function withu_tt_active_scheme(array $content): array
{
    $settings = (array)($content['settings'] ?? []);
    $schemes = (array)($content['timeSchemes'] ?? []);
    $activeId = (string)($settings['activeTimeSchemeId'] ?? '');
    foreach ($schemes as $scheme) {
        if (!is_array($scheme)) continue;
        if ((string)($scheme['id'] ?? '') === $activeId) {
            return [(string)($scheme['name'] ?? '未命名模板'), count((array)($scheme['sections'] ?? []))];
        }
    }
    return ['默认节次', count((array)($settings['sections'] ?? []))];
}

/** 看板统计条用的精简指标 */
function withu_tt_summary(array $content): array
{
    $settings = is_array($content['settings'] ?? null) ? $content['settings'] : [];
    $activeScheme = withu_tt_active_scheme($content);
    return [
        'courses' => count((array)($content['courses'] ?? [])),
        'current_week' => is_numeric($content['currentWeek'] ?? null) ? (int)$content['currentWeek'] : null,
        'schemes' => count((array)($content['timeSchemes'] ?? [])),
        'scheme_name' => $activeScheme[0],
        'sections' => $activeScheme[1],
        'semester_weeks' => is_numeric($settings['semesterWeekCount'] ?? null) ? (int)$settings['semesterWeekCount'] : null,
        'semester_start' => withu_tt_millis_date($settings['semesterStartDate'] ?? null),
        'settings_keys' => count($settings),
    ];
}

/* ---------- 处理导入提交（必须在任何输出之前，成功即重定向） ---------- */
$importNotice = null;
$importDraft = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (string)($_POST['action'] ?? '') === 'import_timetable') {
    $importResult = withu_tt_import(
        $db,
        (string)($_POST['json'] ?? ''),
        $_POST['target_user_id'] ?? 0,
        isset($_POST['_token']) ? (string)$_POST['_token'] : null
    );
    if (!empty($importResult['ok'])) {
        $query = 'imported=' . (int)$importResult['user_id'];
        if (!empty($importResult['unchanged'])) {
            $query .= '&unchanged=1';
        }
        header('Location: /admin/timetable_settings.php?' . $query);
        exit;
    }
    $importNotice = ['type' => 'error', 'text' => (string)$importResult['message']];
    $importDraft = (string)($_POST['json'] ?? '');   // 失败时把内容还给输入框，避免白粘贴
}

$users = withu_tt_users($db, $auth);
$currentId = (int)($auth->getCurrentUser()['id'] ?? 0);

// 导入成功后重定向回来，把成功提示渲染出来（提示里带上账号名）
if (!$importNotice && (int)($_GET['imported'] ?? 0) > 0) {
    $importedId = (int)$_GET['imported'];
    $importedName = '';
    foreach ($users as $u) {
        if ((int)$u['id'] === $importedId) {
            $importedName = (string)($u['nickname'] ?: $u['username']);
            break;
        }
    }
    $importNotice = [
        'type' => 'success',
        'text' => !empty($_GET['unchanged'])
            ? '内容与「' . $importedName . '」当前课表一致，未做改动。'
            : '课表 JSON 已导入到「' . $importedName . '」，原内容已写入历史（App 内可回滚）。',
    ];
}
$adminPage = 'timetable_settings';
$adminNarrow = true;
include __DIR__ . '/header.php';
?>

<style>
.tt-pill { display: inline-flex; align-items: center; margin-left: 0.4rem; padding: 0.1rem 0.55rem; border-radius: 999px; font-size: 0.72rem; font-weight: 650; vertical-align: middle; }
.tt-pill-ok { background: rgba(16, 185, 129, 0.12); color: #047857; }
.tt-pill-empty { background: rgba(148, 163, 184, 0.16); color: #64748b; }
.tt-pill-bad { background: rgba(239, 68, 68, 0.12); color: #b91c1c; }
.tt-pill-self { background: var(--v3-pink-glass, rgba(242, 109, 156, 0.12)); color: var(--v3-pink-deep, #db2777); }
.tt-pill-partner { background: rgba(59, 130, 246, 0.12); color: #1d4ed8; }

/* ── 导入卡片：跨满整行，放在看板最前 ── */
.admin-grid > .tt-import-card { grid-column: 1 / -1; }
.tt-import-head { display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 1rem; }
.tt-import-icon { flex: 0 0 38px; width: 38px; height: 38px; display: grid; place-items: center; border-radius: 12px; background: var(--v3-pink-glass); color: var(--v3-pink-deep); font-size: 19px; }
.tt-import-title { margin: 0; font-size: 1rem; font-weight: 700; color: var(--v3-text); }
.tt-import-sub { margin: 0.15rem 0 0; font-size: 0.8rem; line-height: 1.55; color: var(--v3-text-3); }
.tt-import-sub code { padding: 0.05rem 0.3rem; border-radius: 5px; background: var(--v3-surface-soft); font-size: 0.92em; }
.tt-import-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.6rem 1rem; margin-bottom: 0.7rem; }
.tt-target { display: inline-flex; gap: 3px; padding: 3px; border: 1px solid var(--v3-border); border-radius: 999px; background: var(--v3-surface-soft); }
.tt-target label { position: relative; display: inline-flex; align-items: center; }
.tt-target input { position: absolute; width: 0; height: 0; opacity: 0; }
.tt-target span { display: inline-flex; align-items: center; padding: 0.32rem 0.85rem; border-radius: 999px; font-size: 0.82rem; font-weight: 600; color: var(--v3-text-2); cursor: pointer; transition: background-color 0.2s var(--v3-ease), color 0.2s var(--v3-ease); }
.tt-target input:checked + span { background: var(--v3-surface); color: var(--v3-pink-deep); box-shadow: var(--v3-shadow-sm); }
.tt-target input:focus-visible + span { outline: 2px solid var(--v3-pink); outline-offset: 1px; }
.tt-import-actions { display: inline-flex; flex-wrap: wrap; gap: 0.5rem; }
.tt-import-actions .btn { cursor: pointer; }
.tt-json-input { display: block; width: 100%; box-sizing: border-box; min-height: 190px; padding: 0.75rem 0.85rem; border: 1px solid rgba(148, 163, 184, 0.6); border-radius: 0.75rem; background: var(--v3-surface); color: var(--v3-text); font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 0.78rem; line-height: 1.6; resize: vertical; outline: none; transition: border-color 0.2s var(--v3-ease), box-shadow 0.2s var(--v3-ease); }
.tt-json-input:focus { border-color: var(--v3-pink); box-shadow: 0 0 0 3px rgba(242, 109, 156, 0.12); }
.tt-import-foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.7rem; margin-top: 0.75rem; }
.tt-import-preview { flex: 1 1 320px; min-width: 0; padding: 0.5rem 0.7rem; border: 1px dashed var(--v3-border); border-radius: 0.7rem; background: var(--v3-surface-soft); font-size: 0.78rem; line-height: 1.5; color: var(--v3-text-3); }
.tt-import-preview[data-state="ok"] { border-color: rgba(63, 189, 139, 0.45); background: var(--v3-green-glass); color: var(--v3-green-deep); }
.tt-import-preview[data-state="bad"] { border-color: rgba(214, 69, 80, 0.4); background: rgba(214, 69, 80, 0.08); color: #a33a42; }
.tt-import-hint { margin: 0.6rem 0 0; font-size: 0.74rem; line-height: 1.55; color: var(--v3-text-3); }

/* ── 用户看板卡片 ── */
.tt-user-head { display: flex; align-items: center; gap: 0.7rem; margin-bottom: 0.9rem; }
.tt-user-avatar { flex: 0 0 38px; width: 38px; height: 38px; border-radius: 50%; object-fit: cover; background: var(--v3-surface-soft); }
.tt-user-ident { flex: 1 1 auto; min-width: 0; }
.tt-user-name { display: flex; align-items: center; gap: 0.1rem; font-size: 0.95rem; font-weight: 700; color: var(--v3-text); }
.tt-user-meta { margin-top: 2px; font-size: 0.74rem; color: var(--v3-text-3); word-break: break-all; }
.tt-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1px; margin-bottom: 0.9rem; border-radius: var(--v3-radius-sm); background: var(--v3-border); overflow: hidden; }
.tt-stat { display: flex; flex-direction: column; gap: 0.1rem; padding: 0.6rem 0.7rem; background: var(--v3-surface); }
.tt-stat-value { font-size: 1.15rem; font-weight: 750; line-height: 1.2; letter-spacing: -0.02em; color: var(--v3-text); font-variant-numeric: tabular-nums; }
.tt-stat-value-sm { font-size: 0.82rem; font-weight: 650; }
.tt-stat-label { font-size: 0.7rem; color: var(--v3-text-3); }
.tt-kv { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.55rem 1.1rem; margin: 0; }
.tt-kv-item { display: grid; gap: 0.1rem; min-width: 0; }
.tt-kv-label { font-size: 0.7rem; color: var(--v3-text-3); }
.tt-kv-value { font-size: 0.86rem; font-weight: 600; color: var(--v3-text); word-break: break-all; }
.tt-subblock { margin-top: 0.95rem; padding-top: 0.85rem; border-top: 1px solid var(--v3-border-light); }
.tt-block-title { margin: 0 0 0.55rem; font-size: 0.76rem; font-weight: 650; color: var(--v3-text-2); }
.tt-empty { display: flex; flex-direction: column; align-items: center; gap: 0.3rem; padding: 1.5rem 1rem; border: 1px dashed var(--v3-border); border-radius: var(--v3-radius-sm); background: var(--v3-surface-soft); text-align: center; }
.tt-empty i { font-size: 24px; color: var(--v3-text-3); }
.tt-empty strong { font-size: 0.86rem; font-weight: 650; color: var(--v3-text-2); }
.tt-empty span { max-width: 34ch; font-size: 0.76rem; line-height: 1.55; color: var(--v3-text-3); }
.tt-warn { margin-top: 0.7rem; }
.tt-warn .admin-alert { margin-bottom: 0.4rem; }
.tt-subblock .admin-alert { margin-bottom: 0; }
@media (max-width: 900px) {
    .tt-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 767px) {
    .tt-kv { grid-template-columns: minmax(0, 1fr); }
    .tt-import-actions { width: 100%; }
    .tt-import-actions .btn { flex: 1 1 auto; }
}
</style>

<section class="admin-page-title">
    <h1>课表设置 · 回传看板</h1>
    <p>只读看板：课表由轻屿课表 App 修改后回传，这里查看双方最近一次回传的内容与格式健康度；编辑请在 App 内完成。</p>
    <div style="margin-top:0.7rem;">
        <a class="btn btn-secondary btn-sm" href="/admin/warehouse_sync.php">
            <i class="ti ti-cloud-download" aria-hidden="true"></i>仓库同步管理
        </a>
    </div>
</section>

<section class="admin-grid">
    <div class="admin-card tt-import-card">
        <div class="tt-import-head">
            <span class="tt-import-icon"><i class="ti ti-database-import" aria-hidden="true"></i></span>
            <div>
                <h2 class="tt-import-title">导入课表 JSON</h2>
                <p class="tt-import-sub">把 App 导出的课表包（或任意一份课表 JSON）直接粘贴进来，或选择一份 <code>.json</code> 文件导入，不必等 App 回传。</p>
            </div>
        </div>

        <?php if ($importNotice): ?>
        <div class="admin-alert admin-alert-<?php echo $importNotice['type'] === 'success' ? 'success' : 'error'; ?>">
            <i class="ti ti-<?php echo $importNotice['type'] === 'success' ? 'circle-check' : 'alert-triangle'; ?>" aria-hidden="true"></i>
            <?php echo e($importNotice['text']); ?>
        </div>
        <?php endif; ?>

        <?php if ($users): ?>
        <form method="post" id="tt-import-form" autocomplete="off">
            <input type="hidden" name="action" value="import_timetable">
            <?php echo csrf_field(); ?>

            <div class="tt-import-toolbar">
                <div class="tt-target" role="radiogroup" aria-label="导入目标账号">
                    <?php foreach ($users as $optionIndex => $optionUser):
                        $optionName = $optionUser['nickname'] ?: $optionUser['username'];
                        $optionIsSelf = (int)$optionUser['id'] === $currentId;
                    ?>
                    <label>
                        <input type="radio" name="target_user_id" value="<?php echo (int)$optionUser['id']; ?>" <?php echo $optionIndex === 0 ? 'checked' : ''; ?>>
                        <span><?php echo $optionIsSelf ? '我' : '对方'; ?> · <?php echo e($optionName); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="tt-import-actions">
                    <label class="btn btn-secondary btn-sm" for="tt-json-file">
                        <i class="ti ti-upload" aria-hidden="true"></i>选择 .json 文件
                    </label>
                    <input type="file" id="tt-json-file" accept=".json,application/json,text/plain" hidden>
                    <button type="button" class="btn btn-secondary btn-sm" data-tt-clear>
                        <i class="ti ti-eraser" aria-hidden="true"></i>清空
                    </button>
                </div>
            </div>

            <textarea class="tt-json-input" name="json" rows="10" spellcheck="false"
                      placeholder="粘贴课表 JSON，例如：&#10;{ &quot;app&quot;: &quot;mikcb&quot;, &quot;courses&quot;: [ ... ], &quot;settings&quot;: { ... } }"><?php echo e($importDraft); ?></textarea>

            <div class="tt-import-foot">
                <div class="tt-import-preview" id="tt-preview" data-state="idle" aria-live="polite"></div>
                <button type="submit" class="btn btn-primary">
                    <i class="ti ti-database-import" aria-hidden="true"></i>导入并覆盖
                </button>
            </div>
            <p class="tt-import-hint">导入会覆盖所选账号的当前课表；原内容会自动写入历史（最多保留 13 条，App 内可回滚）。单份上限 2 MB。</p>
        </form>
        <?php else: ?>
        <div class="admin-alert admin-alert-error">未找到可导入的课表账号。</div>
        <?php endif; ?>
    </div>

    <?php if (!$users): ?>
    <div class="admin-alert admin-alert-error">未找到可查看的课表用户。</div>
    <?php endif; ?>

    <?php foreach ($users as $user):
        $userName = $user['nickname'] ?: $user['username'];
        $isSelf = (int)$user['id'] === $currentId;
        $timetableRow = $user['timetable_row'];
        $timetable = $user['timetable'];
        $settingsRow = $user['settings_row'];
        $settings = $user['settings'];
        $avatar = trim((string)($user['avatar'] ?? ''));
        if ($avatar === '') {
            $avatar = '/assets/images/default-avatar.svg';
        }
    ?>
    <div class="admin-card">
        <div class="tt-user-head">
            <img class="tt-user-avatar" src="<?php echo e($avatar); ?>" alt="" loading="lazy">
            <div class="tt-user-ident">
                <div class="tt-user-name">
                    <?php echo e($userName); ?>
                    <span class="tt-pill <?php echo $isSelf ? 'tt-pill-self' : 'tt-pill-partner'; ?>"><?php echo $isSelf ? '我' : '对方'; ?></span>
                </div>
                <div class="tt-user-meta">
                    @<?php echo e($user['username']); ?><?php if ($timetableRow): ?> · 最近回传 <?php echo e((string)($timetableRow['updated_at'] ?? '-')); ?><?php endif; ?>
                </div>
            </div>
            <?php if (!$timetableRow): ?>
            <span class="tt-pill tt-pill-empty" style="margin-left:0;">尚未同步</span>
            <?php elseif ($timetable === null): ?>
            <span class="tt-pill tt-pill-bad" style="margin-left:0;">数据异常</span>
            <?php else: ?>
            <span class="tt-pill tt-pill-ok" style="margin-left:0;">已同步</span>
            <?php endif; ?>
        </div>

        <?php if (!$timetableRow): ?>
        <div class="tt-empty">
            <i class="ti ti-calendar-off" aria-hidden="true"></i>
            <strong>尚未回传课表</strong>
            <span>App 内修改课表后才会自动回传，这里为空不代表掉线；也可以在上方直接粘贴 JSON 导入。</span>
        </div>
        <?php elseif ($timetable === null): ?>
        <div class="admin-alert admin-alert-error">课表数据不是有效的 JSON 对象。</div>
        <div class="tt-kv">
            <div class="tt-kv-item"><span class="tt-kv-label">内容哈希（前 8 位）</span><span class="tt-kv-value" title="<?php echo e((string)($timetableRow['content_hash'] ?? '')); ?>"><?php echo e(withu_tt_hash8($timetableRow['content_hash'] ?? null)); ?></span></div>
            <div class="tt-kv-item"><span class="tt-kv-label">内容大小</span><span class="tt-kv-value"><?php echo e(withu_tt_size($timetableRow['content'] ?? '')); ?></span></div>
        </div>
        <?php else:
            $warnings = withu_tt_health($timetable);
            $summary = withu_tt_summary($timetable);
        ?>
        <div class="tt-stats">
            <div class="tt-stat">
                <span class="tt-stat-value"><?php echo (int)$summary['courses']; ?></span>
                <span class="tt-stat-label">课程</span>
            </div>
            <div class="tt-stat">
                <span class="tt-stat-value"><?php echo $summary['current_week'] === null ? '—' : (int)$summary['current_week']; ?></span>
                <span class="tt-stat-label">当前教学周</span>
            </div>
            <div class="tt-stat">
                <span class="tt-stat-value"><?php echo (int)$summary['schemes']; ?></span>
                <span class="tt-stat-label">时间模板</span>
            </div>
            <div class="tt-stat">
                <span class="tt-stat-value"><?php echo (int)$summary['sections']; ?></span>
                <span class="tt-stat-label">激活节次</span>
            </div>
            <div class="tt-stat">
                <span class="tt-stat-value"><?php echo $summary['semester_weeks'] === null ? '—' : (int)$summary['semester_weeks']; ?></span>
                <span class="tt-stat-label">学期周数</span>
            </div>
            <div class="tt-stat">
                <span class="tt-stat-value tt-stat-value-sm"><?php echo e($summary['semester_start']); ?></span>
                <span class="tt-stat-label">开学日期</span>
            </div>
        </div>

        <div class="tt-kv">
            <div class="tt-kv-item"><span class="tt-kv-label">课表名 profileName</span><span class="tt-kv-value"><?php echo e((string)($timetable['profileName'] ?? '') !== '' ? (string)$timetable['profileName'] : '（未设置）'); ?></span></div>
            <div class="tt-kv-item"><span class="tt-kv-label">激活模板</span><span class="tt-kv-value"><?php echo e($summary['scheme_name']); ?></span></div>
            <div class="tt-kv-item"><span class="tt-kv-label">打包时间 exportedAt</span><span class="tt-kv-value"><?php echo e(withu_tt_iso_local($timetable['exportedAt'] ?? null)); ?></span></div>
            <div class="tt-kv-item"><span class="tt-kv-label">内容大小</span><span class="tt-kv-value"><?php echo e(withu_tt_size($timetableRow['content'] ?? '')); ?></span></div>
            <div class="tt-kv-item"><span class="tt-kv-label">内容哈希（前 8 位）</span><span class="tt-kv-value" title="<?php echo e((string)($timetableRow['content_hash'] ?? '')); ?>"><?php echo e(withu_tt_hash8($timetableRow['content_hash'] ?? null)); ?></span></div>
            <div class="tt-kv-item"><span class="tt-kv-label">settings 键数</span><span class="tt-kv-value"><?php echo (int)$summary['settings_keys']; ?></span></div>
        </div>

        <?php if ($warnings): ?>
        <div class="tt-warn">
            <?php foreach ($warnings as $warning): ?>
            <div class="admin-alert admin-alert-warning"><i class="ti ti-alert-triangle" aria-hidden="true"></i><?php echo e($warning); ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <div class="tt-subblock">
            <div class="tt-block-title">个人设置回传（save_settings）</div>
            <?php if (!$settingsRow): ?>
            <div class="admin-alert admin-alert-info">尚未回传个人设置；App 内修改设置后才会回传。</div>
            <?php elseif ($settings === null): ?>
            <div class="admin-alert admin-alert-error">个人设置不是有效的 JSON 对象。</div>
            <?php else: ?>
            <div class="tt-kv">
                <div class="tt-kv-item"><span class="tt-kv-label">最近回传</span><span class="tt-kv-value"><?php echo e((string)($settingsRow['updated_at'] ?? '-')); ?></span></div>
                <div class="tt-kv-item"><span class="tt-kv-label">设置键数</span><span class="tt-kv-value"><?php echo count($settings); ?></span></div>
                <div class="tt-kv-item"><span class="tt-kv-label">内容哈希（前 8 位）</span><span class="tt-kv-value" title="<?php echo e((string)($settingsRow['content_hash'] ?? '')); ?>"><?php echo e(withu_tt_hash8($settingsRow['content_hash'] ?? null)); ?></span></div>
                <div class="tt-kv-item"><span class="tt-kv-label">内容大小</span><span class="tt-kv-value"><?php echo e(withu_tt_size($settingsRow['content'] ?? '')); ?></span></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</section>

<script>
(function () {
    var form = document.getElementById('tt-import-form');
    if (!form) { return; }

    var input = form.querySelector('.tt-json-input');
    var preview = document.getElementById('tt-preview');
    var fileInput = document.getElementById('tt-json-file');
    var maxBytes = 2 * 1024 * 1024;

    function show(state, text) {
        preview.dataset.state = state;
        preview.textContent = text;
    }

    // 粘贴/输入时即时解析，先在本地把格式问题挡掉，避免白提交
    function analyze() {
        var raw = input.value.trim();
        if (!raw) {
            show('idle', '等待粘贴课表 JSON，或选择一个 .json 文件。');
            return;
        }
        if (raw.length > maxBytes) {
            show('bad', '内容超过 2 MB 上限（当前 ' + (raw.length / 1024 / 1024).toFixed(2) + ' MB）。');
            return;
        }
        var data;
        try {
            data = JSON.parse(raw);
        } catch (err) {
            show('bad', 'JSON 解析失败：' + err.message);
            return;
        }
        if (!data || typeof data !== 'object' || Array.isArray(data)) {
            show('bad', '顶层必须是 JSON 对象 { ... }。');
            return;
        }
        var known = ['courses', 'settings', 'timeSchemes', 'profileName'].some(function (key) {
            return Object.prototype.hasOwnProperty.call(data, key);
        });
        var parts = [];
        if (data.profileName) { parts.push('课表名 ' + data.profileName); }
        parts.push('课程 ' + (Array.isArray(data.courses) ? data.courses.length : 0) + ' 门');
        parts.push('模板 ' + (Array.isArray(data.timeSchemes) ? data.timeSchemes.length : 0) + ' 套');
        if (data.settings && typeof data.settings === 'object') {
            parts.push('设置 ' + Object.keys(data.settings).length + ' 项');
        }
        if (data.currentWeek !== undefined && data.currentWeek !== null) {
            parts.push('当前第 ' + data.currentWeek + ' 周');
        }
        parts.push((raw.length / 1024).toFixed(1) + ' KB');
        if (!known) {
            show('bad', '已解析，但缺少 courses / settings / timeSchemes / profileName，提交会被拒绝。');
            return;
        }
        show('ok', '识别为课表：' + parts.join(' · ') + (data.app && data.app !== 'mikcb' ? ' · ⚠ app=' + data.app : ''));
    }

    function readFile(file) {
        if (!file) { return; }
        if (file.size > maxBytes) {
            show('bad', '文件超过 2 MB 上限（' + (file.size / 1024 / 1024).toFixed(2) + ' MB）。');
            return;
        }
        var reader = new FileReader();
        reader.onload = function () {
            input.value = String(reader.result || '');
            analyze();
        };
        reader.onerror = function () { show('bad', '文件读取失败，请重试。'); };
        reader.readAsText(file, 'utf-8');
    }

    input.addEventListener('input', analyze);

    fileInput.addEventListener('change', function () {
        readFile(fileInput.files && fileInput.files[0]);
        fileInput.value = '';
    });

    input.addEventListener('dragover', function (event) { event.preventDefault(); });
    input.addEventListener('drop', function (event) {
        if (event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files.length) {
            event.preventDefault();
            readFile(event.dataTransfer.files[0]);
        }
    });

    var clearBtn = form.querySelector('[data-tt-clear]');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            input.value = '';
            analyze();
            input.focus();
        });
    }

    analyze();
})();
</script>

<?php include __DIR__ . '/footer.php'; ?>
