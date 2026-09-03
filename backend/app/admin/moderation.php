<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../config/config.php'; require_once __DIR__ . '/../core/Database.php'; require_once __DIR__ . '/../core/Auth.php'; require_once __DIR__ . '/../core/helpers.php'; require_once __DIR__ . '/../core/withu.php';
$auth = new Auth();
$currentUser = withu_require_couple_user($auth);
$db = Database::getInstance();
$adminPage = 'moderation';
require_once __DIR__ . '/_advanced/moderation.php';
include __DIR__ . '/header.php';
?><section class="admin-page-title"><h1>安全审核记录</h1><p>规则拦截、待复核结果都在这里保留。</p></section><?php echo withu_advanced_moderation_panel(); ?><?php include __DIR__ . '/footer.php'; ?>
