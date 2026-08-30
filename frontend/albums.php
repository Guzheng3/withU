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
<meta property="og:site_name" content="withU Demo">
<meta property="og:title" content="withU Demo">
<meta property="og:description" content="withU 是一个适合记录恋爱日常与纪念时刻的情侣小站，支持相册、时间轴、点滴文章、留言互动和邀请页面，让每一段关系都能拥有自己的专属回忆空间。">
<meta property="og:url" content="/albums.php">
<meta property="og:image" content="withU">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="withU Demo">
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
        <script>
        window._AMapSecurityConfig = {"securityJsCode":"d4fe1ef6bb455368bc92d5fb577b2f3b"};
        window.WITHU_MAP_CONFIG = {"amapKey":"7d245650b5ba899ce4f025961613dcc5","modeConfig":{"lovers":{"title":"情侣模式","desc":"无论相隔多远，心始终在一起"},"moments":{"title":"点点滴滴","desc":"记录我们的每一个美好瞬间"},"messages":{"title":"留言模式","desc":"来自世界各地的温暖祝福"},"albums":{"title":"相册模式","desc":"用照片定格我们的回忆"},"events":{"title":"事件清单","desc":"一起完成的每一个小目标"}},"lovers":[],"milestones":[],"events":[],"albums":[],"messages":[],"moments":[],"loveStartDate":"","hsla":"345deg,70%,55%","mapStyle":"amap://styles/grey","soloMode":false,"_apiBase":"/assets/map-api.php"};
        window.WithUMapData = window.WithUMapData || {
            assign: function (data) {
                if (data.lovers) window.WITHU_MAP_CONFIG.lovers = data.lovers;
                if (typeof data.loveStartDate !== 'undefined') window.WITHU_MAP_CONFIG.loveStartDate = data.loveStartDate;
                if (data.milestones) window.WITHU_MAP_CONFIG.milestones = data.milestones;
                if (data.moments) window.WITHU_MAP_CONFIG.moments = data.moments;
                if (data.messages) window.WITHU_MAP_CONFIG.messages = data.messages;
                if (data.albums) window.WITHU_MAP_CONFIG.albums = data.albums;
                if (data.events) window.WITHU_MAP_CONFIG.events = data.events;
                return data;
            },
            fetchAll: function () {
                var apiUrl = new URL(window.WITHU_MAP_CONFIG._apiBase, window.location.origin);
                apiUrl.searchParams.set('module', 'all');
                return fetch(apiUrl.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                })
                    .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
                    .then(this.assign.bind(this));
            }
        };

        window.WITHU_MAP_DATA_READY = window.WithUMapData.fetchAll()
            .catch(function (err) {
                if (window.WITHU_CONFIG && window.WITHU_CONFIG.debugMap && window.console && typeof window.console.warn === 'function') {
                    window.console.warn('地图数据加载失败:', err);
                }
            });
    </script>
<script src="/assets/js/app.js"></script>
<script src="/assets/js/withu-location.js"></script>
<script src="/assets/js/head-avatar-location.js"></script>
<script src="/assets/js/components.js"></script>

<!-- 礼花效果已迁移到 components.js 的 ConfettiEffect 模块 -->

