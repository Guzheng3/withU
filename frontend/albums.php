<?php require __DIR__ . '/inc/auth.php'; ?>
<?php require __DIR__ . '/inc/config.php'; ?>
<meta name="x-withu-license-instance" content="858ee1d099b9">

<link rel="icon" href="/favicon.png" />
<meta name="viewport" content="width=device-width,minimum-scale=1.0,maximum-scale=1.0,user-scalable=no">
<meta name="description" content="withU 是一个适合记录恋爱日常与纪念时刻的情侣小站，支持相册、时间轴、点滴文章、留言互动和邀请页面，让每一段关系都能拥有自己的专属回忆空间。">
<meta name="keywords" content="情侣网站,恋爱记录,祝福留言,情侣相册,恋爱清单,爱情纪念,情侣头像框,祝福语句,情侣互动,爱情相册,情侣事件记录,情侣留言,爱情故事,情感交流,用户互动,祝福卡片,音乐分享,甜蜜瞬间,情侣活动,爱情动态,withU">
<meta name="robots" content="index, follow">
<link rel="canonical" href="/albums.php">

<!-- Open Graph (Facebook/微信/QQ) -->
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?php echo htmlspecialchars($withuSiteTitle, ENT_QUOTES, 'UTF-8'); ?>">
<meta property="og:title" content="<?php echo htmlspecialchars($withuSiteTitle, ENT_QUOTES, 'UTF-8'); ?>">
<meta property="og:description" content="withU 是一个适合记录恋爱日常与纪念时刻的情侣小站，支持相册、时间轴、点滴文章、留言互动和邀请页面，让每一段关系都能拥有自己的专属回忆空间。">
<meta property="og:url" content="/albums.php">
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
           class="withu-nav-island-item active "
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

