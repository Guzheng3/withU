<?php
/**
 * 一次性迁移：把关于页原有的演示对话（原为前端内置占位对话）写入数据库，
 * 作为一篇 edit_mode='chat' 的「聊天体」文章及其 article_blocks 对话块，
 * 供后台文章编辑器继续编辑，前台关于页从数据库回放。
 *
 * 幂等：已存在同名聊天体文章则跳过；可重复执行。
 * 用法：php scripts/seed-demo-chat.php
 */

require_once dirname(__DIR__) . '/backend/app/config/config.php';
require_once dirname(__DIR__) . '/backend/app/core/Database.php';

$dataFile = __DIR__ . '/demo-chat-data.json';
if (!is_file($dataFile)) {
    fwrite(STDERR, "找不到 demo-chat-data.json\n");
    exit(1);
}
$dialogues = json_decode((string)file_get_contents($dataFile), true);
if (!is_array($dialogues) || !$dialogues) {
    fwrite(STDERR, "demo-chat-data.json 为空或格式错误\n");
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

$title = '我们的对话回放';
$existing = $db->fetch(
    "SELECT id FROM articles WHERE title = :title AND edit_mode = 'chat' LIMIT 1",
    ['title' => $title]
);
if ($existing) {
    echo "已存在聊天体文章（id={$existing['id']}），跳过。\n";
    exit(0);
}

$now = date('Y-m-d H:i:s');
$db->insert('articles', [
    'user_id'      => $ownerId,
    'title'        => $title,
    'content'      => '',
    'type'         => 'article',
    'is_encrypted' => 0,
    'visibility'   => 'public',
    'status'       => 'published',
    'edit_mode'    => 'chat',
    'views'        => 0,
    'like_count'   => 0,
    'created_at'   => $now,
    'updated_at'   => $now,
]);
$articleId = (int)$db->getPDO()->lastInsertId();

$index = 0;
foreach ($dialogues as $line) {
    $speaker = (string)($line['speaker'] ?? 'system');
    if (!in_array($speaker, ['male', 'female', 'system'], true)) {
        $speaker = 'system';
    }
    $text = (string)($line['text'] ?? '');
    if (trim($text) === '') {
        continue;
    }
    $db->insert('article_blocks', [
        'article_id'  => $articleId,
        'block_index' => $index,
        'user_id'     => $ownerId,
        'speaker'     => $speaker,
        'html'        => '<p>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</p>',
        'created_at'  => $now,
        'updated_at'  => $now,
    ]);
    $index++;
}

echo "聊天体文章种子完成：文章 id={$articleId}，对话块 {$index} 条\n";
