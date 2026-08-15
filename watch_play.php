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
require_once __DIR__ . '/core/MediaRecognition.php';

migrate_schema_if_needed();
$csrfToken = csrf_token();
$auth = new Auth();
$user = withu_require_couple_user($auth);
$partner = $auth->getPartner();
$partnerId = (int)($partner['id'] ?? 0);
$mediaId = (int)($_GET['media_id'] ?? 0);
$czMode = (($_GET['source'] ?? '') === 'cz');
$strmMode = (($_GET['source'] ?? '') === 'strm');
$media = $mediaId > 0 ? withu_media_fetch($mediaId) : null;
if (!$media && !$czMode && !$strmMode) { header('Location: /watch.php'); exit; }
$themeConfig = withu_theme_config();
$themeInlineStyle = '';
foreach (($themeConfig['colors'] ?? []) as $themeName => $themeValue) $themeInlineStyle .= '--withu-custom-' . $themeName . ':' . $themeValue . ';';
$watchPollIntervalMs = max(300, min(3000, (int)get_setting('watch_poll_interval_ms', '500')));
$watchSyncThresholdMs = max(500, min(5000, (int)get_setting('watch_sync_threshold_ms', '1000')));
$watchHeartbeatIntervalMs = max(1000, min(10000, (int)get_setting('watch_heartbeat_interval_ms', '2500')));
$watchAutoplayEnabled = (string)get_setting('watch_autoplay_enabled', '1') === '1';
$playerAutoNextEnabled = (string)get_setting('player_auto_next_enabled', '1') === '1';
$playerDefaultSpeed = trim((string)get_setting('player_default_speed', '1'));
if (!in_array($playerDefaultSpeed, ['0.75', '1', '1.25', '1.5', '2'], true)) $playerDefaultSpeed = '1';
$playerLogoSetting = trim((string)get_setting('player_logo_image', ''));
$playerLogoUrl = $playerLogoSetting !== '' ? upload_url($playerLogoSetting) : '/assets/images/withu-logo.png';
$playerLogoBgStyle = withu_player_logo_bg_style();
$playerLoadBackground = trim((string)get_setting('art_player_load_bg', '/assets/admin-art/js/bjt.jpg'));
$initialResolveUrl = ($czMode || $strmMode) ? '' : withu_media_resolve_url($media);
// ---- cz 厂长资源分支：原播放界面新增 cz 源（source=cz&url=4kcz.com 详情页） ----
$czLines = [];
$czDetailUrl = '';
if ($czMode) {
    require_once __DIR__ . '/core/CzSource.php';
    require_once __DIR__ . '/core/CzCatalog.php';
    $czDetailUrl = trim((string)($_GET['url'] ?? ''));
    if ($czDetailUrl === '' || strpos($czDetailUrl, '4kcz.com') === false) {
        header('Location: /watch.php'); exit;
    }
    try {
        $db2 = Database::getInstance();
        $czDetail = withu_cz_catalog_refresh_detail($db2, $czDetailUrl);
        $czTitle = (string)($czDetail['title'] ?? '');
        $czRawLines = $czDetail['vplays'] ?? [];
        $czLines = [];
        $czIdx = 1;
        foreach ($czRawLines as $czLine) {
            $czVp = (string)($czLine['url'] ?? '');
            if ($czVp === '') continue;
            $czLines[] = [
                'n' => $czIdx,
                'label' => (string)($czLine['label'] ?? '') !== '' ? (string)$czLine['label'] : ('第 ' . $czIdx . ' 集'),
                'vplay' => $czVp,
            ];
            $czIdx++;
        }
        // 伪 media：复用原界面模板（id=0 表示非媒体库）
        $czMeta = array_merge([
            'title' => $czTitle, 'poster' => '', 'director' => '', 'writer' => '', 'actors' => '',
            'year' => '', 'area' => '', 'types' => [], 'aka' => '', 'release' => '', 'lang' => '',
            'intro' => '', 'rating' => '', 'episode_status' => '',
        ], is_array($czDetail['meta'] ?? null) ? $czDetail['meta'] : []);
        $czMeta['title'] = $czTitle !== '' ? $czTitle : ((string)($czMeta['title'] ?? '') !== '' ? (string)$czMeta['title'] : 'cz 资源');
        $czIntro = trim((string)($czMeta['intro'] ?? ''));
        $czTypes = array_values(array_filter((array)($czMeta['types'] ?? []), 'strlen'));
        $czFacts = array_values(array_filter([
            (string)($czMeta['year'] ?? ''),
            (string)($czMeta['area'] ?? ''),
            implode('、', $czTypes),
            (string)($czMeta['lang'] ?? ''),
        ], 'strlen'));
        $czSummary = $czIntro !== '' ? $czIntro : ('厂长资源(4kcz.com) · 编码 cz · 共 ' . count($czLines) . ' 集');
        if ($czFacts) $czSummary = implode(' / ', $czFacts) . "
" . $czSummary;
        $media = [
            'id' => 0,
            'file_name' => $czTitle !== '' ? $czTitle : 'cz 资源',
            'series_name' => $czTitle !== '' ? $czTitle : 'cz 资源',
            'summary' => $czSummary,
            'resolution' => '',
        ];
        $mediaId = 0;
        $initialResolveUrl = '';
    } catch (Throwable $e) {
        header('Location: /watch.php'); exit;
    }
}// ---- withUstrm 媒体库分支：source=strm&id=<媒体id> ----
$strmMode = (($_GET['source'] ?? '') === 'strm');
if ($strmMode) {
    $strmMediaId = (int)($_GET['id'] ?? 0);
    if ($strmMediaId <= 0) { header('Location: /watch.php'); exit; }
    $strmConfPath = dirname(__DIR__, 1) . '/runtime/strm/config/systemconf.json';
    $strmKey = '';
    if (is_file($strmConfPath)) {
        try {
            $strmConf = json_decode((string)file_get_contents($strmConfPath), true);
            $strmKey = (string)($strmConf['external']['apiKey'] ?? '');
        } catch (Throwable $e) { $strmKey = ''; }
    }
    if ($strmKey === '') { header('Location: /watch.php'); exit; }
    $strmCh = curl_init('http://127.0.0.1:8080/api/external/media/' . $strmMediaId);
    curl_setopt_array($strmCh, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['X-API-Key: ' . $strmKey],
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $strmBody = curl_exec($strmCh);
    $strmCode = (int)curl_getinfo($strmCh, CURLINFO_RESPONSE_CODE);
    curl_close($strmCh);
    $strmMeta = $strmCode === 200 ? (json_decode((string)$strmBody, true) ?: []) : [];
    if (!$strmMeta) { header('Location: /watch.php'); exit; }
    $strmTitle = (string)($strmMeta['name'] ?? 'strm 媒体');
    $strmSummary = trim((string)($strmMeta['overview'] ?? ''));
    $media = [
        'id' => 0,
        'file_name' => $strmTitle,
        'series_name' => $strmTitle,
        'summary' => $strmSummary !== '' ? $strmSummary : 'withUstrm 媒体库 · 编码 strm',
        'resolution' => '',
    ];
    $mediaId = 0;
    $initialResolveUrl = '';
}

?>
<!doctype html>
<html lang="zh-CN" data-withu-theme="<?php echo e($themeConfig['preset']); ?>" data-withu-mode="<?php echo e($themeConfig['mode']); ?>"<?php if (!empty($themeConfig['custom'])): ?> data-withu-theme-custom="1" style="<?php echo e($themeInlineStyle); ?>"<?php endif; ?>>
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo e($media['series_name'] ?: $media['file_name']); ?> - withU</title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="/assets/css/theme.css?v=withu-theme-20260719-3">
<style>
.player-shell{max-width:1480px;margin:0 auto;padding:1rem}.player-top{display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1rem}.player-top h1{margin:0;font-size:1.4rem}.player-top p{margin:.35rem 0 0;color:#64748b}.player-actions{display:flex;gap:.5rem;flex-wrap:wrap}.player-layout{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:1rem}.player-stage{background:#10131a;border-radius:8px;padding:1rem}.player-gesture{position:relative;touch-action:none}.player-container{width:100%;aspect-ratio:16/9;background:#000;border-radius:5px;overflow:hidden}.player-container .artplayer-app{width:100%;height:100%}.player-container .artplayer-app video{width:100%;height:100%;object-fit:contain}.player-info{display:flex;align-items:center;gap:1rem;flex-wrap:wrap;color:#e5e7eb;margin-top:.75rem;font-size:.9rem}.player-status{margin:.75rem 0;padding:.7rem 1rem;border-radius:6px;background:#f3f4f6;color:#334155}.heart-status{display:inline-flex;align-items:center;gap:.3rem;color:#94a3b8}.heart-status .heart{font-size:1.1rem;transition:color .2s,transform .2s}.heart-status .link{width:28px;height:2px;background:#cbd5e1;transition:background .2s}.heart-status.connected{color:#ec4899}.heart-status.connected .heart{color:#ec4899;transform:scale(1.12)}.heart-status.connected .link{background:#ec4899}.episode-panel{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:1rem;max-height:72vh;overflow:auto}.episode-panel h2{font-size:1rem;margin:0 0 .8rem}.episode-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.45rem}.episode-btn{min-height:34px;border:1px solid #dbe1ea;background:#fff;border-radius:5px;cursor:pointer;font-size:.8rem;padding:.3rem;transition:border-color .18s,background .18s,color .18s,transform .18s,box-shadow .18s}.episode-btn:hover,.episode-btn.active{border-color:#ec4899;color:#ec4899;background:#fff5fa;box-shadow:0 3px 10px rgba(236,72,153,.12)}.episode-btn:active{transform:scale(.97)}.withu-episode-overlay{position:absolute;right:1rem;bottom:4rem;z-index:9999!important;width:min(360px,calc(100% - 2rem));max-height:min(68vh,560px);padding:.75rem;background:rgba(15,23,42,.97);border:1px solid rgba(255,255,255,.16);border-radius:7px;overflow:hidden;box-shadow:0 16px 48px rgba(0,0,0,.45);pointer-events:none;opacity:0;visibility:hidden;transform:translateY(12px) scale(.98);transform-origin:bottom right;transition:opacity .22s ease,transform .22s ease,visibility 0s linear .22s}.withu-episode-overlay.is-open{pointer-events:auto;opacity:1;visibility:visible;transform:translateY(0) scale(1);transition-delay:0s}.withu-episode-overlay h3{margin:0 0 .55rem;color:#fff;font-size:.9rem}.withu-episode-list{display:flex;flex-direction:column;gap:.4rem}.withu-episode-list .episode-btn{width:100%;min-height:34px;text-align:left;border-color:#475569;background:#1e293b;color:#f8fafc;opacity:0;transform:translateY(7px)}.withu-episode-overlay.is-open .withu-episode-list .episode-btn{animation:withu-episode-in .24s ease forwards}.withu-episode-overlay.is-open .withu-episode-list .episode-btn:nth-child(2){animation-delay:.025s}.withu-episode-overlay.is-open .withu-episode-list .episode-btn:nth-child(3){animation-delay:.05s}.withu-episode-overlay.is-open .withu-episode-list .episode-btn:nth-child(4){animation-delay:.075s}.withu-episode-overlay.is-open .withu-episode-list .episode-btn:nth-child(5){animation-delay:.1s}.withu-episode-overlay.is-open .withu-episode-list .episode-btn:nth-child(6){animation-delay:.125s}.withu-episode-overlay.is-open .withu-episode-list .episode-btn:nth-child(7){animation-delay:.15s}.withu-episode-overlay.is-open .withu-episode-list .episode-btn:nth-child(8){animation-delay:.175s}.withu-episode-overlay.is-open .withu-episode-list .episode-btn:nth-child(9){animation-delay:.2s}.withu-episode-overlay.is-open .withu-episode-list .episode-btn:nth-child(10){animation-delay:.225s}.withu-episode-list .episode-btn.active{border-color:#ec4899;background:#4c1d3b;color:#fff}.withu-episode-select{width:100%;margin-top:.5rem;min-height:34px;border:1px solid #475569;border-radius:4px;background:#1e293b;color:#fff;padding:.25rem}.watch-choice{position:fixed;inset:0;z-index:50;display:flex;align-items:center;justify-content:center;padding:1rem;background:#0f172a80}.watch-choice[hidden]{display:none}.watch-choice-box{width:min(420px,100%);padding:1.25rem;border-radius:8px;background:#fff;box-shadow:0 20px 60px #0f172a4d}.watch-choice-actions{display:flex;gap:.6rem;flex-wrap:wrap;margin-top:1rem}.gesture-value{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);color:#fff;background:#111827cc;padding:.5rem .75rem;border-radius:6px;pointer-events:none;z-index:40}.player-hint{color:#64748b;font-size:.82rem;margin:.6rem 0}@keyframes withu-episode-in{to{opacity:1;transform:translateY(0)}}@media(max-width:980px){.player-layout{grid-template-columns:minmax(0,1fr) 320px}}@media(max-width:850px){.player-layout{grid-template-columns:1fr}.episode-panel{max-height:none}.episode-list{grid-template-columns:repeat(5,minmax(0,1fr))}}
@media (max-width:760px){.withu-episode-overlay .withu-episode-list,.withu-episode-list{grid-template-columns:repeat(2,minmax(0,1fr))!important}.withu-episode-overlay{width:min(240px,calc(100% - 1rem))!important}}
</style>
<style id="withu-player-overrides">
 .gesture-value{display:none}
 .gesture-value:not(:empty){display:block}
 :root{--withu-ease-out:cubic-bezier(.23,1,.32,1);--withu-ease-in-out:cubic-bezier(.77,0,.175,1);--withu-ui-fast:160ms;--withu-ui-pop:200ms;--withu-player-radius:30px;--withu-player-inner-radius:26px;--withu-player-control-radius:18px}
 .player-container{position:relative}
 .player-container,.player-container .artplayer-app,.player-container .art-video-player{border-radius:var(--withu-player-radius)!important;overflow:hidden!important;background:#000!important;clip-path:inset(0 round var(--withu-player-radius))!important}
 .player-container .art-video,.player-container .art-video video,.player-container video{border-radius:var(--withu-player-inner-radius)!important;overflow:hidden!important}
 .art-video-player .art-mask,.art-video-player .art-poster,.art-video-player .art-layers,.art-video-player .art-subtitle{border-radius:inherit!important;overflow:hidden!important}
 .art-video-player .art-bottom{left:.7rem!important;right:.7rem!important;bottom:.65rem!important;width:auto!important;overflow:visible!important}
 .art-video-player .art-bottom .art-progress{border-radius:999px!important;overflow:visible!important}
 .art-video-player .art-bottom .art-controls{border-radius:var(--withu-player-control-radius)!important;overflow:hidden!important}
 .player-watermark{position:absolute;left:.7rem;top:.65rem;z-index:10002;display:flex;align-items:center;gap:.45rem;pointer-events:none;filter:drop-shadow(0 2px 5px rgba(0,0,0,.35))}
 .watermark-mark{position:relative;display:inline-flex;width:48px;height:44px;align-items:center;justify-content:center}
 .watermark-mark img{display:block;width:48px;height:44px;object-fit:contain;background:var(--withu-player-logo-bg,#f5b6c8);border-radius:8px}
 .watermark-heart{position:absolute;right:-.08rem;top:-.45rem;color:#ff4f9a;font-size:1.15rem;line-height:1;text-shadow:0 1px 4px rgba(0,0,0,.55);opacity:0;transform:scale(.7);transition:opacity .2s,transform .2s}
 .watermark-mark.is-online .watermark-heart{opacity:1;transform:scale(1)}
 .watermark-online{padding:.28rem .55rem;border:1px solid rgba(255,255,255,.25);border-radius:999px;background:rgba(15,23,42,.58);color:#fff;font-size:.76rem;white-space:nowrap;backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px)}
 .art-video-player .art-bottom .art-progress{padding-top:8px;padding-bottom:8px}
 .art-video-player .art-control-progress-inner{height:7px;border:1px solid rgba(255,255,255,.3);border-radius:999px;background:rgba(255,255,255,.2);box-shadow:inset 0 1px 2px rgba(0,0,0,.28),0 2px 12px rgba(0,0,0,.18);backdrop-filter:blur(14px) saturate(155%);-webkit-backdrop-filter:blur(14px) saturate(155%);overflow:visible}
 .art-video-player .art-progress-loaded{height:100%;border-radius:inherit;background:rgba(255,255,255,.28)}
 .art-video-player .art-progress-played{height:100%;border-radius:inherit;background:rgba(236,72,153,.9);box-shadow:0 0 12px rgba(236,72,153,.45)}
 .art-video-player .art-progress-hover{height:100%;border-radius:inherit;background:rgba(255,255,255,.48)}
 .art-video-player .art-progress-indicator{width:14px;height:14px;margin-top:-3px;border:2px solid rgba(255,255,255,.95);border-radius:50%;background:rgba(236,72,153,.95);box-shadow:0 2px 10px rgba(0,0,0,.35),0 0 0 3px rgba(255,255,255,.16);transform:translateX(-50%)}
 .art-video-player .art-progress-tip{border:1px solid rgba(255,255,255,.3);border-radius:6px;background:rgba(15,23,42,.72);box-shadow:0 8px 24px rgba(0,0,0,.25);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px)}
 .art-video-player .art-bottom .art-controls{margin:0 .35rem .35rem;padding:0 .35rem;}
 .art-video-player .art-bottom .art-controls .art-control{text-shadow:0 1px 3px rgba(0,0,0,.35)}
 .player-top{position:relative}
 .media-search-wrap{position:relative;display:flex;align-items:center;min-width:min(250px,42vw)}
 .media-search-wrap input{width:100%;height:38px;padding:.45rem 2rem .45rem 2.1rem;border:1px solid #d7dce5;border-radius:999px;background:rgba(255,255,255,.88);color:#1f2937;outline:none;box-shadow:0 5px 18px rgba(15,23,42,.08)}
 .media-search-wrap input:focus{border-color:#ec4899;box-shadow:0 0 0 3px rgba(236,72,153,.14)}
 .media-search-icon{position:absolute;left:.8rem;color:#64748b;font-size:1.15rem;line-height:1;pointer-events:none}
 .media-search-results{position:absolute;left:0;right:0;top:calc(100% + .45rem);z-index:10040;display:grid;gap:.3rem;padding:.45rem;border:1px solid rgba(255,255,255,.5);border-radius:10px;background:rgba(255,255,255,.95);box-shadow:0 18px 45px rgba(15,23,42,.2);backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px)}
 .media-search-results[hidden]{display:none}
 .media-search-result{display:flex;align-items:center;gap:.55rem;width:100%;padding:.45rem .55rem;border:0;border-radius:6px;background:transparent;color:#1f2937;text-align:left;cursor:pointer}
 .media-search-result:hover{background:#fff0f7;color:#be185d}
 .media-search-result small{display:block;color:#64748b;font-size:.72rem}
 .sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
.recommend-panel{margin-top:1rem;padding:1rem 1.1rem;background:#151a23;border:1px solid #283142;border-radius:8px;color:#e5e7eb}
.recommend-panel-header{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:.75rem}
.recommend-panel-titleline{display:flex;align-items:center;gap:.6rem;min-width:0}
.recommend-panel-header h2{margin:0;font-size:1rem;color:#fff}
.recommend-more{display:inline-flex;align-items:center;justify-content:center;height:26px;padding:0 .62rem;border:1px solid rgba(236,72,153,.34);border-radius:999px;background:rgba(236,72,153,.12);color:#f9a8d4;font-size:.74rem;font-weight:800;text-decoration:none;white-space:nowrap;transition:transform .18s,border-color .18s,background .18s,color .18s,box-shadow .18s}
.recommend-more:hover{transform:translateY(-1px);border-color:rgba(236,72,153,.68);background:rgba(236,72,153,.22);color:#fff;box-shadow:0 8px 22px rgba(236,72,153,.18)}
.recommend-panel-footer{display:flex;justify-content:flex-end;margin-top:.9rem}
 .recommend-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:.85rem}
 .recommend-card{display:flex;flex-direction:column;align-items:stretch;gap:.55rem;min-width:0;padding:.62rem;border:1px solid #334155;border-radius:6px;background:#101722;color:#e5e7eb;text-decoration:none;transition:border-color .18s,background .18s,transform .18s}
 .recommend-card:hover{border-color:#ec4899;background:#1d2432;transform:translateY(-1px)}
 .recommend-card img{width:100%;height:100%;object-fit:cover;border-radius:4px;background:#0b0f14}
 .recommend-card-copy{display:block;min-width:0;text-align:center}
 .recommend-card-title{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.92rem;color:#fff}
 .recommend-card-meta{display:none}
 .player-layout{grid-template-columns:minmax(0,1fr);align-items:start}
 .episode-panel{display:none}
 .episode-launcher{position:relative;z-index:10001;display:inline-flex;align-items:center;gap:.35rem;min-height:38px;padding:.5rem .65rem;border:1px solid rgba(255,255,255,.28);border-radius:999px;background:rgba(24,32,46,.58);color:#f8fafc;box-shadow:0 10px 30px rgba(0,0,0,.28),inset 0 1px 0 rgba(255,255,255,.18);backdrop-filter:blur(18px) saturate(150%);-webkit-backdrop-filter:blur(18px) saturate(150%);cursor:pointer;transition:background .2s,border-color .2s,box-shadow .2s,opacity .2s}
 .episode-launcher:hover,.episode-launcher[aria-expanded="true"]{background:rgba(236,72,153,.62);border-color:rgba(255,255,255,.48);box-shadow:0 12px 34px rgba(236,72,153,.28),inset 0 1px 0 rgba(255,255,255,.24)}
 .episode-launcher-icon{font-size:1rem;line-height:1}
 .episode-launcher-label{font-size:.78rem;white-space:nowrap}
 .media-detail{display:grid;grid-template-columns:minmax(0,1fr) 148px;gap:1.25rem;margin-top:1rem;padding:1.2rem 1.25rem;background:#151a23;border:1px solid #283142;border-radius:8px;color:#e5e7eb}
 .media-detail-copy{min-width:0}
 .media-detail-kicker{font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:#ec4899}
 .media-detail h2{margin:.35rem 0 .55rem;font-size:1.2rem;color:#fff}
 .media-detail-facts{display:flex;gap:.45rem;flex-wrap:wrap;color:#cbd5e1;font-size:.85rem}
 .media-detail-facts span{padding:.2rem .45rem;background:#202938;border-radius:4px}
 .media-detail-summary{margin:.8rem 0 0;color:#aeb8c7;line-height:1.75;white-space:pre-line}
 .media-detail-poster{width:148px;aspect-ratio:2/3;object-fit:cover;border-radius:5px;background:#0b0f14}
 .withu-episode-overlay{right:.6rem;top:1rem;bottom:3.2rem;width:min(204px,calc(100% - 1.2rem));max-height:calc(100% - 5.2rem);padding:.55rem;overflow:hidden;scrollbar-gutter:stable;background:rgba(20,29,43,.56);border-color:rgba(255,255,255,.26);box-shadow:0 18px 55px rgba(0,0,0,.42),inset 0 1px 0 rgba(255,255,255,.18);backdrop-filter:blur(22px) saturate(145%);-webkit-backdrop-filter:blur(22px) saturate(145%)}
 .withu-episode-overlay{right:.6rem;top:1rem;bottom:3.2rem;width:min(204px,calc(100% - 1.2rem));max-height:calc(100% - 5.2rem);padding:.55rem;overflow:hidden;scrollbar-gutter:stable;background:rgba(255,255,255,.16);border-color:rgba(255,255,255,.46);box-shadow:0 18px 55px rgba(0,0,0,.46),inset 0 1px 0 rgba(255,255,255,.38),inset 0 -1px 0 rgba(255,255,255,.1);backdrop-filter:blur(24px) saturate(175%) contrast(108%);-webkit-backdrop-filter:blur(24px) saturate(175%) contrast(108%);isolation:isolate}
  .art-video-player .art-bottom{overflow:visible}
  .art-video-player .art-info{left:50%!important;right:auto!important;top:50%!important;transform:translate(-50%,-50%);max-width:calc(100% - 2rem);box-sizing:border-box}
 .withu-episode-overlay{left:0;right:0;top:auto;bottom:calc(var(--art-control-height) + var(--art-progress-height) + var(--art-progress-top-gap) + .35rem);width:auto;max-height:min(30vh,260px);transform:translateY(12px) scale(.98);transform-origin:bottom center}
 .withu-episode-overlay.is-open{transform:translateY(0) scale(1)}
 .withu-episode-overlay h3{margin:.1rem .2rem .45rem;font-size:.78rem}
 .withu-episode-overlay h3{text-align:center}
.withu-episode-list{gap:.25rem;min-height:0;overflow:visible;overscroll-behavior:contain}
 .withu-episode-list .episode-btn{min-height:0;flex:1 1 auto;height:auto;padding:.1rem .2rem;font-size:.68rem;line-height:1.1;text-align:center}
 .withu-episode-select{display:none}
.player-layout{--withu-player-height:auto;grid-template-columns:minmax(0,1fr) minmax(220px,320px);align-items:start}
 .player-stage{min-width:0}
.episode-panel{display:flex;flex-direction:column;justify-content:flex-start;align-items:stretch;grid-column:2;grid-row:1;min-width:0;min-height:0;height:var(--withu-player-height);max-height:var(--withu-player-height);box-sizing:border-box;margin-top:0;padding:1rem 1.1rem;overflow:hidden;background:#151a23;border-color:#283142;color:#e5e7eb;text-align:center}
 .episode-panel-header{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.65rem;flex-wrap:wrap;margin-bottom:.8rem}
 .episode-panel-header h2{margin:0;font-size:1rem;color:#fff;text-align:center}
 .episode-panel-controls{display:flex;align-items:center;justify-content:center;gap:.65rem;flex-wrap:wrap}
 .episode-control-group{display:inline-flex;align-items:center;gap:.2rem;padding:.2rem;border:1px solid #334155;border-radius:6px;background:#101722}
 .episode-toggle{display:inline-flex;align-items:center;justify-content:center;gap:.2rem;min-height:40px;padding:.2rem;border:1px solid #334155;border-radius:14px;background:#101722;color:#cbd5e1;cursor:pointer}
 .episode-toggle-label{margin:0 .2rem;color:#94a3b8;font-size:.72rem}
 .episode-toggle-option{display:inline-flex;align-items:center;justify-content:center;min-width:44px;min-height:30px;padding:.2rem .6rem;border-radius:999px;color:#cbd5e1;font-size:.75rem;font-weight:700;line-height:1}
 .episode-toggle-option.is-active{background:#ec4899;color:#fff}
 .episode-control-label{margin:0 .2rem;color:#94a3b8;font-size:.72rem}
 .episode-control{min-height:28px;padding:.25rem .55rem;border:0;border-radius:4px;background:transparent;color:#cbd5e1;font-size:.75rem;cursor:pointer}
 .episode-control:hover,.episode-control.is-active{background:#ec4899;color:#fff}
.episode-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.45rem;flex:1 1 auto;min-height:0;max-height:none;overflow-y:auto;overflow-x:hidden;overscroll-behavior:contain;scrollbar-gutter:stable}
 .episode-list.is-single{grid-template-columns:minmax(0,1fr)}
 .episode-list .episode-btn{text-align:center}
 .art-video-player.art-fullscreen .withu-episode-overlay,.art-video-player.art-fullscreen-web .withu-episode-overlay{width:min(320px,34vw);max-height:calc(100% - 5.2rem)}
 .art-video-player.art-fullscreen .episode-launcher,.art-video-player.art-fullscreen-web .episode-launcher{z-index:10030}
 .art-video-player.art-fullscreen .withu-episode-overlay,.art-video-player.art-fullscreen-web .withu-episode-overlay{left:50%;right:auto;top:auto;bottom:3.35rem;width:min(760px,calc(100% - 2rem))!important;max-height:min(28vh,240px);transform:translate(-50%,12px) scale(.98);transform-origin:bottom center}
 .art-video-player.art-fullscreen .withu-episode-overlay.is-open,.art-video-player.art-fullscreen-web .withu-episode-overlay.is-open{transform:translate(-50%,0) scale(1)}
 .art-video-player.art-fullscreen .withu-episode-list,.art-video-player.art-fullscreen-web .withu-episode-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(86px,1fr));gap:.3rem}
 .art-video-player.art-fullscreen .episode-launcher,.art-video-player.art-fullscreen-web .episode-launcher{top:auto;right:auto;bottom:auto;transform:none}
 .art-video-player.art-hide-cursor .episode-launcher{opacity:0;pointer-events:none}
 .withu-speed-control{display:inline-flex;align-items:center;justify-content:center;min-width:2.4rem;font-size:.78rem;font-weight:700;color:#f8fafc}
 .art-video-player.art-fullscreen .art-bottom,.art-video-player.art-fullscreen-web .art-bottom{overflow:visible}
 .art-video-player.art-fullscreen .withu-episode-overlay,.art-video-player.art-fullscreen-web .withu-episode-overlay{top:auto;right:auto;width:min(210px,calc(100% - 1rem));max-height:min(75vh,620px);padding:.65rem .55rem .55rem;transform-origin:bottom center}
 .art-video-player.art-fullscreen .withu-episode-overlay .withu-episode-list,.art-video-player.art-fullscreen-web .withu-episode-overlay .withu-episode-list{display:flex;flex-direction:column;gap:.28rem}
 .art-video-player.art-fullscreen .withu-episode-overlay .episode-btn,.art-video-player.art-fullscreen-web .withu-episode-overlay .episode-btn{min-height:32px}
 @media(max-width:850px){.art-video-player.art-fullscreen .withu-episode-overlay,.art-video-player.art-fullscreen-web .withu-episode-overlay{top:auto;right:auto;width:min(176px,calc(100% - 1rem));max-height:52vh}}
 @media(max-width:980px){.player-layout{grid-template-columns:minmax(0,1fr) minmax(210px,280px)}}
 @media(max-width:820px){.player-layout{grid-template-columns:1fr}.episode-panel{grid-column:1;grid-row:auto;height:var(--withu-player-height);max-height:var(--withu-player-height);margin-top:1rem}.episode-list{grid-template-columns:repeat(2,minmax(0,1fr));max-height:none}}
 @media(max-width:850px){.player-layout{grid-template-columns:1fr}.episode-panel{grid-column:1;grid-row:auto;height:var(--withu-player-height);max-height:var(--withu-player-height);margin-top:1rem}.media-detail{grid-template-columns:minmax(0,1fr) 92px}.media-detail-poster{width:92px}.episode-launcher{right:.45rem}.withu-episode-overlay{width:min(176px,calc(100% - 1rem));max-height:52vh}}
 @media(max-width:760px){.player-actions{width:100%}.media-search-wrap{flex:1;min-width:180px}.recommend-list{grid-template-columns:repeat(2,minmax(0,1fr))}}
 @media(max-width:520px){.media-search-wrap{min-width:0;width:100%}.player-actions{display:grid;grid-template-columns:1fr 1fr}.media-search-wrap{grid-column:1 / -1}.player-actions .btn{width:100%}.player-icon-action{width:100%}.recommend-list{grid-template-columns:1fr}}
 .withu-episode-overlay.is-open.is-anchored{animation:withu-episode-pop .24s cubic-bezier(.2,.8,.2,1) both}
 @keyframes withu-episode-pop{from{opacity:0;transform:translateY(14px) scale(.94)}to{opacity:1;transform:translateY(0) scale(1)}}
.withu-episode-list,.art-video-player.art-fullscreen .withu-episode-list,.art-video-player.art-fullscreen-web .withu-episode-list{display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr))!important;flex:1 1 auto!important;min-height:0!important;align-content:start!important;gap:.32rem!important;overflow-y:auto!important;overscroll-behavior:contain!important;scrollbar-gutter:stable!important}
  .withu-speed-menu{position:absolute;right:.45rem;bottom:calc(var(--art-control-height) + var(--art-progress-height) + var(--art-progress-top-gap) + .35rem);z-index:10060;display:flex;flex-direction:column;gap:.2rem;width:126px;padding:.45rem;border:1px solid rgba(255,255,255,.35);border-radius:9px;background:rgba(15,23,42,.76);box-shadow:0 14px 36px rgba(0,0,0,.35),inset 0 1px 0 rgba(255,255,255,.2);backdrop-filter:blur(18px) saturate(150%);-webkit-backdrop-filter:blur(18px) saturate(150%)}
 .withu-speed-menu[hidden]{display:none}
 .withu-speed-menu-title{padding:.2rem .3rem .35rem;color:#cbd5e1;font-size:.7rem;text-align:center}
  .withu-speed-option{min-height:28px;border:1px solid transparent;border-radius:5px;background:transparent;color:#f8fafc;font-size:.78rem;cursor:pointer}
  .withu-speed-option:hover,.withu-speed-option.is-active{border-color:rgba(236,72,153,.6);background:rgba(236,72,153,.72);color:#fff}
  .withu-speed-custom{display:flex;align-items:center;gap:.25rem;margin-top:.2rem;padding-top:.35rem;border-top:1px solid rgba(255,255,255,.16)}
  .withu-speed-custom-input{width:0;min-width:0;flex:1 1 auto;height:28px;padding:0 .35rem;border:1px solid rgba(255,255,255,.22);border-radius:5px;background:rgba(15,23,42,.56);color:#fff;font-size:.72rem;text-align:center;outline:none}
  .withu-speed-custom-input:focus{border-color:rgba(236,72,153,.75);box-shadow:0 0 0 2px rgba(236,72,153,.16)}
  .withu-speed-custom-apply{flex:0 0 auto;min-height:28px;padding:0 .38rem;border:1px solid rgba(255,255,255,.2);border-radius:5px;background:rgba(236,72,153,.72);color:#fff;font-size:.7rem;cursor:pointer}
  .withu-speed-custom-apply:hover{background:rgba(236,72,153,.92)}
  .withu-speed-menu,.withu-speed-menu *{pointer-events:auto!important}
  .withu-speed-option:focus-visible,.withu-speed-custom-input:focus-visible,.withu-speed-custom-apply:focus-visible{outline:2px solid rgba(255,255,255,.9);outline-offset:2px}
  .withu-speed-option:active,.withu-speed-custom-apply:active{transform:translateY(1px)}
  .withu-episode-overlay.is-open .withu-episode-list .episode-btn:hover,.withu-episode-overlay.is-open .withu-episode-list .episode-btn:focus-visible{border-color:rgba(245,182,200,.9)!important;background:linear-gradient(135deg,rgba(245,182,200,.72),rgba(228,134,164,.72))!important;color:#fff!important;box-shadow:0 8px 22px rgba(228,134,164,.3),inset 0 1px 0 rgba(255,255,255,.25)!important;transform:translateY(-1px)}
  .withu-episode-overlay.is-open .withu-episode-list .episode-btn:focus-visible{outline:2px solid rgba(255,255,255,.92);outline-offset:2px}
  .withu-switch-loading{position:absolute;inset:0;z-index:10040;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.46);pointer-events:auto}
  .withu-switch-loading[hidden]{display:none}
  .withu-switch-loading-box{display:flex;align-items:center;gap:.6rem;padding:.65rem .9rem;border:1px solid rgba(255,255,255,.28);border-radius:999px;background:rgba(15,23,42,.78);box-shadow:0 12px 32px rgba(0,0,0,.3);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);color:#fff;font-size:.82rem;font-weight:700}
  .withu-switch-loading-spinner{width:16px;height:16px;border:2px solid rgba(255,255,255,.32);border-top-color:#f5b6c8;border-radius:50%;animation:withu-switch-spin .72s linear infinite}
  @keyframes withu-switch-spin{to{transform:rotate(360deg)}}
  .player-container .art-video-player{position:relative}
  .player-container .player-watermark{position:absolute;left:.8rem;top:.75rem;z-index:10050;display:flex;align-items:center;gap:.45rem;pointer-events:none;opacity:1;visibility:visible;transform:translateZ(0)}
  .player-container .watermark-mark{width:42px;height:38px;padding:.2rem;border:1px solid rgba(255,255,255,.42);border-radius:11px;background:transparent;box-shadow:0 5px 18px rgba(0,0,0,.28)}
  .player-container .watermark-mark img{width:38px;height:34px;object-fit:contain;background:var(--withu-player-logo-bg,#f5b6c8);border-radius:7px}
  .player-container .watermark-heart{right:-.24rem;top:-.55rem;font-size:1rem;color:#ff5b9f;text-shadow:0 1px 6px rgba(0,0,0,.7);filter:drop-shadow(0 0 5px rgba(255,91,159,.55))}
  .player-container .watermark-online{padding:.3rem .58rem;border:1px solid rgba(255,255,255,.3);border-radius:999px;background:rgba(15,23,42,.52);color:#fff;font-size:.75rem;line-height:1;white-space:nowrap;box-shadow:0 5px 18px rgba(0,0,0,.2);backdrop-filter:blur(16px) saturate(155%);-webkit-backdrop-filter:blur(16px)}
  .player-container .watermark-online::before{content:"";display:inline-block;width:6px;height:6px;margin-right:.35rem;border-radius:50%;background:#fb7185;box-shadow:0 0 0 3px rgba(251,113,133,.16)}
  .player-container .player-watermark.partner-online .watermark-online::before{background:#4ade80;box-shadow:0 0 0 3px rgba(74,222,128,.16)}
  .player-container .player-watermark.partner-online .watermark-mark{border-color:rgba(255,91,159,.72);box-shadow:inset 0 1px 0 rgba(255,255,255,.45),0 0 22px rgba(255,91,159,.24)}
  .art-video-player .player-watermark{position:absolute;left:.8rem;top:.75rem;z-index:10050;display:flex;align-items:center;gap:.45rem;pointer-events:none;opacity:1;visibility:visible}
  .art-video-player .watermark-mark{width:42px;height:38px;padding:.2rem;border:1px solid rgba(255,255,255,.42);border-radius:11px;background:transparent;box-shadow:0 5px 18px rgba(0,0,0,.28)}
  .art-video-player .watermark-mark img{width:38px;height:34px;object-fit:contain;background:var(--withu-player-logo-bg,#f5b6c8);border-radius:7px}
  .art-video-player .watermark-heart{right:-.24rem;top:-.55rem;font-size:1rem;color:#ff5b9f;text-shadow:0 1px 6px rgba(0,0,0,.7);filter:drop-shadow(0 0 5px rgba(255,91,159,.55))}
  .art-video-player .watermark-online{padding:.3rem .58rem;border:1px solid rgba(255,255,255,.3);border-radius:999px;background:rgba(15,23,42,.52);color:#fff;font-size:.75rem;line-height:1;white-space:nowrap;box-shadow:0 5px 18px rgba(0,0,0,.2);backdrop-filter:blur(16px) saturate(155%);-webkit-backdrop-filter:blur(16px)}
  .art-video-player .watermark-online::before{content:"";display:inline-block;width:6px;height:6px;margin-right:.35rem;border-radius:50%;background:#fb7185;box-shadow:0 0 0 3px rgba(251,113,133,.16)}
  .art-video-player .player-watermark.partner-online .watermark-online::before{background:#4ade80;box-shadow:0 0 0 3px rgba(74,222,128,.16)}
  .art-video-player .player-watermark.partner-online .watermark-mark{border-color:rgba(255,91,159,.72);box-shadow:inset 0 1px 0 rgba(255,255,255,.45),0 0 22px rgba(255,91,159,.24)}
  .withu-watch-only-control[hidden]{display:none!important}
  .withu-voice-control,.withu-chat-control,.withu-side-chat-control{display:inline-flex;align-items:center;justify-content:center;min-width:2.65rem;height:30px;padding:0 .72rem;border:1px solid rgba(255,255,255,.28);border-radius:999px;background:rgba(24,32,46,.52);color:#f8fafc;font-size:.76rem;font-weight:700;letter-spacing:.02em;box-shadow:inset 0 1px 0 rgba(255,255,255,.16),0 8px 22px rgba(0,0,0,.2);backdrop-filter:blur(16px) saturate(155%);-webkit-backdrop-filter:blur(16px) saturate(155%);transition:transform var(--withu-ui-fast) var(--withu-ease-out),background var(--withu-ui-fast) var(--withu-ease-out),border-color var(--withu-ui-fast) var(--withu-ease-out),color var(--withu-ui-fast) var(--withu-ease-out),box-shadow var(--withu-ui-fast) var(--withu-ease-out)}
  .withu-watch-only-control:hover .withu-voice-control,.withu-watch-only-control:hover .withu-chat-control,.withu-watch-only-control:hover .withu-side-chat-control,.withu-watch-only-control.is-active .withu-voice-control,.withu-watch-only-control.is-active .withu-side-chat-control{border-color:rgba(255,255,255,.48);background:rgba(236,72,153,.72);color:#fff;box-shadow:0 10px 28px rgba(236,72,153,.26),inset 0 1px 0 rgba(255,255,255,.22)}
  .withu-chat-panel{position:absolute;right:.55rem;bottom:calc(var(--art-control-height) + var(--art-progress-height) + var(--art-progress-top-gap) + .48rem);z-index:10072;width:min(360px,calc(100% - 1.1rem));padding:.5rem;border:1px solid rgba(255,255,255,.38);border-radius:12px;background:rgba(15,23,42,.7);box-shadow:0 16px 44px rgba(0,0,0,.36),inset 0 1px 0 rgba(255,255,255,.2);backdrop-filter:blur(22px) saturate(170%);-webkit-backdrop-filter:blur(22px) saturate(170%);transform:translateY(8px) scale(.98);transform-origin:bottom right;opacity:0;pointer-events:none;transition:opacity 180ms var(--withu-ease-out),transform 180ms var(--withu-ease-out)}
  .withu-chat-panel.is-open{opacity:1;transform:translateY(0) scale(1);pointer-events:auto}
  .withu-chat-panel[hidden]{display:none!important}
  .withu-chat-form{display:flex;align-items:center;gap:.42rem}
  .withu-chat-input{min-width:0;flex:1;height:34px;padding:.45rem .75rem;border:1px solid rgba(255,255,255,.26);border-radius:999px;background:rgba(255,255,255,.12);color:#fff;outline:none;font-size:.82rem}
  .withu-chat-input::placeholder{color:rgba(255,255,255,.62)}
  .withu-chat-input:focus{border-color:rgba(236,72,153,.75);box-shadow:0 0 0 3px rgba(236,72,153,.18)}
  .withu-chat-send{height:34px;padding:0 .82rem;border:0;border-radius:999px;background:rgba(236,72,153,.9);color:#fff;font-size:.8rem;font-weight:700;cursor:pointer}
  .art-control-chat-button{width:auto!important;overflow:visible!important;padding:0 .18rem!important}
  .withu-danmaku-inline-form{display:inline-flex;align-items:center;gap:.28rem;width:min(240px,24vw);min-width:168px;height:30px;padding:.12rem .14rem .12rem .58rem;border:1px solid rgba(255,255,255,.28);border-radius:999px;background:rgba(24,32,46,.52);box-shadow:inset 0 1px 0 rgba(255,255,255,.16),0 8px 22px rgba(0,0,0,.2);backdrop-filter:blur(16px) saturate(155%);-webkit-backdrop-filter:blur(16px) saturate(155%);transition:transform var(--withu-ui-fast) var(--withu-ease-out),border-color var(--withu-ui-fast) var(--withu-ease-out),background var(--withu-ui-fast) var(--withu-ease-out),box-shadow var(--withu-ui-fast) var(--withu-ease-out)}
  .withu-danmaku-inline-form:focus-within{border-color:rgba(255,255,255,.52);background:rgba(15,23,42,.66);box-shadow:0 10px 28px rgba(236,72,153,.22),inset 0 1px 0 rgba(255,255,255,.2)}
  .withu-danmaku-inline-input{min-width:0;flex:1;height:24px;border:0;background:transparent;color:#fff;outline:none;font-size:.76rem;line-height:24px}
  .withu-danmaku-inline-input::placeholder{color:rgba(255,255,255,.62)}
  .withu-danmaku-inline-send{flex:0 0 auto;height:24px;padding:0 .56rem;border:0;border-radius:999px;background:rgba(236,72,153,.86);color:#fff;font-size:.72rem;font-weight:800;cursor:pointer;transition:transform 120ms var(--withu-ease-out),background 120ms var(--withu-ease-out)}
  .withu-danmaku-inline-send:hover{background:rgba(236,72,153,1)}
  .withu-danmaku-layer{position:absolute;left:0;right:0;top:10%;height:46%;z-index:10024;overflow:hidden;pointer-events:none}
  .withu-danmaku-item{position:absolute;right:-25%;max-width:min(52%,520px);padding:.28rem .68rem;border:1px solid rgba(255,255,255,.34);border-radius:999px;background:rgba(15,23,42,.55);color:#fff;font-size:.84rem;line-height:1.35;text-shadow:0 1px 3px rgba(0,0,0,.32);white-space:nowrap;box-shadow:0 8px 22px rgba(0,0,0,.22);backdrop-filter:blur(12px) saturate(150%);-webkit-backdrop-filter:blur(12px) saturate(150%);animation:withu-danmaku-fly 7s linear forwards}
  .withu-danmaku-item.is-mine{background:rgba(236,72,153,.62);border-color:rgba(255,255,255,.48)}
  .withu-side-chat-panel{position:absolute;right:.7rem;top:4.25rem;bottom:4.6rem;z-index:10065;display:flex;flex-direction:column;width:min(320px,36%);min-width:240px;border:1px solid rgba(255,255,255,.36);border-radius:16px;background:rgba(15,23,42,.68);box-shadow:0 18px 55px rgba(0,0,0,.42),inset 0 1px 0 rgba(255,255,255,.18);backdrop-filter:blur(24px) saturate(170%);-webkit-backdrop-filter:blur(24px) saturate(170%);opacity:0;pointer-events:none;transform:translateX(18px) scale(.985);transform-origin:right center;transition:opacity 220ms var(--withu-ease-out),transform 220ms var(--withu-ease-out)}
  .withu-side-chat-panel.is-open{opacity:1;pointer-events:auto;transform:translateX(0) scale(1)}
  .withu-side-chat-panel[hidden]{display:none!important}
  .withu-side-chat-header{display:flex;align-items:center;justify-content:space-between;gap:.7rem;padding:.72rem .82rem;border-bottom:1px solid rgba(255,255,255,.14);color:#fff;font-size:.88rem;font-weight:800}
  .withu-side-chat-close{width:28px;height:28px;border:1px solid rgba(255,255,255,.25);border-radius:999px;background:rgba(255,255,255,.1);color:#fff;cursor:pointer}
  .withu-side-chat-messages{flex:1;display:flex;flex-direction:column;gap:.5rem;padding:.72rem;overflow-y:auto;scrollbar-gutter:stable}
  .withu-side-chat-empty{margin:auto;color:rgba(255,255,255,.58);font-size:.8rem;text-align:center}
  .withu-side-chat-row{display:flex;flex-direction:column;align-items:flex-start;gap:.18rem}
  .withu-side-chat-row.is-mine{align-items:flex-end}
  .withu-side-chat-name{color:rgba(255,255,255,.58);font-size:.68rem}
  .withu-side-chat-bubble{max-width:88%;padding:.42rem .62rem;border:1px solid rgba(255,255,255,.18);border-radius:12px 12px 12px 4px;background:rgba(255,255,255,.12);color:#fff;font-size:.8rem;line-height:1.45;word-break:break-word}
  .withu-side-chat-row.is-mine .withu-side-chat-bubble{border-color:rgba(255,255,255,.32);border-radius:12px 12px 4px 12px;background:rgba(236,72,153,.68)}
  .withu-side-chat-form{display:flex;align-items:center;gap:.42rem;padding:.62rem;border-top:1px solid rgba(255,255,255,.14)}
  .withu-side-chat-input{min-width:0;flex:1;height:34px;padding:.42rem .7rem;border:1px solid rgba(255,255,255,.24);border-radius:999px;background:rgba(255,255,255,.1);color:#fff;outline:none;font-size:.8rem}
  .withu-side-chat-input::placeholder{color:rgba(255,255,255,.56)}
  .withu-side-chat-send{height:34px;padding:0 .78rem;border:0;border-radius:999px;background:rgba(236,72,153,.9);color:#fff;font-size:.78rem;font-weight:800;cursor:pointer}
  html,body{min-height:100vh}
  body{background:
    radial-gradient(circle at 12% 8%,rgba(245,182,200,.38),transparent 28%),
    radial-gradient(circle at 88% 10%,rgba(184,221,242,.42),transparent 30%),
    radial-gradient(circle at 72% 78%,rgba(185,227,208,.36),transparent 28%),
    linear-gradient(180deg,#fbfdfc 0%,#f8fbfa 48%,#f6fafc 100%)!important;color:#263238!important}
  body::before{content:"";position:fixed;inset:0;z-index:-1;pointer-events:none;background-image:linear-gradient(rgba(226,235,231,.55) 1px,transparent 1px),linear-gradient(90deg,rgba(226,235,231,.45) 1px,transparent 1px);background-size:42px 42px;mask-image:linear-gradient(180deg,rgba(0,0,0,.36),transparent 70%)}
  .player-shell{max-width:min(1520px,calc(100vw - 28px));padding:clamp(1rem,2vw,1.65rem)}
  .player-top{padding:1rem 1.05rem;border:1px solid rgba(226,235,231,.86);border-radius:22px;background:rgba(255,255,255,.78);box-shadow:0 18px 50px rgba(106,174,210,.12),inset 0 1px 0 rgba(255,255,255,.9);backdrop-filter:blur(22px) saturate(160%);-webkit-backdrop-filter:blur(22px) saturate(160%)}
  .player-top h1{font-size:clamp(1.18rem,2.1vw,1.7rem);line-height:1.25;color:#263238;letter-spacing:-.03em}
  .player-top h1::before{content:"WithU";display:inline-flex;align-items:center;justify-content:center;margin-right:.62rem;min-height:1.38em;padding:.2rem .62rem;border-radius:999px;background:linear-gradient(135deg,#f5b6c8,#b8ddf2);color:#fff;font-size:.76rem;font-weight:900;line-height:1;letter-spacing:.01em;vertical-align:.08em;box-shadow:0 8px 18px rgba(228,134,164,.22)}
  .player-top p{color:#718087}
  .player-actions{align-items:center;gap:.62rem}
  .player-actions .btn,.watch-choice-actions .btn{min-height:38px;border-radius:999px;border:1px solid rgba(226,235,231,.95);background:rgba(255,255,255,.82);color:#42545b;box-shadow:0 8px 22px rgba(38,50,56,.08);transition:transform .18s,box-shadow .18s,border-color .18s,background .18s,color .18s}
  .player-actions .btn:hover,.watch-choice-actions .btn:hover{transform:translateY(-1px);border-color:#f5b6c8;background:#fff5fa;color:#be5775;box-shadow:0 12px 28px rgba(228,134,164,.16)}
  .player-icon-action{display:inline-grid;place-items:center;gap:.24rem;min-width:56px;min-height:54px;padding:.42rem .58rem;border:1px solid rgba(226,235,231,.95);border-radius:20px;background:rgba(255,255,255,.72);color:#42545b;text-decoration:none;box-shadow:0 8px 22px rgba(38,50,56,.08);transition:transform .18s var(--withu-ease-out),box-shadow .18s var(--withu-ease-out),border-color .18s var(--withu-ease-out),background .18s var(--withu-ease-out),color .18s var(--withu-ease-out)}
  .player-icon-action:hover,.player-icon-action:focus-visible{transform:translateY(-1px);border-color:#f5b6c8;background:#fff5fa;color:#be5775;box-shadow:0 12px 28px rgba(228,134,164,.16);outline:0}
  .player-icon-action:focus-visible{box-shadow:0 0 0 4px rgba(245,182,200,.32),0 12px 28px rgba(228,134,164,.16)}
  .player-icon-action:active{transform:scale(.97)}
  .player-icon-action svg{width:21px;height:21px;display:block;stroke:currentColor}
  .player-icon-action span{font-size:.74rem;font-weight:900;line-height:1;white-space:nowrap}
  #togetherExit{font-size:.88rem}
  #togetherExit::after{content:none}
  #togetherExit.is-solo{background:transparent!important;border-color:transparent!important;color:#42545b!important;box-shadow:none!important}
  #togetherExit.is-together{background:linear-gradient(135deg,rgba(245,182,200,.92),rgba(255,214,230,.86))!important;border-color:rgba(245,182,200,.95)!important;color:#8f3457!important;box-shadow:0 8px 22px rgba(228,134,164,.18)!important}
  .media-search-wrap input{height:40px;border-color:#e2ebe7;background:rgba(255,255,255,.9);box-shadow:0 10px 26px rgba(106,174,210,.1)}
  .media-search-wrap input:focus{border-color:#e486a4;box-shadow:0 0 0 4px rgba(245,182,200,.28),0 12px 28px rgba(228,134,164,.12)}
  .player-status{margin:.9rem 0 1rem;padding:.72rem .95rem;border:1px solid rgba(184,221,242,.65);border-radius:16px;background:linear-gradient(135deg,rgba(184,221,242,.42),rgba(255,255,255,.78));color:#3f5962;box-shadow:0 12px 32px rgba(106,174,210,.12)}
  .player-status::before{content:"";display:inline-block;width:8px;height:8px;margin-right:.55rem;border-radius:50%;background:#73b997;box-shadow:0 0 0 4px rgba(185,227,208,.38);vertical-align:middle}
  .player-layout{gap:1.05rem;align-items:start}
  .player-stage{position:relative;padding:.72rem;border:1px solid rgba(226,235,231,.9);border-radius:24px;background:linear-gradient(145deg,rgba(255,255,255,.92),rgba(248,251,250,.72));box-shadow:0 22px 60px rgba(38,50,56,.12),inset 0 1px 0 rgba(255,255,255,.95)}
  .player-stage::before{content:"";position:absolute;inset:.45rem;border-radius:20px;background:linear-gradient(135deg,rgba(245,182,200,.24),rgba(184,221,242,.18));pointer-events:none}
  .player-gesture{z-index:1}
  .player-container{border-radius:20px;box-shadow:0 20px 44px rgba(15,23,42,.24);outline:1px solid rgba(255,255,255,.72)}
  .episode-panel{padding:1rem;border:1px solid rgba(226,235,231,.92);border-radius:24px;background:rgba(255,255,255,.78);color:#263238;box-shadow:0 20px 54px rgba(115,185,151,.12),inset 0 1px 0 rgba(255,255,255,.95);backdrop-filter:blur(18px) saturate(145%);-webkit-backdrop-filter:blur(18px) saturate(145%)}
  .episode-panel-header h2{color:#263238;font-weight:900}
  .episode-panel-header h2::after{content:"";display:block;width:42px;height:3px;margin:.38rem auto 0;border-radius:999px;background:linear-gradient(90deg,#f5b6c8,#b8ddf2)}
  .episode-control-group,.episode-toggle{border-color:#e2ebe7;background:#f8fbfa}
  .episode-control-label{color:#718087}
  .episode-control{border-radius:999px;color:#52646c}
  .episode-control:hover,.episode-control.is-active{background:linear-gradient(135deg,#f5b6c8,#e486a4);color:#fff}
  .episode-toggle-label{color:#718087}
  .episode-toggle-option{color:#52646c}
  .episode-toggle-option.is-active{background:linear-gradient(135deg,#f5b6c8,#e486a4);color:#fff}
  #episodeListOutside{padding:.12rem}
  #episodeListOutside .episode-btn{min-height:38px;border-color:#e2ebe7;border-radius:12px;background:rgba(255,255,255,.88);color:#52646c;font-weight:700;box-shadow:0 6px 16px rgba(38,50,56,.06)}
  #episodeListOutside .episode-btn:hover,#episodeListOutside .episode-btn.active{border-color:#e486a4;background:linear-gradient(135deg,#fff5fa,#f7fbff);color:#be5775;box-shadow:0 10px 24px rgba(228,134,164,.16)}
  .media-detail,.recommend-panel{border:1px solid rgba(226,235,231,.9);border-radius:24px;background:rgba(255,255,255,.8);color:#263238;box-shadow:0 18px 52px rgba(38,50,56,.09),inset 0 1px 0 rgba(255,255,255,.95);backdrop-filter:blur(18px) saturate(150%);-webkit-backdrop-filter:blur(18px) saturate(150%)}
  .media-detail{grid-template-columns:minmax(0,1fr) 148px;padding:1.15rem 1.2rem}
  .media-detail-kicker{display:inline-flex;width:max-content;padding:.22rem .52rem;border-radius:999px;background:#fff0f7;color:#be5775;font-weight:900}
  .media-detail h2,.recommend-panel-header h2{color:#263238}
  .media-detail-facts span{border:1px solid rgba(226,235,231,.9);background:#f8fbfa;color:#52646c;border-radius:999px}
  .media-detail-summary{color:#52646c}
  .media-detail-poster{width:148px;border-radius:16px;box-shadow:0 16px 34px rgba(38,50,56,.16)}
  .poster-badge-wrap{position:relative;display:block;width:max-content;max-width:100%;grid-area:poster!important}
  .poster-badge-wrap .media-detail-poster{grid-area:auto!important;display:block}
  .recommend-list{grid-template-columns:repeat(auto-fit,minmax(140px,1fr))!important;gap:.9rem!important}
  .recommend-card-poster{position:relative;display:block;width:100%;aspect-ratio:2/3}
  .recommend-card-poster img{width:100%!important;height:100%!important}
  .resolution-badge{position:absolute;right:.42rem;top:.42rem;z-index:3;display:inline-flex;align-items:center;justify-content:center;min-width:2.05rem;height:1.25rem;padding:0 .42rem;border:1px solid rgba(255,255,255,.54);border-radius:999px;color:#fff;font-size:.68rem;font-weight:900;line-height:1;letter-spacing:.02em;text-shadow:0 1px 2px rgba(0,0,0,.34);box-shadow:0 8px 18px rgba(0,0,0,.22),inset 0 1px 0 rgba(255,255,255,.34);backdrop-filter:blur(10px) saturate(150%);-webkit-backdrop-filter:blur(10px) saturate(150%)}
  .resolution-badge.is-4k{padding:0;min-width:0;height:auto;background:transparent;border:0;box-shadow:none;backdrop-filter:none;-webkit-backdrop-filter:none}
  .resolution-badge.is-4k img{display:block;width:2.58rem;height:1.58rem;object-fit:contain;filter:drop-shadow(0 2px 3px rgba(0,0,0,.24))}
   .resolution-badge.is-2k{background:linear-gradient(135deg,#ffd7eb,#f08abb 58%,#be5775);color:#fff}
  .resolution-badge.is-1k{background:linear-gradient(135deg,#f6c799,#b87333 58%,#704214)}
  .poster-badge-wrap>.resolution-badge{right:.45rem;top:.45rem}
  .recommend-card-poster>.resolution-badge{right:.26rem;top:.26rem;min-width:1.85rem;height:1.12rem;padding:0 .3rem;font-size:.6rem}
  .recommend-panel{background:linear-gradient(135deg,rgba(255,255,255,.82),rgba(248,251,250,.74))}
  .recommend-more{border-color:rgba(245,182,200,.7);background:#fff0f7;color:#be5775;box-shadow:0 8px 18px rgba(228,134,164,.1)}
  .recommend-more:hover{border-color:#e486a4;background:linear-gradient(135deg,#f5b6c8,#e486a4);color:#fff;box-shadow:0 12px 24px rgba(228,134,164,.18)}
  .recommend-card{min-height:0;padding:.62rem .62rem .7rem;border-color:#e2ebe7;border-radius:18px;background:rgba(255,255,255,.78);color:#263238;box-shadow:0 10px 24px rgba(38,50,56,.06)}
  .recommend-card:hover{border-color:#b8ddf2;background:#f7fbff;box-shadow:0 14px 30px rgba(106,174,210,.16)}
  .recommend-card img{width:100%;height:100%;border-radius:14px}
  .recommend-card-title{color:#263238;font-weight:800;font-size:.92rem;line-height:1.25}
  .recommend-card-meta{display:none!important}
  .watch-choice{background:rgba(38,50,56,.32);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px)}
  .watch-choice-box{border:1px solid rgba(255,255,255,.78);border-radius:24px;background:rgba(255,255,255,.9);box-shadow:0 24px 70px rgba(38,50,56,.2)}
  .episode-launcher{display:inline-flex!important;align-items:center!important;justify-content:center!important;height:100%!important;min-height:0!important;padding:0 .18rem!important;border:0!important;border-radius:0!important;background:transparent!important;box-shadow:none!important;backdrop-filter:none!important;-webkit-backdrop-filter:none!important;transform:none!important;top:auto!important}
  .episode-launcher-pill{display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:.28rem!important;min-width:2.65rem!important;height:30px!important;padding:0 .72rem!important;border:1px solid rgba(255,255,255,.28)!important;border-radius:999px!important;background:rgba(24,32,46,.52)!important;color:#f8fafc!important;font-size:.76rem!important;font-weight:700!important;letter-spacing:.02em!important;box-shadow:inset 0 1px 0 rgba(255,255,255,.16),0 8px 22px rgba(0,0,0,.2)!important;backdrop-filter:blur(16px) saturate(155%)!important;-webkit-backdrop-filter:blur(16px) saturate(155%)!important;transition:transform var(--withu-ui-fast) var(--withu-ease-out),background var(--withu-ui-fast) var(--withu-ease-out),border-color var(--withu-ui-fast) var(--withu-ease-out),box-shadow var(--withu-ui-fast) var(--withu-ease-out)!important}
  .episode-launcher:hover .episode-launcher-pill,.episode-launcher[aria-expanded="true"] .episode-launcher-pill{border-color:rgba(255,255,255,.48)!important;background:rgba(236,72,153,.72)!important;box-shadow:0 10px 28px rgba(236,72,153,.26),inset 0 1px 0 rgba(255,255,255,.22)!important}
  .episode-launcher-icon{font-size:.9rem!important}
  .episode-launcher-label{font-size:.76rem!important}
  .withu-episode-overlay{display:flex!important;flex-direction:column!important;width:min(352px,calc(100% - 1rem))!important;height:75%!important;max-height:75%!important;padding:.65rem .55rem .55rem!important;overflow:hidden!important;overscroll-behavior:contain!important;transform-origin:bottom center!important;will-change:opacity,transform;transition:opacity 180ms var(--withu-ease-out),transform 180ms var(--withu-ease-out),visibility 0s linear 180ms!important}
  .withu-episode-overlay .withu-episode-list{display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:.3rem!important}
  .withu-episode-overlay .episode-btn{min-width:0!important;min-height:34px!important;height:34px!important;padding:.2rem .3rem!important;text-align:center!important;line-height:1.2!important}
  .art-video-player.art-fullscreen .withu-episode-overlay,.art-video-player.art-fullscreen-web .withu-episode-overlay{width:min(352px,calc(100% - 1rem))!important;height:75%!important;max-height:75%!important}
  @media(max-width:850px){.withu-episode-overlay,.art-video-player.art-fullscreen .withu-episode-overlay,.art-video-player.art-fullscreen-web .withu-episode-overlay{width:min(176px,calc(100% - 1rem))!important;height:75%!important;max-height:75%!important}}
  html[data-withu-mode="dark"] body{background:
    radial-gradient(circle at 12% 8%,rgba(228,134,164,.18),transparent 28%),
    radial-gradient(circle at 88% 10%,rgba(106,174,210,.16),transparent 30%),
    linear-gradient(180deg,#111827 0%,#101820 100%)!important;color:#edf5f2!important}
  html[data-withu-mode="dark"] .player-top,html[data-withu-mode="dark"] .episode-panel,html[data-withu-mode="dark"] .media-detail,html[data-withu-mode="dark"] .recommend-panel{border-color:rgba(255,255,255,.1);background:rgba(17,24,39,.72);color:#edf5f2;box-shadow:0 20px 54px rgba(0,0,0,.28),inset 0 1px 0 rgba(255,255,255,.08)}
  html[data-withu-mode="dark"] .player-top h1,html[data-withu-mode="dark"] .episode-panel-header h2,html[data-withu-mode="dark"] .media-detail h2,html[data-withu-mode="dark"] .recommend-panel-header h2,html[data-withu-mode="dark"] .recommend-card-title{color:#f8fafc}
  html[data-withu-mode="dark"] .player-top p,html[data-withu-mode="dark"] .media-detail-summary,html[data-withu-mode="dark"] .recommend-card-meta{color:#aeb8c7}
  html[data-withu-mode="dark"] .player-stage{border-color:rgba(255,255,255,.1);background:rgba(15,23,42,.82);box-shadow:0 22px 60px rgba(0,0,0,.32)}
  html[data-withu-mode="dark"] #episodeListOutside .episode-btn,html[data-withu-mode="dark"] .recommend-card,html[data-withu-mode="dark"] .episode-control-group,html[data-withu-mode="dark"] .episode-toggle,html[data-withu-mode="dark"] .media-detail-facts span{border-color:rgba(255,255,255,.1);background:rgba(255,255,255,.07);color:#d8e3df}
  html[data-withu-mode="dark"] .episode-toggle-label{color:#9fb0aa}
  html[data-withu-mode="dark"] .episode-toggle-option{color:#d8e3df}
  .player-actions .btn:active,.watch-choice-actions .btn:active,#episodeListOutside .episode-btn:active,.recommend-card:active,.episode-control:active,.media-search-result:active{transform:scale(.97)}
  .withu-watch-only-control:active .withu-voice-control,.withu-watch-only-control:active .withu-side-chat-control,.episode-launcher:active .episode-launcher-pill,.withu-danmaku-inline-form:active{transform:scale(.97)}
  .withu-danmaku-inline-send:active,.withu-side-chat-send:active,.withu-chat-send:active,.withu-side-chat-close:active{transform:scale(.94)}
  .withu-side-chat-send,.withu-chat-send,.withu-side-chat-close,.episode-btn,.episode-control,.recommend-card,.player-actions .btn,.watch-choice-actions .btn{transition:transform var(--withu-ui-fast) var(--withu-ease-out),background var(--withu-ui-fast) var(--withu-ease-out),border-color var(--withu-ui-fast) var(--withu-ease-out),box-shadow var(--withu-ui-fast) var(--withu-ease-out),color var(--withu-ui-fast) var(--withu-ease-out)}
  @media(hover:none){.recommend-card:hover,#episodeListOutside .episode-btn:hover,.player-actions .btn:hover{transform:none}}
  @media(prefers-reduced-motion:reduce){*,*::before,*::after{scroll-behavior:auto!important;transition-duration:.01ms!important;animation-duration:.01ms!important;animation-iteration-count:1!important}.withu-episode-overlay,.withu-chat-panel,.withu-side-chat-panel{transform:none!important}.withu-danmaku-item{left:50%!important;right:auto!important;top:14%!important;transform:translateX(-50%)!important;animation:none!important;opacity:1}}
  @media(prefers-reduced-transparency:reduce){.player-top,.player-stage,.episode-panel,.media-detail,.recommend-panel,.withu-chat-panel,.withu-side-chat-panel,.withu-danmaku-inline-form,.episode-launcher-pill,.withu-voice-control,.withu-side-chat-control,.art-video-player .art-bottom .art-controls{backdrop-filter:none!important;-webkit-backdrop-filter:none!important}.withu-chat-panel,.withu-side-chat-panel,.withu-danmaku-inline-form,.episode-launcher-pill,.withu-voice-control,.withu-side-chat-control{background:rgba(15,23,42,.94)!important}}
  @media(prefers-contrast:more){.player-top,.player-stage,.episode-panel,.media-detail,.recommend-panel,.withu-chat-panel,.withu-side-chat-panel,.withu-danmaku-inline-form,.episode-launcher-pill,.withu-voice-control,.withu-side-chat-control{border-color:currentColor!important}.withu-danmaku-inline-input,.withu-side-chat-input{font-weight:700}}
  @keyframes withu-danmaku-fly{from{transform:translateX(0);opacity:0}6%,92%{opacity:1}to{transform:translateX(calc(-100vw - 120%));opacity:0}}
  @keyframes withu-episode-title-marquee{0%,18%{transform:translateX(0)}48%,68%{transform:translateX(calc(-1 * var(--withu-marquee-distance,0px)))}100%{transform:translateX(0)}}
  @media(max-width:780px){.withu-side-chat-panel{left:.55rem;right:.55rem;top:3.8rem;bottom:4.25rem;width:auto;min-width:0}}
  @media(max-width:720px){.withu-danmaku-inline-form{width:min(210px,38vw);min-width:142px}.withu-danmaku-inline-send{padding:0 .46rem}}
  @media(max-width:640px){.withu-chat-panel{right:.35rem;bottom:calc(var(--art-control-height) + var(--art-progress-height) + var(--art-progress-top-gap) + .38rem);width:calc(100% - .7rem)}.withu-danmaku-item{max-width:72%;font-size:.78rem}}
  :root{--withu-sakura:#f5b6c8;--withu-sakura-strong:#e486a4;--withu-sakura-soft:rgba(245,182,200,.18);--withu-sakura-glow:rgba(228,134,164,.36);--withu-player-logo-bg:<?php echo e($playerLogoBgStyle); ?>}
  .art-video-player .art-control-screenshot,.art-video-player .art-control-pip,.art-video-player .art-control-fullscreenWeb,.art-video-player .art-control-fullscreen-web,.art-video-player .art-control-web-fullscreen,.art-video-player [data-name="screenshot"],.art-video-player [data-name="pip"],.art-video-player [data-name="fullscreenWeb"]{display:none!important}
  .art-video-player .art-bottom .art-controls{--withu-input-width:220px;position:relative!important;min-width:0!important;}
  .art-video-player .art-controls-right{position:static!important;display:flex!important;align-items:center!important;gap:.18rem!important;min-width:0!important;flex:0 1 auto!important}
  .art-video-player .art-progress-played{background:linear-gradient(90deg,var(--withu-sakura),var(--withu-sakura-strong))!important;box-shadow:0 0 14px var(--withu-sakura-glow)!important}
  .art-video-player .art-progress-indicator{background:var(--withu-sakura-strong)!important;box-shadow:0 2px 10px rgba(0,0,0,.34),0 0 0 4px rgba(245,182,200,.2)!important}
  .art-video-player .art-control-speed-button{order:10!important}
  .art-video-player .art-control-episode-button{order:20!important}
  .art-video-player .art-control-side-chat-button{order:30!important}
  .art-video-player .art-control-voice-button{order:40!important}
  .art-video-player .art-control-setting{order:50!important}
  .art-video-player .art-control-fullscreen{order:99!important}
  .art-video-player .art-control-setting,.art-video-player .art-control-fullscreen{width:38px!important;min-width:38px!important}
  .withu-speed-control{min-width:2.1rem!important}
  .withu-voice-control,.withu-side-chat-control{min-width:2.35rem!important;height:30px!important;padding:0 .55rem!important}
  .episode-launcher-pill{min-width:2.55rem!important;padding:0 .58rem!important}
  .art-control-chat-button.withu-danmaku-inline-control{position:absolute!important;left:50%!important;top:50%!important;width:var(--withu-input-width,220px)!important;max-width:var(--withu-input-width,220px)!important;height:100%!important;padding:0!important;display:flex!important;align-items:center!important;justify-content:center!important;overflow:visible!important;transform:translate(-50%,-50%)!important;z-index:10008!important}
  .art-control-chat-button.withu-danmaku-inline-control[hidden]{display:none!important}
  .withu-danmaku-inline-form{width:100%!important;max-width:none!important;min-width:0!important;border-color:rgba(255,255,255,.28)!important;background:rgba(24,32,46,.52)!important;box-shadow:inset 0 1px 0 rgba(255,255,255,.16),0 8px 22px rgba(0,0,0,.2)!important}
  .withu-danmaku-inline-form:focus-within{border-color:rgba(245,182,200,.72)!important;box-shadow:0 10px 30px rgba(228,134,164,.28),inset 0 1px 0 rgba(255,255,255,.22)!important}
  .withu-danmaku-inline-send,.withu-voice-control,.withu-side-chat-control{background:rgba(255,255,255,.12)!important;border-color:rgba(255,255,255,.28)!important;color:rgba(248,250,252,.86)!important;box-shadow:inset 0 1px 0 rgba(255,255,255,.14),0 8px 22px rgba(0,0,0,.18)!important}
  .withu-side-chat-send,.withu-chat-send{background:linear-gradient(135deg,var(--withu-sakura),var(--withu-sakura-strong))!important;border-color:rgba(255,255,255,.54)!important;color:#fff!important;box-shadow:0 10px 28px var(--withu-sakura-glow),inset 0 1px 0 rgba(255,255,255,.24)!important}
  .withu-danmaku-inline-form:focus-within .withu-danmaku-inline-send,.withu-danmaku-inline-form.has-message .withu-danmaku-inline-send,.withu-watch-only-control.is-active .withu-voice-control,.withu-watch-only-control.is-active .withu-side-chat-control,.withu-side-chat-send:hover,.withu-chat-send:hover,.episode-launcher:hover .episode-launcher-pill,.episode-launcher[aria-expanded="true"] .episode-launcher-pill,.withu-speed-option:hover,.withu-speed-option.is-active{background:linear-gradient(135deg,var(--withu-sakura),var(--withu-sakura-strong))!important;border-color:rgba(255,255,255,.54)!important;box-shadow:0 10px 28px var(--withu-sakura-glow),inset 0 1px 0 rgba(255,255,255,.24)!important;color:#fff!important}
  .withu-danmaku-item.is-mine,.withu-side-chat-row.is-mine .withu-side-chat-bubble{background:linear-gradient(135deg,rgba(245,182,200,.72),rgba(228,134,164,.74))!important}
  .art-video-player .art-controls-left,.art-video-player .art-controls-right{display:flex!important;align-items:center!important;flex:0 0 auto!important;white-space:nowrap!important}
  .art-video-player .art-controls-left{gap:.04rem!important}
  .art-video-player .art-control-previous-button{order:10!important}
  .art-video-player .art-control-playAndPause{order:20!important}
  .art-video-player .art-control-next-button{order:30!important}
  .art-video-player .art-control-time{order:40!important;white-space:nowrap!important;flex:0 0 auto!important}
  .withu-net-speed{order:39!important;display:inline-flex;align-items:center;padding:0 .5rem;font-size:.72rem;font-weight:600;letter-spacing:.02em;color:rgba(255,255,255,.92);background:rgba(0,0,0,.18);border-radius:8px;white-space:nowrap;flex:0 0 auto;margin-right:.35rem;line-height:1.7}
  .art-video-player .art-controls-left .art-control-volume{display:none!important}
  .art-video-player .art-controls-right .art-control-setting{order:10!important}
  .art-video-player .art-controls-right .art-control-voice-button{order:20!important}
  .art-video-player .art-controls-right .art-control-side-chat-button{order:30!important}
  .art-video-player .art-controls-right .art-control-episode-button{order:40!important}
  .art-video-player .art-controls-right .art-control-volume{order:50!important;display:flex!important}
  .art-video-player .art-controls-right .art-control-speed-button{order:60!important}
  .art-video-player .art-controls-right .art-control-fullscreen{order:99!important}
  .art-video-player .art-controls.is-withu-compact .withu-danmaku-inline-form{padding-left:.46rem!important;padding-right:.46rem!important}
  .art-video-player .art-controls.is-withu-compact .withu-danmaku-inline-send{display:none!important}
  .art-video-player .art-controls.is-withu-tiny .art-control-chat-button.withu-danmaku-inline-control{display:none!important}
  .art-video-player .art-controls.is-withu-overflow .art-controls-right{gap:.08rem!important}
  .art-video-player .art-controls.is-withu-overflow .withu-voice-control,.art-video-player .art-controls.is-withu-overflow .withu-side-chat-control,.art-video-player .art-controls.is-withu-overflow .episode-launcher-pill,.art-video-player .art-controls.is-withu-overflow .withu-speed-control{width:30px!important;min-width:30px!important;padding:0!important;justify-content:center!important}
  .art-video-player .art-controls.is-withu-overflow .withu-voice-control,.art-video-player .art-controls.is-withu-overflow .withu-side-chat-control{font-size:0!important}
  .art-video-player .art-controls.is-withu-overflow .withu-voice-control::before{content:'麦';font-size:.75rem;font-weight:800}
  .art-video-player .art-controls.is-withu-overflow .withu-side-chat-control::before{content:'聊';font-size:.75rem;font-weight:800}
  .art-video-player .art-controls.is-withu-overflow .episode-launcher-label{display:none!important}
  .art-video-player .art-controls.is-withu-overflow .withu-speed-control{font-size:.72rem!important}
  .art-video-player .art-controls.is-withu-ultra .art-control-time,.art-video-player .art-controls.is-withu-ultra .art-control-setting,.art-video-player .art-controls.is-withu-ultra .art-controls-right .art-control-volume{display:none!important}
  .art-video-player .art-controls.is-withu-micro .art-control-voice-button,.art-video-player .art-controls.is-withu-micro .art-control-side-chat-button{display:none!important}
  .withu-speed-menu,.withu-chat-panel,.withu-side-chat-panel,.withu-episode-overlay{right:auto!important;top:auto!important;transform-origin:bottom center!important}
  .withu-side-chat-panel{bottom:auto;max-height:min(58%,420px)!important}
  .withu-side-chat-panel{left:auto!important;right:.7rem!important;top:4.25rem!important;bottom:4.6rem!important;width:min(320px,36%)!important;min-width:240px!important;max-height:none!important;transform-origin:right center!important}
  @media(max-width:780px){.withu-side-chat-panel{left:.55rem!important;right:.55rem!important;top:3.8rem!important;bottom:4.25rem!important;width:auto!important;min-width:0!important}}
  .player-layout>.episode-panel{justify-content:flex-start!important;align-items:stretch!important;height:var(--withu-player-height)!important;min-height:0!important;max-height:var(--withu-player-height)!important;padding:.75rem!important;overflow:hidden!important}
  .player-layout>.episode-panel .episode-panel-header{flex:0 0 auto!important;margin:0 0 .65rem!important;gap:.5rem!important}
  .player-layout>.episode-panel .episode-panel-header h2{margin:.05rem 0 0!important}
  .player-layout>.episode-panel #episodeListOutside{flex:1 1 auto!important;min-height:0!important;max-height:none!important;height:auto!important;align-content:start!important;overflow-y:auto!important;overscroll-behavior:contain!important;padding:.05rem .08rem .15rem!important;scrollbar-gutter:stable!important}
  .player-layout>.episode-panel #episodeListOutside .episode-btn{min-height:56px!important;display:flex!important;align-items:center!important;justify-content:center!important}
  .episode-btn{min-width:0!important;max-width:100%!important;overflow:hidden!important}
  .episode-btn-text{display:block!important;position:relative!important;min-width:0!important;max-width:100%!important;overflow:hidden!important;text-overflow:ellipsis!important;white-space:nowrap!important;text-align:center!important;line-height:1.25!important}
  .episode-btn-marquee{display:block!important;min-width:0!important;overflow:hidden!important;text-overflow:ellipsis!important;white-space:nowrap!important}
  .episode-btn-text.is-scrollable{overflow:hidden!important;text-overflow:clip!important;scrollbar-width:none!important;overscroll-behavior-x:contain!important}
  .episode-btn-text.is-scrollable::-webkit-scrollbar{display:none}
  .episode-btn-text.is-scrollable .episode-btn-marquee{display:inline-block!important;min-width:max-content!important;overflow:visible!important;text-overflow:clip!important;will-change:transform!important}
  .episode-btn-text.is-scrollable.is-marquee-ready .episode-btn-marquee{animation:withu-episode-title-marquee var(--withu-marquee-duration,9s) var(--withu-ease-in-out) infinite!important}
  .withu-episode-overlay .episode-btn-text{color:inherit}
   .media-detail{display:grid!important;grid-template-columns:148px minmax(0,1fr)!important;grid-template-rows:auto 1fr!important;grid-template-areas:"label label" "poster copy"!important;align-items:start!important;gap:.55rem .85rem!important;padding:.85rem 1rem 1rem!important;min-height:212px!important}
  .media-detail-kicker{grid-area:label!important;display:block!important;width:auto!important;padding:0!important;border-radius:0!important;background:transparent!important;color:#e486a4!important;font-size:.92rem!important;font-weight:900!important;letter-spacing:.02em!important;line-height:1.2!important}
   .media-detail-poster{grid-area:poster!important;width:148px!important;height:198px!important;aspect-ratio:auto!important;align-self:start!important;object-fit:cover!important;border-radius:14px!important}
   .media-detail .poster-badge-wrap{grid-area:poster!important;align-self:start!important;width:148px!important;height:198px!important}
  .media-detail .poster-badge-wrap .media-detail-poster{width:100%!important;height:100%!important;grid-area:auto!important}
  .media-detail-copy{grid-area:copy!important;display:grid!important;grid-template-rows:auto minmax(0,1fr)!important;align-self:stretch!important;min-width:0!important;max-width:100%!important;overflow:hidden!important;gap:.45rem!important}
  .media-detail-titlebar{display:flex!important;align-items:center!important;gap:.5rem!important;flex-wrap:nowrap!important;width:100%!important;max-width:100%!important;box-sizing:border-box!important;height:46px!important;min-height:46px!important;padding:.42rem .65rem!important;border:1px solid rgba(226,235,231,.92)!important;border-radius:14px!important;background:rgba(255,255,255,.66)!important;overflow:hidden!important}
  .media-detail-titlebar h2{flex:0 1 auto!important;max-width:46%!important;margin:0!important;color:#263238!important;font-size:1.08rem!important;line-height:1.25!important;letter-spacing:-.02em!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important}
  .media-detail-titlebar .media-detail-facts{display:flex!important;align-items:center!important;gap:.35rem!important;flex:1 1 auto!important;min-width:0!important;flex-wrap:nowrap!important;margin:0!important;font-size:.76rem!important;overflow:hidden!important}
  .media-detail-titlebar .media-detail-facts span{flex:0 1 auto!important;max-width:180px!important;min-width:0!important;padding:.18rem .48rem!important;border-radius:999px!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important}
  .media-detail-summary{min-width:0!important;width:100%!important;max-width:100%!important;box-sizing:border-box!important;overflow:hidden!important;min-height:112px!important;margin:0!important;padding:.78rem .9rem!important;border:1px solid rgba(226,235,231,.92)!important;border-radius:16px!important;background:rgba(255,255,255,.58)!important;color:#52646c!important;line-height:1.2!important;white-space:normal!important}
  .media-cast-row{display:flex!important;align-items:center!important;gap:.55rem!important;min-width:0!important;width:100%!important;max-width:100%!important;box-sizing:border-box!important;margin:0 0 .38rem!important;line-height:1.2!important;overflow:hidden!important}
  .media-cast-text{flex:1 1 auto!important;min-width:0!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important}
  .media-cast-toggle{flex:0 0 auto!important;height:24px!important;padding:0 .55rem!important;border:1px solid rgba(226,235,231,.95)!important;border-radius:999px!important;background:rgba(255,255,255,.76)!important;color:#be5775!important;font-size:.72rem!important;font-weight:800!important;cursor:pointer!important}
  .media-cast-toggle[hidden]{display:none!important}
  .media-detail-summary.is-cast-open .media-cast-row{align-items:flex-start!important;overflow:visible!important}
  .media-detail-summary.is-cast-open .media-cast-text{white-space:normal!important;overflow-y:auto!important;overflow-x:hidden!important;text-overflow:clip!important;line-height:1.2!important;max-height:86px!important;scrollbar-gutter:stable!important;overflow-wrap:anywhere!important;word-break:break-word!important}
  .media-summary-body{line-height:1.2!important;white-space:pre-line!important}
  .player-status{position:absolute!important;width:1px!important;height:1px!important;margin:0!important;padding:0!important;overflow:hidden!important;clip:rect(0 0 0 0)!important;white-space:nowrap!important;border:0!important;box-shadow:none!important}
  .player-status::before{display:none!important}
  .player-watermark{display:none!important}
  .withu-player-topbar{position:absolute;left:1.05rem;right:1.05rem;top:.9rem;z-index:10055;display:grid;grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);align-items:center;gap:.55rem;min-width:0;height:42px;padding:.28rem .55rem;color:#fff;text-shadow:0 1px 3px rgba(0,0,0,.42);pointer-events:none;opacity:1;transform:translateY(0);transition:opacity 220ms var(--withu-ease-out),transform 220ms var(--withu-ease-out),visibility 0s linear 0s}
  .art-video-player.art-hide-cursor .withu-player-topbar{opacity:0;visibility:hidden;transform:translateY(-8px);transition:opacity 220ms var(--withu-ease-out),transform 220ms var(--withu-ease-out),visibility 0s linear 220ms}
  .withu-player-topbar-left{display:flex;align-items:center;gap:.55rem;min-width:0;justify-self:start}
  .withu-player-topbar-logo{flex:0 0 auto;width:34px;height:30px;padding:0;box-sizing:border-box;object-fit:contain;border-radius:6px;background:var(--withu-player-logo-bg,#f5b6c8);box-shadow:0 6px 14px rgba(228,134,164,.2);filter:drop-shadow(0 1px 3px rgba(0,0,0,.18))}
  .withu-player-topbar-title{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.88rem;font-weight:900;letter-spacing:.01em}
  .withu-player-topbar-watch{justify-self:center;display:inline-flex;align-items:center;justify-content:center;gap:.24rem;height:26px;padding:0 .72rem;border:1px solid rgba(255,255,255,.28);border-radius:999px;background:transparent;font-size:.74rem;font-weight:900;white-space:nowrap;transition:background 180ms var(--withu-ease-out),border-color 180ms var(--withu-ease-out),box-shadow 180ms var(--withu-ease-out)}
  .withu-player-topbar.is-together .withu-player-topbar-watch{border-color:rgba(255,196,220,.86);background:linear-gradient(135deg,rgba(245,182,200,.88),rgba(255,221,235,.72));box-shadow:0 10px 26px rgba(245,182,200,.24),inset 0 1px 0 rgba(255,255,255,.35)}
  .withu-player-topbar-heart{color:rgba(255,255,255,.32);text-shadow:none;transform:scale(.96)}
  .withu-player-topbar.is-together .withu-player-topbar-heart{color:#ff4f7d;text-shadow:0 0 10px rgba(255,79,125,.58);transform:scale(1.08)}
  .withu-player-topbar-right{grid-column:3;grid-row:1;justify-self:end;display:inline-flex;align-items:center;gap:.55rem;min-width:0}
  .withu-player-topbar-speed{font-size:.72rem;font-weight:700;letter-spacing:.02em;padding:.12rem .5rem;border-radius:999px;background:rgba(0,0,0,.26);border:1px solid rgba(255,255,255,.16);color:#fff;text-shadow:none;white-space:nowrap}
  .withu-player-topbar-time{min-width:3.3rem;text-align:right;font-size:.82rem;font-weight:900;letter-spacing:.02em}
  .withu-player-topbar.is-solo .withu-player-topbar-watch{display:none!important}
  .withu-voice-activity{position:absolute;left:1.05rem;bottom:calc(var(--art-control-height) + var(--art-progress-height) + var(--art-progress-top-gap) + .82rem);z-index:10062;display:inline-flex;align-items:center;gap:.42rem;height:34px;padding:0 .58rem;border:1px solid rgba(255,255,255,.42);border-radius:999px;background:linear-gradient(135deg,rgba(245,182,200,.62),rgba(228,134,164,.5));box-shadow:0 12px 30px rgba(228,134,164,.3),inset 0 1px 0 rgba(255,255,255,.28);backdrop-filter:blur(18px) saturate(165%);-webkit-backdrop-filter:blur(18px) saturate(165%);color:#fff;pointer-events:none;opacity:0;visibility:hidden;transform:translateY(8px) scale(.94);transition:opacity 160ms var(--withu-ease-out),transform 160ms var(--withu-ease-out),visibility 0s linear 160ms}
  .withu-voice-activity.is-speaking{opacity:1;visibility:visible;transform:translateY(0) scale(1);transition:opacity 120ms var(--withu-ease-out),transform 180ms var(--withu-ease-out),visibility 0s linear 0s}
  .withu-voice-activity-icon{display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:50%;background:rgba(255,255,255,.18);box-shadow:inset 0 1px 0 rgba(255,255,255,.22)}
  .withu-voice-activity-bars{display:inline-flex;align-items:center;gap:3px;height:18px}
  .withu-voice-activity-bars span{width:3px;height:7px;border-radius:999px;background:#fff;opacity:.72;transform-origin:center bottom;animation:withu-voice-wave 620ms ease-in-out infinite}
  .withu-voice-activity-bars span:nth-child(2){height:12px;animation-delay:90ms}
  .withu-voice-activity-bars span:nth-child(3){height:16px;animation-delay:180ms}
  .withu-voice-activity-bars span:nth-child(4){height:10px;animation-delay:270ms}
  @keyframes withu-voice-wave{0%,100%{transform:scaleY(.45);opacity:.55}50%{transform:scaleY(1);opacity:1}}
  @media(max-width:760px){.player-icon-action{min-width:52px;min-height:50px;border-radius:18px}.withu-player-topbar{left:.45rem;right:.45rem;top:.45rem;height:36px;grid-template-columns:minmax(0,1fr) auto}.withu-player-topbar-left{gap:.42rem}.withu-player-topbar-logo{width:31px;height:26px;padding:0;border-radius:5px}.withu-player-topbar-title{font-size:.78rem}.withu-player-topbar-watch{grid-column:1/3;grid-row:2;height:22px;padding:0 .5rem;font-size:.66rem}.withu-player-topbar-right{grid-column:2;grid-row:1;justify-self:end}
  .withu-player-topbar-speed{font-size:.64rem;padding:.08rem .4rem}
  .withu-player-topbar-time{font-size:.74rem;min-width:2.8rem}}
  html[data-withu-mode="dark"] .media-detail-kicker{color:#f5b6c8!important}
  html[data-withu-mode="dark"] .media-detail-titlebar,html[data-withu-mode="dark"] .media-detail-summary{border-color:rgba(255,255,255,.1)!important;background:rgba(255,255,255,.06)!important}
   @media(max-width:760px){.media-detail{grid-template-columns:104px minmax(0,1fr)!important;gap:.5rem!important}.media-detail-poster,.media-detail .poster-badge-wrap{width:104px!important;height:144px!important}.media-detail-titlebar{min-height:38px!important;padding:.35rem .5rem!important}.media-detail-summary{min-height:96px!important;padding:.65rem!important}}
   @media(max-width:520px){.media-detail{grid-template-columns:88px minmax(0,1fr)!important}.media-detail-poster,.media-detail .poster-badge-wrap{width:88px!important;height:120px!important}.media-detail-titlebar .media-detail-facts{font-size:.7rem!important}.media-detail-summary{font-size:.86rem!important}}
   @media(max-width:980px){.withu-danmaku-inline-form{min-width:0!important;padding-left:.42rem!important}.withu-danmaku-inline-send{padding:0 .42rem!important}.episode-launcher-pill{padding:0 .5rem!important}.withu-voice-control,.withu-side-chat-control{padding:0 .48rem!important}}
   @media(max-width:760px){.art-video-player .art-controls-right{gap:.14rem!important}}
   .player-top>div:first-child{min-width:0}
   .player-actions{margin-left:auto;justify-content:flex-end;min-width:0}
   @media(max-width:760px){.player-actions{width:100%;margin-left:0;justify-content:flex-end}}
   .player-top{display:grid;grid-template-columns:minmax(0,1fr) minmax(220px,360px) minmax(0,1fr);align-items:center;column-gap:1rem;justify-content:initial}
   .player-top-copy{grid-column:1;min-width:0}
   .player-top-search{grid-column:2;display:flex;align-items:center;justify-content:center;min-width:0;width:100%}
   .player-top-search .media-search-wrap{width:100%;min-width:0}
   .player-top .player-actions{grid-column:3;justify-self:end;width:auto;min-width:0;margin-left:0;display:flex;align-items:center;justify-content:flex-end;flex-wrap:nowrap;row-gap:.45rem}
   @media(max-width:980px){.player-top{grid-template-columns:minmax(0,1fr) auto;grid-template-areas:'copy actions' 'search search';row-gap:.7rem}.player-top-copy{grid-area:copy}.player-top-search{grid-area:search;width:min(100%,360px);justify-self:center}.player-top .player-actions{grid-area:actions;width:auto;justify-self:end}}
   @media(max-width:520px){.player-top-search{width:min(100%,360px)}.player-top .player-actions{display:flex;width:auto}.player-top .player-actions .btn,.player-top .player-actions .player-icon-action{width:auto}}

/* ==== 选集浮层：单列优先 · 放不下自动多列 · 始终居中填满 ==== */
.withu-episode-overlay,.art-video-player.art-fullscreen .withu-episode-overlay,.art-video-player.art-fullscreen-web .withu-episode-overlay{display:flex!important;flex-direction:column!important;height:auto!important;max-height:none!important;width:var(--ep-overlay-w,96px)!important;min-width:0!important;max-width:calc(100% - 1rem)!important;left:auto!important;right:auto!important;top:auto!important;bottom:auto!important;padding:.45rem .5rem .5rem!important;overflow:hidden!important;transform-origin:center bottom!important;transform:none!important;will-change:opacity,transform;transition:opacity 180ms var(--withu-ease-out),transform 180ms var(--withu-ease-out),visibility 0s linear 180ms!important}
.withu-episode-overlay.is-open,.art-video-player.art-fullscreen .withu-episode-overlay.is-open,.art-video-player.art-fullscreen-web .withu-episode-overlay.is-open{transform:none!important}
.withu-episode-overlay .withu-episode-list,.art-video-player.art-fullscreen .withu-episode-overlay .withu-episode-list,.art-video-player.art-fullscreen-web .withu-episode-overlay .withu-episode-list{display:grid!important;grid-template-columns:repeat(var(--ep-cols,1),minmax(0,1fr))!important;grid-auto-rows:auto!important;gap:.24rem!important;align-content:start!important;min-height:0!important;overflow:visible!important;overscroll-behavior:contain!important;scrollbar-width:none!important;flex:1 1 auto!important;width:100%!important;max-width:100%!important}
.withu-episode-overlay .withu-episode-list .episode-btn,.art-video-player.art-fullscreen .withu-episode-overlay .withu-episode-list .episode-btn,.art-video-player.art-fullscreen-web .withu-episode-overlay .withu-episode-list .episode-btn{min-width:0!important;min-height:28px!important;height:auto!important;width:100%!important;padding:.12rem .2rem!important;text-align:center!important;font-size:.7rem!important;line-height:1.2!important;box-sizing:border-box!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important}
.withu-episode-overlay .withu-episode-list::-webkit-scrollbar{width:0!important;height:0!important;display:none!important}
.withu-episode-overlay h3{flex:0 0 auto!important;margin:.05rem .1rem .4rem!important;font-size:.78rem!important}
</style>
</head>
<body>
<main class="player-shell">
  <div class="player-top"><div class="player-top-copy"><h1 id="seriesTitle"><?php echo e($media['series_name'] ?: $media['file_name']); ?></h1><p>WithU Watch · 选择分集后开始播放</p></div><div class="player-top-search"><div class="media-search-wrap"><label class="sr-only" for="mediaSearch">搜索影片</label><span class="media-search-icon" aria-hidden="true">⌕</span><input id="mediaSearch" type="search" autocomplete="off" placeholder="搜索影片…"><div id="mediaSearchResults" class="media-search-results" hidden></div></div></div><div class="player-actions"><button id="togetherExit" class="btn btn-secondary" type="button" hidden title="退出一起看" aria-label="退出一起看">一起看</button><a class="player-icon-action" href="/watch_history.php" aria-label="观影历史"><svg aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke-width="2"><path d="M12 8v5l3 2"/><path d="M3.05 11a9 9 0 1 1 2.63 6.36"/><path d="M3 17v-6h6"/></svg><span>历史</span></a><a class="player-icon-action" href="/admin/index.php" aria-label="后台设置"><svg aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke-width="2"><path d="M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5Z"/><path d="M19.4 15a1.8 1.8 0 0 0 .36 1.98l.04.04a2.1 2.1 0 0 1-2.97 2.97l-.04-.04a1.8 1.8 0 0 0-1.98-.36 1.8 1.8 0 0 0-1.1 1.66V21a2.1 2.1 0 0 1-4.2 0v-.06a1.8 1.8 0 0 0-1.1-1.66 1.8 1.8 0 0 0-1.98.36l-.04.04a2.1 2.1 0 0 1-2.97-2.97l.04-.04A1.8 1.8 0 0 0 3.8 15a1.8 1.8 0 0 0-1.66-1.1H2a2.1 2.1 0 0 1 0-4.2h.06A1.8 1.8 0 0 0 3.72 8.6a1.8 1.8 0 0 0-.36-1.98l-.04-.04a2.1 2.1 0 0 1 2.97-2.97l.04.04A1.8 1.8 0 0 0 8.3 4a1.8 1.8 0 0 0 1.1-1.66V2a2.1 2.1 0 0 1 4.2 0v.06A1.8 1.8 0 0 0 14.7 3.72a1.8 1.8 0 0 0 1.98-.36l.04-.04a2.1 2.1 0 0 1 2.97 2.97l-.04.04A1.8 1.8 0 0 0 19.28 8.3a1.8 1.8 0 0 0 1.66 1.1H21a2.1 2.1 0 0 1 0 4.2h-.06A1.8 1.8 0 0 0 19.4 15Z"/></svg><span>后台</span></a></div></div>
  <div id="status" class="player-status">正在读取播放器…</div>
  <div class="player-layout"><section class="player-stage"><div id="gesture" class="player-gesture"><div id="playerContainer" class="player-container"><div id="playerTopBar" class="withu-player-topbar is-solo" aria-live="polite"><span class="withu-player-topbar-left"><img class="withu-player-topbar-logo" src="<?php echo e($playerLogoUrl); ?>" alt="withU"><span id="playerTopTitle" class="withu-player-topbar-title"><?php echo e($media['series_name'] ?: $media['file_name']); ?></span></span><span id="playerTopWatch" class="withu-player-topbar-watch">一起看<span class="withu-player-topbar-heart">❤</span><span id="playerTopOnlineText">宝宝离线中</span></span><span class="withu-player-topbar-right"><span id="playerNetSpeed" class="withu-player-topbar-speed">网速 --</span><span id="playerTopTime" class="withu-player-topbar-time">--:--</span></span></div><div id="playerWatermark" class="player-watermark" aria-live="polite"><span id="watermarkMark" class="watermark-mark"><img src="<?php echo e($playerLogoUrl); ?>" alt="withU"><span class="watermark-heart" aria-hidden="true">♥</span></span><span id="watermarkOnline" class="watermark-online" hidden>宝宝在线中</span></div><div id="switchLoading" class="withu-switch-loading" hidden aria-live="polite"><div class="withu-switch-loading-box"><span class="withu-switch-loading-spinner" aria-hidden="true"></span><span id="switchLoadingText">正在切换选集…</span></div></div></div><span id="gestureValue" class="gesture-value"></span></div></section><section class="episode-panel" aria-labelledby="episodeListHeading"><div class="episode-panel-header"><h2 id="episodeListHeading">选集列表</h2><div class="episode-panel-controls"><button type="button" class="episode-toggle" data-episode-toggle="columns" aria-label="切换选集排版"><span class="episode-toggle-label">排版</span><span class="episode-toggle-option" data-episode-columns-state="2">双排</span><span class="episode-toggle-option" data-episode-columns-state="1">单排</span></button><button type="button" class="episode-toggle" data-episode-toggle="order" aria-label="切换选集排序"><span class="episode-toggle-label">排序</span><span class="episode-toggle-option" data-episode-order-state="asc">正序</span><span class="episode-toggle-option" data-episode-order-state="desc">倒序</span></button></div></div><div id="episodeListOutside" class="episode-list" data-columns="2"></div></section></div>
  <section class="media-detail" aria-label="影片简介"><div class="media-detail-kicker">简介</div><span class="poster-badge-wrap"<?php echo (($czMode && empty($czMeta['poster'])) || ($strmMode && empty($strmMeta['posterUrl'] ?? ''))) ? ' style="display:none"' : ''; ?>><img id="detailPoster" class="media-detail-poster" src="<?php echo $czMode ? (e($czMeta['poster'] ?? '')) : ($strmMode ? (e($strmMeta['posterUrl'] ?? '')) : ('/api/media_cover.php?id=' . (int)$media['id'])); ?>" alt=""><span id="detailResolutionBadge"><?php echo ($czMode || $strmMode) ? '' : withu_resolution_badge_html($media['resolution'] ?? ''); ?></span></span><div class="media-detail-copy"><div class="media-detail-titlebar"><h2 id="detailTitle"><?php echo e($media['series_name'] ?: $media['file_name']); ?></h2><div id="detailFacts" class="media-detail-facts"></div></div><div id="detailSummary" class="media-detail-summary"><div class="media-summary-body"><?php echo e($media['summary'] ?? '正在读取评分、简介和演职员信息…'); ?></div></div></div></section>
  <?php if (!$czMode && !$strmMode): ?><section class="recommend-panel" aria-labelledby="recommendHeading"><div class="recommend-panel-header"><div class="recommend-panel-titleline"><h2 id="recommendHeading">猜你想看</h2></div><a class="recommend-more" href="/watch.php">显示更多</a></div><div id="recommendList" class="recommend-list"></div></section><?php endif; ?>
</main>
<div id="choiceModal" class="watch-choice" hidden><div class="watch-choice-box"><h2>另一位正在观影</h2><p id="choiceText">检测到另一位已进入 WithU Watch。</p><div class="watch-choice-actions"><button id="chooseTogether" class="btn btn-primary">一起看</button><button id="chooseSolo" class="btn btn-secondary">自己看</button></div></div></div>
<script src="/assets/vendor/hls.min.js"></script>
<script src="/assets/vendor/artplayer-5.4.0.js"></script>
<script>
 var csrf=<?php echo json_encode($csrfToken); ?>,partnerId=<?php echo $partnerId; ?>,initialMediaId=<?php echo $mediaId; ?>,initialUrl=<?php echo json_encode($initialResolveUrl); ?>,initialName=<?php echo json_encode($media['file_name']); ?>,playerLogoUrl=<?php echo json_encode($playerLogoUrl); ?>,playerLoadBackground=<?php echo json_encode($playerLoadBackground); ?>,watchPollIntervalMs=<?php echo $watchPollIntervalMs; ?>,watchHeartbeatIntervalMs=<?php echo $watchHeartbeatIntervalMs; ?>,watchAutoplayEnabled=<?php echo $watchAutoplayEnabled ? 'true' : 'false'; ?>,playerAutoNextEnabled=<?php echo $playerAutoNextEnabled ? 'true' : 'false'; ?>;
  var czMode=<?php echo $czMode ? 'true' : 'false'; ?>,czLines=<?php echo json_encode($czLines); ?>,czDetailUrl=<?php echo json_encode($czDetailUrl); ?>,czMeta=<?php echo json_encode($czMeta ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG); ?>;
var strmMode=<?php echo $strmMode ? 'true' : 'false'; ?>,strmMediaId=<?php echo (int)($strmMediaId ?? 0); ?>,strmMeta=<?php echo json_encode($strmMeta ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG); ?>;
  var mediaApi='/api/media.php',watchApi='/api/watch.php',code='WithU Watch',roomJoined=false,loadedMediaId=null,lastEvent=0,timer=null,heartbeatTimer=null,pollInFlight=false,applying=false,applyingTimer=null,remoteSeekPending=false,remoteSeekTimer=null,syncCatchupTimer=null,holdTimer=null,normalSpeed=1,peer=null,voiceStream=null,voiceActive=false,voiceAudioCtx=null,voiceMicSource=null,voiceAnalyser=null,voiceLevelData=null,voiceActivityFrame=null,voiceActivityLastLoudAt=0,oldVolume=1,localOnly=false,partnerOnline=false,pendingMediaId=initialMediaId,mediaItems=[],recommendationGroups=[],searchRequestSerial=0,searchTimer=null,searchRequestController=null,playbackSourceController=null,brightness=1,currentEpisodes=[],episodeColumns=2,episodeColumnsManual=false,episodeOrder='asc',syncDriftThreshold=<?php echo $watchSyncThresholdMs / 1000; ?>,mediaSelectionSerial=0,mediaSwitchSerial=0,mediaSwitchBusy=false,localAutoplayRequest=false,localAutoplayPendingUntil=0,castExpanded=false,playerLayoutFrame=0,desktopRectFrame=0,episodePointerFrame=0,episodePointerEvent=null;
var $=function(id){return document.getElementById(id);};
var player=null,mediaPlayer=null,speedControl=null,speedMenu=null,episodeLauncher=null,voiceControl=null,chatControl=null,sideChatControl=null,voiceActivityNode=null,chatPanel=null,sideChatPanel=null,sideChatMessagesNode=null,danmakuLayer=null,chatMessages=[],episodeRenderSignature='',metaRenderSignature='',speedSteps=[.5,.75,1,1.25,1.5,2,3],playerDefaultSpeed=<?php echo json_encode($playerDefaultSpeed); ?>;
var desktopBridgeAvailable=!!(window.chrome&&window.chrome.webview),desktopMpvActive=false,desktopMpvState={position:0,duration:0,paused:true,speed:1,volume:.8},desktopPendingResult=null;
function sendDesktopMessage(type,data){if(!desktopBridgeAvailable)return;var message=Object.assign({type:type},data||{});try{window.chrome.webview.postMessage(message);}catch(e){desktopBridgeAvailable=false;}}
function desktopCurrentTime(){return desktopMpvActive?Number(desktopMpvState.position||0):Number(player&&player.currentTime||0);}
function desktopDuration(){return desktopMpvActive?Number(desktopMpvState.duration||0):Number(player&&player.duration||0);}
function desktopPaused(){return desktopMpvActive?!!desktopMpvState.paused:!!(player&&player.paused);}
function desktopSpeed(){return desktopMpvActive?Number(desktopMpvState.speed||1):Number(player&&player.playbackRate||1);}
function desktopVolume(){return desktopMpvActive?Number(desktopMpvState.volume||0):Number(player&&player.volume||0);}
function desktopCommand(command){if(!desktopMpvActive)return false;sendDesktopMessage('desktop-player-command',{command:command});return true;}
function setDesktopMpvActive(active){desktopMpvActive=!!active;var root=mediaPlayer&&mediaPlayer.template&&mediaPlayer.template.$player;if(root)root.classList.toggle('desktop-mpv-active',desktopMpvActive);if(player)player.style.opacity=desktopMpvActive?'0':'1';updateDesktopProgress();updateSpeedControl();}
function updateDesktopProgress(){if(!desktopMpvActive||!mediaPlayer||!mediaPlayer.template)return;var progress=mediaPlayer.template.$progress;if(!progress)return;var duration=desktopDuration(),position=desktopCurrentTime(),ratio=duration>0?Math.max(0,Math.min(1,position/duration)):0;var played=progress.querySelector('.art-progress-played');if(played)played.style.width=(ratio*100)+'%';var indicator=progress.querySelector('.art-progress-indicator');if(indicator)indicator.style.left=(ratio*100)+'%';var label=$('playerTopTime');if(label&&duration>0)label.textContent=Math.floor(position/60).toString().padStart(2,'0')+':'+Math.floor(position%60).toString().padStart(2,'0')+' / '+Math.floor(duration/60).toString().padStart(2,'0')+':'+Math.floor(duration%60).toString().padStart(2,'0');}
function sendDesktopPlayerRect(){if(!desktopBridgeAvailable)return;var box=$('playerContainer'),root=mediaPlayer&&mediaPlayer.template&&mediaPlayer.template.$player;if(!box||!root)return;var rect=box.getBoundingClientRect(),bottom=root.querySelector('.art-bottom'),controlsHeight=bottom?bottom.getBoundingClientRect().height:58;sendDesktopMessage('desktop-player-rect',{x:Math.round(rect.left),y:Math.round(rect.top),width:Math.round(rect.width),height:Math.round(rect.height),controlsHeight:Math.round(controlsHeight)});}
function bindDesktopBridge(){if(!desktopBridgeAvailable||window.__withuDesktopBridgeBound)return;window.__withuDesktopBridgeBound=true;window.chrome.webview.addEventListener('message',function(event){var message=event.data||{};if(message.type==='desktop-mpv-state'){desktopMpvState={position:Number(message.position||0),duration:Number(message.duration||0),paused:!!message.paused,speed:Number(message.speed||1),volume:Number(message.volume||.8)};desktopPendingResult=null;setDesktopMpvActive(!!message.active);if(desktopMpvActive)setSwitchLoading(false);updateDesktopProgress();}else if(message.type==='desktop-mpv-ended'){nextEpisode();}else if(message.type==='desktop-mpv-error'){setDesktopMpvActive(false);if(desktopPendingResult){var fallback=desktopPendingResult;desktopPendingResult=null;setStatus('桌面解码器不可用，回退网页播放器…');mediaPlayer.switchUrl(fallback.url).then(function(){player=mediaPlayer.video;patchDesktopVideoProxy();bindPlayerEvents(player);});}else{setStatus(message.message||'桌面解码器不可用');}}});sendDesktopMessage('desktop-shell-ready');}
bindDesktopBridge();
sendDesktopMessage('desktop-player-route',{route:location.href});
function patchDesktopVideoProxy(){if(!player||player.__withuDesktopProxy)return;var nativePlay=player.play.bind(player),nativePause=player.pause.bind(player);player.play=function(){if(desktopMpvActive){desktopMpvState.paused=false;desktopCommand('play');return Promise.resolve();}return nativePlay();};player.pause=function(){if(desktopMpvActive){desktopMpvState.paused=true;desktopCommand('pause');return;}nativePause();};player.__withuDesktopProxy=true;}
  function scheduleDesktopPlayerRect(){if(desktopRectFrame)return;desktopRectFrame=window.requestAnimationFrame(function(){desktopRectFrame=0;sendDesktopPlayerRect();});}
  window.addEventListener('resize',function(){scheduleDesktopPlayerRect();schedulePlayerLayout();});window.addEventListener('scroll',scheduleDesktopPlayerRect,true);
document.addEventListener('click',function(event){if(!desktopMpvActive)return;var root=mediaPlayer&&mediaPlayer.template&&mediaPlayer.template.$player;if(!root||!root.contains(event.target))return;var progress=event.target.closest('.art-progress');if(progress){event.preventDefault();event.stopImmediatePropagation();var rect=progress.getBoundingClientRect(),ratio=Math.max(0,Math.min(1,(event.clientX-rect.left)/Math.max(1,rect.width)));var target=desktopDuration()*ratio;desktopMpvState.position=target;desktopCommand('seek '+target.toFixed(3));updateDesktopProgress();return;}if(event.target.closest('.art-state')||(!event.target.closest('.art-bottom')&&!event.target.closest('.withu-episode-overlay')&&!event.target.closest('.withu-speed-menu'))){event.preventDefault();event.stopImmediatePropagation();desktopCommand(desktopPaused()?'play':'pause');}} ,true);
document.addEventListener('input',function(event){if(!desktopMpvActive)return;var volume=event.target.closest&&event.target.closest('.art-control-volume');if(volume){var value=Number(event.target.value);if(Number.isFinite(value)){desktopMpvState.volume=Math.max(0,Math.min(1,value));desktopCommand('volume '+(desktopMpvState.volume*100).toFixed(2));}}},true);
function fileType(name){var match=String(name||'').toLowerCase().match(/\.([a-z0-9]+)(?:[?#].*)?$/);return match?match[1]:'mp4';}
Artplayer.PLAYBACK_RATE=[.5,.75,1,1.25,1.5,2,3];
Artplayer.REMOVE_SRC_WHEN_DESTROY=true;
var withuNetSpeedEl=null,withuNetSpeedTimer=null,withuNetSpeedLast={ts:0,bytes:0,idx:0};
var withuHls=null;
function destroyWithuHls(){if(withuHls){try{withuHls.destroy();}catch(e){}withuHls=null;}}
function attachWithuHls(video,url){destroyWithuHls();if(!video)return;if(video.canPlayType('application/vnd.apple.mpegurl')){video.src=url;return;}if(window.Hls&&Hls.isSupported()){withuHls=new Hls({enableWorker:true,backBufferLength:90,manifestLoadingMaxRetry:20,levelLoadingMaxRetry:20,fragLoadingMaxRetry:20,manifestLoadingRetryDelay:500,levelLoadingRetryDelay:500,fragLoadingRetryDelay:500});withuHls.loadSource(url);withuHls.attachMedia(video);withuHls.on(Hls.Events.ERROR,function(event,data){if(!data||!data.fatal)return;if(data.type===Hls.ErrorTypes.NETWORK_ERROR){withuHls.startLoad();return;}if(data.type===Hls.ErrorTypes.MEDIA_ERROR){withuHls.recoverMediaError();return;}destroyWithuHls();playbackError();});return;}video.src=url;setStatus('当前浏览器不支持 HLS 播放，请更换浏览器。');}
function currentEpisodeId(){return Number(loadedMediaId||pendingMediaId||initialMediaId||0);}
function currentEpisodeIndex(){var id=currentEpisodeId();return currentEpisodes.findIndex(function(item){return Number(item.id)===id;});}
function currentMediaItem(){var id=currentEpisodeId();return mediaItems.find(function(item){return Number(item.id)===id;})||currentEpisodes.find(function(item){return Number(item.id)===id;})||null;}
function currentTopTitle(){var item=currentMediaItem(),fallback=($('detailTitle')&&$('detailTitle').textContent)||($('seriesTitle')&&$('seriesTitle').textContent)||initialName||'正在观看';if(!item)return fallback;var title=item.series_name||fallback||item.file_name||'正在观看';if(item.episode_number)return title+' 第 '+item.episode_number+' 集';return title;}
function previousEpisode(){var index=currentEpisodeIndex();if(index>0)selectMedia(Number(currentEpisodes[index-1].id));}
function nextEpisode(force){if(!force&&!playerAutoNextEnabled)return;var index=currentEpisodeIndex();if(index>=0&&index<currentEpisodes.length-1)selectMedia(Number(currentEpisodes[index+1].id));}
function applyPlayerLoadBackground(){var background=String(playerLoadBackground||'').trim();if(!background)return;var safe=background.replace(/\\/g,'\\\\').replace(/"/g,'\\"');var image='url("'+safe+'")';var root=mediaPlayer&&mediaPlayer.template&&mediaPlayer.template.$player;var poster=root&&root.querySelector('.art-poster');if(poster){poster.style.backgroundImage=image;poster.style.backgroundSize='cover';poster.style.backgroundPosition='center';}var loading=$('switchLoading');if(loading){loading.style.backgroundImage=image;loading.style.backgroundSize='cover';loading.style.backgroundPosition='center';}}
var prevIcon='<svg fill="none" stroke-width="2" xmlns="http://www.w3.org/2000/svg" height="22" width="22" viewBox="0 0 24 24" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M20.341 4.247l-8 7a1 1 0 0 0 0 1.506l8 7c.647 .565 1.659 .106 1.659 -.753v-14c0-.86-1.012-1.318-1.659-.753z" stroke-width="0" fill="currentColor"></path><path d="M9.341 4.247l-8 7a1 1 0 0 0 0 1.506l8 7c.647 .565 1.659 .106 1.659 -.753v-14c0-.86-1.012-1.318-1.659-.753z" stroke-width="0" fill="currentColor"></path></svg>';
var nextIcon='<svg fill="none" stroke-width="2" xmlns="http://www.w3.org/2000/svg" height="22" width="22" viewBox="0 0 24 24" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M2 5v14c0 .86 1.012 1.318 1.659 .753l8-7a1 1 0 0 0 0-1.506l-8-7a1 1 0 0 0-1.659 .753z" stroke-width="0" fill="currentColor"></path><path d="M13 5v14c0 .86 1.012 1.318 1.659 .753l8-7a1 1 0 0 0-1.659 .753z" stroke-width="0" fill="currentColor"></path></svg>';
  mediaPlayer=new Artplayer({container:'#playerContainer',url:'',type:'mp4',customType:{m3u8:function(video,url){attachWithuHls(video,url);},hls:function(video,url){attachWithuHls(video,url);}},autoplay:false,autoSize:false,autoMini:true,loop:false,flip:true,playbackRate:false,aspectRatio:true,screenshot:false,setting:true,hotkey:true,pip:false,mutex:true,fullscreen:true,fullscreenWeb:false,playsInline:true,lock:true,fastForward:true,autoPlayback:true,autoOrientation:true,airplay:false,theme:'#f5b6c8',moreVideoAttr:{'webkit-playsinline':true,playsInline:true,crossOrigin:'anonymous'},controls:[{name:'previous-button',index:10,position:'left',html:prevIcon,tooltip:'上一集',click:previousEpisode},{name:'next-button',index:11,position:'left',html:nextIcon,tooltip:'下一集',click:nextEpisode},{name:'chat-button',index:40,position:'left',html:'<form class="withu-danmaku-inline-form"><input class="withu-danmaku-inline-input" type="text" maxlength="60" autocomplete="off" placeholder="发消息"><button class="withu-danmaku-inline-send" type="submit">发送</button></form>',tooltip:'发消息',click:function(){},mounted:function(element){chatControl=element;chatControl.classList.add('withu-watch-only-control','withu-danmaku-inline-control');var form=chatControl.querySelector('.withu-danmaku-inline-form'),input=chatControl.querySelector('.withu-danmaku-inline-input');function syncInlineMessageState(){if(form)form.classList.toggle('has-message',!!(input&&input.value.trim()));}if(form)form.addEventListener('submit',function(event){event.preventDefault();event.stopPropagation();sendChatMessage();syncInlineMessageState();});if(form)form.addEventListener('click',function(event){event.stopPropagation();});if(input){input.addEventListener('click',function(event){event.stopPropagation();});input.addEventListener('input',syncInlineMessageState);input.addEventListener('keydown',function(event){event.stopPropagation();if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendChatMessage();syncInlineMessageState();}});syncInlineMessageState();}updateTogetherControls();}},{name:'speed-button',index:60,position:'right',html:'<span class="withu-speed-control">1x</span>',tooltip:'选择播放速度',click:function(){},mounted:function(element){speedControl=element;['pointerdown','mousedown','touchstart'].forEach(function(type){element.addEventListener(type,function(event){event.stopPropagation();});});element.addEventListener('click',function(event){event.preventDefault();event.stopPropagation();toggleSpeedMenu();});updateSpeedControl();}},{name:'episode-button',index:62,position:'right',html:'<span class="episode-launcher-pill"><span class="episode-launcher-icon" aria-hidden="true">☷</span><span class="episode-launcher-label">选集</span></span>',tooltip:'打开选集',click:function(){toggleEpisodeOverlay();},mounted:function(element){episodeLauncher=element;episodeLauncher.classList.add('episode-launcher');episodeLauncher.setAttribute('aria-expanded','false');episodeLauncher.setAttribute('aria-label','打开选集');episodeLauncher.setAttribute('aria-controls','episodeOverlay');}},{name:'side-chat-button',index:64,position:'right',html:'<span class="withu-side-chat-control">聊天</span>',tooltip:'聊天窗口',click:function(){toggleSideChatPanel();},mounted:function(element){sideChatControl=element;sideChatControl.classList.add('withu-watch-only-control');sideChatControl.setAttribute('aria-expanded','false');updateTogetherControls();}},{name:'voice-button',index:66,position:'right',html:'<span class="withu-voice-control">连麦</span>',tooltip:'连麦',click:function(){toggleVoice();},mounted:function(element){voiceControl=element;voiceControl.classList.add('withu-watch-only-control');updateVoiceControl();updateTogetherControls();}}],settings:[]});
  player=mediaPlayer.video;
  applyPlayerLoadBackground();
  patchDesktopVideoProxy();
  function mountWatermark(){var node=$('playerWatermark'),root=mediaPlayer&&mediaPlayer.template&&mediaPlayer.template.$player;if(!node||!root)return;if(node.parentNode!==root)root.appendChild(node);node.classList.add('is-mounted');}

function withuFormatSpeed(bps){if(!(bps>0))return '--';var by=bps/8;if(by>=1048576)return (by/1048576).toFixed(1)+' MB/s';if(by>=1024)return Math.round(by/1024)+' KB/s';return Math.round(by)+' B/s';}
function updateWithuNetSpeed(){var el=withuNetSpeedEl;if(!el)return;var speed=0;try{if(withuHls&&typeof withuHls.bandwidthEstimate==='number'&&withuHls.bandwidthEstimate>0){speed=withuHls.bandwidthEstimate/8;withuNetSpeedLast={ts:0,bytes:0,idx:0};}else{var video=mediaPlayer&&mediaPlayer.video;var src=video?(video.currentSrc||video.src||''):'';if(src){var now=performance.now();var all=performance.getEntriesByType('resource');var base=withuNetSpeedLast.idx||0;var bytes=0;for(var j=base;j<all.length;j++){var e=all[j];var n=e.name||'';var match=n===src||(src.indexOf('m3u8')>-1&&/(.m3u8|.ts|.m4s|.mp4)(\?|$)/.test(n))||(src&&n.indexOf(src)>-1);if(match&&e.transferSize>0)bytes+=e.transferSize;}if(withuNetSpeedLast.ts>0){var dt=(now-withuNetSpeedLast.ts)/1000;var db=bytes-withuNetSpeedLast.bytes;if(dt>0&&db>=0)speed=db/dt;}withuNetSpeedLast={ts:now,bytes:bytes,idx:all.length};}}}catch(e){}el.textContent='网速 '+withuFormatSpeed(speed);}
function mountNetSpeed(){var el=document.getElementById('playerNetSpeed');if(!el){var root=mediaPlayer&&mediaPlayer.template&&mediaPlayer.template.$player;if(root)el=root.querySelector('.withu-player-topbar-speed');}withuNetSpeedEl=el||null;if(withuNetSpeedTimer){clearInterval(withuNetSpeedTimer);withuNetSpeedTimer=null;}if(!withuNetSpeedEl)return;withuNetSpeedTimer=setInterval(updateWithuNetSpeed,1000);updateWithuNetSpeed();}
  function mountPlayerTopBar(){var node=$('playerTopBar'),root=mediaPlayer&&mediaPlayer.template&&mediaPlayer.template.$player;if(!root)return;if(!node){node=document.createElement('div');node.id='playerTopBar';node.className='withu-player-topbar is-solo';node.setAttribute('aria-live','polite');node.innerHTML='<span class="withu-player-topbar-left"><img class="withu-player-topbar-logo" src="'+esc(playerLogoUrl)+'" alt="withU"><span id="playerTopTitle" class="withu-player-topbar-title"></span></span><span id="playerTopWatch" class="withu-player-topbar-watch">一起看<span class="withu-player-topbar-heart">❤</span><span id="playerTopOnlineText">宝宝离线中</span></span><span class="withu-player-topbar-right"><span id="playerNetSpeed" class="withu-player-topbar-speed">网速 --</span><span id="playerTopTime" class="withu-player-topbar-time">--:--</span></span>';}if(node.parentNode!==root)root.appendChild(node);updatePlayerTopBar();}
  function mountVoiceActivity(){var root=mediaPlayer&&mediaPlayer.template&&mediaPlayer.template.$player;if(!root)return null;var node=$('voiceActivity');if(!node){node=document.createElement('div');node.id='voiceActivity';node.className='withu-voice-activity';node.setAttribute('aria-hidden','true');node.innerHTML='<span class="withu-voice-activity-icon" aria-hidden="true"><svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 14a3 3 0 0 0 3-3V5a3 3 0 0 0-6 0v6a3 3 0 0 0 3 3Zm5-3a1 1 0 1 1 2 0 7 7 0 0 1-6 6.93V20h2a1 1 0 1 1 0 2H9a1 1 0 1 1 0-2h2v-2.07A7 7 0 0 1 5 11a1 1 0 1 1 2 0 5 5 0 0 0 10 0Z"/></svg></span><span class="withu-voice-activity-bars" aria-hidden="true"><span></span><span></span><span></span><span></span></span>';}if(node.parentNode!==root)root.appendChild(node);voiceActivityNode=node;return node;}
  function setVoiceActivityVisible(active){var node=voiceActivityNode||mountVoiceActivity();if(!node)return;node.classList.toggle('is-speaking',!!active);}
  function updatePlayerTopTime(){var node=$('playerTopTime');if(!node)return;node.textContent=new Date().toLocaleTimeString('zh-CN',{hour:'2-digit',minute:'2-digit',hour12:false});}
  function updatePlayerTopBar(){var bar=$('playerTopBar'),title=$('playerTopTitle'),onlineText=$('playerTopOnlineText');if(!bar)return;var together=!localOnly&&roomJoined;bar.classList.toggle('is-solo',!together);bar.classList.toggle('is-together',together);bar.classList.toggle('is-partner-online',together&&partnerOnline);if(title)title.textContent=currentTopTitle();if(onlineText)onlineText.textContent=partnerOnline?'宝宝在线中':'宝宝离线中';updatePlayerTopTime();}
  mountWatermark();
  mountPlayerTopBar();
  mountNetSpeed();
  mountVoiceActivity();
  function syncEpisodePanelHeight(){var layout=document.querySelector('.player-layout'),playerBox=document.querySelector('.player-stage')||$('playerContainer');if(!layout||!playerBox)return;var height=Math.round(playerBox.getBoundingClientRect().height);if(height>0)layout.style.setProperty('--withu-player-height',height+'px');}
  function syncPlayerControlLayout(){var root=mediaPlayer&&mediaPlayer.template&&mediaPlayer.template.$player;if(!root)return;var controls=root.querySelector('.art-bottom .art-controls')||root.querySelector('.art-controls'),left=root.querySelector('.art-controls-left'),right=root.querySelector('.art-controls-right'),volume=root.querySelector('.art-control-volume'),chat=root.querySelector('.art-control-chat-button');if(right&&volume&&volume.parentNode!==right)right.appendChild(volume);if(controls&&chat&&chat.parentNode!==controls)controls.appendChild(chat);if(!controls||!left||!right)return;var stateClasses=['is-withu-compact','is-withu-tiny','is-withu-overflow','is-withu-ultra','is-withu-micro'];stateClasses.forEach(function(name){controls.classList.remove(name);});controls.style.setProperty('--withu-input-width','220px');function measure(){var c=controls.getBoundingClientRect(),l=left.getBoundingClientRect(),r=right.getBoundingClientRect(),center=c.left+c.width/2,gap=10,leftLimit=l.right+gap,rightLimit=r.left-gap,centered=Math.floor(Math.max(0,Math.min(center-leftLimit,rightLimit-center)*2));return{controls:c,left:l,right:r,centered:centered,total:c.width,side:l.width+r.width};}var data=measure();if(data.side+88>data.total||data.centered<150){controls.classList.add('is-withu-overflow');data=measure();}if(data.side+24>data.total||data.centered<96){controls.classList.add('is-withu-ultra');data=measure();}if(data.side+8>data.total){controls.classList.add('is-withu-micro');data=measure();}var width=Math.floor(Math.min(250,Math.max(0,data.centered)));if(width<96){controls.classList.add('is-withu-tiny');controls.style.setProperty('--withu-input-width','0px');}else{controls.style.setProperty('--withu-input-width',width+'px');if(width<150)controls.classList.add('is-withu-compact');}}
  function schedulePlayerLayout(){if(playerLayoutFrame)return;playerLayoutFrame=window.requestAnimationFrame(function(){playerLayoutFrame=0;syncEpisodePanelHeight();syncPlayerControlLayout();});}
  schedulePlayerLayout();if(window.ResizeObserver){var playerControlResizeObserver=new ResizeObserver(function(){schedulePlayerLayout();});var playerControlResizeRoot=mediaPlayer&&mediaPlayer.template&&mediaPlayer.template.$player,playerPanelResizeRoot=document.querySelector('.player-stage')||$('playerContainer');if(playerControlResizeRoot)playerControlResizeObserver.observe(playerControlResizeRoot);if(playerPanelResizeRoot)playerControlResizeObserver.observe(playerPanelResizeRoot);}
var episodeOverlay=document.createElement('div');episodeOverlay.id='episodeOverlay';episodeOverlay.className='withu-episode-overlay';episodeOverlay.setAttribute('aria-hidden','true');var episodeOverlayHost=$('playerContainer'),artTemplate=mediaPlayer.template||{},episodeFullscreenHost=artTemplate.$player||((artTemplate.$container&&typeof artTemplate.$container.querySelector==='function')?artTemplate.$container.querySelector('.art-video-player'):null)||document.querySelector('#playerContainer .art-video-player')||episodeOverlayHost,episodeBottomHost=artTemplate.$bottom||episodeFullscreenHost,episodeCloseTimer=null,sideChatCloseTimer=null;
 speedMenu=document.createElement('div');speedMenu.className='withu-speed-menu';speedMenu.hidden=true;speedMenu.setAttribute('aria-label','播放速度');speedMenu.addEventListener('pointerdown',function(event){event.stopPropagation();});speedMenu.addEventListener('click',function(event){event.stopPropagation();});
  function applyCustomSpeed(value){if(!player)return false;var speed=Number(value);if(!Number.isFinite(speed)||speed<.1||speed>4){setStatus('自定义倍速请输入 0.1 至 4 之间的数字');return false;}var wasPaused=desktopPaused();speed=Math.round(speed*100)/100;beginRemoteApply(900);if(desktopMpvActive){desktopMpvState.speed=speed;desktopCommand('rate '+speed.toFixed(2));}else{player.playbackRate=speed;}updateSpeedControl();if(wasPaused&&!desktopPaused())desktopCommand('pause');if(!wasPaused&&desktopPaused())desktopCommand('play');if(!localOnly&&roomJoined)sendEvent('speed');return true;}
  function renderSpeedMenu(){if(!speedMenu||!player)return;var current=Number(player.playbackRate||1);speedMenu.innerHTML='<div class="withu-speed-menu-title">播放速度</div>'+speedSteps.map(function(value){var active=Math.abs(current-value)<.01;return '<button type="button" class="withu-speed-option '+(active?'is-active':'')+'" data-speed-value="'+value+'">'+value+'x</button>';}).join('')+'<div class="withu-speed-custom"><input class="withu-speed-custom-input" type="number" min="0.1" max="4" step="0.05" inputmode="decimal" placeholder="自定义倍速" aria-label="自定义倍速"><button type="button" class="withu-speed-custom-apply">应用</button></div>';speedMenu.querySelectorAll('[data-speed-value]').forEach(function(button){button.onclick=function(event){event.preventDefault();event.stopImmediatePropagation();applyCustomSpeed(button.getAttribute('data-speed-value'));toggleSpeedMenu(false);};});var input=speedMenu.querySelector('.withu-speed-custom-input'),apply=speedMenu.querySelector('.withu-speed-custom-apply');if(input){input.value=(current%1===0?String(current):String(current.toFixed(2)).replace(/0+$/,'').replace(/\.$/,''));['click','pointerdown','mousedown','touchstart','focus','input'].forEach(function(type){input.addEventListener(type,function(event){event.stopImmediatePropagation();});});input.addEventListener('keydown',function(event){event.stopImmediatePropagation();if(event.key==='Enter'){event.preventDefault();if(applyCustomSpeed(input.value))toggleSpeedMenu(false);}});}if(apply){['pointerdown','mousedown','touchstart','click'].forEach(function(type){apply.addEventListener(type,function(event){event.preventDefault();event.stopImmediatePropagation();if(type==='click'&&applyCustomSpeed(input&&input.value))toggleSpeedMenu(false);});});}}
function closePlayerPopups(except){if(except!=='episode')toggleEpisodeOverlay(false);if(except!=='speed')toggleSpeedMenu(false);if(except!=='chat')toggleChatPanel(false);if(except!=='sideChat')toggleSideChatPanel(false);}
function clearSideChatClose(){if(sideChatCloseTimer){clearTimeout(sideChatCloseTimer);sideChatCloseTimer=null;}}
function isSideChatFocused(){return !!(sideChatPanel&&!sideChatPanel.hidden&&(sideChatPanel.matches(':hover')||sideChatPanel.contains(document.activeElement)||(sideChatControl&&(sideChatControl.matches(':hover')||sideChatControl.contains(document.activeElement)))));}
function scheduleSideChatClose(){clearSideChatClose();sideChatCloseTimer=setTimeout(function(){sideChatCloseTimer=null;if(!isSideChatFocused())toggleSideChatPanel(false);},3000);}
function positionPopupAboveControl(panel,control,gap){if(!panel||!control)return;var host=panel.parentElement||episodeBottomHost||episodeFullscreenHost||episodeOverlayHost;if(!host)return;var hostRect=host.getBoundingClientRect(),buttonRect=control.getBoundingClientRect(),safeGap=typeof gap==='number'?gap:8,availableAbove=Math.max(120,buttonRect.top-hostRect.top-safeGap-8);if(panel.classList.contains('withu-episode-overlay')){var itemsCount=panel.querySelectorAll('.episode-btn').length||1;var launcherWidth=buttonRect.width||68;var btnH=30,btnGap=6,padH=36;var maxH=Math.max(150,Math.min(availableAbove,hostRect.height*.82,470));var rowsPerCol=Math.max(1,Math.floor((maxH-padH+btnGap)/(btnH+btnGap)));var cols=Math.max(1,Math.ceil(itemsCount/rowsPerCol));var baseW=Math.max(58,Math.min(Math.round(launcherWidth),92));var width=Math.max(baseW,Math.min(cols*baseW,hostRect.width-16));var rows=Math.min(itemsCount,rowsPerCol);var naturalH=rows*(btnH+btnGap)-btnGap+padH;var episodeHeight=Math.max(64,Math.min(naturalH,maxH));panel.style.setProperty('--ep-cols',String(cols),'important');panel.style.setProperty('--ep-overlay-w',width+'px','important');panel.style.setProperty('width',width+'px','important');panel.style.setProperty('max-width',(hostRect.width-16)+'px','important');panel.style.setProperty('height',episodeHeight+'px','important');panel.style.setProperty('max-height',episodeHeight+'px','important');panel.style.setProperty('transform','none','important');}else if(panel.classList.contains('withu-side-chat-panel')||panel.classList.contains('withu-chat-panel')){panel.style.setProperty('max-height',availableAbove+'px','important');}var panelWidth=panel.offsetWidth||Math.min(320,Math.max(160,hostRect.width-16));var left=buttonRect.left-hostRect.left+(buttonRect.width-panelWidth)/2,maxLeft=Math.max(8,hostRect.width-panelWidth-8);left=Math.max(8,Math.min(maxLeft,left));var bottom=hostRect.bottom-buttonRect.top+safeGap;panel.style.setProperty('left',left+'px','important');panel.style.setProperty('right','auto','important');panel.style.setProperty('top','auto','important');panel.style.setProperty('bottom',bottom+'px','important');panel.classList.add('is-anchored');}
function toggleSpeedMenu(force){if(!speedMenu)return;var open=typeof force==='boolean'?force:speedMenu.hidden; if(open){closePlayerPopups('speed');if(speedMenu.parentNode!==episodeBottomHost)episodeBottomHost.appendChild(speedMenu);renderSpeedMenu();speedMenu.hidden=false;window.requestAnimationFrame(function(){positionPopupAboveControl(speedMenu,speedControl,8);});}else{speedMenu.hidden=true;}}
function togetherControlsEnabled(){return !localOnly&&roomJoined;}
function updateVoiceControl(){if(!voiceControl)return;var label=voiceControl.querySelector('.withu-voice-control');if(label)label.textContent=voiceActive?'闭麦':'连麦';voiceControl.classList.toggle('is-active',!!voiceActive);voiceControl.setAttribute('aria-label',voiceActive?'闭麦':'连麦');voiceControl.setAttribute('title',voiceActive?'闭麦':'连麦');}
function updateTogetherControls(){var enabled=togetherControlsEnabled();[voiceControl,chatControl,sideChatControl].forEach(function(control){if(control)control.hidden=!enabled;});if(!enabled){toggleChatPanel(false);toggleSideChatPanel(false);if(voiceActive)stopVoice(false);}}
function toggleVoice(){if(!togetherControlsEnabled()){setStatus('一起看时才能连麦');return;}if(voiceActive)stopVoice();else startVoice();}
function ensureChatPanel(){if(chatPanel)return chatPanel;chatPanel=document.createElement('div');chatPanel.className='withu-chat-panel';chatPanel.hidden=true;chatPanel.innerHTML='<form class="withu-chat-form"><input class="withu-chat-input" type="text" maxlength="60" autocomplete="off" placeholder="发一条弹幕…"><button class="withu-chat-send" type="submit">发送</button></form>';var host=episodeBottomHost||episodeFullscreenHost||episodeOverlayHost;if(host)host.appendChild(chatPanel);chatPanel.querySelector('form').addEventListener('submit',function(event){event.preventDefault();sendChatMessage();});return chatPanel;}
function toggleChatPanel(force){if(force===false){if(!chatPanel)return;chatPanel.classList.remove('is-open');setTimeout(function(){if(chatPanel&&!chatPanel.classList.contains('is-open'))chatPanel.hidden=true;},180);return;}if(!togetherControlsEnabled()){setStatus('一起看时才能发弹幕');return;}var panel=ensureChatPanel(),open=typeof force==='boolean'?force:panel.hidden;if(open){closePlayerPopups('chat');if(panel.parentNode!==episodeBottomHost)episodeBottomHost.appendChild(panel);panel.hidden=false;window.requestAnimationFrame(function(){positionPopupAboveControl(panel,chatControl,8);panel.classList.add('is-open');var input=panel.querySelector('.withu-chat-input');if(input)input.focus();});}else{panel.classList.remove('is-open');setTimeout(function(){if(!panel.classList.contains('is-open'))panel.hidden=true;},180);}}
function ensureDanmakuLayer(){if(danmakuLayer)return danmakuLayer;danmakuLayer=document.createElement('div');danmakuLayer.className='withu-danmaku-layer';var host=episodeFullscreenHost||episodeOverlayHost;if(host)host.appendChild(danmakuLayer);return danmakuLayer;}
function showDanmaku(text,mine){text=String(text||'').trim();if(!text)return;var layer=ensureDanmakuLayer(),item=document.createElement('div'),lane=layer.childElementCount%5;item.className='withu-danmaku-item'+(mine?' is-mine':'');item.textContent=text;item.style.top=(lane*18+Math.random()*5)+'%';item.style.animationDuration=(mine?'6.2s':'7s');layer.appendChild(item);setTimeout(function(){if(item.parentNode)item.parentNode.removeChild(item);},7600);}
function ensureSideChatPanel(){if(sideChatPanel)return sideChatPanel;sideChatPanel=document.createElement('div');sideChatPanel.className='withu-side-chat-panel';sideChatPanel.hidden=true;sideChatPanel.innerHTML='<div class="withu-side-chat-header"><span>聊天</span><button type="button" class="withu-side-chat-close" aria-label="收起聊天">×</button></div><div class="withu-side-chat-messages"><div class="withu-side-chat-empty">还没有聊天消息</div></div><form class="withu-side-chat-form"><input class="withu-side-chat-input" type="text" maxlength="120" autocomplete="off" placeholder="和宝宝说点什么…"><button class="withu-side-chat-send" type="submit">发送</button></form>';var host=episodeFullscreenHost||episodeOverlayHost;if(host)host.appendChild(sideChatPanel);sideChatMessagesNode=sideChatPanel.querySelector('.withu-side-chat-messages');sideChatPanel.addEventListener('mouseenter',clearSideChatClose);sideChatPanel.addEventListener('mouseleave',scheduleSideChatClose);sideChatPanel.addEventListener('focusin',clearSideChatClose);sideChatPanel.addEventListener('focusout',function(){setTimeout(function(){if(!isSideChatFocused())scheduleSideChatClose();},0);});sideChatPanel.querySelector('.withu-side-chat-close').addEventListener('click',function(){toggleSideChatPanel(false);});sideChatPanel.querySelector('form').addEventListener('submit',function(event){event.preventDefault();sendSideChatMessage();});renderSideChatMessages();return sideChatPanel;}
function toggleSideChatPanel(force){if(force===false){clearSideChatClose();if(!sideChatPanel)return;sideChatPanel.classList.remove('is-open');if(sideChatControl){sideChatControl.classList.remove('is-active');sideChatControl.setAttribute('aria-expanded','false');}setTimeout(function(){if(sideChatPanel&&!sideChatPanel.classList.contains('is-open'))sideChatPanel.hidden=true;},220);return;}if(!togetherControlsEnabled()){setStatus('一起看时才能打开聊天');return;}var panel=ensureSideChatPanel(),open=typeof force==='boolean'?force:panel.hidden;if(open){clearSideChatClose();closePlayerPopups('sideChat');if(panel.parentNode!==episodeFullscreenHost)episodeFullscreenHost.appendChild(panel);panel.hidden=false;if(sideChatControl){sideChatControl.classList.add('is-active');sideChatControl.setAttribute('aria-expanded','true');}window.requestAnimationFrame(function(){panel.classList.add('is-open');var input=panel.querySelector('.withu-side-chat-input');if(input)input.focus();});}else{toggleSideChatPanel(false);}}
function addChatMessage(text,mine,source){text=String(text||'').trim();if(!text)return;chatMessages.push({text:text,mine:!!mine,source:source||'chat',time:new Date()});if(chatMessages.length>80)chatMessages=chatMessages.slice(-80);renderSideChatMessages();}
function renderSideChatMessages(){if(!sideChatMessagesNode)return;if(!chatMessages.length){sideChatMessagesNode.innerHTML='<div class="withu-side-chat-empty">还没有聊天消息</div>';return;}sideChatMessagesNode.innerHTML=chatMessages.map(function(message){return '<div class="withu-side-chat-row '+(message.mine?'is-mine':'')+'"><span class="withu-side-chat-name">'+(message.mine?'我':'宝宝')+(message.source==='danmaku'?' · 弹幕':'')+'</span><span class="withu-side-chat-bubble">'+esc(message.text)+'</span></div>';}).join('');sideChatMessagesNode.scrollTop=sideChatMessagesNode.scrollHeight;}
function sendSideChatMessage(){if(!togetherControlsEnabled())return;var panel=ensureSideChatPanel(),input=panel.querySelector('.withu-side-chat-input'),text=String(input&&input.value||'').trim();if(!text)return;if(input)input.value='';addChatMessage(text,true,'chat');sendEvent('chat_message',{payload:JSON.stringify({text:text,kind:'side_chat',nonce:Date.now()+'-'+Math.random().toString(16).slice(2)})});}
function sendChatMessage(){if(!togetherControlsEnabled())return;var input=(chatControl&&chatControl.querySelector('.withu-danmaku-inline-input'))||(chatPanel&&chatPanel.querySelector('.withu-chat-input')),text=String(input&&input.value||'').trim();if(!text)return;if(input){input.value='';input.focus();}addChatMessage(text,true,'danmaku');showDanmaku(text,true);sendEvent('chat_message',{payload:JSON.stringify({text:text,kind:'danmaku',nonce:Date.now()+'-'+Math.random().toString(16).slice(2)})});toggleChatPanel(false);}
function parseWatchEventPayload(event){try{var outer=JSON.parse(event.payload||'{}');return typeof outer.payload==='string'?JSON.parse(outer.payload):outer.payload||{};}catch(e){return {};}}
 function mountEpisodeControls(forceFullscreen){var overlayTarget=episodeBottomHost||episodeOverlayHost;if(!overlayTarget)return;if(episodeOverlay.parentNode!==overlayTarget)overlayTarget.appendChild(episodeOverlay);episodeOverlay.classList.toggle('is-fullscreen',!!forceFullscreen);mountPlayerTopBar();syncPlayerControlLayout();positionEpisodeOverlay();}
  episodeOverlay.addEventListener('pointerdown',function(event){event.stopPropagation();});
  mountEpisodeControls(false);mediaPlayer.on('fullscreen',function(active){window.requestAnimationFrame(function(){mountEpisodeControls(Boolean(active));schedulePlayerLayout();});});mediaPlayer.on('fullscreenWeb',function(active){window.requestAnimationFrame(function(){mountEpisodeControls(Boolean(active));schedulePlayerLayout();});});mediaPlayer.on('control',function(show){schedulePlayerLayout();if(!show){closePlayerPopups('sideChat');scheduleSideChatClose();}});mediaPlayer.on('blur',function(){closePlayerPopups('sideChat');scheduleSideChatClose();});document.addEventListener('fullscreenchange',function(){window.requestAnimationFrame(function(){mountEpisodeControls();schedulePlayerLayout();});});
 function clearEpisodeClose(){if(episodeCloseTimer){clearTimeout(episodeCloseTimer);episodeCloseTimer=null;}}
 function scheduleEpisodeClose(){clearEpisodeClose();episodeCloseTimer=setTimeout(function(){toggleEpisodeOverlay(false);},3000);}
 function positionEpisodeOverlay(){positionPopupAboveControl(episodeOverlay,episodeLauncher,10);}
 function centerActiveEpisodeInPanel(){var active=episodeOverlay.querySelector('.episode-btn.active'),list=active&&active.closest('.withu-episode-list');if(!active||!list)return;var target=active.offsetTop-(list.clientHeight-active.offsetHeight)/2;list.scrollTop=Math.max(0,target);}
 function toggleEpisodeOverlay(force){var open=typeof force==='boolean'?force:!episodeOverlay.classList.contains('is-open');clearEpisodeClose();if(open)closePlayerPopups('episode');episodeOverlay.classList.toggle('is-open',open);episodeOverlay.setAttribute('aria-hidden',open?'false':'true');if(episodeLauncher)episodeLauncher.setAttribute('aria-expanded',open?'true':'false');if(open){window.requestAnimationFrame(function(){positionEpisodeOverlay();refreshEpisodeMarquees();window.requestAnimationFrame(centerActiveEpisodeInPanel);});}}
 function handleEpisodePointerMove(event){if(!episodeOverlay.classList.contains('is-open')||!episodeLauncher)return;episodePointerEvent=event;if(episodePointerFrame)return;episodePointerFrame=window.requestAnimationFrame(function(){episodePointerFrame=0;var currentEvent=episodePointerEvent;episodePointerEvent=null;if(!currentEvent||!episodeOverlay.classList.contains('is-open')||!episodeLauncher)return;var x=Number(currentEvent.clientX),y=Number(currentEvent.clientY);if(!Number.isFinite(x)||!Number.isFinite(y))return;var buttonRect=episodeLauncher.getBoundingClientRect(),panelRect=episodeOverlay.getBoundingClientRect();var inButton=x>=buttonRect.left&&x<=buttonRect.right&&y>=buttonRect.top&&y<=buttonRect.bottom;var inPanel=x>=panelRect.left&&x<=panelRect.right&&y>=panelRect.top&&y<=panelRect.bottom;if(inButton||inPanel)clearEpisodeClose();else scheduleEpisodeClose();});}
 episodeOverlay.addEventListener('mouseenter',clearEpisodeClose);episodeOverlay.addEventListener('mouseleave',scheduleEpisodeClose);document.addEventListener('pointermove',handleEpisodePointerMove);document.addEventListener('click',function(event){if(episodeOverlay.classList.contains('is-open')&&!event.target.closest('#episodeOverlay,.episode-launcher'))toggleEpisodeOverlay(false);if(speedMenu&&!speedMenu.hidden&&!speedMenu.contains(event.target)&&!(speedControl&&speedControl.contains(event.target)))toggleSpeedMenu(false);if(chatPanel&&!chatPanel.hidden&&!chatPanel.contains(event.target)&&!(chatControl&&chatControl.contains(event.target)))toggleChatPanel(false);if(sideChatPanel&&!sideChatPanel.hidden&&!sideChatPanel.contains(event.target)&&!(sideChatControl&&sideChatControl.contains(event.target)))scheduleSideChatClose();});
 document.addEventListener('keydown',function(event){if(event.key==='Escape')closePlayerPopups();});
 window.addEventListener('resize',function(){schedulePlayerLayout();refreshEpisodeMarquees();if(episodeOverlay.classList.contains('is-open'))positionEpisodeOverlay();if(speedMenu&&!speedMenu.hidden)positionPopupAboveControl(speedMenu,speedControl,8);if(chatPanel&&!chatPanel.hidden)positionPopupAboveControl(chatPanel,chatControl,8);});
  function setSwitchLoading(active,label){var node=$('switchLoading');if(!node){node=document.createElement('div');node.id='switchLoading';node.className='withu-switch-loading';node.hidden=true;node.setAttribute('aria-live','polite');node.innerHTML='<div class="withu-switch-loading-box"><span class="withu-switch-loading-spinner" aria-hidden="true"></span><span id="switchLoadingText">正在切换选集…</span></div>';var container=$('playerContainer');if(container)container.appendChild(node);}applyPlayerLoadBackground();var text=$('switchLoadingText');if(text&&label)text.textContent=label;node.hidden=!active;node.setAttribute('aria-busy',active?'true':'false');if(!active)mediaSwitchBusy=false;}
  function stopCurrentPlaybackForSwitch(){
   mediaSwitchSerial++;
   if(playbackSourceController){playbackSourceController.abort();playbackSourceController=null;}
   beginRemoteApply(2600);
   localAutoplayPendingUntil=0;
   if(desktopMpvActive){desktopCommand('pause');desktopMpvState.paused=true;desktopMpvState.position=0;setDesktopMpvActive(false);desktopCommand('stop');}
   destroyWithuHls();
   if(!player)return;
   try{player.pause();}catch(e){}
   try{player.removeAttribute('src');player.load();}catch(e){}
  }
  function playbackError(error){setSwitchLoading(false);setStatus('WebDAV 直链不可用，请检查 OpenList 或稍后重试。');}
 function resolvePlaybackSource(url,signal){return fetch(url,{headers:{'Accept':'application/json'},credentials:'same-origin',cache:'no-store',signal:signal}).then(function(response){return response.text().then(function(text){var payload={};try{payload=text?JSON.parse(text):{};}catch(e){throw new Error('播放接口返回的不是有效 JSON');}if(!response.ok||payload.success===false||!payload.url)throw new Error(payload.message||'播放接口没有返回可播放地址');return payload;});});}
  function setPlayerSource(url,name,forcedType,preservePosition,autoplayOverride,announceAutoplay){
   var switchId=++mediaSwitchSerial;
   if(playbackSourceController)playbackSourceController.abort();
   var sourceController=new AbortController();playbackSourceController=sourceController;
   var wasPlaying=player&&!desktopPaused();
   var position=preservePosition&&player?desktopCurrentTime():0;
   var shouldAutoplay=typeof autoplayOverride==='boolean'?autoplayOverride:(wasPlaying||watchAutoplayEnabled);
   beginRemoteApply(2200);
   mediaPlayer.option.id=String(pendingMediaId);
   setStatus('正在获取 '+(name||'当前选集')+'…');
   return resolvePlaybackSource(url,sourceController.signal).then(function(result){
    if(switchId!==mediaSwitchSerial)return;
    setStatus('正在加载 WebDAV 直链 '+(name||'当前选集')+'…');
    var type=forcedType||result.type||fileType(result.url||name||initialName);
    if(type==='hls')type='m3u8';
    if(switchId!==mediaSwitchSerial)return;
    if(type!=='m3u8')destroyWithuHls();
    mediaPlayer.option.type=type;
    setStatus('正在切换到 '+(name||'当前选集')+'…');

    if(desktopBridgeAvailable){
     desktopPendingResult={url:result.url,type:type};
     setDesktopMpvActive(false);
     setStatus('正在使用桌面 libmpv 解码…');
     sendDesktopMessage('desktop-player-source',{url:result.url,type:type,autoplay:shouldAutoplay,position:position});
     sendDesktopPlayerRect();
     return;
    }

    return mediaPlayer.switchUrl(result.url).then(function(){
     if(switchId!==mediaSwitchSerial)return;
     player=mediaPlayer.video;
     bindPlayerEvents(player);
     mountWatermark();
     mountPlayerTopBar();
     mountNetSpeed();
     var clearSwitchLoading=function(){if(switchId===mediaSwitchSerial)setSwitchLoading(false);};
     if(player){
      player.addEventListener('loadedmetadata',clearSwitchLoading,{once:true});
      player.addEventListener('canplay',clearSwitchLoading,{once:true});
      if((!roomJoined||localOnly)&&playerDefaultSpeed){var defaultSpeed=Number(playerDefaultSpeed)||1;if(Math.abs(Number(player.playbackRate||1)-defaultSpeed)>.01){beginRemoteApply(800);player.playbackRate=defaultSpeed;}}
      if(position>0&&Number.isFinite(position))player.currentTime=position;
      if(shouldAutoplay){if(announceAutoplay)localAutoplayPendingUntil=Date.now()+3000;Promise.resolve(player.play()).then(function(){if(announceAutoplay)setTimeout(function(){if(switchId===mediaSwitchSerial&&!localOnly&&roomJoined&&!player.paused)sendEvent('play');},2400);}).catch(function(){if(announceAutoplay)localAutoplayPendingUntil=0;});}
     }
    });
   }).catch(function(error){
    if(error&&error.name==='AbortError')return;
    if(switchId===mediaSwitchSerial){localAutoplayPendingUntil=0;setSwitchLoading(false);playbackError(error);}
   }).then(function(result){if(playbackSourceController===sourceController)playbackSourceController=null;return result;});
  }
 player.addEventListener('loadstart',function(){setStatus('正在加载播放地址…');});player.addEventListener('canplay',function(){setStatus('已进入 WithU Watch');});player.addEventListener('error',playbackError);
 function jsonRequest(url,action,data,method,requestOptions){var get=method==='GET',query=new URLSearchParams({action:action}),options=requestOptions||{};if(get&&data)Object.keys(data).forEach(function(k){query.set(k,String(data[k]));});return fetch(url+'?'+query.toString(),{method:method||'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:get?undefined:JSON.stringify(Object.assign({},data||{},{_token:csrf})),signal:options.signal}).then(function(r){return r.text().then(function(text){var payload={};try{payload=text?JSON.parse(text):{};}catch(e){}if(!r.ok){payload.success=false;payload.message=payload.message||('服务暂时不可用（HTTP '+r.status+'）');}return payload;});});}
function watchRequest(action,data,method){return jsonRequest(watchApi,action,data,method);}
 function setStatus(text){var node=$('status');if(node)node.textContent=text;updatePlayerTopBar();}
 function beginRemoteApply(duration){applying=true;if(applyingTimer)clearTimeout(applyingTimer);applyingTimer=setTimeout(function(){applying=false;applyingTimer=null;},Math.max(800,duration||1500));}
 function setTogetherUi(enabled){var together=!!enabled,button=$('togetherExit');if(button){button.hidden=false;button.classList.toggle('is-together',together);button.classList.toggle('is-solo',!together);button.textContent='一起看';button.title=together?'结束一起看':'一起看';button.setAttribute('aria-label',together?'结束一起看':'一起看');button.setAttribute('aria-pressed',together?'true':'false');}updateTogetherControls();updatePlayerTopBar();}
function esc(text){return String(text||'').replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
function resolutionTier(resolution){var text=String(resolution||'').trim().toUpperCase();if(!text)return null;if(/\b(4K|UHD|2160P)\b/.test(text))return{label:'4K',className:'is-4k'};if(/\b(2K|QHD|1440P)\b/.test(text))return{label:'2K',className:'is-2k'};if(/\b(1K|FHD|1080P|720P|HD)\b/.test(text))return{label:'1K',className:'is-1k'};var match=text.match(/(\d{3,5})\s*[X×]\s*(\d{3,5})/)||text.match(/(\d{3,5})\s*P?\b/),height=0;if(!match)return null;height=Number(match[2]||match[1]||0);if(height>=2000)return{label:'4K',className:'is-4k'};if(height>=1300)return{label:'2K',className:'is-2k'};if(height>=700)return{label:'1K',className:'is-1k'};return null;}
function resolutionBadgeHtml(resolution){var tier=resolutionTier(resolution);if(!tier)return'';if(tier.label==='4K')return'<span class="resolution-badge is-4k"><img src="/assets/images/4k-badge.png" alt="4K"></span>';return'<span class="resolution-badge '+tier.className+'">'+tier.label+'</span>';}
function derive(item){var name=String(item.file_name||''),key=item.series_key||'',series=item.series_name||'',season=Number(item.season_number||0),episode=Number(item.episode_number||0),source=String(item.source_key||'');if(!key){var slash=source.lastIndexOf('/');key=slash>0?source.slice(0,slash):name;}if(!series){var slash2=source.lastIndexOf('/');series=slash2>0?source.slice(source.lastIndexOf('/',slash2-1)+1,slash2):name.replace(/\.[^.]+$/,'');}var m=name.match(/S(\d{1,2})\s*E(\d{1,4})/i);if(m){season=season||Number(m[1]);episode=episode||Number(m[2]);}var e=name.match(/第\s*(\d{1,4})\s*集/u);if(e)episode=episode||Number(e[1]);item.series_key=key;item.series_name=series;item.season_number=season;item.episode_number=episode;return item;}
 function bindEpisodeButtons(){
     if(document.__withuEpisodeClickBound)return;
     document.__withuEpisodeClickBound=true;
     document.addEventListener('click',function(event){
         var button=event.target.closest('.episode-btn');
         if(!button)return;
         event.preventDefault();
         event.stopPropagation();
         var id=Number(button.getAttribute('data-id'));
         if(!Number.isFinite(id))return;
         selectMedia(id);
         toggleEpisodeOverlay(false);
     },true);
 }
 function updateEpisodeControls(){var outside=$('episodeListOutside');if(outside)outside.classList.toggle('is-single',episodeColumns===1);document.querySelectorAll('[data-episode-columns]').forEach(function(btn){var active=Number(btn.getAttribute('data-episode-columns'))===episodeColumns;btn.classList.toggle('is-active',active);btn.setAttribute('aria-pressed',active?'true':'false');});document.querySelectorAll('[data-episode-order]').forEach(function(btn){var active=btn.getAttribute('data-episode-order')===episodeOrder;btn.classList.toggle('is-active',active);btn.setAttribute('aria-pressed',active?'true':'false');});document.querySelectorAll('[data-episode-toggle="columns"]').forEach(function(btn){btn.setAttribute('aria-pressed',episodeColumns===1?'true':'false');btn.querySelectorAll('[data-episode-columns-state]').forEach(function(option){option.classList.toggle('is-active',Number(option.getAttribute('data-episode-columns-state'))===episodeColumns);});});document.querySelectorAll('[data-episode-toggle="order"]').forEach(function(btn){btn.setAttribute('aria-pressed',episodeOrder==='desc'?'true':'false');btn.querySelectorAll('[data-episode-order-state]').forEach(function(option){option.classList.toggle('is-active',option.getAttribute('data-episode-order-state')===episodeOrder);});});}
 function episodeLabel(item){return item.episode_number?'第 '+item.episode_number+' 集':String(item.file_name||item.series_name||'未命名影片');}
function isLongEpisodeLabel(label){label=String(label||'');return label.length>=18||/[A-Za-z0-9._-]{28,}/.test(label);}
function isScrollableEpisodeLabel(label){label=String(label||'');return label.length>=32||/[A-Za-z0-9._-]{36,}/.test(label);}
function applyEpisodeAutoLayout(items){var shouldSingle=items.length<=1||items.some(function(item){return isLongEpisodeLabel(episodeLabel(item));});if(items.length<=1){episodeColumns=1;return;}if(!episodeColumnsManual)episodeColumns=shouldSingle?1:2;}
function refreshEpisodeMarquees(){
 requestAnimationFrame(function(){
  document.querySelectorAll('.episode-btn-text.is-scrollable').forEach(function(node){
   var inner=node.querySelector('.episode-btn-marquee');
   if(!inner)return;
   node.classList.remove('is-marquee-ready');
   inner.style.transform='translateX(0)';
   var distance=Math.max(0,inner.scrollWidth-node.clientWidth);
   if(distance>4){
    node.style.setProperty('--withu-marquee-distance',distance+'px');
    node.style.setProperty('--withu-marquee-duration',Math.max(7,Math.min(18,4+distance/24))+'s');
    node.classList.add('is-marquee-ready');
   }
  });
 });
}
 function sortEpisodes(items){var direction=episodeOrder==='desc'?-1:1;return items.slice().sort(function(a,b){var seasonDiff=Number(a.season_number||0)-Number(b.season_number||0);var episodeDiff=Number(a.episode_number||0)-Number(b.episode_number||0);return (seasonDiff||episodeDiff||Number(a.id)-Number(b.id))*direction;});}
 function renderEpisodes(){
  var current=mediaItems.find(function(x){return Number(x.id)===Number(pendingMediaId)||Number(x.id)===Number(loadedMediaId);})||mediaItems.find(function(x){return Number(x.id)===Number(initialMediaId);});
  var outside=$('episodeListOutside');
  if(!current){
   updateEpisodeControls();
   var emptySignature='empty';
   if(episodeRenderSignature===emptySignature)return;
   episodeRenderSignature=emptySignature;
   if(outside)outside.innerHTML='<span class="player-hint">暂无可用选集</span>';
   if(episodeOverlay)episodeOverlay.innerHTML='<h3>选择分集</h3><span class="player-hint">暂无可用选集</span>';
   bindEpisodeButtons();
   return;
  }
  if(!episodeOverlay)return;
  current=derive(current);
  var items=sortEpisodes(mediaItems.filter(function(x){return derive(x).series_key===current.series_key;}));
  currentEpisodes=items;
  applyEpisodeAutoLayout(items);
  updateEpisodeControls();
  $('seriesTitle').textContent=current.series_name||current.file_name;
  var activeId=Number(loadedMediaId||pendingMediaId||current.id);
  var signature=current.series_key+'|'+activeId+'|'+episodeOrder+'|'+episodeColumns+'|'+items.map(function(item){return [item.id,item.episode_number||'',item.file_name||''].join(':');}).join(',');
  if(episodeRenderSignature===signature)return;
  episodeRenderSignature=signature;
  var html=items.map(function(item){var label=episodeLabel(item),scrollable=isScrollableEpisodeLabel(label);return '<button type="button" class="episode-btn '+(Number(item.id)===activeId?'active':'')+'" data-id="'+item.id+'" title="'+esc(label)+'"><span class="episode-btn-text '+(scrollable?'is-scrollable':'')+'"><span class="episode-btn-marquee">'+esc(label)+'</span></span></button>';}).join('');
  episodeOverlay.innerHTML='<h3>选择分集</h3><div class="withu-episode-list">'+html+'</div>';
  if(outside)outside.innerHTML=html;
  bindEpisodeButtons();
  refreshEpisodeMarquees();
  updatePlayerTopBar();
 }
function seriesGroups(){var groups=[];mediaItems.forEach(function(raw){var item=derive(raw),key=item.series_key||String(item.id),group=groups.find(function(entry){return entry.key===key;});if(!group){group={key:key,name:item.series_name||item.file_name,items:[]};groups.push(group);}group.items.push(item);});return groups;}
function normalizeMetaList(value){if(!value)return[];if(Array.isArray(value))return value.map(function(x){return String(x||'').trim();}).filter(Boolean);var text=String(value||'').trim();if(!text)return[];try{var parsed=JSON.parse(text);if(Array.isArray(parsed))return normalizeMetaList(parsed);}catch(e){}return text.split(/[、,，/|;；\s]+/u).map(function(x){return x.trim();}).filter(function(x){return x.length>=2;});}
function uniqueList(list){var seen={};return list.filter(function(item){var key=String(item||'').toLowerCase();if(!key||seen[key])return false;seen[key]=true;return true;});}
function groupMeta(group,field){var values=[];(group.items||[]).forEach(function(item){values=values.concat(normalizeMetaList(item[field]));});return uniqueList(values);}
function scoreRecommendationGroup(group,current){var item=group.items[0]||{},currentActors=normalizeMetaList(current.cast_names),currentTags=normalizeMetaList(current.tags),groupActors=groupMeta(group,'cast_names'),groupTags=groupMeta(group,'tags'),currentTier=resolutionTier(current.resolution),groupTier=resolutionTier(item.resolution),score=0;currentActors.forEach(function(actor){if(groupActors.indexOf(actor)>=0)score+=9;});currentTags.forEach(function(tag){if(groupTags.indexOf(tag)>=0)score+=5;});if(currentTier&&groupTier&&currentTier.label===groupTier.label)score+=2;if(item.rating)score+=Math.min(1.5,Number(item.rating||0)/10);return score;}
function findMediaItem(id){return mediaItems.find(function(item){return Number(item.id)===Number(id);})||null;}
function mergeMediaItems(items,replace){var map={};if(!replace)mediaItems.forEach(function(item){map[Number(item.id)]=item;});(items||[]).forEach(function(raw){var item=derive(raw),id=Number(item.id);if(id>0)map[id]=item;});mediaItems=Object.keys(map).map(function(id){return map[id];});return mediaItems;}
function recommendationGroupsForRender(){if(recommendationGroups&&recommendationGroups.length){return recommendationGroups.map(function(group){var item=derive(group.item||{});return{key:group.key||item.series_key||String(item.id),name:group.name||item.series_name||item.file_name,items:[item]};}).filter(function(group){return group.items[0]&&group.items[0].id;});}return seriesGroups();}
function renderRecommendations(){var current=findMediaItem(pendingMediaId)||findMediaItem(loadedMediaId),currentKey=current?derive(current).series_key:'';var groups=recommendationGroupsForRender().filter(function(group){return group.key!==currentKey;});var target=$('recommendList');if(!target)return;if(!groups.length){target.innerHTML='<span class="player-hint">暂无更多推荐</span>';return;}var ranked=groups.map(function(group,index){return{group:group,score:current?scoreRecommendationGroup(group,current):0,index:index};}).sort(function(a,b){return (b.score-a.score)||(Number(b.group.items[0].id)-Number(a.group.items[0].id))||(a.index-b.index);});target.innerHTML=ranked.map(function(entry){var group=entry.group,item=group.items[0];return '<a class="recommend-card" href="/watch_play.php?media_id='+encodeURIComponent(item.id)+'" title="'+esc(group.name)+'"><span class="recommend-card-poster"><img loading="lazy" src="/api/media_cover.php?id='+encodeURIComponent(item.id)+'" alt="">'+resolutionBadgeHtml(item.resolution)+'</span><span class="recommend-card-copy"><span class="recommend-card-title">'+esc(group.name)+'</span></span></a>';}).join('');}
function renderSearchGroups(groups){var input=$('mediaSearch'),target=$('mediaSearchResults');if(!input||!target)return;groups=groups||[];if(!groups.length){target.hidden=false;target.innerHTML='<span class="player-hint">没有匹配的影片</span>';return;}target.hidden=false;target.innerHTML=groups.map(function(group){var item=group.item||{},count=Number(group.count||0);return '<button type="button" class="media-search-result" data-search-media-id="'+esc(group.id||item.id)+'"><span>'+esc(group.name||item.series_name||item.file_name||'未命名影片')+'</span><small>'+(count>1?count+' 集':'播放')+'</small></button>';}).join('');target.querySelectorAll('[data-search-media-id]').forEach(function(button){button.onclick=function(){var id=Number(button.getAttribute('data-search-media-id'));input.value='';target.hidden=true;selectMedia(id);};});}
function renderSearchResults(){var input=$('mediaSearch'),target=$('mediaSearchResults');if(!input||!target)return;var term=String(input.value||'').trim();if(searchTimer){clearTimeout(searchTimer);searchTimer=null;}if(searchRequestController){searchRequestController.abort();searchRequestController=null;}if(!term){searchRequestSerial++;target.hidden=true;target.innerHTML='';return;}var serial=++searchRequestSerial;target.hidden=false;target.innerHTML='<span class="player-hint">正在搜索…</span>';searchTimer=setTimeout(function(){var controller=new AbortController();searchRequestController=controller;jsonRequest(mediaApi,'list',{q:term},'GET',{signal:controller.signal}).then(function(result){if(serial!==searchRequestSerial)return;if(!result.success){target.innerHTML='<span class="player-hint">'+esc(result.message||'搜索失败')+'</span>';return;}mergeMediaItems((result.groups||[]).map(function(group){return group.item;}).filter(Boolean),false);renderSearchGroups(result.groups||[]);}).catch(function(error){if(error&&error.name==='AbortError')return;if(serial===searchRequestSerial)target.innerHTML='<span class="player-hint">搜索失败，请稍后重试</span>';}).then(function(){if(searchRequestController===controller)searchRequestController=null;});},220);}
function loadMediaLibrary(options){options=options||{};var currentId=Number(options.current_id||pendingMediaId||loadedMediaId||initialMediaId||0);return jsonRequest(mediaApi,'list',{current_id:currentId},'GET').then(function(result){if(!result.success){if(!options.quiet)setStatus(result.message||'媒体库读取失败');return false;}mergeMediaItems(result.items||[],!!options.replace);recommendationGroups=result.groups||recommendationGroups||[];renderEpisodes();renderRecommendations();return true;});}
function ensureMediaLoaded(id){var loadedInCurrentSeries=currentEpisodes.some(function(item){return Number(item.id)===Number(id);});if(findMediaItem(id)&&loadedInCurrentSeries)return Promise.resolve(true);return loadMediaLibrary({current_id:id,quiet:true}).then(function(){return !!findMediaItem(id);});}
function loadEpisodes(){return loadMediaLibrary({current_id:initialMediaId,replace:true}).then(function(ok){if(ok)return selectMedia(initialMediaId);}).catch(function(){setStatus('媒体库读取失败，请检查服务状态。');});}
function loadCzEpisodes(){
  if(!czLines || !czLines.length){setStatus('该资源暂无可用选集');renderEpisodes();return;}
  mediaItems=czLines.map(function(line,index){
    var item={id:line.n||(index+1),file_name:'第 '+line.n+' 集',series_name:initialName||'cz 资源',series_key:'cz-'+czDetailUrl,episode_number:line.n,source_key:czDetailUrl,czVplay:line.vplay};
    return item;
  });
  pendingMediaId=mediaItems[0]?mediaItems[0].id:0;
  setTogetherUi(false);localOnly=true;
  if(document.getElementById('togetherExit'))document.getElementById('togetherExit').hidden=true;
  var searchWrap=document.querySelector('.player-top-search');if(searchWrap)searchWrap.style.display='none';
  renderEpisodes();
  renderCzMeta();
  if(mediaItems.length) selectMedia(mediaItems[0].id);
}
function loadStrmEpisodes(){
  var meta=strmMeta||{}, eps=meta.episodes||[];
  var base=Number(meta.id||strmMediaId||0);
  var isMovie=(meta.mediaType==='movie')||(meta.type==='Movie');
  if(isMovie || !eps || !eps.length){
    mediaItems=[{id:base,file_name:meta.name||'strm 媒体',series_name:meta.name||'strm 媒体',series_key:'strm-'+base,episode_number:0,strmMediaId:base,strmEpisodeId:0}];
  }else{
    mediaItems=eps.map(function(ep){
      return {id:Number(ep.id)||0,file_name:ep.sourceFileName||('第 '+ep.episodeNo+' 集'),series_name:meta.name||'strm 媒体',series_key:'strm-'+base,episode_number:Number(ep.episodeNo)||0,strmMediaId:base,strmEpisodeId:Number(ep.id)||0};
    });
  }
  pendingMediaId=mediaItems[0]?mediaItems[0].id:0;
  setTogetherUi(false);localOnly=true;
  if(document.getElementById('togetherExit'))document.getElementById('togetherExit').hidden=true;
  var searchWrap=document.querySelector('.player-top-search');if(searchWrap)searchWrap.style.display='none';
  renderEpisodes();
  renderStrmMeta();
  if(mediaItems.length) selectMedia(mediaItems[0].id);
}
function renderStrmMeta(){
  var t=strmMeta||{};
  var facts=[];
  if(t.year)facts.push(String(t.year));
  if(t.mediaType==='movie')facts.push('电影');else if(t.mediaType==='tv')facts.push('剧集');
  if(t.voteAverage)facts.push('评分 '+Number(t.voteAverage).toFixed(1));
  var dt=$('detailTitle'); if(dt)dt.textContent=t.name||'strm 媒体';
  var df=$('detailFacts'); if(df)df.innerHTML=facts.map(function(x){return '<span>'+esc(x)+'</span>';}).join('');
  var detail=$('detailSummary');
  if(detail){
    var summary=String(t.overview||'暂无简介').trim();
    detail.innerHTML='<div class="media-summary-body"> '+esc(summary)+'</div>';
  }
  var poster=$('detailPoster');
  if(poster&&t.posterUrl){ if(poster.getAttribute('src')!==t.posterUrl)poster.src=t.posterUrl; var wrap=poster.closest('.poster-badge-wrap'); if(wrap)wrap.style.display=''; }
  var badge=$('detailResolutionBadge'); if(badge)badge.innerHTML='';
  updatePlayerTopBar();
}

 function setWatermarkOnline(online){var label=$('watermarkOnline'),mark=$('watermarkMark'),node=$('playerWatermark');if(label)label.hidden=!online;if(mark)mark.classList.toggle('is-online',!!online);if(node)node.classList.toggle('partner-online',!!online);}
function updatePartner(payload){partnerOnline=(payload.members||[]).some(function(member){return Number(member.user_id)===Number(partnerId);});setWatermarkOnline(partnerOnline);updatePlayerTopBar();return partnerOnline;}
function bindCastToggle(detail){
 var toggle=detail&&detail.querySelector('.media-cast-toggle');
 if(!toggle)return;
 if(!toggle.__withuCastBound){
  toggle.__withuCastBound=true;
  toggle.onclick=function(event){
   event.preventDefault();
   event.stopPropagation();
   if(toggle.hidden)return;
   castExpanded=!detail.classList.contains('is-cast-open');
   detail.classList.toggle('is-cast-open',castExpanded);
   toggle.setAttribute('aria-expanded',castExpanded?'true':'false');
   toggle.textContent=castExpanded?'收起':'展开';
  };
 }
 requestAnimationFrame(function(){refreshCastToggleVisibility(detail);});
}
function refreshCastToggleVisibility(detail){
 detail=detail||$('detailSummary');
 var text=detail&&detail.querySelector('.media-cast-text'),toggle=detail&&detail.querySelector('.media-cast-toggle');
 if(!detail||!text||!toggle)return;
 var wasOpen=detail.classList.contains('is-cast-open');
 if(wasOpen)detail.classList.remove('is-cast-open');
 toggle.hidden=false;
 var overflow=text.scrollWidth>text.clientWidth+1;
 toggle.hidden=!overflow;
 if(wasOpen&&overflow)detail.classList.add('is-cast-open');
 if(!overflow){
  castExpanded=false;
  detail.classList.remove('is-cast-open');
 }
 toggle.setAttribute('aria-expanded',castExpanded?'true':'false');
 toggle.textContent=castExpanded?'收起':'展开';
}
function renderCzMeta(){
  if(!czMeta) return;
  var t=czMeta||{};
  var facts=[];
  if(t.year)facts.push(String(t.year));
  if(t.area)facts.push(String(t.area));
  if(t.types&&t.types.length)facts.push(t.types.join('、'));
  if(t.director)facts.push('导演 '+t.director);
  if(t.episode_status)facts.push(String(t.episode_status));
  var dt=$('detailTitle'); if(dt)dt.textContent=t.title||initialName||'cz 资源';
  var df=$('detailFacts'); if(df)df.innerHTML=facts.map(function(x){return '<span>'+esc(x)+'</span>';}).join('');
  var detail=$('detailSummary');
  if(detail){
    var summary=String(t.intro||'暂无简介').trim();
    var cast=String(t.actors||'').trim();
    var html='<div class="media-summary-body"> '+esc(summary)+'</div>';
    if(cast)html='<div class="media-cast-row"><span class="media-cast-text">主演：'+esc(cast)+'</span></div>'+html;
    detail.innerHTML=html;
  }
  var poster=$('detailPoster');
  if(poster&&t.poster){ if(poster.getAttribute('src')!==t.poster)poster.src=t.poster; var wrap=poster.closest('.poster-badge-wrap'); if(wrap)wrap.style.display=''; }
  var badge=$('detailResolutionBadge'); if(badge)badge.innerHTML='';
  updatePlayerTopBar();
}
function renderMeta(room){ if(czMode){ renderCzMeta(); return; } if(strmMode){ renderStrmMeta(); return; }
 var mediaItem=findMediaItem(room.media_id)||{},metadata={};
 try{metadata=JSON.parse(mediaItem.metadata_json||room.metadata_json||'{}');}catch(e){metadata={};}
 var cast=room.cast_names||'';
 try{var parsed=JSON.parse(cast);if(Array.isArray(parsed))cast=parsed.join('、');}catch(e){}
 cast=String(cast||'').trim();
 var tags=room.tags||'';
 try{var parsedTags=JSON.parse(tags);if(Array.isArray(parsedTags))tags=parsedTags.join('、');}catch(e){}
 var facts=[];
 if(room.rating)facts.push('评分 '+room.rating);
 if(room.resolution)facts.push(room.resolution);
 if(tags)facts.push(tags);
 var doubanEpisodeCount=Number(metadata.douban_episode_count||metadata.episodes_count||metadata.episode_count||0);
 var libraryEpisodeCount=currentEpisodes.length;
 if(doubanEpisodeCount>0)facts.push('豆瓣共 '+doubanEpisodeCount+' 集');
 if(libraryEpisodeCount>0)facts.push('库中 '+libraryEpisodeCount+' 集');
 var completionStatus='';
 var lastEpisodeNumber=Number(metadata.douban_last_episode_number||0);
 if(doubanEpisodeCount>0){
  if(lastEpisodeNumber>0)completionStatus=lastEpisodeNumber>=doubanEpisodeCount?'completed':'ongoing';
  else completionStatus=libraryEpisodeCount>=doubanEpisodeCount?'completed':'ongoing';
 }
 if(completionStatus)facts.push(completionStatus==='completed'?'已完结':'连载中');
 var title=room.series_name||room.file_name||'影片资料',summary=String(room.summary||'暂无简介').trim(),detail=$('detailSummary');
 var signature=[room.media_id||'',title,room.file_name||'',room.rating||'',room.resolution||'',tags,cast,summary,doubanEpisodeCount,libraryEpisodeCount,completionStatus].join('|');
 if(metaRenderSignature===signature){
  if(detail)bindCastToggle(detail);
  updatePlayerTopBar();
  return;
 }
 metaRenderSignature=signature;
 $('detailTitle').textContent=title;
 $('detailFacts').innerHTML=facts.map(function(item){return '<span>'+esc(item)+'</span>';}).join('');
 if(detail){
  var summaryHtml='<div class="media-summary-body">　　'+esc(summary)+'</div>';
  if(cast){
   detail.innerHTML='<div class="media-cast-row"><span class="media-cast-text">主演：'+esc(cast)+'</span><button type="button" class="media-cast-toggle" aria-expanded="'+(castExpanded?'true':'false')+'" hidden>'+(castExpanded?'收起':'展开')+'</button></div>'+summaryHtml;
   detail.classList.toggle('is-cast-open',!!castExpanded);
   bindCastToggle(detail);
  }else{
   castExpanded=false;
   detail.classList.remove('is-cast-open');
   detail.innerHTML=summaryHtml;
  }
 }
 var poster=$('detailPoster');
 if(poster&&room.media_id){
  var posterSrc='/api/media_cover.php?id='+encodeURIComponent(room.media_id);
  if(poster.getAttribute('src')!==posterSrc)poster.src=posterSrc;
 }
 var detailBadge=$('detailResolutionBadge');
 if(detailBadge)detailBadge.innerHTML=resolutionBadgeHtml(room.resolution);
 updatePlayerTopBar();
}
function sendEvent(type,extra){if(localOnly||!roomJoined||!code||applying)return;watchRequest('event',Object.assign({room_code:code,event_type:type,position_ms:Math.round(desktopCurrentTime()*1000),speed:desktopSpeed(),client_timestamp_ms:Date.now()},extra||{})).catch(function(){});}
function sendHeartbeat(){if(localOnly||!roomJoined||!code)return;watchRequest('heartbeat',{room_code:code,client_timestamp_ms:Date.now()},'POST').catch(function(){});}
 function applyRoom(payload){
  if(!payload||!payload.room)return;
  var room=payload.room;
  if(room.media_id&&!findMediaItem(room.media_id)){
   mergeMediaItems([Object.assign({},room,{id:room.media_id})],false);
   loadMediaLibrary({current_id:room.media_id,quiet:true});
  }
  var changed=loadedMediaId===null||Number(loadedMediaId)!==Number(room.media_id);
  loadedMediaId=Number(room.media_id);
  pendingMediaId=Number(room.media_id);
   if(changed){
    document.title=room.file_name||'withU Watch';
   renderEpisodes();
   renderRecommendations();
    var localSwitchAutoplay=localAutoplayRequest;
    localAutoplayRequest=false;
    setPlayerSource(room.url||'',room.file_name||initialName,false,false,room.playback_state==='playing'||localSwitchAutoplay,localSwitchAutoplay);
  }else{
   renderEpisodes();
   renderRecommendations();
  }
  renderMeta(room);
  setTogetherUi(!localOnly);
  var together=!localOnly&&updatePartner(payload);
  if(!together)return;
  var roomSpeed=Math.max(.5,Math.min(3,Number(room.speed||1)));
  var target=Number(room.position_ms||0)/1000;
  if(room.playback_state==='playing'&&room.last_sync_unix_ms)target+=(Date.now()-Number(room.last_sync_unix_ms))/1000*roomSpeed;
  var current=desktopCurrentTime();
  var drift=target-current;
  var ready=desktopMpvActive?desktopDuration()>0:player.readyState>=2;
   if(ready&&!player.seeking&&Math.abs(drift)>syncDriftThreshold){
    // A small positive drift is cheaper and less noticeable to recover with
    // a short speed boost. A large or negative drift still seeks directly.
   if(room.playback_state==='playing'&&!desktopPaused()&&drift>0&&drift<=3.5){
     if(syncCatchupTimer)clearTimeout(syncCatchupTimer);
     beginRemoteApply(1400);
     var catchupRate=Math.min(3,Math.max(roomSpeed+.25,roomSpeed*1.35));if(desktopMpvActive){desktopMpvState.speed=catchupRate;desktopCommand('rate '+catchupRate.toFixed(2));}else player.playbackRate=catchupRate;
     syncCatchupTimer=setTimeout(function(){syncCatchupTimer=null;beginRemoteApply(1000);if(desktopMpvActive){desktopMpvState.speed=roomSpeed;desktopCommand('rate '+roomSpeed.toFixed(2));}else if(player)player.playbackRate=roomSpeed;},Math.min(1800,Math.max(900,Math.round(drift*650))));
    }else{
     remoteSeekPending=true;
     if(remoteSeekTimer)clearTimeout(remoteSeekTimer);
     remoteSeekTimer=setTimeout(function(){remoteSeekPending=false;remoteSeekTimer=null;},2200);
     beginRemoteApply(1600);
     if(desktopMpvActive){desktopMpvState.position=Math.max(0,target);desktopCommand('seek '+Math.max(0,target).toFixed(3));}else player.currentTime=Math.max(0,target);
    }
   }else if(syncCatchupTimer&&Math.abs(drift)<=.35){
    clearTimeout(syncCatchupTimer);syncCatchupTimer=null;beginRemoteApply(1000);if(desktopMpvActive){desktopMpvState.speed=roomSpeed;desktopCommand('rate '+roomSpeed.toFixed(2));}else player.playbackRate=roomSpeed;
   }
   if(!syncCatchupTimer&&Math.abs(desktopSpeed()-roomSpeed)>.02){
   beginRemoteApply(1000);
   if(desktopMpvActive){desktopMpvState.speed=roomSpeed;desktopCommand('rate '+roomSpeed.toFixed(2));}else player.playbackRate=roomSpeed;
  }
  if(room.playback_state==='playing'&&desktopPaused()){
   beginRemoteApply(1000);
   if(desktopMpvActive){desktopMpvState.paused=false;desktopCommand('play');}else player.play().catch(function(){});
  }
   if(room.playback_state!=='playing'&&!desktopPaused()&&Date.now()>=localAutoplayPendingUntil){
   beginRemoteApply(1000);
   if(desktopMpvActive){desktopMpvState.paused=true;desktopCommand('pause');}else player.pause();
  }
 }
function selectMedia(id){id=Number(id);if(!Number.isFinite(id)||id<=0)return;if(mediaSwitchBusy)return;
  if(czMode){
    var czTarget=mediaItems.find(function(x){return Number(x.id)===id;})||currentEpisodes.find(function(x){return Number(x.id)===id;});
    if(!czTarget)return;
    var czName='第 '+(czTarget.episode_number||czTarget.id)+' 集';
    pendingMediaId=id;loadedMediaId=null;renderEpisodes();updatePlayerTopBar();
    setStatus('正在解析 '+czName+' 直链…');
    return setPlayerSource('/api/cz.php?action=resolve_line&url='+encodeURIComponent(czTarget.czVplay||czDetailUrl)+'&from=player',czName,false,false,true,false).catch(function(err){setStatus('解析失败：'+(err&&err.message||err));});
  }
  if(strmMode){
    var st=mediaItems.find(function(x){return Number(x.id)===id;})||currentEpisodes.find(function(x){return Number(x.id)===id;});
    if(!st)return;
    var stName=st.episode_number?('第 '+st.episode_number+' 集'):(st.file_name||st.series_name||'');
    pendingMediaId=id;loadedMediaId=null;renderEpisodes();updatePlayerTopBar();
    setStatus('正在解析 '+(stName||'媒体')+' 直链…');
    var resolveUrl='/api/strm.php?action=resolve&id='+Number(st.strmMediaId||strmMediaId);
    if(st.strmEpisodeId)resolveUrl+='&episode='+Number(st.strmEpisodeId);
    return setPlayerSource(resolveUrl,stName||'strm 媒体',false,false,true,false).catch(function(err){setStatus('解析失败：'+(err&&err.message||err));});
  }var target=findMediaItem(id)||currentEpisodes.find(function(item){return Number(item.id)===id;})||{};var switchName=target.episode_number?'第 '+target.episode_number+' 集':(target.file_name||target.series_name||'当前选集');var selectionId=++mediaSelectionSerial;mediaSwitchBusy=true;pendingMediaId=id;loadedMediaId=null;localAutoplayRequest=watchAutoplayEnabled;setSwitchLoading(true,'正在切换到 '+switchName+'…');stopCurrentPlaybackForSwitch();renderEpisodes();updatePlayerTopBar();setStatus('正在读取选集…');ensureMediaLoaded(id).then(function(found){if(selectionId!==mediaSelectionSerial){localAutoplayRequest=false;return;}if(!found){localAutoplayRequest=false;setSwitchLoading(false);setStatus('未找到该选集');return;}renderEpisodes();setStatus('正在进入 WithU Watch…');var action=localOnly?'choose':'default';var data=localOnly?{choice:'solo',media_id:id}:{media_id:id};return watchRequest(action,data);}).then(function(result){if(!result||selectionId!==mediaSelectionSerial){localAutoplayRequest=false;return;}if(!result.success){localAutoplayRequest=false;setSwitchLoading(false);setStatus(result.message||'进入房间失败');return;}if(result.choice_required){mediaSwitchBusy=false;setSwitchLoading(false);roomJoined=false;$('choiceText').textContent='检测到另一位在线，当前影片为：'+result.current_file_name+'。请选择观看方式。';$('choiceModal').hidden=false;return;}roomJoined=!localOnly;setStatus(localOnly?'自己看模式':'已进入 WithU Watch');applyRoom(result);startPolling();}).catch(function(){if(selectionId===mediaSelectionSerial){mediaSwitchBusy=false;localAutoplayRequest=false;setSwitchLoading(false);setStatus('切换选集失败，请稍后重试');}});}
 function poll(){if(localOnly||pollInFlight)return;pollInFlight=true;watchRequest('poll',{room:code,since:lastEvent},'GET').then(function(payload){if(!payload||payload.success===false){setStatus((payload&&payload.message)||'服务暂时不可用，请稍后重试。');return;}applyRoom(payload);handleVoiceEvents(payload.events||[]);lastEvent=payload.last_event_id||lastEvent;}).catch(function(){setStatus('服务暂时不可用，请稍后重试。');}).then(function(){pollInFlight=false;});}
 function startPolling(){if(timer)clearInterval(timer);if(localOnly)return;timer=setInterval(poll,watchPollIntervalMs);poll();}
function chooseTogether(){watchRequest('choose',{choice:'together',media_id:pendingMediaId}).then(function(result){if(!result.success){setStatus(result.message||'加入失败');return;}$('choiceModal').hidden=true;localOnly=false;roomJoined=true;setTogetherUi(true);setStatus('已加入一起看，之后切换将自动同步');applyRoom(result);startPolling();startHeartbeat();});}
 function chooseSolo(){watchRequest('choose',{choice:'solo',media_id:pendingMediaId}).then(function(result){if(!result.success){setStatus(result.message||'自己看进入失败');return;}$('choiceModal').hidden=true;localOnly=true;roomJoined=false;setWatermarkOnline(false);setTogetherUi(false);if(timer)clearInterval(timer);if(heartbeatTimer)clearInterval(heartbeatTimer);heartbeatTimer=null;setStatus('自己看模式，不会影响另一位');applyRoom(result);});}
function updateSpeedControl(){if(!speedControl||!player)return;var label=speedControl.querySelector('.withu-speed-control'),rate=desktopSpeed();if(label)label.textContent=(Number(rate||1)%1===0?String(Number(rate||1)):String(Number(rate||1).toFixed(2)).replace(/0+$/,'').replace(/\.$/,'') )+'x';}
function cycleSpeed(){if(!player)return;var current=desktopSpeed(),index=speedSteps.findIndex(function(value){return Math.abs(value-current)<.01;}),value=speedSteps[(index+1+speedSteps.length)%speedSteps.length];if(desktopMpvActive){desktopMpvState.speed=value;desktopCommand('rate '+value.toFixed(2));}else player.playbackRate=value;updateSpeedControl();}
function bindPlayerEvents(video){if(!video||video.__withuSyncBound)return;video.__withuSyncBound=true;video.addEventListener('play',function(){if(!applying)sendEvent('play');});video.addEventListener('pause',function(){if(!applying)sendEvent('pause');});video.addEventListener('seeked',function(){if(remoteSeekPending){remoteSeekPending=false;if(remoteSeekTimer)clearTimeout(remoteSeekTimer);remoteSeekTimer=null;return;}if(!applying)sendEvent('seek');});video.addEventListener('ratechange',function(){updateSpeedControl();if(!applying)sendEvent('speed');});video.addEventListener('ended',function(){nextEpisode();});updateSpeedControl();}
 function startHeartbeat(){if(heartbeatTimer)clearInterval(heartbeatTimer);if(localOnly)return;heartbeatTimer=setInterval(sendHeartbeat,watchHeartbeatIntervalMs);sendHeartbeat();}
 bindPlayerEvents(player);startHeartbeat();document.addEventListener('visibilitychange',function(){if(document.hidden){if(heartbeatTimer)clearInterval(heartbeatTimer);heartbeatTimer=null;}else{startHeartbeat();poll();}});
function startVoiceActivity(stream){stopVoiceActivity(false);setVoiceActivityVisible(false);var AudioContext=window.AudioContext||window.webkitAudioContext;if(!stream||!AudioContext)return;try{voiceAudioCtx=new AudioContext();voiceMicSource=voiceAudioCtx.createMediaStreamSource(stream);voiceAnalyser=voiceAudioCtx.createAnalyser();voiceAnalyser.fftSize=256;voiceAnalyser.smoothingTimeConstant=.72;voiceLevelData=new Uint8Array(voiceAnalyser.fftSize);voiceMicSource.connect(voiceAnalyser);if(voiceAudioCtx.state==='suspended'&&voiceAudioCtx.resume)voiceAudioCtx.resume().catch(function(){});voiceActivityLastLoudAt=0;voiceActivityLoop();}catch(e){stopVoiceActivity(false);}}
function voiceActivityLoop(){if(!voiceAnalyser||!voiceLevelData||!voiceActive){setVoiceActivityVisible(false);voiceActivityFrame=null;return;}voiceAnalyser.getByteTimeDomainData(voiceLevelData);var sum=0;for(var i=0;i<voiceLevelData.length;i++){var value=(voiceLevelData[i]-128)/128;sum+=value*value;}var level=Math.sqrt(sum/voiceLevelData.length),now=Date.now();if(level>.035)voiceActivityLastLoudAt=now;setVoiceActivityVisible(now-voiceActivityLastLoudAt<360);voiceActivityFrame=requestAnimationFrame(voiceActivityLoop);}
function stopVoiceActivity(hide){if(voiceActivityFrame){cancelAnimationFrame(voiceActivityFrame);voiceActivityFrame=null;}if(voiceMicSource){try{voiceMicSource.disconnect();}catch(e){}voiceMicSource=null;}voiceAnalyser=null;voiceLevelData=null;voiceActivityLastLoudAt=0;if(voiceAudioCtx){var ctx=voiceAudioCtx;voiceAudioCtx=null;if(ctx.close)ctx.close().catch(function(){});}if(hide!==false)setVoiceActivityVisible(false);}
function lowerMediaVolume(){if(player&&!voiceActive){oldVolume=desktopVolume();if(desktopMpvActive){desktopMpvState.volume=Math.max(.12,oldVolume*.35);desktopCommand('volume '+(desktopMpvState.volume*100).toFixed(2));}else player.volume=Math.max(.12,oldVolume*.35);}voiceActive=true;updateVoiceControl();startVoiceActivity(voiceStream);}
function restoreMediaVolume(){voiceActive=false;stopVoiceActivity();if(player){if(desktopMpvActive){desktopMpvState.volume=oldVolume;desktopCommand('volume '+(oldVolume*100).toFixed(2));}else player.volume=oldVolume;}updateVoiceControl();}
async function startVoice(){if(!togetherControlsEnabled()){setStatus('一起看时才能连麦');return;}if(!window.RTCPeerConnection||!navigator.mediaDevices){setStatus('当前浏览器不支持连麦');return;}try{voiceStream=await navigator.mediaDevices.getUserMedia({audio:true});peer=new RTCPeerConnection();voiceStream.getTracks().forEach(function(track){peer.addTrack(track,voiceStream);});peer.ontrack=function(event){var audio=document.getElementById('voiceAudio')||document.body.appendChild(Object.assign(document.createElement('audio'),{id:'voiceAudio',autoplay:true}));audio.srcObject=event.streams[0];};peer.onicecandidate=function(event){if(event.candidate)sendEvent('voice_candidate',{payload:JSON.stringify(event.candidate)});};var offer=await peer.createOffer();await peer.setLocalDescription(offer);sendEvent('voice_offer',{payload:JSON.stringify(offer)});lowerMediaVolume();}catch(e){restoreMediaVolume();setStatus('无法开启麦克风，请检查浏览器权限');}}
function stopVoice(notify){if(peer){peer.close();peer=null;}if(voiceStream){voiceStream.getTracks().forEach(function(track){track.stop();});voiceStream=null;}if(notify!==false)sendEvent('voice_leave');restoreMediaVolume();}
async function handleVoiceEvents(events){for(var i=0;i<events.length;i++){var event=events[i];if(Number(event.user_id)===Number(<?php echo (int)$user['id']; ?>))continue;if(event.event_type==='chat_message'){var chatData=parseWatchEventPayload(event);if(chatData&&chatData.text){addChatMessage(chatData.text,false,chatData.kind==='danmaku'?'danmaku':'chat');if(chatData.kind!=='side_chat')showDanmaku(chatData.text,false);}continue;}if(event.event_type.indexOf('voice_')!==0)continue;var data=parseWatchEventPayload(event);if(event.event_type==='voice_offer'&&!peer){try{voiceStream=await navigator.mediaDevices.getUserMedia({audio:true});peer=new RTCPeerConnection();voiceStream.getTracks().forEach(function(track){peer.addTrack(track,voiceStream);});peer.ontrack=function(ev){var audio=document.getElementById('voiceAudio')||document.body.appendChild(Object.assign(document.createElement('audio'),{id:'voiceAudio',autoplay:true}));audio.srcObject=ev.streams[0];};peer.onicecandidate=function(ev){if(ev.candidate)sendEvent('voice_candidate',{payload:JSON.stringify(ev.candidate)});};await peer.setRemoteDescription(data);var answer=await peer.createAnswer();await peer.setLocalDescription(answer);sendEvent('voice_answer',{payload:JSON.stringify(answer)});lowerMediaVolume();}catch(e){restoreMediaVolume();}}else if(event.event_type==='voice_answer'&&peer){try{await peer.setRemoteDescription(data);}catch(e){}}else if(event.event_type==='voice_candidate'&&peer){try{await peer.addIceCandidate(data);}catch(e){}}else if(event.event_type==='voice_leave'){stopVoice(false);}}}
function showGesture(text){$('gestureValue').textContent=text;clearTimeout(showGesture.timer);showGesture.timer=setTimeout(function(){$('gestureValue').textContent='';},900);}
function beginHold(){if(holdTimer)return;normalSpeed=desktopSpeed();holdTimer=setTimeout(function(){applying=true;if(desktopMpvActive){desktopMpvState.speed=2;desktopCommand('rate 2.00');}else player.playbackRate=2;applying=false;sendEvent('speed');showGesture('长按：2x');},450);}function endHold(){if(!holdTimer)return;clearTimeout(holdTimer);holdTimer=null;if(Math.abs(desktopSpeed()-normalSpeed)>.01){if(desktopMpvActive){desktopMpvState.speed=normalSpeed;desktopCommand('rate '+normalSpeed.toFixed(2));}else player.playbackRate=normalSpeed;sendEvent('speed');showGesture('恢复：'+normalSpeed+'x');}}
var touchStart=null;$('gesture').addEventListener('pointerdown',function(e){beginHold();touchStart={x:e.clientX,y:e.clientY,volume:desktopVolume(),brightness:brightness};});$('gesture').addEventListener('pointerup',function(e){endHold();if(!touchStart)return;var dy=touchStart.y-e.clientY;if(Math.abs(dy)>24){var ratio=Math.max(-1,Math.min(1,dy/260));if(touchStart.x<window.innerWidth/2){brightness=Math.max(.4,Math.min(1.5,touchStart.brightness+ratio*.7));if(player)player.style.filter='brightness('+brightness+')';showGesture('亮度：'+Math.round(brightness*100)+'%');}else{var volume=Math.max(0,Math.min(1,touchStart.volume+ratio));if(desktopMpvActive){desktopMpvState.volume=volume;desktopCommand('volume '+(volume*100).toFixed(2));}else player.volume=volume;showGesture('音量：'+Math.round(volume*100)+'%');}}touchStart=null;});$('gesture').addEventListener('pointercancel',function(){endHold();touchStart=null;});
 function toggleTogether(){if(!localOnly&&roomJoined){endTogether();return;}chooseTogether();}
 $('togetherExit').onclick=toggleTogether;$('chooseTogether').onclick=chooseTogether;$('chooseSolo').onclick=chooseSolo;$('mediaSearch').addEventListener('input',renderSearchResults);$('mediaSearch').addEventListener('focus',renderSearchResults);document.addEventListener('click',function(event){if(!event.target.closest('.media-search-wrap')){var results=$('mediaSearchResults');if(results)results.hidden=true;}});window.addEventListener('resize',function(){clearTimeout(refreshCastToggleVisibility.timer);refreshCastToggleVisibility.timer=setTimeout(function(){refreshCastToggleVisibility();},120);});document.querySelectorAll('[data-episode-columns]').forEach(function(btn){btn.onclick=function(){episodeColumns=Number(btn.getAttribute('data-episode-columns'))===1?1:2;episodeColumnsManual=true;updateEpisodeControls();};});document.querySelectorAll('[data-episode-order]').forEach(function(btn){btn.onclick=function(){episodeOrder=btn.getAttribute('data-episode-order')==='desc'?'desc':'asc';renderEpisodes();};});document.querySelectorAll('[data-episode-toggle="columns"]').forEach(function(btn){btn.onclick=function(){episodeColumns=episodeColumns===1?2:1;episodeColumnsManual=true;updateEpisodeControls();};});document.querySelectorAll('[data-episode-toggle="order"]').forEach(function(btn){btn.onclick=function(){episodeOrder=episodeOrder==='asc'?'desc':'asc';renderEpisodes();};});setInterval(updatePlayerTopTime,30000);setTogetherUi(false);updatePlayerTopBar();updateEpisodeControls();if(czMode)loadCzEpisodes();else if(strmMode)loadStrmEpisodes();else loadEpisodes();
function endTogether(){watchRequest('end_together',{room_code:code}).then(function(result){if(!result.success){setStatus(result.message||'结束一起看失败');return;}if(voiceActive)stopVoice();localOnly=true;roomJoined=false;setWatermarkOnline(false);setTogetherUi(false);if(timer)clearInterval(timer);if(heartbeatTimer)clearInterval(heartbeatTimer);heartbeatTimer=null;setStatus('已结束一起看，当前仅自己观看');});}
</script>
</body>
</html>

