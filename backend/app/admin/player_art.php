<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/withu.php';

$auth = new Auth();
$currentUser = withu_require_couple_user($auth);
$db = Database::getInstance();
$error = '';
$success = isset($_GET['saved']) && $_GET['saved'] === '1' ? '播放器设置已保存' : '';

function withu_art_save_setting(Database $db, string $key, string $value): void
{
    $row = $db->fetch('SELECT id FROM settings WHERE `key` = :key LIMIT 1', ['key' => $key]);
    if ($row) {
        $db->update('settings', ['value' => $value, 'updated_at' => withu_now()], 'id = :id', ['id' => (int)$row['id']]);
        return;
    }
    $db->insert('settings', ['key' => $key, 'value' => $value, 'description' => 'WithU 播放器设置']);
}

function withu_art_setting(array $settings, string $key, string $default = ''): string
{
    return array_key_exists($key, $settings) ? (string)$settings[$key] : $default;
}

function withu_art_local_path(string $path): bool
{
    return $path === '' || str_starts_with($path, '/') || str_starts_with($path, '#');
}

function withu_art_background_value(string $value): bool
{
    if ($value === '') return true;
    if (preg_match('/[\x00-\x1F\x7F"\\]/', $value)) return false;
    return str_starts_with($value, '/') || str_starts_with($value, './') || str_starts_with($value, '../') || str_starts_with($value, '#') || (bool)preg_match('/^https?:\/\//i', $value);
}

