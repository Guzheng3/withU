<?php
/**
 * 安装脚本
 */

// 设置 UTF-8 编码
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// 先判断是否已安装：已安装时禁止访问安装页面，直接跳转到首页
if (file_exists(__DIR__ . '/.installed')) {
    header('Location: /');
    exit;
}

// 再检查安装解锁文件：没有 enable_install.lock 时禁止访问安装脚本
$installLockFile = __DIR__ . '/enable_install.lock';
if (!file_exists($installLockFile)) {
    http_response_code(403);
    echo '安装未解锁。请在网站根目录创建 enable_install.lock 文件后再访问本页面。';
    exit;
}

// 确保安装时存在全局配置文件（某些环境可能未上传 config/config.php）
$configPath = __DIR__ . '/config/config.php';
if (!file_exists($configPath)) {
    if (!is_dir(__DIR__ . '/config')) {
        mkdir(__DIR__ . '/config', 0755, true);
    }
    $defaultConfig = <<<'PHP'
<?php
/**
 * 应用全局配置文件（前台和后台共用）
 */

// 调试模式：上线后建议为 false
define('DEBUG_MODE', false);

// 错误报告：开发环境显示错误，上线后仅记录到日志
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1');

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 应用根目录
define('ROOT_PATH', dirname(__DIR__));

// 应用 URL：根据当前请求自动生成（包含协议）
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $scheme . '://' . $host);

// Session 设置
ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
if ($scheme === 'https') {
    ini_set('session.cookie_secure', '1');
}
ini_set('session.cookie_samesite', 'Lax');

// 安全相关 HTTP 响应头（仅在非 CLI 环境下设置）
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    if ($scheme === 'https') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// 文件上传设置
define('UPLOAD_DIR', ROOT_PATH . '/uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// 安全密钥（请在生产环境中改为随机字符串）
define('SECRET_KEY', 'your-secret-key-change-this-in-production');

// 登录防爆破配置
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_ATTEMPT_WINDOW', 900);
define('LOGIN_LOCKOUT_SECONDS', 900);

// 站点名称
define('SITE_NAME', '我们的小情侣网站');
PHP;

    file_put_contents($configPath, $defaultConfig);
}

require_once $configPath;
require_once __DIR__ . '/core/helpers.php';

