-- 站点数据库结构（安装脚本使用）

-- 用户表
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL COMMENT '用户名',
  `password` varchar(255) NOT NULL COMMENT '密码（加密后）',
  `nickname` varchar(50) NOT NULL COMMENT '昵称',
  `role` enum('user1','user2','admin') NOT NULL DEFAULT 'user1' COMMENT '角色',
  `qq` varchar(32) DEFAULT NULL COMMENT 'QQ 号',
  `avatar` varchar(255) DEFAULT NULL COMMENT '头像地址',
  `avatar_source` varchar(20) DEFAULT 'upload' COMMENT '头像来源',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active' COMMENT '状态',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表';

-- 文章表
CREATE TABLE IF NOT EXISTS `articles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '作者用户ID',
  `title` varchar(200) NOT NULL COMMENT '标题',
  `content` text NOT NULL COMMENT '内容',
  `type` enum('article','diary') NOT NULL DEFAULT 'article' COMMENT '类型',
  `is_encrypted` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否加密',
  `tags` varchar(255) DEFAULT NULL COMMENT '标签，逗号分隔',
  `comments_enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否允许评论（1=允许，0=关闭）',
  `views` int(11) NOT NULL DEFAULT 0 COMMENT '浏览量',
  `status` enum('published','draft','deleted') NOT NULL DEFAULT 'published' COMMENT '状态',
  `edit_mode` enum('full','blocks','chat') NOT NULL DEFAULT 'full' COMMENT '编辑模式：full=整篇富文本，blocks=块级对话，chat=聊天创作',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文章表';

-- 相册表
CREATE TABLE IF NOT EXISTS `albums` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '创建用户ID',
  `name` varchar(100) NOT NULL COMMENT '相册名称',
  `description` text DEFAULT NULL COMMENT '描述',
  `cover_image` varchar(255) DEFAULT NULL COMMENT '封面图片',
  `is_encrypted` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否加密',
  `keep_original_quality` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否保留原始画质（0=默认压缩，1=尽量不压缩主图）',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='相册表';

-- 相册图片表
CREATE TABLE IF NOT EXISTS `album_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `album_id` int(11) NOT NULL COMMENT '相册ID',
  `image_path` varchar(255) NOT NULL COMMENT '图片路径',
  `thumbnail_path` varchar(255) DEFAULT NULL COMMENT '缩略图路径',
  `is_optimized` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已按当前规则压缩',
  `skip_optimize` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否永久跳过主图压缩',
  `description` varchar(255) DEFAULT NULL COMMENT '描述',
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '排序值',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `album_id` (`album_id`),
  KEY `sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='相册图片表';

-- 相册视频表
CREATE TABLE IF NOT EXISTS `album_videos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `album_id` int(11) NOT NULL COMMENT '相册ID',
  `video_path` varchar(255) NOT NULL COMMENT '视频路径',
  `poster_path` varchar(255) DEFAULT NULL COMMENT '封面图片路径',
  `description` varchar(255) DEFAULT NULL COMMENT '视频描述',
  `uploader_id` int(11) DEFAULT NULL COMMENT '上传用户ID',
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '排序值',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `album_id` (`album_id`),
  KEY `sort_order` (`sort_order`),
  KEY `uploader_id` (`uploader_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='相册视频表';

-- 事件表（精简版本，无颜色字段）
CREATE TABLE IF NOT EXISTS `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '创建用户ID',
  `title` varchar(200) NOT NULL COMMENT '事件标题',
  `description` text DEFAULT NULL COMMENT '事件描述',
  `event_date` date NOT NULL COMMENT '事件日期',
  `icon` varchar(50) DEFAULT 'heart' COMMENT '图标',
  `is_important` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否重要',
  `is_recurring` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否每年重复',
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '排序值，越小越靠前',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `event_date` (`event_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='事件表';

