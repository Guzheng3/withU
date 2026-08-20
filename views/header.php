<?php
// 确保顶部使用的变量已定义，避免未定义变量报错
if (!isset($currentUser)) {
    $currentUser = null;
}
if (!isset($partner)) {
    $partner = null;
}
if (!isset($albumHeaderMood)) {
    $albumHeaderMood = '';
}

// 头部展示用情侣头像（即使未登录也尽量显示）
$headerUser1 = null;
$headerUser2 = null;

// 优先使用当前登录用户与其伴侣
if ($currentUser && $partner) {
    $headerUser1 = $currentUser;
    $headerUser2 = $partner;
} else {
    // 未登录或未能获取伴侣时，从数据库中直接读取 user1 / user2
    $headerDb = null;
    if (isset($db) && is_object($db)) {
        $headerDb = $db;
    } elseif (class_exists('Database')) {
        try {
            $headerDb = Database::getInstance();
        } catch (Exception $e) {
            $headerDb = null;
        }
    }

    if ($headerDb) {
        try {
            $users = $headerDb->fetchAll("SELECT * FROM users WHERE role IN ('user1','user2') AND status = 'active'");
            if ($users) {
                $roleMap = [];
                foreach ($users as $u) {
                    if (!empty($u['role'])) {
                        $roleMap[$u['role']] = $u;
                    }
                }
                // 分别保留已存在的用户；仅配置一位时由首页 Hero 使用另一半占位。
                if (isset($roleMap['user1'])) {
                    $headerUser1 = $roleMap['user1'];
                }
                if (isset($roleMap['user2'])) {
                    $headerUser2 = $roleMap['user2'];
                }
            }
        } catch (Exception $e) {
            // 忽略头像对读取失败
        }
    }
}

// 获取数据库连接
$headerDb = null;
if (isset($db) && is_object($db)) {
    $headerDb = $db;
} elseif (class_exists('Database')) {
    try {
        $headerDb = Database::getInstance();
    } catch (Exception $e) {
        $headerDb = null;
    }
}

// 从设置表读取网站标题与网站描述
$siteTitle = SITE_NAME; // 默认值
$siteDescription = '';
if ($headerDb) {
    try {
        $row = $headerDb->fetch("SELECT value FROM settings WHERE `key` = 'site_title'");
        if ($row && !empty($row['value'])) {
            $siteTitle = $row['value'];
        }

        $row = $headerDb->fetch("SELECT value FROM settings WHERE `key` = 'site_description'");
        if ($row && !empty($row['value'])) {
            $siteDescription = $row['value'];
        }
    } catch (Exception $e) {
        // 忽略读取失败的异常，使用默认值
    }
}

// 首页顶部大图：默认从设置表读取；如果外部已经传入 $homeBannerImage（例如相册详情页），则不再覆盖
if (!isset($homeBannerImage)) {
    $homeBannerImage = '';
    if ($headerDb) {
        try {
            $row = $headerDb->fetch("SELECT value FROM settings WHERE `key` = 'home_banner_image'");
            if ($row) {
                // 只要存在这一条设置记录，就不再使用默认图片；
                // 用户可以通过清空该设置来实现“无大图”效果
                if (!empty($row['value'])) {
                    $homeBannerImage = $row['value'];
                }
            } else {
                // 完全没有 home_banner_image 记录时（全新安装且未保存设置），使用预设默认大图（静态资源）
                $homeBannerImage = '/assets/images/default_hero.jpg';
            }
        } catch (Exception $e) {
            // 忽略顶部图片读取失败的异常
        }
    }

    // 新版：根据不同形式的路径补全为可直接在前端使用的地址
    if ($homeBannerImage !== '') {
        // 已经是绝对 URL 或协议相对 URL，原样使用
        if (strpos($homeBannerImage, 'http://') === 0 ||
            strpos($homeBannerImage, 'https://') === 0 ||
            strpos($homeBannerImage, '//') === 0) {
            // do nothing
        // 以 / 开头：视为站点根路径，例如 /assets/images/default_hero.jpg
        } elseif (strpos($homeBannerImage, '/') === 0) {
            // 保留为根路径，前端将相对当前域名加载
            // 如有需要，也可以改为 BASE_URL . $homeBannerImage
        // 其它情况：视为 uploads 下面的相对路径
        } else {
            $homeBannerImage = UPLOAD_URL . ltrim($homeBannerImage, '/');
        }
    }
}

