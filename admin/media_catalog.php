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
require_once __DIR__ . '/../core/MediaRecognition.php';
require_once __DIR__ . '/../core/MediaDedupe.php';
require_once __DIR__ . '/../core/MediaDedupeLauncher.php';

$auth = new Auth();
withu_require_couple_user($auth);
$db = withu_media_db();
$error = '';
$success = '';
$seriesKey = trim((string)($_GET['series'] ?? ''));
$filterTypeId = max(0, (int)($_GET['type_id'] ?? 0));
$filterSourceKind = trim((string)($_GET['source_kind'] ?? ''));
$filterResolution = trim((string)($_GET['resolution'] ?? ''));

function media_catalog_in_params(array $values, string $prefix): array
{
    $placeholders = [];
    $params = [];
    foreach (array_values($values) as $index => $value) {
        $name = $prefix . $index;
        $placeholders[] = ':' . $name;
        $params[$name] = $value;
    }
    return [$placeholders, $params];
}

function media_catalog_source_label(string $kind): string
{
    $labels = [
        'webdav' => 'WebDAV',
        'openlist' => 'OpenList',
        'external' => '外部来源',
        'legacy' => '旧来源',
    ];
    return $labels[$kind] ?? ($kind !== '' ? $kind : '未关联');
}

function media_catalog_filter_query(array $extra = []): string
{
    $params = array_filter([
        'type_id' => (int)($_GET['type_id'] ?? 0) ?: null,
        'source_kind' => trim((string)($_GET['source_kind'] ?? '')) ?: null,
        'resolution' => trim((string)($_GET['resolution'] ?? '')) ?: null,
    ], static fn($value) => $value !== null && $value !== '');
    foreach ($extra as $key => $value) {
        if ($value === null || $value === '') unset($params[$key]);
        else $params[$key] = $value;
    }
    return $params ? '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986) : '';
}

