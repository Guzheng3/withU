<?php
/**
 * withU 课表导入脚本：把「解析脚本仓库」（qingyu_warehouse）产出的课表 CSV
 * 转成与轻屿课表 App 回传完全同构的 JSON 包，写入 timetables 表。
 *
 * 哈希算法、历史保留与写入方式与 backend/app/api/timetable.php 的 action=save
 * 及后台「课表设置 · 回传看板」的导入完全一致：
 *   - 内容哈希排除 packageId / exportedAt 后对规范化 JSON 做 sha256；
 *   - 内容与当前一致时跳过（幂等，重复执行无副作用）；
 *   - 变更时把旧内容写入 timetable_history（change_type=script_import，App 内可回滚）。
 *
 * CSV 格式（mikcb 课程表 CSV，UTF-8 或 GBK，支持 BOM）：
 *   课程名,星期,开始节,结束节,上课周,教师,教室
 *   概率论与数理统计[29],1,3,4,1-14,韩东方,11号楼 11312
 *   上课周支持 1-14 / 9-16(双) / 1-16(单) / 1-3,5-8 / 14-14 等表达式，
 * 语义与 App 内 WeekExpressionParser 一致。
 *
 * 用法（命令行 / 定时任务）：
 *   php scripts/import-timetable.php [--csv <文件>] [--users 1,2]
 *       [--profile 课表名] [--semester-start YYYY-MM-DD] [--week-count N]
 *       [--current-week N] [--dry-run]
 *
 * 默认导入到所有启用状态的情侣账号（user1/user2）；--dry-run 只预览不落库。
 *
 * 退出码：0 = 成功；2 = 用法错误；1 = 执行失败。
 */

/* ---------- 引导：复用站点核心（CLI 安全，config.php 已兼容非 HTTP 环境） ---------- */
require __DIR__ . '/../backend/app/config/config.php';
require ROOT_PATH . '/core/Database.php';
require ROOT_PATH . '/core/withu.php';
require ROOT_PATH . '/core/helpers.php'; // migrate_schema_if_needed() 等迁移/辅助函数

/* ---------- 参数 ---------- */
$csvPath = dirname(__DIR__) . '/runtime/imports/kcb_2026-2027-1_可导入.csv';
$targetUsers = null;            // null = 全部情侣账号
$profileName = '2026-2027-1 课表';
$semesterStart = '2026-08-31';  // 学期第 1 周周一
$weekCount = 20;
$currentWeek = 1;
$dryRun = false;

$argv = $argv ?? [];
for ($i = 1; $i < count($argv); $i++) {
    switch ($argv[$i]) {
        case '--csv':            $csvPath = (string)($argv[++$i] ?? ''); break;
        case '--users':          $targetUsers = array_filter(array_map('intval', explode(',', (string)($argv[++$i] ?? '')))); break;
        case '--profile':        $profileName = (string)($argv[++$i] ?? ''); break;
        case '--semester-start': $semesterStart = (string)($argv[++$i] ?? ''); break;
        case '--week-count':     $weekCount = max(1, (int)($argv[++$i] ?? 20)); break;
        case '--current-week':   $currentWeek = max(1, (int)($argv[++$i] ?? 1)); break;
        case '--dry-run':        $dryRun = true; break;
        case '--help':
        case '-h':
            fwrite(STDOUT, '用法见脚本头部注释。' . PHP_EOL);
            exit(0);
        default:
            fwrite(STDERR, "未知参数：{$argv[$i]}" . PHP_EOL);
            exit(2);
    }
}

/* ---------- 常量：节次时间表（与 seed_withu_timetables.js 一致） ---------- */
const TT_SECTIONS = [
    ['08:00', '08:45'], ['08:50', '09:35'], ['10:00', '10:45'], ['10:50', '11:35'],
    ['14:00', '14:45'], ['14:50', '15:35'], ['16:00', '16:45'], ['16:50', '17:35'],
    ['19:00', '19:45'], ['19:50', '20:35'], ['20:40', '21:25'], ['21:30', '22:15'],
];

