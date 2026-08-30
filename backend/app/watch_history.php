<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/withu.php';

migrate_schema_if_needed();
$auth = new Auth();
$user = withu_require_couple_user($auth);
$db = Database::getInstance();
$historyMinMs = withu_watch_history_min_ms();
$historyRows = $db->fetchAll(
    "SELECT wh.*, COALESCE(wr.source, 'library') AS history_source, COALESCE(wr.source_episode, 0) AS history_source_episode
     FROM watch_history wh
     LEFT JOIN watch_rooms wr ON wr.id = wh.room_id
     WHERE wh.watch_duration_ms >= :min_ms
     ORDER BY wh.updated_at DESC, wh.id DESC LIMIT 200",
    ['min_ms' => $historyMinMs]
);
$mediaForRow = static function (array $row): array {
    $mediaId = (int)$row['media_id'];
    if ((string)($row['history_source'] ?? 'library') !== 'strm') return [];
    $meta = withu_strm_media_fetch($mediaId);
    if (!$meta) return [];
    $episodeId = (int)($row['history_source_episode'] ?? 0);
    $episode = null;
    foreach ((array)($meta['episodes'] ?? []) as $candidate) {
        if ((int)($candidate['id'] ?? 0) === $episodeId) { $episode = $candidate; break; }
    }
    if (!$episode && $episodeId > 0 && !empty($meta['episodes'])) $episode = $meta['episodes'][0];
    return [
        'id' => $mediaId,
        'file_name' => (string)($episode['sourceFileName'] ?? ($meta['title'] ?? '')),
        'series_name' => (string)($meta['title'] ?? 'strm 媒体'),
        'series_key' => 'strm-title-' . preg_replace('/\s+/u', '', mb_strtolower((string)($meta['title'] ?? 'strm 媒体'), 'UTF-8')),
        'episode_number' => (int)($episode['episodeNo'] ?? 0),
        'duration_ms' => 0,
        'resolution' => '',
        'cover_url' => (string)($meta['posterUrl'] ?? ''),
        'summary' => (string)($meta['overview'] ?? ''),
        'source' => 'strm',
    ];
};

$formatClock = static function (int $ms): string {
    $ms = max(0, $ms);
    $seconds = (int)floor($ms / 1000);
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $seconds = $seconds % 60;
    return $hours > 0
        ? sprintf('%d:%02d:%02d', $hours, $minutes, $seconds)
        : sprintf('%02d:%02d', $minutes, $seconds);
};
$formatDate = static function (string $date): string {
    $ts = strtotime($date);
    if (!$ts) return $date;
    if (date('Y') === date('Y', $ts)) return date('m月d日 H:i', $ts);
    return date('Y年m月d日', $ts);
};
$formatFriendlyDate = static function (string $date): string {
    $ts = strtotime($date);
    if (!$ts) return $date;
    $days = (int)floor((strtotime('today') - strtotime(date('Y-m-d', $ts))) / 86400);
    $clock = date('H:i', $ts);
    if ($days <= 0) return '今天 ' . $clock;
    if ($days === 1) return '昨天 ' . $clock;
    if ($days < 7) return $days . ' 天前';
    if (date('Y') === date('Y', $ts)) return date('n月j日', $ts);
    return date('Y年n月j日', $ts);
};
$humanDuration = static function (int $ms): string {
    $seconds = (int)round(max(0, $ms) / 1000);
    if ($seconds < 60) return $seconds . ' 秒';
    $minutes = intdiv($seconds, 60);
    if ($minutes < 60) {
        $rest = $seconds % 60;
        return $rest > 0 ? ($minutes . ' 分 ' . $rest . ' 秒') : ($minutes . ' 分钟');
    }
    $hours = intdiv($minutes, 60);
    $rest = $minutes % 60;
    return $rest > 0 ? ($hours . ' 小时 ' . $rest . ' 分') : ($hours . ' 小时');
};

// Group by media_id first (dedupe repeated watch sessions of the same file),
// keeping the most recently updated row for each media.
$byMedia = [];
foreach ($historyRows as $row) {
    $mediaId = (int)$row['media_id'];
    if ((string)($row['history_source'] ?? 'library') !== 'strm') continue;
    $episode = (int)($row['history_source_episode'] ?? 0);
    $key = $mediaId . ':strm:' . $episode;
    if (!isset($byMedia[$key]) || (string)($row['updated_at'] ?? '') > (string)($byMedia[$key]['updated_at'] ?? '')) {
        $byMedia[$key] = $row;
    }
}

// Merge episodes that belong to the same series into one card.
$bySeries = [];
$standalone = [];
foreach ($byMedia as $row) {
    $media = $mediaForRow($row);
    $seriesKey = (string)($media['series_key'] ?? '');
    if ($seriesKey !== '') {
        $series = $bySeries[$seriesKey] ?? [
            'media_id' => 0, 'rows' => [], 'updated_at' => '', 'started_at' => '', 'duration_ms' => 0,
            'episodes' => [], 'title' => (string)($media['series_name'] ?? ''),
        ];
        $series['rows'][] = $row;
        $series['media_id'] = (int)$row['media_id'];
        if ((string)($row['updated_at'] ?? '') > $series['updated_at']) {
            $series['updated_at'] = (string)$row['updated_at'];
            $series['started_at'] = (string)($row['started_at'] ?? '');
        }
        if (!empty($media['duration_ms'])) $series['duration_ms'] += (int)$media['duration_ms'];
        if (!empty($media['episode_number'])) $series['episodes'][] = (int)$media['episode_number'];
        $bySeries[$seriesKey] = $series;
    } else {
        $standalone[] = ['row' => $row, 'media' => $media];
    }
}

