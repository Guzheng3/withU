<?php

/**
 * WebDAV 物理资源扫描器。
 * 只保存来源路径和文件元数据，不保存 OpenList 临时直链。
 */
final class MediaScanner
{
    private $db;
    private array $source;
    private array $config;
    private int $taskId;
    private int $scanned = 0;
    private int $created = 0;
    private int $updated = 0;
    private int $skipped = 0;
    private int $failed = 0;
    private int $metadataMatched = 0;
    private int $metadataFailed = 0;
    private int $metadataPending = 0;
    private array $visited = [];
    private string $basePath;

    public function __construct($db, array $source, int $taskId)
    {
        $this->db = $db;
        $this->source = $source;
        $this->config = MediaSource::runtimeConfig($source);
        $this->taskId = $taskId;
        $openlistPath = rawurldecode((string)(parse_url($this->config['openlist_url'], PHP_URL_PATH) ?: ''));
        $this->basePath = self::normalizePath($openlistPath . $this->config['webdav_path']);
    }

    public function run(): array
    {
        $now = date('Y-m-d H:i:s');
        $this->setTask(['status' => 'running', 'started_at' => $now, 'message' => '正在读取 WebDAV 目录。']);
        $this->db->update('media_sources', ['scan_status' => 'running', 'last_error' => null, 'updated_at' => $now], 'id = :id', ['id' => (int)$this->source['id']]);
        try {
            $root = self::normalizePath($this->config['media_root']);
            $this->walk($root);
            $this->matchMetadata();
            $this->setTask([
                'status' => 'succeeded', 'progress' => 100, 'message' => '扫描和元数据匹配完成。',
                'scanned_count' => $this->scanned, 'created_count' => $this->created, 'updated_count' => $this->updated,
                'skipped_count' => $this->skipped, 'failed_count' => $this->failed + $this->metadataFailed,
                'metadata_matched_count' => $this->metadataMatched, 'metadata_failed_count' => $this->metadataFailed,
                'metadata_pending_count' => $this->metadataPending, 'finished_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->db->update('media_sources', ['scan_status' => 'idle', 'last_scan_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int)$this->source['id']]);
            return ['scanned' => $this->scanned, 'created' => $this->created, 'updated' => $this->updated, 'failed' => $this->failed, 'metadata_matched' => $this->metadataMatched, 'metadata_failed' => $this->metadataFailed, 'metadata_pending' => $this->metadataPending];
        } catch (Throwable $e) {
            $message = mb_substr($e->getMessage(), 0, 1000);
            $this->setTask(['status' => 'failed', 'error_message' => $message, 'message' => '扫描失败。', 'finished_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
            $this->db->update('media_sources', ['scan_status' => 'error', 'last_error' => $message, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int)$this->source['id']]);
            throw $e;
        }
    }

    private function walk(string $directory): void
    {
        $directory = self::normalizePath($directory);
        if (isset($this->visited[$directory])) return;
        $this->visited[$directory] = true;
        $entries = $this->propfind($directory);
        $directoryFingerprint = [];
        foreach ($entries as $entry) {
            $path = self::normalizePath((string)$entry['path']);
            if ($path === $directory || $path === '/') continue;
            $directoryFingerprint[] = [$path, !empty($entry['collection']) ? 'd' : 'f', $entry['etag'] ?? '', $entry['size'] ?? null, $entry['last_modified'] ?? ''];
            if (!empty($entry['collection'])) {
                $this->walk($path);
                continue;
            }
            $name = basename($path);
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($this->isVideoExtension($extension)) {
                $this->upsertVideo($path, $name, $extension, $entry);
            } elseif ($this->isSubtitleExtension($extension)) {
                $this->upsertSubtitle($path, $name, $extension, $entry);
            } else {
                $this->skipped++;
            }
        }
        $this->saveDirectorySnapshot($directory, $directoryFingerprint);
    }