/** 周次表达式解析：语义对齐 App 的 WeekExpressionParser（去掉节次后缀、支持(单)/(双)全局奇偶）。 */
function tt_parse_weeks(string $raw, int $semesterWeekCount, array &$warnings): array
{
    $normalized = trim($raw);
    if ($normalized === '') {
        return [];
    }
    $normalized = preg_replace('/\s+/', '', $normalized);
    $normalized = preg_replace('/\[[^\]]*节\]/', '', $normalized);
    $normalized = preg_replace('/【[^】]*节】/', '', $normalized);

    $mode = null;
    if (preg_match('/[（(](全部|单|双)[）)]/', $normalized, $m) === 1) {
        $mode = $m[1];
    }
    $normalized = preg_replace('/[（(][^）)]*[）)]/', '', $normalized);

    $weeks = [];
    $anyTokenParity = false;
    $tokenCount = 0;
    foreach (preg_split('/[,，、]/', $normalized) as $tokenRaw) {
        $token = trim($tokenRaw);
        if ($token === '') {
            continue;
        }
        $tokenCount++;

        $tokenParity = null;
        if (str_ends_with($token, '单')) {
            $tokenParity = '单';
            $token = substr($token, 0, -1);
            $anyTokenParity = true;
        } elseif (str_ends_with($token, '双')) {
            $tokenParity = '双';
            $token = substr($token, 0, -1);
            $anyTokenParity = true;
        }

        if (preg_match('/^(\d+)-(\d+)$/', $token, $m) === 1) {
            $start = (int)$m[1];
            $end = (int)$m[2];
            if ($start < 1) {
                throw new RuntimeException("周次表达式「{$raw}」起始周无效");
            }
            if ($start > $end) {
                throw new RuntimeException("周次表达式「{$raw}」范围颠倒");
            }
            if ($end > 30) {
                throw new RuntimeException("周次表达式「{$raw}」超出 30 周上限");
            }
            for ($w = $start; $w <= $end; $w++) {
                $weeks[$w] = true;
            }
            if ($tokenParity !== null) {
                foreach (array_keys($weeks) as $w) {
                    if (($tokenParity === '单' && $w % 2 === 0) || ($tokenParity === '双' && $w % 2 === 1)) {
                        unset($weeks[$w]);
                    }
                }
            }
            continue;
        }

        if (preg_match('/^\d+$/', $token) === 1) {
            $week = (int)$token;
            if ($week < 1) {
                throw new RuntimeException("周次表达式「{$raw}」周数无效");
            }
            $weeks[$week] = true;
            if ($tokenParity !== null && (($tokenParity === '单' && $week % 2 === 0) || ($tokenParity === '双' && $week % 2 === 1))) {
                unset($weeks[$week]);
            }
            continue;
        }

        throw new RuntimeException("周次表达式「{$raw}」无法识别：{$tokenRaw}");
    }

    $list = array_keys($weeks);
    sort($list);

    // 全局 (单)/(双) 只作用于单个范围表达式（与 App 一致，避免误伤 1-5、7-11(单) 这类多段）
    if ($mode !== null && !$anyTokenParity && $tokenCount <= 1) {
        $list = array_values(array_filter($list, function (int $w) use ($mode): bool {
            return $mode === '单' ? $w % 2 === 1 : $w % 2 === 0;
        }));
    }

    foreach ($list as $w) {
        if ($w > $semesterWeekCount) {
            $warnings[] = "周次超出学期周数（{$semesterWeekCount}）已截断：{$w}";
        }
    }
    return array_values(array_filter($list, function (int $w) use ($semesterWeekCount): bool {
        return $w <= $semesterWeekCount;
    }));
}

