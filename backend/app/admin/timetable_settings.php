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
        "SELECT id, username, nickname, role FROM users
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

$users = withu_tt_users($db, $auth);
$currentId = (int)($auth->getCurrentUser()['id'] ?? 0);
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
.tt-kv { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.7rem 1.4rem; margin: 0.25rem 0 0.75rem; }
.tt-kv-item { display: grid; gap: 0.12rem; }
.tt-kv-label { font-size: 0.72rem; color: var(--v3-text-3); }
.tt-kv-value { font-size: 0.92rem; font-weight: 600; color: var(--v3-text); word-break: break-all; }
.tt-block-title { font-size: 0.78rem; font-weight: 650; color: var(--v3-text-2); margin: 0.9rem 0 0.55rem; }
@media (max-width: 767px) {
    .tt-kv { grid-template-columns: minmax(0, 1fr); }
}
</style>

<section class="admin-page-title">
    <h1>课表设置 · 回传看板</h1>
    <p>只读看板：课表由轻屿课表 App 修改后回传，这里查看双方最近一次回传的内容与格式健康度；编辑请在 App 内完成。</p>
</section>

<section class="admin-grid">
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
    ?>
    <div class="admin-card">
        <div class="admin-card-header">
            <div>
                <div class="admin-card-title">
                    <i class="ti ti-calendar-time" aria-hidden="true"></i><?php echo e($userName); ?><?php if ($user['username'] !== $userName): ?>（<?php echo e($user['username']); ?>）<?php endif; ?>
                    <span class="tt-pill <?php echo $isSelf ? 'tt-pill-self' : 'tt-pill-partner'; ?>"><?php echo $isSelf ? '我' : '对方'; ?></span>
                    <?php if (!$timetableRow): ?>
                    <span class="tt-pill tt-pill-empty">尚未同步</span>
                    <?php elseif ($timetable === null): ?>
                    <span class="tt-pill tt-pill-bad">数据异常</span>
                    <?php else: ?>
                    <span class="tt-pill tt-pill-ok">已同步</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (!$timetableRow): ?>
        <div class="admin-alert admin-alert-info">
            尚未回传课表。只有修改课表后 App 才会向 withU 回传，这里为空不代表掉线。
        </div>
        <?php elseif ($timetable === null): ?>
        <div class="admin-alert admin-alert-error">课表数据不是有效的 JSON 对象。</div>
        <div class="tt-kv">
            <div class="tt-kv-item"><span class="tt-kv-label">最近回传 updated_at</span><span class="tt-kv-value"><?php echo e((string)($timetableRow['updated_at'] ?? '-')); ?></span></div>
            <div class="tt-kv-item"><span class="tt-kv-label">内容哈希（前 8 位）</span><span class="tt-kv-value" title="<?php echo e((string)($timetableRow['content_hash'] ?? '')); ?>"><?php echo e(withu_tt_hash8($timetableRow['content_hash'] ?? null)); ?></span></div>
            <div class="tt-kv-item"><span class="tt-kv-label">内容大小</span><span class="tt-kv-value"><?php echo e(withu_tt_size($timetableRow['content'] ?? '')); ?></span></div>
        </div>
        <?php else:
            $warnings = withu_tt_health($timetable);
            $activeScheme = withu_tt_active_scheme($timetable);
            $ttSettings = is_array($timetable['settings'] ?? null) ? $timetable['settings'] : [];
        ?>
        <div class="tt-kv">
            <div class="tt-kv-item"><span class="tt-kv-label">课表名 profileName</span><span class="tt-kv-value"><?php echo e((string)($timetable['profileName'] ?? '') !== '' ? (string)$timetable['profileName'] : '（未设置）'); ?></span></div>
            <div class="tt-kv-item"><span class="tt-kv-label">最近回传 updated_at</span><span class="tt-kv-value"><?php echo e((string)($timetableRow['updated_at'] ?? '-')); ?></span></div>
            <div class="tt-kv-item"><span class="tt-kv-label">打包时间 exportedAt</span><span class="tt-kv-value"><?php echo e(withu_tt_iso_local($timetable['exportedAt'] ?? null)); ?></span></div>
            <div class="tt-kv-item"><span class="tt-kv-label">内容哈希（前 8 位）</span><span class="tt-kv-value" title="<?php echo e((string)($timetableRow['content_hash'] ?? '')); ?>"><?php echo e(withu_tt_hash8($timetableRow['content_hash'] ?? null)); ?></span></div>
            <div class="tt-kv-item"><span class="tt-kv-label">当前教学周</span><span class="tt-kv-value"><?php echo is_numeric($timetable['currentWeek'] ?? null) ? (int)$timetable['currentWeek'] : '-'; ?></span></div>
            <div class="tt-kv-item"><span class="tt-kv-label">课程数</span><span class="tt-kv-value"><?php echo count((array)($timetable['courses'] ?? [])); ?></span></div>
            <div class="tt-kv-item"><span class="tt-kv-label">时间模板数</span><span class="tt-kv-value"><?php echo count((array)($timetable['timeSchemes'] ?? [])); ?></span></div>
            <div class="tt-kv-item"><span class="tt-kv-label">激活模板</span><span class="tt-kv-value"><?php echo e($activeScheme[0]); ?> · <?php echo (int)$activeScheme[1]; ?> 节</span></div>
            <div class="tt-kv-item"><span class="tt-kv-label">开学日期</span><span class="tt-kv-value"><?php echo e(withu_tt_millis_date($ttSettings['semesterStartDate'] ?? null)); ?></span></div>
            <div class="tt-kv-item"><span class="tt-kv-label">学期周数</span><span class="tt-kv-value"><?php echo is_numeric($ttSettings['semesterWeekCount'] ?? null) ? (int)$ttSettings['semesterWeekCount'] : '-'; ?></span></div>
            <div class="tt-kv-item"><span class="tt-kv-label">settings 键数</span><span class="tt-kv-value"><?php echo count($ttSettings); ?> 键（旧版 10 / 当前 161+）</span></div>
        </div>
        <?php foreach ($warnings as $warning): ?>
        <div class="admin-alert admin-alert-warning"><i class="ti ti-alert-triangle" aria-hidden="true"></i><?php echo e($warning); ?></div>
        <?php endforeach; ?>
        <?php endif; ?>

        <div class="tt-block-title">个人设置回传（save_settings）</div>
        <?php if (!$settingsRow): ?>
        <div class="admin-alert admin-alert-info">尚未回传个人设置；App 内修改设置后才会回传。</div>
        <?php elseif ($settings === null): ?>
        <div class="admin-alert admin-alert-error">个人设置不是有效的 JSON 对象。</div>
        <?php else: ?>
        <div class="tt-kv">
            <div class="tt-kv-item"><span class="tt-kv-label">最近回传</span><span class="tt-kv-value"><?php echo e((string)($settingsRow['updated_at'] ?? '-')); ?></span></div>
            <div class="tt-kv-item"><span class="tt-kv-label">内容哈希（前 8 位）</span><span class="tt-kv-value" title="<?php echo e((string)($settingsRow['content_hash'] ?? '')); ?>"><?php echo e(withu_tt_hash8($settingsRow['content_hash'] ?? null)); ?></span></div>
            <div class="tt-kv-item"><span class="tt-kv-label">设置键数</span><span class="tt-kv-value"><?php echo count($settings); ?> 键（旧版 10 / 当前 164）</span></div>
            <div class="tt-kv-item"><span class="tt-kv-label">内容大小</span><span class="tt-kv-value"><?php echo e(withu_tt_size($settingsRow['content'] ?? '')); ?></span></div>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</section>

<?php include __DIR__ . '/footer.php'; ?>
