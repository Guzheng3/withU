<?php require __DIR__ . '/inc/auth.php'; ?>
<?php require __DIR__ . '/inc/config.php'; ?>
<!DOCTYPE html>
<html>

<head>
    <meta name="x-withu-license-instance" content="858ee1d099b9">

<link rel="icon" href="/favicon.png" />
<meta name="viewport" content="width=device-width,minimum-scale=1.0,maximum-scale=1.0,user-scalable=no">
<meta name="description" content="withU 是一个适合记录恋爱日常与纪念时刻的情侣小站，支持相册、时间轴、点滴文章、留言互动和邀请页面，让每一段关系都能拥有自己的专属回忆空间。">
<meta name="keywords" content="情侣网站,恋爱记录,祝福留言,情侣相册,恋爱清单,爱情纪念,情侣头像框,祝福语句,情侣互动,爱情相册,情侣事件记录,情侣留言,爱情故事,情感交流,用户互动,祝福卡片,音乐分享,甜蜜瞬间,情侣活动,爱情动态,withU">
<meta name="robots" content="index, follow">
<link rel="canonical" href="/lovelist.php">

<!-- Open Graph (Facebook/微信/QQ) -->
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?php echo htmlspecialchars($withuSiteTitle, ENT_QUOTES, 'UTF-8'); ?>">
<meta property="og:title" content="<?php echo htmlspecialchars($withuSiteTitle, ENT_QUOTES, 'UTF-8'); ?>">
<meta property="og:description" content="withU 是一个适合记录恋爱日常与纪念时刻的情侣小站，支持相册、时间轴、点滴文章、留言互动和邀请页面，让每一段关系都能拥有自己的专属回忆空间。">
<meta property="og:url" content="/lovelist.php">
<meta property="og:image" content="withU">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo htmlspecialchars($withuSiteTitle, ENT_QUOTES, 'UTF-8'); ?>">
<meta name="twitter:description" content="withU 是一个适合记录恋爱日常与纪念时刻的情侣小站，支持相册、时间轴、点滴文章、留言互动和邀请页面，让每一段关系都能拥有自己的专属回忆空间。">
<meta name="twitter:image" content="withU">

    <!-- Google Fonts CDN 版本 -->
        <link rel="stylesheet" href="/Style/vendor/google-fonts/google-fonts.css">
    <!-- 非 Google Fonts 字体（HarmonyOS Sans、汉仪粗仿宋）本地补充 -->
    <link rel="stylesheet" href="/Style/vendor/google-fonts/fonts-non-google.css">

<!-- Font Awesome 本地化 -->
<link rel="stylesheet" href="/Style/vendor/fontawesome/css/all.min.css">
<link rel="stylesheet" href="/Style/css/leaving.css">
<link rel="stylesheet" href="/Style/css/leav.css">
<link rel="stylesheet" href="/Style/css/message.css">
<link rel="stylesheet" href="/Style/css/index.css">
<link rel="stylesheet" href="/Style/css/little.css">
<link rel="stylesheet" href="/Style/css/loveImg.css">
<link rel="stylesheet" href="/Style/css/list.css">
<link rel="stylesheet" href="/Style/Font/font_list/iconfont.css">
<link rel="stylesheet" href="/Style/toastify/toastify.min.css">
<link rel="stylesheet" href="/Style/css/loadinglike.css">
<!-- AOS 本地化 -->
<link rel="stylesheet" href="/Style/vendor/aos/aos.css">

<link rel="stylesheet" href="/Style/css/plyr.css">
<link rel="stylesheet" href="/Style/css/kicode.css">
<link rel="stylesheet" href="/Style/css/phosphor-regular.css">
<link rel="stylesheet" href="/Style/css/phosphor-icons.css">
<link rel="stylesheet" href="/Style/css/phosphor-fill.css">
<link rel="stylesheet" href="/Style/css/phosphor-duotone.css">
<!-- QWeather Icons 本地化 -->
<link rel="stylesheet" href="/Style/vendor/qweather-icons/qweather-icons.css">
<link href="/Style/css/nprogress.css" rel="stylesheet" type="text/css">
<!-- Remix Icon 本地化 -->
<link rel="stylesheet" href="/Style/vendor/remixicon/remixicon.css">
<link rel="stylesheet" href="/Style/css/tooltip.css">
<link rel="stylesheet" href="/Style/css/interaction.css">
<link rel="stylesheet" href="/Style/css/withu-home-style.css">
<link rel="stylesheet" href="/Style/css/withu-detail.css">
<link rel="stylesheet" href="/Style/css/mobile-nav.css">
<link rel="stylesheet" href="/Style/css/header.css">
<!-- 自定义右键菜单 -->
<link rel="stylesheet" href="/Style/css/context-menu.css">
<!-- 足迹地图样式 -->
    <link rel="stylesheet" href="/Style/css/map.css">


<script src="/Style/jquery/jquery.min.js"></script>
<script src="/Style/Font/font_leav/iconfont.js"></script>
<script src="/Style/js/jquery.pjax.js" type="text/javascript"></script>
<script src="/Style/js/plyr.js"></script>
<!-- AOS.js 本地化 -->
<script src="/Style/vendor/aos/aos.js"></script>

<script src="/Style/js/highlight.min.js"></script>
<script src="/Style/js/lazyload.min.js"></script>
<script src="/Style/js/masonry.pkgd.min.js"></script>
<script src="/Style/js/imagesloaded.pkgd.min.js"></script>
<script src="/Style/js/loading.js"></script>
<script src="/Style/js/withu-owoui.js"></script>
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
<link rel="stylesheet" href="/Style/dplayer/DPlayer.min.css">
<link rel="stylesheet" href="/Style/css/video-modal.css">
<script src="/Style/dplayer/DPlayer.min.js"></script>
<script src="/Style/js/video-modal.js"></script>
    <script src="/ext/static.geetest.com/v4/gt4.js"></script>
    <script src="/Style/js/geetest-helper.js"></script>
    <script>if (typeof GeetestHelper !== 'undefined') GeetestHelper.setCaptchaId("8342edf0a8b10d336e5d0d2d6ede60d4");</script>
<script src="/Style/js/nprogress.js"></script>
<!-- Canvas Confetti 本地化 -->
<script src="/Style/vendor/confetti/confetti.browser.min.js"></script>
<!-- QRCode JS -->
<script src="/Style/vendor/qrcode/qrcode.min.js"></script>
<!-- QR Code Styling (美化二维码) -->
<script src="/Style/vendor/qr-code-styling/qr-code-styling.min.js"></script>