$error = '';
$success = '';
// 优先从 POST 中读取步骤（因为表单是 POST 提交），再回退到 GET，最后默认为 1
$step = isset($_POST['step'])
    ? intval($_POST['step'])
    : (isset($_GET['step']) ? intval($_GET['step']) : 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 1) {
    // 第一步：数据库配置
    $dbConfig = [
        'host' => $_POST['host'] ?? 'localhost',
        'port' => intval($_POST['port'] ?? 3306),
        'dbname' => $_POST['dbname'] ?? 'couple_website',
        'username' => $_POST['username'] ?? 'root',
        'password' => $_POST['password'] ?? '',
    ];

    // 为后续运行统一补充 charset 和 PDO 选项，避免安装后配置不完整
    if (!isset($dbConfig['charset'])) {
        // 默认优先使用 utf8mb4，后续在 Database 类中会自动回退到 utf8（兼容老 MySQL）
        $dbConfig['charset'] = 'utf8mb4';
    }
    if (!isset($dbConfig['options'])) {
        $dbConfig['options'] = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
    }
    
    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;charset=utf8mb4',
            $dbConfig['host'],
            $dbConfig['port']
        );
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
        
        // 创建数据库
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbConfig['dbname']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$dbConfig['dbname']}`");
        
        // 保存配置（安装完成后，系统将直接使用该配置）
        $configContent = "<?php\nreturn " . var_export($dbConfig, true) . ";\n";
        file_put_contents(__DIR__ . '/config/database.php', $configContent);
        
        // 导入数据库结构
        $sql = file_get_contents(__DIR__ . '/database/schema.sql');
        $statements = explode(';', $sql);
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }
        
        $step = 2;
    } catch (PDOException $e) {
        $error = '数据库连接失败：' . $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    // 第二步：创建管理员账号
    require_once __DIR__ . '/core/Database.php';
    require_once __DIR__ . '/core/Auth.php';

    // 安装时也允许选择角色（男/女），内部仍使用 user1/user2
    $role = $_POST['role'] ?? 'user1';
    if (!in_array($role, ['user1', 'user2'], true)) {
        $role = 'user1';
    }

    $password        = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    $auth = new Auth();
    if ($password !== $passwordConfirm) {
        $error = '两次输入的密码不一致';
    } else {
        $result = $auth->register(
            $_POST['username'] ?? '',
            $password,
            $_POST['nickname'] ?? '',
            $role
        );

        if ($result['success']) {
            // 管理员创建成功，进入第三步：站点基础信息设置
            $success = '管理员账号创建成功，请继续配置网站基础信息。';
            $step    = 3;
        } else {
            $error = $result['message'];
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 3) {
    // 第三步：设置网站基础信息
    require_once __DIR__ . '/core/Database.php';
    $db = Database::getInstance();

    $siteTitle       = trim($_POST['site_title'] ?? '');
    $siteDescription = trim($_POST['site_description'] ?? '');
    $loveDate        = trim($_POST['love_date'] ?? '');

    if ($siteTitle === '') {
        $siteTitle = SITE_NAME;
    }

    try {
        // 兼容安装向导中的恋爱开始时间：允许使用日期或精确到秒
        if ($loveDate !== '') {
            $normalizedLove = str_replace(' ', 'T', $loveDate);
            $dtLove = date_create($normalizedLove);
            if ($dtLove instanceof DateTime) {
                $loveDate = $dtLove->format('Y-m-d H:i:s');
            }
        }

        $settingsToSave = [
            'site_title'       => $siteTitle,
            'site_description' => $siteDescription,
            'love_date'        => $loveDate,
        ];

        foreach ($settingsToSave as $key => $value) {
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

        // 标记安装完成
        file_put_contents(__DIR__ . '/.installed', date('Y-m-d H:i:s'));
        // 安装完成后，删除安装解锁文件，避免长期遗留提升误操作风险
        if (file_exists($installLockFile)) {
            @unlink($installLockFile);
        }
        $success = '安装完成！';
        $step    = 4;
    } catch (Exception $e) {
        $error = '保存网站信息失败：' . $e->getMessage();
        $step  = 3;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>安装向导 - <?php echo e(SITE_NAME); ?></title>
    <link rel="stylesheet" href="/admin-assets/vendor/fontawesome/css/all.min.css">
    <style>
    :root{--brand:#e75480;--brand-dark:#c23b64;}
    *{box-sizing:border-box;}
    body{background:linear-gradient(135deg,#ffeef5 0%,#f4f6fb 60%,#eef2ff 100%);
      font-family:"Nunito","Microsoft YaHei","PingFang SC",sans-serif;margin:0;min-height:100vh;
      display:flex;align-items:center;justify-content:center;padding:24px 12px;}
    .install-card{width:520px;max-width:94vw;background:#fff;border-radius:18px;
      box-shadow:0 18px 50px rgba(180,60,110,.15);overflow:hidden;}
    .install-head{background:linear-gradient(135deg,#e75480,#c23b64);color:#fff;padding:34px 30px 26px;text-align:center;}
    .install-head h2{margin:0 0 6px;font-weight:800;font-size:22px;letter-spacing:.5px;}
    .install-head h2 i{margin-right:8px;}
    .install-head p{margin:0;opacity:.88;font-size:13px;line-height:1.6;}
    .install-body{padding:26px 30px 30px;}
    /* 步骤指示器 */
    .stepper{display:flex;align-items:flex-start;justify-content:center;margin-bottom:26px;}
    .stepper-step{display:flex;flex-direction:column;align-items:center;gap:7px;width:84px;}
    .stepper-circle{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;
      background:#f2f3f8;color:#b0b6c6;border:2px solid #e6e8f0;transition:.25s;}
    .stepper-circle i{font-size:14px;}
    .stepper-step.done .stepper-circle{background:#ffe9f1;color:#e75480;border-color:#ffd0e0;}
    .stepper-step.active .stepper-circle{background:linear-gradient(135deg,var(--brand),var(--brand-dark));
      color:#fff;border-color:transparent;box-shadow:0 5px 14px rgba(231,84,128,.32);}
    .stepper-label{font-size:12px;color:#9aa0b0;font-weight:600;letter-spacing:.3px;}
    .stepper-step.done .stepper-label,.stepper-step.active .stepper-label{color:#e75480;}
    .stepper-line{flex:1;height:2px;background:#eceef4;border-radius:2px;margin-top:17px;margin-left:4px;margin-right:4px;transition:.25s;}
    .stepper-line.filled{background:linear-gradient(90deg,var(--brand),var(--brand-dark));}
    /* 表单 */
    .form-group{margin-bottom:16px;}
    .form-group label{font-size:13px;color:#555;font-weight:600;margin-bottom:6px;display:flex;align-items:center;gap:6px;}
    .form-group label i{color:var(--brand);width:16px;text-align:center;}
    .form-group input[type="text"],.form-group input[type="number"],.form-group input[type="password"],
    .form-group input[type="datetime-local"],.form-group textarea{width:100%;padding:11px 14px;border:1.5px solid #e3e6f0;
      border-radius:10px;font-size:14px;outline:none;transition:.2s;font-family:inherit;background:#fff;color:#333;}
    .form-group input:focus,.form-group textarea:focus{border-color:var(--brand);box-shadow:0 0 0 3px rgba(231,84,128,.12);}
    .form-group textarea{min-height:96px;resize:vertical;line-height:1.6;}
    .form-group .hint{font-size:12px;color:#9aa0b0;margin-top:6px;line-height:1.5;}
    /* 角色选择 */
    .role-toggle{display:flex;gap:10px;}
    .role-toggle .role-option{flex:1;cursor:pointer;}
    .role-toggle .role-option input{display:none;}
    .role-toggle .role-option-inner{display:flex;flex-direction:column;align-items:center;padding:12px 8px;gap:4px;
      border:1.5px solid #e3e6f0;border-radius:12px;transition:.2s;text-align:center;}
    .role-toggle .role-option input:checked+.role-option-inner{border-color:var(--brand);background:#fff5f8;
      color:var(--brand);box-shadow:0 4px 12px rgba(231,84,128,.12);}
    .role-toggle .role-option-inner i{font-size:24px;}
    .role-toggle .role-option-inner .role-text-main{font-size:14px;font-weight:700;}
    .role-toggle .role-option-inner .role-text-sub{font-size:11px;color:#9aa0b0;}
    .role-male-icon{color:#5dade2;}
    .role-female-icon{color:var(--brand);}
    /* 按钮 */
    .btn-row{display:flex;gap:12px;margin-top:8px;}
    .btn{flex:1;padding:12px;border:none;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer;transition:.2s;
      font-family:inherit;display:inline-flex;align-items:center;justify-content:center;gap:6px;text-decoration:none;}
    .btn .fa{font-size:14px;}
    .btn-primary{background:linear-gradient(135deg,#e75480,#c23b64);color:#fff;}
    .btn-primary:hover{filter:brightness(1.08);transform:translateY(-1px);box-shadow:0 4px 14px rgba(231,84,128,.3);}
    .btn-ghost{background:#f6f7fb;color:#666;border:1.5px solid #e6e8f0;}
    .btn-ghost:hover{background:#eef0f6;color:#333;}
    /* 提示 */
    .alert{padding:10px 13px;border-radius:9px;font-size:13px;margin-bottom:16px;line-height:1.6;}
    .alert i{margin-right:4px;}
    .alert-error{background:#fff1f1;color:#c0392b;border:1px solid #ffd6d6;}
    .alert-success{background:#f0faf0;color:#27ae60;border:1px solid #d5f5d5;}
    /* 完成页 */
    .done-wrap{text-align:center;padding:8px 0 4px;}
    .done-icon{width:76px;height:76px;border-radius:50%;background:linear-gradient(135deg,var(--brand),var(--brand-dark));
      color:#fff;display:flex;align-items:center;justify-content:center;font-size:34px;margin:0 auto 18px;
      box-shadow:0 10px 26px rgba(231,84,128,.3);}
    .done-wrap h3{margin:0 0 8px;font-size:20px;color:#333;}
    .done-wrap p{margin:0 0 14px;font-size:14px;color:#777;line-height:1.7;}
    .done-note{background:#fff8fa;border:1px solid #ffdce8;color:#9b5672;border-radius:10px;padding:12px 14px;
      font-size:12.5px;line-height:1.7;margin:18px 0 20px;text-align:left;}
    .done-note code{background:#ffe9f1;color:#c23b64;padding:1px 6px;border-radius:5px;font-size:11.5px;}
    .footer-note{text-align:center;margin-top:18px;font-size:12px;color:#b0b5c4;}
    @media (prefers-reduced-motion: reduce){*{transition:none !important;}}
    @media (max-width:480px){.install-body{padding:20px 18px 24px;}.install-head{padding:28px 18px 22px;}}
    </style>
</head>
<body>
<div class="install-card">
    <div class="install-head">
        <h2><i class="fas fa-heart"></i>安装向导</h2>
        <p>三步完成数据库配置、账号创建与站点信息设置</p>
    </div>
    <div class="install-body">
        <?php
        // 步骤指示器（完成页不显示）
        if ($step <= 3) {
            $installSteps = [
                ['icon' => 'fa-database',  'label' => '数据库'],
                ['icon' => 'fa-user-plus', 'label' => '账号'],
                ['icon' => 'fa-palette',   'label' => '站点信息'],
            ];
            echo '<div class="stepper">';
            foreach ($installSteps as $idx => $s) {
                $num   = $idx + 1;
                $state = $step > $num ? 'done' : ($step === $num ? 'active' : '');
                if ($num > 1) {
                    $lineCls = $step >= $num ? ' stepper-line filled' : ' stepper-line';
                    echo '<div class="' . trim($lineCls) . '"></div>';
                }
                echo '<div class="stepper-step ' . $state . '">';
                echo '<span class="stepper-circle">' . ($state === 'done' ? '<i class="fas fa-check"></i>' : '<i class="fas ' . $s['icon'] . '"></i>') . '</span>';
                echo '<span class="stepper-label">' . $s['label'] . '</span>';
                echo '</div>';
            }
            echo '</div>';
        }
        ?>

        <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><?php echo e($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i><?php echo e($success); ?></div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
        <form method="POST" novalidate>
            <input type="hidden" name="step" value="1">
            <div class="form-group">
                <label><i class="fas fa-server"></i>数据库主机</label>
                <input type="text" name="host" value="localhost" autofocus>
            </div>
            <div class="form-group">
                <label><i class="fas fa-plug"></i>端口</label>
                <input type="number" name="port" value="3306">
            </div>
            <div class="form-group">
                <label><i class="fas fa-database"></i>数据库名称</label>
                <input type="text" name="dbname" value="couple_website">
                <div class="hint">库不存在时会自动创建，字符集 utf8mb4。</div>
            </div>
            <div class="form-group">
                <label><i class="fas fa-user"></i>数据库用户名</label>
                <input type="text" name="username" value="root">
            </div>
            <div class="form-group">
                <label><i class="fas fa-lock"></i>数据库密码</label>
                <input type="password" name="password" placeholder="本地开发默认留空">
            </div>
            <div class="btn-row">
                <button type="submit" class="btn btn-primary"><i class="fas fa-arrow-right"></i>下一步：创建账号</button>
            </div>
        </form>
        <?php elseif ($step === 2): ?>
        <form method="POST" novalidate>
            <input type="hidden" name="step" value="2">
            <div class="form-group">
                <label><i class="fas fa-user"></i>用户名</label>
                <input type="text" name="username" maxlength="32" placeholder="字母和数字，3~32 位，用于登录" autocomplete="username" autofocus>
            </div>
            <div class="form-group">
                <label><i class="fas fa-user-tag"></i>昵称</label>
                <input type="text" name="nickname" maxlength="32" placeholder="展示在网站里的名字">
            </div>
            <div class="form-group">
                <label><i class="fas fa-lock"></i>密码</label>
                <input type="password" name="password" minlength="8" placeholder="至少 8 位" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label><i class="fas fa-check-double"></i>确认密码</label>
                <input type="password" name="password_confirm" minlength="8" placeholder="再输入一次密码" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label><i class="fas fa-user-friends"></i>角色（你在网站中的身份）</label>
                <div class="role-toggle">
                    <label class="role-option">
                        <input type="radio" name="role" value="user1" checked>
                        <span class="role-option-inner">
                            <i class="fas fa-mars role-male-icon"></i>
                            <span class="role-text-main">男生</span>
                            <span class="role-text-sub">（角色 1）</span>
                        </span>
                    </label>
                    <label class="role-option">
                        <input type="radio" name="role" value="user2">
                        <span class="role-option-inner">
                            <i class="fas fa-venus role-female-icon"></i>
                            <span class="role-text-main">女生</span>
                            <span class="role-text-sub">（角色 2）</span>
                        </span>
                    </label>
                </div>
                <div class="hint">第一个账号创建后，可通过后台生成的邀请链接注册另一半。</div>
            </div>
            <div class="btn-row">
                <a href="?step=1" class="btn btn-ghost"><i class="fas fa-arrow-left"></i>上一步</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-arrow-right"></i>下一步：站点信息</button>
            </div>
        </form>
        <?php elseif ($step === 3): ?>
        <form method="POST" novalidate>
            <input type="hidden" name="step" value="3">
            <div class="form-group">
                <label><i class="fas fa-heading"></i>网站标题</label>
                <input type="text" name="site_title"
                       value="<?php echo isset($_POST['site_title']) ? e($_POST['site_title']) : e(SITE_NAME); ?>"
                       placeholder="例如：我们的小情侣网站" autofocus>
            </div>
            <div class="form-group">
                <label><i class="fas fa-align-left"></i>网站描述</label>
                <textarea name="site_description"
                          placeholder="简单介绍一下你们的故事～"><?php echo isset($_POST['site_description']) ? e($_POST['site_description']) : ''; ?></textarea>
            </div>
            <div class="form-group">
                <label><i class="fas fa-calendar-days"></i>恋爱开始时间</label>
                <input type="datetime-local" name="love_date"
                       value="<?php echo isset($_POST['love_date']) ? e($_POST['love_date']) : ''; ?>"
                       placeholder="未设置" step="1">
                <div class="hint">用于计算在一起的天数，支持精确到秒（可留空，未设置时默认按当前日期开始计算）。</div>
            </div>
            <div class="btn-row">
                <a href="?step=2" class="btn btn-ghost"><i class="fas fa-arrow-left"></i>上一步</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check-circle"></i>保存并完成安装</button>
            </div>
        </form>
        <?php elseif ($step === 4): ?>
        <div class="done-wrap">
            <div class="done-icon"><i class="fas fa-check"></i></div>
            <h3>安装完成！</h3>
            <p>系统已成功安装，现在可以开始使用了。</p>
            <div class="done-note">
                出于安全考虑，建议删除站点根目录下的 <code>enable_install.lock</code> 文件
                （如无特殊需要，也可以备份后删除 <code>install.php</code>）。
            </div>
            <div class="btn-row">
                <a href="/login.php" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i>前往登录</a>
            </div>
        </div>
        <?php endif; ?>

        <div class="footer-note">withU · 情侣网站安装向导</div>
    </div>
</div>
</body>
</html>
