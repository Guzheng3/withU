<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/withu.php';
require_once __DIR__ . '/../core/MediaDatabase.php';
require_once __DIR__ . '/../core/MediaSchema.php';
require_once __DIR__ . '/../core/MediaRepository.php';
require_once __DIR__ . '/../core/OpenList.php';
require_once __DIR__ . '/../core/MediaRecognition.php';

migrate_schema_if_needed();
$auth = new Auth();
$user = withu_require_couple_user($auth);
$db = withu_media_db();
$action = (string)($_GET['action'] ?? $_POST['action'] ?? 'list');

function withu_media_api_public_source(array $source): array
{
    return [
        'source_id' => (int)($source['source_id'] ?? $source['id'] ?? 0),
        'source_kind' => (string)($source['source_kind'] ?? 'external'),
        'source_label' => (string)($source['source_label'] ?? '外部来源'),
        'player_mode' => (string)($source['player_mode'] ?? 'parsed'),
        'player_code' => (string)($source['player_code'] ?? 'parsed'),
        'play_source' => (string)($source['play_source'] ?? ''),
        'collection_source_id' => (int)($source['collection_source_id'] ?? 0),
        'external_id' => (string)($source['external_id'] ?? ''),
        'episode_number' => (int)($source['episode_number'] ?? 0),
        'episode_title' => (string)($source['episode_title'] ?? ''),
        'is_primary' => !empty($source['is_primary']),
    ];
}

function withu_media_api_row(array $row, $db = null): array
{
    $sources = [];
    if ($db && (int)($row['id'] ?? 0) > 0) {
        $sources = withu_media_catalog_fetch_sources($db, (int)$row['id']);
        $row = withu_media_catalog_resolve_row($db, $row, 0);
    }
    $row = withu_media_display_row($row);
    $row['id'] = (int)$row['id'];
    $row['url'] = withu_media_url($row);
    $row['player_mode'] = withu_media_player_mode($row);
    $row['player_code'] = withu_media_player_code($row);
    $row['quality_text'] = withu_media_quality_text($row);
    $row['cover_api'] = '/api/media_cover.php?id=' . (int)$row['id'];
    $row['backdrop_api'] = '/api/media_backdrop.php?id=' . (int)$row['id'];
    $row['file_size'] = $row['file_size'] !== null ? (int)$row['file_size'] : null;
    $row['sources'] = array_map('withu_media_api_public_source', $sources);
    $row['source_count'] = count($row['sources']);
    return $row;
}

function withu_media_api_unique_rows(array $rows, $db = null): array
{
    $seen = [];
    $items = [];
    foreach ($rows as $row) {
        $row = withu_media_api_row($row, $db);
        $id = (int)$row['id'];
        if ($id <= 0 || isset($seen[$id])) continue;
        $seen[$id] = true;
        $items[] = $row;
    }
    return $items;
}

function withu_media_api_sort_episodes(array $a, array $b): int
{
    $season = (int)($a['season_number'] ?? 0) <=> (int)($b['season_number'] ?? 0);
    if ($season !== 0) return $season;
    $episode = (int)($a['episode_number'] ?? 0) <=> (int)($b['episode_number'] ?? 0);
    if ($episode !== 0) return $episode;
    return (int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0);
}

function withu_media_api_like_term(string $term): string
{
    $term = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    return '%' . $term . '%';
}

