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
$auth = new Auth();
$user = withu_require_couple_user($auth);
$db = Database::getInstance();
$mediaDb = null;
$mediaError = '';
try {
    $mediaDb = withu_media_db();
} catch (Throwable $e) {
    $mediaError = '影视库暂时不可用，请稍后再试。';
}
$historyMinMs = withu_watch_history_min_ms();

$typeNames = [1 => '电影', 2 => '电视剧', 3 => '动漫', 4 => '综艺'];
$categoryOrder = [2, 1, 4, 3];
$requestedTypeId = (int)($_GET['type'] ?? 0);
if (!isset($typeNames[$requestedTypeId])) $requestedTypeId = 0;
$isAllPage = (string)($_GET['all'] ?? '') === '1';
$isBrowsePage = $requestedTypeId > 0 || $isAllPage;
$isCategoryPage = $requestedTypeId > 0;
$mediaSql = "SELECT * FROM media_library FORCE INDEX (idx_media_status_added) WHERE recognition_status = 'recognized' AND media_type_id IN (1,2,3,4)";
$mediaParams = [];
if ($isCategoryPage) {
    $mediaSql .= ' AND media_type_id = :requested_type_id';
    $mediaParams['requested_type_id'] = $requestedTypeId;
}
$mediaSql .= ' ORDER BY added_at DESC, id DESC';
if (!$isBrowsePage) $mediaSql .= ' LIMIT 360';
$mediaRows = $mediaDb ? $mediaDb->fetchAll($mediaSql, $mediaParams) : [];
$mediaRows = array_map('withu_media_display_row', $mediaRows);
$groups = [];
foreach ($mediaRows as $media) {
    $key = (string)($media['series_key'] ?: $media['id']);
    if (!isset($groups[$key])) {
        $groups[$key] = ['key' => $key, 'name' => $media['series_name'], 'cover_url' => $media['cover_url'], 'backdrop_url' => $media['backdrop_url'] ?? '', 'type_id' => (int)($media['media_type_id'] ?? 1), 'added_at' => (string)(($media['added_at'] ?? '') ?: (($media['folder_created_at'] ?? '') ?: ($media['created_at'] ?? ''))), 'items' => []];
    }
    if (empty($groups[$key]['cover_url']) && !empty($media['cover_url'])) $groups[$key]['cover_url'] = $media['cover_url'];
    if (empty($groups[$key]['backdrop_url']) && !empty($media['backdrop_url'])) $groups[$key]['backdrop_url'] = $media['backdrop_url'];
    $groups[$key]['items'][] = $media;
}
foreach ($groups as &$group) {
    usort($group['items'], function (array $a, array $b): int {
        $seasonA = (int)($a['season_number'] ?? 0); $seasonB = (int)($b['season_number'] ?? 0);
        $episodeA = (int)($a['episode_number'] ?? 0); $episodeB = (int)($b['episode_number'] ?? 0);
        return [$seasonA, $episodeA, (int)$a['id']] <=> [$seasonB, $episodeB, (int)$b['id']];
    });
    if (empty($group['cover_url'])) $group['cover_url'] = '/assets/images/Coverloaderror.jpg';
}
unset($group);
usort($groups, static function (array $a, array $b): int { return strcmp((string)($b['added_at'] ?? ''), (string)($a['added_at'] ?? '')); });
$categoryGroups = [1 => [], 2 => [], 3 => [], 4 => []];
foreach ($groups as $group) {
    $typeId = (int)($group['type_id'] ?? 1);
    if (isset($categoryGroups[$typeId])) $categoryGroups[$typeId][] = $group;
}

