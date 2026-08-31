<?php
$loggedIn = false;
try {
    $rootPath = dirname(__DIR__, 2) . '/backend/app';
    if (is_file($rootPath . '/config/database.php') && is_file($rootPath . '/.installed')) {
        require_once $rootPath . '/config/config.php';
        require_once $rootPath . '/core/Database.php';
        require_once $rootPath . '/core/Auth.php';
        $withuAuth = new Auth();
        $loggedIn = $withuAuth->isLoggedIn();
        // 已登录会话的页面访问视为活跃，刷新在线徽标依据的最近上线时间
        if ($loggedIn && !empty($_SESSION['user_id'])) {
            $withuAuth->touchLastLogin((int)$_SESSION['user_id']);
        }
    }
} catch (Throwable $e) {}