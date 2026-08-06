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
    $mediaError = '影视资源库未初始化：请先用数据库管理员执行 deploy/init-media-db.sql，然后刷新页面。';
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
<html lang="zh-CN" data-withu-theme="<?php echo e($themeConfig['preset']); ?>" data-withu-mode="<?php echo e($themeConfig['mode']); ?>"<?php if (!empty($themeConfig['custom'])): ?> data-withu-theme-custom="1" style="<?php echo e($themeInlineStyle); ?>"<?php endif; ?>>
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo e($pageTitle); ?> - withU</title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="/assets/css/theme.css?v=withu-theme-20260719-3">
<style>
.watch-page{background:#0b0f14;color:#f4f7f5}.watch-page a{color:inherit}.watch-loading{position:fixed;inset:0;z-index:100;display:flex;align-items:center;justify-content:center;gap:.65rem;background:#0b0f14;color:#a8b4ad;transition:opacity .25s,visibility .25s}.watch-loading.is-hidden{opacity:0;visibility:hidden;pointer-events:none}.watch-spinner{width:18px;height:18px;border:2px solid #34423a;border-top-color:#9be15d;border-radius:50%;animation:watch-spin .8s linear infinite}@keyframes watch-spin{to{transform:rotate(360deg)}}.watch-home{max-width:1380px;margin:0 auto;padding:0 1.25rem 4rem}.watch-nav{height:70px;display:flex;align-items:center;gap:2rem;border-bottom:1px solid #1b2420}.watch-brand{display:inline-flex;align-items:baseline;gap:.45rem;text-decoration:none}.watch-brand strong{font-size:1.45rem;letter-spacing:0;color:#fff}.watch-brand span{font-size:.82rem;color:#9be15d}.watch-nav nav{display:flex;align-items:center;gap:1.35rem;font-size:.92rem;color:#93a19a}.watch-nav nav a{padding:25px 0 22px;text-decoration:none;border-bottom:2px solid transparent}.watch-nav nav a:hover,.watch-nav nav a.active{color:#fff;border-color:#9be15d}.watch-home-actions{margin-left:auto;display:flex;align-items:center;gap:.6rem}.watch-search-wrap{display:flex;align-items:center;gap:.5rem;width:min(310px,32vw);padding:.48rem .7rem;border:1px solid #29352e;border-radius:5px;background:#121a16;color:#819087}.watch-search{width:100%;border:0;outline:0;background:transparent;color:#f4f7f5}.watch-search::placeholder{color:#68766e}.watch-hero{position:relative;min-height:390px;margin:1.35rem 0 2.15rem;display:flex;align-items:flex-end;overflow:hidden;border-radius:4px;background-color:#17201b;background-image:var(--hero-image);background-size:cover;background-position:center}.watch-hero-shade{position:absolute;inset:0;background:linear-gradient(90deg,rgba(7,12,10,.88),rgba(7,12,10,.56) 42%,rgba(7,12,10,.18))}.watch-hero-content{position:relative;z-index:2;max-width:620px;padding:3rem 3.2rem}.watch-eyebrow{color:#9be15d;font-size:.76rem;letter-spacing:.08em}.watch-hero h1{margin:.65rem 0 .55rem;font-size:clamp(1.8rem,4vw,3.2rem);color:#fff}.watch-hero p{max-width:560px;margin:0;color:#d0d9d3;line-height:1.7}.watch-hero-meta{margin:1rem 0;color:#a9b8ae;font-size:.85rem}.watch-primary-action{display:inline-flex;align-items:center;gap:.45rem;padding:.68rem 1.1rem;border-radius:4px;background:#9be15d;color:#101710!important;font-weight:700;text-decoration:none}.watch-section{margin:2.1rem 0 2.7rem}.watch-section-head{display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-bottom:1rem}.watch-section-head h2{margin:0;font-size:1.2rem;color:#fff}.watch-section-head h2:before{content:"";display:inline-block;width:3px;height:1.05em;margin-right:.55rem;vertical-align:-.14em;background:#9be15d}.watch-section-head span{color:#77857c;font-size:.8rem}.watch-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:1rem}.watch-grid-continue{grid-template-columns:repeat(auto-fill,minmax(250px,1fr))}.watch-card{display:block;overflow:hidden;background:#121916;border:1px solid #202c25;border-radius:4px;color:inherit;text-decoration:none;transition:transform .2s,border-color .2s,background .2s}.watch-card:hover{transform:translateY(-3px);border-color:#6fa74b;background:#172119}.watch-card-cover{position:relative;aspect-ratio:2/3;background:#1b2520;overflow:hidden}.watch-card-cover img{width:100%;height:100%;object-fit:cover;display:block}.watch-card-badge{position:absolute;left:.5rem;bottom:.5rem;padding:.2rem .42rem;border-radius:3px;background:#0b0f14d9;color:#fff;font-size:.7rem}.watch-card-body{padding:.65rem .7rem}.watch-card-title{font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#f3f7f4}.watch-card-meta{margin-top:.35rem;color:#89988e;font-size:.77rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.watch-progress{height:3px;background:#29352e;margin-top:.55rem}.watch-progress i{display:block;height:100%;background:#9be15d}.watch-empty{padding:1.2rem;background:#121916;border:1px dashed #36453b;border-radius:4px;color:#819087}.watch-home-note{font-size:.8rem;color:#68776d;margin-top:.5rem}@media(max-width:720px){.watch-home{padding:0 .8rem 2rem}.watch-nav{height:auto;min-height:62px;gap:1rem;flex-wrap:wrap;padding:.7rem 0}.watch-nav nav{order:3;width:100%;gap:1rem}.watch-nav nav a{padding:0 0 .55rem}.watch-search-wrap{width:42vw}.watch-hero{min-height:330px}.watch-hero-content{padding:2rem 1.3rem}.watch-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:.7rem}.watch-grid-continue{grid-template-columns:repeat(2,minmax(0,1fr))}.watch-card-body{padding:.55rem}.watch-card-title{font-size:.88rem}.watch-card-meta{font-size:.7rem}}
 .watch-hero-art{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:.9}
 .resolution-badge{position:absolute;right:.52rem;top:.52rem;z-index:3;display:inline-flex;align-items:center;justify-content:center;min-width:2.15rem;height:1.35rem;padding:0 .48rem;border:1px solid rgba(255,255,255,.52);border-radius:999px;color:#fff;font-size:.72rem;font-weight:900;line-height:1;letter-spacing:.02em;text-shadow:0 1px 2px rgba(0,0,0,.34);box-shadow:0 8px 18px rgba(0,0,0,.22),inset 0 1px 0 rgba(255,255,255,.34);backdrop-filter:blur(10px) saturate(150%);-webkit-backdrop-filter:blur(10px) saturate(150%)}
 .resolution-badge.is-4k{background:linear-gradient(135deg,#fff1a8,#d69a17 56%,#8a5a05)}
   .resolution-badge.is-2k{background:linear-gradient(135deg,#ffd7eb,#f08abb 58%,#be5775);color:#fff}
 .resolution-badge.is-1k{background:linear-gradient(135deg,#f6c799,#b87333 58%,#704214)}
 .watch-hero .resolution-badge{right:1rem;top:1rem}
</style>
<style>
:root{--watch-bg:#0b0f14;--watch-panel:#121916;--watch-panel-2:#172119;--watch-border:#202c25;--watch-text:#f4f7f5;--watch-muted:#89988e;--watch-accent:#9be15d;--watch-shadow:rgba(0,0,0,.32)}
 body.watch-page{background:var(--watch-bg)!important;color:var(--watch-text)!important;transition:background .25s,color .25s}.watch-nav{border-color:var(--watch-border)}
 .watch-card{background:var(--watch-panel);border-color:var(--watch-border);box-shadow:0 8px 22px transparent}.watch-card:hover{background:var(--watch-panel-2);box-shadow:0 14px 30px var(--watch-shadow)}
.watch-card-cover{isolation:isolate}.watch-card-cover:after{content:'▶';position:absolute;z-index:4;left:50%;top:50%;width:3.2rem;height:3.2rem;display:flex;align-items:center;justify-content:center;transform:translate(-50%,-50%) scale(.72);border:1px solid rgba(255,255,255,.72);border-radius:50%;background:rgba(10,15,12,.72);color:#fff;font-size:1.2rem;padding-left:.12rem;opacity:0;transition:opacity .18s ease,transform .22s cubic-bezier(.2,.8,.2,1);pointer-events:none}.watch-card:hover .watch-card-cover:after,.watch-card:focus-visible .watch-card-cover:after{opacity:1;transform:translate(-50%,-50%) scale(1)}.watch-card-cover img{transition:transform .35s cubic-bezier(.2,.8,.2,1),filter .35s}.watch-card:hover .watch-card-cover>img{transform:scale(1.055);filter:saturate(1.06)}
.resolution-badge img{display:block;width:2.55rem;height:1.55rem;object-fit:contain;filter:drop-shadow(0 2px 3px rgba(0,0,0,.35))}.resolution-badge:has(img){padding:0;background:transparent;border:0;backdrop-filter:none}
.resolution-badge.is-bluray{background:linear-gradient(135deg,#91d8ff,#2476c7 58%,#17437a);color:#fff}
.watch-card-added{margin-top:.3rem;color:var(--watch-muted);font-size:.68rem}.watch-load-more{display:block;margin:1rem auto 0;border:1px solid var(--watch-border);background:var(--watch-panel);color:var(--watch-text);border-radius:999px;padding:.6rem 1rem;cursor:pointer}.watch-load-more:hover{border-color:var(--watch-accent)}
 @media(max-width:720px){.watch-card-cover:after{opacity:.92;transform:translate(-50%,-50%) scale(.78);width:2.55rem;height:2.55rem}}
</style>
<style>
:root{--watch-bg:#f3f6f2;--watch-panel:#ffffff;--watch-panel-2:#edf5ea;--watch-border:#dbe4d8;--watch-text:#172019;--watch-muted:#607064;--watch-accent:#5f9d37;--watch-shadow:rgba(40,70,45,.12)}
body.watch-page{background:linear-gradient(180deg,#f8fbf7 0%,#f3f6f2 100%)!important;color:#172019!important}
.watch-page a{color:inherit}
.watch-loading{background:#f3f6f2;color:#7c8b81}
.watch-nav{position:sticky;top:0;z-index:20;min-height:76px;padding:1rem 0;border-bottom:1px solid rgba(219,228,216,.9);background:rgba(243,246,242,.86);backdrop-filter:blur(18px) saturate(140%);-webkit-backdrop-filter:blur(18px) saturate(140%);align-items:center;flex-wrap:wrap}
.watch-brand strong{color:#172019}
.watch-brand span{color:#5f9d37}
 .watch-nav-links{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}
 .watch-nav nav.watch-nav-links a{padding:.58rem .94rem;color:#607064;border:1px solid transparent;border-radius:999px;text-decoration:none}
 .watch-nav nav.watch-nav-links a:hover,.watch-nav nav.watch-nav-links a.active{color:#172019;background:#fff;border-color:#dbe4d8;box-shadow:0 8px 22px rgba(40,70,45,.06)}
 .watch-nav-links a{padding:.58rem .94rem;border:1px solid transparent;border-radius:999px;text-decoration:none;color:#607064;transition:background .18s ease,border-color .18s ease,color .18s ease,transform .18s ease}
.watch-nav-links a:hover,.watch-nav-links a.active{color:#172019;background:#fff;border-color:#dbe4d8;box-shadow:0 8px 22px rgba(40,70,45,.06)}
.watch-home-actions{margin-left:auto;display:flex;align-items:center;gap:.7rem;justify-content:flex-end}
.watch-search-wrap{width:min(360px,36vw);padding:.62rem .82rem;border:1px solid #dbe4d8;border-radius:999px;background:#fff;color:#7d8a80;box-shadow:0 8px 22px rgba(40,70,45,.06)}
.watch-search{color:#172019}
.watch-hero{min-height:400px;border:1px solid rgba(219,228,216,.92);border-radius:28px;background-color:#fff;box-shadow:0 22px 60px rgba(40,70,45,.12)}
.watch-hero-shade{background:linear-gradient(90deg,rgba(248,251,247,.94),rgba(248,251,247,.82) 42%,rgba(248,251,247,.34))}
.watch-hero-content{max-width:640px}
.watch-eyebrow{color:#5f9d37}
.watch-hero h1{color:#172019}
.watch-hero p,.watch-hero-meta{color:#52646c}
.watch-primary-action{background:linear-gradient(135deg,#5f9d37,#77b14b);color:#fff!important;box-shadow:0 14px 28px rgba(95,157,55,.22)}
.watch-section-head h2{color:#172019}
.watch-section-head span{color:#7d8a80}
.watch-section-head h2:before{background:#5f9d37}
.watch-grid{gap:1rem}
.watch-card{background:#fff;border:1px solid #dde4db;border-radius:24px;box-shadow:0 12px 30px rgba(40,70,45,.06)}
.watch-card:hover{transform:translateY(-4px);border-color:#b9d5aa;background:#fff;box-shadow:0 18px 38px rgba(40,70,45,.12)}
.watch-card-cover{background:#edf4ea}
.watch-card-badge{background:rgba(23,32,25,.82);border-radius:999px}
.watch-card-title{color:#172019}
.watch-card-meta{color:#6b786e}
.watch-progress{background:#dbe4d8}
.watch-progress i{background:linear-gradient(90deg,#5f9d37,#7ac04f)}
.watch-empty{background:#fff;border-color:#dde4db;color:#607064;box-shadow:0 10px 28px rgba(40,70,45,.06)}
.watch-load-more{background:#fff;border-color:#dbe4d8;color:#172019;box-shadow:0 10px 24px rgba(40,70,45,.06)}
.watch-load-more:hover{border-color:#5f9d37;background:#f6fbf4}
.watch-home-note{color:#6c7a71}
.resolution-badge.is-4k{padding:0;min-width:0;height:auto;background:transparent;border:0;box-shadow:none;backdrop-filter:none;-webkit-backdrop-filter:none}
.resolution-badge.is-4k img{display:block;width:2.6rem;height:1.62rem;object-fit:contain;filter:drop-shadow(0 2px 3px rgba(0,0,0,.25))}
.resolution-badge.is-2k{background:linear-gradient(135deg,#ffd7eb,#f08abb 58%,#be5775);color:#fff}
.resolution-badge.is-bluray{background:linear-gradient(135deg,#91d8ff,#2476c7 58%,#17437a);color:#fff}
@media(max-width:720px){
  .watch-nav{padding:.75rem 0}
  .watch-nav-links{width:100%;order:3}
  .watch-home-actions{width:100%;order:2}
  .watch-search-wrap{width:100%}
  .watch-hero{min-height:340px;border-radius:22px}
  .watch-hero-content{padding:2rem 1.3rem}
  .watch-grid,.watch-grid-continue{grid-template-columns:repeat(2,minmax(0,1fr))}
  .watch-card-body{padding:.58rem .62rem}
  .watch-card-title{font-size:.9rem}
 .watch-card-meta{font-size:.72rem}
 }
 body.watch-page{background:
   radial-gradient(circle at 9% -8%,rgba(255,205,225,.82),transparent 34%),
   radial-gradient(circle at 96% 10%,rgba(255,229,239,.74),transparent 32%),
   linear-gradient(180deg,#fff7fb 0%,#fffdfd 48%,#fff5fa 100%)!important;
   background-attachment:fixed!important;
 }
 .watch-loading{background:rgba(255,247,251,.96)}
 .watch-nav{background:rgba(255,248,251,.86);border-bottom-color:rgba(241,214,224,.92);box-shadow:0 10px 28px rgba(176,91,122,.06)}
 .watch-search-wrap{border-color:#efd9e2;box-shadow:0 8px 22px rgba(176,91,122,.07)}
 .watch-nav nav.watch-nav-links a:hover,.watch-nav nav.watch-nav-links a.active{border-color:#efd9e2;box-shadow:0 8px 22px rgba(176,91,122,.09)}
</style>
<style>
/* Hallmark · pre-emit critique: P5 H4 E4 S4 R4 V4 */
:root{
  --background-base:#FFFAFB;
  --background-pink:rgba(255,183,197,.15);
  --background-blue:rgba(135,206,235,.12);
  --background-green:rgba(144,238,144,.10);
  --background-cyan:rgba(168,216,234,.08);
  --cherry-canvas:var(--background-base);
  --cherry-canvas-secondary:#fff5f7;
  --cherry-surface:#ffffff;
  --cherry-text:#2d1b2e;
  --cherry-text-secondary:#6b5b6d;
  --cherry-text-muted:#9b8b9d;
  --cherry-pink-100:#ffe0e6;
  --cherry-pink-300:#ffb3c6;
  --cherry-pink-400:#ff9bb5;
  --cherry-pink-500:#ff85a2;
  --cherry-blue-200:#a8d8ea;
  --cherry-blue-300:#87ceeb;
  --cherry-green-300:#90ee90;
  --cherry-border:rgba(255,183,197,.28);
  --cherry-border-focus:rgba(255,133,162,.52);
  --cherry-shadow:rgba(255,133,162,.12);
  --cherry-shadow-hover:rgba(255,133,162,.2);
  --cherry-brand-gradient:linear-gradient(135deg,var(--cherry-pink-500),var(--cherry-blue-300),var(--cherry-green-300));
  --cherry-active-gradient:linear-gradient(135deg,rgba(255,183,197,.28),rgba(135,206,235,.2));
  --cherry-atmosphere:radial-gradient(ellipse at 10% 20%,var(--background-pink) 0%,transparent 50%),radial-gradient(ellipse at 85% 15%,var(--background-blue) 0%,transparent 50%),radial-gradient(ellipse at 50% 80%,var(--background-green) 0%,transparent 50%),radial-gradient(ellipse at 30% 60%,var(--background-cyan) 0%,transparent 40%);
}
html,body{min-height:100%;overflow-x:clip}
body.watch-page{position:relative;isolation:isolate;min-height:100vh;background:var(--cherry-canvas)!important;color:var(--cherry-text)!important;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Hiragino Sans GB","Microsoft YaHei","Helvetica Neue",Helvetica,Arial,sans-serif}
body.watch-page::before{content:"";position:fixed;inset:0;z-index:0;pointer-events:none;background:var(--cherry-atmosphere)}
body.watch-page .watch-home{position:relative;z-index:1;max-width:1400px;padding:24px 24px 60px}
body.watch-page .watch-nav{min-height:64px;height:auto;padding:0;border-bottom:1px solid var(--cherry-border);background:rgba(255,250,251,.85);box-shadow:0 4px 16px var(--cherry-shadow);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px)}
 body.watch-page .watch-brand{width:56px;height:56px;flex:0 0 56px;align-items:center;justify-content:center}
 body.watch-page .watch-brand-logo{display:block;width:56px;height:56px;object-fit:contain;background:transparent;border:0}
body.watch-page .watch-brand span{color:var(--cherry-pink-500);font-weight:600}
body.watch-page .watch-nav nav.watch-nav-links a{padding:8px 18px;color:var(--cherry-text-secondary);font-weight:500;border:1px solid transparent;border-radius:20px;transition:background .2s ease,border-color .2s ease,color .2s ease,transform .2s ease}
body.watch-page .watch-nav nav.watch-nav-links a:hover{color:var(--cherry-pink-600,var(--cherry-pink-500));background:rgba(255,183,197,.12);border-color:var(--cherry-border)}
body.watch-page .watch-nav nav.watch-nav-links a.active{color:var(--cherry-pink-500);font-weight:600;background:var(--cherry-active-gradient);border-color:var(--cherry-border)}
body.watch-page .watch-search-wrap{min-width:220px;width:min(360px,36vw);padding:8px 16px;color:var(--cherry-text-muted);background:rgba(255,183,197,.08);border:1px solid var(--cherry-border);border-radius:20px;box-shadow:none}
body.watch-page .watch-search{color:var(--cherry-text)}
body.watch-page .watch-search::placeholder{color:var(--cherry-text-muted)}
body.watch-page .watch-search-wrap:focus-within{border-color:var(--cherry-border-focus);box-shadow:0 0 0 3px rgba(255,133,162,.1)}
body.watch-page .watch-hero{min-height:420px;margin:24px 0 40px;border:0;border-radius:20px;box-shadow:0 8px 32px var(--cherry-shadow-hover)}
body.watch-page .watch-hero-shade{background:linear-gradient(to top,rgba(0,0,0,.82),rgba(0,0,0,.34) 58%,rgba(0,0,0,.08))}
body.watch-page .watch-hero-content{max-width:600px;padding:48px}
body.watch-page .watch-eyebrow{color:rgba(255,255,255,.78)}
body.watch-page .watch-hero h1{color:#fff;font-weight:800;letter-spacing:normal}
body.watch-page .watch-hero p{color:rgba(255,255,255,.84);line-height:1.6}
body.watch-page .watch-hero-meta{color:rgba(255,255,255,.78)}
body.watch-page .watch-primary-action{padding:10px 24px;border-radius:10px;background:linear-gradient(135deg,var(--cherry-pink-500),var(--cherry-pink-400));box-shadow:0 2px 10px rgba(255,133,162,.3);transition:transform .2s ease,box-shadow .2s ease}
body.watch-page .watch-primary-action:hover{transform:scale(1.03);box-shadow:0 4px 16px rgba(255,133,162,.4)}
body.watch-page .watch-section{margin:36px 0}
body.watch-page .watch-section-head{margin-bottom:10px;align-items:center}
 body.watch-page .watch-section-head h2{font-size:1.1rem;font-weight:700;color:var(--cherry-text)}
 body.watch-page .watch-section-head h2::before{width:4px;height:24px;margin-right:10px;vertical-align:-6px;border-radius:2px;background:var(--cherry-brand-gradient)}
 body.watch-page .watch-section-head span{color:var(--cherry-text-muted)}
 body.watch-page .watch-more-link{display:inline-flex;align-items:center;gap:6px;margin-left:auto;padding:6px 12px;border:1px solid var(--cherry-border);border-radius:999px;color:var(--cherry-pink-500);font-size:.82rem;font-weight:600;text-decoration:none;white-space:nowrap;transition:background .2s ease,border-color .2s ease,box-shadow .2s ease,transform .2s ease}
 body.watch-page .watch-more-link::after{content:'›';font-size:1.05rem;line-height:.8;transition:transform .2s ease}
 body.watch-page .watch-more-link:hover,body.watch-page .watch-more-link:focus-visible{background:rgba(255,183,197,.16);border-color:var(--cherry-border-focus);box-shadow:0 6px 16px var(--cherry-shadow);transform:translateY(-1px)}
 body.watch-page .watch-more-link:hover::after,body.watch-page .watch-more-link:focus-visible::after{transform:translateX(2px)}
body.watch-page .watch-grid{grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:20px}
body.watch-page .watch-card{border:1px solid var(--cherry-border);border-radius:12px;background:var(--cherry-surface);box-shadow:0 2px 12px var(--cherry-shadow);transition:transform .35s cubic-bezier(.4,0,.2,1),box-shadow .35s,border-color .35s}
body.watch-page .watch-card:hover{transform:translateY(-6px);border-color:rgba(255,133,162,.42);background:var(--cherry-surface);box-shadow:0 12px 32px var(--cherry-shadow-hover)}
body.watch-page .watch-card-cover{background:linear-gradient(135deg,var(--cherry-pink-100),var(--cherry-blue-200));border-radius:12px 12px 0 0}
body.watch-page .watch-card-cover img{transition:transform .5s ease,filter .5s ease}
body.watch-page .watch-card:hover .watch-card-cover>img{transform:scale(1.08);filter:saturate(1.06)}
body.watch-page .watch-card-cover::after{width:40px;height:40px;border:0;background:rgba(255,255,255,.92);color:var(--cherry-pink-500);font-size:1rem;box-shadow:0 4px 16px rgba(255,133,162,.3)}
body.watch-page .watch-card-badge{left:8px;bottom:8px;padding:5px 10px;border-radius:16px;background:rgba(45,27,46,.68);font-size:.75rem}
body.watch-page .watch-card-body{padding:12px 14px}
body.watch-page .watch-card-title{font-size:.95rem;font-weight:600;color:var(--cherry-text)}
body.watch-page .watch-card-meta{margin-top:6px;color:var(--cherry-text-secondary);font-size:.85rem}
body.watch-page .watch-progress{height:3px;margin-top:10px;background:var(--cherry-pink-100)}
body.watch-page .watch-progress i{background:linear-gradient(90deg,var(--cherry-pink-500),var(--cherry-blue-300))}
body.watch-page .watch-empty{background:var(--cherry-surface);border:1px dashed var(--cherry-border);border-radius:12px;color:var(--cherry-text-secondary);box-shadow:0 2px 12px var(--cherry-shadow)}
body.watch-page .watch-load-more{padding:10px 24px;border:0;border-radius:10px;background:linear-gradient(135deg,var(--cherry-pink-500),var(--cherry-pink-400));color:#fff;box-shadow:0 2px 10px rgba(255,133,162,.3)}
body.watch-page .watch-load-more:hover{border:0;background:linear-gradient(135deg,var(--cherry-pink-500),var(--cherry-pink-400));box-shadow:0 4px 16px rgba(255,133,162,.4);transform:scale(1.03)}
body.watch-page .resolution-badge.is-4k{padding:0;min-width:0;height:auto;background:transparent;border:0;box-shadow:none;backdrop-filter:none}
 body.watch-page .resolution-badge.is-4k img{display:block;width:2.6rem;height:1.62rem;object-fit:contain;filter:drop-shadow(0 2px 3px rgba(255,133,162,.24))}
@media(max-width:720px){
  body.watch-page .watch-home{padding:16px 16px 48px}
  body.watch-page .watch-nav{min-height:64px}
  body.watch-page .watch-nav-links{width:auto;order:2}
  body.watch-page .watch-home-actions{width:100%;order:3}
  body.watch-page .watch-search-wrap{width:100%;min-width:0}
  body.watch-page .watch-hero{min-height:360px;margin:20px 0 32px}
  body.watch-page .watch-hero-content{padding:24px}
  body.watch-page .watch-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
  body.watch-page .watch-card-body{padding:10px 12px}
}
 @media(max-width:520px){
    body.watch-page .watch-section-head{align-items:flex-start;flex-direction:column;gap:6px}
    body.watch-page .watch-section-head h2{font-size:1rem}
    body.watch-page .watch-more-link{margin-left:0;margin-top:2px}
  }
 body.watch-page .watch-grid.watch-grid-last-watched{grid-template-columns:repeat(8,minmax(0,1fr));gap:16px}
 @media(max-width:1100px){body.watch-page .watch-grid.watch-grid-last-watched{grid-template-columns:repeat(4,minmax(0,1fr))}}
 @media(max-width:520px){body.watch-page .watch-grid.watch-grid-last-watched{grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}}
</style>
</head>
<body class="watch-page">
<div id="watchLoading" class="watch-loading" aria-hidden="true"><span class="watch-spinner"></span><span>正在加载影视库</span></div>
<main class="watch-home">
  <header class="watch-nav"><a class="watch-brand" href="/watch.php"><img class="watch-brand-logo" src="/assets/images/withu-logo.png" alt="withU"></a><nav class="watch-nav-links"><a class="<?php echo $isBrowsePage ? '' : 'active'; ?>" href="/watch.php">首页</a><a href="/watch_history.php">历史</a><a href="/">情侣空间</a></nav><div class="watch-home-actions"><label class="watch-search-wrap"><span aria-hidden="true">⌕</span><input id="watchSearch" class="watch-search" placeholder="<?php echo e($isBrowsePage ? '在' . $pageTitle . '中搜索片名、演员或集标题' : '模糊搜索片名、演员、年份或集标题'); ?>"></label></div></header>
  <?php if ($mediaError !== ''): ?><div class="watch-empty" style="margin-top:1rem"><?php echo e($mediaError); ?></div><?php endif; ?>

  <?php if (!$isBrowsePage && $featured && $featuredItem): ?>
  <section class="watch-hero" style="--hero-image:url('/api/media_backdrop.php?id=<?php echo (int)$featuredItem['id']; ?>')"><img class="watch-hero-art" src="/api/media_backdrop.php?id=<?php echo (int)$featuredItem['id']; ?>" alt=""><?php echo withu_media_quality_badge_html($featuredItem); ?><div class="watch-hero-shade"></div><div class="watch-hero-content"><span class="watch-eyebrow">WITHU WATCH · 今日推荐</span><h1><?php echo e($featured['name']); ?></h1><p><?php echo e(mb_substr((string)($featuredItem['summary'] ?? ''), 0, 100)); ?></p><div class="watch-hero-meta"><?php echo e($featuredItem['rating'] ? '评分 ' . $featuredItem['rating'] : ''); ?><?php echo $featuredItem['rating'] ? ' · ' : ''; ?><?php echo e($featuredItem['resolution'] ?: ''); ?><?php echo count($featured['items']) > 1 ? ' · ' . count($featured['items']) . ' 集' : ''; ?></div><a class="watch-primary-action" href="/watch_play.php?media_id=<?php echo (int)$featuredItem['id']; ?>"><span aria-hidden="true">▶</span> 立即播放</a></div></section>
  <?php endif; ?>

  <?php if (!$isBrowsePage): ?>
  <section id="watchLastWatchedSection" class="watch-section"><div class="watch-section-head"><h2>上次观看</h2><span>按最新观看记录显示 · <?php echo count($recent); ?> 部</span><a class="watch-more-link" href="/watch_history.php">显示更多</a></div><div class="watch-grid watch-grid-last-watched">
    <?php foreach ($recentGroups as $item): $duration = max(0, (int)($item['duration_ms'] ?? 0)); $position = max(0, (int)($item['last_position_ms'] ?? 0)); $progress = $duration > 0 ? min(100, round($position / $duration * 100)) : 0; ?>
     <a class="watch-card" data-watch-title="<?php echo e(($item['series_name'] ?? '') . ' ' . $item['file_name']); ?>" href="/watch_play.php?media_id=<?php echo (int)$item['id']; ?>"><div class="watch-card-cover"><img loading="lazy" src="/api/media_cover.php?id=<?php echo (int)$item['id']; ?>" alt=""><?php echo withu_media_quality_badge_html($item); ?><span class="watch-card-badge"><?php echo e($item['episode_number'] ? '第 ' . $item['episode_number'] . ' 集' : '继续观看'); ?></span></div><div class="watch-card-body"><div class="watch-card-title"><?php echo e($item['series_name']); ?></div><div class="watch-card-meta"><?php echo e($item['episode_number'] ? '第 ' . $item['episode_number'] . ' 集' : $item['file_name']); ?></div><div class="watch-progress"><i style="width:<?php echo $progress; ?>%"></i></div></div></a>
    <?php endforeach; ?>
  </div><?php if (empty($recentGroups)): ?><div class="watch-empty">还没有达到记录条件的观看历史。</div><?php endif; ?></section>
  <?php endif; ?>

  <?php if (!$isBrowsePage): ?><div id="watchCategorySections">
  <?php foreach ($categoryOrder as $typeId): $typeName = $typeNames[$typeId]; $category = array_slice($categoryGroups[$typeId] ?? [], 0, 14); if (!$category && $typeId !== 1) continue; ?>
  <section class="watch-section watch-category-section" data-watch-type="<?php echo $typeId; ?>"><div class="watch-section-head"><h2><?php echo e($typeName); ?></h2><span><?php echo count($categoryGroups[$typeId] ?? []); ?> 部</span><a class="watch-more-link" href="/watch.php?type=<?php echo $typeId; ?>">显示更多</a></div><div class="watch-grid">
    <?php foreach ($category as $group): $item = $group['items'][0]; $episodeCount = count($group['items']); ?><a class="watch-card" data-watch-title="<?php echo e($group['name']); ?>" href="/watch_play.php?media_id=<?php echo (int)$item['id']; ?>"><div class="watch-card-cover"><img loading="lazy" src="/api/media_cover.php?id=<?php echo (int)$item['id']; ?>" alt=""><?php echo withu_media_quality_badge_html($item); ?><span class="watch-card-badge"><?php echo $episodeCount > 1 ? $episodeCount . ' 集' : '播放'; ?></span></div><div class="watch-card-body"><div class="watch-card-title"><?php echo e($group['name']); ?></div><div class="watch-card-meta"><?php echo e($item['rating'] ? '评分 ' . $item['rating'] . ' · ' : ''); ?><?php echo e($item['resolution'] ?: '分辨率未知'); ?></div></div></a><?php endforeach; ?>
  </div><?php if (!$category): ?><div class="watch-empty">暂无已识别的<?php echo e($typeName); ?>资源。</div><?php endif; ?></section>
  <?php endforeach; ?>
  </div><?php endif; ?>

  <section id="watchAllSection" class="watch-section"><div class="watch-section-head"><h2 id="watchGridTitle"><?php echo e($isBrowsePage ? $pageTitle : '全部影片'); ?></h2><span id="watchGridCount"><?php echo count($groups); ?> 部</span><?php if ($isBrowsePage): ?><a class="watch-more-link" href="/watch.php">返回资源库</a><?php else: ?><a class="watch-more-link" href="/watch.php?all=1">显示更多</a><?php endif; ?></div><div id="watchGrid" class="watch-grid">
    <?php foreach (($isBrowsePage ? $groups : $allGroups) as $group): $first = $group['items'][0]; $episodeCount = count($group['items']); ?>
    <a class="watch-card" data-watch-title="<?php echo e($group['name']); ?>" href="/watch_play.php?media_id=<?php echo (int)$first['id']; ?>"><div class="watch-card-cover"><img loading="lazy" src="/api/media_cover.php?id=<?php echo (int)$first['id']; ?>" alt=""><?php echo withu_media_quality_badge_html($first); ?><span class="watch-card-badge"><?php echo $episodeCount > 1 ? $episodeCount . ' 集' : '播放'; ?></span></div><div class="watch-card-body"><div class="watch-card-title"><?php echo e($group['name']); ?></div><div class="watch-card-meta"><?php echo e($first['rating'] ? '评分 ' . $first['rating'] . ' · ' : ''); ?><?php echo e($first['resolution'] ?: '分辨率未知'); ?></div></div></a>
    <?php endforeach; ?>
  </div><div id="watchSearchState" class="watch-home-note" hidden></div><?php if (empty($groups)): ?><div class="watch-empty"><?php echo $isBrowsePage && $isCategoryPage ? '该分类暂无已识别影视资源。' : ($isAllPage ? '暂无已识别影视资源。' : '媒体库暂无影片，请在后台扫描 OpenList。'); ?></div><?php endif; ?></section>
  <button id="watchLoadMore" class="watch-load-more" type="button" hidden>加载更多</button>
</main>
<script>
(function(){
  var search=document.getElementById('watchSearch'), grid=document.getElementById('watchGrid'), title=document.getElementById('watchGridTitle'), count=document.getElementById('watchGridCount'), state=document.getElementById('watchSearchState'), more=document.getElementById('watchLoadMore'), recentSection=document.getElementById('watchLastWatchedSection'), categorySections=document.getElementById('watchCategorySections'), categoryTypeId=<?php echo (int)$requestedTypeId; ?>, categoryTitle=<?php echo json_encode($pageTitle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>, browseTitle=<?php echo json_encode($isBrowsePage ? $pageTitle : '全部影片', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>, controller=null, searchPage=1, searching=false;
  function esc(value){return String(value==null?'':value).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
  function badge(item){var text=String((item&&item.quality_text)||((item&&item.resolution)||'')).toUpperCase();if(/4K|2160P|UHD/.test(text))return '<span class="resolution-badge is-4k"><img src="/assets/images/4k-badge.png" alt="4K"></span>';if(/2K|1440P|QHD/.test(text))return '<span class="resolution-badge is-2k">2K</span>';if(/蓝光|BLU/.test(text))return '<span class="resolution-badge is-bluray">蓝光</span>';return '';}
  function card(item){var name=item.group_name||item.series_name||item.file_name||'未命名影视';var countText=(item.episode_count||1)>1?item.episode_count+' 集':'播放';return '<a class="watch-card" data-watch-title="'+esc(name)+'" href="/watch_play.php?media_id='+Number(item.id)+'"><div class="watch-card-cover"><img loading="lazy" src="/api/media_cover.php?id='+Number(item.id)+'" alt="">'+badge(item)+'<span class="watch-card-badge">'+countText+'</span></div><div class="watch-card-body"><div class="watch-card-title">'+esc(name)+'</div><div class="watch-card-meta">'+(item.rating?'评分 '+esc(item.rating)+' · ':'')+esc(item.resolution||'分辨率未知')+'</div></div></a>';}
  function bindImages(){}
  function setSearchView(active){searching=active; if(recentSection)recentSection.hidden=active; if(categorySections)categorySections.hidden=active; if(more)more.hidden=!active; if(title)title.textContent=active?(categoryTypeId?categoryTitle+'搜索结果':'搜索结果'):browseTitle;}
  async function loadSearch(append){var q=search.value.trim();if(!q){if(searching)location.reload();return;}if(controller)controller.abort();controller=new AbortController();if(!append)searchPage=1;var url='/api/media.php?action=library&q='+encodeURIComponent(q)+'&page='+searchPage+'&limit=24';if(categoryTypeId)url+='&type_id='+categoryTypeId;if(!append){grid.innerHTML='<div class="watch-empty">正在搜索…</div>';setSearchView(true);}try{var response=await fetch(url,{credentials:'same-origin',signal:controller.signal});var data=await response.json();if(!data.success)throw new Error(data.message||'搜索失败');if(!append)grid.innerHTML='';(data.items||[]).forEach(function(item){grid.insertAdjacentHTML('beforeend',card(item));});if(count)count.textContent=(data.items||[]).length+' 条';if(state){state.hidden=false;state.textContent=(data.items||[]).length?'支持片名、别名、演员、年份和集标题的模糊匹配':'没有找到匹配的影视资源';}if(more){more.hidden=!data.has_more;more.textContent='加载更多';}bindImages();}catch(error){if(error.name==='AbortError')return;grid.innerHTML='<div class="watch-empty">'+esc(error.message||'搜索失败，请重试')+'</div>';if(more)more.hidden=true;}}
  var timer=null;if(search)search.addEventListener('input',function(){clearTimeout(timer);timer=setTimeout(function(){loadSearch(false);},280);});if(more)more.addEventListener('click',function(){searchPage++;loadSearch(true);});
  if(grid)grid.addEventListener('error',function(event){var image=event.target;if(!image||image.tagName!=='IMG'||image.dataset.fallback)return;image.dataset.fallback='1';image.src='/assets/images/Coverloaderror.jpg';},true);
  document.querySelectorAll('.watch-card-cover img').forEach(function(image){image.decoding='async';});
  document.addEventListener('DOMContentLoaded',function(){requestAnimationFrame(function(){var loading=document.getElementById('watchLoading');if(loading)loading.classList.add('is-hidden');});});
})();
</script>
</body>
</html>
