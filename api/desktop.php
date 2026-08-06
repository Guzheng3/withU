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
require_once __DIR__ . '/../core/MediaRecognition.php';
require_once __DIR__ . '/../core/Moderation.php';

migrate_schema_if_needed();

$auth = new Auth();
$db = Database::getInstance();
$action = (string)($_GET['action'] ?? $_POST['action'] ?? 'bootstrap');

/**
 * Desktop media features are private WithU features, not a public catalog API.
 * Login alone is not enough because the application only has two authorized
 * accounts; keep the same role boundary as the web media endpoints.
 */
function desktop_require_couple_user(Auth $auth): array
{
    if (!$auth->isLoggedIn()) {
        withu_json_response(['success' => false, 'message' => '请先登录桌面端'], 401);
    }
    $user = $auth->getCurrentUser();
    if (!$user || !in_array((string)($user['role'] ?? ''), ['user1', 'user2'], true)) {
        withu_json_response(['success' => false, 'message' => '仅 WithU 授权用户可以使用桌面端媒体功能'], 403);
    }
    return $user;
}

function desktop_payload(Auth $auth, Database $db): array
{
    $user = $auth->getCurrentUser();
    $isCoupleUser = $user && in_array((string)($user['role'] ?? ''), ['user1', 'user2'], true);
    $partner = null;
    $room = null;
    $watchRooms = 0;
    $recognizedMedia = 0;
    $mediaCount = 0;

    try {
        $mediaDb = withu_media_db();
        if ($isCoupleUser) {
            $mediaCountRow = $mediaDb->fetch("SELECT COUNT(*) AS c FROM media_library");
            $recognizedRow = $mediaDb->fetch("SELECT COUNT(*) AS c FROM media_library WHERE recognition_status = 'recognized'");
            $watchRoomsRow = $db->fetch("SELECT COUNT(*) AS c FROM watch_rooms");
            $mediaCount = (int)($mediaCountRow['c'] ?? 0);
            $recognizedMedia = (int)($recognizedRow['c'] ?? 0);
            $watchRooms = (int)($watchRoomsRow['c'] ?? 0);
        }

        if ($isCoupleUser) {
            $partner = $auth->getPartner();
            $defaultRoom = $db->fetch("SELECT * FROM watch_rooms WHERE room_code = 'WithU Watch' LIMIT 1");
            if ($defaultRoom) {
                $room = withu_media_merge_room($defaultRoom);
            }
        }
    } catch (Throwable $e) {
        // Best-effort bootstrap; keep the desktop client usable even if media
        // schema migration is still warming up.
    }

    $theme = function_exists('withu_theme_config') ? withu_theme_config() : ['preset' => 'pastel-couple', 'mode' => 'auto', 'custom' => false, 'colors' => []];
    $watchConfig = [
        'poll_interval_ms' => max(300, min(3000, (int)get_setting('watch_poll_interval_ms', '500'))),
        'sync_threshold_ms' => max(500, min(5000, (int)get_setting('watch_sync_threshold_ms', '1000'))),
        'heartbeat_interval_ms' => max(1000, min(10000, (int)get_setting('watch_heartbeat_interval_ms', '2500'))),
        'autoplay_enabled' => (string)get_setting('watch_autoplay_enabled', '1') === '1',
    ];

    return [
        'success' => true,
        'server_time' => withu_now(),
        'csrf_token' => csrf_token(),
        'logged_in' => (bool)$user,
        'user' => $user,
        'partner' => $partner,
        'theme' => $theme,
        'watch_config' => $watchConfig,
        'summary' => [
            'media_count' => $mediaCount,
            'recognized_media_count' => $recognizedMedia,
            'watch_room_count' => $watchRooms,
        ],
        'watch' => $room ? [
            'room_code' => $room['room_code'] ?? 'WithU Watch',
            'media_id' => (int)($room['media_id'] ?? 0),
            'file_name' => $room['file_name'] ?? '',
            'series_name' => $room['series_name'] ?? '',
            'episode_number' => $room['episode_number'] ?? null,
            'playback_state' => $room['playback_state'] ?? 'paused',
            'position_ms' => (int)($room['current_position_ms'] ?? 0),
            'speed' => (float)($room['speed'] ?? 1),
            'cover_url' => $room['cover_url'] ?? '',
            'url' => withu_media_stream_url($room),
        ] : null,
    ];
}