<script src="/assets/js/pjax.js"></script><script>if(window.WithUPjax&typeof window.WithUPjax.init==="function")window.WithUPjax.init();</script>
<style>
    #loader-wrapper {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 10000;
        overflow: hidden;
    }

    .no-js #loader-wrapper {
        display: none;
    }

    #loader {
        display: block;
        position: relative;
        left: 50%;
        top: 50%;
        width: 150px;
        height: 150px;
        margin: -75px 0 0 -75px;
        border-radius: 50%;
        border: 3px solid transparent;
        border-top-color: #16a085;
        -webkit-animation: spin 1.7s linear infinite;
        animation: spin 1.7s linear infinite;
        z-index: 11;
    }

    #loader:before {
        content: "";
        position: absolute;
        top: 5px;
        left: 5px;
        right: 5px;
        bottom: 5px;
        border-radius: 50%;
        border: 3px solid transparent;
        border-top-color: #e74c3c;
        -webkit-animation: spin-reverse 0.6s linear infinite;
        animation: spin-reverse 0.6s linear infinite;
    }

    #loader:after {
        content: "";
        position: absolute;
        top: 15px;
        left: 15px;
        right: 15px;
        bottom: 15px;
        border-radius: 50%;
        border: 3px solid transparent;
        border-top-color: #f9c922;
        -webkit-animation: spin 1s linear infinite;
        animation: spin 1s linear infinite;
    }

    @-webkit-keyframes spin {
        0% {
            -webkit-transform: rotate(0deg);
        }

        100% {
            -webkit-transform: rotate(360deg);
        }
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    @-webkit-keyframes spin-reverse {
        0% {
            -webkit-transform: rotate(0deg);
        }

        100% {
            -webkit-transform: rotate(-360deg);
        }
    }

    @keyframes spin-reverse {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(-360deg);
        }
    }

    #loader-wrapper .loader-section {
        position: fixed;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        z-index: 10;
        background: #ffffffd6;
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
    }

    .loaded #loader {
        opacity: 0;
        transition: all 0.3s ease-out;
    }

    .loaded #loader-wrapper {
        animation: MaskFadeOut .5s linear forwards;
        display: none;
    }

    @keyframes MaskFadeOut {
        0% {
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }

        95% {
            backdrop-filter: blur(0px);
            -webkit-backdrop-filter: blur(0px);
        }

        100% {
            opacity: 0;
            display: none;
        }
    }

    /* 全局通用修正 */
    * {
        -webkit-overflow-scrolling: touch;
    }

    /* 媒体查询修正 */
    @media screen and (max-width: 768px) {
        .row.central.nav_k {
            display: none;
        }

        .swiper-container {
            margin-top: 3rem;
        }

        .swiper-wrap {
            width: 100%;
        }

        .introduce img {
            height: 8em;
        }

        .introduce {
            padding: 0;
        }

        iframe {
            height: 270px;
        }

        .wrap {
            width: 95%;
            height: 320px;
            position: relative;
            margin: 0 auto 1rem;
            margin-top: 5.2rem;
        }

        .list {
            height: 320px;
        }
    }

    /* 气泡与头像样式 */
    .bg-wrap .love-icon {
        position: relative;
        z-index: 200 !important;
    }

    /* 只有爱心图片可以交互和 hover */
    .bg-wrap .love-icon>img {
        cursor: pointer !important;
        transition: filter 0.3s ease, transform 0.15s ease;
        pointer-events: auto !important;
    }

    /* hover 时发光效果 */
    .bg-wrap .love-icon>img:hover {
        filter: drop-shadow(0 0 15px rgba(252, 93, 121, 0.8)) drop-shadow(0 0 25px rgba(252, 93, 121, 0.5)) brightness(1.1);
    }

    /* 点击时轻微缩小 */
    .bg-wrap .love-icon>img:active {
        transform: scale(0.95);
        filter: drop-shadow(0 0 20px rgba(252, 93, 121, 1)) brightness(1.2);
    }

    /* 确保遮罩层不阻挡点击 */
    ul.mask_black::before {
        pointer-events: none !important;
    }

    /* 确保爱心容器在最上层，但允许子元素交互 */
    .bg-wrap.central.limg {
        z-index: 150 !important;
    }

    /* 头像区域可以交互 */
    .bg-wrap.central.limg .avatarArea,
    .bg-wrap.central.limg .img-male,
    .bg-wrap.central.limg .img-female {
        pointer-events: auto;
    }

    /* ── 交换头像位置 ── */
    .bg-wrap.central.limg[data-avatar-swap="1"] .middle {
        flex-direction: row-reverse;
    }

    /* ── 单人模式：只保留主头像与距离气泡 ── */
    .bg-wrap.central.limg[data-solo-mode="1"] .middle .love-icon>img {
        display: none;
    }

    .bg-wrap.central.limg[data-solo-mode="1"] .middle {
        position: relative;
        justify-content: center;
    }

    .bg-wrap.central.limg[data-solo-mode="1"] .middle .love-icon {
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -204px);
        z-index: 260 !important;
        width: auto;
    }

    .bg-wrap.central.limg[data-solo-mode="1"] .middle .love-info-wrapper {
        position: static;
        top: auto;
        left: auto;
        transform: none;
    }

    /* ── 紧凑间距（桌面端全屏/卡片均生效，移动端不变） ── */
    @media (min-width: 769px) {
        .bg-wrap.central.limg[data-avatar-spacing="compact"] .bg-img .middle {
            padding-left: 20%;
            padding-right: 20%;
        }
    }

    .love-info-wrapper {
        position: absolute;
        top: -7rem;
        left: 50%;
        transform: translateX(-50%);
        z-index: 50;
        text-align: center;
        pointer-events: none;
    }

    .love-info-wrapper .distance-bubble {
        pointer-events: auto;
    }

    .love-info-wrapper .mr-1 {
        margin-right: .3rem;
    }

    /* 融合方圆风格 - Style 4 */
    .distance-bubble {
        display: flex;
        align-items: center;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        /* 方圆形 */
        border-radius: 14px;
        padding: 6px;
        padding-right: 18px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        white-space: nowrap;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        user-select: none;
        -webkit-user-select: none;
        font-family: 'Noto Serif SC', cursive;
    }

    .distance-bubble:hover {
        background: rgba(255, 255, 255, 0.18);
        border: 1px solid rgba(255, 255, 255, 0.25);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .distance-bubble:active {
        transform: translateY(0);
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
    }

    .distance-bubble .distance-icon-box {
        background: rgba(255, 255, 255, 0.15);
        width: 34px;
        height: 34px;
        border-radius: 11px;
        /* 方圆角 */
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .distance-bubble:hover .distance-icon-box {
        background: rgba(255, 255, 255, 0.25);
        transform: scale(1.05);
    }

    .distance-bubble i {
        font-size: 18px;
        color: #fff;
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .distance-bubble:hover i {
        transform: rotate(45deg);
    }

    .distance-bubble .distance-text {
        margin-left: 10px;
        color: #fff;
        font-size: 15px;
        display: flex;
        gap: 5px;
    }

    .distance-bubble .distance-text-sm {
        font-weight: 400;
        opacity: 0.9;
        transition: opacity 0.3s ease;
    }

    .distance-bubble:hover .distance-text-sm {
        opacity: 1;
    }

    .distance-bubble .km-value {
        font-weight: 700;
        font-family: 'Space Mono', sans-serif;
        font-size: 16px;
    }

    .middle .distance-bubble {
        background: rgba(0, 0, 0, 0.35);
        border: 1px solid rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
    }

    .middle .distance-bubble:hover {
        background: rgba(0, 0, 0, 0.45);
        border: 1px solid rgba(255, 255, 255, 0.25);
    }

    .middle .distance-bubble .distance-icon-box {
        background: rgba(255, 255, 255, 0.2);
    }

    .middle .distance-bubble:hover .distance-icon-box {
        background: rgba(255, 255, 255, 0.3);
    }

    .middle.Blurkg .distance-bubble {
        background: rgba(255, 255, 255, 0.15);
        border: 1px solid rgba(255, 255, 255, 0.15);
    }

    .middle.Blurkg .distance-bubble:hover {
        background: rgba(255, 255, 255, 0.25);
        border: 1px solid rgba(255, 255, 255, 0.35);
    }

    .online-status {
        margin-top: 10px;
        padding: 5px 14px;
        font-size: 0.95rem;
        font-weight: 500;
        color: #fff;
        background: linear-gradient(90deg, rgba(255, 105, 180, 0.9), rgba(255, 182, 193, 0.8));
        border-radius: 15px;
        box-shadow: 0 0 10px rgba(255, 192, 203, 0.4);
        animation: glow 2.5s ease-in-out infinite;
    }

    @keyframes bubbleFloat {

        0%,
        100% {
            transform: translateY(0);
        }

        50% {
            transform: translateY(-6px);
        }
    }

    @keyframes glow {

        0%,
        100% {
            opacity: 1;
            box-shadow: 0 0 10px rgba(255, 182, 193, 0.5);
        }

        50% {
            opacity: 0.8;
            box-shadow: 0 0 18px rgba(255, 182, 193, 0.8);
        }
    }

    @media (max-width: 768px) {
        .distance-bubble {
            padding: 5px;
            padding-right: 14px;
            border-radius: 12px;
        }

        .distance-bubble .distance-icon-box {
            width: 28px;
            height: 28px;
            border-radius: 9px;
        }

        .distance-bubble i {
            font-size: 15px;
        }

        .distance-bubble .distance-text {
            margin-left: 8px;
            font-size: 13px;
            gap: 4px;
        }

        .distance-bubble .km-value {
            font-size: 14px;
        }

        .online-status {
            font-size: 0.85rem;
        }
    }

    .CarouselImage {
        width: 100%;
        height: 100%;
        object-fit: cover;
        user-select: none;
        -webkit-user-select: none;
        -webkit-user-drag: none;
        -moz-user-select: none;
        -ms-user-select: none;
        pointer-events: none;
    }

    .left_info {
        position: relative;
    }


    .div_off {
        padding: 3rem 2rem;
        background: #fff;
        border-radius: 1rem;
        text-align: center;
        font-size: 1.8rem;
        font-family: 'Noto Serif SC', serif;
        font-weight: 700;
        letter-spacing: 3px;
        color: #eb2c14;
        box-shadow: 0 6px 15px #d3d1d159;
    }

    .nub_1 b {
        position: absolute;
        right: 0;
        top: 0;
        padding: 0.5rem 2rem;
        background: #f9d7ea;
        color: #e03997;
        border-radius: 1rem;
    }

    .central.bg .row .card .leavform .textinfo .time {
        text-align: right;
        display: inherit;
        font-family: 'Noto Serif SC', serif;
        font-weight: 400;
        color: #423a3a;
    }

    .bg-img {
        background-size: cover !important;
    }

    .alogo {
        color: #181818 !important;
        text-shadow: 0 2px 4px rgb(0 0 0 / 15%);
        font-weight: 900;
        letter-spacing: 0.05em;
        position: relative;
        display: inline-block;
        transition:
            color 0.3s ease,
            text-shadow 0.3s ease,
            opacity 0.3s ease,
            transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
        will-change: transform, opacity;
    }

    /* Logo hover 首页图标 */
    .alogo-home-icon {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) scale(0.4);
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: #111;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 20px;
        opacity: 0;
        pointer-events: none;
        box-shadow: 0 4px 18px rgba(0,0,0,0.2);
        transition:
            opacity 0.25s ease,
            transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1),
            box-shadow 0.3s ease;
    }

    .alogo.alogo--home-show {
        color: transparent !important;
        text-shadow: none !important;
    }

    .alogo.alogo--home-show .alogo-home-icon {
        opacity: 1;
        transform: translate(-50%, -50%) scale(1);
        pointer-events: auto;
    }

    .alogo.alogo--home-press .alogo-home-icon {
        transform: translate(-50%, -50%) scale(0.82);
        box-shadow: 0 2px 8px rgba(0,0,0,0.25);
        transition-duration: 0.1s, 0.1s, 0.1s;
    }

    /* Logo 淡出效果 - 通过JS添加类控制 */
    .alogo.withu-logo-faded {
        opacity: 0.3;
        transform: scale(0.92);
    }

    /* Header 布局 - 保持原有居中，图标使用绝对定位 */
    .header {
        position: relative;
    }

    /* 左侧纯文字 Logo - 只有文字 */
    /* 左侧 Logo - 时尚杂志风格 */
    .withu-header-left-avatar {
        position: absolute;
        left: 6%;
        top: 50%;
        transform: translateY(-50%) translateX(-20px) scale(0.9);
        opacity: 0;
        pointer-events: none;
        z-index: 10;
        /* 移除胶囊背景，回归纯粹 */
        background: none;
        backdrop-filter: none;
        -webkit-backdrop-filter: none;
        padding: 0;
        border: none;
        box-shadow: none;
        display: flex;
        align-items: center;
        gap: 8px;
        /* 只过渡 GPU 可加速属性 */
        transition:
            transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1),
            opacity 0.3s ease;
        will-change: transform, opacity;
    }

    .withu-header-left-avatar:hover {
        background: none;
        box-shadow: none;
        transform: translateY(-50%) translateX(-20px);
    }

    /* ================================== */
    /* 吸顶 Logo 多套风格系统 (v5 · 14套)  */
    /* 命名: .stuck-logo--{cn|en}-v{1~7}   */
    /* ================================== */
    .stuck-logo {
        display: flex;
        align-items: center;
    }

    /* ============================== */
    /* cn-v1: 水墨风 · 黑色胶囊         */
    /* Ma Shan Zheng + heart SVG      */
    /* ============================== */
    .stuck-logo--cn-v1 {
        gap: 0;
        background-color: rgba(0, 0, 0, 0.9);
        padding: 6px 16px;
        border-radius: 9999px;
        height: 40px;
        box-sizing: border-box;
    }

    .stuck-logo--cn-v1 .stuck-logo__name {
        font-family: 'Ma Shan Zheng', cursive;
        font-size: 20px;
        font-weight: 700;
        color: #fff;
        padding: 0 4px;
        line-height: 1;
    }

    .stuck-logo--cn-v1 .stuck-logo__heart {
        display: flex;
        align-items: center;
        margin: 0 6px;
        color: rgba(255, 255, 255, 0.8);
        animation: stuckHeartPulse 2s ease-in-out infinite;
    }

    /* ============================== */
    /* cn-v2: 狂野红 · 红色胶囊         */
    /* Ma Shan Zheng + 囍 圆形          */
    /* ============================== */
    .stuck-logo--cn-v2 {
        gap: 0;
        background-color: #7f1d1d;
        padding: 6px 16px;
        border-radius: 9999px;
        height: 40px;
        box-sizing: border-box;
    }

    .stuck-logo--cn-v2 .stuck-logo__name {
        font-family: 'Ma Shan Zheng', cursive;
        font-size: 22px;
        color: #fff;
        padding: 0 4px;
        line-height: 1;
    }

    .stuck-logo--cn-v2 .stuck-logo__xi {
        color: #7f1d1d;
        font-weight: bold;
        font-size: 18px;
        background: #fff;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 8px;
        line-height: 1;
        flex-shrink: 0;
    }

    /* ============================== */
    /* cn-v3: 时尚宋体 · 黑色胶囊       */
    /* Noto Serif SC + infinity SVG    */
    /* ============================== */
    .stuck-logo--cn-v3 {
        gap: 0;
        background-color: #000;
        padding: 6px 16px;
        border-radius: 9999px;
        height: 40px;
        box-sizing: border-box;
    }

    .stuck-logo--cn-v3 .stuck-logo__name {
        font-family: 'Noto Serif SC', serif;
        font-size: 18px;
        font-weight: 700;
        color: #fff;
        padding: 0 4px;
        line-height: 1;
    }

    .stuck-logo--cn-v3 .stuck-logo__icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background-color: rgba(255, 255, 255, 0.2);
        margin: 0 8px;
        flex-shrink: 0;
        color: #fff;
    }

    /* ============================== */
    /* cn-v4: 宋体渐变竖线 · 无胶囊     */
    /* Noto Serif SC + gradient line   */
    /* ============================== */
    .stuck-logo--cn-v4 {
        gap: 16px;
    }

    .stuck-logo--cn-v4 .stuck-logo__name {
        font-family: 'Noto Serif SC', serif;
        color: #0f172a;
        font-weight: 700;
        font-size: 20px;
        letter-spacing: 0.1em;
        line-height: 1;
    }

    .stuck-logo--cn-v4 .stuck-logo__divider {
        width: 1px;
        height: 20px;
        background: linear-gradient(to bottom, transparent, #0f172a, transparent);
        opacity: 0.8;
        flex-shrink: 0;
    }

    /* ============================== */
    /* cn-v5: 艺术风 · AND 副文字      */
    /* Ma Shan Zheng + sub text       */
    /* ============================== */
    .stuck-logo--cn-v5 {
        gap: 0;
    }

    .stuck-logo--cn-v5 .stuck-logo__name {
        font-family: 'Ma Shan Zheng', cursive;
        font-size: 24px;
        color: #1e293b;
        line-height: 1;
    }

    .stuck-logo--cn-v5 .stuck-logo__sub {
        font-family: 'Noto Serif SC', serif;
        font-size: 12px;
        color: #64748b;
        letter-spacing: 0.1em;
        margin: 0 8px;
        margin-top: 4px;
    }

    /* ============================== */
    /* cn-v6: 角括号 · 伪元素装饰       */
    /* Noto Serif SC + corner borders  */
    /* ============================== */
    .stuck-logo--cn-v6 {
        gap: 0;
    }

    .stuck-logo--cn-v6 .stuck-logo__bracket {
        position: relative;
        padding: 8px 24px;
    }

    .stuck-logo--cn-v6 .stuck-logo__bracket::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 10px;
        height: 10px;
        border-top: 1px solid #0f172a;
        border-left: 1px solid #0f172a;
    }

    .stuck-logo--cn-v6 .stuck-logo__bracket::after {
        content: '';
        position: absolute;
        bottom: 0;
        right: 0;
        width: 10px;
        height: 10px;
        border-bottom: 1px solid #0f172a;
        border-right: 1px solid #0f172a;
    }

    .stuck-logo--cn-v6 .stuck-logo__text {
        font-family: 'Noto Serif SC', serif;
        font-size: 20px;
        font-weight: 700;
        color: #0f172a;
    }

    /* ============================== */
    /* cn-v7: 红线爱心 · 经典红绳       */
    /* Noto Serif SC + red lines       */
    /* ============================== */
    .stuck-logo--cn-v7 {
        gap: 0;
    }

    .stuck-logo--cn-v7 .stuck-logo__name {
        font-family: 'Noto Serif SC', serif;
        font-size: 18px;
        font-weight: 700;
        color: #1e293b;
        padding: 0 8px;
        line-height: 1;
    }

    .stuck-logo--cn-v7 .stuck-logo__redline-l {
        width: 32px;
        height: 1px;
        background: linear-gradient(to right, transparent, #f87171);
    }

    .stuck-logo--cn-v7 .stuck-logo__redline-r {
        width: 32px;
        height: 1px;
        background: linear-gradient(to left, transparent, #f87171);
    }

    .stuck-logo--cn-v7 .stuck-logo__heart {
        color: #ef4444;
        margin: 0 8px;
        display: flex;
        align-items: center;
        animation: stuckHeartPulse 2s ease-in-out infinite;
    }

    /* ============================== */
    /* en-v1: 优雅衬线 · 黑色胶囊       */
    /* Playfair Display + heart SVG    */
    /* ============================== */
    .stuck-logo--en-v1 {
        gap: 0;
        background-color: rgba(0, 0, 0, 0.9);
        padding: 6px 16px;
        border-radius: 9999px;
        height: 40px;
        box-sizing: border-box;
    }

    .stuck-logo--en-v1 .stuck-logo__name {
        font-family: 'Playfair Display', serif;
        font-size: 18px;
        letter-spacing: 0.1em;
        color: #fff;
        padding: 0 4px;
        line-height: 1;
    }

    .stuck-logo--en-v1 .stuck-logo__heart {
        display: flex;
        align-items: center;
        margin: 0 6px;
        color: rgba(255, 255, 255, 0.8);
        animation: stuckHeartPulse 2s ease-in-out infinite;
    }

    /* ============================== */
    /* en-v2: 蓝灰胶囊 · 花体手写       */
    /* Dancing Script + sparkles       */
    /* ============================== */
    .stuck-logo--en-v2 {
        gap: 0;
        background-color: #64748b;
        padding: 6px 16px;
        border-radius: 9999px;
        height: 40px;
        box-sizing: border-box;
    }

    .stuck-logo--en-v2 .stuck-logo__name {
        font-family: 'Dancing Script', cursive;
        font-size: 24px;
        color: #fff;
        padding: 0 4px;
        line-height: 1;
    }

    .stuck-logo--en-v2 .stuck-logo__icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background-color: #fff;
        color: #64748b;
        margin: 0 8px;
        flex-shrink: 0;
    }

    /* ============================== */
    /* en-v3: 时尚黑 · 花体手写          */
    /* Dancing Script + infinity       */
    /* ============================== */
    .stuck-logo--en-v3 {
        gap: 0;
        background-color: #000;
        padding: 6px 16px;
        border-radius: 9999px;
        height: 40px;
        box-sizing: border-box;
    }

    .stuck-logo--en-v3 .stuck-logo__name {
        font-family: 'Dancing Script', cursive;
        font-size: 26px;
        color: #fff;
        padding: 0 4px;
        line-height: 1;
    }

    .stuck-logo--en-v3 .stuck-logo__icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background-color: rgba(255, 255, 255, 0.2);
        color: #fff;
        margin: 0 8px;
        flex-shrink: 0;
    }

    /* ============================== */
    /* en-v4: 渐变竖线 · 大写衬线       */
    /* Crimson Pro + gradient line     */
    /* ============================== */
    .stuck-logo--en-v4 {
        gap: 16px;
    }

    .stuck-logo--en-v4 .stuck-logo__name {
        font-family: 'Crimson Pro', serif;
        color: #0f172a;
        font-weight: 700;
        font-size: 20px;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        line-height: 1;
    }

    .stuck-logo--en-v4 .stuck-logo__divider {
        width: 1px;
        height: 20px;
        background: linear-gradient(to bottom, transparent, #0f172a, transparent);
        opacity: 0.8;
        flex-shrink: 0;
    }

    /* ============================== */
    /* en-v5: 经典衬线 · with 副文字    */
    /* Libre Baskerville + sub text   */
    /* ============================== */
    .stuck-logo--en-v5 {
        gap: 0;
    }

    .stuck-logo--en-v5 .stuck-logo__name {
        font-family: 'Libre Baskerville', serif;
        font-size: 22px;
        font-weight: 600;
        color: #1e293b;
        line-height: 1;
    }

    .stuck-logo--en-v5 .stuck-logo__sub {
        font-family: 'Inter', sans-serif;
        font-size: 10px;
        color: #94a3b8;
        letter-spacing: 0.2em;
        margin: 0 12px;
        text-transform: uppercase;
    }

    /* ============================== */
    /* en-v6: 角括号 · 大写衬线         */
    /* Playfair Display + brackets    */
    /* ============================== */
    .stuck-logo--en-v6 {
        gap: 0;
    }

    .stuck-logo--en-v6 .stuck-logo__bracket {
        position: relative;
        padding: 8px 24px;
    }

    .stuck-logo--en-v6 .stuck-logo__bracket::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 10px;
        height: 10px;
        border-top: 1px solid #0f172a;
        border-left: 1px solid #0f172a;
    }

    .stuck-logo--en-v6 .stuck-logo__bracket::after {
        content: '';
        position: absolute;
        bottom: 0;
        right: 0;
        width: 10px;
        height: 10px;
        border-bottom: 1px solid #0f172a;
        border-right: 1px solid #0f172a;
    }

    .stuck-logo--en-v6 .stuck-logo__text {
        font-family: 'Playfair Display', serif;
        font-size: 20px;
        letter-spacing: 0.05em;
        color: #0f172a;
        text-transform: uppercase;
    }

    /* ============================== */
    /* en-v7: 红线爱心 · 花体手写       */
    /* Dancing Script + red lines     */
    /* ============================== */
    .stuck-logo--en-v7 {
        gap: 0;
    }

    .stuck-logo--en-v7 .stuck-logo__name {
        font-family: 'Dancing Script', cursive;
        font-size: 24px;
        color: #1e293b;
        padding: 0 8px;
        line-height: 1;
    }

    .stuck-logo--en-v7 .stuck-logo__redline-l {
        width: 18px;
        height: 1px;
        background: linear-gradient(to right, transparent, #f87171);
    }

    .stuck-logo--en-v7 .stuck-logo__redline-r {
        width: 18px;
        height: 1px;
        background: linear-gradient(to left, transparent, #f87171);
    }

    .stuck-logo--en-v7 .stuck-logo__heart {
        color: #ef4444;
        margin: 0 8px;
        display: flex;
        align-items: center;
        animation: stuckHeartPulse 2s ease-in-out infinite;
    }

    /* ============================== */
    /* Solo Logo 系列（单人模式）      */
    /* ============================== */

    /* solo-cn-v1: 角括号 · 宋体（复用 cn-v6 风格） */
    .stuck-logo--solo-cn-v1 {
        gap: 0;
    }
    .stuck-logo--solo-cn-v1 .stuck-logo__bracket {
        position: relative;
        padding: 6px 20px;
    }
    .stuck-logo--solo-cn-v1 .stuck-logo__bracket::before {
        content: '';
        position: absolute;
        top: 0; left: 0;
        width: 10px; height: 10px;
        border-top: 1px solid #0f172a;
        border-left: 1px solid #0f172a;
    }
    .stuck-logo--solo-cn-v1 .stuck-logo__bracket::after {
        content: '';
        position: absolute;
        bottom: 0; right: 0;
        width: 10px; height: 10px;
        border-bottom: 1px solid #0f172a;
        border-right: 1px solid #0f172a;
    }
    .stuck-logo--solo-cn-v1 .stuck-logo__text {
        font-family: 'Noto Serif SC', serif;
        font-size: 18px;
        font-weight: 700;
        letter-spacing: 0.15em;
        color: #0f172a;
    }

    /* solo-cn-v2: 深色胶囊 */
    .stuck-logo--solo-cn-v2 {
        gap: 0;
        position: relative;
    }
    .stuck-logo--solo-cn-v2 .stuck-logo__name {
        font-family: 'Noto Serif SC', serif;
        font-size: 14px;
        font-weight: 600;
        color: #fff;
        letter-spacing: 0.15em;
        line-height: 1;
        padding: 7px 18px 6px;
        background: #0f172a;
        border-radius: 20px;
    }

    /* solo-cn-v3: 书法 · 马善政 */
    .stuck-logo--solo-cn-v3 {
        gap: 0;
    }
    .stuck-logo--solo-cn-v3 .stuck-logo__name {
        font-family: 'Ma Shan Zheng', cursive;
        font-size: 22px;
        font-weight: 400;
        color: #1e293b;
        letter-spacing: 0.08em;
        line-height: 1;
    }

    /* solo-en-v1: Typewriter · 角括号（复用 en-v6 风格） */
    .stuck-logo--solo-en-v1 {
        gap: 0;
    }
    .stuck-logo--solo-en-v1 .stuck-logo__bracket {
        position: relative;
        padding: 8px 24px;
    }
    .stuck-logo--solo-en-v1 .stuck-logo__bracket::before {
        content: '';
        position: absolute;
        top: 0; left: 0;
        width: 10px; height: 10px;
        border-top: 1px solid #0f172a;
        border-left: 1px solid #0f172a;
    }
    .stuck-logo--solo-en-v1 .stuck-logo__bracket::after {
        content: '';
        position: absolute;
        bottom: 0; right: 0;
        width: 10px; height: 10px;
        border-bottom: 1px solid #0f172a;
        border-right: 1px solid #0f172a;
    }
    .stuck-logo--solo-en-v1 .stuck-logo__text {
        font-family: 'Playfair Display', serif;
        font-size: 20px;
        letter-spacing: 0.05em;
        color: #0f172a;
        text-transform: uppercase;
    }

    /* solo-en-v2: Signature · 花体手写 */
    .stuck-logo--solo-en-v2 {
        position: relative;
    }
    .stuck-logo--solo-en-v2 .stuck-logo__name {
        font-family: 'Dancing Script', cursive;
        font-size: 24px;
        font-weight: 700;
        color: #1e293b;
        line-height: 1;
    }
    .stuck-logo--solo-en-v2::after {
        content: '';
        position: absolute;
        bottom: -3px;
        left: 10%;
        right: 10%;
        height: 1px;
        background: linear-gradient(90deg, transparent, #94a3b8, transparent);
    }

    /* solo-en-v3: Minimal Serif · 大写衬线+两侧线条 */
    .stuck-logo--solo-en-v3 {
        gap: 13.14px;
    }
    .stuck-logo--solo-en-v3 .stuck-logo__divider {
        width: 28px;
        height: 0.5px;
        background: linear-gradient(90deg, transparent, #94a3b8 40%, #94a3b8 60%, transparent);
        flex-shrink: 0;
    }
    .stuck-logo--solo-en-v3 .stuck-logo__name {
        font-family: 'Crimson Pro', 'Libre Baskerville', Georgia, serif;
        font-size: 16px;
        font-weight: 600;
        color: #0f172a;
        text-transform: uppercase;
        letter-spacing: 0.18em;
        line-height: 1;
    }

    /* --- 兜底默认 --- */
    .stuck-logo--default .stuck-logo__text {
        font-family: 'Inter', sans-serif;
        font-weight: 500;
        font-size: 16px;
        color: #333;
        letter-spacing: 0.03em;
    }

    /* 爱心微呼吸动画 */
    @keyframes stuckHeartPulse {

        0%,
        100% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.15);
        }
    }

    /* 大屏下增加最大边距限制 */
    @media screen and (min-width: 1600px) {
        .withu-header-left-avatar {
            left: 100px;
        }

        .withu-header-actions {
            right: 52px !important;
        }
    }

    /* 右侧工具区域 - 地图 + 情侣头像 */
    .withu-header-actions {
        position: absolute;
        right: 10%;
        top: 50%;
        transform: translateY(-50%) translateX(0) scale(1);
        display: flex;
        align-items: center;
        gap: 13.14px;
        opacity: 1;
        pointer-events: auto;
        z-index: 10;
        /* 只过渡 GPU 可加速属性 */
        transition:
            transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1),
            opacity 0.3s ease;
        will-change: transform, opacity;
    }

    /* === 情侣头像组 - 丝滑交互版 === */
    .withu-couple-avatars-right {
        display: flex;
        align-items: center;
        position: relative;
        cursor: pointer;
    }

    .withu-avatar-group {
        display: flex;
        align-items: center;
        position: relative;
        background: rgba(0, 0, 0, 0.04);
        padding: 4px;
        border-radius: 30px;
        will-change: transform;
        transition: transform 0.5s cubic-bezier(0.23, 1, 0.32, 1),
            background 0.3s ease;
    }

    .withu-avatar-group:hover {
        background: rgba(0, 0, 0, 0.06);
    }

    .withu-avatar-group img {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #fff;
        position: relative;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        will-change: transform;
        transition: transform 0.5s cubic-bezier(0.23, 1, 0.32, 1);
    }

    .withu-avatar-group .avatar-male {
        z-index: 2;
    }

    .withu-avatar-group .avatar-female {
        margin-left: -10px;
        z-index: 1;
    }

    /* 中间连接点 - 简约小圆点 */
    .withu-right-heart {
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
        width: 8px;
        height: 8px;
        background: linear-gradient(135deg, #ff94aa, #ea3a5d);
        border-radius: 50%;
        z-index: 5;
        pointer-events: none;
        box-shadow: 0 0 0 2px #fff, 0 2px 8px rgba(245, 158, 11, 0.4);
        will-change: transform, opacity;
        transition: transform 0.5s cubic-bezier(0.23, 1, 0.32, 1),
            opacity 0.5s cubic-bezier(0.23, 1, 0.32, 1);
    }

    /* === 悬停效果 - 整体放大 === */
    .withu-couple-avatars-right:hover .withu-avatar-group {
        transform: scale(1.08);
    }

    .withu-couple-avatars-right:hover .withu-right-heart {
        transform: translate(-50%, -50%) scale(1.2);
    }


    /* 竖线分隔符 (位于 Avatar 和 图标之间) */
    .withu-header-divider {
        width: 1px;
        height: 18px;
        background: rgba(0, 0, 0, 0.1);
    }
    /* 诗句 - 一行居中 (未滚动显示；吸顶后自动隐藏，让位给天气足迹) */
    .withu-header-poem {
        opacity: 1;
        pointer-events: none;
    }
    .withu-header-poem-line {
        font-size: 15px;
        line-height: 1;
        letter-spacing: 1px;
        color: #000;
        font-family: 'KaiTi', 'STKaiti', 'Kaiti SC', 'Kaiti', serif;
        white-space: nowrap;
        user-select: none;
    }
    .withu-header-actions.withu-actions-visible .withu-header-poem {
        display: none;
    }

    /* 天气足迹 - 吸顶时与 stuck-logo、导航岛一起出现 */
    .withu-header-actions .withu-header-weather:not(.withu-weather-visible),
    .withu-header-actions #withuMapOpenBtn:not(.withu-weather-visible) {
        display: none !important;
        pointer-events: none;
    }
    .withu-header-actions .withu-header-weather.withu-weather-visible,
    .withu-header-actions #withuMapOpenBtn.withu-weather-visible {
        display: inline-flex !important;
        pointer-events: auto;
        animation: withu-weather-in 0.3s ease;
    }
    @keyframes withu-weather-in {
        from { opacity: 0; transform: translateY(4px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* 吸附时显示 - 通过JS添加类控制 - 灵动岛弹出效果 */
    .withu-header-left-avatar.withu-avatar-visible,
    .withu-header-actions.withu-actions-visible {
        opacity: 1;
        transform: translateY(-50%) translateX(0) scale(1);
        pointer-events: auto;
    }

    /* ── 子页面胶囊返回按钮（微信小程序风格） ── */
    .withu-capsule-back {
        position: absolute;
        left: 6%;
        top: 50%;
        transform: translateY(-50%) translateX(-16px);
        z-index: 10;
        display: none;
        align-items: center;
        gap: 2px;
        background: rgba(0, 0, 0, 0.04);
        border: none;
        border-radius: 100px;
        padding: 3px 4px;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s ease, transform 0.3s ease;
    }
    .withu-capsule-back.subpage-back-ready {
        display: flex;
    }
    .withu-capsule-back.scroll-back-visible {
        opacity: 1;
        transform: translateY(-50%) translateX(0);
        pointer-events: auto;
    }
    .withu-capsule-back__btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border-radius: 50%;
        color: #666;
        text-decoration: none;
        transition: all 0.25s ease;
        font-size: 15px;
    }
    .withu-capsule-back__btn:hover {
        background: rgba(255, 255, 255, 0.85);
        color: #333;
        transform: scale(1.08);
    }
    .withu-capsule-back__btn:active {
        transform: scale(0.95);
    }
    .withu-capsule-back__btn svg {
        width: 18px;
        height: 18px;
    }

    /* 吸顶时左侧 logo 被胶囊替换的隐藏动画 */
    .withu-header-left-avatar.withu-avatar-visible.scroll-logo-hidden {
        opacity: 0;
        transform: translateY(-50%) translateX(12px) scale(0.9);
        pointer-events: none;
    }

    /* 大屏下胶囊 left 限制 */
    @media screen and (min-width: 1600px) {
        .withu-capsule-back {
            left: 100px;
        }
    }

    .withu-header-action-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border-radius: 50%;
        color: #666;
        background: rgba(0, 0, 0, 0.04);
        border: none;
        text-decoration: none;
        transition: all 0.25s ease;
        font-size: 15px;
    }

    .withu-header-action-btn:hover {
        background: rgba(0, 0, 0, 0.08);
        color: #333;
        transform: scale(1.08);
    }

    .withu-header-action-btn:active {
        transform: scale(0.95);
    }

    .withu-header-weather {
        position: relative;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        height: 34px;
        padding: 0 12px 0 6px;
        border-radius: 999px;
        color: #334155;
        background: rgba(0, 0, 0, 0.04);
        font-size: 12px;
        line-height: 1;
        white-space: nowrap;
        user-select: none;
        cursor: pointer;
        transition: background 0.24s ease, transform 0.24s ease, box-shadow 0.24s ease;
        outline: none;
        -webkit-tap-highlight-color: transparent;
    }

    .withu-header-weather:hover {
        background: rgba(0, 0, 0, 0.08);
    }

    .withu-header-weather:focus {
        outline: none;
    }

    .withu-header-weather:focus-visible {
        box-shadow:
            0 0 0 2px rgba(59, 130, 246, 0.18),
            0 10px 24px rgba(15, 23, 42, 0.08);
    }

    .withu-header-weather:active {
        transform: scale(0.98);
    }

    .withu-header-weather.is-loading .withu-header-weather-icon,
    .withu-header-weather.is-loading .withu-header-weather-text {
        display: none;
    }

    .withu-header-weather:not(.is-loading) .withu-header-weather-loading {
        display: none;
    }

    .withu-header-weather-icon-wrap {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        border-radius: 999px;
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.82), rgba(255, 255, 255, 0.58));
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.92), 0 4px 10px rgba(15, 23, 42, 0.04);
        flex-shrink: 0;
    }

    .withu-header-weather-icon {
        font-size: 14px;
        color: #5f6672;
    }

    .withu-header-weather-loading {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 18px;
        height: 18px;
        color: #64748b;
    }

    .withu-header-weather-loading svg {
        width: 16px;
        height: 16px;
        animation: withuHeaderWeatherSpin 0.9s linear infinite;
    }

    .withu-header-weather-text {
        display: inline-block;
        max-width: 96px;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: 13px;
        font-weight: 600;
        letter-spacing: -0.02em;
        color: #334155;
    }

    .withu-header-weather-panel {
        position: fixed;
        top: 0;
        left: 0;
        width: 288px;
        padding: 14px;
        border-radius: 26px;
        background:
            linear-gradient(160deg, rgba(255, 255, 255, 0.38), rgba(247, 248, 250, 0.2) 58%, rgba(235, 241, 249, 0.26));
        border: 1px solid rgba(255, 255, 255, 0.48);
        box-shadow:
            0 26px 60px rgba(15, 23, 42, 0.16),
            0 8px 18px rgba(15, 23, 42, 0.08),
            inset 0 1px 0 rgba(255, 255, 255, 0.72);
        backdrop-filter: saturate(180%) blur(34px);
        -webkit-backdrop-filter: saturate(180%) blur(34px);
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px) scale(0.96);
        pointer-events: none;
        filter: blur(10px);
        transform-origin: top right;
        isolation: isolate;
        transition: opacity 0.24s cubic-bezier(0.22, 1, 0.36, 1),
            transform 0.24s cubic-bezier(0.22, 1, 0.36, 1),
            filter 0.24s cubic-bezier(0.22, 1, 0.36, 1),
            visibility 0.24s ease;
        z-index: 10002;
        overflow: hidden;
    }

    .withu-header-weather-panel.is-open {
        opacity: 1;
        visibility: visible;
        transform: translateY(0) scale(1);
        pointer-events: auto;
        filter: blur(0);
    }

    .withu-header-weather-panel::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(circle at top left, rgba(255, 255, 255, 0.46), transparent 30%),
            radial-gradient(circle at 86% 16%, rgba(147, 197, 253, 0.16), transparent 28%),
            radial-gradient(circle at 18% 100%, rgba(251, 191, 153, 0.12), transparent 30%),
            radial-gradient(circle at bottom right, rgba(255, 255, 255, 0.16), transparent 34%);
        pointer-events: none;
        z-index: 0;
    }

    .withu-header-weather-panel::after {
        content: '';
        position: absolute;
        inset: 1px;
        border-radius: 25px;
        background:
            linear-gradient(135deg, rgba(255, 255, 255, 0.16), rgba(255, 255, 255, 0.04)),
            linear-gradient(180deg, rgba(148, 163, 184, 0.08), rgba(255, 255, 255, 0));
        pointer-events: none;
        z-index: 0;
    }

    .withu-header-weather-sheet {
        position: relative;
        z-index: 1;
    }

    .withu-header-weather-hero {
        display: grid;
        grid-template-columns: 42px minmax(0, 1fr) auto;
        align-items: center;
        gap: 12px;
        padding: 2px 2px 14px;
    }

    .withu-header-weather-hero-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 42px;
        height: 42px;
        border-radius: 15px;
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.34), rgba(255, 255, 255, 0.16));
        border: 1px solid rgba(255, 255, 255, 0.34);
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.6),
            0 8px 20px rgba(15, 23, 42, 0.06);
        backdrop-filter: blur(18px) saturate(160%);
        -webkit-backdrop-filter: blur(18px) saturate(160%);
        flex-shrink: 0;
    }

    .withu-header-weather-hero-icon i {
        font-size: 24px;
        background: linear-gradient(180deg, #667085 0%, #344054 100%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        -webkit-text-fill-color: transparent;
        filter: drop-shadow(0 2px 6px rgba(255, 255, 255, 0.2));
    }

    .withu-header-weather-hero-main {
        min-width: 0;
        padding-top: 0;
    }

    .withu-header-weather-hero-title {
        display: block;
        margin-bottom: 4px;
    }

    .withu-header-weather-hero-desc {
        min-width: 0;
        display: block;
        font-size: 15px;
        font-weight: 700;
        color: #111827;
        overflow: hidden;
        text-overflow: ellipsis;
        letter-spacing: -0.02em;
    }

    .withu-header-weather-hero-temp {
        display: block;
        padding-top: 0;
        font-size: 28px;
        line-height: 1;
        font-weight: 700;
        color: #0f172a;
        letter-spacing: -0.05em;
        text-align: right;
        white-space: nowrap;
        align-self: center;
    }

    .withu-header-weather-hero-sub {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 12px;
        color: #6b7280;
        line-height: 1.2;
    }

    .withu-header-weather-hero-sub i {
        font-size: 13px;
        color: #8896ab;
    }

    .withu-header-weather-meta {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
        margin-bottom: 10px;
    }

    .withu-header-weather-meta-item {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
        padding: 10px 12px;
        border-radius: 18px;
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.22), rgba(255, 255, 255, 0.12));
        border: 1px solid rgba(255, 255, 255, 0.34);
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.42),
            0 8px 18px rgba(15, 23, 42, 0.04);
        backdrop-filter: blur(14px) saturate(145%);
        -webkit-backdrop-filter: blur(14px) saturate(145%);
    }

    .withu-header-weather-meta-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 999px;
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.46), rgba(255, 255, 255, 0.2));
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: #475569;
        flex-shrink: 0;
    }

    .withu-header-weather-meta-icon i {
        font-size: 15px;
    }

    .withu-header-weather-meta-copy {
        min-width: 0;
    }

    .withu-header-weather-meta-label {
        display: block;
        margin-bottom: 2px;
        font-size: 10px;
        color: #8b98ab;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }

    .withu-header-weather-meta-value {
        display: block;
        font-size: 12px;
        color: #162033;
        font-weight: 600;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .withu-header-weather-foot {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-top: 4px;
        padding: 10px 4px 2px;
    }

    .withu-header-weather-foot::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 1px;
        background: linear-gradient(90deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.72), rgba(255, 255, 255, 0));
    }

    .withu-header-weather-updated {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        color: rgba(86, 98, 116, 0.88);
        min-width: 0;
    }

    .withu-header-weather-updated i {
        font-size: 13px;
        color: rgba(112, 124, 141, 0.88);
    }

    .withu-header-weather-note {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.02em;
        color: rgba(76, 88, 106, 0.84);
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.22), rgba(255, 255, 255, 0.1));
        border: 1px solid rgba(255, 255, 255, 0.24);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.3);
    }

    .withu-header-weather-note i {
        font-size: 13px;
        color: rgba(104, 116, 134, 0.86);
    }

    @keyframes withuHeaderWeatherSpin {
        from {
            transform: rotate(0deg);
        }
        to {
            transform: rotate(360deg);
        }
    }

    @media (max-width: 980px) {
        .withu-header-weather-text {
            max-width: 78px;
        }

        .withu-header-weather-panel {
            width: 264px;
        }
    }

    code {
        padding: 0.2rem 0.5rem;
        border-radius: 0.4rem;
        font-size: 1rem;
        color: #ff5c28;
        background-color: rgb(255 186 200 / 18%);
        font-family: 'Noto Serif SC', serif;
        margin: 0 4px;
    }

    /* ================================== */
    /* 公共头像组件 .withu-author             */
    /* 用于: 相册、点点滴滴、时间轴等       */
    /* 性别徽章: 父级加 .show-gender 显示  */
    /* ================================== */
    .withu-author {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
    }

    .withu-author__ring {
        padding: 3px;
        background: linear-gradient(135deg, #f4f4f4 0%, #fafafa 100%);
        /* border: 1px solid rgba(0, 0, 0, 0.06); */
        border-radius: 10px;
        flex-shrink: 0;
        display: inline-block;
        line-height: 0;
        position: relative;
        /* box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04); */
    }

    .withu-author__avatar {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        object-fit: cover;
        display: block;
        background-size: cover;
        background-position: center;
    }

    .withu-author__badge {
        position: absolute;
        bottom: -2px;
        right: -2px;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        border: 2px solid white;
        display: none;
        align-items: center;
        justify-content: center;
        font-size: 8px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
        color: white;
    }

    .withu-author__badge.male {
        background: #3b82f6;
    }

    .withu-author__badge.female {
        background: #ec4899;
    }

    .show-gender .withu-author__badge {
        display: flex;
    }

    .withu-author__text {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
    }

    .withu-author__name {
        font-family: 'Dancing Script', 'Noto Serif SC', cursive;
        font-size: 18px;
        font-weight: 700;
        color: #1f2937;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.2;
        letter-spacing: -0.02em;
    }

    .withu-author__meta {
        font-size: 11px;
        opacity: 0.5;
        display: flex;
        align-items: center;
        gap: 4px;
        letter-spacing: 0.03em;
        font-weight: 500;
        min-width: 0;
    }

    .withu-author__meta i {
        font-size: 10px;
    }

    @media (min-width: 640px) {
        .withu-author__badge {
            width: 20px;
            height: 20px;
            font-size: 10px;
        }

        .withu-author__name {
            font-size: 20px;
        }
    }

    .delay-03s {
        -webkit-animation-delay: .3s;
        animation-delay: .3s;
    }

    .Blurkg {
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
        background: transparent !important;
        border: none !important;
    }

    .cpt-loading-mask.column {
        background: transparent !important;
    }

    .view-image-lead img {
        border-radius: 10px !important;
    }

    .view-image-btn {
        border-radius: 8px !important;
    }

    .view-image {
        z-index: 10000 !important;
    }

    img.photo_style,
    img.aiv_touxiang,
    img.lazy {
        opacity: 0;
        -webkit-transition: .8s ease-in-out opacity;
        transition: .8s ease-in-out opacity;
        filter: blur(35px);
        overflow: hidden;
        box-sizing: border-box;
    }

    img.loaded {
        filter: blur(0);
        opacity: 1;
        transition: .7s filter linear, .7s -webkit-filter linear;
    }


    .list {
        position: relative;
        width: 100%;
        height: 100%;
        overflow: hidden;
        padding: 0;
        margin: 0;
    }

    .item {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        z-index: 1;
        transition: opacity 1.5s ease-in-out;
        pointer-events: none;
    }

    .item.active {
        opacity: 1;
        z-index: 2;
        pointer-events: auto;
    }

    .CarouselImage {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        user-select: none;
        -webkit-user-select: none;
        -webkit-user-drag: none;
        -moz-user-select: none;
        -ms-user-select: none;
        pointer-events: none;
    }

    /* 用户 DIY CSS */
    
    /* === V3 导航栏样式 === */
    :root {
        --font-serif: 'Noto Serif SC', serif;
        --font-sans: 'Inter', sans-serif;
        --font-display: 'Playfair Display', serif;
        --ease-elastic: cubic-bezier(0.175, 0.885, 0.32, 1.1);
        --ease-smooth: cubic-bezier(0.25, 0.8, 0.25, 1);
    }

    /* 页面描述区域 */
    .withu-page-header {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        width: 100%;
        user-select: none;
        -webkit-user-select: none;
        padding: 2rem 0 1rem;
    }

    .withu-meta-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
    }

    .withu-meta-tag {
        font-family: var(--font-display);
        font-size: 14px;
        letter-spacing: 0.15em;
        color: #888;
        font-style: italic;
        display: flex;
        align-items: center;
        gap: 12px;
        opacity: 0;
        filter: blur(8px);
        transform: translateY(10px);
        transition: all 0.6s var(--ease-smooth);
    }

    .withu-meta-tag.in {
        opacity: 1;
        filter: blur(0);
        transform: translateY(0);
    }

    .withu-meta-icon {
        font-size: 10px;
        color: #bbb;
    }

    .withu-meta-line {
        width: 44px;
        height: 1px;
        background: linear-gradient(90deg, transparent 0%, rgba(187, 187, 187, 0.75) 50%, transparent 100%);
        opacity: 0;
        transform: scaleX(0) translateY(6px);
        transform-origin: center center;
        filter: blur(4px);
        transition: opacity 0.5s var(--ease-smooth), transform 0.7s var(--ease-smooth), filter 0.5s var(--ease-smooth);
    }

    .withu-meta-line.in {
        opacity: 1;
        transform: scaleX(1) translateY(0);
        filter: blur(0);
    }

    .withu-hero-title {
        font-family: var(--font-serif);
        font-size: clamp(24px, 4vw, 36px);
        font-weight: 600;
        margin: 0;
        text-align: center;
        line-height: 1.4;
        letter-spacing: 0.05em;
        padding: 0 20px;
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 0 4px;
        max-width: 800px;
        cursor: default;
        color: #1a1a1a;
        text-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
    }

    .withu-hero-title .char {
        opacity: 0;
        filter: blur(12px);
        transform: scale(0.95) translateY(10px);
        transition: opacity 0.8s var(--ease-smooth), filter 0.8s var(--ease-smooth), transform 0.8s var(--ease-smooth);
        display: inline-block;
        min-width: 0.2em;
    }

    .withu-hero-title .char.in {
        opacity: 1;
        filter: blur(0);
        transform: scale(1) translateY(0);
    }

    .withu-connector {
        width: 1px;
        height: 40px;
        background: linear-gradient(to bottom, rgba(0, 0, 0, 0), rgba(0, 0, 0, 0.1) 40%, rgba(0, 0, 0, 0));
        margin: 10px 0;
    }

    .withu-sticky-sentinel {
        width: 100%;
        height: 1px;
        pointer-events: none;
        visibility: hidden;
    }

    /* 导航栏容器 */
    .withu-nav-wrapper {
        position: -webkit-sticky;
        position: sticky;
        top: 70px;
        z-index: 100;
        display: flex;
        justify-content: center;
        width: 100%;
        perspective: 1000px;
        pointer-events: none;
        z-index: 9999;
    }

    .withu-nav-wrapper.is-fixed {
        position: fixed;
        top: 14px;
        left: 0;
        right: 0;
    }

    .withu-nav-placeholder {
        display: none;
    }

    .withu-nav-placeholder.is-active {
        display: block;
    }

    /* 导航岛容器 - 灵动岛风格 */
    .withu-nav-island-container {
        pointer-events: auto;
        display: flex;
        align-items: center;
        background: rgba(255, 255, 255, 0.75);
        backdrop-filter: saturate(180%) blur(20px);
        -webkit-backdrop-filter: saturate(180%) blur(20px);
        padding: 8px;
        border-radius: 60px;
        box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.03), 0 2px 8px rgba(0, 0, 0, 0.02);
        transform-origin: center center;
        user-select: none;
        -webkit-user-select: none;
        /* 只过渡 GPU 可加速的属性 + 必要的布局属性缩短时间 */
        transition:
            transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1),
            padding 0.25s ease-out,
            border-radius 0.25s ease-out,
            background 0.25s ease,
            box-shadow 0.25s ease;
        will-change: transform;
    }

    /* 吸顶时的收缩效果 - 灵动岛风格 */
    .withu-nav-island-container.withu-is-stuck {
        padding: 5px;
        background: rgba(248, 248, 248, 0.65);
        border-radius: 50px;
        box-shadow: none;
        transform: scale(0.88);
    }

    /* 吸顶Logo和工具图标 - 默认隐藏 */
    .withu-nav-stuck-logo,
    .withu-nav-stuck-actions {
        display: none;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .withu-nav-island-container.withu-is-stuck .withu-nav-stuck-logo,
    .withu-nav-island-container.withu-is-stuck .withu-nav-stuck-actions {
        display: flex;
        opacity: 1;
    }

    /* 吸顶Logo样式 */
    .withu-nav-stuck-logo {
        align-items: center;
        margin-right: 8px;
        padding-left: 8px;
    }

    .withu-nav-stuck-logo img {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        object-fit: cover;
    }

    /* 工具图标区域 */
    .withu-nav-stuck-actions {
        align-items: center;
        gap: 4px;
        margin-left: 8px;
        padding-right: 4px;
    }

    .withu-nav-stuck-action-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        color: #666;
        background: transparent;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 14px;
    }

    .withu-nav-stuck-action-btn:hover {
        background: rgba(0, 0, 0, 0.05);
        color: #333;
    }

    .withu-nav-stuck-divider {
        width: 1px;
        height: 20px;
        background: rgba(0, 0, 0, 0.1);
        margin: 0 4px;
    }

    .withu-nav-island-container.withu-is-stuck .withu-nav-island-item {
        padding: 10px 18px;
        font-size: 16px;
        gap: 6px;
    }

    .withu-nav-island-container.withu-is-stuck .withu-nav-island-item i {
        font-size: 13px;
    }

    .withu-nav-island-container.withu-is-stuck .withu-nav-island-item.nav-home {
        padding: 8px 24px;
    }

    .withu-nav-island-container.withu-is-stuck .withu-nav-island-item.nav-home i {
        font-size: 15px;
    }

    /* 导航项 - 灵动岛风格过渡 */
    .withu-nav-island-item {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        text-decoration: none;
        color: #6f6f6f;
        border-radius: 32px;
        white-space: nowrap;
        font-size: 14px;
        font-weight: 300;
        /* font-family: var(--font-serif); */
        -webkit-user-drag: none;
        user-select: none;
        -webkit-user-select: none;
        cursor: pointer;
        /* 缩短过渡时间，减少布局属性过渡 */
        transition:
            padding 0.25s ease-out,
            font-size 0.25s ease-out,
            gap 0.25s ease-out,
            color 0.2s ease,
            background-color 0.2s ease;
    }

    .withu-nav-island-item i {
        transition: font-size 0.25s ease-out;
        font-size: 16px !important;
    }

    .withu-nav-wrapper.is-fixed .withu-nav-island-item {
        color: #000000;
    }

    .withu-nav-island-item:not(.active):hover {
        background-color: rgb(248 114 113 / 9%);
        color: #f87171;
    }

    .withu-nav-wrapper.is-fixed .withu-nav-island-item:not(.active):hover {
        background-color: rgb(255 255 255);
        color: #6f6f6f;
    }

    /* 首页 Tab */
    .withu-nav-island-item.nav-home {
        padding: 10px 32px;
        margin: 0 4px;
        color: #1d1d1f;
        opacity: 0.8;
    }

    .withu-nav-island-item.nav-home.active {
        opacity: 1;
        color: #fff;
    }

    .withu-nav-island-item.nav-home i {
        font-size: 18px;
        transition: color 0.2s ease;
    }

    .withu-nav-island-item.nav-home:hover {
        opacity: 1;
        background-color: rgba(0, 0, 0, 0.04);
    }

    .withu-nav-island-item.nav-home::before,
    .withu-nav-island-item.nav-home::after {
        content: '';
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 1px;
        height: 12px;
        background-color: rgba(0, 0, 0, 0.1);
        transition: opacity 0.2s;
    }

    .withu-nav-island-item.nav-home::before {
        left: 0;
    }

    .withu-nav-island-item.nav-home::after {
        right: 0;
    }

    .withu-nav-island-item.nav-home:hover::before,
    .withu-nav-island-item.nav-home:hover::after,
    .withu-nav-island-item.nav-home.active::before,
    .withu-nav-island-item.nav-home.active::after,
    .withu-nav-island-container.withu-is-stuck .withu-nav-island-item.nav-home::before,
    .withu-nav-island-container.withu-is-stuck .withu-nav-island-item.nav-home::after {
        opacity: 0;
    }

    .withu-nav-wrapper.is-fixed .withu-nav-island-item.active,
    .withu-nav-wrapper .withu-nav-island-item.active {
        color: #fff;
        font-weight: 600;
    }


    .withu-nav-indicator {
        position: absolute;
        top: 8px;
        left: 0;
        height: calc(100% - 16px);
        /* background: #1d1d1f; */
        background: #f87171;
        border-radius: 100px;
        z-index: 0;
        transition: all 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.1);
        /* box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); */
    }

    .withu-nav-indicator::before {
        content: "";
        display: block;
        background: inherit;
        filter: blur(0.5rem);
        position: absolute;
        width: 100%;
        height: 100%;
        top: 2px;
        left: 2px;
        z-index: -1;
        opacity: 0.5;
        transform-origin: 0 0;
        border-radius: inherit;
        transform: scale(1, 1);
    }

    .withu-nav-indicator.no-transition {
        transition: none !important;
    }

    .withu-nav-island-container.withu-is-stuck .withu-nav-indicator {
        top: 5px;
        height: calc(100% - 10px);
        /* box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08); */
    }

    @media (max-width: 768px) {
        .withu-nav-island-container {
            max-width: 90vw;
            overflow-x: auto;
            justify-content: flex-start;
            scrollbar-width: none;
            -ms-overflow-style: none;
            padding: 4px;
        }

        .withu-nav-island-container::-webkit-scrollbar {
            display: none;
        }

        .withu-nav-island-item {
            padding: 8px 16px;
            flex-shrink: 0;
        }

        .withu-hero-title {
            font-size: clamp(20px, 5vw, 28px);
        }
    }

    /* === PJAX 内容区域 Loading 效果 === */

    /* Loading 时内容隐藏 */
    #pjax-container.pjax-loading {
        position: relative;
        min-height: 400px;
    }

    #pjax-container.pjax-loading>* {
        opacity: 0 !important;
        visibility: hidden;
        transition: opacity 0.2s ease;
    }

    /* ===== 固定点位追逐加载效果 ===== */
    .pjax-loader-overlay {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 9999;
        pointer-events: none;
    }

    .pjax-loader-content {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 40px;
    }

    .pjax-loader-grid {
        width: 100px;
        height: 100px;
        position: relative;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .pjax-dot {
        position: absolute;
        width: 12px;
        height: 12px;
        background-color: #e5e5e5;
        border-radius: 50%;
        transform: rotate(var(--r)) translate(42px);
        animation: pjax-pulse-chase 1.2s infinite ease-in-out;
        animation-delay: calc(-1.2s * var(--i) / 12);
    }

    @keyframes pjax-pulse-chase {

        0%,
        100% {
            background-color: #e5e5e5;
            transform: rotate(var(--r)) translate(42px) scale(1);
        }

        30% {
            background-color: #000000;
            transform: rotate(var(--r)) translate(42px) scale(1.4);
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
    }

    .pjax-loader-label {
        color: #999;
        font-size: 13px;
        letter-spacing: 3px;
        text-transform: uppercase;
        font-weight: 500;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }

    /* 移动端适配 */
    @media (max-width: 768px) {
        .pjax-loader-grid {
            width: 80px;
            height: 80px;
        }

        .pjax-dot {
            width: 10px;
            height: 10px;
            transform: rotate(var(--r)) translate(34px);
        }

        @keyframes pjax-pulse-chase {

            0%,
            100% {
                background-color: #e5e5e5;
                transform: rotate(var(--r)) translate(34px) scale(1);
            }

            30% {
                background-color: #000000;
                transform: rotate(var(--r)) translate(34px) scale(1.4);
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            }
        }

        .pjax-loader-label {
            font-size: 12px;
        }

        .pjax-loader-content {
            gap: 32px;
        }
    }
</style>

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
                <img class="lazy CarouselImage" data-src="/Lovefolder/20260408044247_69d56c47870ec497937320.webp" draggable="false">
            </li>
                    <li class="item">
                <img class="lazy CarouselImage" data-src="/Lovefolder/20260408044246_69d56c468eddf735445232.webp" draggable="false">
            </li>
                    <li class="item">
                <img class="lazy CarouselImage" data-src="/Lovefolder/20260408044242_69d56c4212ab5344890628.webp" draggable="false">
            </li>
                    <li class="item">
                <img class="lazy CarouselImage" data-src="/Lovefolder/20260408044237_69d56c3dcde96349173286.webp" draggable="false">
            </li>
                    <li class="item">
                <img class="lazy CarouselImage" data-src="/Lovefolder/20260408044237_69d56c3d97f46162328378.webp" draggable="false">
            </li>
                    <li class="item">
                <img class="lazy CarouselImage" data-src="/Lovefolder/20260408044229_69d56c35d59a9841528398.webp" draggable="false">
            </li>
                    <li class="item">
                <img class="lazy CarouselImage" data-src="/Lovefolder/20260408044228_69d56c34b1c8f984679558.webp" draggable="false">
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
    <title>相册 — withU Demo</title>
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
    <script>
    $(function() {
        if (typeof GeetestHelper !== 'undefined') {
            var siteTitle = (window.WITHU_CONFIG && window.WITHU_CONFIG.title) || '';

            GeetestHelper.init({
                toast: {
                    success: function(msg) { Toastify.showScenario('success', { text: msg }); },
                    error: function(msg) { Toastify.showScenario('error', { text: msg }); },
                    warning: function(msg) { Toastify.showScenario('warning', { text: msg }); }
                },
                onClose: function() {
                    $('#leavingPost').removeAttr('disabled').text('提交留言');
                },
                onSuccess: function(result) {
                    if (typeof submitMessage === 'function') {
                        submitMessage(result);
                    }
                }
            });

            $('#leavingPost').off('click.withuGeetest').on('click.withuGeetest', function() {
                var qq = $("input[name='qq']").val();
                var name = $("input[name='name']").val();
                var text = $("textarea[name='text']").val();

                if (!qq || !name || !text) {
                    Toastify.showScenario('warning', { text: '留言提交失败 表单输入不完整！' });
                    return false;
                }

                if (typeof containsBannedChar === 'function' && containsBannedChar((name || '') + ' ' + (text || ''))) {
                    Toastify.showScenario('warning', { text: '留言包含违禁内容，请修改后重试' });
                    return false;
                }

                $('#leavingPost').text('请完成验证...').attr('disabled', 'disabled');
                GeetestHelper.show();
            });
        } else {
            $('#leavingPost').off('click.withuGeetest').on('click.withuGeetest', function() {
                var qq = $("input[name='qq']").val();
                var name = $("input[name='name']").val();
                var text = $("textarea[name='text']").val();

                if (!qq || !name || !text) {
                    Toastify.showScenario('warning', { text: '留言提交失败 表单输入不完整！' });
                    return false;
                }

                if (typeof containsBannedChar === 'function' && containsBannedChar((name || '') + ' ' + (text || ''))) {
                    Toastify.showScenario('warning', { text: '留言包含违禁内容，请修改后重试' });
                    return false;
                }

                if (typeof submitMessage === 'function') {
                    submitMessage({});
                }
            });
        }
    });
    </script>


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
<script>
/* 图片查看器增强：鼠标滚轮缩放（仅作用于 ViewImage 弹层，不影响页面其它区域） */
(function () {
    if (window.WithuViewImageWheelZoom) return;
    window.WithuViewImageWheelZoom = true;

    var MIN_SCALE = 0.5, MAX_SCALE = 8;

    function activeImg() {
        var viewer = document.querySelector('.view-image');
        return viewer ? viewer.querySelector('.view-image-lead img') : null;
    }

    function apply(img) {
        img.style.transform = 'translate(' + (img._vx || 0) + 'px,' + (img._vy || 0) + 'px) scale(' + img._vs + ')';
    }

    document.addEventListener('wheel', function (e) {
        var img = activeImg();
        if (!img) return;
        e.preventDefault();
        if (img._vs === undefined) { img._vs = 1; img._vx = 0; img._vy = 0; }

        var delta = e.deltaY * (e.deltaMode === 1 ? 16 : (e.deltaMode === 2 ? 100 : 1));
        var next = Math.min(MAX_SCALE, Math.max(MIN_SCALE, img._vs * Math.exp(-delta * 0.0022)));
        if (next === img._vs) return;

        img._vs = next;
        if (next <= 1) { img._vx = 0; img._vy = 0; }
        img.style.transition = 'transform .12s ease-out';
        img.style.cursor = next > 1 ? 'grab' : '';
        apply(img);
    }, { passive: false });

    /* 放大后可按住图片拖动查看局部；图片本身点击无动作，不会误触关闭 */
    document.addEventListener('pointerdown', function (e) {
        var img = activeImg();
        if (!img || e.target !== img || (img._vs || 1) <= 1) return;
        e.preventDefault();
        var ox = e.clientX - (img._vx || 0), oy = e.clientY - (img._vy || 0);
        img.style.transition = 'none';
        img.style.cursor = 'grabbing';

        function onMove(ev) {
            img._vx = ev.clientX - ox;
            img._vy = ev.clientY - oy;
            apply(img);
        }
        function onUp() {
            img.style.cursor = 'grab';
            window.removeEventListener('pointermove', onMove);
            window.removeEventListener('pointerup', onUp);
        }
        window.addEventListener('pointermove', onMove);
        window.addEventListener('pointerup', onUp);
    });
})();
</script>
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

    <script>
        // 滚动按钮和回到顶部功能已迁移到 components.js 的 ScrollButtons 模块
        // 以下代码由 WithUApp.init() 统一初始化，保留最小必要代码

        $(document).ready(function() {
            $('body').addClass('loaded');

            // 初始化 WithUApp 核心框架
            if (window.WithUApp && typeof window.WithUApp.init === 'function') {
                window.WithUApp.setConfig(window.WITHU_CONFIG || {});
                window.WithUApp.init();
            }

            // 初始化组件（礼花、轮播、导航等）
            if (window.WithUApp && window.WithUApp.Components) {
                const {
                    ConfettiEffect,
                    Carousel,
                    AvatarInteraction,
                    Navigation,
                    ScrollButtons,
                    HeaderVisitorWeather
                } = window.WithUApp.Components;

                // 初始化礼花效果
                if (ConfettiEffect) {
                    ConfettiEffect.init();
                    // 页面加载完成后延迟触发如影随形效果
                    setTimeout(() => {
                        ConfettiEffect.loveWingEffect();
                    }, 800);
                }

                // 初始化轮播图
                if (Carousel) Carousel.init();

                // 初始化头像交互
                if (AvatarInteraction) AvatarInteraction.init();

                // 初始化导航栏
                if (Navigation) Navigation.init();

                // 初始化滚动按钮
                if (ScrollButtons) ScrollButtons.init();

                // 初始化吸顶栏访客天气
                if (HeaderVisitorWeather) HeaderVisitorWeather.init();
            }

            // GetEm 函数调用（如果存在）
            if (typeof GetEm === 'function') GetEm();
        });
    </script>

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
            cursor: url(/Style/cur/hover.cur), pointer;
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
            background: url(/Style/img/animalBg.jpg) repeat center / auto 100%;
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


<script>
    (function () {
        var requestId = "847020a5cb325b9d086bdda2ce8a657c";
        var token = "eaeca9484fc82115b6ba9198ae9d3e96a9f4ea1ca366bc217d979ab4a47068d8";
        window.WITHU_CONFIG = Object.assign(window.WITHU_CONFIG || {}, {
            endpoints: Object.assign({}, (window.WITHU_CONFIG && window.WITHU_CONFIG.endpoints) || {}, {
                accessBeacon: "/services/access-beacon.php"            })
        });

        var endpoint = (window.WITHU_CONFIG && window.WITHU_CONFIG.endpoints && window.WITHU_CONFIG.endpoints.accessBeacon) || '';
        if (!endpoint || !navigator.sendBeacon) {
            return;
        }

        var current = {
            requestId: '',
            token: ''
        };
        var startAt = Date.now();
        var reported = false;

        function isValidHex(value, len) {
            return new RegExp('^[a-f0-9]{' + len + '}$').test(String(value || ''));
        }

        function setContext(nextRequestId, nextToken) {
            var rid = String(nextRequestId || '').trim().toLowerCase();
            var t = String(nextToken || '').trim().toLowerCase();
            if (!isValidHex(rid, 32) || !isValidHex(t, 64)) {
                return false;
            }
            current.requestId = rid;
            current.token = t;
            startAt = Date.now();
            reported = false;
            return true;
        }

        function reportStay() {
            if (reported) {
                return;
            }
            if (!isValidHex(current.requestId, 32) || !isValidHex(current.token, 64)) {
                return;
            }
            reported = true;

            var staySeconds = Math.max(0, Math.round((Date.now() - startAt) / 1000));
            if (staySeconds > 86400) {
                staySeconds = 86400;
            }

            var formData = new FormData();
            formData.append('request_id', current.requestId);
            formData.append('stay_seconds', String(staySeconds));
            formData.append('token', current.token);
            navigator.sendBeacon(endpoint, formData);
        }

        setContext(requestId, token);

        window.addEventListener('pagehide', reportStay, { once: true });
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'hidden') {
                reportStay();
            }
        }, { passive: true });

        if (window.jQuery && typeof window.jQuery.fn === 'object') {
            window.jQuery(document).on('pjax:send', function () {
                reportStay();
            });
            window.jQuery(document).on('pjax:complete', function (event, xhr) {
                if (!xhr || typeof xhr.getResponseHeader !== 'function') {
                    return;
                }
                var nextRequestId = xhr.getResponseHeader('X-WithU-Access-Request-Id') || '';
                var nextToken = xhr.getResponseHeader('X-WithU-Access-Beacon-Token') || '';
                setContext(nextRequestId, nextToken);
            });
        }
    })();
</script>


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
