<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/withu.php';
require_once __DIR__ . '/core/MediaDatabase.php';
require_once __DIR__ . '/core/MediaSchema.php';
require_once __DIR__ . '/core/MediaRepository.php';

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
$libraryRows = array_filter($historyRows, static function (array $row): bool {
    return (string)($row['history_source'] ?? 'library') !== 'strm';
});
$mediaMap = withu_media_fetch_many(array_map(static function ($row) {
    return (int)$row['media_id'];
}, $libraryRows));
$strmMap = [];
$strmFetch = static function (int $id) use (&$strmMap): array {
    if (isset($strmMap[$id])) return $strmMap[$id];
    $jwtPath = dirname(__DIR__) . '/runtime/strm/jwt.txt';
    $secret = is_file($jwtPath) ? trim((string)file_get_contents($jwtPath)) : '';
    if ($id <= 0 || $secret === '') return $strmMap[$id] = [];
    $b64u = static function (string $value): string {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    };
    $now = time();
    $header = $b64u(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = $b64u(json_encode(['sub' => 'withu_admin', 'iat' => $now, 'exp' => $now + 600]));
    $signature = $b64u(hash_hmac('sha256', $header . '.' . $payload, $secret, true));
        $ch = curl_init('http://127.0.0.1:8081/api/media-library/' . $id);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $header . '.' . $payload . '.' . $signature],
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 10,
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $response = $status === 200 ? json_decode((string)$body, true) : null;
    return $strmMap[$id] = (($response['code'] ?? 0) === 200 && is_array($response['data'] ?? null)) ? $response['data'] : [];
};
$mediaForRow = static function (array $row) use (&$mediaMap, $strmFetch): array {
    $mediaId = (int)$row['media_id'];
    if ((string)($row['history_source'] ?? 'library') !== 'strm') return $mediaMap[$mediaId] ?? [];
    $meta = $strmFetch($mediaId);
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

// Group by media_id first (dedupe repeated watch sessions of the same file),
// keeping the most recently updated row for each media.
$byMedia = [];
foreach ($historyRows as $row) {
    $mediaId = (int)$row['media_id'];
    $source = (string)($row['history_source'] ?? 'library');
    $episode = $source === 'strm' ? (int)($row['history_source_episode'] ?? 0) : 0;
    $key = $mediaId . ':' . $source . ':' . $episode;
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
    $isStrm = (string)($row['history_source'] ?? 'library') === 'strm';
    $episodeId = (int)($row['history_source_episode'] ?? 0);
    $playUrl = $isStrm
        ? '/watch_play.php?source=strm&id=' . $mediaId . ($episodeId > 0 ? '&episode=' . $episodeId : '')
        : '/watch_play.php?media_id=' . $mediaId;
    $items[] = [
        'media_id' => $mediaId,
        'title' => (string)($media['series_name'] ?: $media['file_name'] ?: ('影片 #' . $mediaId)),
        'subtitle' => !empty($media['episode_number']) ? ('第 ' . (int)$media['episode_number'] . ' 集') : '',
        'cover_url' => (string)($media['cover_url'] ?? '') ?: ($isStrm ? '/api/strm.php?action=img&id=' . $mediaId : '/api/media_cover.php?id=' . $mediaId),
        'play_url' => $playUrl,
        'has_media' => !empty($media),
        'started_at' => $formatDate((string)($row['started_at'] ?? '')),
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
    $isStrm = (string)($latest['history_source'] ?? 'library') === 'strm';
    $episodeId = (int)($latest['history_source_episode'] ?? 0);
    $playUrl = $isStrm
        ? '/watch_play.php?source=strm&id=' . $mediaId . ($episodeId > 0 ? '&episode=' . $episodeId : '')
        : '/watch_play.php?media_id=' . $mediaId;
    sort($series['episodes']);
    $episodeOptions = [];
    if ($isStrm) {
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
            $watchRank = count($episodeOptions) === 0 ? '上次观看' : '上上次观看';
            $episodeOptions[] = [
                'id' => $candidateId,
                'label' => $candidateLabel,
                'url' => '/watch_play.php?source=strm&id=' . $mediaId . '&episode=' . $candidateId,
                'is_last' => $candidateId === $episodeId,
                'rank_label' => $watchRank,
            ];
            if (count($episodeOptions) >= 2) break;
        }
    }
    $epText = '';
    $lastEpisodeNo = (int)($media['episode_number'] ?? 0);
    if ($lastEpisodeNo > 0) {
        $epText = '上次观看：第 ' . $lastEpisodeNo . ' 集';
    } elseif (count($series['episodes']) > 1) {
        $epText = '上次观看：已记录 ' . count($series['episodes']) . ' 集';
    }
    $items[] = [
        'media_id' => $mediaId,
        'title' => (string)($series['title'] ?: $media['series_name'] ?: ('影片 #' . $mediaId)),
        'subtitle' => $epText,
        'cover_url' => (string)($media['cover_url'] ?? '') ?: ($isStrm ? '/api/strm.php?action=img&id=' . $mediaId : '/api/media_cover.php?id=' . $mediaId),
        'play_url' => $playUrl,
        'has_media' => !empty($media),
        'started_at' => $formatDate((string)($series['started_at'] ?: $latest['started_at'] ?? '')),
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

// Newest series first: item order follows the group's latest update time.
usort($items, static function (array $a, array $b): int {
    return ($b['started_at'] ?? '') <=> ($a['started_at'] ?? '');
});
foreach ($items as &$item) {
    $item['watch_clock'] = $formatClock((int)$item['watch_ms']);
    $item['solo_clock'] = $formatClock((int)$item['solo_ms']);
    $item['together_clock'] = $formatClock((int)$item['together_ms']);
    $item['position_clock'] = $formatClock((int)$item['last_position_ms']);
    $item['progress_pct'] = 0;
    if ((int)$item['duration_ms'] > 0) {
        $item['progress_pct'] = (int)round((min((int)$item['last_position_ms'], (int)$item['duration_ms']) / (int)$item['duration_ms']) * 100);
    }
}
unset($item);
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
  --wh-bg:#fff7fa;--wh-card:#fff;--wh-border:#f6dfe8;--wh-text:#3d2a33;--wh-muted:#a98c99;
  --wh-accent:#f78da7;--wh-accent-strong:#e486a4;--wh-glow:rgba(228,134,164,.16)
}
body.watch-history-page{
  margin:0;min-height:100vh;color:var(--wh-text)!important;
  background-color:var(--wh-bg)!important;
  background-image:radial-gradient(900px 420px at 12% -4%,rgba(255,214,229,.5),transparent 62%),
    radial-gradient(760px 420px at 92% 8%,rgba(248,199,216,.42),transparent 60%),
    radial-gradient(920px 520px at 50% 112%,rgba(255,224,235,.5),transparent 60%)!important
}
body.watch-history-page a{color:inherit}
.watch-history-shell{max-width:1080px;margin:0 auto;padding:2rem 1.25rem 3.5rem}
.watch-history-header{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;padding:.6rem 0 1.2rem}
.watch-history-kicker{margin:0 0 .34rem;font-size:.76rem;letter-spacing:.18em;text-transform:uppercase;color:var(--wh-accent-strong);font-weight:800}
.watch-history-header h1{margin:0;font-size:clamp(1.6rem,3vw,2.3rem);line-height:1.12;letter-spacing:.01em}
.watch-history-header p{margin:.48rem 0 0;color:var(--wh-muted);font-size:.9rem}
.watch-history-home{display:inline-flex;align-items:center;gap:.45rem;padding:.66rem 1.05rem;border-radius:999px;background:rgba(255,255,255,.82);text-decoration:none;font-size:.9rem;font-weight:700;box-shadow:0 10px 24px var(--wh-glow);transition:transform .18s ease,box-shadow .18s ease}
.watch-history-home:hover{transform:translateY(-2px);box-shadow:0 16px 32px rgba(228,134,164,.22)}
.watch-history-note{margin:1.05rem 0 1.25rem;padding:.8rem 1rem;border-radius:16px;background:rgba(255,255,255,.72);color:var(--wh-muted);font-size:.86rem}
.watch-history-list{display:grid;gap:1rem}
 .watch-history-item{
   display:grid;grid-template-columns:152px minmax(0,1fr);gap:1.05rem;align-items:stretch;padding:.95rem;
   border-radius:22px;background:var(--wh-card);text-decoration:none;
   box-shadow:0 14px 34px rgba(228,134,164,.1);transition:transform .2s ease,box-shadow .2s ease
 }
.watch-history-item:hover{transform:translateY(-3px);box-shadow:0 20px 42px rgba(228,134,164,.18)}
.watch-history-cover{position:relative;overflow:hidden;min-height:0;aspect-ratio:2/3;border-radius:16px;background:linear-gradient(135deg,#ffe9f0,#f9d7e4);display:flex;align-items:center;justify-content:center}
.watch-history-cover img{width:100%;height:100%;object-fit:cover;display:block}
.watch-history-cover .wh-cover-fallback{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:2.6rem;font-weight:900;color:rgba(228,134,164,.5)}
.watch-history-badge{position:absolute;left:.55rem;bottom:.55rem}
.watch-history-content{display:flex;flex-direction:column;justify-content:space-between;min-width:0;padding:.15rem 0 .05rem}
.watch-history-title{font-size:1.12rem;font-weight:800;line-height:1.35;word-break:break-word}
.watch-history-subtitle{margin:.42rem 0 0;color:var(--wh-muted);font-size:.88rem;line-height:1.5;word-break:break-word}
.watch-history-meta{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.85rem}
.watch-history-meta span{padding:.3rem .62rem;border-radius:999px;background:#fffafc;color:#8a6a78;font-size:.76rem;font-weight:600}
.watch-history-meta span.wh-progress-chip{background:linear-gradient(135deg,#fff,#ffeef4);color:var(--wh-accent-strong)}
.watch-history-progress{margin-top:.9rem;height:5px;border-radius:999px;background:#f7e6ed;overflow:hidden}
 .watch-history-progress i{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,var(--wh-accent),var(--wh-accent-strong));box-shadow:0 0 10px rgba(228,134,164,.35)}
 .watch-history-primary{display:inline-flex;align-items:center;gap:.4rem;color:inherit;text-decoration:none}
 .watch-history-primary:hover{color:var(--wh-accent-strong)}
 .watch-history-actions{display:flex;align-items:center;flex-wrap:wrap;gap:.55rem;margin-top:.72rem}
 .watch-history-episode-label{color:var(--wh-muted);font-size:.78rem;font-weight:700}
 .watch-history-episode-select{min-height:34px;max-width:220px;padding:.35rem .65rem;border:1px solid var(--wh-border);border-radius:10px;background:#fffafc;color:var(--wh-text);font:inherit;font-size:.78rem;font-weight:700;cursor:pointer}
 .watch-history-last-badge{display:inline-flex;align-items:center;padding:.28rem .55rem;border-radius:999px;background:#fff0f5;color:var(--wh-accent-strong);font-size:.72rem;font-weight:800}
 .watch-history-empty{padding:1.4rem 1.1rem;border-radius:18px;background:rgba(255,255,255,.8);color:var(--wh-muted);text-align:center}
.wh-load-more{display:block;margin:1.35rem auto 0;padding:.72rem 1.6rem;border:0;border-radius:999px;background:#fff;color:var(--wh-accent-strong);font-size:.9rem;font-weight:800;cursor:pointer;box-shadow:0 10px 24px var(--wh-glow);transition:transform .18s ease,box-shadow .18s ease}
.wh-load-more:hover{transform:translateY(-2px);box-shadow:0 16px 32px rgba(228,134,164,.22)}
.wh-load-more:disabled{opacity:.5;cursor:default;transform:none}
.resolution-badge{position:absolute;right:.55rem;top:.55rem;z-index:3;display:inline-flex;align-items:center;justify-content:center;min-width:2.05rem;height:1.25rem;padding:0 .42rem;border:1px solid rgba(255,255,255,.54);border-radius:999px;color:#fff;font-size:.68rem;font-weight:900;line-height:1;letter-spacing:.02em;text-shadow:0 1px 2px rgba(0,0,0,.34);box-shadow:0 8px 18px rgba(0,0,0,.22),inset 0 1px 0 rgba(255,255,255,.34);backdrop-filter:blur(10px) saturate(150%);-webkit-backdrop-filter:blur(10px) saturate(150%)}
.resolution-badge.is-4k{padding:0;min-width:0;height:auto;background:transparent;border:0;box-shadow:none;backdrop-filter:none;-webkit-backdrop-filter:none}
.resolution-badge.is-4k img{display:block;width:2.58rem;height:1.6rem;object-fit:contain;filter:drop-shadow(0 2px 3px rgba(0,0,0,.24))}
.resolution-badge.is-2k{background:linear-gradient(135deg,#ffd7eb,#f08abb 58%,#be5775);color:#fff}
 .resolution-badge.is-bluray{background:linear-gradient(135deg,#91d8ff,#2476c7 58%,#17437a);color:#fff}
 /* History layout refresh: content-first media library cards. */
 body.watch-history-page{font-family:'AlimamaCustom',-apple-system,BlinkMacSystemFont,'Segoe UI','PingFang SC','Hiragino Sans GB','Microsoft YaHei',sans-serif!important;background:#f5f6f8!important;background-image:radial-gradient(760px 360px at 8% -12%,rgba(247,141,167,.22),transparent 62%),radial-gradient(680px 360px at 96% 0%,rgba(126,200,227,.18),transparent 62%)!important;color:#27313d!important}
 body.watch-history-page button,body.watch-history-page select{font-family:inherit}
 .watch-history-shell{max-width:1240px;padding:2.6rem 1.5rem 4rem}
 .watch-history-header{align-items:center;padding:0 0 1.65rem;border-bottom:1px solid rgba(39,49,61,.1)}
 .watch-history-kicker{color:#d46f8e;letter-spacing:.2em}
 .watch-history-header h1{color:#202a35;font-size:clamp(1.8rem,3vw,2.55rem);letter-spacing:-.02em}
 .watch-history-header p{color:#75808c}
 .watch-history-home{border:1px solid rgba(39,49,61,.1);background:rgba(255,255,255,.86);color:#3f4d5b;box-shadow:0 8px 20px rgba(39,49,61,.08)}
 .watch-history-note{display:flex;align-items:center;gap:.65rem;margin:1.4rem 0;padding:.82rem 1rem;border:1px solid rgba(39,49,61,.08);border-radius:12px;background:rgba(255,255,255,.7);color:#66727e}
 .watch-history-note::before{content:"";width:4px;height:1.1rem;border-radius:99px;background:#e486a4;flex:0 0 auto}
 .watch-history-list{grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}
 .watch-history-item{grid-template-columns:132px minmax(0,1fr);gap:1.15rem;min-height:214px;padding:.8rem;border:1px solid rgba(39,49,61,.08);border-radius:16px;background:rgba(255,255,255,.92);box-shadow:0 10px 26px rgba(39,49,61,.07);transition:transform .2s ease,box-shadow .2s ease,border-color .2s ease}
 .watch-history-item:hover{transform:translateY(-3px);border-color:rgba(228,134,164,.42);box-shadow:0 16px 34px rgba(39,49,61,.12)}
 .watch-history-cover{border-radius:11px;background:#edf0f3}
 .watch-history-cover img{transition:transform .35s ease}
 .watch-history-item:hover .watch-history-cover img{transform:scale(1.04)}
 .watch-history-content{padding:.2rem .15rem .1rem}
 .watch-history-title{font-size:1.04rem;color:#202a35;line-height:1.35}
 .watch-history-subtitle{margin-top:.35rem;color:#788490;font-size:.8rem}
 .watch-history-meta{gap:.35rem;margin-top:1rem}
 .watch-history-meta span{padding:.28rem .5rem;border:1px solid rgba(39,49,61,.07);background:#f7f8fa;color:#64717d;font-size:.7rem}
 .watch-history-meta span.wh-progress-chip{border-color:rgba(228,134,164,.18);background:#fff1f5;color:#c86683}
 .watch-history-actions{margin-top:.75rem;padding-top:.7rem;border-top:1px solid rgba(39,49,61,.08)}
 .watch-history-episode-label{font-size:.72rem;color:#7a8792}
 .watch-history-episode-select{min-height:36px;max-width:100%;border-color:rgba(39,49,61,.12);border-radius:8px;background:#fff;color:#3f4d5b}
 .watch-history-last-badge{background:#fff1f5;color:#c86683}
 .watch-history-progress{margin-top:1rem;height:4px;background:#e9edf1}
 .watch-history-progress i{background:linear-gradient(90deg,#e486a4,#7ec8e3);box-shadow:none}
 .wh-load-more{border:1px solid rgba(39,49,61,.1);background:#fff;color:#c86683;box-shadow:0 8px 20px rgba(39,49,61,.08)}
 @media(max-width:760px){
  .watch-history-shell{padding:1.5rem .85rem 3rem}
  .watch-history-list{grid-template-columns:1fr;gap:.8rem}
  .watch-history-item{grid-template-columns:96px minmax(0,1fr);gap:.85rem;min-height:176px;padding:.65rem;border-radius:14px}
  .watch-history-shell{padding:1.4rem .8rem 2.6rem}
  .watch-history-header{align-items:flex-start;flex-direction:column}
  .watch-history-item{grid-template-columns:96px minmax(0,1fr);gap:.85rem;padding:.65rem;border-radius:14px}
  .watch-history-title{font-size:1rem}
  .watch-history-subtitle{font-size:.84rem}
  .watch-history-meta span{font-size:.72rem}
  .watch-history-meta{margin-top:.7rem}
  .watch-history-actions{align-items:flex-start;flex-direction:column;gap:.35rem}
  .watch-history-episode-select{width:100%;font-size:.76rem}
 }
 @media(max-width:420px){
  .watch-history-item{grid-template-columns:82px minmax(0,1fr)}
 .watch-history-meta{gap:.34rem}
 .watch-history-meta span{padding:.26rem .48rem}
}
</style>
</head>
<body class="watch-history-page">
<main class="watch-history-shell">
  <header class="watch-history-header">
    <div>
      <p class="watch-history-kicker">withU WATCH</p>
      <h1>观影历史</h1>
      <p>仅保留累计观看超过 <?php echo (int)round($historyMinMs / 1000); ?> 秒的记录，误点不会进入列表。</p>
    </div>
    <a class="watch-history-home" href="/watch.php" aria-label="返回同步观影">返回首页</a>
  </header>

  <section class="watch-history-note">
    按最新观看时间排序，同剧集自动合并为一张卡片；进度按影片实际时长计算。
  </section>

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
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
  function coverFallback(img){
    var wrap=img.parentNode;
    if(wrap&&!wrap.querySelector('.wh-cover-fallback')){
      var fb=document.createElement('span');
      fb.className='wh-cover-fallback';
      fb.textContent=(img.getAttribute('data-initial')||'影').slice(0,1);
      wrap.appendChild(fb);
    }
  }
  function card(item){
    var title=esc(item.title||'影片');
     var subtitle=item.subtitle?esc(item.subtitle):'';
     var options=Array.isArray(item.episode_options)?item.episode_options:[];
    var meta='<span>总观看 '+esc(item.watch_clock)+'</span>';
    if(item.together_ms>0)meta+='<span>一起看 '+esc(item.together_clock)+'</span>';
    if(item.solo_ms>0)meta+='<span>单人 '+esc(item.solo_clock)+'</span>';
    meta+='<span>最后位置 '+esc(item.position_clock)+'</span>';
    var progress='';
    if(item.duration_ms>0&&item.progress_pct>0){
      meta+='<span class="wh-progress-chip">已看 '+item.progress_pct+'%</span>';
      progress='<div class="watch-history-progress" aria-hidden="true"><i style="width:'+item.progress_pct+'%"></i></div>';
    }else{
      progress='<div class="watch-history-progress" aria-hidden="true"><i style="width:0"></i></div>';
    }
     var episodePicker='';
     if(options.length>1){
       episodePicker='<div class="watch-history-actions" onclick="event.stopPropagation()">'+
         '<label class="watch-history-episode-label" for="history-episode-'+esc(item.media_id)+'">选择集数</label>'+
         '<select class="watch-history-episode-select" id="history-episode-'+esc(item.media_id)+'" data-episode-picker>'+
         options.map(function(option){return '<option value="'+esc(option.url)+'" '+(option.is_last?'selected':'')+'>'+esc(option.rank_label||'最近观看')+' · '+esc(option.label)+'</option>';}).join('')+
         '</select></div>';
     }
     return '<div class="watch-history-item" data-play-url="'+esc(item.play_url||('/watch_play.php?media_id='+item.media_id))+'">'+
       '<div class="watch-history-cover">'+
         '<img loading="lazy" src="'+esc(item.cover_url)+'" data-initial="'+esc(title)+'" alt="'+title+'" onerror="coverFallback(this)">'+
       '</div>'+
       '<div class="watch-history-content">'+
         '<div><a class="watch-history-primary" href="'+esc(item.play_url||('/watch_play.php?media_id='+item.media_id))+'"><div class="watch-history-title">'+title+'</div></a>'+
         (subtitle?'<div class="watch-history-subtitle">'+subtitle+' · '+esc(item.started_at)+'</div>':'<div class="watch-history-subtitle">'+esc(item.started_at)+'</div>')+'</div>'+
         '<div><div class="watch-history-meta">'+meta+'</div>'+episodePicker+progress+'</div>'+
       '</div></div>';
  }
  function render(){
    var frag=document.createDocumentFragment();
    var end=Math.min(index+PAGE,items.length);
    for(var i=index;i<end;i++)frag.appendChild(htmlToNode(card(items[i])));
    list.appendChild(frag);
    index=end;
    loadMore.hidden=index>=items.length;
  }
  function htmlToNode(html){
    var t=document.createElement('template');t.innerHTML=html;return t.content.firstElementChild;
  }
  window.coverFallback=coverFallback;
  if(!items.length){
    list.insertAdjacentHTML('beforeend','<div class="watch-history-empty">暂无有效观影记录。看满一段时间后，这里才会显示对应影片。</div>');
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