if ($action === 'login') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        withu_json_response(['success' => false, 'message' => '请求方式错误'], 405);
    }
    $body = withu_json_body();
    $username = trim((string)($body['username'] ?? ''));
    $password = (string)($body['password'] ?? '');
    if ($username === '' || $password === '') {
        withu_json_response(['success' => false, 'message' => '请输入账号和密码'], 400);
    }
    if (!$auth->login($username, $password)) {
        withu_json_response(['success' => false, 'message' => '账号或密码错误'], 401);
    }
    withu_json_response(desktop_payload($auth, $db));
}

if ($action === 'logout') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        withu_json_response(['success' => false, 'message' => '请求方式错误'], 405);
    }
    $body = withu_json_body();
    withu_require_json_csrf($body);
    $auth->logout();
    withu_json_response(['success' => true]);
}

if ($action === 'library') {
    desktop_require_couple_user($auth);
    try {
        $mediaDb = withu_media_db();
        $search = trim((string)($_GET['q'] ?? ''));
        $typeId = (int)($_GET['type_id'] ?? 0);
        if ($typeId < 0 || !in_array($typeId, [0, 1, 2, 3, 4], true)) {
            withu_json_response(['success' => false, 'message' => '影视分类无效'], 400);
        }
        $page = max(1, min(1000, (int)($_GET['page'] ?? 1)));
        $perPage = max(20, min(500, (int)($_GET['per_page'] ?? 240)));
        $offset = ($page - 1) * $perPage;
        $where = "recognition_status = 'recognized' AND media_type_id IN (1,2,3,4) AND source_url IS NOT NULL AND source_url <> ''";
        $params = [];
        if ($typeId > 0) {
            $where .= ' AND media_type_id = :media_type_id';
            $params['media_type_id'] = $typeId;
        }
        if ($search !== '') {
            $where .= " AND (series_name LIKE :search_series OR file_name LIKE :search_file OR episode_title LIKE :search_episode)";
            $like = '%' . $search . '%';
            $params['search_series'] = $like;
            $params['search_file'] = $like;
            $params['search_episode'] = $like;
        }
        $rows = $mediaDb->fetchAll(
            "SELECT id,source_key,source_url,direct_url,file_name,file_size,series_key,series_name,season_number,episode_number,episode_title,media_type_id,resolution,rating,tags,cast_names,summary,cover_url,recognition_status,updated_at
             FROM media_library
             WHERE {$where}
             ORDER BY CASE WHEN recognition_status = 'recognized' THEN 0 ELSE 1 END, updated_at DESC, id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        $groups = [];
        foreach ($rows as $row) {
            $row = withu_media_display_row($row);
            $key = (string)($row['series_key'] ?: $row['id']);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'key' => $key,
                    'id' => (int)$row['id'],
                    'name' => (string)($row['series_name'] ?: $row['file_name']),
                    'type_id' => (int)($row['media_type_id'] ?? 1),
                    'count' => 0,
                    'cover_url' => (string)($row['cover_url'] ?? ''),
                    'resolution' => (string)($row['resolution'] ?? ''),
                    'rating' => $row['rating'] ?? null,
                    'summary' => (string)($row['summary'] ?? ''),
                    'cast_names' => (string)($row['cast_names'] ?? ''),
                    'recognition_status' => (string)($row['recognition_status'] ?? 'pending'),
                    'updated_at' => (string)($row['updated_at'] ?? ''),
                    // 桌面端不能把资源站页面地址直接交给 libmpv；统一走
                    // WithU 的受保护解析入口，由后端按 WebDAV/采集来源选择
                    // 直链或 JSON 解析，并且不把临时签名地址写入数据库。
                    'play_url' => withu_media_resolve_url($row),
                    'episodes' => [],
                ];
            }
            $groups[$key]['count']++;
            $episodeLabel = !empty($row['episode_number'])
                ? '第 ' . (int)$row['episode_number'] . ' 集'
                : (string)($row['episode_title'] ?: $row['file_name']);
            $groups[$key]['episodes'][] = [
                'id' => (int)$row['id'],
                'label' => $episodeLabel,
                'file_name' => (string)$row['file_name'],
                'season_number' => $row['season_number'] ?? null,
                'episode_number' => $row['episode_number'] ?? null,
                'resolution' => (string)($row['resolution'] ?? ''),
                'recognition_status' => (string)($row['recognition_status'] ?? 'pending'),
                'play_url' => withu_media_resolve_url($row),
            ];
        }
        foreach ($groups as &$group) {
            usort($group['episodes'], static function (array $a, array $b): int {
                $season = (int)($a['season_number'] ?? 0) <=> (int)($b['season_number'] ?? 0);
                if ($season !== 0) return $season;
                $episode = (int)($a['episode_number'] ?? 0) <=> (int)($b['episode_number'] ?? 0);
                if ($episode !== 0) return $episode;
                return (int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0);
            });
            if (!empty($group['episodes'][0]['play_url'])) {
                $group['id'] = (int)$group['episodes'][0]['id'];
                $group['play_url'] = (string)$group['episodes'][0]['play_url'];
            }
        }
        unset($group);
        $items = array_slice(array_values($groups), 0, 120);
        withu_json_response([
            'success' => true,
            'items' => $items,
            'page' => $page,
            'per_page' => $perPage,
            'has_more' => count($rows) >= $perPage,
            'query' => $search,
            'type_id' => $typeId,
        ]);
    } catch (Throwable $e) {
        withu_json_response(['success' => false, 'message' => '媒体库读取失败'], 500);
    }
}

