<?php
/**
 * 和风天气（QWeather）服务端客户端。
 *
 * 设计目标：
 * - API Key / JWT（静态 Token 或 Ed25519 自动签发）两种认证都由服务端处理，
 *   Key、Token、私钥绝不下发到网页或 App。
 * - 供 withU 网站（/services/weather.php）与清涵课表 App
 *   （/api/timetable.php?action=weather）共用同一套配置、缓存与映射。
 * - 上游异常时优先回退未过期的旧缓存，天气只是装饰性信息，不阻断页面/课表。
 *
 * 依赖 helpers.php 的 get_setting()；单独引用本文件时请先加载配置与 helpers。
 */

if (!defined('WITHU_WEATHER_CACHE_DIR')) {
    define('WITHU_WEATHER_CACHE_DIR', dirname(__DIR__) . '/runtime/weather-cache');
}

if (!function_exists('withu_weather_normalize_host')) {
    /**
     * 规范化后台填写的 API Host：
     * - 允许只填域名（自动补 https://）；
     * - 去掉末尾斜杠与误粘贴的 /v7 路径；
     * - 仅回环地址允许保留 http（本地 mock/测试用），其余强制 https。
     */
    function withu_weather_normalize_host(string $host): string
    {
        $host = trim($host);
        if ($host === '') {
            return '';
        }

        $hasScheme = (bool)preg_match('#^([a-z][a-z0-9+.-]*)://#i', $host, $schemeMatch);
        if ($hasScheme && !in_array(strtolower($schemeMatch[1]), ['http', 'https'], true)) {
            return '';
        }
        if (!$hasScheme) {
            $host = 'https://' . $host;
        }

        $host = rtrim($host, '/');
        $host = preg_replace('#/v7(?:/.*)?$#i', '', $host) ?? $host;
        if (preg_match('#^http://(?:localhost|127\.0\.0\.1|\[::1\])(?::\d+)?$#i', $host) !== 1) {
            $host = preg_replace('#^http://#i', 'https://', $host) ?? $host;
        }

        $parts = parse_url($host);
        if (!is_array($parts) || empty($parts['host'])) {
            return '';
        }
        $hostHeader = $parts['host'];
        if (isset($parts['port'])) {
            $hostHeader .= ':' . $parts['port'];
        }
        if (!preg_match('/^(?:\[[0-9a-f:.]+\]|[a-z0-9.-]+)(?::\d{1,5})?$/i', $hostHeader)) {
            return '';
        }
        return rtrim($host, '/');
    }
}

if (!function_exists('withu_weather_config')) {
    /**
     * 读取并归一化和风天气配置。
     * auth_type=auto 时按「静态 Token → API Key → JWT 私钥」顺序自动选择。
     */
    function withu_weather_config(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $get = static function (string $key, string $default = ''): string {
            if (!function_exists('get_setting')) {
                return $default;
            }
            $value = get_setting($key, $default);
            return is_scalar($value) ? (string)$value : $default;
        };

        $host = withu_weather_normalize_host($get('qweather_api_host'));
        $apiKey = trim($get('qweather_api_key'));
        $jwtToken = trim($get('qweather_jwt_token'));
        // 允许后台直接粘贴完整的 Authorization 值
        $jwtToken = preg_replace('/^Bearer\s+/i', '', $jwtToken) ?? $jwtToken;
        $jwtKid = trim($get('qweather_jwt_key_id'));
        $jwtIss = trim($get('qweather_jwt_developer_id'));
        $jwtSub = trim($get('qweather_jwt_project_id'));
        $jwtPrivateKey = trim($get('qweather_jwt_private_key'));

        $authType = strtolower(trim($get('qweather_auth_type', 'auto')));
        if (!in_array($authType, ['auto', 'apikey', 'jwt'], true)) {
            $authType = 'auto';
        }

        $jwtAutoReady = $jwtKid !== '' && $jwtIss !== '' && $jwtSub !== '' && $jwtPrivateKey !== '';
        $jwtReady = $jwtToken !== '' || $jwtAutoReady;

        $mode = $authType;
        if ($mode === 'auto') {
            if ($jwtToken !== '') {
                $mode = 'jwt';
            } elseif ($apiKey !== '') {
                $mode = 'apikey';
            } elseif ($jwtAutoReady) {
                $mode = 'jwt';
            }
        }
        // 显式选择的认证方式缺少凭据时，自动回退到另一种可用方式，避免
        // 后台只改了一半配置后天气整体失效。
        if ($mode === 'apikey' && $apiKey === '' && $jwtReady) {
            $mode = 'jwt';
        } elseif ($mode === 'jwt' && !$jwtReady && $apiKey !== '') {
            $mode = 'apikey';
        }

        $lang = strtolower(trim($get('qweather_lang', 'zh')));
        if (!preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/', $lang)) {
            $lang = 'zh';
        }
        $unit = strtolower(trim($get('qweather_unit', 'm')));
        if (!in_array($unit, ['m', 'i'], true)) {
            $unit = 'm';
        }

        $ready = $host !== ''
            && (($mode === 'apikey' && $apiKey !== '')
                || ($mode === 'jwt' && $jwtReady));

        $cached = [
            'host' => $host,
            'auth_type' => $authType,
            'mode' => $mode,
            'api_key' => $apiKey,
            'jwt_token' => $jwtToken,
            'jwt_kid' => $jwtKid,
            'jwt_iss' => $jwtIss,
            'jwt_sub' => $jwtSub,
            'jwt_private_key' => $jwtPrivateKey,
            'lang' => $lang,
            'unit' => $unit,
            'ready' => $ready,
        ];
        return $cached;
    }
}

