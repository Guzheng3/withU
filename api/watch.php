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

migrate_schema_if_needed();
$auth = new Auth();
$user = withu_require_couple_user($auth);
$db = Database::getInstance();
$action = (string)($_GET['action'] ?? $_POST['action'] ?? 'state');
$body = withu_json_body();
$now = withu_now();
$nowUnixMs = (int)round(microtime(true) * 1000);
$presenceTimeout = max(3, min(30, (int)get_setting('watch_presence_timeout_sec', '8')));
$historyMinMs = withu_watch_history_min_ms();
$defaultRoomCode = 'WithU Watch';
$code = '';

function watch_room_or_fail($db, string $code): array {
    $row = $db->fetch("SELECT * FROM watch_rooms WHERE room_code = :code LIMIT 1", ['code' => $code]);
    if (!$row) withu_json_response(['success' => false, 'message' => '观影房间不存在'], 404);
    $row = withu_media_merge_room($row);
    if (empty($row['file_name'])) withu_json_response(['success' => false, 'message' => '影片不存在或影视资源库未初始化'], 404);
    return $row;
}

function watch_require_active_member($db, array $room, array $user): void {
    $member = $db->fetch(
        'SELECT user_id FROM watch_room_members WHERE room_id = :room_id AND user_id = :user_id AND left_at IS NULL LIMIT 1',
        ['room_id' => (int)$room['id'], 'user_id' => (int)$user['id']]
    );
    if (!$member) {
        withu_json_response(['success' => false, 'message' => '当前用户未加入该共看房间'], 403);
    }
}

if ($action === 'list') {
    $rows = withu_media_db()->fetchAll("SELECT id, file_name, direct_url, source_url, cover_url, duration_ms, resolution, recognition_status FROM media_library FORCE INDEX (idx_media_status_updated) WHERE recognition_status = 'recognized' ORDER BY updated_at DESC, id DESC LIMIT 200");
    foreach ($rows as &$row) {
        $row['url'] = withu_media_url($row);
        $row['player_mode'] = withu_media_player_mode($row);
        $row['player_code'] = withu_media_player_code($row);
    }
    withu_json_response(['success' => true, 'items' => $rows]);
}

if ($action === 'create') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') withu_json_response(['success' => false, 'message' => '请求方式错误'], 405);
    withu_require_json_csrf($body);
    $mediaId = (int)($body['media_id'] ?? 0);
    $media = withu_media_fetch($mediaId);
    if (!$media) withu_json_response(['success' => false, 'message' => '请选择媒体'], 400);
    $code = strtoupper(substr(withu_token(8), 0, 10));
    $roomId = $db->insert('watch_rooms', ['room_code' => $code, 'media_id' => $mediaId, 'host_user_id' => $user['id'], 'playback_state' => 'paused', 'current_position_ms' => 0, 'speed' => 1.00, 'last_sync_at' => $now, 'last_sync_unix_ms' => $nowUnixMs, 'created_at' => $now]);
    $db->insert('watch_room_members', ['room_id' => $roomId, 'user_id' => $user['id'], 'joined_at' => $now, 'last_seen_at' => $now]);
    $historyId = $db->insert('watch_history', ['media_id' => $mediaId, 'room_id' => $roomId, 'started_at' => $now, 'watch_duration_ms' => 0, 'solo_duration_ms' => 0, 'together_duration_ms' => 0, 'initiated_by' => $user['id'], 'last_position_ms' => 0, 'participants' => json_encode([$user['id']]), 'created_at' => $now, 'updated_at' => $now]);
    withu_json_response(['success' => true, 'room_code' => $code, 'room_id' => (int)$roomId, 'history_id' => (int)$historyId]);
}

