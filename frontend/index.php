<?php
// withU 动态首页 - PHP 版
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/config.php';

// 首页大图轮播：后台「基础信息 → 首页大图」配置多张后按顺序轮播；
// 尚未保存过多图设置时（无 home_banner_images 记录），使用内置默认列表兜底
if (!function_exists('withu_normalize_banner_entry')) {
    require_once __DIR__ . '/../backend/app/core/helpers.php';
}
$homeCarouselImages = null;
try {
    if (isset($db) && is_object($db)) {
        $bannerRow = $db->fetch("SELECT value FROM settings WHERE `key` = 'home_banner_images'");
        if ($bannerRow && isset($bannerRow['value']) && trim((string)$bannerRow['value']) !== '') {
            $bannerParsedList = json_decode($bannerRow['value'], true);
            if (is_array($bannerParsedList)) {
                $homeCarouselImages = [];
                foreach ($bannerParsedList as $bannerEntry) {
                    if (is_string($bannerEntry) && trim($bannerEntry) !== '') {
                        $homeCarouselImages[] = withu_normalize_banner_entry($bannerEntry);
                    }
                }
            }
        }
    }
} catch (Throwable $e) {
    // 设置读取失败时保持默认列表
}
if (!is_array($homeCarouselImages)) {
    $homeCarouselImages = withu_home_carousel_defaults();
}

// 访问统计（使用已有的 $db 连接）
$todayViews = 0;
$todayVisitors = 0;
$totalViews = 0;
$totalVisitors = 0;
try {
    if (isset($db) && $db) {
        $today = date('Y-m-d');
        $tvRow = $db->fetch("SELECT page_views, unique_visitors FROM site_visits WHERE visit_date = ?", [$today]);
        if ($tvRow) {
            $todayViews = (int)($tvRow['page_views'] ?? 0);
            $todayVisitors = (int)($tvRow['unique_visitors'] ?? 0);
        }
        $totalRow = $db->fetch("SELECT SUM(page_views) AS total_views, SUM(unique_visitors) AS total_visitors FROM site_visits");
        if ($totalRow) {
            $totalViews = (int)($totalRow['total_views'] ?? 0);
            $totalVisitors = (int)($totalRow['total_visitors'] ?? 0);
        }
    }
} catch (Throwable $e) {}
?>
<meta name="x-withu-license-instance" content="858ee1d099b9">

<link rel="icon" href="favicon.png" />
<meta name="viewport" content="width=device-width,minimum-scale=1.0,maximum-scale=1.0,user-scalable=no">
<meta name="description" content="withU 是一个适合记录恋爱日常与纪念时刻的情侣小站，支持相册、时间轴、点滴文章、留言互动和邀请页面，让每一段关系都能拥有自己的专属回忆空间。">
<meta name="keywords" content="情侣网站,恋爱记录,祝福留言,情侣相册,恋爱清单,爱情纪念,情侣头像框,祝福语句,情侣互动,爱情相册,情侣事件记录,情侣留言,爱情故事,情感交流,用户互动,祝福卡片,音乐分享,甜蜜瞬间,情侣活动,爱情动态,withU">
<meta name="robots" content="index, follow">
<link rel="canonical" href="index.php">

<!-- Open Graph (Facebook/微信/QQ) -->
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?php echo htmlspecialchars($withuSiteTitle, ENT_QUOTES, 'UTF-8'); ?>">
<meta property="og:title" content="<?php echo htmlspecialchars($withuSiteTitle, ENT_QUOTES, 'UTF-8'); ?>">
<meta property="og:description" content="withU 是一个适合记录恋爱日常与纪念时刻的情侣小站，支持相册、时间轴、点滴文章、留言互动和邀请页面，让每一段关系都能拥有自己的专属回忆空间。">
<meta property="og:url" content="/index.php">
<meta property="og:image" content="withU">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo htmlspecialchars($withuSiteTitle, ENT_QUOTES, 'UTF-8'); ?>">
<meta name="twitter:description" content="withU 是一个适合记录恋爱日常与纪念时刻的情侣小站，支持相册、时间轴、点滴文章、留言互动和邀请页面，让每一段关系都能拥有自己的专属回忆空间。">
<meta name="twitter:image" content="withU">

    <!-- Google Fonts CDN 版本 -->
        <link rel="stylesheet" href="Style/vendor/google-fonts/google-fonts.css">
    <!-- 非 Google Fonts 字体（HarmonyOS Sans、汉仪粗仿宋）本地补充 -->
    <link rel="stylesheet" href="Style/vendor/google-fonts/fonts-non-google.css">

<!-- Font Awesome 本地化 -->
<link rel="stylesheet" href="Style/vendor/fontawesome/css/all.min.css">
<link rel="stylesheet" href="Style/css/leaving.css">
<link rel="stylesheet" href="Style/css/leav.css">
<link rel="stylesheet" href="Style/css/message.css">
<link rel="stylesheet" href="Style/css/index.css">
<link rel="stylesheet" href="Style/css/little.css">
<link rel="stylesheet" href="Style/css/loveImg.css">
<link rel="stylesheet" href="Style/css/list.css">
<link rel="stylesheet" href="Style/Font/font_list/iconfont.css">
<link rel="stylesheet" href="Style/toastify/toastify.min.css">
<link rel="stylesheet" href="Style/css/loadinglike.css">
<!-- AOS 本地化 -->
<link rel="stylesheet" href="Style/vendor/aos/aos.css">

<link rel="stylesheet" href="Style/css/plyr.css">
<link rel="stylesheet" href="Style/css/kicode.css">
<link rel="stylesheet" href="Style/css/phosphor-regular.css">
<link rel="stylesheet" href="Style/css/phosphor-icons.css">
<link rel="stylesheet" href="Style/css/phosphor-fill.css">
<link rel="stylesheet" href="Style/css/phosphor-duotone.css">
<!-- QWeather Icons 本地化 -->
<link rel="stylesheet" href="Style/vendor/qweather-icons/qweather-icons.css">
<link href="Style/css/nprogress.css" rel="stylesheet" type="text/css">
<!-- Remix Icon 本地化 -->
<link rel="stylesheet" href="Style/vendor/remixicon/remixicon.css">
<link rel="stylesheet" href="Style/css/tooltip.css">
<link rel="stylesheet" href="Style/css/interaction.css">
<link rel="stylesheet" href="Style/css/withu-home-style.css">
<link rel="stylesheet" href="Style/css/timetable.css?v=20260907-3">
<link rel="stylesheet" href="Style/css/withu-detail.css">
<link rel="stylesheet" href="Style/css/mobile-nav.css">
<link rel="stylesheet" href="Style/css/header.css">
<!-- 自定义右键菜单 -->
<link rel="stylesheet" href="Style/css/context-menu.css">
<!-- 足迹地图样式 -->
    <link rel="stylesheet" href="Style/css/map.css">

<script src="Style/jquery/jquery.min.js"></script>
<script src="Style/Font/font_leav/iconfont.js"></script>
<script src="Style/js/jquery.pjax.js" type="text/javascript"></script>
<script src="Style/js/plyr.js"></script>
<!-- AOS.js 本地化 -->
<script src="Style/vendor/aos/aos.js"></script>

<script src="Style/js/highlight.min.js"></script>
<script src="Style/js/lazyload.min.js"></script>
<script src="Style/js/masonry.pkgd.min.js"></script>
<script src="Style/js/imagesloaded.pkgd.min.js"></script>
<script src="Style/js/loading.js"></script>
<script src="Style/js/withu-owoui.js"></script>
<!-- 全局滚动锁工具（所有弹窗共用，防止滚动条消失时布局跳动） -->
<script>
(function(){
    var _count = 0;
    window.withuScrollLock = function(){
        _count++;
        if (_count === 1) {
            var w = window.innerWidth - document.documentElement.clientWidth;
            document.documentElement.style.setProperty('--withu-scrollbar-compensate', w + 'px');
            document.documentElement.classList.add('withu-scroll-locked');
        }
    };
    window.withuScrollUnlock = function(){
        _count = Math.max(0, _count - 1);
        if (_count === 0) {
            document.documentElement.classList.remove('withu-scroll-locked');
            document.documentElement.style.removeProperty('--withu-scrollbar-compensate');
        }
    };
    window.withuScrollReset = function(){
        _count = 0;
        document.documentElement.classList.remove('withu-scroll-locked');
        document.documentElement.style.removeProperty('--withu-scrollbar-compensate');
    };
})();
</script>
<link rel="stylesheet" href="Style/dplayer/DPlayer.min.css">
<link rel="stylesheet" href="Style/css/video-modal.css">
<script src="Style/dplayer/DPlayer.min.js"></script>
<script src="Style/js/video-modal.js"></script>
    <script src="https://static.geetest.com/v4/gt4.js"></script>
    <script src="Style/js/geetest-helper.js"></script>
    <script>if (typeof GeetestHelper !== 'undefined') GeetestHelper.setCaptchaId("8342edf0a8b10d336e5d0d2d6ede60d4");</script>
<script src="Style/js/nprogress.js"></script>
<!-- Canvas Confetti 本地化 -->
<script src="Style/vendor/confetti/confetti.browser.min.js"></script>
<!-- QRCode JS -->
<script src="Style/vendor/qrcode/qrcode.min.js"></script>
<!-- QR Code Styling (美化二维码) -->
<script src="Style/vendor/qr-code-styling/qr-code-styling.min.js"></script>

<!-- withU 核心框架 -->
<script>
    window.WITHU_CONFIG = Object.assign(window.WITHU_CONFIG || {}, <?php echo $withuConfigJson; ?>);

    // AOS 动画配置（供 app.js 的 AOSManager 使用）
    window.WITHU_AOS_CONFIG = {"enabled":true,"animation":"fade-up","duration":800,"delay":0,"interval":50,"maxDelay":300,"easing":"ease-out-cubic","offset":50,"once":true,"mirror":true,"anchorPlacement":"top-bottom"};

    window.WithUVisitorGeoCache = window.WithUVisitorGeoCache || (function () {
        var storageKey = 'withu_visitor_geo_v1';
        var cookieKey = 'withu_visitor_geo';
        var maxAgeMs = 6 * 60 * 60 * 1000;

        function normalize(payload) {
            if (!payload || typeof payload !== 'object') {
                return null;
            }

            var lat = Number(payload.lat);
            var lng = Number(payload.lng);
            var ts = Number(payload.ts || Date.now());
            var city = typeof payload.city === 'string' ? payload.city.trim() : '';

            if (!isFinite(lat) || !isFinite(lng)) {
                return null;
            }

            if (lat < -90 || lat > 90 || lng < -180 || lng > 180 || (lat === 0 && lng === 0)) {
                return null;
            }

            if (!isFinite(ts) || ts <= 0) {
                ts = Date.now();
            }

            return {
                lat: Number(lat.toFixed(6)),
                lng: Number(lng.toFixed(6)),
                ts: ts,
                city: city
            };
        }

        function writeCookie(payload) {
            var normalized = normalize(payload);
            if (!normalized) {
                return;
            }

            document.cookie = cookieKey + '=' + encodeURIComponent(JSON.stringify(normalized))
                + '; path=/; max-age=' + String(Math.floor(maxAgeMs / 1000))
                + '; SameSite=Lax';
        }

        function clear() {
            try {
                window.localStorage.removeItem(storageKey);
            } catch (err) {}

            document.cookie = cookieKey + '=; path=/; max-age=0; SameSite=Lax';
        }

        function getCached() {
            try {
                var raw = window.localStorage.getItem(storageKey);
                if (!raw) {
                    return null;
                }

                var parsed = JSON.parse(raw);
                var normalized = normalize(parsed);
                if (!normalized) {
                    return null;
                }

                if ((Date.now() - normalized.ts) > maxAgeMs) {
                    clear();
                    return null;
                }

                return normalized;
            } catch (err) {
                return null;
            }
        }

        function save(payload) {
            var normalized = normalize(payload);
            if (!normalized) {
                return null;
            }

            try {
                window.localStorage.setItem(storageKey, JSON.stringify(normalized));
            } catch (err) {}

            writeCookie(normalized);
            return normalized;
        }

        function syncCookieFromCache() {
            var cached = getCached();
            if (cached) {
                writeCookie(cached);
            }
            return cached;
        }

        return {
            storageKey: storageKey,
            cookieKey: cookieKey,
            maxAgeMs: maxAgeMs,
            getCached: getCached,
            save: save,
            clear: clear,
            syncCookieFromCache: syncCookieFromCache
        };
    })();

    window.WithUVisitorGeoCache.syncCookieFromCache();
</script>

<!-- 足迹地图配置（懒加载，点击才初始化） -->
        <script src="/assets/js/withu-shared-bc1ba20d.js"></script>
<script src="assets/js/app.js"></script>
<script src="/assets/js/withu-location.js?v=20260906e"></script>
<script src="/assets/js/head-avatar-location.js?v=20260906b"></script>
<?php if (!empty($loggedIn)): ?>
<!-- 对方正在看邀请气泡（头像区） -->
<script src="/assets/js/head-avatar-watch-bubble.js"></script>
<?php endif; ?>
<script src="assets/js/components.js"></script>

<!-- 礼花效果已迁移到 components.js 的 ConfettiEffect 模块 -->

<script src="assets/js/pjax.js"></script><script>if(window.WithUPjax&&typeof window.WithUPjax.init==="function")window.WithUPjax.init();</script>
<link rel="stylesheet" href="/assets/css/withu-shared-f1846031.css">

<script>
    // 倒计时、高度调整、轮播图、导航栏等功能已迁移到 app.js 和 components.js
    // 保留必要的全局变量供旧代码兼容
    var pcCarouselHeight = "80vh";
    var mobileCarouselHeight = "50vh";
    var pcPhotoCoverHeight = "80vh";
    var mobilePhotoCoverHeight = "60vh";
    var pcImgMaxHeight = "450px";
    var mobileImgMaxHeight = "260px";
</script>

<div id="loader-wrapper">
    <div id="loader"></div>
    <div class="loader-section"></div>
</div>

<?php include __DIR__ . '/inc/header.php'; ?>
<div id="homePage" class="wrap" data-Fullscreen>
    <ul class="list mask_black">
                <?php foreach ($homeCarouselImages as $homeCarouselIndex => $homeCarouselImg): ?>
                <li class="item<?php echo $homeCarouselIndex === 0 ? ' active' : ''; ?>">
                    <img class="lazy CarouselImage" data-src="<?php echo e($homeCarouselImg); ?>" draggable="false">
                </li>
                <?php endforeach; ?>
            </ul>

    <?php include __DIR__ . '/inc/head-avatars.php'; ?>


    <svg class="waves" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
        viewBox="0 24 150 28" preserveAspectRatio="none" shape-rendering="auto">
        <defs>
            <path id="gentle-wave" d="M-160 44c30 0 58-18 88-18s 58 18 88 18 58-18 88-18 58 18 88 18 v44h-352z" />
        </defs>
        <g class="parallax">
            <use xlink:href="#gentle-wave" x="48" y="0" fill="rgba(255,255,255,0.7" />
            <use xlink:href="#gentle-wave" x="48" y="3" fill="rgba(255,255,255,0.5)" />
            <use xlink:href="#gentle-wave" x="48" y="5" fill="rgba(255,255,255,0.3)" />
            <use xlink:href="#gentle-wave" x="48" y="7" fill="#fff" />
        </g>
    </svg>

    <ul class="pointList">
                <?php foreach ($homeCarouselImages as $homeCarouselIndex => $homeCarouselImg): ?>
                <li class="point<?php echo $homeCarouselIndex === 0 ? ' active' : ''; ?>" data-index="<?php echo $homeCarouselIndex; ?>"></li>
                <?php endforeach; ?>
            </ul>
</div>

<div class="Width_limit_10rem">
    <div class="withu-sticky-sentinel" id="withuStickySentinel"></div>
</div>

<div class="withu-nav-placeholder" id="withuNavPlaceholder"></div>
<div class="withu-nav-wrapper" id="withuNavWrapper">
    <nav class="withu-nav-island-container" id="withuNavIsland">
        <div class="withu-nav-indicator" id="withuNavIndicator"></div>

                <a href="articles.php"
           class="withu-nav-island-item  "
           draggable="false"
           data-desc="写下日常、心情与想念"
           data-meta="Memory Notes">
            <i class="ph-fill ph-notebook"></i>
            <span>点滴</span>        </a>
                <a href="messages.php"
           class="withu-nav-island-item  "
           draggable="false"
           data-desc="留下想说的话与温柔回应"
           data-meta="Kind Messages">
            <i class="ph-fill ph-chat-teardrop-dots"></i>
            <span>留言</span>        </a>
                <a href="timeline.php"
           class="withu-nav-island-item  "
           draggable="false"
           data-desc="回看我们一路走来的轨迹"
           data-meta="Steps of Us">
            <i class="ph-fill ph-clock-countdown"></i>
            <span>轨迹</span>        </a>
                <a href="index.php"
           class="withu-nav-island-item active nav-home"
           draggable="false"
           data-desc="收好我们的日常与心动"
           data-meta="Our Cozy Place">
            <i class="ph-fill ph-house"></i>
                    </a>
                <a href="albums.php"
           class="withu-nav-island-item  "
           draggable="false"
           data-desc="收藏见面与出游的闪亮瞬间"
           data-meta="Photo Keepsakes">
            <i class="ph-fill ph-camera"></i>
            <span>相册</span>        </a>
                <a href="lovelist.php"
           class="withu-nav-island-item  "
           draggable="false"
           data-desc="记下想一起完成的心愿"
           data-meta="Plans Together">
            <i class="ph-fill ph-list-checks"></i>
            <span>清单</span>        </a>
                <a href="about.php"
           class="withu-nav-island-item  "
           draggable="false"
           data-desc="用对话回放我们的故事"
           data-meta="Story Replay">
            <i class="ph-fill ph-book-open-text"></i>
            <span>关于</span>        </a>
            </nav>
</div>

<div class="Width_limit_10rem">
    <div class="withu-page-header">
        <div class="withu-meta-container">
            <div class="withu-meta-tag" id="withuMetaTag">
                <i class="fa-solid fa-star-of-life withu-meta-icon"></i>
                <span id="withuMetaText">Sanctuary of Us</span>
                <i class="fa-solid fa-star-of-life withu-meta-icon"></i>
            </div>
            <div class="withu-meta-line" id="withuMetaLine"></div>
        </div>
        <h2 class="withu-hero-title" id="withuHeroTitle"></h2>
    </div>
</div>

<!-- 情侣头像点击效果已迁移到 components.js 的 AvatarInteraction 模块 -->

<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($withuSiteTitle, ENT_QUOTES, 'UTF-8'); ?></title>

</head>

<body class="bg-pdot-vignette">
    <?php if (isset($_GET['logout']) && $_GET['logout'] === '1'): ?>
    <script>document.addEventListener('DOMContentLoaded',function(){if(typeof Toastify!=='undefined'&&Toastify.showScenario)Toastify.showScenario('success',{text:'已退出登录'});});</script>
    <?php endif; ?>
    <div id="pjax-container">
        <main class="withu-home withu-container">

            <!-- Countdown Module -->
            <div class="withu-day-wrapper withu-mb-4" data-aos="fade-up" data-aos-delay="0">
                <div class="withu-day-fusion-card">
                    <!-- 朦胧光斑 -->
                    <div class="withu-day-ambient-light"></div>
                    <!-- Mac 装饰点 -->
                    <div class="withu-day-mac-dots">
                        <div class="withu-day-dot withu-day-dot-red"></div>
                        <div class="withu-day-dot withu-day-dot-yellow"></div>
                        <div class="withu-day-dot withu-day-dot-green"></div>
                    </div>
                    <!-- 左侧 -->
                    <div class="withu-day-left-section">
                        <div class="withu-day-title-container">
                            <h2 class="withu-day-poetic-title">
                                朝暮与年岁并往， <br />
