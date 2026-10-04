<?php
/**
 * 一次性迁移：把前台 albums.php 原硬编码的 demo 相册（含图片/视频清单）写入数据库。
 *
 * 数据来源 scripts/demo-albums-data.json：
 *   - 相册名/日期/地点/坐标取自 frontend/services/map-all.json
 *   - 图片清单（原图、缩略图、描述、大小）取自 frontend/services/album-photos.json 与原静态卡片
 *   - 浏览量/点赞数为原静态卡片展示值
 *
 * 幂等：以相册名判重（与后台「前台查看」、photo-list 的同名匹配约定一致），已存在则跳过；可重复执行。
 * 用法：php scripts/seed-demo-albums.php
 */

require_once dirname(__DIR__) . '/backend/app/config/config.php';
require_once dirname(__DIR__) . '/backend/app/core/Database.php';

$dataFile = __DIR__ . '/demo-albums-data.json';
if (!is_file($dataFile)) {
    fwrite(STDERR, "找不到 demo-albums-data.json\n");
    exit(1);
}
$albums = json_decode((string)file_get_contents($dataFile), true);
if (!is_array($albums) || !$albums) {
    fwrite(STDERR, "demo-albums-data.json 为空或格式错误\n");
    exit(1);
}

try {
    $db = Database::getInstance();
} catch (Throwable $e) {
    fwrite(STDERR, "数据库不可用：{$e->getMessage()}\n");
    exit(1);
}

// 相册归属：优先第一个 user1（站长），否则第一个用户；没有用户时无法建相册
$owner = $db->fetch("SELECT id FROM users WHERE role='user1' ORDER BY id ASC LIMIT 1");
if (!$owner) {
    $owner = $db->fetch("SELECT id FROM users ORDER BY id ASC LIMIT 1");
}
if (!$owner) {
    fwrite(STDERR, "users 表没有账号，请先完成安装注册后再执行种子\n");
    exit(1);
}
$ownerId = (int)$owner['id'];

$insertedAlbums = 0;
$insertedImages = 0;
$insertedVideos = 0;
$skipped = 0;

foreach ($albums as $album) {
    $name = trim((string)($album['name'] ?? ''));
    if ($name === '') {
        $skipped++;
        continue;
    }

    // 同名相册视为已迁移（后台/详情页同样按名称关联前台）
    if ($db->fetch("SELECT id FROM albums WHERE name = :name LIMIT 1", ['name' => $name])) {
        $skipped++;
        continue;
    }

    $date = (string)($album['date'] ?? '');
    $createdAt = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date . ' 00:00:00' : date('Y-m-d H:i:s');

    $db->insert('albums', [
        'user_id'               => $ownerId,
        'name'                  => $name,
        'description'           => (string)($album['desc'] ?? ''),
        'cover_image'           => (string)($album['cover'] ?? ''),
        'is_encrypted'          => 0,
        'visibility'            => 'public',
        'keep_original_quality' => 0,
        'location_name'         => (string)($album['city'] ?? ''),
        'latitude'              => isset($album['lat']) ? (float)$album['lat'] : null,
        'longitude'             => isset($album['lng']) ? (float)$album['lng'] : null,
        'views'                 => (int)($album['views'] ?? 0),
        'like_count'            => (int)($album['likes'] ?? 0),
        'created_at'            => $createdAt,
        'updated_at'            => $createdAt,
    ]);
    $albumId = (int)$db->getPDO()->lastInsertId();
    $insertedAlbums++;

    // 媒体清单按原卡片展示顺序写入：created_at 依次递减，
    // 使「ORDER BY created_at DESC, id DESC」还原原预览顺序
    $baseTs = strtotime($createdAt) ?: time();
    $slotTs = $baseTs + 12 * 3600; // 媒体时间从当日 12:00 起往回排

    foreach (($album['media'] ?? []) as $index => $media) {
        $mediaAt = date('Y-m-d H:i:s', $slotTs - $index * 60);
        if (($media['kind'] ?? 'image') === 'video') {
            if ((string)($media['url'] ?? '') === '') {
                continue;
            }
            $db->insert('album_videos', [
                'album_id'     => $albumId,
                'video_path'   => (string)$media['url'],
                'poster_path'  => (string)($media['cover'] ?? ''),
                'description'  => '',
                'uploader_id'  => $ownerId,
                'sort_order'   => 0,
                'created_at'   => $mediaAt,
            ]);
            $insertedVideos++;
            continue;
        }

        $imagePath = (string)($media['image'] ?? '');
        $thumbPath = (string)($media['thumb'] ?? '');
        if ($imagePath === '' && $thumbPath === '') {
            continue;
        }
        $db->insert('album_images', [
            'album_id'       => $albumId,
            'image_path'     => $imagePath !== '' ? $imagePath : $thumbPath,
            'thumbnail_path' => $thumbPath !== '' ? $thumbPath : null,
            'is_optimized'   => 0,
            'skip_optimize'  => 0,
            'description'    => mb_substr((string)($media['text'] ?? ''), 0, 255) ?: null,
            'file_size'      => isset($media['size']) && (int)$media['size'] > 0 ? (int)$media['size'] : null,
            'sort_order'     => 0,
            'created_at'     => $mediaAt,
        ]);
        $insertedImages++;
    }
}

$albumTotal = (int)$db->fetch("SELECT COUNT(*) AS c FROM albums")['c'];
$imageTotal = (int)$db->fetch("SELECT COUNT(*) AS c FROM album_images")['c'];
echo "种子完成：相册新写入 {$insertedAlbums} 个（跳过 {$skipped}），图片 {$insertedImages} 张，视频 {$insertedVideos} 个；"
    . "表内现有相册 {$albumTotal} 个、图片 {$imageTotal} 张\n";