$items = [];
foreach ($standalone as $entry) {
    $row = $entry['row'];
    $media = $entry['media'];
    $mediaId = (int)$row['media_id'];
    $items[] = [
        'media_id' => $mediaId,
        'title' => (string)($media['series_name'] ?: $media['file_name'] ?: ('影片 #' . $mediaId)),
        'subtitle' => !empty($media['episode_number']) ? ('第 ' . (int)$media['episode_number'] . ' 集') : '',
        'cover_url' => (string)($media['cover_url'] ?? '') ?: ('/api/strm.php?action=img&id=' . $mediaId),
        'play_url' => '/watch_play.php?source=strm&id=' . $mediaId . ($episodeId > 0 ? '&episode=' . $episodeId : ''),
        'has_media' => !empty($media),
        'started_at' => $formatDate((string)($row['started_at'] ?? '')),
        'updated_friendly' => $formatFriendlyDate((string)($row['updated_at'] ?? '')),
        'watch_ms' => (int)($row['watch_duration_ms'] ?? 0),
        'solo_ms' => (int)($row['solo_duration_ms'] ?? 0),
        'together_ms' => (int)($row['together_duration_ms'] ?? 0),
        'last_position_ms' => (int)($row['last_position_ms'] ?? 0),
        'duration_ms' => (int)($media['duration_ms'] ?? 0),
        'resolution' => (string)($media['resolution'] ?? ''),
        'episode_count' => 1,
    ];
}
foreach ($bySeries as $series) {
    $latest = null;
    foreach ($series['rows'] as $row) {
        if ($latest === null || (string)($row['updated_at'] ?? '') >= (string)($latest['updated_at'] ?? '')) $latest = $row;
    }
    if ($latest === null) continue;
    $mediaId = (int)$latest['media_id'];
    $media = $mediaForRow($latest);
    $episodeId = (int)($latest['history_source_episode'] ?? 0);
    sort($series['episodes']);
    $episodeOptions = [];
    $watchedRows = $series['rows'];
    usort($watchedRows, static function (array $a, array $b): int {
        return [(string)($b['updated_at'] ?? ''), (int)($b['id'] ?? 0)] <=> [(string)($a['updated_at'] ?? ''), (int)($a['id'] ?? 0)];
    });
    $seenEpisodeIds = [];
    foreach ($watchedRows as $watchedRow) {
        $candidateId = (int)($watchedRow['history_source_episode'] ?? 0);
        if ($candidateId <= 0) continue;
        if (isset($seenEpisodeIds[$candidateId])) continue;
        $seenEpisodeIds[$candidateId] = true;
        $candidateMedia = $mediaForRow($watchedRow);
        $candidateNo = (int)($candidateMedia['episode_number'] ?? 0);
        $candidateLabel = $candidateNo > 0
            ? '第 ' . $candidateNo . ' 集'
            : (string)($candidateMedia['file_name'] ?? ('分集 #' . $candidateId));
        $watchRank = count($episodeOptions) === 0 ? '上次观看' : '更早观看';
        $episodeOptions[] = [
            'id' => $candidateId,
            'label' => $candidateLabel,
            'url' => '/watch_play.php?source=strm&id=' . $mediaId . '&episode=' . $candidateId,
            'is_last' => $candidateId === $episodeId,
            'rank_label' => $watchRank,
        ];
        if (count($episodeOptions) >= 2) break;
    }
    $lastEpisodeNo = (int)($media['episode_number'] ?? 0);
    if ($lastEpisodeNo > 0) {
        $epText = '第 ' . $lastEpisodeNo . ' 集';
    } elseif (count($series['episodes']) > 1) {
        $epText = '已记录 ' . count($series['episodes']) . ' 集';
    } else {
        $epText = '';
    }
    $items[] = [
        'media_id' => $mediaId,
        'title' => (string)($series['title'] ?: $media['series_name'] ?: ('影片 #' . $mediaId)),
        'subtitle' => $epText,
        'cover_url' => (string)($media['cover_url'] ?? '') ?: ('/api/strm.php?action=img&id=' . $mediaId),
        'play_url' => '/watch_play.php?source=strm&id=' . $mediaId . ($episodeId > 0 ? '&episode=' . $episodeId : ''),
        'has_media' => !empty($media),
        'started_at' => $formatDate((string)($series['started_at'] ?: $latest['started_at'] ?? '')),
        'updated_friendly' => $formatFriendlyDate((string)($latest['updated_at'] ?? '')),
        'watch_ms' => (int)($latest['watch_duration_ms'] ?? 0),
        'solo_ms' => (int)($latest['solo_duration_ms'] ?? 0),
        'together_ms' => (int)($latest['together_duration_ms'] ?? 0),
        'last_position_ms' => (int)($latest['last_position_ms'] ?? 0),
        'duration_ms' => (int)($media['duration_ms'] ?? 0),
        'resolution' => (string)($media['resolution'] ?? ''),
        'episode_count' => count($episodeOptions) ?: count($series['episodes']),
        'episode_options' => $episodeOptions,
        'last_episode_id' => $episodeId,
        'last_episode_number' => $lastEpisodeNo,
    ];
}