if ($action === 'history') {
    desktop_require_couple_user($auth);
    try {
        $historyRows = $db->fetchAll(
            "SELECT id,media_id,room_id,started_at,ended_at,watch_duration_ms,solo_duration_ms,together_duration_ms,last_position_ms,participants,updated_at
             FROM watch_history
             ORDER BY updated_at DESC, id DESC
             LIMIT 120"
        );
        $mediaMap = withu_media_fetch_many(array_map(static function (array $row): int {
            return (int)($row['media_id'] ?? 0);
        }, $historyRows));
        $items = [];
        foreach ($historyRows as $history) {
            $media = $mediaMap[(int)$history['media_id']] ?? [];
            $participants = json_decode((string)($history['participants'] ?? ''), true);
            if (!is_array($participants)) $participants = [];
            $display = withu_media_display_row($media);
            $items[] = [
                'id' => (int)$history['id'],
                'media_id' => (int)$history['media_id'],
                'file_name' => (string)($display['file_name'] ?? ('影片 #' . (int)$history['media_id'])),
                'series_name' => (string)($display['series_name'] ?? ''),
                'episode_number' => $display['episode_number'] ?? null,
                'episode_title' => (string)($display['episode_title'] ?? ''),
                'cover_url' => (string)($display['cover_url'] ?? ''),
                'resolution' => (string)($display['resolution'] ?? ''),
                'play_url' => $media ? withu_media_resolve_url($media) : '',
                'started_at' => (string)($history['started_at'] ?? ''),
                'updated_at' => (string)($history['updated_at'] ?? ''),
                'watch_duration_ms' => (int)($history['watch_duration_ms'] ?? 0),
                'solo_duration_ms' => (int)($history['solo_duration_ms'] ?? 0),
                'together_duration_ms' => (int)($history['together_duration_ms'] ?? 0),
                'last_position_ms' => (int)($history['last_position_ms'] ?? 0),
                'participants_count' => count(array_unique(array_map('intval', $participants))),
            ];
        }
        withu_json_response(['success' => true, 'items' => $items]);
    } catch (Throwable $e) {
        withu_json_response(['success' => false, 'message' => '观影历史读取失败'], 500);
    }
}

