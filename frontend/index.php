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

$__cfgWeather = json_decode($withuConfigJson ?? '{}', true);

// 首页统计数字：按数据库实时计算（原为静态快照数字）
$homeStats = ['articles' => 0, 'photos' => 0, 'messages' => 0, 'timeline' => 0];
try {
    if (isset($db) && $db) {
        $guestArt = $loggedIn ? '' : " AND (is_encrypted = 0 OR is_encrypted IS NULL)";
        $guestAlb = $loggedIn ? '' : " AND (is_encrypted = 0 OR is_encrypted IS NULL)";
        $homeStats['articles'] = (int) $db->fetch("SELECT COUNT(*) AS c FROM articles WHERE status = 'published'{$guestArt}")['c'];
        $homeStats['photos']   = (int) $db->fetch("SELECT COUNT(*) AS c FROM album_images")['c'];
        $homeStats['messages'] = (int) $db->fetch("SELECT COUNT(*) AS c FROM messages WHERE status = 'published' AND is_public = 1")['c'];
        $homeStats['lovelist_done']  = (int) $db->fetch("SELECT COUNT(*) AS c FROM love_list_items WHERE is_done = 1")['c'];
        $homeStats['lovelist_total'] = (int) $db->fetch("SELECT COUNT(*) AS c FROM love_list_items")['c'];
        $homeStats['timeline'] = $homeStats['articles']
            + (int) $db->fetch("SELECT COUNT(*) AS c FROM albums WHERE 1=1{$guestAlb}")['c']
            + (int) $db->fetch("SELECT COUNT(*) AS c FROM love_list_items WHERE is_done = 1")['c']
            + (int) $db->fetch("SELECT COUNT(*) AS c FROM events")['c'];
    }
} catch (Throwable $e) {
    // 统计失败保持 0
}

// ── 首页三大区块数据：与后台/数据库联动 ────────────────────────
// 点滴（articles）/ 相册（albums）/ 留言（messages）均从数据库读取，
// 取代原先写死在页面里的静态卡片；读取失败时对应区块降级为空。
if (!function_exists('upload_url')) {
    require_once __DIR__ . '/../backend/app/core/helpers.php';
}

if (!function_exists('withu_home_cn_date')) {
    /** 2021-08-29 → 二〇二一年八月二十九日 */
    function withu_home_cn_date(string $date): string {
        $ts = strtotime($date);
        if (!$ts) return '';
        $y = date('Y', $ts);
        $m = (int) date('n', $ts);
        $d = (int) date('j', $ts);
        $digits = ['〇', '一', '二', '三', '四', '五', '六', '七', '八', '九'];
        $yearCn = '';
        foreach (str_split($y) as $ch) {
            $yearCn .= $digits[(int) $ch] ?? $ch;
        }
        $months = ['', '一', '二', '三', '四', '五', '六', '七', '八', '九', '十', '十一', '十二'];
        $dayCn = $d <= 10
            ? ($d === 10 ? '十' : $digits[$d])
            : ($d < 20 ? '十' . $digits[$d - 10] : ($d % 10 === 0 ? $digits[intdiv($d, 10)] . '十' : $digits[intdiv($d, 10)] . '十' . $digits[$d % 10]));
        return $yearCn . '年' . $months[$m] . '月' . $dayCn . '日';
    }
}

$homeLoveTs = false;
try {
    $__cfgHome = json_decode($withuConfigJson ?? '{}', true);
    $__loveStartHome = function_exists('get_setting') ? trim((string) get_setting('love_date', '')) : '';
    if ($__loveStartHome === '') {
        $__loveStartHome = trim((string) ($__cfgHome['startTime'] ?? ''));
    }
    if ($__loveStartHome !== '') {
        $homeLoveTs = strtotime($__loveStartHome) ?: false;
    }
} catch (Throwable $e) {
    $homeLoveTs = false;
}

// Love Day 卡片：与后台「纪念日（milestones）」同源，读取 frontend/services/map-all.json；
// 后台增删后此区块随 JSON 同步，不再使用写死的静态卡片
$homeLoveDays = [];
try {
    $__loveMapFile = __DIR__ . '/services/map-all.json';
    if (is_file($__loveMapFile)) {
        $__loveMap = json_decode((string) file_get_contents($__loveMapFile), true);
        $homeLoveDays = is_array($__loveMap) ? ($__loveMap['milestones'] ?? []) : [];
    }
} catch (Throwable $e) {
    $homeLoveDays = [];
}

