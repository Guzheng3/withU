<?php

/** WebDAV来源模型。密码只以加密文本存储，不参与日志和前端响应。 */
final class MediaSource
{
    public static function normalizePath(string $path): string
    {
        $path = rawurldecode(trim($path));
        if ($path === '') return '/';
        return '/' . trim(preg_replace('#/+#', '/', $path), '/');
    }

    public static function sourceKey(string $openlistUrl, string $webdavPath, string $mediaRoot): string
    {
        $url = rtrim(trim($openlistUrl), '/');
        return hash('sha256', $url . '|' . self::normalizePath($webdavPath) . '|' . self::normalizePath($mediaRoot));
    }

    public static function encryptPassword(string $password): ?string
    {
        if ($password === '') return null;
        $key = hash('sha256', (string)SECRET_KEY, true);
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($password, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if (!is_string($ciphertext) || $tag === '') throw new RuntimeException('无法加密 WebDAV 密码。');
        return 'v1:' . base64_encode($iv . $tag . $ciphertext);
    }

    public static function decryptPassword(?string $ciphertext): string
    {
        $ciphertext = trim((string)$ciphertext);
        if ($ciphertext === '') return '';
        if (strpos($ciphertext, 'v1:') !== 0) throw new RuntimeException('WebDAV 密码密文版本不受支持。');
        $raw = base64_decode(substr($ciphertext, 3), true);
        if (!is_string($raw) || strlen($raw) < 29) throw new RuntimeException('WebDAV 密码密文无效。');
        $key = hash('sha256', (string)SECRET_KEY, true);
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $encrypted = substr($raw, 28);
        $password = openssl_decrypt($encrypted, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if (!is_string($password)) throw new RuntimeException('WebDAV 密码解密失败。');
        return $password;
    }

    public static function validateUrl(string $url): string
    {
        $url = rtrim(trim($url), '/');
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            throw new InvalidArgumentException('WebDAV 地址必须是有效的 http 或 https 地址。');
        }
        return $url;
    }

    public static function upsert($db, array $input): array
    {
        $name = trim((string)($input['name'] ?? ''));
        if ($name === '') throw new InvalidArgumentException('WebDAV来源名称不能为空。');
        $url = self::validateUrl((string)($input['openlist_url'] ?? ''));
        $webdavPath = self::normalizePath((string)($input['webdav_path'] ?? '/'));
        $mediaRoot = self::normalizePath((string)($input['media_root'] ?? '/'));
        $sourceKey = self::sourceKey($url, $webdavPath, $mediaRoot);
        $now = date('Y-m-d H:i:s');
        $existing = $db->fetch('SELECT * FROM media_sources WHERE source_key = :source_key LIMIT 1', ['source_key' => $sourceKey]);
        $data = [
            'source_key' => $sourceKey,
            'name' => mb_substr($name, 0, 120),
            'openlist_url' => mb_substr($url, 0, 1000),
            'webdav_path' => mb_substr($webdavPath, 0, 1000),
            'media_root' => mb_substr($mediaRoot, 0, 1000),
            'username' => mb_substr(trim((string)($input['username'] ?? '')), 0, 255),
            'enabled' => !empty($input['enabled']) ? 1 : 0,
            'updated_at' => $now,
        ];
        if (array_key_exists('password', $input) && trim((string)$input['password']) !== '') {
            $data['password_ciphertext'] = self::encryptPassword((string)$input['password']);
        }
        if ($existing) {
            $db->update('media_sources', $data, 'id = :id', ['id' => (int)$existing['id']]);
            $row = $db->fetch('SELECT * FROM media_sources WHERE id = :id LIMIT 1', ['id' => (int)$existing['id']]);
            return $row ?: array_merge($existing, $data);
        }
        $data['created_at'] = $now;
        $data['scan_status'] = 'idle';
        $id = (int)$db->insert('media_sources', $data);
        return $db->fetch('SELECT * FROM media_sources WHERE id = :id LIMIT 1', ['id' => $id]) ?: array_merge($data, ['id' => $id]);
    }

    public static function runtimeConfig(array $source): array
    {
        return [
            'id' => (int)($source['id'] ?? 0),
            'name' => (string)($source['name'] ?? ''),
            'openlist_url' => (string)($source['openlist_url'] ?? ''),
            'webdav_path' => self::normalizePath((string)($source['webdav_path'] ?? '/')),
            'media_root' => self::normalizePath((string)($source['media_root'] ?? '/')),
            'username' => (string)($source['username'] ?? ''),
            'password' => self::decryptPassword($source['password_ciphertext'] ?? null),
        ];
    }
}
