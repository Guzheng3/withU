<?php
// 新版后台 - withUstrm 媒体库对接设置
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/withu.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requireRole(['user1', 'user2']);
$db = Database::getInstance();
$error = '';
$success = '';

function withu_strm_setting_save(Database $db, string $key, string $value): void
{
    $existing = $db->fetch('SELECT id FROM settings WHERE `key` = :key LIMIT 1', ['key' => $key]);
    $data = ['value' => $value, 'updated_at' => date('Y-m-d H:i:s')];
    if ($existing) {
        $db->update('settings', $data, 'id = :id', ['id' => (int)$existing['id']]);
    } else {
        $db->insert('settings', ['key' => $key, 'description' => 'withUstrm 对接设置'] + $data);
    }
}

$probeResult = null;

// 连接测试：GET ?action=probe，允许携带表单里尚未保存的地址和 Key
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'probe') {
    header('Content-Type: application/json; charset=UTF-8');
    if (!csrf_verify($_GET['_token'] ?? null)) {
        echo json_encode(['success' => false, 'message' => '页面已过期，请刷新后重试'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $cfg = [
        'base_url' => withu_strm_normalize_base((string)($_GET['url'] ?? '')),
        'api_key'  => trim((string)($_GET['key'] ?? '')),
    ];
    // 前端把已保存但未重新输入的 Key 用占位符传来，这里替换成库里的真实值
    if ($cfg['api_key'] === '(SAVED)') {
        $row = $db->fetch("SELECT value FROM settings WHERE `key` = 'strm_api_key' LIMIT 1");
        $cfg['api_key'] = trim((string)($row['value'] ?? ''));
    }
    if ($cfg['base_url'] === '') {
        echo json_encode(['success' => false, 'message' => '请先填写 withUstrm 服务地址'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $info = withu_strm_request('info', [], $cfg, 8);
    if (!$info['success']) {
        echo json_encode(['success' => false, 'message' => $info['message']], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $counts = withu_strm_request('counts', [], $cfg, 8);
    echo json_encode([
        'success' => true,
        'message' => '',
        'data' => [
            'server_name' => (string)($info['data']['serverName'] ?? 'withUstrm'),
            'version'     => (string)($info['data']['version'] ?? ''),
            'total'  => (int)($counts['data']['total'] ?? 0),
            'movie'  => (int)($counts['data']['movie'] ?? 0),
            'series' => (int)($counts['data']['series'] ?? 0),
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$settings = [];
foreach ($db->fetchAll('SELECT `key`, `value` FROM settings') as $row) {
    $settings[(string)$row['key']] = (string)($row['value'] ?? '');
}

if (isset($_GET['success']) && $_GET['success'] === '1') {
    $success = 'withUstrm 对接设置已保存';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $enabled = isset($_POST['strm_enabled']) ? '1' : '0';
    $baseUrl = withu_strm_normalize_base((string)($_POST['strm_backend_url'] ?? ''));
    $apiKey  = trim((string)($_POST['strm_api_key'] ?? ''));

    if ($enabled === '1') {
        if ($baseUrl === '') {
            $error = '启用对接前请填写 withUstrm 服务地址。';
        } elseif (!preg_match('#^https?://#i', $baseUrl)) {
            $error = '服务地址必须以 http:// 或 https:// 开头。';
        } elseif ($apiKey === '') {
            $error = '启用对接前请填写 API Key（在 withUstrm 后台"系统设置 → 外部媒体库接口"中开启并复制）。';
        } else {
            // 启用时立即验证一次连通性，避免保存后发现配错
            $check = withu_strm_request('health', [], ['base_url' => $baseUrl, 'api_key' => $apiKey], 8);
            if (!$check['success']) {
                $error = '无法连接 withUstrm 外部接口：' . $check['message'];
            }
        }
    }

    if ($error === '') {
        withu_strm_setting_save($db, 'strm_enabled', $enabled);
        withu_strm_setting_save($db, 'strm_backend_url', $baseUrl);
        withu_strm_setting_save($db, 'strm_api_key', $apiKey);
        header('Location: /admin/strm_settings.php?success=1');
        exit;
    }

    $settings = array_merge($settings, [
        'strm_enabled'     => $enabled,
        'strm_backend_url' => $baseUrl,
        'strm_api_key'     => $apiKey,
    ]);
}

$currentEnabled = ($settings['strm_enabled'] ?? '0') === '1';
$currentUrl = withu_strm_normalize_base($settings['strm_backend_url'] ?? '');
$apiKeyPlaceholder = !empty($settings['strm_api_key']) ? '已保存，留空则维持不变' : '';
$savedKey = (string)($settings['strm_api_key'] ?? '');
// 表单提交失败时优先展示本次输入的 Key；正常展示时用占位符避免明文回显
$formKey = $_POST['strm_api_key'] ?? '';

$adminPage = 'strm_settings';
$adminNarrow = true;
include __DIR__ . '/header.php';
?>

<section class="admin-page-title">
    <h1>withUstrm 媒体库</h1>
    <p>配置与 withUstrm 媒体服务的对接，开启后在「媒体库浏览」中查看与播放</p>
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

<section class="admin-grid admin-grid-single">
    <div class="admin-card">
        <div class="admin-card-header"><div><div class="admin-card-title">连接配置 <button type="button" class="admin-help-toggle" title="查看说明" aria-label="查看说明" aria-expanded="false"><i class="ti ti-info-circle"></i></button></div></div></div>
        <div class="admin-card-help"><div class="admin-card-subtitle">服务地址、外部接口鉴权</div></div>

        <form method="POST" id="strm-form" novalidate>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="saved_api_key_present" value="<?php echo $savedKey !== '' ? '1' : '0'; ?>">

            <label class="switch" style="margin-bottom:.85rem;">
                <input type="checkbox" name="strm_enabled" value="1" id="strm-enabled" <?php echo $currentEnabled ? 'checked' : ''; ?>>
                <span class="switch-track"><span class="switch-thumb"></span></span>
                <span class="switch-label">启用 withUstrm 媒体库对接</span>
            </label>

            <div class="form-group" style="margin-bottom:.65rem;">
                <label style="display:block;font-size:.85rem;margin-bottom:.25rem;">withUstrm 服务地址</label>
                <input type="text" name="strm_backend_url" id="strm-url" value="<?php echo e($currentUrl); ?>" placeholder="http://127.0.0.1:8081" style="width:100%;padding:.55rem .75rem;border-radius:.75rem;border:1px solid rgba(148,163,184,.6);font-size:.9rem;">
                <p style="margin:.3rem 0 0;font-size:.78rem;color:var(--text-light);">
                    即 withUstrm Spring Boot 后端地址（默认端口 8081）。本机部署填 http://127.0.0.1:8081；远程部署请使用内网地址，不要暴露到公网。
                </p>
            </div>

            <div class="form-group" style="margin-bottom:.65rem;">
                <label style="display:block;font-size:.85rem;margin-bottom:.25rem;">外部接口 API Key</label>
                <input type="password" name="strm_api_key" id="strm-key" value="<?php echo e($formKey); ?>" placeholder="<?php echo e($apiKeyPlaceholder !== '' ? $apiKeyPlaceholder : '在 withUstrm 系统设置中生成后粘贴到这里'); ?>" autocomplete="new-password" style="width:100%;padding:.55rem .75rem;border-radius:.75rem;border:1px solid rgba(148,163,184,.6);font-size:.9rem;">
                <p style="margin:.3rem 0 0;font-size:.78rem;color:var(--text-light);">
                    打开 withUstrm 管理界面 → 系统设置 → 外部媒体库接口，启用后会生成 API Key（对应 <code>external.enabled</code> 与 <code>external.apiKey</code>）。Key 只保存在主站服务器端。
                </p>
            </div>

            <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.9rem;">
                <button type="button" class="btn" id="strm-probe-btn" style="justify-content:center;">
                    <i class="ti ti-plug-connected"></i><span>测试连接</span>
                </button>
                <button type="submit" class="btn btn-primary" style="justify-content:center;flex:1;min-width:140px;">
                    <i class="fas fa-save"></i><span>保存设置</span>
                </button>
            </div>
            <div id="strm-probe-result" style="margin-top:.65rem;display:none;border-radius:.75rem;padding:.6rem .75rem;font-size:.82rem;"></div>
        </form>
    </div>

    <div class="admin-card">
        <div class="admin-card-header"><div><div class="admin-card-title">对接说明 <button type="button" class="admin-help-toggle" title="查看说明" aria-label="查看说明" aria-expanded="false"><i class="ti ti-info-circle"></i></button></div></div></div>
        <div class="admin-card-help"><div class="admin-card-subtitle">对接前的准备与安全边界</div></div>
        <ol style="margin:.2rem 0 .4rem;padding-left:1.1rem;font-size:.82rem;line-height:1.7;color:var(--text);">
            <li>确认 withUstrm 服务已在运行（Spring Boot 后端默认监听 <code>127.0.0.1:8081</code>）。</li>
            <li>在 withUstrm 管理界面启用<strong>外部媒体库接口</strong>并复制生成的 API Key。</li>
            <li>在这里填入服务地址与 Key，点击「测试连接」看到媒体计数后再保存。</li>
            <li>保存成功后前往<a href="/admin/media_library.php" style="color:var(--v3-pink,#f26d9c);">「媒体库浏览」</a>查看电影、剧集与动漫。</li>
        </ol>
        <p style="margin:.4rem 0 0;font-size:.78rem;line-height:1.7;color:var(--text-light);">
            安全说明：API Key 仅保存在主站数据库的服务器端；媒体库页面与播放地址解析都要求先登录情侣账号，未登录无法访问。withUstrm 的其他管理接口不受影响。
        </p>
        <?php if ($currentEnabled && $currentUrl !== ''): ?>
            <p style="margin:.6rem 0 0;font-size:.78rem;color:#16a34a;"><i class="fas fa-check-circle"></i> 当前状态：对接已启用，地址 <?php echo e($currentUrl); ?></p>
        <?php endif; ?>
    </div>
</section>

<script>
(function () {
    var btn = document.getElementById('strm-probe-btn');
    var out = document.getElementById('strm-probe-result');
    if (!btn || !out) return;
    var csrfQuery = '<?php echo function_exists('csrf_query') ? csrf_query() : ''; ?>';

    function show(ok, text) {
        out.style.display = 'block';
        out.textContent = text;
        out.style.border = ok ? '1px solid rgba(34,197,94,.35)' : '1px solid rgba(248,113,113,.35)';
        out.style.background = ok ? 'rgba(34,197,94,.05)' : 'rgba(248,113,113,.05)';
        out.style.color = ok ? '#15803d' : '#b91c1c';
    }

    btn.addEventListener('click', function () {
        var url = (document.getElementById('strm-url') || {}).value || '';
        var key = (document.getElementById('strm-key') || {}).value || '';
        var savedPresent = document.querySelector('input[name="saved_api_key_present"]');
        if (!key && savedPresent && savedPresent.value === '1') key = '(SAVED)';
        btn.disabled = true;
        out.style.display = 'none';
        fetch('/admin/strm_settings.php?action=probe&url=' + encodeURIComponent(url)
                + '&key=' + encodeURIComponent(key) + '&' + csrfQuery, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) {
                    var d = res.data || {};
                    show(true, '✅ 连接成功：' + (d.server_name || 'withUstrm')
                        + (d.version ? ' v' + d.version : '')
                        + '｜媒体总数 ' + d.total + '（电影 ' + d.movie + ' · 剧集 ' + d.series + '）');
                } else {
                    show(false, '❌ ' + (res.message || '连接失败'));
                }
            })
            .catch(function () { show(false, '❌ 网络异常，请求未能完成'); })
            .finally(function () { btn.disabled = false; });
    });
}());
</script>

<?php include __DIR__ . '/footer.php'; ?>
