<?php
// withU 动态首页 - PHP 版
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// 默认静态配置（数据库不可用时兜底）
$withuConfigJson = '{"title":"withU","boy":"Ki.","girl":"Really","startTime":"2023-07-19 00:00:00","version":"2.2.5","pcCarouselHeight":"80vh","mobileCarouselHeight":"50vh","pcPhotoCoverHeight":"80vh","mobilePhotoCoverHeight":"60vh","pcImgMaxHeight":"450px","mobileImgMaxHeight":"260px","maleName":"Ki.","maleAvatar":"Lovefolder/20260411043037_69d95ded97293201118237.webp","femaleName":"Really","femaleAvatar":"Lovefolder/20260411043046_69d95df639c33274072975.webp","siteBase":"","assetBase":"","imageErrorFallback":"Style/img/file-placeholder.svg","owoBase":"OwO","soloMode":false,"weatherEnabled":true,"weatherToken":"d4210665334edba618aecc1829a5e734701e2b824c5aebd4ff8859d7a2536721","soloOwnerGeo":{"lat":21.915454,"lng":110.856708},"bannedChars":"操屌","endpoints":{"mapApi":"assets/map-api.php","weatherNow":"services/weather.php","interaction":"services/interaction.php","accessBeacon":"services/access-beacon.php","messageList":"services/message-list.php","messageSubmit":"services/message.php","infoService":"services/info-service.php","weatherApi":"services/weather.php"}}';