// 固定默认房间：页面打开即加入，不需要用户手动创建或输入房间号。
if ($action === 'default') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') withu_json_response(['success' => false, 'message' => '请求方式错误'], 405);
    withu_require_json_csrf($body);
    $requestedMediaId = (int)($body['media_id'] ?? 0);
    if ($requestedMediaId > 0 && !withu_media_exists($requestedMediaId)) {
        withu_json_response(['success' => false, 'message' => '影片不存在'], 404);
    }
    $defaultRoom = $db->fetch("SELECT * FROM watch_rooms WHERE room_code = :code LIMIT 1", ['code' => $defaultRoomCode]);
    if (!$defaultRoom) {
        if ($requestedMediaId <= 0) withu_json_response(['success' => false, 'message' => '媒体库暂无影片'], 400);
        $media = withu_media_fetch($requestedMediaId);
        if (!$media) withu_json_response(['success' => false, 'message' => '影片不存在'], 404);
        $roomId = $db->insert('watch_rooms', ['room_code' => $defaultRoomCode, 'media_id' => $requestedMediaId, 'host_user_id' => $user['id'], 'playback_state' => 'paused', 'current_position_ms' => 0, 'speed' => 1.00, 'last_sync_at' => $now, 'last_sync_unix_ms' => $nowUnixMs, 'created_at' => $now]);
        $defaultRoom = $db->fetch("SELECT * FROM watch_rooms WHERE id = :id LIMIT 1", ['id' => $roomId]);
        $db->insert('watch_history', ['media_id' => $requestedMediaId, 'room_id' => $roomId, 'started_at' => $now, 'watch_duration_ms' => 0, 'solo_duration_ms' => 0, 'together_duration_ms' => 0, 'initiated_by' => $user['id'], 'last_position_ms' => 0, 'participants' => json_encode([$user['id']]), 'created_at' => $now, 'updated_at' => $now]);
    } else {
        $alreadyMember = $db->fetch("SELECT user_id FROM watch_room_members WHERE room_id = :rid AND user_id = :uid AND left_at IS NULL LIMIT 1", ['rid' => $defaultRoom['id'], 'uid' => $user['id']]);
        $activeOther = $db->fetch("SELECT COUNT(*) AS c FROM watch_room_members rm JOIN users u ON u.id = rm.user_id WHERE rm.room_id = :active_rid AND rm.user_id <> :active_uid AND u.status = 'active' AND u.role IN ('user1','user2') AND rm.last_seen_at >= DATE_SUB(:active_now, INTERVAL {$presenceTimeout} SECOND) AND rm.left_at IS NULL", ['active_rid' => $defaultRoom['id'], 'active_uid' => $user['id'], 'active_now' => $now]);
        $hasJoinedTogether = (bool)$alreadyMember;
        if ((int)($activeOther['c'] ?? 0) > 0 && $requestedMediaId > 0 && !$hasJoinedTogether) {
            $currentMedia = withu_media_fetch((int)$defaultRoom['media_id']);
            $requestedMedia = withu_media_fetch($requestedMediaId);
            withu_json_response(['success' => true, 'choice_required' => true, 'room_code' => $defaultRoomCode, 'current_media_id' => (int)$defaultRoom['media_id'], 'current_file_name' => $currentMedia['file_name'] ?? '当前影片', 'current_cover_url' => $currentMedia['cover_url'] ?? null, 'requested_media_id' => $requestedMedia ? $requestedMediaId : 0, 'requested_file_name' => $requestedMedia['file_name'] ?? '']);
        }
    }
    if ($defaultRoom && $requestedMediaId > 0 && (int)$defaultRoom['media_id'] !== $requestedMediaId && ((int)($activeOther['c'] ?? 0) === 0 || !empty($alreadyMember))) {
        $db->update('watch_rooms', ['media_id' => $requestedMediaId, 'playback_state' => 'paused', 'current_position_ms' => 0, 'speed' => 1.00, 'last_sync_at' => $now, 'last_sync_unix_ms' => $nowUnixMs, 'ended_at' => null], 'id = :id', ['id' => $defaultRoom['id']]);
        $db->insert('watch_history', ['media_id' => $requestedMediaId, 'room_id' => $defaultRoom['id'], 'started_at' => $now, 'watch_duration_ms' => 0, 'solo_duration_ms' => 0, 'together_duration_ms' => 0, 'initiated_by' => $user['id'], 'last_position_ms' => 0, 'participants' => json_encode([$user['id']]), 'created_at' => $now, 'updated_at' => $now]);
    }
    $code = $defaultRoomCode;
}