if ($action === 'article' || $action === 'album') {
    desktop_require_couple_user($auth);
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($id <= 0) {
        withu_json_response(['success' => false, 'message' => '内容编号无效'], 400);
    }
    try {
        if ($action === 'article') {
            $row = $db->fetch(
                "SELECT a.id, a.title, a.content, a.created_at, a.is_encrypted, u.nickname, u.avatar
                 FROM articles a LEFT JOIN users u ON u.id = a.user_id
                 WHERE a.id = :id AND a.status = 'published' LIMIT 1",
                ['id' => $id]
            );
            if (!$row) withu_json_response(['success' => false, 'message' => '文章不存在'], 404);
            if (!empty($row['is_encrypted']) && !$auth->isLoggedIn()) {
                withu_json_response(['success' => false, 'message' => '文章需要登录后查看'], 403);
            }
            withu_json_response([
                'success' => true,
                'type' => 'article',
                'item' => [
                    'id' => (int)$row['id'],
                    'title' => (string)$row['title'],
                    'content' => (string)($row['content'] ?? ''),
                    'created_at' => (string)($row['created_at'] ?? ''),
                    'nickname' => (string)($row['nickname'] ?? ''),
                    'avatar' => (string)($row['avatar'] ?? ''),
                ],
            ]);
        }

        $album = $db->fetch(
            "SELECT a.id, a.name, a.description, a.created_at, a.is_encrypted, u.nickname, u.avatar
             FROM albums a LEFT JOIN users u ON u.id = a.user_id
             WHERE a.id = :id LIMIT 1",
            ['id' => $id]
        );
        if (!$album) withu_json_response(['success' => false, 'message' => '相册不存在'], 404);
        $images = $db->fetchAll(
            "SELECT image_path, thumbnail_path, description, created_at
             FROM album_images WHERE album_id = :id ORDER BY created_at DESC, id DESC LIMIT 120",
            ['id' => $id]
        );
        $imagePayload = [];
        foreach ($images as $image) {
            $path = (string)($image['image_path'] ?? '');
            $thumb = (string)($image['thumbnail_path'] ?? $path);
            $imagePayload[] = [
                'url' => $path !== '' ? upload_url($path) : '',
                'thumbnail' => $thumb !== '' ? upload_url($thumb) : '',
                'description' => (string)($image['description'] ?? ''),
                'created_at' => (string)($image['created_at'] ?? ''),
            ];
        }
        withu_json_response([
            'success' => true,
            'type' => 'album',
            'item' => [
                'id' => (int)$album['id'],
                'name' => (string)$album['name'],
                'description' => (string)($album['description'] ?? ''),
                'created_at' => (string)($album['created_at'] ?? ''),
                'nickname' => (string)($album['nickname'] ?? ''),
                'avatar' => (string)($album['avatar'] ?? ''),
                'images' => $imagePayload,
            ],
        ]);
    } catch (Throwable $e) {
        withu_json_response(['success' => false, 'message' => '内容读取失败'], 500);
    }
}

if ($action === 'message') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        withu_json_response(['success' => false, 'message' => '请求方式错误'], 405);
    }
    desktop_require_couple_user($auth);
    $body = withu_json_body();
    withu_require_json_csrf($body);
    $content = trim((string)($body['content'] ?? ''));
    if ($content === '') withu_json_response(['success' => false, 'message' => '留言内容不能为空'], 400);
    if (mb_strlen($content, 'UTF-8') > 100) withu_json_response(['success' => false, 'message' => '留言不能超过 100 个字符'], 400);
    $user = $auth->getCurrentUser();
    $recent = $db->fetch(
        'SELECT id FROM messages WHERE user_id = :uid AND created_at >= :recent LIMIT 1',
        ['uid' => (int)$user['id'], 'recent' => date('Y-m-d H:i:s', time() - 60)]
    );
    if ($recent) withu_json_response(['success' => false, 'message' => '留言太频繁，请稍后再试'], 429);
    $moderation = withu_moderate_text($db, 'message', 0, $content);
    if ($moderation['blocked']) withu_json_response(['success' => false, 'message' => '内容触发安全规则，已拦截并提交后台复核'], 403);
    $messageId = (int)$db->insert('messages', [
        'user_id' => (int)$user['id'],
        'guest_nickname' => null,
        'guest_avatar' => null,
        'guest_qq' => null,
        'content' => $content,
        'is_public' => !empty($body['is_public']) ? 1 : 0,
        'status' => 'published',
        'created_at' => withu_now(),
    ]);
    if (!empty($moderation['log_id'])) withu_finish_moderation($db, (int)$moderation['log_id'], $messageId);
    withu_json_response(['success' => true, 'message_id' => $messageId]);
}

if ($action === 'bootstrap') {
    withu_json_response(desktop_payload($auth, $db));
}

withu_json_response(['success' => false, 'message' => '桌面端接口动作不存在'], 404);
