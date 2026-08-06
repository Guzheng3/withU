<?php
// 新版后台公用头部（移动端优先）
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/helpers.php';

$auth = $auth ?? new Auth();
// 后台仅允许情侣双方账号访问（user1 / user2），禁止其他角色误入
$auth->requireLogin();
$auth->requireRole(['user1', 'user2']);
$db          = $db ?? Database::getInstance();
$currentUser = $currentUser ?? $auth->getCurrentUser();

// 当前页面用于高亮底部导航/抽屉菜单
$adminPage = $adminPage ?? 'dashboard';
$adminSection = (string)($_GET['section'] ?? 'general');
$themeConfig = function_exists('withu_theme_config') ? withu_theme_config() : ['preset' => 'sakura', 'mode' => 'auto', 'custom' => false, 'colors' => []];
$adminUiMode = (string)get_setting('admin_ui_mode', 'current');
if (!in_array($adminUiMode, ['current', 'apple'], true)) {
    $adminUiMode = 'current';
}
$nextAdminUiMode = $adminUiMode === 'apple' ? 'current' : 'apple';
$currentAdminUri = (string)($_SERVER['REQUEST_URI'] ?? '/admin/index.php');
if ($currentAdminUri === '' || strpos($currentAdminUri, '/admin/') !== 0) {
    $currentAdminUri = '/admin/index.php';
}
$adminPageMeta = [
    'dashboard' => ['title' => '仪表盘', 'section' => '总览'],
    'articles' => ['title' => '文章与日记', 'section' => '内容管理'],
    'albums' => ['title' => '相册管理', 'section' => '内容管理'],
    'messages' => ['title' => '留言管理', 'section' => '内容管理'],
    'events' => ['title' => '纪念事件', 'section' => '内容管理'],
    'media' => ['title' => '媒体配置', 'section' => '影视与播放'],
    'media_catalog' => ['title' => '影视资源库', 'section' => '影视与播放'],
    'media_resources' => ['title' => '资源列表', 'section' => '影视与播放'],
    'together_settings' => ['title' => '一起看设置', 'section' => '影视与播放'],
    'player_settings' => ['title' => '播放器设置', 'section' => '影视与播放'],
    'player_art' => ['title' => '播放器设置', 'section' => '影视与播放'],
    'moderation' => ['title' => '安全审核', 'section' => '系统管理'],
    'devices' => ['title' => '信任设备', 'section' => '系统管理'],
    'settings' => ['title' => $adminSection === 'theme' ? '主题与外观' : '系统设置', 'section' => '系统管理'],
    'profile' => ['title' => '个人资料', 'section' => '账号与协作'],
    'invites' => ['title' => '邀请另一半', 'section' => '账号与协作'],
    'comment_ip_blacklist' => ['title' => '评论黑名单', 'section' => '系统管理'],
    'tools_stats' => ['title' => '图片工具', 'section' => '工具'],
];
$activeAdminMeta = $adminPageMeta[$adminPage] ?? ['title' => '管理后台', 'section' => '总览'];
$themeInlineStyle = '';
foreach (($themeConfig['colors'] ?? []) as $themeName => $themeValue) {
    $themeInlineStyle .= '--withu-custom-' . $themeName . ':' . $themeValue . ';';
}
?>
<!DOCTYPE html>
<html lang="zh-CN" data-withu-theme="<?php echo e($themeConfig['preset']); ?>" data-withu-mode="<?php echo e($themeConfig['mode']); ?>" data-admin-ui="<?php echo e($adminUiMode); ?>"<?php if (!empty($themeConfig['custom'])): ?> data-withu-theme-custom="1" style="<?php echo e($themeInlineStyle); ?>"<?php endif; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理后台 - <?php echo e(SITE_NAME); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css?v=withu-logo-20260718">
    <link rel="stylesheet" href="/assets/css/admin_v2.css?v=withu-admin-20260724-1">
    <link rel="stylesheet" href="/assets/css/theme.css?v=withu-theme-20260724-1">
    <link rel="stylesheet" href="/assets/css/admin_pages.css?v=withu-admin-pages-20260724-2">
    <link rel="stylesheet" href="/assets/css/admin_apple.css?v=withu-admin-apple-20260724-11">
    <link rel="stylesheet"
          href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.4.0/css/all.min.css"
          onerror="this.onerror=null;this.href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';">