if ($action === 'choose') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') withu_json_response(['success' => false, 'message' => '请求方式错误'], 405);
    withu_require_json_csrf($body);
    $choice = (string)($body['choice'] ?? '');
    if ($choice === 'together') {
        $code = $defaultRoomCode;
        $requestedMediaId = (int)($body['media_id'] ?? 0);
        if ($requestedMediaId > 0) {
            $media = withu_media_fetch($requestedMediaId);
            if (!$media) withu_json_response(['success' => false, 'message' => '影片不存在'], 404);
            $roomToUpdate = $db->fetch("SELECT * FROM watch_rooms WHERE room_code = :code LIMIT 1", ['code' => $defaultRoomCode]);
            if ($roomToUpdate && (int)$roomToUpdate['media_id'] !== $requestedMediaId) {
                $db->update('watch_rooms', ['media_id' => $requestedMediaId, 'playback_state' => 'paused', 'current_position_ms' => 0, 'speed' => 1.00, 'last_sync_at' => $now, 'last_sync_unix_ms' => $nowUnixMs, 'ended_at' => null], 'id = :id', ['id' => $roomToUpdate['id']]);
                $db->insert('watch_history', ['media_id' => $requestedMediaId, 'room_id' => $roomToUpdate['id'], 'started_at' => $now, 'watch_duration_ms' => 0, 'solo_duration_ms' => 0, 'together_duration_ms' => 0, 'initiated_by' => $user['id'], 'last_position_ms' => 0, 'participants' => json_encode([$user['id']]), 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        $action = 'join';
    } elseif ($choice === 'solo') {
        $mediaId = (int)($body['media_id'] ?? 0);
        $media = withu_media_fetch($mediaId);
        if (!$media) withu_json_response(['success' => false, 'message' => '独立观看影片不存在'], 404);
        $sharedRoom = $db->fetch("SELECT id FROM watch_rooms WHERE room_code = :code LIMIT 1", ['code' => $defaultRoomCode]);
        if ($sharedRoom) {
            $db->update('watch_room_members', ['left_at' => $now], 'room_id = :rid AND user_id = :uid', ['rid' => $sharedRoom['id'], 'uid' => $user['id']]);
        }
        $code = 'SOLO-' . (int)$user['id'] . '-' . strtoupper(substr(withu_token(5), 0, 8));
        $roomId = $db->insert('watch_rooms', ['room_code' => $code, 'media_id' => $mediaId, 'host_user_id' => $user['id'], 'playback_state' => 'paused', 'current_position_ms' => 0, 'speed' => 1.00, 'last_sync_at' => $now, 'created_at' => $now]);
        $db->insert('watch_room_members', ['room_id' => $roomId, 'user_id' => $user['id'], 'joined_at' => $now, 'last_seen_at' => $now]);
        $db->insert('watch_history', ['media_id' => $mediaId, 'room_id' => $roomId, 'started_at' => $now, 'watch_duration_ms' => 0, 'solo_duration_ms' => 0, 'together_duration_ms' => 0, 'initiated_by' => $user['id'], 'last_position_ms' => 0, 'participants' => json_encode([$user['id']]), 'created_at' => $now, 'updated_at' => $now]);
        $action = 'solo';
    } else {
        withu_json_response(['success' => false, 'message' => '请选择一起看或自己看'], 400);
    }
}

if ($action === 'end_together') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') withu_json_response(['success' => false, 'message' => '请求方式错误'], 405);
    withu_require_json_csrf($body);
    $sharedRoom = $db->fetch("SELECT id FROM watch_rooms WHERE room_code = :code LIMIT 1", ['code' => $defaultRoomCode]);
    if ($sharedRoom) {
        $db->update('watch_room_members', ['left_at' => $now], 'room_id = :rid AND user_id = :uid', ['rid' => $sharedRoom['id'], 'uid' => $user['id']]);
    }
    withu_json_response(['success' => true, 'mode' => 'solo', 'message' => '已结束共看，当前仅自己观看']);
}

$code = trim((string)($_GET['room'] ?? $_POST['room_code'] ?? $body['room_code'] ?? $code));
if ($code === '') withu_json_response(['success' => false, 'message' => '缺少房间号'], 400);
// Heartbeats only update presence. Avoid joining media metadata on the most
// frequent request in the player lifecycle.
$room = $action === 'heartbeat'
    ? $db->fetch("SELECT * FROM watch_rooms WHERE room_code = :code LIMIT 1", ['code' => $code])
    : watch_room_or_fail($db, $code);
if (!$room) withu_json_response(['success' => false, 'message' => '观影房间不存在'], 404);

// Polling and playback events must be tied to a live membership record.
// This prevents a stale tab that has already left together-view from
// changing the other user's room state with the predictable default code.
if (in_array($action, ['state', 'poll', 'event'], true)) {
    watch_require_active_member($db, $room, $user);
}

if ($action === 'join' || $action === 'default') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') withu_require_json_csrf($body);
    $db->query("INSERT INTO watch_room_members (room_id,user_id,joined_at,last_seen_at,left_at) VALUES (:rid,:uid,:joined_at,:seen_at,NULL) ON DUPLICATE KEY UPDATE last_seen_at = :seen_update, left_at = NULL", ['rid' => $room['id'], 'uid' => $user['id'], 'joined_at' => $now, 'seen_at' => $now, 'seen_update' => $now]);
}

// Heartbeat is liveness only. It must never write playback position, speed,
// room timestamps, or a watch event that another client could apply.
if ($action === 'heartbeat') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') withu_json_response(['success' => false, 'message' => '请求方式错误'], 405);
    withu_require_json_csrf($body);
    $db->query("INSERT INTO watch_room_members (room_id,user_id,joined_at,last_seen_at,left_at) VALUES (:rid,:uid,:joined_at,:seen_at,NULL) ON DUPLICATE KEY UPDATE last_seen_at = :seen_update, left_at = NULL", ['rid' => $room['id'], 'uid' => $user['id'], 'joined_at' => $now, 'seen_at' => $now, 'seen_update' => $now]);
    withu_json_response(['success' => true, 'server_now_ms' => $nowUnixMs]);
}