<!-- withU 核心框架 -->
<script>
    window.WITHU_CONFIG = <?php echo $withuConfigJson; ?>;

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
        <script src="/assets/js/withu-shared-7eca2584.js"></script>
<script src="/assets/js/app.js"></script>
<script src="/assets/js/withu-location.js?v=20260906e"></script>
<script src="/assets/js/head-avatar-location.js?v=20260906"></script>
<script src="/assets/js/components.js"></script>

<!-- 礼花效果已迁移到 components.js 的 ConfettiEffect 模块 -->

<script src="/assets/js/pjax.js"></script><script>if(window.WithUPjax&&typeof window.WithUPjax.init==="function")window.WithUPjax.init();</script>
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
                    <li class="item active">
                <img class="lazy CarouselImage" data-src="/Lovefolder/20260408044229_69d56c35d59a9841528398.webp" draggable="false">
            </li>
                    <li class="item">
                <img class="lazy CarouselImage" data-src="/Lovefolder/20260408044228_69d56c34421f3439264035.webp" draggable="false">
            </li>
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
                    <li class="point active" data-index="0"></li>
                    <li class="point " data-index="1"></li>
                    <li class="point " data-index="2"></li>
                    <li class="point " data-index="3"></li>
                    <li class="point " data-index="4"></li>
                    <li class="point " data-index="5"></li>
                    <li class="point " data-index="6"></li>
                    <li class="point " data-index="7"></li>
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
           class="withu-nav-island-item  nav-home"
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
           class="withu-nav-island-item active "
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

    <title>清单 — <?php echo htmlspecialchars($withuSiteTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="withU 是一个适合记录恋爱日常与纪念时刻的情侣小站，支持相册、时间轴、点滴文章、留言互动和邀请页面，让每一段关系都能拥有自己的专属回忆空间。">
    <meta name="keywords" content="情侣网站,恋爱记录,祝福留言,情侣相册,恋爱清单,爱情纪念,情侣头像框,祝福语句,情侣互动,爱情相册,情侣事件记录,情侣留言,爱情故事,情感交流,用户互动,祝福卡片,音乐分享,甜蜜瞬间,情侣活动,爱情动态,withU">
    <meta name="author" content="Ki">
    <meta name="love-theme" content="withU-情侣小站">
    <meta name="copyright" content="2024 withU Web All Rights Reserved">
</head>

<body class="bg-pdot-vignette">
    <div id="pjax-container">
        <link rel="stylesheet" href="/Style/LoveListStyle/styleCarousel.css" />
        <link rel="stylesheet" href="/assets/css/page-lovelist-93cf0532.css">

        <div class="Width_limit_10rem">
            <div class="central mar_t0">

                
                                    <div class="Search_warp">
                        <div class="Tab_Warp">
                            <div class="LgLoveList-tab-container">
                                <div class="LgLoveList-tab-slider"></div>
                                <div class="LgLoveList-tab" data-id="1">
                                    <i class="ph-fill ph-check-circle"></i>
                                    <span>已完成</span>
                                    <span class="tab-badge">23</span>
                                </div>

                                <div class="LgLoveList-tab LgLoveList-tab-active" data-id="2">
                                    <i class="ph-fill ph-heart"></i>
                                    <span>全部</span>
                                    <span class="tab-badge">36</span>
                                </div>

                                <div class="LgLoveList-tab" data-id="3">
                                    <i class="ph-fill ph-clock"></i>
                                    <span>未完成</span>
                                    <span class="tab-badge">13</span>
                                </div>
                            </div>
                        </div>

                        <div class="search-box">
                            <!-- 内嵌范围胶囊 -->
                            <div class="scope-tag" id="scopeTag" data-scope="all">
                                <i class="fa-solid fa-heart"></i>
                                <span class="scope-tag-text">全部</span>
                            </div>

                            <input id="search" name="search" type="text" placeholder="搜索甜蜜回忆...">

                            <button id="search_btn" class="shadow-blur">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <span>查询</span>
                            </button>
                        </div>
                    </div>

                    <div id="list_container">
                        <div id="withuListSkeleton" class="withu-skeleton-screen-wrap" aria-hidden="true">
                                                            <div class="withu-skeleton-screen-card withu-skeleton-screen-flex withu-skeleton-screen-items-center withu-skeleton-screen-justify-between withu-skeleton-screen-gap-4">
                                    <div class="withu-skeleton-screen-flex withu-skeleton-screen-items-center withu-skeleton-screen-gap-3 withu-skeleton-screen-left-wrap">
                                        <div class="withu-skeleton-screen-anim withu-skeleton-screen-circle withu-skeleton-screen-dot"></div>
                                        <div class="withu-skeleton-screen-anim withu-skeleton-screen-text withu-skeleton-screen-line"></div>
                                    </div>
                                    <div class="withu-skeleton-screen-anim withu-skeleton-screen-text withu-skeleton-screen-arrow"></div>
                                </div>
                                                            <div class="withu-skeleton-screen-card withu-skeleton-screen-flex withu-skeleton-screen-items-center withu-skeleton-screen-justify-between withu-skeleton-screen-gap-4">
                                    <div class="withu-skeleton-screen-flex withu-skeleton-screen-items-center withu-skeleton-screen-gap-3 withu-skeleton-screen-left-wrap">
                                        <div class="withu-skeleton-screen-anim withu-skeleton-screen-circle withu-skeleton-screen-dot"></div>
                                        <div class="withu-skeleton-screen-anim withu-skeleton-screen-text withu-skeleton-screen-line"></div>
                                    </div>
                                    <div class="withu-skeleton-screen-anim withu-skeleton-screen-text withu-skeleton-screen-arrow"></div>
                                </div>
                                                            <div class="withu-skeleton-screen-card withu-skeleton-screen-flex withu-skeleton-screen-items-center withu-skeleton-screen-justify-between withu-skeleton-screen-gap-4">
                                    <div class="withu-skeleton-screen-flex withu-skeleton-screen-items-center withu-skeleton-screen-gap-3 withu-skeleton-screen-left-wrap">
                                        <div class="withu-skeleton-screen-anim withu-skeleton-screen-circle withu-skeleton-screen-dot"></div>
                                        <div class="withu-skeleton-screen-anim withu-skeleton-screen-text withu-skeleton-screen-line"></div>
                                    </div>
                                    <div class="withu-skeleton-screen-anim withu-skeleton-screen-text withu-skeleton-screen-arrow"></div>
                                </div>
                                                            <div class="withu-skeleton-screen-card withu-skeleton-screen-flex withu-skeleton-screen-items-center withu-skeleton-screen-justify-between withu-skeleton-screen-gap-4">
                                    <div class="withu-skeleton-screen-flex withu-skeleton-screen-items-center withu-skeleton-screen-gap-3 withu-skeleton-screen-left-wrap">
                                        <div class="withu-skeleton-screen-anim withu-skeleton-screen-circle withu-skeleton-screen-dot"></div>
                                        <div class="withu-skeleton-screen-anim withu-skeleton-screen-text withu-skeleton-screen-line"></div>
                                    </div>
                                    <div class="withu-skeleton-screen-anim withu-skeleton-screen-text withu-skeleton-screen-arrow"></div>
                                </div>
                                                            <div class="withu-skeleton-screen-card withu-skeleton-screen-flex withu-skeleton-screen-items-center withu-skeleton-screen-justify-between withu-skeleton-screen-gap-4">
                                    <div class="withu-skeleton-screen-flex withu-skeleton-screen-items-center withu-skeleton-screen-gap-3 withu-skeleton-screen-left-wrap">
                                        <div class="withu-skeleton-screen-anim withu-skeleton-screen-circle withu-skeleton-screen-dot"></div>
                                        <div class="withu-skeleton-screen-anim withu-skeleton-screen-text withu-skeleton-screen-line"></div>
                                    </div>
                                    <div class="withu-skeleton-screen-anim withu-skeleton-screen-text withu-skeleton-screen-arrow"></div>
                                </div>
                                                            <div class="withu-skeleton-screen-card withu-skeleton-screen-flex withu-skeleton-screen-items-center withu-skeleton-screen-justify-between withu-skeleton-screen-gap-4">
                                    <div class="withu-skeleton-screen-flex withu-skeleton-screen-items-center withu-skeleton-screen-gap-3 withu-skeleton-screen-left-wrap">
                                        <div class="withu-skeleton-screen-anim withu-skeleton-screen-circle withu-skeleton-screen-dot"></div>
                                        <div class="withu-skeleton-screen-anim withu-skeleton-screen-text withu-skeleton-screen-line"></div>
                                    </div>
                                    <div class="withu-skeleton-screen-anim withu-skeleton-screen-text withu-skeleton-screen-arrow"></div>
                                </div>
                                                            <div class="withu-skeleton-screen-card withu-skeleton-screen-flex withu-skeleton-screen-items-center withu-skeleton-screen-justify-between withu-skeleton-screen-gap-4">
                                    <div class="withu-skeleton-screen-flex withu-skeleton-screen-items-center withu-skeleton-screen-gap-3 withu-skeleton-screen-left-wrap">
                                        <div class="withu-skeleton-screen-anim withu-skeleton-screen-circle withu-skeleton-screen-dot"></div>
                                        <div class="withu-skeleton-screen-anim withu-skeleton-screen-text withu-skeleton-screen-line"></div>
                                    </div>
                                    <div class="withu-skeleton-screen-anim withu-skeleton-screen-text withu-skeleton-screen-arrow"></div>
                                </div>
                                                            <div class="withu-skeleton-screen-card withu-skeleton-screen-flex withu-skeleton-screen-items-center withu-skeleton-screen-justify-between withu-skeleton-screen-gap-4">
                                    <div class="withu-skeleton-screen-flex withu-skeleton-screen-items-center withu-skeleton-screen-gap-3 withu-skeleton-screen-left-wrap">
                                        <div class="withu-skeleton-screen-anim withu-skeleton-screen-circle withu-skeleton-screen-dot"></div>
                                        <div class="withu-skeleton-screen-anim withu-skeleton-screen-text withu-skeleton-screen-line"></div>
                                    </div>
                                    <div class="withu-skeleton-screen-anim withu-skeleton-screen-text withu-skeleton-screen-arrow"></div>
                                </div>
                                                            <div class="withu-skeleton-screen-card withu-skeleton-screen-flex withu-skeleton-screen-items-center withu-skeleton-screen-justify-between withu-skeleton-screen-gap-4">
                                    <div class="withu-skeleton-screen-flex withu-skeleton-screen-items-center withu-skeleton-screen-gap-3 withu-skeleton-screen-left-wrap">
                                        <div class="withu-skeleton-screen-anim withu-skeleton-screen-circle withu-skeleton-screen-dot"></div>
                                        <div class="withu-skeleton-screen-anim withu-skeleton-screen-text withu-skeleton-screen-line"></div>
                                    </div>
                                    <div class="withu-skeleton-screen-anim withu-skeleton-screen-text withu-skeleton-screen-arrow"></div>
                                </div>
                                                            <div class="withu-skeleton-screen-card withu-skeleton-screen-flex withu-skeleton-screen-items-center withu-skeleton-screen-justify-between withu-skeleton-screen-gap-4">
                                    <div class="withu-skeleton-screen-flex withu-skeleton-screen-items-center withu-skeleton-screen-gap-3 withu-skeleton-screen-left-wrap">
                                        <div class="withu-skeleton-screen-anim withu-skeleton-screen-circle withu-skeleton-screen-dot"></div>
                                        <div class="withu-skeleton-screen-anim withu-skeleton-screen-text withu-skeleton-screen-line"></div>
                                    </div>
                                    <div class="withu-skeleton-screen-anim withu-skeleton-screen-text withu-skeleton-screen-arrow"></div>
                                </div>
                                                    </div>

                        <div class="query_data"></div>

                        <div id="list_data">
                                                            <div class="love-card" id="event-36" data-aos="fade-up" data-aos-delay="50">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot com"></span>
                                            <span class="event-name">一起去吃淘蛙</span>
                                            <span class="event-tags">
                                                <span class="etag"><i data-lucide="image" title="有照片"></i></span>                                                <span class="etag"><i data-lucide="map-pin" title="有定位"></i></span>                                                <span class="etag"><i data-lucide="notebook-pen" title="有备注"></i></span>                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="img-counter">1 / 4</div>
                                                    <div class="f-carousel ConventionPhoto" view-image>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="/Lovefolder/20260411053515_69d96d13af174939872800.webp" data-original="/Lovefolder/20260411053515_69d96d13af174939872800.webp" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20260411053516_69d96d1401905291123552.webp" data-original="/Lovefolder/20260411053516_69d96d1401905291123552.webp" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20260411053516_69d96d145e059867107434.webp" data-original="/Lovefolder/20260411053516_69d96d145e059867107434.webp" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20260411053516_69d96d14a01d9816507175.webp" data-original="/Lovefolder/20260411053516_69d96d14a01d9816507175.webp" />
                                                            </div>
                                                                                                            </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">一起去吃淘蛙</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">2026-04-11</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value lovelist-location-link has-coords" onclick="event.stopPropagation(); if(window.WithUMap) WithUMap.open({ mode:'events', coords:[113.820617,22.808944], zoom:15 });" data-tooltip="淘蛙(长安万达店)"><i class="ph-fill ph-map-pin"></i><span>淘蛙(长安万达店)</span></span>
                                                                                                    </div>
                                                                                                    <div class="info-item remark-item">
                                                        <span class="info-label">清单备注 / NOTE</span>
                                                        <span class="info-value">好像一直都在吃的路上 个个不重样</span>
                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-35" data-aos="fade-up" data-aos-delay="100">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot com"></span>
                                            <span class="event-name">测试修改问题 2.0.7</span>
                                            <span class="event-tags">
                                                <span class="etag"><i data-lucide="image" title="有照片"></i></span>                                                <span class="etag"><i data-lucide="map-pin" title="有定位"></i></span>                                                                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="img-counter">1 / 1</div>
                                                    <div class="f-carousel ConventionPhoto" view-image>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20260409211730_69d7a6eaecf46322029252.webp" data-original="/Lovefolder/20260409211730_69d7a6eaecf46322029252.webp" />
                                                            </div>
                                                                                                            </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">测试修改问题 2.0.7</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">2026-02-17</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value lovelist-location-link has-coords" onclick="event.stopPropagation(); if(window.WithUMap) WithUMap.open({ mode:'events', coords:[110.993509,21.947605], zoom:15 });" data-tooltip="高州市"><i class="ph-fill ph-map-pin"></i><span>高州市</span></span>
                                                                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-34" data-aos="fade-up" data-aos-delay="150">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot air"></span>
                                            <span class="event-name">测试最新版本新增问题</span>
                                            <span class="event-tags">
                                                                                                                                                <span class="etag"><i data-lucide="notebook-pen" title="有备注"></i></span>                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark wm-pending">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="no-img-placeholder">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                            fill="currentColor">
                                                            <path
                                                                d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z" />
                                                        </svg>
                                                        <span>暂无影像</span>
                                                    </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">测试最新版本新增问题</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">---</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value">---</span>
                                                                                                    </div>
                                                                                                    <div class="info-item remark-item">
                                                        <span class="info-label">清单备注 / NOTE</span>
                                                        <span class="info-value">测试一下</span>
                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-33" data-aos="fade-up" data-aos-delay="200">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot com"></span>
                                            <span class="event-name">一起去吃海底捞</span>
                                            <span class="event-tags">
                                                <span class="etag"><i data-lucide="image" title="有照片"></i></span>                                                <span class="etag"><i data-lucide="map-pin" title="有定位"></i></span>                                                <span class="etag"><i data-lucide="notebook-pen" title="有备注"></i></span>                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="img-counter">1 / 5</div>
                                                    <div class="f-carousel ConventionPhoto" view-image>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="/Lovefolder/20260411053340_69d96cb4ec19c441742336.webp" data-original="/Lovefolder/20260411053340_69d96cb4ec19c441742336.webp" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20260411053340_69d96cb48a9b7708963429.webp" data-original="/Lovefolder/20260411053340_69d96cb48a9b7708963429.webp" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="/Lovefolder/20260411053340_69d96cb427f3a007965255.webp" data-original="/Lovefolder/20260411053340_69d96cb427f3a007965255.webp" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="/Lovefolder/20260411053339_69d96cb3d69e4764961994.webp" data-original="/Lovefolder/20260411053339_69d96cb3d69e4764961994.webp" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20260411053339_69d96cb368213953103954.webp" data-original="/Lovefolder/20260411053339_69d96cb368213953103954.webp" />
                                                            </div>
                                                                                                            </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">一起去吃海底捞</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">2026-04-11</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value lovelist-location-link has-coords" onclick="event.stopPropagation(); if(window.WithUMap) WithUMap.open({ mode:'events', coords:[113.799025,22.800425], zoom:15 });" data-tooltip="海底捞火锅(长安万科店)"><i class="ph-fill ph-map-pin"></i><span>海底捞火锅(长安万科店)</span></span>
                                                                                                    </div>
                                                                                                    <div class="info-item remark-item">
                                                        <span class="info-label">清单备注 / NOTE</span>
                                                        <span class="info-value">太好了 你妹也在这里了</span>
                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-32" data-aos="fade-up" data-aos-delay="250">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot com"></span>
                                            <span class="event-name">测试上传自动读取EXIF</span>
                                            <span class="event-tags">
                                                <span class="etag"><i data-lucide="image" title="有照片"></i></span>                                                <span class="etag"><i data-lucide="map-pin" title="有定位"></i></span>                                                                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="img-counter">1 / 1</div>
                                                    <div class="f-carousel ConventionPhoto" view-image>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20260409200659_69d7966311ed3484002730.webp" data-original="/Lovefolder/20260409200659_69d7966311ed3484002730.webp" />
                                                            </div>
                                                                                                            </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">测试上传自动读取EXIF</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">2025-12-07</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value lovelist-location-link has-coords" onclick="event.stopPropagation(); if(window.WithUMap) WithUMap.open({ mode:'events', coords:[114.695386,23.006932], zoom:15 });" data-tooltip="惠东县"><i class="ph-fill ph-map-pin"></i><span>惠东县</span></span>
                                                                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-31" data-aos="fade-up" data-aos-delay="300">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot com"></span>
                                            <span class="event-name">测试照片自动读EXIF</span>
                                            <span class="event-tags">
                                                <span class="etag"><i data-lucide="image" title="有照片"></i></span>                                                <span class="etag"><i data-lucide="map-pin" title="有定位"></i></span>                                                                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="img-counter">1 / 3</div>
                                                    <div class="f-carousel ConventionPhoto" view-image>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="/Lovefolder/20260411004651_69d9297b8e0ae609910748.webp" data-original="/Lovefolder/20260411004651_69d9297b8e0ae609910748.webp" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20260411004647_69d929774f115996156546.webp" data-original="/Lovefolder/20260411004647_69d929774f115996156546.webp" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20260411004643_69d92973937db905949089.webp" data-original="/Lovefolder/20260411004643_69d92973937db905949089.webp" />
                                                            </div>
                                                                                                            </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">测试照片自动读EXIF</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">2025-12-14</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value lovelist-location-link has-coords" onclick="event.stopPropagation(); if(window.WithUMap) WithUMap.open({ mode:'events', coords:[114.700042,23.004825], zoom:15 });" data-tooltip="惠东县"><i class="ph-fill ph-map-pin"></i><span>惠东县</span></span>
                                                                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-30" data-aos="fade-up" data-aos-delay="300">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot com"></span>
                                            <span class="event-name">带上咕噜一起去海边</span>
                                            <span class="event-tags">
                                                <span class="etag"><i data-lucide="image" title="有照片"></i></span>                                                <span class="etag"><i data-lucide="map-pin" title="有定位"></i></span>                                                <span class="etag"><i data-lucide="notebook-pen" title="有备注"></i></span>                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="img-counter">1 / 8</div>
                                                    <div class="f-carousel ConventionPhoto" view-image>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="https://loveli-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20260409041123_69d6b66b54d23885513672.webp" data-original="https://loveli-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20260409041123_69d6b66b54d23885513672.webp" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="https://loveli-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20260409041124_69d6b66c6e4fa859548001.webp" data-original="https://loveli-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20260409041124_69d6b66c6e4fa859548001.webp" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="https://loveli-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20260409041123_69d6b66be06d0791462106.webp" data-original="https://loveli-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20260409041123_69d6b66be06d0791462106.webp" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="https://loveli-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20260409041122_69d6b66ac985e009630399.webp" data-original="https://loveli-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20260409041122_69d6b66ac985e009630399.webp" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="https://loveli-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20260409041122_69d6b66a69895063869438.webp" data-original="https://loveli-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20260409041122_69d6b66a69895063869438.webp" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="https://loveli-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20260409040937_69d6b6018a85e995422179.webp" data-original="https://loveli-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20260409040937_69d6b6018a85e995422179.webp" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="https://loveli-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20260409040932_69d6b5fc34687391366004.webp" data-original="https://loveli-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20260409040932_69d6b5fc34687391366004.webp" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="https://loveli-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20260409000955_69d67dd3b7a69592012172.webp" data-original="https://loveli-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20260409000955_69d67dd3b7a69592012172.webp" />
                                                            </div>
                                                                                                            </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">带上咕噜一起去海边</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">2025-06-28</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value lovelist-location-link has-coords" onclick="event.stopPropagation(); if(window.WithUMap) WithUMap.open({ mode:'events', coords:[113.589436,22.284681], zoom:15 });" data-tooltip="珠海日月贝"><i class="ph-fill ph-map-pin"></i><span>珠海日月贝</span></span>
                                                                                                    </div>
                                                                                                    <div class="info-item remark-item">
                                                        <span class="info-label">清单备注 / NOTE</span>
                                                        <span class="info-value">这个紫外线 不愧是珠海 很过瘾</span>
                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-29" data-aos="fade-up" data-aos-delay="300">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot com"></span>
                                            <span class="event-name">一起去远方旅游</span>
                                            <span class="event-tags">
                                                <span class="etag"><i data-lucide="image" title="有照片"></i></span>                                                <span class="etag"><i data-lucide="map-pin" title="有定位"></i></span>                                                                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="img-counter">1 / 5</div>
                                                    <div class="f-carousel ConventionPhoto" view-image>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20241225224015_676c194fa9c7d_thumb.webp" data-original="/Lovefolder/20241225224015_676c194fa9c7d.jpeg" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20241225224015_676c194fbd6a9_thumb.webp" data-original="/Lovefolder/20241225224015_676c194fbd6a9.jpeg" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20241225224015_676c194fc11f9_thumb.webp" data-original="/Lovefolder/20241225224015_676c194fc11f9.jpeg" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20241225224015_676c194fc4e95_thumb.webp" data-original="/Lovefolder/20241225224015_676c194fc4e95.jpeg" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20241225224015_676c194fc8970_thumb.webp" data-original="/Lovefolder/20241225224015_676c194fc8970.jpeg" />
                                                            </div>
                                                                                                            </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">一起去远方旅游</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">2024-10-01</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value lovelist-location-link has-coords" onclick="event.stopPropagation(); if(window.WithUMap) WithUMap.open({ mode:'events', coords:[113.890634,22.915318], zoom:15 });" data-tooltip="松山湖风景区"><i class="ph-fill ph-map-pin"></i><span>松山湖风景区</span></span>
                                                                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-28" data-aos="fade-up" data-aos-delay="300">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot com"></span>
                                            <span class="event-name">withU 冬至限定 多图约定测试</span>
                                            <span class="event-tags">
                                                <span class="etag"><i data-lucide="image" title="有照片"></i></span>                                                                                                                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="img-counter">1 / 8</div>
                                                    <div class="f-carousel ConventionPhoto" view-image>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20241223171943_67692b2fd5982_thumb.webp" data-original="/Lovefolder/20241223171943_67692b2fd5982.png" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20241223171943_67692b2faaa02_thumb.webp" data-original="/Lovefolder/20241223171943_67692b2faaa02.png" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20241223171943_67692b2f18406_thumb.webp" data-original="/Lovefolder/20241223171943_67692b2f18406.png" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20241223171943_67692b2f03c46_thumb.webp" data-original="/Lovefolder/20241223171943_67692b2f03c46.png" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20241223171942_67692b2edb1fc_thumb.webp" data-original="/Lovefolder/20241223171942_67692b2edb1fc.png" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20241223171942_67692b2edb1fc_thumb.webp" data-original="/Lovefolder/20241223171942_67692b2edb1fc.png" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20241223171942_67692b2ec69e3_thumb.webp" data-original="/Lovefolder/20241223171942_67692b2ec69e3.png" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20241223171805_67692acdb71b5_thumb.webp" data-original="/Lovefolder/20241223171805_67692acdb71b5.png" />
                                                            </div>
                                                                                                            </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">withU 冬至限定 多图约定测试</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">---</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value">---</span>
                                                                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-27" data-aos="fade-up" data-aos-delay="300">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot com"></span>
                                            <span class="event-name">一起骑小电驴去上班</span>
                                            <span class="event-tags">
                                                <span class="etag"><i data-lucide="image" title="有照片"></i></span>                                                                                                                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="img-counter">1 / 2</div>
                                                    <div class="f-carousel ConventionPhoto" view-image>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20240729105425_1_thumb.webp" data-original="/uploads/20240729105425_1.jpeg" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20241223172154_67692bb20f4aa_thumb.webp" data-original="/Lovefolder/20241223172154_67692bb20f4aa.jpeg" />
                                                            </div>
                                                                                                            </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">一起骑小电驴去上班</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">---</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value">---</span>
                                                                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-26" data-aos="fade-up" data-aos-delay="300">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot com"></span>
                                            <span class="event-name">一起去理发</span>
                                            <span class="event-tags">
                                                <span class="etag"><i data-lucide="image" title="有照片"></i></span>                                                                                                                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="img-counter">1 / 1</div>
                                                    <div class="f-carousel ConventionPhoto" view-image>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20240630231525_1_thumb.webp" data-original="/uploads/20240630231525_1.jpeg" />
                                                            </div>
                                                                                                            </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">一起去理发</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">---</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value">---</span>
                                                                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-25" data-aos="fade-up" data-aos-delay="300">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot com"></span>
                                            <span class="event-name">测试恋爱约定多图显示 📷️</span>
                                            <span class="event-tags">
                                                <span class="etag"><i data-lucide="image" title="有照片"></i></span>                                                                                                                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="img-counter">1 / 9</div>
                                                    <div class="f-carousel ConventionPhoto" view-image>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20240601163852_9_thumb.webp" data-original="/uploads/20240601163852_9.jpeg" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20240601163852_8_thumb.webp" data-original="/uploads/20240601163852_8.jpeg" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20240601163852_7_thumb.webp" data-original="/uploads/20240601163852_7.jpeg" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20240601163851_6_thumb.webp" data-original="/uploads/20240601163851_6.jpeg" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20240601163851_5_thumb.webp" data-original="/uploads/20240601163851_5.jpeg" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20240601163850_4_thumb.webp" data-original="/uploads/20240601163850_4.jpeg" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20240601163849_3_thumb.webp" data-original="/uploads/20240601163849_3.jpeg" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20240601163848_2_thumb.webp" data-original="/uploads/20240601163848_2.jpeg" />
                                                            </div>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20240601163848_1_thumb.webp" data-original="/uploads/20240601163848_1.jpeg" />
                                                            </div>
                                                                                                            </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">测试恋爱约定多图显示 📷️</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">---</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value">---</span>
                                                                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-24" data-aos="fade-up" data-aos-delay="300">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot com"></span>
                                            <span class="event-name">亲手烤个鸡翅给你吃</span>
                                            <span class="event-tags">
                                                <span class="etag"><i data-lucide="image" title="有照片"></i></span>                                                                                                                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="img-counter">1 / 1</div>
                                                    <div class="f-carousel ConventionPhoto" view-image>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20240516165358_1_thumb.webp" data-original="/uploads/20240516165358_1.jpeg" />
                                                            </div>
                                                                                                            </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">亲手烤个鸡翅给你吃</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">---</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value">---</span>
                                                                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-23" data-aos="fade-up" data-aos-delay="300">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot com"></span>
                                            <span class="event-name">一起听音乐，听同一首歌♪</span>
                                            <span class="event-tags">
                                                <span class="etag"><i data-lucide="image" title="有照片"></i></span>                                                                                                                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="img-counter">1 / 1</div>
                                                    <div class="f-carousel ConventionPhoto" view-image>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20240430113332_1_thumb.webp" data-original="/uploads/20240430113332_1.jpeg" />
                                                            </div>
                                                                                                            </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">一起听音乐，听同一首歌♪</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">---</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value">---</span>
                                                                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-22" data-aos="fade-up" data-aos-delay="300">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot air"></span>
                                            <span class="event-name">一起去旅游🚄</span>
                                            <span class="event-tags">
                                                                                                                                                                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark wm-pending">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="no-img-placeholder">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                            fill="currentColor">
                                                            <path
                                                                d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z" />
                                                        </svg>
                                                        <span>暂无影像</span>
                                                    </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">一起去旅游🚄</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">---</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value">---</span>
                                                                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-21" data-aos="fade-up" data-aos-delay="300">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot air"></span>
                                            <span class="event-name">一起熬夜通宵跨年🧨</span>
                                            <span class="event-tags">
                                                                                                                                                                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark wm-pending">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="no-img-placeholder">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                            fill="currentColor">
                                                            <path
                                                                d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z" />
                                                        </svg>
                                                        <span>暂无影像</span>
                                                    </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">一起熬夜通宵跨年🧨</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">---</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value">---</span>
                                                                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-20" data-aos="fade-up" data-aos-delay="300">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot com"></span>
                                            <span class="event-name">一起去吃一次全家桶🍔</span>
                                            <span class="event-tags">
                                                <span class="etag"><i data-lucide="image" title="有照片"></i></span>                                                                                                                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="img-counter">1 / 1</div>
                                                    <div class="f-carousel ConventionPhoto" view-image>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20240729104806_1_thumb.webp" data-original="/uploads/20240729104806_1.jpeg" />
                                                            </div>
                                                                                                            </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">一起去吃一次全家桶🍔</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">---</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value">---</span>
                                                                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-19" data-aos="fade-up" data-aos-delay="300">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot air"></span>
                                            <span class="event-name">一起去做次陶艺👩‍🎨</span>
                                            <span class="event-tags">
                                                                                                                                                                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark wm-pending">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="no-img-placeholder">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                            fill="currentColor">
                                                            <path
                                                                d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z" />
                                                        </svg>
                                                        <span>暂无影像</span>
                                                    </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">一起去做次陶艺👩‍🎨</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">---</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value">---</span>
                                                                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-18" data-aos="fade-up" data-aos-delay="300">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot air"></span>
                                            <span class="event-name">一起为对方抹指甲油💅🏻</span>
                                            <span class="event-tags">
                                                                                                                                                                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark wm-pending">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="no-img-placeholder">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                            fill="currentColor">
                                                            <path
                                                                d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z" />
                                                        </svg>
                                                        <span>暂无影像</span>
                                                    </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">一起为对方抹指甲油💅🏻</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">---</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value">---</span>
                                                                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-17" data-aos="fade-up" data-aos-delay="300">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot air"></span>
                                            <span class="event-name">一起给对方化妆🤡</span>
                                            <span class="event-tags">
                                                                                                                                                                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark wm-pending">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="no-img-placeholder">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                            fill="currentColor">
                                                            <path
                                                                d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z" />
                                                        </svg>
                                                        <span>暂无影像</span>
                                                    </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">一起给对方化妆🤡</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">---</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value">---</span>
                                                                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-16" data-aos="fade-up" data-aos-delay="300">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot air"></span>
                                            <span class="event-name">一起研究口红色号💄</span>
                                            <span class="event-tags">
                                                                                                                                                                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark wm-pending">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="no-img-placeholder">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                            fill="currentColor">
                                                            <path
                                                                d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z" />
                                                        </svg>
                                                        <span>暂无影像</span>
                                                    </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">一起研究口红色号💄</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">---</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value">---</span>
                                                                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-15" data-aos="fade-up" data-aos-delay="300">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot com"></span>
                                            <span class="event-name">一起养一只宠物🐕️</span>
                                            <span class="event-tags">
                                                <span class="etag"><i data-lucide="image" title="有照片"></i></span>                                                                                                                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="img-counter">1 / 1</div>
                                                    <div class="f-carousel ConventionPhoto" view-image>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="/Lovefolder/20241223180401_676935918919c.jpeg" data-original="/Lovefolder/20241223180401_676935918919c.jpeg" />
                                                            </div>
                                                                                                            </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">一起养一只宠物🐕️</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">---</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value">---</span>
                                                                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-14" data-aos="fade-up" data-aos-delay="300">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot air"></span>
                                            <span class="event-name">一起去蹦极🥝</span>
                                            <span class="event-tags">
                                                                                                                                                                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark wm-pending">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="no-img-placeholder">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                            fill="currentColor">
                                                            <path
                                                                d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z" />
                                                        </svg>
                                                        <span>暂无影像</span>
                                                    </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">一起去蹦极🥝</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">---</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value">---</span>
                                                                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-13" data-aos="fade-up" data-aos-delay="300">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot air"></span>
                                            <span class="event-name">一起去一次鬼屋🥥</span>
                                            <span class="event-tags">
                                                                                                                                                                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark wm-pending">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="no-img-placeholder">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                            fill="currentColor">
                                                            <path
                                                                d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z" />
                                                        </svg>
                                                        <span>暂无影像</span>
                                                    </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">一起去一次鬼屋🥥</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">---</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value">---</span>
                                                                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-12" data-aos="fade-up" data-aos-delay="300">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot air"></span>
                                            <span class="event-name">一起给对方写信，然后读给对方听🍍</span>
                                            <span class="event-tags">
                                                                                                                                                                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark wm-pending">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="no-img-placeholder">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                            fill="currentColor">
                                                            <path
                                                                d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z" />
                                                        </svg>
                                                        <span>暂无影像</span>
                                                    </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">一起给对方写信，然后读给对方听🍍</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">---</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value">---</span>
                                                                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-11" data-aos="fade-up" data-aos-delay="300">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot com"></span>
                                            <span class="event-name">一起打扫卫生🥭</span>
                                            <span class="event-tags">
                                                                                                                                                                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="no-img-placeholder">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                            fill="currentColor">
                                                            <path
                                                                d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z" />
                                                        </svg>
                                                        <span>暂无影像</span>
                                                    </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">一起打扫卫生🥭</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">---</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value">---</span>
                                                                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-10" data-aos="fade-up" data-aos-delay="300">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot com"></span>
                                            <span class="event-name">一起过生日🎂</span>
                                            <span class="event-tags">
                                                <span class="etag"><i data-lucide="image" title="有照片"></i></span>                                                                                                                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="img-counter">1 / 1</div>
                                                    <div class="f-carousel ConventionPhoto" view-image>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20240430112144_1_thumb.webp" data-original="/uploads/20240430112144_1.jpeg" />
                                                            </div>
                                                                                                            </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">一起过生日🎂</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">---</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value">---</span>
                                                                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-9" data-aos="fade-up" data-aos-delay="300">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot air"></span>
                                            <span class="event-name">一起过次烛光晚餐🍒</span>
                                            <span class="event-tags">
                                                                                                                                                                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark wm-pending">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="no-img-placeholder">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                            fill="currentColor">
                                                            <path
                                                                d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z" />
                                                        </svg>
                                                        <span>暂无影像</span>
                                                    </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">一起过次烛光晚餐🍒</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">---</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value">---</span>
                                                                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-8" data-aos="fade-up" data-aos-delay="300">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot com"></span>
                                            <span class="event-name">一起在厨房做n次饭🍲</span>
                                            <span class="event-tags">
                                                <span class="etag"><i data-lucide="image" title="有照片"></i></span>                                                                                                                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="img-counter">1 / 1</div>
                                                    <div class="f-carousel ConventionPhoto" view-image>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20240430110747_6_thumb.webp" data-original="/uploads/20240430110747_6.jpeg" />
                                                            </div>
                                                                                                            </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">一起在厨房做n次饭🍲</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">---</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value">---</span>
                                                                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-7" data-aos="fade-up" data-aos-delay="300">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot com"></span>
                                            <span class="event-name">一起唱唱歌并且录下来🎹</span>
                                            <span class="event-tags">
                                                <span class="etag"><i data-lucide="image" title="有照片"></i></span>                                                                                                                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="img-counter">1 / 1</div>
                                                    <div class="f-carousel ConventionPhoto" view-image>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/537f33cfc6a0d1b48d608b724dd57d1e_thumb.webp" data-original="https://img.gejiba.com/images/537f33cfc6a0d1b48d608b724dd57d1e.jpg" />
                                                            </div>
                                                                                                            </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">一起唱唱歌并且录下来🎹</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">---</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value">---</span>
                                                                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-6" data-aos="fade-up" data-aos-delay="300">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot air"></span>
                                            <span class="event-name">一起去游泳🏊‍♂️🏊‍♀️</span>
                                            <span class="event-tags">
                                                                                                                                                                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark wm-pending">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="no-img-placeholder">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                            fill="currentColor">
                                                            <path
                                                                d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z" />
                                                        </svg>
                                                        <span>暂无影像</span>
                                                    </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">一起去游泳🏊‍♂️🏊‍♀️</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">---</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value">---</span>
                                                                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-5" data-aos="fade-up" data-aos-delay="300">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot air"></span>
                                            <span class="event-name">一起去一趟迪士尼游乐园🍉</span>
                                            <span class="event-tags">
                                                                                                                                                                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark wm-pending">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="no-img-placeholder">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                            fill="currentColor">
                                                            <path
                                                                d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z" />
                                                        </svg>
                                                        <span>暂无影像</span>
                                                    </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">一起去一趟迪士尼游乐园🍉</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">---</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value">---</span>
                                                                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-4" data-aos="fade-up" data-aos-delay="300">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot com"></span>
                                            <span class="event-name">一起穿情侣装逛街💑🏻</span>
                                            <span class="event-tags">
                                                <span class="etag"><i data-lucide="image" title="有照片"></i></span>                                                                                                                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="img-counter">1 / 1</div>
                                                    <div class="f-carousel ConventionPhoto" view-image>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20240430112324_1_thumb.webp" data-original="/uploads/20240430112324_1.jpeg" />
                                                            </div>
                                                                                                            </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">一起穿情侣装逛街💑🏻</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">---</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value">---</span>
                                                                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-3" data-aos="fade-up" data-aos-delay="300">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot com"></span>
                                            <span class="event-name">一起去电影院看一场电影🎬</span>
                                            <span class="event-tags">
                                                <span class="etag"><i data-lucide="image" title="有照片"></i></span>                                                                                                                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="img-counter">1 / 1</div>
                                                    <div class="f-carousel ConventionPhoto" view-image>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20240430112343_1_thumb.webp" data-original="/uploads/20240430112343_1.jpeg" />
                                                            </div>
                                                                                                            </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">一起去电影院看一场电影🎬</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">---</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value">---</span>
                                                                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-2" data-aos="fade-up" data-aos-delay="300">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot com"></span>
                                            <span class="event-name">一起去看海🌊</span>
                                            <span class="event-tags">
                                                <span class="etag"><i data-lucide="image" title="有照片"></i></span>                                                                                                                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="img-counter">1 / 1</div>
                                                    <div class="f-carousel ConventionPhoto" view-image>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/f3b164c311ebce22a5403b908c2f8b9a_thumb.webp" data-original="https://img.gejiba.com/images/f3b164c311ebce22a5403b908c2f8b9a.jpg" />
                                                            </div>
                                                                                                            </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">一起去看海🌊</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">---</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value">---</span>
                                                                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="love-card" id="event-1" data-aos="fade-up" data-aos-delay="300">
                                    <div class="card-header">
                                        <div class="header-left">
                                            <span class="status-dot com"></span>
                                            <span class="event-name">一起吃火锅🧂</span>
                                            <span class="event-tags">
                                                <span class="etag"><i data-lucide="image" title="有照片"></i></span>                                                                                                                                            </span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 9l6 6 6-6" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="body-content">
                                                                                        <div class="achievement-watermark">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                                                                        <div class="body-gallery">
                                                                                                    <div class="img-counter">1 / 1</div>
                                                    <div class="f-carousel ConventionPhoto" view-image>
                                                                                                                    <div class="f-carousel__slide">
                                                                <img class="lazy" draggable="false" data-src="Lovefolder/20240430110749_8_thumb.webp" data-original="/uploads/20240430110749_8.jpeg" />
                                                            </div>
                                                                                                            </div>
                                                                                            </div>

                                            <div class="body-info">
                                                <div class="body-full-title">一起吃火锅🧂</div>
                                                <div class="info-item">
                                                    <span class="info-label">完成时间 / TIME</span>
                                                    <span class="info-value">---</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">达成地点 / LOCATION</span>
                                                                                                        <span class="info-value">---</span>
                                                                                                    </div>
                                                                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                    </div>
                    </div>
                
            </div>
        </div>
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
        window.WITHU_CONFIG.anonymousAvatar = "/Lovefolder/20250310095643_67ce46dbe2e56.webp";
    </script>

    <!-- 极验验证与留言提交绑定 -->
    <script src="/assets/js/withu-shared-0c59da72.js"></script>


<link rel="stylesheet" href="/Style/Font/font_footer/iconfont.css">
    <link rel="stylesheet" href="/assets/fonts/pacifico.css">

<script src="/Style/vendor/confetti/confetti.browser.min.js"></script>
<script src="/assets/js/page-messages.js"></script>
<script src="/Style/toastify/lucide.min.js"></script>
<script src="/Style/toastify/toastify.js"></script>
<script>if(typeof lucide!=='undefined')lucide.createIcons();</script>
<script src="/Style/js/clipboard.min.js"></script>
<script src="/assets/js/clipboard.js"></script>
<script src="/assets/js/tooltip.js"></script>
<script src="/Style/js/view-image.min.js"></script>
<script src="/Style/LoveListStyle/carousel.umd.js"></script>
<script src="/Style/LoveListStyle/carousel.thumbs.umd.js"></script>
<script src="/Style/LoveListStyle/fancybox.umd.js"></script>
<script src="/assets/js/page-lovelist.js"></script>
<script src="/assets/js/page-index.js"></script>
<script src="/assets/js/page-detail.js"></script>
<script src="/assets/js/page-album-detail.js"></script>
<script src="/assets/js/html2canvas.min.js"></script>
<script src="/assets/js/chat.js"></script>

<script src="/assets/js/visitor-hash.js"></script>
<script src="/assets/js/interaction.js"></script>
<script src="/assets/js/context-menu.js"></script>


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
<script src="/assets/js/map.js"></script>

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

    <script src="/assets/js/withu-shared-6eddfbc2.js"></script>

    <link rel="stylesheet" href="/assets/css/withu-shared-18595d61.css">
</div>

<div id="footer-animal">
    <img class="animal" src="/Style/img/animals.png" draggable="false" alt="动物">
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
                                <a class="withu-base-nav-item js-withu-v5-item"
               href="index.php">
                <i class="ph-fill ph-house"></i>
                <span>首页</span>
            </a>
                                <a class="withu-base-nav-item js-withu-v5-item"
               href="albums.php">
                <i class="ph-fill ph-camera"></i>
                <span>相册</span>
            </a>
                                <a class="withu-base-nav-item js-withu-v5-item active"
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

<script src="/assets/js/mobile-nav.js"></script>


<script src="/assets/js/page-lovelist-8293c7cc.js"></script>

</body>

</html>
