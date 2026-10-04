<?php
/**
 * 一次性迁移：把前台首页/点滴页原有硬编码的 demo 文章写入数据库 articles 表。
 *
 * 数据来源 scripts/demo-articles-data.json：
 *   - 标题/日期/作者/地点/坐标取自 frontend/services/map-all.json 的 moments
 *   - 正文、浏览量、点赞数、天气、心情取自 index.php 原静态「点滴」卡片
 *
 * 保留原文章 id（首页与列表页链接为 page.php?id=N，id 不变才能继续打开）。
 * 幂等：按 id 或标题判重，已存在则跳过；可重复执行。
 * 用法：php scripts/seed-demo-articles.php
 */

require_once dirname(__DIR__) . '/backend/app/config/config.php';
require_once dirname(__DIR__) . '/backend/app/core/Database.php';

$dataFile = __DIR__ . '/demo-articles-data.json';
if (!is_file($dataFile)) {
    fwrite(STDERR, "找不到 demo-articles-data.json\n");
    exit(1);
}
$articles = json_decode((string)file_get_contents($dataFile), true);
if (!is_array($articles) || !$articles) {
    fwrite(STDERR, "demo-articles-data.json 为空或格式错误\n");
    exit(1);
}

try {
    $db = Database::getInstance();
} catch (Throwable $e) {
    fwrite(STDERR, "数据库不可用：{$e->getMessage()}\n");
    exit(1);
}

$owner = $db->fetch("SELECT id FROM users WHERE role='user1' ORDER BY id ASC LIMIT 1");
if (!$owner) {
    $owner = $db->fetch("SELECT id FROM users ORDER BY id ASC LIMIT 1");
}
if (!$owner) {
    fwrite(STDERR, "users 表没有账号，请先完成安装注册后再执行种子\n");
    exit(1);
}
$ownerId = (int)$owner['id'];

// 昵称 → 用户 id（文章作者；无匹配则归站长）
$userByNickname = [];
foreach ($db->fetchAll("SELECT id, nickname FROM users") as $u) {
    $nick = trim((string)($u['nickname'] ?? ''));
    if ($nick !== '') {
        $userByNickname[$nick] = (int)$u['id'];
    }
}

$inserted = 0;
$skipped = 0;

foreach ($articles as $a) {
    $id = (int)($a['id'] ?? 0);
    $title = trim((string)($a['title'] ?? ''));
    if ($id <= 0 || $title === '') {
        $skipped++;
        continue;
    }
    // 按原文章 id 判重（标题可能重复，如加密文章的占位标题，不能作为键）
    if ($db->fetch("SELECT id FROM articles WHERE id = :id LIMIT 1", ['id' => $id])) {
        $skipped++;
        continue;
    }

    $createdAt = (string)($a['created_at'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $createdAt)) {
        $createdAt = date('Y-m-d H:i:s');
    } elseif (strlen($createdAt) === 16) {
        $createdAt .= ':00';
    }

    $encrypted = (int)($a['encrypted'] ?? 0) === 1;
    $authorId = $userByNickname[(string)($a['author'] ?? '')] ?? $ownerId;

    $db->insert('articles', [
        'id'               => $id,
        'user_id'          => $authorId,
        'title'            => $title,
        'content'          => (string)($a['content'] ?? ''),
        'type'             => 'article',
        'is_encrypted'     => $encrypted ? 1 : 0,
        'visibility'       => $encrypted ? 'hidden' : 'public',
        'location_name'    => (string)($a['city'] ?? ''),
        'latitude'         => isset($a['lat']) ? (float)$a['lat'] : null,
        'longitude'        => isset($a['lng']) ? (float)$a['lng'] : null,
        'weather'          => (string)($a['weather'] ?? ''),
        'weather_icon'     => (string)($a['weather_icon'] ?? ''),
        'mood'             => (string)($a['mood'] ?? ''),
        'mood_icon'        => (string)($a['mood_icon'] ?? ''),
        'like_count'       => (int)($a['likes'] ?? 0),
        'views'            => (int)($a['views'] ?? 0),
        'status'           => 'published',
        'edit_mode'        => 'full',
        'comments_enabled' => 1,
        'created_at'       => $createdAt,
        'updated_at'       => $createdAt,
    ]);
    $inserted++;
}

$total = (int)$db->fetch("SELECT COUNT(*) AS c FROM articles")['c'];
echo "点滴种子完成：新写入 {$inserted} 篇（跳过 {$skipped}），articles 表现有 {$total} 篇\n";