// 首页清单/事件卡片：与后台「恋爱清单」同源，读取 love_list_items 表最新 4 条；
// 后台增删后此区块随数据库同步，不再使用写死的静态卡片
$homeListEvents = [];
try {
    if (isset($db) && $db) {
        if (function_exists('migrate_schema_if_needed')) {
            migrate_schema_if_needed();
        }
        if (!function_exists('upload_url')) {
            require_once __DIR__ . '/../backend/app/core/helpers.php';
        }
        $rows = $db->fetchAll(
            "SELECT * FROM love_list_items ORDER BY sort_order ASC, id DESC LIMIT 4"
        );
        $ids = array_column($rows, 'id');
        $imagesByItem = [];
        if ($ids) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            foreach ($db->fetchAll(
                "SELECT item_id, image_path, thumbnail_path
                 FROM love_list_item_images
                 WHERE item_id IN ($ph)
                 ORDER BY item_id ASC, sort_order ASC, id ASC",
                $ids
            ) as $im) {
                $path = (string) ($im['thumbnail_path'] ?: $im['image_path']);
                if ($path === '') {
                    continue;
                }
                $imagesByItem[(int) $im['item_id']] ??= upload_url($path);
            }
        }
        foreach ($rows as $r) {
            $homeListEvents[] = [
                'id'      => (int) $r['id'],
                'title'   => (string) $r['title'],
                'done'    => !empty($r['is_done']),
                'date'    => (string) ($r['done_date'] ?? ''),
                'place'   => (string) ($r['location_name'] ?? ''),
                'note'    => (string) ($r['note'] ?? ''),
                'image'   => $imagesByItem[(int) $r['id']] ?? '',
            ];
        }
    }
} catch (Throwable $e) {
    $homeListEvents = [];
}

// 点滴：最新 6 篇（游客仅公开文章）
$homeArticles = [];
try {
    if (isset($db) && $db) {
        $rows = $db->fetchAll(
            "SELECT a.*, u.nickname, u.avatar
             FROM articles a
             LEFT JOIN users u ON a.user_id = u.id
             WHERE a.status = 'published'
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT 12"
        );
        foreach ($rows as $r) {
            $vis = function_exists('withu_effective_visibility') ? withu_effective_visibility($r) : 'public';
            if (!$loggedIn && $vis !== 'public') {
                continue;
            }
            $ts = strtotime((string) $r['created_at']) ?: 0;
            $dayNo = ($homeLoveTs && $ts >= $homeLoveTs) ? (int) floor(($ts - $homeLoveTs) / 86400) + 1 : null;
            $excerpt = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($r['content'] ?? ''))));
            if (mb_strlen($excerpt) > 160) {
                $excerpt = mb_substr($excerpt, 0, 160) . '…';
            }
            $homeArticles[] = [
                'id'        => (int) $r['id'],
                'title'     => (string) $r['title'],
                'excerpt'   => $excerpt,
                'author'    => (string) ($r['nickname'] ?? ''),
                'avatar'    => upload_url((string) ($r['avatar'] ?? '')) ?: 'https://q1.qlogo.cn/g?b=qq&nk=10000&s=640',
                'date'      => $ts ? date('Y-m-d H:i', $ts) : '',
                'city'      => (string) ($r['location_name'] ?? ''),
                'weather'   => (string) ($r['weather'] ?? ''),
                'weather_icon' => (string) ($r['weather_icon'] ?? ''),
                'mood'      => (string) ($r['mood'] ?? ''),
                'mood_icon' => (string) ($r['mood_icon'] ?? ''),
                'views'     => (int) ($r['views'] ?? 0),
                'likes'     => (int) ($r['like_count'] ?? 0),
                'day_no'    => $dayNo,
            ];
            if (count($homeArticles) >= 6) {
                break;
            }
        }
    }
} catch (Throwable $e) {
    $homeArticles = [];
}