// 情侣主页使用专属浪漫背景，其他页面继续使用各自的背景配置
if (!empty($isWithuHomePage) && $headerUser1 && $headerUser2) {
    $homeBannerImage = '';
}

// 页面标题：如果未设置，则只显示网站标题；如果设置了，则显示"页面标题 - 网站标题"
$pageTitle = isset($pageTitle) ? $pageTitle : '';
$fullTitle = $pageTitle ? $pageTitle . ' - ' . $siteTitle : $siteTitle;

// 页面描述：允许单页通过 $pageDescription 覆盖，未设置则使用全站网站描述
$pageDescription = isset($pageDescription) ? (string) $pageDescription : '';
if ($pageDescription === '') {
    $pageDescription = $siteDescription;
}

$themeConfig = function_exists('withu_theme_config') ? withu_theme_config() : ['preset' => 'pastel-couple', 'mode' => 'auto', 'custom' => false, 'colors' => []];
$themeInlineStyle = '';
foreach (($themeConfig['colors'] ?? []) as $themeName => $themeValue) {
    $themeInlineStyle .= '--withu-custom-' . $themeName . ':' . $themeValue . ';';
}

// 所有前台页面共享 LG-inspired 视觉壳，页面类型只作为附加 class
$bodyClass = 'withu-front-modern';
if (!empty($isAlbumDetail)) {
    $bodyClass .= ' page-album-detail';
} elseif (!empty($isArticleDetail)) {
    $bodyClass .= ' page-article-detail';
}
$isWithuHomePage = in_array(basename((string)($_SERVER['SCRIPT_NAME'] ?? '')), ['index.php', ''], true);
$heroDistance = trim((string)get_setting('couple_distance', ''));
$heroDistance = $heroDistance !== '' ? $heroDistance : '心在一起';
$heroDays = isset($daysTogether) && $loveDateSet ? (int)$daysTogether : null;
$heroTagline = $siteDescription !== '' ? $siteDescription : '把平凡的日子，过成只属于我们的浪漫。';

// Hero 即使在只完成一位用户配置时也保持完整展示；第二位用户注册后自动使用真实资料。
$heroAvatarFallback = '/assets/images/default-avatar.svg';
if ($headerUser1 && empty($headerUser1['avatar'])) {
    $headerUser1['avatar'] = $heroAvatarFallback;
}
if ($isWithuHomePage && $headerUser1 && !$headerUser2) {
    $headerUser2 = [
        'nickname' => '另一半',
        'avatar' => $heroAvatarFallback,
    ];
}
if ($headerUser2 && empty($headerUser2['avatar'])) {
    $headerUser2['avatar'] = $heroAvatarFallback;
}

if ($headerDb && (string)get_setting('front_animation_enabled', '1') !== '1') {
    $bodyClass = trim($bodyClass . ' withu-no-front-effects');
}
?>
<!DOCTYPE html>
<html lang="zh-CN" data-withu-theme="<?php echo e($themeConfig['preset']); ?>" data-withu-mode="<?php echo e($themeConfig['mode']); ?>"<?php if (!empty($themeConfig['custom'])): ?> data-withu-theme-custom="1" style="<?php echo e($themeInlineStyle); ?>"<?php endif; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($fullTitle); ?></title>
    <?php if (!empty($pageDescription)): ?>
    <meta name="description" content="<?php echo e($pageDescription); ?>">
    <meta property="og:description" content="<?php echo e($pageDescription); ?>">
    <?php endif; ?>
    <meta property="og:title" content="<?php echo e($fullTitle); ?>">
    <link rel="stylesheet" href="/assets/css/style.css?v=withu-logo-20260718">
    <link rel="stylesheet" href="/assets/css/theme.css?v=withu-theme-20260719-3">
    <link rel="stylesheet" href="/assets/css/withu_lg_ui.css?v=withu-lg-20260809-2">
    <link rel="stylesheet" href="/assets/css/withu_polish.css?v=withu-polish-20260818">
    <link rel="stylesheet" href="/assets/css/withustrm_home.css?v=withustrm-home-20260815">
    <?php if (!empty($isArticleDetail)): ?>
    <link rel="stylesheet" href="/assets/css/article-detail.css">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Infant:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.4.0/css/all.min.css"
          onerror="this.onerror=null;this.href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';">
