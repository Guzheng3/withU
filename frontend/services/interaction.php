<?php
/**
 * 公共点赞 / 浏览量交互接口（前台 services 层，interaction.js 的真实后端）
 *
 * 动作：
 *   POST like  {target_type, target_id}   → toggle 点赞，返回 {code:200, data:{liked, like_count}}
 *   POST view  {target_type, target_id}   → 浏览量 +1，返回 {code:200, data:{view_count}}
 *   GET  status?items=album:1,article:2   → 批量查询，返回 {code:200, data:{items:[...]}}
 *
 * 存储：backend/app/content_likes 表（与后台 /api/like.php 同一张表、同一套 actor_key 规则：
 * 登录用户按 user:<id>，未登录访客按会话 guest 键）。浏览量存 settings 表。
 * 数据库不可用时整体降级为会话内存储，页面功能不中断（不持久）。
 *
 * 响应格式沿用 {code, data} 结构，interaction.js 无需改动。
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$rootPath = dirname(__DIR__, 2) . '/backend/app';
require_once $rootPath . '/config/config.php';
require_once $rootPath . '/core/Database.php';
require_once $rootPath . '/core/Auth.php';
require_once $rootPath . '/core/helpers.php';
require_once $rootPath . '/core/withu.php';

const INTERACTION_TYPES = ['article', 'album', 'event', 'photo'];

/** 解析请求参数：interaction.js 走 x-www-form-urlencoded，兼容 JSON 体 */
function interaction_params(): array {
    $ctype = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    if (strpos($ctype, 'application/json') !== false) {
        return withu_json_body();
    }
    return array_merge($_GET, $_POST);
}

/** 与后台 /api/like.php 同一套 actor_key 规则 */
function interaction_actor(Auth $auth): string {
    if ($auth->isLoggedIn()) {
        return 'user:' . $auth->getCurrentUser()['id'];
    }
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['withu_guest_like_key'])) {
        $_SESSION['withu_guest_like_key'] = 'guest:' . withu_token(24);
    }
    return $_SESSION['withu_guest_like_key'];
}

/** 数据库不可用时返回 null（调用方走会话兜底），避免整站 500 */
function interaction_db(): ?object {
    try {
        $db = Database::getInstance();
        $db->fetch('SELECT 1');
        return $db;
    } catch (Throwable $e) {
        return null;
    }
}

/** 会话兜底存储（DB 不可用时）：{likes:{key:{actor:true}}, views:{key:n}} */
function interaction_session_store(): array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['withu_interaction_fallback']) || !is_array($_SESSION['withu_interaction_fallback'])) {
        $_SESSION['withu_interaction_fallback'] = ['likes' => [], 'views' => []];
    }
    return $_SESSION['withu_interaction_fallback'];
}

function interaction_like_count(object $db, string $type, int $id): int {
    $row = $db->fetch(
        'SELECT COUNT(*) c FROM content_likes WHERE target_type=:type AND target_id=:id',
        ['type' => $type, 'id' => $id]
    );
    return (int)($row['c'] ?? 0);
}

/** 浏览量计数存 settings 表，键 interaction_view_<type>_<id> */
function interaction_view_count(object $db, string $type, int $id): int {
    return (int)get_setting('interaction_view_' . $type . '_' . $id, '0');
}

function interaction_bump_view(?object $db, string $type, int $id, array &$fb): int {
    if ($db) {
        $key = 'interaction_view_' . $type . '_' . $id;
        $n = (int)get_setting($key, '0') + 1;
        $existing = $db->fetch("SELECT id FROM settings WHERE `key` = :key", ['key' => $key]);
        if ($existing) {
            $db->update('settings', ['value' => (string)$n, 'updated_at' => date('Y-m-d H:i:s')], '`key` = :key', ['key' => $key]);
        } else {
            $db->insert('settings', ['key' => $key, 'value' => (string)$n, 'updated_at' => date('Y-m-d H:i:s')]);
        }
        return $n;
    }
    return $fb['views'][$type . ':' . $id] = ((int)($fb['views'][$type . ':' . $id] ?? 0)) + 1;
}