/** CSV 文本 → 行数组（去 BOM、UTF-8/GBK 探测、按行解析）。 */
function tt_csv_rows(string $content): array
{
    if (str_starts_with($content, "\xEF\xBB\xBF")) {
        $content = substr($content, 3); // UTF-8 BOM 共 3 字节
    }
    if (!mb_check_encoding($content, 'UTF-8')) {
        $converted = mb_convert_encoding($content, 'UTF-8', 'GBK');
        if ($converted !== false && $converted !== '') {
            $content = $converted;
        }
    }
    $rows = [];
    foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
        if ($line === '') {
            continue;
        }
        $fields = str_getcsv($line);
        if (count($fields) > 0 && $fields[0] !== '') {
            $rows[] = $fields;
        }
    }
    return $rows;
}

/** 表头列名 → 列下标（支持别名，与 App 导入一致）。 */
function tt_column_map(array $headerRow): array
{
    $map = [];
    foreach ($headerRow as $i => $header) {
        $header = trim((string)$header);
        if ($header !== '') {
            $map[$header] = $i;
        }
    }
    return $map;
}

function tt_col(array $map, array $row, string $canonical, array $aliases, string $default = ''): string
{
    $index = $map[$canonical] ?? null;
    if ($index === null) {
        foreach ($aliases as $alias) {
            if (isset($map[$alias])) {
                $index = $map[$alias];
                break;
            }
        }
    }
    if ($index === null || !isset($row[$index])) {
        return $default;
    }
    return trim((string)$row[$index]);
}

function tt_fail(string $message): never
{
    fwrite(STDERR, '失败：' . $message . PHP_EOL);
    exit(1);
}

/* ---------- 1. 读取并解析 CSV ---------- */
if (!is_file($csvPath)) {
    return tt_fail("找不到课表 CSV：{$csvPath}（可用 --csv 指定）");
}
$content = file_get_contents($csvPath);
if ($content === false) {
    return tt_fail("读取 CSV 失败：{$csvPath}");
}

$rows = tt_csv_rows($content);
if (count($rows) < 2) {
    return tt_fail('CSV 内容为空或缺少表头/数据行。');
}

$map = tt_column_map($rows[0]);
foreach (['课程名', '星期', '开始节', '结束节'] as $required) {
    if (!isset($map[$required])) {
        $aliases = [
            '课程名' => ['课程名称'],
            '星期' => [],
            '开始节' => ['开始节数'],
            '结束节' => ['结束节数'],
        ];
        $found = false;
        foreach ($aliases[$required] as $alias) {
            if (isset($map[$alias])) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            return tt_fail("CSV 缺少必要列「{$required}」，请确认是 mikcb 课程表 CSV 格式。");
        }
    }
}
$hasWeeks = isset($map['上课周']) || isset($map['周数'])
    || (isset($map['开始周']) && isset($map['结束周']));
if (!$hasWeeks) {
    return tt_fail('CSV 缺少「上课周」或「开始周/结束周」列。');
}

