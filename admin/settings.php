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

    // 如果已有首页大图并使用旧目录 banners，则删除旧文件并清空设置，让用户重新上传
    if (!empty($settingsData['home_banner_image']) && strpos($settingsData['home_banner_image'], '/banners/') !== false) {
        if (strpos($settingsData['home_banner_image'], UPLOAD_URL) === 0) {
            $oldPath = str_replace(UPLOAD_URL, '', $settingsData['home_banner_image']);
            deleteFile($oldPath);
        }
        $_POST['settings']['home_banner_image'] = '';
        $settingsData['home_banner_image'] = '';
    }

    // 处理首页大图上传（新目录 hero_covers，避免命中广告拦截规则）
    if (isset($_FILES['home_banner_image']) && $_FILES['home_banner_image']['error'] === UPLOAD_ERR_OK) {
        $upload = uploadFile($_FILES['home_banner_image'], 'hero_covers');
        if (!empty($upload['success'])) {
            // 删除旧的大图文件（无论是 URL 还是相对路径）
            if (!empty($settingsData['home_banner_image'])) {
                $oldPath = $settingsData['home_banner_image'];
                if (strpos($oldPath, UPLOAD_URL) === 0) {
                    $oldPath = str_replace(UPLOAD_URL, '', $oldPath);
                }
                deleteFile($oldPath);
            }
            // 只保存相对路径，便于站点迁移
            $_POST['settings']['home_banner_image'] = $upload['path'];
        } else {
            $error = $upload['message'] ?? '首页大图上传失败';
        }
    }

    if (!$error && !empty($_POST['settings']) && is_array($_POST['settings'])) {
        // 规范化布尔开关：未勾选时明确写入 '0'
        $booleanKeys = [
            'image_optimize_enabled',
            'video_upload_ignore_site_limit',
            'turnstile_enabled',
            'front_animation_enabled',
            'backend_animation_enabled',
            'ai_moderation_enabled',
            'watch_autoplay_enabled',
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
        $adminUiMode = (string)($_POST['settings']['admin_ui_mode'] ?? 'current');
        if (!in_array($themePreset, $themePresets, true)) {
            $error = '主题预设不合法，请重新选择。';
        } elseif (!in_array($adminUiMode, ['current', 'apple'], true)) {
            $error = '后台界面模式不合法，请重新选择。';
        } else {
            $_POST['settings']['theme_preset'] = $themePreset;
            $_POST['settings']['theme_mode'] = 'light';
            $_POST['settings']['admin_ui_mode'] = $adminUiMode;
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

        // Turnstile：启用时必须同时填写 Site Key 与 Secret Key
        if (!$error && isset($_POST['settings']['turnstile_enabled']) && $_POST['settings']['turnstile_enabled'] === '1') {
            $tsSiteKey   = trim((string)($_POST['settings']['turnstile_site_key'] ?? ''));
            $tsSecretKey = trim((string)($_POST['settings']['turnstile_secret_key'] ?? ''));
            if ($tsSiteKey === '' || $tsSecretKey === '') {
                $error = '启用 Turnstile 前，请先填写完整的 Site Key 和 Secret Key。';
            } else {
                $_POST['settings']['turnstile_site_key']   = $tsSiteKey;
                $_POST['settings']['turnstile_secret_key'] = $tsSecretKey;
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
}

$adminPage = 'settings';

include __DIR__ . '/header.php';
?>

    <section class="admin-page-title">
        <h1>系统设置</h1>
        <p>管理站点基础信息、首页展示和备案信息</p>
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

        <section class="admin-grid" style="margin-bottom:0.75rem;">
            <div class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <div class="admin-card-title">基础信息</div>
                        <div class="admin-card-subtitle">站点标题与描述、登录安全</div>
                    </div>
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

                <hr style="border:none;border-top:1px dashed rgba(148,163,184,0.5);margin:0.75rem 0;">

                <div class="form-group" style="margin-bottom:0.5rem;">
                    <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">Cloudflare Turnstile 登录验证</label>
                    <?php
                    $turnstileEnabled   = $settingsData['turnstile_enabled'] ?? '0';
                    $turnstileSiteKey   = $settingsData['turnstile_site_key'] ?? '';
                    $turnstileSecretKey = $settingsData['turnstile_secret_key'] ?? '';
                    ?>
                    <label class="switch">
                        <input
                            type="checkbox"
                            name="settings[turnstile_enabled]"
                            value="1"
                            <?php echo $turnstileEnabled === '1' ? 'checked' : ''; ?>>
                        <span class="switch-track">
                            <span class="switch-thumb"></span>
                        </span>
                        <span class="switch-label">启用 Cloudflare Turnstile 登录验证</span>
                    </label>
                    <p style="margin:0.25rem 0 0;font-size:0.78rem;color:var(--text-light);">
                        启用后，登录 / 注册时需要通过 Turnstile 验证，建议配合 Cloudflare 保护站点安全。
                    </p>
                    <p style="margin:0.25rem 0 0;font-size:0.78rem;color:var(--text-light);">
                        启用 Cloudflare Turnstile 后，可以在很大程度上提升登录安全性，有效防止脚本暴力尝试登录。但由于 Cloudflare 在中国大陆的访问速度和稳定性不友好，请在确认当前网络环境能够正常访问 Cloudflare 后再开启此功能，否则可能会导致登录页验证码无法加载，从而无法正常登录后台。
                    </p>
                </div>

                <div class="form-group" style="margin-bottom:0.5rem;">
                    <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">Turnstile Site Key</label>
                    <input
                        type="text"
                        name="settings[turnstile_site_key]"
                        value="<?php echo e($turnstileSiteKey); ?>"
                        placeholder="在 Cloudflare Turnstile 控制台中获取"
                        style="width:100%;padding:0.55rem 0.75rem;border-radius:0.75rem;border:1px solid rgba(148,163,184,0.6);font-size:0.9rem;">
                </div>

                <div class="form-group">
                    <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">Turnstile Secret Key</label>
                    <input
                        type="text"
                        name="settings[turnstile_secret_key]"
                        value="<?php echo e($turnstileSecretKey); ?>"
                        placeholder="在 Cloudflare Turnstile 控制台中获取（请妥善保管）"
                        style="width:100%;padding:0.55rem 0.75rem;border-radius:0.75rem;border:1px solid rgba(148,163,184,0.6);font-size:0.9rem;">
                    <div style="margin-top:0.2rem;font-size:0.78rem;color:var(--text-light);">
                        Secret Key 仅用于服务器端验证，切勿公开。若不启用 Turnstile，可留空。
                    </div>
                </div>
            </div>

            <div class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <div class="admin-card-title">恋爱与首页</div>
                        <div class="admin-card-subtitle">恋爱开始日期与首页大图</div>
                    </div>
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
                    <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">首页大图地址</label>
                    <?php
                    $hasHomeBannerSetting = array_key_exists('home_banner_image', $settingsData);
                    // 若尚未保存过该设置，则展示静态默认图路径，避免依赖 uploads 目录
                    $homeBannerSetting = $hasHomeBannerSetting ? $settingsData['home_banner_image'] : '/assets/images/default_hero.jpg';
                    ?>
                    <input
                        type="text"
                        name="settings[home_banner_image]"
                        value="<?php echo e($homeBannerSetting); ?>"
                        placeholder="图片 URL / 外链图片地址"
                        style="width:100%;padding:0.55rem 0.75rem;border-radius:0.75rem;border:1px solid rgba(148,163,184,0.6);font-size:0.9rem;">
                    <div style="margin-top:0.2rem;font-size:0.78rem;color:var(--text-light);">
                        如果同时上传了图片，将优先使用上传的新图片。
                    </div>
                </div>

                <div class="form-group">
                    <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">首页大图上传</label>
                    <input type="file" name="home_banner_image" accept="image/*" style="font-size:0.85rem;">
                    <?php
                    $bannerMaxBytes = get_max_upload_size_bytes();
                    $bannerMaxMb    = round($bannerMaxBytes / 1024 / 1024, 1);
                    ?>
                    <div style="margin-top:0.2rem;font-size:0.78rem;color:var(--text-light);">
                        建议使用横向大图，宽度不小于 1200 像素，单文件最大约 <?php echo $bannerMaxMb; ?>MB。
                    </div>
                </div>
            </div>
        </section>

        <?php
        $watchPollIntervalValue = (int)($settingsData['watch_poll_interval_ms'] ?? 500);
        $watchSyncThresholdValue = (int)($settingsData['watch_sync_threshold_ms'] ?? 1000);
        $watchPresenceTimeoutValue = (int)($settingsData['watch_presence_timeout_sec'] ?? 8);
        $watchHeartbeatValue = (int)($settingsData['watch_heartbeat_interval_ms'] ?? 2500);
        $watchAutoplayValue = $settingsData['watch_autoplay_enabled'] ?? '1';
        ?>
        <section class="admin-grid" id="together-settings" style="margin-bottom:0.75rem;">
            <div class="admin-card">
                <div class="admin-card-header"><div><div class="admin-card-title">一起看</div><div class="admin-card-subtitle">同步、在线状态和自动播放</div></div></div>
                <div class="form-group" style="margin-bottom:.65rem;"><label style="display:block;font-size:.85rem;margin-bottom:.25rem;">状态轮询间隔（毫秒）</label><input type="number" name="settings[watch_poll_interval_ms]" min="300" max="3000" value="<?php echo $watchPollIntervalValue; ?>" style="width:100%;padding:.55rem .75rem;border-radius:.75rem;border:1px solid rgba(148,163,184,.6);font-size:.9rem;"><div style="margin-top:.2rem;font-size:.78rem;color:var(--text-light);">默认 500ms，只读取房间状态，不会因此修改播放进度。</div></div>
                <div class="form-group" style="margin-bottom:.65rem;"><label style="display:block;font-size:.85rem;margin-bottom:.25rem;">偏差校正阈值（毫秒）</label><input type="number" name="settings[watch_sync_threshold_ms]" min="500" max="5000" value="<?php echo $watchSyncThresholdValue; ?>" style="width:100%;padding:.55rem .75rem;border-radius:.75rem;border:1px solid rgba(148,163,184,.6);font-size:.9rem;"><div style="margin-top:.2rem;font-size:.78rem;color:var(--text-light);">小偏差使用短暂倍速追赶，大偏差才直接跳转。</div></div>
            </div>
            <div class="admin-card">
                <div class="admin-card-header"><div><div class="admin-card-title">一起看体验</div><div class="admin-card-subtitle">在线判定、心跳与进入播放</div></div></div>
                <div class="form-group" style="margin-bottom:.65rem;"><label style="display:block;font-size:.85rem;margin-bottom:.25rem;">在线判定时间（秒）</label><input type="number" name="settings[watch_presence_timeout_sec]" min="3" max="30" value="<?php echo $watchPresenceTimeoutValue; ?>" style="width:100%;padding:.55rem .75rem;border-radius:.75rem;border:1px solid rgba(148,163,184,.6);font-size:.9rem;"></div>
                <div class="form-group" style="margin-bottom:.65rem;"><label style="display:block;font-size:.85rem;margin-bottom:.25rem;">心跳间隔（毫秒）</label><input type="number" name="settings[watch_heartbeat_interval_ms]" min="1000" max="10000" value="<?php echo $watchHeartbeatValue; ?>" style="width:100%;padding:.55rem .75rem;border-radius:.75rem;border:1px solid rgba(148,163,184,.6);font-size:.9rem;"></div>
                <label class="switch"><input type="checkbox" name="settings[watch_autoplay_enabled]" value="1" <?php echo $watchAutoplayValue === '1' ? 'checked' : ''; ?>><span class="switch-track"><span class="switch-thumb"></span></span><span class="switch-label">首次打开、换集和换剧默认自动播放</span></label>
            </div>
        </section>

        <?php
        $themePresetValue = $settingsData['theme_preset'] ?? 'sakura';
        if ($themePresetValue === 'pastel-couple') $themePresetValue = 'sakura';
        $themeModeValue = 'light';
        $themeCustomPrimary = $settingsData['theme_custom_primary'] ?? '#F5B6C8';
        $themeCustomSecondary = $settingsData['theme_custom_secondary'] ?? '#B9E3D0';
        $themeCustomAccent = $settingsData['theme_custom_accent'] ?? '#B8DDF2';
        $adminUiModeValue = $settingsData['admin_ui_mode'] ?? 'current';
        if (!in_array($adminUiModeValue, ['current', 'apple'], true)) $adminUiModeValue = 'current';
        ?>
        <section class="admin-grid" id="theme-settings" style="margin-bottom:0.75rem;">
            <div class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <div class="admin-card-title">主题与外观</div>
                        <div class="admin-card-subtitle">主站、后台和播放器共用的颜色主题</div>
                    </div>
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
                    <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">显示模式</label>
                    <input type="hidden" name="settings[theme_mode]" value="light">
                    <div class="settings-light-mode-note">白天模式</div>
                </div>
                <div class="theme-preview-strip" id="themePreviewStrip" aria-label="主题预览">
                    <span></span><span></span><span></span><span></span>
                </div>
                <hr style="border:none;border-top:1px dashed rgba(148,163,184,0.5);margin:0.9rem 0;">
                <div class="form-group">
                    <label style="display:block;font-size:0.85rem;margin-bottom:0.35rem;">后台界面模式</label>
                    <div class="admin-ui-choice-grid">
                        <label class="admin-ui-choice <?php echo $adminUiModeValue === 'current' ? 'is-selected' : ''; ?>">
                            <input type="radio" name="settings[admin_ui_mode]" value="current" <?php echo $adminUiModeValue === 'current' ? 'checked' : ''; ?>>
                            <span class="admin-ui-choice-preview admin-ui-choice-preview-current"></span>
                            <span>
                                <strong>当前后台</strong>
                                <small>保留现在的布局和排版，作为稳定回退。</small>
                            </span>
                        </label>
                        <label class="admin-ui-choice <?php echo $adminUiModeValue === 'apple' ? 'is-selected' : ''; ?>">
                            <input type="radio" name="settings[admin_ui_mode]" value="apple" <?php echo $adminUiModeValue === 'apple' ? 'checked' : ''; ?>>
                            <span class="admin-ui-choice-preview admin-ui-choice-preview-apple"></span>
                            <span>
                                <strong>透粉玻璃</strong>
                                <small>Apple 风格侧栏、浮层和浅粉液态玻璃材质。</small>
                            </span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <div class="admin-card-title">自定义强调色</div>
                        <div class="admin-card-subtitle">留空则使用预设主题颜色</div>
                    </div>
                </div>
                <div class="theme-color-grid">
                    <label>主色<input type="color" data-theme-picker="primary" value="<?php echo e($themeCustomPrimary); ?>"><input class="theme-hex-input" type="text" name="settings[theme_custom_primary]" value="<?php echo e($settingsData['theme_custom_primary'] ?? ''); ?>" placeholder="#F5B6C8" maxlength="7"></label>
                    <label>辅助色<input type="color" data-theme-picker="secondary" value="<?php echo e($themeCustomSecondary); ?>"><input class="theme-hex-input" type="text" name="settings[theme_custom_secondary]" value="<?php echo e($settingsData['theme_custom_secondary'] ?? ''); ?>" placeholder="#B9E3D0" maxlength="7"></label>
                    <label>强调色<input type="color" data-theme-picker="accent" value="<?php echo e($themeCustomAccent); ?>"><input class="theme-hex-input" type="text" name="settings[theme_custom_accent]" value="<?php echo e($settingsData['theme_custom_accent'] ?? ''); ?>" placeholder="#B8DDF2" maxlength="7"></label>
                </div>
                    <p style="margin:.75rem 0 0;font-size:.78rem;color:var(--text-light);">浅色主题保持清爽明亮，图片和视频不会被滤镜改变。</p>
            </div>
        </section>

        <section class="admin-grid">
            <div class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <div class="admin-card-title">上传与其他</div>
                        <div class="admin-card-subtitle">上传限制、图片压缩、备案号等信息</div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:0.75rem;">
                    <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">图片压缩与 WebP 优化</label>
                    <?php
                    // 默认开启图片压缩与 WebP 优化
                    $imageOptimizeEnabled = $settingsData['image_optimize_enabled'] ?? '1';
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
                        <span class="switch-label">启用图片压缩与 WebP 优化（推荐）</span>
                    </label>
                    <p style="margin:0.25rem 0 0;font-size:0.78rem;color:var(--text-light);">
                        开启后，新上传的图片会自动按比例缩小（长边约 2560 像素）并进行适度压缩，同时为 JPEG/PNG 生成一份 WebP 副本，用于前台优先加载以减轻带宽压力。
                        仅对之后上传的图片生效，已有图片不受影响。
                    </p>
                </div>

                <div class="form-group" style="margin-bottom:0.75rem;">
                    <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">界面动效</label>
                    <?php $frontAnimation = $settingsData['front_animation_enabled'] ?? '1'; $backendAnimation = $settingsData['backend_animation_enabled'] ?? '0'; ?>
                    <label class="switch"><input type="checkbox" name="settings[front_animation_enabled]" value="1" <?php echo $frontAnimation === '1' ? 'checked' : ''; ?>><span class="switch-track"><span class="switch-thumb"></span></span><span class="switch-label">前台花瓣、光影与转场</span></label>
                    <label class="switch" style="margin-top:.45rem"><input type="checkbox" name="settings[backend_animation_enabled]" value="1" <?php echo $backendAnimation === '1' ? 'checked' : ''; ?>><span class="switch-track"><span class="switch-thumb"></span></span><span class="switch-label">后台动效</span></label>
                </div>

                <div class="form-group" style="margin-bottom:0.75rem;">
                    <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">AI 安全与媒体识别</label>
                    <?php $aiModeration = $settingsData['ai_moderation_enabled'] ?? '0'; ?>
                    <label class="switch"><input type="checkbox" name="settings[ai_moderation_enabled]" value="1" <?php echo $aiModeration === '1' ? 'checked' : ''; ?>><span class="switch-track"><span class="switch-thumb"></span></span><span class="switch-label">启用 AI 辅助审核（规则审核始终保留）</span></label>
                    <input type="url" name="settings[ai_api_endpoint]" value="<?php echo e($settingsData['ai_api_endpoint'] ?? ''); ?>" placeholder="AI 兼容接口地址，可留空" style="width:100%;margin-top:.55rem;padding:.55rem .75rem;border-radius:.75rem;border:1px solid rgba(148,163,184,.6);font-size:.9rem;">
                    <input type="password" name="settings[ai_api_key]" value="<?php echo e($settingsData['ai_api_key'] ?? ''); ?>" placeholder="AI API Key，可留空" style="width:100%;margin-top:.55rem;padding:.55rem .75rem;border-radius:.75rem;border:1px solid rgba(148,163,184,.6);font-size:.9rem;">
                    <?php $currentModel = $settingsData['ai_model'] ?? 'deepseek-chat'; $modelOptions = ['deepseek-chat' => 'DeepSeek V3 / deepseek-chat', 'deepseek-reasoner' => 'DeepSeek R1 / deepseek-reasoner', 'gpt-4o-mini' => 'GPT-4o-mini', 'gpt-4o' => 'GPT-4o', 'gpt-4.1' => 'GPT-4.1', 'gpt-4.1-mini' => 'GPT-4.1-mini', 'gpt-4.1-nano' => 'GPT-4.1-nano', 'o3-mini' => 'o3-mini', 'o4-mini' => 'o4-mini', 'claude-sonnet-4-20250514' => 'Claude Sonnet 4', 'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet', 'gemini-2.5-pro' => 'Gemini 2.5 Pro', 'gemini-2.0-flash' => 'Gemini 2.0 Flash']; ?>
<select name="settings[ai_model]" style="width:100%;margin-top:.55rem;padding:.55rem .75rem;border-radius:.75rem;border:1px solid rgba(148,163,184,.6);font-size:.9rem;background:#fff;color:inherit;-webkit-appearance:none;appearance:none;background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23666' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E\");background-repeat:no-repeat;background-position:right .75rem center;background-size:.75rem;padding-right:2rem;">
    <?php foreach ($modelOptions as $modelValue => $modelLabel): ?>
    <option value="<?php echo e($modelValue); ?>" <?php echo $currentModel === $modelValue ? 'selected' : ''; ?>><?php echo e($modelLabel); ?></option>
    <?php endforeach; ?>
</select>
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

            <div class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <div class="admin-card-title">保存设置</div>
                        <div class="admin-card-subtitle">确认无误后保存</div>
                    </div>
                </div>

                <p style="font-size:0.85rem;color:var(--text-light);margin-bottom:0.75rem;">
                    保存后，新设置会立即生效。涉及首页大图等资源的修改，可能需要刷新前台页面才能看到最新效果。
                </p>

                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">
                    <i class="fas fa-save"></i>
                    <span>保存设置</span>
                </button>

                <div style="margin-top:0.5rem;font-size:0.78rem;color:var(--text-light);text-align:center;">
                    如果保存设置后出现异常，可以使用底部“旧版后台”入口回到旧设置页面排查。
                </div>
            </div>
        </section>
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
        document.querySelectorAll('.theme-color-grid label').forEach(function (label) {
            var color = label.querySelector('input[type="color"]');
            var hex = label.querySelector('.theme-hex-input');
            if (!color || !hex) return;
            color.addEventListener('input', function () { hex.value = color.value.toUpperCase(); });
            hex.addEventListener('input', function () {
                if (/^#[0-9a-f]{6}$/i.test(hex.value)) color.value = hex.value;
            });
        });
        var uiChoiceRadios = document.querySelectorAll('input[name="settings[admin_ui_mode]"]');
        var uiChoiceLabels = document.querySelectorAll('.admin-ui-choice');
        function refreshUiChoiceState() {
            uiChoiceLabels.forEach(function (label) {
                var radio = label.querySelector('input[name="settings[admin_ui_mode]"]');
                if (!radio) return;
                label.classList.toggle('is-selected', radio.checked);
            });
        }
        uiChoiceRadios.forEach(function (radio) {
            radio.addEventListener('change', refreshUiChoiceState);
        });
        refreshUiChoiceState();
        renderPreview();
    }());
    </script>

<?php include __DIR__ . '/footer.php'; ?>