function withu_media_api_library_groups($db, string $term, int $typeId, int $page, int $limit): array
{
    $page = max(1, $page);
    $limit = max(1, min(60, $limit));
    $offset = ($page - 1) * $limit;
    $where = ["recognition_status = 'recognized'", 'media_type_id IN (1,2,3,4)'];
    $params = [];
    if ($typeId > 0 && in_array($typeId, [1, 2, 3, 4], true)) {
        $where[] = 'media_type_id = :type_id';
        $params['type_id'] = $typeId;
    }
    if ($term !== '') {
        $like = withu_media_api_like_term($term);
        $where[] = '(series_name LIKE :q1 ESCAPE \'\\\\\' OR file_name LIKE :q2 ESCAPE \'\\\\\' OR tags LIKE :q3 ESCAPE \'\\\\\' OR cast_names LIKE :q4 ESCAPE \'\\\\\' OR episode_title LIKE :q5 ESCAPE \'\\\\\')';
        foreach (['q1','q2','q3','q4','q5'] as $key) $params[$key] = $like;
    }
    $sql = "SELECT COALESCE(NULLIF(series_key,''), CONCAT('single:', MIN(id))) AS group_key,
                   MIN(id) AS first_id, MAX(series_name) AS group_name, COUNT(*) AS episode_count,
                   MAX(added_at) AS added_at,
                   MAX(updated_at) AS updated_at
            FROM media_library WHERE " . implode(' AND ', $where) . "
            GROUP BY COALESCE(NULLIF(series_key,''), CONCAT('single:', id))
            ORDER BY added_at DESC, first_id DESC LIMIT {$offset},{$limit}";
    $groupRows = $db->fetchAll($sql, $params);
    $items = [];
    foreach ($groupRows as $group) {
        $row = $db->fetch('SELECT * FROM media_library WHERE id = :id LIMIT 1', ['id' => (int)$group['first_id']]);
        if (!$row) continue;
        $item = withu_media_api_row($row, $db);
        $item['group_key'] = (string)$group['group_key'];
        $item['group_name'] = (string)($group['group_name'] ?: $item['series_name'] ?: $item['file_name']);
        $item['episode_count'] = (int)$group['episode_count'];
        $item['added_at'] = (string)$group['added_at'];
        $items[] = $item;
    }
    return ['items' => $items, 'page' => $page, 'limit' => $limit, 'has_more' => count($groupRows) >= $limit];
}

if ($action === 'library') {
    $term = trim((string)($_GET['q'] ?? ''));
    $typeId = (int)($_GET['type_id'] ?? 0);
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min(60, (int)($_GET['limit'] ?? 24)));
    withu_json_response(['success' => true] + withu_media_api_library_groups($db, $term, $typeId, $page, $limit));
}

function withu_media_api_search_groups($db, string $term, int $limit = 10): array
{
    $term = trim($term);
    if ($term === '') return [];
    $like = withu_media_api_like_term($term);
    $fields = 'id,source_key,source_url,direct_url,file_name,file_size,series_key,series_name,season_number,episode_number,resolution,rating,tags,cast_names,cover_url,backdrop_url,metadata_json,recognition_status';
    $rows = array_merge(
        $db->fetchAll(
            "SELECT {$fields}
             FROM media_library
             WHERE recognition_status = 'recognized' AND media_type_id IN (1,2,3,4) AND series_name LIKE :series_like ESCAPE '\\\\'
             ORDER BY series_name ASC, id DESC
             LIMIT 80",
            ['series_like' => $like]
        ),
        $db->fetchAll(
            "SELECT {$fields}
             FROM media_library
             WHERE recognition_status = 'recognized' AND media_type_id IN (1,2,3,4) AND file_name LIKE :file_like ESCAPE '\\\\'
             ORDER BY file_name ASC, id DESC
             LIMIT 80",
            ['file_like' => $like]
        )
    );
    $groups = [];
    foreach ($rows as $row) {
        $row = withu_media_api_row($row, $db);
        $key = (string)($row['series_key'] ?: $row['id']);
        if ((string)($row['recognition_status'] ?? '') !== 'recognized') {
            try {
                withu_recognize_series($db, $key, [], false);
                $fresh = $db->fetch('SELECT * FROM media_library WHERE id = :id LIMIT 1', ['id' => (int)$row['id']]);
                if ($fresh) $row = withu_media_api_row($fresh, $db);
            } catch (Throwable $e) {}
        }
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'key' => $key,
                'id' => (int)$row['id'],
                'name' => (string)($row['series_name'] ?: $row['file_name']),
                'count' => 0,
                'item' => $row,
            ];
        }
        $groups[$key]['count']++;
    }
    return array_slice(array_values($groups), 0, $limit);
}