if (!function_exists('withu_weather_error')) {
    function withu_weather_error(string $error, string $message, int $httpStatus = 502, string $upstreamCode = ''): array
    {
        $payload = [
            'success' => false,
            'error' => $error,
            'message' => $message,
            'http_status' => $httpStatus,
            'source' => 'qweather',
        ];
        if ($upstreamCode !== '') {
            $payload['upstream_code'] = $upstreamCode;
        }
        return $payload;
    }
}

if (!function_exists('withu_weather_error_for_code')) {
    /** 把和风返回的 code 映射为稳定错误码；日志与响应里都不带凭据。 */
    function withu_weather_error_for_code(string $apiCode, int $httpStatus = 0): array
    {
        $code = trim($apiCode);
        switch ($code) {
            case '400':
                return withu_weather_error('weather_invalid_params', '天气请求参数不正确', 400, $code);
            case '401':
                return withu_weather_error('weather_auth_failed', '和风天气认证失败，请检查 API Key 或 Token 配置', 502, $code);
            case '402':
                return withu_weather_error('weather_quota_exceeded', '和风天气额度已用尽', 502, $code);
            case '403':
                return withu_weather_error('weather_auth_failed', '和风天气无访问权限，请检查套餐与凭据', 502, $code);
            case '404':
                return withu_weather_error('weather_not_found', '未找到对应城市或天气数据', 404, $code);
            case '429':
                return withu_weather_error('weather_rate_limited', '天气请求过于频繁，请稍后再试', 429, $code);
            case '204':
                return withu_weather_error('weather_no_data', '暂无天气数据', 404, $code);
            default:
                break;
        }

        if ($httpStatus === 401) {
            return withu_weather_error('weather_auth_failed', '和风天气认证失败，请检查 API Key 或 Token 配置', 502, $code);
        }
        if ($httpStatus === 429) {
            return withu_weather_error('weather_rate_limited', '天气请求过于频繁，请稍后再试', 429, $code);
        }
        return withu_weather_error('weather_upstream_error', '天气服务暂时不可用', 502, $code);
    }
}

if (!function_exists('withu_weather_cache_dir')) {
    function withu_weather_cache_dir(): string
    {
        $dir = WITHU_WEATHER_CACHE_DIR;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }
}

if (!function_exists('withu_weather_cache_read')) {
    /**
     * 读取缓存。$allowStale=true 时允许返回过期数据（最多 24 小时），
     * 用于上游故障时的兜底。
     */
    function withu_weather_cache_read(string $key, int $ttl, bool $allowStale): ?array
    {
        $file = withu_weather_cache_dir() . '/' . sha1($key) . '.json';
        if (!is_file($file)) {
            return null;
        }
        $raw = @file_get_contents($file);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['payload']) || !is_array($decoded['payload'])) {
            return null;
        }
        $age = time() - (int)($decoded['ts'] ?? 0);
        if ($allowStale) {
            if ($age > 86400) {
                return null;
            }
        } elseif ($age > $ttl) {
            return null;
        }

        $payload = $decoded['payload'];
        $payload['cached'] = $age <= $ttl;
        $payload['stale'] = $age > $ttl;
        return $payload;
    }
}

if (!function_exists('withu_weather_cache_write')) {
    function withu_weather_cache_write(string $key, array $payload): void
    {
        $file = withu_weather_cache_dir() . '/' . sha1($key) . '.json';
        $encoded = json_encode(
            ['ts' => time(), 'payload' => $payload],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if ($encoded === false) {
            return;
        }
        // 先写临时文件再 rename，避免并发请求读到半份 JSON。
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $encoded, LOCK_EX) !== false) {
            @chmod($tmp, 0600);
            if (!@rename($tmp, $file)) {
                @unlink($tmp);
            }
        }
    }
}

if (!function_exists('withu_weather_rate_limit')) {
    /**
     * 轻量文件限流（默认 180 次/分钟）。公开的网站天气接口会消耗和风额度，
     * 这里只做基础防刷；文件不可写时 fail-open，不影响正常天气展示。
     */
    function withu_weather_rate_limit(string $scope, int $limit = 180, int $window = 60): bool
    {
        $ip = function_exists('getClientIp') ? getClientIp() : (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $file = withu_weather_cache_dir() . '/rate-' . sha1($scope . '|' . $ip) . '.json';
        $handle = @fopen($file, 'c+');
        if (!$handle) {
            return true;
        }
        if (!@flock($handle, LOCK_EX)) {
            fclose($handle);
            return true;
        }

        $now = time();
        $raw = stream_get_contents($handle);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        $start = $now;
        $count = 0;
        if (is_array($decoded) && ($now - (int)($decoded['start'] ?? 0)) < $window) {
            $start = (int)($decoded['start'] ?? $now);
            $count = (int)($decoded['count'] ?? 0);
        }

        $allowed = $count < $limit;
        if ($allowed) {
            $count++;
        }
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode(['start' => $start, 'count' => $count]));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
        @chmod($file, 0600);

        return $allowed;
    }
}

