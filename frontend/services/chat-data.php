<?php
/**
 * 关于页「对话回放」数据接口
 *
 * 数据源：数据库中 edit_mode='chat' 的已发布文章及其 article_blocks 对话块
 * （后台文章编辑器「聊天创作」模式写入）。
 * 页面 chat.js 约定：{status:'success'|'empty'|'error', data:{settings, dialogues}}
 * 无聊天体文章时返回 status='empty'，由前端展示内置占位对话。
 */
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache');

require_once __DIR__ . '/../inc/config.php';

$chatLoggedIn = false;
try {
    $__root = dirname(__DIR__, 2) . '/backend/app';
    if (is_file($__root . '/config/database.php') && is_file($__root . '/.installed')) {
        require_once $__root . '/config/config.php';
        require_once $__root . '/core/Database.php';
        require_once $__root . '/core/Auth.php';
        require_once $__root . '/core/helpers.php';
        $chatLoggedIn = (new Auth())->isLoggedIn();
    }
} catch (Throwable $e) {
    // 登录态不可用时按未登录处理
}

if (!isset($db) || !is_object($db)) {
    echo json_encode(['status' => 'empty', 'message' => '数据库不可用'], JSON_UNESCAPED_UNICODE);
    exit;
}

$cfg = json_decode($withuConfigJson ?? '{}', true);
$maleName   = (string) ($cfg['maleName'] ?? ($cfg['boy'] ?? '我'));
$femaleName = (string) ($cfg['femaleName'] ?? ($cfg['girl'] ?? '你'));
$maleAvatar   = (string) ($cfg['maleAvatar'] ?? '');
$femaleAvatar = (string) ($cfg['femaleAvatar'] ?? '');

try {
    // 最近的聊天体文章（游客仅公开文章）
    $guestFilter = $chatLoggedIn ? '' : " AND (a.is_encrypted = 0 OR a.is_encrypted IS NULL)";
    $article = $db->fetch(
        "SELECT a.*, u.nickname, u.gender
         FROM articles a
         LEFT JOIN users u ON u.id = a.user_id
         WHERE a.status = 'published' AND a.edit_mode = 'chat'{$guestFilter}
         ORDER BY a.updated_at DESC, a.created_at DESC, a.id DESC
         LIMIT 1"
    );
    if (!$article) {
        echo json_encode(['status' => 'empty'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 文章作者视角：男主视角时左侧为女主，反之亦然（沿用页面原始约定）
    $articleAuthorGender = ($article['gender'] ?? '') === 'female' ? 'female' : 'male';
    $swapViewpoint = $articleAuthorGender === 'female';

    $blocks = $db->fetchAll(
        "SELECT speaker, html FROM article_blocks WHERE article_id = :id ORDER BY block_index ASC, id ASC",
        ['id' => (int) $article['id']]
    );

    $dialogues = [];
    foreach ($blocks as $b) {
        $speaker = (string) ($b['speaker'] ?? '');
        $text = trim(html_entity_decode(strip_tags((string) ($b['html'] ?? '')), ENT_QUOTES, 'UTF-8'));
        if ($text === '') {
            continue;
        }
        if ($speaker === 'system') {
            $dialogues[] = ['role' => null, 'type' => 'notice', 'content' => $text];
        } elseif ($speaker === 'female') {
            $dialogues[] = ['role' => 'female', 'type' => 'text', 'content' => $text];
        } else {
            $dialogues[] = ['role' => 'male', 'type' => 'text', 'content' => $text];
        }
    }

    if (!$dialogues) {
        echo json_encode(['status' => 'empty'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'data'   => [
            'settings' => [
                'swapViewpoint' => $swapViewpoint,
                'dialogueName'  => (string) ($article['title'] ?? ''),
                'endingText'    => '— 故事还在继续 —',
                'avatars'       => ['male' => $maleAvatar, 'female' => $femaleAvatar],
                'colors'        => [
                    'male'   => ['bg' => '#007aff', 'text' => '#ffffff'],
                    'female' => ['bg' => '#ffffff', 'text' => '#1d1d1f'],
                ],
            ],
            'dialogues' => $dialogues,
            'article'   => ['id' => (int) $article['id'], 'title' => (string) $article['title']],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    echo json_encode(['status' => 'empty'], JSON_UNESCAPED_UNICODE);
}