if ($action === 'list') {
    $query = trim((string)($_GET['q'] ?? ''));
    if ($query !== '') {
        $groups = withu_media_api_search_groups($db, $query, 10);
        withu_json_response([
            'success' => true,
            'items' => [],
            'groups' => $groups,
        ]);
    }

    $currentId = (int)($_GET['current_id'] ?? 0);
    $current = null;
    if ($currentId > 0) {
        $current = $db->fetch("SELECT * FROM media_library WHERE id = :id AND recognition_status <> 'disabled' AND media_type_id IN (1,2,3,4) LIMIT 1", ['id' => $currentId]);
    }
    if (!$current) {
        $current = $db->fetch("SELECT * FROM media_library WHERE recognition_status = 'recognized' AND media_type_id IN (1,2,3,4) ORDER BY updated_at DESC, id DESC LIMIT 1");
    }

    $rows = [];
    $seriesKey = '';
    if ($current) {
        $current = withu_media_display_row($current);
        $seriesKey = trim((string)($current['series_key'] ?? ''));
        if ($seriesKey !== '') {
            $episodes = $db->fetchAll(
                "SELECT * FROM media_library
                 WHERE recognition_status <> 'disabled' AND series_key = :series_key
                 ORDER BY season_number ASC, episode_number ASC, id ASC
                 LIMIT 2000",
                ['series_key' => $seriesKey]
            );
            $rows = array_merge($rows, $episodes);
            if (!$episodes) $rows[] = $current;
        } else {
            $rows[] = $current;
        }
    }

    // 当前媒体必须始终在返回集合中。重建、补识别或来源合并期间，
    // 某些记录可能暂时不满足推荐列表的状态筛选，但不能因此让播放页丢失当前选集。
    if ($current) {
        $currentId = (int)$current['id'];
        $hasCurrent = false;
        foreach ($rows as $row) {
            if ((int)($row['id'] ?? 0) === $currentId) {
                $hasCurrent = true;
                break;
            }
        }
        if (!$hasCurrent) array_unshift($rows, $current);
    }

    $recommendationRows = [];
    if ($current) {
        $params = ['id' => (int)$current['id']];
        $where = "recognition_status = 'recognized' AND media_type_id IN (1,2,3,4) AND id <> :id";
        if ($seriesKey !== '') {
            $where .= " AND (series_key IS NULL OR series_key = '' OR series_key <> :series_key)";
            $params['series_key'] = $seriesKey;
        }
        if (!empty($current['resolution'])) {
            $recommendationRows = $db->fetchAll(
                "SELECT * FROM media_library FORCE INDEX (idx_media_status_updated)
                 WHERE {$where} AND resolution = :resolution
                 ORDER BY updated_at DESC, id DESC
                 LIMIT 70",
                $params + ['resolution' => $current['resolution']]
            );
        }
        if (count($recommendationRows) < 70) {
            $recommendationRows = array_merge($recommendationRows, $db->fetchAll(
                "SELECT * FROM media_library FORCE INDEX (idx_media_status_updated)
                 WHERE {$where}
                 ORDER BY updated_at DESC, id DESC
                 LIMIT 120",
                $params
            ));
        }
    } else {
        $recommendationRows = $db->fetchAll("SELECT * FROM media_library FORCE INDEX (idx_media_status_updated) WHERE recognition_status = 'recognized' AND media_type_id IN (1,2,3,4) ORDER BY updated_at DESC, id DESC LIMIT 120");
    }

    $recommendationGroups = [];
    foreach (withu_media_api_unique_rows($recommendationRows, $db) as $row) {
        $key = (string)($row['series_key'] ?: $row['id']);
        if ($seriesKey !== '' && $key === $seriesKey) continue;
        if (!isset($recommendationGroups[$key])) {
            $recommendationGroups[$key] = [
                'key' => $key,
                'id' => (int)$row['id'],
                'name' => (string)($row['series_name'] ?: $row['file_name']),
                'count' => 0,
                'item' => $row,
            ];
            $rows[] = $row;
        }
        $recommendationGroups[$key]['count']++;
        if (count($recommendationGroups) >= 10) break;
    }

    $items = withu_media_api_unique_rows($rows, $db);
    usort($items, static function (array $a, array $b) use ($seriesKey): int {
        $aCurrent = ($seriesKey !== '' && (string)($a['series_key'] ?? '') === $seriesKey) ? 0 : 1;
        $bCurrent = ($seriesKey !== '' && (string)($b['series_key'] ?? '') === $seriesKey) ? 0 : 1;
        if ($aCurrent !== $bCurrent) return $aCurrent <=> $bCurrent;
        return withu_media_api_sort_episodes($a, $b);
    });

    withu_json_response([
        'success' => true,
        'items' => $items,
        'groups' => array_slice(array_values($recommendationGroups), 0, 10),
        'current_id' => $current ? (int)$current['id'] : 0,
        'total_returned' => count($items),
    ]);
}

