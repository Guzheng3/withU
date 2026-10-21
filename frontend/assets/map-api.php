<?php
/**
 * 地图 API 接口
 * 相册、留言数据从数据库读取，其他数据从 map-all.json 读取
 * 支持 module=album_photos 查询相册照片
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../services/message-common.php';

// 登录态（后端 Auth）：加密相册与私密留言只对已登录情侣可见
$mapApiLoggedIn = false;
try {
    $mapApiRoot = dirname(__DIR__, 2) . '/backend/app';
    if (is_file($mapApiRoot . '/config/database.php') && is_file($mapApiRoot . '/.installed')) {
        require_once $mapApiRoot . '/config/config.php';
        require_once $mapApiRoot . '/core/Database.php';
        require_once $mapApiRoot . '/core/Auth.php';
        $mapApiAuth = new Auth();
        $mapApiLoggedIn = $mapApiAuth->isLoggedIn();
    }
} catch (Throwable $e) {
    // 登录态不可用时按未登录处理（只返回公开数据）
}

// 加载基础数据（lovers, milestones, events, moments）
$mapFile = __DIR__ . '/../services/map-all.json';
$mapData = [];
if (file_exists($mapFile)) {
    $mapData = json_decode(file_get_contents($mapFile), true) ?: [];
}

// 处理 album_photos 模块请求
$module = $_GET['module'] ?? '';
if ($module === 'album_photos') {
    $code = $_GET['code'] ?? '';
    $photos = [];
    try {
        $rootPath = dirname(__DIR__, 2) . '/backend/app';
        if (is_file($rootPath . '/config/database.php') && is_file($rootPath . '/.installed')) {
            require_once $rootPath . '/config/config.php';
            require_once $rootPath . '/core/Database.php';
            $db = Database::getInstance();

            // 加密相册：未登录直接拒绝，不返回照片与 GPS 坐标
            $albumInfo = $db->fetch("SELECT is_encrypted FROM albums WHERE id = :id LIMIT 1", ['id' => (int)$code]);
            if ($albumInfo && (int) ($albumInfo['is_encrypted'] ?? 0) === 1 && !$mapApiLoggedIn) {
                echo json_encode(['code' => 403, 'msg' => '该相册仅对情侣可见，请先登录'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }

            $photos = $db->fetchAll(
                "SELECT id, image_path, thumbnail_path, description, location_name, latitude, longitude, created_at
                 FROM album_images
                 WHERE album_id = :album_id
                 ORDER BY sort_order ASC, created_at ASC",
                ['album_id' => (int)$code]
            );
        }
    } catch (Throwable $e) {
        // 数据库不可用时返回空
    }

    echo json_encode(['code' => 200, 'data' => $photos], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// 尝试从数据库加载相册数据
$albums = [];
try {
    $rootPath = dirname(__DIR__, 2) . '/backend/app';
    if (is_file($rootPath . '/config/database.php') && is_file($rootPath . '/.installed')) {
        require_once $rootPath . '/config/config.php';
        require_once $rootPath . '/core/Database.php';
        $db = Database::getInstance();

        // 未登录：过滤加密相册，只返回公开相册
        $encryptedFilter = $mapApiLoggedIn ? '' : " AND (a.is_encrypted = 0 OR a.is_encrypted IS NULL)";
        $rows = $db->fetchAll(
            "SELECT a.*, u.nickname AS owner_name, u.avatar AS owner_avatar
             FROM albums a
             JOIN users u ON u.id = a.user_id
             WHERE u.status = 'active'{$encryptedFilter}
             ORDER BY a.created_at DESC"
        );

        foreach ($rows as $row) {
            $albums[] = [
                'name'        => $row['name'],
                'city'        => $row['location_name'] ?? '',
                'coords'      => [(float)$row['longitude'], (float)$row['latitude']],
                'image'       => $row['cover_image'] ?? '',
                'thumb'       => '', // 缩略图字段待后续补充
                'date'        => substr($row['created_at'] ?? '', 0, 10),
                'desc'        => $row['description'] ?? '',
                'code'        => (string)$row['id'],
                'count'       => 0,
                'photoCount'  => 0,
                'ownerName'   => $row['owner_name'] ?? '',
                'ownerAvatar' => $row['owner_avatar'] ?? '',
            ];
        }
    }
} catch (Throwable $e) {
    // 数据库不可用时，回退到 JSON 数据
    $albums = $mapData['albums'] ?? [];
}

// 留言：统一从数据库读取；库不可用时回读 map-all.json 冻结快照
$messages = withu_message_fetch_all();
if ($messages === null) {
    $messages = withu_message_json_fallback();
}

// 情侣昵称/头像：与页头头像区同源（数据库 users 表），保持全站同步；
// 坐标沿用 map-all.json 的 lovers（按头像文件名/昵称匹配，顺序不保证），JSON 只作库不可用时兜底
$lovers = $mapData['lovers'] ?? [];
try {
    $rootPath = dirname(__DIR__, 2) . '/backend/app';
    if (is_file($rootPath . '/config/database.php') && is_file($rootPath . '/.installed')) {
        require_once $rootPath . '/core/Database.php';
        require_once $rootPath . '/core/Auth.php';
        $db = Database::getInstance();
        $mapAuth = new Auth();
        $mapCurrentUser = $mapAuth->getCurrentUser();
        $mapCurrentRole = (string) ($mapCurrentUser['role'] ?? '');
        $users = $db->fetchAll("SELECT nickname, avatar, role FROM users WHERE status='active' ORDER BY FIELD(role,'user1','user2')");
        $boy = null; $girl = null;
        foreach ($users as $u) {
            if (($u['role'] ?? '') === 'user1' && !$boy) $boy = $u;
            if (($u['role'] ?? '') === 'user2' && !$girl) $girl = $u;
        }
        if ($boy || $girl) {
            $jsonLovers    = $mapData['lovers'] ?? [];
            $defaultAvatar = '/_qqavatar.php?qq=10000&s=640';
            $pickMeta = function (array $user) use ($jsonLovers) {
                $nickname = trim((string)($user['nickname'] ?? ''));
                $avatarFile = strtolower(pathinfo(parse_url((string)($user['avatar'] ?? ''), PHP_URL_PATH) ?? '', PATHINFO_BASENAME));
                foreach ($jsonLovers as $lover) {
                    $c = $lover['coords'] ?? null;
                    if (!is_array($c) || count($c) < 2) continue;
                    $lAvatarFile = strtolower(pathinfo(parse_url((string)($lover['avatar'] ?? ''), PHP_URL_PATH) ?? '', PATHINFO_BASENAME));
                    if (($avatarFile !== '' && $lAvatarFile === $avatarFile)
                        || ($nickname !== '' && trim((string)($lover['name'] ?? '')) === $nickname)) {
                        return [
                            'coords' => array_slice($c, 0, 2),
                            'label'  => (string) ($lover['label'] ?? ''),
                        ];
                    }
                }
                return null;
            };

            if ($boy && $girl) {
                // 两位实际用户都存在：头像、昵称、位置全部来自真实账号
                $boyMeta  = $pickMeta($boy);
                $girlMeta = $pickMeta($girl);
                $lovers = [
                    ['name' => $boy['nickname'] ?? '', 'label' => $boyMeta['label'] ?? '未知', 'role' => 'user1', 'coords' => $boyMeta['coords'] ?? null, 'avatar' => ($boy['avatar'] ?? '') ?: $defaultAvatar],
                    ['name' => $girl['nickname'] ?? '', 'label' => $girlMeta['label'] ?? '未知', 'role' => 'user2', 'coords' => $girlMeta['coords'] ?? null, 'avatar' => ($girl['avatar'] ?? '') ?: $defaultAvatar],
                ];
                // 面板左「我」右「TA」：登录者是 user2 时交换顺序，保证头像与登录身份对应
                if ($mapCurrentRole === 'user2') {
                    $lovers = [$lovers[1], $lovers[0]];
                }
            } else {
                // 只有一位实际用户：左侧显示真实账号，右侧用「等待加入」占位，避免套用旧演示情侣
                $only     = $boy ?: $girl;
                $onlyRole = $boy ? 'user1' : 'user2';
                $onlyName = trim((string) ($only['nickname'] ?? ''));
                $onlyMeta = $pickMeta($only);
                $lovers = [
                    [
                        'name'   => $onlyName,
                        'label'  => $onlyMeta['label'] ?? '未知',
                        'role'   => $onlyRole,
                        'coords' => $onlyMeta['coords'] ?? null,
                        'avatar' => ($only['avatar'] ?? '') ?: $defaultAvatar,
                    ],
                    [
                        'name'        => 'TA',
                        'label'       => '等待加入',
                        'role'        => 'user2',
                        'coords'      => null,
                        'avatar'      => $defaultAvatar,
                        'placeholder' => true,
                    ],
                ];
            }
        }
    }
} catch (Throwable $e) {
    // 数据库不可用时保留 JSON 数据
}

// 合并数据：相册、留言用数据库的，其他用 JSON 的
$result = array_merge($mapData, ['lovers' => $lovers], ['albums' => $albums], ['messages' => $messages]);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);