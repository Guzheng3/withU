<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/TravelMap.php';
$auth=new Auth(); $auth->requireLogin(); $auth->requireRole(['user1','user2']); $db=Database::getInstance(); withu_travel_map_ensure_schema($db); $adminPage='map'; include __DIR__.'/header.php';
?>
<section class="admin-page-title"><h1>地图与足迹</h1><p>维护情侣位置、共同去过的地方与路线信息，前台地图会实时读取。</p></section>
<section class="admin-grid admin-map-admin-grid">
 <div class="admin-card"><div class="admin-card-header"><div><div class="admin-card-title">前台地图 <button type="button" class="admin-help-toggle" title="查看说明" aria-label="查看说明" aria-expanded="false"><i class="ti ti-info-circle"></i></button></div></div></div><div class="admin-card-help"><div class="admin-card-subtitle">在这里查看地图、缩放和全屏展示。</div></div><a class="btn btn-primary" href="/travel.php#map"><i class="fas fa-map-location-dot"></i> 打开地图</a></div>
 <div class="admin-card"><div class="admin-card-header"><div><div class="admin-card-title">数据入口 <button type="button" class="admin-help-toggle" title="查看说明" aria-label="查看说明" aria-expanded="false"><i class="ti ti-info-circle"></i></button></div></div></div><div class="admin-card-help"><div class="admin-card-subtitle">足迹和当前位置从前台地图表单保存。</div><div class="admin-help">支持位置名称、经纬度、访问日期、收藏标记、路线起终点及距离。</div></div></div>
</section>
<section class="admin-card"><div class="admin-card-header"><div><div class="admin-card-title">使用说明 <button type="button" class="admin-help-toggle" title="查看说明" aria-label="查看说明" aria-expanded="false"><i class="ti ti-info-circle"></i></button></div></div></div><div class="admin-card-help"><div class="admin-card-subtitle">先在前台地图中搜索地点或输入经纬度，再保存为足迹；路线可通过"添加路线点"绘制。</div></div><ul class="admin-map-help-list"><li>情侣位置默认仅情侣双方可见。</li><li>足迹会展示在地图和下方时间线。</li><li>地图支持缩放、定位、全屏和图层切换。</li></ul></section>
<?php include __DIR__.'/footer.php'; ?>