if (!function_exists('withu_weather_b64url')) {
    function withu_weather_b64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

if (!function_exists('withu_weather_private_key_normalize')) {
    function withu_weather_private_key_normalize(string $pem): string
    {
        $pem = trim($pem);
        if ($pem === '') {
            return '';
        }
        // 后台 textarea 偶尔会把换行存成字面量 \n
        return str_replace(["\\r\\n", "\\n", "\\r"], ["\n", "\n", ''], $pem);
    }
}

if (!function_exists('withu_weather_ed25519_seed')) {
    /** 从 Ed25519 PKCS#8 PEM / 原始 32 字节 base64 中取出 32 字节 seed。 */
    function withu_weather_ed25519_seed(string $pem): ?string
    {
        $pem = withu_weather_private_key_normalize($pem);
        if ($pem === '') {
            return null;
        }

        if (strpos($pem, 'BEGIN') === false) {
            $raw = base64_decode(preg_replace('/\s+/', '', $pem) ?? '', true);
            return is_string($raw) && strlen($raw) === 32 ? $raw : null;
        }

        if (function_exists('openssl_pkey_get_private')) {
            $key = @openssl_pkey_get_private($pem);
            if ($key !== false && function_exists('openssl_pkey_get_details')) {
                $details = @openssl_pkey_get_details($key);
                if (is_array($details)
                    && isset($details['ed25519']['priv_key'])
                    && is_string($details['ed25519']['priv_key'])
                    && strlen($details['ed25519']['priv_key']) === 32) {
                    return $details['ed25519']['priv_key'];
                }
            }
        }

        // 回退：直接解析 PKCS#8 DER，最后一个 04 20 后面就是 32 字节 seed。
        $der = @base64_decode(preg_replace('/-----[^-]+-----/', '', $pem) ?? '', true);
        if (!is_string($der) || $der === '') {
            return null;
        }
        if (strlen($der) === 32) {
            return $der;
        }
        // 标准 PKCS#8 Ed25519 私钥 DER 固定 48 字节，最后 32 字节就是 seed。
        if (strlen($der) === 48 && strncmp($der, "\x30\x2e", 2) === 0) {
            return substr($der, -32);
        }
        $pos = strrpos($der, "\x04\x20");
        if ($pos !== false && strlen($der) >= $pos + 34) {
            $seed = substr($der, $pos + 2, 32);
            return strlen($seed) === 32 ? $seed : null;
        }
        return null;
    }
}

if (!function_exists('withu_weather_ed25519_sign')) {
    /** Ed25519 签名：优先 OpenSSL（PHP 8 + OpenSSL 1.1.1+），回退 libsodium。 */
    function withu_weather_ed25519_sign(string $message, string $privateKey): ?string
    {
        $pem = withu_weather_private_key_normalize($privateKey);
        if ($pem === '') {
            return null;
        }

        if (function_exists('openssl_pkey_get_private') && function_exists('openssl_sign')) {
            $key = @openssl_pkey_get_private($pem);
            if ($key !== false) {
                $signature = '';
                // Ed25519 必须使用摘要算法 0；PHP/OpenSSL 不支持时会返回 false。
                if (@openssl_sign($message, $signature, $key, 0) && strlen($signature) === 64) {
                    return $signature;
                }
            }
        }

        if (function_exists('sodium_crypto_sign_detached') && defined('SODIUM_CRYPTO_SIGN_SECRETKEYBYTES')) {
            $seed = withu_weather_ed25519_seed($pem);
            if ($seed !== null) {
                try {
                    $pair = sodium_crypto_sign_seed_keypair($seed);
                    if (is_array($pair)) {
                        $secretKey = $pair[1] ?? ($pair['secretkey'] ?? null);
                    } elseif (is_string($pair) && strlen($pair) >= SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
                        // PHP 8.5 的低层绑定返回 secretkey(64) . publickey(32)
                        $secretKey = substr($pair, 0, SODIUM_CRYPTO_SIGN_SECRETKEYBYTES);
                    } else {
                        $secretKey = null;
                    }
                    if (is_string($secretKey) && strlen($secretKey) === SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
                        return sodium_crypto_sign_detached($message, $secretKey);
                    }
                } catch (Throwable $e) {
                    // 落到下面统一返回 null
                }
            }
        }
        return null;
    }
}

if (!function_exists('withu_weather_jwt_token')) {
    /**
     * 生成 / 复用 JWT。静态 Token 配置优先；否则用 Ed25519 私钥签发 15 分钟
     * 有效期的 token，并缓存到过期前 60 秒。
     */
    function withu_weather_jwt_token(array $cfg): ?string
    {
        $staticToken = trim((string)($cfg['jwt_token'] ?? ''));
        $staticToken = preg_replace('/^Bearer\s+/i', '', $staticToken) ?? $staticToken;
        if ($staticToken !== '') {
            return $staticToken;
        }
        $kid = (string)($cfg['jwt_kid'] ?? '');
        $iss = (string)($cfg['jwt_iss'] ?? '');
        $sub = (string)($cfg['jwt_sub'] ?? '');
        $privateKey = (string)($cfg['jwt_private_key'] ?? '');
        if ($kid === '' || $iss === '' || $sub === '' || $privateKey === '') {
            return null;
        }

        // JWT 只在本次请求内复用（一次天气请求通常要访问天气 + GeoAPI）。
        // 不落盘，避免任何可被读取的 token 文件；Ed25519 签名本身开销极小。
        $cacheKey = hash('sha256', $kid . '|' . $iss . '|' . $sub . '|' . hash('sha256', withu_weather_private_key_normalize($privateKey)));
        static $memo = [];
        if (isset($memo[$cacheKey]) && (int)$memo[$cacheKey]['exp'] > time() + 60) {
            return (string)$memo[$cacheKey]['token'];
        }

        $iat = time() - 30;
        $exp = time() + 900;
        $header = ['alg' => 'EdDSA', 'kid' => $kid];
        $payload = ['iss' => $iss, 'sub' => $sub, 'iat' => $iat, 'exp' => $exp];
        $headerJson = json_encode($header, JSON_UNESCAPED_SLASHES);
        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($headerJson === false || $payloadJson === false) {
            return null;
        }
        $signingInput = withu_weather_b64url($headerJson) . '.' . withu_weather_b64url($payloadJson);
        $signature = withu_weather_ed25519_sign($signingInput, $privateKey);
        if ($signature === null) {
            return null;
        }
        $token = $signingInput . '.' . withu_weather_b64url($signature);

        $memo[$cacheKey] = ['token' => $token, 'exp' => $exp];
        return $token;
    }
}

if (!function_exists('withu_weather_http_get')) {
    function withu_weather_http_get(string $url, array $headers, int $timeout = 8): array
    {
        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => min(4, $timeout),
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_USERAGENT => 'withU-weather/1.0',
                // 和风天气的响应始终带 content-encoding: gzip（即使请求
                // Accept-Encoding: identity），必须让 cURL 自动解压。
                CURLOPT_ENCODING => '',
            ]);
            $body = curl_exec($curl);
            $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $errno = curl_errno($curl);
            $error = curl_error($curl);
            curl_close($curl);

            if ($body === false || $errno !== 0) {
                $timeoutErrno = defined('CURLE_OPERATION_TIMEDOUT') ? CURLE_OPERATION_TIMEDOUT : 28;
                return [
                    'ok' => false,
                    'status' => $status,
                    'body' => '',
                    'error' => $error !== '' ? $error : ('curl#' . $errno),
                    'timeout' => $errno === $timeoutErrno,
                ];
            }
            $body = (string)$body;
            // cURL 未编入 zlib 时不会自动解压，按 gzip 魔数再兜底一次。
            if (strncmp($body, "\x1f\x8b", 2) === 0) {
                $decoded = function_exists('gzdecode') ? @gzdecode($body) : false;
                if (!is_string($decoded)) {
                    return ['ok' => false, 'status' => $status, 'body' => '', 'error' => 'gzip_decode_failed', 'timeout' => false];
                }
                $body = $decoded;
            }
            return ['ok' => true, 'status' => $status, 'body' => $body, 'error' => '', 'timeout' => false];
        }

        $context = @stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', (string)$http_response_header[0], $m)) {
            $status = (int)$m[1];
        }
        if ($body === false) {
            return ['ok' => false, 'status' => $status, 'body' => '', 'error' => 'network', 'timeout' => false];
        }
        // file_get_contents 不会像 cURL 那样自动解压，按 gzip 魔数手动解。
        if (strncmp($body, "\x1f\x8b", 2) === 0) {
            $decoded = function_exists('gzdecode') ? @gzdecode($body) : false;
            if (!is_string($decoded)) {
                return ['ok' => false, 'status' => $status, 'body' => '', 'error' => 'gzip_decode_failed', 'timeout' => false];
            }
            $body = $decoded;
        }
        return ['ok' => true, 'status' => $status, 'body' => (string)$body, 'error' => '', 'timeout' => false];
    }
}

