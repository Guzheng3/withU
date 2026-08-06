<?php

/**
 * 影视重复候选和播放链接检查。
 *
 * 分析只生成候选，不自动删除数据；合并必须由后台管理员确认。
 */
class MediaDedupe
{
    private MediaDatabase $db;

    public function __construct(?MediaDatabase $db = null)
    {
        $this->db = $db ?: withu_media_db();
    }

    public function analyze(int $limit = 120): array
    {
        // This is an administrator-triggered local analysis. Fetch every
        // series group, then compare only entries sharing an ID, a normalized
        // title, or a non-generic title n-gram. The prior nested loop examined
        // only the first 500 groups and scaled quadratically, so most of a
        // large library was never considered.
        $groups = $this->db->fetchAll(
            "SELECT series_key, MAX(series_name) AS series_name, MAX(media_type_id) AS media_type_id,
                    MAX(douban_id) AS douban_id, MAX(tmdb_id) AS tmdb_id, COUNT(*) AS episode_count
             FROM media_library
             WHERE series_key IS NOT NULL AND series_key <> '' AND recognition_status <> 'disabled'
             GROUP BY series_key"
        );
        $created = 0;
        $now = withu_now();
        $pairs = $this->candidatePairs($groups);
        foreach ($pairs as [$leftIndex, $rightIndex]) {
            $a = $groups[$leftIndex];
            $b = $groups[$rightIndex];
            $score = $this->score($a, $b);
            if ($score < 72) continue;
            $target = ((int)$a['episode_count'] >= (int)$b['episode_count']) ? $a : $b;
            $duplicate = $target['series_key'] === $a['series_key'] ? $b : $a;
            // AI is deliberately not called during batch candidate discovery.
            // It is paid/network work and each suggested pair can be reviewed
            // explicitly from the administrator page before any merge.
            $reason = $this->reason($a, $b, null);
            $this->db->query(
                "INSERT INTO media_merge_candidates
                 (target_series_key,duplicate_series_key,target_name,duplicate_name,score,reason,ai_result,ai_explanation,status,created_at,updated_at)
                 VALUES (:target_key,:duplicate_key,:target_name,:duplicate_name,:score,:reason,NULL,NULL,'pending',:created_at,:updated_at)
                 ON DUPLICATE KEY UPDATE target_name=VALUES(target_name),duplicate_name=VALUES(duplicate_name),score=VALUES(score),reason=VALUES(reason),updated_at=VALUES(updated_at)",
                [
                    'target_key' => (string)$target['series_key'], 'duplicate_key' => (string)$duplicate['series_key'],
                    'target_name' => mb_substr((string)$target['series_name'], 0, 255), 'duplicate_name' => mb_substr((string)$duplicate['series_name'], 0, 255),
                    'score' => number_format($score, 2, '.', ''), 'reason' => $reason,
                    'created_at' => $now, 'updated_at' => $now,
                ]
            );
            $created++;
            if ($created >= $limit) break;
        }
        return ['created' => $created, 'groups' => count($groups), 'pairs' => count($pairs)];
    }

    /**
     * Build a bounded set of plausible pairs in approximately linear time.
     *
     * @param array<int,array<string,mixed>> $groups
     * @return array<int,array{0:int,1:int}>
     */
    private function candidatePairs(array $groups): array
    {
        $byMetadata = [];
        $byTitle = [];
        $byGram = [];
        foreach ($groups as $index => $group) {
            $typeId = (int)($group['media_type_id'] ?? 0);
            foreach (['douban_id' => 'douban', 'tmdb_id' => 'tmdb'] as $field => $prefix) {
                $value = trim((string)($group[$field] ?? ''));
                if ($value !== '') $byMetadata[$prefix . ':' . $value][] = $index;
            }
            $title = $this->normalizeTitle((string)($group['series_name'] ?? ''));
            if ($title === '') continue;
            $byTitle[$title][] = $index;
            foreach ($this->titleNgrams($title) as $gram) {
                // A title n-gram is only a discovery shortcut. score() still
                // verifies that one normalized title contains the other.
                $byGram[$typeId . ':' . $gram][] = $index;
            }
        }

        $pairs = [];
        foreach ($byMetadata as $bucket) $this->addCandidatePairs($pairs, $bucket, 256);
        foreach ($byTitle as $bucket) $this->addCandidatePairs($pairs, $bucket, 256);
        foreach ($byGram as $bucket) $this->addCandidatePairs($pairs, $bucket, 96);
        return array_values($pairs);
    }