</head>
<body<?php if (!empty($bodyClass)): ?> class="<?php echo e($bodyClass); ?>"<?php endif; ?>>
    <div class="top-nav">
        <div class="top-nav-inner">
            <a href="/" class="top-nav-logo" aria-label="<?php echo e($siteTitle); ?>">
                <img class="top-nav-logo-image" src="/assets/images/withu-logo.png" alt="<?php echo e($siteTitle); ?>">
                <span class="top-nav-logo-sr"><?php echo e($siteTitle); ?></span>
            </a>
            <div class="top-nav-user">
                <?php if ($currentUser): ?>
                    <a href="/admin/" class="top-nav-link">管理后台</a>
                    <a href="/logout.php" class="top-nav-link">退出登录</a>
                <?php else: ?>
                    <a href="/login.php" class="top-nav-link">登录</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <header class="main-header">
        <div class="header-background"<?php if (!empty($homeBannerImage)): ?> data-bg="<?php echo e($homeBannerImage); ?>"<?php endif; ?>>
            <div class="header-overlay"></div>
            <div class="header-content">
                <?php if (!empty($isAlbumDetail)): ?>
                    <div class="welcome-text album-header-text">
                        <h1><?php echo e($albumHeaderTitle ?? $siteTitle); ?></h1>
                        <div class="album-header-tags">
                            <?php if (!empty($albumHeaderDate)): ?>
                                <span class="album-header-tag">
                                    <i class="fas fa-calendar-alt"></i>
                                    <?php echo e(is_string($albumHeaderDate) ? date('Y-m-d', strtotime($albumHeaderDate)) : date('Y-m-d', $albumHeaderDate)); ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($albumHeaderAuthor)): ?>
                                <span class="album-header-tag">
                                    <i class="fas fa-user"></i>
                                    <?php echo e($albumHeaderAuthor); ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($albumHeaderMood)): ?>
                                <span class="album-header-tag album-header-tag-mood">
                                    <i class="fas fa-heart"></i>
                                    <?php echo e($albumHeaderMood); ?>
                                </span>
                            <?php endif; ?>
                            <?php
                            // 相册详情页：加密且未登录时，用锁图标替代“已上传 X 张照片”
                            $showLockForEncryptedAlbum = isset($albumIsEncryptedForGuest) && $albumIsEncryptedForGuest;
                            ?>
                            <?php if ($showLockForEncryptedAlbum): ?>
                                <span class="album-header-tag">
                                    <i class="fas fa-lock"></i>
                                    加密相册
                                </span>
                            <?php else: ?>
                                <?php
                                $imgCount   = isset($albumHeaderImageCount)
                                    ? (int) $albumHeaderImageCount
                                    : (isset($albumHeaderCount) ? (int) $albumHeaderCount : 0);
                                $videoCount = isset($albumHeaderVideoCount) ? (int) $albumHeaderVideoCount : 0;
                                ?>
                                <?php if ($imgCount > 0): ?>
                                    <span class="album-header-tag">
                                        <i class="fas fa-images"></i>
                                        已上传 <?php echo $imgCount; ?> 张照片
                                    </span>
                                <?php else: ?>
                                    <span class="album-header-tag">
                                        <i class="fas fa-images"></i>
                                        未上传图片
                                    </span>
                                <?php endif; ?>
                                <?php if ($videoCount > 0): ?>
                                    <span class="album-header-tag">
                                        <i class="fas fa-video"></i>
                                        已上传 <?php echo $videoCount; ?> 个视频
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php elseif ($isWithuHomePage && $headerUser1 && $headerUser2): ?>
                <section class="withu-lg-hero" aria-label="情侣主页">
                    <div class="withu-hero-glow withu-hero-glow-a"></div>
                    <div class="withu-hero-glow withu-hero-glow-b"></div>
                    <div class="withu-hero-particles" id="withu-hero-particles" aria-hidden="true"></div>
                    <div class="withu-hero-orbit withu-hero-orbit-one"></div>
                    <div class="withu-hero-orbit withu-hero-orbit-two"></div>
                    <div class="withu-hero-copy">
                        <span class="withu-hero-eyebrow"><i class="fas fa-sparkles"></i> OUR LITTLE UNIVERSE</span>
                        <h1 id="withustrm-hero-title" data-hero-split><?php echo e($siteTitle); ?></h1>
                        <p><?php echo e($heroTagline); ?></p>
                    </div>
                    <div class="withu-hero-couple">
                        <div class="withu-hero-person withu-hero-person-left">
                            <img src="<?php echo e($headerUser1['avatar']); ?>" alt="<?php echo e($headerUser1['nickname']); ?>" class="withu-hero-avatar">
                            <strong><?php echo e($headerUser1['nickname']); ?></strong>
                        </div>
                        <div class="withu-hero-heart" aria-label="相爱"><i class="fas fa-heart"></i><span>together</span></div>
                        <div class="withu-hero-person withu-hero-person-right">
                            <img src="<?php echo e($headerUser2['avatar']); ?>" alt="<?php echo e($headerUser2['nickname']); ?>" class="withu-hero-avatar">
                            <strong><?php echo e($headerUser2['nickname']); ?></strong>
                        </div>
                    </div>
                    <div class="withu-hero-facts">
                        <div class="withu-hero-fact"><i class="fas fa-heart"></i><span>相爱</span><b><?php echo $heroDays !== null ? $heroDays : '—'; ?></b><small>天</small></div>
                        <div class="withu-hero-fact"><i class="fas fa-location-dot"></i><span>相距</span><b><?php echo e($heroDistance); ?></b></div>
                        <div class="withu-hero-fact"><i class="fas fa-wand-magic-sparkles"></i><span>记录</span><b>每一天</b></div>
                    </div>
                    <div class="withu-hero-actions"><a href="/events.php"><i class="fas fa-calendar-plus"></i> 查看我们的纪念日</a><a href="/albums.php" class="is-ghost"><i class="fas fa-images"></i> 打开相册</a></div>
                </section>
                <?php elseif ($headerUser1 && $headerUser2): ?>
                <div class="avatar-pair">
                    <div class="avatar-container">
                        <img src="<?php echo e($headerUser1['avatar']); ?>" alt="<?php echo e($headerUser1['nickname']); ?>" class="avatar">
                        <div class="avatar-label"><?php echo e($headerUser1['nickname']); ?></div>
                    </div>
                    <div class="heart-icon"><i class="fas fa-heart"></i></div>
                    <div class="avatar-container">
                        <img src="<?php echo e($headerUser2['avatar']); ?>" alt="<?php echo e($headerUser2['nickname']); ?>" class="avatar">
                        <div class="avatar-label"><?php echo e($headerUser2['nickname']); ?></div>
                    </div>
                </div>
                <?php else: ?>
                <div class="welcome-text">
                    <h1><?php echo e($siteTitle); ?></h1>
                    <p>记录我们的小小点滴</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="header-wave">
            <svg class="waves" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                 viewBox="0 24 150 28" preserveAspectRatio="none" shape-rendering="auto">
                <defs>
                    <path id="gentle-wave"
                          d="M-160 44c30 0 58-18 88-18s58 18 88 18 58-18 88-18 58 18 88 18v44h-352z"></path>
                </defs>
                <g class="parallax">
                    <use xlink:href="#gentle-wave" x="48" y="0" fill="rgba(255,255,255,0.7)"></use>
                    <use xlink:href="#gentle-wave" x="48" y="3" fill="rgba(255,255,255,0.5)"></use>
                    <use xlink:href="#gentle-wave" x="48" y="5" fill="rgba(255,255,255,0.3)"></use>
                    <use xlink:href="#gentle-wave" x="48" y="7" fill="#ffffff"></use>
                </g>
            </svg>
        </div>
    </header>

    <nav class="main-nav lgnewui-nav-wrapper" aria-label="情侣功能导航">
        <div class="nav-buttons lgnewui-nav-island-container">
            <a href="/articles.php" class="nav-button lgnewui-nav-island-item gradient-green">
                <i class="fas fa-book"></i>
                <span>点点滴滴</span>
            </a>
            <a href="/messages.php" class="nav-button lgnewui-nav-island-item gradient-pink">
                <i class="fas fa-comment"></i>
                <span>留言墙</span>
            </a>
            <a href="/albums.php" class="nav-button lgnewui-nav-island-item gradient-blue">
                <i class="fas fa-images"></i>
                <span>爱情相册</span>
            </a>
            <a href="/events.php" class="nav-button lgnewui-nav-island-item gradient-purple">
                <i class="fas fa-calendar-days"></i>
                <span>纪念事件</span>
            </a>
            <?php if ($currentUser): ?>
            <a href="/watch.php" class="nav-button lgnewui-nav-island-item gradient-pink">
                <i class="fas fa-film"></i>
                <span>同步观影</span>
            </a>
            <?php endif; ?>
        </div>
    </nav>

    <main class="page-main">
