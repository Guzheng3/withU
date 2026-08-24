<?php
$loggedIn = false;
try {
    $rootPath = dirname(__DIR__) . '/backend/app';
    if (is_file($rootPath . '/config/database.php') && is_file($rootPath . '/.installed')) {
        require_once $rootPath . '/config/config.php';
        require_once $rootPath . '/core/Database.php';
        require_once $rootPath . '/core/Auth.php';
        $loggedIn = (new Auth())->isLoggedIn();
    }
} catch (Throwable $e) {}