$courses = [];
$warnings = [];
foreach (array_slice($rows, 1) as $rowIndex => $row) {
    $rowNumber = $rowIndex + 2;
    $name = tt_col($map, $row, '课程名', ['课程名称']);
    if ($name === '') {
        $warnings[] = "第 {$rowNumber} 行课程名为空，已跳过。";
        continue;
    }
    $dayOfWeek = (int)tt_col($map, $row, '星期', []);
    if ($dayOfWeek < 1 || $dayOfWeek > 7) {
        $warnings[] = "第 {$rowNumber} 行星期无效（{$dayOfWeek}），已跳过。";
        continue;
    }
    $startSection = (int)tt_col($map, $row, '开始节', ['开始节数']);
    $endSection = (int)tt_col($map, $row, '结束节', ['结束节数']);
    if ($startSection < 1 || $endSection < $startSection || $endSection > count(TT_SECTIONS)) {
        $warnings[] = "第 {$rowNumber} 行节次无效（{$startSection}-{$endSection}），已跳过。";
        continue;
    }
    $teacher = tt_col($map, $row, '教师', ['老师']);
    $location = tt_col($map, $row, '教室', ['地点', '上课地点']);

    $weeksRaw = tt_col($map, $row, '上课周', ['周数']);
    if ($weeksRaw === '') {
        $warnings[] = "第 {$rowNumber} 行缺少上课周，已跳过。";
        continue;
    }
    try {
        $customWeeks = tt_parse_weeks($weeksRaw, $weekCount, $warnings);
    } catch (RuntimeException $e) {
        $warnings[] = "第 {$rowNumber} 行：{$e->getMessage()}，已跳过。";
        continue;
    }
    if ($customWeeks === []) {
        $warnings[] = "第 {$rowNumber} 行上课周解析结果为空，已跳过。";
        continue;
    }

    $courses[] = [
        'id' => sprintf('kcb-import-%03d', count($courses) + 1),
        'name' => $name,
        'shortName' => null,
        'teacher' => $teacher,
        'location' => $location,
        'dayOfWeek' => $dayOfWeek,
        'startSection' => $startSection,
        'endSection' => $endSection,
        'startTime' => TT_SECTIONS[$startSection - 1][0],
        'endTime' => TT_SECTIONS[$endSection - 1][1],
        'color' => '#2196F3', // 与 App 电子表格导入的默认颜色一致
        'textColor' => null,
        'startWeek' => $customWeeks[0],
        'endWeek' => $customWeeks[count($customWeeks) - 1],
        'isOddWeek' => false,
        'isEvenWeek' => false,
        'customWeeks' => $customWeeks,
        'suspendedWeeks' => null,
        'courseNature' => 'normal',
        'description' => null,
        'note' => null,
        'sessionNotes' => null,
        'timeSchemeIdOverride' => null,
    ];
}

if ($courses === []) {
    return tt_fail('CSV 中没有解析出任何课程，请检查格式。');
}

/* ---------- 2. 组装课表 JSON 包 ---------- */
$semesterStartMs = strtotime($semesterStart . ' 00:00:00');
if ($semesterStartMs === false) {
    return tt_fail("开学日期无效：{$semesterStart}");
}
$semesterStartMs *= 1000;

$package = [
    'app' => 'mikcb',
    'packageType' => 'transfer',
    'schemaVersion' => 1,
    'packageId' => 'withu-couple-timetable',
    'scope' => 'current_timetable',
    'channel' => 'file',
    'exportedAt' => gmdate('c'),
    'profileName' => $profileName,
    'currentWeek' => $currentWeek,
    'settings' => [
        'sections' => array_map(
            fn(array $s): array => ['startTime' => $s[0], 'endTime' => $s[1]],
            TT_SECTIONS
        ),
        'activeTimeSchemeId' => 'scheme-import-1',
        'semesterWeekCount' => $weekCount,
        'semesterStartDate' => $semesterStartMs,
        'timeColumnWidthMode' => 'narrow',
        'sectionHeight' => 68,
        'compactFontSize' => 9,
        'courseCardShowName' => true,
        'courseCardShowTeacher' => true,
        'courseCardShowLocation' => true,
    ],
    'timeSchemes' => [
        [
            'id' => 'scheme-import-1',
            'name' => '当前课表时间',
            'sections' => array_map(
                fn(array $s): array => ['startTime' => $s[0], 'endTime' => $s[1]],
                TT_SECTIONS
            ),
        ],
    ],
    'courses' => $courses,
];

