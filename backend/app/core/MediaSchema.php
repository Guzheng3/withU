<?php

function withu_media_db_name(): string
{
    $config = require ROOT_PATH . '/config/media_database.php';
    return (string)($config['dbname'] ?? 'withu_media');
}

function withu_media_db_available(): bool
{
    try {
        MediaDatabase::getInstance()->fetch('SELECT 1 AS ok');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function migrate_media_schema_if_needed($db = null): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    $schemaVersion = '20260723-06';
    $runtimeDir = dirname(ROOT_PATH) . DIRECTORY_SEPARATOR . 'runtime';
    $markerPath = $runtimeDir . DIRECTORY_SEPARATOR . 'media-schema-version';
    $lockPath = $runtimeDir . DIRECTORY_SEPARATOR . 'media-schema-migration.lock';
    if (is_file($markerPath) && trim((string)@file_get_contents($markerPath)) === $schemaVersion) return;
    if (!is_dir($runtimeDir)) @mkdir($runtimeDir, 0775, true);
    $lock = @fopen($lockPath, 'c');
    if ($lock) @flock($lock, LOCK_EX);
    $db = $db ?: MediaDatabase::getInstance();

    $tables = [
        "CREATE TABLE IF NOT EXISTS `media_library` (
            `id` int(11) NOT NULL AUTO_INCREMENT, `source_key` varchar(500) NOT NULL,
            `source_url` text DEFAULT NULL, `direct_url` text DEFAULT NULL, `file_name` varchar(255) NOT NULL,
            `series_key` varchar(255) DEFAULT NULL, `catalog_key` varchar(255) DEFAULT NULL, `series_name` varchar(255) DEFAULT NULL,
            `season_number` int(11) DEFAULT NULL, `episode_number` int(11) DEFAULT NULL, `episode_title` varchar(255) DEFAULT NULL,
            `media_type_id` smallint(6) NOT NULL DEFAULT 1, `video_codec` varchar(30) DEFAULT NULL, `audio_codec` varchar(30) DEFAULT NULL,
            `browser_playback` varchar(20) NOT NULL DEFAULT 'direct', `file_size` bigint(20) unsigned DEFAULT NULL,
            `file_md5` varchar(64) DEFAULT NULL, `file_etag` varchar(255) DEFAULT NULL, `mime_type` varchar(100) DEFAULT NULL,
            `duration_ms` bigint(20) unsigned DEFAULT NULL, `width` int(11) DEFAULT NULL, `height` int(11) DEFAULT NULL,
            `resolution` varchar(40) DEFAULT NULL, `tags` varchar(500) DEFAULT NULL, `douban_id` varchar(40) DEFAULT NULL,
            `tmdb_id` varchar(40) DEFAULT NULL, `rating` decimal(3,1) DEFAULT NULL, `cast_names` text DEFAULT NULL,
            `summary` text DEFAULT NULL, `cover_url` text DEFAULT NULL, `backdrop_url` text DEFAULT NULL,
            `intro_start_ms` bigint(20) unsigned DEFAULT NULL, `intro_end_ms` bigint(20) unsigned DEFAULT NULL,
            `recognition_status` varchar(20) NOT NULL DEFAULT 'pending', `recognition_source` varchar(30) DEFAULT NULL,
            `recognized_at` datetime DEFAULT NULL, `last_scanned_at` datetime NOT NULL, `folder_created_at` datetime DEFAULT NULL,
            `created_at` datetime NOT NULL, `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `uk_media_source` (`source_key`),
            KEY `idx_media_status_updated` (`recognition_status`,`updated_at`,`id`),
            KEY `idx_media_catalog_key` (`catalog_key`(191)), KEY `idx_media_type` (`media_type_id`,`recognition_status`,`updated_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='WithU WebDAV影视资源库';",
        "CREATE TABLE IF NOT EXISTS `media_catalog_sources` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT, `media_id` int(11) NOT NULL,
            `source_kind` varchar(20) NOT NULL DEFAULT 'webdav', `source_key` varchar(500) NOT NULL,
            `source_hash` char(64) NOT NULL, `source_url` text DEFAULT NULL, `file_name` varchar(255) DEFAULT NULL,
            `file_size` bigint(20) unsigned DEFAULT NULL, `file_etag` varchar(255) DEFAULT NULL, `mime_type` varchar(100) DEFAULT NULL,
            `season_number` int(11) NOT NULL DEFAULT 0, `episode_number` int(11) NOT NULL DEFAULT 0,
            `episode_title` varchar(255) DEFAULT NULL, `is_primary` tinyint(1) NOT NULL DEFAULT 0,
            `status` varchar(20) NOT NULL DEFAULT 'active', `created_at` datetime NOT NULL, `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `uk_media_catalog_source_hash` (`source_hash`),
            KEY `idx_media_catalog_source_media` (`media_id`,`source_kind`,`status`,`is_primary`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='WebDAV分集来源';",
        "CREATE TABLE IF NOT EXISTS `media_scan_state` (
            `id` int(11) NOT NULL AUTO_INCREMENT, `source` varchar(40) NOT NULL DEFAULT 'openlist',
            `cursor_path` varchar(1000) DEFAULT NULL, `last_run_at` datetime DEFAULT NULL, `last_message` varchar(500) DEFAULT '',
            `created_at` datetime NOT NULL, `updated_at` datetime NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uk_media_scan_source` (`source`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='OpenList扫描状态';",
        "CREATE TABLE IF NOT EXISTS `media_types` (
            `id` smallint(6) NOT NULL AUTO_INCREMENT, `name` varchar(60) NOT NULL, `slug` varchar(60) NOT NULL DEFAULT '',
            `sort_order` int(11) NOT NULL DEFAULT 0, `status` tinyint(1) NOT NULL DEFAULT 1,
            `created_at` datetime NOT NULL, `updated_at` datetime NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uk_media_type_name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='WithU影视分类';",
        "CREATE TABLE IF NOT EXISTS `media_merge_candidates` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT, `target_series_key` varchar(255) NOT NULL, `duplicate_series_key` varchar(255) NOT NULL,
            `target_name` varchar(255) NOT NULL DEFAULT '', `duplicate_name` varchar(255) NOT NULL DEFAULT '', `score` decimal(5,2) NOT NULL DEFAULT 0,
            `reason` varchar(500) NOT NULL DEFAULT '', `ai_result` varchar(20) DEFAULT NULL, `ai_explanation` varchar(500) DEFAULT NULL,
            `status` varchar(20) NOT NULL DEFAULT 'pending', `created_at` datetime NOT NULL, `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `uk_media_merge_pair` (`target_series_key`(191),`duplicate_series_key`(191))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='影视重复合并候选';",
        "CREATE TABLE IF NOT EXISTS `media_link_checks` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT, `media_id` int(11) NOT NULL, `source_id` bigint(20) DEFAULT NULL,
            `source_url` text NOT NULL, `url_hash` char(64) NOT NULL, `final_url` text DEFAULT NULL, `http_code` int(11) NOT NULL DEFAULT 0,
            `content_type` varchar(120) DEFAULT NULL, `content_length` bigint(20) DEFAULT NULL, `etag` varchar(255) DEFAULT NULL,
            `last_modified` varchar(120) DEFAULT NULL, `content_sample_hash` char(64) DEFAULT NULL, `fingerprint` char(64) DEFAULT NULL,
            `fingerprint_method` varchar(40) NOT NULL DEFAULT 'none', `comparison_confidence` varchar(20) NOT NULL DEFAULT 'none',
            `status` varchar(20) NOT NULL DEFAULT 'unknown', `message` varchar(500) NOT NULL DEFAULT '', `checked_at` datetime NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `uk_media_link_check_source_url` (`source_id`,`url_hash`),
            KEY `idx_media_link_fingerprint` (`fingerprint`,`comparison_confidence`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='WebDAV链接检测';",
        "CREATE TABLE IF NOT EXISTS `media_sources` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `source_key` char(64) NOT NULL,
            `name` varchar(120) NOT NULL, `openlist_url` varchar(1000) NOT NULL,
            `webdav_path` varchar(1000) NOT NULL DEFAULT '/', `media_root` varchar(1000) NOT NULL DEFAULT '/',
            `username` varchar(255) NOT NULL DEFAULT '', `password_ciphertext` text DEFAULT NULL,
            `enabled` tinyint(1) NOT NULL DEFAULT 1, `scan_status` varchar(20) NOT NULL DEFAULT 'idle',
            `last_scan_at` datetime DEFAULT NULL, `last_error` varchar(1000) DEFAULT NULL,
            `created_at` datetime NOT NULL, `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `uk_media_source_key` (`source_key`),
            KEY `idx_media_source_enabled` (`enabled`,`scan_status`,`updated_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='WithU WebDAV媒体来源';",
        "CREATE TABLE IF NOT EXISTS `media_resources` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `source_id` bigint(20) unsigned NOT NULL,
            `media_id` int(11) DEFAULT NULL, `source_path` varchar(1000) NOT NULL,
            `file_name` varchar(255) NOT NULL, `folder_path` varchar(1000) NOT NULL DEFAULT '/',
            `extension` varchar(20) NOT NULL DEFAULT '', `file_size` bigint(20) unsigned DEFAULT NULL,
            `file_etag` varchar(255) DEFAULT NULL, `last_modified` datetime DEFAULT NULL,
            `fingerprint` char(64) DEFAULT NULL, `fingerprint_method` varchar(40) NOT NULL DEFAULT 'metadata',
            `media_type_id` smallint(6) DEFAULT NULL, `title` varchar(255) DEFAULT NULL,
            `year` smallint(6) DEFAULT NULL, `season_number` int(11) DEFAULT NULL, `episode_number` int(11) DEFAULT NULL,
            `resolution` varchar(40) DEFAULT NULL, `video_codec` varchar(30) DEFAULT NULL, `audio_codec` varchar(30) DEFAULT NULL,
            `metadata_json` longtext DEFAULT NULL, `recognition_status` varchar(20) NOT NULL DEFAULT 'pending',
            `skip_status` varchar(20) NOT NULL DEFAULT 'pending', `skip_reason` varchar(500) DEFAULT NULL,
            `first_seen_at` datetime NOT NULL, `last_seen_at` datetime NOT NULL, `missing_since` datetime DEFAULT NULL,
            `created_at` datetime NOT NULL, `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `uk_media_resource_path` (`source_id`,`source_path`(750)),
            KEY `idx_media_resource_media` (`media_id`), KEY `idx_media_resource_scan` (`source_id`,`last_seen_at`,`missing_since`),
            KEY `idx_media_resource_status` (`recognition_status`,`skip_status`,`updated_at`),
            KEY `idx_media_resource_fingerprint` (`fingerprint`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='WebDAV物理媒体资源';",
        "CREATE TABLE IF NOT EXISTS `media_source_directories` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `source_id` bigint(20) unsigned NOT NULL,
            `directory_path` varchar(1000) NOT NULL, `fingerprint` char(64) DEFAULT NULL,
            `entry_count` int(11) NOT NULL DEFAULT 0, `scan_version` varchar(40) NOT NULL DEFAULT '',
            `last_scanned_at` datetime DEFAULT NULL, `status` varchar(20) NOT NULL DEFAULT 'ok',
            `error_message` varchar(500) DEFAULT NULL, `created_at` datetime NOT NULL, `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `uk_media_source_directory` (`source_id`,`directory_path`(750)),
            KEY `idx_media_source_directory_scan` (`source_id`,`last_scanned_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='WebDAV目录扫描快照';",
        "CREATE TABLE IF NOT EXISTS `media_tasks` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `task_key` char(64) NOT NULL,
            `task_type` varchar(30) NOT NULL, `source_id` bigint(20) unsigned DEFAULT NULL,
            `status` varchar(20) NOT NULL DEFAULT 'queued', `progress` decimal(5,2) NOT NULL DEFAULT 0,
            `scanned_count` int(11) NOT NULL DEFAULT 0, `created_count` int(11) NOT NULL DEFAULT 0,
            `updated_count` int(11) NOT NULL DEFAULT 0, `skipped_count` int(11) NOT NULL DEFAULT 0,
            `failed_count` int(11) NOT NULL DEFAULT 0, `metadata_matched_count` int(11) NOT NULL DEFAULT 0,
            `metadata_failed_count` int(11) NOT NULL DEFAULT 0, `metadata_pending_count` int(11) NOT NULL DEFAULT 0, `cursor_path` varchar(1000) DEFAULT NULL,
            `message` varchar(1000) NOT NULL DEFAULT '', `error_message` text DEFAULT NULL, `pid` int(11) DEFAULT NULL,
            `started_at` datetime DEFAULT NULL, `finished_at` datetime DEFAULT NULL,
            `created_at` datetime NOT NULL, `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `uk_media_task_key` (`task_key`),
            KEY `idx_media_task_status` (`status`,`created_at`), KEY `idx_media_task_source` (`source_id`,`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='影视资源异步任务';",
        "CREATE TABLE IF NOT EXISTS `media_resource_subtitles` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `resource_id` bigint(20) unsigned DEFAULT NULL,
            `source_id` bigint(20) unsigned NOT NULL, `subtitle_path` varchar(1000) NOT NULL,
            `file_name` varchar(255) NOT NULL, `language` varchar(40) DEFAULT NULL, `codec` varchar(40) DEFAULT NULL,
            `file_size` bigint(20) unsigned DEFAULT NULL, `file_etag` varchar(255) DEFAULT NULL,
            `status` varchar(20) NOT NULL DEFAULT 'active', `created_at` datetime NOT NULL, `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `uk_media_subtitle_path` (`source_id`,`subtitle_path`(750)),
            KEY `idx_media_subtitle_resource` (`resource_id`,`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='WebDAV字幕资源';",
        "CREATE TABLE IF NOT EXISTS `media_resource_segments` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `resource_id` bigint(20) unsigned NOT NULL,
            `segment_type` varchar(20) NOT NULL, `start_ms` bigint(20) unsigned NOT NULL DEFAULT 0,
            `end_ms` bigint(20) unsigned NOT NULL DEFAULT 0, `method` varchar(30) NOT NULL DEFAULT 'manual',
            `confidence` decimal(5,2) DEFAULT NULL, `evidence` varchar(1000) DEFAULT NULL,
            `input_fingerprint` char(64) DEFAULT NULL, `created_at` datetime NOT NULL, `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `uk_media_resource_segment` (`resource_id`,`segment_type`),
            KEY `idx_media_segment_fingerprint` (`input_fingerprint`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='影视片头片尾片段';",
    ];
    foreach ($tables as $sql) $db->query($sql);

    $columns = [
        ['media_library', 'catalog_key', "ALTER TABLE `media_library` ADD COLUMN `catalog_key` varchar(255) DEFAULT NULL AFTER `series_key`"],
        ['media_library', 'media_type_id', "ALTER TABLE `media_library` ADD COLUMN `media_type_id` smallint(6) NOT NULL DEFAULT 1 AFTER `episode_title`"],
        ['media_library', 'folder_created_at', "ALTER TABLE `media_library` ADD COLUMN `folder_created_at` datetime DEFAULT NULL AFTER `last_scanned_at`"],
        ['media_catalog_sources', 'source_kind', "ALTER TABLE `media_catalog_sources` ADD COLUMN `source_kind` varchar(20) NOT NULL DEFAULT 'webdav' AFTER `media_id`"],
        ['media_library', 'source_id', "ALTER TABLE `media_library` ADD COLUMN `source_id` bigint(20) unsigned DEFAULT NULL AFTER `id`"],
        ['media_library', 'resource_id', "ALTER TABLE `media_library` ADD COLUMN `resource_id` bigint(20) unsigned DEFAULT NULL AFTER `source_id`"],
        ['media_library', 'source_path', "ALTER TABLE `media_library` ADD COLUMN `source_path` varchar(1000) DEFAULT NULL AFTER `source_key`"],
        ['media_library', 'folder_path', "ALTER TABLE `media_library` ADD COLUMN `folder_path` varchar(1000) DEFAULT NULL AFTER `source_path`"],
        ['media_library', 'file_extension', "ALTER TABLE `media_library` ADD COLUMN `file_extension` varchar(20) DEFAULT NULL AFTER `file_name`"],
        ['media_library', 'last_modified', "ALTER TABLE `media_library` ADD COLUMN `last_modified` datetime DEFAULT NULL AFTER `file_etag`"],
        ['media_library', 'fingerprint', "ALTER TABLE `media_library` ADD COLUMN `fingerprint` char(64) DEFAULT NULL AFTER `last_modified`"],
        ['media_library', 'metadata_json', "ALTER TABLE `media_library` ADD COLUMN `metadata_json` longtext DEFAULT NULL AFTER `backdrop_url`"],
        ['media_library', 'skip_status', "ALTER TABLE `media_library` ADD COLUMN `skip_status` varchar(20) NOT NULL DEFAULT 'pending' AFTER `recognition_status`"],
        ['media_catalog_sources', 'source_id', "ALTER TABLE `media_catalog_sources` ADD COLUMN `source_id` bigint(20) unsigned DEFAULT NULL AFTER `media_id`"],
        ['media_catalog_sources', 'resource_id', "ALTER TABLE `media_catalog_sources` ADD COLUMN `resource_id` bigint(20) unsigned DEFAULT NULL AFTER `source_id`"],
        ['media_catalog_sources', 'source_path', "ALTER TABLE `media_catalog_sources` ADD COLUMN `source_path` varchar(1000) DEFAULT NULL AFTER `source_key`"],
        ['media_catalog_sources', 'folder_path', "ALTER TABLE `media_catalog_sources` ADD COLUMN `folder_path` varchar(1000) DEFAULT NULL AFTER `source_path`"],
        ['media_catalog_sources', 'fingerprint', "ALTER TABLE `media_catalog_sources` ADD COLUMN `fingerprint` char(64) DEFAULT NULL AFTER `file_etag`"],
    ];
    foreach ($columns as $column) {
        try { if (!$db->fetch("SHOW COLUMNS FROM `{$column[0]}` LIKE '{$column[1]}'")) $db->query($column[2]); } catch (Throwable $e) {}
    }
    foreach ([
        ['media_tasks', 'metadata_matched_count', "ALTER TABLE `media_tasks` ADD COLUMN `metadata_matched_count` int(11) NOT NULL DEFAULT 0 AFTER `failed_count`"],
        ['media_tasks', 'metadata_failed_count', "ALTER TABLE `media_tasks` ADD COLUMN `metadata_failed_count` int(11) NOT NULL DEFAULT 0 AFTER `metadata_matched_count`"],
        ['media_tasks', 'metadata_pending_count', "ALTER TABLE `media_tasks` ADD COLUMN `metadata_pending_count` int(11) NOT NULL DEFAULT 0 AFTER `metadata_failed_count`"],
    ] as $column) {
        try { if (!$db->fetch("SHOW COLUMNS FROM `{$column[0]}` LIKE '{$column[1]}'")) $db->query($column[2]); } catch (Throwable $e) {}
    }

    $now = date('Y-m-d H:i:s');
    foreach ([['id'=>1,'name'=>'电影','slug'=>'movie','sort_order'=>1],['id'=>2,'name'=>'电视剧','slug'=>'series','sort_order'=>2],['id'=>3,'name'=>'动漫','slug'=>'anime','sort_order'=>3],['id'=>4,'name'=>'综艺','slug'=>'show','sort_order'=>4]] as $type) {
        $db->query("INSERT INTO `media_types` (`id`,`name`,`slug`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (:id,:name,:slug,:sort_order,1,:created_at,:updated_at) ON DUPLICATE KEY UPDATE `name`=VALUES(`name`),`slug`=VALUES(`slug`),`sort_order`=VALUES(`sort_order`),`updated_at`=VALUES(`updated_at`)", $type + ['created_at'=>$now,'updated_at'=>$now]);
    }
    $db->query("INSERT INTO `media_scan_state` (`source`,`cursor_path`,`last_run_at`,`last_message`,`created_at`,`updated_at`) VALUES ('openlist',NULL,NULL,'待扫描',:created_at,:updated_at) ON DUPLICATE KEY UPDATE `updated_at`=VALUES(`updated_at`)", ['created_at'=>$now,'updated_at'=>$now]);
    @file_put_contents($markerPath, $schemaVersion, LOCK_EX);
    if ($lock) { @flock($lock, LOCK_UN); @fclose($lock); }
}