if ($action === 'state' || $action === 'poll' || $action === 'join' || $action === 'default' || $action === 'solo') {
    $members = $db->fetchAll("SELECT rm.user_id, rm.joined_at, rm.last_seen_at FROM watch_room_members rm JOIN users u ON u.id = rm.user_id WHERE rm.room_id = :rid AND u.status = 'active' AND u.role IN ('user1','user2') AND rm.last_seen_at >= DATE_SUB(:now, INTERVAL {$presenceTimeout} SECOND) AND rm.left_at IS NULL", ['rid' => $room['id'], 'now' => $now]);
    $lastEvent = $db->fetch("SELECT id FROM watch_events WHERE room_id = :rid ORDER BY id DESC LIMIT 1", ['rid' => $room['id']]);
    $events = [];
    $since = max(0, (int)($_GET['since'] ?? $body['since'] ?? 0));
    if ($action === 'poll' && $since > 0) $events = $db->fetchAll("SELECT id,user_id,event_type,position_ms,speed,payload,created_at FROM watch_events WHERE room_id = :rid AND id > :since ORDER BY id ASC LIMIT 20", ['rid' => $room['id'], 'since' => $since]);
    $lastSyncMs = (int)($room['last_sync_unix_ms'] ?? 0);
    if ($lastSyncMs <= 0) $lastSyncMs = (int)(strtotime((string)$room['last_sync_at']) * 1000);
    withu_json_response(['success' => true, 'mode' => $action === 'solo' ? 'solo' : 'together', 'server_now_ms' => $nowUnixMs, 'room' => ['code' => $room['room_code'], 'media_id' => (int)$room['media_id'], 'file_name' => $room['file_name'], 'series_name' => $room['series_name'], 'season_number' => $room['season_number'], 'episode_number' => $room['episode_number'], 'episode_title' => $room['episode_title'], 'url' => withu_media_resolve_url($room), 'player_mode' => withu_media_player_mode($room), 'player_code' => withu_media_player_code($room), 'cover_url' => $room['cover_url'], 'duration_ms' => $room['duration_ms'], 'resolution' => $room['resolution'], 'rating' => $room['rating'], 'cast_names' => $room['cast_names'], 'summary' => $room['summary'], 'tags' => $room['tags'], 'douban_id' => $room['douban_id'], 'playback_state' => $room['playback_state'], 'position_ms' => (int)$room['current_position_ms'], 'speed' => (float)$room['speed'], 'last_sync_unix_ms' => $lastSyncMs], 'members' => $members, 'events' => $events, 'last_event_id' => (int)($lastEvent['id'] ?? 0)]);
}