// 相册：最新 3 个（游客过滤完全隐藏的相册）
$homeAlbums = [];
try {
    if (isset($db) && $db) {
        $rows = $db->fetchAll(
            "SELECT a.*, u.nickname, u.avatar,
                    ((SELECT COUNT(*) FROM album_images WHERE album_id = a.id) +
                     (SELECT COUNT(*) FROM album_videos  WHERE album_id = a.id)) AS image_count
             FROM albums a
             LEFT JOIN users u ON a.user_id = u.id
             ORDER BY a.created_at DESC, a.id ASC
             LIMIT 8"
        );
        $homeAlbumCodes = [];
        $__mapFileHome = __DIR__ . '/services/map-all.json';
        if (is_file($__mapFileHome)) {
            $__mapHome = json_decode((string) file_get_contents($__mapFileHome), true);
            foreach ((is_array($__mapHome) ? ($__mapHome['albums'] ?? []) : []) as $__ma) {
                if (!empty($__ma['name']) && !empty($__ma['code'])) {
                    $homeAlbumCodes[(string) $__ma['name']] = (string) $__ma['code'];
                }
            }
        }
        foreach ($rows as $r) {
            $vis = function_exists('withu_effective_visibility') ? withu_effective_visibility($r) : 'public';
            if (!$loggedIn && $vis === 'hidden') {
                continue;
            }
            $homeAlbums[] = [
                'name'    => (string) $r['name'],
                'city'    => (string) ($r['location_name'] ?? ''),
                'count'   => (int) ($r['image_count'] ?? 0),
                'cover'   => upload_url((string) ($r['cover_image'] ?? '')),
                'author'  => (string) ($r['nickname'] ?? ''),
                'avatar'  => upload_url((string) ($r['avatar'] ?? '')) ?: 'https://q1.qlogo.cn/g?b=qq&nk=10000&s=640',
                'date'    => withu_home_cn_date((string) $r['created_at']),
                'code'    => $homeAlbumCodes[(string) $r['name']] ?? '',
            ];
            if (count($homeAlbums) >= 3) {
                break;
            }
        }
    }
} catch (Throwable $e) {
    $homeAlbums = [];
}