function media_catalog_delete_series(MediaDatabase $db, array $seriesKeys): int
{
    $seriesKeys = array_values(array_unique(array_filter(array_map('strval', $seriesKeys), static fn($value) => trim($value) !== '')));
    if (!$seriesKeys) throw new InvalidArgumentException('请先选择影视分组。');

    [$seriesPlaceholders, $seriesParams] = media_catalog_in_params($seriesKeys, 'delete_series_');
    $rows = $db->fetchAll(
        'SELECT id FROM media_library WHERE series_key IN (' . implode(',', $seriesPlaceholders) . ')',
        $seriesParams
    );
    $mediaIds = array_values(array_unique(array_map(static fn($row) => (int)$row['id'], $rows)));
    if (!$mediaIds) return 0;

    [$mediaPlaceholders, $mediaParams] = media_catalog_in_params($mediaIds, 'delete_media_');
    $resourceRows = $db->fetchAll(
        'SELECT id FROM media_resources WHERE media_id IN (' . implode(',', $mediaPlaceholders) . ')',
        $mediaParams
    );
    $resourceIds = array_values(array_unique(array_map(static fn($row) => (int)$row['id'], $resourceRows)));
    $pdo = $db->getPDO();
    $pdo->beginTransaction();
    try {
        if ($resourceIds) {
            [$resourcePlaceholders, $resourceParams] = media_catalog_in_params($resourceIds, 'delete_resource_');
            $db->query('DELETE FROM media_resource_subtitles WHERE resource_id IN (' . implode(',', $resourcePlaceholders) . ')', $resourceParams);
            $db->query('DELETE FROM media_resource_segments WHERE resource_id IN (' . implode(',', $resourcePlaceholders) . ')', $resourceParams);
            $db->query('DELETE FROM media_resources WHERE id IN (' . implode(',', $resourcePlaceholders) . ')', $resourceParams);
        }
        $db->query('DELETE FROM media_link_checks WHERE media_id IN (' . implode(',', $mediaPlaceholders) . ')', $mediaParams);
        $db->query('DELETE FROM media_catalog_sources WHERE media_id IN (' . implode(',', $mediaPlaceholders) . ')', $mediaParams);
        [$duplicateSeriesPlaceholders, $duplicateSeriesParams] = media_catalog_in_params($seriesKeys, 'delete_duplicate_series_');
        $db->query(
            'DELETE FROM media_merge_candidates WHERE target_series_key IN (' . implode(',', $seriesPlaceholders) . ') OR duplicate_series_key IN (' . implode(',', $duplicateSeriesPlaceholders) . ')',
            array_merge($seriesParams, $duplicateSeriesParams)
        );
        $db->query('DELETE FROM media_library WHERE id IN (' . implode(',', $mediaPlaceholders) . ')', $mediaParams);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    // 主库没有外键约束，删除相关观看记录和房间，避免留下无法打开的媒体 ID。
    try {
        $main = Database::getInstance();
        $roomRows = $main->fetchAll('SELECT id FROM watch_rooms WHERE media_id IN (' . implode(',', $mediaPlaceholders) . ')', $mediaParams);
        $main->delete('watch_history', 'media_id IN (' . implode(',', $mediaPlaceholders) . ')', $mediaParams);
        if ($roomRows) {
            $roomIds = array_values(array_unique(array_map(static fn($row) => (int)$row['id'], $roomRows)));
            [$roomPlaceholders, $roomParams] = media_catalog_in_params($roomIds, 'delete_room_');
            $main->delete('watch_events', 'room_id IN (' . implode(',', $roomPlaceholders) . ')', $roomParams);
            $main->delete('watch_room_members', 'room_id IN (' . implode(',', $roomPlaceholders) . ')', $roomParams);
            $main->delete('watch_rooms', 'id IN (' . implode(',', $roomPlaceholders) . ')', $roomParams);
        }
    } catch (Throwable $e) {
        error_log('[MediaCatalog] Deleted media but could not clean watch references: ' . $e->getMessage());
    }
    return count($mediaIds);
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $action = (string)($_POST['action'] ?? '');
        $selectedKeys = array_values(array_unique(array_filter(array_map('strval', (array)($_POST['series_keys'] ?? [])))));
        if ($action === 'analyze_duplicates') {
            (new MediaDedupeLauncher())->launchAnalysis(120);
            header('Location: /admin/media_catalog.php?duplicates=queued');
            exit;
        }
        if ($action === 'merge_candidate' || $action === 'reject_candidate' || $action === 'review_candidate_ai') {
            $candidateId = (int)($_POST['candidate_id'] ?? 0);
            if ($action === 'merge_candidate') {
                (new MediaDedupe($db))->merge($candidateId);
                $result = 'handled';
            } elseif ($action === 'reject_candidate') {
                (new MediaDedupe($db))->reject($candidateId);
                $result = 'handled';
            } else {
                (new MediaDedupeLauncher())->launchAiReview($candidateId);
                $result = 'queued';
            }
            header('Location: /admin/media_catalog.php?duplicates=' . $result);
            exit;
        }
        if ($action === 'check_links') {
            $checkKey = trim((string)($_POST['series_key'] ?? ''));
            if ($checkKey === '') throw new InvalidArgumentException('影视分组不存在。');
            $ids = $db->fetchAll('SELECT id FROM media_library WHERE series_key = :series_key', ['series_key' => $checkKey]);
            $checked = (new MediaDedupe($db))->checkLinks(array_column($ids, 'id'));
            header('Location: /admin/media_catalog.php?series=' . rawurlencode($checkKey) . '&checked=' . count($checked));
            exit;
        }
        if ($action === 'bulk_status' || $action === 'bulk_type' || $action === 'bulk_recognize' || $action === 'bulk_delete') {
            if (!$selectedKeys) throw new InvalidArgumentException('请先选择影视分组。');
            if ($action === 'bulk_delete') {
                $deleted = media_catalog_delete_series($db, $selectedKeys);
                header('Location: /admin/media_catalog.php' . media_catalog_filter_query(['deleted' => $deleted]));
                exit;
            } elseif ($action === 'bulk_status') {
                $status = in_array((string)($_POST['status'] ?? ''), ['recognized', 'pending', 'disabled'], true) ? (string)$_POST['status'] : 'recognized';
                foreach ($selectedKeys as $selectedKey) $db->update('media_library', ['recognition_status' => $status, 'updated_at' => withu_now()], 'series_key = :series_key', ['series_key' => $selectedKey]);
                $success = '已批量更新 ' . count($selectedKeys) . ' 个影视分组状态。';
            } elseif ($action === 'bulk_type') {
                $typeId = (int)($_POST['media_type_id'] ?? 0);
                if (!in_array($typeId, [1, 2, 3, 4], true)) throw new InvalidArgumentException('影视分类只能是电影、电视剧、动漫或综艺。');
                foreach ($selectedKeys as $selectedKey) $db->update('media_library', ['media_type_id' => $typeId, 'updated_at' => withu_now()], 'series_key = :series_key', ['series_key' => $selectedKey]);
                $success = '已批量调整 ' . count($selectedKeys) . ' 个影视分组分类。';
            } else {
                $recognized = 0;
                foreach ($selectedKeys as $selectedKey) {
                    $result = withu_recognize_series($db, $selectedKey, [], true);
                    if (!empty($result['success'])) $recognized++;
                }
                $success = '已提交 ' . $recognized . ' 个影视分组进行 AI/元数据识别。';
            }
        }
        $key = trim((string)($_POST['series_key'] ?? ''));
        if (in_array($action, ['bulk_status', 'bulk_type', 'bulk_recognize', 'bulk_delete'], true)) $key = '';
        if ($key === '' && !in_array($action, ['analyze_duplicates', 'merge_candidate', 'reject_candidate', 'review_candidate_ai', 'bulk_status', 'bulk_type', 'bulk_recognize', 'bulk_delete'], true)) throw new InvalidArgumentException('影视分组不存在。');
        if ($action === 'save') {
            $typeId = max(1, (int)($_POST['media_type_id'] ?? 1));
            $type = $db->fetch('SELECT id FROM media_types WHERE id = :id AND status = 1 LIMIT 1', ['id' => $typeId]);
            if (!$type) throw new InvalidArgumentException('影视分类不存在。');
            $data = [
                'series_name' => mb_substr(trim((string)($_POST['series_name'] ?? '')), 0, 255),
                'media_type_id' => $typeId,
                'cover_url' => trim((string)($_POST['cover_url'] ?? '')) ?: null,
                'cast_names' => trim((string)($_POST['cast_names'] ?? '')) ?: null,
                'summary' => trim((string)($_POST['summary'] ?? '')) ?: null,
                'recognition_status' => in_array((string)($_POST['recognition_status'] ?? 'recognized'), ['recognized', 'pending', 'disabled'], true) ? (string)$_POST['recognition_status'] : 'recognized',
                'updated_at' => withu_now(),
            ];
            if ($data['series_name'] === '') throw new InvalidArgumentException('影视名称不能为空。');
            $db->update('media_library', $data, 'series_key = :series_key', ['series_key' => $key]);
            header('Location: /admin/media_catalog.php?series=' . rawurlencode($key) . '&saved=1');
            exit;
        }
        if ($action === 'disable') {
            $db->update('media_library', ['recognition_status' => 'disabled', 'updated_at' => withu_now()], 'series_key = :series_key', ['series_key' => $key]);
            header('Location: /admin/media_catalog.php?saved=disabled');
            exit;
        }
    }
    if (($_GET['saved'] ?? '') === '1') $success = '影视分组已保存。';
    if (($_GET['saved'] ?? '') === 'disabled') $success = '影视分组已从前台隐藏。';
    if (isset($_GET['deleted'])) $success = '已删除 ' . max(0, (int)$_GET['deleted']) . ' 个影视分组及其关联资源。';
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$types = [];
$groups = [];
$edit = null;
$episodes = [];
$episodeSources = [];
$candidates = [];
$dedupeJobs = [];
$sourceOptions = [];
$resolutionOptions = [];
try {
    $types = $db->fetchAll('SELECT id,name FROM media_types WHERE status = 1 ORDER BY sort_order ASC,id ASC');
    $sourceOptions = $db->fetchAll("SELECT source_kind, COUNT(DISTINCT media_id) AS group_count FROM media_catalog_sources WHERE status = 'active' GROUP BY source_kind ORDER BY source_kind ASC");
    $resolutionOptions = $db->fetchAll("SELECT TRIM(resolution) AS value, COUNT(DISTINCT series_key) AS group_count FROM media_library WHERE resolution IS NOT NULL AND TRIM(resolution) <> '' GROUP BY TRIM(resolution) ORDER BY value ASC");
    $groupWhere = ["m.series_key IS NOT NULL", "m.series_key <> ''"];
    $groupParams = [];
    if ($filterTypeId > 0) {
        $groupWhere[] = 'm.media_type_id = :filter_type_id';
        $groupParams['filter_type_id'] = $filterTypeId;
    }
    if ($filterSourceKind === 'none') {
        $groupWhere[] = "NOT EXISTS (SELECT 1 FROM media_catalog_sources s0 WHERE s0.media_id = m.id AND s0.status = 'active')";
    } elseif ($filterSourceKind !== '') {
        $groupWhere[] = "EXISTS (SELECT 1 FROM media_catalog_sources s0 WHERE s0.media_id = m.id AND s0.status = 'active' AND s0.source_kind = :filter_source_kind)";
        $groupParams['filter_source_kind'] = $filterSourceKind;
    }
    if ($filterResolution === 'unknown') {
        $groupWhere[] = "(m.resolution IS NULL OR TRIM(m.resolution) = '')";
    } elseif ($filterResolution !== '') {
        $groupWhere[] = 'TRIM(m.resolution) = :filter_resolution';
        $groupParams['filter_resolution'] = $filterResolution;
    }
    $groups = $db->fetchAll(
        "SELECT m.series_key, MAX(m.series_name) AS series_name, MAX(m.media_type_id) AS media_type_id, MAX(m.cover_url) AS cover_url, MAX(m.recognition_status) AS recognition_status,
                COUNT(DISTINCT m.id) AS episode_count, COUNT(DISTINCT s.id) AS source_count,
                GROUP_CONCAT(DISTINCT s.source_kind ORDER BY s.source_kind SEPARATOR ',') AS source_kinds,
                MAX(m.updated_at) AS updated_at
         FROM media_library m LEFT JOIN media_catalog_sources s ON s.media_id = m.id AND s.status = 'active'
         WHERE " . implode(' AND ', $groupWhere) . "
         GROUP BY m.series_key ORDER BY updated_at DESC LIMIT 500",
        $groupParams
    );
    if ($seriesKey !== '') {
        $edit = $db->fetch('SELECT * FROM media_library WHERE series_key = :series_key ORDER BY id ASC LIMIT 1', ['series_key' => $seriesKey]);
        $episodes = $db->fetchAll("SELECT m.id,m.file_name,m.episode_title,m.episode_number,m.play_source,m.source_url,m.recognition_status,
                    c.status AS link_status,c.http_code,c.fingerprint_method,c.comparison_confidence,
                    (SELECT COUNT(DISTINCT c2.media_id) FROM media_link_checks c2 WHERE c2.fingerprint = c.fingerprint AND c2.media_id <> m.id) AS same_media_count
             FROM media_library m
             LEFT JOIN media_link_checks c ON c.id = (SELECT c0.id FROM media_link_checks c0 WHERE c0.media_id = m.id ORDER BY c0.checked_at DESC,c0.id DESC LIMIT 1)
             WHERE m.series_key = :series_key ORDER BY m.season_number ASC,m.episode_number ASC,m.id ASC", ['series_key' => $seriesKey]);
        foreach ($episodes as $episode) {
            $episodeId = (int)$episode['id'];
            $episodeSources[$episodeId] = withu_media_catalog_fetch_sources($db, $episodeId);
        }
    }
    $candidates = (new MediaDedupe($db))->candidates(50);
    $dedupeLauncher = new MediaDedupeLauncher();
    $dedupeLauncher->reconcileJobs();
    $dedupeJobs = $dedupeLauncher->jobs(8);
    if (isset($_GET['duplicates'])) $success = $_GET['duplicates'] === 'handled' ? '重复候选已处理。' : ($_GET['duplicates'] === 'queued' ? '去重维护任务已转入后台，刷新页面查看进度。' : '全库重复分析完成，新增候选 ' . (int)$_GET['duplicates'] . ' 条。');
    if (isset($_GET['checked'])) $success = '已检测 ' . (int)$_GET['checked'] . ' 个播放链接；相同最终地址会显示为同源。';
} catch (Throwable $e) {
    if ($error === '') $error = $e->getMessage();
}
$adminPage = 'media_catalog';
include __DIR__ . '/header.php';
?>
<section class="admin-page-title"><h1>影视资源库</h1><p>管理影视分组、分集、分类、重复合并和 AI 元数据识别。</p></section>
<?php if ($error): ?><div class="admin-alert admin-alert-error"><?php echo e($error); ?></div><?php endif; ?>
<?php if ($success): ?><div class="admin-alert admin-alert-success"><?php echo e($success); ?></div><?php endif; ?>
<?php if ($edit): ?>
<section class="admin-card" style="margin-bottom:1rem;"><div class="admin-card-header"><div><div class="admin-card-title">编辑影视分组</div><div class="admin-card-subtitle">保存会同步到该影视的全部分集。</div></div><div style="display:flex;gap:.5rem;flex-wrap:wrap;"><form method="post"><?php echo csrf_field(); ?><input type="hidden" name="action" value="check_links"><input type="hidden" name="series_key" value="<?php echo e($seriesKey); ?>"><button class="btn btn-secondary" type="submit"><i class="fas fa-link"></i> 检测播放链接</button></form><a class="btn btn-secondary" href="/admin/media_catalog.php"><i class="fas fa-list"></i> 返回列表</a></div></div>
<form method="post" class="media-edit-form"><?php echo csrf_field(); ?><input type="hidden" name="action" value="save"><input type="hidden" name="series_key" value="<?php echo e($seriesKey); ?>"><div class="media-edit-primary-grid"><label class="media-edit-name-field">影视名称<input class="media-edit-name-input" required name="series_name" value="<?php echo e($edit['series_name']); ?>"></label><label class="media-edit-type-field">分类<select name="media_type_id"><?php foreach($types as $type): ?><option value="<?php echo (int)$type['id']; ?>" <?php echo (int)$edit['media_type_id']===(int)$type['id']?'selected':''; ?>><?php echo e($type['name']); ?></option><?php endforeach; ?></select></label></div><label>封面地址<input name="cover_url" value="<?php echo e($edit['cover_url']); ?>"></label><label>演员<input name="cast_names" value="<?php echo e($edit['cast_names']); ?>"></label><label>简介<textarea name="summary" rows="4"><?php echo e($edit['summary']); ?></textarea></label><div style="display:flex;gap:.8rem;align-items:center;flex-wrap:wrap;"><label>前台状态<select name="recognition_status"><option value="recognized" <?php echo $edit['recognition_status']==='recognized'?'selected':''; ?>>显示</option><option value="pending" <?php echo $edit['recognition_status']==='pending'?'selected':''; ?>>待处理</option><option value="disabled" <?php echo $edit['recognition_status']==='disabled'?'selected':''; ?>>隐藏</option></select></label><button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> 保存分组</button></div></form></section>
<section class="admin-card"><div class="admin-card-header"><div><div class="admin-card-title">分集（<?php echo count($episodes); ?>）</div><div class="admin-card-subtitle">仅保留 WebDAV 刮削来源，播放器直接获取 OpenList 签名直链。</div></div></div><div style="overflow:auto;"><table class="admin-table"><thead><tr><th>集数</th><th>名称</th><th>播放来源</th><th>链接检测</th><th>状态</th></tr></thead><tbody><?php foreach($episodes as $episode): $episodeId=(int)$episode['id']; $sources=$episodeSources[$episodeId]??[]; ?><tr><td><?php echo (int)($episode['episode_number'] ?: 1); ?></td><td><?php echo e($episode['episode_title'] ?: $episode['file_name']); ?></td><td><?php if($sources): foreach($sources as $source): ?><div style="margin:.18rem 0;"><span class="admin-tag"><?php echo e($source['source_label']); ?></span> <small>直链<?php if(!empty($source['is_primary'])): ?> · 主来源<?php endif; ?></small></div><?php endforeach; else: ?><div><span class="admin-tag">无有效 WebDAV 来源</span></div><?php endif; ?><small style="word-break:break-all;color:var(--text-light);"><?php echo e($episode['source_url']); ?></small></td><td><?php echo e($episode['link_status'] ?? '未检测'); ?><?php if (!empty($episode['http_code'])): ?><br><small>HTTP <?php echo (int)$episode['http_code']; ?></small><?php endif; ?><?php if ((int)($episode['same_media_count'] ?? 0) > 0): ?><br><small style="color:<?php echo ($episode['comparison_confidence'] ?? '')==='confirmed'?'#1b8f57':'#b7791f'; ?>"><?php echo ($episode['comparison_confidence'] ?? '')==='confirmed'?'已确认同内容':'疑似同内容'; ?> · <?php echo e($episode['fingerprint_method'] ?? ''); ?></small><?php endif; ?></td><td><?php echo e($episode['recognition_status']); ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php else: ?>
<section class="admin-card media-catalog-filter-card">
    <div class="admin-card-header">
        <div>
            <div class="admin-card-title">筛选影视资源</div>
            <div class="admin-card-subtitle">按类型、来源和分辨率缩小当前列表，批量操作只作用于筛选结果。</div>
        </div>
    </div>
    <form method="get" class="media-catalog-filter-form" aria-label="筛选影视资源">
        <label><span>类型</span><select name="type_id">
            <option value="0">全部类型</option>
            <?php foreach ($types as $type): ?><option value="<?php echo (int)$type['id']; ?>" <?php echo $filterTypeId === (int)$type['id'] ? 'selected' : ''; ?>><?php echo e($type['name']); ?></option><?php endforeach; ?>
        </select></label>
        <label><span>来源</span><select name="source_kind">
            <option value="">全部来源</option>
            <?php foreach ($sourceOptions as $source): ?><option value="<?php echo e($source['source_kind']); ?>" <?php echo $filterSourceKind === (string)$source['source_kind'] ? 'selected' : ''; ?>><?php echo e(media_catalog_source_label((string)$source['source_kind'])); ?>（<?php echo (int)$source['group_count']; ?>）</option><?php endforeach; ?>
            <option value="none" <?php echo $filterSourceKind === 'none' ? 'selected' : ''; ?>>未关联来源</option>
        </select></label>
        <label><span>分辨率</span><select name="resolution">
            <option value="">全部分辨率</option>
            <?php foreach ($resolutionOptions as $resolution): ?><option value="<?php echo e($resolution['value']); ?>" <?php echo $filterResolution === (string)$resolution['value'] ? 'selected' : ''; ?>><?php echo e($resolution['value']); ?>（<?php echo (int)$resolution['group_count']; ?>）</option><?php endforeach; ?>
            <option value="unknown" <?php echo $filterResolution === 'unknown' ? 'selected' : ''; ?>>未知分辨率</option>
        </select></label>
        <div class="media-catalog-filter-actions"><button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> 筛选</button><a class="btn btn-secondary" href="/admin/media_catalog.php"><i class="fas fa-rotate-left"></i> 重置</a></div>
    </form>
</section>
<section class="admin-card">
<div class="admin-card-header"><div><div class="admin-card-title">影视分组</div><div class="admin-card-subtitle">当前筛选显示 <?php echo count($groups); ?> 组；只显示电影、电视剧、动漫、综艺四类</div></div></div>
<form method="post" id="bulk-media-form" class="media-catalog-form" data-bulk-form><?php echo csrf_field(); ?><input type="hidden" name="action" id="bulk-action" value="bulk_status">
    <div class="media-catalog-toolbar" role="toolbar" aria-label="影视批量管理">
        <div class="media-catalog-selection">
            <label class="media-check-control media-select-all-control">
                <input type="checkbox" id="media-catalog-select-all" aria-label="全选当前页影视分组">
                <span>全选本页</span>
            </label>
            <button class="media-tool-button" type="button" data-select-action="invert">反选</button>
            <button class="media-tool-button" type="button" data-select-action="clear">清空</button>
            <output class="media-selected-summary" id="media-selected-summary" aria-live="polite">已选择 0 组</output>
        </div>
        <div class="media-catalog-actions">
            <button class="btn btn-secondary" type="button" data-bulk-action="bulk_recognize"><i class="fas fa-wand-magic-sparkles"></i><span>AI识别选中</span></button>
            <label class="media-bulk-select"><span>状态</span><select name="status" aria-label="批量状态"><option value="recognized">显示</option><option value="pending">待处理</option><option value="disabled">隐藏</option></select></label>
            <button class="btn btn-secondary" type="button" data-bulk-action="bulk_status">改状态</button>
            <label class="media-bulk-select"><span>分类</span><select name="media_type_id" aria-label="批量分类"><option value="1">电影</option><option value="2">电视剧</option><option value="3">动漫</option><option value="4">综艺</option></select></label>
            <button class="btn btn-secondary" type="button" data-bulk-action="bulk_type">改分类</button>
            <button class="btn media-danger-button" type="button" data-bulk-action="bulk_delete"><i class="fas fa-trash"></i><span>批量删除</span></button>
            <button class="btn btn-outline" type="button" data-duplicate-action><i class="fas fa-magnifying-glass"></i><span>分析重复</span></button>
        </div>
    </div>
    <div class="media-catalog-table-wrap"><table class="admin-table media-catalog-table"><thead><tr><th class="media-check-column"><input type="checkbox" id="media-catalog-select-all-header" aria-label="全选当前页影视分组"></th><th>影视名称</th><th>分类</th><th>分集</th><th>来源</th><th>状态</th><th>更新时间</th><th>操作</th></tr></thead><tbody>
    <?php foreach($groups as $group): $typeName='未分类'; foreach($types as $type) if((int)$type['id']===(int)$group['media_type_id']) $typeName=$type['name']; $groupStatus=(string)($group['recognition_status'] ?? 'pending'); $statusLabel=$groupStatus==='recognized'?'已识别':($groupStatus==='disabled'?'已隐藏':'待处理'); $sourceKinds=array_values(array_filter(explode(',', (string)($group['source_kinds'] ?? '')))); $sourceLabel=$sourceKinds ? implode(' / ', array_map('media_catalog_source_label', $sourceKinds)) : '未关联来源'; ?>
        <tr class="media-catalog-row" data-selectable-row>
            <td class="media-check-column"><input class="media-row-check" type="checkbox" name="series_keys[]" value="<?php echo e($group['series_key']); ?>" aria-label="选择 <?php echo e($group['series_name']); ?>"></td>
            <td><div class="media-catalog-title"><?php echo e($group['series_name']); ?></div><div class="media-catalog-key"><?php echo e($group['series_key']); ?></div></td>
            <td><span class="media-catalog-type"><?php echo e($typeName); ?></span></td>
            <td><strong><?php echo (int)$group['episode_count']; ?></strong><span class="media-catalog-muted"> 集</span></td>
            <td><span class="media-catalog-source"><i class="fas fa-cloud"></i><?php echo (int)($group['source_count'] ?? 0); ?> <?php echo e($sourceLabel); ?></span></td>
            <td><span class="media-catalog-status media-catalog-status-<?php echo e($groupStatus); ?>"><?php echo e($statusLabel); ?></span></td>
            <td class="media-catalog-time"><?php echo e($group['updated_at']); ?></td>
            <td><a class="btn btn-secondary media-row-action" href="/admin/media_catalog.php?series=<?php echo rawurlencode($group['series_key']); ?>"><i class="fas fa-pen"></i><span>管理</span></a></td>
        </tr>
    <?php endforeach; ?>
    <?php if(!$groups): ?><tr><td colspan="8" class="media-catalog-empty">暂无影视分组，请在后台扫描 OpenList。</td></tr><?php endif; ?>
    </tbody></table></div>
</form></section>
<form method="post" id="duplicate-form" style="display:none;"><?php echo csrf_field(); ?><input type="hidden" name="action" value="analyze_duplicates"></form>
<?php if($dedupeJobs): ?><section class="admin-card" style="margin-bottom:1rem;"><div class="admin-card-header"><div><div class="admin-card-title">去重维护任务</div><div class="admin-card-subtitle">全库分析和 AI 核验在后台执行，不占用当前页面请求。</div></div></div><div style="overflow:auto;"><table class="admin-table"><thead><tr><th>任务</th><th>状态</th><th>创建时间</th><th>结果</th></tr></thead><tbody><?php foreach($dedupeJobs as $job): ?><tr><td><?php echo e($job['label'] ?? '去重维护'); ?></td><td><?php echo e($job['status'] ?? 'unknown'); ?></td><td><?php echo e($job['created_at'] ?? ''); ?></td><td><?php echo e($job['message'] ?? ''); ?></td></tr><?php endforeach; ?></tbody></table></div></section><?php endif; ?>
<section class="admin-card"><div class="admin-card-header"><div><div class="admin-card-title">重复影视候选</div><div class="admin-card-subtitle">本地索引先生成全库候选；AI 核验只在管理员单独点击时调用，结果只做建议；合并前会保留不同线路。</div></div></div><div style="overflow:auto;"><table class="admin-table"><thead><tr><th>相似度</th><th>保留名称</th><th>疑似重复</th><th>依据</th><th>操作</th></tr></thead><tbody><?php foreach($candidates as $candidate): ?><tr><td><?php echo e($candidate['score']); ?></td><td><?php echo e($candidate['target_name']); ?></td><td><?php echo e($candidate['duplicate_name']); ?></td><td><?php echo e($candidate['reason']); ?><?php if(!empty($candidate['ai_result'])): ?><br><small>AI：<?php echo $candidate['ai_result']==='same'?'建议合并':'建议不合并'; ?></small><?php endif; ?><?php if(!empty($candidate['ai_explanation'])): ?><br><small><?php echo e($candidate['ai_explanation']); ?></small><?php endif; ?></td><td><form method="post" style="display:inline"><?php echo csrf_field(); ?><input type="hidden" name="action" value="review_candidate_ai"><input type="hidden" name="candidate_id" value="<?php echo (int)$candidate['id']; ?>"><button class="btn btn-secondary" type="submit">AI核验</button></form> <form method="post" style="display:inline" onsubmit="return confirm('合并后保留两边不同播放线路，确认合并吗？');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="merge_candidate"><input type="hidden" name="candidate_id" value="<?php echo (int)$candidate['id']; ?>"><button class="btn btn-primary" type="submit">确认合并</button></form> <form method="post" style="display:inline"><?php echo csrf_field(); ?><input type="hidden" name="action" value="reject_candidate"><input type="hidden" name="candidate_id" value="<?php echo (int)$candidate['id']; ?>"><button class="btn btn-secondary" type="submit">不是重复</button></form></td></tr><?php endforeach; ?><?php if(!$candidates): ?><tr><td colspan="5" style="text-align:center;color:var(--text-light);padding:1.5rem;">暂无待处理候选，请先点击“分析全库重复”。</td></tr><?php endif; ?></tbody></table></div></section>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('bulk-media-form');
    if (!form) return;
    var checks = Array.prototype.slice.call(form.querySelectorAll('.media-row-check'));
    var selectAll = document.getElementById('media-catalog-select-all');
    var selectAllHeader = document.getElementById('media-catalog-select-all-header');
    var summary = document.getElementById('media-selected-summary');
    var actionInput = document.getElementById('bulk-action');
    var duplicateForm = document.getElementById('duplicate-form');

    function selected() { return checks.filter(function (check) { return check.checked; }); }
    function syncSelection() {
        var selectedCount = selected().length;
        var allSelected = checks.length > 0 && selectedCount === checks.length;
        var mixed = selectedCount > 0 && !allSelected;
        [selectAll, selectAllHeader].forEach(function (control) {
            if (!control) return;
            control.checked = allSelected;
            control.indeterminate = mixed;
            control.setAttribute('aria-checked', mixed ? 'mixed' : (allSelected ? 'true' : 'false'));
            var controlWrap = control.closest('.media-select-all-control, .media-check-column');
            if (controlWrap) controlWrap.classList.toggle('is-mixed', mixed);
        });
        if (summary) summary.textContent = '已选择 ' + selectedCount + ' 组';
        form.querySelectorAll('[data-selectable-row]').forEach(function (row) {
            var check = row.querySelector('.media-row-check');
            row.classList.toggle('is-selected', !!(check && check.checked));
        });
    }
    function setAll(checked) {
        checks.forEach(function (check) { check.checked = checked; });
        syncSelection();
    }
    [selectAll, selectAllHeader].forEach(function (control) {
        if (control) control.addEventListener('change', function () { setAll(control.checked); });
    });
    checks.forEach(function (check) { check.addEventListener('change', syncSelection); });
    form.querySelectorAll('[data-select-action]').forEach(function (button) {
        button.addEventListener('click', function () {
            var action = button.getAttribute('data-select-action');
            if (action === 'invert') checks.forEach(function (check) { check.checked = !check.checked; });
            if (action === 'clear') setAll(false);
            syncSelection();
        });
    });
    form.querySelectorAll('[data-bulk-action]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (!selected().length) { window.alert('请先选择影视分组'); return; }
            var action = button.getAttribute('data-bulk-action');
            if (action === 'bulk_delete' && !window.confirm('将删除选中的影视分组、分集、来源、链接检测和观看记录，删除后不可恢复。确认继续吗？')) return;
            actionInput.value = action;
            form.submit();
        });
    });
    var duplicateButton = form.querySelector('[data-duplicate-action]');
    if (duplicateButton && duplicateForm) duplicateButton.addEventListener('click', function () { duplicateForm.submit(); });
    syncSelection();
});
</script>
<?php endif; ?>
<?php include __DIR__ . '/footer.php'; ?>
