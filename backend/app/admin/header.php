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
// 后台界面 v3 轻量通透设计
	$adminUiMode = 'v3';
$adminPageMeta = [
    'dashboard' => ['title' => '仪表盘', 'section' => '总览'],
    'articles' => ['title' => '文章与日记', 'section' => '内容管理'],
    'albums' => ['title' => '相册管理', 'section' => '内容管理'],
    'messages' => ['title' => '留言管理', 'section' => '内容管理'],
    'events' => ['title' => '纪念事件', 'section' => '内容管理'],
    'map' => ['title' => '地图与足迹', 'section' => '内容管理'],
    'timetable_settings' => ['title' => '课表设置', 'section' => '内容管理'],
    'together_settings' => ['title' => '一起看设置', 'section' => '影视与播放'],
    'player_settings' => ['title' => '播放器设置', 'section' => '影视与播放'],
    'player_art' => ['title' => '播放器设置', 'section' => '影视与播放'],
    'strm_settings' => ['title' => 'withUstrm', 'section' => '影视与播放'],
    'profile' => ['title' => '个人资料', 'section' => '设置'],
    'invites' => ['title' => '邀请伴侣', 'section' => '账号'],
    'settings' => ['title' => $adminSection === 'theme' ? '主题与外观' : ($adminSection === 'advanced' ? '高级设置' : '系统设置'), 'section' => '设置'],
    'moderation' => ['title' => '安全审核', 'section' => '高级设置'],
    'devices' => ['title' => '信任设备', 'section' => '高级设置'],
    'comment_ip_blacklist' => ['title' => '评论黑名单', 'section' => '高级设置'],
    'tools_stats' => ['title' => '图片工具', 'section' => '高级设置'],
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
<link rel="stylesheet" href="/admin-assets/css/style.css?v=withu-logo-20260718">
	<link rel="stylesheet" href="/admin-assets/css/admin_v3.css?v=20260831-admin-album-layout">
	<link rel="stylesheet" href="/admin-assets/css/admin_v2.css?v=ui-polish-3">
	<link rel="stylesheet" href="/admin-assets/css/theme.css?v=withu-theme-20260724-1">
	<link rel="stylesheet" href="/admin-assets/css/admin_pages.css?v=player-art-polish-1">
<!-- Tabler Icons（本地） -->
		<link rel="stylesheet" href="/admin-assets/vendor/tabler-icons/tabler-icons.min.css">
		<!-- Font Awesome 备用（本地） -->
		<link rel="stylesheet" href="/admin-assets/vendor/fontawesome/css/all.min.css">
</head>
<body class="admin-v3 admin-ui-<?php echo e($adminUiMode); ?><?php echo !empty($adminNarrow) ? ' admin-narrow' : ''; ?>">
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
                <i class="ti ti-x"></i>
            </button>
    </div>

    <div class="admin-drawer-menu">
        <div class="admin-drawer-section-title">总览</div>
        <a href="/admin/index.php" class="admin-drawer-link <?php echo $adminPage === 'dashboard' ? 'admin-drawer-link-active' : ''; ?>">
            <i class="ti ti-layout-dashboard"></i><span>仪表盘</span>
        </a>

        <div class="admin-drawer-section-title">内容管理</div>
        <a href="/admin/articles.php" class="admin-drawer-link <?php echo $adminPage === 'articles' ? 'admin-drawer-link-active' : ''; ?>">
            <i class="ti ti-book"></i><span>文章与日记</span>
        </a>
        <a href="/admin/albums.php" class="admin-drawer-link <?php echo $adminPage === 'albums' ? 'admin-drawer-link-active' : ''; ?>">
            <i class="ti ti-photo"></i><span>相册管理</span>
        </a>
        <a href="/admin/messages.php" class="admin-drawer-link <?php echo $adminPage === 'messages' ? 'admin-drawer-link-active' : ''; ?>">
            <i class="ti ti-message-circle"></i><span>留言管理</span>
        </a>
        <a href="/admin/events.php" class="admin-drawer-link <?php echo $adminPage === 'events' ? 'admin-drawer-link-active' : ''; ?>">
            <i class="ti ti-calendar-event"></i><span>纪念事件</span>
        </a>
        <a href="/admin/map.php" class="admin-drawer-link <?php echo $adminPage === 'map' ? 'admin-drawer-link-active' : ''; ?>">
            <i class="ti ti-map-pin"></i><span>地图与足迹</span>
        </a>
        <a href="/admin/timetable_settings.php" class="admin-drawer-link <?php echo $adminPage === 'timetable_settings' ? 'admin-drawer-link-active' : ''; ?>">
            <i class="ti ti-calendar-time"></i><span>课表设置</span>
        </a>

        <div class="admin-drawer-section-title">影视与播放</div>
        <a href="/admin/together_settings.php" class="admin-drawer-link <?php echo $adminPage === 'together_settings' ? 'admin-drawer-link-active' : ''; ?>">
            <i class="ti ti-users"></i><span>一起看设置</span>
        </a>
        <a href="/admin/player_art.php" class="admin-drawer-link <?php echo in_array($adminPage, ['player_settings', 'player_art'], true) ? 'admin-drawer-link-active' : ''; ?>">
            <i class="ti ti-player-play"></i><span>播放器设置</span>
        </a>
        <a href="/admin/strm_settings.php" class="admin-drawer-link <?php echo $adminPage === 'strm_settings' ? 'admin-drawer-link-active' : ''; ?>">
            <i class="ti ti-server-2"></i><span>withUstrm</span>
        </a>

        <div class="admin-drawer-section-title">设置</div>
        <a href="/admin/settings.php?section=general" class="admin-drawer-link <?php echo ($adminPage === 'settings' && $adminSection !== 'advanced') ? 'admin-drawer-link-active' : ''; ?>">
            <i class="ti ti-settings"></i><span>系统设置</span>
        </a>
        <a href="/admin/profile.php" class="admin-drawer-link <?php echo $adminPage === 'profile' ? 'admin-drawer-link-active' : ''; ?>">
            <i class="ti ti-user"></i><span>账号设置</span>
        </a>
        <a href="/admin/settings.php?section=advanced" class="admin-drawer-link <?php echo ($adminPage === 'settings' && $adminSection === 'advanced') || in_array($adminPage, ['moderation', 'devices', 'comment_ip_blacklist', 'tools_stats'], true) ? 'admin-drawer-link-active' : ''; ?>">
            <i class="ti ti-adjustments"></i><span>高级设置</span>
        </a>
    </div>

    <div class="admin-drawer-footer">
        <div class="admin-drawer-footer-user">当前用户：<?php echo e($currentUser['nickname'] ?? $currentUser['username']); ?></div>
        <a href="/logout.php" class="admin-drawer-logout" onclick="return confirm('确定要退出登录吗？');">
            <i class="ti ti-logout"></i><span>退出登录</span>
        </a>
    </div>
</aside>

<header class="admin-appbar">
    <div class="admin-appbar-inner">
        <div class="admin-appbar-left">
            <button class="admin-icon-btn" type="button" data-admin-toggle="drawer" aria-label="打开菜单" aria-controls="admin-drawer" aria-expanded="false">
                <i class="ti ti-menu-2"></i>
            </button>
            <a href="/admin/index.php" class="admin-logo">
                <img class="admin-logo-image" src="/admin-assets/images/withu-logo.png" alt="<?php echo e(SITE_NAME); ?>">
            </a>
            <div class="admin-appbar-context">
                <span class="admin-appbar-section"><?php echo e($activeAdminMeta['section']); ?></span>
                <strong><?php echo e($activeAdminMeta['title']); ?></strong>
            </div>
        </div>
        <div class="admin-appbar-actions">
            <a href="/" class="admin-icon-btn" title="前台">
                <i class="ti ti-world"></i>
            </a>
            <img src="<?php echo e($currentUser['avatar']); ?>"
                 alt="<?php echo e($currentUser['nickname']); ?>"
                 class="admin-user-avatar">
        </div>
    </div>
</header>

<div class="admin-shell">
    <main class="admin-main">