if (!function_exists('withu_weather_api')) {
    /** 请求和风 API；返回 success/data 或稳定错误。 */
    function withu_weather_api(array $cfg, string $path, array $query, int $timeout = 8): array
    {
        if (empty($cfg['ready']) || empty($cfg['host'])) {
            return withu_weather_error('weather_not_configured', '天气服务尚未配置', 503);
        }

        $url = rtrim((string)$cfg['host'], '/') . '/' . ltrim($path, '/');
        if ($query) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $headers = ['Accept: application/json', 'User-Agent: withU-weather/1.0'];
        if (($cfg['mode'] ?? '') === 'apikey') {
            if ((string)($cfg['api_key'] ?? '') === '') {
                return withu_weather_error('weather_not_configured', '天气服务尚未配置 API Key', 503);
            }
            $headers[] = 'X-QW-Api-Key: ' . $cfg['api_key'];
        } else {
            $token = withu_weather_jwt_token($cfg);
            if ($token === null) {
                return withu_weather_error('weather_auth_failed', '天气服务 JWT 签发失败，请检查私钥配置', 502);
            }
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $response = withu_weather_http_get($url, $headers, $timeout);
        if (empty($response['ok'])) {
            return withu_weather_error(
                !empty($response['timeout']) ? 'weather_timeout' : 'weather_network_failed',
                !empty($response['timeout']) ? '天气服务请求超时' : '无法连接天气服务',
                !empty($response['timeout']) ? 504 : 502
            );
        }

        $decoded = json_decode((string)$response['body'], true);
        if (!is_array($decoded)) {
            return withu_weather_error('weather_invalid_response', '天气服务返回内容无法解析', 502);
        }
        $apiCode = isset($decoded['code']) && is_scalar($decoded['code']) ? (string)$decoded['code'] : '';
        if ($apiCode === '204') {
            return [
                'success' => true,
                'data' => ['_no_data' => true],
                'http_status' => 200,
                'source' => 'qweather',
            ];
        }
        if ($apiCode !== '200') {
            return withu_weather_error_for_code($apiCode, (int)$response['status']);
        }

        return [
            'success' => true,
            'data' => $decoded,
            'http_status' => 200,
            'source' => 'qweather',
        ];
    }
}

if (!function_exists('withu_weather_cached_api')) {
    /**
     * 带文件缓存的 API 请求：先读新鲜缓存，失败时回退 24h 内的旧缓存。
     * 缓存 key 不包含 Key/Token/私钥，避免凭据落盘。
     */
    function withu_weather_cached_api(array $cfg, string $path, array $query, int $ttl, int $timeout = 8): array
    {
        $cacheKey = ($cfg['host'] ?? '') . '|' . $path . '|' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        $cached = withu_weather_cache_read($cacheKey, $ttl, false);
        if ($cached !== null) {
            $cached['cached'] = true;
            $cached['stale'] = false;
            return $cached;
        }

        $fresh = withu_weather_api($cfg, $path, $query, $timeout);
        if (!empty($fresh['success'])) {
            $fresh['cached'] = false;
            $fresh['stale'] = false;
            withu_weather_cache_write($cacheKey, $fresh);
            return $fresh;
        }

        $stale = withu_weather_cache_read($cacheKey, $ttl, true);
        if ($stale !== null) {
            $stale['cached'] = false;
            $stale['stale'] = true;
            return $stale;
        }
        return $fresh;
    }
}

if (!function_exists('withu_weather_valid_coords')) {
    function withu_weather_valid_coords($lat, $lng): bool
    {
        if (!is_numeric($lat) || !is_numeric($lng)) {
            return false;
        }
        $lat = (float)$lat;
        $lng = (float)$lng;
        return is_finite($lat) && is_finite($lng)
            && $lat >= -90 && $lat <= 90
            && $lng >= -180 && $lng <= 180;
    }
}

if (!function_exists('withu_weather_location_param')) {
    /** 和风坐标参数顺序是「经度,纬度」，最多两位小数。 */
    function withu_weather_location_param(float $lat, float $lng): string
    {
        return sprintf('%.2f,%.2f', $lng, $lat);
    }
}

if (!function_exists('withu_weather_sanitize_lang')) {
    function withu_weather_sanitize_lang($lang, string $default = 'zh'): string
    {
        $value = strtolower(trim((string)$lang));
        return preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/', $value) ? $value : $default;
    }
}

if (!function_exists('withu_weather_sanitize_unit')) {
    function withu_weather_sanitize_unit($unit, string $default = 'm'): string
    {
        $value = strtolower(trim((string)$unit));
        return in_array($value, ['m', 'i'], true) ? $value : $default;
    }
}

if (!function_exists('withu_weather_int')) {
    function withu_weather_int($value): ?int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }
        return (int)round((float)$value);
    }
}

