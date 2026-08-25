<?php
/**
 * 访问信标接口
 * 记录详细访问信息：域名、IP、UA、来源
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $rootPath = dirname(__DIR__, 2) . '/backend/app';
    if (!is_file($rootPath . '/config/database.php') || !is_file($rootPath . '/.installed')) {
        echo json_encode(['success' => false, 'reason' => 'not_installed']);
        exit;
    }
    
    require_once $rootPath . '/config/config.php';
    require_once $rootPath . '/core/Database.php';
    $db = Database::getInstance();

    // 确保表存在
    $db->query("CREATE TABLE IF NOT EXISTS `visitor_logs` (
        `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
        `visit_time` DATETIME NOT NULL,
        `visit_date` DATE NOT NULL,
        `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
        `ip_hash` VARCHAR(64) NOT NULL DEFAULT '',
        `domain` VARCHAR(255) NOT NULL DEFAULT '',
        `page_url` VARCHAR(500) NOT NULL DEFAULT '',
        `referrer` VARCHAR(500) NOT NULL DEFAULT '',
        `user_agent` VARCHAR(500) NOT NULL DEFAULT '',
        `ua_browser` VARCHAR(100) NOT NULL DEFAULT '',
        `ua_os` VARCHAR(100) NOT NULL DEFAULT '',
        `ua_device` VARCHAR(50) NOT NULL DEFAULT '',
        KEY `idx_date` (`visit_date`),
        KEY `idx_domain` (`domain`),
        KEY `idx_ip_hash` (`ip_hash`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS `site_visits` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `visit_date` DATE NOT NULL,
        `page_views` INT NOT NULL DEFAULT 0,
        `unique_visitors` INT NOT NULL DEFAULT 0,
        UNIQUE KEY `uk_date` (`visit_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 收集访问信息
    $now       = date('Y-m-d H:i:s');
    $today     = date('Y-m-d');
    $ip        = $_SERVER['REMOTE_ADDR'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '0.0.0.0');
    // 取第一个 IP（可能经过代理）
    $ip        = explode(',', $ip)[0];
    $ip        = trim($ip);
    $ipHash    = hash('sha256', $ip . 'withu_salt_2026');
    $domain    = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
    $pageUrl   = $_SERVER['REQUEST_URI'] ?? '';
    // 从 POST 或 GET 获取页面 URL（前端传过来的）
    $pageUrl   = $_POST['url'] ?? $_GET['url'] ?? $pageUrl;
    $referrer  = $_SERVER['HTTP_REFERER'] ?? '';
    $ua        = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // 解析 UA
    $uaBrowser = '';
    $uaOS      = '';
    $uaDevice  = 'Desktop';
    if (preg_match('/Edg\/([\d.]+)/i', $ua, $m)) { $uaBrowser = 'Edge ' . $m[1]; }
    elseif (preg_match('/Chrome\/([\d.]+)/i', $ua, $m)) { $uaBrowser = 'Chrome ' . $m[1]; }
    elseif (preg_match('/Firefox\/([\d.]+)/i', $ua, $m)) { $uaBrowser = 'Firefox ' . $m[1]; }
    elseif (preg_match('/Safari\/([\d.]+)/i', $ua, $m)) { $uaBrowser = 'Safari ' . $m[1]; }
    if (preg_match('/Windows/i', $ua)) { $uaOS = 'Windows'; }
    elseif (preg_match('/Mac OS|Macintosh/i', $ua)) { $uaOS = 'macOS'; }
    elseif (preg_match('/Linux/i', $ua) && !preg_match('/Android/i', $ua)) { $uaOS = 'Linux'; }
    elseif (preg_match('/Android/i', $ua)) { $uaOS = 'Android'; $uaDevice = 'Mobile'; }
    elseif (preg_match('/iPhone|iPad|iPod/i', $ua)) { $uaOS = 'iOS'; $uaDevice = (preg_match('/iPad/i', $ua) ? 'Tablet' : 'Mobile'); }
    if (preg_match('/Mobile|Android.*Mobile/i', $ua)) { $uaDevice = 'Mobile'; }
    if (preg_match('/Tablet|iPad/i', $ua)) { $uaDevice = 'Tablet'; }

    // 插入详细日志
    $db->insert('visitor_logs', [
        'visit_time'  => $now,
        'visit_date'  => $today,
        'ip_address'  => $ip,
        'ip_hash'     => $ipHash,
        'domain'      => $domain,
        'page_url'    => $pageUrl,
        'referrer'    => $referrer,
        'user_agent'  => mb_substr($ua, 0, 500),
        'ua_browser'  => $uaBrowser,
        'ua_os'       => $uaOS,
        'ua_device'   => $uaDevice,
    ]);

    // 更新聚合统计（site_visits）
    $row = $db->fetch("SELECT id FROM site_visits WHERE visit_date = ?", [$today]);
    if ($row) {
        $db->query("UPDATE site_visits SET page_views = page_views + 1 WHERE visit_date = ?", [$today]);
    } else {
        $db->insert('site_visits', [
            'visit_date'      => $today,
            'page_views'      => 1,
            'unique_visitors' => 1,
        ]);
    }

    // 更新今日独立访客（基于 IP hash）
    $uniqueCount = $db->fetch(
        "SELECT COUNT(DISTINCT ip_hash) AS cnt FROM visitor_logs WHERE visit_date = ?",
        [$today]
    );
    if ($uniqueCount) {
        $db->query("UPDATE site_visits SET unique_visitors = ? WHERE visit_date = ?", [
            (int)$uniqueCount['cnt'], $today
        ]);
    }

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    // 静默失败，记录日志
    error_log('access-beacon error: ' . $e->getMessage());
    echo json_encode(['success' => false]);
}