</head>
<body class="admin-v2 admin-ui-<?php echo e($adminUiMode); ?>">
<div class="admin-drawer-backdrop" aria-hidden="true"></div>
<aside class="admin-drawer" id="admin-drawer" aria-label="后台导航">
    <div class="admin-drawer-header">
        <div>
            <div class="admin-drawer-title"><?php echo e(SITE_NAME); ?></div>
            <div style="font-size:0.8rem;color:var(--text-light);margin-top:0.15rem;">
                管理中心
            </div>
        </div>
        <button class="admin-icon-btn" type="button" data-admin-toggle="drawer" aria-label="关闭菜单" aria-controls="admin-drawer" aria-expanded="false">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div class="admin-drawer-menu">
        <div class="admin-drawer-section-title">总览</div>
        <a href="/admin/index.php" class="admin-drawer-link <?php echo $adminPage === 'dashboard' ? 'admin-drawer-link-active' : ''; ?>">
            <i class="fas fa-grid-2"></i><span>仪表盘</span><small>状态概览</small>
        </a>

        <div class="admin-drawer-section-title">内容管理</div>
        <a href="/admin/articles.php" class="admin-drawer-link <?php echo $adminPage === 'articles' ? 'admin-drawer-link-active' : ''; ?>">
            <i class="fas fa-book-open"></i><span>文章与日记</span>
        </a>
        <a href="/admin/albums.php" class="admin-drawer-link <?php echo $adminPage === 'albums' ? 'admin-drawer-link-active' : ''; ?>">
            <i class="fas fa-images"></i><span>相册管理</span>
        </a>
        <a href="/admin/messages.php" class="admin-drawer-link <?php echo $adminPage === 'messages' ? 'admin-drawer-link-active' : ''; ?>">
            <i class="fas fa-comment-dots"></i><span>留言管理</span>
        </a>
        <a href="/admin/events.php" class="admin-drawer-link <?php echo $adminPage === 'events' ? 'admin-drawer-link-active' : ''; ?>">
            <i class="fas fa-calendar-heart"></i><span>纪念事件</span>
        </a>

        <div class="admin-drawer-section-title">影视与播放</div>
        <a href="/admin/media.php" class="admin-drawer-link <?php echo $adminPage === 'media' ? 'admin-drawer-link-active' : ''; ?>">
            <i class="fas fa-sliders"></i><span>媒体配置</span>
        </a>
        <a href="/admin/media_catalog.php" class="admin-drawer-link <?php echo $adminPage === 'media_catalog' ? 'admin-drawer-link-active' : ''; ?>">
            <i class="fas fa-layer-group"></i><span>影视资源库</span>
        </a>
        <a href="/admin/media_resources.php" class="admin-drawer-link <?php echo $adminPage === 'media_resources' ? 'admin-drawer-link-active' : ''; ?>">
            <i class="fas fa-list"></i><span>资源列表</span>
        </a>
        <a href="/admin/together_settings.php" class="admin-drawer-link <?php echo $adminPage === 'together_settings' ? 'admin-drawer-link-active' : ''; ?>">
            <i class="fas fa-people-arrows"></i><span>一起看设置</span>
        </a>
        <a href="/admin/player_art.php" class="admin-drawer-link <?php echo in_array($adminPage, ['player_settings', 'player_art'], true) ? 'admin-drawer-link-active' : ''; ?>">
            <i class="fas fa-play-circle"></i><span>播放器设置</span>
        </a>

        <div class="admin-drawer-section-title">系统管理</div>
        <a href="/admin/settings.php?section=general" class="admin-drawer-link <?php echo $adminPage === 'settings' && $adminSection !== 'theme' ? 'admin-drawer-link-active' : ''; ?>">
            <i class="fas fa-sliders-h"></i><span>系统设置</span>
        </a>
        <a href="/admin/settings.php?section=theme#theme-settings" class="admin-drawer-link <?php echo $adminPage === 'settings' && $adminSection === 'theme' ? 'admin-drawer-link-active' : ''; ?>">
            <i class="fas fa-palette"></i><span>主题与外观</span>
        </a>
        <a href="/admin/moderation.php" class="admin-drawer-link <?php echo $adminPage === 'moderation' ? 'admin-drawer-link-active' : ''; ?>">
            <i class="fas fa-shield-halved"></i><span>安全审核</span>
        </a>
        <a href="/admin/devices.php" class="admin-drawer-link <?php echo $adminPage === 'devices' ? 'admin-drawer-link-active' : ''; ?>">
            <i class="fas fa-mobile-screen-button"></i><span>信任设备</span>
        </a>
        <a href="/admin/comment_ip_blacklist.php" class="admin-drawer-link <?php echo $adminPage === 'comment_ip_blacklist' ? 'admin-drawer-link-active' : ''; ?>">
            <i class="fas fa-user-slash"></i><span>评论黑名单</span>
        </a>

        <div class="admin-drawer-section-title">账号与工具</div>
        <a href="/admin/profile.php" class="admin-drawer-link <?php echo $adminPage === 'profile' ? 'admin-drawer-link-active' : ''; ?>">
            <i class="fas fa-user"></i><span>个人资料</span>
        </a>
        <a href="/admin/invites.php" class="admin-drawer-link <?php echo $adminPage === 'invites' ? 'admin-drawer-link-active' : ''; ?>">
            <i class="fas fa-user-plus"></i><span>邀请另一半</span>
        </a>
        <?php $toolsTab = $_GET['tab'] ?? ''; ?>
        <a href="/admin/tools_image_stats.php?tab=optimize" class="admin-drawer-link <?php echo ($adminPage === 'tools_stats' && $toolsTab === 'optimize') ? 'admin-drawer-link-active' : ''; ?>">
            <i class="fas fa-compress-arrows-alt"></i><span>图片补齐</span>
        </a>
        <a href="/admin/tools_image_stats.php" class="admin-drawer-link <?php echo ($adminPage === 'tools_stats' && $toolsTab !== 'optimize') ? 'admin-drawer-link-active' : ''; ?>">
            <i class="fas fa-chart-bar"></i><span>图片统计</span>
        </a>
        <a href="/logout.php" class="admin-drawer-link admin-drawer-link-danger">
            <i class="fas fa-sign-out-alt"></i><span>退出登录</span>
        </a>
    </div>

    <div class="admin-drawer-footer">
        <div>当前用户：<?php echo e($currentUser['nickname'] ?? $currentUser['username']); ?></div>
    </div>