    private function propfind(string $relativePath): array
    {
        $url = $this->urlFor($relativePath);
        $ch = curl_init($url);
        if (!is_resource($ch) && $ch === false) throw new RuntimeException('无法创建 WebDAV 请求。');
        $headers = ['Depth: 1', 'Content-Type: application/xml', 'User-Agent: withU/1.0'];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PROPFIND', CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => 60, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        if ($this->config['username'] !== '') curl_setopt($ch, CURLOPT_USERPWD, $this->config['username'] . ':' . $this->config['password']);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if (!is_string($body) || $status < 200 || $status >= 300) throw new RuntimeException($error !== '' ? $error : 'WebDAV 目录请求失败（HTTP ' . $status . '）。');
        $xml = @simplexml_load_string($body);
        if ($xml === false) throw new RuntimeException('WebDAV 返回的目录 XML 无法解析。');
        $xml->registerXPathNamespace('d', 'DAV:');
        $entries = [];
        foreach ($xml->xpath('//d:response') ?: [] as $response) {
            $response->registerXPathNamespace('d', 'DAV:');
            $hrefNode = $response->xpath('./d:href');
            $href = $hrefNode && isset($hrefNode[0]) ? (string)$hrefNode[0] : '';
            $path = $this->relativePathFromHref($href, $relativePath);
            if ($path === '') continue;
            $isCollection = !empty($response->xpath('.//d:resourcetype/d:collection'));
            $sizeNode = $response->xpath('.//d:getcontentlength');
            $etagNode = $response->xpath('.//d:getetag');
            $modifiedNode = $response->xpath('.//d:getlastmodified');
            $entries[] = [
                'path' => $path, 'collection' => $isCollection,
                'size' => $sizeNode && isset($sizeNode[0]) && trim((string)$sizeNode[0]) !== '' ? (int)$sizeNode[0] : null,
                'etag' => $etagNode && isset($etagNode[0]) ? trim((string)$etagNode[0], '"') : null,
                'last_modified' => $modifiedNode && isset($modifiedNode[0]) ? $this->dateValue((string)$modifiedNode[0]) : null,
            ];
        }
        return $entries;
    }

