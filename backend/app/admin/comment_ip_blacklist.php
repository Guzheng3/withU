<?php
// 新版后台 - 评论 / 留言 IP 黑名单管理
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/helpers.php';

$auth = new Auth();
$auth->requireLogin();
$db          = Database::getInstance();
$currentUser = $auth->getCurrentUser();

$adminPage = 'comment_ip_blacklist';

require_once __DIR__ . '/_advanced/comment_ip_blacklist.php';

include __DIR__ . '/header.php';
?>

    <section class="admin-page-title">
        <h1>评论 / 留言 IP 黑名单</h1>
        <p>查看、添加或解除被禁止发表评论或留言的 IP</p>
    </section>

<?php echo withu_advanced_blacklist_panel(); ?>

<?php include __DIR__ . '/footer.php'; ?>