if (!function_exists('withu_weather_float')) {
    function withu_weather_float($value): ?float
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }
        return (float)$value;
    }
}

if (!function_exists('withu_weather_parse_time')) {
    function withu_weather_parse_time($value): ?DateTimeImmutable
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        try {
            return new DateTimeImmutable($value);
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('withu_weather_wmo_from_icon')) {
    /** 和风图标码 -> WMO 码，供课表 App 现有天气分类/本地化逻辑复用。 */
    function withu_weather_wmo_from_icon($icon): int
    {
        $icon = trim((string)$icon);
        if (!preg_match('/^\d+$/', $icon)) {
            return 0;
        }
        $n = (int)$icon;

        if (in_array($n, [100, 150], true)) return 0;
        if (in_array($n, [102, 152], true)) return 1;
        if (in_array($n, [101, 103, 151, 153], true)) return 2;
        if ($n === 104) return 3;

        // 雾 / 霾 / 沙尘：WMO 子集里统一归入雾类
        if (in_array($n, [500, 501, 502, 503, 504, 507, 508, 509, 510, 511, 512, 513, 514, 515], true)) {
            return 45;
        }

        if ($n === 300) return 80;
        if ($n === 301) return 82;
        if (in_array($n, [302, 303], true)) return 95;
        if ($n === 304) return 96;
        if (in_array($n, [305, 309, 314, 399], true)) return 61;
        if (in_array($n, [306, 315], true)) return 63;
        if (in_array($n, [307, 308, 310, 311, 312, 316, 317, 318], true)) return 65;
        if ($n === 313) return 66;

        if (in_array($n, [400, 408, 499], true)) return 71;
        if (in_array($n, [401, 409], true)) return 73;
        if (in_array($n, [402, 403, 410], true)) return 75;
        if (in_array($n, [404, 405], true)) return 66;
        if (in_array($n, [406, 407], true)) return 85;

        return 0;
    }
}

if (!function_exists('withu_weather_normalize_location')) {
    /** 把和风 GeoAPI 结果同时映射成 qhkb WeatherLocation 与原始字段两套形状。 */
    function withu_weather_normalize_location(array $item): array
    {
        $lat = withu_weather_float($item['lat'] ?? null);
        $lon = withu_weather_float($item['lon'] ?? null);
        return [
            // qhkb WeatherLocation.fromJson
            'name' => (string)($item['name'] ?? ''),
            'latitude' => $lat,
            'longitude' => $lon,
            'admin1' => $item['adm1'] ?? null,
            'country' => $item['country'] ?? null,
            'district' => null,
            'timezone' => $item['tz'] ?? null,
            'adcode' => $item['id'] ?? null,

            // 和风原始字段
            'id' => $item['id'] ?? null,
            'lat' => $item['lat'] ?? null,
            'lon' => $item['lon'] ?? null,
            'adm1' => $item['adm1'] ?? null,
            'adm2' => $item['adm2'] ?? null,
            'tz' => $item['tz'] ?? null,
            'utcOffset' => $item['utcOffset'] ?? null,
            'type' => $item['type'] ?? null,
            'rank' => $item['rank'] ?? null,
        ];
    }
}

if (!function_exists('withu_weather_lookup')) {
    /**
     * GeoAPI 城市搜索 / 坐标反查。
     * $query 可以是城市名、LocationID，或「经度,纬度」。
     */
    function withu_weather_lookup(string $query, array $options = []): array
    {
        $cfg = $options['cfg'] ?? withu_weather_config();
        if (empty($cfg['ready'])) {
            return withu_weather_error('weather_not_configured', '天气服务尚未配置', 503);
        }
        $query = trim($query);
        if ($query === '') {
            return withu_weather_error('weather_invalid_params', '缺少查询地点', 400);
        }

        $number = (int)($options['number'] ?? 10);
        if ($number < 1 || $number > 20) {
            $number = 10;
        }
        $lang = withu_weather_sanitize_lang($options['lang'] ?? $cfg['lang'], $cfg['lang']);

        $response = withu_weather_cached_api($cfg, '/geo/v2/city/lookup', [
            'location' => $query,
            'number' => $number,
            'lang' => $lang,
        ], 86400);

        if (empty($response['success'])) {
            return $response;
        }
        if (!empty($response['data']['_no_data'])) {
            $response['data'] = ['locations' => []];
            return $response;
        }

        $raw = $response['data']['location'] ?? [];
        if (isset($raw['name'])) {
            $raw = [$raw];
        }
        $locations = [];
        if (is_array($raw)) {
            foreach ($raw as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $location = withu_weather_normalize_location($item);
                if ($location['name'] === '' || $location['latitude'] === null || $location['longitude'] === null) {
                    continue;
                }
                $locations[] = $location;
            }
        }
        $response['data'] = ['locations' => $locations];
        return $response;
    }
}

if (!function_exists('withu_weather_locate')) {
    /**
     * 解析坐标对应的地点。$label 非空时直接作为显示名，跳过 GeoAPI（省一次配额）；
     * 否则反查最近城市，失败时退化为仅带坐标的空地点。
     */
    function withu_weather_locate(float $lat, float $lng, array $cfg, string $label = ''): array
    {
        $label = trim($label);
        $fallback = [
            'name' => $label,
            'latitude' => $lat,
            'longitude' => $lng,
            'admin1' => null,
            'country' => null,
            'district' => null,
            'timezone' => null,
            'adcode' => null,
        ];
        if ($label !== '') {
            return $fallback;
        }

        $lookup = withu_weather_lookup(withu_weather_location_param($lat, $lng), [
            'cfg' => $cfg,
            'number' => 1,
        ]);
        if (!empty($lookup['success']) && !empty($lookup['data']['locations'][0])) {
            $location = $lookup['data']['locations'][0];
            // 请求坐标比和风返回的城市中心更精确，响应里保留原始坐标。
            $location['latitude'] = $lat;
            $location['longitude'] = $lng;
            return $location;
        }
        return $fallback;
    }
}

if (!function_exists('withu_weather_map_now')) {
    function withu_weather_map_now(array $now, array $location): array
    {
        $temp = withu_weather_int($now['temp'] ?? null);
        $feelsLike = withu_weather_int($now['feelsLike'] ?? null);
        $humidity = withu_weather_int($now['humidity'] ?? null);
        $vis = withu_weather_int($now['vis'] ?? null);
        $icon = trim((string)($now['icon'] ?? ''));
        if ($icon === '') {
            $icon = '999';
        }
        $text = trim((string)($now['text'] ?? ''));
        if ($text === '') {
            $text = '未知';
        }
        $windDir = trim((string)($now['windDir'] ?? ''));
        $windScale = trim((string)($now['windScale'] ?? ''));
        $obsTime = trim((string)($now['obsTime'] ?? ''));
        $obsAt = withu_weather_parse_time($obsTime);
        $reportTime = $obsAt !== null ? $obsAt->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');
        $obsIso = $obsTime !== ''
            ? $obsTime
            : ($obsAt !== null ? $obsAt->format(DATE_ATOM) : date(DATE_ATOM));
        $city = (string)($location['name'] ?? '');

        return [
            // qhkb CurrentWeather.fromAmapLive 兼容字段
            'weather' => $text,
            'temperature' => $temp === null ? '--' : (string)$temp,
            'humidity' => $humidity === null ? '--' : (string)$humidity,
            'winddirection' => $windDir !== '' ? $windDir : '--',
            'windpower' => $windScale !== '' ? $windScale : '--',
            'reporttime' => $reportTime,

            // withU 网页既有契约字段
            'temp' => $temp === null ? '--' : $temp,
            'feelsLike' => $feelsLike === null ? '--' : $feelsLike,
            'desc' => $text,
            'icon' => $icon,
            'windDir' => $windDir !== '' ? $windDir : '--',
            'windScale' => $windScale !== '' ? $windScale : '--',
            'vis' => $vis === null ? '--' : $vis,
            'city' => $city,
            'obsTime' => $obsIso,
            'available' => true,
            'source' => 'qweather',

            // 和风原始/附加字段
            'wmo_code' => withu_weather_wmo_from_icon($icon),
            'wind360' => withu_weather_int($now['wind360'] ?? null),
            'windSpeed' => withu_weather_int($now['windSpeed'] ?? null),
            'precip' => withu_weather_float($now['precip'] ?? null),
            'pressure' => withu_weather_int($now['pressure'] ?? null),
            'cloud' => withu_weather_int($now['cloud'] ?? null),
            'dew' => withu_weather_int($now['dew'] ?? null),
        ];
    }
}

if (!function_exists('withu_weather_now')) {
    function withu_weather_now($lat, $lng, array $options = []): array
    {
        $cfg = $options['cfg'] ?? withu_weather_config();
        if (empty($cfg['ready'])) {
            return withu_weather_error('weather_not_configured', '天气服务尚未配置', 503);
        }
        if (!withu_weather_valid_coords($lat, $lng)) {
            return withu_weather_error('weather_invalid_params', '经纬度参数不正确', 400);
        }
        $lat = (float)$lat;
        $lng = (float)$lng;
        $lang = withu_weather_sanitize_lang($options['lang'] ?? $cfg['lang'], $cfg['lang']);
        $unit = withu_weather_sanitize_unit($options['unit'] ?? $cfg['unit'], $cfg['unit']);

        $response = withu_weather_cached_api($cfg, '/v7/weather/now', [
            'location' => withu_weather_location_param($lat, $lng),
            'lang' => $lang,
            'unit' => $unit,
        ], 300);

        if (empty($response['success'])) {
            return $response;
        }
        if (!empty($response['data']['_no_data']) || !is_array($response['data']['now'] ?? null)) {
            return withu_weather_error('weather_no_data', '暂无天气数据', 404);
        }

        $location = withu_weather_locate($lat, $lng, $cfg, (string)($options['label'] ?? ''));
        $response['data'] = withu_weather_map_now($response['data']['now'], $location);
        $response['location'] = $location;
        $response['updated_at'] = (string)($response['data']['obsTime'] ?? '');
        return $response;
    }
}

if (!function_exists('withu_weather_hourly')) {
    /**
     * 逐小时预报。输出与 Open-Meteo 同构的列式结构，方便 qhkb 复用现有
     * WeatherForecast.fromOpenMeteoResponse。
     */
    function withu_weather_hourly($lat, $lng, array $options = []): array
    {
        $cfg = $options['cfg'] ?? withu_weather_config();
        if (empty($cfg['ready'])) {
            return withu_weather_error('weather_not_configured', '天气服务尚未配置', 503);
        }
        if (!withu_weather_valid_coords($lat, $lng)) {
            return withu_weather_error('weather_invalid_params', '经纬度参数不正确', 400);
        }
        $lat = (float)$lat;
        $lng = (float)$lng;
        $hours = (int)($options['hours'] ?? 24);
        if (!in_array($hours, [24, 72, 168], true)) {
            $hours = 24;
        }
        $lang = withu_weather_sanitize_lang($options['lang'] ?? $cfg['lang'], $cfg['lang']);
        $unit = withu_weather_sanitize_unit($options['unit'] ?? $cfg['unit'], $cfg['unit']);
        $path = '/v7/weather/' . $hours . 'h';

        $response = withu_weather_cached_api($cfg, $path, [
            'location' => withu_weather_location_param($lat, $lng),
            'lang' => $lang,
            'unit' => $unit,
        ], 1800);

        if (empty($response['success'])) {
            return $response;
        }
        $raw = $response['data']['hourly'] ?? null;
        $updateTime = (string)($response['data']['updateTime'] ?? '');
        if (!is_array($raw) || !$raw) {
            return withu_weather_error('weather_no_data', '暂无逐小时预报', 404);
        }

        $columns = [
            'time' => [],
            'temperature_2m' => [],
            'precipitation_probability' => [],
            'precipitation' => [],
            'snowfall' => [],
            'weather_code' => [],
        ];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $time = withu_weather_parse_time($item['fxTime'] ?? null);
            if ($time === null) {
                continue;
            }
            $columns['time'][] = $time->format('Y-m-d\TH:i');
            $columns['temperature_2m'][] = withu_weather_float($item['temp'] ?? null);
            $columns['precipitation_probability'][] = withu_weather_int($item['pop'] ?? null);
            $columns['precipitation'][] = withu_weather_float($item['precip'] ?? null);
            // 和风小时预报没有降雪量，返回 null，客户端模型允许缺报。
            $columns['snowfall'][] = null;
            $columns['weather_code'][] = withu_weather_wmo_from_icon($item['icon'] ?? '');
        }
        if (!$columns['time']) {
            return withu_weather_error('weather_no_data', '暂无逐小时预报', 404);
        }

        $location = withu_weather_locate($lat, $lng, $cfg, (string)($options['label'] ?? ''));
        $response['data'] = [
            'latitude' => $lat,
            'longitude' => $lng,
            'hourly' => $columns,
        ];
        $response['location'] = $location;
        $response['hours'] = $hours;
        $response['updated_at'] = $updateTime;
        return $response;
    }
}

