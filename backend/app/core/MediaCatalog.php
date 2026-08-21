<?php

/**
 * Unified media catalogue helpers.
 *
 * media_library keeps one canonical episode row. Every active source is a
 * WebDAV path and points to that row.
 */

if (!function_exists('withu_media_catalog_normalize_title')) {
    function withu_media_catalog_normalize_title(string $title): string
    {
        $title = mb_strtolower(trim($title));
        $title = str_replace(['（', '）', '【', '】', '［', '］'], ['(', ')', '[', ']', '[', ']'], $title);
        $title = preg_replace('/\.(?:mp4|mkv|avi|mov|flv|ts|m3u8)$/iu', '', $title);
        $title = preg_replace('/\b(?:19|20)\d{2}\b/iu', '', $title);
        $title = preg_replace('/\b(?:s\d{1,2}e\d{1,4}|第\s*\d{1,4}\s*集|ep?\s*\d{1,4})\b/iu', '', $title);
        $title = preg_replace('/\b(?:2160p|1440p|1080p|720p|480p|4k|2k|fhd|hd|web[- ]?dl|hdr|sdr|h\.?265|h\.?264|hevc|aac|ddp|dolby|x264|x265|60fps)\b/iu', '', $title);
        $title = preg_replace('/[\[\(][^\]\)]*(?:中字|字幕|国语|粤语|杜比|高码|资源|合集)[^\]\)]*[\]\)]/u', '', $title);
        $title = preg_replace('/[^\p{L}\p{N}]+/u', '', $title);
        return trim((string)$title);
    }
}

if (!function_exists('withu_media_catalog_normalize_episode_title')) {
    function withu_media_catalog_normalize_episode_title(string $title): string
    {
        $title = trim($title);
        if ($title === '') return '';
        if (preg_match('/^(?:第\s*)?\d{1,4}\s*(?:集|话|期)?$/u', $title)) return '';
        $title = preg_replace('/^第\s*/u', '', $title);
        $title = preg_replace('/(?:集|话|期)$/u', '', (string)$title);
        return withu_media_catalog_normalize_title((string)$title);
    }
}

if (!function_exists('withu_media_catalog_source_kind')) {
    function withu_media_catalog_source_kind(array $media): string
    {
        return 'webdav';
    }
}

if (!function_exists('withu_media_catalog_series_key')) {
    function withu_media_catalog_series_key(array $media): string
    {
        $typeId = max(1, (int)($media['media_type_id'] ?? 1));
        $name = withu_media_catalog_normalize_title((string)($media['series_name'] ?? $media['file_name'] ?? '未命名影视'));
        if ($name === '') $name = 'unnamed';
        return 'catalog:' . sha1($typeId . '|' . $name);
    }
}

if (!function_exists('withu_media_catalog_key')) {
    function withu_media_catalog_key(array $media): string
    {
        $seriesKey = withu_media_catalog_series_key($media);
        $season = max(0, (int)($media['season_number'] ?? 0));
        $episode = max(0, (int)($media['episode_number'] ?? 0));
        $suffix = 's' . $season . 'e' . $episode;
        $episodeTitle = withu_media_catalog_normalize_episode_title((string)($media['episode_title'] ?? ''));
        // Some JSON sources put every short-video URL at episode 1. A
        // descriptive label is the only reliable episode identity in that
        // case; retaining it prevents unrelated episodes from collapsing.
        if ($episodeTitle !== '' && in_array((int)($media['media_type_id'] ?? 1), [2, 3, 4], true)) {
            $suffix .= '|t:' . sha1($episodeTitle);
        }
        // Unknown TV episode numbers must not collapse every file into one row.
        if ($episode === 0 && $episodeTitle === '' && (int)($media['media_type_id'] ?? 1) === 2) {
            $hint = withu_media_catalog_normalize_title((string)($media['file_name'] ?? $media['episode_title'] ?? ''));
            $suffix .= '|f:' . ($hint !== '' ? $hint : 'unknown');
        }
        return $seriesKey . '|' . $suffix;
    }
}

