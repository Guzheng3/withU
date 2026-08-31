<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/TravelMap.php';
$auth=new Auth(); $auth->requireLogin(); $auth->requireRole(['user1','user2']); $db=Database::getInstance(); withu_travel_map_ensure_schema($db);

// 与前台地图（/travel.php）对接：读取前台保存的情侣位置、足迹与路线，实时展示在本页
$mapStats = ['positions' => 0, 'locations' => 0, 'routes' => 0];
$mapRecentLocations = [];
$mapPositions = [];
$mapVisibilityLabels = ['private' => '仅自己', 'couple' => '仅情侣', 'public' => '公开'];
try {
    $mapStats['positions'] = (int)($db->fetch("SELECT COUNT(*) AS c FROM couple_positions")['c'] ?? 0);
    $mapStats['locations'] = (int)($db->fetch("SELECT COUNT(*) AS c FROM travel_locations")['c'] ?? 0);
    $mapStats['routes']    = (int)($db->fetch("SELECT COUNT(*) AS c FROM travel_routes")['c'] ?? 0);
    $mapRecentLocations = $db->fetchAll("SELECT l.*, u.nickname FROM travel_locations l LEFT JOIN users u ON u.id = l.creator_id ORDER BY COALESCE(l.visit_date, '1000-01-01') DESC, l.created_at DESC LIMIT 5") ?: [];
    $mapPositions = $db->fetchAll("SELECT p.*, u.nickname FROM couple_positions p LEFT JOIN users u ON u.id = p.user_id ORDER BY p.updated_at DESC LIMIT 2") ?: [];
} catch (Throwable $e) {
    // 数据暂不可用时按空数据展示，页面其余部分不受影响
}

$adminPage='map'; include __DIR__.'/header.php';
?>
<section class="admin-page-title"><h1>地图与足迹</h1><p>维护情侣位置、共同去过的地方与路线信息，前台地图会实时读取。</p></section>
<section class="admin-grid admin-map-admin-grid">
 <div class="admin-card admin-map-entry-card"><div class="admin-card-header"><div><div class="admin-card-title">前台地图 <button type="button" class="admin-help-toggle" title="查看说明" aria-label="查看说明" aria-expanded="false"><i class="ti ti-info-circle"></i></button></div></div></div><div class="admin-card-help"><div class="admin-card-subtitle">这里实时统计前台地图已保存的数据，点击下方按钮可打开前台地图查看、缩放和全屏展示。</div></div><div class="admin-map-stat-row"><div class="admin-map-stat"><b><?php echo $mapStats['positions']; ?></b><span>情侣位置</span></div><div class="admin-map-stat"><b><?php echo $mapStats['locations']; ?></b><span>共同足迹</span></div><div class="admin-map-stat"><b><?php echo $mapStats['routes']; ?></b><span>路线</span></div></div><a class="btn btn-primary" href="/travel.php#map"><i class="fas fa-map-location-dot"></i> 打开地图</a></div>
 <div class="admin-card"><div class="admin-card-header"><div><div class="admin-card-title">前台同步数据 <button type="button" class="admin-help-toggle" title="查看说明" aria-label="查看说明" aria-expanded="false"><i class="ti ti-info-circle"></i></button></div></div></div><div class="admin-card-help"><div class="admin-card-subtitle">前台地图保存的情侣位置与足迹会实时写入数据库并同步到这里，点击任意一条可到前台地图查看。</div></div>
<?php if (!empty($mapRecentLocations) || !empty($mapPositions)): ?>
  <ul class="admin-map-sync-list">
<?php foreach ($mapRecentLocations as $loc): ?>
   <li><a class="admin-map-sync-item" href="/travel.php#map"><div class="admin-map-sync-main"><div class="admin-map-sync-title"><?php echo e((string)($loc['title'] ?? '')); ?><?php echo !empty($loc['is_favorite']) ? ' <i class="fas fa-heart"></i>' : ''; ?></div><div class="admin-map-sync-meta"><?php echo e(trim((string)($loc['location_name'] ?? '')) !== '' ? $loc['location_name'] . ' · ' : ''); ?><?php echo e(!empty($loc['visit_date']) ? (string)$loc['visit_date'] : '未填写日期'); ?><?php echo !empty($loc['nickname']) ? ' · ' . e((string)$loc['nickname']) . ' 记录' : ''; ?></div></div><span class="admin-map-sync-badge">足迹</span></a></li>
<?php endforeach; ?>
<?php foreach ($mapPositions as $pos): ?>
<?php $posName = trim((string)($pos['location_name'] ?? '')); ?>
   <li><a class="admin-map-sync-item" href="/travel.php#map"><div class="admin-map-sync-main"><div class="admin-map-sync-title"><?php echo e($posName !== '' ? $posName : (($pos['nickname'] ?? '') !== '' ? $pos['nickname'] . ' 的位置' : '情侣位置')); ?></div><div class="admin-map-sync-meta"><?php echo e($mapVisibilityLabels[$pos['visibility'] ?? 'couple'] ?? '仅情侣'); ?> · 更新于 <?php echo e(!empty($pos['updated_at']) ? date('m-d H:i', strtotime($pos['updated_at'])) : '—'); ?></div></div><span class="admin-map-sync-badge is-position">位置</span></a></li>
<?php endforeach; ?>
  </ul>
<?php else: ?>
  <div class="admin-map-sync-empty"><i class="fas fa-map-pin"></i><div>前台地图还没有保存过位置或足迹。在前台保存后，这里会实时同步展示。</div><a class="btn btn-secondary" href="/travel.php#map"><i class="fas fa-map-location-dot"></i> 去前台保存</a></div>
<?php endif; ?>
 </div>
</section>
<section class="admin-card"><div class="admin-card-header"><div><div class="admin-card-title">使用说明 <button type="button" class="admin-help-toggle" title="查看说明" aria-label="查看说明" aria-expanded="false"><i class="ti ti-info-circle"></i></button></div></div></div><div class="admin-card-help"><div class="admin-card-subtitle">先在前台地图中搜索地点或输入经纬度，再保存为足迹；路线可通过"添加路线点"绘制。</div></div><ul class="admin-map-help-list"><li>情侣位置默认仅情侣双方可见。</li><li>足迹会展示在地图和下方时间线。</li><li>地图支持缩放、定位、全屏和图层切换。</li></ul></section>
<?php include __DIR__.'/footer.php'; ?>
