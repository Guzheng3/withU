<?php
/**
 * 相册照片列表接口（前台相册详情页数据源）
 *
 * 数据优先级：
 *   ① MySQL 后台相册（与 map-all 同名视为同一相册，后台上传的图片/视频优先展示）
 *   ② frontend/services/album-photos.json 本地缓存（按 code 存放的各相册照片清单）
 *   ③ Lovefolder 目录全量扫描（兜底，保持与本地 Node 桥接行为一致）
 *
 * 响应结构与前台 page-album-detail.js 的约定保持一致：
 *   {code:200, data:{album, photos, counts:{total}, pagination:{has_more}}}
 */
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache');

require_once __DIR__ . '/../inc/config.php';

$code    = isset($_GET['code']) ? trim((string) $_GET['code']) : '';
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? 20);
if ($perPage <= 0) { $perPage = 20; }
if ($perPage > 50) { $perPage = 50; }

if ($code === '') {
    echo json_encode(['code' => 404, 'msg' => '缺少相册 code'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── 相册元信息（map-all.json） ──────────────────────────────
$mapFile  = __DIR__ . '/../services/map-all.json';
$map      = is_file($mapFile) ? json_decode((string) file_get_contents($mapFile), true) : null;
$albumRow = null;
foreach ((is_array($map) ? ($map['albums'] ?? []) : []) as $a) {
    if (isset($a['code']) && (string) $a['code'] === $code) { $albumRow = $a; break; }
}
if (!$albumRow) {
    echo json_encode(['code' => 404, 'msg' => '相册不存在'], JSON_UNESCAPED_UNICODE);
    exit;
}

$cfg = json_decode($withuConfigJson ?? '{}', true);

// 封面：优先本地存在的缩略图，其次 map-all 原地址
$cover = (string) ($albumRow['thumb'] ?? '');
if ($cover === '' || !is_file(__DIR__ . '/../' . ltrim($cover, '/'))) {
    $cover = (string) ($albumRow['image'] ?? '');
}
if ($cover !== '' && !preg_match('#^https?://#i', $cover) && strpos($cover, '/') !== 0) {
    $cover = '/' . $cover;
}

$album = [
    'title'    => (string) ($albumRow['name'] ?? ''),
    'date'     => (string) ($albumRow['date'] ?? ''),
    'location' => (string) ($albumRow['city'] ?? ''),
    'desc'     => (string) ($albumRow['desc'] ?? ''),
    'cover'    => $cover,
    'author'   => (string) ($cfg['maleName'] ?? ($cfg['boy'] ?? 'Ki.')),
];

if (!function_exists('photolist_ago')) {
    // 粗粒度相对时间
    function photolist_ago($d) {
        $ts = strtotime((string) $d);
        if (!$ts) return '';
        $diff = max(0, time() - $ts);
        $day = 86400;
        if ($diff < $day) return '今天';
        if ($diff < 30 * $day) return floor($diff / $day) . '天前';
        if ($diff < 365 * $day) return floor($diff / (30 * $day)) . '个月前';
        return floor($diff / (365 * $day)) . '年前';
    }
}

if (!function_exists('photolist_local_url')) {
    // 前台静态资源解析：本地存在则用站内绝对路径，否则回落到生产站同路径
    function photolist_local_url($path) {
        $path = trim((string) $path);
        if ($path === '') return '';
        if (preg_match('#^https?://#i', $path)) return $path;
        $rel = ltrim($path, '/');
        if (is_file(__DIR__ . '/../' . $rel)) {
            return '/' . $rel;
        }
        return 'https://love-really.kikiw.cn/' . $rel;
    }
}

$photos = [];
$source = 'cache';
$cacheHit = false; // 缓存中存在该 code 条目（即便 0 张）即视为已知照片集，不再回落全量池

// ── ① MySQL 后台相册（同名匹配） ───────────────────────────
try {
    if (isset($db)) {
        // upload_url() 位于后端 helpers（config 常量已由 inc/config.php 载入）
        $helpersFile = dirname(__DIR__, 2) . '/backend/app/core/helpers.php';
        if (is_file($helpersFile)) {
            require_once $helpersFile;
        }
        $row = $db->fetch("SELECT id, name FROM albums WHERE name = :name LIMIT 1", ['name' => (string) ($albumRow['name'] ?? '')]);
        if ($row) {
            $aid = (int) $row['id'];
            // 图片（后传的在前，尽量携带上传者信息）
            $imgs = $db->fetchAll(
                "SELECT ai.*, u.nickname AS up_name, u.avatar AS up_avatar, u.gender AS up_gender
                 FROM album_images ai
                 LEFT JOIN album_image_uploads au ON au.image_id = ai.id
                 LEFT JOIN users u ON u.id = au.user_id
                 WHERE ai.album_id = :aid
                 ORDER BY ai.created_at DESC, ai.id DESC",
                ['aid' => $aid]
            );
            foreach ($imgs as $im) {
                $photos[] = [
                    'id'             => (int) $im['id'],
                    'photo_text'     => (string) ($im['description'] ?? ''),
                    'photo_url'      => upload_url($im['image_path'] ?? ''),
                    'photo_thumb'    => upload_url($im['thumbnail_path'] ?? ($im['image_path'] ?? '')),
                    'photo_date'     => substr((string) ($im['created_at'] ?? ''), 0, 10),
                    'photo_date_ago' => photolist_ago($im['created_at'] ?? ''),
                    'photo_byname'   => (string) ($im['up_name'] ?? ''),
                    'photo_location' => '',
                    'photo_lng'      => '',
                    'photo_lat'      => '',
                    'photo_type'     => 0,
                    'VideoCover'     => '',
                    'up_avatar'      => (string) ($im['up_avatar'] ?? ''),
                    'up_gender'      => (string) ($im['up_gender'] ?? ''),
                ];
            }
            // 视频
            $vids = $db->fetchAll(
                "SELECT av.*, u.nickname AS up_name, u.avatar AS up_avatar, u.gender AS up_gender
                 FROM album_videos av
                 LEFT JOIN users u ON u.id = av.uploader_id
                 WHERE av.album_id = :aid
                 ORDER BY av.created_at DESC, av.id DESC",
                ['aid' => $aid]
            );
            foreach ($vids as $v) {
                $photos[] = [
                    'id'             => (int) $v['id'],
                    'photo_text'     => (string) ($v['description'] ?? ''),
                    'photo_url'      => upload_url($v['video_path'] ?? ''),
                    'photo_thumb'    => upload_url($v['poster_path'] ?? ''),
                    'photo_date'     => substr((string) ($v['created_at'] ?? ''), 0, 10),
                    'photo_date_ago' => photolist_ago($v['created_at'] ?? ''),
                    'photo_byname'   => (string) ($v['up_name'] ?? ''),
                    'photo_location' => '',
                    'photo_lng'      => '',
                    'photo_lat'      => '',
                    'photo_type'     => 1,
                    'VideoCover'     => upload_url($v['poster_path'] ?? ''),
                    'up_avatar'      => (string) ($v['up_avatar'] ?? ''),
                    'up_gender'      => (string) ($v['up_gender'] ?? ''),
                ];
            }
            if (!empty($photos)) { $source = 'mysql'; }
        }
    }
} catch (Throwable $e) {
    // 数据库不可用或表缺失：忽略，走后续数据源
    $photos = [];
}

// ── ② 本地缓存（album-photos.json，按 code 存放） ──────────
if (empty($photos)) {
    $cacheFile = __DIR__ . '/album-photos.json';
    $cache = is_file($cacheFile) ? json_decode((string) file_get_contents($cacheFile), true) : null;
    if (is_array($cache) && array_key_exists($code, $cache) && isset($cache[$code]['photos']) && is_array($cache[$code]['photos'])) {
        $cacheHit = true;
        foreach ($cache[$code]['photos'] as $p) {
            $photos[] = [
                'id'             => $p['id'] ?? 0,
                'photo_text'     => (string) ($p['photo_text'] ?? ''),
                'photo_url'      => photolist_local_url($p['photo_url'] ?? ''),
                'photo_thumb'    => photolist_local_url($p['photo_thumb'] ?? ($p['photo_url'] ?? '')),
                'photo_date'     => (string) ($p['photo_date'] ?? ''),
                'photo_date_ago' => (string) ($p['photo_date_ago'] ?? photolist_ago($p['photo_date'] ?? '')),
                'photo_byname'   => (string) ($p['photo_byname'] ?? ''),
                'photo_location' => (string) ($p['photo_location'] ?? ''),
                'photo_lng'      => (string) ($p['photo_lng'] ?? ''),
                'photo_lat'      => (string) ($p['photo_lat'] ?? ''),
                'photo_type'     => (int) ($p['photo_type'] ?? 0),
                'VideoCover'     => photolist_local_url($p['VideoCover'] ?? ''),
                'up_avatar'      => photolist_local_url($p['up_avatar'] ?? ''),
                'up_gender'      => (string) ($p['up_gender'] ?? ''),
            ];
        }
        $source = 'cache';
    }
}

// ── ③ Lovefolder 全量扫描兜底（仅当缓存中无此相册条目时） ──
if (empty($photos) && !$cacheHit) {
    $dir = __DIR__ . '/../Lovefolder';
    $exts = ['webp', 'jpg', 'jpeg', 'gif', 'png'];
    $scan = [];
    if (is_dir($dir)) {
        foreach (scandir($dir) ?: [] as $f) {
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (!in_array($ext, $exts, true)) continue;
            if (substr($f, -10) === '_thumb.' . $ext) continue; // 跳过缩略图衍生文件
            $thumb = preg_replace('/(\.\w+)$/', '_thumb$1', $f);
            $date = preg_match('/^(\d{4})(\d{2})(\d{2})/', $f, $m) ? "{$m[1]}-{$m[2]}-{$m[3]}" : '';
            $scan[] = [
                'id'             => $f,
                'photo_text'     => '',
                'photo_url'      => '/Lovefolder/' . $f,
                'photo_thumb'    => is_file($dir . '/' . $thumb) ? '/Lovefolder/' . $thumb : '/Lovefolder/' . $f,
                'photo_date'     => $date,
                'photo_date_ago' => photolist_ago($date),
                'photo_byname'   => '',
                'photo_location' => '',
                'photo_lng'      => '',
                'photo_lat'      => '',
                'photo_type'     => 0,
                'VideoCover'     => '',
                'up_avatar'      => '',
                'up_gender'      => '',
            ];
        }
        usort($scan, function ($x, $y) { return strcmp($y['photo_date'], $x['photo_date']); });
        $photos = $scan;
    }
    $source = 'pool';
}

$total  = count($photos);
$offset = ($page - 1) * $perPage;
$slice  = array_slice($photos, $offset, $perPage);

echo json_encode([
    'code' => 200,
    'data' => [
        'album'      => $album,
        'source'     => $source,
        'photos'     => $slice,
        'counts'     => ['total' => $total],
        'pagination' => ['has_more' => ($offset + $perPage) < $total],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