$defaults = [
    'art_player_title' => 'wituUPlayer',
    'art_player_keywords' => 'WithU WebDAV 直链播放器',
    'art_player_title_url' => '/',
    'art_player_logo' => '/assets/images/withu-logo.png',
    'art_player_color' => '#00bcd4',
    'art_player_waittime' => '0',
    'art_player_right_text' => '返回 WithU',
    'art_player_right_link' => '/',
    'art_player_err_bg' => 'video',
    'art_player_err_bg_imgurl' => '/assets/admin-art/js/bjt.jpg',
    'art_player_err_bg_vodurl' => '',
    'art_player_showtime' => 'off',
    'art_player_video_thumbnails' => 'off',
    'art_player_load_bg' => '/assets/admin-art/js/bjt.jpg',
    'art_player_errzdytext' => 'WebDAV 直链加载失败，请稍后重试',
    'art_player_errzdylink' => '/watch.php',
    'art_player_blank_referer' => 'off',
];
$settings = $defaults;
foreach ($db->fetchAll('SELECT `key`, `value` FROM settings') as $row) {
    $settings[(string)$row['key']] = (string)($row['value'] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? 'save');
    if ($action === 'reset') {
        foreach ($defaults as $key => $value) withu_art_save_setting($db, $key, $value);
        foreach (['player_logo_image' => '', 'player_logo_bg_preset' => 'sakura', 'player_logo_bg_color' => '#f5b6c8', 'player_default_speed' => '1', 'player_auto_next_enabled' => '1'] as $key => $value) {
            withu_art_save_setting($db, $key, $value);
        }
        header('Location: /admin/player_art.php?saved=1');
        exit;
    }

    $input = is_array($_POST['MIZHI'] ?? null) ? $_POST['MIZHI'] : [];
    $read = static fn(string $key, string $default = ''): string => trim((string)($input[$key] ?? $default));
    $speed = $read('default_speed', '1');
    $logoBgPreset = $read('logo_bg_preset', 'sakura');
    $logoBgColor = strtolower($read('logo_bg_color', '#f5b6c8'));
    $logoImage = withu_art_setting($settings, 'player_logo_image');
    if (!in_array($speed, ['0.75', '1', '1.25', '1.5', '2'], true)) $error = '默认倍速设置无效。';
    if (!$error && !preg_match('/^#[0-9a-f]{6}$/i', $logoBgColor)) $error = '台标背景颜色必须是 6 位 HEX 格式。';
    foreach (['title_url', 'right_link', 'errzdylink'] as $key) {
        if (!$error && !withu_art_local_path($read($key))) $error = '跳转地址只能填写 WithU 站内路径。';
    }
    if (!$error && !withu_art_background_value($read('load_bg', $defaults['art_player_load_bg']))) $error = '加载背景只能填写本地路径或 HTTP/HTTPS 图片地址。';
    if (!$error && !empty($_POST['reset_player_logo'])) {
        if ($logoImage !== '' && str_starts_with($logoImage, 'player/')) deleteFile($logoImage);
        $logoImage = '';
    }
    if (!$error && isset($_FILES['player_logo_image']) && $_FILES['player_logo_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload = $_FILES['player_logo_image']['error'] === UPLOAD_ERR_OK ? uploadFile($_FILES['player_logo_image'], 'player') : ['success' => false];
        if (empty($upload['success'])) $error = (string)($upload['message'] ?? '台标上传失败');
        else {
            if ($logoImage !== '' && str_starts_with($logoImage, 'player/')) deleteFile($logoImage);
            $logoImage = (string)$upload['path'];
        }
    }
    if (!$error) {
        foreach ($defaults as $key => $default) {
            $field = substr($key, 11);
            $value = in_array($key, ['art_player_showtime', 'art_player_video_thumbnails', 'art_player_blank_referer'], true)
                ? (!empty($input[$field]) ? 'on' : 'off')
                : $read($field, $default);
            withu_art_save_setting($db, $key, $value);
        }
        withu_art_save_setting($db, 'player_default_speed', $speed);
        withu_art_save_setting($db, 'player_logo_image', $logoImage);
        withu_art_save_setting($db, 'player_logo_bg_preset', $logoBgPreset);
        withu_art_save_setting($db, 'player_logo_bg_color', $logoBgColor);
        withu_art_save_setting($db, 'player_auto_next_enabled', !empty($input['auto_next']) ? '1' : '0');
        header('Location: /admin/player_art.php?saved=1');
        exit;
    }
}

$adminPage = 'player_art';
include __DIR__ . '/header.php';
$speed = withu_art_setting($settings, 'player_default_speed', '1');
$logoImage = withu_art_setting($settings, 'player_logo_image');
$presets = withu_player_logo_bg_presets();
$logoBgPreset = withu_art_setting($settings, 'player_logo_bg_preset', 'sakura');
$logoBgColor = withu_art_setting($settings, 'player_logo_bg_color', '#f5b6c8');
$autoNext = withu_art_setting($settings, 'player_auto_next_enabled', '1') === '1';
$logoPreviewUrl = $logoImage !== '' ? upload_url($logoImage) : '/assets/images/withu-logo.png';
$logoBgStyle = withu_player_logo_bg_style($logoBgPreset, $logoBgColor);
?>
<section class="admin-page-title">
    <h1>播放器设置</h1>
    <p>管理播放器品牌、播放行为与加载提示。</p>
</section>

<?php if ($error): ?><div class="admin-alert admin-alert-error"><?php echo e($error); ?></div><?php endif; ?>
<?php if ($success): ?><div class="admin-alert admin-alert-success"><?php echo e($success); ?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="player-settings-form">
    <?php echo csrf_field(); ?><input type="hidden" name="action" value="save">

    <section class="admin-card player-settings-card player-brand-card">
        <div class="admin-card-header">
            <div>
                <div class="admin-card-title">
                    <i class="ti ti-palette" aria-hidden="true"></i>界面展示
                    <button type="button" class="admin-help-toggle" title="查看说明" aria-label="查看说明" aria-expanded="false"><i class="ti ti-info-circle"></i></button>
                </div>
            </div>
        </div>
        <div class="admin-card-help">
            <div class="admin-card-subtitle">标题、台标与右键菜单。</div>
        </div>

        <div class="player-settings-grid player-brand-fields">
            <label>播放器名称
                <input name="MIZHI[title]" value="<?php echo e(withu_art_setting($settings, 'art_player_title')); ?>">
            </label>
            <label>播放器说明
                <input name="MIZHI[keywords]" value="<?php echo e(withu_art_setting($settings, 'art_player_keywords')); ?>">
            </label>
            <label>标题跳转地址
                <input name="MIZHI[title_url]" value="<?php echo e(withu_art_setting($settings, 'art_player_title_url', '/')); ?>">
                <small>填写 WithU 站内路径，点击播放器标题时跳转。</small>
            </label>
            <label>主题颜色
                <span class="player-color-field">
                    <input type="color" name="MIZHI[color]" value="<?php echo e(withu_art_setting($settings, 'art_player_color')); ?>">
                    <input class="player-color-text" value="<?php echo e(withu_art_setting($settings, 'art_player_color')); ?>" aria-label="主题颜色值" readonly>
                </span>
            </label>
            <label>右键文字
                <input name="MIZHI[right_text]" value="<?php echo e(withu_art_setting($settings, 'art_player_right_text')); ?>">
            </label>
            <label>右键站内链接
                <input name="MIZHI[right_link]" value="<?php echo e(withu_art_setting($settings, 'art_player_right_link', '/')); ?>">
            </label>
        </div>

        <div class="player-settings-grid player-logo-settings-grid" style="margin-top:.9rem;">
            <label class="player-field-wide">台标文件
                <div class="player-logo-upload-row" data-logo-preview data-default-logo="<?php echo e('/assets/images/withu-logo.png'); ?>" data-default-background="<?php echo e($logoBgStyle); ?>">
                    <div class="player-logo-preview-stage" data-logo-preview-stage style="background:<?php echo e($logoBgStyle); ?>">
                        <img data-logo-preview-image src="<?php echo e($logoPreviewUrl); ?>" alt="播放器台标预览">
                    </div>
                    <div class="player-logo-upload-control">
                        <input type="file" name="player_logo_image" accept="image/png,image/jpeg,image/webp,image/gif" data-logo-file>
                        <small>支持 PNG、JPG、WebP 或 GIF。</small>
                        <span class="player-logo-preview-status" data-logo-preview-status><?php echo $logoImage !== '' ? '当前使用已保存台标' : '当前使用默认台标'; ?></span>
                    </div>
                </div>
            </label>
            <label>台标背景
                <select name="MIZHI[logo_bg_preset]" data-logo-preset>
                    <?php foreach ($presets as $key => $preset): ?>
                        <option value="<?php echo e($key); ?>" data-style="<?php echo e($preset['style']); ?>" <?php echo $logoBgPreset === $key ? 'selected' : ''; ?>><?php echo e($preset['name']); ?></option>
                    <?php endforeach; ?>
                    <option value="custom">自定义纯色</option>
                </select>
            </label>
            <label>自定义背景色
                <span class="player-color-field">
                    <input type="color" name="MIZHI[logo_bg_color]" value="<?php echo e($logoBgColor); ?>" data-logo-color>
                    <input class="player-color-text" value="<?php echo e($logoBgColor); ?>" aria-label="自定义背景色值" readonly>
                </span>
            </label>
        </div>

        <?php if ($logoImage !== ''): ?>
            <label class="player-reset-logo">
                <input type="checkbox" name="reset_player_logo" value="1">恢复默认台标
            </label>
        <?php endif; ?>
    </section>

    <section class="admin-card player-settings-card player-behavior-card">
        <div class="admin-card-header">
            <div>
                <div class="admin-card-title">
                    <i class="ti ti-adjustments-horizontal" aria-hidden="true"></i>播放控制
                    <button type="button" class="admin-help-toggle" title="查看说明" aria-label="查看说明" aria-expanded="false"><i class="ti ti-info-circle"></i></button>
                </div>
            </div>
        </div>
        <div class="admin-card-help">
            <div class="admin-card-subtitle">倍速、自动播放与异常提示。</div>
        </div>

        <div class="player-settings-grid">
            <label>默认倍速
                <select name="MIZHI[default_speed]">
                    <?php foreach (['0.75','1','1.25','1.5','2'] as $value): ?>
                        <option value="<?php echo $value; ?>" <?php echo $speed === $value ? 'selected' : ''; ?>><?php echo $value; ?>x</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>继续播放等待
                <input type="number" min="0" max="60" name="MIZHI[waittime]" value="<?php echo (int)withu_art_setting($settings, 'art_player_waittime'); ?>">
                <small>0 秒不等待。</small>
            </label>
            <label class="player-field-wide">加载背景
                <input type="text" inputmode="url" autocomplete="url" name="MIZHI[load_bg]" value="<?php echo e(withu_art_setting($settings, 'art_player_load_bg')); ?>">
                <small>支持本地或在线图片。</small>
            </label>
            <div class="player-field-wide player-switch-row">
                <label class="switch">
                    <input type="checkbox" name="MIZHI[auto_next]" value="1" <?php echo $autoNext ? 'checked' : ''; ?>>
                    <span class="switch-track"><span class="switch-thumb"></span></span>
                    <span class="switch-label">自动下一集</span>
                </label>
                <p class="player-switch-note">当前集结束后自动切换到下一集。</p>
            </div>
            <label>失败提示文字
                <input name="MIZHI[errzdytext]" value="<?php echo e(withu_art_setting($settings, 'art_player_errzdytext')); ?>">
            </label>
            <label>失败返回地址
                <input name="MIZHI[errzdylink]" value="<?php echo e(withu_art_setting($settings, 'art_player_errzdylink')); ?>">
            </label>
        </div>
    </section>

    <div class="settings-savebar">
        <div class="player-savebar-actions">
            <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i><span>保存设置</span></button>
            <button class="btn btn-secondary" type="submit" name="action" value="reset" onclick="return confirm('确定恢复播放器默认设置吗？');"><i class="fas fa-rotate-left"></i><span>恢复默认</span></button>
        </div>
        <p class="settings-savebar-note">保存后立即生效。</p>
    </div>
</form>
<script>
(() => {
    const preview = document.querySelector('[data-logo-preview]');
    if (!preview) return;
    const image = preview.querySelector('[data-logo-preview-image]');
    const stage = preview.querySelector('[data-logo-preview-stage]');
    const status = preview.querySelector('[data-logo-preview-status]');
    const file = document.querySelector('[data-logo-file]');
    const preset = document.querySelector('[data-logo-preset]');
    const color = document.querySelector('[data-logo-color]');
    const reset = document.querySelector('input[name="reset_player_logo"]');
    const defaultLogo = preview.dataset.defaultLogo;
    const defaultBackground = preview.dataset.defaultBackground;
    const hexText = color?.closest('.player-color-field')?.querySelector('.player-color-text');

    const updateBackground = () => {
        const option = preset?.options[preset.selectedIndex];
        stage.style.background = preset?.value === 'custom' ? (color.value || '#f5b6c8') : (option?.dataset.style || defaultBackground);
        if (hexText) hexText.value = color.value;
    };

    preset?.addEventListener('change', updateBackground);
    color?.addEventListener('input', updateBackground);
    reset?.addEventListener('change', () => {
        if (!reset.checked) return;
        image.src = defaultLogo;
        status.textContent = '保存后恢复默认台标';
    });
    file?.addEventListener('change', () => {
        const selected = file.files?.[0];
        if (!selected || !selected.type.startsWith('image/')) return;
        const reader = new FileReader();
        reader.addEventListener('load', () => {
            image.src = reader.result;
            if (reset) reset.checked = false;
            status.textContent = '当前预览为待上传台标';
        });
        reader.readAsDataURL(selected);
    });
    image.addEventListener('error', () => {
        image.src = defaultLogo;
        status.textContent = '台标加载失败，显示默认台标';
    });
})();
</script>
<?php include __DIR__ . '/footer.php'; ?>