    /** @return array<int,string> */
    private function titleNgrams(string $title): array
    {
        $length = mb_strlen($title);
        if ($length < 3) return [];
        $grams = [];
        for ($offset = 0; $offset <= $length - 3; $offset++) {
            $grams[mb_substr($title, $offset, 3)] = true;
        }
        return array_keys($grams);
    }

    /** @param array<string,array{0:int,1:int}> $pairs @param array<int,int> $bucket */
    private function addCandidatePairs(array &$pairs, array $bucket, int $maxBucketSize): void
    {
        $bucket = array_values(array_unique(array_map('intval', $bucket)));
        $count = count($bucket);
        if ($count < 2 || $count > $maxBucketSize) return;
        for ($left = 0; $left < $count - 1; $left++) {
            for ($right = $left + 1; $right < $count; $right++) {
                $a = min($bucket[$left], $bucket[$right]);
                $b = max($bucket[$left], $bucket[$right]);
                $pairs[$a . ':' . $b] = [$a, $b];
            }
        }
    }

    /** AI only reviews a concrete administrator-visible candidate; it never merges automatically. */
    public function reviewCandidateWithAi(int $candidateId): array
    {
        $candidate = $this->db->fetch('SELECT * FROM media_merge_candidates WHERE id = :id AND status = :status LIMIT 1', ['id' => $candidateId, 'status' => 'pending']);
        if (!$candidate) throw new InvalidArgumentException('重复候选不存在或已经处理。');
        [$aiResult, $explanation] = $this->aiCheck((string)$candidate['target_name'], (string)$candidate['duplicate_name']);
        if ($aiResult === null) throw new RuntimeException('AI 核验未得到有效结果，请检查 AI 配置后重试。');
        $score = (float)$candidate['score'];
        if ($aiResult === 'same') $score = max($score, 94.0);
        $reason = $aiResult === 'same'
            ? 'AI 判断为同一影视，标题存在别名或版本差异'
            : 'AI 判断为不同影视，仍由管理员最终决定是否合并';
        $this->db->update('media_merge_candidates', [
            'score' => number_format($score, 2, '.', ''), 'reason' => $reason,
            'ai_result' => $aiResult, 'ai_explanation' => $explanation, 'updated_at' => withu_now(),
        ], 'id = :id', ['id' => $candidateId]);
        return ['ai_result' => $aiResult, 'message' => $aiResult === 'same' ? 'AI 建议可合并，仍需人工确认。' : 'AI 建议不要合并，记录保留供人工决定。'];
    }

    public function candidates(int $limit = 100): array
    {
        return $this->db->fetchAll('SELECT * FROM media_merge_candidates WHERE status = :status ORDER BY score DESC, id DESC LIMIT ' . max(1, min(500, $limit)), ['status' => 'pending']);
    }