// 留言：最新 24 条公开留言
$homeMessages = [];
try {
    if (isset($db) && $db) {
        $rows = $db->fetchAll(
            "SELECT * FROM messages
             WHERE status = 'published' AND is_public = 1 AND (parent_id IS NULL OR parent_id = 0)
             ORDER BY created_at DESC, id DESC
             LIMIT 24"
        );
        foreach ($rows as $r) {
            $qq = trim((string) ($r['guest_qq'] ?? ''));
            $avatar = trim((string) ($r['guest_avatar'] ?? ''));
            if ($avatar === '') {
                $avatar = $qq !== '' ? '/_qqavatar.php?qq=' . rawurlencode($qq) . '&s=100' : '/_qqavatar.php?qq=10000&s=100';
            }
            $homeMessages[] = [
                'id'         => (int) $r['id'],
                'name'       => (string) ($r['guest_nickname'] ?? '') !== '' ? (string) $r['guest_nickname'] : '匿名留言',
                'avatar'     => $avatar,
                'time'       => (string) ($r['created_at'] ?? ''),
                'content'    => (string) ($r['content_html'] ?? '') !== '' ? (string) $r['content_html'] : nl2br(htmlspecialchars((string) ($r['content'] ?? ''), ENT_QUOTES, 'UTF-8')),
                'location'   => (string) ($r['location'] ?? ''),
                'os'         => (string) ($r['os'] ?? ''),
                'browser'    => (string) ($r['browser'] ?? ''),
            ];
        }
    }
} catch (Throwable $e) {
    $homeMessages = [];
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
<link rel="stylesheet" href="Style/css/index.css?v=20261007f">
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
<link rel="stylesheet" href="Style/css/withu-home-style.css?v=20261007g">
<link rel="stylesheet" href="Style/css/timetable.css?v=20261027-weather">
<link rel="stylesheet" href="Style/css/withu-detail.css?v=1.0.80">
<link rel="stylesheet" href="Style/css/mobile-nav.css?v=1.0.1-flush">
<link rel="stylesheet" href="Style/css/header.css">
<!-- 自定义右键菜单 -->
<link rel="stylesheet" href="Style/css/context-menu.css">
<!-- 足迹地图样式 -->
    <link rel="stylesheet" href="Style/css/map.css?v=1.0.85">

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

    // AOS 动画配置（供 app.js?v=20261026 的 AOSManager 使用）
    window.WITHU_AOS_CONFIG = {"enabled":true,"animation":"fade-up","duration":800,"delay":0,"interval":50,"maxDelay":300,"easing":"ease-out-cubic","offset":50,"once":true,"mirror":true,"anchorPlacement":"top-bottom"};

</script>

<!-- 足迹地图配置（懒加载，点击才初始化） -->
        <script src="/assets/js/withu-shared-1916a4be.js"></script>
<script src="assets/js/app.js?v=20261026"></script>
<script src="/assets/js/withu-location.js?v=20261026gps"></script>
<script src="/assets/js/head-avatar-location.js?v=20261026gps"></script>
<?php if (!empty($loggedIn)): ?>
<!-- 对方正在看邀请气泡（头像区） -->
<script src="/assets/js/head-avatar-watch-bubble.js"></script>
<?php endif; ?>
<script src="assets/js/components.js?v=20261026gps"></script>

<!-- 礼花效果已迁移到 components.js 的 ConfettiEffect 模块 -->

<script src="/assets/js/pjax.js?v=20261027-watch"></script><script>if(window.WithUPjax&&typeof window.WithUPjax.init==="function")window.WithUPjax.init();</script>
<link rel="stylesheet" href="/assets/css/withu-shared-6790eb7e.css?v=20261021">
<link rel="stylesheet" href="Style/css/motion-effects.css?v=20261007a">
<script src="Style/js/motion-effects.js?v=20261007a" defer></script>

<script>
    // 倒计时、高度调整、轮播图、导航栏等功能已迁移到 app.js?v=20261026 和 components.js
    // 保留必要的全局变量供旧代码兼容
    var pcCarouselHeight = "80vh";
    var mobileCarouselHeight = "50vh";
    var pcPhotoCoverHeight = "80vh";
    var mobilePhotoCoverHeight = "60vh";
    var pcImgMaxHeight = "450px";
    var mobileImgMaxHeight = "260px";
</script>

<div id="loader-wrapper">
    <img id="loader" src="/assets/pet/yier/yier-poke.webp" alt="">
    <div class="loader-section"></div>
</div>

<?php include __DIR__ . '/inc/header.php'; ?>
<div id="homePage" class="wrap" data-Fullscreen>
    <ul class="list mask_black">
                <?php foreach ($homeCarouselImages as $homeCarouselIndex => $homeCarouselImg): ?>
                <li class="item<?php echo $homeCarouselIndex === 0 ? ' active' : ''; ?>">
                    <img class="lazy CarouselImage"
                         data-src="<?php echo e($homeCarouselImg); ?>"
                         src="<?php echo e($homeCarouselImg); ?>"
                         <?php echo $homeCarouselIndex === 0 ? 'fetchpriority="high"' : ''; ?>
                         alt="" draggable="false">
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

<script>
(function(){var p=(location.pathname||'/').replace(/\/+$/,'');var home=p===''||p==='/'||/\/index\.php$/.test(p);var d=document.documentElement;d.classList.add('withu-v2');if(!home)d.classList.add('withu-subpage');})();
</script>
<!-- withU v2 关键首屏样式（完整样式由页脚统一加载，避免布局跳动） -->
<style>
html.withu-subpage #homePage.wrap{height:clamp(300px,38vh,460px)!important;min-height:300px;margin-top:0!important;overflow:hidden;background:linear-gradient(135deg,#fff1f4,#ffe3e9)}
@media(max-width:768px){html.withu-subpage #homePage.wrap{height:clamp(220px,32vh,300px)!important;min-height:220px}html.withu-subpage #homePage .bg-wrap.central.limg{display:none!important}}
</style>
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
                                <img src="<?php echo htmlspecialchars(function_exists('upload_url') ? (upload_url((string) ($__cfgWeather['maleAvatar'] ?? '')) ?: 'https://q1.qlogo.cn/g?b=qq&nk=10000&s=640') : 'https://q1.qlogo.cn/g?b=qq&nk=10000&s=640', ENT_QUOTES, 'UTF-8'); ?>"
                                    class="withu-home-weather-avatar" alt="<?php echo htmlspecialchars((string) ($__cfgWeather['maleName'] ?? '我'), ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="withu-home-weather-username"><?php echo htmlspecialchars((string) ($__cfgWeather['maleName'] ?? '我'), ENT_QUOTES, 'UTF-8'); ?></span>
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
                                <img src="<?php echo htmlspecialchars(function_exists('upload_url') ? (upload_url((string) ($__cfgWeather['femaleAvatar'] ?? '')) ?: 'https://q1.qlogo.cn/g?b=qq&nk=10000&s=640') : 'https://q1.qlogo.cn/g?b=qq&nk=10000&s=640', ENT_QUOTES, 'UTF-8'); ?>"
                                    class="withu-home-weather-avatar" alt="<?php echo htmlspecialchars((string) ($__cfgWeather['femaleName'] ?? '你'), ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="withu-home-weather-username"><?php echo htmlspecialchars((string) ($__cfgWeather['femaleName'] ?? '你'), ENT_QUOTES, 'UTF-8'); ?></span>
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
                                            class="withu-lovelist-completed"><?php echo (int) $homeStats['lovelist_done']; ?></span>
                                        <span class="withu-lovelist-divider">/</span>
                                        <span
                                            class="withu-lovelist-total"><?php echo (int) $homeStats['lovelist_total']; ?></span>
                                    </div>

                                    <div class="withu-font-num withu-num-huge">
                                        <span><?php echo $homeStats['lovelist_total'] > 0 ? (int) round($homeStats['lovelist_done'] * 100 / $homeStats['lovelist_total']) : 0; ?></span><span
                                            class="withu-num-suffix">%</span>
                                    </div>
                                </div>

                                <div class="withu-progress withu-progress-sm">
                                    <div class="withu-progress__bar withu-progress-fill-white"
                                        style="width: <?php echo $homeStats['lovelist_total'] > 0 ? (int) round($homeStats['lovelist_done'] * 100 / $homeStats['lovelist_total']) : 0; ?>%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>
            </section>

            <!-- 课表 Module（与清涵课表 App 周视图一致，仅登录可见；数据由 page-timetable.js 拉取） -->
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
<?php if (empty($homeListEvents)): ?>
                    <div class="withu-no-data" data-aos="fade-up" data-aos-delay="0">
                        <div class="withu-no-data-wrap"><div class="withu-no-data-content">
                            <div class="withu-no-data-icon" aria-hidden="true"><i class="ph-fill ph-heart"></i></div>
                            <h3 class="withu-no-data-title">还没有心愿</h3>
                            <p class="withu-no-data-desc">去后台恋爱清单添加一个吧～</p>
                        </div></div>
                    </div>
<?php else: ?>
<?php foreach ($homeListEvents as $__he_i => $__he): ?>
<?php
    $__he_done = !empty($__he['done']);
    $__he_href = 'lovelist.php#event-' . (int) $__he['id'];
?>
                    <div data-aos="fade-up" data-aos-delay="<?php echo (int) min($__he_i * 50, 300); ?>">
                        <a href="<?php echo $__he_href; ?>"
                            class="withu-event-card <?php echo $__he_done ? 'withu-event-card--has-img withu-event-card--link' : 'withu-event-card--locked withu-event-card--link'; ?>">
                            <?php if ($__he_done && $__he['image'] !== ''): ?>
                                <img class="withu-event-bg-img lazy" data-src="<?php echo htmlspecialchars($__he['image'], ENT_QUOTES, 'UTF-8'); ?>"
                                    alt="<?php echo htmlspecialchars($__he['title'], ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="withu-event-overlay"></div>
                            <?php endif; ?>
                            <div class="withu-event-content">
                                <?php if ($__he_done): ?>
                                    <div>
                                        <div class="withu-event-icon">
                                            <i class="ph-fill ph-heart"></i>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="withu-flex-between-start">
                                        <div class="withu-event-icon">
                                            <i class="ph-duotone ph-lock-key"></i>
                                        </div>
                                        <i class="ph-fill ph-lock-key withu-event-seal"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="withu-event-content-mt">
                                    <h3
                                        class="withu-event-title withu-text-white withu-text-xl withu-font-bold withu-mb-1">
                                        <?php echo htmlspecialchars($__he['title'], ENT_QUOTES, 'UTF-8'); ?>                                    </h3>
<?php if ($__he['note'] !== ''): ?>
                                    <div
                                        class="withu-event-note withu-text-white withu-opacity-80 withu-event-note-sm">
                                        <?php echo htmlspecialchars($__he['note'], ENT_QUOTES, 'UTF-8'); ?>                                    </div>
<?php endif; ?>
                                </div>
                                <div class="withu-event-footer-glass">
                                    <span class="withu-chip withu-chip--glass"><i class="ph-fill ph-map-pin"></i>
                                        <?php echo htmlspecialchars($__he['place'] !== '' ? $__he['place'] : '未设置', ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="withu-chip withu-chip--glass"><i class="ph-fill ph-calendar-blank"></i>
                                        <?php echo htmlspecialchars($__he['date'] !== '' ? $__he['date'] : '待完成', ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </div>
                        </a>
                    </div>
<?php endforeach; ?>
<?php endif; ?>
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
<?php if (empty($homeLoveDays)): ?>
                        <div class="withu-no-data" data-aos="fade-up" data-aos-delay="0">
                            <div class="withu-no-data-wrap"><div class="withu-no-data-content">
                                <div class="withu-no-data-icon" aria-hidden="true"><i class="ph-fill ph-calendar-heart"></i></div>
                                <h3 class="withu-no-data-title">还没有纪念日</h3>
                                <p class="withu-no-data-desc">去后台添加一个吧～</p>
                            </div></div>
                        </div>
<?php else: ?>
<?php foreach ($homeLoveDays as $__ld_i => $__ld): ?>
<?php
    $__ld_date = trim((string) ($__ld['date'] ?? ''));
    if ($__ld_date === '' || strtotime($__ld_date) === false) { continue; }
    $__ld_ts = strtotime($__ld_date);
    $__ld_today = strtotime(date('Y-m-d'));
    // 每年重复按月日推算到今年/明年；一次性日期按原样计算
    $__ld_is_recurring = stripos((string) ($__ld['desc'] ?? ''), '每年') !== false;
    if ($__ld_is_recurring) {
        $__ld_next = mktime(0, 0, 0, (int) date('n', $__ld_ts), (int) date('j', $__ld_ts), (int) date('Y'));
        if ($__ld_next < $__ld_today) {
            $__ld_next = mktime(0, 0, 0, (int) date('n', $__ld_ts), (int) date('j', $__ld_ts), (int) date('Y') + 1);
        }
        $__ld_count = (int) floor(($__ld_next - $__ld_today) / 86400);
        $__ld_past = false;
    } else {
        $__ld_count = (int) floor(($__ld_today - $homeLoveTs && false ? 0 : $__ld_ts) / 86400);
        $__ld_past = $__ld_ts < $__ld_today;
        $__ld_count = (int) floor(max(0, $__ld_today - $__ld_ts) / 86400);
        if ($__ld_count === 0 && date('Y-m-d', $__ld_ts) === date('Y-m-d')) {
            $__ld_count = 0;
        }
        $__ld_count = abs($__ld_count);
    }
    $__ld_title = trim((string) ($__ld['title'] ?? ''));
    $__ld_desc = trim((string) ($__ld['desc'] ?? ''));
?>
                    <div data-aos="fade-up" data-aos-delay="<?php echo (int) min($__ld_i * 50, 300); ?>">
                        <div class="withu-widget withu-widget--loveday-vibrant <?php echo $__ld_past ? 'withu-widget--loveday-past' : 'withu-widget--loveday-future'; ?>">
                            <div class="withu-loveday-sup-label"><?php echo $__ld_past ? '已经' : '还有'; ?></div>
                            <!-- Decorative BG Icon -->
                            <svg class="withu-loveday-bg-icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg">
                                <?php if ($__ld_past): ?><path d="M923 283.6a260.04 260.04 0 0 0-56.9-82.6c-64.5-70-170.8-84-245.5-32.9L512 216.7l-108.6-48.6c-74.7-51.1-181-37.1-245.5 32.9-64.5 70-79.9 174.6-44.1 262.8 33.3 82.3 98.7 151.7 185.3 227.1L512 884.2l212.9-193.3c86.6-75.4 152-144.8 185.3-227.1 35.8-88.2 20.4-192.8-44.1-262.8z" fill="currentColor"></path>
                                <?php else: ?><path d="M792 120H232a40 40 0 0 0-40 40v56c0 88.4 71.6 160 160 160 5.2 0 10.4-.2 15.6-.6 4.4 11.8 4.4 24.8 0 36.6-5.2-.4-10.4-.6-15.6-.6-88.4 0-160 71.6-160 160v56a40 40 0 0 0 40 40h560a40 40 0 0 0 40-40v-56c0-88.4-71.6-160-160-160-5.2 0-10.4.2-15.6.6-4.4 11.8-4.4 24.8 0 36.6 5.2.4 10.4.6 15.6.6 88.4 0 160-71.6 160-160v-56a40 40 0 0 0-40-40z" fill="currentColor"></path><?php endif; ?>
                            </svg>
                            <div class="withu-flex-between-center withu-loveday-content">
                                <div class="withu-flex-center-gap" tabindex="0">
                                    <div class="withu-icon-box-glass-white">
                                        <i class="ph-fill ph-heart"></i>
                                    </div>
                                    <div class="withu-loveday-copy">
                                        <div class="withu-loveday-title" data-withu-tip="<?php echo htmlspecialchars($__ld_title, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($__ld_title, ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="withu-loveday-date">
                                            <span class="withu-loveday-date-line"><?php echo $__ld_past ? '起始日：' : '目标日：'; ?><?php echo htmlspecialchars($__ld_date, ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="withu-text-right">
                                    <div class="withu-loveday-count"><?php echo (int) max($__ld_count, 0); ?><span class="withu-loveday-unit">天</span></div>
<?php if ($__ld_desc !== ''): ?>
                                    <div class="withu-loveday-lunar-inline"><?php echo htmlspecialchars($__ld_desc, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
<?php endforeach; ?>
<?php endif; ?>

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
<?php if (empty($homeArticles)): ?>
                    <div class="withu-no-data withu-no-data--search">
                        <div class="withu-no-data-wrap"><div class="withu-no-data-content">
                            <div class="withu-no-data-icon" aria-hidden="true"><i class="ph-fill ph-notebook"></i></div>
                            <h3 class="withu-no-data-title">还没有点滴记录</h3>
                            <p class="withu-no-data-desc">去后台写下第一篇日常吧～</p>
                        </div></div>
                    </div>
<?php else: ?>
<?php foreach ($homeArticles as $__i => $__a): ?>
                    <div data-aos="fade-up" data-aos-delay="<?php echo (int) $__i * 50; ?>">
                            <a href="page.php?id=<?php echo (int) $__a['id']; ?>"
                                class="withu-journal-card withu-journal-card--link">
                                <div class="withu-watermark">DAY <?php echo $__a['day_no'] !== null ? (int) $__a['day_no'] : '—'; ?></div>

                                <div class="withu-journal-header">
                                    <div class="withu-journal-user">
                                        <img data-src="<?php echo htmlspecialchars($__a['avatar'], ENT_QUOTES, 'UTF-8'); ?>" class="withu-journal-avatar lazy">
                                        <div>
                                            <div class="withu-font-sm-bold"><?php echo htmlspecialchars($__a['author'] !== '' ? $__a['author'] : 'withU', ENT_QUOTES, 'UTF-8'); ?></div>
                                            <div class="withu-journal-meta"><?php echo htmlspecialchars($__a['date'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="withu-journal-content">
                                    <h3 class="withu-journal-title withu-journal-title-text">
                                        <?php echo htmlspecialchars($__a['title'], ENT_QUOTES, 'UTF-8'); ?>
                                    </h3>
                                    <?php if ($__a['excerpt'] !== ''): ?>
                                        <p class="withu-journal-body withu-journal-body-clamp">
                                            <?php echo htmlspecialchars($__a['excerpt'], ENT_QUOTES, 'UTF-8'); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <div class="withu-journal-footer">
                                    <div class="withu-flex-gap-sm">
                                        <?php if ($__a['city'] !== ''): ?>
                                            <span class="withu-chip withu-chip--light"><i class="ph-bold ph-map-pin"></i>
                                                <?php echo htmlspecialchars($__a['city'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                        <?php if ($__a['weather'] !== ''): ?>
                                            <span class="withu-chip withu-chip--light"><i class="ph-bold <?php echo htmlspecialchars($__a['weather_icon'] !== '' ? $__a['weather_icon'] : 'ph-cloud-sun', ENT_QUOTES, 'UTF-8'); ?>"></i>
                                                <?php echo htmlspecialchars($__a['weather'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                        <?php if ($__a['mood'] !== ''): ?>
                                            <span class="withu-chip withu-chip--light"><i class="ph-bold <?php echo htmlspecialchars($__a['mood_icon'] !== '' ? $__a['mood_icon'] : 'ph-smiley', ENT_QUOTES, 'UTF-8'); ?>"></i>
                                                <?php echo htmlspecialchars($__a['mood'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                        <span class="withu-chip withu-chip--light"><i class="ph-bold ph-eye"></i>
                                            <?php echo (int) $__a['views']; ?></span>
                                        <span class="withu-chip withu-chip--light"><i class="ph-bold ph-heart"></i>
                                            <?php echo (int) $__a['likes']; ?></span>
                                    </div>
                                </div>
                            </a>
                            </div>
<?php endforeach; ?>
<?php endif; ?>
                </div>
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
<?php if (empty($homeAlbums)): ?>
                    <div class="withu-no-data withu-no-data--search">
                        <div class="withu-no-data-wrap"><div class="withu-no-data-content">
                            <div class="withu-no-data-icon" aria-hidden="true"><i class="ph-fill ph-image"></i></div>
                            <h3 class="withu-no-data-title">还没有相册</h3>
                            <p class="withu-no-data-desc">去后台上传第一组照片吧～</p>
                        </div></div>
                    </div>
<?php else: ?>
<?php foreach ($homeAlbums as $__i => $__al): ?>
                    <div data-aos="fade-up" data-aos-delay="<?php echo (int) $__i * 50; ?>">
                        <a href="<?php echo $__al['code'] !== '' ? 'album-detail.php?code=' . rawurlencode($__al['code']) : 'albums.php'; ?>" class="withu-mosaic-item">
                            <?php if ($__al['cover'] !== ''): ?>
                                <img data-src="<?php echo htmlspecialchars($__al['cover'], ENT_QUOTES, 'UTF-8'); ?>" class="withu-mosaic-img lazy">
                            <?php endif; ?>

                            <div class="withu-mosaic-pos-tr">
                                <div class="withu-chip--dark-glass">
                                    <?php if ($__al['city'] !== ''): ?>
                                        <span class="withu-flex-center-gap-xs"><i class="ph-fill ph-map-pin"></i>
                                            <?php echo htmlspecialchars($__al['city'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="withu-mosaic-divider"></span>
                                    <?php endif; ?>
                                    <span class="withu-flex-center-gap-xs"><i class="ph-fill ph-image"></i>
                                        <?php echo (int) $__al['count']; ?></span>
                                </div>
                            </div>

                            <div class="withu-mosaic-overlay">
                                <div class="withu-mosaic-overlay-content">
                                    <div class="withu-capsule withu-capsule--avatar withu-mosaic-avatar-mb">
                                        <img data-src="<?php echo htmlspecialchars($__al['avatar'], ENT_QUOTES, 'UTF-8'); ?>" class="withu-capsule__img lazy">
                                        <span class="withu-capsule__text withu-text-white"><?php echo htmlspecialchars($__al['author'] !== '' ? $__al['author'] : 'withU', ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>

                                    <h3 class="u-font-serif withu-mosaic-title"><?php echo htmlspecialchars($__al['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                    <?php if ($__al['date'] !== ''): ?>
                                        <div class="u-font-serif withu-mosaic-date"><?php echo htmlspecialchars($__al['date'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                        </div>
<?php endforeach; ?>
<?php endif; ?>
                </div>
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
<?php if (empty($homeMessages)): ?>
                        <div class="withu-no-data withu-no-data--search">
                            <div class="withu-no-data-wrap"><div class="withu-no-data-content">
                                <div class="withu-no-data-icon" aria-hidden="true"><i class="ph-fill ph-chat-circle-text"></i></div>
                                <h3 class="withu-no-data-title">还没有留言</h3>
                                <p class="withu-no-data-desc">成为第一个留下祝福的人吧～</p>
                            </div></div>
                        </div>
<?php else: ?>
<?php foreach ($homeMessages as $__m): ?>
                        <a href="messages.php#comment_<?php echo (int) $__m['id']; ?>" class="withu-home-message-card">
                            <div class="withu-home-message-header">
                                <img class="withu-home-message-avatar lazy" data-src="<?php echo htmlspecialchars($__m['avatar'], ENT_QUOTES, 'UTF-8'); ?>" src="<?php echo htmlspecialchars($__m['avatar'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($__m['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="withu-home-message-user-info">
                                    <div class="withu-home-message-name-row">
                                        <span class="withu-home-message-user-name"><?php echo htmlspecialchars($__m['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <span class="withu-home-message-post-time"><?php echo htmlspecialchars($__m['time'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </div>
                            <div class="withu-home-message-content"><?php echo $__m['content']; ?></div>
                            <div class="withu-home-message-divider"></div>
                            <div class="withu-home-message-footer">
                                <?php if ($__m['location'] !== ''): ?>
                                    <span class="withu-chip withu-chip--light"><i class="ph-fill ph-map-pin"></i> <?php echo htmlspecialchars($__m['location'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                                <?php if ($__m['os'] !== ''): ?>
                                    <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-devices"></i> <?php echo htmlspecialchars($__m['os'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                                <?php if ($__m['browser'] !== ''): ?>
                                    <span class="withu-chip withu-chip--light withu-chip--no-transform"><i class="ph-bold ph-globe"></i> <?php echo htmlspecialchars($__m['browser'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            </div>
                        </a>
<?php endforeach; ?>
<?php endif; ?>
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
<script src="assets/js/page-messages.js?v=20261026"></script>
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
<script src="assets/js/page-index.js?v=20261026gps"></script>
<script src="assets/js/page-timetable.js?v=20261027-weather"></script>
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
<script src="assets/js/map.js?v=20261027-loc2"></script>

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

    </style>
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
