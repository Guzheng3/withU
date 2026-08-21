<?php
/** OpenList 媒体扫描与 WebDAV 直链缓存。视频内容始终由 OpenList 直出。 */
class OpenListClient
{
    private $db;
    private $baseUrl;
    private $username;
    private $password;
    private $rootPath;
    private $apiBaseUrl;
    private $webdavRoot = '/';
    private $webdavFallbackRoot = null;
    private $legacyStoragePrefix = '';
    private $scanErrors = 0;

    public function __construct($db)
    {
        $this->db = $db;
        $configuredUrl = rtrim((string)get_setting('openlist_webdav_url', ''), '/');
        $this->baseUrl = $configuredUrl;
        $this->username = (string)get_setting('openlist_webdav_username', '');
        $this->password = (string)get_setting('openlist_webdav_password', '');
        $parsed = parse_url($configuredUrl);
        $origin = '';
        if (is_array($parsed) && !empty($parsed['scheme']) && !empty($parsed['host'])) {
            $origin = $parsed['scheme'] . '://' . $parsed['host'] . (!empty($parsed['port']) ? ':' . $parsed['port'] : '');
            $configuredPath = rawurldecode(rtrim((string)($parsed['path'] ?? ''), '/'));
            $configuredPath = preg_replace('#^/(?:d|dav|webdav)(?=/|$)#i', '', $configuredPath);
            $segments = array_values(array_filter(explode('/', trim((string)$configuredPath, '/')), 'strlen'));
            $this->baseUrl = $origin . '/dav';
            $this->webdavRoot = $segments ? '/' . implode('/', $segments) : '/';
            $this->legacyStoragePrefix = $segments ? '/' . $segments[0] : '';
            // OpenList 网页路径常带存储挂载名，例如 /移动云盘/电视；
            // WebDAV 根目录实际可能直接暴露为 /电视，失败时自动尝试。
            if (count($segments) > 1) {
                $this->webdavFallbackRoot = '/' . implode('/', array_slice($segments, 1));
            }
            $parsed = parse_url($this->baseUrl);
        }
        $this->apiBaseUrl = rtrim($origin !== '' ? $origin : $this->baseUrl, '/');
        $configuredRoot = '/' . trim((string)get_setting('openlist_root_path', '/'), '/');
        $urlPath = is_array($parsed) ? rawurldecode(rtrim((string)($parsed['path'] ?? ''), '/')) : '';
        $this->rootPath = $configuredRoot;
        if ($this->rootPath === '//') $this->rootPath = '/';
        if ($this->webdavRoot === '/' && $configuredRoot !== '/') $this->webdavRoot = $configuredRoot;
    }

    public function configured(): bool { return $this->baseUrl !== ''; }

    public function scanErrorCount(): int { return $this->scanErrors; }