// Newest first: sort by the group's real update timestamp instead of a formatted date string.
foreach ($items as &$item) {
    $item['sort_ts'] = strtotime((string)($item['started_at'] ?? '')) ?: 0;
}
unset($item);
usort($items, static function (array $a, array $b): int {
    return ($b['sort_ts'] ?? 0) <=> ($a['sort_ts'] ?? 0);
});
$totalWatchMs = 0;
$totalTogetherMs = 0;
foreach ($items as &$item) {
    $item['watch_clock'] = $formatClock((int)$item['watch_ms']);
    $item['solo_clock'] = $formatClock((int)$item['solo_ms']);
    $item['together_clock'] = $formatClock((int)$item['together_ms']);
    $item['position_clock'] = $formatClock((int)$item['last_position_ms']);
    $item['progress_pct'] = 0;
    if ((int)$item['duration_ms'] > 0) {
        $item['progress_pct'] = (int)round((min((int)$item['last_position_ms'], (int)$item['duration_ms']) / (int)$item['duration_ms']) * 100);
    }
    $totalWatchMs += (int)$item['watch_ms'];
    $totalTogetherMs += (int)$item['together_ms'];
}
unset($item);
$stats = [
    'count' => count($items),
    'watch' => $humanDuration($totalWatchMs),
    'together' => $totalTogetherMs > 0 ? $humanDuration($totalTogetherMs) : '',
];
$itemsJson = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
$pageSize = 12;
$themeConfig = ['preset' => 'light', 'mode' => 'light', 'custom' => false, 'colors' => []];
$themeInlineStyle = '';
?><!doctype html>
<html lang="zh-CN" data-withu-theme="<?php echo e($themeConfig['preset']); ?>" data-withu-mode="<?php echo e($themeConfig['mode']); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>观影历史 - withU</title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="/assets/css/theme.css?v=withu-theme-20260723-1">
<style>
:root{
  --wh-pink:#f78da7;--wh-pink-strong:#e06d8f;--wh-pink-deep:#c8557a;
  --wh-blue:#7ec8e3;--wh-green:#7fbf9d;
  --wh-ink:#3d3038;--wh-ink-soft:#7a6a73;--wh-ink-faint:#a8969f;
  --wh-bg:#fff7fa;--wh-card:#ffffff;
  --wh-line:rgba(247,141,167,.16);--wh-soft:#fdf1f5;--wh-love:#ffe9f0;
  --wh-shadow:0 10px 30px rgba(247,141,167,.12);
  --wh-shadow-hover:0 18px 40px rgba(247,141,167,.22);
  --wh-ease:cubic-bezier(.22,.8,.28,1)
}