与你行至天光。                            </h2>
                        </div>
                        <!-- 起始日 -->
                        <div class="withu-day-start-date-capsule">
                            <div class="withu-day-icon-circle">
                                <i class="ph-fill ph-heart"></i>
                            </div>
                            <div class="withu-day-date-text-group">
                                <span class="withu-day-date-label-small">Together Since</span>
                                <span class="withu-day-date-value-clean"
                                    id="withu-day-start-date-display"><?php echo htmlspecialchars($withuStartDateDisplay, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </div>
                    </div>
                    <!-- 右侧 -->
                    <div class="withu-day-right-section">
                        <div class="withu-day-main-days-wrapper">
                            <div class="withu-day-main-days-number" id="withu-day-counter-days">0</div>
                            <div class="withu-day-days-divider"></div>
                            <div class="withu-day-days-label">DAYS</div>
                        </div>
                        <div class="withu-day-digital-timer">
                            <div class="withu-day-timer-block">
                                <div class="withu-day-timer-val" id="withu-day-counter-hours">00</div>
                                <div class="withu-day-timer-label">Hours</div>
                            </div>
                            <div class="withu-day-timer-block">
                                <div class="withu-day-timer-val" id="withu-day-counter-minutes">00</div>
                                <div class="withu-day-timer-label">Minutes</div>
                            </div>
                            <div class="withu-day-timer-block">
                                <div class="withu-day-timer-val" id="withu-day-counter-seconds">00</div>
                                <div class="withu-day-timer-label">Seconds</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 1. Top Section: Staggered Masonry -->
            <section class="withu-section">
                <div class="withu-grid">

                    <!-- Card 1: Moment of the Day -->
                                        <div class="withu-col-2 withu-row-2" data-aos="fade-up" data-aos-delay="0">                    <!-- 智能媒体卡片：时光碎片 -->
                    <div id="moment-card" class="withu-smart-card">
                        <!-- 媒体容器 -->
                        <div class="withu-smart-card__media"></div>
                        <!-- 遮罩层 -->
                        <div class="withu-smart-card__overlay"></div>

                        <!-- 顶部：发布者信息 + 相册入口 -->
                        <div class="withu-smart-card__header">
                            <div class="withu-smart-card__capsule">
                                <img class="withu-smart-card__avatar lazy" src="" alt="">
                                <div class="withu-smart-card__user-info">
                                    <span class="withu-smart-card__name"></span>
                                    <span class="withu-smart-card__time"></span>
                                </div>
                            </div>
                            <!-- 相册入口链接 -->
                            <a href="albums.php" class="withu-smart-card__album-link">
                                <span>进入相册</span>
                                <i class="ph-bold ph-arrow-right"></i>
                            </a>
                        </div>

                        <!-- 底部：内容区域 -->
                        <div class="withu-smart-card__content">
                            <!-- 地点胶囊 -->
                            <div class="withu-smart-card__location-pill">
                                <i class="ph-fill ph-map-pin"></i>
                                <span class="withu-smart-card__location-text"></span>
                            </div>
                            <!-- 标题 -->
                            <h2 class="withu-smart-card__title"></h2>
                            <!-- 元数据 -->
                            <div class="withu-smart-card__meta">
                                <span class="withu-smart-card__date"></span>
                                <p class="withu-smart-card__desc"></p>
                            </div>
                        </div>

                        <!-- 切换按钮 -->
                        <div class="withu-smart-card__switch-btn-container">
                            <button class="withu-smart-card__switch-btn" type="button">
                                <i class="ph-bold ph-arrows-clockwise"></i>
                            </button>
                        </div>
                    </div>
                    </div>
                                                            <!-- Card 2: Weather -->
                    <div class="withu-col-2 withu-col-md-1 withu-weather-wrapper" data-aos="fade-up" data-aos-delay="50">                    <div class="withu-home-weather-card blue" data-weather-slot="1" data-location-name="--">

                        <!-- 装饰背景 -->
                        <div class="withu-home-weather-bg-decoration"></div>

                        <!-- 顶部：用户 + 时间 -->
                        <div class="withu-home-weather-row-top">
                            <div class="withu-home-weather-user-pill">
                                <img src="Lovefolder/20260411043037_69d95ded97293201118237.webp"
                                    class="withu-home-weather-avatar" alt="Ki.">
                                <span class="withu-home-weather-username">Ki.</span>
                            </div>
                            <div class="withu-home-weather-time-tag">--</div>
                        </div>

                        <!-- 核心区：温度 + 图标 -->
                        <div class="withu-home-weather-row-main">
                            <div class="withu-home-weather-text-temp">--°</div>
                            <i class="qi-100-fill withu-home-weather-icon-main"></i>
                        </div>

                        <!-- 地址信息 -->
                        <div class="withu-home-weather-row-location">
                            <i class="ph-fill ph-map-pin withu-home-weather-icon-pin"></i>
                            <span class="withu-home-weather-text-city">--</span>
                            <span class="withu-home-weather-dot-divider">•</span>
                            <span class="withu-home-weather-text-status">--</span>
                        </div>

                        <!-- 底部指标 -->
                        <div class="withu-home-weather-grid-stats">
                            <div class="withu-home-weather-stat-pill">
                                <i class="ph-fill ph-drop withu-home-weather-icon-stat"></i>
                                <span class="withu-home-weather-text-stat stat-humidity">--%</span>
                            </div>
                            <div class="withu-home-weather-stat-pill">
                                <i class="ph-fill ph-eye withu-home-weather-icon-stat"></i>
                                <span class="withu-home-weather-text-stat stat-vis">--km</span>
                            </div>
                            <div class="withu-home-weather-stat-pill">
                                <i class="ph-fill ph-thermometer withu-home-weather-icon-stat"></i>
                                <span class="withu-home-weather-text-stat stat-feels">--°</span>
                            </div>
                        </div>
                    </div>
                    </div>
                    <!-- Card 3: Weather (双人模式) -->
                                        <div class="withu-col-2 withu-col-md-1 withu-weather-wrapper" data-aos="fade-up" data-aos-delay="100">                    <div class="withu-home-weather-card orange" data-weather-slot="2" data-location-name="--">

                        <div class="withu-home-weather-bg-decoration"></div>

                        <div class="withu-home-weather-row-top">
                            <div class="withu-home-weather-user-pill">
                                <img src="Lovefolder/20260411043046_69d95df639c33274072975.webp"
                                    class="withu-home-weather-avatar" alt="Really">
                                <span class="withu-home-weather-username">Really</span>
                            </div>
                            <div class="withu-home-weather-time-tag">--</div>
                        </div>

                        <div class="withu-home-weather-row-main">
                            <div class="withu-home-weather-text-temp">--°</div>
                            <i class="qi-100-fill withu-home-weather-icon-main"></i>
                        </div>

                        <div class="withu-home-weather-row-location">
                            <i class="ph-fill ph-map-pin withu-home-weather-icon-pin"></i>
                            <span class="withu-home-weather-text-city">--</span>
                            <span class="withu-home-weather-dot-divider">•</span>
                            <span class="withu-home-weather-text-status">--</span>
                        </div>

                        <div class="withu-home-weather-grid-stats">
                            <div class="withu-home-weather-stat-pill">
                                <i class="ph-fill ph-drop withu-home-weather-icon-stat"></i>
                                <span class="withu-home-weather-text-stat stat-humidity">--%</span>
                            </div>
                            <div class="withu-home-weather-stat-pill">
                                <i class="ph-fill ph-eye withu-home-weather-icon-stat"></i>
                                <span class="withu-home-weather-text-stat stat-vis">--km</span>
                            </div>
                            <div class="withu-home-weather-stat-pill">
                                <i class="ph-fill ph-thermometer withu-home-weather-icon-stat"></i>
                                <span class="withu-home-weather-text-stat stat-feels">--°</span>
                            </div>
                        </div>
                    </div>
                    </div>                                        
                    <!-- Card 4: Love List -->
                    <div class="withu-col-2" data-aos="fade-up" data-aos-delay="150">                    <div class="withu-widget withu-widget--lovelist">
                        <div class="withu-widget__bg-icon withu-lovelist-bg-icon">
                            <i class="ph-fill ph-shooting-star"></i>
                        </div>

                        <div class="withu-flex-col-between-relative">
                            <div class="withu-flex-between-center withu-mb-4">
                                <div class="withu-flex-center-gap">
                                    <div class="withu-icon-box-glass">
                                        <i class="ph-bold ph-list-heart withu-icon-md-white"></i>
                                    </div>
                                    <div class="withu-card-title-lg">清单</div>
                                </div>
                                <div class="withu-card-subtitle">Plans Together</div>
                            </div>

                            <div class="withu-lovelist-bottom">
                                <div class="withu-lovelist-stats">
                                    <div class="withu-lovelist-fraction withu-font-num">
                                        <span
                                            class="withu-lovelist-completed">23</span>
                                        <span class="withu-lovelist-divider">/</span>
                                        <span
                                            class="withu-lovelist-total">36</span>
                                    </div>

                                    <div class="withu-font-num withu-num-huge">
                                        <span>64</span><span
                                            class="withu-num-suffix">%</span>
                                    </div>
                                </div>

                                <div class="withu-progress withu-progress-sm">
                                    <div class="withu-progress__bar withu-progress-fill-white"
                                        style="width: 64%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>
                    <!-- Stats Cards -->
                    <div class="withu-col-2 withu-col-md-1" data-aos="fade-up" data-aos-delay="200">                    <div class="withu-widget withu-widget--stats-vibrant-1">
                        <div class="withu-widget__bg-icon withu-widget__bg-icon--tilted">
                            <i class="ph-fill ph-article"></i>
                        </div>
                        <div class="withu-flex-col-between-1">
                            <div>
                                <div class="withu-stats-header-row">
                                    <div class="withu-icon-circle-glass">
                                        <i class="ph-bold ph-newspaper-clipping withu-icon-sm-white"></i>
                                    </div>
                                    <div class="withu-stats-title" data-withu-tip="点滴">点滴</div>
                                </div>
                            </div>
                            <div class="withu-mt-1rem">
                                <div class="withu-font-num withu-stats-num">
                                    10                                </div>
                                <div class="withu-stats-label withu-stats-label--en">Memory Notes</div>
                            </div>
                        </div>
                    </div>
                    </div>
                    <div class="withu-col-2 withu-col-md-1" data-aos="fade-up" data-aos-delay="250">                    <div class="withu-widget withu-widget--stats-vibrant-2">
                        <div class="withu-widget__bg-icon withu-widget__bg-icon--tilted">
                            <i class="ph-fill ph-images"></i>
                        </div>
                        <div class="withu-flex-col-between-1">
                            <div>
                                <div class="withu-stats-header-row">
                                    <div class="withu-icon-circle-glass">
                                        <i class="ph-bold ph-camera withu-icon-sm-white"></i>
                                    </div>
                                    <div class="withu-stats-title" data-withu-tip="相册">相册</div>
                                </div>
                            </div>
                            <div class="withu-mt-1rem">
                                <div class="withu-font-num withu-stats-num">
                                    144                                </div>
                                <div class="withu-stats-label withu-stats-label--en">Photo Keepsakes</div>
                            </div>
                        </div>
                    </div>
                    </div>
                    <div class="withu-col-2 withu-col-md-1" data-aos="fade-up" data-aos-delay="300">                    <div class="withu-widget withu-widget--stats-vibrant-3">
                        <div class="withu-widget__bg-icon withu-widget__bg-icon--tilted">
                            <i class="ph-fill ph-chat-circle-dots"></i>
                        </div>
                        <div class="withu-flex-col-between-1">
                            <div>
                                <div class="withu-stats-header-row">
                                    <div class="withu-icon-circle-glass">
                                        <i class="ph-bold ph-chat-teardrop-dots withu-icon-sm-white"></i>
                                    </div>
                                    <div class="withu-stats-title" data-withu-tip="留言">留言</div>
                                </div>
                            </div>
                            <div class="withu-mt-1rem">
                                <div class="withu-font-num withu-stats-num">
                                    184                                </div>
                                <div class="withu-stats-label withu-stats-label--en">Kind Messages</div>
                            </div>
                        </div>
                    </div>
                    </div>
                    <div class="withu-col-2 withu-col-md-1" data-aos="fade-up" data-aos-delay="300">                    <div class="withu-widget withu-widget--stats-vibrant-4">
                        <div class="withu-widget__bg-icon withu-widget__bg-icon--tilted">
                            <i class="ph-fill ph-hourglass-medium"></i>
                        </div>
                        <div class="withu-flex-col-between-1">
                            <div>
                                <div class="withu-stats-header-row">
                                    <div class="withu-icon-circle-glass">
                                        <i class="ph-bold ph-timer withu-icon-sm-white"></i>
                                    </div>
                                    <div class="withu-stats-title" data-withu-tip="轨迹">轨迹</div>
                                </div>
                            </div>
                            <div class="withu-mt-1rem">
                                <div class="withu-font-num withu-stats-num">
                                    4                                </div>
                                <div class="withu-stats-label withu-stats-label--en">Steps of Us</div>
                            </div>
                        </div>
                    </div>
                    </div>
                    <div class="withu-col-2 withu-col-md-1" data-aos="fade-up" data-aos-delay="300">                    <div class="withu-widget withu-widget--stats-vibrant-5">
                        <div class="withu-widget__bg-icon withu-widget__bg-icon--tilted">
                            <i class="ph-fill ph-heart"></i>
                        </div>
                        <div class="withu-traffic-card">
                            <div class="withu-stats-header-row">
                                <div class="withu-icon-circle-glass">
                                    <i class="ph-bold ph-chart-line-up withu-icon-sm-white"></i>
                                </div>
                                <div class="withu-stats-title" data-withu-tip="今日访问">今日访问</div>
                            </div>
                            <div class="withu-traffic-metrics">
                                <div class="withu-traffic-metric"
                                    data-withu-tip="访问次数：<?php echo $todayViews; ?>" data-withu-tip-force="true">
                                    <div class="withu-font-num withu-traffic-value">
                                        <?php echo $todayViews; ?>                                    </div>
                                    <div class="withu-traffic-label">访问次数</div>
                                </div>
                                <div class="withu-traffic-divider" aria-hidden="true"></div>
                                <div class="withu-traffic-metric"
                                    data-withu-tip="今日访客：<?php echo $todayVisitors; ?>" data-withu-tip-force="true">
                                    <div class="withu-font-num withu-traffic-value">
                                        <?php echo $todayVisitors; ?>                                    </div>
                                    <div class="withu-traffic-label">今日访客</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>
                    <div class="withu-col-2 withu-col-md-1" data-aos="fade-up" data-aos-delay="300">                    <div class="withu-widget withu-widget--stats-vibrant-7">
                        <div class="withu-widget__bg-icon withu-widget__bg-icon--tilted">
                            <i class="ph-fill ph-eye"></i>
                        </div>
                        <div class="withu-traffic-card">
                            <div class="withu-stats-header-row">
                                <div class="withu-icon-circle-glass">
                                    <i class="ph-bold ph-users-three withu-icon-sm-white"></i>
                                </div>
                                <div class="withu-stats-title" data-withu-tip="累计访问">累计访问</div>
                            </div>
                            <div class="withu-traffic-metrics">
                                <div class="withu-traffic-metric"
                                    data-withu-tip="总访客数：<?php echo $totalVisitors; ?>" data-withu-tip-force="true">
                                    <div class="withu-font-num withu-traffic-value">
                                        <?php echo $totalVisitors; ?>                                    </div>
                                    <div class="withu-traffic-label">总访客数</div>
                                </div>
                                <div class="withu-traffic-divider" aria-hidden="true"></div>
                                <div class="withu-traffic-metric"
                                    data-withu-tip="总访问次：<?php echo $totalViews; ?>" data-withu-tip-force="true">
                                    <div class="withu-font-num withu-traffic-value">
                                        <?php echo $totalViews; ?>                                    </div>
                                    <div class="withu-traffic-label">总访问次</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>
                    <div class="withu-col-2" data-aos="fade-up" data-aos-delay="300">                    <div class="withu-widget withu-widget--stats-vibrant-6">
                        <div class="withu-widget__bg-icon withu-runtime-bg-icon">
                            <i class="ph-fill ph-planet"></i>
                        </div>

                        <div class="withu-flex-col-runtime">
                            <div>
                                <div class="withu-header-row-sm">
                                    <div class="withu-icon-circle-glass">
                                        <i class="ph-bold ph-planet withu-icon-sm-white"></i>
                                    </div>
                                    <div class="withu-stats-title" data-withu-tip="我们的小世界">我们的小世界</div>
                                </div>
                            </div>

                            <div class="withu-mt-auto">
                                <div class="withu-runtime-values">
                                    <div class="withu-font-num withu-runtime-num">
                                        1,115                                    </div>

                                    <div class="withu-runtime-meta">
                                        <div class="withu-runtime-days">DAYS</div>
                                        <span class="withu-runtime-divider"></span>
                                        <div class="withu-runtime-text">已平稳运行</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>                </div>
            </section>

            <!-- 课表 Module（与轻屿课表 App 周视图一致，仅登录可见；数据由 page-timetable.js 拉取） -->
            <?php if (false): // 课表前端展示暂时注释，恢复时改回 !empty($loggedIn) ?>
            <section id="timetable-section" class="withu-section" style="display:none;">
                <div class="withu-section-header withu-section-header--blue" data-aos="fade-up" data-aos-delay="0">
                    <div class="withu-section-header__left">
                        <h2 class="withu-section-title withu-section-title-color-blue withu-flex-center">
                            <div class="withu-section-icon-box withu-section-icon-box--blue">
                                <i class="ph-fill ph-calendar-blank withu-icon-md-white"></i>
                            </div>
                            <span>课表</span>
                        </h2>
                    </div>
                    <div class="withu-section-header__right withu-tt-header-actions">
                        <button type="button" class="withu-tt-bg-btn" id="withu-tt-history-btn" aria-label="修改记录">
                            <i class="ph-bold ph-clock-counter-clockwise"></i>
                        </button>
                        <button type="button" class="withu-tt-bg-btn" id="withu-tt-bg-btn" aria-label="调整页面背景" aria-expanded="false">
                            <i class="ph-bold ph-sliders-horizontal"></i>
                        </button>
                        <div class="withu-tt-tabs" id="withu-tt-tabs"></div>
                    </div>
                </div>
                <div class="withu-tt-card" id="withu-tt-card" data-aos="fade-up" data-aos-delay="50">
                    <div class="withu-tt-loading" id="withu-tt-loading">
                        <div class="withu-tt-spinner"></div>
                        <span>课表加载中…</span>
                    </div>
                    <div class="withu-tt-body" id="withu-tt-body"></div>
                </div>
            </section>
            <?php endif; ?>

                        <section id="events" class="withu-section">
                <div class="withu-section-header withu-section-header--rose" data-aos="fade-up" data-aos-delay="0">
                    <div class="withu-section-header__left">
                        <h2 class="withu-section-title withu-section-title-color-rose withu-flex-center">
                            <div class="withu-section-icon-box withu-section-icon-box--rose">
                                <i class="ph-fill ph-heart withu-icon-md-white"></i>
                            </div>
                            <span>清单</span>
                            <span class="withu-badge-new">NEW</span>
                        </h2>
                    </div>
                    <div class="withu-section-header__right">
                        <a href="lovelist.php" class="withu-link-more">
                            <i class="ph-bold ph-arrow-right"></i>
                        </a>
                    </div>
                </div>
                <div class="withu-events-grid">
                    
                                                    <!-- 有图片样式 -->
                            <div data-aos="fade-up" data-aos-delay="0">                            <a href="lovelist.php#event-36"
                                class="withu-event-card withu-event-card--has-img withu-event-card--link">
                                <img class="withu-event-bg-img lazy" data-src="Lovefolder/20260411053515_69d96d13af174939872800.webp"
                                    alt="一起去吃淘蛙">
                                <div class="withu-event-overlay"></div>
                                <div class="withu-event-content">
                                    <div>
                                        <div class="withu-event-icon">
                                            <i class="ph-fill ph-heart"></i>
                                        </div>
                                    </div>
                                    <div class="withu-event-content-mt">
                                        <h3
                                            class="withu-event-title withu-text-white withu-text-xl withu-font-bold withu-mb-1">
                                            一起去吃淘蛙                                        </h3>
                                        <div
                                            class="withu-event-note withu-text-white withu-opacity-80 withu-event-note-sm">
                                            好像一直都在吃的路上 个个不重样                                        </div>
                                    </div>
                                    <div class="withu-event-footer-glass">
                                        <span class="withu-chip withu-chip--glass"><i class="ph-fill ph-map-pin"></i>
                                            淘蛙(长安万达店)</span>
                                        <span class="withu-chip withu-chip--glass"><i class="ph-fill ph-calendar-blank"></i>
                                            2026-04-11</span>
                                    </div>
                                </div>
                            </a>
                            </div>
                        
                    
                                                    <!-- 有图片样式 -->
                            <div data-aos="fade-up" data-aos-delay="50">                            <a href="lovelist.php#event-35"
                                class="withu-event-card withu-event-card--has-img withu-event-card--link">
                                <img class="withu-event-bg-img lazy" data-src="Lovefolder/20260409211730_69d7a6eaecf46322029252.webp"
                                    alt="测试修改问题 2.0.7">
                                <div class="withu-event-overlay"></div>
                                <div class="withu-event-content">
                                    <div>
                                        <div class="withu-event-icon">
                                            <i class="ph-fill ph-heart"></i>
                                        </div>
                                    </div>
                                    <div class="withu-event-content-mt">
                                        <h3
                                            class="withu-event-title withu-text-white withu-text-xl withu-font-bold withu-mb-1">
                                            测试修改问题 2.0.7                                        </h3>
                                        <div
                                            class="withu-event-note withu-text-white withu-opacity-80 withu-event-note-sm">
                                            又一个美好的回忆                                        </div>
                                    </div>
                                    <div class="withu-event-footer-glass">
                                        <span class="withu-chip withu-chip--glass"><i class="ph-fill ph-map-pin"></i>
                                            高州市</span>
                                        <span class="withu-chip withu-chip--glass"><i class="ph-fill ph-calendar-blank"></i>
                                            2026-02-17</span>
                                    </div>
                                </div>
                            </a>
                            </div>
                        
                    
                                                    <!-- 未完成/锁定样式 -->
                            <div data-aos="fade-up" data-aos-delay="100">                            <a href="lovelist.php#event-34"
                                class="withu-event-card withu-event-card--locked withu-event-card--link">
                                <div class="withu-event-content">
                                    <div class="withu-flex-between-start">
                                        <div class="withu-event-icon">
                                            <i class="ph-duotone ph-lock-key"></i>
                                        </div>
                                        <i class="ph-fill ph-lock-key withu-event-seal"></i>
                                    </div>
                                    <div class="withu-event-content-mt">
                                        <h3 class="withu-event-title withu-text-xl withu-font-bold withu-mb-1">
                                            测试最新版本新增问题                                        </h3>
                                        <div class="withu-event-note withu-event-note-color">
                                            测试一下                                        </div>
                                    </div>
                                    <div class="withu-event-footer-light">
                                        <span class="withu-chip withu-chip--light"><i class="ph-bold ph-map-pin"></i>
                                            未设置</span>
                                        <span class="withu-chip withu-chip--light"><i class="ph-bold ph-calendar-blank"></i>
                                            待解锁</span>
                                    </div>
                                </div>
                            </a>
                            </div>                        
                    
                                                    <!-- 有图片样式 -->
                            <div data-aos="fade-up" data-aos-delay="150">                            <a href="lovelist.php#event-33"
                                class="withu-event-card withu-event-card--has-img withu-event-card--link">
                                <img class="withu-event-bg-img lazy" data-src="Lovefolder/20260411053340_69d96cb4ec19c441742336.webp"
                                    alt="一起去吃海底捞">
                                <div class="withu-event-overlay"></div>
                                <div class="withu-event-content">
                                    <div>
                                        <div class="withu-event-icon">
                                            <i class="ph-fill ph-heart"></i>
                                        </div>
                                    </div>
                                    <div class="withu-event-content-mt">
                                        <h3
                                            class="withu-event-title withu-text-white withu-text-xl withu-font-bold withu-mb-1">
                                            一起去吃海底捞                                        </h3>
                                        <div
                                            class="withu-event-note withu-text-white withu-opacity-80 withu-event-note-sm">
                                            太好了 你妹也在这里了                                        </div>
                                    </div>
                                    <div class="withu-event-footer-glass">
                                        <span class="withu-chip withu-chip--glass"><i class="ph-fill ph-map-pin"></i>
                                            海底捞火锅(长安万科店)</span>
                                        <span class="withu-chip withu-chip--glass"><i class="ph-fill ph-calendar-blank"></i>
                                            2026-04-11</span>
                                    </div>
                                </div>
                            </a>
                            </div>
                        
                                    </div>
            </section>
            
            <!-- Love Day List -->
                            <section id="loveday-list" class="withu-section">
                    <div class="withu-section-header withu-section-header--purple" data-aos="fade-up" data-aos-delay="0">
                        <div class="withu-section-header__left">
                            <h2 class="withu-section-title withu-section-title-color-purple withu-flex-center">
                                <div class="withu-section-icon-box withu-section-icon-box--purple">
                                    <i class="ph-fill ph-calendar withu-icon-md-white"></i>
                                </div>
                                <span>Love Day</span>
                                <span class="withu-badge-new">FULL</span>
                            </h2>
                        </div>
                        <div class="withu-section-header__right">
                            <!-- iOS Style Tab Switcher with Slider -->
                            <div class="withu-ios-tabs">
                                <div class="withu-ios-tabs-slider"></div>
                                <button class="withu-ios-tab active" data-filter="all"
                                    onclick="filterLoveDays('all', this)">
                                    <i class="ph-fill ph-squares-four"></i>
                                    <span>全部</span>
                                </button>
                                <button class="withu-ios-tab" data-filter="past" onclick="filterLoveDays('past', this)">
                                    <i class="ph-fill ph-heart"></i>
                                    <span>纪念日</span>
                                </button>
                                <button class="withu-ios-tab" data-filter="future"
                                    onclick="filterLoveDays('future', this)">
                                    <i class="ph-fill ph-hourglass"></i>
                                    <span>倒计时</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="withu-grid withu-loveday-grid">
                                                    <div data-aos="fade-up" data-aos-delay="0">                            <div class="withu-widget withu-widget--loveday-vibrant withu-widget--loveday-future">

                                                                <div class="withu-loveday-sup-label">
                                    还有                                </div>
                                <!-- Decorative BG Icon -->
                                                                <svg class="withu-loveday-bg-icon" viewBox="0 0 1024 1024" version="1.1"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M792 120H232a40 40 0 0 0-40 40v56c0 88.4 71.6 160 160 160 5.2 0 10.4-.2 15.6-.6 4.4 11.8 4.4 24.8 0 36.6-5.2-.4-10.4-.6-15.6-.6-88.4 0-160 71.6-160 160v56a40 40 0 0 0 40 40h560a40 40 0 0 0 40-40v-56c0-88.4-71.6-160-160-160-5.2 0-10.4.2-15.6.6-4.4-11.8-4.4-24.8 0-36.6 5.2.4 10.4.6 15.6.6 88.4 0 160-71.6 160-160v-56a40 40 0 0 0-40-40z" fill="currentColor"></path>
                                </svg>

                                <div class="withu-flex-between-center withu-loveday-content">
                                    <div class="withu-flex-center-gap"
                                        tabindex="0">
                                        <div class=" withu-icon-box-glass-white">
                                                                                            <svg viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg">
                                                    <path
                                                        d="M810 249.5c-38.6-38.6-83.5-68.8-133.5-90-51.8-21.9-106.8-33-163.5-33s-111.7 11.1-163.5 33c-50 21.2-94.9 51.4-133.5 90-38.6 38.6-68.8 83.5-90 133.5-21.9 51.8-33 106.8-33 163.5s11.1 111.7 33 163.5c21.2 50 51.4 94.9 90 133.5s83.5 68.8 133.5 90c51.8 21.9 106.8 33 163.5 33s111.7-11.1 163.5-33c50-21.2 94.9-51.4 133.5-90S878.8 760 900 710c21.9-51.8 33-106.8 33-163.5S921.9 434.8 900 383c-21.2-50-51.5-94.9-90-133.5z m-297 657c-198.5 0-360-161.5-360-360s161.5-360 360-360 360 161.5 360 360-161.5 360-360 360zM357 96.5c-42.3-49.6-141-53.3-208.1 4s-77.3 153.9-35 203.5L357 96.5zM877.2 100.5C810 43.2 711.3 47 669 96.5L912.2 304c42.3-49.6 32.1-146.2-35-203.5z">
                                                    </path>
                                                    <path
                                                        d="M667.1 558.6H543V351c0-17.9-14.5-32.4-32.4-32.4-15.2 0-27.6 12.3-27.6 27.6v272.4h182.2c17.1 0 30.9-13.8 30.9-30.9 0-16.1-13-29.1-29-29.1z">
                                                    </path>
                                                </svg>
                                                                                    </div>
                                        <div class="withu-loveday-copy">
                                            <div class="withu-loveday-title" data-withu-tip="咕噜的一岁生日">
                                                咕噜的一岁生日                                            </div>
                                            <div class="withu-loveday-date">
                                                <span class="withu-loveday-date-line">
                                                    目标日：                                                    2027-07-11                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="withu-text-right">
                                        <div class="withu-loveday-count">
                                                                                            339<span class="withu-loveday-unit">天</span>
                                                                                    </div>
                                                                                    <div class="withu-loveday-lunar-inline">
                                                农历 六月初八                                            </div>
                                                                            </div>
                                </div>
                            </div>
                            </div>                                                    <div data-aos="fade-up" data-aos-delay="50">                            <div class="withu-widget withu-widget--loveday-vibrant withu-widget--loveday-past">

                                                                <div class="withu-loveday-sup-label">
                                    已经                                </div>
                                <!-- Decorative BG Icon -->
                                                                <svg class="withu-loveday-bg-icon" viewBox="0 0 1024 1024" version="1.1"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M923 283.6a260.04 260.04 0 0 0-56.9-82.6c-64.5-70-170.8-84-245.5-32.9L512 216.7l-108.6-48.6c-74.7-51.1-181-37.1-245.5 32.9-64.5 70-79.9 174.6-44.1 262.8 33.3 82.3 98.7 151.7 185.3 227.1L512 884.2l212.9-193.3c86.6-75.4 152-144.8 185.3-227.1 35.8-88.2 20.4-192.8-44.1-262.8z" fill="currentColor"></path>
                                </svg>

                                <div class="withu-flex-between-center withu-loveday-content">
                                    <div class="withu-flex-center-gap"
                                        tabindex="0">
                                        <div class=" withu-icon-box-glass-white">
                                                                                            <svg viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg">
                                                    <path
                                                        d="M470.4 204.8l44.8 44.8 44.8-44.8c99.2-99.2 262.4-99.2 361.6 0 48 48 73.6 112 73.6 179.2 0 19.2-12.8 32-32 32s-32-12.8-32-32c0-51.2-19.2-99.2-57.6-134.4-73.6-73.6-195.2-73.6-272 0l-67.2 67.2c-12.8 12.8-32 12.8-44.8 0l-67.2-67.2c-73.6-73.6-195.2-73.6-272 0-73.6 73.6-73.6 195.2 0 272L512 883.2c12.8 12.8 12.8 32 0 44.8s-32 12.8-44.8 0L105.6 566.4c-99.2-99.2-99.2-262.4 0-361.6 102.4-102.4 262.4-102.4 364.8 0z m176 710.4L425.6 694.4c-57.6-57.6-57.6-147.2 0-204.8 57.6-57.6 147.2-57.6 204.8 0l57.6 57.6 57.6-57.6c57.6-57.6 147.2-57.6 204.8 0 57.6 57.6 57.6 147.2 0 204.8L729.6 915.2c-9.6 9.6-25.6 16-38.4 16-19.2 0-32-6.4-44.8-16z m256-265.6c32-32 32-83.2 0-112-32-32-83.2-32-112 0l-80 80c-12.8 12.8-32 12.8-44.8 0l-80-80c-32-32-83.2-32-112 0-32 32-32 83.2 0 112L688 864l214.4-214.4z"
                                                        fill="#ffffff"></path>
                                                </svg>
                                                                                    </div>
                                        <div class="withu-loveday-copy">
                                            <div class="withu-loveday-title" data-withu-tip="拉拉扯扯一周年啦">
                                                拉拉扯扯一周年啦                                            </div>
                                            <div class="withu-loveday-date">
                                                <span class="withu-loveday-date-line">
                                                    起始日：                                                    2024-07-19                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="withu-text-right">
                                        <div class="withu-loveday-count">
                                                                                            748<span class="withu-loveday-unit">天</span>
                                                                                    </div>
                                                                            </div>
                                </div>
                            </div>
                            </div>                                                    <div data-aos="fade-up" data-aos-delay="100">                            <div class="withu-widget withu-widget--loveday-vibrant withu-widget--loveday-past">

                                                                <div class="withu-loveday-sup-label">
                                    已经                                </div>
                                <!-- Decorative BG Icon -->
                                                                <svg class="withu-loveday-bg-icon" viewBox="0 0 1024 1024" version="1.1"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M923 283.6a260.04 260.04 0 0 0-56.9-82.6c-64.5-70-170.8-84-245.5-32.9L512 216.7l-108.6-48.6c-74.7-51.1-181-37.1-245.5 32.9-64.5 70-79.9 174.6-44.1 262.8 33.3 82.3 98.7 151.7 185.3 227.1L512 884.2l212.9-193.3c86.6-75.4 152-144.8 185.3-227.1 35.8-88.2 20.4-192.8-44.1-262.8z" fill="currentColor"></path>
                                </svg>

                                <div class="withu-flex-between-center withu-loveday-content">
                                    <div class="withu-flex-center-gap"
                                        tabindex="0">
                                        <div class=" withu-icon-box-glass-white">
                                                                                            <svg viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg">
                                                    <path
                                                        d="M470.4 204.8l44.8 44.8 44.8-44.8c99.2-99.2 262.4-99.2 361.6 0 48 48 73.6 112 73.6 179.2 0 19.2-12.8 32-32 32s-32-12.8-32-32c0-51.2-19.2-99.2-57.6-134.4-73.6-73.6-195.2-73.6-272 0l-67.2 67.2c-12.8 12.8-32 12.8-44.8 0l-67.2-67.2c-73.6-73.6-195.2-73.6-272 0-73.6 73.6-73.6 195.2 0 272L512 883.2c12.8 12.8 12.8 32 0 44.8s-32 12.8-44.8 0L105.6 566.4c-99.2-99.2-99.2-262.4 0-361.6 102.4-102.4 262.4-102.4 364.8 0z m176 710.4L425.6 694.4c-57.6-57.6-57.6-147.2 0-204.8 57.6-57.6 147.2-57.6 204.8 0l57.6 57.6 57.6-57.6c57.6-57.6 147.2-57.6 204.8 0 57.6 57.6 57.6 147.2 0 204.8L729.6 915.2c-9.6 9.6-25.6 16-38.4 16-19.2 0-32-6.4-44.8-16z m256-265.6c32-32 32-83.2 0-112-32-32-83.2-32-112 0l-80 80c-12.8 12.8-32 12.8-44.8 0l-80-80c-32-32-83.2-32-112 0-32 32-32 83.2 0 112L688 864l214.4-214.4z"
                                                        fill="#ffffff"></path>
                                                </svg>
                                                                                    </div>
                                        <div class="withu-loveday-copy">
                                            <div class="withu-loveday-title" data-withu-tip="搬新家就是今天">
                                                搬新家就是今天                                            </div>
                                            <div class="withu-loveday-date">
                                                <span class="withu-loveday-date-line">
                                                    起始日：                                                    2024-07-20                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="withu-text-right">
                                        <div class="withu-loveday-count">
                                                                                            747<span class="withu-loveday-unit">天</span>
                                                                                    </div>
                                                                            </div>
                                </div>
                            </div>
                            </div>                                                    <div data-aos="fade-up" data-aos-delay="150">                            <div class="withu-widget withu-widget--loveday-vibrant withu-widget--loveday-past">

                                                                <div class="withu-loveday-sup-label">
                                    已经                                </div>
                                <!-- Decorative BG Icon -->
                                                                <svg class="withu-loveday-bg-icon" viewBox="0 0 1024 1024" version="1.1"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M923 283.6a260.04 260.04 0 0 0-56.9-82.6c-64.5-70-170.8-84-245.5-32.9L512 216.7l-108.6-48.6c-74.7-51.1-181-37.1-245.5 32.9-64.5 70-79.9 174.6-44.1 262.8 33.3 82.3 98.7 151.7 185.3 227.1L512 884.2l212.9-193.3c86.6-75.4 152-144.8 185.3-227.1 35.8-88.2 20.4-192.8-44.1-262.8z" fill="currentColor"></path>
                                </svg>

                                <div class="withu-flex-between-center withu-loveday-content">
                                    <div class="withu-flex-center-gap"
                                        tabindex="0">
                                        <div class=" withu-icon-box-glass-white">
                                                                                            <svg viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg">
                                                    <path
                                                        d="M470.4 204.8l44.8 44.8 44.8-44.8c99.2-99.2 262.4-99.2 361.6 0 48 48 73.6 112 73.6 179.2 0 19.2-12.8 32-32 32s-32-12.8-32-32c0-51.2-19.2-99.2-57.6-134.4-73.6-73.6-195.2-73.6-272 0l-67.2 67.2c-12.8 12.8-32 12.8-44.8 0l-67.2-67.2c-73.6-73.6-195.2-73.6-272 0-73.6 73.6-73.6 195.2 0 272L512 883.2c12.8 12.8 12.8 32 0 44.8s-32 12.8-44.8 0L105.6 566.4c-99.2-99.2-99.2-262.4 0-361.6 102.4-102.4 262.4-102.4 364.8 0z m176 710.4L425.6 694.4c-57.6-57.6-57.6-147.2 0-204.8 57.6-57.6 147.2-57.6 204.8 0l57.6 57.6 57.6-57.6c57.6-57.6 147.2-57.6 204.8 0 57.6 57.6 57.6 147.2 0 204.8L729.6 915.2c-9.6 9.6-25.6 16-38.4 16-19.2 0-32-6.4-44.8-16z m256-265.6c32-32 32-83.2 0-112-32-32-83.2-32-112 0l-80 80c-12.8 12.8-32 12.8-44.8 0l-80-80c-32-32-83.2-32-112 0-32 32-32 83.2 0 112L688 864l214.4-214.4z"
                                                        fill="#ffffff"></path>
                                                </svg>
                                                                                    </div>
                                        <div class="withu-loveday-copy">
                                            <div class="withu-loveday-title" data-withu-tip="不异地已经">
                                                不异地已经                                            </div>
                                            <div class="withu-loveday-date">
                                                <span class="withu-loveday-date-line">
                                                    起始日：                                                    2024-07-19                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="withu-text-right">
                                        <div class="withu-loveday-count">
                                                                                            748<span class="withu-loveday-unit">天</span>
                                                                                    </div>
                                                                            </div>
                                </div>
                            </div>
                            </div>                                                    <div data-aos="fade-up" data-aos-delay="200">                            <div class="withu-widget withu-widget--loveday-vibrant withu-widget--loveday-past">

                                                                <div class="withu-loveday-sup-label">
                                    已经                                </div>
                                <!-- Decorative BG Icon -->
                                                                <svg class="withu-loveday-bg-icon" viewBox="0 0 1024 1024" version="1.1"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M923 283.6a260.04 260.04 0 0 0-56.9-82.6c-64.5-70-170.8-84-245.5-32.9L512 216.7l-108.6-48.6c-74.7-51.1-181-37.1-245.5 32.9-64.5 70-79.9 174.6-44.1 262.8 33.3 82.3 98.7 151.7 185.3 227.1L512 884.2l212.9-193.3c86.6-75.4 152-144.8 185.3-227.1 35.8-88.2 20.4-192.8-44.1-262.8z" fill="currentColor"></path>
                                </svg>

                                <div class="withu-flex-between-center withu-loveday-content">
                                    <div class="withu-flex-center-gap"
                                        tabindex="0">
                                        <div class=" withu-icon-box-glass-white">
                                                                                            <svg viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg">
                                                    <path
                                                        d="M470.4 204.8l44.8 44.8 44.8-44.8c99.2-99.2 262.4-99.2 361.6 0 48 48 73.6 112 73.6 179.2 0 19.2-12.8 32-32 32s-32-12.8-32-32c0-51.2-19.2-99.2-57.6-134.4-73.6-73.6-195.2-73.6-272 0l-67.2 67.2c-12.8 12.8-32 12.8-44.8 0l-67.2-67.2c-73.6-73.6-195.2-73.6-272 0-73.6 73.6-73.6 195.2 0 272L512 883.2c12.8 12.8 12.8 32 0 44.8s-32 12.8-44.8 0L105.6 566.4c-99.2-99.2-99.2-262.4 0-361.6 102.4-102.4 262.4-102.4 364.8 0z m176 710.4L425.6 694.4c-57.6-57.6-57.6-147.2 0-204.8 57.6-57.6 147.2-57.6 204.8 0l57.6 57.6 57.6-57.6c57.6-57.6 147.2-57.6 204.8 0 57.6 57.6 57.6 147.2 0 204.8L729.6 915.2c-9.6 9.6-25.6 16-38.4 16-19.2 0-32-6.4-44.8-16z m256-265.6c32-32 32-83.2 0-112-32-32-83.2-32-112 0l-80 80c-12.8 12.8-32 12.8-44.8 0l-80-80c-32-32-83.2-32-112 0-32 32-32 83.2 0 112L688 864l214.4-214.4z"
                                                        fill="#ffffff"></path>
                                                </svg>
                                                                                    </div>
                                        <div class="withu-loveday-copy">
                                            <div class="withu-loveday-title" data-withu-tip="测试纪念日10年前">
                                                测试纪念日10年前                                            </div>
                                            <div class="withu-loveday-date">
                                                <span class="withu-loveday-date-line">
                                                    起始日：                                                    2014-05-15                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="withu-text-right">
                                        <div class="withu-loveday-count">
                                                                                            4466<span class="withu-loveday-unit">天</span>
                                                                                    </div>
                                                                            </div>
                                </div>
                            </div>
                            </div>                                                    <div data-aos="fade-up" data-aos-delay="250">                            <div class="withu-widget withu-widget--loveday-vibrant withu-widget--loveday-future">

                                                                <div class="withu-loveday-sup-label">
                                    还有                                </div>
                                <!-- Decorative BG Icon -->
                                                                <svg class="withu-loveday-bg-icon" viewBox="0 0 1024 1024" version="1.1"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M792 120H232a40 40 0 0 0-40 40v56c0 88.4 71.6 160 160 160 5.2 0 10.4-.2 15.6-.6 4.4 11.8 4.4 24.8 0 36.6-5.2-.4-10.4-.6-15.6-.6-88.4 0-160 71.6-160 160v56a40 40 0 0 0 40 40h560a40 40 0 0 0 40-40v-56c0-88.4-71.6-160-160-160-5.2 0-10.4.2-15.6.6-4.4-11.8-4.4-24.8 0-36.6 5.2.4 10.4.6 15.6.6 88.4 0 160-71.6 160-160v-56a40 40 0 0 0-40-40z" fill="currentColor"></path>
                                </svg>

                                <div class="withu-flex-between-center withu-loveday-content">
                                    <div class="withu-flex-center-gap"
                                        tabindex="0">
                                        <div class=" withu-icon-box-glass-white">
                                                                                            <svg viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg">
                                                    <path
                                                        d="M810 249.5c-38.6-38.6-83.5-68.8-133.5-90-51.8-21.9-106.8-33-163.5-33s-111.7 11.1-163.5 33c-50 21.2-94.9 51.4-133.5 90-38.6 38.6-68.8 83.5-90 133.5-21.9 51.8-33 106.8-33 163.5s11.1 111.7 33 163.5c21.2 50 51.4 94.9 90 133.5s83.5 68.8 133.5 90c51.8 21.9 106.8 33 163.5 33s111.7-11.1 163.5-33c50-21.2 94.9-51.4 133.5-90S878.8 760 900 710c21.9-51.8 33-106.8 33-163.5S921.9 434.8 900 383c-21.2-50-51.5-94.9-90-133.5z m-297 657c-198.5 0-360-161.5-360-360s161.5-360 360-360 360 161.5 360 360-161.5 360-360 360zM357 96.5c-42.3-49.6-141-53.3-208.1 4s-77.3 153.9-35 203.5L357 96.5zM877.2 100.5C810 43.2 711.3 47 669 96.5L912.2 304c42.3-49.6 32.1-146.2-35-203.5z">
                                                    </path>
                                                    <path
                                                        d="M667.1 558.6H543V351c0-17.9-14.5-32.4-32.4-32.4-15.2 0-27.6 12.3-27.6 27.6v272.4h182.2c17.1 0 30.9-13.8 30.9-30.9 0-16.1-13-29.1-29-29.1z">
                                                    </path>
                                                </svg>
                                                                                    </div>
                                        <div class="withu-loveday-copy">
                                            <div class="withu-loveday-title" data-withu-tip="测试自动判断倒计时自动增加一年">
                                                测试自动判断倒计时自动增加一年                                            </div>
                                            <div class="withu-loveday-date">
                                                <span class="withu-loveday-date-line">
                                                    目标日：                                                    2027-03-08                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="withu-text-right">
                                        <div class="withu-loveday-count">
                                                                                            214<span class="withu-loveday-unit">天</span>
                                                                                    </div>
                                                                            </div>
                                </div>
                            </div>
                            </div>                                                    <div data-aos="fade-up" data-aos-delay="300">                            <div class="withu-widget withu-widget--loveday-vibrant withu-widget--loveday-past">

                                                                <div class="withu-loveday-sup-label">
                                    已经                                </div>
                                <!-- Decorative BG Icon -->
                                                                <svg class="withu-loveday-bg-icon" viewBox="0 0 1024 1024" version="1.1"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M923 283.6a260.04 260.04 0 0 0-56.9-82.6c-64.5-70-170.8-84-245.5-32.9L512 216.7l-108.6-48.6c-74.7-51.1-181-37.1-245.5 32.9-64.5 70-79.9 174.6-44.1 262.8 33.3 82.3 98.7 151.7 185.3 227.1L512 884.2l212.9-193.3c86.6-75.4 152-144.8 185.3-227.1 35.8-88.2 20.4-192.8-44.1-262.8z" fill="currentColor"></path>
                                </svg>

                                <div class="withu-flex-between-center withu-loveday-content">
                                    <div class="withu-flex-center-gap"
                                        tabindex="0">
                                        <div class=" withu-icon-box-glass-white">
                                                                                            <svg viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg">
                                                    <path
                                                        d="M470.4 204.8l44.8 44.8 44.8-44.8c99.2-99.2 262.4-99.2 361.6 0 48 48 73.6 112 73.6 179.2 0 19.2-12.8 32-32 32s-32-12.8-32-32c0-51.2-19.2-99.2-57.6-134.4-73.6-73.6-195.2-73.6-272 0l-67.2 67.2c-12.8 12.8-32 12.8-44.8 0l-67.2-67.2c-73.6-73.6-195.2-73.6-272 0-73.6 73.6-73.6 195.2 0 272L512 883.2c12.8 12.8 12.8 32 0 44.8s-32 12.8-44.8 0L105.6 566.4c-99.2-99.2-99.2-262.4 0-361.6 102.4-102.4 262.4-102.4 364.8 0z m176 710.4L425.6 694.4c-57.6-57.6-57.6-147.2 0-204.8 57.6-57.6 147.2-57.6 204.8 0l57.6 57.6 57.6-57.6c57.6-57.6 147.2-57.6 204.8 0 57.6 57.6 57.6 147.2 0 204.8L729.6 915.2c-9.6 9.6-25.6 16-38.4 16-19.2 0-32-6.4-44.8-16z m256-265.6c32-32 32-83.2 0-112-32-32-83.2-32-112 0l-80 80c-12.8 12.8-32 12.8-44.8 0l-80-80c-32-32-83.2-32-112 0-32 32-32 83.2 0 112L688 864l214.4-214.4z"
                                                        fill="#ffffff"></path>
                                                </svg>
                                                                                    </div>
                                        <div class="withu-loveday-copy">
                                            <div class="withu-loveday-title" data-withu-tip="我们在一起咯">
                                                我们在一起咯                                            </div>
                                            <div class="withu-loveday-date">
                                                <span class="withu-loveday-date-line">
                                                    起始日：                                                    2023-07-19                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="withu-text-right">
                                        <div class="withu-loveday-count">
                                                                                            1114<span class="withu-loveday-unit">天</span>
                                                                                    </div>
                                                                            </div>
                                </div>
                            </div>
                            </div>                                                    <div data-aos="fade-up" data-aos-delay="300">                            <div class="withu-widget withu-widget--loveday-vibrant withu-widget--loveday-future">

                                                                <div class="withu-loveday-sup-label">
                                    还有                                </div>
                                <!-- Decorative BG Icon -->
                                                                <svg class="withu-loveday-bg-icon" viewBox="0 0 1024 1024" version="1.1"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M792 120H232a40 40 0 0 0-40 40v56c0 88.4 71.6 160 160 160 5.2 0 10.4-.2 15.6-.6 4.4 11.8 4.4 24.8 0 36.6-5.2-.4-10.4-.6-15.6-.6-88.4 0-160 71.6-160 160v56a40 40 0 0 0 40 40h560a40 40 0 0 0 40-40v-56c0-88.4-71.6-160-160-160-5.2 0-10.4.2-15.6.6-4.4-11.8-4.4-24.8 0-36.6 5.2.4 10.4.6 15.6.6 88.4 0 160-71.6 160-160v-56a40 40 0 0 0-40-40z" fill="currentColor"></path>
                                </svg>

                                <div class="withu-flex-between-center withu-loveday-content">
                                    <div class="withu-flex-center-gap"
                                        tabindex="0">
                                        <div class=" withu-icon-box-glass-white">
                                                                                            <svg viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg">
                                                    <path
                                                        d="M810 249.5c-38.6-38.6-83.5-68.8-133.5-90-51.8-21.9-106.8-33-163.5-33s-111.7 11.1-163.5 33c-50 21.2-94.9 51.4-133.5 90-38.6 38.6-68.8 83.5-90 133.5-21.9 51.8-33 106.8-33 163.5s11.1 111.7 33 163.5c21.2 50 51.4 94.9 90 133.5s83.5 68.8 133.5 90c51.8 21.9 106.8 33 163.5 33s111.7-11.1 163.5-33c50-21.2 94.9-51.4 133.5-90S878.8 760 900 710c21.9-51.8 33-106.8 33-163.5S921.9 434.8 900 383c-21.2-50-51.5-94.9-90-133.5z m-297 657c-198.5 0-360-161.5-360-360s161.5-360 360-360 360 161.5 360 360-161.5 360-360 360zM357 96.5c-42.3-49.6-141-53.3-208.1 4s-77.3 153.9-35 203.5L357 96.5zM877.2 100.5C810 43.2 711.3 47 669 96.5L912.2 304c42.3-49.6 32.1-146.2-35-203.5z">
                                                    </path>
                                                    <path
                                                        d="M667.1 558.6H543V351c0-17.9-14.5-32.4-32.4-32.4-15.2 0-27.6 12.3-27.6 27.6v272.4h182.2c17.1 0 30.9-13.8 30.9-30.9 0-16.1-13-29.1-29-29.1z">
                                                    </path>
                                                </svg>
                                                                                    </div>
                                        <div class="withu-loveday-copy">
                                            <div class="withu-loveday-title" data-withu-tip="距离2028年春节">
                                                距离2028年春节                                            </div>
                                            <div class="withu-loveday-date">
                                                <span class="withu-loveday-date-line">
                                                    目标日：                                                    2028-01-26                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="withu-text-right">
                                        <div class="withu-loveday-count">
                                                                                            538<span class="withu-loveday-unit">天</span>
                                                                                    </div>
                                                                            </div>
                                </div>
                            </div>
                            </div>                                                    <div data-aos="fade-up" data-aos-delay="300">                            <div class="withu-widget withu-widget--loveday-vibrant withu-widget--loveday-past">

                                                                <div class="withu-loveday-sup-label">
                                    已经                                </div>
                                <!-- Decorative BG Icon -->
                                                                <svg class="withu-loveday-bg-icon" viewBox="0 0 1024 1024" version="1.1"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M923 283.6a260.04 260.04 0 0 0-56.9-82.6c-64.5-70-170.8-84-245.5-32.9L512 216.7l-108.6-48.6c-74.7-51.1-181-37.1-245.5 32.9-64.5 70-79.9 174.6-44.1 262.8 33.3 82.3 98.7 151.7 185.3 227.1L512 884.2l212.9-193.3c86.6-75.4 152-144.8 185.3-227.1 35.8-88.2 20.4-192.8-44.1-262.8z" fill="currentColor"></path>
                                </svg>

                                <div class="withu-flex-between-center withu-loveday-content">
                                    <div class="withu-flex-center-gap"
                                        tabindex="0">
                                        <div class=" withu-icon-box-glass-white">
                                                                                            <svg viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg">
                                                    <path
                                                        d="M470.4 204.8l44.8 44.8 44.8-44.8c99.2-99.2 262.4-99.2 361.6 0 48 48 73.6 112 73.6 179.2 0 19.2-12.8 32-32 32s-32-12.8-32-32c0-51.2-19.2-99.2-57.6-134.4-73.6-73.6-195.2-73.6-272 0l-67.2 67.2c-12.8 12.8-32 12.8-44.8 0l-67.2-67.2c-73.6-73.6-195.2-73.6-272 0-73.6 73.6-73.6 195.2 0 272L512 883.2c12.8 12.8 12.8 32 0 44.8s-32 12.8-44.8 0L105.6 566.4c-99.2-99.2-99.2-262.4 0-361.6 102.4-102.4 262.4-102.4 364.8 0z m176 710.4L425.6 694.4c-57.6-57.6-57.6-147.2 0-204.8 57.6-57.6 147.2-57.6 204.8 0l57.6 57.6 57.6-57.6c57.6-57.6 147.2-57.6 204.8 0 57.6 57.6 57.6 147.2 0 204.8L729.6 915.2c-9.6 9.6-25.6 16-38.4 16-19.2 0-32-6.4-44.8-16z m256-265.6c32-32 32-83.2 0-112-32-32-83.2-32-112 0l-80 80c-12.8 12.8-32 12.8-44.8 0l-80-80c-32-32-83.2-32-112 0-32 32-32 83.2 0 112L688 864l214.4-214.4z"
                                                        fill="#ffffff"></path>
                                                </svg>
                                                                                    </div>
                                        <div class="withu-loveday-copy">
                                            <div class="withu-loveday-title" data-withu-tip="Our first 100days">
                                                Our first 100days                                            </div>
                                            <div class="withu-loveday-date">
                                                <span class="withu-loveday-date-line">
                                                    起始日：                                                    2023-10-27                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="withu-text-right">
                                        <div class="withu-loveday-count">
                                                                                            1014<span class="withu-loveday-unit">天</span>
                                                                                    </div>
                                                                            </div>
                                </div>
                            </div>
                            </div>                                                    <div data-aos="fade-up" data-aos-delay="300">                            <div class="withu-widget withu-widget--loveday-vibrant withu-widget--loveday-past">

                                                                <div class="withu-loveday-sup-label">
                                    已经                                </div>
                                <!-- Decorative BG Icon -->
                                                                <svg class="withu-loveday-bg-icon" viewBox="0 0 1024 1024" version="1.1"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M923 283.6a260.04 260.04 0 0 0-56.9-82.6c-64.5-70-170.8-84-245.5-32.9L512 216.7l-108.6-48.6c-74.7-51.1-181-37.1-245.5 32.9-64.5 70-79.9 174.6-44.1 262.8 33.3 82.3 98.7 151.7 185.3 227.1L512 884.2l212.9-193.3c86.6-75.4 152-144.8 185.3-227.1 35.8-88.2 20.4-192.8-44.1-262.8z" fill="currentColor"></path>
                                </svg>

                                <div class="withu-flex-between-center withu-loveday-content">
                                    <div class="withu-flex-center-gap"
                                        tabindex="0">
                                        <div class=" withu-icon-box-glass-white">
                                                                                            <svg viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg">
                                                    <path
                                                        d="M470.4 204.8l44.8 44.8 44.8-44.8c99.2-99.2 262.4-99.2 361.6 0 48 48 73.6 112 73.6 179.2 0 19.2-12.8 32-32 32s-32-12.8-32-32c0-51.2-19.2-99.2-57.6-134.4-73.6-73.6-195.2-73.6-272 0l-67.2 67.2c-12.8 12.8-32 12.8-44.8 0l-67.2-67.2c-73.6-73.6-195.2-73.6-272 0-73.6 73.6-73.6 195.2 0 272L512 883.2c12.8 12.8 12.8 32 0 44.8s-32 12.8-44.8 0L105.6 566.4c-99.2-99.2-99.2-262.4 0-361.6 102.4-102.4 262.4-102.4 364.8 0z m176 710.4L425.6 694.4c-57.6-57.6-57.6-147.2 0-204.8 57.6-57.6 147.2-57.6 204.8 0l57.6 57.6 57.6-57.6c57.6-57.6 147.2-57.6 204.8 0 57.6 57.6 57.6 147.2 0 204.8L729.6 915.2c-9.6 9.6-25.6 16-38.4 16-19.2 0-32-6.4-44.8-16z m256-265.6c32-32 32-83.2 0-112-32-32-83.2-32-112 0l-80 80c-12.8 12.8-32 12.8-44.8 0l-80-80c-32-32-83.2-32-112 0-32 32-32 83.2 0 112L688 864l214.4-214.4z"
                                                        fill="#ffffff"></path>
                                                </svg>
                                                                                    </div>
                                        <div class="withu-loveday-copy">
                                            <div class="withu-loveday-title" data-withu-tip="第一次约会">
                                                第一次约会                                            </div>
                                            <div class="withu-loveday-date">
                                                <span class="withu-loveday-date-line">
                                                    起始日：                                                    2023-08-29                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="withu-text-right">
                                        <div class="withu-loveday-count">
                                                                                            1073<span class="withu-loveday-unit">天</span>
                                                                                    </div>
                                                                            </div>
                                </div>
                            </div>
                            </div>                                                    <div data-aos="fade-up" data-aos-delay="300">                            <div class="withu-widget withu-widget--loveday-vibrant withu-widget--loveday-past">

                                                                <div class="withu-loveday-sup-label">
                                    已经                                </div>
                                <!-- Decorative BG Icon -->
                                                                <svg class="withu-loveday-bg-icon" viewBox="0 0 1024 1024" version="1.1"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M923 283.6a260.04 260.04 0 0 0-56.9-82.6c-64.5-70-170.8-84-245.5-32.9L512 216.7l-108.6-48.6c-74.7-51.1-181-37.1-245.5 32.9-64.5 70-79.9 174.6-44.1 262.8 33.3 82.3 98.7 151.7 185.3 227.1L512 884.2l212.9-193.3c86.6-75.4 152-144.8 185.3-227.1 35.8-88.2 20.4-192.8-44.1-262.8z" fill="currentColor"></path>
                                </svg>

                                <div class="withu-flex-between-center withu-loveday-content">
                                    <div class="withu-flex-center-gap"
                                        tabindex="0">
                                        <div class=" withu-icon-box-glass-white">
                                                                                            <svg viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg">
                                                    <path
                                                        d="M470.4 204.8l44.8 44.8 44.8-44.8c99.2-99.2 262.4-99.2 361.6 0 48 48 73.6 112 73.6 179.2 0 19.2-12.8 32-32 32s-32-12.8-32-32c0-51.2-19.2-99.2-57.6-134.4-73.6-73.6-195.2-73.6-272 0l-67.2 67.2c-12.8 12.8-32 12.8-44.8 0l-67.2-67.2c-73.6-73.6-195.2-73.6-272 0-73.6 73.6-73.6 195.2 0 272L512 883.2c12.8 12.8 12.8 32 0 44.8s-32 12.8-44.8 0L105.6 566.4c-99.2-99.2-99.2-262.4 0-361.6 102.4-102.4 262.4-102.4 364.8 0z m176 710.4L425.6 694.4c-57.6-57.6-57.6-147.2 0-204.8 57.6-57.6 147.2-57.6 204.8 0l57.6 57.6 57.6-57.6c57.6-57.6 147.2-57.6 204.8 0 57.6 57.6 57.6 147.2 0 204.8L729.6 915.2c-9.6 9.6-25.6 16-38.4 16-19.2 0-32-6.4-44.8-16z m256-265.6c32-32 32-83.2 0-112-32-32-83.2-32-112 0l-80 80c-12.8 12.8-32 12.8-44.8 0l-80-80c-32-32-83.2-32-112 0-32 32-32 83.2 0 112L688 864l214.4-214.4z"
                                                        fill="#ffffff"></path>
                                                </svg>
                                                                                    </div>
                                        <div class="withu-loveday-copy">
                                            <div class="withu-loveday-title" data-withu-tip="第一次一起玩游戏">
                                                第一次一起玩游戏                                            </div>
                                            <div class="withu-loveday-date">
                                                <span class="withu-loveday-date-line">
                                                    起始日：                                                    2023-07-16                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="withu-text-right">
                                        <div class="withu-loveday-count">
                                                                                            1117<span class="withu-loveday-unit">天</span>
                                                                                    </div>
                                                                            </div>
                                </div>
                            </div>
                            </div>                                                    <div data-aos="fade-up" data-aos-delay="300">                            <div class="withu-widget withu-widget--loveday-vibrant withu-widget--loveday-past">

                                                                <div class="withu-loveday-sup-label">
                                    已经                                </div>
                                <!-- Decorative BG Icon -->
                                                                <svg class="withu-loveday-bg-icon" viewBox="0 0 1024 1024" version="1.1"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M923 283.6a260.04 260.04 0 0 0-56.9-82.6c-64.5-70-170.8-84-245.5-32.9L512 216.7l-108.6-48.6c-74.7-51.1-181-37.1-245.5 32.9-64.5 70-79.9 174.6-44.1 262.8 33.3 82.3 98.7 151.7 185.3 227.1L512 884.2l212.9-193.3c86.6-75.4 152-144.8 185.3-227.1 35.8-88.2 20.4-192.8-44.1-262.8z" fill="currentColor"></path>
                                </svg>

                                <div class="withu-flex-between-center withu-loveday-content">
                                    <div class="withu-flex-center-gap"
                                        tabindex="0">
                                        <div class=" withu-icon-box-glass-white">
                                                                                            <svg viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg">
                                                    <path
                                                        d="M470.4 204.8l44.8 44.8 44.8-44.8c99.2-99.2 262.4-99.2 361.6 0 48 48 73.6 112 73.6 179.2 0 19.2-12.8 32-32 32s-32-12.8-32-32c0-51.2-19.2-99.2-57.6-134.4-73.6-73.6-195.2-73.6-272 0l-67.2 67.2c-12.8 12.8-32 12.8-44.8 0l-67.2-67.2c-73.6-73.6-195.2-73.6-272 0-73.6 73.6-73.6 195.2 0 272L512 883.2c12.8 12.8 12.8 32 0 44.8s-32 12.8-44.8 0L105.6 566.4c-99.2-99.2-99.2-262.4 0-361.6 102.4-102.4 262.4-102.4 364.8 0z m176 710.4L425.6 694.4c-57.6-57.6-57.6-147.2 0-204.8 57.6-57.6 147.2-57.6 204.8 0l57.6 57.6 57.6-57.6c57.6-57.6 147.2-57.6 204.8 0 57.6 57.6 57.6 147.2 0 204.8L729.6 915.2c-9.6 9.6-25.6 16-38.4 16-19.2 0-32-6.4-44.8-16z m256-265.6c32-32 32-83.2 0-112-32-32-83.2-32-112 0l-80 80c-12.8 12.8-32 12.8-44.8 0l-80-80c-32-32-83.2-32-112 0-32 32-32 83.2 0 112L688 864l214.4-214.4z"
                                                        fill="#ffffff"></path>
                                                </svg>
                                                                                    </div>
                                        <div class="withu-loveday-copy">
                                            <div class="withu-loveday-title" data-withu-tip="我们相遇的那天">
                                                我们相遇的那天                                            </div>
                                            <div class="withu-loveday-date">
                                                <span class="withu-loveday-date-line">
                                                    起始日：                                                    2023-06-30                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="withu-text-right">
                                        <div class="withu-loveday-count">
                                                                                            1133<span class="withu-loveday-unit">天</span>
                                                                                    </div>
                                                                            </div>
                                </div>
                            </div>
                            </div>                                            </div>

                </section>
            
            <!-- 3. Updates (日常点滴) -->
                        <section id="updates" class="withu-section">
                <div class="withu-section-header withu-section-header--blue" data-aos="fade-up" data-aos-delay="0">
                    <div class="withu-section-header__left">
                        <h2 class="withu-section-title withu-section-title-color-blue withu-flex-center">
                            <div class="withu-section-icon-box withu-section-icon-box--blue">
                                <i class="ph-fill ph-star withu-icon-md-white"></i>
                            </div>
                            <span>点滴</span>
                            <span class="withu-badge-new">NEW</span>
                        </h2>
                    </div>
                    <div class="withu-section-header__right">
                        <a href="articles.php" class="withu-link-more">
                            <i class="ph-bold ph-arrow-right"></i>
                        </a>
                    </div>
                </div>
                <div class="withu-journal-grid">
                                                    <div data-aos="fade-up" data-aos-delay="0">                            <a href="page.php?id=12"
                                class="withu-journal-card withu-journal-card--link">
                                <div class="withu-watermark">DAY 1002</div>

                                <div class="withu-journal-header">
                                    <div class="withu-journal-user">
                                        <img data-src="Lovefolder/20260411043046_69d95df639c33274072975.webp" class="withu-journal-avatar lazy">
                                        <div>
                                            <div class="withu-font-sm-bold">Really</div>
                                            <div class="withu-journal-meta">2026-04-15 21:04</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="withu-journal-content">
                                    <h3 class="withu-journal-title withu-journal-title-text">
                                        测试女主发布文章丨の 文章标题                                    </h3>
                                    <p class="withu-journal-body withu-journal-body-clamp">
                                         自动识别1233。 test                                    </p>
                                </div>

                                <div class="withu-journal-footer">
                                    <div class="withu-flex-gap-sm">
                                        <span class="withu-chip withu-chip--light"><i class="ph-bold ph-map-pin"></i>
                                            广东 · 惠东</span>
                                                                                <span class="withu-chip withu-chip--light"><i class="ph-bold ph-cloud-sun"></i>
                                            多云</span>
                                                                                                                        <span class="withu-chip withu-chip--light"><i class="ph-bold ph-smiley"></i>
                                            开心</span>
                                                                                <span class="withu-chip withu-chip--light"><i class="ph-bold ph-eye"></i>
                                            109</span>
                                        <span class="withu-chip withu-chip--light"><i class="ph-bold ph-heart"></i>
                                            4</span>
                                    </div>
                                </div>
                            </a>
                            </div>                                                    <div data-aos="fade-up" data-aos-delay="50">                            <a href="page.php?id=11"
                                class="withu-journal-card withu-journal-card--link">
                                <div class="withu-watermark">DAY 997</div>

                                <div class="withu-journal-header">
                                    <div class="withu-journal-user">
                                        <img data-src="Lovefolder/20260411043037_69d95ded97293201118237.webp" class="withu-journal-avatar lazy">
                                        <div>
                                            <div class="withu-font-sm-bold">Ki.</div>
                                            <div class="withu-journal-meta">2026-04-10 17:39</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="withu-journal-content">
                                    <h3 class="withu-journal-title withu-journal-title-text">
                                        测试 v2.0.7发布是否正常                                    </h3>
                                    <p class="withu-journal-body withu-journal-body-clamp">
                                        测试 v2.0.7发布是否正常测试 v2.0.7发布是否正常测试 v2.0.7发布是否正常测试 v2.0.7发布是否正常测试 v2.0.7发布是否正常测试 v2.0.7发布是否正常测试 v2.0.7发布是否正常测试 v2.0.7发布是否正常                                    </p>
                                </div>

                                <div class="withu-journal-footer">
                                    <div class="withu-flex-gap-sm">
                                        <span class="withu-chip withu-chip--light"><i class="ph-bold ph-map-pin"></i>
                                            广东 · 惠东</span>
                                                                                <span class="withu-chip withu-chip--light"><i class="ph-bold ph-cloud-sun"></i>
                                            多云</span>
                                                                                                                        <span class="withu-chip withu-chip--light"><i class="ph-bold ph-smiley"></i>
                                            开心</span>
                                                                                <span class="withu-chip withu-chip--light"><i class="ph-bold ph-eye"></i>
                                            85</span>
                                        <span class="withu-chip withu-chip--light"><i class="ph-bold ph-heart"></i>
                                            2</span>
                                    </div>
                                </div>
                            </a>
                            </div>                                                    <div data-aos="fade-up" data-aos-delay="100">                            <a href="page.php?id=10"
                                class="withu-journal-card withu-journal-card--link">
                                <div class="withu-watermark">DAY 997</div>

                                <div class="withu-journal-header">
                                    <div class="withu-journal-user">
                                        <img data-src="Lovefolder/20260411043037_69d95ded97293201118237.webp" class="withu-journal-avatar lazy">
                                        <div>
                                            <div class="withu-font-sm-bold">Ki.</div>
                                            <div class="withu-journal-meta">2026-04-10 05:36</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="withu-journal-content">
                                    <h3 class="withu-journal-title withu-journal-title-text">
                                        我再新增一遍测试                                    </h3>
                                    <p class="withu-journal-body withu-journal-body-clamp">
                                        我再新增一遍测试我再新增一遍测试我再新增一遍测试我再新增一遍测试我再新增一遍测试我再新增一遍测试我再新增一遍测试我再新增一遍测试我再新增一遍测试我再新增一遍测试我再新增一遍测试我再新增一遍测试我再新增一遍测试我再新增一遍测试我再新增一遍测试                                    </p>
                                </div>

                                <div class="withu-journal-footer">
                                    <div class="withu-flex-gap-sm">
                                        <span class="withu-chip withu-chip--light"><i class="ph-bold ph-map-pin"></i>
                                            广东 · 惠东</span>
                                                                                <span class="withu-chip withu-chip--light"><i class="ph-bold ph-cloud-sun"></i>
                                            多云</span>
                                                                                                                        <span class="withu-chip withu-chip--light"><i class="ph-bold ph-smiley"></i>
                                            开心</span>
                                                                                <span class="withu-chip withu-chip--light"><i class="ph-bold ph-eye"></i>
                                            36</span>
                                        <span class="withu-chip withu-chip--light"><i class="ph-bold ph-heart"></i>
                                            1</span>
                                    </div>
                                </div>
                            </a>
                            </div>                                                    <div data-aos="fade-up" data-aos-delay="150">                            <a href="page.php?id=9"
                                class="withu-journal-card withu-journal-card--link">
                                <div class="withu-watermark">DAY 996</div>

                                <div class="withu-journal-header">
                                    <div class="withu-journal-user">
                                        <img data-src="Lovefolder/20260411043037_69d95ded97293201118237.webp" class="withu-journal-avatar lazy">
                                        <div>
                                            <div class="withu-font-sm-bold">Ki.</div>
                                            <div class="withu-journal-meta">2026-04-09 21:40</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="withu-journal-content">
                                    <h3 class="withu-journal-title withu-journal-title-text">
                                        测试新增文章 目前时间                                    </h3>
                                    <p class="withu-journal-body withu-journal-body-clamp">
                                        31232123123 123312123 123 123123 123 123 123 123 1231 312 123 312 123 123 123 123 123 123 123                                    </p>
                                </div>

                                <div class="withu-journal-footer">
                                    <div class="withu-flex-gap-sm">
                                        <span class="withu-chip withu-chip--light"><i class="ph-bold ph-map-pin"></i>
                                            广东 · 惠东</span>
                                                                                <span class="withu-chip withu-chip--light"><i class="ph-bold ph-cloud-sun"></i>
                                            多云</span>
                                                                                                                        <span class="withu-chip withu-chip--light"><i class="ph-bold ph-smiley"></i>
                                            开心</span>
                                                                                <span class="withu-chip withu-chip--light"><i class="ph-bold ph-eye"></i>
                                            34</span>
                                        <span class="withu-chip withu-chip--light"><i class="ph-bold ph-heart"></i>
                                            0</span>
                                    </div>
                                </div>
                            </a>
                            </div>                                                    <div data-aos="fade-up" data-aos-delay="200">                            <a href="page.php?id=8"
                                class="withu-journal-card withu-journal-card--link">
                                <div class="withu-watermark">DAY 996</div>

                                <div class="withu-journal-header">
                                    <div class="withu-journal-user">
                                        <img data-src="Lovefolder/20260411043037_69d95ded97293201118237.webp" class="withu-journal-avatar lazy">
                                        <div>
                                            <div class="withu-font-sm-bold">Ki.</div>
                                            <div class="withu-journal-meta">2026-04-09 20:09</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="withu-journal-content">
                                    <h3 class="withu-journal-title withu-journal-title-text">
                                        测试新增点点滴滴内容😈表情测试《》                                    </h3>
                                    <p class="withu-journal-body withu-journal-body-clamp">
                                        测试彩色标签 测试解析音乐 测试测试 21:38:34 《》《》：， 哈哈哈 测试一下测试 测试测试发布动画效果                                    </p>
                                </div>

                                <div class="withu-journal-footer">
                                    <div class="withu-flex-gap-sm">
                                        <span class="withu-chip withu-chip--light"><i class="ph-bold ph-map-pin"></i>
                                            广东 · 惠东</span>
                                                                                <span class="withu-chip withu-chip--light"><i class="ph-bold ph-cloud-sun"></i>
                                            多云</span>
                                                                                                                        <span class="withu-chip withu-chip--light"><i class="ph-bold ph-smiley"></i>
                                            开心</span>
                                                                                <span class="withu-chip withu-chip--light"><i class="ph-bold ph-eye"></i>
                                            36</span>
                                        <span class="withu-chip withu-chip--light"><i class="ph-bold ph-heart"></i>
                                            1</span>
                                    </div>
                                </div>
                            </a>
                            </div>                                                    <div data-aos="fade-up" data-aos-delay="250">                            <a href="page.php?id=1"
                                class="withu-journal-card withu-journal-card--link">
                                <div class="withu-watermark">DAY 554</div>

                                <div class="withu-journal-header">
                                    <div class="withu-journal-user">
                                        <img data-src="Lovefolder/20260411043037_69d95ded97293201118237.webp" class="withu-journal-avatar lazy">
                                        <div>
                                            <div class="withu-font-sm-bold">Ki.</div>
                                            <div class="withu-journal-meta">2025-01-22 09:03</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="withu-journal-content">
                                    <h3 class="withu-journal-title withu-journal-title-text">
                                        音乐解析播放演示                                    </h3>
                                    <p class="withu-journal-body withu-journal-body-clamp">
                                        音乐解析测试 音乐解析参考#直链解析 data-id：音乐ID data-type：netease 为网易云 tencent为QQ音乐 双双 Copy 音乐解析参考#直链解析+本地URL文件 data-id：音乐ID data-type：n                                    </p>
                                </div>

                                <div class="withu-journal-footer">
                                    <div class="withu-flex-gap-sm">
                                        <span class="withu-chip withu-chip--light"><i class="ph-bold ph-map-pin"></i>
                                            广东·深圳</span>
                                                                                                                        <span class="withu-chip withu-chip--light"><i class="ph-bold ph-eye"></i>
                                            45</span>
                                        <span class="withu-chip withu-chip--light"><i class="ph-bold ph-heart"></i>
                                            1</span>
                                    </div>
                                </div>
                            </a>
                            </div>                                        </div>
            </section>
            
            <!-- 4. Album (回忆相册) -->
                        <section id="album" class="withu-section">
                <div class="withu-section-header withu-section-header--orange" data-aos="fade-up" data-aos-delay="0">
                    <div class="withu-section-header__left">
                        <h2 class="withu-section-title withu-section-title-color-orange withu-flex-center">
                            <div class="withu-section-icon-box withu-section-icon-box--orange">
                                <i class="ph-fill ph-image withu-icon-md-white"></i>
                            </div>
                            <span>相册</span>
                            <span class="withu-badge-new">NEW</span>
                        </h2>
                    </div>
                    <div class="withu-section-header__right">
                        <a href="albums.php" class="withu-link-more">
                            <i class="ph-bold ph-arrow-right"></i>
                        </a>
                    </div>
                </div>
                                <div class="withu-mosaic-grid withu-mosaic-count-3">
                                            <div data-aos="fade-up" data-aos-delay="0">                        <a href="album-detail.php?code=1776318513866" class="withu-mosaic-item">
                            <img data-src="Lovefolder/20260409200702_69d79666f3e2b024272479.webp" class="withu-mosaic-img lazy">

                            <div class="withu-mosaic-pos-tr">
                                <div class="withu-chip--dark-glass">
                                    <span class="withu-flex-center-gap-xs"><i class="ph-fill ph-map-pin"></i>
                                        广州市</span>
                                    <span class="withu-mosaic-divider"></span>
                                    <span class="withu-flex-center-gap-xs"><i class="ph-fill ph-image"></i>
                                        21</span>
                                </div>
                            </div>

                            <div class="withu-mosaic-overlay">
                                <div class="withu-mosaic-overlay-content">
                                    <div class="withu-capsule withu-capsule--avatar withu-mosaic-avatar-mb">
                                        <img data-src="Lovefolder/20260411043046_69d95df639c33274072975.webp" class="withu-capsule__img lazy">
                                        <span class="withu-capsule__text withu-text-white">Really</span>
                                    </div>

                                    <h3 class="u-font-serif withu-mosaic-title">测试相册</h3>
                                                                        <div class="u-font-serif withu-mosaic-date">二〇二一年八月二十九日</div>
                                </div>
                            </div>
                        </a>
                        </div>                                            <div data-aos="fade-up" data-aos-delay="50">                        <a href="album-detail.php?code=20250811124452" class="withu-mosaic-item">
                            <img data-src="Lovefolder/20250811124741_689975ed5796a_thumb.webp" class="withu-mosaic-img lazy">

                            <div class="withu-mosaic-pos-tr">
                                <div class="withu-chip--dark-glass">
                                    <span class="withu-flex-center-gap-xs"><i class="ph-fill ph-map-pin"></i>
                                        珠海渔女</span>
                                    <span class="withu-mosaic-divider"></span>
                                    <span class="withu-flex-center-gap-xs"><i class="ph-fill ph-image"></i>
                                        26</span>
                                </div>
                            </div>

                            <div class="withu-mosaic-overlay">
                                <div class="withu-mosaic-overlay-content">
                                    <div class="withu-capsule withu-capsule--avatar withu-mosaic-avatar-mb">
                                        <img data-src="Lovefolder/20260411043037_69d95ded97293201118237.webp" class="withu-capsule__img lazy">
                                        <span class="withu-capsule__text withu-text-white">Ki.</span>
                                    </div>

                                    <h3 class="u-font-serif withu-mosaic-title">Dalinshan</h3>
                                                                        <div class="u-font-serif withu-mosaic-date">二〇二五年八月十一日</div>
                                </div>
                            </div>
                        </a>
                        </div>                                            <div data-aos="fade-up" data-aos-delay="100">                        <a href="album-detail.php?code=20241225163641" class="withu-mosaic-item">
                            <img data-src="Lovefolder/20250310100354_67ce488abed5b_thumb.webp" class="withu-mosaic-img lazy">

                            <div class="withu-mosaic-pos-tr">
                                <div class="withu-chip--dark-glass">
                                    <span class="withu-flex-center-gap-xs"><i class="ph-fill ph-map-pin"></i>
                                        广东·东莞</span>
                                    <span class="withu-mosaic-divider"></span>
                                    <span class="withu-flex-center-gap-xs"><i class="ph-fill ph-image"></i>
                                        15</span>
                                </div>
                            </div>

                            <div class="withu-mosaic-overlay">
                                <div class="withu-mosaic-overlay-content">
                                    <div class="withu-capsule withu-capsule--avatar withu-mosaic-avatar-mb">
                                        <img data-src="Lovefolder/20260411043037_69d95ded97293201118237.webp" class="withu-capsule__img lazy">
                                        <span class="withu-capsule__text withu-text-white">Ki.</span>
                                    </div>

                                    <h3 class="u-font-serif withu-mosaic-title">探索秋日山林的宁静之旅</h3>
                                                                        <div class="u-font-serif withu-mosaic-date">二〇二四年十二月二十五日</div>
                                </div>
                            </div>
                        </a>
                        </div>                                    </div>
            </section>
            
            <!-- 5. Messages (祝福留言) -->
                        <section id="messages" class="withu-section">
                <div class="withu-section-header withu-section-header--teal" data-aos="fade-up" data-aos-delay="0">
                    <div class="withu-section-header__left">
                        <h2 class="withu-section-title withu-section-title-color-teal withu-flex-center">
                            <div class="withu-section-icon-box withu-section-icon-box--teal">
                                <i class="ph-fill ph-chat-circle-text withu-icon-md-white"></i>
                            </div>
                            <span>留言</span>
                            <span class="withu-badge-new">NEW</span>
                        </h2>
                    </div>
                    <div class="withu-section-header__right">
                        <a href="messages.php" class="withu-link-more">
                            <i class="ph-bold ph-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <div class="withu-home-message-container" id="messageCarousel">
                    <div class="withu-home-message-track">
                                                                    <a href="messages.php#comment_544" class="withu-home-message-card">
                            <div class="withu-home-message-header">
                                <img class="withu-home-message-avatar" src="https://weavatar.com/avatar/4e11c40a0e83de42c9c91974b48630bf05a2f75a79827dd01491dd99a09a57e0?s=100&amp;d=mm&amp;r=g" alt="南城">
                                <div class="withu-home-message-user-info">
                                    <div class="withu-home-message-name-row">
                                        <span class="withu-home-message-user-name">南城</span>
                                                                            </div>
                                    <span class="withu-home-message-post-time">2026-08-02 13:10</span>
                                </div>
                            </div>
                            <div class="withu-home-message-content">已经支持了，价格不贵，不懂得兄弟也听热心解决，程序员懂美感的真心不多了<img class="lazy" data-src="OwO/images/emoji/threekids/8.png" data-emoji=":@(TK_8)"/></div>
                            <div class="withu-home-message-divider"></div>
                            <div class="withu-home-message-footer">
                                                                <span class="withu-chip withu-chip--light"><i class="ph-fill ph-map-pin"></i> 江苏 · 南京</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-devices"></i> Android</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-globe"></i> Chrome</span>
                                                            </div>
                        </a>
                                                                    <a href="messages.php#comment_543" class="withu-home-message-card">
                            <div class="withu-home-message-header">
                                <img class="withu-home-message-avatar" src="https://weavatar.com/avatar/4e11c40a0e83de42c9c91974b48630bf05a2f75a79827dd01491dd99a09a57e0?s=100&amp;d=mm&amp;r=g" alt="南城">
                                <div class="withu-home-message-user-info">
                                    <div class="withu-home-message-name-row">
                                        <span class="withu-home-message-user-name">南城</span>
                                                                            </div>
                                    <span class="withu-home-message-post-time">2026-08-01 14:25</span>
                                </div>
                            </div>
                            <div class="withu-home-message-content">看到blog做的这么好，必须买一个支持一下<img class="lazy" data-src="OwO/images/emoji/aru/E8A385E5A4A7E6ACBE_2x.png" data-emoji=":@(装大款)"/><img class="lazy" data-src="OwO/images/emoji/aru/E8A385E5A4A7E6ACBE_2x.png" data-emoji=":@(装大款)"/><img class="lazy" data-src="OwO/images/emoji/aru/E8A385E5A4A7E6ACBE_2x.png" data-emoji=":@(装大款)"/></div>
                            <div class="withu-home-message-divider"></div>
                            <div class="withu-home-message-footer">
                                                                <span class="withu-chip withu-chip--light"><i class="ph-fill ph-map-pin"></i> 江苏 · 南京</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-devices"></i> Windows</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-globe"></i> Chrome</span>
                                                            </div>
                        </a>
                                                                    <a href="messages.php#comment_540" class="withu-home-message-card">
                            <div class="withu-home-message-header">
                                <img class="withu-home-message-avatar" src="Lovefolder/20250310095643_67ce46dbe283d.webp" alt="深卦">
                                <div class="withu-home-message-user-info">
                                    <div class="withu-home-message-name-row">
                                        <span class="withu-home-message-user-name">深卦</span>
                                                                            </div>
                                    <span class="withu-home-message-post-time">2026-07-19 23:53</span>
                                </div>
                            </div>
                            <div class="withu-home-message-content">两情相悦春常在 一世恩爱梦也甜<img class="lazy" data-src="OwO/images/emoji/qq/5.gif" data-emoji="::(5)"/><img class="lazy" data-src="OwO/images/emoji/qq/5.gif" data-emoji="::(5)"/><img class="lazy" data-src="OwO/images/emoji/qq/5.gif" data-emoji="::(5)"/></div>
                            <div class="withu-home-message-divider"></div>
                            <div class="withu-home-message-footer">
                                                                <span class="withu-chip withu-chip--light"><i class="ph-fill ph-map-pin"></i> 河南 · 信阳</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-devices"></i> Windows</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-globe"></i> Chrome</span>
                                                            </div>
                        </a>
                                                                    <a href="messages.php#reply_516_536" class="withu-home-message-card">
                            <div class="withu-home-message-header">
                                <img class="withu-home-message-avatar" src="https://weavatar.com/avatar/e9f098f09a2ee8095e5bf8b2f862be96fb873ba805901d76f293162a53d69dee?s=100&amp;d=mm&amp;r=g" alt="独木舟">
                                <div class="withu-home-message-user-info">
                                    <div class="withu-home-message-name-row">
                                        <span class="withu-home-message-user-name">独木舟</span>
                                        <span class="withu-home-message-badge withu-home-message-badge--level"><i class="ph ph-arrow-bend-down-right"></i> 二级</span>                                    </div>
                                    <span class="withu-home-message-post-time">2026-06-02 16:12</span>
                                </div>
                            </div>
                            <div class="withu-home-message-content">携手共赴红尘路 恩爱相伴到永远</div>
                            <div class="withu-home-message-divider"></div>
                            <div class="withu-home-message-footer">
                                                                <span class="withu-chip withu-chip--light"><i class="ph-fill ph-map-pin"></i> 中国 · 河南</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-devices"></i> Windows</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-globe"></i> Chrome</span>
                                                            </div>
                        </a>
                                                                    <a href="messages.php#reply_533_535" class="withu-home-message-card">
                            <div class="withu-home-message-header">
                                <img class="withu-home-message-avatar" src="https://weavatar.com/avatar/43c37253c302e96e98489ba43c055b5463e18bca05d4f70eaa544dfe834a6c71?s=100&amp;d=mm&amp;r=g" alt="Qing.Ruo(奶龙版)">
                                <div class="withu-home-message-user-info">
                                    <div class="withu-home-message-name-row">
                                        <span class="withu-home-message-user-name">Qing.Ruo(奶龙版)</span>
                                        <span class="withu-home-message-badge withu-home-message-badge--level"><i class="ph ph-arrow-bend-down-right"></i> 二级</span>                                    </div>
                                    <span class="withu-home-message-post-time">2026-06-02 11:53</span>
                                </div>
                            </div>
                            <div class="withu-home-message-content">这是二级回复测试</div>
                            <div class="withu-home-message-divider"></div>
                            <div class="withu-home-message-footer">
                                                                <span class="withu-chip withu-chip--light"><i class="ph-fill ph-map-pin"></i> 湖北 · 武汉</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-devices"></i> Windows</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-globe"></i> Chrome</span>
                                                            </div>
                        </a>
                                                                    <a href="messages.php#comment_533" class="withu-home-message-card">
                            <div class="withu-home-message-header">
                                <img class="withu-home-message-avatar" src="https://weavatar.com/avatar/43c37253c302e96e98489ba43c055b5463e18bca05d4f70eaa544dfe834a6c71?s=100&amp;d=mm&amp;r=g" alt="Qing.Ruo(奶龙版)">
                                <div class="withu-home-message-user-info">
                                    <div class="withu-home-message-name-row">
                                        <span class="withu-home-message-user-name">Qing.Ruo(奶龙版)</span>
                                                                            </div>
                                    <span class="withu-home-message-post-time">2026-06-01 11:50</span>
                                </div>
                            </div>
                            <div class="withu-home-message-content"><img class="lazy" data-src="OwO/images/emoji/aru/E4B88DE587BAE68980E69699_2x.png" data-emoji=":@(不出所料)"/><img class="lazy" data-src="OwO/images/emoji/aru/E4B88DE587BAE68980E69699_2x.png" data-emoji=":@(不出所料)"/><img class="lazy" data-src="OwO/images/emoji/aru/E4B88DE587BAE68980E69699_2x.png" data-emoji=":@(不出所料)"/>百年好合</div>
                            <div class="withu-home-message-divider"></div>
                            <div class="withu-home-message-footer">
                                                                <span class="withu-chip withu-chip--light"><i class="ph-fill ph-map-pin"></i> 湖北 · 武汉</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-devices"></i> Android</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-globe"></i> Chrome</span>
                                                            </div>
                        </a>
                                                                    <a href="messages.php#comment_526" class="withu-home-message-card">
                            <div class="withu-home-message-header">
                                <img class="withu-home-message-avatar" src="https://weavatar.com/avatar/9a4eeef886a23c8a579df9a93d74651bb41e5de8ed539d355d7c21db80c1e8da?s=100&amp;d=mm&amp;r=g" alt="听雨">
                                <div class="withu-home-message-user-info">
                                    <div class="withu-home-message-name-row">
                                        <span class="withu-home-message-user-name">听雨</span>
                                                                            </div>
                                    <span class="withu-home-message-post-time">2026-05-17 02:13</span>
                                </div>
                            </div>
                            <div class="withu-home-message-content"><img class="lazy" data-src="OwO/images/emoji/qq/88.gif" data-emoji="::(88)"/><img class="lazy" data-src="OwO/images/emoji/qq/88.gif" data-emoji="::(88)"/><img class="lazy" data-src="OwO/images/emoji/qq/88.gif" data-emoji="::(88)"/></div>
                            <div class="withu-home-message-divider"></div>
                            <div class="withu-home-message-footer">
                                                                <span class="withu-chip withu-chip--light"><i class="ph-fill ph-map-pin"></i> 中国 · 新疆</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-devices"></i> Android</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-globe"></i> Chrome</span>
                                                            </div>
                        </a>
                                                                    <a href="messages.php#comment_527" class="withu-home-message-card">
                            <div class="withu-home-message-header">
                                <img class="withu-home-message-avatar" src="https://weavatar.com/avatar/a122f65031fefddae290ee10ef645e0457987386325d501444c5204eb84b6cf0?s=100&amp;d=mm&amp;r=g" alt="Ki.">
                                <div class="withu-home-message-user-info">
                                    <div class="withu-home-message-name-row">
                                        <span class="withu-home-message-user-name">Ki.</span>
                                        <span class="withu-home-message-badge withu-home-message-badge--developer"><i class="ph-fill ph-terminal-window"></i> 开发者</span>                                    </div>
                                    <span class="withu-home-message-post-time">2026-05-17 02:13</span>
                                </div>
                            </div>
                            <div class="withu-home-message-content">测试一条留言数据</div>
                            <div class="withu-home-message-divider"></div>
                            <div class="withu-home-message-footer">
                                                                <span class="withu-chip withu-chip--light"><i class="ph-fill ph-map-pin"></i> 广东 · 惠州</span>
                                                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-globe"></i> Safari</span>
                                                            </div>
                        </a>
                                                                    <a href="messages.php#comment_523" class="withu-home-message-card">
                            <div class="withu-home-message-header">
                                <img class="withu-home-message-avatar" src="https://weavatar.com/avatar/f06b376a446f6b7a0526c79699a09af4d8f67e4f230174bb3f7915128962ffcb?s=100&amp;d=mm&amp;r=g" alt="一清化剑.">
                                <div class="withu-home-message-user-info">
                                    <div class="withu-home-message-name-row">
                                        <span class="withu-home-message-user-name">一清化剑.</span>
                                                                            </div>
                                    <span class="withu-home-message-post-time">2026-04-30 21:25</span>
                                </div>
                            </div>
                            <div class="withu-home-message-content">天天开心.<img class="lazy" data-src="OwO/images/emoji/qq/A01.gif" data-emoji="::(A01)"/><img class="lazy" data-src="OwO/images/emoji/qq/A01.gif" data-emoji="::(A01)"/><img class="lazy" data-src="OwO/images/emoji/qq/A01.gif" data-emoji="::(A01)"/></div>
                            <div class="withu-home-message-divider"></div>
                            <div class="withu-home-message-footer">
                                                                <span class="withu-chip withu-chip--light"><i class="ph-fill ph-map-pin"></i> 中国 · 河南</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-devices"></i> Android</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-globe"></i> Chrome</span>
                                                            </div>
                        </a>
                                                                    <a href="messages.php#reply_521_522" class="withu-home-message-card">
                            <div class="withu-home-message-header">
                                <img class="withu-home-message-avatar" src="https://weavatar.com/avatar/a122f65031fefddae290ee10ef645e0457987386325d501444c5204eb84b6cf0?s=100&amp;d=mm&amp;r=g" alt="Ki.">
                                <div class="withu-home-message-user-info">
                                    <div class="withu-home-message-name-row">
                                        <span class="withu-home-message-user-name">Ki.</span>
                                        <span class="withu-home-message-badge withu-home-message-badge--developer"><i class="ph-fill ph-terminal-window"></i> 开发者</span><span class="withu-home-message-badge withu-home-message-badge--level"><i class="ph ph-arrow-bend-down-right"></i> 二级</span>                                    </div>
                                    <span class="withu-home-message-post-time">2026-04-22 21:59</span>
                                </div>
                            </div>
                            <div class="withu-home-message-content">测试二级评论</div>
                            <div class="withu-home-message-divider"></div>
                            <div class="withu-home-message-footer">
                                                                <span class="withu-chip withu-chip--light"><i class="ph-fill ph-map-pin"></i> 广东 · 惠州</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-devices"></i> Mac</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-globe"></i> Chrome</span>
                                                            </div>
                        </a>
                                                                    <a href="messages.php#comment_521" class="withu-home-message-card">
                            <div class="withu-home-message-header">
                                <img class="withu-home-message-avatar" src="https://weavatar.com/avatar/7ef891bf3c0bbd177ca5765a93b12f145f957dff9d862ddfc2d34ced7c4f2c4d?s=100&amp;d=mm&amp;r=g" alt="Ki.">
                                <div class="withu-home-message-user-info">
                                    <div class="withu-home-message-name-row">
                                        <span class="withu-home-message-user-name">Ki.</span>
                                        <span class="withu-home-message-badge withu-home-message-badge--admin"><i class="ph-fill ph-seal-check"></i> 管理员</span>                                    </div>
                                    <span class="withu-home-message-post-time">2026-04-22 21:58</span>
                                </div>
                            </div>
                            <div class="withu-home-message-content">测试一级评论<img class="lazy" data-src="OwO/images/emoji/threekids/14.png" data-emoji=":@(TK_14)"/><img class="lazy" data-src="OwO/images/emoji/threekids/14.png" data-emoji=":@(TK_14)"/><img class="lazy" data-src="OwO/images/emoji/threekids/14.png" data-emoji=":@(TK_14)"/></div>
                            <div class="withu-home-message-divider"></div>
                            <div class="withu-home-message-footer">
                                                                <span class="withu-chip withu-chip--light"><i class="ph-fill ph-map-pin"></i> 广东 · 惠州</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-devices"></i> Mac</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-globe"></i> Chrome</span>
                                                            </div>
                        </a>
                                                                    <a href="messages.php#comment_520" class="withu-home-message-card">
                            <div class="withu-home-message-header">
                                <img class="withu-home-message-avatar" src="https://weavatar.com/avatar/b79d5dc704f398313e2dd108021a399c2bd0adfd568b794ccc9499c621ac61d4?s=100&amp;d=mm&amp;r=g" alt="江奕浩">
                                <div class="withu-home-message-user-info">
                                    <div class="withu-home-message-name-row">
                                        <span class="withu-home-message-user-name">江奕浩</span>
                                                                            </div>
                                    <span class="withu-home-message-post-time">2026-04-22 21:22</span>
                                </div>
                            </div>
                            <div class="withu-home-message-content">祝福你们的心如同繁星闪耀 永远相伴不离 愿长长久久</div>
                            <div class="withu-home-message-divider"></div>
                            <div class="withu-home-message-footer">
                                                                <span class="withu-chip withu-chip--light"><i class="ph-fill ph-map-pin"></i> 内蒙古 · 通辽</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-devices"></i> Android</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-globe"></i> Chrome</span>
                                                            </div>
                        </a>
                                                                    <a href="messages.php#comment_519" class="withu-home-message-card">
                            <div class="withu-home-message-header">
                                <img class="withu-home-message-avatar" src="https://weavatar.com/avatar/6e4f1f21dce85d7fc7e9edf34d6e7796d5db3be2d5eda25de4c95ae8d345241b?s=100&amp;d=mm&amp;r=g" alt="轩">
                                <div class="withu-home-message-user-info">
                                    <div class="withu-home-message-name-row">
                                        <span class="withu-home-message-user-name">轩</span>
                                                                            </div>
                                    <span class="withu-home-message-post-time">2026-04-22 21:21</span>
                                </div>
                            </div>
                            <div class="withu-home-message-content">两情相悦春常在 一世恩爱梦也甜</div>
                            <div class="withu-home-message-divider"></div>
                            <div class="withu-home-message-footer">
                                                                <span class="withu-chip withu-chip--light"><i class="ph-fill ph-map-pin"></i> 江西 · 上饶</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-devices"></i> Android</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-globe"></i> Chrome</span>
                                                            </div>
                        </a>
                                                                    <a href="messages.php#comment_518" class="withu-home-message-card">
                            <div class="withu-home-message-header">
                                <img class="withu-home-message-avatar" src="https://weavatar.com/avatar/7a92b8dc2bdc586c5b761c7bb3ded431a0edb6def62c8fad50f97e0a3496c4f8?s=100&amp;d=mm&amp;r=g" alt="小嘿">
                                <div class="withu-home-message-user-info">
                                    <div class="withu-home-message-name-row">
                                        <span class="withu-home-message-user-name">小嘿</span>
                                                                            </div>
                                    <span class="withu-home-message-post-time">2026-04-22 21:21</span>
                                </div>
                            </div>
                            <div class="withu-home-message-content"><img class="lazy" data-src="OwO/images/emoji/qq/9.gif" data-emoji="::(9)"/><img class="lazy" data-src="OwO/images/emoji/qq/9.gif" data-emoji="::(9)"/><img class="lazy" data-src="OwO/images/emoji/qq/9.gif" data-emoji="::(9)"/>祝福你们的心灵似广袤天空般辽阔 彼此成就不凡 愿你们99</div>
                            <div class="withu-home-message-divider"></div>
                            <div class="withu-home-message-footer">
                                                                <span class="withu-chip withu-chip--light"><i class="ph-fill ph-map-pin"></i> 中国 · 浙江</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-devices"></i> iOS</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-globe"></i> Chrome</span>
                                                            </div>
                        </a>
                                                                    <a href="messages.php#reply_516_517" class="withu-home-message-card">
                            <div class="withu-home-message-header">
                                <img class="withu-home-message-avatar" src="https://weavatar.com/avatar/86587ba68b4fea9e6f44e77d9860f9341b210610c323bdcb6ca3db88b233a809?s=100&amp;d=mm&amp;r=g" alt=".">
                                <div class="withu-home-message-user-info">
                                    <div class="withu-home-message-name-row">
                                        <span class="withu-home-message-user-name">.</span>
                                        <span class="withu-home-message-badge withu-home-message-badge--admin"><i class="ph-fill ph-seal-check"></i> 管理员</span><span class="withu-home-message-badge withu-home-message-badge--level"><i class="ph ph-arrow-bend-down-right"></i> 二级</span>                                    </div>
                                    <span class="withu-home-message-post-time">2026-04-22 21:09</span>
                                </div>
                            </div>
                            <div class="withu-home-message-content">测试二级评论回复邮件问题<img class="lazy" data-src="OwO/images/emoji/threekids/4.png" data-emoji=":@(TK_4)"/></div>
                            <div class="withu-home-message-divider"></div>
                            <div class="withu-home-message-footer">
                                                                <span class="withu-chip withu-chip--light"><i class="ph-fill ph-map-pin"></i> 广东 · 惠州</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-devices"></i> Mac</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-globe"></i> Chrome</span>
                                                            </div>
                        </a>
                                                                    <a href="messages.php#comment_516" class="withu-home-message-card">
                            <div class="withu-home-message-header">
                                <img class="withu-home-message-avatar" src="https://weavatar.com/avatar/7ef891bf3c0bbd177ca5765a93b12f145f957dff9d862ddfc2d34ced7c4f2c4d?s=100&amp;d=mm&amp;r=g" alt="Ki.">
                                <div class="withu-home-message-user-info">
                                    <div class="withu-home-message-name-row">
                                        <span class="withu-home-message-user-name">Ki.</span>
                                        <span class="withu-home-message-badge withu-home-message-badge--admin"><i class="ph-fill ph-seal-check"></i> 管理员</span>                                    </div>
                                    <span class="withu-home-message-post-time">2026-04-22 21:07</span>
                                </div>
                            </div>
                            <div class="withu-home-message-content">测试邮件回复<img class="lazy" data-src="OwO/images/emoji/qq/101.gif" data-emoji="::(101)"/><img class="lazy" data-src="OwO/images/emoji/qq/101.gif" data-emoji="::(101)"/><img class="lazy" data-src="OwO/images/emoji/qq/101.gif" data-emoji="::(101)"/></div>
                            <div class="withu-home-message-divider"></div>
                            <div class="withu-home-message-footer">
                                                                <span class="withu-chip withu-chip--light"><i class="ph-fill ph-map-pin"></i> 广东 · 惠州</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-devices"></i> Mac</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-globe"></i> Chrome</span>
                                                            </div>
                        </a>
                                                                    <a href="messages.php#comment_514" class="withu-home-message-card">
                            <div class="withu-home-message-header">
                                <img class="withu-home-message-avatar" src="https://weavatar.com/avatar/43e538b0ccbd0e491d47baefe8d4816f77a1fdd58f5ed8663fb1283b377cfae6?s=100&amp;d=mm&amp;r=g" alt="泽北饱饱想吃糖">
                                <div class="withu-home-message-user-info">
                                    <div class="withu-home-message-name-row">
                                        <span class="withu-home-message-user-name">泽北饱饱想吃糖</span>
                                                                            </div>
                                    <span class="withu-home-message-post-time">2026-04-21 09:59</span>
                                </div>
                            </div>
                            <div class="withu-home-message-content">心像花朵欣然绽放 每日都弥漫着甜蜜的芬芳 愿你们爱情长长久久</div>
                            <div class="withu-home-message-divider"></div>
                            <div class="withu-home-message-footer">
                                                                <span class="withu-chip withu-chip--light"><i class="ph-fill ph-map-pin"></i> 贵州 · 黔西南布依族苗族自治州</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-devices"></i> Windows</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-globe"></i> Chrome</span>
                                                            </div>
                        </a>
                                                                    <a href="messages.php#comment_512" class="withu-home-message-card">
                            <div class="withu-home-message-header">
                                <img class="withu-home-message-avatar" src="https://weavatar.com/avatar/a9b68c9c93d54c0670271c778cb349230a33d24b2f34473970532ca513d0baa1?s=100&amp;d=mm&amp;r=g" alt="小晴挽星河✨✨">
                                <div class="withu-home-message-user-info">
                                    <div class="withu-home-message-name-row">
                                        <span class="withu-home-message-user-name">小晴挽星河✨✨</span>
                                                                            </div>
                                    <span class="withu-home-message-post-time">2026-04-21 00:27</span>
                                </div>
                            </div>
                            <div class="withu-home-message-content">执手相看情脉脉 倾心相守爱悠悠</div>
                            <div class="withu-home-message-divider"></div>
                            <div class="withu-home-message-footer">
                                                                <span class="withu-chip withu-chip--light"><i class="ph-fill ph-map-pin"></i> 龙场镇</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-devices"></i> Android</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-globe"></i> Chrome</span>
                                                            </div>
                        </a>
                                                                    <a href="messages.php#reply_508_511" class="withu-home-message-card">
                            <div class="withu-home-message-header">
                                <img class="withu-home-message-avatar" src="https://weavatar.com/avatar/86587ba68b4fea9e6f44e77d9860f9341b210610c323bdcb6ca3db88b233a809?s=100&amp;d=mm&amp;r=g" alt=".">
                                <div class="withu-home-message-user-info">
                                    <div class="withu-home-message-name-row">
                                        <span class="withu-home-message-user-name">.</span>
                                        <span class="withu-home-message-badge withu-home-message-badge--level"><i class="ph ph-arrow-bend-down-right"></i> 二级</span>                                    </div>
                                    <span class="withu-home-message-post-time">2026-04-16 20:21</span>
                                </div>
                            </div>
                            <div class="withu-home-message-content">测试留言回复是否邮件通知<img class="lazy" data-src="OwO/images/emoji/threekids/6.png" data-emoji=":@(TK_6)"/></div>
                            <div class="withu-home-message-divider"></div>
                            <div class="withu-home-message-footer">
                                                                <span class="withu-chip withu-chip--light"><i class="ph-fill ph-map-pin"></i> 广东 · 惠州</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-devices"></i> Mac</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-globe"></i> Chrome</span>
                                                            </div>
                        </a>
                                                                    <a href="messages.php#comment_508" class="withu-home-message-card">
                            <div class="withu-home-message-header">
                                <img class="withu-home-message-avatar" src="https://weavatar.com/avatar/7ef891bf3c0bbd177ca5765a93b12f145f957dff9d862ddfc2d34ced7c4f2c4d?s=100&amp;d=mm&amp;r=g" alt="Ki.">
                                <div class="withu-home-message-user-info">
                                    <div class="withu-home-message-name-row">
                                        <span class="withu-home-message-user-name">Ki.</span>
                                        <span class="withu-home-message-badge withu-home-message-badge--admin"><i class="ph-fill ph-seal-check"></i> 管理员</span>                                    </div>
                                    <span class="withu-home-message-post-time">2026-04-16 19:39</span>
                                </div>
                            </div>
                            <div class="withu-home-message-content">测试一下邮件通知</div>
                            <div class="withu-home-message-divider"></div>
                            <div class="withu-home-message-footer">
                                                                <span class="withu-chip withu-chip--light"><i class="ph-fill ph-map-pin"></i> 广东 · 惠州</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-devices"></i> Mac</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-globe"></i> Chrome</span>
                                                            </div>
                        </a>
                                                                    <a href="messages.php#comment_507" class="withu-home-message-card">
                            <div class="withu-home-message-header">
                                <img class="withu-home-message-avatar" src="https://weavatar.com/avatar/86587ba68b4fea9e6f44e77d9860f9341b210610c323bdcb6ca3db88b233a809?s=100&amp;d=mm&amp;r=g" alt=".">
                                <div class="withu-home-message-user-info">
                                    <div class="withu-home-message-name-row">
                                        <span class="withu-home-message-user-name">.</span>
                                        <span class="withu-home-message-badge withu-home-message-badge--admin"><i class="ph-fill ph-seal-check"></i> 管理员</span>                                    </div>
                                    <span class="withu-home-message-post-time">2026-04-14 19:00</span>
                                </div>
                            </div>
                            <div class="withu-home-message-content">携手相伴情路远 爱意绵延岁月长<img class="lazy" data-src="OwO/images/emoji/threekids/31.png" data-emoji=":@(TK_31)"/></div>
                            <div class="withu-home-message-divider"></div>
                            <div class="withu-home-message-footer">
                                                                <span class="withu-chip withu-chip--light"><i class="ph-fill ph-map-pin"></i> 广东 · 惠州</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-devices"></i> Mac</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-globe"></i> Chrome</span>
                                                            </div>
                        </a>
                                                                    <a href="messages.php#reply_501_506" class="withu-home-message-card">
                            <div class="withu-home-message-header">
                                <img class="withu-home-message-avatar" src="Lovefolder/20250310095445_67ce466597870.gif" alt="匿名">
                                <div class="withu-home-message-user-info">
                                    <div class="withu-home-message-name-row">
                                        <span class="withu-home-message-user-name">匿名</span>
                                        <span class="withu-home-message-badge withu-home-message-badge--level"><i class="ph ph-arrow-bend-down-right"></i> 二级</span>                                    </div>
                                    <span class="withu-home-message-post-time">2026-04-12 16:42</span>
                                </div>
                            </div>
                            <div class="withu-home-message-content">同心同德情不断 相亲相爱到永远1 月底到期了吗？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？？<img class="lazy" data-src="OwO/images/emoji/threekids/1.png" data-emoji=":@(TK_1)"/><img class="lazy" data-src="OwO/images/emoji/threekids/1.png" data-emoji=":@(TK_1)"/><img class="lazy" data-src="OwO/images/emoji/threekids/1.png" data-emoji=":@(TK_1)"/></div>
                            <div class="withu-home-message-divider"></div>
                            <div class="withu-home-message-footer">
                                                                <span class="withu-chip withu-chip--light"><i class="ph-fill ph-map-pin"></i> 广东 · 惠州</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-devices"></i> iOS</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-globe"></i> Chrome</span>
                                                            </div>
                        </a>
                                                                    <a href="messages.php#comment_504" class="withu-home-message-card">
                            <div class="withu-home-message-header">
                                <img class="withu-home-message-avatar" src="https://weavatar.com/avatar/87146fa766ead530e2fbe7ba1000ee30beab30e96c23d54c326db0864a18e441?s=100&amp;d=mm&amp;r=g" alt="Ki.">
                                <div class="withu-home-message-user-info">
                                    <div class="withu-home-message-name-row">
                                        <span class="withu-home-message-user-name">Ki.</span>
                                        <span class="withu-home-message-badge withu-home-message-badge--admin"><i class="ph-fill ph-seal-check"></i> 管理员</span>                                    </div>
                                    <span class="withu-home-message-post-time">2026-04-10 17:43</span>
                                </div>
                            </div>
                            <div class="withu-home-message-content">测试一下归属地问题</div>
                            <div class="withu-home-message-divider"></div>
                            <div class="withu-home-message-footer">
                                                                <span class="withu-chip withu-chip--light"><i class="ph-fill ph-map-pin"></i> 广东 · 惠州</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-devices"></i> Mac</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-globe"></i> Chrome</span>
                                                            </div>
                        </a>
                                                                    <a href="messages.php#comment_503" class="withu-home-message-card">
                            <div class="withu-home-message-header">
                                <img class="withu-home-message-avatar" src="https://weavatar.com/avatar/73e1eb095f75a569e42275bde8ae9fd38e71a471b680d85dbc4450834a4eaf55?s=100&amp;d=mm&amp;r=g" alt="𝘈𝘱𝘰𝘭𝘰𝘨𝘪𝘻𝘦">
                                <div class="withu-home-message-user-info">
                                    <div class="withu-home-message-name-row">
                                        <span class="withu-home-message-user-name">𝘈𝘱𝘰𝘭𝘰𝘨𝘪𝘻𝘦</span>
                                        <span class="withu-home-message-badge withu-home-message-badge--admin"><i class="ph-fill ph-seal-check"></i> 管理员</span>                                    </div>
                                    <span class="withu-home-message-post-time">2026-04-10 16:57</span>
                                </div>
                            </div>
                            <div class="withu-home-message-content">测试</div>
                            <div class="withu-home-message-divider"></div>
                            <div class="withu-home-message-footer">
                                                                <span class="withu-chip withu-chip--light"><i class="ph-fill ph-map-pin"></i> 未知</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-devices"></i> Mac</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-globe"></i> Chrome</span>
                                                            </div>
                        </a>
                                                                    <a href="messages.php#comment_502" class="withu-home-message-card">
                            <div class="withu-home-message-header">
                                <img class="withu-home-message-avatar" src="https://weavatar.com/avatar/87146fa766ead530e2fbe7ba1000ee30beab30e96c23d54c326db0864a18e441?s=100&amp;d=mm&amp;r=g" alt="袁小K">
                                <div class="withu-home-message-user-info">
                                    <div class="withu-home-message-name-row">
                                        <span class="withu-home-message-user-name">袁小K</span>
                                        <span class="withu-home-message-badge withu-home-message-badge--admin"><i class="ph-fill ph-seal-check"></i> 管理员</span>                                    </div>
                                    <span class="withu-home-message-post-time">2026-04-09 19:06</span>
                                </div>
                            </div>
                            <div class="withu-home-message-content">共赴爱河情无尽 同享人生乐无边<img class="lazy" data-src="OwO/images/emoji/douyin/dy109.gif" data-emoji=":@(DY_109)"/><img class="lazy" data-src="OwO/images/emoji/douyin/dy109.gif" data-emoji=":@(DY_109)"/><img class="lazy" data-src="OwO/images/emoji/douyin/dy109.gif" data-emoji=":@(DY_109)"/></div>
                            <div class="withu-home-message-divider"></div>
                            <div class="withu-home-message-footer">
                                                                <span class="withu-chip withu-chip--light"><i class="ph-fill ph-map-pin"></i> 广东 · 惠州</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-devices"></i> Mac</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-globe"></i> Chrome</span>
                                                            </div>
                        </a>
                                                                    <a href="messages.php#comment_501" class="withu-home-message-card">
                            <div class="withu-home-message-header">
                                <img class="withu-home-message-avatar" src="https://weavatar.com/avatar/afe9b79a36417da3cf1149214441f97cd427ba11c30a731d9086a6d4672baedf?s=100&amp;d=mm&amp;r=g" alt="Mental derangement">
                                <div class="withu-home-message-user-info">
                                    <div class="withu-home-message-name-row">
                                        <span class="withu-home-message-user-name">Mental derangement</span>
                                                                            </div>
                                    <span class="withu-home-message-post-time">2026-04-09 11:42</span>
                                </div>
                            </div>
                            <div class="withu-home-message-content"><img class="lazy" data-src="OwO/images/emoji/threekids/12.png" data-emoji=":@(TK_12)"/>同心同德情不断 相亲相爱到永远</div>
                            <div class="withu-home-message-divider"></div>
                            <div class="withu-home-message-footer">
                                                                <span class="withu-chip withu-chip--light"><i class="ph-fill ph-map-pin"></i> 中国 · 辽宁</span>
                                                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-globe"></i> Chrome</span>
                                                            </div>
                        </a>
                                                                    <a href="messages.php#comment_500" class="withu-home-message-card">
                            <div class="withu-home-message-header">
                                <img class="withu-home-message-avatar" src="https://weavatar.com/avatar/a583b0a809b5cbfa2ce8c534e63d2cf8762c0345f049227453a4220bcd295d15?s=100&amp;d=mm&amp;r=g" alt="宇柯">
                                <div class="withu-home-message-user-info">
                                    <div class="withu-home-message-name-row">
                                        <span class="withu-home-message-user-name">宇柯</span>
                                                                            </div>
                                    <span class="withu-home-message-post-time">2026-04-09 07:05</span>
                                </div>
                            </div>
                            <div class="withu-home-message-content">于彼此陪伴之时 你们定能发掘生活的每一帧美好瞬间 愿爱情99</div>
                            <div class="withu-home-message-divider"></div>
                            <div class="withu-home-message-footer">
                                                                <span class="withu-chip withu-chip--light"><i class="ph-fill ph-map-pin"></i> 江苏 · 扬州</span>
                                                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-globe"></i> Chrome</span>
                                                            </div>
                        </a>
                                                                    <a href="messages.php#comment_496" class="withu-home-message-card">
                            <div class="withu-home-message-header">
                                <img class="withu-home-message-avatar" src="https://weavatar.com/avatar/a122f65031fefddae290ee10ef645e0457987386325d501444c5204eb84b6cf0?s=100&amp;d=mm&amp;r=g" alt="Ki.">
                                <div class="withu-home-message-user-info">
                                    <div class="withu-home-message-name-row">
                                        <span class="withu-home-message-user-name">Ki.</span>
                                        <span class="withu-home-message-badge withu-home-message-badge--developer"><i class="ph-fill ph-terminal-window"></i> 开发者</span>                                    </div>
                                    <span class="withu-home-message-post-time">2026-04-09 04:00</span>
                                </div>
                            </div>
                            <div class="withu-home-message-content">测试一条留言数据</div>
                            <div class="withu-home-message-divider"></div>
                            <div class="withu-home-message-footer">
                                                                <span class="withu-chip withu-chip--light"><i class="ph-fill ph-map-pin"></i> 广东 · 惠州</span>
                                                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-globe"></i> Safari</span>
                                                            </div>
                        </a>
                                                                    <a href="messages.php#comment_495" class="withu-home-message-card">
                            <div class="withu-home-message-header">
                                <img class="withu-home-message-avatar" src="https://weavatar.com/avatar/e9ea60a17075cc4a050bcefbd4f114afc9be98b8e5f938d3b63e01935ec563f2?s=100&amp;d=mm&amp;r=g" alt="星期六">
                                <div class="withu-home-message-user-info">
                                    <div class="withu-home-message-name-row">
                                        <span class="withu-home-message-user-name">星期六</span>
                                                                            </div>
                                    <span class="withu-home-message-post-time">2026-03-24 20:31</span>
                                </div>
                            </div>
                            <div class="withu-home-message-content">情定今生 爱如繁星闪耀 永恒璀璨</div>
                            <div class="withu-home-message-divider"></div>
                            <div class="withu-home-message-footer">
                                                                <span class="withu-chip withu-chip--light"><i class="ph-fill ph-map-pin"></i> 未知</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-devices"></i> iOS</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-globe"></i> Chrome</span>
                                                            </div>
                        </a>
                                                                    <a href="messages.php#comment_494" class="withu-home-message-card">
                            <div class="withu-home-message-header">
                                <img class="withu-home-message-avatar" src="https://weavatar.com/avatar/e9ea60a17075cc4a050bcefbd4f114afc9be98b8e5f938d3b63e01935ec563f2?s=100&amp;d=mm&amp;r=g" alt="星期六">
                                <div class="withu-home-message-user-info">
                                    <div class="withu-home-message-name-row">
                                        <span class="withu-home-message-user-name">星期六</span>
                                                                            </div>
                                    <span class="withu-home-message-post-time">2026-03-24 01:54</span>
                                </div>
                            </div>
                            <div class="withu-home-message-content">琴瑟和鸣 奏响爱情乐章 相伴到天荒</div>
                            <div class="withu-home-message-divider"></div>
                            <div class="withu-home-message-footer">
                                                                <span class="withu-chip withu-chip--light"><i class="ph-fill ph-map-pin"></i> 未知</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-devices"></i> iOS</span>
                                                                                                <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-globe"></i> Safari</span>
                                                            </div>
                        </a>
                                        </div>
                </div>
            </section>
            
            <!-- 6. Ending: 笔记本卡片式结尾 -->
            <section class="withu-epilogue" data-aos="fade-up" data-aos-delay="300">
                <div class="withu-epilogue__card">

                    <!-- 顶部活页孔 -->
                    <div class="withu-epilogue__holes">
                        <div class="withu-epilogue__hole"></div>
                        <div class="withu-epilogue__hole"></div>
                        <div class="withu-epilogue__hole"></div>
                        <div class="withu-epilogue__hole"></div>
                        <div class="withu-epilogue__hole"></div>
                        <div class="withu-epilogue__hole"></div>
                        <div class="withu-epilogue__hole"></div>
                        <div class="withu-epilogue__hole"></div>
                        <div class="withu-epilogue__hole"></div>
                        <div class="withu-epilogue__hole"></div>
                        <div class="withu-epilogue__hole"></div>
                        <div class="withu-epilogue__hole"></div>
                    </div>

                    <!-- 头部 -->
                    <div class="withu-epilogue__header">
                        <div class="withu-epilogue__title">未完 · 待续</div>
                    </div>

                    <!-- 文案区 -->
                    <div class="withu-epilogue__quote-container">
                        <h3 id="epilogue-quote-text" class="withu-epilogue__quote-text">
                            <!-- JS 动态注入 -->
                        </h3>
                    </div>

                    <!-- 底部功能区 -->
                    <div class="withu-epilogue__actions">

                        <!-- 左侧导航 -->
                        <div class="withu-epilogue__nav">
                            <a href="javascript:void(0)" class="withu-epilogue__btn-pill" id="epilogue-leaving-btn">
                                <i class="ph-bold ph-feather"></i> 留下祝福
                            </a>
                            <a href="javascript:void(0)" class="withu-epilogue__btn-pill" id="epilogue-random-album">
                                <i class="ph-bold ph-aperture"></i> 随机光影
                            </a>
                            <a href="javascript:void(0)" class="withu-epilogue__btn-pill"
                                id="epilogue-random-article">
                                <i class="ph-bold ph-coffee"></i> 随机碎片
                            </a>
                        </div>

                        <!-- 右侧工具 -->
                        <div class="withu-epilogue__tools">
                            <button id="epilogue-btn-refresh" class="withu-epilogue__btn-icon" title="换一句">
                                <i class="ph-bold ph-shuffle"></i>
                            </button>
                            <button id="epilogue-btn-copy" class="withu-epilogue__btn-icon" title="复制文案">
                                <i class="ph-bold ph-copy"></i>
                            </button>
                        </div>

                    </div>

                </div>
            </section>

        </main>
    </div>

    
    <!-- 留言弹窗遮罩层 -->
    <div class="mask" id="mask">
        <div class="close">
            <svg t="1682818912164" class="icon" viewBox="0 0 1024 1024" version="1.1"
                xmlns="http://www.w3.org/2000/svg" p-id="2416" width="200" height="200">
                <path
                    d="M550.848 502.496l308.64-308.896a31.968 31.968 0 1 0-45.248-45.248l-308.608 308.896-308.64-308.928a31.968 31.968 0 1 0-45.248 45.248l308.64 308.896-308.64 308.896a31.968 31.968 0 1 0 45.248 45.248l308.64-308.896 308.608 308.896a31.968 31.968 0 1 0 45.248-45.248l-308.64-308.864z"
                    p-id="2417"></path>
            </svg>
        </div>
    </div>

    <!-- 表情面板（全局可用，弹窗 & 抽屉共用） -->
    <div class="withu-message-emoji-panel" id="withumsgEmojiPanel">
        <div class="withu-message-emoji-tabs-wrap">
            <div class="withu-message-emoji-tabs" id="withumsgEmojiTabs"></div>
        </div>
        <div class="withu-message-emoji-cat-title" id="withumsgEmojiCatTitle"></div>
        <div class="withu-message-emoji-list" id="withumsgEmojiGrid"></div>
    </div>

    <div class="withu-message-emoji-preview" id="withumsgEmojiPreview">
        <img src="" id="withumsgPreviewImg">
        <span id="withumsgPreviewText"></span>
    </div>

    <!-- 留言触发按钮 -->
    <div class="message_btn" id="mes">
        <span class="mesly shadow-blur">
            <i data-lucide="message-circle" style="width:2rem;height:2rem;fill:currentColor;stroke:none;"></i>
        </span>
    </div>

    <!-- 随机一言确认弹窗（about.php 风格） -->
    <div class="withumsg-confirm-overlay" id="withumsgConfirmOverlay">
        <div class="withumsg-confirm-panel">
            <!-- 关闭按钮 -->
            <button class="withumsg-confirm-close-btn" id="withumsgConfirmClose" aria-label="关闭">
                <i class="ph ph-x"></i>
            </button>
            <!-- 图标 -->
            <div class="withumsg-confirm-icon-wrapper">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.582a.5.5 0 0 1 0 .962L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/><path d="M20 3v4"/><path d="M22 5h-4"/></svg>
            </div>
            <!-- 标题 -->
            <h2 class="withumsg-confirm-title">替换为随机一言？</h2>
            <!-- 描述 -->
            <p class="withumsg-confirm-desc">当前输入框已有内容，确认后将清空并替换为一条随机文案</p>
            <!-- 操作按钮 -->
            <div class="withumsg-confirm-actions">
                <button class="withumsg-confirm-btn withumsg-confirm-btn-secondary" id="withumsgConfirmCancel">取消</button>
                <button class="withumsg-confirm-btn withumsg-confirm-btn-primary" id="withumsgConfirmOk">确认替换</button>
            </div>
        </div>
    </div>

    <!-- 留言弹窗（全局可用） -->
    <div class="withu-message-modal-overlay" id="withumsgCommentModal">
        <div class="withu-message-modal-content" id="withumsgModalContent">
            <div class="withu-message-close-wrapper">
                <button class="withu-message-close-btn" id="withumsgModalCloseBtn">
                    <i data-lucide="x" style="width:20px;height:20px;"></i>
                </button>
            </div>
            <div class="withu-message-modal-body">
                <div class="withu-message-head-titles">
                    <div class="withu-message-title">写一条留言</div>
                    <div class="withu-message-subtitle">在这里，留下属于你的印记</div>
                </div>
                <div class="withu-message-ios-tabs-wrap">
                    <div class="withu-message-ios-tabs" id="withumsgTabContainer">
                        <div class="withu-message-ios-tab-slider" id="withumsgTabSlider"></div>
                        <div class="withu-message-ios-tab active" data-mode="qq">QQ留言</div>
                        <div class="withu-message-ios-tab" data-mode="anonymous">匿名留言</div>
                    </div>
                </div>
                <div class="withu-message-visitor-tags" id="withumsgVisitorTags">
                    <div class="withu-message-v-tag">
                        <div class="withu-message-v-tag-icon withu-message-icon-os">
                            <i data-lucide="monitor"></i>
                        </div>
                        <span id="withumsgTagOS">--</span>
                    </div>
                    <div class="withu-message-v-tag">
                        <div class="withu-message-v-tag-icon withu-message-icon-browser">
                            <i data-lucide="globe"></i>
                        </div>
                        <span id="withumsgTagBrowser">--</span>
                    </div>
                    <div class="withu-message-v-tag">
                        <div class="withu-message-v-tag-icon withu-message-icon-location">
                            <i data-lucide="map-pin"></i>
                        </div>
                        <span id="withumsgTagLocation">--</span>
                    </div>
                    <div class="withu-message-v-tag">
                        <div class="withu-message-v-tag-icon withu-message-icon-weather">
                            <i class="qi-100-fill" id="withumsgWeatherIcon"></i>
                        </div>
                        <span id="withumsgTagWeather">--</span>
                    </div>
                </div>
                <div class="withu-message-input-row" id="withumsgInputRow"></div>
                <div class="withu-message-privacy-hint" id="withumsgPrivacyHint"><i data-lucide="lock"></i>QQ 信息经过加密脱敏处理，不会公开展示，请放心留言</div>
                <div class="withu-message-editor-wrap">
                    <div class="withu-message-editor-content" id="withumsgEditor" contenteditable="true" data-placeholder="想说点什么..."></div>
                    <div class="withu-message-emoji-bubbles" id="withumsgEmojiBubbles"></div>
                    <div class="withu-message-editor-toolbar">
                        <div class="withu-message-tb-left">
                            <button class="withu-message-tb-btn" id="withumsgBtnEmoji" title="表情">
                                <i data-lucide="smile"></i>
                            </button>
                            <button class="withu-message-tb-btn" id="withumsgBtnQuote" title="随机一言">
                                <i data-lucide="sparkles"></i>
                            </button>
                            <div class="withu-message-switch-wrap" id="withumsgEnterToSendWrap">
                                <div class="withu-message-switch"></div>
                                <span class="withu-message-switch-text">Enter 发送</span>
                            </div>
                        </div>
                        <span class="withu-message-char-counter" id="withumsgCharCounter">0/500</span>
                        <button class="withu-message-submit-btn" id="withumsgSubmitBtn">
                            <span class="withu-message-submit-label">发送留言</span>
                            <i data-lucide="send" class="withu-message-submit-icon" style="width:18px;height:18px;"></i>
                            <i data-lucide="loader" class="withu-message-submit-loader withu-message-lucide-loader" style="width:18px;height:18px;"></i>
                            <i data-lucide="check" class="withu-message-submit-check" style="width:18px;height:18px;"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 留言弹窗配置输出 -->
    <script>
        window.WITHU_CONFIG = window.WITHU_CONFIG || {};
        window.WITHU_CONFIG.userCity = "湖北 · 武汉";
        window.WITHU_CONFIG.anonymousAvatar = "Lovefolder/20250310095445_67ce466597c27.webp";
    </script>

    <!-- 极验验证与留言提交绑定 -->
    <script src="/assets/js/withu-shared-0c59da72.js"></script>

<link rel="stylesheet" href="Style/Font/font_footer/iconfont.css">
    <link rel="stylesheet" href="/assets/fonts/pacifico.css">

<script src="Style/vendor/confetti/confetti.browser.min.js"></script>
<script src="assets/js/page-messages.js"></script>
<script src="Style/toastify/lucide.min.js"></script>
<script src="Style/toastify/toastify.js"></script>
<script>if(typeof lucide!=='undefined')lucide.createIcons();</script>
<script src="Style/js/clipboard.min.js"></script>
<script src="assets/js/clipboard.js"></script>
<script src="assets/js/tooltip.js"></script>
<script src="Style/js/view-image.min.js"></script>
<script src="/assets/js/webp-default.js?v=20260830"></script>
<script src="Style/LoveListStyle/carousel.umd.js"></script>
<script src="Style/LoveListStyle/carousel.thumbs.umd.js"></script>
<script src="Style/LoveListStyle/fancybox.umd.js"></script>
<script src="assets/js/page-lovelist.js"></script>
<script src="assets/js/page-index.js?v=20260906"></script>
<script src="assets/js/page-timetable.js?v=20260907-3"></script>
<script src="assets/js/page-detail.js"></script>
<script src="assets/js/page-album-detail.js"></script>
<script src="assets/js/html2canvas.min.js"></script>
<script src="assets/js/chat.js"></script>

<script src="assets/js/visitor-hash.js"></script>
<script src="assets/js/interaction.js"></script>
<script src="assets/js/context-menu.js"></script>
<script src="assets/js/sakura.js"></script>

<!-- 足迹地图弹窗 -->
<!-- ============ 足迹地图弹窗 ============ -->
<div class="withu-map-overlay" id="withuMapOverlay" style="display:none;">
    <div class="withu-map-modal">
        <div class="withu-map">
            <section id="missing-pets-module">
                <div class="missing-pets-wrap">
                    <div id="missing-pets-map"></div>

                    <div class="ui-footer-container" id="ui-footer">
                        <div class="ui-footer-left">
                            <div class="ui-footer-title" id="footer-title">情侣模式</div>
                            <div class="ui-footer-sub" id="footer-sub">
                                <span class="status-dot"></span>
                                <span id="footer-desc">无论相隔多远，心始终在一起</span>
                            </div>
                        </div>
                        <div class="ui-footer-right">
                            <div class="withu-badge">
                                <div class="withu-icon-circle">withU</div>
                                <div class="withu-text-thin">withU</div>
                            </div>
                            <div class="ui-footer-copy">
                                Powered by <span class="footer-amap-logo">
                                    <svg t="1767096719086" class="icon" viewBox="0 0 1024 1024" version="1.1"
                                        xmlns="http://www.w3.org/2000/svg" p-id="1907" width="256" height="256">
                                        <path d="M658.285714 621.714286h365.714286v256a146.285714 146.285714 0 0 1-146.285714 146.285714h-219.428572V621.714286z" fill="#B2D8FF" p-id="1908"></path>
                                        <path d="M1024 364.397714V218.624H0v145.773714z" fill="#FFFFFF" p-id="1909"></path>
                                        <path d="M649.142857 1024h145.773714V0H649.142857z" fill="#FFFFFF" p-id="1910"></path>
                                        <path d="M1024 729.417143v-145.773714H0v145.773714z" fill="#FFCF68" p-id="1911"></path>
                                        <path d="M0 218.624h649.179429V0H146.285714a146.285714 146.285714 0 0 0-146.285714 146.285714v72.338286z" fill="#AFE881" p-id="1912"></path>
                                        <path d="M195.803429 1024H341.577143V0H195.803429z" fill="#FFCF68" p-id="1913"></path>
                                        <path d="M103.862857 543.670857L349.622857 618.057143l302.628572-256.950857-234.569143 276.772571 262.765714 81.188572 135.314286-520.192z" fill="#0093FD" p-id="1914"></path>
                                        <path d="M652.251429 361.142857L349.586286 618.057143l68.096 19.821714z" fill="#0066BD" p-id="1915"></path>
                                        <path d="M349.622857 618.093714v143.908572l97.938286-114.834286-97.974857-29.074286z" fill="#0064BB" p-id="1916"></path>
                                    </svg>
                                    高德地图</span><br>
                                © 2025 Ki All Rights Reserved.
                            </div>
                        </div>
                    </div>

                    <div class="full-screen-function">
                        <button id="map-zoom" type="button" class="control-icon-button" aria-label="重置缩放">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="control-icon">
                                <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 0 1-9.201 2.466l-.312-.311h2.433a.75.75 0 0 0 0-1.5H3.989a.75.75 0 0 0-.75.75v4.242a.75.75 0 0 0 1.5 0v-2.43l.31.31a7 7 0 0 0 11.712-3.138.75.75 0 0 0-1.449-.39Zm1.23-3.723a.75.75 0 0 0 .219-.53V2.929a.75.75 0 0 0-1.5 0V5.36l-.31-.31A7 7 0 0 0 3.239 8.188a.75.75 0 1 0 1.448.389A5.5 5.5 0 0 1 13.89 6.11l.311.31h-2.432a.75.75 0 0 0 0 1.5h4.243a.75.75 0 0 0 .53-.219Z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <button id="full-screen-button" type="button" class="control-icon-button" aria-label="全屏切换">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="control-icon">
                                <path d="m13.28 7.78 3.22-3.22v2.69a.75.75 0 0 0 1.5 0v-4.5a.75.75 0 0 0-.75-.75h-4.5a.75.75 0 0 0 0 1.5h2.69l-3.22 3.22a.75.75 0 0 0 1.06 1.06ZM2 17.25v-4.5a.75.75 0 0 1 1.5 0v2.69l3.22-3.22a.75.75 0 0 1 1.06 1.06L4.56 16.5h2.69a.75.75 0 0 1 0 1.5h-4.5a.747.747 0 0 1-.75-.75ZM12.22 13.28l3.22 3.22h-2.69a.75.75 0 0 0 0 1.5h4.5a.747.747 0 0 0 .75-.75v-4.5a.75.75 0 0 0-1.5 0v2.69l-3.22-3.22a.75.75 0 1 0-1.06 1.06ZM3.5 4.56l3.22 3.22a.75.75 0 0 0 1.06-1.06L4.56 3.5h2.69a.75.75 0 0 0 0-1.5h-4.5a.75.75 0 0 0-.75.75v4.5a.75.75 0 0 0 1.5 0V4.56Z" />
                            </svg>
                        </button>
                    </div>

                    <!-- 缩放倍数显示器 -->
                    <div class="zoom-indicator" id="zoom-indicator">
                        <span class="zoom-current" id="zoom-current">5</span>
                        <span class="zoom-range">/ 2-20</span>
                    </div>

                    <!-- 模式切换器 -->
                    <div class="mode-switcher" id="mode-switcher">
                        <button class="mode-btn active" data-mode="lovers" title="情侣模式">
                                                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z" /></svg>
                                                    </button>
                        <button class="mode-btn" data-mode="moments" title="点点滴滴">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>
                        </button>
                        <button class="mode-btn" data-mode="messages" title="留言模式">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                        </button>
                        <button class="mode-btn" data-mode="albums" title="相册模式">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                        </button>
                        <button class="mode-btn" data-mode="events" title="事件清单">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                        </button>
                    </div>
                </div>
            </section>

            <!-- 情侣信息面板 -->
            <div class="lovers-panel" id="lovers-panel">
                <div class="lover-card lover-left" id="lover-left">
                    <div class="avatar-box"><img src="" alt="我" id="lover-left-avatar" class="avatar-img"></div>
                    <div class="lover-info">
                        <div class="lover-name" id="lover-left-name">我</div>
                        <div class="lover-meta" id="lover-left-meta">
                            <i class="ri-loader-4-line" id="lover-left-weather-icon"></i>
                            <span id="lover-left-location">加载中...</span>
                        </div>
                    </div>
                </div>
                <div class="love-distance-center">
                    <i class="ri-map-pin-fill distance-icon" id="distance-icon"></i>
                    <div class="distance-val" id="love-distance-text">计算中...</div>
                </div>
                <div class="lover-card lover-right" id="lover-right">
                    <div class="lover-info">
                        <div class="lover-name" id="lover-right-name">TA</div>
                        <div class="lover-meta" id="lover-right-meta">
                            <i class="ri-loader-4-line" id="lover-right-weather-icon"></i>
                            <span id="lover-right-location">加载中...</span>
                        </div>
                    </div>
                    <div class="avatar-box"><img src="" alt="TA" id="lover-right-avatar" class="avatar-img"></div>
                </div>
            </div>

            <div class="love-distance-panel" id="love-distance-panel">
                <div class="panel-title">我们之间的距离</div>
                <div class="panel-body" id="love-distance-text-panel">加载中...</div>
            </div>
        </div>
    </div>
</div>
<!-- ============ /足迹地图弹窗 ============ -->
<script src="assets/js/map.js"></script>

<div id="pjax-container">

    
    <div id="withuFloatingActions">
        
        
        <a href="javascript:void(0)" id="scrollTopBtn" title="回到顶部">
            <i class="ph-fill ph-arrow-circle-up"></i>
        </a>
    </div>

    <script>
    // 加密免验提示点击
    (function () {
        var btn = document.getElementById('withuEncryptHint');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var label = this.getAttribute('data-encrypt-label') || '加密';
            var msg = '当前处于「' + label + '」保护中，因管理员已登录自动免验通过';
            if (typeof Toastify !== 'undefined' && Toastify.showScenario) {
                Toastify.showScenario('info', { text: msg });
            } else {
                alert(msg);
            }
        });
    })();
    </script>

    <script src="/assets/js/withu-shared-c69475f5.js"></script>

    <style>
        .NotAbout {
            display: none;
        }

        .about_y {
            font-size: 2rem;
            background: #ffffff;
            padding: 0.8rem;
            margin-left: 1rem;
            border-radius: 1rem;
            color: #03A9F4;
            position: fixed;
            right: 1rem;
            bottom: 7.5rem;
            z-index: 100;
            box-shadow: 0 3px 10px #bdb7b78c;
            border: 1px solid #fff;
            transition: 0.1s all;
        }

        .about_y:hover {
            background: #03A9F4;
            color: #ffffff;
        }

        .icon {
            width: 1.5em;
            height: 1.5em;
            vertical-align: -0.3em;
            fill: currentColor;
            overflow: hidden;
        }

        li.cike {
            border-bottom: 1px solid #ddd;
        }

        li {
            list-style-type: none;
        }

        .cike:hover {
            cursor: pointer;
            cursor: url(Style/cur/hover.cur), pointer;
        }

        button:disabled {
            background: #888;
            opacity: 0.6;
        }

        .avatar {
            width: 2.5em;
            height: 2.5em;
            border-radius: 50%;
            box-shadow: 0 2px 8px #a9a9a98c;
            border: 2px solid #fff;
            margin-right: 0.8rem;
        }

        .footer-warp {
            background: #ffffff;
            margin-top: 0;
            border-top: 1px solid #efefef;
            padding: 2rem 0;
        }

        .footer-warp .footer {
            padding-bottom: 0;
        }

        .footer-warp .footer p {
            line-height: 1.2rem;
            margin: 0.5rem auto 0;
        }

        .github-badge {
            display: inline-block;
            border-radius: 4px;
            text-shadow: none;
            font-size: 12px;
            color: #fff;
            line-height: 15px;
            background-color: #5d5d5d;
            margin-bottom: 5px;
            white-space: nowrap;

        }

        .footer .github-badge .badge-subject img {
            width: 12px;
            vertical-align: bottom;
            margin: 0 .3rem;
        }

        .github-badge:hover {
            color: #fafafa;
        }

        .github-badge .badge-subject {
            display: inline-block;
            background-color: #4d4d4d;
            padding: 4px 4px 4px 6px;
            border-top-left-radius: 4px;
            border-bottom-left-radius: 4px;
        }

        .github-badge .badge-value {
            display: inline-block;
            padding: 4px 6px 4px 4px;
            border-top-right-radius: 4px;
            border-bottom-right-radius: 4px;
        }

        .github-badge .bg-pink {
            /* background-image: linear-gradient(to right, #a4b7ff 0%, #ff7eb3 100%); */
            background-image: linear-gradient(to right, #747474 0%, #ff7eb3 100%);
        }

        .github-badge .bg-DIY {
            /* background-image: linear-gradient(to right, #00decf 0%, #e46cff 100%); */
            background-image: linear-gradient(to right, #747474 0%, #ff7575 100%)
        }

        .github-badge .bg-DIY1 {
            background-color: #7f7f7f;
        }

        .github-badge .bg-blue {
            /* background-image: linear-gradient(120deg, #02f0ff 0%, #66a6ff 100%); */
            background-image: linear-gradient(120deg, #747474 0%, #66a6ff 100%);
        }

        #footer-animal {
            position: relative;
            user-select: none;
        }

        #footer-animal:before {
            content: '';
            position: absolute;
            bottom: 0;
            width: 100%;
            height: 36px;
            background: url(Style/img/animalBg.jpg) repeat center / auto 100%;
            box-shadow: 0 4px 7px rgba(0, 0, 0, .15);
        }

        .animal {
            position: relative;
            max-width: min(974px, 100vw);
            margin: 0 auto;
            display: block;
        }

        @media (max-width: 768px) {
            .animal {
                bottom: 15px;
            }
        }
    </style>
</div>

<div id="footer-animal">
    <img class="animal" src="Style/img/animals.png" draggable="false" alt="动物">
</div>

<?php include __DIR__ . '/inc/footer.php'; ?>

<div class="withu-mobile-nav-root">

    <!-- 方案5: 极简包裹点阵 -->
    <div class="withu-tab-template-v5-container withu-glass-panel" id="withu-mobile-nav-v5">
        <div class="withu-tab-template-v5-indicator"></div>
                                <a class="withu-base-nav-item js-withu-v5-item"
               href="articles.php">
                <i class="ph-fill ph-notebook"></i>
                <span>点滴</span>
            </a>
                                <a class="withu-base-nav-item js-withu-v5-item"
               href="messages.php">
                <i class="ph-fill ph-chat-teardrop-dots"></i>
                <span>留言</span>
            </a>
                                <a class="withu-base-nav-item js-withu-v5-item"
               href="timeline.php">
                <i class="ph-fill ph-clock-countdown"></i>
                <span>轨迹</span>
            </a>
                                <a class="withu-base-nav-item js-withu-v5-item active"
               href="index.php">
                <i class="ph-fill ph-house"></i>
                <span>首页</span>
            </a>
                                <a class="withu-base-nav-item js-withu-v5-item"
               href="albums.php">
                <i class="ph-fill ph-camera"></i>
                <span>相册</span>
            </a>
                                <a class="withu-base-nav-item js-withu-v5-item"
               href="lovelist.php">
                <i class="ph-fill ph-list-checks"></i>
                <span>清单</span>
            </a>
                                <a class="withu-base-nav-item js-withu-v5-item"
               href="about.php">
                <i class="ph-fill ph-book-open-text"></i>
                <span>关于</span>
            </a>
            </div>

</div>

<script src="assets/js/mobile-nav.js"></script>

<script src="/assets/js/page-index-972eda00.js"></script>

<!-- auth-status.js removed: server-side PHP auth now handles login state -->
</body>

</html>
