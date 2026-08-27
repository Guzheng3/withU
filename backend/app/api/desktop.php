<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/withu.php';
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
        if ($isCoupleUser) {
            $partner = $auth->getPartner();
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
        'watch' => null,
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
