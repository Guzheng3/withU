<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/withu.php';
require_once __DIR__ . '/../core/MediaDatabase.php';
require_once __DIR__ . '/../core/MediaSchema.php';
require_once __DIR__ . '/../core/MediaRepository.php';

$auth = new Auth();
withu_require_couple_user($auth);
$error = '';
$media = [];

function media_resources_badge(string $status): string
{
    $status = strtolower(trim($status));
    if ($status === 'recognized') return '已识别';
    if ($status === 'pending') return '待识别';
    if ($status === 'failed') return '失败';
    return $status === '' ? '未知' : $status;
}

try {
    $mediaDb = withu_media_db();
    $media = $mediaDb->fetchAll(
        "SELECT id,file_name,series_name,series_key,episode_number,resolution,rating,recognition_status,cover_url,last_scanned_at,updated_at
         FROM media_library
         ORDER BY CASE WHEN recognition_status = 'recognized' THEN 0 ELSE 1 END, updated_at DESC, id DESC
         LIMIT 500"
    );
} catch (Throwable $e) {
    $error = '影视资源库不可用：' . $e->getMessage();
}

$adminPage = 'media_resources';
include __DIR__ . '/header.php';
?>
<section class="admin-page-title">
    <h1>资源列表</h1>
    <p>单集资源独立管理；影视分组、批量操作和重复合并请进入影视资源库。</p>
</section>

<?php if ($error): ?>
<div class="admin-alert admin-alert-error"><?php echo e($error); ?></div>
<?php endif; ?>

<section class="admin-card" style="margin-bottom:1rem;">
    <div class="admin-card-header">
        <div>
            <div class="admin-card-title">资源库入口已分离</div>
            <div class="admin-card-subtitle">当前显示最近更新的单集资源，配置和扫描操作不再混在列表页面。</div>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
            <a class="btn btn-secondary" href="/admin/media.php"><i class="fas fa-sliders"></i> 媒体配置</a>
            <a class="btn btn-secondary" href="/admin/media_catalog.php"><i class="fas fa-layer-group"></i> 影视资源库</a>
        </div>
    </div>
</section>

<section class="media-list-card">
    <div class="media-list-toolbar">
        <div>
            <h2>单集资源</h2>
            <div class="media-sub">按更新时间排序，已识别资源优先显示</div>
        </div>
        <div class="media-sub"><?php echo count($media); ?> 条</div>
    </div>
    <div class="media-table-wrap">
        <table class="media-table">
            <thead>
                <tr>
                    <th>封面 / 名称</th>
                    <th>分组 / 选集</th>
                    <th>状态</th>
                    <th>分辨率</th>
                    <th>评分</th>
                    <th>更新时间</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($media as $item): ?>
                <?php
                $status = (string)($item['recognition_status'] ?? 'pending');
                $statusClass = $status === 'recognized' ? 'is-recognized' : ($status === 'pending' ? 'is-pending' : 'is-failed');
                ?>
                <tr>
                    <td>
                        <div style="display:flex;gap:.7rem;align-items:flex-start;">
                            <img class="media-cover" src="<?php echo e($item['cover_url'] ?: '/assets/images/Coverloaderror.jpg'); ?>" alt="">
                            <div style="min-width:0;">
                                <div class="media-row-name"><?php echo e($item['series_name'] ?: $item['file_name']); ?></div>
                                <div class="media-row-meta"><?php echo e($item['file_name']); ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="media-row-name"><?php echo e($item['series_key'] ?: '未分组'); ?></div>
                        <div class="media-row-meta"><?php echo !empty($item['episode_number']) ? '第 ' . (int)$item['episode_number'] . ' 集' : '单文件'; ?></div>
                    </td>
                    <td><span class="media-badge <?php echo e($statusClass); ?>"><?php echo e(media_resources_badge($status)); ?></span></td>
                    <td><?php echo e($item['resolution'] ?: '未知'); ?></td>
                    <td><?php echo e($item['rating'] !== null && $item['rating'] !== '' ? (string)$item['rating'] : '—'); ?></td>
                    <td><?php echo e($item['last_scanned_at'] ?: $item['updated_at']); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$media): ?>
                <tr><td colspan="6" style="text-align:center;color:var(--text-light);padding:2rem;">暂无资源记录。</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . '/footer.php'; ?>
