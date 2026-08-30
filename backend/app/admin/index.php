<?php
// 新版后台 - 仪表盘（移动端优先）
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/MediaTranscode.php';

$auth = new Auth();
$auth->requireLogin();
$db          = Database::getInstance();
$currentUser = $auth->getCurrentUser();

// 确保 site_visits 表存在
$db->query("CREATE TABLE IF NOT EXISTS `site_visits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `visit_date` DATE NOT NULL,
    `page_views` INT NOT NULL DEFAULT 0,
    `unique_visitors` INT NOT NULL DEFAULT 0,
    UNIQUE KEY `uk_date` (`visit_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 今日日期
$today = date('Y-m-d');

// 统计数据
// 基础统计
$articleCountRow = $db->fetch("SELECT COUNT(*) AS c FROM articles WHERE status != 'deleted'");
$albumCountRow   = $db->fetch("SELECT COUNT(*) AS c FROM albums");
$eventCountRow   = $db->fetch("SELECT COUNT(*) AS c FROM events");
$messageCountRow = $db->fetch("SELECT COUNT(*) AS c FROM messages WHERE status != 'deleted'");

$articleCount = (int) ($articleCountRow['c'] ?? 0);
$albumCount   = (int) ($albumCountRow['c'] ?? 0);
$eventCount   = (int) ($eventCountRow['c'] ?? 0);
$messageCount = (int) ($messageCountRow['c'] ?? 0);

// 访问统计
$todayVisitRow = $db->fetch("SELECT page_views, unique_visitors FROM site_visits WHERE visit_date = ?", [$today]);
$todayViews    = (int) ($todayVisitRow['page_views'] ?? 0);
$todayVisitors = (int) ($todayVisitRow['unique_visitors'] ?? 0);

$totalVisitRow = $db->fetch("SELECT SUM(page_views) AS total_views, SUM(unique_visitors) AS total_visitors FROM site_visits");
$totalViews    = (int) ($totalVisitRow['total_views'] ?? 0);
$totalVisitors = (int) ($totalVisitRow['total_visitors'] ?? 0);

// 详细统计：域名分布
$domainStats = [];
try {
    $domainRows = $db->fetchAll("SELECT domain, COUNT(*) AS cnt FROM visitor_logs WHERE visit_date = ? GROUP BY domain ORDER BY cnt DESC LIMIT 5", [$today]);
    $domainStats = $domainRows ?: [];
} catch (Throwable $e) { $domainStats = []; }

// 最近访问记录
$recentVisitors = [];
try {
    $recentVisitors = $db->fetchAll(
        "SELECT visit_time, ip_address, domain, ua_browser, ua_os, ua_device, page_url 
         FROM visitor_logs 
         ORDER BY visit_time DESC LIMIT 10"
    ) ?: [];
} catch (Throwable $e) { $recentVisitors = []; }

// 设备/浏览器分布
$deviceStats = [];
$browserStats = [];
try {
    $deviceRows = $db->fetchAll("SELECT ua_device, COUNT(*) AS cnt FROM visitor_logs WHERE visit_date = ? GROUP BY ua_device ORDER BY cnt DESC", [$today]);
    $deviceStats = $deviceRows ?: [];
    $browserRows = $db->fetchAll("SELECT ua_browser, COUNT(*) AS cnt FROM visitor_logs WHERE visit_date = ? AND ua_browser != '' GROUP BY ua_browser ORDER BY cnt DESC LIMIT 5", [$today]);
    $browserStats = $browserRows ?: [];
} catch (Throwable $e) { $deviceStats = []; $browserStats = []; }

// ============ 流量统计（百度统计风格）+ 蜘蛛统计 ============
// 趋势与指标取自 site_visits（每日 PV/UV 聚合）与 visitor_logs（访问明细，含完整 UA），
// 蜘蛛通过 User-Agent 识别。查询失败时保持仪表盘其余部分可用。
$statsRangeDefs = [
    'today'     => '今天',
    'yesterday' => '昨天',
    '7d'        => '最近 7 天',
    '30d'       => '最近 30 天',
];
$statsRangeKey = isset($_GET['range']) && isset($statsRangeDefs[(string) $_GET['range']])
    ? (string) $_GET['range']
    : '7d';

$statsTodayTs = strtotime($today);
$statsEndTs   = $statsTodayTs;
$statsStartTs = $statsTodayTs;
if ($statsRangeKey === 'yesterday') {
    $statsStartTs = $statsEndTs = $statsTodayTs - 86400;
} elseif ($statsRangeKey === '7d') {
    $statsStartTs = $statsTodayTs - 6 * 86400;
} elseif ($statsRangeKey === '30d') {
    $statsStartTs = $statsTodayTs - 29 * 86400;
}
$statsDays  = (int) round(($statsEndTs - $statsStartTs) / 86400) + 1;
$statsStart = date('Y-m-d', $statsStartTs);
$statsEnd   = date('Y-m-d', $statsEndTs);
// 环比：紧邻的等长上一周期
$statsPrevEndTs   = $statsStartTs - 86400;
$statsPrevStartTs = $statsPrevEndTs - ($statsDays - 1) * 86400;
$statsPrevStart   = date('Y-m-d', $statsPrevStartTs);
$statsPrevEnd     = date('Y-m-d', $statsPrevEndTs);

// 蜘蛛定义（UA 关键字，全部小写；多关键字用 | 分隔配合 REGEXP）
$spiderDefs = [
    ['key' => 'baidu',  'name' => '百度蜘蛛', 'icon' => 'ti-brand-baidu',  'color' => '#2932e1', 'pattern' => 'baiduspider'],
    ['key' => 'google', 'name' => '谷歌蜘蛛', 'icon' => 'ti-brand-google', 'color' => '#4285f4', 'pattern' => 'googlebot'],
    ['key' => 'bing',   'name' => '必应蜘蛛', 'icon' => 'ti-brand-bing',   'color' => '#0f766e', 'pattern' => 'bingbot|msnbot'],
    ['key' => 'sogou',  'name' => '搜狗蜘蛛', 'icon' => 'ti-world',        'color' => '#ff6a00', 'pattern' => 'sogou'],
    ['key' => 'haosou', 'name' => '360 蜘蛛', 'icon' => 'ti-shield',       'color' => '#00b358', 'pattern' => '360spider|haosouspider'],
    ['key' => 'yisou',  'name' => '神马蜘蛛', 'icon' => 'ti-horse',        'color' => '#f59e0b', 'pattern' => 'yisouspider'],
    ['key' => 'byte',   'name' => '字节蜘蛛', 'icon' => 'ti-robot',        'color' => '#ef4444', 'pattern' => 'bytespider'],
];
$waSpiderAllPattern    = 'bot|crawl|slurp|spider';
$waSpiderKnownPattern  = implode('|', array_map(function ($d) { return $d['pattern']; }, $spiderDefs));

// 将 UA 归属到蜘蛛名称（用于明细表展示）
$waSpiderName = function ($ua) use ($spiderDefs) {
    $lower = strtolower((string) $ua);
    foreach ($spiderDefs as $def) {
        foreach (explode('|', $def['pattern']) as $p) {
            if ($p !== '' && strpos($lower, $p) !== false) {
                return $def['name'];
            }
        }
    }
    return '其他蜘蛛';
};

// 环比：返回 ['dir' => up/down/flat, 'pct' => 绝对百分比|null]
$waTrend = function ($cur, $prev) {
    $cur = (float) $cur;
    $prev = (float) $prev;
    if ($prev <= 0) {
        return ['dir' => $cur > 0 ? 'up' : 'flat', 'pct' => null];
    }
    $pct = round(($cur - $prev) / $prev * 100, 1);
    if ($pct == 0) return ['dir' => 'flat', 'pct' => 0.0];
    return ['dir' => $pct > 0 ? 'up' : 'down', 'pct' => abs($pct)];
};

// 默认值（查询失败时兜底）
$waMetrics  = ['pv' => 0, 'uv' => 0, 'spider' => 0, 'pages' => 0, 'avg' => 0.0];
$waPrev     = ['pv' => 0, 'uv' => 0, 'spider' => 0, 'pages' => 0, 'avg' => 0.0];
$waSeries   = [];
$waXLabels  = [];   // ['pos' => 百分比, 'text' => 文案]
$waYLabels  = [];   // ['pos' => 百分比, 'text' => 文案]
$waSvg      = ['grid' => [], 'area' => '', 'linePv' => '', 'lineUv' => '', 'dotsPv' => [], 'dotsUv' => []];
$waYMax     = 0;
$waSourceTop = [];
$waPageTop   = [];
$spiderCounts = array_fill_keys(array_map(function ($d) { return $d['key']; }, $spiderDefs), 0);
$spiderOther  = 0;
$spiderTotal  = 0;
$spiderRecent = [];

try {
    // 区间与上一周期指标
    $curRow = $db->fetch(
        "SELECT COALESCE(SUM(page_views),0) AS pv, COALESCE(SUM(unique_visitors),0) AS uv
         FROM site_visits WHERE visit_date BETWEEN ? AND ?",
        [$statsStart, $statsEnd]
    ) ?: [];
    $prevRow = $db->fetch(
        "SELECT COALESCE(SUM(page_views),0) AS pv, COALESCE(SUM(unique_visitors),0) AS uv
         FROM site_visits WHERE visit_date BETWEEN ? AND ?",
        [$statsPrevStart, $statsPrevEnd]
    ) ?: [];

    $curAgg = $db->fetch(
        "SELECT COUNT(*) AS hits,
                COUNT(DISTINCT page_url) AS pages,
                SUM(LOWER(user_agent) REGEXP ?) AS spider
         FROM visitor_logs WHERE visit_date BETWEEN ? AND ?",
        [$waSpiderAllPattern, $statsStart, $statsEnd]
    ) ?: [];
    $prevAgg = $db->fetch(
        "SELECT COUNT(DISTINCT page_url) AS pages,
                SUM(LOWER(user_agent) REGEXP ?) AS spider
         FROM visitor_logs WHERE visit_date BETWEEN ? AND ?",
        [$waSpiderAllPattern, $statsPrevStart, $statsPrevEnd]
    ) ?: [];

    $waMetrics['pv']     = (int) ($curRow['pv'] ?? 0);
    $waMetrics['uv']     = (int) ($curRow['uv'] ?? 0);
    $waMetrics['spider'] = (int) ($curAgg['spider'] ?? 0);
    $waMetrics['pages']  = (int) ($curAgg['pages'] ?? 0);
    $waMetrics['avg']    = $waMetrics['uv'] > 0 ? round($waMetrics['pv'] / $waMetrics['uv'], 1) : 0.0;

    $waPrev['pv']     = (int) ($prevRow['pv'] ?? 0);
    $waPrev['uv']     = (int) ($prevRow['uv'] ?? 0);
    $waPrev['spider'] = (int) ($prevAgg['spider'] ?? 0);
    $waPrev['pages']  = (int) ($prevAgg['pages'] ?? 0);
    $waPrev['avg']    = $waPrev['uv'] > 0 ? round($waPrev['pv'] / $waPrev['uv'], 1) : 0.0;

    // 趋势序列：今天/昨天按小时，其余按天
    if ($statsRangeKey === 'today' || $statsRangeKey === 'yesterday') {
        $hourMap = [];
        $hourRows = $db->fetchAll(
            "SELECT HOUR(visit_time) AS h, COUNT(*) AS pv, COUNT(DISTINCT ip_hash) AS uv
             FROM visitor_logs WHERE visit_date = ? GROUP BY h",
            [$statsStart]
        ) ?: [];
        foreach ($hourRows as $r) { $hourMap[(int) $r['h']] = $r; }
        for ($h = 0; $h < 24; $h++) {
            $waSeries[] = [
                'label' => sprintf('%02d:00', $h),
                'pv'    => (int) ($hourMap[$h]['pv'] ?? 0),
                'uv'    => (int) ($hourMap[$h]['uv'] ?? 0),
            ];
        }
    } else {
        $dayMap = [];
        $dayRows = $db->fetchAll(
            "SELECT visit_date, page_views AS pv, unique_visitors AS uv
             FROM site_visits WHERE visit_date BETWEEN ? AND ?",
            [$statsStart, $statsEnd]
        ) ?: [];
        foreach ($dayRows as $r) { $dayMap[date('Y-m-d', strtotime($r['visit_date']))] = $r; }
        for ($ts = $statsStartTs; $ts <= $statsEndTs; $ts += 86400) {
            $d = date('Y-m-d', $ts);
            $waSeries[] = [
                'label' => date('n/j', $ts),
                'pv'    => (int) ($dayMap[$d]['pv'] ?? 0),
                'uv'    => (int) ($dayMap[$d]['uv'] ?? 0),
            ];
        }
    }

    // 生成 SVG 几何（viewBox 0 0 1000 220，上下各留 10px）
    $waSvgW = 1000; $waSvgH = 220; $waPad = 10;
    $waMaxVal = 0;
    foreach ($waSeries as $p) { $waMaxVal = max($waMaxVal, $p['pv'], $p['uv']); }
    $waStep = pow(10, floor(log10(max(1, $waMaxVal))));
    $waYMax = ceil((max(1, $waMaxVal) * 1.15) / $waStep) * $waStep;
    $waYMax = max(4, (int) $waYMax);

    $waY = function ($v) use ($waYMax, $waSvgH, $waPad) {
        return $waSvgH - $waPad - ($waYMax > 0 ? ($v / $waYMax) : 0) * ($waSvgH - 2 * $waPad);
    };
    $waX = function ($i, $n) use ($waSvgW) {
        return $n > 1 ? $i / ($n - 1) * $waSvgW : $waSvgW / 2;
    };

    $n = count($waSeries);
    // 网格线与 y 轴标签（4 等分）
    for ($g = 0; $g <= 4; $g++) {
        $v = $waYMax * $g / 4;
        $y = $waY($v);
        $waSvg['grid'][] = ['y' => round($y, 1), 'bottom' => $g === 0];
        $waYLabels[] = ['pos' => round($y / $waSvgH * 100, 2), 'text' => (string) round($v)];
    }

    if ($n > 0) {
        $ptsPv = []; $ptsUv = [];
        foreach ($waSeries as $i => $p) {
            $ptsPv[] = round($waX($i, $n), 1) . ',' . round($waY($p['pv']), 1);
            $ptsUv[] = round($waX($i, $n), 1) . ',' . round($waY($p['uv']), 1);
        }
        $waSvg['linePv'] = implode(' ', $ptsPv);
        $waSvg['lineUv'] = implode(' ', $ptsUv);
        $waSvg['area']   = '0,' . $waY(0) . ' ' . implode(' ', $ptsPv) . ' ' . $waSvgW . ',' . $waY(0);
        foreach ($waSeries as $i => $p) {
            $waSvg['dotsPv'][] = ['x' => round($waX($i, $n) / $waSvgW * 100, 2), 'y' => round($waY($p['pv']) / $waSvgH * 100, 2)];
            $waSvg['dotsUv'][] = ['x' => round($waX($i, $n) / $waSvgW * 100, 2), 'y' => round($waY($p['uv']) / $waSvgH * 100, 2)];
        }
    }

    // x 轴刻度标签（最多 8 个，两端必显示）
    if ($n > 0) {
        $xEvery = max(1, (int) ceil($n / 6));
        $waShownX = [];
        foreach ($waSeries as $i => $p) {
            if ($i % $xEvery === 0 || $i === $n - 1) { $waShownX[$i] = $p['label']; }
        }
        $xPos = function ($i) use ($n) {
            return $n > 1 ? $i / ($n - 1) * 100 : 50;
        };
        foreach ($waShownX as $i => $text) {
            $align = 'center';
            $pos   = $xPos($i);
            if ($pos <= 0) { $align = 'left'; }
            elseif ($pos >= 100) { $align = 'right'; }
            $waXLabels[] = ['pos' => round($pos, 2), 'text' => (string) $text, 'align' => $align];
        }
    }

    // 访问来源 TOP（referrer 取 host）
    $waSourceTop = $db->fetchAll(
        "SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(referrer, '/', 3), '/', -1) AS src, COUNT(*) AS cnt
         FROM visitor_logs WHERE visit_date BETWEEN ? AND ?
         GROUP BY src ORDER BY cnt DESC LIMIT 8",
        [$statsStart, $statsEnd]
    ) ?: [];

    // 受访页面 TOP
    $waPageTop = $db->fetchAll(
        "SELECT page_url, COUNT(*) AS cnt
         FROM visitor_logs WHERE visit_date BETWEEN ? AND ?
         GROUP BY page_url ORDER BY cnt DESC LIMIT 8",
        [$statsStart, $statsEnd]
    ) ?: [];

    // 蜘蛛分布（一条 SQL 汇总各蜘蛛计数）
    $spiderCase = [];
    foreach ($spiderDefs as $def) {
        $spiderCase[] = "SUM(LOWER(user_agent) REGEXP '" . $def['pattern'] . "') AS " . $def['key'];
    }
    $spiderAgg = $db->fetch(
        "SELECT " . implode(', ', $spiderCase) . ",
                SUM(LOWER(user_agent) REGEXP ?) AS all_cnt,
                SUM(LOWER(user_agent) REGEXP ? AND LOWER(user_agent) NOT REGEXP ?) AS other_cnt
         FROM visitor_logs WHERE visit_date BETWEEN ? AND ?",
        [$waSpiderAllPattern, $waSpiderAllPattern, $waSpiderKnownPattern, $statsStart, $statsEnd]
    ) ?: [];
    foreach ($spiderDefs as $def) {
        $spiderCounts[$def['key']] = (int) ($spiderAgg[$def['key']] ?? 0);
    }
    $spiderOther = (int) ($spiderAgg['other_cnt'] ?? 0);
    $spiderTotal = (int) ($spiderAgg['all_cnt'] ?? 0);

    // 最近蜘蛛抓取记录
    $spiderRecent = $db->fetchAll(
        "SELECT visit_time, ip_address, page_url, user_agent
         FROM visitor_logs
         WHERE visit_date BETWEEN ? AND ? AND LOWER(user_agent) REGEXP ?
         ORDER BY visit_time DESC LIMIT 10",
        [$statsStart, $statsEnd, $waSpiderAllPattern]
    ) ?: [];
} catch (Throwable $e) {
    // 统计失败时保持默认空数据，仪表盘其余部分不受影响
}

// 传递给图表脚本的点数据
$waChartJson = json_encode(
    ['points' => $waSeries, 'yMax' => $waYMax],
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);

// ffmpeg 状态检测（统一放在仪表盘），用于提示视频相关能力
// 状态：
// - embedded/ok: 已检测到可执行的内置或系统 ffmpeg
// - no_exec: 检测到 ffmpeg，但 PHP 的实际执行函数 exec 被禁用
// - missing: 未检测到可用 ffmpeg，或无法确认
$ffmpegStatus    = 'missing';
$ffmpegPath      = '';
$ffmpegHintPath  = ROOT_PATH . '/bin/ffmpeg';

$disableFunctions = ini_get('disable_functions');
$disableFunctions = is_string($disableFunctions) ? $disableFunctions : '';
$shellDisabled    = stripos($disableFunctions, 'shell_exec') !== false;
$execDisabled     = stripos($disableFunctions, 'exec') !== false;
$canUseShell      = function_exists('shell_exec') && !$shellDisabled;
$canUseExec       = function_exists('exec') && !$execDisabled;

// 更详细的 ffmpeg 能力检测信息，用于「查看详情」弹窗
$ffmpegDiagnostics = [
    'can_use_shell' => $canUseShell,
    'can_use_exec'  => $canUseExec,
    'binary_found'  => false,
    'binary_path'   => '',
    'has_libx264'   => null, // true=有，false=无，null=未知
    'has_aac'       => null,
    'version_line'  => '',
];

$ffmpegPath = withu_binary_path('ffmpeg');
$embeddedFfmpegRoot = realpath(ROOT_PATH . '/bin/ffmpeg');
$resolvedFfmpegPath = $ffmpegPath !== '' ? realpath($ffmpegPath) : false;
$embeddedFfmpeg = $embeddedFfmpegRoot !== false
    && $resolvedFfmpegPath !== false
    && strpos($resolvedFfmpegPath, $embeddedFfmpegRoot . DIRECTORY_SEPARATOR) === 0;
if ($ffmpegPath !== '') {
    $ffmpegStatus = $canUseExec ? ($embeddedFfmpeg ? 'embedded' : 'ok') : 'no_exec';
} elseif ($canUseShell) {
    $lookupCommand = PHP_OS_FAMILY === 'Windows' ? 'where ffmpeg 2>NUL' : 'command -v ffmpeg 2>/dev/null';
    $whichOutput = @shell_exec($lookupCommand);
    if (is_string($whichOutput)) {
        $whichOutput = trim($whichOutput);
    }
    if (!empty($whichOutput)) {
        $ffmpegStatus = $canUseExec ? 'ok' : 'no_exec';
        $ffmpegPath   = $whichOutput;
    } else {
        $ffmpegStatus = 'missing';
    }
} else {
    if (is_executable('/usr/bin/ffmpeg')) {
        $ffmpegStatus = $canUseExec ? 'ok' : 'no_exec';
        $ffmpegPath   = '/usr/bin/ffmpeg';
    } elseif (is_executable('/usr/local/bin/ffmpeg')) {
        $ffmpegStatus = $canUseExec ? 'ok' : 'no_exec';
        $ffmpegPath   = '/usr/local/bin/ffmpeg';
    } else {
        $ffmpegStatus = 'missing';
    }
}

// 在已知 ffmpeg 可执行文件路径且 shell_exec 可用时，进一步检测版本与编码器支持情况
$ffmpegDiagnostics['binary_path']  = $ffmpegPath;
$ffmpegDiagnostics['binary_found'] = !empty($ffmpegPath);

if ($canUseShell && $ffmpegDiagnostics['binary_found']) {
    $versionOutput = @shell_exec(escapeshellarg($ffmpegPath) . ' -version 2>&1');
    if (is_string($versionOutput)) {
        $versionOutput = trim($versionOutput);
        if ($versionOutput !== '') {
            $lines = preg_split('/\r\n|\r|\n/', $versionOutput);
            if (!empty($lines[0])) {
                $ffmpegDiagnostics['version_line'] = trim($lines[0]);
            }
        }
    }

    $codecsOutput = @shell_exec(escapeshellarg($ffmpegPath) . ' -codecs 2>&1');
    if (is_string($codecsOutput) && $codecsOutput !== '') {
        $ffmpegDiagnostics['has_libx264'] = stripos($codecsOutput, 'libx264') !== false;
        $ffmpegDiagnostics['has_aac']     = preg_match('/\baac\b/i', $codecsOutput) === 1;
    }
}

// 图片概览：采样最近部分相册图片估算平均体积
$albumImageStats = [
    'count'       => 0,
    'total_bytes' => 0,
    'avg_bytes'   => 0,
];

try {
    $rows = $db->fetchAll("
        SELECT image_path
        FROM album_images
        ORDER BY id DESC
        LIMIT 200
    ");
    $totalBytes = 0;
    $count      = 0;
    foreach ($rows as $row) {
        $path = $row['image_path'] ?? '';
        if (!$path) continue;
        $abs = rtrim(UPLOAD_DIR, '/\\') . '/' . ltrim($path, '/');
        if (!is_file($abs)) continue;
        $sz = filesize($abs);
        if ($sz === false) continue;
        $totalBytes += $sz;
        $count++;
    }
    if ($count > 0) {
        $albumImageStats['count']       = $count;
        $albumImageStats['total_bytes'] = $totalBytes;
        $albumImageStats['avg_bytes']   = (int) floor($totalBytes / $count);
    }
} catch (Throwable $e) {
    // 忽略统计失败，仪表盘保持可用
}

$adminPage = 'dashboard';

include __DIR__ . '/header.php';
?>

    <section class="admin-page-title">
        <h1>欢迎回来，<?php echo e($currentUser['nickname'] ?? $currentUser['username']); ?></h1>
        <p>快速了解你们的小站运行情况</p>
    </section>

    <?php
    // 根据 ffmpeg 状态选择不同的提示样式与摘要文案
    $ffmpegCardBg     = 'rgba(59,130,246,0.04)';
    $ffmpegCardBorder = 'rgba(59,130,246,0.45)';
    $ffmpegCardColor  = '#1d4ed8';
    $ffmpegIcon       = 'ti ti-info-circle';
    $ffmpegSummaryText = '';

    if (in_array($ffmpegStatus, ['embedded', 'ok'], true)) {
        $ffmpegCardBg     = 'rgba(34,197,94,0.05)';
        $ffmpegCardBorder = 'rgba(34,197,94,0.45)';
        $ffmpegCardColor  = '#15803d';
        $ffmpegIcon       = 'ti ti-circle-check';

        if ($embeddedFfmpeg) {
            $ffmpegSummaryText = '已启用程序内置 FFmpeg，支持 H.264 + AAC 视频转码和自动封面生成。';
        } elseif ($ffmpegDiagnostics['has_libx264'] === true && $ffmpegDiagnostics['has_aac'] === true) {
            $ffmpegSummaryText = '已检测到可用的 FFmpeg，支持 H.264 + AAC 视频转码。';
        } elseif ($ffmpegDiagnostics['has_libx264'] === false) {
            $ffmpegSummaryText = '已检测到 ffmpeg，但当前环境不支持 H.264（libx264）视频转码，仅封面截取等基础能力可用。';
        } else {
            $ffmpegSummaryText = '已检测到可用的 ffmpeg，可用于视频相关能力，编码器支持情况建议查看详情。';
        }
    } elseif ($ffmpegStatus === 'no_exec') {
        $ffmpegCardBg     = 'rgba(245,158,11,0.05)';
        $ffmpegCardBorder = 'rgba(245,158,11,0.55)';
        $ffmpegCardColor  = '#b45309';
        $ffmpegIcon       = 'ti ti-alert-triangle';
        $ffmpegSummaryText = '检测到 ffmpeg，但 PHP 当前无法调用 exec，自动转码与封面生成能力受限。';
    } else {
        $ffmpegSummaryText = '当前未检测到可用的 ffmpeg 命令，视频转码与自动封面生成能力将被关闭。';
    }
    ?>
    <div style="margin-bottom:1.5rem;padding:0.85rem 1rem;background:<?php echo $ffmpegCardBg; ?>;border:1px solid <?php echo $ffmpegCardBorder; ?>;border-radius:13.14px;display:flex;align-items:flex-start;justify-content:space-between;gap:0.75rem;color:<?php echo $ffmpegCardColor; ?>;font-size:0.86rem;line-height:1.5;">
        <div style="display:flex;align-items:flex-start;gap:0.55rem;flex:1 1 auto;">
            <i class="<?php echo $ffmpegIcon; ?>" style="margin-top:2px;font-size:16px;"></i>
            <div>
                <div style="font-weight:600;margin-bottom:2px;">视频能力与内置 FFmpeg</div>
                <div style="font-size:0.8rem;"><?php echo e($ffmpegSummaryText); ?></div>
            </div>
        </div>
        <div style="flex:0 0 auto;display:flex;align-items:center;">
            <button type="button"
                    class="btn btn-secondary"
                    style="padding:0.25rem 0.7rem;font-size:0.78rem;white-space:nowrap;"
                    data-ffmpeg-details="open">
                查看详情
            </button>
        </div>
    </div>

    <div class="admin-modal-backdrop" id="ffmpegDetailBackdrop">
        <div class="admin-modal">
            <div class="admin-modal-header">内置 FFmpeg 详细信息</div>
            <div class="admin-modal-body">
                <div style="font-size:0.85rem;line-height:1.5;">
                    <div>
                        <strong>检测状态：</strong>
                        <?php
                        if ($embeddedFfmpeg) {
                            echo '已启用程序内置 FFmpeg';
                        } elseif ($ffmpegStatus === 'ok') {
                            echo '已检测到可用的 FFmpeg 命令';
                        } elseif ($ffmpegStatus === 'no_exec') {
                            echo '检测到 ffmpeg，但 PHP 禁用了 exec';
                        } else {
                            echo '未检测到可用的 ffmpeg 命令';
                        }
                        ?>
                    </div>
                    <div style="margin-top:0.25rem;">
                        <strong>shell_exec：</strong><?php echo $ffmpegDiagnostics['can_use_shell'] ? '可用' : '不可用（被禁用或不存在）'; ?>
                    </div>
                    <div style="margin-top:0.25rem;">
                        <strong>exec：</strong><?php echo $ffmpegDiagnostics['can_use_exec'] ? '可用（实际转码调用）' : '不可用（被禁用或不存在）'; ?>
                    </div>
                    <div style="margin-top:0.25rem;">
                        <strong>可执行路径：</strong>
                        <?php echo $ffmpegDiagnostics['binary_found'] ? '<code>' . e($ffmpegDiagnostics['binary_path']) . '</code>' : '未找到'; ?>
                    </div>
                    <?php if (!empty($ffmpegDiagnostics['version_line'])): ?>
                        <div style="margin-top:0.25rem;">
                            <strong>版本信息：</strong>
                            <span style="font-size:0.8rem;"><code><?php echo e($ffmpegDiagnostics['version_line']); ?></code></span>
                        </div>
                    <?php endif; ?>

                    <div style="margin-top:0.4rem;">
                        <strong>编码器支持：</strong>
                        <ul style="margin:0.25rem 0 0 1rem;padding:0;font-size:0.8rem;list-style:disc;">
                            <li>H.264（libx264）：
                                <?php
                                if ($ffmpegDiagnostics['has_libx264'] === true) {
                                    echo '<span style="color:#15803d;">已检测到</span>';
                                } elseif ($ffmpegDiagnostics['has_libx264'] === false) {
                                    echo '<span style="color:#b91c1c;">未检测到，视频转码功能将无法正常工作</span>';
                                } else {
                                    echo '无法确认（可能检测函数被禁用或检测失败）';
                                }
                                ?>
                            </li>
                            <li>AAC 音频：
                                <?php
                                if ($ffmpegDiagnostics['has_aac'] === true) {
                                    echo '<span style="color:#15803d;">已检测到</span>';
                                } elseif ($ffmpegDiagnostics['has_aac'] === false) {
                                    echo '<span style="color:#b91c1c;">未检测到，可能导致部分音频无法转码</span>';
                                } else {
                                    echo '无法确认';
                                }
                                ?>
                            </li>
                        </ul>
                    </div>

                    <div style="margin-top:0.5rem;font-size:0.78rem;color:var(--text-light);">
                        提示：视频上传时的转码命令使用 <code>ffmpeg -c:v libx264 -c:a aac</code>，若缺少 libx264 或 AAC 编码器，将导致转码失败但封面截取仍然可用。
                    </div>
                </div>
            </div>
            <div class="admin-modal-actions">
                <button type="button" class="btn btn-secondary" data-ffmpeg-details="close">关闭</button>
            </div>
        </div>
    </div>

    <!-- ============ 访问详细数据弹窗（域名分布 / 最近访问） ============ -->
    <div class="admin-modal-backdrop" id="statsDetailBackdrop">
        <div class="admin-modal" style="max-width:480px;">
            <div class="admin-modal-header">访问详细数据</div>
            <div class="admin-modal-body" style="max-height:72vh;overflow:auto;">
                <div class="wa-subhead">域名分布 <span style="font-weight:400;color:var(--v3-text-3);">今日 <?php echo $today; ?></span></div>
                <?php if (!empty($domainStats)): ?>
                    <ul class="wa-plain-list">
                        <?php foreach ($domainStats as $ds): ?>
                        <li style="display:flex;justify-content:space-between;align-items:center;">
                            <span style="font-family:monospace;font-size:0.82rem;"><?php echo e($ds['domain'] ?: '(未知)'); ?></span>
                            <strong><?php echo (int)$ds['cnt']; ?> 次</strong>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p style="font-size:0.85rem;color:var(--v3-text-3);">暂无今日访问数据。</p>
                <?php endif; ?>

                <div class="wa-subhead" style="margin-top:1.25rem;">最近访问 <span style="font-weight:400;color:var(--v3-text-3);">最近 10 条记录</span></div>
                <?php if (!empty($recentVisitors)): ?>
                    <ul class="wa-plain-list">
                        <?php foreach ($recentVisitors as $rv): ?>
                        <li>
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:0.5rem;">
                                <div style="min-width:0;">
                                    <div style="font-family:monospace;font-size:0.78rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?php echo e($rv['ip_address']); ?>">
                                        <?php echo e($rv['ip_address']); ?>
                                    </div>
                                    <div style="color:var(--v3-text-3);font-size:0.72rem;margin-top:1px;">
                                        <?php echo e($rv['domain'] ?: ''); ?> · <?php echo e($rv['ua_browser'] ?: '未知浏览器'); ?> · <?php echo e($rv['ua_os'] ?: '未知系统'); ?>
                                    </div>
                                </div>
                                <span style="color:var(--v3-text-3);font-size:0.72rem;white-space:nowrap;"><?php echo e(date('H:i:s', strtotime($rv['visit_time']))); ?></span>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p style="font-size:0.85rem;color:var(--v3-text-3);">暂无访问记录。</p>
                <?php endif; ?>
            </div>
            <div class="admin-modal-actions">
                <button type="button" class="btn btn-secondary" data-stats-details="close">关闭</button>
            </div>
        </div>
    </div>
    <script>
    (function () {
        function initStatsDetailModal() {
            var backdrop = document.getElementById('statsDetailBackdrop');
            if (!backdrop) return;
            document.querySelectorAll('[data-stats-details="open"]').forEach(function (btn) {
                btn.addEventListener('click', function () { backdrop.classList.add('active'); });
            });
            var closeBtn = backdrop.querySelector('[data-stats-details="close"]');
            if (closeBtn) closeBtn.addEventListener('click', function () { backdrop.classList.remove('active'); });
            backdrop.addEventListener('click', function (e) {
                if (e.target === backdrop) backdrop.classList.remove('active');
            });
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initStatsDetailModal);
        } else {
            initStatsDetailModal();
        }
    })();
    </script>

    <section class="admin-dashboard-stats" style="margin-bottom: 1.5rem;">
        <div class="admin-dashboard-stat-card">
            <div class="admin-card-header">
                <div>
                    <div class="admin-card-title">
                        文章 · 日记
                        <button type="button" class="admin-help-toggle" title="查看说明" aria-label="查看说明" aria-expanded="false"><i class="ti ti-info-circle"></i></button>
                    </div>
                </div>
                <a href="/admin/articles.php" class="btn btn-outline">
                    <i class="ti ti-pencil"></i><span>去管理</span>
                </a>
            </div>
            <div class="admin-card-help">
                <div class="admin-card-subtitle">记录的点点滴滴</div>
            </div>
            <div>
                <div class="admin-stat-value"><?php echo $articleCount; ?></div>
                <div class="admin-stat-label">篇内容</div>
            </div>
        </div>

        <div class="admin-dashboard-stat-card">
            <div class="admin-card-header">
                <div>
                    <div class="admin-card-title">
                        相册
                        <button type="button" class="admin-help-toggle" title="查看说明" aria-label="查看说明" aria-expanded="false"><i class="ti ti-info-circle"></i></button>
                    </div>
                </div>
                <a href="/admin/albums.php" class="btn btn-outline">
                    <i class="ti ti-photo"></i><span>去查看</span>
                </a>
            </div>
            <div class="admin-card-help">
                <div class="admin-card-subtitle">保存的照片与回忆</div>
            </div>
            <div>
                <div class="admin-stat-value"><?php echo $albumCount; ?></div>
                <div class="admin-stat-label">个相册</div>
            </div>
        </div>

        <div class="admin-dashboard-stat-card">
            <div class="admin-card-header">
                <div>
                    <div class="admin-card-title">
                        纪念事件
                        <button type="button" class="admin-help-toggle" title="查看说明" aria-label="查看说明" aria-expanded="false"><i class="ti ti-info-circle"></i></button>
                    </div>
                </div>
                <a href="/admin/events.php" class="btn btn-outline">
                    <i class="ti ti-calendar-event"></i><span>去添加</span>
                </a>
            </div>
            <div class="admin-card-help">
                <div class="admin-card-subtitle">重要的时刻</div>
            </div>
            <div>
                <div class="admin-stat-value"><?php echo $eventCount; ?></div>
                <div class="admin-stat-label">个事件</div>
            </div>
        </div>

        <div class="admin-dashboard-stat-card">
            <div class="admin-card-header">
                <div>
                    <div class="admin-card-title">
                        留言
                        <button type="button" class="admin-help-toggle" title="查看说明" aria-label="查看说明" aria-expanded="false"><i class="ti ti-info-circle"></i></button>
                    </div>
                </div>
                <a href="/admin/messages.php" class="btn btn-outline">
                    <i class="ti ti-messages"></i><span>去查看</span>
                </a>
            </div>
            <div class="admin-card-help">
                <div class="admin-card-subtitle">来自你们或朋友的话</div>
            </div>
            <div>
                <div class="admin-stat-value"><?php echo $messageCount; ?></div>
                <div class="admin-stat-label">条留言</div>
            </div>
        </div>

        <div class="admin-dashboard-stat-card">
            <div class="admin-card-header">
                <div>
                    <div class="admin-card-title">
                        今日访问
                        <button type="button" class="admin-help-toggle" title="查看说明" aria-label="查看说明" aria-expanded="false"><i class="ti ti-info-circle"></i></button>
                    </div>
                </div>
            </div>
            <div class="admin-card-help">
                <div class="admin-card-subtitle"><?php echo $today; ?></div>
            </div>
            <div>
                <div class="admin-stat-value"><?php echo $todayViews; ?></div>
                <div class="admin-stat-label">访问次数 · <?php echo $todayVisitors; ?> 访客</div>
            </div>
        </div>

        <div class="admin-dashboard-stat-card">
            <div class="admin-card-header">
                <div>
                    <div class="admin-card-title">
                        累计访问
                        <button type="button" class="admin-help-toggle" title="查看说明" aria-label="查看说明" aria-expanded="false"><i class="ti ti-info-circle"></i></button>
                    </div>
                </div>
            </div>
            <div class="admin-card-help">
                <div class="admin-card-subtitle">从建站至今</div>
            </div>
            <div>
                <div class="admin-stat-value"><?php echo $totalViews; ?></div>
                <div class="admin-stat-label">总访问 · <?php echo $totalVisitors; ?> 访客</div>
            </div>
        </div>
    </section>

    <?php
    // 当前范围说明文字（用于趋势图副标题）
    $waRangeText = $statsRangeDefs[$statsRangeKey];
    if ($statsRangeKey === 'today' || $statsRangeKey === 'yesterday') {
        $waRangeText .= '（' . $statsStart . '）';
    } else {
        $waRangeText .= '（' . $statsStart . ' ~ ' . $statsEnd . '）';
    }

    $waTiles = [
        ['name' => '浏览量',   'key' => 'pv',     'value' => number_format($waMetrics['pv'])],
        ['name' => '独立访客', 'key' => 'uv',     'value' => number_format($waMetrics['uv'])],
        ['name' => '蜘蛛访问', 'key' => 'spider', 'value' => number_format($waMetrics['spider'])],
        ['name' => '受访页面', 'key' => 'pages',  'value' => number_format($waMetrics['pages'])],
        ['name' => '人均浏览', 'key' => 'avg',    'value' => $waMetrics['avg']],
    ];
    $waSpiderMax = 1;
    foreach ($spiderCounts as $c) { $waSpiderMax = max($waSpiderMax, (int) $c); }
    ?>

    <section class="admin-dashboard-panels" style="margin-bottom: 1.5rem;grid-template-columns: 1fr;">
        <div class="admin-dashboard-panel">
            <div class="admin-card-header">
                <div>
                    <div class="admin-card-title">
                        快捷设置
                        <button type="button" class="admin-help-toggle" title="查看说明" aria-label="查看说明" aria-expanded="false"><i class="ti ti-info-circle"></i></button>
                    </div>
                </div>
            </div>
            <div class="admin-card-help">
                <div class="admin-card-subtitle">常用设置与个人信息入口</div>
            </div>
            <ul class="quick-list">
                <li>
                    <span class="quick-icon" style="background:rgba(79,168,224,0.12);color:#2b7fb8;"><i class="ti ti-settings"></i></span>
                    <div class="quick-text">
                        <div class="quick-title">网站设置</div>
                        <div class="quick-desc">修改站点标题、描述、首页大图、备案信息</div>
                    </div>
                    <a href="/admin/settings.php" class="btn btn-secondary btn-sm quick-go">
                        <span>进入</span><i class="ti ti-chevron-right"></i>
                    </a>
                </li>
                <li>
                    <span class="quick-icon" style="background:rgba(63,189,139,0.12);color:#1f8f66;"><i class="ti ti-user"></i></span>
                    <div class="quick-text">
                        <div class="quick-title">个人资料</div>
                        <div class="quick-desc">修改昵称、头像、QQ 头像来源与登录密码</div>
                    </div>
                    <a href="/admin/profile.php" class="btn btn-secondary btn-sm quick-go">
                        <span>进入</span><i class="ti ti-chevron-right"></i>
                    </a>
                </li>
                <li>
                    <span class="quick-icon" style="background:rgba(167,139,250,0.14);color:#7c5cd6;"><i class="ti ti-messages"></i></span>
                    <div class="quick-text">
                        <div class="quick-title">留言管理</div>
                        <div class="quick-desc">查看和删除前台的留言内容</div>
                    </div>
                    <a href="/admin/messages.php" class="btn btn-secondary btn-sm quick-go">
                        <span>进入</span><i class="ti ti-chevron-right"></i>
                    </a>
                </li>
                <li>
                    <span class="quick-icon" style="background:rgba(244,63,94,0.10);color:#c2335a;"><i class="ti ti-shield"></i></span>
                    <div class="quick-text">
                        <div class="quick-title">IP 黑名单</div>
                        <div class="quick-desc">统一管理被禁止评论与留言的 IP</div>
                    </div>
                    <a href="/admin/comment_ip_blacklist.php" class="btn btn-secondary btn-sm quick-go">
                        <span>进入</span><i class="ti ti-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </div>
    </section>

    <!-- ============ 百度统计风格 · 流量统计 ============ -->
    <section class="admin-card wa-section" id="waAnalytics">
        <div class="wa-toolbar">
            <div class="admin-card-title">
                <i class="ti ti-chart-area-line"></i>流量统计
                <button type="button" class="admin-help-toggle" title="查看说明" aria-label="查看说明" aria-expanded="false"><i class="ti ti-info-circle"></i></button>
            </div>
            <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;justify-content:flex-end;">
                <button type="button" class="btn btn-secondary btn-sm" data-stats-details="open" style="white-space:nowrap;">
                    <i class="ti ti-list-details"></i><span>详细数据</span>
                </button>
                <div class="wa-range">
                    <?php foreach ($statsRangeDefs as $waKey => $waLabel): ?>
                        <a class="wa-range-item <?php echo $statsRangeKey === $waKey ? 'active' : ''; ?>" href="?range=<?php echo $waKey; ?>"><?php echo $waLabel; ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="admin-card-help">
            <div class="admin-card-subtitle">口径与百度统计概览一致：浏览量（PV）为页面打开次数，独立访客（UV）按访问者去重；趋势图在「今天 / 昨天」按小时展示、其余按天展示，指标下方为与上一等长周期的环比。</div>
        </div>

        <div class="wa-tiles">
            <?php foreach ($waTiles as $tile): ?>
                <?php $tr = $waTrend($waMetrics[$tile['key']], $waPrev[$tile['key']]); ?>
                <div class="wa-tile">
                    <div class="wa-tile-name"><?php echo $tile['name']; ?></div>
                    <div class="wa-tile-value"><?php echo $tile['value']; ?></div>
                    <div class="wa-tile-trend <?php echo $tr['dir']; ?>">
                        <?php if ($tr['dir'] === 'flat'): ?>
                            与上期持平
                        <?php elseif ($tr['pct'] === null): ?>
                            <i class="ti ti-sparkles"></i>上期无数据
                        <?php else: ?>
                            <i class="ti <?php echo $tr['dir'] === 'up' ? 'ti-caret-up-filled' : 'ti-caret-down-filled'; ?>"></i><?php echo $tr['pct']; ?>%
                        <?php endif; ?>
                        <?php if ($tr['pct'] !== null): ?>
                            <span class="wa-tile-prev">上期 <?php echo $tile['key'] === 'avg' ? $waPrev['avg'] : number_format($waPrev[$tile['key']]); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="wa-chart-card">
            <div class="wa-chart-head">
                <div class="wa-chart-title">访问趋势<span class="wa-chart-sub"><?php echo $waRangeText; ?></span></div>
                <div class="wa-legend">
                    <span class="wa-legend-item"><span class="wa-legend-dot wa-dot-pv"></span>浏览量</span>
                    <span class="wa-legend-item"><span class="wa-legend-dot wa-dot-uv"></span>独立访客</span>
                </div>
            </div>
            <div class="wa-chart" id="waChart">
                <div class="wa-chart-ylayer">
                    <?php foreach ($waYLabels as $yl): ?>
                        <span style="top:<?php echo $yl['pos']; ?>%;"><?php echo $yl['text']; ?></span>
                    <?php endforeach; ?>
                </div>
                <div class="wa-chart-body" id="waChartBody">
                    <svg class="wa-chart-svg" viewBox="0 0 1000 220" preserveAspectRatio="none" aria-hidden="true">
                        <defs>
                            <linearGradient id="waAreaGrad" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="rgba(79,168,224,0.20)"/>
                                <stop offset="100%" stop-color="rgba(79,168,224,0.02)"/>
                            </linearGradient>
                        </defs>
                        <?php foreach ($waSvg['grid'] as $g): ?>
                            <line x1="0" y1="<?php echo $g['y']; ?>" x2="1000" y2="<?php echo $g['y']; ?>"
                                  stroke="<?php echo $g['bottom'] ? '#dfe7ee' : '#eef2f6'; ?>"
                                  stroke-width="1"
                                  <?php echo $g['bottom'] ? '' : 'stroke-dasharray="4 6"'; ?>
                                  vector-effect="non-scaling-stroke"/>
                        <?php endforeach; ?>
                        <?php if ($waSvg['area'] !== ''): ?>
                            <polygon points="<?php echo $waSvg['area']; ?>" fill="url(#waAreaGrad)"/>
                            <polyline points="<?php echo $waSvg['linePv']; ?>" fill="none" stroke="#4fa8e0" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>
                            <polyline points="<?php echo $waSvg['lineUv']; ?>" fill="none" stroke="#f26d9c" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>
                        <?php endif; ?>
                    </svg>
                    <div class="wa-guide" id="waGuide" hidden></div>
                    <div class="wa-hover-dot wa-hover-dot-pv" id="waDotPv" hidden></div>
                    <div class="wa-hover-dot wa-hover-dot-uv" id="waDotUv" hidden></div>
                    <div class="wa-tooltip" id="waTooltip" hidden>
                        <div class="wa-tooltip-label" id="waTipLabel"></div>
                        <div class="wa-tooltip-row"><span class="wa-legend-dot wa-dot-pv"></span>浏览量 <b id="waTipPv"></b></div>
                        <div class="wa-tooltip-row"><span class="wa-legend-dot wa-dot-uv"></span>独立访客 <b id="waTipUv"></b></div>
                    </div>
                </div>
                <div class="wa-chart-x">
                    <?php foreach ($waXLabels as $xl): ?>
                        <span class="wa-xlabel wa-xlabel-<?php echo $xl['align']; ?>" style="left:<?php echo $xl['pos']; ?>%;"><?php echo $xl['text']; ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="wa-tops">
            <div class="wa-top">
                <div class="wa-subhead">访问来源 TOP</div>
                <ul class="wa-rank">
                    <?php if (empty($waSourceTop)): ?>
                        <li class="wa-empty">当前范围暂无来源数据。</li>
                    <?php else: ?>
                        <?php
                        $waSrcMax = 1;
                        foreach ($waSourceTop as $r) { $waSrcMax = max($waSrcMax, (int) $r['cnt']); }
                        $waSrcSum = 0;
                        foreach ($waSourceTop as $r) { $waSrcSum += (int) $r['cnt']; }
                        ?>
                        <?php foreach ($waSourceTop as $r): ?>
                            <li>
                                <div class="wa-rank-row">
                                    <span class="wa-rank-name" title="<?php echo e($r['src']); ?>"><?php echo e($r['src'] !== '' ? $r['src'] : '直接访问'); ?></span>
                                    <span class="wa-rank-num"><?php echo number_format((int) $r['cnt']); ?><em><?php echo $waSrcSum > 0 ? round($r['cnt'] / $waSrcSum * 100) : 0; ?>%</em></span>
                                </div>
                                <div class="wa-rank-bar"><span style="width:<?php echo $r['cnt'] > 0 ? max(2.5, round($r['cnt'] / $waSrcMax * 100, 1)) : 0; ?>%;"></span></div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="wa-top">
                <div class="wa-subhead">受访页面 TOP</div>
                <ul class="wa-rank">
                    <?php if (empty($waPageTop)): ?>
                        <li class="wa-empty">当前范围暂无页面数据。</li>
                    <?php else: ?>
                        <?php
                        $waPageMax = 1;
                        foreach ($waPageTop as $r) { $waPageMax = max($waPageMax, (int) $r['cnt']); }
                        $waPageSum = 0;
                        foreach ($waPageTop as $r) { $waPageSum += (int) $r['cnt']; }
                        ?>
                        <?php foreach ($waPageTop as $r): ?>
                            <?php $waUrl = (string) $r['page_url']; ?>
                            <li>
                                <div class="wa-rank-row">
                                    <span class="wa-rank-name" title="<?php echo e($waUrl); ?>"><?php echo e(mb_strlen($waUrl) > 40 ? mb_substr($waUrl, 0, 40) . '…' : $waUrl); ?></span>
                                    <span class="wa-rank-num"><?php echo number_format((int) $r['cnt']); ?><em><?php echo $waPageSum > 0 ? round($r['cnt'] / $waPageSum * 100) : 0; ?>%</em></span>
                                </div>
                                <div class="wa-rank-bar"><span style="width:<?php echo $r['cnt'] > 0 ? max(2.5, round($r['cnt'] / $waPageMax * 100, 1)) : 0; ?>%;"></span></div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </section>

    <script id="waChartData" type="application/json"><?php echo $waChartJson; ?></script>
    <script>
    (function () {
        var dataEl = document.getElementById('waChartData');
        var body = document.getElementById('waChartBody');
        if (!dataEl || !body) return;
        var chart;
        try { chart = JSON.parse(dataEl.textContent || '{}'); } catch (err) { return; }
        var points = chart.points || [];
        var yMax = Math.max(1, chart.yMax || 1);
        var guide = document.getElementById('waGuide');
        var dotPv = document.getElementById('waDotPv');
        var dotUv = document.getElementById('waDotUv');
        var tip = document.getElementById('waTooltip');
        var tipLabel = document.getElementById('waTipLabel');
        var tipPv = document.getElementById('waTipPv');
        var tipUv = document.getElementById('waTipUv');
        if (!points.length || !guide || !dotPv || !dotUv || !tip) return;

        // 与 SVG 几何一致：viewBox 220 高，上下各留 10px
        function yPct(v) { return (1 - (v / yMax) * (200 / 220)) * 100; }
        function xPct(i) { return points.length > 1 ? (i / (points.length - 1)) * 100 : 50; }

        var active = -1;
        function show(i) {
            active = i;
            var p = points[i];
            var x = xPct(i);
            guide.hidden = false;
            guide.style.left = x + '%';
            dotPv.hidden = false; dotPv.style.left = x + '%'; dotPv.style.top = yPct(p.pv) + '%';
            dotUv.hidden = false; dotUv.style.left = x + '%'; dotUv.style.top = yPct(p.uv) + '%';
            tipLabel.textContent = p.label;
            tipPv.textContent = p.pv;
            tipUv.textContent = p.uv;
            tip.hidden = false;
            var tipW = tip.offsetWidth || 140;
            var leftPct = Math.min(96, Math.max(4, x));
            tip.style.left = leftPct + '%';
            tip.style.top = Math.max(yPct(p.pv), yPct(p.uv)) + '%';
            tip.style.transform = 'translate(' + (leftPct > 50 ? '-108%' : '8%') + ', -110%)';
        }
        function hide() {
            active = -1;
            guide.hidden = true; dotPv.hidden = true; dotUv.hidden = true; tip.hidden = true;
        }
        body.addEventListener('mousemove', function (ev) {
            var rect = body.getBoundingClientRect();
            if (!rect.width) return;
            var ratio = (ev.clientX - rect.left) / rect.width;
            var i = Math.round(ratio * (points.length - 1));
            i = Math.max(0, Math.min(points.length - 1, i));
            if (i !== active) show(i);
        });
        body.addEventListener('mouseleave', hide);
    })();
    </script>

    <!-- ============ 蜘蛛统计 ============ -->
    <section class="admin-card wa-section" id="waSpider">
        <div class="wa-toolbar">
            <div class="admin-card-title">
                <i class="ti ti-spider"></i>蜘蛛统计
                <button type="button" class="admin-help-toggle" title="查看说明" aria-label="查看说明" aria-expanded="false"><i class="ti ti-info-circle"></i></button>
            </div>
            <div class="wa-spider-total">当前范围共 <b><?php echo number_format($spiderTotal); ?></b> 次抓取</div>
        </div>
        <div class="admin-card-help">
            <div class="admin-card-subtitle">依据访问记录的 User-Agent 识别搜索引擎蜘蛛并分类汇总。蜘蛛大多不执行页面 JS，若长期记录为 0 属正常现象；识别范围覆盖百度、谷歌、必应、搜狗、360、神马与字节。</div>
        </div>

        <div class="wa-spider-grid">
            <?php foreach ($spiderDefs as $def): ?>
                <div class="wa-spider">
                    <div class="wa-spider-head">
                        <i class="ti <?php echo $def['icon']; ?>" style="color:<?php echo $def['color']; ?>"></i>
                        <span class="wa-spider-name"><?php echo $def['name']; ?></span>
                    </div>
                    <div class="wa-spider-count"><?php echo number_format($spiderCounts[$def['key']]); ?></div>
                    <div class="wa-spider-bar"><span style="width:<?php echo $spiderCounts[$def['key']] > 0 ? max(2.5, round($spiderCounts[$def['key']] / $waSpiderMax * 100, 1)) : 0; ?>%;background:<?php echo $def['color']; ?>"></span></div>
                </div>
            <?php endforeach; ?>
            <?php if ($spiderOther > 0): ?>
                <div class="wa-spider">
                    <div class="wa-spider-head">
                        <i class="ti ti-spider" style="color:#64748b"></i>
                        <span class="wa-spider-name">其他蜘蛛</span>
                    </div>
                    <div class="wa-spider-count"><?php echo number_format($spiderOther); ?></div>
                    <div class="wa-spider-bar"><span style="width:<?php echo max(2.5, round($spiderOther / $waSpiderMax * 100, 1)); ?>%;background:#64748b"></span></div>
                </div>
            <?php endif; ?>
        </div>

        <div class="wa-subhead">最近抓取记录</div>
        <table class="wa-table">
            <thead>
                <tr><th>时间</th><th>蜘蛛</th><th>来源 IP</th><th>抓取页面</th></tr>
            </thead>
            <tbody>
                <?php if (empty($spiderRecent)): ?>
                    <tr><td colspan="4" class="wa-empty">当前范围暂未捕捉到蜘蛛访问。</td></tr>
                <?php else: ?>
                    <?php foreach ($spiderRecent as $sr): ?>
                        <?php $waSpiderUrl = (string) $sr['page_url']; ?>
                        <tr>
                            <td class="wa-nowrap"><?php echo e(date('m-d H:i:s', strtotime($sr['visit_time']))); ?></td>
                            <td class="wa-nowrap"><?php echo e($waSpiderName($sr['user_agent'])); ?></td>
                            <td class="wa-nowrap"><?php echo e($sr['ip_address']); ?></td>
                            <td class="wa-cell-url" title="<?php echo e($waSpiderUrl); ?>"><?php echo e(mb_strlen($waSpiderUrl) > 46 ? mb_substr($waSpiderUrl, 0, 46) . '…' : $waSpiderUrl); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

<?php include __DIR__ . '/footer.php'; ?>
