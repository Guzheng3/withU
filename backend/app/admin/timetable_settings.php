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
    // 与 API 侧一致：只接受标量，避免异常数据触发 Array to string 警告。
    $profileName = $package['profileName'] ?? '';
    $profileName = is_scalar($profileName) ? (string)$profileName : '';
    $semesterStart = $settings['semesterStartDate'] ?? '';
    $semesterStart = is_scalar($semesterStart) ? (string)$semesterStart : '';

    $db->insert('timetable_history', [
        'user_id' => $userId,
        'content' => $content,
        'content_hash' => (string)$contentHash,
        'change_type' => substr($changeType, 0, 32),
        'profile_name' => substr($profileName, 0, 190),
        'course_count' => count((array)($package['courses'] ?? [])),
        'current_week' => is_numeric($package['currentWeek'] ?? null) ? (int)$package['currentWeek'] : 0,
        'semester_start_date' => $semesterStart,
        'created_at' => withu_now(),
    ]);

    $rows = $db->fetchAll(
        'SELECT id FROM timetable_history
         WHERE user_id = :user_id
         ORDER BY id DESC
         LIMIT ' . withu_timetable_history_limit(),
        ['user_id' => $userId]
    );
    if (count($rows) < withu_timetable_history_limit()) {
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
    if (withu_timetable_is_full_backup($decoded)) {
        return ['ok' => false, 'message' => '这是包含多份课表的完整备份，无法作为单份课表导入；请在 App 内导出当前课表后再试。'];
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
        $user['history'] = withu_tt_history_for_user(
            $db,
            $uid,
            is_array($user['timetable_row']) ? (string)($user['timetable_row']['content'] ?? '') : null
        );
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

/* ============================================================
 * 历史版本 diff
 * 历史表存的是「每次变更前的快照」，因此第 i 条记录描述的变化是
 * 快照 i → 快照 i+1（最后一条与当前课表比较）。
 * ============================================================ */

function withu_tt_decode_package($content): ?array
{
    if (!is_string($content) || trim($content) === '') return null;
    $decoded = json_decode($content, true);
    if (!is_array($decoded) || array_is_list($decoded)) return null;
    return $decoded;
}

function withu_tt_course_identity(array $course): string
{
    $id = $course['id'] ?? null;
    if (is_scalar($id) && trim((string)$id) !== '') {
        return 'id:' . trim((string)$id);
    }
    // 旧格式没有课程 id：按「名称 + 星期 + 节次」匹配，避免数组顺序变化造成误判
    return 'c:' . md5(json_encode([
        $course['name'] ?? ($course['title'] ?? ''),
        $course['dayOfWeek'] ?? '',
        $course['startSection'] ?? '',
        $course['endSection'] ?? '',
    ], JSON_UNESCAPED_UNICODE));
}

function withu_tt_course_name(array $course): string
{
    $name = $course['name'] ?? ($course['title'] ?? '');
    $name = is_scalar($name) ? trim((string)$name) : '';
    return $name !== '' ? $name : '未命名课程';
}

function withu_tt_day_text($value): string
{
    $names = [1 => '周一', 2 => '周二', 3 => '周三', 4 => '周四', 5 => '周五', 6 => '周六', 7 => '周日'];
    $day = is_numeric($value) ? (int)$value : 0;
    return $names[$day] ?? '周' . ($day > 0 ? $day : '?');
}

function withu_tt_week_text(array $course): string
{
    $custom = array_values(array_filter(array_map('intval', (array)($course['customWeeks'] ?? []))));
    if ($custom) {
        $text = '第' . implode('、', $custom) . '周';
    } else {
        $start = is_numeric($course['startWeek'] ?? null) ? (int)$course['startWeek'] : 1;
        $end = is_numeric($course['endWeek'] ?? null) ? (int)$course['endWeek'] : 16;
        if (!empty($course['isOddWeek'])) {
            $text = '第' . $start . '-' . $end . '周（单周）';
        } elseif (!empty($course['isEvenWeek'])) {
            $text = '第' . $start . '-' . $end . '周（双周）';
        } elseif ($start === $end) {
            $text = '第' . $start . '周';
        } else {
            $text = '第' . $start . '-' . $end . '周';
        }
    }
    $suspended = array_values(array_filter(array_map('intval', (array)($course['suspendedWeeks'] ?? []))));
    if ($suspended) {
        $text .= '，停课：' . implode('、', $suspended);
    }
    return $text;
}

function withu_tt_scalar_text($value): string
{
    if (is_bool($value)) return $value ? '是' : '否';
    if ($value === null) return '空';
    if (is_scalar($value)) {
        $text = trim((string)$value);
        return $text === '' ? '空' : $text;
    }
    return '已调整';
}

function withu_tt_scheme_name(array $package): string
{
    $settings = is_array($package['settings'] ?? null) ? $package['settings'] : [];
    $schemes = is_array($package['timeSchemes'] ?? null) ? $package['timeSchemes'] : [];
    $activeId = $settings['activeTimeSchemeId'] ?? '';
    $activeId = is_scalar($activeId) ? (string)$activeId : '';
    foreach ($schemes as $scheme) {
        if (!is_array($scheme)) continue;
        $schemeId = $scheme['id'] ?? '';
        $schemeId = is_scalar($schemeId) ? (string)$schemeId : '';
        if ($schemeId === $activeId) {
            $name = $scheme['name'] ?? '';
            return is_scalar($name) && trim((string)$name) !== '' ? trim((string)$name) : '未命名模板';
        }
    }
    return $activeId === '' ? '默认节次' : '未知模板';
}

/** 单门课程对比：返回形如「地点 教1 → 教2」的细节列表。 */
function withu_tt_course_diff_details(array $before, array $after): array
{
    $details = [];

    $nameBefore = withu_tt_course_name($before);
    $nameAfter = withu_tt_course_name($after);
    if ($nameBefore !== $nameAfter) {
        $details[] = '课程名 ' . $nameBefore . ' → ' . $nameAfter;
    }

    foreach (['teacher' => '教师', 'location' => '地点'] as $key => $label) {
        $a = withu_tt_scalar_text($before[$key] ?? '');
        $b = withu_tt_scalar_text($after[$key] ?? '');
        if ($a !== $b) $details[] = $label . ' ' . $a . ' → ' . $b;
    }

    $dayA = (int)($before['dayOfWeek'] ?? 0);
    $dayB = (int)($after['dayOfWeek'] ?? 0);
    if ($dayA !== $dayB) {
        $details[] = '星期 ' . withu_tt_day_text($dayA) . ' → ' . withu_tt_day_text($dayB);
    }

    $secA = (int)($before['startSection'] ?? 0) . '-' . (int)($before['endSection'] ?? 0);
    $secB = (int)($after['startSection'] ?? 0) . '-' . (int)($after['endSection'] ?? 0);
    if ($secA !== $secB) {
        $details[] = '节次 第' . $secA . '节 → 第' . $secB . '节';
    }

    $weekA = withu_tt_week_text($before);
    $weekB = withu_tt_week_text($after);
    if ($weekA !== $weekB) {
        $details[] = '周次 ' . $weekA . ' → ' . $weekB;
    }

    $timeA = withu_tt_scalar_text($before['startTime'] ?? '') . '-' . withu_tt_scalar_text($before['endTime'] ?? '');
    $timeB = withu_tt_scalar_text($after['startTime'] ?? '') . '-' . withu_tt_scalar_text($after['endTime'] ?? '');
    if ($timeA !== $timeB) {
        $details[] = '时间 ' . $timeA . ' → ' . $timeB;
    }

    $simple = [
        'shortName' => '简称',
        'note' => '备注',
        'description' => '简介',
        'courseNature' => '课程性质',
        'timeSchemeIdOverride' => '时间模板',
    ];
    foreach ($simple as $key => $label) {
        $a = withu_tt_scalar_text($before[$key] ?? '');
        $b = withu_tt_scalar_text($after[$key] ?? '');
        if ($a !== $b) $details[] = $label . '已调整';
    }

    $colorA = withu_tt_scalar_text($before['color'] ?? '') . '|' . withu_tt_scalar_text($before['textColor'] ?? '');
    $colorB = withu_tt_scalar_text($after['color'] ?? '') . '|' . withu_tt_scalar_text($after['textColor'] ?? '');
    if ($colorA !== $colorB) $details[] = '课程颜色已调整';

    if (array_key_exists('sessionNotes', $before) || array_key_exists('sessionNotes', $after)) {
        if (json_encode($before['sessionNotes'] ?? null) !== json_encode($after['sessionNotes'] ?? null)) {
            $details[] = '节次备注已调整';
        }
    }

    return $details;
}

function withu_tt_trim_text(string $text, int $limit = 180): string
{
    if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > $limit) {
        return mb_substr($text, 0, $limit, 'UTF-8') . '…';
    }
    if (!function_exists('mb_strlen') && strlen($text) > $limit * 3) {
        return substr($text, 0, $limit * 3) . '…';
    }
    return $text;
}

/**
 * 两份课表快照的差异摘要。
 *
 * @return array{summary:string,added:array,removed:array,modified:array,settings:array,created:bool,cleared:bool}
 */
function withu_tt_diff_packages(?array $before, ?array $after): array
{
    $result = [
        'summary' => '',
        'added' => [],
        'removed' => [],
        'modified' => [],
        'settings' => [],
        'created' => false,
        'cleared' => false,
    ];

    if ($before === null && $after === null) {
        $result['summary'] = '空课表，无变化';
        return $result;
    }
    if ($before === null) {
        $count = count((array)($after['courses'] ?? []));
        $result['created'] = true;
        $result['summary'] = '创建课表（' . $count . ' 门课程）';
        return $result;
    }
    if ($after === null) {
        $count = count((array)($before['courses'] ?? []));
        $result['cleared'] = true;
        $result['summary'] = '清空课表（原 ' . $count . ' 门课程）';
        return $result;
    }

    // 传输元数据（packageId / exportedAt）每次导出都会变，不参与「是否有变化」判断
    $semanticBefore = $before;
    $semanticAfter = $after;
    unset($semanticBefore['packageId'], $semanticBefore['exportedAt']);
    unset($semanticAfter['packageId'], $semanticAfter['exportedAt']);
    if (json_encode($semanticBefore, JSON_UNESCAPED_UNICODE) === json_encode($semanticAfter, JSON_UNESCAPED_UNICODE)) {
        $result['summary'] = '无实质变化';
        return $result;
    }

    $beforeCourses = [];
    foreach ((array)($before['courses'] ?? []) as $course) {
        if (is_array($course)) $beforeCourses[withu_tt_course_identity($course)] = $course;
    }
    $afterCourses = [];
    foreach ((array)($after['courses'] ?? []) as $course) {
        if (is_array($course)) $afterCourses[withu_tt_course_identity($course)] = $course;
    }

    foreach ($afterCourses as $key => $course) {
        if (!isset($beforeCourses[$key])) {
            $result['added'][] = withu_tt_course_name($course);
        }
    }
    foreach ($beforeCourses as $key => $course) {
        if (!isset($afterCourses[$key])) {
            $result['removed'][] = withu_tt_course_name($course);
        }
    }
    foreach ($afterCourses as $key => $course) {
        if (!isset($beforeCourses[$key])) continue;
        $details = withu_tt_course_diff_details($beforeCourses[$key], $course);
        if ($details) {
            $result['modified'][] = [
                'name' => withu_tt_course_name($course),
                'details' => $details,
            ];
        }
    }

    // 课表级字段
    $profileA = withu_tt_scalar_text($before['profileName'] ?? '');
    $profileB = withu_tt_scalar_text($after['profileName'] ?? '');
    if ($profileA !== $profileB) {
        $result['settings'][] = '课表名 ' . $profileA . ' → ' . $profileB;
    }

    $weekA = is_numeric($before['currentWeek'] ?? null) ? (int)$before['currentWeek'] : 0;
    $weekB = is_numeric($after['currentWeek'] ?? null) ? (int)$after['currentWeek'] : 0;
    if ($weekA !== $weekB) {
        $result['settings'][] = '当前周 ' . ($weekA > 0 ? $weekA : '空') . ' → ' . ($weekB > 0 ? $weekB : '空');
    }

    $settingsA = is_array($before['settings'] ?? null) ? $before['settings'] : [];
    $settingsB = is_array($after['settings'] ?? null) ? $after['settings'] : [];

    $startA = $settingsA['semesterStartDate'] ?? null;
    $startB = $settingsB['semesterStartDate'] ?? null;
    $startAText = is_numeric($startA) ? withu_tt_millis_date($startA) : withu_tt_scalar_text($startA);
    $startBText = is_numeric($startB) ? withu_tt_millis_date($startB) : withu_tt_scalar_text($startB);
    if ($startAText !== $startBText) {
        $result['settings'][] = '开学日期 ' . $startAText . ' → ' . $startBText;
    }

    $semesterWeeksA = is_numeric($settingsA['semesterWeekCount'] ?? null) ? (int)$settingsA['semesterWeekCount'] : 0;
    $semesterWeeksB = is_numeric($settingsB['semesterWeekCount'] ?? null) ? (int)$settingsB['semesterWeekCount'] : 0;
    if ($semesterWeeksA !== $semesterWeeksB) {
        $result['settings'][] = '学期周数 ' . $semesterWeeksA . ' → ' . $semesterWeeksB;
    }

    $schemeA = withu_tt_scheme_name($before);
    $schemeB = withu_tt_scheme_name($after);
    if ($schemeA !== $schemeB) {
        $result['settings'][] = '激活模板 ' . $schemeA . ' → ' . $schemeB;
    }

    $sectionsA = is_array($settingsA['sections'] ?? null) ? count($settingsA['sections']) : 0;
    $sectionsB = is_array($settingsB['sections'] ?? null) ? count($settingsB['sections']) : 0;
    if ($sectionsA !== $sectionsB) {
        $result['settings'][] = '节次时间 ' . $sectionsA . ' → ' . $sectionsB . ' 段';
    }

    $schemesA = is_array($before['timeSchemes'] ?? null) ? $before['timeSchemes'] : [];
    $schemesB = is_array($after['timeSchemes'] ?? null) ? $after['timeSchemes'] : [];
    $schemeNamesA = [];
    foreach ($schemesA as $scheme) {
        if (is_array($scheme)) $schemeNamesA[] = withu_tt_scalar_text($scheme['name'] ?? '');
    }
    $schemeNamesB = [];
    foreach ($schemesB as $scheme) {
        if (is_array($scheme)) $schemeNamesB[] = withu_tt_scalar_text($scheme['name'] ?? '');
    }
    if ($schemeNamesA !== $schemeNamesB) {
        $result['settings'][] = '时间模板 ' . count($schemeNamesA) . ' → ' . count($schemeNamesB) . ' 套';
    }

    // 其余标量设置项：只报数量与键名，避免整段 JSON 淹没摘要
    $ignored = ['sections', 'activeTimeSchemeId', 'semesterWeekCount', 'semesterStartDate'];
    $changedKeys = [];
    foreach (array_unique(array_merge(array_keys($settingsA), array_keys($settingsB))) as $key) {
        if (in_array($key, $ignored, true)) continue;
        $valueA = $settingsA[$key] ?? null;
        $valueB = $settingsB[$key] ?? null;
        if (is_array($valueA) || is_array($valueB)) continue;
        if (withu_tt_scalar_text($valueA) !== withu_tt_scalar_text($valueB)) {
            $changedKeys[] = (string)$key;
        }
    }
    if ($changedKeys) {
        $shownKeys = array_slice($changedKeys, 0, 4);
        $result['settings'][] = '其他设置 ' . count($changedKeys) . ' 项变化（' . implode('、', $shownKeys) . (count($changedKeys) > 4 ? ' 等' : '') . '）';
    }

    $parts = [];
    if ($result['added']) {
        $names = array_slice($result['added'], 0, 3);
        $parts[] = '新增 ' . count($result['added']) . ' 门（' . implode('、', $names) . (count($result['added']) > 3 ? ' 等' : '') . '）';
    }
    if ($result['removed']) {
        $names = array_slice($result['removed'], 0, 3);
        $parts[] = '删除 ' . count($result['removed']) . ' 门（' . implode('、', $names) . (count($result['removed']) > 3 ? ' 等' : '') . '）';
    }
    if ($result['modified']) {
        $first = $result['modified'][0];
        $detailText = implode('；', array_slice($first['details'], 0, 2));
        $parts[] = '修改 ' . count($result['modified']) . ' 门（' . $first['name'] . '：' . $detailText . (count($result['modified']) > 1 ? ' 等' : '') . '）';
    }
    if ($result['settings']) {
        $parts[] = implode('；', $result['settings']);
    }
    if (!$parts) {
        $parts[] = '内容有更新（未检测到课程与设置差异）';
    }

    $result['summary'] = withu_tt_trim_text(implode('；', $parts), 220);
    return $result;
}

/** 某个账号的历史记录（含与下一版本/当前课表的 diff 摘要），按时间倒序。 */
function withu_tt_history_for_user(Database $db, int $userId, ?string $currentContent): array
{
    $rows = $db->fetchAll(
        'SELECT id, content, change_type, profile_name, course_count,
                current_week, semester_start_date, created_at
         FROM timetable_history
         WHERE user_id = :user_id
         ORDER BY id ASC',
        ['user_id' => $userId]
    );
    if (!$rows) return [];

    $entries = [];
    $total = count($rows);
    for ($i = 0; $i < $total; $i++) {
        $before = withu_tt_decode_package($rows[$i]['content'] ?? null);
        if ($i + 1 < $total) {
            $after = withu_tt_decode_package($rows[$i + 1]['content'] ?? null);
        } else {
            $after = withu_tt_decode_package($currentContent);
        }
        $diff = withu_tt_diff_packages($before, $after);
        $entries[] = [
            'id' => (int)$rows[$i]['id'],
            'change_type' => (string)($rows[$i]['change_type'] ?? 'save'),
            'profile_name' => (string)($rows[$i]['profile_name'] ?? ''),
            'course_count' => (int)($rows[$i]['course_count'] ?? 0),
            'current_week' => (int)($rows[$i]['current_week'] ?? 0),
            'created_at' => (string)($rows[$i]['created_at'] ?? ''),
            'summary' => $diff['summary'],
            'added' => count($diff['added']),
            'removed' => count($diff['removed']),
            'modified' => count($diff['modified']),
        ];
    }
    return array_reverse($entries);
}

/** 历史记录的变更来源标签。 */
function withu_tt_change_type_label(string $type): string
{
    $labels = [
        'save' => 'App 回传',
        'admin_import' => '后台导入',
        'rollback' => '历史回退',
    ];
    return $labels[$type] ?? $type;
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

    // 清涵课表 App 更名后写出 app=qhkb；旧版轻屿课表仍写 app=mikcb，
    // 两种标识的包结构一致，读取侧保持兼容。
    $app = $content['app'] ?? null;
    if (!in_array($app, ['qhkb', 'mikcb'], true)) {
        $shown = is_scalar($app) ? var_export($app, true) : (is_array($app) ? '非标量' : '缺失');
        $warnings[] = 'app 为 ' . $shown . '，预期 qhkb（兼容旧版 mikcb）';
    }

    $expected = [
        'packageType' => 'transfer',
        'schemaVersion' => 1,
        'scope' => 'current_timetable',
    ];
    foreach ($expected as $key => $expectedValue) {
        $actual = $content[$key] ?? null;
        if ($actual !== $expectedValue) {
            $shown = is_scalar($actual) ? var_export($actual, true) : (is_array($actual) ? '非标量' : '缺失');
            $warnings[] = $key . ' 为 ' . $shown . '，预期 ' . var_export($expectedValue, true);
        }
    }

    $channel = $content['channel'] ?? null;
    if (!in_array($channel, ['file', 'cloud'], true)) {
        $shown = is_scalar($channel) ? var_export($channel, true) : (is_array($channel) ? '非标量' : '缺失');
        $warnings[] = 'channel 为 ' . $shown . '，预期 file 或 cloud';
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
    $activeId = $settings['activeTimeSchemeId'] ?? '';
    $activeId = is_scalar($activeId) ? (string)$activeId : '';
    foreach ($schemes as $scheme) {
        if (!is_array($scheme)) continue;
        $schemeId = $scheme['id'] ?? '';
        $schemeId = is_scalar($schemeId) ? (string)$schemeId : '';
        if ($schemeId === $activeId) {
            $schemeName = $scheme['name'] ?? '未命名模板';
            return [
                is_scalar($schemeName) ? (string)$schemeName : '未命名模板',
                count((array)($scheme['sections'] ?? [])),
            ];
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

/* ---------- 一键删除某一条修改记录 ---------- */
$deleteNotice = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (string)($_POST['action'] ?? '') === 'delete_history') {
    if (!csrf_verify($_POST['_token'] ?? null)) {
        $deleteNotice = ['type' => 'error', 'text' => '表单已过期，请刷新页面后重试。'];
    } else {
        $historyId = (int)($_POST['history_id'] ?? 0);
        $allowedUserIds = array_map('intval', array_column($users, 'id'));
        $historyRow = $historyId > 0 ? $db->fetch(
            'SELECT id, user_id FROM timetable_history WHERE id = :id LIMIT 1',
            ['id' => $historyId]
        ) : null;
        if (!$historyRow || !in_array((int)$historyRow['user_id'], $allowedUserIds, true)) {
            $deleteNotice = ['type' => 'error', 'text' => '修改记录不存在或无权删除。'];
        } else {
            $db->query(
                'DELETE FROM timetable_history WHERE id = :id AND user_id = :user_id',
                ['id' => $historyId, 'user_id' => (int)$historyRow['user_id']]
            );
            header('Location: /admin/timetable_settings.php?deleted=1');
            exit;
        }
    }
}
if (!$deleteNotice && (int)($_GET['deleted'] ?? 0) === 1) {
    $deleteNotice = ['type' => 'success', 'text' => '已删除 1 条修改记录，当前课表不受影响。'];
}

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

/* ── 修改记录（diff） ── */
.tt-history { list-style: none; margin: 0; padding: 0; display: grid; gap: 0.55rem; }
.tt-history-item { display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.65rem 0.75rem; border: 1px solid var(--v3-border); border-radius: var(--v3-radius-sm); background: var(--v3-surface-soft); }
.tt-history-main { flex: 1 1 auto; min-width: 0; }
.tt-history-head { display: flex; flex-wrap: wrap; align-items: center; gap: 0.45rem; font-size: 0.74rem; color: var(--v3-text-3); }
.tt-history-time { font-weight: 600; color: var(--v3-text-2); font-variant-numeric: tabular-nums; }
.tt-history-type { padding: 0.05rem 0.45rem; border-radius: 999px; font-weight: 650; background: rgba(79, 168, 224, 0.12); color: #1d4ed8; }
.tt-history-type[data-type="admin_import"] { background: var(--v3-pink-glass); color: var(--v3-pink-deep); }
.tt-history-type[data-type="rollback"] { background: var(--v3-green-glass); color: var(--v3-green-deep); }
.tt-history-summary { margin-top: 0.32rem; font-size: 0.82rem; line-height: 1.6; color: var(--v3-text); word-break: break-word; }
.tt-history-meta { margin-top: 0.2rem; font-size: 0.7rem; color: var(--v3-text-3); }
.tt-history-delete { flex: none; }
.tt-history-delete .btn { white-space: nowrap; }
.tt-history-delete .btn i { margin-right: 0.2rem; }
@media (max-width: 580px) {
    .tt-history-item { flex-direction: column; }
    .tt-history-delete { align-self: flex-end; }
}
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
    <h1>课表设置 · 双方课表与修改记录</h1>
    <p>课表由清涵课表 App（qhkb，兼容旧版 mikcb）修改后回传。这里可以查看双方完整课表与课程天气、逐次修改的差异摘要，并删除某一条修改记录；编辑课表请在 App 内完成。</p>
</section>

<?php if ($deleteNotice): ?>
<div class="admin-alert admin-alert-<?php echo $deleteNotice['type'] === 'success' ? 'success' : 'error'; ?>" data-aos="fade-up">
    <i class="ti ti-<?php echo $deleteNotice['type'] === 'success' ? 'circle-check' : 'alert-triangle'; ?>" aria-hidden="true"></i>
    <?php echo e($deleteNotice['text']); ?>
</div>
<?php endif; ?>

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
                      placeholder="粘贴课表 JSON，例如：&#10;{ &quot;app&quot;: &quot;qhkb&quot;, &quot;courses&quot;: [ ... ], &quot;settings&quot;: { ... } }"><?php echo e($importDraft); ?></textarea>

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
        $history = is_array($user['history'] ?? null) ? $user['history'] : [];
        $avatar = trim((string)($user['avatar'] ?? ''));
        if ($avatar === '') {
            $avatar = 'https://q1.qlogo.cn/g?b=qq&nk=10000&s=640';
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

        <div class="tt-subblock">
            <div class="tt-block-title">
                近期修改记录
                <span class="tt-pill tt-pill-ok" style="margin-left:0.35rem;">保留 <?php echo count($history); ?>/<?php echo (int)withu_timetable_history_limit(); ?> 条</span>
            </div>
            <?php if (!$history): ?>
            <div class="tt-empty" style="padding:1rem;">
                <i class="ti ti-history-off" aria-hidden="true"></i>
                <strong>暂无修改记录</strong>
                <span>App 回传、后台导入或历史回退后，会在这里按时间倒序记录每次改动，并自动总结差异。</span>
            </div>
            <?php else: ?>
            <ul class="tt-history">
                <?php foreach ($history as $entry): ?>
                <li class="tt-history-item">
                    <div class="tt-history-main">
                        <div class="tt-history-head">
                            <span class="tt-history-time"><?php echo e((string)$entry['created_at']); ?></span>
                            <span class="tt-history-type" data-type="<?php echo e((string)$entry['change_type']); ?>"><?php echo e(withu_tt_change_type_label((string)$entry['change_type'])); ?></span>
                            <span><?php echo (int)$entry['course_count']; ?> 门课程<?php if ((int)$entry['current_week'] > 0): ?> · 第 <?php echo (int)$entry['current_week']; ?> 周<?php endif; ?></span>
                        </div>
                        <div class="tt-history-summary"><?php echo e((string)$entry['summary']); ?></div>
                        <div class="tt-history-meta">修改前快照 · 记录 #<?php echo (int)$entry['id']; ?></div>
                    </div>
                    <form method="post" class="tt-history-delete" onsubmit="return confirm('删除这条修改记录？删除后只影响历史列表，不会改动当前课表。');">
                        <input type="hidden" name="action" value="delete_history">
                        <input type="hidden" name="history_id" value="<?php echo (int)$entry['id']; ?>">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="btn btn-secondary btn-sm" title="删除这条修改记录">
                            <i class="ti ti-trash" aria-hidden="true"></i>删除
                        </button>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
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
        var isFullBackup = data.backupType === 'full'
            || (data.packageType === 'transfer' && data.scope === 'all_data' && Array.isArray(data.profiles));
        if (isFullBackup) {
            show('bad', '这是包含多份课表的完整备份，请在 App 内导出「当前课表」后再导入。');
            return;
        }
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
        var appOk = !data.app || data.app === 'qhkb' || data.app === 'mikcb';
        show('ok', '识别为课表：' + parts.join(' · ') + (appOk ? '' : ' · ⚠ app=' + data.app));
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