    private function upsertVideo(string $path, string $name, string $extension, array $entry): void
    {
        $this->scanned++;
        $now = date('Y-m-d H:i:s');
        $fingerprint = hash('sha256', (int)$this->source['id'] . '|' . $path . '|' . (string)($entry['size'] ?? '') . '|' . (string)($entry['etag'] ?? '') . '|' . (string)($entry['last_modified'] ?? ''));
        $identified = withu_media_identify([
            'source_key' => $path,
            'source_path' => $path,
            'file_name' => $name,
        ]);
        if (empty($identified['resolution']) && function_exists('withu_probe_source_stream_info')) {
            $probe = withu_probe_source_stream_info($this->urlFor($path), $this->webdavHeaders(), 18);
            if ($probe) {
                foreach (['resolution', 'video_codec', 'width', 'height'] as $field) {
                    if (!empty($probe[$field])) $identified[$field] = $probe[$field];
                }
            }
        }
        $existing = $this->db->fetch('SELECT id,fingerprint FROM media_resources WHERE source_id = :source_id AND source_path = :source_path LIMIT 1', ['source_id' => (int)$this->source['id'], 'source_path' => $path]);
        $data = [
            'source_id' => (int)$this->source['id'], 'source_path' => mb_substr($path, 0, 1000), 'file_name' => mb_substr($name, 0, 255),
            'folder_path' => mb_substr(dirname($path) === '\\' ? '/' : dirname($path), 0, 1000), 'extension' => $extension,
            'file_size' => $entry['size'], 'file_etag' => trim((string)($entry['etag'] ?? '')) ?: null,
            'last_modified' => $entry['last_modified'], 'fingerprint' => $fingerprint, 'fingerprint_method' => 'metadata',
            'media_type_id' => (int)($identified['media_type_id'] ?? 1), 'title' => (string)($identified['series_name'] ?? ''),
            'season_number' => $identified['season_number'] ?? null, 'episode_number' => $identified['episode_number'] ?? null,
            'resolution' => $identified['resolution'] ?? null, 'video_codec' => $identified['video_codec'] ?? null,
            'audio_codec' => $identified['audio_codec'] ?? null, 'metadata_json' => $identified['metadata_json'] ?? null,
            'recognition_status' => $identified['recognition_status'] ?? 'pending',
            'last_seen_at' => $now, 'missing_since' => null, 'updated_at' => $now,
        ];
        if ($existing) {
            $this->db->update('media_resources', $data, 'id = :id', ['id' => (int)$existing['id']]);
            $this->updated++;
            $resourceId = (int)$existing['id'];
        } else {
            $data['first_seen_at'] = $now; $data['created_at'] = $now;
            $resourceId = (int)$this->db->insert('media_resources', $data);
            $this->created++;
        }
        withu_media_upsert_file([
            'source_key' => $path, 'source_url' => '', 'file_name' => $name, 'file_size' => $entry['size'] ?? null,
            'file_etag' => $entry['etag'] ?? '', 'source_id' => (int)$this->source['id'], 'resource_id' => $resourceId,
            'source_path' => $path, 'folder_path' => dirname($path) === '\\' ? '/' : dirname($path), 'file_extension' => $extension,
            'last_modified' => $entry['last_modified'] ?? null, 'fingerprint' => $fingerprint,
            'resolution' => $identified['resolution'] ?? null, 'video_codec' => $identified['video_codec'] ?? null,
            'width' => $identified['width'] ?? null, 'height' => $identified['height'] ?? null,
        ], false, false);
        if ($this->scanned % 25 === 0) $this->setTask(['scanned_count' => $this->scanned, 'created_count' => $this->created, 'updated_count' => $this->updated, 'skipped_count' => $this->skipped, 'failed_count' => $this->failed, 'cursor_path' => $path, 'message' => '已扫描：' . $path, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    private function matchMetadata(): void
    {
        $this->setTask(['progress' => 80, 'message' => '扫描完成，正在按影视分组匹配元数据。', 'updated_at' => date('Y-m-d H:i:s')]);
        $groups = $this->db->fetchAll(
            "SELECT series_key, MIN(id) AS media_id, COUNT(*) AS episode_count
             FROM media_library
             WHERE source_id = :source_id AND series_key IS NOT NULL AND series_key <> '' AND recognition_status <> 'disabled'
             GROUP BY series_key ORDER BY MIN(id) ASC",
            ['source_id' => (int)$this->source['id']]
        );
        foreach ($groups as $index => $group) {
            try {
                $result = withu_recognize_series($this->db, (string)$group['series_key'], [], false);
                if (!empty($result['success'])) {
                    if (!empty($result['skipped'])) $this->metadataPending++;
                    else $this->metadataMatched++;
                } else {
                    $this->metadataPending++;
                }
            } catch (Throwable $e) {
                $this->metadataFailed++;
                $this->db->update('media_library', ['recognition_status' => 'recognized', 'recognition_source' => 'local', 'updated_at' => date('Y-m-d H:i:s')], 'series_key = :series_key AND source_id = :source_id', ['series_key' => (string)$group['series_key'], 'source_id' => (int)$this->source['id']]);
            }
            $progress = count($groups) > 0 ? min(99, 80 + (($index + 1) / count($groups)) * 19) : 99;
            $this->setTask(['progress' => $progress, 'metadata_matched_count' => $this->metadataMatched, 'metadata_failed_count' => $this->metadataFailed, 'metadata_pending_count' => $this->metadataPending, 'message' => '元数据匹配：' . ($index + 1) . '/' . count($groups), 'updated_at' => date('Y-m-d H:i:s')]);
        }
    }

    private function upsertSubtitle(string $path, string $name, string $extension, array $entry): void
    {
        $stem = preg_replace('/\.[^.]+$/', '', $name);
        $folder = dirname($path) === '\\' ? '/' : dirname($path);
        $video = $this->db->fetch("SELECT id FROM media_resources WHERE source_id = :source_id AND folder_path = :folder_path AND file_name LIKE :file_name ORDER BY id ASC LIMIT 1", ['source_id' => (int)$this->source['id'], 'folder_path' => $folder, 'file_name' => $stem . '.%']);
        $now = date('Y-m-d H:i:s');
        $data = ['resource_id' => $video ? (int)$video['id'] : null, 'source_id' => (int)$this->source['id'], 'subtitle_path' => mb_substr($path, 0, 1000), 'file_name' => mb_substr($name, 0, 255), 'language' => $this->subtitleLanguage($name), 'codec' => $extension, 'file_size' => $entry['size'], 'file_etag' => trim((string)($entry['etag'] ?? '')) ?: null, 'status' => 'active', 'updated_at' => $now];
        $old = $this->db->fetch('SELECT id FROM media_resource_subtitles WHERE source_id = :source_id AND subtitle_path = :subtitle_path LIMIT 1', ['source_id' => (int)$this->source['id'], 'subtitle_path' => $path]);
        if ($old) $this->db->update('media_resource_subtitles', $data, 'id = :id', ['id' => (int)$old['id']]);
        else { $data['created_at'] = $now; $this->db->insert('media_resource_subtitles', $data); }
    }

    private function saveDirectorySnapshot(string $path, array $entries): void
    {
        $now = date('Y-m-d H:i:s');
        $data = ['source_id' => (int)$this->source['id'], 'directory_path' => $path, 'fingerprint' => hash('sha256', json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 'entry_count' => count($entries), 'scan_version' => '20260723-05', 'last_scanned_at' => $now, 'status' => 'ok', 'error_message' => null, 'updated_at' => $now];
        $old = $this->db->fetch('SELECT id FROM media_source_directories WHERE source_id = :source_id AND directory_path = :directory_path LIMIT 1', ['source_id' => (int)$this->source['id'], 'directory_path' => $path]);
        if ($old) $this->db->update('media_source_directories', $data, 'id = :id', ['id' => (int)$old['id']]);
        else { $data['created_at'] = $now; $this->db->insert('media_source_directories', $data); }
    }

    private function linkLegacyMedia(int $resourceId, string $path): void
    {
        $resource = $this->db->fetch('SELECT source_id,source_path,folder_path,file_name,extension,last_modified,fingerprint FROM media_resources WHERE id = :id LIMIT 1', ['id' => $resourceId]);
        if (!$resource) return;
        $row = $this->db->fetch('SELECT id FROM media_library WHERE source_path = :path OR source_key = :path LIMIT 1', ['path' => $path]);
        if (!$row) return;
        $now = date('Y-m-d H:i:s');
        $this->db->update('media_resources', ['media_id' => (int)$row['id'], 'updated_at' => $now], 'id = :id', ['id' => $resourceId]);
        $this->db->update('media_library', [
            'source_id' => (int)$resource['source_id'], 'resource_id' => $resourceId,
            'source_path' => $resource['source_path'], 'folder_path' => $resource['folder_path'],
            'file_extension' => $resource['extension'], 'last_modified' => $resource['last_modified'],
            'fingerprint' => $resource['fingerprint'], 'updated_at' => $now,
        ], 'id = :id', ['id' => (int)$row['id']]);
        $this->db->query('UPDATE media_catalog_sources SET source_id = :source_id, resource_id = :resource_id, source_path = :source_path, folder_path = :folder_path, fingerprint = :fingerprint, updated_at = :updated_at WHERE media_id = :media_id AND source_key = :source_key', [
            'source_id' => (int)$resource['source_id'], 'resource_id' => $resourceId, 'source_path' => $resource['source_path'],
            'folder_path' => $resource['folder_path'], 'fingerprint' => $resource['fingerprint'], 'updated_at' => $now,
            'media_id' => (int)$row['id'], 'source_key' => $path,
        ]);
    }

    private function setTask(array $data): void
    {
        $allowed = ['status','progress','scanned_count','created_count','updated_count','skipped_count','failed_count','metadata_matched_count','metadata_failed_count','metadata_pending_count','cursor_path','message','error_message','pid','started_at','finished_at','updated_at'];
        $data = array_intersect_key($data, array_flip($allowed));
        if ($data) $this->db->update('media_tasks', $data, 'id = :id', ['id' => $this->taskId]);
    }

    private function urlFor(string $relativePath): string
    {
        $remotePath = self::normalizePath($this->config['webdav_path'] . '/' . trim($relativePath, '/'));
        $parts = array_map('rawurlencode', array_filter(explode('/', trim($remotePath, '/')), 'strlen'));
        return rtrim($this->config['openlist_url'], '/') . '/' . implode('/', $parts);
    }

    private function webdavHeaders(): array
    {
        if (trim((string)$this->config['username']) === '') return [];
        return ['Authorization: Basic ' . base64_encode($this->config['username'] . ':' . $this->config['password'])];
    }

    private function relativePathFromHref(string $href, string $current): string
    {
        $path = rawurldecode((string)(parse_url($href, PHP_URL_PATH) ?: $href));
        $path = self::normalizePath($path);
        $base = $this->basePath;
        if ($path === $base) return self::normalizePath($current);
        if (strpos($path, rtrim($base, '/') . '/') === 0) return self::normalizePath(substr($path, strlen(rtrim($base, '/'))));
        $current = self::normalizePath($current);
        if ($path === $current || strpos($path, rtrim($current, '/') . '/') === 0) return $path;
        return self::normalizePath($current . '/' . basename($path));
    }

    private function dateValue(string $value): ?string
    {
        $timestamp = strtotime(trim($value));
        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }

    private function subtitleLanguage(string $name): ?string
    {
        if (preg_match('/(?:^|[. _-])(zh(?:-CN)?|chs|cht|中|简|繁)(?:[. _-]|$)/iu', $name, $match)) return strtolower($match[1]);
        if (preg_match('/(?:^|[. _-])(en|eng)(?:[. _-]|$)/iu', $name)) return 'en';
        return null;
    }

    private function isVideoExtension(string $extension): bool { return in_array($extension, ['mp4','mkv','webm','mov','avi','m4v','ts'], true); }
    private function isSubtitleExtension(string $extension): bool { return in_array($extension, ['srt','ass','ssa','vtt','sup'], true); }

    private static function normalizePath(string $path): string
    {
        $path = rawurldecode(trim($path));
        return '/' . trim(preg_replace('#/+#', '/', $path), '/');
    }
}