$historyRows = $isBrowsePage ? [] : $db->fetchAll("SELECT * FROM watch_history WHERE watch_duration_ms >= :min_ms ORDER BY updated_at DESC, id DESC LIMIT 24", ['min_ms' => $historyMinMs]);
$historyMedia = withu_media_fetch_many(array_map(static function ($row) { return (int)$row['media_id']; }, $historyRows));
$recentRows = [];
foreach ($historyRows as $historyRow) {
    $mediaRow = $historyMedia[(int)$historyRow['media_id']] ?? null;
    if ($mediaRow) $recentRows[] = array_merge($mediaRow, $historyRow);
}
$recentSeries = [];
foreach ($recentRows as $row) {
    $item = withu_media_display_row($row);
    $key = (string)($item['series_key'] ?: $item['id']);
    if (!isset($recentSeries[$key])) {
        $item['latest_watch_at'] = (string)($row['updated_at'] ?? '');
        $recentSeries[$key] = $item;
    }
}
$recent = array_values($recentSeries);
$recentGroups = array_values(array_slice($recent, 0, 8));
$allGroups = array_values(array_slice($groups, 0, 14));
$featured = !$isBrowsePage && !empty($groups) ? reset($groups) : null;
$featuredItem = $featured && !empty($featured['items']) ? $featured['items'][0] : null;
$pageTitle = $isCategoryPage ? $typeNames[$requestedTypeId] : ($isAllPage ? '全部影片' : '影视库');
$themeConfig = ['preset' => 'light', 'mode' => 'light', 'custom' => false, 'colors' => []];
$themeInlineStyle = '';
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo e($pageTitle); ?> - withU 共享观影</title>
<style>
:root{
  --pink:#f78da7; --pink-soft:#ffe9ef;
  --blue:#7ec8e3; --blue-soft:#e6f5fb;
  --green:#7fbf9d; --green-soft:#e8f6ee;
  --ink:#3d3038; --ink-soft:#8a7a83;
  --bg:#fff7fa;
  --shadow:0 10px 30px rgba(247,141,167,.14);
  --shadow-hover:0 20px 44px rgba(247,141,167,.28);
  --ease:cubic-bezier(.22,.8,.28,1);
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
html,body{margin:0;padding:0}
body.watch-page{
  min-height:100vh;
  background:
    radial-gradient(circle at 12% 8%, rgba(126,200,227,.20), transparent 34%),
    radial-gradient(circle at 90% 18%, rgba(247,141,167,.20), transparent 34%),
    radial-gradient(circle at 55% 92%, rgba(127,191,157,.20), transparent 38%),
    var(--bg);
  background-attachment:fixed;
  color:var(--ink);
  font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Hiragino Sans GB","Microsoft YaHei","Helvetica Neue",Arial,sans-serif;
  overflow-x:hidden;
}
body.watch-page a{color:inherit}
.watch-loading{position:fixed;inset:0;z-index:200;display:flex;align-items:center;justify-content:center;gap:.6rem;background:var(--bg);color:var(--ink-soft);transition:opacity .3s,visibility .3s}
.watch-loading.is-hidden{opacity:0;visibility:hidden;pointer-events:none}
.watch-spinner{width:18px;height:18px;border:2px solid rgba(247,141,167,.3);border-top-color:var(--pink);border-radius:50%;animation:watch-spin .8s linear infinite}
@keyframes watch-spin{to{transform:rotate(360deg)}}

/* ===== 顶部品牌 + 搜索 ===== */
.site-header{position:sticky;top:0;z-index:60;display:flex;align-items:center;gap:1.2rem;padding:.85rem 2rem;background:rgba(255,247,250,.82);backdrop-filter:blur(18px) saturate(150%);-webkit-backdrop-filter:blur(18px) saturate(150%);border-bottom:1px solid rgba(247,141,167,.16)}
.site-header .brand{display:inline-flex;align-items:center;gap:.45rem;font-size:1.25rem;font-weight:800;text-decoration:none;letter-spacing:.02em}
.site-header .brand .brand-mark{font-size:1.3rem}
.site-header .brand em{font-style:normal;font-size:.78rem;font-weight:600;color:var(--ink-soft)}
.site-header .search-box{flex:1;max-width:480px;margin-left:auto;display:flex;align-items:center;gap:.5rem;padding:.5rem 1rem;border-radius:999px;background:rgba(255,255,255,.82);border:1px solid rgba(247,141,167,.22);box-shadow:0 6px 18px rgba(247,141,167,.08);transition:border-color .2s,box-shadow .2s}
.site-header .search-box:focus-within{border-color:var(--pink);box-shadow:0 8px 24px rgba(247,141,167,.18)}
.site-header .search-box .s-icon{color:var(--pink);font-size:.95rem}
.site-header .search-box input{flex:1;border:0;outline:0;background:transparent;color:var(--ink);font-size:.92rem}
.site-header .search-box input::placeholder{color:var(--ink-soft)}
.site-header .header-links{display:flex;align-items:center;gap:.5rem}
.site-header .header-links a{font-size:.85rem;color:var(--ink-soft);text-decoration:none;padding:.4rem .7rem;border-radius:999px;transition:background .2s,color .2s}
.site-header .header-links a:hover{background:var(--pink-soft);color:var(--pink)}

/* ===== Hero 全宽横幅轮播 ===== */
.hero{position:relative;height:clamp(380px,52vh,520px);margin:0;overflow:hidden;background:#ffe9ef}
.hero .slide{position:absolute;inset:0;opacity:0;visibility:hidden;transition:opacity .8s ease,visibility .8s}
.hero .slide.active{opacity:1;visibility:visible;z-index:2}
.hero .slide .bg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center 22%;transform:scale(1.12);transition:transform 6.5s linear}
.hero .slide.active .bg{transform:scale(1)}
.hero .slide .shade{position:absolute;inset:0;background:linear-gradient(90deg,rgba(61,48,56,.78),rgba(61,48,56,.42) 46%,rgba(61,48,56,.1) 78%),linear-gradient(0deg,rgba(61,48,56,.55),transparent 46%)}
.hero .slide .content{position:absolute;z-index:3;left:0;top:0;bottom:0;display:flex;flex-direction:column;justify-content:center;padding-left:240px;padding-right:3rem;max-width:820px}
.hero .elem{opacity:0;transform:translateY(26px);transition:opacity .7s var(--ease),transform .7s var(--ease)}
.hero .slide.active .elem{opacity:1;transform:none}
.hero .slide.active .elem:nth-child(1){transition-delay:.15s}
.hero .slide.active .elem:nth-child(2){transition-delay:.28s}
.hero .slide.active .elem:nth-child(3){transition-delay:.41s}
.hero .slide.active .elem:nth-child(4){transition-delay:.54s}
.hero .slide.active .elem:nth-child(5){transition-delay:.67s}
.hero .badge{display:inline-flex;align-items:center;gap:.4rem;width:max-content;padding:.3rem .85rem;border-radius:999px;font-size:.78rem;font-weight:700;letter-spacing:.06em;color:#fff;background:linear-gradient(135deg,var(--pink),var(--blue));box-shadow:0 8px 20px rgba(247,141,167,.4)}
.hero h1{margin:.7rem 0 .5rem;font-size:clamp(1.7rem,4.4vw,3.1rem);line-height:1.15;color:#fff;font-weight:800;text-shadow:0 2px 18px rgba(0,0,0,.35)}
.hero .rating{color:#ffe9ef;font-size:.95rem;font-weight:600}
.hero .desc{max-width:540px;margin:.4rem 0 1.1rem;color:rgba(255,255,255,.88);font-size:.95rem;line-height:1.65;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.hero .cta{display:inline-flex;align-items:center;gap:.5rem;width:max-content;padding:.72rem 1.5rem;border-radius:999px;background:linear-gradient(135deg,var(--pink),#ff9ab5);color:#fff;font-weight:700;font-size:.95rem;text-decoration:none;box-shadow:0 14px 30px rgba(247,141,167,.45);transition:transform .2s var(--ease),box-shadow .2s}
.hero .cta:hover{transform:translateY(-2px) scale(1.03);box-shadow:0 18px 38px rgba(247,141,167,.55)}
.hero .arrow{position:absolute;z-index:4;top:50%;transform:translateY(-50%);width:44px;height:44px;border-radius:50%;border:1px solid rgba(255,255,255,.55);background:rgba(255,255,255,.14);color:#fff;font-size:1.5rem;line-height:1;cursor:pointer;backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);transition:background .2s,transform .2s;display:flex;align-items:center;justify-content:center}
.hero .arrow.prev{left:216px}
.hero .arrow.next{right:1.4rem}
.hero .arrow:hover{background:rgba(255,255,255,.32)}
.hero .dots{position:absolute;z-index:4;left:240px;bottom:1.2rem;display:flex;gap:.5rem}
.hero .dots i{width:9px;height:9px;border-radius:999px;background:rgba(255,255,255,.5);cursor:pointer;transition:width .3s var(--ease),background .3s}
.hero .dots i.active{width:26px;background:#fff}

/* ===== 左侧液态玻璃导航 ===== */
.side-nav{position:fixed;left:26px;z-index:50;display:flex;flex-direction:column;gap:.35rem;padding:.7rem;border-radius:22px;background:linear-gradient(135deg,rgba(255,255,255,.6),rgba(255,255,255,.28));backdrop-filter:blur(22px) saturate(170%);-webkit-backdrop-filter:blur(22px) saturate(170%);border:1px solid rgba(255,255,255,.72);box-shadow:inset 0 1px 0 rgba(255,255,255,.8),inset 1px 0 0 rgba(255,255,255,.4),inset -1px -1px 0 rgba(255,255,255,.2),0 22px 46px rgba(247,141,167,.18);top:50%;transform:translateY(-50%);transition:top .7s cubic-bezier(.34,1.56,.64,1),transform .7s cubic-bezier(.34,1.56,.64,1)}
.side-nav::before{content:"";position:absolute;left:14%;right:14%;top:0;height:1px;background:linear-gradient(90deg,transparent,rgba(255,255,255,.95),transparent)}
.side-nav.is-down{top:var(--recent-center,50%);transform:translateY(-50%)}
.side-link{position:relative;display:flex;align-items:center;gap:.6rem;padding:.62rem .95rem;border-radius:14px;color:var(--ink);font-size:.9rem;font-weight:600;text-decoration:none;overflow:hidden;transition:transform .22s var(--ease),color .22s}
.side-link::before{content:"";position:absolute;inset:0;background:linear-gradient(135deg,var(--pink),var(--blue));border-radius:14px;transform:scaleX(0);transform-origin:left;transition:transform .3s var(--ease);z-index:0}
.side-link::after{content:"";position:absolute;left:50%;top:50%;width:26px;height:26px;margin:-13px 0 0 -13px;border-radius:50%;background:radial-gradient(circle,rgba(255,255,255,.85),transparent 65%);transform:scale(0);transition:transform .5s var(--ease);z-index:0}
.side-link:hover::before,.side-link.active::before{transform:scaleX(1)}
.side-link:hover::after{transform:scale(2.7)}
.side-link .side-icon{position:relative;z-index:1;display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:9px;background:var(--pink-soft);color:var(--pink);font-size:.82rem;transition:transform .25s var(--ease),background .25s,color .25s}
.side-link:nth-child(2) .side-icon{background:var(--blue-soft);color:var(--blue)}
.side-link:nth-child(3) .side-icon{background:var(--green-soft);color:var(--green)}
.side-link:hover .side-icon,.side-link.active .side-icon{transform:translateY(-2px) rotate(-8deg);background:#fff;color:var(--pink)}
.side-link:hover,.side-link.active{color:#fff;transform:translateX(4px)}

/* ===== 主布局 ===== */
.page{position:relative;display:flex;gap:2rem;max-width:1380px;margin:0 auto;padding:1.6rem 2rem 4rem}
.main-col{flex:1;min-width:0;padding-left:8px}
.section{margin:2.2rem 0 3rem}
.section-head{display:flex;align-items:center;gap:.7rem;margin-bottom:1.05rem}
.section-head h2{margin:0;font-size:1.18rem;font-weight:800;color:var(--ink)}
.section-head .sec-accent{width:5px;height:22px;border-radius:3px;background:var(--sec-accent,var(--pink))}
.section-head .sec-note{font-size:.82rem;color:var(--ink-soft)}
.section-head .sec-more{margin-left:auto;font-size:.82rem;color:var(--ink-soft);text-decoration:none;transition:color .2s}
.section-head .sec-more:hover{color:var(--pink)}

/* ===== 竖版封面卡片 ===== */
.card{position:relative;display:block;border-radius:16px;overflow:hidden;background:#fff;box-shadow:var(--shadow);text-decoration:none;aspect-ratio:2/3;opacity:0;transform:translateY(0);transition:box-shadow .35s var(--ease),transform .35s var(--ease)}
.card.in{opacity:1;animation:cardIn .6s var(--ease) both;animation-delay:var(--stagger,0s)}
@keyframes cardIn{from{opacity:0;transform:translateY(28px)}to{opacity:1;transform:none}}
.card:hover{box-shadow:var(--shadow-hover);transform:translateY(-6px)}
.card .poster{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;transition:transform .45s var(--ease)}
.card:hover .poster{transform:scale(1.07)}
.card .rate{position:absolute;top:.55rem;left:.55rem;z-index:2;padding:.22rem .6rem;border-radius:999px;font-size:.72rem;font-weight:800;color:#fff;background:linear-gradient(135deg,var(--pink),#ffb3c6);box-shadow:0 6px 14px rgba(247,141,167,.45)}
.card .rate.none{background:rgba(61,48,56,.55);box-shadow:none}
.card .mask{position:absolute;inset:auto 0 0 0;height:52%;background:linear-gradient(0deg,rgba(61,48,56,.72),transparent)}
.card .play-btn{position:absolute;left:50%;top:46%;z-index:2;width:44px;height:44px;border-radius:50%;background:rgba(255,255,255,.94);color:var(--pink);display:flex;align-items:center;justify-content:center;font-size:1rem;transform:translate(-50%,-50%) scale(.6);opacity:0;box-shadow:0 10px 24px rgba(247,141,167,.5);transition:opacity .25s var(--ease),transform .25s var(--ease)}
.card:hover .play-btn,.card:focus-visible .play-btn{opacity:1;transform:translate(-50%,-50%) scale(1)}
.card .tag{position:absolute;left:.55rem;bottom:.55rem;z-index:2;padding:.18rem .55rem;border-radius:999px;font-size:.7rem;font-weight:700;color:#fff;background:rgba(61,48,56,.45);backdrop-filter:blur(6px)}
.card .name{position:absolute;left:.7rem;right:.7rem;bottom:.62rem;z-index:2;color:#fff;font-size:.88rem;font-weight:700;line-height:1.3;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;text-shadow:0 1px 6px rgba(0,0,0,.5)}
.card .name:has(+ .tag){bottom:2.2rem}
.card .eps{position:absolute;right:.55rem;bottom:.55rem;z-index:2;color:rgba(255,255,255,.9);font-size:.68rem;text-shadow:0 1px 4px rgba(0,0,0,.6)}

/* ===== 网格 ===== */
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:1.05rem}

/* ===== 最近播放横向轨道 ===== */
.rail-wrap{position:relative}
.rail{display:flex;gap:1rem;overflow-x:auto;padding:.6rem .2rem 1.2rem;scroll-behavior:smooth;scrollbar-width:none;-ms-overflow-style:none}
.rail::-webkit-scrollbar{display:none}
.rail .card{flex:0 0 190px;aspect-ratio:2/3}
.rail .progress-bar{position:absolute;left:0;right:0;bottom:0;z-index:2;height:4px;background:rgba(255,255,255,.28)}
.rail .progress-bar i{display:block;height:100%;width:0;background:linear-gradient(90deg,var(--pink),var(--blue));transition:width 1.1s var(--ease)}
.rail .card.in .progress-bar i{width:var(--pct,0%)}
.rail .cont-badge{position:absolute;right:.55rem;bottom:.55rem;z-index:2;padding:.16rem .5rem;border-radius:999px;font-size:.66rem;font-weight:700;color:#fff;background:rgba(61,48,56,.55);backdrop-filter:blur(6px);transform:translateX(8px);opacity:0;transition:opacity .25s,transform .25s}
.rail .card:hover .cont-badge{opacity:1;transform:none}
.rail-arrow{position:absolute;top:38%;z-index:3;width:38px;height:38px;border-radius:50%;border:1px solid rgba(247,141,167,.35);background:rgba(255,255,255,.85);color:var(--pink);font-size:1.1rem;cursor:pointer;box-shadow:0 8px 20px rgba(247,141,167,.25);display:flex;align-items:center;justify-content:center;transition:background .2s,transform .2s}
.rail-arrow:hover{background:#fff;transform:scale(1.08)}
.rail-arrow.prev{left:-8px}
.rail-arrow.next{right:-8px}

/* ===== 空态 ===== */
.empty{padding:2.2rem 1rem;border:1.5px dashed rgba(247,141,167,.4);border-radius:16px;color:var(--ink-soft);text-align:center;font-size:.9rem;background:rgba(255,255,255,.5)}

/* ===== cz / 豆瓣 徽标 ===== */
.src-badge{display:inline-flex;align-items:center;justify-content:center;min-width:2rem;height:1.3rem;padding:0 .5rem;border-radius:6px;background:linear-gradient(135deg,#0ea5e9,#6366f1);color:#fff;font-size:.72rem;font-weight:900;letter-spacing:.05em}
.badge-cz{position:absolute;right:.55rem;top:.55rem;z-index:2;padding:.2rem .55rem;border-radius:8px;font-size:.7rem;font-weight:800;color:#fff;background:linear-gradient(135deg,#0ea5e9,#6366f1);box-shadow:0 6px 14px rgba(14,165,233,.4)}
.badge-douban{position:absolute;right:.55rem;top:.55rem;z-index:2;padding:.18rem .5rem;border-radius:8px;font-size:.68rem;font-weight:700;color:#fff;background:rgba(61,48,56,.5);backdrop-filter:blur(6px)}
.card-cz{cursor:pointer;overflow:hidden;background:#101a24}
.card-cz .ph{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:radial-gradient(circle at 50% 40%,#1b3a52,#0c1520);color:#9be15d;font-size:2rem}
.card-cz .poster{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.card-cz .eps{position:absolute;left:.55rem;bottom:.45rem;z-index:2;max-width:calc(100% - 1.1rem);padding:.14rem .42rem;border-radius:999px;font-size:.66rem;font-weight:600;color:#e6f5fb;background:rgba(12,21,32,.72);backdrop-filter:blur(4px);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.card-douban{cursor:pointer;border:1px solid rgba(126,200,227,.35)}
.card-douban:hover{border-color:var(--blue);transform:translateY(-6px)}

/* ===== 响应式 ===== */
@media(max-width:1000px){
  .page{flex-direction:column;padding:1.2rem 1rem 3.5rem}
  .main-col{padding-left:0}
  .side-nav{position:sticky;top:76px;left:auto;flex-direction:row;width:100%;justify-content:space-around;border-radius:18px;transform:none;margin-bottom:1.2rem;z-index:40}
  .side-nav.is-down{top:76px;transform:none}
  .side-link{flex:1;justify-content:center}
  .side-link .side-txt{display:none}
  .hero .slide .content{padding-left:2rem}
  .hero .arrow.prev{left:22px}
  .hero .dots{left:2rem}
}
@media(max-width:760px){
  .site-header{padding:.7rem 1rem;flex-wrap:wrap;gap:.6rem}
  .site-header .search-box{order:3;max-width:100%;width:100%}
  .header-links .hide-m{display:none}
  .hero{height:320px}
  .hero .slide .content{padding:0 1.2rem}
  .hero .desc{display:none}
  .hero .cta{display:none}
  .hero h1{font-size:1.35rem}
  .grid{grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:.7rem}
  .rail .card{flex-basis:120px}
  .section{margin:1.7rem 0 2.2rem}
}
</style>
</head>
<body class="watch-page">
<div id="watchLoading" class="watch-loading"><span class="watch-spinner"></span><span>正在加载影视库</span></div>

<header class="site-header">
  <a class="brand" href="/watch.php"><span class="brand-mark">🌸</span>樱视<em>withU 共享观影</em></a>
  <label class="search-box"><span class="s-icon">⌕</span><input id="watchSearch" placeholder="模糊搜索片名、演员、年份 或 直接搜 cz 厂长资源"></label>
  <nav class="header-links">
    <a href="/watch_history.php">历史</a>
    <a class="hide-m" href="#cz">cz 资源</a>
    <a class="hide-m" href="/">情侣空间</a>
  </nav>
</header>

<?php if (!$isBrowsePage): ?>
<section class="hero" id="hero"></section>
<?php endif; ?>

<div class="page">
  <nav class="side-nav" id="sideNav">
    <a class="side-link" href="#recent"><span class="side-icon">▶</span><span class="side-txt">最近播放</span></a>
    <a class="side-link" href="#movie"><span class="side-icon">🎬</span><span class="side-txt">热门电影</span></a>
    <a class="side-link" href="#tv"><span class="side-icon">📺</span><span class="side-txt">热门剧集</span></a>
    <a class="side-link" href="#library"><span class="side-icon">🗂</span><span class="side-txt">影视库</span></a>
  </nav>

  <div class="main-col">
    <?php if (!$isBrowsePage): ?>
    <section class="section" id="recent" style="--sec-accent:var(--pink);--sec-soft:var(--pink-soft)">
      <div class="section-head"><span class="sec-accent"></span><h2>最近播放</h2><span class="sec-note">继续上次的进度</span><a class="sec-more" href="/watch_history.php">全部历史 ›</a></div>
      <?php if (empty($recentGroups)): ?>
        <div class="empty">还没有达到记录条件的观看历史，去看一部片吧。</div>
      <?php else: ?>
      <div class="rail-wrap">
        <button class="rail-arrow prev" type="button" aria-label="向左">‹</button>
        <div class="rail" id="recentRail">
          <?php foreach ($recentGroups as $item): $duration = max(0, (int)($item['duration_ms'] ?? 0)); $position = max(0, (int)($item['last_position_ms'] ?? 0)); $progress = $duration > 0 ? min(100, round($position / $duration * 100)) : 0; ?>
          <a class="card" href="/watch_play.php?media_id=<?php echo (int)$item['id']; ?>" data-watch-title="<?php echo e(($item['series_name'] ?? '') . ' ' . $item['file_name']); ?>">
            <img class="poster" loading="lazy" src="/api/media_cover.php?id=<?php echo (int)$item['id']; ?>" alt="">
            <span class="tag"><?php echo e($item['episode_number'] ? '第 ' . $item['episode_number'] . ' 集' : '继续观看'); ?></span>
            <span class="name"><?php echo e($item['series_name']); ?></span>
            <span class="cont-badge">继续观看 · <?php echo $progress; ?>%</span>
            <span class="progress-bar"><i style="--pct:<?php echo $progress; ?>%"></i></span>
          </a>
          <?php endforeach; ?>
        </div>
        <button class="rail-arrow next" type="button" aria-label="向右">›</button>
      </div>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="section" id="movie" style="--sec-accent:var(--blue);--sec-soft:var(--blue-soft)">
      <div class="section-head"><span class="sec-accent"></span><h2>热门电影</h2><span class="sec-note">豆瓣新片 · 点击卡片用 cz 源搜索资源</span><a class="sec-more" href="/cz_player.php" target="_blank">cz 播放器 ›</a></div>
      <div class="grid" id="movieGrid"><div class="empty">正在加载豆瓣新片…</div></div>
    </section>

    <section class="section" id="tv" style="--sec-accent:var(--green);--sec-soft:var(--green-soft)">
      <div class="section-head"><span class="sec-accent"></span><h2>热门剧集</h2><span class="sec-note">豆瓣新剧 · 点击卡片用 cz 源搜索资源</span></div>
      <div class="grid" id="tvGrid"><div class="empty">正在加载豆瓣新剧…</div></div>
    </section>

    <section class="section" id="cz" hidden style="--sec-accent:var(--pink);--sec-soft:var(--pink-soft)">
      <div class="section-head"><span class="sec-accent"></span><h2><span class="src-badge">cz</span> 厂长资源</h2><span class="sec-note" id="czResultNote"></span></div>
      <div class="grid" id="czResultGrid"></div>
    </section>

    <section class="section" id="library" style="--sec-accent:var(--pink);--sec-soft:var(--pink-soft)">
      <div class="section-head"><span class="sec-accent"></span><h2 id="libraryTitle"><?php echo e($isBrowsePage ? $pageTitle : '影视库'); ?></h2><span class="sec-note" id="libraryCount"><?php echo count($groups); ?> 部</span><?php if ($isBrowsePage): ?><a class="sec-more" href="/watch.php">返回首页 ›</a><?php else: ?><a class="sec-more" href="/watch.php?all=1">显示更多 ›</a><?php endif; ?></div>
      <div class="grid" id="libraryGrid">
        <?php foreach (($isBrowsePage ? $groups : $allGroups) as $group): $first = $group['items'][0]; $episodeCount = count($group['items']); ?>
        <a class="card" href="/watch_play.php?media_id=<?php echo (int)$first['id']; ?>" data-watch-title="<?php echo e($group['name']); ?>">
          <img class="poster" loading="lazy" src="/api/media_cover.php?id=<?php echo (int)$first['id']; ?>" alt="">
          <?php if ($first['rating']): ?><span class="rate"><?php echo e($first['rating']); ?></span><?php endif; ?>
          <span class="mask"></span><span class="play-btn">▶</span>
          <span class="tag"><?php echo $episodeCount > 1 ? $episodeCount . ' 集' : '播放'; ?></span>
          <span class="name"><?php echo e($group['name']); ?></span>
        </a>
        <?php endforeach; ?>
      </div>
      <div id="libraryState" class="sec-note" style="margin-top:.6rem" hidden></div>
      <?php if (empty($groups)): ?><div class="empty"><?php echo $isBrowsePage && $isCategoryPage ? '该分类暂无已识别影视资源。' : ($isAllPage ? '暂无已识别影视资源。' : '媒体库暂无影片，稍后再来看看吧。'); ?></div><?php endif; ?>
    </section>
  </div>
</div>

<script>
(function(){
  'use strict';
  var isBrowse = <?php echo $isBrowsePage ? 'true' : 'false'; ?>;
  var categoryTypeId = <?php echo (int)$requestedTypeId; ?>;
  var searchPage = 1, controller = null, searching = false;

  function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}

  /* ============ 豆瓣数据 ============ */
  var movieGrid=document.getElementById('movieGrid'), tvGrid=document.getElementById('tvGrid');
  var heroEl=document.getElementById('hero'), heroTimer=null, heroIdx=0, heroSlides=[];
  function doubanCard(item){
    var r = item.rate ? Number(item.rate).toFixed(1) : 0;
    var eps = item.episodes_info || '';
    return '<a class="card card-douban" data-cz-title="'+esc(item.title)+'" href="javascript:void(0)" onclick="withuCzSearch(this.dataset.czTitle)">'
      + '<img class="poster" loading="lazy" src="'+esc(item.cover)+'" alt="">'
      + '<span class="rate'+(r?'':' none')+'">'+(r?'评分 '+r:'暂无评分')+'</span>'
      + '<span class="mask"></span><span class="play-btn">▶</span>'
      + '<span class="badge-douban">豆瓣</span>'
      + '<span class="name">'+esc(item.title)+'</span>'
      + (eps ? '<span class="eps">'+esc(eps)+'</span>' : '')
      + '</a>';
  }
  function renderDoubanGrid(type,list){
    var grid = type==='movie' ? movieGrid : tvGrid;
    if(!grid) return;
    if(!list || !list.length){grid.innerHTML='<div class="empty">豆瓣榜单暂时无法获取。</div>';return;}
    grid.innerHTML = list.map(doubanCard).join('');
    revealIn(grid);
  }
  function buildHero(){
    if(!heroEl || !heroSlides.length) return;
    heroEl.innerHTML = heroSlides.map(function(s,i){
      return '<div class="slide'+(i===0?' active':'')+'" data-idx="'+i+'">'
        + '<img class="bg" src="'+esc(s.bg)+'" alt="">'
        + '<div class="shade"></div>'
        + '<div class="content">'
        + '<span class="badge elem">'+esc(s.badge)+'</span>'
        + '<h1 class="elem">'+esc(s.title)+'</h1>'
        + '<div class="rating elem">'+esc(s.rating)+'</div>'
        + '<p class="desc elem">'+esc(s.desc)+'</p>'
        + '<a class="cta elem" href="javascript:void(0)" onclick="withuCzSearch(\''+esc(s.title).replace(/'/g,"\\'")+'\')">▶ 用 cz 源搜索资源</a>'
        + '</div></div>';
    }).join('');
    heroEl.insertAdjacentHTML('beforeend','<button class="arrow prev" type="button">‹</button><button class="arrow next" type="button">›</button><div class="dots">'+heroSlides.map(function(_,i){return '<i data-i="'+i+'"'+(i===0?' class="active"':'')+'></i>';}).join('')+'</div>');
    heroEl.querySelector('.arrow.prev').addEventListener('click',function(){gotoHero(heroIdx-1);});
    heroEl.querySelector('.arrow.next').addEventListener('click',function(){gotoHero(heroIdx+1);});
    heroEl.querySelectorAll('.dots i').forEach(function(dot){dot.addEventListener('click',function(){gotoHero(Number(dot.dataset.i));});});
    heroEl.addEventListener('mouseenter',stopHero);
    heroEl.addEventListener('mouseleave',startHero);
    startHero();
  }
  function gotoHero(i){
    if(!heroSlides.length) return;
    heroIdx = (i + heroSlides.length) % heroSlides.length;
    heroEl.querySelectorAll('.slide').forEach(function(s){s.classList.toggle('active', Number(s.dataset.idx)===heroIdx);});
    heroEl.querySelectorAll('.dots i').forEach(function(d,i2){d.classList.toggle('active', i2===heroIdx);});
  }
  function startHero(){ if(heroTimer||!heroSlides.length) return; heroTimer=setInterval(function(){gotoHero(heroIdx+1);},5800); }
  function stopHero(){ if(heroTimer){clearInterval(heroTimer);heroTimer=null;} }
  function loadDouban(){
    function done(type,d){
      if(d && d.success && d.list && d.list.length){
        if(type==='movie'){
          renderDoubanGrid('movie', d.list.slice(0,12));
          heroSlides = heroSlides.concat(d.list.slice(0,3).map(function(m){return {bg:m.cover,badge:'豆瓣新片',title:m.title,rating:m.rate?'评分 '+Number(m.rate).toFixed(1):'',desc:m.desc||'',src:'movie'};}));
        } else {
          renderDoubanGrid('tv', d.list.slice(0,12));
          heroSlides = heroSlides.concat(d.list.slice(0,3).map(function(m){return {bg:m.cover,badge:'豆瓣新剧',title:m.title,rating:m.rate?'评分 '+Number(m.rate).toFixed(1):'',desc:m.desc||'',src:'tv'};}));
        }
        if(heroSlides.length) buildHero();
      } else if(type==='movie' && movieGrid){
        movieGrid.innerHTML='<div class="empty">'+(d&&d.message?esc(d.message):'豆瓣榜单加载失败')+'</div>';
      } else if(type==='tv' && tvGrid){
        tvGrid.innerHTML='<div class="empty">'+(d&&d.message?esc(d.message):'豆瓣榜单加载失败')+'</div>';
      }
    }
    fetch('/api/douban_chart.php?type=movie&limit=12',{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){done('movie',d);}).catch(function(){done('movie',null);});
    fetch('/api/douban_chart.php?type=tv&limit=12',{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){done('tv',d);}).catch(function(){done('tv',null);});
  }

  /* ============ cz 联动 ============ */
  var czSec=document.getElementById('cz'), czGrid=document.getElementById('czResultGrid'), czNote=document.getElementById('czResultNote');
  function czCard(item){
    var poster = item.poster || '';
    var metaLine = [item.year, (item.types||[]).join('、')].filter(Boolean).join(' · ');
    return '<a class="card card-cz" href="/watch_play.php?source=cz&url='+encodeURIComponent(item.url)+'">'
      + (poster
          ? '<img class="poster" loading="lazy" src="'+esc(poster)+'" alt="">'
          : '<div class="ph">▶</div>')
      + '<span class="badge-cz">cz</span>'
      + '<span class="mask"></span>'
      + '<span class="tag">厂长资源</span>'
      + '<span class="name">'+esc(item.title)+'</span>'
      + (metaLine ? '<span class="eps">'+esc(metaLine)+'</span>' : '')
      + '</a>';
  }
  function renderCz(list,q){
    if(!czSec) return;
    if(!list || !list.length){
      czSec.hidden=false;
      czGrid.innerHTML='<div class="empty">cz 源未找到「'+esc(q)+'」的匹配资源。</div>';
      czNote.textContent='';
      return;
    }
    czSec.hidden=false;
    czGrid.innerHTML=list.map(czCard).join('');
    czNote.textContent='「'+q+'」匹配 '+list.length+' 条 · 编码 cz';
    revealIn(czGrid);
  }
  function czSearch(title){
    if(!title) return;
    if(czSec){czSec.hidden=false;czGrid.innerHTML='<div class="empty">正在用 cz 源搜索「'+esc(title)+'」…</div>';czNote.textContent='';}
    fetch('/api/cz.php?action=search&q='+encodeURIComponent(title),{credentials:'same-origin'})
      .then(function(r){return r.json();})
      .then(function(d){ if(d && d.success) renderCz(d.list,title); else renderCz(null,title); })
      .catch(function(){ renderCz(null,title); });
    if(czSec) czSec.scrollIntoView({behavior:'smooth',block:'start'});
  }
  window.withuCzSearch = czSearch;

  /* ============ 本地库搜索 ============ */
  var search=document.getElementById('watchSearch'), libGrid=document.getElementById('libraryGrid'),
      libTitle=document.getElementById('libraryTitle'), libCount=document.getElementById('libraryCount'), libState=document.getElementById('libraryState');
  function badgeHtml(item){
    var text=String((item&&item.quality_text)||((item&&item.resolution)||'')).toUpperCase();
    if(/4K|2160P|UHD/.test(text)) return '<span class="rate" style="background:linear-gradient(135deg,#fff1a8,#d69a17)">4K</span>';
    return '';
  }
  function libCard(item){
    var name=item.group_name||item.series_name||item.file_name||'未命名影视';
    var countText=(item.episode_count||1)>1?item.episode_count+' 集':'播放';
    return '<a class="card" data-watch-title="'+esc(name)+'" href="/watch_play.php?media_id='+Number(item.id)+'">'
      + '<img class="poster" loading="lazy" src="/api/media_cover.php?id='+Number(item.id)+'" alt="">'
      + (item.rating?'<span class="rate">'+esc(item.rating)+'</span>':'')
      + '<span class="mask"></span><span class="play-btn">▶</span>'
      + '<span class="tag">'+countText+'</span>'
      + '<span class="name">'+esc(name)+'</span>'
      + '</a>';
  }
  async function loadSearch(append){
    var q=search.value.trim();
    if(!q){ if(searching) location.reload(); return; }
    if(controller) controller.abort();
    controller=new AbortController();
    if(!append) searchPage=1;
    var url='/api/media.php?action=library&q='+encodeURIComponent(q)+'&page='+searchPage+'&limit=24';
    if(categoryTypeId) url+='&type_id='+categoryTypeId;
    if(!append){libGrid.innerHTML='<div class="empty">正在搜索…</div>';searching=true;libTitle.textContent=categoryTypeId?'搜索结果':'搜索结果';}
    try{
      var response=await fetch(url,{credentials:'same-origin',signal:controller.signal});
      var data=await response.json();
      if(!data.success) throw new Error(data.message||'搜索失败');
      if(!append) libGrid.innerHTML='';
      (data.items||[]).forEach(function(item){libGrid.insertAdjacentHTML('beforeend',libCard(item));});
      if(libCount) libCount.textContent=(data.items||[]).length+' 条';
      if(libState){libState.hidden=false;libState.textContent=(data.items||[]).length?'支持片名、别名、演员、年份和集标题的模糊匹配':'没有找到匹配的影视资源';}
      revealIn(libGrid);
    }catch(error){
      if(error.name==='AbortError') return;
      libGrid.innerHTML='<div class="empty">'+esc(error.message||'搜索失败，请重试')+'</div>';
    }
  }
  function loadCzSearch(q){
    if(!q){ if(czSec) czSec.hidden=true; return; }
    czSearch(q);
  }
  var timer=null;
  if(search) search.addEventListener('input',function(){
    clearTimeout(timer);
    timer=setTimeout(function(){ loadSearch(false); loadCzSearch(search.value.trim()); },320);
  });

  /* ============ 入场动画 ============ */
  var io=null;
  function revealIn(wrap){
    var cards = wrap.querySelectorAll('.card:not(.in)');
    if(!cards.length) return;
    cards.forEach(function(c,i){ c.style.setProperty('--stagger', (i%10)*55+'ms'); });
    if(!('IntersectionObserver' in window)){ cards.forEach(function(c){c.classList.add('in');}); return; }
    io = io || new IntersectionObserver(function(entries){
      entries.forEach(function(en){
        if(en.isIntersecting){ en.target.classList.add('in'); io.unobserve(en.target); }
      });
    },{rootMargin:'0px 0px -40px 0px'});
    cards.forEach(function(c){ io.observe(c); });
  }

  /* ============ 侧边导航滚动联动 + scroll spy ============ */
  var sideNav=document.getElementById('sideNav'), recentSec=document.getElementById('recent');
  function placeNav(){
    if(!sideNav || !recentSec) return;
    if(window.innerWidth<=1000){ sideNav.classList.remove('is-down'); return; }
    var rect=recentSec.getBoundingClientRect();
    var center=rect.top + rect.height/2;
    sideNav.style.setProperty('--recent-center', center+'px');
    var heroRect=document.getElementById('hero') ? document.getElementById('hero').getBoundingClientRect() : {height:0,top:0};
    if(window.scrollY < heroRect.height*0.6){ sideNav.classList.add('is-down'); } else { sideNav.classList.remove('is-down'); }
  }
  var rafPending=false;
  window.addEventListener('scroll',function(){ if(rafPending) return; rafPending=true; requestAnimationFrame(function(){ rafPending=false; placeNav(); }); },{passive:true});
  window.addEventListener('resize',function(){ placeNav(); });
  if(sideNav && document.querySelectorAll('.section').length){
    var spy=new IntersectionObserver(function(entries){
      entries.forEach(function(en){
        if(en.isIntersecting){
          sideNav.querySelectorAll('.side-link').forEach(function(a){ a.classList.toggle('active', a.getAttribute('href')==='#'+en.target.id); });
        }
      });
    },{rootMargin:'-40% 0px -55% 0px'});
    document.querySelectorAll('.section').forEach(function(s){ spy.observe(s); });
  }

  /* ============ 轨道滚动 ============ */
  document.querySelectorAll('.rail-wrap').forEach(function(wrap){
    var rail=wrap.querySelector('.rail');
    var prev=wrap.querySelector('.rail-arrow.prev'), next=wrap.querySelector('.rail-arrow.next');
    if(prev) prev.addEventListener('click',function(){ rail.scrollBy({left:-600,behavior:'smooth'}); });
    if(next) next.addEventListener('click',function(){ rail.scrollBy({left:600,behavior:'smooth'}); });
  });

  /* ============ 图片错误 fallback ============ */
  document.addEventListener('error',function(event){
    var image=event.target;
    if(!image || image.tagName!=='IMG' || image.dataset.fallback) return;
    if(image.src.indexOf('Coverloaderror')>=0) return;
    image.dataset.fallback='1';
    image.src='/assets/images/Coverloaderror.jpg';
  },true);

  /* ============ 启动 ============ */
  document.addEventListener('DOMContentLoaded',function(){
    requestAnimationFrame(function(){
      var loading=document.getElementById('watchLoading');
      if(loading) loading.classList.add('is-hidden');
      revealIn(document.getElementById('recentRail')||document.body);
      revealIn(document.getElementById('libraryGrid'));
      if(!isBrowse) loadDouban();
      placeNav();
    });
  });
  window.addEventListener('load',placeNav);
})();
</script>
</body>
</html>