if (!function_exists('withu_weather_daily')) {
    function withu_weather_daily($lat, $lng, array $options = []): array
    {
        $cfg = $options['cfg'] ?? withu_weather_config();
        if (empty($cfg['ready'])) {
            return withu_weather_error('weather_not_configured', '天气服务尚未配置', 503);
        }
        if (!withu_weather_valid_coords($lat, $lng)) {
            return withu_weather_error('weather_invalid_params', '经纬度参数不正确', 400);
        }
        $lat = (float)$lat;
        $lng = (float)$lng;
        $days = (int)($options['days'] ?? 7);
        $dayPaths = [3 => '3d', 7 => '7d', 10 => '10d', 15 => '15d'];
        if (!isset($dayPaths[$days])) {
            $days = 7;
        }
        $lang = withu_weather_sanitize_lang($options['lang'] ?? $cfg['lang'], $cfg['lang']);
        $unit = withu_weather_sanitize_unit($options['unit'] ?? $cfg['unit'], $cfg['unit']);

        $response = withu_weather_cached_api($cfg, '/v7/weather/' . $dayPaths[$days], [
            'location' => withu_weather_location_param($lat, $lng),
            'lang' => $lang,
            'unit' => $unit,
        ], 3600);

        if (empty($response['success'])) {
            return $response;
        }
        $raw = $response['data']['daily'] ?? null;
        if (!is_array($raw) || !$raw) {
            return withu_weather_error('weather_no_data', '暂无逐日预报', 404);
        }

        $daily = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $iconDay = (string)($item['iconDay'] ?? '');
            $daily[] = [
                'fxDate' => (string)($item['fxDate'] ?? ''),
                'tempMax' => withu_weather_int($item['tempMax'] ?? null),
                'tempMin' => withu_weather_int($item['tempMin'] ?? null),
                'iconDay' => $iconDay,
                'textDay' => (string)($item['textDay'] ?? ''),
                'iconNight' => (string)($item['iconNight'] ?? ''),
                'textNight' => (string)($item['textNight'] ?? ''),
                'windDirDay' => (string)($item['windDirDay'] ?? ''),
                'windScaleDay' => (string)($item['windScaleDay'] ?? ''),
                'windDirNight' => (string)($item['windDirNight'] ?? ''),
                'windScaleNight' => (string)($item['windScaleNight'] ?? ''),
                'humidity' => withu_weather_int($item['humidity'] ?? null),
                'precip' => withu_weather_float($item['precip'] ?? null),
                'pressure' => withu_weather_int($item['pressure'] ?? null),
                'vis' => withu_weather_int($item['vis'] ?? null),
                'uvIndex' => withu_weather_int($item['uvIndex'] ?? null),
                'sunrise' => (string)($item['sunrise'] ?? ''),
                'sunset' => (string)($item['sunset'] ?? ''),
                'weather_code' => withu_weather_wmo_from_icon($iconDay),
            ];
        }
        if (!$daily) {
            return withu_weather_error('weather_no_data', '暂无逐日预报', 404);
        }

        $location = withu_weather_locate($lat, $lng, $cfg, (string)($options['label'] ?? ''));
        $response['data'] = ['daily' => $daily];
        $response['location'] = $location;
        $response['days'] = $days;
        return $response;
    }
}

