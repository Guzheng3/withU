<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/helpers.php';

header('Content-Type: application/json; charset=utf-8');

// 简单 IP 限流：防止被当作 QQ 头像/昵称公共代理接口滥用
// 规则：同一 IP 每小时最多 60 次请求
$ip        = function_exists('getClientIp') ? getClientIp() : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
$now       = time();
$limitPerHour = 60;

try {
    $db = Database::getInstance();

    // 幂等创建限流表
    $db->query("
        CREATE TABLE IF NOT EXISTS `qq_profile_attempts` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `ip` varchar(45) DEFAULT NULL,
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_ip_time` (`ip`,`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='QQ 资料查询尝试记录';
    ");

    // 昵称本地缓存表：外部源成功一次后落库，后续查询零延迟且不依赖外部接口
    $db->query("
        CREATE TABLE IF NOT EXISTS `qq_nick_cache` (
            `qq` varchar(20) NOT NULL,
            `nickname` varchar(64) NOT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`qq`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='QQ 昵称本地缓存';
    ");

    // 统计最近 1 小时内该 IP 的请求次数
    $windowStart = date('Y-m-d H:i:s', $now - 3600);
    $row = $db->fetch(
        "SELECT COUNT(*) AS c FROM qq_profile_attempts WHERE ip = :ip AND created_at >= :start",
        [
            'ip'    => $ip,
            'start' => $windowStart,
        ]
    );
    $ipCount = $row ? (int) ($row['c'] ?? 0) : 0;

    if ($ipCount >= $limitPerHour) {
        echo json_encode([
            'success' => false,
            'message' => '当前 IP 查询太频繁，请稍后再试',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
} catch (Throwable $e) {
    // 数据库异常时不影响主流程，只是不做限流
    $db = null;
}

$qq = isset($_GET['qq']) ? trim($_GET['qq']) : '';
if ($qq === '') {
    echo json_encode(['success' => false, 'message' => '缺少 QQ 参数'], JSON_UNESCAPED_UNICODE);
    exit;
}

/** 带 UA 与超时的 HTTP GET（外部昵称源通用） */
function qq_profile_http_get(string $url): string
{
    $ctx = stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true, 'header' => "User-Agent: Mozilla/5.0\r\n"]]);
    return (string) @file_get_contents($url, false, $ctx);
}

/** 通用 JSON 昵称解析：code ∈ okCodes 时取 name/nick/nickname 任意字段 */
function qq_profile_parse_generic(string $body, array $okCodes): string
{
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) return '';
    $code = $decoded['code'] ?? $decoded['status'] ?? null;
    if ($code !== null && $code !== true && !in_array((int) $code, $okCodes, true)) return '';
    $nick = trim((string) ($decoded['nick'] ?? $decoded['name'] ?? $decoded['nickname'] ?? ''));
    if ($nick === '' && isset($decoded['data']) && is_array($decoded['data'])) {
        $nick = trim((string) ($decoded['data']['name'] ?? $decoded['data']['nick'] ?? $decoded['data']['nickname'] ?? ''));
    }
    if ($nick === '' && isset($decoded['qq']) && is_array($decoded['qq'])) {
        $nick = trim((string) ($decoded['qq']['name'] ?? $decoded['qq']['nick'] ?? ''));
    }
    if ($nick === '' && isset($decoded['result']) && is_array($decoded['result'])) {
        $nick = trim((string) ($decoded['result']['name'] ?? $decoded['result']['nick'] ?? ''));
    }
    return $nick;
}

/** 腾讯官方 CGI（GBK JSONP；需登录时返回空） */
function qq_profile_parse_tencent(string $body): string
{
    $body = mb_convert_encoding($body, 'UTF-8', 'GBK');
    if (!preg_match('/\((\{.*\})\)/s', $body, $m)) return '';
    $decoded = json_decode($m[1], true);
    if (!is_array($decoded)) return '';
    $entry = reset($decoded);
    if (!is_array($entry) || count($entry) < 7) return '';
    return trim((string) $entry[6]);
}

/**
 * 昵称获取：本地优先，外部多源兜底
 * ① qq_nick_cache / users / messages —— 本站已知昵称，零外部依赖
 * ② mmp.cc → 腾讯官方 → UOMG → QJQQ → VVHAN → OIOWEB —— 任一源成功即返回并写缓存
 */
$nickname = '';

if ($db instanceof Database) {
    try {
        $cached = $db->fetch("SELECT nickname FROM qq_nick_cache WHERE qq = :qq LIMIT 1", ['qq' => $qq]);
        if ($cached && trim((string) $cached['nickname']) !== '') {
            $nickname = trim((string) $cached['nickname']);
        }
    } catch (Throwable $e) { }

    if ($nickname === '') {
        try {
            $known = $db->fetch(
                "SELECT nickname FROM users WHERE qq = :qq AND nickname <> '' AND status = 'active' LIMIT 1",
                ['qq' => $qq]
            );
            if ($known && trim((string) $known['nickname']) !== '') {
                $nickname = trim((string) $known['nickname']);
            }
        } catch (Throwable $e) { }
    }

    if ($nickname === '') {
        try {
            $known = $db->fetch(
                "SELECT guest_nickname FROM messages
                 WHERE guest_qq = :qq AND guest_nickname <> '' AND status = 'published'
                 ORDER BY id DESC LIMIT 1",
                ['qq' => $qq]
            );
            if ($known && trim((string) $known['guest_nickname']) !== '') {
                $nickname = trim((string) $known['guest_nickname']);
            }
        } catch (Throwable $e) { }
    }
}

if ($nickname === '') {
    // 外部源按可用性排序；任一成功即写入本地缓存
    $sources = [
        ['https://api.mmp.cc/api/qqname?qq=' . urlencode($qq),          [1, 200]],
        ['https://r.qzone.qq.com/fcg-bin/cgi_get_portrait.fcg?uins=' . $qq, null],
        ['https://api.uomg.com/api/qq.info?qq=' . urlencode($qq),        [1, 200]],
        ['https://api.qjqq.cn/api/qqinfo?qq=' . urlencode($qq),          [1, 200]],
        ['https://api.vvhan.com/api/qqinfo?qq=' . urlencode($qq),        [1, 200]],
        ['https://api.oioweb.cn/api/qq/Info?qq=' . urlencode($qq),       [1, 200]],
    ];
    foreach ($sources as [$sourceUrl, $okCodes]) {
        $body = qq_profile_http_get($sourceUrl);
        if ($body === '') continue;
        $nick = $okCodes === null ? qq_profile_parse_tencent($body) : qq_profile_parse_generic($body, $okCodes);
        if ($nick !== '') {
            $nickname = $nick;
            break;
        }
    }

    // 外部成功 → 写入本地缓存，后续查询不再依赖外部接口
    if ($nickname !== '' && $db instanceof Database) {
        try {
            $db->query(
                "INSERT INTO qq_nick_cache (qq, nickname, updated_at) VALUES (:qq, :nick, :now)
                 ON DUPLICATE KEY UPDATE nickname = :nick, updated_at = :now",
                ['qq' => $qq, 'nick' => $nickname, 'now' => date('Y-m-d H:i:s')]
            );
        } catch (Throwable $e) { }
    }
}

// 头像统一使用官方 qlogo 接口，避免 http 协议或尺寸不一致问题
$avatarUrl = 'https://q1.qlogo.cn/g?b=qq&nk=' . urlencode($qq) . '&s=100';

// 记录一次成功的查询尝试（最佳努力，不影响主流程）
if ($db instanceof Database && !empty($ip)) {
    try {
        $db->insert('qq_profile_attempts', [
            'ip'         => $ip,
            'created_at' => date('Y-m-d H:i:s', $now),
        ]);
    } catch (Throwable $e) {
        // 记录失败忽略
    }
}

echo json_encode([
    'success'    => true,
    'avatar_url' => $avatarUrl,
    'nickname'   => $nickname,
], JSON_UNESCAPED_UNICODE);