if ($action === 'event') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') withu_json_response(['success' => false, 'message' => '请求方式错误'], 405);
    withu_require_json_csrf($body);
    $type = trim((string)($body['event_type'] ?? 'heartbeat'));
    $allowed = ['play','pause','seek','speed','leave','voice_offer','voice_answer','voice_candidate','voice_leave','chat_message'];
    if (!in_array($type, $allowed, true)) withu_json_response(['success' => false, 'message' => '不支持的同步事件'], 400);
    $position = max(0, (int)($body['position_ms'] ?? 0));
    $speed = (float)($body['speed'] ?? $room['speed']);
    $speed = max(0.5, min(3.0, round($speed * 2) / 2));
    $oldPosition = (int)$room['current_position_ms'];
    $oldState = (string)$room['playback_state'];

    // Match SyncTV's authoritative state model. The client timestamp lets us
    // account for request transit, bounded to avoid stale tabs jumping ahead.
    $clientTimestampMs = (int)($body['client_timestamp_ms'] ?? 0);
    $timeDiffMs = $clientTimestampMs > 0 ? max(0, min(1500, $nowUnixMs - $clientTimestampMs)) : 0;
    $isPlaybackEvent = in_array($type, ['play', 'pause', 'seek', 'speed', 'leave'], true);
    $delta = 0;
    if ($isPlaybackEvent) {
        $state = $type === 'play' ? 'playing' : (($type === 'pause' || $type === 'leave') ? 'paused' : $oldState);
        if ($state === 'playing') {
            $position = (int)round($position + ($timeDiffMs * $speed));
        } else {
            $position = max(0, $position);
        }
        $delta = ($oldState === 'playing' && $position >= $oldPosition && $position - $oldPosition < 12000) ? $position - $oldPosition : 0;
        $db->update('watch_rooms', ['playback_state' => $state, 'current_position_ms' => $position, 'speed' => number_format($speed, 2, '.', ''), 'last_sync_at' => $now, 'last_sync_unix_ms' => $nowUnixMs], 'id = :id', ['id' => $room['id']]);
    }
    $db->query("INSERT INTO watch_room_members (room_id,user_id,joined_at,last_seen_at,left_at) VALUES (:rid,:uid,:joined_at,:seen_at,NULL) ON DUPLICATE KEY UPDATE last_seen_at = :seen_update, left_at = IF(:leave_flag = 1, :left_value, NULL)", ['rid' => $room['id'], 'uid' => $user['id'], 'joined_at' => $now, 'seen_at' => $now, 'seen_update' => $now, 'leave_flag' => $type === 'leave' ? 1 : 0, 'left_value' => $now]);
    $eventId = $db->insert('watch_events', ['room_id' => $room['id'], 'user_id' => $user['id'], 'event_type' => $type, 'position_ms' => $position, 'speed' => number_format($speed, 2, '.', ''), 'payload' => json_encode($body, JSON_UNESCAPED_UNICODE), 'created_at' => $now]);
    if ($delta > 0) {
        $online = $db->count('watch_room_members', "room_id = :rid AND last_seen_at >= DATE_SUB(:now, INTERVAL {$presenceTimeout} SECOND) AND left_at IS NULL", ['rid' => $room['id'], 'now' => $now]);
        $history = $db->fetch("SELECT id,watch_duration_ms,solo_duration_ms,together_duration_ms,participants FROM watch_history WHERE room_id = :rid ORDER BY id DESC LIMIT 1", ['rid' => $room['id']]);
        if ($history) {
            $participants = json_decode((string)$history['participants'], true); if (!is_array($participants)) $participants = [];
            if (!in_array((int)$user['id'], $participants, true)) $participants[] = (int)$user['id'];
            $update = ['watch_duration_ms' => (int)$history['watch_duration_ms'] + $delta, 'solo_duration_ms' => (int)$history['solo_duration_ms'] + ($online >= 2 ? 0 : $delta), 'together_duration_ms' => (int)$history['together_duration_ms'] + ($online >= 2 ? $delta : 0), 'last_position_ms' => $position, 'participants' => json_encode($participants), 'updated_at' => $now];
            $db->update('watch_history', $update, 'id = :id', ['id' => $history['id']]);
        }
    }
    if ($type === 'leave') {
        $history = $db->fetch("SELECT id,watch_duration_ms FROM watch_history WHERE room_id = :rid ORDER BY id DESC LIMIT 1", ['rid' => $room['id']]);
        if ($history) {
            if ((int)$history['watch_duration_ms'] < $historyMinMs) {
                $db->delete('watch_history', 'id = :id', ['id' => (int)$history['id']]);
            } else {
                $db->update('watch_history', ['ended_at' => $now, 'updated_at' => $now], 'id = :id', ['id' => (int)$history['id']]);
            }
        }
    }
    withu_json_response(['success' => true, 'event_id' => (int)$eventId]);
}

withu_json_response(['success' => false, 'message' => '未知操作'], 400);