-- 留言表
CREATE TABLE IF NOT EXISTS `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '主人用户ID',
  `guest_nickname` varchar(100) DEFAULT NULL COMMENT '访客昵称',
  `guest_avatar` varchar(255) DEFAULT NULL COMMENT '访客头像',
  `guest_qq` varchar(20) DEFAULT NULL COMMENT '访客QQ',
  `ip` varchar(45) DEFAULT NULL COMMENT '留言IP',
  `location` varchar(255) DEFAULT NULL COMMENT 'IP归属地',
  `content` text NOT NULL COMMENT '留言内容',
  `is_public` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否公开',
  `status` enum('published','deleted') NOT NULL DEFAULT 'published' COMMENT '状态',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='留言表';

-- 评论表
CREATE TABLE IF NOT EXISTS `comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `article_id` int(11) DEFAULT NULL COMMENT '文章ID',
  `album_id` int(11) DEFAULT NULL COMMENT '相册ID',
  `event_id` int(11) DEFAULT NULL COMMENT '事件ID',
  `user_id` int(11) NOT NULL COMMENT '评论用户ID',
  `guest_nickname` varchar(100) DEFAULT NULL COMMENT '访客昵称',
  `guest_avatar` varchar(255) DEFAULT NULL COMMENT '访客头像',
  `guest_qq` varchar(20) DEFAULT NULL COMMENT '访客QQ',
  `ip` varchar(45) DEFAULT NULL COMMENT '评论IP',
  `location` varchar(255) DEFAULT NULL COMMENT 'IP归属地',
  `content` text NOT NULL COMMENT '评论内容',
  `parent_id` int(11) DEFAULT NULL COMMENT '父评论ID',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `article_id` (`article_id`),
  KEY `album_id` (`album_id`),
  KEY `event_id` (`event_id`),
  KEY `user_id` (`user_id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='评论表';

-- 登录尝试记录表（防爆破）
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_username_ip_time` (`username`,`ip`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='登录尝试记录';

-- 评论尝试记录表（评论节流）
CREATE TABLE IF NOT EXISTS `comment_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ip_time` (`ip`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='评论尝试记录';

-- 评论IP黑名单表
CREATE TABLE IF NOT EXISTS `comment_ip_blacklist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL COMMENT 'IP地址',
  `reason` varchar(255) DEFAULT NULL COMMENT '拉黑原因',
  `expires_at` datetime DEFAULT NULL COMMENT '过期时间，NULL 表示永久',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='评论IP黑名单';

-- 留言尝试记录表（留言节流）
CREATE TABLE IF NOT EXISTS `message_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ip_time` (`ip`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='留言尝试记录';

-- 点赞表
CREATE TABLE IF NOT EXISTS `likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `article_id` int(11) DEFAULT NULL COMMENT '文章ID',
  `album_id` int(11) DEFAULT NULL COMMENT '相册ID',
  `event_id` int(11) DEFAULT NULL COMMENT '事件ID',
  `user_id` int(11) NOT NULL COMMENT '点赞用户ID',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_like` (`user_id`,`article_id`,`album_id`,`event_id`),
  KEY `article_id` (`article_id`),
  KEY `album_id` (`album_id`),
  KEY `event_id` (`event_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='点赞表';

-- 文章权限表（是否允许另一半编辑）
CREATE TABLE IF NOT EXISTS `article_permissions` (
  `article_id` int(11) NOT NULL COMMENT '文章ID',
  `allow_partner_edit` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否允许另一半编辑',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文章权限表';

-- 文章编辑记录表（用于共创判断）
CREATE TABLE IF NOT EXISTS `article_edit_logs` (
  `article_id` int(11) NOT NULL COMMENT '文章ID',
  `user_id` int(11) NOT NULL COMMENT '编辑用户ID',
  `last_edited_at` datetime NOT NULL COMMENT '最后编辑时间',
  PRIMARY KEY (`article_id`, `user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文章编辑记录';

-- 文章贡献统计（记录双方各自贡献字数）
CREATE TABLE IF NOT EXISTS `article_contributions` (
  `article_id` int(11) NOT NULL COMMENT '文章ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `contributed_chars` int(11) NOT NULL DEFAULT 0 COMMENT '累计贡献字数',
  `last_updated_at` datetime NOT NULL COMMENT '最后更新时间',
  PRIMARY KEY (`article_id`, `user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文章贡献统计';

-- 文章文字归属段落表（逐字级统计）
CREATE TABLE IF NOT EXISTS `article_segments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `article_id` int(11) NOT NULL COMMENT '文章ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `start_offset` int(11) NOT NULL COMMENT '起始字符位置（从0开始）',
  `length` int(11) NOT NULL COMMENT '该段字符长度',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime NOT NULL COMMENT '最后更新时间',
  PRIMARY KEY (`id`),
  KEY `article_id` (`article_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文章文字归属段落';

-- 文章块级内容归属表（按块记录 HTML 归属）
CREATE TABLE IF NOT EXISTS `article_blocks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `article_id` int(11) NOT NULL COMMENT '文章ID',
  `block_index` int(11) NOT NULL COMMENT '块索引（从0开始）',
  `user_id` int(11) NOT NULL COMMENT '作者用户ID',
  `speaker` enum('male','female','system') DEFAULT NULL COMMENT '说话人：男主/女主/系统',
  `html` mediumtext NOT NULL COMMENT '该块的 HTML 内容',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime NOT NULL COMMENT '最后更新时间',
  PRIMARY KEY (`id`),
  KEY `article_id` (`article_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文章块级内容归属';

-- 相册权限表（是否允许另一半编辑/上传）
CREATE TABLE IF NOT EXISTS `album_permissions` (
  `album_id` int(11) NOT NULL COMMENT '相册ID',
  `allow_partner_edit` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否允许另一半编辑与上传',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`album_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='相册权限表';

-- 相册图片上传者映射表
CREATE TABLE IF NOT EXISTS `album_image_uploads` (
  `image_id` int(11) NOT NULL COMMENT '图片ID',
  `user_id` int(11) NOT NULL COMMENT '上传用户ID',
  `created_at` datetime NOT NULL COMMENT '记录创建时间',
  PRIMARY KEY (`image_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='相册图片上传者映射';

-- 系统设置表
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL COMMENT '键名',
  `value` text DEFAULT NULL COMMENT '键值',
  `description` varchar(255) DEFAULT NULL COMMENT '描述',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统设置表';

-- 插入默认设置
INSERT IGNORE INTO `settings` (`key`, `value`, `description`) VALUES
('love_date', '', '恋爱开始日期'),
('site_title', 'I Love Day', '网站标题'),
('site_description', '从相遇到相守，把每一个平凡的日子都写成只属于我们的浪漫。', '网站描述'),
('video_upload_ignore_site_limit', '0', '视频上传是否忽略站点单文件大小限制（仅受服务器限制）'),
('turnstile_enabled', '0', '是否启用 Cloudflare Turnstile 登录验证'),
('turnstile_site_key', '', 'Cloudflare Turnstile Site Key'),
('turnstile_secret_key', '', 'Cloudflare Turnstile Secret Key'),
('front_animation_enabled', '1', '前台花瓣与转场动效开关'),
('backend_animation_enabled', '0', '后台动效开关'),
('ai_moderation_enabled', '0', 'AI内容审核开关'),
('ai_api_endpoint', '', 'AI兼容接口地址'),
('ai_api_key', '', 'AI接口密钥'),
('ai_model', 'gpt-4o-mini', 'AI模型名称'),
('openlist_webdav_url', '', 'OpenList WebDAV地址'),
('openlist_webdav_username', '', 'OpenList WebDAV用户名'),
('openlist_webdav_password', '', 'OpenList WebDAV密码'),
('openlist_root_path', '/', 'OpenList媒体根目录'),
('theme_preset', 'pastel-couple', '主站与后台主题预设'),
('theme_mode', 'auto', '主题显示模式：auto/light/dark'),
('theme_custom_primary', '', '自定义主题主色'),
('theme_custom_secondary', '', '自定义主题辅助色'),
('theme_custom_accent', '', '自定义主题强调色'),
('watch_poll_interval_ms', '500', '一起看状态轮询间隔（毫秒）'),
('watch_sync_threshold_ms', '1000', '一起看同步校正阈值（毫秒）'),
('watch_presence_timeout_sec', '8', '一起看在线判定时间（秒）'),
('watch_heartbeat_interval_ms', '2500', '一起看心跳间隔（毫秒）'),
('watch_autoplay_enabled', '1', '一起看与个人观看默认自动播放'),
('player_default_speed', '1', '播放器默认倍速'),
('player_auto_next_enabled', '1', '播放器结束后自动播放下一集');

-- withU 扩展表：设备、媒体库、同步房间、观影历史和审核记录
CREATE TABLE IF NOT EXISTS `couple_invites` (
  `id` int(11) NOT NULL AUTO_INCREMENT, `inviter_id` int(11) NOT NULL,
  `invite_code` varchar(32) NOT NULL, `invite_token_hash` varchar(64) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending', `invitee_id` int(11) DEFAULT NULL,
  `expires_at` datetime NOT NULL, `accepted_at` datetime DEFAULT NULL, `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_invite_code` (`invite_code`), UNIQUE KEY `uk_invite_token` (`invite_token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `trusted_devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT, `user_id` int(11) NOT NULL,
  `device_token_hash` varchar(64) NOT NULL, `device_name` varchar(120) DEFAULT NULL,
  `last_ip` varchar(45) DEFAULT NULL, `last_seen_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL, `revoked_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_device_token` (`device_token_hash`), KEY `idx_device_user` (`user_id`,`revoked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `media_library` (
  `id` int(11) NOT NULL AUTO_INCREMENT, `source_id` bigint(20) unsigned DEFAULT NULL, `resource_id` bigint(20) unsigned DEFAULT NULL,
  `source_key` varchar(500) NOT NULL, `source_path` varchar(1000) DEFAULT NULL, `folder_path` varchar(1000) DEFAULT NULL,
  `source_url` varchar(1000) DEFAULT NULL, `direct_url` text DEFAULT NULL, `file_name` varchar(255) NOT NULL,
  `file_extension` varchar(20) DEFAULT NULL,
  `series_key` varchar(255) DEFAULT NULL, `catalog_key` varchar(255) DEFAULT NULL, `series_name` varchar(255) DEFAULT NULL,
  `season_number` int(11) DEFAULT NULL, `episode_number` int(11) DEFAULT NULL,
  `episode_title` varchar(255) DEFAULT NULL, `media_type_id` smallint(6) NOT NULL DEFAULT 1, `video_codec` varchar(30) DEFAULT NULL,
  `audio_codec` varchar(30) DEFAULT NULL, `browser_playback` varchar(20) NOT NULL DEFAULT 'unknown',
  `file_size` bigint(20) unsigned DEFAULT NULL, `file_md5` varchar(64) DEFAULT NULL, `file_etag` varchar(255) DEFAULT NULL,
  `last_modified` datetime DEFAULT NULL, `fingerprint` char(64) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL, `duration_ms` bigint(20) unsigned DEFAULT NULL, `width` int(11) DEFAULT NULL,
  `height` int(11) DEFAULT NULL, `resolution` varchar(40) DEFAULT NULL, `tags` varchar(500) DEFAULT NULL,
  `douban_id` varchar(40) DEFAULT NULL, `rating` decimal(3,1) DEFAULT NULL, `cast_names` text DEFAULT NULL,
  `summary` text DEFAULT NULL, `cover_url` text DEFAULT NULL, `backdrop_url` text DEFAULT NULL, `metadata_json` longtext DEFAULT NULL,
  `intro_start_ms` bigint(20) unsigned DEFAULT NULL,
  `intro_end_ms` bigint(20) unsigned DEFAULT NULL, `recognition_status` varchar(20) NOT NULL DEFAULT 'pending', `skip_status` varchar(20) NOT NULL DEFAULT 'pending',
  `recognition_source` varchar(30) DEFAULT NULL, `recognized_at` datetime DEFAULT NULL,
  `last_scanned_at` datetime NOT NULL, `folder_created_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL, `added_at` datetime GENERATED ALWAYS AS (COALESCE(`folder_created_at`, `created_at`)) STORED, `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_media_source` (`source_key`), KEY `idx_media_source_id` (`source_id`,`resource_id`), KEY `idx_media_md5` (`file_md5`), KEY `idx_media_catalog_key` (`catalog_key`),
  KEY `idx_media_status` (`recognition_status`),
  KEY `idx_media_type` (`media_type_id`,`recognition_status`,`updated_at`), KEY `idx_media_status_added` (`recognition_status`,`added_at`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `media_catalog_sources` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT, `media_id` int(11) NOT NULL, `source_id` bigint(20) unsigned DEFAULT NULL, `resource_id` bigint(20) unsigned DEFAULT NULL,
  `source_kind` varchar(20) NOT NULL DEFAULT 'webdav', `source_key` varchar(500) NOT NULL,
  `source_path` varchar(1000) DEFAULT NULL, `folder_path` varchar(1000) DEFAULT NULL,
  `source_hash` char(64) NOT NULL, `source_url` text DEFAULT NULL, `file_name` varchar(255) DEFAULT NULL,
  `file_size` bigint(20) unsigned DEFAULT NULL, `file_etag` varchar(255) DEFAULT NULL, `fingerprint` char(64) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `season_number` int(11) NOT NULL DEFAULT 0, `episode_number` int(11) NOT NULL DEFAULT 0,
  `episode_title` varchar(255) DEFAULT NULL, `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'active', `created_at` datetime NOT NULL, `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_media_catalog_source_hash` (`source_hash`),
  KEY `idx_media_catalog_source_media` (`media_id`,`source_kind`,`status`,`is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `media_types` (
  `id` smallint(6) NOT NULL AUTO_INCREMENT, `name` varchar(60) NOT NULL,
  `slug` varchar(60) NOT NULL DEFAULT '', `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1, `created_at` datetime NOT NULL, `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_media_type_name` (`name`), KEY `idx_media_type_status_sort` (`status`,`sort_order`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `media_merge_candidates` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT, `target_series_key` varchar(255) NOT NULL,
  `duplicate_series_key` varchar(255) NOT NULL, `target_name` varchar(255) NOT NULL DEFAULT '',
  `duplicate_name` varchar(255) NOT NULL DEFAULT '', `score` decimal(5,2) NOT NULL DEFAULT 0,
  `reason` varchar(500) NOT NULL DEFAULT '', `ai_result` varchar(20) DEFAULT NULL,
  `ai_explanation` varchar(500) DEFAULT NULL, `status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL, `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_media_merge_pair` (`target_series_key`(191),`duplicate_series_key`(191)),
  KEY `idx_media_merge_status` (`status`,`score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `media_link_checks` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT, `media_id` int(11) NOT NULL, `source_id` bigint(20) DEFAULT NULL, `source_url` text NOT NULL,
  `url_hash` char(64) NOT NULL, `final_url` text DEFAULT NULL, `http_code` int(11) NOT NULL DEFAULT 0,
  `content_type` varchar(120) DEFAULT NULL, `content_length` bigint(20) DEFAULT NULL,
  `etag` varchar(255) DEFAULT NULL, `last_modified` varchar(120) DEFAULT NULL, `content_sample_hash` char(64) DEFAULT NULL,
  `fingerprint` char(64) DEFAULT NULL, `fingerprint_method` varchar(40) NOT NULL DEFAULT 'none', `comparison_confidence` varchar(20) NOT NULL DEFAULT 'none',
  `status` varchar(20) NOT NULL DEFAULT 'unknown', `message` varchar(500) NOT NULL DEFAULT '',
  `checked_at` datetime NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uk_media_link_check_source_url` (`source_id`,`url_hash`),
  KEY `idx_media_link_fingerprint` (`fingerprint`,`comparison_confidence`), KEY `idx_media_link_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `media_sources` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `source_key` char(64) NOT NULL,
  `name` varchar(120) NOT NULL, `openlist_url` varchar(1000) NOT NULL,
  `webdav_path` varchar(1000) NOT NULL DEFAULT '/', `media_root` varchar(1000) NOT NULL DEFAULT '/',
  `username` varchar(255) NOT NULL DEFAULT '', `password_ciphertext` text DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1, `scan_status` varchar(20) NOT NULL DEFAULT 'idle',
  `last_scan_at` datetime DEFAULT NULL, `last_error` varchar(1000) DEFAULT NULL,
  `created_at` datetime NOT NULL, `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_media_source_key` (`source_key`),
  KEY `idx_media_source_enabled` (`enabled`,`scan_status`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `media_resources` (
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
  KEY `idx_media_resource_status` (`recognition_status`,`skip_status`,`updated_at`), KEY `idx_media_resource_fingerprint` (`fingerprint`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `media_source_directories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `source_id` bigint(20) unsigned NOT NULL,
  `directory_path` varchar(1000) NOT NULL, `fingerprint` char(64) DEFAULT NULL,
  `entry_count` int(11) NOT NULL DEFAULT 0, `scan_version` varchar(40) NOT NULL DEFAULT '',
  `last_scanned_at` datetime DEFAULT NULL, `status` varchar(20) NOT NULL DEFAULT 'ok',
  `error_message` varchar(500) DEFAULT NULL, `created_at` datetime NOT NULL, `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_media_source_directory` (`source_id`,`directory_path`(750)),
  KEY `idx_media_source_directory_scan` (`source_id`,`last_scanned_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `media_tasks` (
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
  PRIMARY KEY (`id`), UNIQUE KEY `uk_media_task_key` (`task_key`), KEY `idx_media_task_status` (`status`,`created_at`),
  KEY `idx_media_task_source` (`source_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `media_resource_subtitles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `resource_id` bigint(20) unsigned DEFAULT NULL,
  `source_id` bigint(20) unsigned NOT NULL, `subtitle_path` varchar(1000) NOT NULL,
  `file_name` varchar(255) NOT NULL, `language` varchar(40) DEFAULT NULL, `codec` varchar(40) DEFAULT NULL,
  `file_size` bigint(20) unsigned DEFAULT NULL, `file_etag` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active', `created_at` datetime NOT NULL, `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_media_subtitle_path` (`source_id`,`subtitle_path`(750)), KEY `idx_media_subtitle_resource` (`resource_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `media_resource_segments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `resource_id` bigint(20) unsigned NOT NULL,
  `segment_type` varchar(20) NOT NULL, `start_ms` bigint(20) unsigned NOT NULL DEFAULT 0, `end_ms` bigint(20) unsigned NOT NULL DEFAULT 0,
  `method` varchar(30) NOT NULL DEFAULT 'manual', `confidence` decimal(5,2) DEFAULT NULL, `evidence` varchar(1000) DEFAULT NULL,
  `input_fingerprint` char(64) DEFAULT NULL, `created_at` datetime NOT NULL, `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_media_resource_segment` (`resource_id`,`segment_type`), KEY `idx_media_segment_fingerprint` (`input_fingerprint`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `watch_rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT, `room_code` varchar(32) NOT NULL, `media_id` int(11) NOT NULL,
  `host_user_id` int(11) NOT NULL, `playback_state` varchar(20) NOT NULL DEFAULT 'paused',
  `current_position_ms` bigint(20) unsigned NOT NULL DEFAULT 0, `speed` decimal(3,2) NOT NULL DEFAULT 1.00,
  `last_sync_at` datetime NOT NULL, `last_sync_unix_ms` bigint(20) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL, `ended_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_room_code` (`room_code`), KEY `idx_room_media` (`media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `watch_room_members` (
  `room_id` int(11) NOT NULL, `user_id` int(11) NOT NULL, `joined_at` datetime NOT NULL,
  `last_seen_at` datetime NOT NULL, `left_at` datetime DEFAULT NULL,
  PRIMARY KEY (`room_id`,`user_id`), KEY `idx_member_last_seen` (`user_id`,`last_seen_at`), KEY `idx_member_room_presence` (`room_id`,`left_at`,`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `watch_events` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT, `room_id` int(11) NOT NULL, `user_id` int(11) NOT NULL,
  `event_type` varchar(30) NOT NULL, `position_ms` bigint(20) unsigned NOT NULL DEFAULT 0,
  `speed` decimal(3,2) NOT NULL DEFAULT 1.00, `payload` text DEFAULT NULL, `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`), KEY `idx_watch_event_room` (`room_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `watch_history` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT, `media_id` int(11) NOT NULL, `room_id` int(11) DEFAULT NULL,
  `started_at` datetime NOT NULL, `ended_at` datetime DEFAULT NULL, `watch_duration_ms` bigint(20) unsigned NOT NULL DEFAULT 0,
  `solo_duration_ms` bigint(20) unsigned NOT NULL DEFAULT 0, `together_duration_ms` bigint(20) unsigned NOT NULL DEFAULT 0,
  `initiated_by` int(11) DEFAULT NULL, `last_position_ms` bigint(20) unsigned NOT NULL DEFAULT 0,
  `participants` text DEFAULT NULL, `created_at` datetime NOT NULL, `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`), KEY `idx_history_media` (`media_id`,`started_at`), KEY `idx_history_updated` (`updated_at`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `moderation_events` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT, `target_type` varchar(30) NOT NULL, `target_id` bigint(20) DEFAULT NULL,
  `content` text DEFAULT NULL, `rule_result` varchar(20) NOT NULL DEFAULT 'allow', `ai_result` varchar(20) DEFAULT NULL,
  `risk_score` decimal(5,2) DEFAULT NULL, `reasons` text DEFAULT NULL, `review_status` varchar(20) NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL, `review_note` varchar(500) DEFAULT NULL, `created_at` datetime NOT NULL, `reviewed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`), KEY `idx_moderation_status` (`review_status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `weather_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT, `cache_key` varchar(120) NOT NULL,
  `latitude` decimal(10,7) NOT NULL, `longitude` decimal(10,7) NOT NULL,
  `payload` text NOT NULL, `expires_at` datetime NOT NULL, `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_weather_cache_key` (`cache_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `travel_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT, `creator_id` int(11) NOT NULL,
  `destination` varchar(255) NOT NULL, `start_date` date DEFAULT NULL, `end_date` date DEFAULT NULL,
  `prompt` text DEFAULT NULL, `plan_payload` text NOT NULL, `ai_source` varchar(30) DEFAULT NULL,
  `created_at` datetime NOT NULL, `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`), KEY `idx_travel_creator` (`creator_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `content_likes` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT, `actor_key` varchar(64) NOT NULL,
  `target_type` varchar(20) NOT NULL, `target_id` int(11) NOT NULL, `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_like_actor_target` (`actor_key`,`target_type`,`target_id`), KEY `idx_like_target` (`target_type`,`target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