if (!function_exists('withu_media_catalog_source_hash')) {
    function withu_media_catalog_source_hash(string $kind, string $sourceKey): string
    {
        return hash('sha256', $kind . '|' . $sourceKey);
    }
}

if (!function_exists('withu_media_catalog_prepare')) {
    function withu_media_catalog_prepare(array $media): array
    {
        if (function_exists('withu_media_structure')) {
            $display = withu_media_structure($media);
            foreach ($display as $key => $value) {
                if (!isset($media[$key]) || $media[$key] === '' || $media[$key] === null) $media[$key] = $value;
            }
        }
        $media['series_key'] = withu_media_catalog_series_key($media);
        $media['catalog_key'] = withu_media_catalog_key($media);
        return $media;
    }
}

if (!function_exists('withu_media_catalog_source_data')) {
    function withu_media_catalog_source_data(array $media, int $mediaId, string $kind): array
    {
        $sourceKey = trim((string)($media['source_key'] ?? $media['source_url'] ?? ''));
        return [
            'media_id' => $mediaId,
            'source_kind' => $kind,
            'source_key' => mb_substr($sourceKey, 0, 500),
            'source_hash' => withu_media_catalog_source_hash($kind, $sourceKey),
            'source_url' => trim((string)($media['source_url'] ?? '')) ?: null,
            'file_name' => mb_substr((string)($media['file_name'] ?? ''), 0, 255),
            'file_size' => isset($media['file_size']) && $media['file_size'] !== null ? (int)$media['file_size'] : null,
            'file_etag' => trim((string)($media['file_etag'] ?? '')) ?: null,
            'fingerprint' => trim((string)($media['fingerprint'] ?? '')) ?: null,
            'mime_type' => trim((string)($media['mime_type'] ?? '')) ?: null,
            'season_number' => (int)($media['season_number'] ?? 0),
            'episode_number' => (int)($media['episode_number'] ?? 0),
            'episode_title' => mb_substr(trim((string)($media['episode_title'] ?? '')), 0, 255) ?: null,
            'is_primary' => 0,
            'status' => 'active',
            'updated_at' => withu_now(),
        ];
    }
}

if (!function_exists('withu_media_catalog_source_label')) {
    function withu_media_catalog_source_label(array $source): string
    {
        $kind = strtolower(trim((string)($source['source_kind'] ?? 'external')));
        if ($kind === 'webdav') {
            $fileName = trim((string)($source['file_name'] ?? ''));
            return $fileName !== '' ? 'WebDAV · ' . $fileName : 'WebDAV';
        }
        return 'WebDAV';
    }
}

if (!function_exists('withu_media_catalog_source_display')) {
    function withu_media_catalog_source_display(array $source): array
    {
        $source['source_id'] = (int)($source['id'] ?? $source['source_id'] ?? 0);
        $source['source_kind'] = trim((string)($source['source_kind'] ?? 'external')) ?: 'external';
        $source['source_label'] = withu_media_catalog_source_label($source);
        $source['player_mode'] = function_exists('withu_media_player_mode')
            ? withu_media_player_mode($source)
            : 'direct';
        $source['player_code'] = function_exists('withu_media_player_code')
            ? withu_media_player_code($source)
            : 'webdav';
        $source['episode_number'] = (int)($source['episode_number'] ?? 0);
        return $source;
    }
}

if (!function_exists('withu_media_catalog_fetch_sources')) {
    function withu_media_catalog_fetch_sources($db, int $mediaId, bool $includeInactive = false): array
    {
        if ($mediaId <= 0) return [];
        $where = $includeInactive ? '' : " AND status = 'active'";
        $rows = $db->fetchAll(
            // Prefer WebDAV as the default source so normal playback does not
            // A requested source ID is honored only when it is an active WebDAV source.
            "SELECT * FROM media_catalog_sources WHERE media_id = :media_id AND source_kind = 'webdav'" . $where . ' ORDER BY is_primary DESC, id ASC',
            ['media_id' => $mediaId]
        );
        return array_map('withu_media_catalog_source_display', $rows);
    }
}