    public function merge(int $candidateId): array
    {
        $candidate = $this->db->fetch('SELECT * FROM media_merge_candidates WHERE id = :id AND status = :status LIMIT 1', ['id' => $candidateId, 'status' => 'pending']);
        if (!$candidate) throw new InvalidArgumentException('重复候选不存在或已经处理。');
        $targetKey = (string)$candidate['target_series_key'];
        $duplicateKey = (string)$candidate['duplicate_series_key'];
        $targetRows = $this->db->fetchAll('SELECT * FROM media_library WHERE series_key = :key ORDER BY season_number,episode_number,id', ['key' => $targetKey]);
        $duplicateRows = $this->db->fetchAll('SELECT * FROM media_library WHERE series_key = :key ORDER BY season_number,episode_number,id', ['key' => $duplicateKey]);
        $removed = 0;
        $moved = 0;
        foreach ($duplicateRows as $row) {
            $match = null;
            foreach ($targetRows as $target) {
                if ((string)($target['catalog_key'] ?? '') !== '' && (string)$target['catalog_key'] === (string)($row['catalog_key'] ?? '')) { $match = $target; break; }
                if (((int)($row['episode_number'] ?? 0) > 0 || $this->episodeTitleKey((string)($row['episode_title'] ?? '')) !== '')
                    && ((int)($target['episode_number'] ?? 0) > 0 || $this->episodeTitleKey((string)($target['episode_title'] ?? '')) !== '')
                    && (int)($target['season_number'] ?? 0) === (int)($row['season_number'] ?? 0)
                    && (int)($target['episode_number'] ?? 0) === (int)($row['episode_number'] ?? 0)
                    && $this->episodeTitleKey((string)($target['episode_title'] ?? '')) === $this->episodeTitleKey((string)($row['episode_title'] ?? ''))) {
                    $match = $target;
                    break;
                }
            }
            if ($match && (int)$match['id'] !== (int)$row['id']) {
                $movedSources = $this->moveSources((int)$row['id'], (int)$match['id']);
                $this->repointReferences((int)$row['id'], (int)$match['id']);
                $this->db->delete('media_library', 'id = :id', ['id' => (int)$row['id']]);
                $removed++;
                $moved += $movedSources;
                continue;
            }
            $this->db->update('media_library', ['series_key' => $targetKey, 'series_name' => $candidate['target_name'], 'updated_at' => withu_now()], 'id = :id', ['id' => (int)$row['id']]);
            $moved++;
        }
        $this->db->update('media_merge_candidates', ['status' => 'merged', 'updated_at' => withu_now()], 'id = :id', ['id' => $candidateId]);
        return ['success' => true, 'moved' => $moved, 'removed' => $removed, 'message' => "已合并：迁移 {$moved} 个来源/分集，去重 {$removed} 集。"];
    }

    private function episodeTitleKey(string $title): string
    {
        return withu_media_catalog_normalize_episode_title($title);
    }

    private function moveSources(int $fromMediaId, int $toMediaId): int
    {
        $sources = $this->db->fetchAll('SELECT id,source_hash FROM media_catalog_sources WHERE media_id = :media_id', ['media_id' => $fromMediaId]);
        $moved = 0;
        foreach ($sources as $source) {
            $same = $this->db->fetch('SELECT id FROM media_catalog_sources WHERE media_id = :media_id AND source_hash = :source_hash LIMIT 1', ['media_id' => $toMediaId, 'source_hash' => $source['source_hash']]);
            if ($same) $this->db->delete('media_catalog_sources', 'id = :id', ['id' => (int)$source['id']]);
            else { $this->db->update('media_catalog_sources', ['media_id' => $toMediaId, 'updated_at' => withu_now()], 'id = :id', ['id' => (int)$source['id']]); $moved++; }
        }
        return $moved;
    }

    private function repointReferences(int $fromMediaId, int $toMediaId): void
    {
        $this->db->update('media_link_checks', ['media_id' => $toMediaId], 'media_id = :media_id', ['media_id' => $fromMediaId]);
        try {
            $main = Database::getInstance();
            $main->update('watch_rooms', ['media_id' => $toMediaId], 'media_id = :media_id', ['media_id' => $fromMediaId]);
            $main->update('watch_history', ['media_id' => $toMediaId], 'media_id = :media_id', ['media_id' => $fromMediaId]);
        } catch (Throwable $e) {
            // The media database can be used independently by CLI maintenance jobs.
        }
    }

    public function reject(int $candidateId): void
    {
        $this->db->update('media_merge_candidates', ['status' => 'rejected', 'updated_at' => withu_now()], 'id = :id AND status = :status', ['id' => $candidateId, 'status' => 'pending']);
    }