if (!function_exists('withu_weather_historical')) {
    /**
     * 历史天气（和风「时光机」）。
     * 仅支持最近 10 天且不含今天；接口要求 LocationID，因此先用坐标反查城市 ID。
     */
    function withu_weather_historical($lat, $lng, array $options = []): array
    {
        $cfg = $options['cfg'] ?? withu_weather_config();
        if (empty($cfg['ready'])) {
            return withu_weather_error('weather_not_configured', '天气服务尚未配置', 503);
        }
        if (!withu_weather_valid_coords($lat, $lng)) {
            return withu_weather_error('weather_invalid_params', '经纬度参数不正确', 400);
        }

        $date = preg_replace('/\D/', '', (string)($options['date'] ?? ''));
        if (strlen($date) !== 8) {
            return withu_weather_error('weather_invalid_params', '历史日期格式不正确', 400);
        }
        $target = DateTimeImmutable::createFromFormat('!Ymd', $date);
        if (!$target) {
            return withu_weather_error('weather_invalid_params', '历史日期格式不正确', 400);
        }
        $today = new DateTimeImmutable('today');
        $diffDays = (int)$today->diff($target)->format('%r%a');
        if ($diffDays >= 0 || $diffDays < -10) {
            return withu_weather_error('weather_invalid_params', '只能查询最近 10 天的历史天气', 400);
        }

        $lat = (float)$lat;
        $lng = (float)$lng;
        $lang = withu_weather_sanitize_lang($options['lang'] ?? $cfg['lang'], $cfg['lang']);
        $unit = withu_weather_sanitize_unit($options['unit'] ?? $cfg['unit'], $cfg['unit']);

        $lookup = withu_weather_lookup(withu_weather_location_param($lat, $lng), [
            'cfg' => $cfg,
            'number' => 1,
            'lang' => $lang,
        ]);
        $locationId = (string)($lookup['data']['locations'][0]['id'] ?? '');
        if ($locationId === '') {
            return withu_weather_error('weather_no_location', '无法定位历史天气城市', 404);
        }

        $response = withu_weather_cached_api($cfg, '/v7/historical/weather', [
            'location' => $locationId,
            'date' => $date,
            'lang' => $lang,
            'unit' => $unit,
        ], 86400, 4);
        if (empty($response['success'])) {
            return $response;
        }

        $raw = $response['data']['weatherDaily'] ?? null;
        if (is_array($raw) && isset($raw[0])) {
            $raw = $raw[0];
        }
        if (!is_array($raw)) {
            return withu_weather_error('weather_no_data', '暂无历史天气数据', 404);
        }

        $hourly = $response['data']['weatherHourly'] ?? [];
        $icon = '';
        $text = '';
        $fallbackHourly = null;
        if (is_array($hourly)) {
            foreach ($hourly as $item) {
                if (!is_array($item)) {
                    continue;
                }
                if ($fallbackHourly === null) {
                    $fallbackHourly = $item;
                }
                $time = withu_weather_parse_time($item['time'] ?? null);
                if ($time === null) {
                    continue;
                }
                if ((int)$time->format('G') >= 12) {
                    $icon = trim((string)($item['icon'] ?? ''));
                    $text = trim((string)($item['text'] ?? ''));
                    break;
                }
            }
            if ($icon === '' && is_array($fallbackHourly)) {
                $icon = trim((string)($fallbackHourly['icon'] ?? ''));
                $text = trim((string)($fallbackHourly['text'] ?? ''));
            }
        }

        $response['data'] = [
            'fxDate' => $target->format('Y-m-d'),
            'tempMax' => withu_weather_int($raw['tempMax'] ?? null),
            'tempMin' => withu_weather_int($raw['tempMin'] ?? null),
            'iconDay' => $icon !== '' ? $icon : '999',
            'textDay' => $text !== '' ? $text : '—',
            'humidity' => withu_weather_int($raw['humidity'] ?? null),
            'precip' => withu_weather_float($raw['precip'] ?? null),
            'pressure' => withu_weather_int($raw['pressure'] ?? null),
        ];
        $response['date'] = $target->format('Y-m-d');
        return $response;
    }
}