$body = withu_json_body();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') withu_json_response(['success' => false, 'message' => '请求方式错误'], 405);
withu_require_json_csrf($body);

if ($action === 'scan') {
    try {
        $client = new OpenListClient($db);
        $added = 0; $updated = 0;
        $client->scanEach(function (array $file) use (&$added, &$updated): void {
            $result = withu_media_upsert_file($file, false);
            if (!empty($result['changed'])) $added++; else $updated++;
        });
        withu_json_response(['success' => true, 'message' => "扫描完成，新增 {$added} 个，更新 {$updated} 个", 'added' => $added, 'updated' => $updated]);
    } catch (Throwable $e) {
        withu_json_response(['success' => false, 'message' => $e->getMessage()], 400);
    }
}

if ($action === 'add') {
    $source = trim((string)($body['source_url'] ?? ''));
    $name = trim((string)($body['file_name'] ?? basename(parse_url($source, PHP_URL_PATH) ?: '未命名视频')));
    if (!preg_match('#^https?://#i', $source)) withu_json_response(['success' => false, 'message' => '视频链接必须是 http/https 地址'], 400);
    $sourceKey = trim((string)($body['source_key'] ?? $source));
    $result = withu_media_upsert_file(['source_key' => $sourceKey, 'source_url' => $source, 'direct_url' => '', 'file_name' => $name, 'file_size' => null, 'file_etag' => ''], false);
    $freshMedia = $result['media'] ?? null;
    if ($freshMedia) withu_recognize_media($db, $freshMedia, true);
    withu_json_response(['success' => true, 'message' => '视频已加入媒体库']);
}

if ($action === 'resolve') {
    $id = (int)($body['id'] ?? 0);
    $media = $db->fetch("SELECT * FROM media_library WHERE id = :id LIMIT 1", ['id' => $id]);
    if (!$media) withu_json_response(['success' => false, 'message' => '媒体不存在'], 404);
    $direct = (new OpenListClient($db))->resolve($media);
    if ($direct !== '') {
        $db->update('media_library', ['direct_url' => null, 'browser_playback' => 'direct', 'updated_at' => withu_now()], 'id = :id', ['id' => $id]);
    }
    withu_json_response(['success' => $direct !== '', 'url' => $direct, 'message' => $direct !== '' ? '直链已临时获取，未写入媒体库' : '无法获取直链']);
}

if ($action === 'recognize') {
    $id = (int)($body['id'] ?? 0);
    $media = $db->fetch("SELECT * FROM media_library WHERE id = :id LIMIT 1", ['id' => $id]);
    if (!$media) withu_json_response(['success' => false, 'message' => '媒体不存在'], 404);
    $result = withu_recognize_media($db, $media, !empty($body['force']));
    $freshMedia = $db->fetch("SELECT * FROM media_library WHERE id = :id LIMIT 1", ['id' => $id]);
    withu_json_response($result);
}

withu_json_response(['success' => false, 'message' => '未知操作'], 400);
