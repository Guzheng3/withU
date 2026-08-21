<?php
// 设置 UTF-8 编码
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// 如果尚未安装或缺少数据库配置，则引导到安装向导
$rootPath = __DIR__;
if (!file_exists($rootPath . '/config/database.php') || !file_exists($rootPath . '/.installed')) {
    header('Location: /install.php');
    exit;
}

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/withu.php';
require_once __DIR__ . '/core/MediaDatabase.php';
require_once __DIR__ . '/core/MediaSchema.php';
require_once __DIR__ . '/core/MediaRepository.php';
require_once __DIR__ . '/core/MediaRecognition.php';

$auth = new Auth();
$db   = Database::getInstance();

// 设置页面标题
$pageTitle = '首页';

// 当前用户与另一半
$currentUser = $auth->getCurrentUser();
$partner     = $currentUser ? $auth->getPartner() : null;

$watchGroups = [];
$watchRecent = [];
$watchNew = [];
$watchRoomSummary = null;
if ($currentUser) {
    try {
        $mediaDb = withu_media_db();
        // 首页只做预览，不能为了 18w+ 资源把整库加载进 PHP 和浏览器。
        // "最新添加" reflects the source folder's creation time, not a later
        // metadata refresh, link check, or recognition update.  This keeps
        // the couple-home preview consistent with the main media library.
        $watchRows = $mediaDb->fetchAll("SELECT * FROM media_library FORCE INDEX (idx_media_status_added) WHERE recognition_status = 'recognized' AND media_type_id IN (1,2,3,4) ORDER BY added_at DESC, id DESC LIMIT 180");
        foreach ($watchRows as $row) {
            $row = withu_media_display_row($row);
            $key = (string)($row['series_key'] ?: $row['id']);
            if (!isset($watchGroups[$key])) $watchGroups[$key] = ['name' => $row['series_name'], 'cover_url' => $row['cover_url'], 'items' => []];
            if (empty($watchGroups[$key]['cover_url']) && !empty($row['cover_url'])) $watchGroups[$key]['cover_url'] = $row['cover_url'];
            $watchGroups[$key]['items'][] = $row;
            if (count($watchGroups) >= 18) break;
        }
        foreach ($watchGroups as &$watchGroup) {
            usort($watchGroup['items'], function (array $a, array $b): int {
                return [(int)($a['season_number'] ?? 0), (int)($a['episode_number'] ?? 0), (int)$a['id']] <=> [(int)($b['season_number'] ?? 0), (int)($b['episode_number'] ?? 0), (int)$b['id']];
            });
            if (empty($watchGroup['cover_url'])) $watchGroup['cover_url'] = '/assets/images/Coverloaderror.jpg';
        }
        unset($watchGroup);
        // One card per series: episodes belong in the playback picker, not
        // in the latest-added shelf.  Groups are already discovered in the
        // required folder-time order above; pick the sorted first episode as
        // the stable entry point for direct playback.
        foreach (array_slice(array_values($watchGroups), 0, 6) as $watchGroup) {
            if (!empty($watchGroup['items'][0])) {
                $watchNew[] = $watchGroup['items'][0];
            }
        }

        $historyRows = $db->fetchAll("SELECT wh.*, COALESCE(wr.source, 'library') AS history_source, COALESCE(wr.source_episode, 0) AS history_source_episode, wh.updated_at AS watch_updated_at FROM watch_history wh LEFT JOIN watch_rooms wr ON wr.id = wh.room_id WHERE wh.watch_duration_ms >= :min_ms ORDER BY wh.updated_at DESC, wh.id DESC LIMIT 24", ['min_ms' => withu_watch_history_min_ms()]);
        $libraryRows = array_filter($historyRows, static function (array $row): bool { return (string)($row['history_source'] ?? 'library') !== 'strm'; });
        $historyMedia = withu_media_fetch_many(array_map(static function (array $row): int { return (int)$row['media_id']; }, $libraryRows));
        $strmHistory = [];
        $strmFetch = static function (int $id) use (&$strmHistory): array {
            if (isset($strmHistory[$id])) return $strmHistory[$id];
            $jwtPath = dirname(__DIR__) . '/runtime/strm/jwt.txt';
            $secret = is_file($jwtPath) ? trim((string)file_get_contents($jwtPath)) : '';
            if ($id <= 0 || $secret === '') return $strmHistory[$id] = [];
            $b64u = static function (string $value): string { return rtrim(strtr(base64_encode($value), '+/', '-_'), '='); };
            $now = time();
            $header = $b64u(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
            $payload = $b64u(json_encode(['sub' => 'withu_admin', 'iat' => $now, 'exp' => $now + 600]));
            $signature = $b64u(hash_hmac('sha256', $header . '.' . $payload, $secret, true));
            $ch = curl_init('http://127.0.0.1:8081/api/media-library/' . $id);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $header . '.' . $payload . '.' . $signature], CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 10]);
            $body = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            $response = $status === 200 ? json_decode((string)$body, true) : null;
            return $strmHistory[$id] = (($response['code'] ?? 0) === 200 && is_array($response['data'] ?? null)) ? $response['data'] : [];
        };
        $recentSeen = [];
        foreach ($historyRows as $historyRow) {
            $mediaId = (int)$historyRow['media_id'];
            if ((string)($historyRow['history_source'] ?? 'library') === 'strm') {
                $meta = $strmFetch($mediaId);
                $episodeId = (int)($historyRow['history_source_episode'] ?? 0);
                $episode = null;
                foreach ((array)($meta['episodes'] ?? []) as $candidate) {
                    if ((int)($candidate['id'] ?? 0) === $episodeId) { $episode = $candidate; break; }
                }
             $mediaRow = $meta ? ['id' => $mediaId, 'file_name' => (string)($episode['sourceFileName'] ?? ($meta['title'] ?? '')), 'series_name' => (string)($meta['title'] ?? 'strm 媒体'), 'series_key' => 'strm-title-' . preg_replace('/\s+/u', '', mb_strtolower((string)($meta['title'] ?? 'strm 媒体'), 'UTF-8')), 'episode_number' => (int)($episode['episodeNo'] ?? 0), 'duration_ms' => 0, 'cover_url' => (string)($meta['posterUrl'] ?? ''), 'source' => 'strm'] : null;
            } else {
                $mediaRow = $historyMedia[$mediaId] ?? null;
            }
            if (!$mediaRow) continue;
            $item = withu_media_display_row(array_merge($mediaRow, $historyRow));
            $key = (string)($item['history_source'] ?? 'library') . ':' . (string)($item['series_key'] ?: $item['id']);
            if (isset($recentSeen[$key])) continue;
            $recentSeen[$key] = true;
            $watchRecent[] = $item;
            if (count($watchRecent) >= 6) break;
        }

        $activeRoom = $db->fetch("SELECT room_code, media_id, playback_state, current_position_ms, speed, last_sync_at FROM watch_rooms WHERE room_code = :code LIMIT 1", ['code' => 'WithU Watch']);
        if ($activeRoom) {
            $activeRoom = withu_media_merge_room($activeRoom);
            if (!empty($activeRoom['file_name'])) {
                $presence = $db->fetch("SELECT COUNT(*) AS online_members, MAX(CASE WHEN rm.user_id = :uid AND rm.left_at IS NULL THEN 1 ELSE 0 END) AS joined FROM watch_room_members rm JOIN users u ON u.id = rm.user_id WHERE rm.room_id = (SELECT id FROM watch_rooms WHERE room_code = :code LIMIT 1) AND u.status = 'active' AND u.role IN ('user1','user2') AND rm.last_seen_at >= DATE_SUB(:now, INTERVAL 8 SECOND) AND rm.left_at IS NULL", ['uid' => (int)$currentUser['id'], 'code' => 'WithU Watch', 'now' => date('Y-m-d H:i:s')]);
                $activeRoom['online_members'] = (int)($presence['online_members'] ?? 0);
                $activeRoom['joined'] = (int)($presence['joined'] ?? 0);
                $watchRoomSummary = $activeRoom;
            }
        }
    } catch (Throwable $e) {
        $watchGroups = [];
        $watchRecent = [];
        $watchNew = [];
        $watchRoomSummary = null;
    }
}