/* Inter 字体：与首页同一份本地化 @font-face 声明（提取自 Style/vendor/google-fonts/google-fonts.css），中文回退系统字体 */
/* cyrillic-ext */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 300;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa2JL7W0I5nvwUgHU.woff2) format('woff2');
  unicode-range: U+0460-052F, U+1C80-1C8A, U+20B4, U+2DE0-2DFF, U+A640-A69F, U+FE2E-FE2F;
}
/* cyrillic */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 300;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa0ZL7W0I5nvwUgHU.woff2) format('woff2');
  unicode-range: U+0301, U+0400-045F, U+0490-0491, U+04B0-04B1, U+2116;
}
/* greek-ext */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 300;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa2ZL7W0I5nvwUgHU.woff2) format('woff2');
  unicode-range: U+1F00-1FFF;
}
/* greek */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 300;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa1pL7W0I5nvwUgHU.woff2) format('woff2');
  unicode-range: U+0370-0377, U+037A-037F, U+0384-038A, U+038C, U+038E-03A1, U+03A3-03FF;
}
/* vietnamese */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 300;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa2pL7W0I5nvwUgHU.woff2) format('woff2');
  unicode-range: U+0102-0103, U+0110-0111, U+0128-0129, U+0168-0169, U+01A0-01A1, U+01AF-01B0, U+0300-0301, U+0303-0304, U+0308-0309, U+0323, U+0329, U+1EA0-1EF9, U+20AB;
}
/* latin-ext */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 300;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa25L7W0I5nvwUgHU.woff2) format('woff2');
  unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;
}
/* latin */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 300;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa1ZL7W0I5nvwU.woff2) format('woff2');
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
}
/* cyrillic-ext */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 400;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa2JL7W0I5nvwUgHU.woff2) format('woff2');
  unicode-range: U+0460-052F, U+1C80-1C8A, U+20B4, U+2DE0-2DFF, U+A640-A69F, U+FE2E-FE2F;
}
/* cyrillic */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 400;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa0ZL7W0I5nvwUgHU.woff2) format('woff2');
  unicode-range: U+0301, U+0400-045F, U+0490-0491, U+04B0-04B1, U+2116;
}
/* greek-ext */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 400;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa2ZL7W0I5nvwUgHU.woff2) format('woff2');
  unicode-range: U+1F00-1FFF;
}
/* greek */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 400;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa1pL7W0I5nvwUgHU.woff2) format('woff2');
  unicode-range: U+0370-0377, U+037A-037F, U+0384-038A, U+038C, U+038E-03A1, U+03A3-03FF;
}
/* vietnamese */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 400;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa2pL7W0I5nvwUgHU.woff2) format('woff2');
  unicode-range: U+0102-0103, U+0110-0111, U+0128-0129, U+0168-0169, U+01A0-01A1, U+01AF-01B0, U+0300-0301, U+0303-0304, U+0308-0309, U+0323, U+0329, U+1EA0-1EF9, U+20AB;
}
/* latin-ext */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 400;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa25L7W0I5nvwUgHU.woff2) format('woff2');
  unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;
}
/* latin */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 400;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa1ZL7W0I5nvwU.woff2) format('woff2');
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
}
/* cyrillic-ext */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 500;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa2JL7W0I5nvwUgHU.woff2) format('woff2');
  unicode-range: U+0460-052F, U+1C80-1C8A, U+20B4, U+2DE0-2DFF, U+A640-A69F, U+FE2E-FE2F;
}
/* cyrillic */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 500;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa0ZL7W0I5nvwUgHU.woff2) format('woff2');
  unicode-range: U+0301, U+0400-045F, U+0490-0491, U+04B0-04B1, U+2116;
}
/* greek-ext */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 500;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa2ZL7W0I5nvwUgHU.woff2) format('woff2');
  unicode-range: U+1F00-1FFF;
}
/* greek */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 500;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa1pL7W0I5nvwUgHU.woff2) format('woff2');
  unicode-range: U+0370-0377, U+037A-037F, U+0384-038A, U+038C, U+038E-03A1, U+03A3-03FF;
}
/* vietnamese */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 500;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa2pL7W0I5nvwUgHU.woff2) format('woff2');
  unicode-range: U+0102-0103, U+0110-0111, U+0128-0129, U+0168-0169, U+01A0-01A1, U+01AF-01B0, U+0300-0301, U+0303-0304, U+0308-0309, U+0323, U+0329, U+1EA0-1EF9, U+20AB;
}
/* latin-ext */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 500;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa25L7W0I5nvwUgHU.woff2) format('woff2');
  unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;
}
/* latin */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 500;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa1ZL7W0I5nvwU.woff2) format('woff2');
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
}
/* cyrillic-ext */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 600;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa2JL7W0I5nvwUgHU.woff2) format('woff2');
  unicode-range: U+0460-052F, U+1C80-1C8A, U+20B4, U+2DE0-2DFF, U+A640-A69F, U+FE2E-FE2F;
}
/* cyrillic */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 600;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa0ZL7W0I5nvwUgHU.woff2) format('woff2');
  unicode-range: U+0301, U+0400-045F, U+0490-0491, U+04B0-04B1, U+2116;
}
/* greek-ext */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 600;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa2ZL7W0I5nvwUgHU.woff2) format('woff2');
  unicode-range: U+1F00-1FFF;
}
/* greek */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 600;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa1pL7W0I5nvwUgHU.woff2) format('woff2');
  unicode-range: U+0370-0377, U+037A-037F, U+0384-038A, U+038C, U+038E-03A1, U+03A3-03FF;
}
/* vietnamese */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 600;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa2pL7W0I5nvwUgHU.woff2) format('woff2');
  unicode-range: U+0102-0103, U+0110-0111, U+0128-0129, U+0168-0169, U+01A0-01A1, U+01AF-01B0, U+0300-0301, U+0303-0304, U+0308-0309, U+0323, U+0329, U+1EA0-1EF9, U+20AB;
}
/* latin-ext */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 600;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa25L7W0I5nvwUgHU.woff2) format('woff2');
  unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;
}
/* latin */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 600;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa1ZL7W0I5nvwU.woff2) format('woff2');
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
}
/* cyrillic-ext */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 700;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa2JL7W0I5nvwUgHU.woff2) format('woff2');
  unicode-range: U+0460-052F, U+1C80-1C8A, U+20B4, U+2DE0-2DFF, U+A640-A69F, U+FE2E-FE2F;
}
/* cyrillic */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 700;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa0ZL7W0I5nvwUgHU.woff2) format('woff2');
  unicode-range: U+0301, U+0400-045F, U+0490-0491, U+04B0-04B1, U+2116;
}
/* greek-ext */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 700;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa2ZL7W0I5nvwUgHU.woff2) format('woff2');
  unicode-range: U+1F00-1FFF;
}
/* greek */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 700;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa1pL7W0I5nvwUgHU.woff2) format('woff2');
  unicode-range: U+0370-0377, U+037A-037F, U+0384-038A, U+038C, U+038E-03A1, U+03A3-03FF;
}
/* vietnamese */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 700;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa2pL7W0I5nvwUgHU.woff2) format('woff2');
  unicode-range: U+0102-0103, U+0110-0111, U+0128-0129, U+0168-0169, U+01A0-01A1, U+01AF-01B0, U+0300-0301, U+0303-0304, U+0308-0309, U+0323, U+0329, U+1EA0-1EF9, U+20AB;
}
/* latin-ext */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 700;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa25L7W0I5nvwUgHU.woff2) format('woff2');
  unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;
}
/* latin */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 700;
  font-display: swap;
  src: url(/ext/fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa1ZL7W0I5nvwU.woff2) format('woff2');
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
}
body.watch-history-page{
  margin:0;min-height:100vh;color:var(--wh-ink)!important;
  background-color:var(--wh-bg)!important;
  background-image:
    radial-gradient(circle at 12% 6%,rgba(126,200,227,.16),transparent 34%),
    radial-gradient(circle at 90% 12%,rgba(247,141,167,.18),transparent 34%),
    radial-gradient(circle at 55% 96%,rgba(127,191,157,.14),transparent 38%)!important;
  background-attachment:fixed;
  font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif!important
}
body.watch-history-page a{color:inherit}
body.watch-history-page button,body.watch-history-page select{font-family:inherit}
.watch-history-shell{max-width:1180px;margin:0 auto;padding:2.6rem 1.5rem 4rem}

