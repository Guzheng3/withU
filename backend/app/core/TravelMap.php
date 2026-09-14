<?php
/** WithU 地图与情侣足迹数据层。所有表均幂等创建，兼容已部署旧库。 */

if (!function_exists('withu_travel_map_ensure_schema')) {
    function withu_travel_map_ensure_schema($db): void
    {
        static $done = false;
        if ($done) return;
        $db->query("CREATE TABLE IF NOT EXISTS `couple_positions` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `location_name` varchar(160) NOT NULL DEFAULT '',
            `latitude` decimal(10,7) NOT NULL,
            `longitude` decimal(10,7) NOT NULL,
            `visibility` enum('private','couple','public') NOT NULL DEFAULT 'couple',
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `uk_couple_position_user` (`user_id`), KEY `idx_position_updated` (`updated_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->query("CREATE TABLE IF NOT EXISTS `travel_locations` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `creator_id` int(11) NOT NULL,
            `title` varchar(160) NOT NULL,
            `location_name` varchar(255) NOT NULL DEFAULT '',
            `description` text DEFAULT NULL,
            `latitude` decimal(10,7) NOT NULL,
            `longitude` decimal(10,7) NOT NULL,
            `visit_date` date DEFAULT NULL,
            `is_favorite` tinyint(1) NOT NULL DEFAULT 0,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`), KEY `idx_location_date` (`visit_date`), KEY `idx_location_creator` (`creator_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->query("CREATE TABLE IF NOT EXISTS `travel_routes` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `creator_id` int(11) NOT NULL,
            `title` varchar(160) NOT NULL,
            `description` text DEFAULT NULL,
            `start_name` varchar(160) NOT NULL DEFAULT '',
            `end_name` varchar(160) NOT NULL DEFAULT '',
            `distance_km` decimal(10,2) DEFAULT NULL,
            `points_json` mediumtext NOT NULL,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`), KEY `idx_route_creator` (`creator_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $done = true;
    }
}

if (!function_exists('withu_travel_map_valid_point')) {
    function withu_travel_map_valid_point($lat, $lng): bool
    {
        return is_numeric($lat) && is_numeric($lng) && (float)$lat >= -90 && (float)$lat <= 90 && (float)$lng >= -180 && (float)$lng <= 180;
    }
}

if (!function_exists('withu_travel_map_float')) {
    function withu_travel_map_float($value, int $precision = 7): float
    {
        return round((float)$value, $precision);
    }
}

if (!function_exists('withu_travel_map_points')) {
    function withu_travel_map_points($raw): array
    {
        if (is_string($raw)) $raw = json_decode($raw, true);
        if (!is_array($raw)) return [];
        $points = [];
        foreach ($raw as $point) {
            if (!is_array($point) || !withu_travel_map_valid_point($point['lat'] ?? null, $point['lng'] ?? null)) continue;
            $points[] = ['lat' => withu_travel_map_float($point['lat']), 'lng' => withu_travel_map_float($point['lng'])];
        }
        return array_slice($points, 0, 200);
    }
}

if (!function_exists('withu_travel_map_payload')) {
    function withu_travel_map_payload($db, array $user): array
    {
        withu_travel_map_ensure_schema($db);
        $positions = $db->fetchAll("SELECT p.*, u.nickname, u.avatar FROM couple_positions p LEFT JOIN users u ON u.id=p.user_id WHERE p.visibility IN ('couple','public') ORDER BY p.updated_at DESC");
        $locations = $db->fetchAll("SELECT l.*, u.nickname, u.avatar FROM travel_locations l LEFT JOIN users u ON u.id=l.creator_id ORDER BY COALESCE(l.visit_date,'1000-01-01') DESC, l.created_at DESC LIMIT 300");
        $routes = $db->fetchAll("SELECT r.*, u.nickname FROM travel_routes r LEFT JOIN users u ON u.id=r.creator_id ORDER BY r.created_at DESC LIMIT 50");
        foreach ($routes as &$route) $route['points'] = withu_travel_map_points($route['points_json']);
        unset($route);
        return ['positions' => $positions, 'locations' => $locations, 'routes' => $routes];
    }
}