// 获取恋爱开始日期（允许为空：未设置）
$loveDateRow   = $db->fetch("SELECT value FROM settings WHERE `key` = 'love_date'");
$loveStartDate = ($loveDateRow && !empty($loveDateRow['value']))
    ? $loveDateRow['value']
    : null;
$loveDateSet   = $loveStartDate !== null;

// 仅在已设置恋爱开始日期时，计算统计信息；未设置时前端显示「未设置」提示
if ($loveDateSet) {
    // 计算在一起的天数
    $daysTogether = daysBetween($loveStartDate, date('Y-m-d'));

    // 计算距离下一次周年纪念日的天数
    $loveStart = new DateTime($loveStartDate);
    $today     = new DateTime('today');

    $yearsTogether = $loveStart->diff($today)->y;
    $nextAnniversary = (clone $loveStart)->modify('+' . ($yearsTogether + 1) . ' years');
    if ($nextAnniversary <= $today) {
        $nextAnniversary = (clone $loveStart)->modify('+' . ($yearsTogether + 2) . ' years');
    }
    $daysToNextAnniversary = $today->diff($nextAnniversary)->days;
} else {
    $daysTogether          = 0;
    $daysToNextAnniversary = null;
}

// 最新文章
$articles = $db->fetchAll(
    "SELECT a.*, u.nickname, u.avatar 
     FROM articles a 
     LEFT JOIN users u ON a.user_id = u.id 
     WHERE a.status = 'published' 
     ORDER BY a.created_at DESC 
     LIMIT 3"
);