<html lang="zh-CN">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>相册 — <?php echo htmlspecialchars($withuSiteTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="withU 是一个适合记录恋爱日常与纪念时刻的情侣小站，支持相册、时间轴、点滴文章、留言互动和邀请页面，让每一段关系都能拥有自己的专属回忆空间。">
    <meta name="keywords" content="情侣网站,恋爱记录,祝福留言,情侣相册,恋爱清单,爱情纪念,情侣头像框,祝福语句,情侣互动,爱情相册,情侣事件记录,情侣留言,爱情故事,情感交流,用户互动,祝福卡片,音乐分享,甜蜜瞬间,情侣活动,爱情动态,withU">
</head>

<body class="bg-pdot-vignette">
    <div id="pjax-container">

        
        <div class="withu-page-container ">

            <!-- Masonry Grid Container -->
            <div class="withu-masonry-grid">

                                    
                    <!-- Masonry Column -->
                    <div class="withu-masonry-col" data-aos="fade-up" data-aos-delay="0">

                        <!-- 私密相册卡片：未登录显示锁定，已登录显示正常内容 -->
                                                    <div class="withu-card">

                                <!-- 已解锁标识 -->
                                
                                <!-- Header -->
                                <div class="withu-header">
                                                                        <div class="withu-author show-gender">
                                        <div class="withu-author__ring">
                                            <img class="withu-author__avatar"
                                                src="/Lovefolder/20260411043037_69d95ded97293201118237.webp"
                                                alt="Avatar">
                                                                                        <div
                                                class="withu-author__badge male">
                                                <i
                                                    class="ph-bold ph-gender-male"></i>
                                            </div>
                                                                                    </div>
                                        <div class="withu-author__text">
                                            <span class="withu-author__name">Ki.</span>
                                            <span class="withu-author__meta">2026-04-16</span>
                                        </div>
                                    </div>
                                    <!-- 跳转按钮 (单张图片不显示) -->
                                                                            <a href="album-detail.php?code=20240613125618" class="withu-header-action">
                                            <i class="ph-bold ph-arrow-right"></i>
                                        </a>
                                                                    </div>

                                <!-- Content -->
                                <div class="withu-content">
                                    <h3 class="withu-title">帅帅</h3>
                                                                    </div>

                                <!-- Media -->
                                                                    <div class="withu-media grid-9" view-image>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240613130838_1_thumb.webp"
                                                    data-original="/uploads/20240613130838_1.jpeg" src="Lovefolder/20240613130838_1_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">639.1KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240613130534_14_thumb.webp"
                                                    data-original="/uploads/20240613130534_14.jpeg" src="Lovefolder/20240613130534_14_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">63.9KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240613130534_13_thumb.webp"
                                                    data-original="/uploads/20240613130534_13.jpeg" src="Lovefolder/20240613130534_13_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">376.9KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240613130534_12_thumb.webp"
                                                    data-original="/uploads/20240613130534_12.jpeg" src="Lovefolder/20240613130534_12_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">98.2KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240613130533_10_thumb.webp"
                                                    data-original="/uploads/20240613130533_10.png" src="Lovefolder/20240613130533_10_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">349.7KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240613130532_9_thumb.webp"
                                                    data-original="/uploads/20240613130532_9.jpeg" src="Lovefolder/20240613130532_9_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">358.5KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240613130532_8_thumb.webp"
                                                    data-original="/uploads/20240613130532_8.jpeg" src="Lovefolder/20240613130532_8_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">142.2KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240613130532_7_thumb.webp"
                                                    data-original="/uploads/20240613130532_7.jpeg" src="Lovefolder/20240613130532_7_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">81.6KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240613130532_6_thumb.webp"
                                                    data-original="/uploads/20240613130532_6.jpeg" src="Lovefolder/20240613130532_6_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">628.9KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                                    <a href="album-detail.php?code=20240613125618" class="withu-overlay">
                                                        <span>+5</span>
                                                    </a>
                                                                                            </div>
                                                                            </div>
                                
                                <!-- Footer -->
                                <div class="withu-footer">
                                    <div class="withu-location-tag"
                                                                                    data-lng="114.71708800"
                                            data-lat="23.00520100"
                                            onclick="WithUMap.open({ mode: 'albums', coords: [114.71708800, 23.00520100], zoom: 20 })"
                                                                                data-tooltip="惠州市">
                                        <i class="ph-fill ph-map-pin"></i>
                                        <span>惠州市</span>
                                    </div>
                                    <div class="withu-actions-left">
                                        <div class="withu-action-item">
                                            <i class="ph ph-eye"></i>
                                            <span data-view-count="album:20240613125618">54</span>
                                        </div>
                                        <div class="withu-action-item" data-like-target="album" data-like-id="20240613125618">
                                            <i class="ph ph-heart"></i>
                                            <span class="withu-interaction-like-num" data-like-count="album:20240613125618">5</span>
                                        </div>
                                                                                    <div class="withu-photo-count">
                                                <span class="num">14</span>
                                                <span class="label">PICS</span>
                                            </div>
                                                                            </div>
                                </div>

                            </div>
                        
                    </div>
                                    
                    <!-- Masonry Column -->
                    <div class="withu-masonry-col" data-aos="fade-up" data-aos-delay="0">

                        <!-- 私密相册卡片：未登录显示锁定，已登录显示正常内容 -->
                                                    <div class="withu-card">

                                <!-- 已解锁标识 -->
                                
                                <!-- Header -->
                                <div class="withu-header">
                                                                        <div class="withu-author show-gender">
                                        <div class="withu-author__ring">
                                            <img class="withu-author__avatar"
                                                src="/Lovefolder/20260411043037_69d95ded97293201118237.webp"
                                                alt="Avatar">
                                                                                        <div
                                                class="withu-author__badge male">
                                                <i
                                                    class="ph-bold ph-gender-male"></i>
                                            </div>
                                                                                    </div>
                                        <div class="withu-author__text">
                                            <span class="withu-author__name">Ki.</span>
                                            <span class="withu-author__meta">2025-08-11</span>
                                        </div>
                                    </div>
                                    <!-- 跳转按钮 (单张图片不显示) -->
                                                                            <a href="album-detail.php?code=20250811124452" class="withu-header-action">
                                            <i class="ph-bold ph-arrow-right"></i>
                                        </a>
                                                                    </div>

                                <!-- Content -->
                                <div class="withu-content">
                                    <h3 class="withu-title">Dalinshan</h3>
                                                                    </div>

                                <!-- Media -->
                                                                    <div class="withu-media grid-9" view-image>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="https://loveli-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20260409154408_69d758c87bec1109371280.webp"
                                                    data-original="https://loveli-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20260409154408_69d758c87bec1109371280.webp" src="https://loveli-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20260409154408_69d758c87bec1109371280.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">316.5KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20260409200706_69d7966a5de55963866644.webp"
                                                    data-original="/Lovefolder/20260409200706_69d7966a5de55963866644.webp" src="Lovefolder/20260409200706_69d7966a5de55963866644.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">351.3KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20260409200659_69d7966311ed3484002730.webp"
                                                    data-original="/Lovefolder/20260409200659_69d7966311ed3484002730.webp" src="Lovefolder/20260409200659_69d7966311ed3484002730.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">152KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20260409200656_69d7966012351355916717.webp"
                                                    data-original="/Lovefolder/20260409200656_69d7966012351355916717.webp" src="Lovefolder/20260409200656_69d7966012351355916717.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">332.5KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20260409200652_69d7965c9c347266658802.webp"
                                                    data-original="/Lovefolder/20260409200652_69d7965c9c347266658802.webp" src="Lovefolder/20260409200652_69d7965c9c347266658802.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">161.1KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20260409200649_69d796591be57476010915.webp"
                                                    data-original="/Lovefolder/20260409200649_69d796591be57476010915.webp" src="Lovefolder/20260409200649_69d796591be57476010915.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">272.6KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20250811130000_689978d011796_thumb.webp"
                                                    data-original="https://loveli-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20250811130000_689978d011796.jpeg" src="Lovefolder/20250811130000_689978d011796_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">622.7KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20250811125959_689978cf9b95f_thumb.webp"
                                                    data-original="https://loveli-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20250811125959_689978cf9b95f.jpeg" src="Lovefolder/20250811125959_689978cf9b95f_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">572.9KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20250811125958_689978cea8729_thumb.webp"
                                                    data-original="https://loveli-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20250811125958_689978cea8729.jpeg" src="Lovefolder/20250811125958_689978cea8729_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">609.6KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                                    <a href="album-detail.php?code=20250811124452" class="withu-overlay">
                                                        <span>+17</span>
                                                    </a>
                                                                                            </div>
                                                                            </div>
                                
                                <!-- Footer -->
                                <div class="withu-footer">
                                    <div class="withu-location-tag"
                                                                                    data-lng="113.58827700"
                                            data-lat="22.26141700"
                                            onclick="WithUMap.open({ mode: 'albums', coords: [113.58827700, 22.26141700], zoom: 20 })"
                                                                                data-tooltip="珠海渔女">
                                        <i class="ph-fill ph-map-pin"></i>
                                        <span>珠海渔女</span>
                                    </div>
                                    <div class="withu-actions-left">
                                        <div class="withu-action-item">
                                            <i class="ph ph-eye"></i>
                                            <span data-view-count="album:20250811124452">60</span>
                                        </div>
                                        <div class="withu-action-item" data-like-target="album" data-like-id="20250811124452">
                                            <i class="ph ph-heart"></i>
                                            <span class="withu-interaction-like-num" data-like-count="album:20250811124452">2</span>
                                        </div>
                                                                                    <div class="withu-photo-count">
                                                <span class="num">26</span>
                                                <span class="label">PICS</span>
                                            </div>
                                                                            </div>
                                </div>

                            </div>
                        
                    </div>
                                    
                    <!-- Masonry Column -->
                    <div class="withu-masonry-col" data-aos="fade-up" data-aos-delay="0">

                        <!-- 私密相册卡片：未登录显示锁定，已登录显示正常内容 -->
                                                    <div class="withu-card">

                                <!-- 已解锁标识 -->
                                
                                <!-- Header -->
                                <div class="withu-header">
                                                                        <div class="withu-author show-gender">
                                        <div class="withu-author__ring">
                                            <img class="withu-author__avatar"
                                                src="/Lovefolder/20260411043037_69d95ded97293201118237.webp"
                                                alt="Avatar">
                                                                                        <div
                                                class="withu-author__badge male">
                                                <i
                                                    class="ph-bold ph-gender-male"></i>
                                            </div>
                                                                                    </div>
                                        <div class="withu-author__text">
                                            <span class="withu-author__name">Ki.</span>
                                            <span class="withu-author__meta">2024-12-25</span>
                                        </div>
                                    </div>
                                    <!-- 跳转按钮 (单张图片不显示) -->
                                                                            <a href="album-detail.php?code=20241225163641" class="withu-header-action">
                                            <i class="ph-bold ph-arrow-right"></i>
                                        </a>
                                                                    </div>

                                <!-- Content -->
                                <div class="withu-content">
                                    <h3 class="withu-title">探索秋日山林的宁静之旅</h3>
                                                                    </div>

                                <!-- Media -->
                                                                    <div class="withu-media grid-9" view-image>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20260409211730_69d7a6eaecf46322029252.webp"
                                                    data-original="/Lovefolder/20260409211730_69d7a6eaecf46322029252.webp" src="Lovefolder/20260409211730_69d7a6eaecf46322029252.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">932.6KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="https://loveli-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20260409000955_69d67dd3b7a69592012172.webp"
                                                    data-original="https://loveli-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20260409000955_69d67dd3b7a69592012172.webp" src="https://loveli-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20260409000955_69d67dd3b7a69592012172.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">345.8KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="https://loveli-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20260409000955_69d67dd3b7a69592012172.webp"
                                                    data-original="https://loveli-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20260409000955_69d67dd3b7a69592012172.webp" src="https://loveli-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20260409000955_69d67dd3b7a69592012172.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">345.8KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20250122113356_67906724745f6_thumb.webp"
                                                    data-original="/Lovefolder/20250122113356_67906724745f6.webp" src="Lovefolder/20250122113356_67906724745f6_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">553.4KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20250122113356_67906724717c5_thumb.webp"
                                                    data-original="/Lovefolder/20250122113356_67906724717c5.webp" src="Lovefolder/20250122113356_67906724717c5_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">579.4KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20250122113356_679067246e4c6_thumb.webp"
                                                    data-original="/Lovefolder/20250122113356_679067246e4c6.webp" src="Lovefolder/20250122113356_679067246e4c6_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">590.1KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20250122113356_679067246b42b_thumb.webp"
                                                    data-original="/Lovefolder/20250122113356_679067246b42b.webp" src="Lovefolder/20250122113356_679067246b42b_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">643.4KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20250122113356_6790672468456_thumb.webp"
                                                    data-original="/Lovefolder/20250122113356_6790672468456.webp" src="Lovefolder/20250122113356_6790672468456_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">592.2KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20250122113356_6790672465362_thumb.webp"
                                                    data-original="/Lovefolder/20250122113356_6790672465362.webp" src="Lovefolder/20250122113356_6790672465362_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">610.8KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                                    <a href="album-detail.php?code=20241225163641" class="withu-overlay">
                                                        <span>+6</span>
                                                    </a>
                                                                                            </div>
                                                                            </div>
                                
                                <!-- Footer -->
                                <div class="withu-footer">
                                    <div class="withu-location-tag"
                                                                                    data-lng="113.75180000"
                                            data-lat="23.02070000"
                                            onclick="WithUMap.open({ mode: 'albums', coords: [113.75180000, 23.02070000], zoom: 20 })"
                                                                                data-tooltip="广东·东莞">
                                        <i class="ph-fill ph-map-pin"></i>
                                        <span>广东·东莞</span>
                                    </div>
                                    <div class="withu-actions-left">
                                        <div class="withu-action-item">
                                            <i class="ph ph-eye"></i>
                                            <span data-view-count="album:20241225163641">33</span>
                                        </div>
                                        <div class="withu-action-item" data-like-target="album" data-like-id="20241225163641">
                                            <i class="ph ph-heart"></i>
                                            <span class="withu-interaction-like-num" data-like-count="album:20241225163641">0</span>
                                        </div>
                                                                                    <div class="withu-photo-count">
                                                <span class="num">15</span>
                                                <span class="label">PICS</span>
                                            </div>
                                                                            </div>
                                </div>

                            </div>
                        
                    </div>
                                    
                    <!-- Masonry Column -->
                    <div class="withu-masonry-col" data-aos="fade-up" data-aos-delay="0">

                        <!-- 私密相册卡片：未登录显示锁定，已登录显示正常内容 -->
                                                    <div class="withu-card">

                                <!-- 已解锁标识 -->
                                
                                <!-- Header -->
                                <div class="withu-header">
                                                                        <div class="withu-author show-gender">
                                        <div class="withu-author__ring">
                                            <img class="withu-author__avatar"
                                                src="/Lovefolder/20260411043046_69d95df639c33274072975.webp"
                                                alt="Avatar">
                                                                                        <div
                                                class="withu-author__badge female">
                                                <i
                                                    class="ph-bold ph-gender-female"></i>
                                            </div>
                                                                                    </div>
                                        <div class="withu-author__text">
                                            <span class="withu-author__name">Really</span>
                                            <span class="withu-author__meta">2024-07-22</span>
                                        </div>
                                    </div>
                                    <!-- 跳转按钮 (单张图片不显示) -->
                                                                            <a href="album-detail.php?code=20240729105505" class="withu-header-action">
                                            <i class="ph-bold ph-arrow-right"></i>
                                        </a>
                                                                    </div>

                                <!-- Content -->
                                <div class="withu-content">
                                    <h3 class="withu-title">新家记</h3>
                                                                    </div>

                                <!-- Media -->
                                                                    <div class="withu-media grid-9" view-image>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240729112533_2_thumb.webp"
                                                    data-original="/uploads/20240729112533_2.jpeg" src="Lovefolder/20240729112533_2_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">497.6KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240729112533_1_thumb.webp"
                                                    data-original="/uploads/20240729112533_1.jpeg" src="Lovefolder/20240729112533_1_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">264.8KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240729105828_12_thumb.webp"
                                                    data-original="/uploads/20240729105828_12.jpeg" src="Lovefolder/20240729105828_12_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">481KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240729105827_11_thumb.webp"
                                                    data-original="/uploads/20240729105827_11.jpeg" src="Lovefolder/20240729105827_11_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">458.3KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240729105826_10_thumb.webp"
                                                    data-original="/uploads/20240729105826_10.jpeg" src="Lovefolder/20240729105826_10_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">496.3KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240729105825_9_thumb.webp"
                                                    data-original="/uploads/20240729105825_9.jpeg" src="Lovefolder/20240729105825_9_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">719KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240729105824_8_thumb.webp"
                                                    data-original="/uploads/20240729105824_8.jpeg" src="Lovefolder/20240729105824_8_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">429.4KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240729105823_7_thumb.webp"
                                                    data-original="/uploads/20240729105823_7.jpeg" src="Lovefolder/20240729105823_7_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">643.5KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240729105822_6_thumb.webp"
                                                    data-original="/uploads/20240729105822_6.jpeg" src="Lovefolder/20240729105822_6_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">634.9KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                                    <a href="album-detail.php?code=20240729105505" class="withu-overlay">
                                                        <span>+5</span>
                                                    </a>
                                                                                            </div>
                                                                            </div>
                                
                                <!-- Footer -->
                                <div class="withu-footer">
                                    <div class="withu-location-tag"
                                                                                    data-lng="112.46510000"
                                            data-lat="23.04690000"
                                            onclick="WithUMap.open({ mode: 'albums', coords: [112.46510000, 23.04690000], zoom: 20 })"
                                                                                data-tooltip="广东·肇庆">
                                        <i class="ph-fill ph-map-pin"></i>
                                        <span>广东·肇庆</span>
                                    </div>
                                    <div class="withu-actions-left">
                                        <div class="withu-action-item">
                                            <i class="ph ph-eye"></i>
                                            <span data-view-count="album:20240729105505">19</span>
                                        </div>
                                        <div class="withu-action-item" data-like-target="album" data-like-id="20240729105505">
                                            <i class="ph ph-heart"></i>
                                            <span class="withu-interaction-like-num" data-like-count="album:20240729105505">0</span>
                                        </div>
                                                                                    <div class="withu-photo-count">
                                                <span class="num">14</span>
                                                <span class="label">PICS</span>
                                            </div>
                                                                            </div>
                                </div>

                            </div>
                        
                    </div>
                                    
                    <!-- Masonry Column -->
                    <div class="withu-masonry-col" data-aos="fade-up" data-aos-delay="0">

                        <!-- 私密相册卡片：未登录显示锁定，已登录显示正常内容 -->
                                                    <div class="withu-card">

                                <!-- 已解锁标识 -->
                                
                                <!-- Header -->
                                <div class="withu-header">
                                                                        <div class="withu-author show-gender">
                                        <div class="withu-author__ring">
                                            <img class="withu-author__avatar"
                                                src="/Lovefolder/20260411043046_69d95df639c33274072975.webp"
                                                alt="Avatar">
                                                                                        <div
                                                class="withu-author__badge female">
                                                <i
                                                    class="ph-bold ph-gender-female"></i>
                                            </div>
                                                                                    </div>
                                        <div class="withu-author__text">
                                            <span class="withu-author__name">Really</span>
                                            <span class="withu-author__meta">2024-07-15</span>
                                        </div>
                                    </div>
                                    <!-- 跳转按钮 (单张图片不显示) -->
                                                                            <a href="album-detail.php?code=20240729110914" class="withu-header-action">
                                            <i class="ph-bold ph-arrow-right"></i>
                                        </a>
                                                                    </div>

                                <!-- Content -->
                                <div class="withu-content">
                                    <h3 class="withu-title">广州夜游</h3>
                                                                    </div>

                                <!-- Media -->
                                                                    <div class="withu-media grid-9" view-image>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20241109171801_672f28c9e8fef_thumb.webp"
                                                    data-original="/Lovefolder/20241109171801_672f28c9e8fef.jpeg" src="Lovefolder/20241109171801_672f28c9e8fef_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">177.6KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20241109171801_672f28c9d15f1_thumb.webp"
                                                    data-original="/Lovefolder/20241109171801_672f28c9d15f1.jpeg" src="Lovefolder/20241109171801_672f28c9d15f1_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">165.2KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20241109171801_672f28c9ba581_thumb.webp"
                                                    data-original="/Lovefolder/20241109171801_672f28c9ba581.jpeg" src="Lovefolder/20241109171801_672f28c9ba581_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">176.4KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20241109171801_672f28c9a3585_thumb.webp"
                                                    data-original="/Lovefolder/20241109171801_672f28c9a3585.jpeg" src="Lovefolder/20241109171801_672f28c9a3585_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">161.1KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20241109171801_672f28c98c04f_thumb.webp"
                                                    data-original="/Lovefolder/20241109171801_672f28c98c04f.jpeg" src="Lovefolder/20241109171801_672f28c98c04f_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">152.2KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20241109171801_672f28c973b65_thumb.webp"
                                                    data-original="/Lovefolder/20241109171801_672f28c973b65.jpeg" src="Lovefolder/20241109171801_672f28c973b65_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">161.4KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20241109171801_672f28c951082_thumb.webp"
                                                    data-original="/Lovefolder/20241109171801_672f28c951082.jpeg" src="Lovefolder/20241109171801_672f28c951082_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">172.4KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square is-video"
                                                 data-video-url="https://test-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20241016232047_670fd9cfbf374.mp4"
                                                    data-video-cover="https://test-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20241016231935_670fd987aad9f.png" >
                                                <img class="withu-photo lazy" data-src="https://test-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20241016231935_670fd987aad9f.png"
                                                    data-original="https://test-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20241016231935_670fd987aad9f.png" src="https://test-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20241016231935_670fd987aad9f.png" alt="Photo"
                                                    no-view>
                                                                                                    <div class="withu-video-icon"><i class="ph-fill ph-play"></i></div>
                                                                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240729112018_6_thumb.webp"
                                                    data-original="/uploads/20240729112018_6.jpeg" src="Lovefolder/20240729112018_6_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">795.1KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                                    <a href="album-detail.php?code=20240729110914" class="withu-overlay">
                                                        <span>+14</span>
                                                    </a>
                                                                                            </div>
                                                                            </div>
                                
                                <!-- Footer -->
                                <div class="withu-footer">
                                    <div class="withu-location-tag"
                                                                                    data-lng="113.26440000"
                                            data-lat="23.12910000"
                                            onclick="WithUMap.open({ mode: 'albums', coords: [113.26440000, 23.12910000], zoom: 20 })"
                                                                                data-tooltip="广东·广州">
                                        <i class="ph-fill ph-map-pin"></i>
                                        <span>广东·广州</span>
                                    </div>
                                    <div class="withu-actions-left">
                                        <div class="withu-action-item">
                                            <i class="ph ph-eye"></i>
                                            <span data-view-count="album:20240729110914">31</span>
                                        </div>
                                        <div class="withu-action-item" data-like-target="album" data-like-id="20240729110914">
                                            <i class="ph ph-heart"></i>
                                            <span class="withu-interaction-like-num" data-like-count="album:20240729110914">2</span>
                                        </div>
                                                                                    <div class="withu-photo-count">
                                                <span class="num">23</span>
                                                <span class="label">PICS</span>
                                            </div>
                                                                            </div>
                                </div>

                            </div>
                        
                    </div>
                                    
                    <!-- Masonry Column -->
                    <div class="withu-masonry-col" data-aos="fade-up" data-aos-delay="0">

                        <!-- 私密相册卡片：未登录显示锁定，已登录显示正常内容 -->
                                                    <div class="withu-card">

                                <!-- 已解锁标识 -->
                                
                                <!-- Header -->
                                <div class="withu-header">
                                                                        <div class="withu-author show-gender">
                                        <div class="withu-author__ring">
                                            <img class="withu-author__avatar"
                                                src="/Lovefolder/20260411043037_69d95ded97293201118237.webp"
                                                alt="Avatar">
                                                                                        <div
                                                class="withu-author__badge male">
                                                <i
                                                    class="ph-bold ph-gender-male"></i>
                                            </div>
                                                                                    </div>
                                        <div class="withu-author__text">
                                            <span class="withu-author__name">Ki.</span>
                                            <span class="withu-author__meta">2024-05-16</span>
                                        </div>
                                    </div>
                                    <!-- 跳转按钮 (单张图片不显示) -->
                                                                            <a href="album-detail.php?code=20240516152808" class="withu-header-action">
                                            <i class="ph-bold ph-arrow-right"></i>
                                        </a>
                                                                    </div>

                                <!-- Content -->
                                <div class="withu-content">
                                    <h3 class="withu-title">测试新增相册</h3>
                                                                    </div>

                                <!-- Media -->
                                                                    <div class="withu-media grid-9" view-image>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240516162650_3_thumb.webp"
                                                    data-original="/uploads/20240516162650_3.jpeg" src="Lovefolder/20240516162650_3_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">741.9KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240516162650_2_thumb.webp"
                                                    data-original="/uploads/20240516162650_2.jpeg" src="Lovefolder/20240516162650_2_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">715.4KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240516162650_1_thumb.webp"
                                                    data-original="/uploads/20240516162650_1.jpeg" src="Lovefolder/20240516162650_1_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">614.4KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240516161257_1_thumb.webp"
                                                    data-original="/uploads/20240516161257_1.jpeg" src="Lovefolder/20240516161257_1_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">565KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240516154645_5_thumb.webp"
                                                    data-original="/uploads/20240516154645_5.jpeg" src="Lovefolder/20240516154645_5_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">125.1KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240516154645_4_thumb.webp"
                                                    data-original="/uploads/20240516154645_4.jpeg" src="Lovefolder/20240516154645_4_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">108.4KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240516154645_3_thumb.webp"
                                                    data-original="/uploads/20240516154645_3.jpeg" src="Lovefolder/20240516154645_3_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">96.7KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240516154645_2_thumb.webp"
                                                    data-original="/uploads/20240516154645_2.jpeg" src="Lovefolder/20240516154645_2_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">90.9KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240516154645_1_thumb.webp"
                                                    data-original="/uploads/20240516154645_1.jpeg" src="Lovefolder/20240516154645_1_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">104.1KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                            </div>
                                
                                <!-- Footer -->
                                <div class="withu-footer">
                                    <div class="withu-location-tag"
                                                                                    data-lng="116.68230000"
                                            data-lat="23.35350000"
                                            onclick="WithUMap.open({ mode: 'albums', coords: [116.68230000, 23.35350000], zoom: 20 })"
                                                                                data-tooltip="广东·汕头">
                                        <i class="ph-fill ph-map-pin"></i>
                                        <span>广东·汕头</span>
                                    </div>
                                    <div class="withu-actions-left">
                                        <div class="withu-action-item">
                                            <i class="ph ph-eye"></i>
                                            <span data-view-count="album:20240516152808">5</span>
                                        </div>
                                        <div class="withu-action-item" data-like-target="album" data-like-id="20240516152808">
                                            <i class="ph ph-heart"></i>
                                            <span class="withu-interaction-like-num" data-like-count="album:20240516152808">0</span>
                                        </div>
                                                                                    <div class="withu-photo-count">
                                                <span class="num">09</span>
                                                <span class="label">PICS</span>
                                            </div>
                                                                            </div>
                                </div>

                            </div>
                        
                    </div>
                                    
                    <!-- Masonry Column -->
                    <div class="withu-masonry-col" data-aos="fade-up" data-aos-delay="0">

                        <!-- 私密相册卡片：未登录显示锁定，已登录显示正常内容 -->
                                                    <div class="withu-card">

                                <!-- 已解锁标识 -->
                                
                                <!-- Header -->
                                <div class="withu-header">
                                                                        <div class="withu-author show-gender">
                                        <div class="withu-author__ring">
                                            <img class="withu-author__avatar"
                                                src="/Lovefolder/20260411043037_69d95ded97293201118237.webp"
                                                alt="Avatar">
                                                                                        <div
                                                class="withu-author__badge male">
                                                <i
                                                    class="ph-bold ph-gender-male"></i>
                                            </div>
                                                                                    </div>
                                        <div class="withu-author__text">
                                            <span class="withu-author__name">Ki.</span>
                                            <span class="withu-author__meta">2024-05-07</span>
                                        </div>
                                    </div>
                                    <!-- 跳转按钮 (单张图片不显示) -->
                                                                            <a href="album-detail.php?code=20240507221649" class="withu-header-action">
                                            <i class="ph-bold ph-arrow-right"></i>
                                        </a>
                                                                    </div>

                                <!-- Content -->
                                <div class="withu-content">
                                    <h3 class="withu-title">关于五一假期的部分碎片</h3>
                                                                    </div>

                                <!-- Media -->
                                                                    <div class="withu-media grid-3" view-image>
                                                                                    <div class="withu-photo-box "
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240507221938_3_thumb.webp"
                                                    data-original="/uploads/20240507221938_3.jpeg" src="Lovefolder/20240507221938_3_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">769.5KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box "
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240507221938_2_thumb.webp"
                                                    data-original="/uploads/20240507221938_2.jpeg" src="Lovefolder/20240507221938_2_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">740.5KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box "
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240507221937_1_thumb.webp"
                                                    data-original="/uploads/20240507221937_1.jpeg" src="Lovefolder/20240507221937_1_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">480.9KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                            </div>
                                
                                <!-- Footer -->
                                <div class="withu-footer">
                                    <div class="withu-location-tag"
                                                                                    data-lng="113.39280000"
                                            data-lat="22.51760000"
                                            onclick="WithUMap.open({ mode: 'albums', coords: [113.39280000, 22.51760000], zoom: 20 })"
                                                                                data-tooltip="广东·中山">
                                        <i class="ph-fill ph-map-pin"></i>
                                        <span>广东·中山</span>
                                    </div>
                                    <div class="withu-actions-left">
                                        <div class="withu-action-item">
                                            <i class="ph ph-eye"></i>
                                            <span data-view-count="album:20240507221649">8</span>
                                        </div>
                                        <div class="withu-action-item" data-like-target="album" data-like-id="20240507221649">
                                            <i class="ph ph-heart"></i>
                                            <span class="withu-interaction-like-num" data-like-count="album:20240507221649">0</span>
                                        </div>
                                                                                    <div class="withu-photo-count">
                                                <span class="num">03</span>
                                                <span class="label">PICS</span>
                                            </div>
                                                                            </div>
                                </div>

                            </div>
                        
                    </div>
                                    
                    <!-- Masonry Column -->
                    <div class="withu-masonry-col" data-aos="fade-up" data-aos-delay="0">

                        <!-- 私密相册卡片：未登录显示锁定，已登录显示正常内容 -->
                                                    <div class="withu-card">

                                <!-- 已解锁标识 -->
                                
                                <!-- Header -->
                                <div class="withu-header">
                                                                        <div class="withu-author show-gender">
                                        <div class="withu-author__ring">
                                            <img class="withu-author__avatar"
                                                src="/Lovefolder/20260411043046_69d95df639c33274072975.webp"
                                                alt="Avatar">
                                                                                        <div
                                                class="withu-author__badge female">
                                                <i
                                                    class="ph-bold ph-gender-female"></i>
                                            </div>
                                                                                    </div>
                                        <div class="withu-author__text">
                                            <span class="withu-author__name">Really</span>
                                            <span class="withu-author__meta">2024-05-07</span>
                                        </div>
                                    </div>
                                    <!-- 跳转按钮 (单张图片不显示) -->
                                                                            <a href="album-detail.php?code=20240507224441" class="withu-header-action">
                                            <i class="ph-bold ph-arrow-right"></i>
                                        </a>
                                                                    </div>

                                <!-- Content -->
                                <div class="withu-content">
                                    <h3 class="withu-title">五一快乐~</h3>
                                                                    </div>

                                <!-- Media -->
                                                                    <div class="withu-media grid-6" view-image>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240508160357_1_thumb.webp"
                                                    data-original="/uploads/20240508160357_1.jpeg" src="Lovefolder/20240508160357_1_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">763.7KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240507224615_5_thumb.webp"
                                                    data-original="/uploads/20240507224615_5.jpeg" src="Lovefolder/20240507224615_5_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">368.6KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240507224614_4_thumb.webp"
                                                    data-original="/uploads/20240507224614_4.jpeg" src="Lovefolder/20240507224614_4_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">525.8KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240507224614_3_thumb.webp"
                                                    data-original="/uploads/20240507224614_3.jpeg" src="Lovefolder/20240507224614_3_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">262.7KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240507224614_2_thumb.webp"
                                                    data-original="/uploads/20240507224614_2.jpeg" src="Lovefolder/20240507224614_2_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">405.3KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240507224613_1_thumb.webp"
                                                    data-original="/uploads/20240507224613_1.jpeg" src="Lovefolder/20240507224613_1_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">451.4KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                            </div>
                                
                                <!-- Footer -->
                                <div class="withu-footer">
                                    <div class="withu-location-tag"
                                                                                    data-lng="114.41680000"
                                            data-lat="23.11150000"
                                            onclick="WithUMap.open({ mode: 'albums', coords: [114.41680000, 23.11150000], zoom: 20 })"
                                                                                data-tooltip="广东·惠州">
                                        <i class="ph-fill ph-map-pin"></i>
                                        <span>广东·惠州</span>
                                    </div>
                                    <div class="withu-actions-left">
                                        <div class="withu-action-item">
                                            <i class="ph ph-eye"></i>
                                            <span data-view-count="album:20240507224441">8</span>
                                        </div>
                                        <div class="withu-action-item" data-like-target="album" data-like-id="20240507224441">
                                            <i class="ph ph-heart"></i>
                                            <span class="withu-interaction-like-num" data-like-count="album:20240507224441">0</span>
                                        </div>
                                                                                    <div class="withu-photo-count">
                                                <span class="num">06</span>
                                                <span class="label">PICS</span>
                                            </div>
                                                                            </div>
                                </div>

                            </div>
                        
                    </div>
                                    
                    <!-- Masonry Column -->
                    <div class="withu-masonry-col" data-aos="fade-up" data-aos-delay="0">

                        <!-- 私密相册卡片：未登录显示锁定，已登录显示正常内容 -->
                                                    <div class="withu-card">

                                <!-- 已解锁标识 -->
                                
                                <!-- Header -->
                                <div class="withu-header">
                                                                        <div class="withu-author show-gender">
                                        <div class="withu-author__ring">
                                            <img class="withu-author__avatar"
                                                src="/Lovefolder/20260411043037_69d95ded97293201118237.webp"
                                                alt="Avatar">
                                                                                        <div
                                                class="withu-author__badge male">
                                                <i
                                                    class="ph-bold ph-gender-male"></i>
                                            </div>
                                                                                    </div>
                                        <div class="withu-author__text">
                                            <span class="withu-author__name">Ki.</span>
                                            <span class="withu-author__meta">2024-04-30</span>
                                        </div>
                                    </div>
                                    <!-- 跳转按钮 (单张图片不显示) -->
                                                                    </div>

                                <!-- Content -->
                                <div class="withu-content">
                                    <h3 class="withu-title">withU 五一限定相册测试</h3>
                                                                    </div>

                                <!-- Media -->
                                                                    <div class="withu-media grid-1" view-image>
                                                                                    <div class="withu-photo-box "
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240501105219_1_thumb.webp"
                                                    data-original="/uploads/20240501105219_1.jpeg" src="Lovefolder/20240501105219_1_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">96.9KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                            </div>
                                
                                <!-- Footer -->
                                <div class="withu-footer">
                                    <div class="withu-location-tag"
                                                                                    data-lng="113.75180000"
                                            data-lat="23.02070000"
                                            onclick="WithUMap.open({ mode: 'albums', coords: [113.75180000, 23.02070000], zoom: 20 })"
                                                                                data-tooltip="广东·东莞">
                                        <i class="ph-fill ph-map-pin"></i>
                                        <span>广东·东莞</span>
                                    </div>
                                    <div class="withu-actions-left">
                                        <div class="withu-action-item">
                                            <i class="ph ph-eye"></i>
                                            <span data-view-count="album:20240430110438">1</span>
                                        </div>
                                        <div class="withu-action-item" data-like-target="album" data-like-id="20240430110438">
                                            <i class="ph ph-heart"></i>
                                            <span class="withu-interaction-like-num" data-like-count="album:20240430110438">0</span>
                                        </div>
                                                                            </div>
                                </div>

                            </div>
                        
                    </div>
                                    
                    <!-- Masonry Column -->
                    <div class="withu-masonry-col" data-aos="fade-up" data-aos-delay="0">

                        <!-- 私密相册卡片：未登录显示锁定，已登录显示正常内容 -->
                                                    <div class="withu-card">

                                <!-- 已解锁标识 -->
                                
                                <!-- Header -->
                                <div class="withu-header">
                                                                        <div class="withu-author show-gender">
                                        <div class="withu-author__ring">
                                            <img class="withu-author__avatar"
                                                src="/Lovefolder/20260411043046_69d95df639c33274072975.webp"
                                                alt="Avatar">
                                                                                        <div
                                                class="withu-author__badge female">
                                                <i
                                                    class="ph-bold ph-gender-female"></i>
                                            </div>
                                                                                    </div>
                                        <div class="withu-author__text">
                                            <span class="withu-author__name">Really</span>
                                            <span class="withu-author__meta">2024-04-30</span>
                                        </div>
                                    </div>
                                    <!-- 跳转按钮 (单张图片不显示) -->
                                                                            <a href="album-detail.php?code=20240430110508" class="withu-header-action">
                                            <i class="ph-bold ph-arrow-right"></i>
                                        </a>
                                                                    </div>

                                <!-- Content -->
                                <div class="withu-content">
                                    <h3 class="withu-title">关于美食的合集</h3>
                                                                    </div>

                                <!-- Media -->
                                                                    <div class="withu-media grid-9" view-image>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240430111607_1_thumb.webp"
                                                    data-original="/uploads/20240430111607_1.jpeg" src="Lovefolder/20240430111607_1_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">82.1KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240430111426_1_thumb.webp"
                                                    data-original="/uploads/20240430111426_1.jpeg" src="Lovefolder/20240430111426_1_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">537.2KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240430110750_11_thumb.webp"
                                                    data-original="/uploads/20240430110750_11.jpeg" src="Lovefolder/20240430110750_11_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">734.1KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240430110750_10_thumb.webp"
                                                    data-original="/uploads/20240430110750_10.jpeg" src="Lovefolder/20240430110750_10_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">414.7KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240430110749_9_thumb.webp"
                                                    data-original="/uploads/20240430110749_9.jpeg" src="Lovefolder/20240430110749_9_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">728.9KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240430110749_8_thumb.webp"
                                                    data-original="/uploads/20240430110749_8.jpeg" src="Lovefolder/20240430110749_8_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">673KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240430110748_7_thumb.webp"
                                                    data-original="/uploads/20240430110748_7.jpeg" src="Lovefolder/20240430110748_7_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">546.3KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240430110747_6_thumb.webp"
                                                    data-original="/uploads/20240430110747_6.jpeg" src="Lovefolder/20240430110747_6_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">954KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20240430110747_5_thumb.webp"
                                                    data-original="/uploads/20240430110747_5.jpeg" src="Lovefolder/20240430110747_5_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">483.6KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                                    <a href="album-detail.php?code=20240430110508" class="withu-overlay">
                                                        <span>+4</span>
                                                    </a>
                                                                                            </div>
                                                                            </div>
                                
                                <!-- Footer -->
                                <div class="withu-footer">
                                    <div class="withu-location-tag"
                                                                                    data-lng="113.12140000"
                                            data-lat="23.02150000"
                                            onclick="WithUMap.open({ mode: 'albums', coords: [113.12140000, 23.02150000], zoom: 20 })"
                                                                                data-tooltip="广东·佛山">
                                        <i class="ph-fill ph-map-pin"></i>
                                        <span>广东·佛山</span>
                                    </div>
                                    <div class="withu-actions-left">
                                        <div class="withu-action-item">
                                            <i class="ph ph-eye"></i>
                                            <span data-view-count="album:20240430110508">13</span>
                                        </div>
                                        <div class="withu-action-item" data-like-target="album" data-like-id="20240430110508">
                                            <i class="ph ph-heart"></i>
                                            <span class="withu-interaction-like-num" data-like-count="album:20240430110508">1</span>
                                        </div>
                                                                                    <div class="withu-photo-count">
                                                <span class="num">13</span>
                                                <span class="label">PICS</span>
                                            </div>
                                                                            </div>
                                </div>

                            </div>
                        
                    </div>
                                    
                    <!-- Masonry Column -->
                    <div class="withu-masonry-col" data-aos="fade-up" data-aos-delay="0">

                        <!-- 私密相册卡片：未登录显示锁定，已登录显示正常内容 -->
                                                    <div class="withu-card">

                                <!-- 已解锁标识 -->
                                
                                <!-- Header -->
                                <div class="withu-header">
                                                                        <div class="withu-author show-gender">
                                        <div class="withu-author__ring">
                                            <img class="withu-author__avatar"
                                                src="/Lovefolder/20260411043046_69d95df639c33274072975.webp"
                                                alt="Avatar">
                                                                                        <div
                                                class="withu-author__badge female">
                                                <i
                                                    class="ph-bold ph-gender-female"></i>
                                            </div>
                                                                                    </div>
                                        <div class="withu-author__text">
                                            <span class="withu-author__name">Really</span>
                                            <span class="withu-author__meta">2021-08-29</span>
                                        </div>
                                    </div>
                                    <!-- 跳转按钮 (单张图片不显示) -->
                                                                            <a href="album-detail.php?code=1776318513866" class="withu-header-action">
                                            <i class="ph-bold ph-arrow-right"></i>
                                        </a>
                                                                    </div>

                                <!-- Content -->
                                <div class="withu-content">
                                    <h3 class="withu-title">测试相册</h3>
                                                                    </div>

                                <!-- Media -->
                                                                    <div class="withu-media grid-9" view-image>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="https://loveli-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20260523212529_6a11aac9895bc506883115_thumb.webp"
                                                    data-original="https://loveli-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20260523212529_6a11aac989601609134928.webp" src="https://loveli-1255495366.cos.ap-guangzhou.myqcloud.com/Lovefolder/20260523212529_6a11aac9895bc506883115_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">237.8KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20260416152610_69e08f120ea9f251265302_thumb.webp"
                                                    data-original="/Lovefolder/20260416152610_69e08f120ead1831369049.webp" src="Lovefolder/20260416152610_69e08f120ea9f251265302_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">219.5KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20260416152656_69e08f4087d64784547991_thumb.webp"
                                                    data-original="/Lovefolder/20260416152656_69e08f4087da1066854328.webp" src="Lovefolder/20260416152656_69e08f4087d64784547991_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">184.9KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20260416152612_69e08f1433ec1936267310_thumb.webp"
                                                    data-original="/Lovefolder/20260416152612_69e08f1433ef5695777043.webp" src="Lovefolder/20260416152612_69e08f1433ec1936267310_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">364KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20260416152615_69e08f1746aad723099683_thumb.webp"
                                                    data-original="/Lovefolder/20260416152615_69e08f1746ae0802172044.webp" src="Lovefolder/20260416152615_69e08f1746aad723099683_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">211.1KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20260416152620_69e08f1ca7a9f850617615_thumb.webp"
                                                    data-original="/Lovefolder/20260416152620_69e08f1ca7ae6234005612.webp" src="Lovefolder/20260416152620_69e08f1ca7a9f850617615_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">375.2KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20260416152625_69e08f21d3ba4526472220_thumb.webp"
                                                    data-original="/Lovefolder/20260416152625_69e08f21d3bdf147867170.webp" src="Lovefolder/20260416152625_69e08f21d3ba4526472220_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">378.7KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20260416152631_69e08f276e9de203148723_thumb.webp"
                                                    data-original="/Lovefolder/20260416152631_69e08f276ea29566330051.webp" src="Lovefolder/20260416152631_69e08f276e9de203148723_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">254.4KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                            </div>
                                                                                    <div class="withu-photo-box square"
                                                >
                                                <img class="withu-photo lazy" data-src="Lovefolder/20260416152636_69e08f2cbf415963255468_thumb.webp"
                                                    data-original="/Lovefolder/20260416152636_69e08f2cbf44f248335826.webp" src="Lovefolder/20260416152636_69e08f2cbf415963255468_thumb.webp" alt="Photo"
                                                    >
                                                                                                                                                    <span class="withu-file-size">957.9KB</span>
                                                
                                                <!-- +N 遮罩层 -->
                                                                                                    <a href="album-detail.php?code=1776318513866" class="withu-overlay">
                                                        <span>+12</span>
                                                    </a>
                                                                                            </div>
                                                                            </div>
                                
                                <!-- Footer -->
                                <div class="withu-footer">
                                    <div class="withu-location-tag"
                                                                                    data-lng="113.31222700"
                                            data-lat="23.13955500"
                                            onclick="WithUMap.open({ mode: 'albums', coords: [113.31222700, 23.13955500], zoom: 20 })"
                                                                                data-tooltip="广州市">
                                        <i class="ph-fill ph-map-pin"></i>
                                        <span>广州市</span>
                                    </div>
                                    <div class="withu-actions-left">
                                        <div class="withu-action-item">
                                            <i class="ph ph-eye"></i>
                                            <span data-view-count="album:1776318513866">29</span>
                                        </div>
                                        <div class="withu-action-item" data-like-target="album" data-like-id="1776318513866">
                                            <i class="ph ph-heart"></i>
                                            <span class="withu-interaction-like-num" data-like-count="album:1776318513866">0</span>
                                        </div>
                                                                                    <div class="withu-photo-count">
                                                <span class="num">21</span>
                                                <span class="label">PICS</span>
                                            </div>
                                                                            </div>
                                </div>

                            </div>
                        
                    </div>
                
            </div>
        </div>
    </div>

    <script src="/assets/js/page-albums.js"></script>
    

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
        window.WITHU_CONFIG.anonymousAvatar = "/Lovefolder/20250310095445_67ce46659745d.gif";
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
<script src="/assets/js/webp-default.js?v=20260830"></script>
<script src="/assets/js/page-albums-85b34779.js"></script>
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
                                <a class="withu-base-nav-item js-withu-v5-item active"
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

<script src="/assets/js/mobile-nav.js"></script>


<script src="/assets/js/page-albums-f9f55eb3.js"></script>


    <script>
        // 页面首次加载时初始化 Masonry
        $(function () {
            if (window.WithUPjax && window.WithUPjax.MasonryManager) {
                window.WithUPjax.MasonryManager.initLGGrid();
            }
        });
    </script>
</body>

</html>
