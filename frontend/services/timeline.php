<?php
/**
 * 时间轴（轨迹）数据接口
 *
 * 数据源：数据库聚合 —— 点滴（articles）、相册（albums）、已完成的恋爱心愿（love_list_items）、
 * 纪念事件（events）。原页面请求的该接口此前缺失，页面一直无法加载数据。
 *
 * 返回: {code:200, data:[{year,month,day,time,type,title,desc,author,author_id,
 *                        location,map_lng,map_lat,mediaUrl,thumbUrl,linkType,linkPath,...}]}
 */
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache');

require_once __DIR__ . '/../inc/config.php';

$timelineLoggedIn = false;
try {
    $__root = dirname(__DIR__, 2) . '/backend/app';
    if (is_file($__root . '/config/database.php') && is_file($__root . '/.installed')) {
        require_once $__root . '/config/config.php';
        require_once $__root . '/core/Database.php';
        require_once $__root . '/core/Auth.php';
        require_once $__root . '/core/helpers.php';
        $timelineLoggedIn = (new Auth())->isLoggedIn();
    }
} catch (Throwable $e) {
    // 登录态不可用时按未登录处理
}

if (!isset($db) || !is_object($db)) {
    echo json_encode(['code' => 200, 'data' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

// 作者 id 约定：1=男主 2=女主（与页面 TIMELINE_AUTHORS 对应）
$timelineAuthors = ['1' => 1, '2' => 2];
$timelineUserKey = [];
try {
    foreach ($db->fetchAll("SELECT id, nickname, gender FROM users") as $u) {
        $uid = (int) $u['id'];
        $timelineUserKey[$uid] = ($u['gender'] ?? '') === 'female' ? 2 : 1;
    }
} catch (Throwable $e) {
    $timelineUserKey = [];
}

// 相册 code 注册表（同名匹配）
$timelineAlbumCodes = [];
$__mapFile = __DIR__ . '/map-all.json';
if (is_file($__mapFile)) {
    $__map = json_decode((string) file_get_contents($__mapFile), true);
    foreach ((is_array($__map) ? ($__map['albums'] ?? []) : []) as $__ma) {
        if (!empty($__ma['name']) && !empty($__ma['code'])) {
            $timelineAlbumCodes[(string) $__ma['name']] = (string) $__ma['code'];
        }
    }
}

$items = [];
$pushDate = function (string $dateTime) use (&$__out) {
    $ts = strtotime($dateTime) ?: 0;
    return [
        'year'  => $ts ? date('Y', $ts) : '',
        'month' => $ts ? date('m', $ts) : '',
        'day'   => $ts ? date('j', $ts) : '',
        'time'  => $ts ? date('H:i', $ts) : '',
    ];
};

// ① 点滴（文章）
try {
    $guestFilter = $timelineLoggedIn ? '' : " AND (a.is_encrypted = 0 OR a.is_encrypted IS NULL)";
    foreach ($db->fetchAll(
        "SELECT a.*, u.nickname, u.gender,
                (SELECT COUNT(*) FROM comments c WHERE c.article_id = a.id) AS comment_count
         FROM articles a
         LEFT JOIN users u ON u.id = a.user_id
         WHERE a.status = 'published'{$guestFilter}
         ORDER BY a.created_at DESC"
    ) as $r) {
        $date = $pushDate((string) $r['created_at']);
        $excerpt = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($r['content'] ?? ''))));
        if (mb_strlen($excerpt) > 120) {
            $excerpt = mb_substr($excerpt, 0, 120) . '…';
        }
        $items[] = $date + [
            'id'        => (int) $r['id'],
            'type'      => 'text',
            'title'     => (string) $r['title'],
            'desc'      => $excerpt,
            'signature' => (string) ($r['nickname'] ?? ''),
            'author'    => (string) ($r['nickname'] ?? ''),
            'author_id' => $timelineUserKey[(int) $r['user_id']] ?? 1,
            'location'  => (string) ($r['location_name'] ?? ''),
            'map_lng'   => $r['longitude'] !== null ? (float) $r['longitude'] : null,
            'map_lat'   => $r['latitude'] !== null ? (float) $r['latitude'] : null,
            'weather'   => (string) ($r['weather'] ?? ''),
            'weatherIcon' => (string) ($r['weather_icon'] ?? ''),
            'moodLabel' => (string) ($r['mood'] ?? ''),
            'linkType'  => 'article',
            'linkPath'  => 'page.php?id=' . (int) $r['id'],
            'linkTitle' => '查看点滴详情',
        ];
    }
} catch (Throwable $e) {
    // 忽略
}