// 最新事件
$events = $db->fetchAll(
    "SELECT e.*, u.nickname 
     FROM events e 
     LEFT JOIN users u ON e.user_id = u.id 
     ORDER BY e.sort_order ASC, e.event_date DESC, e.created_at DESC 
     LIMIT 14"
);

// 最新相册（首页显示两行左右的相册卡片）
$albums = $db->fetchAll(
    "SELECT a.*, u.nickname, u.avatar,
            (
                (SELECT COUNT(*) FROM album_images WHERE album_id = a.id) +
                (SELECT COUNT(*) FROM album_videos  WHERE album_id = a.id)
            ) AS image_count
     FROM albums a 
     LEFT JOIN users u ON a.user_id = u.id 
     ORDER BY a.created_at DESC 
     LIMIT 6"
);

// 统计数据（总数）
$totals = $db->fetch("SELECT
    (SELECT COUNT(*) FROM articles WHERE status = 'published') AS articles,
    (SELECT COUNT(*) FROM events) AS events,
    (SELECT COUNT(*) FROM albums) AS albums,
    (SELECT COUNT(*) FROM messages WHERE status = 'published') AS messages");

$stats = [
    'articles' => (int) ($totals['articles'] ?? 0),
    'events'   => (int) ($totals['events'] ?? 0),
    'albums'   => (int) ($totals['albums'] ?? 0),
    'messages' => (int) ($totals['messages'] ?? 0),
];

// 最新公开留言（首页预览）
$latestMessages = $db->fetchAll(
    "SELECT m.*, 
            COALESCE(u.nickname, m.guest_nickname, '匿名用户') AS nickname,
            COALESCE(u.avatar, m.guest_avatar, '/assets/images/default-avatar.svg') AS avatar
     FROM messages m 
     LEFT JOIN users u ON m.user_id = u.id 
     WHERE m.status = 'published' AND m.is_public = 1
     ORDER BY m.created_at DESC 
     LIMIT 12"
);

include __DIR__ . '/views/header.php';
include __DIR__ . '/views/home.php';
include __DIR__ . '/views/footer.php';