if (!function_exists('withu_media_catalog_fetch_source')) {
    function withu_media_catalog_fetch_source($db, int $mediaId, int $sourceId = 0): ?array
    {
        if ($mediaId <= 0) return null;
        $params = ['media_id' => $mediaId];
        $where = "media_id = :media_id AND source_kind = 'webdav' AND status = 'active'";
        if ($sourceId > 0) {
            $where .= ' AND id = :source_id';
            $params['source_id'] = $sourceId;
            $source = $db->fetch('SELECT * FROM media_catalog_sources WHERE ' . $where . ' LIMIT 1', $params);
            return $source ? withu_media_catalog_source_display($source) : null;
        }
        $source = $db->fetch("SELECT * FROM media_catalog_sources WHERE {$where} ORDER BY is_primary DESC, id ASC LIMIT 1", $params);
        return $source ? withu_media_catalog_source_display($source) : null;
    }
}

if (!function_exists('withu_media_catalog_apply_source')) {
    function withu_media_catalog_apply_source(array $media, array $source): array
    {
        foreach (['source_key', 'source_url', 'file_name', 'file_size', 'file_etag', 'mime_type', 'episode_number', 'episode_title'] as $field) {
            if (array_key_exists($field, $source) && $source[$field] !== null && $source[$field] !== '') $media[$field] = $source[$field];
        }
        $media['direct_url'] = null;
        $media['source_id'] = (int)($source['source_id'] ?? $source['id'] ?? 0);
        $media['source_kind'] = (string)($source['source_kind'] ?? 'external');
        $media['source_label'] = (string)($source['source_label'] ?? withu_media_catalog_source_label($source));
        $media['player_mode'] = 'direct';
        $media['player_code'] = 'webdav';
        return $media;
    }
}

if (!function_exists('withu_media_catalog_resolve_row')) {
    function withu_media_catalog_resolve_row($db, array $media, int $sourceId = 0): array
    {
        $source = withu_media_catalog_fetch_source($db, (int)($media['id'] ?? 0), $sourceId);
        return $source ? withu_media_catalog_apply_source($media, $source) : $media;
    }
}

if (!function_exists('withu_media_catalog_attach_source')) {
    function withu_media_catalog_attach_source($db, array $media, int $mediaId, string $kind): void
    {
        $data = withu_media_catalog_source_data($media, $mediaId, $kind);
        $existing = $db->fetch('SELECT id,is_primary FROM media_catalog_sources WHERE source_hash = :source_hash LIMIT 1', ['source_hash' => $data['source_hash']]);
        if ($existing) {
            unset($data['media_id'], $data['source_kind'], $data['source_key'], $data['source_hash'], $data['is_primary']);
            $db->update('media_catalog_sources', $data, 'id = :id', ['id' => (int)$existing['id']]);
            return;
        }
        $hasPrimary = $db->fetch('SELECT id FROM media_catalog_sources WHERE media_id = :media_id AND is_primary = 1 LIMIT 1', ['media_id' => $mediaId]);
        $data['is_primary'] = $hasPrimary ? 0 : 1;
        $data['created_at'] = withu_now();
        $db->insert('media_catalog_sources', $data);
    }
}