/* ===== 头部 ===== */
.watch-history-header{display:flex;align-items:center;justify-content:space-between;gap:1.25rem;padding:.4rem 0 1.5rem;border-bottom:1px solid var(--wh-line)}
.watch-history-kicker{display:inline-flex;align-items:center;gap:.5rem;margin:0 0 .5rem;font-size:.74rem;letter-spacing:.2em;text-transform:uppercase;color:var(--wh-pink-strong);font-weight:800}
.watch-history-kicker::before{content:"";width:1.4rem;height:2px;border-radius:99px;background:linear-gradient(90deg,var(--wh-pink),var(--wh-blue))}
.watch-history-header h1{margin:0;font-size:clamp(1.7rem,3vw,2.4rem);line-height:1.12;letter-spacing:-.01em;color:var(--wh-ink)}
.watch-history-header .watch-history-desc{margin:.55rem 0 0;color:var(--wh-ink-soft);font-size:.86rem}
.watch-history-desc b{color:var(--wh-pink-deep);font-weight:800}
.watch-history-stats{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.95rem}
.wh-stat{display:inline-flex;align-items:center;gap:.42rem;padding:.4rem .8rem;border:1px solid var(--wh-line);border-radius:999px;background:rgba(255,255,255,.75);color:var(--wh-ink-soft);font-size:.78rem;font-weight:700}
.wh-stat svg{width:14px;height:14px;flex:0 0 auto}
.wh-stat b{color:var(--wh-ink);font-weight:800}
.wh-stat.is-love{border-color:rgba(247,141,167,.3);background:var(--wh-love);color:var(--wh-pink-deep)}
.wh-stat.is-love b{color:var(--wh-pink-deep)}
.watch-history-home{display:inline-flex;align-items:center;gap:.45rem;flex:0 0 auto;padding:.68rem 1.15rem;border:1px solid var(--wh-line);border-radius:999px;background:rgba(255,255,255,.85);color:var(--wh-ink);text-decoration:none;font-size:.88rem;font-weight:700;box-shadow:var(--wh-shadow);transition:transform .2s var(--wh-ease),box-shadow .2s var(--wh-ease),color .2s}
.watch-history-home:hover{transform:translateY(-2px);color:var(--wh-pink-strong);box-shadow:var(--wh-shadow-hover)}
.watch-history-home svg{width:15px;height:15px}