function tt_content_hash(array $decoded): string
{
    $semantic = $decoded;
    unset($semantic['packageId'], $semantic['exportedAt']);
    $canonical = json_encode($semantic, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return hash('sha256', $canonical === false ? '' : $canonical);
}

$contentHash = tt_content_hash($package);
$content = json_encode($package, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

fwrite(STDOUT, sprintf(
    "解析完成：%d 门课程（%s），课表「%s」，第 %d 周，%d 周学期，开学 %s\n",
    count($courses),
    $csvPath,
    $profileName,
    $currentWeek,
    $weekCount,
    $semesterStart
));
if ($warnings) {
    fwrite(STDOUT, '警告：' . PHP_EOL . implode(PHP_EOL, $warnings) . PHP_EOL);
}

if ($dryRun) {
    fwrite(STDOUT, 'DRY-RUN：内容哈希 ' . substr($contentHash, 0, 8) . '，未写入数据库。' . PHP_EOL);
    exit(0);
}

/* ---------- 3. 写入数据库（哈希去重 + 历史保留，与 App 回传一致） ---------- */
if (!extension_loaded('pdo_mysql')) {
    return tt_fail('PHP 缺少 pdo_mysql 扩展。本地运行请带上站点 php.ini：php -c deploy-local/php.ini scripts/import-timetable.php');
}
try {
    $db = Database::getInstance();
    migrate_schema_if_needed(); // 与站点启动一致：幂等补齐 timetables / timetable_history 等表
} catch (Exception $e) {
    return tt_fail('数据库连接失败：' . $e->getMessage());
}

if ($targetUsers === null) {
    $userRows = $db->fetchAll(
        "SELECT id, nickname, username FROM users
         WHERE status = 'active' AND role IN ('user1','user2') ORDER BY id ASC"
    );
    $targetUsers = array_map('intval', array_column($userRows, 'id'));
}
if ($targetUsers === []) {
    return tt_fail('没有可导入的课表账号（active 的 user1/user2）。');
}

function tt_capture_history(Database $db, int $userId, ?array $currentRow, string $changeType): void
{
    $content = $currentRow['content'] ?? null;
    $content = $content === null ? null : (string)$content;
    $contentHash = hash('sha256', (string)($content ?? 'null'));

    $decoded = $content === null ? null : json_decode($content, true);
    $package = is_array($decoded) ? $decoded : [];
    $settings = is_array($package['settings'] ?? null) ? $package['settings'] : [];

    $db->insert('timetable_history', [
        'user_id' => $userId,
        'content' => $content,
        'content_hash' => $contentHash,
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

$imported = 0;
$unchanged = 0;
foreach ($targetUsers as $userId) {
    $target = $db->fetch(
        "SELECT id, nickname, username FROM users
         WHERE id = :id AND status = 'active' AND role IN ('user1','user2') LIMIT 1",
        ['id' => $userId]
    );
    if (!$target) {
        fwrite(STDOUT, "跳过：用户 {$userId} 不是有效的情侣账号。\n");
        continue;
    }

    $currentRow = $db->fetch(
        'SELECT content, content_hash FROM timetables WHERE user_id = :user_id LIMIT 1',
        ['user_id' => $userId]
    );
    $currentHash = null;
    if ($currentRow && is_string($currentRow['content'] ?? null) && $currentRow['content'] !== '') {
        $decoded = json_decode($currentRow['content'], true);
        $currentHash = is_array($decoded) ? tt_content_hash($decoded) : ($currentRow['content_hash'] ?? null);
    }

    $name = (string)($target['nickname'] ?: $target['username']);
    if ($currentHash === $contentHash) {
        $unchanged++;
        fwrite(STDOUT, "「{$name}」：内容一致，未做改动。\n");
        continue;
    }

    if ($currentRow) {
        tt_capture_history($db, $userId, $currentRow, 'script_import');
    }
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
    $imported++;
    fwrite(STDOUT, "「{$name}」：已导入（哈希 " . substr($contentHash, 0, 8) . "，原内容已入历史）。\n");
}

fwrite(STDOUT, "导入完成：{$imported} 个账号更新，{$unchanged} 个账号无变化。\n");
exit(0);