    private function apiRequest(string $endpoint, array $payload, string $token = ''): array
    {
        $url = $this->apiBaseUrl . '/api/' . ltrim($endpoint, '/');
        $ch = curl_init($url);
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'User-Agent: withU/1.0'];
        if ($token !== '') $headers[] = 'Authorization: ' . $token;
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        if (!is_string($body) || $status < 200 || $status >= 300) {
            throw new RuntimeException($error !== '' ? $error : 'OpenList API 请求失败（HTTP ' . $status . '）');
        }
        $json = json_decode($body, true);
        if (!is_array($json) || (isset($json['code']) && (int)$json['code'] !== 200)) {
            throw new RuntimeException((string)($json['message'] ?? 'OpenList API 返回异常'));
        }
        return is_array($json['data'] ?? null) ? $json['data'] : [];
    }

    private function login(): string
    {
        if ($this->username === '' || $this->password === '') {
            throw new RuntimeException('请先配置 OpenList 账号和密码');
        }
        $data = $this->apiRequest('auth/login', ['username' => $this->username, 'password' => $this->password]);
        $token = trim((string)($data['token'] ?? ''));
        if ($token === '') throw new RuntimeException('OpenList 登录未返回 token');
        return $token;
    }

    private function listDirectory(string $token, string $path): array
    {
        $data = $this->apiRequest('fs/list', ['path' => $path, 'password' => '', 'page' => 1, 'per_page' => 0, 'refresh' => false], $token);
        $items = $data['content'] ?? ($data['files'] ?? []);
        return is_array($items) ? $items : [];
    }

    private function resolvePath(string $token, string $path): string
    {
        $data = $this->apiRequest('fs/get', ['path' => $path, 'password' => ''], $token);
        return trim((string)($data['raw_url'] ?? $data['url'] ?? $data['download_url'] ?? ''));
    }

    private function apiPath(string $parent, string $name): string
    {
        $parent = '/' . trim($parent, '/');
        return ($parent === '/' ? '' : $parent) . '/' . trim($name, '/');
    }

    private function publicDavUrl(string $path): string
    {
        $parts = array_map('rawurlencode', array_filter(explode('/', trim($path, '/')), 'strlen'));
        return rtrim($this->apiBaseUrl, '/') . '/d/' . implode('/', $parts);
    }

    /**
     * 将 API 返回的存储路径映射到当前配置的 WebDAV 目录。
     * 配置通常是 /dav/移动云盘/电视剧，而 API 返回的路径可能包含
     * /移动云盘/电视剧 前缀；这个前缀不能再次拼到 WebDAV 地址上。
     */
    private function webdavRelativePath(string $path): string
    {
        $path = '/' . trim(rawurldecode($path), '/');
        $basePath = rawurldecode((string)(parse_url($this->baseUrl, PHP_URL_PATH) ?: ''));
        $basePath = '/' . trim($basePath, '/');
        $storagePath = preg_replace('#^/(?:dav|webdav)(?=/|$)#i', '', $basePath);
        $storagePath = '/' . trim((string)$storagePath, '/');
        if ($this->legacyStoragePrefix !== '' && ($path === $this->legacyStoragePrefix || strpos($path, $this->legacyStoragePrefix . '/') === 0)) {
            $path = substr($path, strlen($this->legacyStoragePrefix));
        }
        if ($storagePath !== '/' && ($path === $storagePath || strpos($path, $storagePath . '/') === 0)) {
            $path = substr($path, strlen($storagePath));
        }
        return '/' . trim($path, '/');
    }

    private function webdavUrl(string $path): string
    {
        return $this->joinUrl($this->webdavRelativePath($path));
    }

    private function scanDirectory(string $token, string $path, array &$found, array &$visited, ?string $folderCreatedAt = null): void
    {
        if (isset($visited[$path])) return;
        $visited[$path] = true;
        foreach ($this->listDirectory($token, $path) as $item) {
            $name = trim((string)($item['name'] ?? ''));
            if ($name === '') continue;
            $itemPath = $this->apiPath($path, $name);
            $isDir = !empty($item['is_dir']) || !empty($item['isDir']) || ($item['type'] ?? '') === 'folder';
            if ($isDir) {
                $this->scanDirectory($token, $itemPath, $found, $visited, $this->sourceDate($item['created_at'] ?? $item['createdAt'] ?? $item['time'] ?? $item['updated_at'] ?? $item['updatedAt'] ?? null));
                continue;
            }
            if (!preg_match('/\.(mp4|mkv|webm|mov|avi|m4v|ts)$/i', $name)) continue;
            $found[] = [
                'source_key' => $itemPath,
                // WebDAV 地址用于重新请求签名直链；签名直链只在首次播放时
                // 获取并保存到 media_library.direct_url。
                'source_url' => $this->webdavUrl($itemPath),
                'direct_url' => '',
                'file_name' => $name,
                'file_size' => isset($item['size']) ? (int)$item['size'] : null,
                'file_etag' => (string)($item['hash_info']['hash'] ?? $item['etag'] ?? $item['hash'] ?? ''),
                'last_modified' => $this->sourceDate($item['last_modified'] ?? $item['lastmodified'] ?? $item['updated_at'] ?? $item['updatedAt'] ?? $item['time'] ?? null),
                // “最新添加” follows the containing folder timestamp. File
                // timestamps are only a fallback for flat roots or APIs that
                // do not expose directory metadata.
                'folder_created_at' => $folderCreatedAt ?: $this->sourceDate($item['created_at'] ?? $item['createdAt'] ?? $item['time'] ?? $item['updated_at'] ?? $item['updatedAt'] ?? null),
            ];
        }
    }

    private function scanDirectoryEach(string $token, string $path, callable $onFile, array &$visited, ?string $folderCreatedAt = null): bool
    {
        if (isset($visited[$path])) return true;
        $visited[$path] = true;
        foreach ($this->listDirectory($token, $path) as $item) {
            $name = trim((string)($item['name'] ?? ''));
            if ($name === '') continue;
            $itemPath = $this->apiPath($path, $name);
            $isDir = !empty($item['is_dir']) || !empty($item['isDir']) || ($item['type'] ?? '') === 'folder';
            if ($isDir) {
                if (!$this->scanDirectoryEach($token, $itemPath, $onFile, $visited, $this->sourceDate($item['created_at'] ?? $item['createdAt'] ?? $item['time'] ?? $item['updated_at'] ?? $item['updatedAt'] ?? null))) return false;
                continue;
            }
            if (!preg_match('/\.(mp4|mkv|webm|mov|avi|m4v|ts)$/i', $name)) continue;
            if ($onFile([
                'source_key' => $itemPath,
                'source_url' => $this->webdavUrl($itemPath),
                'direct_url' => '',
                'file_name' => $name,
                'file_size' => isset($item['size']) ? (int)$item['size'] : null,
                'file_etag' => (string)($item['hash_info']['hash'] ?? $item['etag'] ?? $item['hash'] ?? ''),
                'last_modified' => $this->sourceDate($item['last_modified'] ?? $item['lastmodified'] ?? $item['updated_at'] ?? $item['updatedAt'] ?? $item['time'] ?? null),
                'folder_created_at' => $folderCreatedAt ?: $this->sourceDate($item['created_at'] ?? $item['createdAt'] ?? $item['time'] ?? $item['updated_at'] ?? $item['updatedAt'] ?? null),
            ]) === false) return false;
        }
        return true;
    }

    private function scanWebdavDirectory(string $path, array &$found, array &$visited, ?string $folderCreatedAt = null): void
    {
        $path = '/' . trim($path, '/');
        if (isset($visited[$path])) return;
        $visited[$path] = true;
        foreach ($this->propfind($path) as $item) {
            $itemPath = '/' . trim((string)($item['path'] ?? ''), '/');
            if ($itemPath === '/' || $itemPath === $path) continue;
            if (!empty($item['collection'])) {
                $this->scanWebdavDirectory($itemPath, $found, $visited, $this->sourceDate($item['creationdate'] ?? $item['created_at'] ?? $item['lastmodified'] ?? $item['updated_at'] ?? null));
                continue;
            }
            $name = basename($itemPath);
            if (!preg_match('/\.(mp4|mkv|webm|mov|avi|m4v|ts)$/i', $name)) continue;
            $found[] = [
                'source_key' => $itemPath,
                'source_url' => $this->webdavUrl($itemPath),
                'direct_url' => '',
                'file_name' => $name,
                'file_size' => $item['size'] ?? null,
                'file_etag' => (string)($item['etag'] ?? ''),
                'last_modified' => $this->sourceDate($item['lastmodified'] ?? $item['last_modified'] ?? $item['updated_at'] ?? null),
                'folder_created_at' => $folderCreatedAt ?: $this->sourceDate($item['creationdate'] ?? $item['created_at'] ?? $item['lastmodified'] ?? $item['updated_at'] ?? null),
            ];
        }
    }

    private function scanWebdavDirectoryEach(string $path, callable $onFile, array &$visited, ?string $folderCreatedAt = null): bool
    {
        $path = '/' . trim($path, '/');
        if (isset($visited[$path])) return true;
        $visited[$path] = true;
        foreach ($this->propfind($path) as $item) {
            $itemPath = '/' . trim((string)($item['path'] ?? ''), '/');
            if ($itemPath === '/' || $itemPath === $path) continue;
            if (!empty($item['collection'])) {
                if (!$this->scanWebdavDirectoryEach($itemPath, $onFile, $visited, $this->sourceDate($item['creationdate'] ?? $item['created_at'] ?? $item['lastmodified'] ?? $item['updated_at'] ?? null))) return false;
                continue;
            }
            $name = basename($itemPath);
            if (!preg_match('/\.(mp4|mkv|webm|mov|avi|m4v|ts)$/i', $name)) continue;
            if ($onFile([
                'source_key' => $itemPath,
                'source_url' => $this->webdavUrl($itemPath),
                'direct_url' => '',
                'file_name' => $name,
                'file_size' => $item['size'] ?? null,
                'file_etag' => (string)($item['etag'] ?? ''),
                'last_modified' => $this->sourceDate($item['lastmodified'] ?? $item['last_modified'] ?? $item['updated_at'] ?? null),
                'folder_created_at' => $folderCreatedAt ?: $this->sourceDate($item['creationdate'] ?? $item['created_at'] ?? $item['lastmodified'] ?? $item['updated_at'] ?? null),
            ]) === false) return false;
        }
        return true;
    }

    private function sourceDate($value): ?string
    {
        if ($value === null || $value === '') return null;
        if (is_numeric($value)) {
            $timestamp = (int)$value;
            if ($timestamp > 20000000000) $timestamp = (int)floor($timestamp / 1000);
            return $timestamp > 0 ? date('Y-m-d H:i:s', $timestamp) : null;
        }
        $timestamp = strtotime((string)$value);
        return $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    private function authOptions($curl): void
    {
        if ($this->username !== '') {
            curl_setopt($curl, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        }
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Depth: 1', 'User-Agent: withU/1.0']);
    }

    private function joinUrl(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        $parts = array_map('rawurlencode', array_filter(explode('/', trim($path, '/')), 'strlen'));
        return $this->baseUrl . '/' . implode('/', $parts);
    }

    private function normalizePath(string $href): string
    {
        $path = parse_url($href, PHP_URL_PATH) ?: $href;
        $basePath = parse_url($this->baseUrl, PHP_URL_PATH) ?: '';
        $path = rawurldecode($path);
        $basePath = '/' . trim(rawurldecode($basePath), '/');
        if ($basePath !== '/' && strpos($path, $basePath) === 0) {
            $path = substr($path, strlen($basePath));
        }
        return '/' . trim($path, '/');
    }

    private function propfind(string $path): array
    {
        $url = $this->joinUrl($path);
        $ch = curl_init($url);
        $this->authOptions($ch);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PROPFIND');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Depth: 1', 'Content-Type: application/xml', 'User-Agent: withU/1.0']);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (!is_string($body) || $status < 200 || $status >= 300) {
            $this->scanErrors++;
            return [];
        }

        $xml = @simplexml_load_string($body);
        if ($xml === false) {
            $this->scanErrors++;
            return [];
        }
        $xml->registerXPathNamespace('d', 'DAV:');
        $items = [];
        foreach ($xml->xpath('//d:response') ?: [] as $response) {
            // SimpleXML 子节点不会继承根节点的 XPath 前缀注册，需要重新绑定 DAV 命名空间。
            $response->registerXPathNamespace('d', 'DAV:');
            $hrefNode = $response->xpath('./d:href');
            $href = $hrefNode && isset($hrefNode[0]) ? rawurldecode((string)$hrefNode[0]) : '';
            $isCollection = !empty($response->xpath('.//d:resourcetype/d:collection'));
            $sizeNode = $response->xpath('.//d:getcontentlength');
            $etagNode = $response->xpath('.//d:getetag');
            $items[] = [
                'href' => $href, 'path' => $this->normalizePath($href), 'collection' => $isCollection,
                'size' => $sizeNode && isset($sizeNode[0]) ? (int)$sizeNode[0] : null,
                'etag' => $etagNode && isset($etagNode[0]) ? trim((string)$etagNode[0], '"') : null,
            ];
        }
        return $items;
    }

    private function resolve302(string $sourceUrl): string
    {
        $ch = curl_init($sourceUrl);
        if ($this->username !== '') curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        $location = '';
        $received = 0;
        curl_setopt_array($ch, [
            CURLOPT_HTTPGET => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_RANGE => '0-0',
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'withU/1.0',
            // Some OpenList storage backends expose a signed HTTPS endpoint
            // whose certificate chain is not present on the PHP host.
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$location): int {
                if (stripos($header, 'Location:') === 0) $location = trim(substr($header, 9));
                return strlen($header);
            },
            CURLOPT_WRITEFUNCTION => static function ($curl, string $data) use (&$received): int {
                $received += strlen($data);
                return $received > 4096 ? 0 : strlen($data);
            },
        ]);
        curl_exec($ch);
        if ($location === '') $location = (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($location === '' && $status >= 200 && $status < 300) return $sourceUrl;
        if ($location !== '' && !preg_match('#^https?://#i', $location)) {
            $parts = parse_url($sourceUrl);
            if (is_array($parts) && !empty($parts['scheme']) && !empty($parts['host'])) {
                $origin = $parts['scheme'] . '://' . $parts['host'] . (!empty($parts['port']) ? ':' . $parts['port'] : '');
                $location = $origin . '/' . ltrim($location, '/');
            }
        }
        return $location;
    }

    public function scan(): array
    {
        if (!$this->configured()) throw new RuntimeException('请先在后台填写 OpenList 地址');
        $found = [];
        $visited = [];
        $this->scanWebdavDirectory($this->webdavRoot, $found, $visited);
        if (!$found && $this->webdavFallbackRoot !== null && $this->webdavFallbackRoot !== $this->webdavRoot) {
            $found = [];
            $visited = [];
            $this->scanWebdavDirectory($this->webdavFallbackRoot, $found, $visited);
        }
        // Some OpenList deployments expose the configured storage mount as
        // the WebDAV root itself, so /dav/<mount> returns 404 while /dav/
        // is valid. Use the root as the final compatibility fallback.
        if (!$found && $this->webdavRoot !== '/') {
            $found = [];
            $visited = [];
            $this->scanWebdavDirectory('/', $found, $visited);
        }
        return $found;
    }

    public function scanEach(callable $onFile): int
    {
        if (!$this->configured()) throw new RuntimeException('请先在后台填写 OpenList 地址');
        $this->scanErrors = 0;
        $count = 0;
        $wrapped = static function (array $file) use ($onFile, &$count) {
            $count++;
            return $onFile($file, $count);
        };
        $visited = [];
        $this->scanWebdavDirectoryEach($this->webdavRoot, $wrapped, $visited);
        if ($count === 0 && $this->webdavFallbackRoot !== null && $this->webdavFallbackRoot !== $this->webdavRoot) {
            $this->scanErrors = 0;
            $visited = [];
            $this->scanWebdavDirectoryEach($this->webdavFallbackRoot, $wrapped, $visited);
        }
        if ($count === 0 && $this->webdavRoot !== '/') {
            $this->scanErrors = 0;
            $visited = [];
            $this->scanWebdavDirectoryEach('/', $wrapped, $visited);
        }
        return $count;
    }

    public function resolve(array $media): string
    {
        $source = trim((string)($media['source_url'] ?? ''));
        $sourceHost = (string)(parse_url($source, PHP_URL_HOST) ?: '');
        $baseHost = (string)(parse_url($this->baseUrl, PHP_URL_HOST) ?: '');
        $sourcePath = rawurldecode((string)(parse_url($source, PHP_URL_PATH) ?: ''));
        $basePath = rawurldecode((string)(parse_url($this->baseUrl, PHP_URL_PATH) ?: ''));
        $isWebdavSource = $source !== '' && $sourceHost !== '' && $sourceHost === $baseHost
            && ($basePath === '' || strpos($sourcePath, rtrim($basePath, '/') . '/') === 0 || $sourcePath === rtrim($basePath, '/'));
        // 旧版本保存的是 /d/... 公共地址，改用 source_key 重新拼接到
        // 当前 WebDAV 目录，避免继续请求旧的公开链接。
        if (!$isWebdavSource) {
            $source = $this->webdavUrl((string)($media['source_key'] ?? ''));
        }
        if ($source === '') return '';
        return $this->resolve302($source);
    }

    /**
     * 使用 GET + Range 探测签名直链，避免只发 HEAD 导致 OpenList/CDN
     * 错误返回或不触发签名校验。只读取最多 4KB，不会下载整部视频。
     */
    public function isDirectUrlValid(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || !preg_match('#^https?://#i', $url)) return false;
        $ch = curl_init($url);
        $received = 0;
        curl_setopt_array($ch, [
            CURLOPT_HTTPGET => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_RANGE => '0-0',
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_USERAGENT => 'withU/1.0',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_WRITEFUNCTION => static function ($curl, string $data) use (&$received): int {
                $received += strlen($data);
                return $received > 4096 ? 0 : strlen($data);
            },
        ]);
        curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        return ($status >= 200 && $status < 300);
    }
}
