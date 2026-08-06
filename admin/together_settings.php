<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/helpers.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requireRole(['user1', 'user2']);
$db = Database::getInstance();
$error = '';
$success = '';

function withu_together_setting_save(Database $db, string $key, string $value): void
{
    $existing = $db->fetch('SELECT id FROM settings WHERE `key` = :key LIMIT 1', ['key' => $key]);
    $data = ['value' => $value, 'updated_at' => date('Y-m-d H:i:s')];
    if ($existing) {
        $db->update('settings', $data, 'id = :id', ['id' => (int)$existing['id']]);
    } else {
        $db->insert('settings', ['key' => $key, 'description' => '一起看设置'] + $data);
    }
}

$settings = [];
foreach ($db->fetchAll('SELECT `key`, `value` FROM settings') as $row) {
    $settings[(string)$row['key']] = (string)($row['value'] ?? '');
}

if (isset($_GET['success']) && $_GET['success'] === '1') {
    $success = '一起看设置已保存';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $ranges = [
        'watch_poll_interval_ms' => [300, 3000, '轮询间隔必须在 300 到 3000 毫秒之间'],
        'watch_sync_threshold_ms' => [500, 5000, '同步阈值必须在 500 到 5000 毫秒之间'],
        'watch_presence_timeout_sec' => [3, 30, '在线判定时间必须在 3 到 30 秒之间'],
        'watch_heartbeat_interval_ms' => [1000, 10000, '心跳间隔必须在 1000 到 10000 毫秒之间'],
    ];
    $values = [];
    foreach ($ranges as $key => $rule) {
        $value = (int)($_POST[$key] ?? 0);
        if ($value < $rule[0] || $value > $rule[1]) {
            $error = $rule[2];
            break;
        }
        $values[$key] = (string)$value;
    }
    $values['watch_autoplay_enabled'] = isset($_POST['watch_autoplay_enabled']) ? '1' : '0';

    if ($error === '') {
        foreach ($values as $key => $value) {
            withu_together_setting_save($db, $key, $value);
            $settings[$key] = $value;
        }
        header('Location: /admin/together_settings.php?success=1');
        exit;
    }
}

$pollInterval = (int)($settings['watch_poll_interval_ms'] ?? 500);
$syncThreshold = (int)($settings['watch_sync_threshold_ms'] ?? 1000);
$presenceTimeout = (int)($settings['watch_presence_timeout_sec'] ?? 8);
$heartbeatInterval = (int)($settings['watch_heartbeat_interval_ms'] ?? 2500);
$autoplay = ($settings['watch_autoplay_enabled'] ?? '1') === '1';
$adminPage = 'together_settings';
include __DIR__ . '/header.php';
?>

<section class="admin-page-title">
    <h1>一起看</h1>
    <p>统一管理同步、在线状态和进入播放行为；网页端与桌面端读取同一套配置。</p>
</section>

<?php if ($error): ?><div class="admin-alert admin-alert-error"><?php echo e($error); ?></div><?php endif; ?>
<?php if ($success): ?><div class="admin-alert admin-alert-success"><?php echo e($success); ?></div><?php endif; ?>

<form method="post">
    <?php echo csrf_field(); ?>
    <section class="admin-grid admin-together-grid">
        <div class="admin-card">
            <div class="admin-card-header">
                <div>
                    <div class="admin-card-title">同步节奏</div>
                    <div class="admin-card-subtitle">双方平等写入状态，只有达到阈值才校正。</div>
                </div>
            </div>
            <label class="admin-field">
                状态轮询间隔（毫秒）
                <input class="admin-input" type="number" name="watch_poll_interval_ms" min="300" max="3000" value="<?php echo $pollInterval; ?>">
                <span class="admin-help">推荐 500ms。轮询只读取状态，不会单独触发跳转。</span>
            </label>
            <label class="admin-field">
                偏差校正阈值（毫秒）
                <input class="admin-input" type="number" name="watch_sync_threshold_ms" min="500" max="5000" value="<?php echo $syncThreshold; ?>">
                <span class="admin-help">小偏差由慢的一方短暂加速追赶，超过阈值才直接跳转。</span>
            </label>
        </div>

        <div class="admin-card">
            <div class="admin-card-header">
                <div>
                    <div class="admin-card-title">在线与进入体验</div>
                    <div class="admin-card-subtitle">控制在线显示、心跳和换集后的播放行为。</div>
                </div>
            </div>
            <label class="admin-field">
                在线判定时间（秒）
                <input class="admin-input" type="number" name="watch_presence_timeout_sec" min="3" max="30" value="<?php echo $presenceTimeout; ?>">
                <span class="admin-help">超过该时间没有心跳，顶部状态会显示为离线。</span>
            </label>
            <label class="admin-field">
                心跳间隔（毫秒）
                <input class="admin-input" type="number" name="watch_heartbeat_interval_ms" min="1000" max="10000" value="<?php echo $heartbeatInterval; ?>">
                <span class="admin-help">推荐 2500ms，必须大于轮询间隔。</span>
            </label>
            <label class="switch">
                <input type="checkbox" name="watch_autoplay_enabled" value="1" <?php echo $autoplay ? 'checked' : ''; ?>>
                <span class="switch-track"><span class="switch-thumb"></span></span>
                <span class="switch-label">首次打开、换集和换剧默认自动播放</span>
            </label>
        </div>
    </section>

    <div class="admin-page-actions">
        <button class="btn btn-primary" type="submit">保存一起看设置</button>
        <a class="btn btn-secondary" href="/admin/media.php">打开媒体库</a>
    </div>
</form>

<?php include __DIR__ . '/footer.php'; ?>