if (!function_exists('withu_media_catalog_merge_metadata')) {
    function withu_media_catalog_merge_metadata($db, array $current, array $incoming, int $mediaId): void
    {
        $fields = ['series_name', 'episode_title', 'media_type_id', 'season_number', 'episode_number', 'video_codec', 'audio_codec', 'cover_url', 'backdrop_url', 'tags', 'douban_id', 'tmdb_id', 'rating', 'cast_names', 'summary', 'resolution', 'duration_ms', 'width', 'height', 'mime_type', 'metadata_json', 'recognition_status', 'recognition_source', 'recognized_at', 'folder_created_at', 'source_id', 'resource_id', 'source_path', 'folder_path', 'file_extension', 'last_modified', 'fingerprint'];
        $update = ['series_key' => $incoming['series_key'], 'catalog_key' => $incoming['catalog_key'], 'updated_at' => withu_now()];
        foreach ($fields as $field) {
            $old = $current[$field] ?? null;
            $new = $incoming[$field] ?? null;
            if ($new !== null && $new !== '' && (($old === null || $old === '' || $old === '0') || in_array($field, ['media_type_id', 'season_number', 'episode_number', 'video_codec', 'audio_codec', 'resolution', 'metadata_json', 'recognition_status', 'recognition_source', 'recognized_at', 'source_id', 'resource_id', 'source_path', 'folder_path', 'file_extension', 'last_modified', 'fingerprint'], true))) $update[$field] = $new;
        }
        if (count($update) > 2) $db->update('media_library', $update, 'id = :id', ['id' => $mediaId]);
        else $db->update('media_library', ['series_key' => $incoming['series_key'], 'catalog_key' => $incoming['catalog_key'], 'updated_at' => withu_now()], 'id = :id', ['id' => $mediaId]);
    }
}

if (!function_exists('withu_media_catalog_upsert')) {
    function withu_media_catalog_upsert($db, array $media, ?string $kind = null): array
    {
        $media = withu_media_catalog_prepare($media);
        $kind = $kind ?: withu_media_catalog_source_kind($media);
        $sourceKey = trim((string)($media['source_key'] ?? $media['source_url'] ?? ''));
        if ($sourceKey === '') throw new InvalidArgumentException('媒体来源标识不能为空。');
        $sourceHash = withu_media_catalog_source_hash($kind, $sourceKey);
        $source = $db->fetch('SELECT media_id FROM media_catalog_sources WHERE source_hash = :source_hash LIMIT 1', ['source_hash' => $sourceHash]);
        $existing = $source ? $db->fetch('SELECT * FROM media_library WHERE id = :id LIMIT 1', ['id' => (int)$source['media_id']]) : null;
        if (!$existing) $existing = $db->fetch('SELECT * FROM media_library WHERE source_key = :source_key LIMIT 1', ['source_key' => $sourceKey]);
        if (!$existing) $existing = $db->fetch('SELECT * FROM media_library WHERE catalog_key = :catalog_key LIMIT 1', ['catalog_key' => $media['catalog_key']]);

        $now = withu_now();
        if ($existing) {
            $mediaId = (int)$existing['id'];
            withu_media_catalog_merge_metadata($db, $existing, $media, $mediaId);
            withu_media_catalog_attach_source($db, $media, $mediaId, $kind);
            $fresh = $db->fetch('SELECT * FROM media_library WHERE id = :id LIMIT 1', ['id' => $mediaId]);
            return ['id' => $mediaId, 'changed' => true, 'merged' => (string)($existing['catalog_key'] ?? '') !== (string)$media['catalog_key'], 'media' => $fresh ?: $existing];
        }

        $data = $media;
        $data['source_key'] = mb_substr($sourceKey, 0, 500);
        $data['source_url'] = trim((string)($data['source_url'] ?? '')) ?: null;
        $data['direct_url'] = null;
        $data['last_scanned_at'] = $data['last_scanned_at'] ?? $now;
        $data['updated_at'] = $data['updated_at'] ?? $now;
        $data['created_at'] = $data['created_at'] ?? $now;
        $mediaId = (int)$db->insert('media_library', $data);
        withu_media_catalog_attach_source($db, $data, $mediaId, $kind);
        $fresh = $db->fetch('SELECT * FROM media_library WHERE id = :id LIMIT 1', ['id' => $mediaId]);
        return ['id' => $mediaId, 'changed' => true, 'merged' => false, 'media' => $fresh ?: $data];
    }
}