    public function checkLinks(array $mediaIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $mediaIds), static fn(int $id): bool => $id > 0)));
        if (!$ids) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->db->getPDO()->prepare("SELECT s.id AS source_id,s.media_id,s.source_url FROM media_catalog_sources s WHERE s.media_id IN ({$placeholders}) AND s.status = 'active' AND s.source_url IS NOT NULL AND s.source_url <> '' ORDER BY s.media_id,s.is_primary DESC,s.id ASC");
        $rows->execute($ids);
        $sourceRows = $rows->fetchAll(PDO::FETCH_ASSOC);
        if (!$sourceRows) {
            $legacy = $this->db->getPDO()->prepare("SELECT id AS media_id,source_url FROM media_library WHERE id IN ({$placeholders}) AND source_url IS NOT NULL AND source_url <> ''");
            $legacy->execute($ids);
            $sourceRows = array_map(static fn(array $row): array => ['source_id' => 0, 'media_id' => (int)$row['media_id'], 'source_url' => $row['source_url']], $legacy->fetchAll(PDO::FETCH_ASSOC));
        }
        $results = [];
        foreach ($sourceRows as $media) {
            $url = trim((string)$media['source_url']);
            $check = $this->probe($url);
            $check['media_id'] = (int)$media['media_id'];
            $check['source_id'] = (int)$media['source_id'];
            $check['source_url'] = $url;
            $this->db->query(
                "INSERT INTO media_link_checks (media_id,source_id,source_url,url_hash,final_url,http_code,content_type,content_length,etag,last_modified,content_sample_hash,fingerprint,fingerprint_method,comparison_confidence,status,message,checked_at)
                 VALUES (:media_id,:source_id,:source_url,:url_hash,:final_url,:http_code,:content_type,:content_length,:etag,:last_modified,:content_sample_hash,:fingerprint,:fingerprint_method,:comparison_confidence,:status,:message,:checked_at)
                 ON DUPLICATE KEY UPDATE media_id=VALUES(media_id),source_url=VALUES(source_url),final_url=VALUES(final_url),http_code=VALUES(http_code),content_type=VALUES(content_type),content_length=VALUES(content_length),etag=VALUES(etag),last_modified=VALUES(last_modified),content_sample_hash=VALUES(content_sample_hash),fingerprint=VALUES(fingerprint),fingerprint_method=VALUES(fingerprint_method),comparison_confidence=VALUES(comparison_confidence),status=VALUES(status),message=VALUES(message),checked_at=VALUES(checked_at)",
                $check + ['media_id' => (int)$media['media_id'], 'source_id' => (int)$media['source_id'], 'source_url' => $url, 'checked_at' => withu_now()]
            );
            $same = $check['fingerprint'] !== '' ? $this->db->fetchAll(
                'SELECT DISTINCT media_id,fingerprint_method,comparison_confidence FROM media_link_checks WHERE fingerprint = :fingerprint AND media_id <> :media_id',
                ['fingerprint' => $check['fingerprint'], 'media_id' => (int)$media['media_id']]
            ) : [];
            $check['same_media_ids'] = array_values(array_map(static fn(array $row): int => (int)$row['media_id'], $same));
            $check['same_media_matches'] = $same;
            $results[] = $check;
        }
        return $results;
    }

    private function probe(string $url): array
    {
        $error = '';
        $info = [];
        $headers = [];
        $body = '';
        $bodyTruncated = false;
        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $ch = curl_init($url);
            $headers = [];
            $body = '';
            $bodyTruncated = false;
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => false, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5,
                CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 20, CURLOPT_RANGE => '0-4095',
                CURLOPT_USERAGENT => 'WithU-LinkCheck/1.0', CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HEADERFUNCTION => static function ($handle, string $header) use (&$headers): int {
                    $line = trim($header);
                    if (preg_match('#^HTTP/\\d(?:\\.\\d)?\\s#i', $line)) {
                        // cURL emits every redirect response. Keep only the final hop.
                        $headers = [];
                    } elseif (($colon = strpos($line, ':')) !== false) {
                        $name = strtolower(trim(substr($line, 0, $colon)));
                        if (in_array($name, ['etag', 'last-modified', 'content-range', 'content-length'], true)) {
                            $headers[$name] = trim(substr($line, $colon + 1));
                        }
                    }
                    return strlen($header);
                },
                CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, &$bodyTruncated): int {
                    $remaining = 4096 - strlen($body);
                    if ($remaining > 0) $body .= substr($chunk, 0, $remaining);
                    if (strlen($chunk) > $remaining) {
                        $bodyTruncated = true;
                        return 0;
                    }
                    return strlen($chunk);
                },
            ]);
            curl_exec($ch);
            $error = curl_error($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);
            $status = (int)($info['http_code'] ?? 0);
            if ($bodyTruncated && str_contains(strtolower($error), 'failed writing body')) $error = '';
            if ($status >= 200 && $status < 400) break;
            if ($status >= 400 && $status < 500 && $status !== 408 && $status !== 429) break;
            if ($attempt < 20) usleep(min(4000000, 200000 * $attempt));
        }
        $finalUrl = trim((string)($info['url'] ?? ''));
        $contentType = $this->normalizeContentType((string)($info['content_type'] ?? ''));
        $length = $this->contentLength($headers, $info);
        $etag = trim((string)($headers['etag'] ?? ''));
        $lastModified = trim((string)($headers['last-modified'] ?? ''));
        $sampleHash = $body !== '' ? hash('sha256', $body) : '';
        [$fingerprint, $method, $confidence] = $this->fingerprint($finalUrl, $contentType, $length, $etag, $lastModified, $sampleHash);
        $status = ((int)($info['http_code'] ?? 0) >= 200 && (int)($info['http_code'] ?? 0) < 400) ? 'ok' : 'failed';
        return [
            'url_hash' => hash('sha256', $url), 'final_url' => $finalUrl, 'http_code' => (int)($info['http_code'] ?? 0),
            'content_type' => $contentType !== '' ? mb_substr($contentType, 0, 120) : null, 'content_length' => $length,
            'etag' => $etag !== '' ? mb_substr($etag, 0, 255) : null, 'last_modified' => $lastModified !== '' ? mb_substr($lastModified, 0, 120) : null,
            'content_sample_hash' => $sampleHash !== '' ? $sampleHash : null, 'fingerprint' => $fingerprint,
            'fingerprint_method' => $method, 'comparison_confidence' => $confidence,
            'status' => $status, 'message' => $error !== '' ? mb_substr($error, 0, 500) : ($status === 'ok' ? '链接可访问（' . $this->confidenceLabel($confidence) . '）' : '链接不可访问'),
        ];
    }

    private function normalizeContentType(string $contentType): string
    {
        return strtolower(trim((string)strtok($contentType, ';')));
    }

    private function contentLength(array $headers, array $info): ?int
    {
        $range = (string)($headers['content-range'] ?? '');
        if (preg_match('/\\/(\\d+)\\s*$/', $range, $matches)) return (int)$matches[1];
        if (isset($headers['content-length']) && ctype_digit((string)$headers['content-length'])) return (int)$headers['content-length'];
        return isset($info['download_content_length']) && (float)$info['download_content_length'] >= 0 ? (int)$info['download_content_length'] : null;
    }

    /** @return array{0:string,1:string,2:string} */
    private function fingerprint(string $finalUrl, string $contentType, ?int $length, string $etag, string $lastModified, string $sampleHash): array
    {
        $base = '|length=' . ($length === null ? 'unknown' : (string)$length) . '|type=' . $contentType;
        if ($etag !== '') {
            $weak = str_starts_with(strtolower($etag), 'w/');
            $method = $weak ? 'weak-etag' : 'strong-etag';
            return [hash('sha256', $method . '|' . strtolower($etag) . $base), $method, $weak ? 'likely' : 'confirmed'];
        }
        if ($lastModified !== '' && $length !== null) {
            return [hash('sha256', 'last-modified|' . strtolower($lastModified) . $base), 'last-modified-length', 'likely'];
        }
        if ($sampleHash !== '' && $length !== null) {
            return [hash('sha256', 'sample|' . $sampleHash . $base), 'range-sample-length', 'possible'];
        }
        $canonicalUrl = $this->canonicalUrl($finalUrl);
        if ($canonicalUrl !== '') {
            return [hash('sha256', 'canonical-url|' . $canonicalUrl), 'canonical-url', 'possible'];
        }
        return ['', 'none', 'none'];
    }

    private function canonicalUrl(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) return '';
        $scheme = strtolower((string)($parts['scheme'] ?? 'https'));
        $host = strtolower((string)$parts['host']);
        $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
        $path = (string)($parts['path'] ?? '/');
        return $scheme . '://' . $host . $port . $path;
    }

    private function confidenceLabel(string $confidence): string
    {
        return match ($confidence) {
            'confirmed' => '已确认内容指纹',
            'likely' => '高可信内容指纹',
            'possible' => '候选内容指纹',
            default => '未取得内容指纹',
        };
    }

    private function score(array $a, array $b): float
    {
        if (!empty($a['douban_id']) && $a['douban_id'] === $b['douban_id']) return 100;
        if (!empty($a['tmdb_id']) && $a['tmdb_id'] === $b['tmdb_id']) return 100;
        $left = $this->normalizeTitle((string)$a['series_name']);
        $right = $this->normalizeTitle((string)$b['series_name']);
        if ($left === '' || $right === '') return 0;
        if ($left === $right) return 95;
        if (mb_strlen($left) >= 3 && (mb_strpos($left, $right) !== false || mb_strpos($right, $left) !== false)) return ((int)$a['media_type_id'] === (int)$b['media_type_id']) ? 82 : 74;
        return 0;
    }

    private function normalizeTitle(string $title): string
    {
        $title = mb_strtolower(trim($title));
        $title = preg_replace('/\b(?:19|20)\d{2}\b|s\d{1,2}|第\s*\d{1,4}\s*集|\d{3,4}p|web[- ]?dl|hdr|h265|h264|hevc|aac/iu', '', $title);
        return preg_replace('/[\s\-_:.：!！?？,，.。·《》\[\]\(\)（）]+/u', '', $title);
    }

    private function reason(array $a, array $b, ?string $aiResult): string
    {
        if (!empty($a['douban_id']) && $a['douban_id'] === $b['douban_id']) return '豆瓣 ID 相同';
        if (!empty($a['tmdb_id']) && $a['tmdb_id'] === $b['tmdb_id']) return 'TMDb ID 相同';
        if ($this->normalizeTitle((string)$a['series_name']) === $this->normalizeTitle((string)$b['series_name'])) return '清洗标题相同';
        return $aiResult === 'same' ? 'AI 判断为同一影视，标题存在别名或版本差异' : '标题包含关系且分类相同';
    }

    private function aiCheck(string $left, string $right): array
    {
        if ((string)get_setting('media_ai_classify_enabled', '1') !== '1') return [null, null];
        $endpoint = trim((string)get_setting('ai_api_endpoint', ''));
        $apiKey = trim((string)get_setting('ai_api_key', ''));
        if ($endpoint === '' || $apiKey === '') return [null, null];
        $payload = json_encode(['model' => get_setting('ai_model', 'deepseek-chat'), 'messages' => [
            ['role' => 'system', 'content' => '你是影视去重助手，只返回 JSON：{"same":true或false,"reason":"不超过80字"}。只根据标题判断，不确定时返回 false。'],
            ['role' => 'user', 'content' => "标题A：{$left}\n标题B：{$right}"],
        ], 'temperature' => 0], JSON_UNESCAPED_UNICODE);
        $raw = '';
        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 10, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey]]);
            $candidateRaw = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            if (is_string($candidateRaw) && $candidateRaw !== '' && $curlError === '' && $httpCode >= 200 && $httpCode < 300) {
                $raw = $candidateRaw;
                break;
            }
            if ($attempt < 20) usleep(min(1000000, 100000 * $attempt));
        }
        $response = is_string($raw) ? json_decode($raw, true) : null;
        $content = preg_replace('/^```(?:json)?|```$/i', '', trim((string)($response['choices'][0]['message']['content'] ?? '')));
        $answer = json_decode(trim($content), true);
        if (!is_array($answer) || !array_key_exists('same', $answer)) return [null, null];
        return [filter_var($answer['same'], FILTER_VALIDATE_BOOLEAN) ? 'same' : 'different', mb_substr(trim((string)($answer['reason'] ?? '')), 0, 500) ?: null];
    }
}
