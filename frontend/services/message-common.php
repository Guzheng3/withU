<?php
/**
 * 留言数据源公共助手：留言统一存数据库 messages 表（map-all.json 的 messages 键仅为迁移前的冻结快照）。
 * 写入口 message.php、读入口 message-list.php、地图接口 map-api.php 共用此处的建表检查与行→前端结构映射。
 */

if (!defined('WITHU_MESSAGE_COMMON')) {
    define('WITHU_MESSAGE_COMMON', true);

    /**
     * 获取数据库连接；本地环境未安装/连接失败时返回 null（调用方自行降级）。
     */
    function withu_message_db()
    {
        static $db = null;
        static $tried = false;
        if ($tried) return $db;
        $tried = true;
        try {
            $rootPath = dirname(__DIR__, 2) . '/backend/app';
            if (!is_file($rootPath . '/config/database.php') || !is_file($rootPath . '/.installed')) {
                return null;
            }
            require_once $rootPath . '/config/config.php';
            require_once $rootPath . '/core/Database.php';
            $db = Database::getInstance();
        } catch (Throwable $e) {
            $db = null;
        }
        return $db;
    }

    /**
     * 幂等补齐留言扩展字段（回复线程 / 坐标 / 天气环境 / 点赞 / 类型徽章 / 富文本）。
     * 与后台 admin/messages.php 的字段迁移写法保持一致：已存在时静默忽略。
     */
    function withu_message_ensure_schema($db): void
    {
        if (!$db) return;
        $columns = [
            "ALTER TABLE `messages` ADD COLUMN `parent_id` int(11) DEFAULT NULL COMMENT '父留言id' AFTER `user_id`",
            "ALTER TABLE `messages` ADD COLUMN `reply_to_id` int(11) DEFAULT NULL COMMENT '被回复留言id' AFTER `parent_id`",
            "ALTER TABLE `messages` ADD COLUMN `lng` double DEFAULT NULL COMMENT '经度' AFTER `location`",
            "ALTER TABLE `messages` ADD COLUMN `lat` double DEFAULT NULL COMMENT '纬度' AFTER `lng`",
            "ALTER TABLE `messages` ADD COLUMN `weather` varchar(100) DEFAULT NULL COMMENT '天气' AFTER `lat`",
            "ALTER TABLE `messages` ADD COLUMN `weather_icon` varchar(100) DEFAULT NULL COMMENT '天气图标' AFTER `weather`",
            "ALTER TABLE `messages` ADD COLUMN `os` varchar(100) DEFAULT NULL COMMENT '操作系统' AFTER `weather_icon`",
            "ALTER TABLE `messages` ADD COLUMN `browser` varchar(100) DEFAULT NULL COMMENT '浏览器' AFTER `os`",
            "ALTER TABLE `messages` ADD COLUMN `like_count` int(11) NOT NULL DEFAULT 0 COMMENT '点赞数' AFTER `browser`",
            "ALTER TABLE `messages` ADD COLUMN `msg_type` varchar(20) NOT NULL DEFAULT '' COMMENT '留言类型' AFTER `like_count`",
            "ALTER TABLE `messages` ADD COLUMN `old_type` varchar(20) NOT NULL DEFAULT '' COMMENT '旧类型标记' AFTER `msg_type`",
            "ALTER TABLE `messages` ADD COLUMN `badge` text DEFAULT NULL COMMENT '徽章JSON' AFTER `old_type`",
            "ALTER TABLE `messages` ADD COLUMN `content_html` mediumtext DEFAULT NULL COMMENT '富文本内容' AFTER `content`",
        ];
        foreach ($columns as $sql) {
            try {
                $db->query($sql);
            } catch (Throwable $e) {
                // 字段已存在时忽略
            }
        }
    }

    /**
     * 数据库留言行 → 前端结构（message-list / map-api 共用）。
     * 输出为历史两代字段的并集：coords/lng/lat、time/timestamp/timeStr、text/textHtml、badge/type 等全部提供。
     */
    function withu_message_row_to_item(array $row): array
    {
        $qq = (string)($row['guest_qq'] ?? '');
        $created = (string)($row['created_at'] ?? '');
        $ts = $created !== '' ? (strtotime($created) ?: 0) : 0;
        $lng = isset($row['lng']) && $row['lng'] !== null ? (float)$row['lng'] : null;
        $lat = isset($row['lat']) && $row['lat'] !== null ? (float)$row['lat'] : null;
        $badge = null;
        if (!empty($row['badge'])) {
            $decoded = json_decode((string)$row['badge'], true);
            if (is_array($decoded)) $badge = $decoded;
        }
        $content = (string)($row['content'] ?? '');
        $contentHtml = (string)($row['content_html'] ?? '');
        return [
            'id'          => (int)$row['id'],
            'parentId'    => isset($row['parent_id']) && $row['parent_id'] !== null ? (int)$row['parent_id'] : null,
            'name'        => (string)($row['guest_nickname'] ?? '匿名'),
            'qq'          => $qq !== '' ? $qq : 'anon',
            'qq_hash'     => ($qq !== '' && $qq !== 'anon') ? md5($qq) : 'anon',
            'avatar'      => (string)($row['guest_avatar'] ?? ''),
            'text'        => $content,
            'textHtml'    => $contentHtml !== '' ? $contentHtml : $content,
            'city'        => (string)($row['location'] ?? '中国'),
            'coords'      => ($lng !== null && $lat !== null) ? [$lng, $lat] : null,
            'lng'         => $lng,
            'lat'         => $lat,
            'os'          => (string)($row['os'] ?? ''),
            'browser'     => (string)($row['browser'] ?? ''),
            'weather'     => (string)($row['weather'] ?? ''),
            'weather_icon' => (string)($row['weather_icon'] ?? ''),
            'time'        => $ts,
            'timestamp'   => $ts,
            'timeStr'     => $created,
            'type'        => (string)($row['msg_type'] ?? ''),
            'oldType'     => (string)($row['old_type'] ?? ''),
            'badge'       => $badge,
            'like_count'  => (int)($row['like_count'] ?? 0),
            'replyCount'  => 0, // 调用方按需填充
            'reply_to_id' => isset($row['reply_to_id']) && $row['reply_to_id'] !== null ? (int)$row['reply_to_id'] : null,
        ];
    }

    /**
     * 从数据库读取全部已发布留言（前端结构），附带每条的回复数；库不可用时返回 null。
     */
    function withu_message_fetch_all(): ?array
    {
        $db = withu_message_db();
        if (!$db) return null;
        try {
            withu_message_ensure_schema($db);
            $rows = $db->fetchAll(
                "SELECT * FROM messages WHERE status = 'published' ORDER BY id ASC"
            );
        } catch (Throwable $e) {
            return null;
        }
        $items = array_map('withu_message_row_to_item', $rows);
        // 回复数：按 parent_id 统计
        $counts = [];
        foreach ($items as $item) {
            if ($item['parentId'] !== null) {
                $counts[$item['parentId']] = ($counts[$item['parentId']] ?? 0) + 1;
            }
        }
        foreach ($items as &$item) {
            $item['replyCount'] = (int)($counts[$item['id']] ?? 0);
        }
        unset($item);
        return $items;
    }

    /**
     * 降级路径：数据库不可用时回读 map-all.json 的冻结快照，保持站点可用。
     */
    function withu_message_json_fallback(): array
    {
        $file = __DIR__ . '/map-all.json';
        if (!is_file($file)) return [];
        $data = json_decode((string)file_get_contents($file), true);
        $msgs = is_array($data) ? ($data['messages'] ?? []) : [];
        foreach ($msgs as &$x) {
            $x['replyCount'] = (int)($x['replyCount'] ?? 0);
        }
        unset($x);
        return $msgs;
    }
}