/* ===== 卡片列表 ===== */
.watch-history-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(420px,1fr));gap:1.05rem;margin-top:1.5rem}
.watch-history-item{position:relative;display:flex;gap:1.05rem;min-height:204px;padding:.85rem;border:1px solid var(--wh-line);border-radius:20px;background:var(--wh-card);box-shadow:var(--wh-shadow);cursor:pointer;opacity:0;animation:whCardIn .55s var(--wh-ease) both;animation-delay:var(--stagger,0s);transition:transform .28s var(--wh-ease),box-shadow .28s var(--wh-ease),border-color .28s}
@keyframes whCardIn{from{opacity:0;transform:translateY(22px)}to{opacity:1;transform:none}}
.watch-history-item:hover{transform:translateY(-4px);border-color:rgba(247,141,167,.4);box-shadow:var(--wh-shadow-hover)}
.watch-history-cover{position:relative;overflow:hidden;flex:0 0 128px;aspect-ratio:2/3;border-radius:14px;background:linear-gradient(135deg,#ffe9f0,#e6f5fb);display:flex;align-items:center;justify-content:center}
.watch-history-cover img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .45s var(--wh-ease)}
.watch-history-item:hover .watch-history-cover img{transform:scale(1.06)}
.wh-cover-fallback{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:2.4rem;font-weight:900;color:rgba(228,134,164,.5)}
.wh-cover-veil{position:absolute;inset:auto 0 0 0;height:46%;background:linear-gradient(0deg,rgba(61,48,56,.5),transparent);opacity:0;transition:opacity .28s var(--wh-ease);pointer-events:none}
.wh-cover-play{position:absolute;left:50%;top:50%;z-index:2;width:42px;height:42px;border-radius:50%;background:rgba(255,255,255,.94);color:var(--wh-pink);display:flex;align-items:center;justify-content:center;transform:translate(-50%,-50%) scale(.6);opacity:0;box-shadow:0 10px 24px rgba(247,141,167,.5);transition:opacity .25s var(--wh-ease),transform .25s var(--wh-ease);pointer-events:none}
.wh-cover-play svg{width:17px;height:17px;margin-left:2px}
@media(hover:hover){
  .watch-history-item:hover .wh-cover-veil,.watch-history-item:focus-within .wh-cover-veil{opacity:1}
  .watch-history-item:hover .wh-cover-play,.watch-history-item:focus-within .wh-cover-play{opacity:1;transform:translate(-50%,-50%) scale(1)}
}
.resolution-badge{position:absolute;right:.45rem;top:.45rem;z-index:3;display:inline-flex;align-items:center;justify-content:center;min-width:2.05rem;height:1.25rem;padding:0 .42rem;border:1px solid rgba(255,255,255,.54);border-radius:999px;color:#fff;font-size:.68rem;font-weight:900;line-height:1;letter-spacing:.02em;text-shadow:0 1px 2px rgba(0,0,0,.34);box-shadow:0 8px 18px rgba(0,0,0,.22),inset 0 1px 0 rgba(255,255,255,.34);backdrop-filter:blur(10px) saturate(150%);-webkit-backdrop-filter:blur(10px) saturate(150%)}
.resolution-badge.is-4k{padding:0;min-width:0;height:auto;background:transparent;border:0;box-shadow:none;backdrop-filter:none;-webkit-backdrop-filter:none}
.resolution-badge.is-4k img{display:block;width:2.4rem;height:1.48rem;object-fit:contain;filter:drop-shadow(0 2px 3px rgba(0,0,0,.24))}
.resolution-badge.is-2k{background:linear-gradient(135deg,#ffd7eb,#f08abb 58%,#be5775);color:#fff}
.resolution-badge.is-1k{background:linear-gradient(135deg,#91d8ff,#2476c7 58%,#17437a);color:#fff}

/* ===== 卡片内容 ===== */
.watch-history-content{display:flex;flex-direction:column;min-width:0;flex:1;padding:.2rem .15rem .1rem}
.watch-history-title{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;font-size:1.06rem;font-weight:800;line-height:1.35;word-break:break-word;color:var(--wh-ink)}
.watch-history-primary{text-decoration:none;transition:color .2s}
.watch-history-primary:hover .watch-history-title{color:var(--wh-pink-strong)}
.watch-history-subline{display:flex;align-items:center;flex-wrap:wrap;gap:.32rem;margin-top:.34rem;color:var(--wh-ink-soft);font-size:.78rem}
.watch-history-subline .wh-ep{padding:.12rem .5rem;border-radius:999px;background:var(--wh-soft);color:var(--wh-pink-deep);font-weight:800}
.watch-history-subline .wh-time{color:var(--wh-ink-faint)}
.watch-history-cta{display:flex;align-items:center;flex-wrap:wrap;gap:.55rem;margin-top:auto;padding-top:.8rem}
.wh-continue{display:inline-flex;align-items:center;gap:.42rem;padding:.5rem .95rem;border-radius:999px;background:linear-gradient(135deg,var(--wh-pink),#f2a9bf);color:#fff!important;text-decoration:none;font-size:.8rem;font-weight:800;box-shadow:0 8px 20px rgba(247,141,167,.4);transition:transform .2s var(--wh-ease),box-shadow .2s var(--wh-ease),filter .2s}
.wh-continue:hover{transform:translateY(-2px);filter:brightness(1.04);box-shadow:0 12px 26px rgba(247,141,167,.5)}
.wh-continue svg{width:13px;height:13px}
.watch-history-actions{display:inline-flex;align-items:center;gap:.45rem;min-width:0}
.watch-history-episode-select{min-height:34px;max-width:190px;padding:.3rem .6rem;border:1px solid rgba(61,48,56,.12);border-radius:10px;background:#fff;color:var(--wh-ink);font-size:.76rem;font-weight:700;cursor:pointer;transition:border-color .2s}
.watch-history-episode-select:hover{border-color:rgba(247,141,167,.45)}
.watch-history-chips{display:flex;flex-wrap:wrap;gap:.38rem;margin-top:.7rem}
.wh-chip{display:inline-flex;align-items:center;gap:.34rem;padding:.3rem .62rem;border:1px solid var(--wh-line);border-radius:999px;background:var(--wh-soft);color:var(--wh-ink-soft);font-size:.72rem;font-weight:700;line-height:1.3}
.wh-chip svg{width:12px;height:12px;flex:0 0 auto;color:var(--wh-pink)}
.wh-chip.is-love{border-color:rgba(247,141,167,.28);background:var(--wh-love);color:var(--wh-pink-deep)}
.wh-chip.is-love svg{color:var(--wh-pink)}
.wh-progress-row{display:flex;align-items:center;gap:.55rem;margin-top:.72rem}
.watch-history-progress{flex:1;height:5px;border-radius:999px;background:#f6e3ea;overflow:hidden}
.watch-history-progress i{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,var(--wh-pink),var(--wh-blue));transition:width .9s var(--wh-ease)}
.wh-progress-pct{flex:0 0 auto;color:var(--wh-pink-deep);font-size:.72rem;font-weight:800}
.wh-position{display:inline-flex;align-items:center;gap:.34rem;color:var(--wh-ink-soft);font-size:.72rem;font-weight:700}
.wh-position svg{width:12px;height:12px;color:var(--wh-pink)}

/* ===== 空态 ===== */
.watch-history-empty{grid-column:1/-1;display:flex;flex-direction:column;align-items:center;gap:.4rem;margin-top:1rem;padding:3.2rem 1.4rem;border:1.5px dashed rgba(247,141,167,.4);border-radius:22px;background:rgba(255,255,255,.6);text-align:center}
.wh-empty-icon{display:flex;align-items:center;justify-content:center;width:58px;height:58px;margin-bottom:.5rem;border-radius:50%;background:linear-gradient(135deg,var(--wh-soft),var(--wh-love));color:var(--wh-pink)}
.wh-empty-icon svg{width:22px;height:22px;margin-left:3px}
.watch-history-empty .wh-empty-title{margin:.2rem 0 0;font-size:1.05rem;font-weight:800;color:var(--wh-ink)}
.watch-history-empty .wh-empty-text{margin:0 0 1rem;color:var(--wh-ink-soft);font-size:.85rem}

/* ===== 加载更多 ===== */
.wh-load-more{display:block;margin:1.6rem auto 0;padding:.72rem 1.7rem;border:1px solid var(--wh-line);border-radius:999px;background:#fff;color:var(--wh-pink-deep);font-size:.88rem;font-weight:800;cursor:pointer;box-shadow:var(--wh-shadow);transition:transform .2s var(--wh-ease),box-shadow .2s var(--wh-ease)}
.wh-load-more:hover{transform:translateY(-2px);box-shadow:var(--wh-shadow-hover)}
.wh-load-more:disabled{opacity:.5;cursor:default;transform:none}
.watch-history-item a:focus-visible,.watch-history-item:focus-visible{outline:none;box-shadow:0 0 0 3px rgba(247,141,167,.35)}
.watch-history-episode-select:focus-visible{outline:none;border-color:var(--wh-pink);box-shadow:0 0 0 3px rgba(247,141,167,.3)}

/* ===== 响应式 ===== */
@media(max-width:960px){
  .watch-history-list{grid-template-columns:1fr;gap:.85rem}
}
@media(max-width:640px){
  .watch-history-shell{padding:1.4rem .85rem 2.8rem}
  .watch-history-header{flex-direction:column;align-items:flex-start;gap:.9rem;padding-bottom:1.2rem}
  .watch-history-home{padding:.55rem 1rem;font-size:.82rem}
  .watch-history-stats{margin-top:.7rem}
  .watch-history-list{margin-top:1.1rem}
  .watch-history-item{min-height:172px;gap:.8rem;padding:.65rem;border-radius:16px}
  .watch-history-cover{flex-basis:94px;border-radius:11px}
  .watch-history-title{font-size:.98rem}
  .watch-history-subline{font-size:.74rem}
  .watch-history-cta{padding-top:.6rem;gap:.4rem}
  .wh-continue{padding:.44rem .8rem;font-size:.76rem}
  .watch-history-episode-select{max-width:100%;min-height:32px;font-size:.72rem}
  .watch-history-chips{gap:.3rem;margin-top:.55rem}
  .wh-chip{padding:.24rem .5rem;font-size:.68rem}
  .wh-progress-row{margin-top:.55rem}
}
@media(max-width:400px){
  .watch-history-cover{flex-basis:82px}
  .watch-history-episode-select{max-width:150px}
}
@media(prefers-reduced-motion:reduce){
  .watch-history-item{animation:none;opacity:1}
}
</style>
</head>
<body class="watch-history-page">
<main class="watch-history-shell">
  <header class="watch-history-header">
    <div>
      <p class="watch-history-kicker">withU WATCH</p>
      <h1>观影历史</h1>
      <p class="watch-history-desc">按最新观看排序，同剧集合并为一张卡片；只保留累计观看超过 <b><?php echo (int)round($historyMinMs / 1000); ?></b> 秒的有效记录。</p>
      <?php if ($stats['count'] > 0): ?>
      <div class="watch-history-stats">
        <span class="wh-stat"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M8 4v16M16 4v16M3 9h5M3 15h5M16 9h5M16 15h5"/></svg>共 <b><?php echo (int)$stats['count']; ?></b> 部</span>
        <span class="wh-stat"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>累计观看 <b><?php echo e($stats['watch']); ?></b></span>
        <?php if ($stats['together'] !== ''): ?>
        <span class="wh-stat is-love"><svg viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.51 4.04 3 5.5l7 7Z"/></svg>一起看 <b><?php echo e($stats['together']); ?></b></span>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <a class="watch-history-home" href="/watch.php" aria-label="返回影视馆">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>返回影视馆
    </a>
  </header>

  <div id="historyList" class="watch-history-list" data-items='<?php echo $itemsJson; ?>'></div>
  <button id="historyLoadMore" class="wh-load-more" type="button" hidden>加载更多</button>
</main>
<script>
(function(){
  var list=document.getElementById('historyList');
  var loadMore=document.getElementById('historyLoadMore');
  var items=[];
  try{ items=JSON.parse(list.getAttribute('data-items')||'[]'); }catch(e){ items=[]; }
  var PAGE=12,index=0;
  var ICONS={
    play:'<svg viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M7 4.8a1 1 0 0 1 1.53-.85l11.2 7.2a1 1 0 0 1 0 1.7l-11.2 7.2A1 1 0 0 1 7 19.2Z"/></svg>',
    clock:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
    heart:'<svg viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.51 4.04 3 5.5l7 7Z"/></svg>',
    user:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>'
  };
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
  function humanMs(ms){
    ms=Math.max(0,ms|0); var s=Math.round(ms/1000);
    if(s<60)return s+' 秒';
    var m=Math.floor(s/60),sec=s%60;
    if(m<60)return sec?m+' 分 '+sec+' 秒':m+' 分钟';
    var h=Math.floor(m/60),mm=m%60;
    return mm?h+' 小时 '+mm+' 分':h+' 小时';
  }
  function coverFallback(img){
    var wrap=img.parentNode;
    if(wrap&&!wrap.querySelector('.wh-cover-fallback')){
      var fb=document.createElement('span');
      fb.className='wh-cover-fallback';
      fb.textContent=(img.getAttribute('data-initial')||'影').slice(0,1);
      wrap.appendChild(fb);
    }
  }
  window.coverFallback=coverFallback;
  function resolutionTier(resolution){
    var text=String(resolution||'').trim().toUpperCase();
    if(!text)return null;
    if(/\b(4K|UHD|2160P)\b/.test(text))return{label:'4K',className:'is-4k'};
    if(/\b(2K|QHD|1440P)\b/.test(text))return{label:'2K',className:'is-2k'};
    if(/\b(1K|FHD|1080P|720P|HD)\b/.test(text))return{label:'HD',className:'is-1k'};
    var match=text.match(/(\d{3,5})\s*[X×]\s*(\d{3,5})/)||text.match(/(\d{3,5})\s*P?\b/),height=0;
    if(!match)return null;
    height=Number(match[2]||match[1]||0);
    if(height>=2000)return{label:'4K',className:'is-4k'};
    if(height>=1300)return{label:'2K',className:'is-2k'};
    if(height>=700)return{label:'HD',className:'is-1k'};
    return null;
  }
  function resolutionBadgeHtml(resolution){
    var tier=resolutionTier(resolution);
    if(!tier)return'';
    if(tier.label==='4K')return'<span class="resolution-badge is-4k"><img src="/assets/images/4k-badge.png" alt="4K"></span>';
    return'<span class="resolution-badge '+tier.className+'">'+tier.label+'</span>';
  }
  function card(item,i){
    var title=esc(item.title||'影片');
    var playUrl=esc(item.play_url||('/watch_play.php?source=strm&id='+item.media_id));
    var subtitle=item.subtitle?esc(item.subtitle):'';
    var options=Array.isArray(item.episode_options)?item.episode_options:[];
    var continueLabel=(item.last_episode_number>0)?('继续看 · 第 '+item.last_episode_number+' 集'):'继续观看';
    var chips='<span class="wh-chip">'+ICONS.clock+'累计 '+esc(humanMs(item.watch_ms))+'</span>';
    if(item.together_ms>0)chips+='<span class="wh-chip is-love">'+ICONS.heart+'一起 '+esc(humanMs(item.together_ms))+'</span>';
    if(item.solo_ms>0)chips+='<span class="wh-chip">'+ICONS.user+'单独 '+esc(humanMs(item.solo_ms))+'</span>';
    var progress='';
    if(item.duration_ms>0&&item.progress_pct>0){
      progress='<div class="wh-progress-row"><div class="watch-history-progress" aria-hidden="true"><i style="width:'+item.progress_pct+'%"></i></div><span class="wh-progress-pct">已看 '+item.progress_pct+'%</span></div>';
    }else if(item.last_position_ms>0){
      progress='<div class="wh-progress-row"><span class="wh-position">'+ICONS.clock+'上次看到 '+esc(item.position_clock)+'</span></div>';
    }
    var episodePicker='';
    if(options.length>1){
      episodePicker='<span class="watch-history-actions" onclick="event.stopPropagation()">'+
        '<select class="watch-history-episode-select" id="history-episode-'+esc(item.media_id)+'" data-episode-picker aria-label="选择集数">'+
        options.map(function(option){return '<option value="'+esc(option.url)+'" '+(option.is_last?'selected':'')+'>'+esc(option.rank_label||'最近观看')+' · '+esc(option.label)+'</option>';}).join('')+
        '</select></span>';
    }
    var subline='<span class="wh-time">'+esc(item.updated_friendly||item.started_at||'')+'</span>';
    if(subtitle)subline='<span class="wh-ep">'+subtitle+'</span>'+subline;
    return '<div class="watch-history-item" data-play-url="'+playUrl+'" style="--stagger:'+Math.min(i,11)*0.05+'s">'+
      '<div class="watch-history-cover">'+
        '<img loading="lazy" src="'+esc(item.cover_url)+'" data-initial="'+title+'" alt="'+title+'" onerror="coverFallback(this)">'+
        '<span class="wh-cover-veil" aria-hidden="true"></span>'+
        '<span class="wh-cover-play" aria-hidden="true">'+ICONS.play+'</span>'+
        resolutionBadgeHtml(item.resolution)+
      '</div>'+
      '<div class="watch-history-content">'+
        '<a class="watch-history-primary" href="'+playUrl+'"><div class="watch-history-title">'+title+'</div></a>'+
        '<div class="watch-history-subline">'+subline+'</div>'+
        '<div class="watch-history-cta">'+
          '<a class="wh-continue" href="'+playUrl+'">'+ICONS.play+continueLabel+'</a>'+
          episodePicker+
        '</div>'+
        '<div class="watch-history-chips">'+chips+'</div>'+
        progress+
      '</div></div>';
  }
  function render(){
    var frag=document.createDocumentFragment();
    var end=Math.min(index+PAGE,items.length);
    for(var i=index;i<end;i++)frag.appendChild(htmlToNode(card(items[i],i)));
    list.appendChild(frag);
    index=end;
    loadMore.hidden=index>=items.length;
  }
  function htmlToNode(html){
    var t=document.createElement('template');t.innerHTML=html;return t.content.firstElementChild;
  }
  if(!items.length){
    list.insertAdjacentHTML('beforeend',
      '<div class="watch-history-empty">'+
        '<span class="wh-empty-icon">'+ICONS.play+'</span>'+
        '<p class="wh-empty-title">还没有值得记录的观影时光</p>'+
        '<p class="wh-empty-text">一起看完一小段，它就会出现在这里。</p>'+
        '<a class="wh-continue" href="/watch.php">'+ICONS.play+'去影视馆看看</a>'+
      '</div>');
    return;
  }
  render();
  loadMore.hidden=index>=items.length;
  loadMore.addEventListener('click',render);
  list.addEventListener('change',function(event){
    var picker=event.target.closest('[data-episode-picker]');
    if(picker&&picker.value)window.location.href=picker.value;
  });
  list.addEventListener('click',function(event){
    if(event.target.closest('[data-episode-picker]')||event.target.closest('a'))return;
    var card=event.target.closest('[data-play-url]');
    if(card)window.location.href=card.getAttribute('data-play-url');
  });
})();
</script>
</body>
</html>