// ② 相册
try {
    $guestFilter = $timelineLoggedIn ? '' : " WHERE (a.is_encrypted = 0 OR a.is_encrypted IS NULL)";
    foreach ($db->fetchAll(
        "SELECT a.*, u.nickname, u.gender
         FROM albums a
         LEFT JOIN users u ON u.id = a.user_id{$guestFilter}
         ORDER BY a.created_at DESC"
    ) as $r) {
        $date = $pushDate((string) $r['created_at']);
        $code = $timelineAlbumCodes[(string) $r['name']] ?? '';
        $cover = upload_url((string) ($r['cover_image'] ?? ''));
        $items[] = $date + [
            'id'        => (int) $r['id'],
            'type'      => 'image',
            'title'     => (string) $r['name'],
            'desc'      => (string) ($r['description'] ?? ''),
            'author'    => (string) ($r['nickname'] ?? ''),
            'author_id' => $timelineUserKey[(int) $r['user_id']] ?? 1,
            'location'  => (string) ($r['location_name'] ?? ''),
            'map_lng'   => $r['longitude'] !== null ? (float) $r['longitude'] : null,
            'map_lat'   => $r['latitude'] !== null ? (float) $r['latitude'] : null,
            'mediaUrl'  => $cover,
            'thumbUrl'  => $cover,
            'linkType'  => $code !== '' ? 'album' : 'none',
            'linkPath'  => $code !== '' ? 'album-detail.php?code=' . rawurlencode($code) : '',
            'linkTitle' => '查看相册',
        ];
    }
} catch (Throwable $e) {
    // 忽略
}

// ③ 已完成的恋爱心愿
try {
    foreach ($db->fetchAll(
        "SELECT * FROM love_list_items WHERE is_done = 1 ORDER BY done_date DESC, id DESC"
    ) as $r) {
        $date = $pushDate((string) ($r['done_date'] ?? '') . ' 20:00:00');
        $thumb = '';
        if (!empty($r['id'])) {
            try {
                $img = $db->fetch(
                    "SELECT image_path, thumbnail_path FROM love_list_item_images
                     WHERE item_id = :id ORDER BY sort_order ASC, id ASC LIMIT 1",
                    ['id' => (int) $r['id']]
                );
                if ($img) {
                    $thumb = upload_url((string) ($img['thumbnail_path'] ?: $img['image_path']));
                }
            } catch (Throwable $e) {
                $thumb = '';
            }
        }
        $items[] = $date + [
            'id'        => (int) $r['id'],
            'type'      => 'list',
            'title'     => '心愿达成：' . (string) $r['title'],
            'desc'      => (string) ($r['note'] ?? ''),
            'author_id' => 1,
            'author'    => '',
            'location'  => (string) ($r['location_name'] ?? ''),
            'map_lng'   => $r['longitude'] !== null ? (float) $r['longitude'] : null,
            'map_lat'   => $r['latitude'] !== null ? (float) $r['latitude'] : null,
            'thumbUrl'  => $thumb,
            'mediaUrl'  => $thumb,
            'items'     => [['text' => (string) $r['title'], 'done' => true]],
            'linkType'  => 'none',
            'linkPath'  => '',
        ];
    }
} catch (Throwable $e) {
    // 忽略
}

// ④ 纪念事件
try {
    foreach ($db->fetchAll("SELECT * FROM events ORDER BY event_date DESC, id DESC") as $r) {
        $date = $pushDate((string) $r['event_date'] . ' 00:00:00');
        $items[] = $date + [
            'id'                  => (int) $r['id'],
            'type'                => 'milestone',
            'title'               => (string) $r['title'],
            'desc'                => (string) ($r['description'] ?? ''),
            'author_id'           => 1,
            'author'              => '',
            'milestoneCategory'   => !empty($r['is_recurring']) ? '每年纪念' : '纪念日',
            'milestoneValue'      => !empty($r['is_important']) ? '★' : '',
            'milestoneUnit'       => '',
            'linkType'            => 'none',
            'linkPath'            => '',
        ];
    }
} catch (Throwable $e) {
    // 忽略
}

// 按时间倒序
usort($items, function ($a, $b) {
    $ta = strtotime(($a['year'] ?: '1970') . '-' . ($a['month'] ?: '01') . '-' . ($a['day'] ?: '01') . ' ' . ($a['time'] ?: '00:00'));
    $tb = strtotime(($b['year'] ?: '1970') . '-' . ($b['month'] ?: '01') . '-' . ($b['day'] ?: '01') . ' ' . ($b['time'] ?: '00:00'));
    if ($ta === $tb) {
        return 0;
    }
    return $ta > $tb ? -1 : 1;
});

echo json_encode(['code' => 200, 'data' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
