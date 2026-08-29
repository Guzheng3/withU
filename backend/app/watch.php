<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/withu.php';

migrate_schema_if_needed();
$auth = new Auth();
$loggedIn = $auth->isLoggedIn();
$user = withu_require_couple_user($auth);

// 加载全局配置（情侣信息等）
$configPath = __DIR__ . '/../../frontend/inc/config.php';
if (file_exists($configPath)) {
    require_once $configPath;
} else {
    $withuConfigJson = '{}';
}
$db = Database::getInstance();
$historyMinMs = withu_watch_history_min_ms();

$historyRows = $db->fetchAll(
    "SELECT wh.*, COALESCE(wr.source, 'library') AS history_source, COALESCE(wr.source_episode, 0) AS history_source_episode
     FROM watch_history wh
     LEFT JOIN watch_rooms wr ON wr.id = wh.room_id
     WHERE wh.watch_duration_ms >= :min_ms
     ORDER BY wh.updated_at DESC, wh.id DESC LIMIT 24",
    ['min_ms' => $historyMinMs]
);
$recentRows = [];
foreach ($historyRows as $historyRow) {
    $mediaId = (int)$historyRow['media_id'];
    if ((string)($historyRow['history_source'] ?? 'library') !== 'strm') continue;
    $meta = withu_strm_media_fetch($mediaId);
    if (!$meta) continue;
    $episodeId = (int)($historyRow['history_source_episode'] ?? 0);
    $episode = null;
    foreach ((array)($meta['episodes'] ?? []) as $candidate) {
        if ((int)($candidate['id'] ?? 0) === $episodeId) { $episode = $candidate; break; }
    }
    $mediaRow = [
        'id' => $mediaId,
        'file_name' => (string)($episode['sourceFileName'] ?? ($meta['title'] ?? '')),
        'series_name' => (string)($meta['title'] ?? 'strm 媒体'),
        'series_key' => 'strm-title-' . preg_replace('/\s+/u', '', mb_strtolower((string)($meta['title'] ?? 'strm 媒体'), 'UTF-8')),
        'episode_number' => (int)($episode['episodeNo'] ?? 0),
        'duration_ms' => 0,
        'cover_url' => (string)($meta['posterUrl'] ?? ''),
        'resolution' => '',
        'source' => 'strm',
    ];
    $recentRows[] = array_merge($mediaRow, $historyRow);
}
$recentSeries = [];
foreach ($recentRows as $row) {
    $key = (string)($row['history_source'] ?? 'library') . ':' . (string)($row['series_key'] ?: $row['id']);
    if (!isset($recentSeries[$key])) {
        $item = $row;
        $item['latest_watch_at'] = (string)($row['updated_at'] ?? '');
        $recentSeries[$key] = $item;
    }
}
$recent = array_values($recentSeries);
$recentGroups = array_values(array_slice($recent, 0, 8));
$pageTitle = '影视库';
$themeConfig = ['preset' => 'light', 'mode' => 'light', 'custom' => false, 'colors' => []];
$themeInlineStyle = '';
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo e($pageTitle); ?> - withU 共享观影</title>
<link rel="stylesheet" href="/Style/vendor/qweather-icons/qweather-icons.css">
<link rel="stylesheet" href="/Style/vendor/remixicon/remixicon.css">
<link rel="stylesheet" href="/Style/css/header.css">
<link rel="stylesheet" href="/Style/css/header-layout.css">
<link rel="stylesheet" href="/Style/css/phosphor-icons.css">
<link rel="stylesheet" href="/Style/css/phosphor-fill.css">
<link rel="stylesheet" href="/Style/css/phosphor-regular.css">
<link rel="stylesheet" href="/Style/css/phosphor-duotone.css">
<!-- 头部交互所需的 WITHU_CONFIG（与 frontend/inc/config.php 一致） -->
<script>window.WITHU_CONFIG = Object.assign(window.WITHU_CONFIG || {}, <?php echo $withuConfigJson; ?>);</script>
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
  /* 顶栏为 fixed 定位，内容需避让（与 header-layout.css 的 .header-wrap 高度三档一致） */
  padding-top:54px;
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
@media(min-width:769px){body.watch-page{padding-top:63px}}
@media(min-width:960px){body.watch-page{padding-top:70px}}
body.watch-page a{color:inherit}
.watch-loading{position:fixed;inset:0;z-index:200;display:flex;align-items:center;justify-content:center;gap:.6rem;background:var(--bg);color:var(--ink-soft);transition:opacity .3s,visibility .3s}
.watch-loading.is-hidden{opacity:0;visibility:hidden;pointer-events:none}
.watch-spinner{width:18px;height:18px;border:2px solid rgba(247,141,167,.3);border-top-color:var(--pink);border-radius:50%;animation:watch-spin .8s linear infinite}
@keyframes watch-spin{to{transform:rotate(360deg)}}

