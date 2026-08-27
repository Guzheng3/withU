<?php
/** SITE_NAME 兜底：config/config.php(不入库) 未定义时用默认值，避免前台致命错误 */
if (!defined('SITE_NAME')) {
    define('SITE_NAME', '我们的小情侣网站');
}
/** withU 共享业务辅助函数。 */

// 厂长资源(cz / 4kcz.com) 总开关：默认 false=暂时屏蔽（部署到别处也不显示 cz 源）。
// 本地如需启用，在 config/config.php（安装向导生成、不入 git）里先 define 覆盖为 true 即可。
if (!defined('WITHU_CZ_ENABLED')) define('WITHU_CZ_ENABLED', false);

if (!function_exists('withu_require_couple_user')) {
    function withu_require_couple_user(Auth $auth): array {
        $auth->requireLogin();
        $user = $auth->getCurrentUser();
        if (!$user || !in_array($user['role'] ?? '', ['user1', 'user2'], true)) {
            http_response_code(403);
            exit('仅情侣账号可以使用此功能');
        }
        // GET endpoints only read authentication state; release the session
        // lock so frequent watch polling cannot serialize other requests.
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        return $user;
    }
}

if (!function_exists('withu_json_body')) {
    function withu_json_body(): array {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') return [];
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('withu_json_response')) {
    function withu_json_response(array $payload, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('withu_require_json_csrf')) {
    function withu_require_json_csrf(array $body = []): void {
        $token = (string)($body['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if (!csrf_verify($token)) {
            withu_json_response(['success' => false, 'message' => '请求已过期，请刷新页面'], 400);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
}

if (!function_exists('withu_now')) {
    function withu_now(): string { return date('Y-m-d H:i:s'); }
}

if (!function_exists('withu_token')) {
    function withu_token(int $bytes = 24): string { return bin2hex(random_bytes($bytes)); }
}

if (!function_exists('withu_hash_token')) {
    function withu_hash_token(string $token): string { return hash('sha256', $token); }
}

if (!function_exists('withu_watch_history_min_ms')) {
    function withu_watch_history_min_ms(): int {
        $seconds = (int)get_setting('watch_history_min_sec', '15');
        if ($seconds < 5) $seconds = 5;
        return $seconds * 1000;
    }
}

if (!function_exists('withu_private_location')) {
    function withu_private_location(array $row, bool $isPrivateViewer): array {
        $visibility = (string)($row['location_visibility'] ?? 'private');
        $lat = $row['latitude'] ?? null;
        $lng = $row['longitude'] ?? null;
        if ($isPrivateViewer || $visibility === 'public') {
            return ['name' => $row['location_name'] ?? '', 'latitude' => $lat, 'longitude' => $lng, 'precision' => 'exact'];
        }
        if ($lat !== null && $lng !== null) {
            return ['name' => $row['location_name'] ?? '', 'latitude' => round((float)$lat, 2), 'longitude' => round((float)$lng, 2), 'precision' => 'approximate'];
        }
        return ['name' => $row['location_name'] ?? '', 'latitude' => null, 'longitude' => null, 'precision' => 'hidden'];
    }
}

