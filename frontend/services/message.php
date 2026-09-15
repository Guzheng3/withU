<?php
/**
 * 留言提交接口
 * 留言统一写入数据库 messages 表（原 map-all.json 写入已迁移，JSON 仅作只读冻结快照）
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/message-common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['Status' => false, 'message' => '请求方式错误']);
    exit;
}

$db = withu_message_db();
if (!$db) {
    echo json_encode(['Status' => false, 'message' => '数据库不可用，请稍后再试']);
    exit;
}
withu_message_ensure_schema($db);

$text = trim((string)($_POST['text'] ?? ''));
if ($text === '') {
    echo json_encode(['Status' => false, 'message' => '留言内容不能为空']);
    exit;
}

// ── 反灌库护栏：长度 / IP 黑名单 / 频率限制 / 内容安全规则 ─────────────
// 留言是匿名公开写入（user_id 恒为 0），没有会话权限可被 CSRF 利用，
// 因此对齐 api/comment.php 的做法：限流 + 黑名单 + 规则拦截，而不是加 CSRF token。
// 失败一律走 HTTP 200 + Status:false，与本文档既有约定一致
// （前端 page-messages.js 的 !res.Status 分支会把 message 弹成 error toast）。
$withuMsgRoot = dirname(__DIR__, 2) . '/backend/app';
foreach (['/core/helpers.php', '/core/Moderation.php'] as $withuDep) {
    if (is_file($withuMsgRoot . $withuDep)) {
        require_once $withuMsgRoot . $withuDep;
    }
}

if (mb_strlen($text, 'UTF-8') > 1000) {
    echo json_encode(['Status' => false, 'message' => '留言内容过长（最多 1000 字）']);
    exit;
}

$withuMsgIp  = function_exists('getClientIp') ? getClientIp() : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
$withuMsgNow = time();

// 记录本次尝试，供频率限制统计（表缺失时按最简结构补建，幂等）
try {
    $db->query("CREATE TABLE IF NOT EXISTS `message_attempts` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `ip` varchar(45) DEFAULT NULL,
        `created_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_ip_time` (`ip`,`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='留言尝试记录'");
} catch (Throwable $e) { /* 建表失败不影响留言主流程 */ }

// IP 黑名单（后台「评论 / 留言 IP 黑名单」，与评论共用同一张表）
if ($withuMsgIp !== '' && $withuMsgIp !== '0.0.0.0') {
    try {
        $withuMsgBlack = $db->fetch(
            "SELECT id, expires_at FROM comment_ip_blacklist WHERE ip = :ip LIMIT 1",
            ['ip' => $withuMsgIp]
        );
        if ($withuMsgBlack) {
            $withuMsgExpires = $withuMsgBlack['expires_at'] ?? null;
            if ($withuMsgExpires === null || $withuMsgExpires === '0000-00-00 00:00:00'
                || strtotime($withuMsgExpires) >= $withuMsgNow) {
                echo json_encode(['Status' => false, 'message' => '当前 IP 已被限制留言']);
                exit;
            }
        }
    } catch (Throwable $e) { /* 表缺失时不影响正常留言 */ }
}

// 频率限制：每 IP 每小时最多 20 条（留言含回复，比评论的 30 条略收紧）
try {
    $withuMsgRow = $db->fetch(
        "SELECT COUNT(*) AS c FROM message_attempts WHERE ip = :ip AND created_at >= :start",
        ['ip' => $withuMsgIp, 'start' => date('Y-m-d H:i:s', $withuMsgNow - 3600)]
    );
    if ($withuMsgRow && (int)($withuMsgRow['c'] ?? 0) >= 20) {
        echo json_encode(['Status' => false, 'message' => '留言太频繁了，请稍后再试']);
        exit;
    }
} catch (Throwable $e) { /* 统计失败不拦截 */ }

// 内容安全规则：高危词 / 刷屏 / 多外链等命中即拦截，其余留痕由后台复核
$withuMsgModeration = [];
if (function_exists('withu_moderate_text')) {
    $withuMsgModeration = withu_moderate_text($db, 'message', 0, $text);
    if (!empty($withuMsgModeration['blocked'])) {
        echo json_encode(['Status' => false, 'message' => '内容触发安全规则，已拦截并提交后台复核']);
        exit;
    }
}

$qq = (string)($_POST['qq'] ?? 'anon');
$now = date('Y-m-d H:i:s');

$row = [
    'user_id'        => 0,
    'guest_nickname' => mb_substr((string)($_POST['name'] ?? '匿名'), 0, 30),
    'guest_avatar'   => (string)($_POST['avatar'] ?? ''),
    'guest_qq'       => $qq,
    'location'       => mb_substr((string)($_POST['city'] ?? '中国'), 0, 255),
    'content'        => $text,
    'content_html'   => $text,
    'is_public'      => 1,
    'status'         => 'published',
    'created_at'     => $now,
    'parent_id'      => isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null,
    'reply_to_id'    => isset($_POST['reply_to_id']) && $_POST['reply_to_id'] !== '' ? (int)$_POST['reply_to_id'] : null,
    'lng'            => isset($_POST['lng']) && $_POST['lng'] !== '' ? (float)$_POST['lng'] : null,
    'lat'            => isset($_POST['lat']) && $_POST['lat'] !== '' ? (float)$_POST['lat'] : null,
    'os'             => mb_substr((string)($_POST['os'] ?? ''), 0, 100),
    'browser'        => mb_substr((string)($_POST['browser'] ?? ''), 0, 100),
    'weather'        => mb_substr((string)($_POST['weather'] ?? ''), 0, 100),
    'weather_icon'   => mb_substr((string)($_POST['weather_icon'] ?? ''), 0, 100),
    'like_count'     => 0,
    'msg_type'       => '',
    'badge'          => null,
];

try {
    $newId = $db->insert('messages', $row);
} catch (Throwable $e) {
    echo json_encode(['Status' => false, 'message' => '留言保存失败，请稍后再试']);
    exit;
}

// 记录本次成功提交，供后续频率限制统计（最佳努力，不影响主流程）
try {
    if ($withuMsgIp !== '' && $withuMsgIp !== '0.0.0.0') {
        $db->insert('message_attempts', ['ip' => $withuMsgIp, 'created_at' => $now]);
    }
} catch (Throwable $e) { /* 记录失败忽略 */ }

// 规则留痕关联到真实留言 id（review 级内容后台可见）
if (!empty($withuMsgModeration['log_id']) && function_exists('withu_finish_moderation')) {
    withu_finish_moderation($db, (int)$withuMsgModeration['log_id'], (int)$newId);
}

echo json_encode(['Status' => true, 'message' => '留言成功', 'id' => (int)$newId, 'pending' => false]);
