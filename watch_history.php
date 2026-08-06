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
    "SELECT * FROM watch_history WHERE watch_duration_ms >= :min_ms ORDER BY updated_at DESC, id DESC LIMIT 200",
    ['min_ms' => $historyMinMs]
);
$mediaMap = withu_media_fetch_many(array_map(static function ($row) {
    return (int)$row['media_id'];
}, $historyRows));
$rows = [];
foreach ($historyRows as $row) {
    $media = $mediaMap[(int)$row['media_id']] ?? [];
    $rows[] = array_merge($media, $row, [
        'file_name' => $media['file_name'] ?? ('影片 #' . (int)$row['media_id'])
    ]);
}
$formatDuration = static function (int $ms): string {
    $ms = max(0, $ms);
    $seconds = (int)floor($ms / 1000);
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $seconds = $seconds % 60;
    return $hours > 0
        ? sprintf('%d:%02d:%02d', $hours, $minutes, $seconds)
        : sprintf('%02d:%02d', $minutes, $seconds);
};
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
:root{--history-bg:#f3f6f2;--history-panel:#fff;--history-border:#dbe4d8;--history-text:#172019;--history-muted:#607064;--history-accent:#5f9d37;--history-shadow:rgba(40,70,45,.12)}
body.watch-history-page{margin:0;background:linear-gradient(180deg,#f8fbf7 0%,#f3f6f2 100%);color:var(--history-text)}
body.watch-history-page a{color:inherit}
.watch-history-shell{max-width:1180px;margin:0 auto;padding:1.25rem 1.25rem 3rem}
.watch-history-header{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;padding:1rem 0 1.1rem;border-bottom:1px solid rgba(219,228,216,.9)}
.watch-history-kicker{margin:0 0 .3rem;font-size:.8rem;letter-spacing:.16em;text-transform:uppercase;color:var(--history-accent)}
.watch-history-header h1{margin:0;font-size:clamp(1.6rem,3vw,2.35rem);line-height:1.1}
.watch-history-header p{margin:.45rem 0 0;color:var(--history-muted)}
.watch-history-home{display:inline-flex;align-items:center;gap:.45rem;padding:.68rem 1rem;border:1px solid var(--history-border);border-radius:999px;background:#fff;text-decoration:none;box-shadow:0 10px 22px rgba(40,70,45,.06);transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease}
.watch-history-home:hover{transform:translateY(-1px);border-color:var(--history-accent);box-shadow:0 16px 30px rgba(40,70,45,.12)}
.watch-history-note{margin:1rem 0 1.15rem;padding:.85rem 1rem;border:1px solid rgba(95,157,55,.2);border-radius:16px;background:#f8fcf5;color:var(--history-muted)}
.watch-history-list{display:grid;gap:.95rem}
.watch-history-item{display:grid;grid-template-columns:160px minmax(0,1fr);gap:1rem;align-items:stretch;padding:.9rem;border:1px solid var(--history-border);border-radius:24px;background:var(--history-panel);text-decoration:none;box-shadow:0 12px 30px rgba(40,70,45,.06);transition:transform .18s ease,border-color .18s ease,box-shadow .18s ease}
.watch-history-item:hover{transform:translateY(-3px);border-color:#b9d5aa;box-shadow:0 18px 38px rgba(40,70,45,.12)}
.watch-history-cover{position:relative;overflow:hidden;min-height:0;aspect-ratio:2/3;border-radius:18px;background:#edf4ea}
.watch-history-cover img{width:100%;height:100%;object-fit:cover;display:block}
.watch-history-badge{position:absolute;left:.55rem;bottom:.55rem}
.watch-history-content{display:flex;flex-direction:column;justify-content:space-between;min-width:0;padding:.2rem 0 .1rem}
.watch-history-title{font-size:1.1rem;font-weight:800;line-height:1.35;word-break:break-word}
.watch-history-subtitle{margin:.4rem 0 0;color:var(--history-muted);font-size:.92rem;line-height:1.55;word-break:break-word}
.watch-history-meta{display:flex;flex-wrap:wrap;gap:.45rem;margin-top:.8rem}
.watch-history-meta span{padding:.34rem .65rem;border:1px solid var(--history-border);border-radius:999px;background:#f8fbf8;color:#53646c;font-size:.78rem}
.watch-history-progress{margin-top:1rem;height:4px;border-radius:999px;background:#dbe4d8;overflow:hidden}
.watch-history-progress i{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,var(--history-accent),#7bc24f)}
.watch-history-empty{padding:1.35rem 1.1rem;border:1px dashed var(--history-border);border-radius:18px;background:#fff;color:var(--history-muted)}
.resolution-badge{position:absolute;right:.55rem;top:.55rem;z-index:3;display:inline-flex;align-items:center;justify-content:center;min-width:2.05rem;height:1.25rem;padding:0 .42rem;border:1px solid rgba(255,255,255,.54);border-radius:999px;color:#fff;font-size:.68rem;font-weight:900;line-height:1;letter-spacing:.02em;text-shadow:0 1px 2px rgba(0,0,0,.34);box-shadow:0 8px 18px rgba(0,0,0,.22),inset 0 1px 0 rgba(255,255,255,.34);backdrop-filter:blur(10px) saturate(150%);-webkit-backdrop-filter:blur(10px) saturate(150%)}
.resolution-badge.is-4k{padding:0;min-width:0;height:auto;background:transparent;border:0;box-shadow:none;backdrop-filter:none;-webkit-backdrop-filter:none}
.resolution-badge.is-4k img{display:block;width:2.58rem;height:1.6rem;object-fit:contain;filter:drop-shadow(0 2px 3px rgba(0,0,0,.24))}
.resolution-badge.is-2k{background:linear-gradient(135deg,#ffd7eb,#f08abb 58%,#be5775);color:#fff}
.resolution-badge.is-bluray{background:linear-gradient(135deg,#91d8ff,#2476c7 58%,#17437a);color:#fff}
@media(max-width:760px){
 .watch-history-shell{padding:1rem .8rem 2.4rem}
 .watch-history-header{align-items:flex-start;flex-direction:column}
 .watch-history-item{grid-template-columns:110px minmax(0,1fr);gap:.82rem;padding:.72rem;border-radius:20px}
 .watch-history-title{font-size:1rem}
 .watch-history-subtitle{font-size:.86rem}
 .watch-history-meta span{font-size:.72rem}
}
@media(max-width:420px){
 .watch-history-item{grid-template-columns:92px minmax(0,1fr)}
 .watch-history-meta{gap:.35rem}
 .watch-history-meta span{padding:.28rem .5rem}
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
    按最新观看时间排序，封面会优先使用本地化缓存；如果本地缓存失效，会继续回退到资源库里的可用封面。
  </section>

  <?php if (empty($rows)): ?>
    <div class="watch-history-empty">暂无有效观影记录。看满一段时间后，这里才会显示对应影片。</div>
  <?php else: ?>
    <div class="watch-history-list">
      <?php foreach ($rows as $row): ?>
        <?php
          $durationMs = (int)($row['watch_duration_ms'] ?? 0);
          $soloMs = (int)($row['solo_duration_ms'] ?? 0);
          $togetherMs = (int)($row['together_duration_ms'] ?? 0);
          $lastPositionMs = (int)($row['last_position_ms'] ?? 0);
          $episodeText = !empty($row['episode_number']) ? ('第 ' . (int)$row['episode_number'] . ' 集') : (string)($row['file_name'] ?? '');
          $posterAlt = (string)($row['series_name'] ?? $row['file_name'] ?? '影片');
        ?>
        <a class="watch-history-item" href="/watch_play.php?media_id=<?php echo (int)$row['media_id']; ?>">
          <div class="watch-history-cover">
            <img loading="lazy" src="/api/media_cover.php?id=<?php echo (int)$row['media_id']; ?>" alt="<?php echo e($posterAlt); ?>">
            <?php echo withu_media_quality_badge_html($row, 'watch-history-badge'); ?>
          </div>
          <div class="watch-history-content">
            <div>
              <div class="watch-history-title"><?php echo e($row['series_name'] ?: $row['file_name']); ?></div>
              <div class="watch-history-subtitle"><?php echo e($episodeText); ?> · <?php echo e((string)($row['started_at'] ?? '')); ?></div>
            </div>
            <div>
              <div class="watch-history-meta">
                <span>总观看 <?php echo e($formatDuration($durationMs)); ?></span>
                <span>单人 <?php echo e($formatDuration($soloMs)); ?></span>
                <span>一起看 <?php echo e($formatDuration($togetherMs)); ?></span>
                <span>最后位置 <?php echo e($formatDuration($lastPositionMs)); ?></span>
              </div>
              <div class="watch-history-progress" aria-hidden="true"><i style="width:<?php echo min(100, $durationMs > 0 ? round(($lastPositionMs / max(1, $durationMs)) * 100) : 0); ?>%"></i></div>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
