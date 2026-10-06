<?php require __DIR__ . '/inc/auth.php'; ?>
<?php require __DIR__ . '/inc/config.php'; ?>
<meta name="x-withu-license-instance" content="858ee1d099b9">

<link rel="icon" href="/favicon.png" />
<meta name="viewport" content="width=device-width,minimum-scale=1.0,maximum-scale=1.0,user-scalable=no">
<meta name="description" content="withU 是一个适合记录恋爱日常与纪念时刻的情侣小站，支持相册、时间轴、点滴文章、留言互动和邀请页面，让每一段关系都能拥有自己的专属回忆空间。">
<meta name="keywords" content="情侣网站,恋爱记录,祝福留言,情侣相册,恋爱清单,爱情纪念,情侣头像框,祝福语句,情侣互动,爱情相册,情侣事件记录,情侣留言,爱情故事,情感交流,用户互动,祝福卡片,音乐分享,甜蜜瞬间,情侣活动,爱情动态,withU">
<meta name="robots" content="index, follow">
<link rel="canonical" href="/page.php?id=6">

<!-- Open Graph (Facebook/微信/QQ) -->
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?php echo htmlspecialchars($withuSiteTitle, ENT_QUOTES, 'UTF-8'); ?>">
<meta property="og:title" content="<?php echo htmlspecialchars($withuSiteTitle, ENT_QUOTES, 'UTF-8'); ?>">
<meta property="og:description" content="withU 是一个适合记录恋爱日常与纪念时刻的情侣小站，支持相册、时间轴、点滴文章、留言互动和邀请页面，让每一段关系都能拥有自己的专属回忆空间。">
<meta property="og:url" content="/page.php?id=6">
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
    <title>点点滴滴语法书写参考 — <?php echo htmlspecialchars($withuSiteTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="author" content="Ki">
    <meta name="love-theme" content="withU-情侣小站">
    <meta name="copyright" content="2024 withU Web All Rights Reserved">
    <meta name="theme-color" content="#ffffff">
</head>

<body class="bg-pdot-vignette">
        <?php
    // ── 文章详情：与后台/数据库联动 ──────────────────────────────
    // 按 ?id= 从 articles 表读取（后台可管理）；不可见或不存在时回列表页。
    $withuArticle = null;
    $withuArticleAuthor = ['name' => '', 'avatar' => '', 'gender' => ''];
    $withuArticlePrev = null;
    $withuArticleNext = null;
    $withuArticleDayNo = null;
    $withuArticleWordCount = 0;
    $withuArticleId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    try {
        if (class_exists('Database') && isset($db) && $db) {
            if (function_exists('migrate_schema_if_needed')) {
                migrate_schema_if_needed();
            }
            if (!function_exists('upload_url')) {
                require_once dirname(__DIR__) . '/backend/app/core/helpers.php';
            }
            if ($withuArticleId > 0) {
                $withuArticle = $db->fetch(
                    "SELECT a.*, u.nickname, u.avatar, u.gender
                     FROM articles a
                     LEFT JOIN users u ON a.user_id = u.id
                     WHERE a.id = :id AND a.status = 'published'
                     LIMIT 1",
                    ['id' => $withuArticleId]
                );
                // 可见范围：hidden/login 的文章仅登录可见（与列表页、首页口径一致）
                if ($withuArticle) {
                    $__vis = function_exists('withu_effective_visibility')
                        ? withu_effective_visibility($withuArticle)
                        : 'public';
                    if (!$loggedIn && $__vis !== 'public') {
                        $withuArticle = null;
                    }
                }
            }

            if ($withuArticle) {
                $withuArticleAuthor = [
                    'name'   => (string) ($withuArticle['nickname'] ?? ''),
                    'avatar' => (string) ($withuArticle['avatar'] ?? ''),
                    'gender' => in_array(($withuArticle['gender'] ?? ''), ['male', 'female'], true) ? $withuArticle['gender'] : '',
                ];
                $withuArticleWordCount = mb_strlen(preg_replace('/\s+/u', '', strip_tags((string) ($withuArticle['content'] ?? ''))));

                // 恋爱 DAY 计数（后台 love_date 优先，回落站点配置 startTime）
                $__loveStart = function_exists('get_setting') ? trim((string) get_setting('love_date', '')) : '';
                if ($__loveStart === '') {
                    $__cfg = json_decode($withuConfigJson ?? '{}', true);
                    $__loveStart = trim((string) ($__cfg['startTime'] ?? ''));
                }
                $__loveTs = $__loveStart !== '' ? strtotime($__loveStart) : false;
                $__artTs  = strtotime((string) $withuArticle['created_at']);
                if ($__loveTs && $__artTs && $__artTs >= $__loveTs) {
                    $withuArticleDayNo = (int) floor(($__artTs - $__loveTs) / 86400) + 1;
                }

                // 上一篇 / 下一篇（同可见范围内的相邻文章）
                $__guestFilter = $loggedIn ? '' : " AND (is_encrypted = 0 OR is_encrypted IS NULL)";
                $withuArticlePrev = $db->fetch(
                    "SELECT a.id, a.title, a.created_at, u.nickname, u.avatar
                     FROM articles a LEFT JOIN users u ON u.id = a.user_id
                     WHERE a.status = 'published' AND a.id < :id{$__guestFilter}
                     ORDER BY a.id DESC LIMIT 1",
                    ['id' => $withuArticleId]
                );
                $withuArticleNext = $db->fetch(
                    "SELECT a.id, a.title, a.created_at, u.nickname, u.avatar
                     FROM articles a LEFT JOIN users u ON u.id = a.user_id
                     WHERE a.status = 'published' AND a.id > :id{$__guestFilter}
                     ORDER BY a.id ASC LIMIT 1",
                    ['id' => $withuArticleId]
                );
            }
        }
    } catch (Throwable $e) {
        $withuArticle = null;
    }

    if (!$withuArticle) {
        // 文章不存在或无权访问：回到点滴列表
        header('Location: /articles.php', true, 302);
        exit;
    }

    $__art = $withuArticle;
    $__artTs = strtotime((string) $__art['created_at']);
    $__artYear = date('Y', $__artTs ?: time());
    $__artMonth = date('m', $__artTs ?: time());
    $__artDay = date('j', $__artTs ?: time());
    $__artTime = date('H:i', $__artTs ?: time());
    $__artDateCn = date('m月d日', $__artTs ?: time());
    $__artLocation = trim((string) ($__art['location_name'] ?? ''));
    $__artLng = $__art['longitude'] ?? null;
    $__artLat = $__art['latitude'] ?? null;
    $__artHasCoords = $__artLng !== null && $__artLat !== null && is_numeric($__artLng) && is_numeric($__artLat);
    $__artAuthorName = $withuArticleAuthor['name'] !== '' ? $withuArticleAuthor['name'] : 'withU';
    $__artAuthorAvatar = function_exists('upload_url') ? (upload_url($withuArticleAuthor['avatar']) ?: '/assets/images/default-avatar.svg') : '/assets/images/default-avatar.svg';
    $__artContent = (string) ($__art['content'] ?? '');
    // 富文本内容原样输出（尊重原有自定义标签语法）；纯文本则转义并保留换行
    $__artContentHtml = (strip_tags($__artContent) !== $__artContent)
        ? $__artContent
        : nl2br(htmlspecialchars($__artContent, ENT_QUOTES, 'UTF-8'));
    ?>

    <div id="pjax-container" data-view-target="article" data-view-id="<?php echo (int) $__art['id']; ?>">
        <!-- PJAX 浏览量元数据 -->
        <div id="withu-view-meta" data-view-target="article" data-view-id="<?php echo (int) $__art['id']; ?>" style="display:none;"></div>

        <!-- Toast -->
        <div id="toast" class="withu-detail-toast">
            <i class="ph-fill ph-check-circle"></i>
            <span id="toast-msg">Success</span>
        </div>

        <!-- Right Vertical Rail (Operations) -->
        <aside class="withu-detail-vertical-rail">
            <button class="withu-detail-rail-btn" id="rail-like-btn" data-like-target="article" data-like-id="<?php echo (int) $__art['id']; ?>" data-withu-tip="<span data-like-count='article:<?php echo (int) $__art['id']; ?>'><?php echo (int) ($__art['like_count'] ?? 0); ?></span> 人喜欢" data-withu-tip-html="true" data-withu-tip-force="true" data-withu-tip-dir="left">
                <i class="ph ph-heart"></i>
            </button>
            <button class="withu-detail-rail-btn withu-detail-rail-mobile-only" id="mobile-toc-btn" data-withu-tip="目录" data-withu-tip-force="true" data-withu-tip-dir="left">
                <i class="ph ph-list-bullets"></i>
            </button>
            <button class="withu-detail-rail-btn withu-detail-rail-desktop-only" id="rail-qr-btn" data-withu-tip="<div id='qrcode-rail' style='width:100px;height:100px;margin:8px auto 0;display:flex;align-items:center;justify-content:center;'><i class='ph ph-spinner-gap' style='font-size:24px;color:rgba(255,255,255,0.5);animation:spin 1s linear infinite;'></i></div><p style='margin:10px 0 0;font-size:12px;color:rgba(255,255,255,0.7);text-align:center;'>扫码分享给好友</p>" data-withu-tip-html="true" data-withu-tip-force="true" data-withu-tip-dir="left">
                <i class="ph ph-qr-code"></i>
            </button>
            <button class="withu-detail-rail-btn" id="rail-copy-btn" data-withu-tip="复制链接" data-withu-tip-force="true" data-withu-tip-dir="left">
                <i class="ph ph-link"></i>
            </button>
        </aside>

        <!-- Mobile TOC Overlay -->
        <div id="mobile-toc-overlay" class="withu-detail-sheet-overlay"></div>
        <div id="mobile-toc-sheet" class="withu-detail-mobile-sheet">
            <div class="withu-detail-mobile-sheet-header">
                <h3 class="withu-detail-mobile-sheet-title withu-font-serif">目录</h3>
                <button id="mobile-toc-close" class="withu-detail-mobile-sheet-close">
                    <i class="ph-bold ph-x"></i>
                </button>
            </div>
            <div class="withu-toc-switcher-bar">
                <div class="withu-toc-switcher-mini">
                    <button class="withu-toc-tab-mini active" data-mode="none" onclick="switchTocMode('none')">
                        <i class="ph ph-minus"></i>
                        <span>默认</span>
                    </button>
                    <button class="withu-toc-tab-mini" data-mode="number" onclick="switchTocMode('number')">
                        <i class="ph ph-hash"></i>
                        <span>序号</span>
                    </button>
                    <button class="withu-toc-tab-mini" data-mode="tag" onclick="switchTocMode('tag')">
                        <i class="ph ph-tag"></i>
                        <span>标签</span>
                    </button>
                    <div class="withu-toc-tab-indicator-mini"></div>
                </div>
            </div>
            <nav id="toc-mobile" class="withu-detail-mobile-sheet-nav"></nav>
        </div>

        <!-- Main Grid -->
        <div class="withu-detail-main-grid">

            <aside class="withu-detail-sidebar">
                <div class="withu-detail-sidebar-sticky">
                    <!-- Weather Widget -->
                    
                    <!-- Sidebar TOC -->
                    <div class="withu-detail-card withu-detail-sidebar-toc">
                        <div class="withu-toc-switcher-bar">
                            <div class="withu-toc-switcher-mini">
                                <button class="withu-toc-tab-mini active" data-mode="none" onclick="switchTocMode('none')">
                                    <i class="ph ph-minus"></i>
                                    <span>默认</span>
                                </button>
                                <button class="withu-toc-tab-mini" data-mode="number" onclick="switchTocMode('number')">
                                    <i class="ph ph-hash"></i>
                                    <span>序号</span>
                                </button>
                                <button class="withu-toc-tab-mini" data-mode="tag" onclick="switchTocMode('tag')">
                                    <i class="ph ph-tag"></i>
                                    <span>标签</span>
                                </button>
                                <div class="withu-toc-tab-indicator-mini"></div>
                            </div>
                        </div>
                        <nav id="toc-desktop" class="withu-detail-toc-list"></nav>
                    </div>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="withu-detail-main-content">

                <!-- Header Card -->
                <section class="withu-detail-card withu-detail-header-card">
                    <div class="withu-detail-header-bg"></div>
                    <div class="withu-detail-header-blob-container">
                        <div class="withu-detail-header-blob withu-detail-blob-left"></div>
                        <div class="withu-detail-header-blob withu-detail-blob-right"></div>
                    </div>
                    <div class="withu-detail-header-watermark">
                        <i class="ph-duotone ph-book-open withu-detail-watermark-icon"></i>
                        <span class="withu-detail-watermark-text">Story</span>
                    </div>
                    <div class="withu-detail-header-content withu-detail-card-p-md">
                        <!-- Date Capsule -->
                        <div class="withu-detail-category-wrapper">
                            <div class="withu-detail-date-capsule">
                                <div class="withu-detail-date-text-wrapper">
                                    <i class="ph-fill ph-sparkle withu-detail-date-icon-sparkle"></i>
                                    <span><?php echo $__artYear; ?></span>
                                    <div class="withu-detail-date-gradient-divider"></div>
                                    <span><?php echo $__artMonth; ?></span>
                                </div>
                                <div class="withu-detail-date-circle">
                                    <span class="withu-detail-date-day-number"><?php echo $__artDay; ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Title -->
                        <h1 class="withu-detail-article-title"><?php echo htmlspecialchars((string) $__art['title'], ENT_QUOTES, 'UTF-8'); ?></h1>

                        <!-- Author Section -->
                        <div class="withu-detail-author-section">
                            <div class="withu-detail-author-row">
                                <div class="withu-detail-author-info">
                                    <div class="withu-detail-author-avatar-wrapper">
                                        <div class="withu-detail-author-avatar-blur"></div>
                                        <img src="<?php echo htmlspecialchars($__artAuthorAvatar, ENT_QUOTES, 'UTF-8'); ?>" class="withu-detail-author-avatar">
                                        <div class="withu-detail-author-badge">
                                            <i class="ph-fill ph-seal-check"></i>
                                        </div>
                                    </div>
                                    <div class="withu-detail-author-text">
                                        <h3 class="withu-detail-author-name"><?php echo htmlspecialchars($__artAuthorName, ENT_QUOTES, 'UTF-8'); ?></h3>
                                        <p class="withu-detail-author-desc">
                                            <?php if ($__artLocation !== '' && $__artHasCoords): ?>
                                                <span><?php echo $__artTime; ?>于<a href="javascript:void(0)" style="color:inherit;" onclick="if(window.WithUMap) WithUMap.open({ mode:'moments', coords:[<?php echo (float) $__artLng; ?>,<?php echo (float) $__artLat; ?>], zoom:15 })"><?php echo htmlspecialchars($__artLocation, ENT_QUOTES, 'UTF-8'); ?></a>，记录当下的瞬间。</span>
                                            <?php elseif ($__artLocation !== ''): ?>
                                                <span><?php echo $__artTime; ?>于<?php echo htmlspecialchars($__artLocation, ENT_QUOTES, 'UTF-8'); ?>，记录当下的瞬间。</span>
                                            <?php else: ?>
                                                <span><?php echo $__artTime; ?>，记录当下的瞬间。</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>

                                <!-- Day Stamp -->
                                <div class="withu-detail-day-stamp">
                                    <span class="withu-detail-day-label">WITH YOU</span>
                                    <div class="withu-detail-day-value">
                                        <i class="ph-fill ph-heart"></i>
                                        <span>DAY <?php echo $withuArticleDayNo !== null ? (int) $withuArticleDayNo : '—'; ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Metadata Row -->
                            <div class="withu-detail-metadata-row">
                                <!-- Views -->
                                <div class="withu-detail-meta-badge">
                                    <div class="withu-detail-meta-icon-wrapper">
                                        <i class="ph-fill ph-eye"></i>
                                    </div>
                                    <span class="withu-detail-meta-text"><span data-view-count="article:<?php echo (int) $__art['id']; ?>"><?php echo (int) ($__art['views'] ?? 0); ?></span> 次阅读</span>
                                </div>
                                <!-- Likes -->
                                <div class="withu-detail-meta-badge" data-like-target="article" data-like-id="<?php echo (int) $__art['id']; ?>" style="cursor:pointer;">
                                    <div class="withu-detail-meta-icon-wrapper">
                                        <i class="ph-fill ph-heart"></i>
                                    </div>
                                    <span class="withu-detail-meta-text"><span class="withu-interaction-like-num" data-like-count="article:<?php echo (int) $__art['id']; ?>"><?php echo (int) ($__art['like_count'] ?? 0); ?></span> 喜欢</span>
                                </div>
                                <!-- Time -->
                                <div class="withu-detail-meta-badge">
                                    <div class="withu-detail-meta-icon-wrapper">
                                        <i class="ph-fill ph-clock"></i>
                                    </div>
                                    <span class="withu-detail-meta-text"><?php echo $__artTime; ?></span>
                                </div>
                                <!-- Location -->
                                <?php if ($__artLocation !== ''): ?>
                                    <div class="withu-detail-meta-badge" style="cursor:pointer"<?php echo $__artHasCoords ? ' onclick="if(window.WithUMap) WithUMap.open({ mode:\'moments\', coords:[' . (float) $__artLng . ',' . (float) $__artLat . '], zoom:15 })"' : ''; ?>>
                                        <div class="withu-detail-meta-icon-wrapper">
                                            <i class="ph-fill ph-map-pin"></i>
                                        </div>
                                        <span class="withu-detail-meta-text"><?php echo htmlspecialchars($__artLocation, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                <?php endif; ?>

                                <!-- Words -->
                                <div class="withu-detail-meta-badge">
                                    <div class="withu-detail-meta-icon-wrapper">
                                        <i class="ph-fill ph-text-aa"></i>
                                    </div>
                                    <span class="withu-detail-meta-text"><?php echo (int) $withuArticleWordCount; ?> 字</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>


                <!-- Main Content Card -->
                <article class="withu-detail-card withu-detail-article-card withu-detail-card-p-md">


                    <div class="withu-article-body withu-main" id="article-content">

                        <div class="withu-detail-quote-icon">
                            <i class="ph-fill ph-quotes"></i>
                        </div>

                        <!-- Font Switcher Bar -->
                        <div class="withu-detail-font-switcher-bar">
                            <div class="withu-detail-font-switcher-bar-inner">
                                <div class="withu-font-switcher-mini">
                                    <button class="withu-font-tab-mini active" data-font="default" onclick="switchFont('default')">
                                        <i class="ph ph-text-aa"></i>
                                        <span>默认</span>
                                    </button>
                                    <button class="withu-font-tab-mini" data-font="noto" onclick="switchFont('noto')">
                                        <i class="ph ph-text-t"></i>
                                        <span>宋体</span>
                                    </button>
                                    <button class="withu-font-tab-mini" data-font="harmony" onclick="switchFont('harmony')">
                                        <i class="ph ph-translate"></i>
                                        <span>鸿蒙</span>
                                    </button>
                                    <div class="withu-font-tab-indicator-mini"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Article Content -->
                        <div id="withu-detail-content" class="withu-detail-text">
<?php echo $__artContentHtml; ?>
                        </div>

                    </div>
                    <?php if ($withuArticlePrev || $withuArticleNext): ?>
                        <div class="withu-detail-bottom-nav">
                            <div class="withu-detail-bottom-nav-grid">
                                <?php if ($withuArticlePrev): ?>
                                    <!-- Previous Article -->
                                    <a href="page.php?id=<?php echo (int) $withuArticlePrev['id']; ?>" class="withu-detail-nav-card">
                                        <div class="withu-detail-nav-header">
                                            <i class="ph-bold ph-arrow-left"></i>
                                            <span>上一篇</span>
                                        </div>
                                        <div class="withu-detail-nav-title"><?php echo htmlspecialchars((string) $withuArticlePrev['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="withu-detail-nav-footer">
                                            <img src="<?php echo htmlspecialchars(function_exists('upload_url') ? (upload_url((string) ($withuArticlePrev['avatar'] ?? '')) ?: '/assets/images/default-avatar.svg') : '/assets/images/default-avatar.svg', ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string) ($withuArticlePrev['nickname'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                            <span><?php echo htmlspecialchars((string) ($withuArticlePrev['nickname'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span>·</span>
                                            <span><?php echo date('m月d日', strtotime((string) $withuArticlePrev['created_at']) ?: time()); ?></span>
                                        </div>
                                    </a>
                                <?php endif; ?>
                                <?php if ($withuArticleNext): ?>
                                    <!-- Next Article -->
                                    <a href="page.php?id=<?php echo (int) $withuArticleNext['id']; ?>" class="withu-detail-nav-card<?php echo $withuArticlePrev ? ' withu-detail-nav-card-right' : ''; ?>">
                                        <div class="withu-detail-nav-header">
                                            <span>下一篇</span>
                                            <i class="ph-bold ph-arrow-right"></i>
                                        </div>
                                        <div class="withu-detail-nav-title"><?php echo htmlspecialchars((string) $withuArticleNext['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="withu-detail-nav-footer">
                                            <img src="<?php echo htmlspecialchars(function_exists('upload_url') ? (upload_url((string) ($withuArticleNext['avatar'] ?? '')) ?: '/assets/images/default-avatar.svg') : '/assets/images/default-avatar.svg', ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string) ($withuArticleNext['nickname'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                            <span><?php echo htmlspecialchars((string) ($withuArticleNext['nickname'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span>·</span>
                                            <span><?php echo date('m月d日', strtotime((string) $withuArticleNext['created_at']) ?: time()); ?></span>
                                        </div>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </article>

            </main>


        </div>

    </div>

    <script>
        // 隐藏首页 Tab 导航
        document.addEventListener('DOMContentLoaded', function() {
            var tabWrap = document.querySelector('.withu-mobile-nav-root');
            if (tabWrap) tabWrap.style.display = 'none';
        });

    </script>

    <script src="/assets/js/page-articles.js"></script>
    

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
        window.WITHU_CONFIG.anonymousAvatar = "/Lovefolder/20250310095445_67ce46659778a.gif";
    </script>

    <!-- 极验验证与留言提交绑定 -->
    <script src="/assets/js/withu-shared-0c59da72.js"></script>


<link rel="stylesheet" href="/Style/Font/font_footer/iconfont.css">
    <link rel="stylesheet" href="/assets/fonts/pacifico.css">

<script src="/Style/vendor/confetti/confetti.browser.min.js"></script>
<script src="/assets/js/page-messages.js?v=20260831"></script>
<script src="/Style/toastify/lucide.min.js"></script>
<script src="/Style/toastify/toastify.js"></script>
<script>if(typeof lucide!=='undefined')lucide.createIcons();</script>
<script src="/Style/js/clipboard.min.js"></script>
<script src="/assets/js/clipboard.js"></script>
<script src="/assets/js/tooltip.js"></script>
<script src="/Style/js/view-image.min.js"></script>
<script src="/assets/js/webp-default.js?v=20260830"></script>
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

    <link rel="stylesheet" href="/assets/css/withu-shared-686093da.css">
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


<script src="/assets/js/page-page-4153e237.js"></script>

</body>

</html>
