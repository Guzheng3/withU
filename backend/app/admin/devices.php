<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../config/config.php'; require_once __DIR__ . '/../core/Database.php'; require_once __DIR__ . '/../core/Auth.php'; require_once __DIR__ . '/../core/helpers.php'; require_once __DIR__ . '/../core/withu.php';
$auth = new Auth();
$currentUser = withu_require_couple_user($auth);
$adminPage = 'devices';
require_once __DIR__ . '/_advanced/devices.php';
include __DIR__ . '/header.php';
?><section class="admin-page-title"><h1>信任设备</h1><p>登录一次后设备会自动保持登录；解绑后该设备需要重新登录。</p></section><?php echo withu_advanced_devices_panel(); ?><?php include __DIR__ . '/footer.php'; ?>