/* ===== 头部搜索（渲染于头部诗句之前，元素在 header.php 条件插槽中） ===== */
.withu-header-search{display:flex;align-items:center;gap:.4rem;height:34px;padding:0 12px 0 11px;border-radius:999px;background:rgba(0,0,0,.04);border:1px solid transparent;transition:background .2s,border-color .2s,box-shadow .2s}
.withu-header-search:focus-within{background:rgba(255,255,255,.9);border-color:var(--pink);box-shadow:0 8px 24px rgba(247,141,167,.18)}
.withu-header-search .s-icon{color:var(--pink);font-size:.95rem;line-height:1}
.withu-header-search input{width:150px;border:0;outline:0;background:transparent;color:var(--ink);font-size:.9rem;padding:0}
.withu-header-search input::placeholder{color:var(--ink-soft)}
@media(max-width:768px){.withu-header-search input{width:96px}}

/* ===== 头部诗句常显：吸顶后不随 withu-actions-visible 隐藏 ===== */
.withu-header-actions.withu-actions-visible .withu-header-poem{display:block !important}

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
 .rail .last-episode-badge{position:absolute;left:.55rem;bottom:2.1rem;z-index:2;padding:.16rem .5rem;border-radius:999px;font-size:.64rem;font-weight:700;color:#fff;background:rgba(61,48,56,.55);backdrop-filter:blur(6px)}
.rail-arrow{position:absolute;top:38%;z-index:3;width:38px;height:38px;border-radius:50%;border:1px solid rgba(247,141,167,.35);background:rgba(255,255,255,.85);color:var(--pink);font-size:1.1rem;cursor:pointer;box-shadow:0 8px 20px rgba(247,141,167,.25);display:flex;align-items:center;justify-content:center;transition:background .2s,transform .2s}
.rail-arrow:hover{background:#fff;transform:scale(1.08)}
.rail-arrow.prev{left:-8px}
.rail-arrow.next{right:-8px}

/* ===== 空态 ===== */
.empty{padding:2.2rem 1rem;border:1.5px dashed rgba(247,141,167,.4);border-radius:16px;color:var(--ink-soft);text-align:center;font-size:.9rem;background:rgba(255,255,255,.5)}

/* ===== cz / 豆瓣 徽标 ===== */
.src-badge{display:inline-flex;align-items:center;justify-content:center;min-width:2rem;height:1.3rem;padding:0 .5rem;border-radius:6px;background:linear-gradient(135deg,#0ea5e9,#6366f1);color:#fff;font-size:.72rem;font-weight:900;letter-spacing:.05em}

/* ===== 响应式 ===== */
@media(max-width:1000px){
  .page{flex-direction:column;padding:1.2rem 1rem 3.5rem}
  .main-col{padding-left:0}
  .side-nav{position:sticky;top:76px;left:auto;flex-direction:row;width:100%;justify-content:space-around;border-radius:18px;transform:none;margin-bottom:1.2rem;z-index:40}
  .side-nav.is-down{top:76px;transform:none}
  .side-link{flex:1;justify-content:center}
  .side-link .side-txt{display:none}
}
@media(max-width:760px){
  .header-links .hide-m{display:none}
  .grid{grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:.7rem}
  .rail .card{flex-basis:120px}
  .section{margin:1.7rem 0 2.2rem}
}

.strm-group{margin-top:.4rem}
.strm-group+.strm-group{margin-top:1.8rem}
.strm-group-title{display:flex;align-items:center;gap:.6rem;margin:0 0 .9rem;font-size:1.02rem;font-weight:800;color:var(--ink)}
.strm-group-title::after{content:"";flex:1;height:1px;background:linear-gradient(90deg,rgba(167,139,250,.4),transparent)}
.card-strm:hover{border-color:#a78bfa;transform:translateY(-6px)}
.card-strm .rate{right:.55rem;top:.55rem;left:auto;bottom:auto;background:linear-gradient(135deg,#a78bfa,#8b5cf6)}

</style>
</head>
<body class="watch-page">
<?php
$withuHeaderSearchBeforePoem = true; // 搜索框由 header.php 条件插槽渲染到诗句前（仅本页生效）
$withuHeaderHideWeatherFootprint = true; // 本页不显示头部天气胶囊与足迹入口（header.php 条件渲染）
$headerPath = __DIR__ . '/../../frontend/inc/header.php';
if (file_exists($headerPath)) {
    include $headerPath;
}
?>
<div id="watchLoading" class="watch-loading"><span class="watch-spinner"></span><span>正在加载影视库</span></div>

<div class="page">
  <nav class="side-nav" id="sideNav">
    <a class="side-link" href="#recent"><span class="side-icon">▶</span><span class="side-txt">最近播放</span></a>
    <a class="side-link" href="#strm"><span class="side-icon">🎬</span><span class="side-txt">媒体库</span></a>
  </nav>

  <div class="main-col">
    <section class="section" id="recent" style="--sec-accent:var(--pink);--sec-soft:var(--pink-soft)">
      <div class="section-head"><span class="sec-accent"></span><h2>最近播放</h2><span class="sec-note">继续上次的进度</span><a class="sec-more" href="/watch_history.php">全部历史 ›</a></div>
      <?php if (empty($recentGroups)): ?>
        <div class="empty">还没有达到记录条件的观看历史，去看一部片吧。</div>
      <?php else: ?>
      <div class="rail-wrap">
        <button class="rail-arrow prev" type="button" aria-label="向左">‹</button>
        <div class="rail" id="recentRail">
          <?php foreach ($recentGroups as $item): $duration = max(0, (int)($item['duration_ms'] ?? 0)); $position = max(0, (int)($item['last_position_ms'] ?? 0)); $progress = $duration > 0 ? min(100, round($position / $duration * 100)) : 0; $playUrl = '/watch_play.php?source=strm&id=' . (int)$item['id']; $coverUrl = (string)($item['cover_url'] ?? '') ?: ('/api/strm.php?action=img&id=' . (int)$item['id']); ?>
          <a class="card" href="<?php echo e($playUrl); ?>" data-watch-title="<?php echo e(($item['series_name'] ?? '') . ' ' . $item['file_name']); ?>">
            <img class="poster" loading="lazy" src="<?php echo e($coverUrl); ?>" alt="">
             <span class="name"><?php echo e($item['series_name']); ?></span>
             <?php if (!empty($item['episode_number'])): ?><span class="last-episode-badge">上次观看 · 第 <?php echo (int)$item['episode_number']; ?> 集</span><?php endif; ?>
             <span class="cont-badge">继续观看 · <?php echo $progress; ?>%</span>
            <span class="progress-bar"><i style="--pct:<?php echo $progress; ?>%"></i></span>
          </a>
          <?php endforeach; ?>
        </div>
        <button class="rail-arrow next" type="button" aria-label="向右">›</button>
      </div>
      <?php endif; ?>
    </section>

    <section class="section" id="strm" style="--sec-accent:#a78bfa;--sec-soft:rgba(167,139,250,.15)">
      <div class="section-head">
         <span class="sec-accent"></span><h2>媒体库</h2>
         <span class="sec-note">点击卡片进入一起看</span>
        <a class="sec-more" href="/admin/strm_home.php" target="_blank">后台管理 ›</a>
      </div>
      <div class="strm-group">
        <h3 class="strm-group-title">电影</h3>
        <div class="grid" id="strmGridMovie"><div class="empty">正在加载电影…</div></div>
      </div>
      <div class="strm-group">
        <h3 class="strm-group-title">电视剧</h3>
        <div class="grid" id="strmGridTv"><div class="empty">正在加载剧集…</div></div>
      </div>
      <div id="strmNote" class="sec-note" style="margin-top:.6rem" hidden></div>
    </section>
  </div>
</div>

<script>
(function(){
  'use strict';
  function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}

  /* ============ 媒体库 ============ */
  var strmSec=document.getElementById('strm'), strmGridMovie=document.getElementById('strmGridMovie'), strmGridTv=document.getElementById('strmGridTv'), strmNote=document.getElementById('strmNote');
  var strmPosterWarm=false;
  function strmCard(item){
    var poster = item.posterUrl || '';
    var metaLine = [item.year, item.mediaType==='tv'?'剧集':'电影'].filter(Boolean).join(' · ');
      var href = '/watch_play.php?source=strm&id='+Number(item.id);
    return '<a class="card card-strm" href="'+href+'" data-watch-title="'+esc(item.name)+'">'
      + (poster ? '<img class="poster" loading="lazy" src="'+esc(poster)+'" alt="" referrerpolicy="no-referrer">' : '<div class="ph">▶</div>')
      + '<span class="mask"></span><span class="play-btn">▶</span>'
      + '<span class="name">'+esc(item.name)+'</span>'
      + (metaLine ? '<span class="eps">'+esc(metaLine)+'</span>' : '')
      + (item.voteAverage ? '<span class="rate">'+Number(item.voteAverage).toFixed(1)+'</span>' : '')
      + '</a>';
  }
  function strmRenderGroup(grid, items, emptyMsg){
    if(!grid) return;
    if(!items.length){ grid.innerHTML='<div class="empty">'+esc(emptyMsg)+'</div>'; return; }
    grid.innerHTML=items.map(strmCard).join('');
    revealIn(grid);
  }
  function loadStrmGroup(type, grid, emptyMsg, kw){
    if(!grid) return Promise.resolve();
    grid.innerHTML='<div class="empty">正在加载…</div>';
    var url='/api/strm.php?action=media&page=1&pageSize=50&type='+type+(kw?'&keyword='+encodeURIComponent(kw):'');
    return fetch(url,{credentials:'same-origin'})
      .then(function(r){return r.json();})
      .then(function(d){
        if(!d || !d.success){ grid.innerHTML='<div class="empty">'+(d&&d.message?esc(d.message):'媒体库加载失败')+'</div>'; return 0; }
        var items=(d.data||{}).items||[];
        strmRenderGroup(grid, items, emptyMsg);
        if(!strmPosterWarm && items.some(function(it){return !(it.posterUrl||'');})){
          strmPosterWarm=true;
          fetch('/api/strm.php?action=posters',{credentials:'same-origin'})
            .then(function(r){return r.json();})
            .then(function(pd){ if(pd && pd.success){ loadStrmGroup(type, grid, emptyMsg, kw); } })
            .catch(function(){});
        }
        return items.length;
      })
      .catch(function(){ grid.innerHTML='<div class="empty">媒体库加载失败</div>'; return 0; });
  }
  function loadStrm(kw){
    var q=kw||'';
    if(!strmSec) return;
    Promise.all([
      loadStrmGroup('movie', strmGridMovie, q?'没有匹配的电影':'暂无电影', q||''),
      loadStrmGroup('tv', strmGridTv, q?'没有匹配的剧集':'暂无剧集', q||'')
    ]).then(function(counts){
      var total=(counts[0]||0)+(counts[1]||0);
      if(strmNote){
        if(q){ strmNote.hidden=false; strmNote.textContent='搜索「'+q+'」· 共 '+total+' 部'; }
        else { strmNote.hidden=true; strmNote.textContent=''; }
      }
    });
  }
  if(strmSec){loadStrm('');}

  /* ============ 媒体库搜索 ============ */
  var search=document.getElementById('watchSearch');
  var searchTimer=null;
  function loadStrmSearch(q){
    if(!strmSec) return;
    loadStrm(q);
  }
  if(search) search.addEventListener('input',function(){
    clearTimeout(searchTimer);
    searchTimer=setTimeout(function(){ loadStrmSearch(search.value.trim()); },320);
  });

  /* ============ 入场动画 ============ */
  var io=null;
  function revealIn(wrap){
    if(!wrap) return;
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
  }
  window.addEventListener('scroll',function(){ placeNav(); },{passive:true});
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
      placeNav();
    });
  });
  window.addEventListener('load',placeNav);

  /* ============ 与首页一致的头部行为（lucide 图标 / 返回胶囊 / 更多面板 / 访客天气） ============ */
  // jQuery 是 app.js / components.js 的依赖，先于二者加载
  var jq = document.createElement('script');
  jq.src = '/Style/jquery/jquery.min.js';
  jq.onload = function () {
    var appJs = document.createElement('script');
    appJs.src = '/assets/js/app.js';
    appJs.onload = function () {
      // WithUApp 核心框架（含返回胶囊、移动端更多面板初始化），与首页一致
      if (window.WithUApp && typeof window.WithUApp.init === 'function') {
        try {
          window.WithUApp.setConfig(window.WITHU_CONFIG || {});
          window.WithUApp.init();
        } catch (err) {}
      }
      var compJs = document.createElement('script');
      compJs.src = '/assets/js/components.js';
      compJs.onload = function () {
        // 天气胶囊 + Logo 交互（与首页 index.php 的初始化一致）
        if (window.WithUComponents) {
          try { window.WithUComponents.HeaderVisitorWeather && window.WithUComponents.HeaderVisitorWeather.init(); } catch (err) {}
          try { window.WithUComponents.LogoHomeIcon && window.WithUComponents.LogoHomeIcon.init(); } catch (err) {}
        }
      };
      document.head.appendChild(compJs);
    };
    document.head.appendChild(appJs);
  };
  document.head.appendChild(jq);

  // lucide 图标渲染（返回按钮/更多按钮的 data-lucide 图标）
  var lucideJs = document.createElement('script');
  lucideJs.src = '/Style/toastify/lucide.min.js';
  lucideJs.onload = function () {
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      try { window.lucide.createIcons(); } catch (err) {}
    }
  };
  document.head.appendChild(lucideJs);

  /* ============ 顶栏滚动吸顶（复刻首页 components.js 的吸顶行为） ============
     首页由导航岛吸顶触发天气/足迹出现、诗句淡出、返回胶囊显示；本页无导航岛，
     以 72px（与 components.js 的 headerHeight 一致）作为等效触发点。 */
  var watchHeaderEls = {
    weather: document.getElementById('withuHeaderVisitorWeather'),
    map: document.getElementById('withuMapOpenBtn'),
    actions: document.querySelector('.withu-header-actions'),
    capsule: document.querySelector('.withu-capsule-back')
  };
  var watchStuck = false;
  function watchHeaderOnScroll(){
    var stuck = window.scrollY > 72;
    if (stuck === watchStuck) return;
    watchStuck = stuck;
    var method = stuck ? 'add' : 'remove';
    if (watchHeaderEls.weather) watchHeaderEls.weather.classList[method]('withu-weather-visible');
    if (watchHeaderEls.map) watchHeaderEls.map.classList[method]('withu-weather-visible');
    if (watchHeaderEls.actions) watchHeaderEls.actions.classList[method]('withu-actions-visible');
    if (watchHeaderEls.capsule && watchHeaderEls.capsule.classList.contains('subpage-back-ready')) {
      watchHeaderEls.capsule.classList[method]('scroll-back-visible');
    }
  }
  window.addEventListener('scroll', watchHeaderOnScroll, { passive: true });
  watchHeaderOnScroll();

  // 地图弹窗（足迹按钮 + 更多面板中的足迹地图）
  // map.js 在 DOMContentLoaded 时自动绑定 withuMapOpenBtn，无需手动 init
  var mapCss1 = document.createElement('link');
  mapCss1.rel = 'stylesheet'; mapCss1.href = '/Style/css/map.css';
  document.head.appendChild(mapCss1);
  var mapJs = document.createElement('script');
  mapJs.src = '/assets/js/map.js';
  document.head.appendChild(mapJs);
})();
</script>
<script src="/assets/js/sakura.js"></script>
</body>
</html>