$loggedIn = false;
try {
    $rootPath = dirname(__DIR__) . '/backend/app';
    if (!is_file($rootPath . '/config/database.php') || !is_file($rootPath . '/.installed')) {
        throw new RuntimeException('not installed');
    }
    require_once $rootPath . '/config/config.php';
    require_once $rootPath . '/core/Database.php';
    require_once $rootPath . '/core/Auth.php';
    $db = Database::getInstance();
    $loggedIn = (new Auth())->isLoggedIn();

    $users = $db->fetchAll("SELECT * FROM users WHERE status='active' ORDER BY FIELD(role,'user1','user2')");
    $user1 = null; $user2 = null;
    foreach ($users as $u) {
        $role = $u['role'] ?? '';
        if ($role === 'user1' && !$user1) $user1 = $u;
        if ($role === 'user2' && !$user2) $user2 = $u;
    }

    $boyName = $user1['nickname'] ?? '\u4ed6';
    $girlName = $user2['nickname'] ?? '\u5979';
    $boyAvatar = $user1['avatar'] ?? '/assets/images/default-avatar.svg';
    $girlAvatar = $user2['avatar'] ?? '/assets/images/default-avatar.svg';

    $loveDateRow = $db->fetch("SELECT value FROM settings WHERE `key`='love_date'");
    $startTime = ($loveDateRow && !empty($loveDateRow['value'])) ? $loveDateRow['value'] . ' 00:00:00' : '2023-07-19 00:00:00';

    $withuConfig = [
        'title' => 'withU',
        'boy' => $boyName, 'girl' => $girlName,
        'startTime' => $startTime,
        'version' => '2.2.5',
        'pcCarouselHeight' => '80vh', 'mobileCarouselHeight' => '50vh',
        'pcPhotoCoverHeight' => '80vh', 'mobilePhotoCoverHeight' => '60vh',
        'pcImgMaxHeight' => '450px', 'mobileImgMaxHeight' => '260px',
        'maleName' => $boyName, 'maleAvatar' => $boyAvatar,
        'femaleName' => $girlName, 'femaleAvatar' => $girlAvatar,
        'siteBase' => '', 'assetBase' => '',
        'imageErrorFallback' => 'Style/img/file-placeholder.svg',
        'owoBase' => 'OwO', 'soloMode' => false,
        'weatherEnabled' => true,
        'weatherToken' => 'd4210665334edba618aecc1829a5e734701e2b824c5aebd4ff8859d7a2536721',
        'soloOwnerGeo' => ['lat' => 21.915454, 'lng' => 110.856708],
        'bannedChars' => '\u64cd\u5c4c',
        'endpoints' => [
            'mapApi' => 'assets/map-api.php', 'weatherNow' => 'services/weather.php',
            'interaction' => 'services/interaction.php', 'accessBeacon' => 'services/access-beacon.php',
            'messageList' => 'services/message-list.php', 'messageSubmit' => 'services/message.php',
            'infoService' => 'services/info-service.php', 'weatherApi' => 'services/weather.php',
        ],
    ];
    $withuConfigJson = json_encode($withuConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    // 使用默认静态配置
}
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
<meta property="og:site_name" content="withU">
<meta property="og:title" content="withU">
<meta property="og:description" content="withU 是一个适合记录恋爱日常与纪念时刻的情侣小站，支持相册、时间轴、点滴文章、留言互动和邀请页面，让每一段关系都能拥有自己的专属回忆空间。">
<meta property="og:url" content="/index.php">
<meta property="og:image" content="withU">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="withU">
<meta name="twitter:description" content="withU 是一个适合记录恋爱日常与纪念时刻的情侣小站，支持相册、时间轴、点滴文章、留言互动和邀请页面，让每一段关系都能拥有自己的专属回忆空间。">
<meta name="twitter:image" content="withU">

    <!-- Google Fonts CDN 版本 -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
        <script>
        window._AMapSecurityConfig = {"securityJsCode":"d4fe1ef6bb455368bc92d5fb577b2f3b"};
        window.WITHU_MAP_CONFIG = {"amapKey":"7d245650b5ba899ce4f025961613dcc5","modeConfig":{"lovers":{"title":"情侣模式","desc":"无论相隔多远，心始终在一起"},"moments":{"title":"点点滴滴","desc":"记录我们的每一个美好瞬间"},"messages":{"title":"留言模式","desc":"来自世界各地的温暖祝福"},"albums":{"title":"相册模式","desc":"用照片定格我们的回忆"},"events":{"title":"事件清单","desc":"一起完成的每一个小目标"}},"lovers":[],"milestones":[],"events":[],"albums":[],"messages":[],"moments":[],"loveStartDate":"","hsla":"345deg,70%,55%","mapStyle":"amap://styles/grey","soloMode":false,"_apiBase":"assets/map-api.php"};
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
<script src="assets/js/app.js"></script>
<script src="assets/js/components.js"></script>

<!-- 礼花效果已迁移到 components.js 的 ConfettiEffect 模块 -->

<script src="assets/js/pjax.js"></script><script>if(window.WithUPjax&typeof window.WithUPjax.init==="function")window.WithUPjax.init();</script>
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
        gap: 14px;
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
            right: 67px !important;
        }
    }

    /* 右侧工具区域 - 地图 + 情侣头像 */
    .withu-header-actions {
        position: absolute;
        right: 6%;
        top: 50%;
        transform: translateY(-50%) translateX(0) scale(1);
        display: flex;
        align-items: center;
        gap: 24px;
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

<div class="header-wrap">
    <div class="header">
        <!-- 吸顶 Logo（模板渲染：根据 $stuckLogoStyle 只输出选中的一套） -->
                                <div class="logo" style="margin-right:auto;margin-left:3%">
                        <h1><a class="alogo" href="index.php" title="withU" style="display:inline-flex;align-items:center;gap:4px"><img src="assets/images/withu-logo.png" alt="withU" style="height:2.2rem;width:auto;object-fit:contain;vertical-align:middle"></a></h1>
        </div>
        <div class="withu-capsule-back">
            <a href="javascript:void(0);" class="withu-capsule-back__btn withu-capsule-back__prev" title="返回">
                <i data-lucide="chevron-left"></i>
            </a>
            <a href="index.php" class="withu-capsule-back__btn withu-capsule-back__home" title="首页">
                <i data-lucide="house"></i>
            </a>
        </div>

        <!-- 吸顶时显示的右侧区域: 地图 + 情侣头像 -->
        <div class="withu-header-actions" id="withuHeaderActions">
                

            <div class="withu-header-poem" id="withuHeaderPoem" aria-hidden="true">
                        <span class="withu-header-poem-line">树是梧桐树，城是南京城，一句梧桐美，种满南京城</span>
                    </div>

<div class="withu-header-weather is-loading" id="withuHeaderVisitorWeather" title="点击查看当前天气信息" role="button" tabindex="0" aria-expanded="false">
                <span class="withu-header-weather-loading" id="withuHeaderVisitorWeatherLoading" aria-label="天气加载中">
                    <i data-lucide="loader-circle"></i>
                </span>
                <span class="withu-header-weather-icon-wrap">
                    <i class="qi-999-fill withu-header-weather-icon" id="withuHeaderVisitorWeatherIcon"></i>
                </span>
                <span class="withu-header-weather-text" id="withuHeaderVisitorWeatherText"></span>
            </div>

<a href="javascript:void(0);" class="withu-header-map" id="withuMapOpenBtn" title="足迹地图">
                <span class="withu-header-map-icon-wrap">
                    <i class="ph-fill ph-globe-hemisphere-west"></i>
                </span>
                <span class="withu-header-map-text">足迹</span>
            </a>

<div class="withu-header-divider"></div>
            
            <div class="withu-couple-avatars-right">
                <div class="withu-avatar-group">
                    <img src="Lovefolder/20260411043046_69d95df639c33274072975.webp"
                        class="avatar-male" alt="She">
                                        <img src="Lovefolder/20260411043037_69d95ded97293201118237.webp"
                        class="avatar-female" alt="He">
                                    </div>
                                <span class="withu-right-heart"></span>
                            </div>

                <?php if ($loggedIn): ?>
                <a href="/watch.php" class="withu-header-map" data-entry="media" title="观影">
                    <span class="withu-header-map-icon-wrap">
                        <i class="ph-fill ph-film-slate"></i>
                    </span>
                    <span class="withu-header-map-text">观影</span>
                </a>
                <a href="/admin/" class="withu-header-map" data-entry="admin" title="后台">
                    <span class="withu-header-map-icon-wrap">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    </span>
                    <span class="withu-header-map-text">后台</span>
                </a>
                <?php else: ?>
                <a href="/login.php" class="withu-header-map" data-entry="login" title="登录">
                    <span class="withu-header-map-icon-wrap">
                        <i class="ph-fill ph-user"></i>
                    </span>
                    <span class="withu-header-map-text">登录</span>
                </a>
                <?php endif; ?>
                

            <!-- 移动端更多按钮 -->
            <button type="button" class="withu-header-more-btn" id="withuHeaderMoreBtn" aria-label="更多信息">
                <i data-lucide="ellipsis"></i>
            </button>
        </div>
    </div>
<div class="withu-header-left-avatar">
                                <div class="stuck-logo stuck-logo--en-v7">
                        <span class="stuck-logo__name" data-withu-tip="Ki.">Ki.</span>
                        <span class="stuck-logo__redline-l"></span>
                        <span class="stuck-logo__heart"><svg width="20" height="20" viewBox="0 0 256 256" fill="currentColor">
                                <path
                                    d="M240,94c0,70-103.79,126.66-108.21,129a8,8,0,0,1-7.58,0C119.79,220.66,16,164,16,94A62.07,62.07,0,0,1,78,32c20.65,0,38.73,8.88,50,23.89C139.27,40.88,157.35,32,178,32A62.07,62.07,0,0,1,240,94Z" />
                            </svg></span>
                        <span class="stuck-logo__redline-r"></span>
                        <span class="stuck-logo__name" data-withu-tip="Really">Really</span>
                    </div>
                            </div>

        
</div>

<!-- 移动端更多面板（毛玻璃磨砂效果） -->
<div class="withu-header-more-panel" id="withuHeaderMorePanel">
    <div class="withu-header-more-overlay" data-close-panel></div>
    <div class="withu-header-more-sheet">
        <button type="button" class="withu-header-more-close" data-close-panel aria-label="关闭">
            <i data-lucide="x"></i>
        </button>

        <!-- stuck-logo 展示 -->
        <div class="withu-header-more-identity">
                                <div class="stuck-logo stuck-logo--en-v7">
                        <span class="stuck-logo__name" data-withu-tip="Ki.">Ki.</span>
                        <span class="stuck-logo__redline-l"></span>
                        <span class="stuck-logo__heart"><svg width="20" height="20" viewBox="0 0 256 256" fill="currentColor"><path d="M240,94c0,70-103.79,126.66-108.21,129a8,8,0,0,1-7.58,0C119.79,220.66,16,164,16,94A62.07,62.07,0,0,1,78,32c20.65,0,38.73,8.88,50,23.89C139.27,40.88,157.35,32,178,32A62.07,62.07,0,0,1,240,94Z"/></svg></span>
                        <span class="stuck-logo__redline-r"></span>
                        <span class="stuck-logo__name" data-withu-tip="Really">Really</span>
                    </div>
                        </div>

        <!-- 功能入口 -->
        <div class="withu-header-more-actions">
            <a href="javascript:void(0);" class="withu-header-more-action-item" id="withuMorePanelWeather" data-close-panel>
                <span class="withu-header-more-action-icon">
                    <i class="qi-999-fill" id="withuMorePanelWeatherIcon"></i>
                </span>
                <span class="withu-header-more-action-label" id="withuMorePanelWeatherText">天气</span>
            </a>

            <a href="javascript:void(0);" class="withu-header-more-action-item" id="withuMorePanelMap" data-close-panel>
                <span class="withu-header-more-action-icon">
                    <i class="ph-fill ph-globe-hemisphere-west"></i>
                </span>
                <span class="withu-header-more-action-label">足迹地图</span>
            </a>

                        <?php if ($loggedIn): ?>
                        <a href="/watch.php" class="withu-header-more-action-item">
                <span class="withu-header-more-action-icon">
                    <i class="ph-fill ph-film-slate"></i>
                </span>
                <span class="withu-header-more-action-label">观影</span>
            </a>
                <a href="/admin/" class="withu-header-more-action-item">
                <span class="withu-header-more-action-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                </span>
                <span class="withu-header-more-action-label">后台</span>
            </a>
                <?php else: ?>
                        <a href="/login.php" class="withu-header-more-action-item">
                <span class="withu-header-more-action-icon">
                    <i class="ph-fill ph-user"></i>
                </span>
                <span class="withu-header-more-action-label">登录</span>
            </a>
                <?php endif; ?>

                        

</div>
    </div>
</div>

<div id="homePage" class="wrap" data-Fullscreen>
    <ul class="list mask_black">
                    <li class="item active">
                <img class="lazy CarouselImage" data-src="Lovefolder/20260408044247_69d56c47870ec497937320.webp" draggable="false">
            </li>
                    <li class="item">
                <img class="lazy CarouselImage" data-src="Lovefolder/20260408044246_69d56c468eddf735445232.webp" draggable="false">
            </li>
                    <li class="item">
                <img class="lazy CarouselImage" data-src="Lovefolder/20260408044242_69d56c4212ab5344890628.webp" draggable="false">
            </li>
                    <li class="item">
                <img class="lazy CarouselImage" data-src="Lovefolder/20260408044237_69d56c3dcde96349173286.webp" draggable="false">
            </li>
                    <li class="item">
                <img class="lazy CarouselImage" data-src="Lovefolder/20260408044237_69d56c3d97f46162328378.webp" draggable="false">
            </li>
                    <li class="item">
                <img class="lazy CarouselImage" data-src="Lovefolder/20260408044229_69d56c35d59a9841528398.webp" draggable="false">
            </li>
                    <li class="item">
                <img class="lazy CarouselImage" data-src="Lovefolder/20260408044228_69d56c34b1c8f984679558.webp" draggable="false">
            </li>
                    <li class="item">
                <img class="lazy CarouselImage" data-src="Lovefolder/20260408044228_69d56c34421f3439264035.webp" draggable="false">
            </li>
            </ul>

    <div class="bg-wrap central limg" data-avatar-swap="1">
        <div class="bg-img">
            <div class="middle Blurkg">
                <div class="img-male">
                    <div class="avatarArea withu-head-avatar-boy">
                        <img draggable="false" class="avatarFrame lazy" data-src= 'https://s1.locimg.com/2024/10/18/db01c99842e69.png' style='transform: scale(1.6);top: 2px;left: 2px;' >
                        <img draggable="false" class="aiv_touxiang" data-src="Lovefolder/20260411043037_69d95ded97293201118237.webp">
                                                <div class="withu-head-avatar-mask">
                            <div class="withu-head-avatar-top withu-head-avatar-anim-item">
                                                                <div class="withu-head-avatar-gender-icon" data-gender="male"><i data-lucide="mars"></i></div>
                                                            </div>
                            <div class="withu-head-avatar-middle withu-head-avatar-anim-item">
                                <div
                                    class="withu-head-avatar-status-text withu-head-avatar-status-away">
                                                                            <i data-lucide="clock" class="withu-head-avatar-icon-away"></i>
                                                                        <em>2小时前</em>
                                </div>
                                <div class="withu-head-avatar-divider"></div>
                            </div>
                            <div class="withu-head-avatar-bottom withu-head-avatar-anim-item">
                                <div class="withu-head-avatar-location">
                                    <i data-lucide="map-pin"></i>
                                    <em>潘州公园</em>
                                </div>
                            </div>
                        </div>
                                            </div>
                    <span class="shadow-blur">Ki.</span>
                </div>
                <div class="love-icon">
                    <div class="love-info-wrapper">
                        <div class="distance-bubble" onclick="if(window.WithUMap)WithUMap.open({mode:'lovers'})"
                                                        style="cursor:pointer">
                            <div class="distance-icon-box">
                                <i class="ph-fill ph-navigation-arrow"></i>
                            </div>
                            <div class="distance-text">
                                                                    <span class="distance-text-sm">相距</span>
                                    <span class="km-value">546.9</span>
                                    <span class="distance-text-sm">km</span>
                                                            </div>
                        </div>
                    </div>
                    <img draggable="false" src="Style/img/like.svg">
                </div>
                                <div class="img-female">
                    <div class="avatarArea withu-head-avatar-girl">
                        <img draggable="false" class="avatarFrame lazy" data-src= 'https://s1.locimg.com/2024/10/18/db01c99842e69.png' style='transform: scale(1.6);top: 2px;left: 2px;' >
                        <img draggable="false" class="aiv_touxiang" data-src="Lovefolder/20260411043046_69d95df639c33274072975.webp">
                                                <div class="withu-head-avatar-mask">
                            <div class="withu-head-avatar-top withu-head-avatar-anim-item">
                                                                <div class="withu-head-avatar-gender-icon" data-gender="female"><i data-lucide="venus"></i></div>
                                                            </div>
                            <div class="withu-head-avatar-middle withu-head-avatar-anim-item">
                                <div
                                    class="withu-head-avatar-status-text withu-head-avatar-status-offline">
                                                                            <i data-lucide="wifi-off" class="withu-head-avatar-icon-offline"></i>
                                                                        <em>离线</em>
                                </div>
                                <div class="withu-head-avatar-divider"></div>
                            </div>
                            <div class="withu-head-avatar-bottom withu-head-avatar-anim-item">
                                <div class="withu-head-avatar-location">
                                    <i data-lucide="map-pin"></i>
                                    <em>甲子公园</em>
                                </div>
                            </div>
                        </div>
                                            </div>
                    <span class="shadow-blur">Really</span>
                </div>
                            </div>
        </div>
    </div>

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
    <title>withU Demo</title>

</head>

<body class="bg-pdot-vignette">
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
                                    id="withu-day-start-date-display">2023-07-19 00:00</span>
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
                                    data-withu-tip="访问次数：121" data-withu-tip-force="true">
                                    <div class="withu-font-num withu-traffic-value">
                                        121                                    </div>
                                    <div class="withu-traffic-label">访问次数</div>
                                </div>
                                <div class="withu-traffic-divider" aria-hidden="true"></div>
                                <div class="withu-traffic-metric"
                                    data-withu-tip="今日访客：23" data-withu-tip-force="true">
                                    <div class="withu-font-num withu-traffic-value">
                                        23                                    </div>
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
                                    data-withu-tip="总访客数：4,047" data-withu-tip-force="true">
                                    <div class="withu-font-num withu-traffic-value">
                                        4,047                                    </div>
                                    <div class="withu-traffic-label">总访客数</div>
                                </div>
                                <div class="withu-traffic-divider" aria-hidden="true"></div>
                                <div class="withu-traffic-metric"
                                    data-withu-tip="总访问次：14,998" data-withu-tip-force="true">
                                    <div class="withu-font-num withu-traffic-value">
                                        14,998                                    </div>
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

<link rel="stylesheet" href="Style/Font/font_footer/iconfont.css">

<script src="Style/vendor/confetti/confetti.browser.min.js"></script>
<script src="assets/js/page-messages.js"></script>
<script src="Style/toastify/lucide.min.js"></script>
<script src="Style/toastify/toastify.js"></script>
<script>if(typeof lucide!=='undefined')lucide.createIcons();</script>
<script src="Style/js/clipboard.min.js"></script>
<script src="assets/js/clipboard.js"></script>
<script src="assets/js/tooltip.js"></script>
<script src="Style/js/view-image.min.js"></script>
<script src="Style/LoveListStyle/carousel.umd.js"></script>
<script src="Style/LoveListStyle/carousel.thumbs.umd.js"></script>
<script src="Style/LoveListStyle/fancybox.umd.js"></script>
<script src="assets/js/page-lovelist.js"></script>
<script src="assets/js/page-index.js"></script>
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

                // 初始化天气胶囊
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

<div class="div_marb_7rem-none">
    <div class="footer-warp">
        <div class="footer">

                            <p><a class="github-badge" href="https://icp.gov.moe/?keyword=20248288" target="_blank">
                        <span class="badge-subject"><img src="Style/img/icp/moeICP.png"></span>
                        <span class="badge-value bg-pink">
                            萌ICP备20248288号                        </span>
                    </a></p>
                                        <p><a class="github-badge" href="https://beian.miit.gov.cn/#/Integrated/index" target="_blank">
                        <span class="badge-subject"><img src="Style/img/icp/ICP.svg"></span>
                        <span class="badge-value bg-blue">
                            粤ICP备2021037776号                        </span>
                    </a></p>
                                        <p><a class="github-badge"
                        href="http://www.beian.gov.cn/portal/registerSystemInfo?recordcode=1112223334455"
                        target="_blank">
                        <span class="badge-subject"><img src="Style/img/icp/policeICP.svg"></span>
                        <span class="badge-value bg-DIY">
                            粤公网安备 1112223334455号                        </span>
                    </a></p>
                                        <p>
                    <a href="javascript:void(0);" class="github-badge">
                        <span class="badge-subject">Copyright</span>
                        <span class="badge-value bg-DIY1">
                            ©
                            2026 withU Web All Rights Reserved.
                        </span>
                    </a>
                </p>
                    </div>
    </div>
</div>

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

<script>
    (function () {
        var requestId = "4bebfcc3df7cdfbe7508d5bb2b3477f1";
        var token = "128536b344d91809322d6d5ffb3d41b3720c1bd9ef067a116d2eb11831fac450";
        window.WITHU_CONFIG = Object.assign(window.WITHU_CONFIG || {}, {
            endpoints: Object.assign({}, (window.WITHU_CONFIG && window.WITHU_CONFIG.endpoints) || {}, {
                accessBeacon: "services/access-beacon.php"            })
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

<script src="assets/js/auth-status.js"></script>
</body>

</html>
