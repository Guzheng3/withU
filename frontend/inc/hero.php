<?php
/**
 * 前台内页顶部横幅（Hero）
 * 与首页共用后台「基础信息 → 首页大图」配置：优先读取 home_banner_images，
 * 未配置或读取失败时回退到内置默认大图，避免出现灰色空白首屏。
 * 需要 inc/config.php 已加载（提供 $db / $loggedIn 等变量）。
 */

if (!function_exists('withu_normalize_banner_entry') || !function_exists('withu_home_carousel_defaults')) {
    require_once dirname(__DIR__, 2) . '/backend/app/core/helpers.php';
}

$withuHeroBanners = null;
try {
    if (isset($db) && is_object($db)) {
        $withuHeroBannerRow = $db->fetch("SELECT value FROM settings WHERE `key` = 'home_banner_images'");
        if ($withuHeroBannerRow && isset($withuHeroBannerRow['value']) && trim((string) $withuHeroBannerRow['value']) !== '') {
            $withuHeroParsed = json_decode($withuHeroBannerRow['value'], true);
            if (is_array($withuHeroParsed)) {
                $withuHeroBanners = [];
                foreach ($withuHeroParsed as $withuHeroEntry) {
                    if (is_string($withuHeroEntry) && trim($withuHeroEntry) !== '') {
                        $withuHeroBanners[] = withu_normalize_banner_entry($withuHeroEntry);
                    }
                }
            }
        }
    }
} catch (Throwable $withuHeroEx) {
    $withuHeroBanners = null;
}
if (!is_array($withuHeroBanners) || $withuHeroBanners === []) {
    $withuHeroBanners = withu_home_carousel_defaults();
}
$withuHeroBanners = array_values(array_filter($withuHeroBanners, function ($withuHeroItem) {
    return is_string($withuHeroItem) && trim($withuHeroItem) !== '';
}));
if ($withuHeroBanners === []) {
    $withuHeroBanners = ['/assets/images/home-banners/01-camping-starry-night.webp'];
}
?>
<div id="homePage" class="wrap withu-hero" data-Fullscreen>
    <ul class="list mask_black">
        <?php foreach ($withuHeroBanners as $withuHeroIndex => $withuHeroImg): ?>
            <li class="item<?php echo $withuHeroIndex === 0 ? ' active' : ''; ?>">
                <img class="lazy CarouselImage"
                     data-src="<?php echo htmlspecialchars($withuHeroImg, ENT_QUOTES, 'UTF-8'); ?>"
                     src="<?php echo htmlspecialchars($withuHeroImg, ENT_QUOTES, 'UTF-8'); ?>"
                     <?php echo $withuHeroIndex === 0 ? 'fetchpriority="high"' : ''; ?>
                     alt="" draggable="false">
            </li>
        <?php endforeach; ?>
    </ul>

    <?php include __DIR__ . '/head-avatars.php'; ?>

    <svg class="waves" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
        viewBox="0 24 150 28" preserveAspectRatio="none" shape-rendering="auto" aria-hidden="true">
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
        <?php foreach ($withuHeroBanners as $withuHeroIndex => $withuHeroImg): ?>
            <li class="point<?php echo $withuHeroIndex === 0 ? ' active' : ''; ?>" data-index="<?php echo $withuHeroIndex; ?>"></li>
        <?php endforeach; ?>
    </ul>
</div>
