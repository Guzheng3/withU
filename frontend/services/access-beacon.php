<?php
/**
 * 访问信标接口
 * 记录页面访问信息
 */
header('Content-Type: application/json; charset=utf-8');

try {
    $rootPath = dirname(__DIR__, 2) . '/backend/app';
    if (is_file($rootPath . '/config/database.php') && is_file($rootPath . '/.installed')) {
        require_once $rootPath . '/config/config.php';
        require_once $rootPath . '/core/Database.php';
        $db = Database::getInstance();
        
        $today = date('Y-m-d');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // 检查今日是否已有记录
        $row = $db->fetch("SELECT id, page_views, unique_visitors FROM site_visits WHERE visit_date = ?", [$today]);
        
        if ($row) {
            // 更新访问次数
            $db->query("UPDATE site_visits SET page_views = page_views + 1 WHERE visit_date = ?", [$today]);
        } else {
            // 新增今日记录
            $db->insert('site_visits', [
                'visit_date' => $today,
                'page_views' => 1,
                'unique_visitors' => 1,
            ]);
        }
    }
} catch (Throwable $e) {
    // 静默失败
}

echo json_encode(['success' => true]);