function interaction_status_item(?object $db, array $fb, string $key, string $actor): ?array {
    $i = strpos($key, ':');
    if ($i <= 0) return null;
    $type = substr($key, 0, $i);
    $id   = (int)substr($key, $i + 1);
    if (!in_array($type, INTERACTION_TYPES, true) || $id <= 0) return null;

    if ($db) {
        $likedRow = $db->fetch(
            'SELECT id FROM content_likes WHERE actor_key=:actor AND target_type=:type AND target_id=:id LIMIT 1',
            ['actor' => $actor, 'type' => $type, 'id' => $id]
        );
        return [
            'target_type' => $type,
            'target_id'   => $id,
            'liked'       => (bool)$likedRow,
            'like_count'  => interaction_like_count($db, $type, $id),
            'view_count'  => interaction_view_count($db, $type, $id),
        ];
    }
    $full = $type . ':' . $id;
    return [
        'target_type' => $type,
        'target_id'   => $id,
        'liked'       => !empty($fb['likes'][$full][$actor]),
        'like_count'  => isset($fb['likes'][$full]) ? count($fb['likes'][$full]) : 0,
        'view_count'  => (int)($fb['views'][$full] ?? 0),
    ];
}

try {
    $auth   = new Auth();
    $params = interaction_params();
    $action = trim((string)($params['action'] ?? ''));
    $fb     = interaction_session_store();

    // like/view 是写操作：校验同源（Referer/Origin 与当前 Host 一致）即可，
    // interaction.js 未携带页面 CSRF token；幂等由 actor_key 唯一键保证。
    if (in_array($action, ['like', 'view'], true)) {
        $ref  = (string)($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '');
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        // 只比 host[:port]，Referer 里的路径不参与；Host 头自带端口，与 parse_url 结果口径一致
        if ($ref !== '' && $host !== '' && parse_url($ref, PHP_URL_HOST) . ':' !== $host . ':' ) {
            $refHost = parse_url($ref, PHP_URL_HOST);
            $refPort = parse_url($ref, PHP_URL_PORT);
            $refAuthority = $refHost . ($refPort ? ':' . $refPort : '');
            if ($refAuthority !== $host) {
                withu_json_response(['code' => 403, 'msg' => '来源校验失败'], 403);
            }
        }
    }

    $actor = interaction_actor($auth);
    $db    = interaction_db();

    if ($action === 'like') {
        $type = trim((string)($params['target_type'] ?? ''));
        $id   = (int)($params['target_id'] ?? 0);
        if (!in_array($type, INTERACTION_TYPES, true) || $id <= 0) {
            withu_json_response(['code' => 400, 'msg' => '点赞对象不合法'], 400);
        }

        if ($db) {
            $old = $db->fetch(
                'SELECT id FROM content_likes WHERE actor_key=:actor AND target_type=:type AND target_id=:id LIMIT 1',
                ['actor' => $actor, 'type' => $type, 'id' => $id]
            );
            if ($old) {
                $db->delete('content_likes', 'id=:id', ['id' => $old['id']]);
                $liked = false;
            } else {
                $db->insert('content_likes', [
                    'actor_key'   => $actor,
                    'target_type' => $type,
                    'target_id'   => $id,
                    'created_at'  => withu_now(),
                ]);
                $liked = true;
            }
            withu_json_response(['code' => 200, 'data' => [
                'liked'      => $liked,
                'like_count' => interaction_like_count($db, $type, $id),
            ]]);
        }

        // DB 不可用：会话兜底（仅本次会话内有效）
        $full = $type . ':' . $id;
        if (empty($fb['likes'][$full][$actor])) {
            $fb['likes'][$full][$actor] = true;
            $liked = true;
        } else {
            unset($fb['likes'][$full][$actor]);
            $liked = false;
        }
        withu_json_response(['code' => 200, 'data' => [
            'liked'      => $liked,
            'like_count' => count($fb['likes'][$full]),
        ]]);
    }

    if ($action === 'view') {
        $type = trim((string)($params['target_type'] ?? ''));
        $id   = (int)($params['target_id'] ?? 0);
        if (!in_array($type, INTERACTION_TYPES, true) || $id <= 0) {
            withu_json_response(['code' => 400, 'msg' => '对象不合法'], 400);
        }
        withu_json_response(['code' => 200, 'data' => ['view_count' => interaction_bump_view($db, $type, $id, $fb)]]);
    }

    if ($action === 'status') {
        $keys  = array_filter(array_map('trim', explode(',', (string)($params['items'] ?? ''))));
        $items = [];
        foreach ($keys as $key) {
            $item = interaction_status_item($db, $fb, $key, $actor);
            if ($item) $items[] = $item;
        }
        withu_json_response(['code' => 200, 'data' => ['items' => $items]]);
    }

    withu_json_response(['code' => 400, 'msg' => '未知交互动作'], 400);
} catch (Throwable $e) {
    withu_json_response(['code' => 500, 'msg' => '服务异常，请稍后重试'], 500);
}