</aside>

<header class="admin-appbar">
    <div class="admin-appbar-inner">
        <div class="admin-appbar-left">
            <button class="admin-icon-btn" type="button" data-admin-toggle="drawer" aria-label="打开菜单" aria-controls="admin-drawer" aria-expanded="false">
                <i class="fas fa-bars"></i>
            </button>
            <a href="/admin/index.php" class="admin-logo">
                <img class="admin-logo-image" src="/assets/images/withu-logo.png" alt="<?php echo e(SITE_NAME); ?>">
            </a>
            <div class="admin-appbar-context">
                <span class="admin-appbar-section"><?php echo e($activeAdminMeta['section']); ?></span>
                <strong><?php echo e($activeAdminMeta['title']); ?></strong>
            </div>
        </div>
        <div class="admin-appbar-actions">
            <form class="admin-ui-mode-form" method="post" action="/admin/ui_mode.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="next" value="<?php echo e($currentAdminUri); ?>">
                <button class="admin-ui-mode-toggle" type="submit" name="mode" value="<?php echo e($nextAdminUiMode); ?>" title="切换后台界面">
                    <span class="admin-ui-mode-track" aria-hidden="true">
                        <span class="admin-ui-mode-thumb"></span>
                    </span>
                    <span class="admin-ui-mode-label"><?php echo $adminUiMode === 'apple' ? 'Apple' : '当前'; ?></span>
                </button>
            </form>
            <a href="/" class="admin-icon-btn" title="前台">
                <i class="fas fa-globe"></i>
            </a>
            <img src="<?php echo e($currentUser['avatar']); ?>"
                 alt="<?php echo e($currentUser['nickname']); ?>"
                 class="admin-user-avatar">
        </div>
    </div>
</header>

<div class="admin-shell">
    <main class="admin-main">
