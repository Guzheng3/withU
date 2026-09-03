<?php
// 新版后台 - 系统设置（移动端优先）
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/helpers.php';

$auth = new Auth();
$auth->requireLogin();
$db          = Database::getInstance();
$currentUser = $auth->getCurrentUser();

$error   = '';
$success = '';

// 读取当前设置
$settings     = $db->fetchAll("SELECT `key`, `value` FROM settings");
$settingsData = [];
foreach ($settings as $setting) {
    $settingsData[$setting['key']] = $setting['value'];
}

// PRG 成功提示
if (isset($_GET['success']) && $_GET['success'] === '1') {
    $success = '设置保存成功';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    // 高级设置内联功能动作（安全审核/信任设备/评论黑名单/图片工具）由各自面板片段处理，
    // 这里跳过系统设置本身的保存逻辑，避免误写 settings 表或触发 PRG 跳转。
    $advancedActionPost = isset($_POST['single_id']) || isset($_POST['bulk_submit'])
        || isset($_POST['delete_id']) || isset($_POST['add_ip'])
        || isset($_POST['mode'])
        || (isset($_POST['action']) && in_array((string)$_POST['action'], ['approved', 'blocked', 'ignored'], true));

    if (!$advancedActionPost) {

    // 如果已有首页大图并使用旧目录 banners，则删除旧文件并清空设置，让用户重新上传
    if (!empty($settingsData['home_banner_image']) && strpos($settingsData['home_banner_image'], '/banners/') !== false) {
        if (strpos($settingsData['home_banner_image'], UPLOAD_URL) === 0) {
            $oldPath = str_replace(UPLOAD_URL, '', $settingsData['home_banner_image']);
            deleteFile($oldPath);
        }
        $_POST['settings']['home_banner_image'] = '';
        $settingsData['home_banner_image'] = '';
    }

    // 首页大图多图管理：home_banner_images 存 JSON 数组，
    // 待上传文件在列表中用占位符占位，按上传成功顺序依次替换
    $bannerUploadToken = '__WITHU_UPLOAD__';
    if (array_key_exists('home_banner_images', $_POST['settings'])) {
        $newBannerList = [];
        $bannerJsonRaw = trim((string)$_POST['settings']['home_banner_images']);
        if ($bannerJsonRaw !== '') {
            $bannerParsed = json_decode($bannerJsonRaw, true);
            if (is_array($bannerParsed)) {
                foreach ($bannerParsed as $bannerEntry) {
                    if (!is_string($bannerEntry)) continue;
                    $bannerEntry = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $bannerEntry));
                    if ($bannerEntry === '' || mb_strlen($bannerEntry) > 2048) continue;
                    // 待上传占位符原样保留；其余统一为可直接展示的地址（外链/根路径原样，相对路径补站点根前缀）
                    $newBannerList[] = $bannerEntry === $bannerUploadToken
                        ? $bannerEntry
                        : withu_normalize_banner_entry($bannerEntry);
                }
            }
            if (count($newBannerList) > 20) {
                $newBannerList = array_slice($newBannerList, 0, 20);
            }
        }

        // 处理多图上传（目录 hero_covers，避免命中广告拦截规则）
        if (isset($_FILES['home_banner_images']) && is_array($_FILES['home_banner_images']['name'])) {
            $bannerFileCount = count($_FILES['home_banner_images']['name']);
            for ($bannerIndex = 0; $bannerIndex < $bannerFileCount; $bannerIndex++) {
                $bannerFileError = $_FILES['home_banner_images']['error'][$bannerIndex] ?? UPLOAD_ERR_NO_FILE;
                if ($bannerFileError === UPLOAD_ERR_NO_FILE) continue;
                $bannerFileName = (string)($_FILES['home_banner_images']['name'][$bannerIndex] ?? '图片');
                if ($bannerFileError !== UPLOAD_ERR_OK) {
                    $error = '图片「' . $bannerFileName . '」上传失败，请重试。';
                    break;
                }
                $bannerUpload = uploadFile([
                    'name'     => $_FILES['home_banner_images']['name'][$bannerIndex],
                    'type'     => $_FILES['home_banner_images']['type'][$bannerIndex],
                    'tmp_name' => $_FILES['home_banner_images']['tmp_name'][$bannerIndex],
                    'error'    => $_FILES['home_banner_images']['error'][$bannerIndex],
                    'size'     => $_FILES['home_banner_images']['size'][$bannerIndex],
                ], 'hero_covers');
                if (empty($bannerUpload['success'])) {
                    $error = '图片「' . $bannerFileName . '」上传失败：' . ($bannerUpload['message'] ?? '未知错误');
                    break;
                }
                // 只保存相对路径，便于站点迁移；按占位符位置插入，保持轮播顺序
                $bannerStored = '/uploads/' . ltrim($bannerUpload['path'], '/');
                $tokenIndex = array_search($bannerUploadToken, $newBannerList, true);
                if ($tokenIndex !== false) {
                    $newBannerList[$tokenIndex] = $bannerStored;
                } else {
                    $newBannerList[] = $bannerStored;
                }
            }
        }

        // 孤儿文件清理：旧列表中被移除的本地上传文件删除（banners/ 旧目录由上方既有逻辑负责）
        $oldBannerList = [];
        if (!empty($settingsData['home_banner_images'])) {
            $oldBannerParsed = json_decode($settingsData['home_banner_images'], true);
            if (is_array($oldBannerParsed)) {
                foreach ($oldBannerParsed as $oldBannerEntry) {
                    if (is_string($oldBannerEntry) && trim($oldBannerEntry) !== '') {
                        $oldBannerList[] = trim($oldBannerEntry);
                    }
                }
            }
        }
        if (!$oldBannerList && !empty($settingsData['home_banner_image'])) {
            // 兼容旧单图数据：多图列表尚未保存过时，旧单图视为列表成员
            $oldBannerList[] = $settingsData['home_banner_image'];
        }
        foreach ($oldBannerList as $oldBannerEntry) {
            if (in_array($oldBannerEntry, $newBannerList, true)) continue;
            $oldBannerRel = $oldBannerEntry;
            if (strpos($oldBannerRel, UPLOAD_URL) === 0) {
                $oldBannerRel = str_replace(UPLOAD_URL, '', $oldBannerRel);
            }
            $oldBannerRel = ltrim($oldBannerRel, '/');
            if (strpos($oldBannerRel, 'uploads/') === 0) {
                $oldBannerRel = substr($oldBannerRel, strlen('uploads/'));
            }
            if (strpos($oldBannerRel, 'http://') === 0 || strpos($oldBannerRel, 'https://') === 0 || strpos($oldBannerRel, '//') === 0) continue;
            if (strpos($oldBannerRel, 'hero_covers/') !== 0 && strpos($oldBannerRel, 'banners/') !== 0) continue;
            if (in_array('/uploads/' . $oldBannerRel, $newBannerList, true) || in_array($oldBannerRel, $newBannerList, true)) continue;
            deleteFile($oldBannerRel);
        }

        // 保存多图列表，并同步旧单图设置为列表首项（空列表时清空，前台无大图）
        $_POST['settings']['home_banner_images'] = json_encode($newBannerList, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $_POST['settings']['home_banner_image'] = $newBannerList[0] ?? '';
    }

    if (!$error && !empty($_POST['settings']) && is_array($_POST['settings'])) {
        // 规范化布尔开关：未勾选时明确写入 '0'
        $booleanKeys = [
            'image_optimize_enabled',
            'front_webp_default',
            'video_upload_ignore_site_limit',
            'front_animation_enabled',
            'backend_animation_enabled',
            'watch_autoplay_enabled',
            'player_auto_next_enabled',
        ];
        foreach ($booleanKeys as $boolKey) {
            if (!isset($_POST['settings'][$boolKey])) {
                $_POST['settings'][$boolKey] = '0';
            }
        }

        // Theme values are kept deliberately small and strict because they
        // are emitted as CSS variables in the shared page header.
        $themePresets = ['sakura', 'mint', 'sky', 'peach', 'lemon', 'sea', 'forest', 'minimal'];
        $themePreset = (string)($_POST['settings']['theme_preset'] ?? 'sakura');
        $themeMode = 'light';
        if (!in_array($themePreset, $themePresets, true)) {
            $error = '主题预设不合法，请重新选择。';
        } else {
            $_POST['settings']['theme_preset'] = $themePreset;
            $_POST['settings']['theme_mode'] = 'light';
            $_POST['settings']['admin_ui_mode'] = 'apple';
            foreach (['primary', 'secondary', 'accent'] as $colorName) {
                $colorKey = 'theme_custom_' . $colorName;
                $color = trim((string)($_POST['settings'][$colorKey] ?? ''));
                if ($color !== '' && !preg_match('/^#[0-9a-f]{6}$/i', $color)) {
                    $error = '自定义主题颜色必须是 6 位 HEX 格式，例如 #F5B6C8。';
                    break;
                }
                $_POST['settings'][$colorKey] = strtolower($color);
            }
        }

        $watchRanges = [
            'watch_poll_interval_ms' => [300, 3000, '轮询间隔必须在 300 到 3000 毫秒之间'],
            'watch_sync_threshold_ms' => [500, 5000, '同步阈值必须在 500 到 5000 毫秒之间'],
            'watch_presence_timeout_sec' => [3, 30, '在线判定时间必须在 3 到 30 秒之间'],
            'watch_heartbeat_interval_ms' => [1000, 10000, '心跳间隔必须在 1000 到 10000 毫秒之间'],
        ];
        if (!$error) {
            foreach ($watchRanges as $watchKey => $watchRule) {
                if (!array_key_exists($watchKey, $_POST['settings'])) continue;
                $watchValue = (int)$_POST['settings'][$watchKey];
                if ($watchValue < $watchRule[0] || $watchValue > $watchRule[1]) {
                    $error = $watchRule[2];
                    break;
                }
                $_POST['settings'][$watchKey] = (string)$watchValue;
            }
        }

        // 上传大小设置单独校验（单位：MB，范围 1~50）
        if (isset($_POST['settings']['max_upload_size_mb'])) {
            $maxUploadMb = (int) $_POST['settings']['max_upload_size_mb'];
            if ($maxUploadMb < 1 || $maxUploadMb > 50) {
                $error = '单文件上传大小必须在 1MB 到 50MB 之间';
            } else {
                $_POST['settings']['max_upload_size_mb'] = (string) $maxUploadMb;
            }
        }

    }

    if (!$error && !empty($_POST['settings']) && is_array($_POST['settings'])) {
        // 恋爱开始时间：支持精确到秒（datetime-local），统一转换为 "Y-m-d H:i:s" 存入数据库
        if (array_key_exists('love_date', $_POST['settings'])) {
            $loveDateInput = trim((string) $_POST['settings']['love_date']);
            if ($loveDateInput === '') {
                $_POST['settings']['love_date'] = '';
            } else {
                // 浏览器 datetime-local 通常为 "Y-m-dTH:i" 或 "Y-m-dTH:i:s"
                // 同时兼容旧格式 "Y-m-d" / "Y-m-d H:i:s"
                $normalized = str_replace(' ', 'T', $loveDateInput);
                $dt = date_create($normalized);
                if ($dt instanceof DateTime) {
                    $_POST['settings']['love_date'] = $dt->format('Y-m-d H:i:s');
                } else {
                    $error = '恋爱开始时间格式不正确，请重新选择。';
                }
            }
        }
    }

    if (!$error && !empty($_POST['settings']) && is_array($_POST['settings'])) {
        foreach ($_POST['settings'] as $key => $value) {
            $existing = $db->fetch("SELECT id FROM settings WHERE `key` = :key", ['key' => $key]);

            if ($existing) {
                $db->update('settings', [
                    'value'      => $value,
                    'updated_at' => date('Y-m-d H:i:s'),
                ], '`key` = :key', ['key' => $key]);
            } else {
                $db->insert('settings', [
                    'key'        => $key,
                    'value'      => $value,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    if (!$error) {
        header('Location: settings.php?success=1');
        exit;
    }

    // 有错误则重新加载最新设置用于展示
    $settings     = $db->fetchAll("SELECT `key`, `value` FROM settings");
    $settingsData = [];
    foreach ($settings as $setting) {
        $settingsData[$setting['key']] = $setting['value'];
    }

    } // if (!$advancedActionPost)
}

$adminPage = 'settings';
$adminNarrow = true;

// 分段定位：?section=theme / ?section=advanced 兼容旧链接/书签直达，其余默认"基础信息"
// ?section=advanced 为独立视图：顶部展示高级功能入口 tab（安全审核等 5 项），
// 点击原地切换并压栈（pushState，支持前进/后退），该视图无表单项、底部吸附保存栏隐藏
$sectionParam = (string)($_GET['section'] ?? '');
$activeTab = in_array($sectionParam, ['theme', 'advanced'], true) ? $sectionParam : 'basic';
$settingsTabs = [
    'basic'    => ['icon' => 'ti-settings',    'label' => '基础信息'],
    'together' => ['icon' => 'ti-users',       'label' => '一起看'],
    'theme'    => ['icon' => 'ti-palette',     'label' => '主题外观'],
    'upload'   => ['icon' => 'ti-upload',      'label' => '上传与其他'],
    'site'     => ['icon' => 'ti-info-circle', 'label' => '站点信息'],
    'advanced' => ['icon' => 'ti-shield',      'label' => '高级设置'],
];

// 高级设置独立功能的入口数据（key 用于 tab 定位 / 压栈 URL ?tab=）
$advancedEntries = [
    ['key' => 'moderation', 'href' => '/admin/moderation.php',                     'icon' => 'ti-shield',          'label' => '安全审核',  'desc' => '规则拦截、待复核审核记录'],
    ['key' => 'devices',    'href' => '/admin/devices.php',                        'icon' => 'ti-device-mobile',   'label' => '信任设备',  'desc' => '已信任的登录设备管理与解绑'],
    ['key' => 'blacklist',  'href' => '/admin/comment_ip_blacklist.php',           'icon' => 'ti-user-x',          'label' => '评论黑名单', 'desc' => '禁止发表评论或留言的 IP 黑名单'],
    ['key' => 'optimize',   'href' => '/admin/tools_image_stats.php?tab=optimize', 'icon' => 'ti-arrows-diagonal', 'label' => '图片补齐',  'desc' => '为旧数据一键补齐缩略图 / WebP / 视频转码'],
    ['key' => 'stats',      'href' => '/admin/tools_image_stats.php',              'icon' => 'ti-chart-bar',       'label' => '图片统计',  'desc' => '图片体积与压缩占比一览'],
];
$advancedKeys = array_column($advancedEntries, 'key');
$advancedActiveKey = (string)($_GET['tab'] ?? '');
if (!in_array($advancedActiveKey, $advancedKeys, true)) {
    $advancedActiveKey = $advancedKeys[0] ?? '';
}

// tab -> 面板映射：图片补齐 / 图片统计共用同一个「图片工具」面板（原工具页本就包含统计与补齐两部分）
$advancedPanelMap = [
    'moderation' => 'moderation',
    'devices'    => 'devices',
    'blacklist'  => 'blacklist',
    'optimize'   => 'tools',
    'stats'      => 'tools',
];
$advancedActivePanelKey = $advancedPanelMap[$advancedActiveKey] ?? 'moderation';

// 高级设置内联片段（函数定义）：独立页与 settings.php 高级设置面板共用
require_once __DIR__ . '/_advanced/moderation.php';
require_once __DIR__ . '/_advanced/devices.php';
require_once __DIR__ . '/_advanced/comment_ip_blacklist.php';
require_once __DIR__ . '/_advanced/tools_image_stats.php';

include __DIR__ . '/header.php';
?>
<style>
/* 滚动条统一为前台首页同款细滑块 */
::-webkit-scrollbar{width:6px;height:6px}
::-webkit-scrollbar-track{background:rgba(135,135,135,.1)}
::-webkit-scrollbar-thumb{background:rgba(135,135,135,.4);border-radius:10px}
::-webkit-scrollbar-thumb:hover{background:#727272}
::-webkit-scrollbar-corner{background:unset}

/* 首页大图多图管理 */
.banner-image-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(108px,1fr));gap:.5rem}
.banner-image-item{position:relative;border-radius:.75rem;overflow:hidden;border:1px solid rgba(148,163,184,.45);background:rgba(148,163,184,.12);aspect-ratio:16/9}
.banner-image-item img{width:100%;height:100%;object-fit:cover;display:block;cursor:zoom-in}
.banner-image-item.is-pending img{opacity:.88}
.banner-image-badge{position:absolute;top:4px;left:4px;font-size:.62rem;line-height:1;padding:.22rem .4rem;border-radius:.4rem;background:rgba(15,23,42,.62);color:#fff;pointer-events:none}
.banner-image-badge.banner-image-badge-example{background:rgba(244,114,182,.85)}
.banner-image-actions{position:absolute;bottom:4px;right:4px;display:flex;gap:.25rem}
.banner-image-btn{width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;border:none;border-radius:.45rem;background:rgba(15,23,42,.55);color:#fff;font-size:.72rem;cursor:pointer;padding:0}
.banner-image-btn:hover{background:rgba(15,23,42,.82)}
.banner-image-btn[disabled]{opacity:.35;cursor:default}
.banner-image-btn.banner-image-btn-danger:hover{background:#dc2626}
.banner-add-btn{display:inline-flex;align-items:center;gap:.3rem;padding:.45rem .8rem;border-radius:.6rem;border:1px dashed rgba(148,163,184,.7);background:rgba(148,163,184,.12);font-size:.82rem;cursor:pointer;color:inherit}
.banner-add-btn:hover{background:rgba(148,163,184,.25)}
.banner-add-btn i{font-size:.9rem}

/* 首页大图展开预览 */
.banner-lightbox{position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center;background:rgba(10,12,20,.88);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px)}
.banner-lightbox[hidden]{display:none}
.banner-lightbox img{max-width:min(92vw,1200px);max-height:86vh;border-radius:.75rem;box-shadow:0 24px 80px rgba(0,0,0,.5);object-fit:contain}
.banner-lightbox-close{position:absolute;top:14px;right:14px;width:38px;height:38px;display:flex;align-items:center;justify-content:center;border:none;border-radius:50%;background:rgba(255,255,255,.14);color:#fff;font-size:1.05rem;cursor:pointer}
.banner-lightbox-close:hover{background:rgba(255,255,255,.28)}
.banner-lightbox-nav{position:absolute;top:50%;transform:translateY(-50%);width:40px;height:40px;display:flex;align-items:center;justify-content:center;border:none;border-radius:50%;background:rgba(255,255,255,.14);color:#fff;font-size:1.2rem;cursor:pointer}
.banner-lightbox-nav:hover{background:rgba(255,255,255,.28)}
.banner-lightbox-prev{left:18px}
.banner-lightbox-next{right:18px}
.banner-lightbox-nav[hidden]{display:none}
.banner-lightbox-count{position:absolute;bottom:16px;left:50%;transform:translateX(-50%);color:rgba(255,255,255,.85);font-size:.8rem;letter-spacing:.05em}
</style>

    <section class="admin-page-title">
        <?php if ($activeTab === 'advanced'): ?>
            <h1>高级设置</h1>
            <p>安全审核、图片统计等独立功能入口</p>
        <?php else: ?>
            <h1>系统设置</h1>
            <p>管理站点基础信息、首页展示和备案信息</p>
        <?php endif; ?>
    </section>

    <?php if ($error): ?>
        <div class="admin-card" style="margin-bottom:0.75rem;background:rgba(248,113,113,0.05);border:1px solid rgba(248,113,113,0.35);">
            <div style="display:flex;align-items:center;gap:0.5rem;color:#b91c1c;font-size:0.9rem;">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo e($error); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="admin-card" style="margin-bottom:0.75rem;background:rgba(34,197,94,0.05);border:1px solid rgba(34,197,94,0.35);">
            <div style="display:flex;align-items:center;gap:0.5rem;color:#15803d;font-size:0.9rem;">
                <i class="fas fa-check-circle"></i>
                <span><?php echo e($success); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" novalidate>
        <?php echo csrf_field(); ?>

        <?php if ($activeTab === 'advanced'): ?>
        <?php // 高级设置视图：顶部为 5 个独立功能入口 tab（原地切换、压栈保留 tab 栏） ?>
        <nav class="settings-tabs" role="tablist" aria-label="高级功能">
            <?php foreach ($advancedEntries as $entry): ?>
                <button
                    type="button"
                    class="settings-tab<?php echo $advancedActiveKey === $entry['key'] ? ' is-active' : ''; ?>"
                    role="tab"
                    id="advtab-<?php echo $entry['key']; ?>"
                    aria-controls="advpanel-<?php echo $advancedPanelMap[$entry['key']]; ?>"
                    aria-selected="<?php echo $advancedActiveKey === $entry['key'] ? 'true' : 'false'; ?>"
                    tabindex="<?php echo $advancedActiveKey === $entry['key'] ? 0 : -1; ?>"
                    data-adv-tab="<?php echo $entry['key']; ?>">
                    <i class="ti <?php echo $entry['icon']; ?>" aria-hidden="true"></i><?php echo $entry['label']; ?>
                </button>
            <?php endforeach; ?>
        </nav>
        <?php else: ?>
        <?php // 系统设置分段导航：基础信息 / 一起看 / 主题外观 / 上传 / 站点信息 / 高级设置（高级设置直达独立入口汇总） ?>
        <nav class="settings-tabs" role="tablist" aria-label="设置分段">
            <?php foreach ($settingsTabs as $tabKey => $tabMeta): ?>
                <button
                    type="button"
                    class="settings-tab<?php echo $activeTab === $tabKey ? ' is-active' : ''; ?>"
                    role="tab"
                    id="tab-<?php echo $tabKey; ?>"
                    aria-controls="<?php echo $tabKey; ?>-settings"
                    aria-selected="<?php echo $activeTab === $tabKey ? 'true' : 'false'; ?>"
                    tabindex="<?php echo $activeTab === $tabKey ? 0 : -1; ?>">
                    <i class="ti <?php echo $tabMeta['icon']; ?>" aria-hidden="true"></i><?php echo $tabMeta['label']; ?>
                </button>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>

        <section class="admin-grid settings-panel" id="basic-settings" role="tabpanel" aria-labelledby="tab-basic" <?php echo $activeTab === 'basic' ? '' : 'hidden'; ?> style="margin-bottom:0.75rem;">
            <div class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <div class="admin-card-title">
                        <i class="ti ti-settings" aria-hidden="true"></i>基础信息
                        <button type="button" class="admin-help-toggle" title="查看说明" aria-label="查看说明" aria-expanded="false"><i class="ti ti-info-circle"></i></button>
                    </div>
                </div>
            </div>
            <div class="admin-card-help">
                <div class="admin-card-subtitle">站点标题、描述与天气设置</div>
            </div>

                <div class="form-group" style="margin-bottom:0.75rem;">
                    <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">网站标题</label>
                    <input
                        type="text"
                        name="settings[site_title]"
                        value="<?php echo e($settingsData['site_title'] ?? SITE_NAME); ?>"
                        style="width:100%;padding:0.55rem 0.75rem;border-radius:0.75rem;border:1px solid rgba(148,163,184,0.6);font-size:0.9rem;">
                </div>

                <div class="form-group">
                    <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">网站描述</label>
                    <?php $siteDescriptionValue = (string)($settingsData['site_description'] ?? ''); if ($siteDescriptionValue === '' || $siteDescriptionValue === '记录我们的小小点滴') $siteDescriptionValue = '从相遇到相守，把每一个平凡的日子都写成只属于我们的浪漫。'; ?>
                    <textarea
                        name="settings[site_description]"
                        style="width:100%;min-height:80px;padding:0.55rem 0.75rem;border-radius:0.75rem;border:1px solid rgba(148,163,184,0.6);font-size:0.9rem;resize:vertical;"><?php echo e($siteDescriptionValue); ?></textarea>
                </div>

                <div class="form-group" style="margin-bottom:0.75rem;">
                    <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">天气 API Key（高德）</label>
                    <input type="text" name="settings[amap_weather_key]" value="<?php echo e($settingsData['amap_weather_key'] ?? ''); ?>" placeholder="高德 Web服务 Key，用于天气查询，可留空" style="width:100%;padding:.55rem .75rem;border-radius:.75rem;border:1px solid rgba(148,163,184,.6);font-size:.9rem;">
                    <small style="color:#888;">使用高德地图同款 Key 即可，留空则使用 IP 定位天气</small>
                </div>

                <div class="form-group" style="margin-bottom:0.75rem;">
                    <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">天气定位</label>
                    <?php
                    $locLat  = $settingsData['weather_loc_lat'] ?? '';
                    $locLng  = $settingsData['weather_loc_lng'] ?? '';
                    $locName = $settingsData['weather_loc_name'] ?? '';
                    $hasLoc  = $locLat !== '' && $locLng !== '';
                    ?>
                    <div style="position:relative;">
                        <input type="text" id="weather_loc_search" placeholder="搜索城市或地址..." value="<?php echo e($locName); ?>" autocomplete="off"
                               style="width:100%;padding:.55rem .75rem;border-radius:.75rem;border:1px solid rgba(148,163,184,.6);font-size:.9rem;">
                        <div id="weather_loc_results" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:100;background:#fff;border:1px solid rgba(148,163,184,.3);border-radius:.75rem;max-height:240px;overflow-y:auto;box-shadow:0 8px 24px rgba(0,0,0,.12);"></div>
                    </div>
                    <input type="hidden" name="settings[weather_loc_lat]" id="weather_loc_lat" value="<?php echo e($locLat); ?>">
                    <input type="hidden" name="settings[weather_loc_lng]" id="weather_loc_lng" value="<?php echo e($locLng); ?>">
                    <input type="hidden" name="settings[weather_loc_name]" id="weather_loc_name" value="<?php echo e($locName); ?>">
                    <div id="weather_loc_selected" style="margin-top:.35rem;font-size:.8rem;color:<?php echo $hasLoc ? '#16a34a' : '#888'; ?>;">
                        <?php if ($hasLoc): ?>
                            ✅ 已选择：<?php echo e($locName); ?>（<?php echo e($locLat); ?>, <?php echo e($locLng); ?>）
                        <?php else: ?>
                            未选择位置，天气将使用默认数据
                        <?php endif; ?>
                    </div>
                    <small style="color:#888;">搜索并选择城市，天气和地图将使用此位置</small>
                </div>

            </div>

            <div class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <div class="admin-card-title">
                        <i class="ti ti-heart" aria-hidden="true"></i>恋爱与首页
                        <button type="button" class="admin-help-toggle" title="查看说明" aria-label="查看说明" aria-expanded="false"><i class="ti ti-info-circle"></i></button>
                    </div>
                </div>
            </div>
            <div class="admin-card-help">
                <div class="admin-card-subtitle">恋爱开始日期与首页大图</div>
            </div>

                <div class="form-group" style="margin-bottom:0.75rem;">
                    <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">恋爱开始时间</label>
                    <?php
                    $loveDateRaw = $settingsData['love_date'] ?? '';
                    $loveDateDate = '';
                    $loveDateTime = '';
                    if ($loveDateRaw !== '') {
                        // 兼容旧数据：仅日期 或 带时间的 "Y-m-d H:i:s"
                        $normalized = str_replace(' ', 'T', $loveDateRaw);
                        $dt = date_create($normalized);
                        if ($dt instanceof DateTime) {
                            $loveDateDate = $dt->format('Y-m-d');
                            $loveDateTime = $dt->format('H:i:s');
                        }
                    }
                    ?>
                    <div class="settings-date-time-row" data-love-date-fields>
                        <label><span>年</span><input type="text" inputmode="numeric" maxlength="4" data-love-part="year" value="<?php echo e($loveDateDate !== '' ? substr($loveDateDate, 0, 4) : ''); ?>" placeholder="YYYY" aria-label="年份"></label>
                        <label><span>月</span><input type="text" inputmode="numeric" maxlength="2" data-love-part="month" value="<?php echo e($loveDateDate !== '' ? substr($loveDateDate, 5, 2) : ''); ?>" placeholder="MM" aria-label="月份"></label>
                        <label><span>日</span><input type="text" inputmode="numeric" maxlength="2" data-love-part="day" value="<?php echo e($loveDateDate !== '' ? substr($loveDateDate, 8, 2) : ''); ?>" placeholder="DD" aria-label="日期"></label>
                        <i aria-hidden="true">·</i>
                        <label><span>时</span><input type="text" inputmode="numeric" maxlength="2" data-love-part="hour" value="<?php echo e($loveDateTime !== '' ? substr($loveDateTime, 0, 2) : ''); ?>" placeholder="HH" aria-label="小时"></label>
                        <label><span>分</span><input type="text" inputmode="numeric" maxlength="2" data-love-part="minute" value="<?php echo e($loveDateTime !== '' ? substr($loveDateTime, 3, 2) : ''); ?>" placeholder="MM" aria-label="分钟"></label>
                        <label><span>秒</span><input type="text" inputmode="numeric" maxlength="2" data-love-part="second" value="<?php echo e($loveDateTime !== '' ? substr($loveDateTime, 6, 2) : ''); ?>" placeholder="SS" aria-label="秒"></label>
                        <input type="hidden" name="settings[love_date]" id="loveDateValue" value="<?php echo e($loveDateDate !== '' ? $loveDateDate . 'T' . $loveDateTime : ''); ?>">
                    </div>
                    <div style="margin-top:0.2rem;font-size:0.78rem;color:var(--text-light);">
                        用于计算“在一起多少天”，支持精确到秒。留空时按当前日期开始计算。
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:0.75rem;">
                    <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">首页大图（支持多张轮播）</label>
                    <?php
                    $bannerMaxBytes = get_max_upload_size_bytes();
                    $bannerMaxMb    = round($bannerMaxBytes / 1024 / 1024, 1);

                    // 多图列表：优先读取已保存的 JSON；否则把当前单图（或默认大图）作为示例图片导入
                    $isLegacyBannerImport = false;
                    $homeBannerListSaved = array_key_exists('home_banner_images', $settingsData) ? (string)$settingsData['home_banner_images'] : '';
                    if ($homeBannerListSaved !== '') {
                        $homeBannerList = json_decode($homeBannerListSaved, true);
                        if (!is_array($homeBannerList)) $homeBannerList = [];
                        $homeBannerList = array_values(array_filter($homeBannerList, function ($v) {
                            return is_string($v) && trim($v) !== '';
                        }));
                    } else {
                        $isLegacyBannerImport = true;
                        // 尚未保存过多图设置：把当前前台首页轮播使用的内置默认图作为示例导入
                        $homeBannerList = withu_home_carousel_defaults();
                    }

                    // 存储值（相对路径 / URL）与展示地址分开：相对路径在前台补全 uploads 前缀
                    $homeBannerListJson = json_encode($homeBannerList, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    $homeBannerViews = array_map(function ($entry) {
                        if (strpos($entry, 'http://') === 0 || strpos($entry, 'https://') === 0 || strpos($entry, '//') === 0 || strpos($entry, '/') === 0) {
                            return $entry;
                        }
                        return UPLOAD_URL . $entry;
                    }, $homeBannerList);
                    $homeBannerViewsJson = json_encode($homeBannerViews, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    ?>
                    <p style="margin:0 0 0.5rem;font-size:0.78rem;color:var(--text-light);">
                        此处与前台首页轮播对接：添加多张图片后，首页大图将按下方顺序自动轮播；点击缩略图可展开预览。
                    </p>
                    <?php if ($isLegacyBannerImport && !empty($homeBannerList)): ?>
                    <div style="display:flex;align-items:flex-start;gap:0.4rem;margin:0 0 0.55rem;padding:0.5rem 0.65rem;border-radius:0.6rem;background:rgba(244,114,182,0.07);border:1px dashed rgba(244,114,182,0.4);font-size:0.78rem;line-height:1.55;color:var(--text-light);">
                        <i class="ti ti-info-circle" aria-hidden="true" style="flex-shrink:0;margin-top:0.1rem;color:#ec4899;"></i>
                        <span>这些图不是在这里上传的：尚未保存过首页大图设置时，系统会把前台首页轮播当前正在使用的内置默认大图（共 <?php echo count($homeBannerList); ?> 张，来自站内相册 Lovefolder 目录）自动导入为编辑起点，即标有「示例」角标的图片。可随意删减、替换或排序，点「保存设置」后才会固化为正式配置。</span>
                    </div>
                    <?php endif; ?>
                    <div class="banner-image-list" id="bannerImageList" data-views="<?php echo e($homeBannerViewsJson); ?>"<?php echo $isLegacyBannerImport && !empty($homeBannerList) ? ' data-legacy-example="1"' : ''; ?>></div>
                    <div style="display:flex;gap:0.4rem;flex-wrap:wrap;margin-top:0.5rem;">
                        <button type="button" id="bannerPickBtn" class="banner-add-btn"><i class="ti ti-upload" aria-hidden="true"></i>本地上传</button>
                        <input type="file" id="bannerFileInput" name="home_banner_images[]" accept="image/*" multiple hidden>
                        <input type="text" id="bannerUrlInput" placeholder="或粘贴图片地址（URL）后点添加" style="flex:1 1 190px;min-width:0;padding:0.45rem 0.6rem;border-radius:0.6rem;border:1px solid rgba(148,163,184,0.6);font-size:0.82rem;">
                        <button type="button" id="bannerAddUrlBtn" class="banner-add-btn"><i class="ti ti-plus" aria-hidden="true"></i>添加</button>
                    </div>
                    <input type="hidden" name="settings[home_banner_images]" id="bannerImagesInput" value="<?php echo e($homeBannerListJson); ?>">
                    <div style="margin-top:0.35rem;font-size:0.78rem;color:var(--text-light);">
                        最多 20 张，可用箭头调整轮播顺序；建议横向大图、宽度不小于 1200 像素，单文件最大约 <?php echo $bannerMaxMb; ?>MB。上传的图片保存在 uploads/hero_covers，从列表移除并保存后会自动删除对应文件；清空全部图片则前台不显示大图。
                    </div>
                </div>

                <!-- 首页大图展开预览遮罩 -->
                <div class="banner-lightbox" id="bannerLightbox" hidden>
                    <button type="button" class="banner-lightbox-close" id="bannerLightboxClose" aria-label="关闭预览"><i class="ti ti-x" aria-hidden="true"></i></button>
                    <button type="button" class="banner-lightbox-nav banner-lightbox-prev" id="bannerLightboxPrev" aria-label="上一张"><i class="ti ti-chevron-left" aria-hidden="true"></i></button>
                    <img id="bannerLightboxImg" alt="大图预览">
                    <button type="button" class="banner-lightbox-nav banner-lightbox-next" id="bannerLightboxNext" aria-label="下一张"><i class="ti ti-chevron-right" aria-hidden="true"></i></button>
                    <div class="banner-lightbox-count" id="bannerLightboxCount"></div>
                </div>
            </div>
        </section>

        <?php
        $watchPollIntervalValue = (int)($settingsData['watch_poll_interval_ms'] ?? 500);
        $watchSyncThresholdValue = (int)($settingsData['watch_sync_threshold_ms'] ?? 1000);
        $watchPresenceTimeoutValue = (int)($settingsData['watch_presence_timeout_sec'] ?? 8);
        $watchHeartbeatValue = (int)($settingsData['watch_heartbeat_interval_ms'] ?? 2500);
        $watchAutoplayValue = $settingsData['watch_autoplay_enabled'] ?? '1';
        $playerAutoNextValue = $settingsData['player_auto_next_enabled'] ?? '1';
        ?>
        <section class="admin-grid settings-panel" id="together-settings" role="tabpanel" aria-labelledby="tab-together" <?php echo $activeTab === 'together' ? '' : 'hidden'; ?> style="margin-bottom:0.75rem;">
            <div class="admin-card">
                <div class="admin-card-header"><div><div class="admin-card-title"><i class="ti ti-users" aria-hidden="true"></i>一起看 <button type="button" class="admin-help-toggle" title="查看说明" aria-label="查看说明" aria-expanded="false"><i class="ti ti-info-circle"></i></button></div></div></div><div class="admin-card-help"><div class="admin-card-subtitle">同步、在线状态和自动播放</div></div>
                <div class="form-group" style="margin-bottom:.65rem;"><label style="display:block;font-size:.85rem;margin-bottom:.25rem;">状态轮询间隔（毫秒）</label><input type="number" name="settings[watch_poll_interval_ms]" min="300" max="3000" value="<?php echo $watchPollIntervalValue; ?>" style="width:100%;padding:.55rem .75rem;border-radius:.75rem;border:1px solid rgba(148,163,184,.6);font-size:.9rem;"><div style="margin-top:.2rem;font-size:.78rem;color:var(--text-light);">默认 500ms，只读取房间状态，不会因此修改播放进度。</div></div>
                <div class="form-group" style="margin-bottom:.65rem;"><label style="display:block;font-size:.85rem;margin-bottom:.25rem;">偏差校正阈值（毫秒）</label><input type="number" name="settings[watch_sync_threshold_ms]" min="500" max="5000" value="<?php echo $watchSyncThresholdValue; ?>" style="width:100%;padding:.55rem .75rem;border-radius:.75rem;border:1px solid rgba(148,163,184,.6);font-size:.9rem;"><div style="margin-top:.2rem;font-size:.78rem;color:var(--text-light);">小偏差使用短暂倍速追赶，大偏差才直接跳转。</div></div>
            </div>
            <div class="admin-card">
                <div class="admin-card-header"><div><div class="admin-card-title"><i class="ti ti-player-play" aria-hidden="true"></i>一起看体验 <button type="button" class="admin-help-toggle" title="查看说明" aria-label="查看说明" aria-expanded="false"><i class="ti ti-info-circle"></i></button></div></div></div><div class="admin-card-help"><div class="admin-card-subtitle">在线判定、心跳与进入播放</div></div>
                <div class="form-group" style="margin-bottom:.65rem;"><label style="display:block;font-size:.85rem;margin-bottom:.25rem;">在线判定时间（秒）</label><input type="number" name="settings[watch_presence_timeout_sec]" min="3" max="30" value="<?php echo $watchPresenceTimeoutValue; ?>" style="width:100%;padding:.55rem .75rem;border-radius:.75rem;border:1px solid rgba(148,163,184,.6);font-size:.9rem;"></div>
                <div class="form-group" style="margin-bottom:.65rem;"><label style="display:block;font-size:.85rem;margin-bottom:.25rem;">心跳间隔（毫秒）</label><input type="number" name="settings[watch_heartbeat_interval_ms]" min="1000" max="10000" value="<?php echo $watchHeartbeatValue; ?>" style="width:100%;padding:.55rem .75rem;border-radius:.75rem;border:1px solid rgba(148,163,184,.6);font-size:.9rem;"></div>
                <label class="switch"><input type="checkbox" name="settings[watch_autoplay_enabled]" value="1" <?php echo $watchAutoplayValue === '1' ? 'checked' : ''; ?>><span class="switch-track"><span class="switch-thumb"></span></span><span class="switch-label">换集 / 换剧自动播放</span></label>
                <p style="margin:0.25rem 0 0;font-size:0.78rem;color:var(--text-light);">切换选集、换剧或首次进入播放页时自动开始播放。</p>
                <label class="switch" style="margin-top:.65rem"><input type="checkbox" name="settings[player_auto_next_enabled]" value="1" <?php echo $playerAutoNextValue === '1' ? 'checked' : ''; ?>><span class="switch-track"><span class="switch-thumb"></span></span><span class="switch-label">自动下一集</span></label>
                <p style="margin:0.25rem 0 0;font-size:0.78rem;color:var(--text-light);">当前一集播放结束后，自动切换并播放下一集。</p>
            </div>
        </section>

        <?php
        $themePresetValue = $settingsData['theme_preset'] ?? 'sakura';
        if ($themePresetValue === 'pastel-couple') $themePresetValue = 'sakura';
        $themeModeValue = 'light';
        // 空字符串也算未自定义；?? 不处理空串，空值进入取色器会回退成黑色色块
        $themeCustomPrimary = trim((string)($settingsData['theme_custom_primary'] ?? '')) ?: '#F5B6C8';
        $themeCustomSecondary = trim((string)($settingsData['theme_custom_secondary'] ?? '')) ?: '#B9E3D0';
        $themeCustomAccent = trim((string)($settingsData['theme_custom_accent'] ?? '')) ?: '#B8DDF2';
        // 后台界面固定使用透粉玻璃（apple）模式，物理移除 current 模式
        $adminUiModeValue = 'apple';
        ?>
        <section class="admin-grid settings-panel" id="theme-settings" role="tabpanel" aria-labelledby="tab-theme" <?php echo $activeTab === 'theme' ? '' : 'hidden'; ?> style="margin-bottom:0.75rem;">
            <div class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <div class="admin-card-title">
                        <i class="ti ti-palette" aria-hidden="true"></i>主题与外观
                        <button type="button" class="admin-help-toggle" title="查看说明" aria-label="查看说明" aria-expanded="false"><i class="ti ti-info-circle"></i></button>
                    </div>
                </div>
            </div>
            <div class="admin-card-help">
                <div class="admin-card-subtitle">主站、后台和播放器共用的颜色主题</div>
            </div>
                <div class="form-group" style="margin-bottom:0.75rem;">
                    <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">主题预设</label>
                    <select name="settings[theme_preset]" id="themePreset" style="width:100%;padding:0.55rem 0.75rem;border-radius:0.75rem;border:1px solid rgba(148,163,184,0.6);font-size:0.9rem;">
                        <?php $themeOptions = ['sakura' => '樱花粉', 'mint' => '薄荷绿', 'sky' => '晴空蓝', 'peach' => '蜜桃橙', 'lemon' => '奶油黄', 'sea' => '海盐青', 'forest' => '森林浅色', 'minimal' => '黑白极简']; foreach ($themeOptions as $value => $label): ?>
                            <option value="<?php echo e($value); ?>" <?php echo $themePresetValue === $value ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0.75rem;">
                    <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">配色预览</label>
                    <input type="hidden" name="settings[theme_mode]" value="light">
                    <div class="theme-preview-strip" id="themePreviewStrip" aria-label="主题预览">
                        <span></span><span></span><span></span><span></span>
                    </div>
                </div>
                <hr style="border:none;border-top:1px dashed rgba(148,163,184,0.5);margin:0.9rem 0;">
                <div class="settings-static-list">
                    <div class="settings-static-item">
                        <span class="settings-static-name">显示模式</span>
                        <span class="settings-static-value">白天模式<small>浅色主题保持清爽明亮，图片和视频不会被滤镜改变。</small></span>
                    </div>
                    <div class="settings-static-item">
                        <span class="settings-static-name">后台界面模式</span>
                        <span class="settings-static-value">透粉玻璃<small>Apple 风格侧栏、浮层和浅粉液态玻璃材质。</small></span>
                    </div>
                </div>
            </div>
            <div class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <div class="admin-card-title">
                        <i class="ti ti-droplet" aria-hidden="true"></i>自定义强调色
                        <button type="button" class="admin-help-toggle" title="查看说明" aria-label="查看说明" aria-expanded="false"><i class="ti ti-info-circle"></i></button>
                    </div>
                </div>
            </div>
            <div class="admin-card-help">
                <div class="admin-card-subtitle">留空则使用预设主题颜色</div>
            </div>
                <div class="theme-color-grid">
                    <label><span class="theme-color-name">主色</span><input type="color" data-theme-picker="primary" value="<?php echo e($themeCustomPrimary); ?>" aria-label="选择主色"><input class="theme-hex-input" type="text" name="settings[theme_custom_primary]" value="<?php echo e($settingsData['theme_custom_primary'] ?? ''); ?>" placeholder="留空用预设" maxlength="7"></label>
                    <label><span class="theme-color-name">辅助色</span><input type="color" data-theme-picker="secondary" value="<?php echo e($themeCustomSecondary); ?>" aria-label="选择辅助色"><input class="theme-hex-input" type="text" name="settings[theme_custom_secondary]" value="<?php echo e($settingsData['theme_custom_secondary'] ?? ''); ?>" placeholder="留空用预设" maxlength="7"></label>
                    <label><span class="theme-color-name">强调色</span><input type="color" data-theme-picker="accent" value="<?php echo e($themeCustomAccent); ?>" aria-label="选择强调色"><input class="theme-hex-input" type="text" name="settings[theme_custom_accent]" value="<?php echo e($settingsData['theme_custom_accent'] ?? ''); ?>" placeholder="留空用预设" maxlength="7"></label>
                </div>
                    <p style="margin:.75rem 0 0;font-size:.78rem;color:var(--text-light);">留空时使用左侧预设配色；填写 6 位 HEX（如 #F5B6C8）后，主站、后台和播放器将使用自定义颜色。</p>
            </div>
        </section>

        <section class="admin-grid settings-panel" id="upload-settings" role="tabpanel" aria-labelledby="tab-upload" <?php echo $activeTab === 'upload' ? '' : 'hidden'; ?> style="margin-bottom:0.75rem;">
            <div class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <div class="admin-card-title">
                        <i class="ti ti-upload" aria-hidden="true"></i>上传与其他
                        <button type="button" class="admin-help-toggle" title="查看说明" aria-label="查看说明" aria-expanded="false"><i class="ti ti-info-circle"></i></button>
                    </div>
                </div>
            </div>
            <div class="admin-card-help">
                <div class="admin-card-subtitle">上传限制、WebP 副本与前台加载策略、备案号等信息</div>
            </div>

                <div class="form-group" style="margin-bottom:0.75rem;">
                    <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">WebP 副本与前台加载策略</label>
                    <?php
                    // 默认开启 WebP 副本生成（上传不再压缩原图）
                    $imageOptimizeEnabled = $settingsData['image_optimize_enabled'] ?? '1';
                    // 前台默认加载 WebP 副本（关闭后前台默认加载原图）
                    $frontWebpDefault = $settingsData['front_webp_default'] ?? '1';
                    ?>
                    <label class="switch">
                        <input
                            type="checkbox"
                            name="settings[image_optimize_enabled]"
                            value="1"
                            <?php echo $imageOptimizeEnabled === '1' ? 'checked' : ''; ?>>
                        <span class="switch-track">
                            <span class="switch-thumb"></span>
                        </span>
                        <span class="switch-label">上传时生成 WebP 副本（推荐）</span>
                    </label>
                    <p style="margin:0.25rem 0 0;font-size:0.78rem;color:var(--text-light);">
                        上传不再压缩或缩小图片，原图按原始画质完整保留；仅在支持时为 JPEG/PNG 额外生成一份同名 .webp 副本（相册图片同时生成缩略图），供前台加速加载。
                        仅对之后上传的图片生效，已有图片不受影响。
                    </p>
                    <label class="switch" style="margin-top:.55rem">
                        <input
                            type="checkbox"
                            name="settings[front_webp_default]"
                            value="1"
                            <?php echo $frontWebpDefault === '1' ? 'checked' : ''; ?>>
                        <span class="switch-track">
                            <span class="switch-thumb"></span>
                        </span>
                        <span class="switch-label">前台默认加载 WebP 副本（推荐）</span>
                    </label>
                    <p style="margin:0.25rem 0 0;font-size:0.78rem;color:var(--text-light);">
                        开启后，相册、文章、日记、纪念事件等前台图片默认加载 WebP 副本，并在查看大图时提供「查看原图」入口；关闭后前台默认直接加载原图。
                        没有生成 WebP 副本的图片会自动加载原图，不受此开关影响。
                    </p>
                </div>

                <div class="form-group" style="margin-bottom:0.75rem;">
                    <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">界面动效</label>
                    <?php $frontAnimation = $settingsData['front_animation_enabled'] ?? '1'; $backendAnimation = $settingsData['backend_animation_enabled'] ?? '0'; ?>
                    <label class="switch"><input type="checkbox" name="settings[front_animation_enabled]" value="1" <?php echo $frontAnimation === '1' ? 'checked' : ''; ?>><span class="switch-track"><span class="switch-thumb"></span></span><span class="switch-label">前台花瓣、光影与转场</span></label>
                    <label class="switch" style="margin-top:.45rem"><input type="checkbox" name="settings[backend_animation_enabled]" value="1" <?php echo $backendAnimation === '1' ? 'checked' : ''; ?>><span class="switch-track"><span class="switch-thumb"></span></span><span class="switch-label">后台动效</span></label>
                </div>

                <div class="form-group" style="margin-bottom:0.75rem;">
                    <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">单文件最大上传大小（MB）</label>
                    <?php
                    $maxUploadSizeMb = $settingsData['max_upload_size_mb'] ?? '';
                    if ($maxUploadSizeMb === '' || !is_numeric($maxUploadSizeMb)) {
                        $maxUploadSizeMb = 15;
                    }

                    // 计算服务器层面的硬上限（来自 php.ini）
                    $serverUploadLimitMb = null;
                    $serverLimits = [];
                    foreach (['upload_max_filesize', 'post_max_size'] as $iniKey) {
                        $val = ini_get($iniKey);
                        if ($val !== false && function_exists('parse_php_size_to_bytes')) {
                            $serverLimits[] = parse_php_size_to_bytes($val);
                        }
                    }
                    if (!empty($serverLimits)) {
                        $serverUploadLimitMb = round(min($serverLimits) / 1024 / 1024, 1);
                    }
                    ?>
                    <input
                        type="number"
                        name="settings[max_upload_size_mb]"
                        min="1"
                        max="50"
                        value="<?php echo e($maxUploadSizeMb); ?>"
                        style="width:100%;padding:0.55rem 0.75rem;border-radius:0.75rem;border:1px solid rgba(148,163,184,0.6);font-size:0.9rem;">
                    <div style="margin-top:0.2rem;font-size:0.78rem;color:var(--text-light);">
                        默认 15MB，取值范围 1～50MB。实际生效值不能超过服务器的 <code>upload_max_filesize</code> 和 <code>post_max_size</code>。
                        <?php if ($serverUploadLimitMb !== null): ?>
                            当前服务器单个上传文件硬上限约 <?php echo $serverUploadLimitMb; ?>MB。
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:0.75rem;">
                    <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">视频上传大小限制</label>
                    <?php
                    $videoIgnoreLimit = $settingsData['video_upload_ignore_site_limit'] ?? '0';
                    ?>
                    <label class="switch">
                        <input
                            type="checkbox"
                            name="settings[video_upload_ignore_site_limit]"
                            value="1"
                            <?php echo $videoIgnoreLimit === '1' ? 'checked' : ''; ?>>
                        <span class="switch-track">
                            <span class="switch-thumb"></span>
                        </span>
                        <span class="switch-label">视频上传仅受服务器限制（忽略上面的站点单文件大小限制）</span>
                    </label>
                    <p style="margin:0.25rem 0 0;font-size:0.78rem;color:var(--text-light);">
                        开启后，视频上传不再受“单文件最大上传大小（MB）”限制，仅受服务器 <code>upload_max_filesize</code> 与 <code>post_max_size</code> 控制。图片等其它上传仍按上面的站点限制执行。
                    </p>
                </div>
            </div>

        </section>

        <section class="admin-grid settings-panel" id="site-settings" role="tabpanel" aria-labelledby="tab-site" <?php echo $activeTab === 'site' ? '' : 'hidden'; ?>>
            <div class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <div class="admin-card-title">
                        <i class="ti ti-info-circle" aria-hidden="true"></i>站点信息
                        <button type="button" class="admin-help-toggle" title="查看说明" aria-label="查看说明" aria-expanded="false"><i class="ti ti-info-circle"></i></button>
                    </div>
                </div>
            </div>
            <div class="admin-card-help">
                <div class="admin-card-subtitle">底部版权备案与统计代码</div>
                <p>
                    这里是不常改动的站点信息。确认无误后，点击底部吸附的“保存设置”按钮提交全部设置。
                </p>
            </div>

                <div class="form-group" style="margin-bottom:0.75rem;">
                    <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">网站底部版权信息</label>
                    <input
                        type="text"
                        name="settings[site_footer_copyright]"
                        value="<?php echo e($settingsData['site_footer_copyright'] ?? ''); ?>"
                        placeholder="例如：Copyright © <?php echo date('Y'); ?> 某某情侣 All Rights Reserved."
                        style="width:100%;padding:0.55rem 0.75rem;border-radius:0.75rem;border:1px solid rgba(148,163,184,0.6);font-size:0.9rem;">
                    <div style="margin-top:0.2rem;font-size:0.78rem;color:var(--text-light);">
                        留空时，将使用默认的版权信息（站点名称 + 年份）。可以填写纯文字内容。
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:0.75rem;">
                    <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">网站备案号</label>
                    <input
                        type="text"
                        name="settings[icp_beian]"
                        value="<?php echo e($settingsData['icp_beian'] ?? ''); ?>"
                        placeholder="例如：粤ICP备2025079898号-1"
                        style="width:100%;padding:0.55rem 0.75rem;border-radius:0.75rem;border:1px solid rgba(148,163,184,0.6);font-size:0.9rem;">
                    <div style="margin-top:0.2rem;font-size:0.78rem;color:var(--text-light);">
                        填写后会显示在网站底部，可点击跳转备案查询页面。留空则不显示。
                        支持一次填写多个备案号（换行、逗号或分号分隔），系统会自动识别备案机关类型（工信部ICP / 萌ICP / 公安备案）并自动匹配徽章图标与查询链接；工信部备案号还会根据省份简称自动推导省级通信管理局（鼠标悬停可见）。
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:0.75rem;">
                    <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">统计代码（可选）</label>
                    <textarea
                        name="settings[site_analytics_code]"
                        placeholder="在这里粘贴统计平台提供的代码，例如 Google Analytics、百度统计等"
                        style="width:100%;min-height:120px;padding:0.55rem 0.75rem;border-radius:0.75rem;border:1px solid rgba(148,163,184,0.6);font-size:0.86rem;resize:vertical;"><?php echo e($settingsData['site_analytics_code'] ?? ''); ?></textarea>
                    <div style="margin-top:0.2rem;font-size:0.78rem;color:var(--text-light);">
                        支持完整的 &lt;script&gt;、&lt;noscript&gt; 等代码片段，保存后会自动插入到网站所有页面底部（&lt;/body&gt; 之前）。
                    </div>
                </div>
            </div>
        </section>

        <?php if ($activeTab === 'advanced'): ?>
        <?php // 高级设置视图：每个独立功能内联渲染对应代码（图片补齐 / 图片统计共用同一图片工具面板） ?>
        <?php $advancedPanels = [
            'moderation' => ['label' => '安全审核'],
            'devices'    => ['label' => '信任设备'],
            'blacklist'  => ['label' => '评论黑名单'],
            'tools'      => ['label' => '图片工具'],
        ]; ?>
        <?php foreach ($advancedPanels as $panelKey => $panelMeta): ?>
        <section class="admin-grid settings-panel" id="advpanel-<?php echo $panelKey; ?>" role="tabpanel" <?php echo $panelKey === $advancedActivePanelKey ? '' : 'hidden'; ?> style="margin-bottom:0.75rem;">
            <?php
            if ($panelKey === 'moderation') {
                echo withu_advanced_moderation_panel();
            } elseif ($panelKey === 'devices') {
                echo withu_advanced_devices_panel();
            } elseif ($panelKey === 'blacklist') {
                echo withu_advanced_blacklist_panel();
            } else {
                echo withu_advanced_tools_panel();
            }
            ?>
        </section>
        <?php endforeach; ?>
        <?php else: ?>
        <?php // 高级设置分段：安全审核等独立功能页的入口汇总（无表单项，隐藏底部保存栏） ?>
        <section class="admin-grid settings-panel" id="advanced-settings" role="tabpanel" aria-labelledby="tab-advanced" hidden style="margin-bottom:0.75rem;">
            <div class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <div class="admin-card-title">
                            <i class="ti ti-shield" aria-hidden="true"></i>安全与高级工具
                            <button type="button" class="admin-help-toggle" title="查看说明" aria-label="查看说明" aria-expanded="false"><i class="ti ti-info-circle"></i></button>
                        </div>
                    </div>
                </div>
                <div class="admin-card-help">
                    <div class="admin-card-subtitle">安全审核、信任设备与图片工具等独立页面入口</div>
                </div>
                <nav class="settings-advanced-list">
                    <?php foreach ($advancedEntries as $entry): ?>
                    <a class="settings-advanced-item" href="<?php echo e($entry['href']); ?>">
                        <i class="ti <?php echo $entry['icon']; ?>" aria-hidden="true"></i>
                        <span class="settings-advanced-body"><strong><?php echo $entry['label']; ?></strong><small><?php echo $entry['desc']; ?></small></span>
                        <i class="ti ti-chevron-right settings-advanced-go" aria-hidden="true"></i>
                    </a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </section>
        <?php endif; ?>

        <div class="settings-savebar"<?php echo $activeTab === 'advanced' ? ' hidden' : ''; ?>>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i>
                <span>保存设置</span>
            </button>
            <p class="settings-savebar-note">保存后新设置立即生效；如出现异常，可使用页面底部“旧版后台”入口回到旧设置页排查。</p>
        </div>
    </form>

    <script>
    (function () {
        var preset = document.getElementById('themePreset');
        var mode = null;
        var preview = document.getElementById('themePreviewStrip');
        var colors = {
            'pastel-couple': ['#F5B6C8', '#B9E3D0', '#B8DDF2', '#F8FBFA'],
            'sakura': ['#F5B6C8', '#B9E3D0', '#B8DDF2', '#FFF7FA'],
            'mint': ['#B9E3D0', '#C5E9ED', '#B8DDF2', '#F5FBF8'],
            'sky': ['#B8DDF2', '#B9E3D0', '#F5B6C8', '#F5FAFD'],
            'peach': ['#F6C2A9', '#C6E3D0', '#B8DDF2', '#FFF8F4'],
            'lemon': ['#F2D58A', '#C5E5D2', '#B8DDF2', '#FFFDF2'],
            'sea': ['#A7DEDA', '#B8DDF2', '#F5B6C8', '#F3FBFA'],
            'forest': ['#AFCB9B', '#B9E3D0', '#B8DDF2', '#F5FAF2'],
            'minimal': ['#CDD5DC', '#D5E1DB', '#D2E3ED', '#F5F6F8']
        };
        function renderPreview() {
            if (!preview) return;
            (colors[preset ? preset.value : 'sakura'] || colors.sakura || colors['pastel-couple']).forEach(function (color, index) {
                if (preview.children[index]) preview.children[index].style.background = color;
            });
        }
        if (preset) preset.addEventListener('change', renderPreview);
        if (mode) mode.addEventListener('change', renderPreview);
        var loveDateFields = Array.prototype.slice.call(document.querySelectorAll('[data-love-part]'));
        var loveDateValue = document.getElementById('loveDateValue');
        function syncLoveDate() {
            if (!loveDateValue) return;
            var values = loveDateFields.map(function (input) { return input.value; });
            loveDateValue.value = values[0] && values[1] && values[2] ? values[0] + '-' + values[1].padStart(2, '0') + '-' + values[2].padStart(2, '0') + 'T' + (values[3] || '00').padStart(2, '0') + ':' + (values[4] || '00').padStart(2, '0') + ':' + (values[5] || '00').padStart(2, '0') : '';
        }
        loveDateFields.forEach(function (input, index) {
            input.addEventListener('input', function () {
                input.value = input.value.replace(/\D/g, '').slice(0, Number(input.maxLength));
                syncLoveDate();
                if (input.value.length >= Number(input.maxLength) && loveDateFields[index + 1]) loveDateFields[index + 1].focus();
            });
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Backspace' && input.value === '' && loveDateFields[index - 1]) loveDateFields[index - 1].focus();
            });
            input.addEventListener('paste', function (event) {
                var pasted = (event.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
                if (pasted.length <= Number(input.maxLength)) return;
                event.preventDefault();
                var cursor = index;
                loveDateFields.slice(index).forEach(function (part) {
                    if (!pasted || cursor >= loveDateFields.length) return;
                    var length = Number(part.maxLength);
                    part.value = pasted.slice(0, length);
                    pasted = pasted.slice(length);
                    cursor++;
                });
                syncLoveDate();
                if (loveDateFields[cursor]) loveDateFields[cursor].focus();
            });
        });
        document.querySelectorAll('.theme-color-grid label').forEach(function (label, index) {
            var color = label.querySelector('input[type="color"]');
            var hex = label.querySelector('.theme-hex-input');
            if (!color || !hex) return;
            // 自定义颜色变化时同步刷新上方的配色预览条（主色/辅助色/强调色对应前三格）
            function applyCustomPreview() {
                if (preview && preview.children[index]) preview.children[index].style.background = color.value;
            }
            color.addEventListener('input', function () { hex.value = color.value.toUpperCase(); applyCustomPreview(); });
            hex.addEventListener('input', function () {
                if (/^#[0-9a-f]{6}$/i.test(hex.value)) color.value = hex.value;
                applyCustomPreview();
            });
        });
        // 后台界面模式已固定为 apple，无需切换逻辑
        renderPreview();
    }());

    // 设置分段 Tab：锚点 > section 参数 > 上次停留分段
    (function () {
        var tablist = document.querySelector('.settings-tabs');
        if (!tablist) return;
        if (tablist.querySelector('[data-adv-tab]')) return; // 高级设置视图走独立的压栈切换逻辑
        var tabs = Array.prototype.slice.call(tablist.querySelectorAll('.settings-tab'));
        var panels = tabs.map(function (tab) { return document.getElementById(tab.getAttribute('aria-controls')); }).filter(Boolean);
        if (!tabs.length || !panels.length) return;
        var storageKey = 'withu_settings_tab';
        var savebar = document.querySelector('.settings-savebar');

        function activate(id, moveFocus) {
            var matched = false;
            tabs.forEach(function (tab) {
                var on = tab.getAttribute('aria-controls') === id;
                if (on) matched = true;
                tab.classList.toggle('is-active', on);
                tab.setAttribute('aria-selected', on ? 'true' : 'false');
                tab.setAttribute('tabindex', on ? '0' : '-1');
            });
            if (!matched) return;
            panels.forEach(function (panel) { panel.hidden = panel.id !== id; });
            // 高级设置分段只有页面入口、没有可保存项，吸附保存栏随之隐藏
            if (savebar) savebar.hidden = (id === 'advanced-settings');
            if (moveFocus) {
                tabs.forEach(function (tab) { if (tab.classList.contains('is-active')) tab.focus(); });
            }
            // 选中 Tab 超出分段导航可视宽度时（窄屏/最后一段），仅横向滚动导航使其可见
            var activeTab = tablist.querySelector('.settings-tab.is-active');
            if (activeTab && tablist.scrollWidth > tablist.clientWidth) {
                var target = activeTab.offsetLeft - (tablist.clientWidth - activeTab.offsetWidth) / 2;
                tablist.scrollLeft = Math.max(0, Math.min(target, tablist.scrollWidth - tablist.clientWidth));
            }
            try { sessionStorage.setItem(storageKey, id); } catch (e) {}
        }

        tabs.forEach(function (tab, index) {
            tab.addEventListener('click', function () {
                if (!tab.classList.contains('is-active')) activate(tab.getAttribute('aria-controls'), false);
                // 切换后回到分段导航处，避免停留在长面板中部
                var form = tablist.closest('form');
                if (!form) return;
                var topbarH = parseInt(getComputedStyle(document.body).getPropertyValue('--v3-topbar-h'), 10) || 54;
                var targetTop = form.getBoundingClientRect().top + window.pageYOffset - topbarH - 8;
                if (window.pageYOffset > targetTop) {
                    window.scrollTo({ top: Math.max(targetTop, 0), behavior: 'smooth' });
                }
            });
            tab.addEventListener('keydown', function (event) {
                if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
                event.preventDefault();
                var delta = event.key === 'ArrowRight' ? 1 : tabs.length - 1;
                var next = tabs[(index + delta) % tabs.length];
                activate(next.getAttribute('aria-controls'), true);
            });
        });

        var initialId = null;
        tabs.forEach(function (tab) { if (tab.classList.contains('is-active')) initialId = tab.getAttribute('aria-controls'); });
        var hashId = window.location.hash.replace('#', '');
        var sectionParam = new URLSearchParams(window.location.search).get('section');
        var hashMatched = panels.some(function (panel) { return panel.id === hashId; });
        if (hashMatched) {
            initialId = hashId;
        } else if (!sectionParam) {
            try {
                var saved = sessionStorage.getItem(storageKey);
                if (saved && panels.some(function (panel) { return panel.id === saved; })) initialId = saved;
            } catch (e) {}
        }
        if (initialId) activate(initialId, false);
    }());

    // 高级设置视图 Tab：原地切换 + pushState 压栈（浏览器前进/后退回退到上一个 tab）
    (function () {
        var tablist = document.querySelector('.settings-tabs[aria-label="高级功能"]');
        if (!tablist) return;
        var tabs = Array.prototype.slice.call(tablist.querySelectorAll('[data-adv-tab]'));
        if (!tabs.length) return;
        var keys = tabs.map(function (tab) { return tab.getAttribute('data-adv-tab'); });
        // 面板与 tab 可能多对一（图片补齐 / 图片统计共用同一个图片工具面板），先按 aria-controls 去重收集
        var panelIds = [];
        tabs.forEach(function (tab) {
            var id = tab.getAttribute('aria-controls');
            if (id && panelIds.indexOf(id) === -1) panelIds.push(id);
        });
        var panels = panelIds.map(function (id) { return document.getElementById(id); }).filter(function (el) { return !!el; });
        var storageKey = 'withu_adv_tab';

        function activate(key, moveFocus) {
            var matched = false;
            tabs.forEach(function (tab) {
                var on = tab.getAttribute('data-adv-tab') === key;
                if (on) matched = true;
                tab.classList.toggle('is-active', on);
                tab.setAttribute('aria-selected', on ? 'true' : 'false');
                tab.setAttribute('tabindex', on ? '0' : '-1');
            });
            if (!matched) return;
            panels.forEach(function (panel) { panel.hidden = true; });
            var activeTab = tablist.querySelector('.settings-tab.is-active');
            if (activeTab) {
                var panel = document.getElementById(activeTab.getAttribute('aria-controls'));
                if (panel) panel.hidden = false;
            }
            if (moveFocus) {
                var activeTab = tablist.querySelector('.settings-tab.is-active');
                if (activeTab) activeTab.focus();
            }
            var active = tablist.querySelector('.settings-tab.is-active');
            if (active && tablist.scrollWidth > tablist.clientWidth) {
                var target = active.offsetLeft - (tablist.clientWidth - active.offsetWidth) / 2;
                tablist.scrollLeft = Math.max(0, Math.min(target, tablist.scrollWidth - tablist.clientWidth));
            }
            try { sessionStorage.setItem(storageKey, key); } catch (e) {}
        }

        function urlKey() {
            var raw = new URLSearchParams(window.location.search).get('tab');
            return (raw && keys.indexOf(raw) !== -1) ? raw : null;
        }

        function push(key) {
            activate(key, false);
            var url = new URL(window.location.href);
            url.searchParams.set('section', 'advanced');
            url.searchParams.set('tab', key);
            history.pushState({ withuAdvTab: key }, '', url.toString());
        }

        tabs.forEach(function (tab, index) {
            tab.addEventListener('click', function () {
                var key = tab.getAttribute('data-adv-tab');
                if (tab.classList.contains('is-active')) return;
                push(key);
                // 切换后回到分段导航处，避免停留在面板中部
                var form = tablist.closest('form');
                if (!form) return;
                var topbarH = parseInt(getComputedStyle(document.body).getPropertyValue('--v3-topbar-h'), 10) || 54;
                var targetTop = form.getBoundingClientRect().top + window.pageYOffset - topbarH - 8;
                if (window.pageYOffset > targetTop) {
                    window.scrollTo({ top: Math.max(targetTop, 0), behavior: 'smooth' });
                }
            });
            tab.addEventListener('keydown', function (event) {
                if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
                event.preventDefault();
                var delta = event.key === 'ArrowRight' ? 1 : keys.length - 1;
                push(keys[(index + delta) % keys.length]);
                var active = tablist.querySelector('.settings-tab.is-active');
                if (active) active.focus();
            });
        });

        window.addEventListener('popstate', function (event) {
            var key = (event.state && event.state.withuAdvTab) ? event.state.withuAdvTab : (urlKey() || keys[0]);
            activate(key, false);
        });

        var initial = urlKey();
        if (!initial) {
            try {
                var saved = sessionStorage.getItem(storageKey);
                if (saved && keys.indexOf(saved) !== -1) initial = saved;
            } catch (e) {}
        }
        activate(initial || keys[0], false);
    }());

    // 首页大图多图管理：增删、排序、待上传本地预览、展开预览
    (function () {
        var listEl = document.getElementById('bannerImageList');
        var inputEl = document.getElementById('bannerImagesInput');
        var fileInput = document.getElementById('bannerFileInput');
        var pickBtn = document.getElementById('bannerPickBtn');
        var urlInput = document.getElementById('bannerUrlInput');
        var addUrlBtn = document.getElementById('bannerAddUrlBtn');
        var lightbox = document.getElementById('bannerLightbox');
        if (!listEl || !inputEl || !fileInput) return;

        var UPLOAD_TOKEN = '__WITHU_UPLOAD__';
        var MAX_ITEMS = 20;
        var isLegacyExample = listEl.getAttribute('data-legacy-example') === '1';
        // state：url=存储值（待上传为占位符），view=预览地址，pending=待上传 File，
        // example=由系统导入的内置默认图（非用户上传，渲染时标「示例」角标）
        var state = [];

        try {
            var initialStored = JSON.parse(inputEl.value || '[]');
            var initialViews = JSON.parse(listEl.getAttribute('data-views') || '[]');
            if (Array.isArray(initialStored)) {
                initialStored.forEach(function (url, index) {
                    if (typeof url !== 'string' || url.trim() === '') return;
                    state.push({ url: url, view: initialViews[index] || url, pending: null, objectUrl: '', example: isLegacyExample });
                });
            }
        } catch (e) {}

        function syncInput() {
            inputEl.value = JSON.stringify(state.map(function (item) {
                return item.pending ? UPLOAD_TOKEN : item.url;
            }));
        }

        // 待上传文件与列表保持同序：移除或排序后重建 file input
        function rebuildFileInput() {
            var dt = new DataTransfer();
            state.forEach(function (item) {
                if (item.pending) dt.items.add(item.pending);
            });
            fileInput.files = dt.files;
        }

        function render() {
            listEl.innerHTML = '';
            state.forEach(function (item, index) {
                var card = document.createElement('div');
                card.className = 'banner-image-item' + (item.pending ? ' is-pending' : '');

                var img = document.createElement('img');
                img.src = item.view;
                img.alt = '首页大图 ' + (index + 1);
                img.title = '点击展开预览';
                img.addEventListener('click', function () { openLightbox(index); });
                card.appendChild(img);

                if (item.example && !item.pending) {
                    var exampleBadge = document.createElement('span');
                    exampleBadge.className = 'banner-image-badge banner-image-badge-example';
                    exampleBadge.textContent = '示例';
                    card.appendChild(exampleBadge);
                }
                if (item.pending) {
                    var pendingBadge = document.createElement('span');
                    pendingBadge.className = 'banner-image-badge';
                    pendingBadge.textContent = '待上传';
                    card.appendChild(pendingBadge);
                }

                var actions = document.createElement('div');
                actions.className = 'banner-image-actions';

                function makeBtn(icon, label, disabled, onClick, extraClass) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'banner-image-btn' + (extraClass ? ' ' + extraClass : '');
                    btn.setAttribute('aria-label', label);
                    btn.title = label;
                    btn.innerHTML = '<i class="ti ' + icon + '" aria-hidden="true"></i>';
                    if (disabled) btn.disabled = true;
                    else btn.addEventListener('click', onClick);
                    return btn;
                }

                actions.appendChild(makeBtn('ti-chevron-left', '前移', index === 0, function () { move(index, -1); }));
                actions.appendChild(makeBtn('ti-chevron-right', '后移', index === state.length - 1, function () { move(index, 1); }));
                actions.appendChild(makeBtn('ti-trash', '移除', false, function () { remove(index); }, 'banner-image-btn-danger'));
                card.appendChild(actions);
                listEl.appendChild(card);
            });
            syncInput();
        }

        function move(index, delta) {
            var target = index + delta;
            if (target < 0 || target >= state.length) return;
            var swapped = state[index];
            state[index] = state[target];
            state[target] = swapped;
            rebuildFileInput();
            render();
        }

        function remove(index) {
            var item = state[index];
            if (!item) return;
            if (item.objectUrl) URL.revokeObjectURL(item.objectUrl);
            state.splice(index, 1);
            rebuildFileInput();
            render();
        }

        if (pickBtn) pickBtn.addEventListener('click', function () { fileInput.click(); });
        fileInput.addEventListener('change', function () {
            Array.prototype.slice.call(fileInput.files || []).forEach(function (file) {
                if (state.length >= MAX_ITEMS) return;
                var objectUrl = URL.createObjectURL(file);
                state.push({ url: UPLOAD_TOKEN, view: objectUrl, pending: file, objectUrl: objectUrl });
            });
            render();
        });

        function addUrl() {
            if (!urlInput) return;
            var url = urlInput.value.trim();
            if (!url) return;
            // 无协议时：形如域名的补 https://，否则视为站点根路径
            if (!/^https?:\/\//i.test(url) && url.indexOf('//') !== 0 && url.charAt(0) !== '/') {
                url = /^[^/]+\.[^/]/.test(url) ? 'https://' + url : '/' + url;
            }
            var exists = state.some(function (item) { return !item.pending && item.url === url; });
            if (exists) {
                urlInput.value = '';
                return;
            }
            if (state.length >= MAX_ITEMS) return;
            state.push({ url: url, view: url, pending: null, objectUrl: '' });
            urlInput.value = '';
            render();
        }
        if (addUrlBtn) addUrlBtn.addEventListener('click', addUrl);
        if (urlInput) urlInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                addUrl();
            }
        });

        // 展开预览
        var lbImg = document.getElementById('bannerLightboxImg');
        var lbCount = document.getElementById('bannerLightboxCount');
        var lbPrev = document.getElementById('bannerLightboxPrev');
        var lbNext = document.getElementById('bannerLightboxNext');
        var lbClose = document.getElementById('bannerLightboxClose');
        var lbIndex = 0;
        var lbKeyDownBound = false;

        function lbKeyHandler(event) {
            if (event.key === 'Escape') closeLightbox();
            else if (event.key === 'ArrowLeft') lbStep(-1);
            else if (event.key === 'ArrowRight') lbStep(1);
        }

        function updateLightbox() {
            if (!lightbox || !lbImg) return;
            var item = state[lbIndex];
            if (!item) { closeLightbox(); return; }
            lbImg.src = item.view;
            if (lbCount) lbCount.textContent = state.length > 1 ? (lbIndex + 1) + ' / ' + state.length : '';
            if (lbPrev) lbPrev.hidden = state.length < 2;
            if (lbNext) lbNext.hidden = state.length < 2;
        }

        function openLightbox(index) {
            if (!lightbox) return;
            lbIndex = index;
            updateLightbox();
            lightbox.hidden = false;
            document.documentElement.style.overflow = 'hidden';
            if (!lbKeyDownBound) {
                lbKeyDownBound = true;
                document.addEventListener('keydown', lbKeyHandler);
            }
        }

        function closeLightbox() {
            if (!lightbox) return;
            lightbox.hidden = true;
            document.documentElement.style.overflow = '';
            if (lbKeyDownBound) {
                lbKeyDownBound = false;
                document.removeEventListener('keydown', lbKeyHandler);
            }
        }

        function lbStep(delta) {
            if (state.length < 2) return;
            lbIndex = (lbIndex + delta + state.length) % state.length;
            updateLightbox();
        }

        if (lbClose) lbClose.addEventListener('click', closeLightbox);
        if (lbPrev) lbPrev.addEventListener('click', function () { lbStep(-1); });
        if (lbNext) lbNext.addEventListener('click', function () { lbStep(1); });
        if (lightbox) lightbox.addEventListener('click', function (event) {
            if (event.target === lightbox) closeLightbox();
        });

        render();
    }());

    // 位置搜索自动完成
    (function () {
        var searchInput = document.getElementById('weather_loc_search');
        var resultsDiv = document.getElementById('weather_loc_results');
        var latInput = document.getElementById('weather_loc_lat');
        var lngInput = document.getElementById('weather_loc_lng');
        var nameInput = document.getElementById('weather_loc_name');
        var selectedDiv = document.getElementById('weather_loc_selected');
        if (!searchInput) return;

        var searchTimer = null;

        function loadAMap(callback) {
            if (window.AMap && window.AMap.Autocomplete) { callback(); return; }
            var script = document.createElement('script');
            script.src = '/assets/js/map-sdk.js';
            script.onload = function () {
                var check = setInterval(function () {
                    if (window.AMap && window.AMap.Autocomplete) {
                        clearInterval(check);
                        callback();
                    }
                }, 200);
            };
            script.onerror = function () {
                console.warn('AMap SDK 加载失败，位置搜索不可用');
            };
            document.head.appendChild(script);
        }

        function selectLocation(item) {
            var lng = item.location.lng;
            var lat = item.location.lat;
            var name = item.name || '';
            if (item.district && item.district !== name && name.indexOf(item.district) === -1) {
                name = item.district + ' ' + name;
            }
            if (item.city && name.indexOf(item.city) === -1) {
                name = item.city + ' ' + name;
            }
            latInput.value = lat;
            lngInput.value = lng;
            nameInput.value = name;
            searchInput.value = name;
            selectedDiv.innerHTML = '已选择：' + name + '（' + lat + ', ' + lng + '）';
            selectedDiv.style.color = '#16a34a';
            resultsDiv.style.display = 'none';
        }

        searchInput.addEventListener('input', function () {
            var val = this.value.trim();
            if (!val || val.length < 1) {
                resultsDiv.style.display = 'none';
                return;
            }
            if (searchTimer) clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                loadAMap(function () {
                    try {
                        AMap.plugin('AMap.Autocomplete', function () {
                            var auto = new AMap.Autocomplete({ citylimit: false });
                            auto.search(val, function (status, result) {
                                if (status === 'complete' && result.tips) {
                                    var tips = result.tips.filter(function (t) {
                                        return t.location && t.location.lng && t.location.lat;
                                    });
                                    if (tips.length === 0) {
                                        resultsDiv.innerHTML = '<div style="padding:10px;color:#888;">未找到结果</div>';
                                    } else {
                                        resultsDiv.innerHTML = tips.map(function (t, i) {
                                            var n = t.name || '';
                                            if (t.district && t.district !== n) n = t.district + ' ' + n;
                                            return '<div class="loc-result-item" data-index="' + i + '" style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #f0f0f0;font-size:.85rem;" onmouseover="this.style.background=\'#f8fafc\'" onmouseout="this.style.background=\'\'">' + n + '</div>';
                                        }).join('');
                                        resultsDiv.querySelectorAll('.loc-result-item').forEach(function (el) {
                                            el.addEventListener('click', function () {
                                                var idx = parseInt(this.getAttribute('data-index'), 10);
                                                selectLocation(tips[idx]);
                                            });
                                        });
                                    }
                                    resultsDiv.style.display = 'block';
                                }
                            });
                        });
                    } catch (e) { console.warn('位置搜索失败:', e); }
                });
            }, 300);
        });

        document.addEventListener('click', function (e) {
            if (!searchInput.contains(e.target) && !resultsDiv.contains(e.target)) {
                resultsDiv.style.display = 'none';
            }
        });

        var clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.textContent = '清除';
        clearBtn.style.cssText = 'margin-top:.35rem;padding:4px 12px;border-radius:6px;border:1px solid #e2e8f0;background:#fff;font-size:.78rem;cursor:pointer;color:#888;';
        clearBtn.addEventListener('click', function () {
            latInput.value = '';
            lngInput.value = '';
            nameInput.value = '';
            searchInput.value = '';
            selectedDiv.innerHTML = '未选择位置，天气将使用默认数据';
            selectedDiv.style.color = '#888';
        });
        searchInput.parentNode.parentNode.appendChild(clearBtn);
    })();
    </script>

<?php include __DIR__ . '/footer.php'; ?>
