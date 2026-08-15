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

if (!function_exists('withu_media_url')) {
    function withu_media_url(array $media): string {
        $direct = trim((string)($media['direct_url'] ?? ''));
        if ($direct !== '') return $direct;
        return trim((string)($media['source_url'] ?? ''));
    }
}

if (!function_exists('withu_media_stream_url')) {
    function withu_media_stream_url(array $media): string {
        $source = (string)($media['source_url'] ?? '');
        $webdav = rtrim((string)get_setting('openlist_webdav_url', ''), '/');
        $mediaId = (int)($media['media_id'] ?? $media['id'] ?? 0);
        $sourceHost = (string)(parse_url($source, PHP_URL_HOST) ?: '');
        $openlistHost = (string)(parse_url($webdav, PHP_URL_HOST) ?: '');
        $isOpenListSource = $webdav !== '' && (strpos($source, $webdav) === 0 || ($sourceHost !== '' && $sourceHost === $openlistHost));
        return $isOpenListSource ? '/api/media_stream.php?id=' . $mediaId : '';
    }
}

if (!function_exists('withu_media_is_openlist_source')) {
    function withu_media_is_openlist_source(array $media): bool {
        $source = trim((string)($media['source_url'] ?? ''));
        $webdav = rtrim((string)get_setting('openlist_webdav_url', ''), '/');
        if ($source === '' || $webdav === '') return false;
        $sourceHost = (string)(parse_url($source, PHP_URL_HOST) ?: '');
        $openlistHost = (string)(parse_url($webdav, PHP_URL_HOST) ?: '');
        return strpos($source, $webdav) === 0 || ($sourceHost !== '' && $sourceHost === $openlistHost);
    }
}

if (!function_exists('withu_media_player_mode')) {
    function withu_media_player_mode(array $media): string {
        return 'direct';
    }
}

if (!function_exists('withu_media_player_code')) {
    function withu_media_player_code(array $media): string {
        return 'webdav';
    }
}

if (!function_exists('withu_media_stream_type')) {
    function withu_media_stream_type(string $url, string $mime = ''): string {
        $mime = strtolower(trim($mime));
        if (str_contains($mime, 'mpegurl') || preg_match('/\.m3u8(?:[?#]|$)/i', $url)) return 'm3u8';
        if (str_contains($mime, 'webm') || preg_match('/\.webm(?:[?#]|$)/i', $url)) return 'webm';
        return 'mp4';
    }
}

if (!function_exists('withu_media_resolve_url')) {
    function withu_media_resolve_url(array $media): string {
        $mediaId = (int)($media['media_id'] ?? $media['id'] ?? 0);
        if ($mediaId <= 0) return withu_media_url($media);
        $url = '/api/media_resolve.php?id=' . $mediaId;
        $sourceId = (int)($media['source_id'] ?? 0);
        if ($sourceId > 0) $url .= '&source_id=' . $sourceId;
        return $url;
    }
}

if (!function_exists('withu_resolution_tier')) {
    function withu_resolution_tier($resolution): ?array {
        $text = strtoupper(trim((string)$resolution));
        if ($text === '') return null;
        if (preg_match('/\b(4K|UHD|2160P)\b/u', $text)) return ['label' => '4K', 'class' => 'is-4k'];
        if (preg_match('/\b(2K|QHD|1440P)\b/u', $text)) return ['label' => '2K', 'class' => 'is-2k'];
        if (preg_match('/蓝光|BLU[\s-]?RAY|BDMV/u', $text)) return ['label' => '蓝光', 'class' => 'is-bluray'];
        if (preg_match('/(\d{3,5})\s*[x×]\s*(\d{3,5})/u', $text, $match)) {
            $height = (int)$match[2];
        } elseif (preg_match('/(\d{3,5})\s*P?\b/u', $text, $match)) {
            $height = (int)$match[1];
        } else {
            return null;
        }
        if ($height >= 2000) return ['label' => '4K', 'class' => 'is-4k'];
        if ($height >= 1300) return ['label' => '2K', 'class' => 'is-2k'];
        return null;
    }
}

if (!function_exists('withu_resolution_badge_html')) {
    function withu_resolution_badge_html($resolution, string $extraClass = ''): string {
        $tier = withu_resolution_tier($resolution);
        if (!$tier) return '';
        $class = trim('resolution-badge ' . $tier['class'] . ' ' . $extraClass);
        if ($tier['label'] === '4K') {
            return '<span class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"><img src="/assets/images/4k-badge.png" alt="4K"></span>';
        }
        return '<span class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($tier['label'], ENT_QUOTES, 'UTF-8') . '</span>';
    }
}

if (!function_exists('withu_media_quality_text')) {
    function withu_media_quality_text(array $media): string {
        $parts = [];
        $resolution = trim((string)($media['resolution'] ?? ''));
        if ($resolution !== '') $parts[] = $resolution;
        $metadata = json_decode((string)($media['metadata_json'] ?? '{}'), true);
        if (is_array($metadata)) {
            $releaseSource = trim((string)($metadata['release_source'] ?? ''));
            if ($releaseSource !== '') $parts[] = $releaseSource;
        }
        $source = trim((string)($media['source_key'] ?? $media['file_name'] ?? ''));
        if ($source !== '') $parts[] = $source;
        return implode(' ', $parts);
    }
}

if (!function_exists('withu_media_quality_badge_html')) {
    function withu_media_quality_badge_html(array $media, string $extraClass = ''): string {
        return withu_resolution_badge_html(withu_media_quality_text($media), $extraClass);
    }
}

if (!function_exists('withu_watch_history_min_ms')) {
    function withu_watch_history_min_ms(): int {
        $seconds = (int)get_setting('watch_history_min_sec', '30');
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
