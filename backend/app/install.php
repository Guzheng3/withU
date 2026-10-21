<?php
/**
 * 安装脚本
 */

// 设置 UTF-8 编码
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// 安装完成后的清理请求（删除 lock / install.php）需要在 .installed 存在时仍可访问
$installLockFile  = __DIR__ . '/enable_install.lock';
$cleanupTokenFile = __DIR__ . '/runtime/install-cleanup.token';
$isCleanupRequest = ($_SERVER['REQUEST_METHOD'] === 'POST' && (int) ($_POST['step'] ?? 0) === 4);

// 已安装：普通访问跳首页，仅允许携带有效令牌的清理请求
if (file_exists(__DIR__ . '/.installed')) {
    if ($isCleanupRequest) {
        $savedToken = is_file($cleanupTokenFile) ? trim((string) file_get_contents($cleanupTokenFile)) : '';
        $givenToken = (string) ($_POST['cleanup_token'] ?? '');
        if ($savedToken === '' || $givenToken === '' || !hash_equals($savedToken, $givenToken)) {
            header('Location: /');
            exit;
        }
    } else {
        header('Location: /');
        exit;
    }
} elseif (!file_exists($installLockFile)) {
    // 未安装时仍需安装解锁文件
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

/**
 * 统计当前数据库配置下的活跃用户数。
 * 直接使用 PDO，避免 Database 单例在安装过程中缓存旧配置。
 */
function install_active_user_count(): int
{
    $configFile = __DIR__ . '/config/database.php';
    if (!is_file($configFile)) {
        return 0;
    }
    try {
        $cfg = include $configFile;
        if (!is_array($cfg) || empty($cfg['dbname'])) {
            return 0;
        }
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'] ?? 'localhost',
            (int) ($cfg['port'] ?? 3306),
            $cfg['dbname'],
            $cfg['charset'] ?? 'utf8mb4'
        );
        $pdo = new PDO($dsn, $cfg['username'] ?? '', $cfg['password'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        return (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

// 优先从 POST 中读取步骤（因为表单是 POST 提交），再回退到 GET，最后默认为 1
$step = isset($_POST['step'])
    ? intval($_POST['step'])
    : (isset($_GET['step']) ? intval($_GET['step']) : 1);

$installHasAdmin = install_active_user_count() > 0;
if ($installHasAdmin) {
    // 已创建管理员：默认进入站点信息；直接访问账号步骤也自动跳过
    if (!isset($_GET['step']) && !isset($_POST['step'])) {
        $step = 3;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $step === 2) {
        $step = 3;
    }
}

// 引导顺序约束：先创建管理员账号，才允许进入站点信息步骤
if ($step === 3 && is_file(__DIR__ . '/config/database.php')) {
    try {
        require_once __DIR__ . '/core/Database.php';
        $installDb      = Database::getInstance();
        $installUserRow = $installDb->fetch("SELECT COUNT(*) AS c FROM users WHERE status = 'active'");
        if ((int) ($installUserRow['c'] ?? 0) === 0) {
            header('Location: ?step=2');
            exit;
        }
    } catch (Throwable $e) {
        // 数据库尚未就绪时继续渲染，提交阶段会给出具体错误
    }
}

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
        
        // 目标数据库已有管理员时，跳过账号创建步骤
        $step = install_active_user_count() > 0 ? 3 : 2;
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
    $qq              = trim((string) ($_POST['qq'] ?? ''));

    $auth = new Auth();
    if (!preg_match('/^[1-9][0-9]{4,10}$/', $qq)) {
        $error = '请输入 5~11 位 QQ 号（QQ 号将作为唯一登录账号）';
    } elseif ($password !== $passwordConfirm) {
        $error = '两次输入的密码不一致';
    } else {
        // QQ 号直接作为 username，既满足唯一登录，也复用 Auth 的 QQ 头像写入逻辑
        $result = $auth->register(
            $qq,
            $password,
            $_POST['nickname'] ?? '',
            $role,
            null,
            $qq
        );

        if ($result['success']) {
            // 管理员创建成功，进入第三步：站点基础信息设置
            $success = '管理员账号创建成功，登录账号为 QQ ' . $qq . '，请继续配置网站基础信息。';
            $step    = 3;
        } else {
            $error = $result['message'];
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 3) {
    // 第三步：设置网站基础信息、备案号与在一起的时间
    require_once __DIR__ . '/core/Database.php';
    $db = Database::getInstance();

    $siteTitle       = trim($_POST['site_title'] ?? '');
    $siteDescription = trim($_POST['site_description'] ?? '');
    $icpBeian        = trim($_POST['icp_beian'] ?? '');
    $loveDate        = trim($_POST['love_date'] ?? '');

    if ($siteTitle === '') {
        $siteTitle = SITE_NAME;
    }

    if ($error === '') {
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
                'icp_beian'        => $icpBeian,
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

            // 生成一次性清理令牌，完成页由用户选择是否删除 lock / install.php
            try {
                $cleanupToken = bin2hex(random_bytes(16));
            } catch (Throwable $e) {
                $cleanupToken = md5(uniqid('', true));
            }
            $runtimeDir = __DIR__ . '/runtime';
            if (!is_dir($runtimeDir)) {
                @mkdir($runtimeDir, 0755, true);
            }
            if (@file_put_contents($cleanupTokenFile, $cleanupToken) === false) {
                // 令牌无法写入时退回自动删除 lock，避免完成页无法提交
                $cleanupToken = '';
                if (file_exists($installLockFile)) {
                    @unlink($installLockFile);
                }
            } else {
                @chmod($cleanupTokenFile, 0600);
            }

            $success = '安装完成！';
            $step = 4;
        } catch (Exception $e) {
            $error = '保存网站信息失败：' . $e->getMessage();
            $step  = 3;
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 4) {
    // 安装完成后的可选清理（默认删除 lock）
    $savedToken = is_file($cleanupTokenFile) ? trim((string) file_get_contents($cleanupTokenFile)) : '';
    $givenToken = (string) ($_POST['cleanup_token'] ?? '');
    if ($savedToken === '' || $givenToken === '' || !hash_equals($savedToken, $givenToken)) {
        header('Location: /');
        exit;
    }

    if (isset($_POST['delete_lock']) && file_exists($installLockFile)) {
        @unlink($installLockFile);
    }
    if (isset($_POST['delete_installer'])) {
        @unlink(__FILE__);
    }
    @unlink($cleanupTokenFile);
    $step = 5;
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
    /* QQ 账号输入与头像预览 */
    .qq-input-row{display:flex;align-items:center;gap:12px;}
    .qq-avatar{width:46px;height:46px;border-radius:50%;overflow:hidden;background:#f2f3f8;flex-shrink:0;
      border:2px solid #ffe1ec;display:flex;align-items:center;justify-content:center;}
    .qq-avatar img{width:100%;height:100%;object-fit:cover;display:block;}
    .qq-input-row input[type="text"]{flex:1;min-width:0;}
    /* 角色选择 */
    .role-toggle{display:flex;justify-content:center;align-items:stretch;gap:16px;}
    .role-toggle .role-option{display:block;flex:0 1 200px;min-width:0;margin-bottom:0;color:inherit;cursor:pointer;}
    .role-toggle .role-option input{display:none;}
    .role-toggle .role-option-inner{display:flex;flex-direction:column;align-items:center;justify-content:center;
      width:100%;box-sizing:border-box;min-height:76px;padding:10px 16px;gap:4px;border:1.5px solid #e3e6f0;border-radius:14px;
      background:#fbfcfe;color:#374151;transition:border-color .2s,background .2s,box-shadow .2s,transform .2s;text-align:center;}
    .role-toggle .role-option:hover .role-option-inner{border-color:#f2b8ca;background:#fffafc;transform:translateY(-1px);}
    .role-toggle .role-option input:checked+.role-option-inner{border-color:var(--brand);
      background:linear-gradient(180deg,#fff9fb,#fff0f5);color:var(--brand);
      box-shadow:0 6px 16px rgba(231,84,128,.14);}
    .role-toggle .role-option-inner i{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;
      justify-content:center;font-size:18px;background:#eef4fb;transition:background .2s,color .2s;}
    .role-toggle .role-option-inner .role-female-icon{background:#fdeef4;}
    .role-toggle .role-option input:checked+.role-option-inner i{background:#fff;}
    .role-toggle .role-option-inner .role-text-main{font-size:15px;font-weight:700;letter-spacing:.02em;}
    .role-male-icon{color:#5dade2;}
    .role-female-icon{color:var(--brand);}
    /* 在一起的时间：分段输入 */
    .form-group .datetime-seg{display:flex;align-items:center;gap:6px;}
    .form-group .datetime-seg input{flex:1 1 0;width:auto;min-width:0;text-align:center;padding:11px 4px;
      font-variant-numeric:tabular-nums;letter-spacing:.02em;}
    .form-group .datetime-seg .seg-year{flex:1.5 1 0;}
    .datetime-seg .seg-sep{color:#b0b6c6;font-weight:700;flex-shrink:0;}
    .datetime-seg .seg-gap{width:6px;}
    @media (max-width:480px){
      .form-group .datetime-seg{gap:4px;}
      .form-group .datetime-seg input{padding:10px 2px;font-size:13px;}
    }
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
    .done-note .cleanup-option{display:flex;align-items:flex-start;gap:8px;margin:10px 0;font-size:13px;line-height:1.6;cursor:pointer;}
    .done-note .cleanup-option input[type="checkbox"]{margin-top:3px;accent-color:var(--brand);flex-shrink:0;}
    .done-note .btn-row{margin-top:14px;}
    .footer-note{text-align:center;margin-top:18px;font-size:12px;color:#b0b5c4;}
    @media (prefers-reduced-motion: reduce){*{transition:none !important;}}
    @media (max-width:480px){.install-body{padding:20px 18px 24px;}.install-head{padding:28px 18px 22px;}}
    </style>
</head>
<body>
<div class="install-card">
    <div class="install-head">
        <h2><i class="fas fa-heart"></i>安装向导</h2>
        <p>三步完成数据库配置、管理员账号创建与站点信息设置（含备案号、在一起的时间）</p>
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
        <?php if ($installHasAdmin): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i>检测到当前数据库已有管理员账号，下一步将直接进入站点信息。</div>
        <?php endif; ?>
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
                <button type="submit" class="btn btn-primary"><i class="fas fa-arrow-right"></i><?php echo $installHasAdmin ? '下一步：站点信息' : '下一步：创建账号'; ?></button>
            </div>
        </form>
        <?php elseif ($step === 2): ?>
        <form method="POST" novalidate>
            <input type="hidden" name="step" value="2">
            <div class="form-group">
                <label><i class="fas fa-comment-dots"></i>QQ 号（登录账号）</label>
                <div class="qq-input-row">
                    <div class="qq-avatar">
                        <img id="installQqAvatar" src="<?php echo (isset($_POST['qq']) && preg_match('/^[1-9][0-9]{4,10}$/', (string) $_POST['qq'])) ? '/_qqavatar.php?qq=' . urlencode((string) $_POST['qq']) . '&s=100' : '/_qqavatar.php?qq=10000&s=100'; ?>" alt="QQ 头像">
                    </div>
                    <input type="text" name="qq" id="installQqInput" maxlength="11" inputmode="numeric"
                           value="<?php echo isset($_POST['qq']) ? e($_POST['qq']) : ''; ?>"
                           placeholder="输入 5~11 位 QQ 号" autocomplete="username" autofocus>
                </div>
                <div class="hint">QQ 号将作为唯一登录账号，并自动获取 QQ 头像。</div>
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
                <label><i class="fas fa-user-friends"></i>性别</label>
                <div class="role-toggle">
                    <label class="role-option">
                        <input type="radio" name="role" value="user1" checked>
                        <span class="role-option-inner">
                            <i class="fas fa-mars role-male-icon"></i>
                            <span class="role-text-main">男生</span>
                        </span>
                    </label>
                    <label class="role-option">
                        <input type="radio" name="role" value="user2">
                        <span class="role-option-inner">
                            <i class="fas fa-venus role-female-icon"></i>
                            <span class="role-text-main">女生</span>
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
        <script>
        (function () {
            var input = document.getElementById('installQqInput');
            var avatar = document.getElementById('installQqAvatar');
            if (!input || !avatar) return;
            var fallback = '/_qqavatar.php?qq=10000&s=100';
            input.addEventListener('input', function () {
                var qq = this.value.replace(/\D/g, '').slice(0, 11);
                if (this.value !== qq) this.value = qq;
                avatar.src = /^[1-9][0-9]{4,10}$/.test(qq)
                    ? '/_qqavatar.php?qq=' + encodeURIComponent(qq) + '&s=100'
                    : fallback;
            });
        })();
        </script>
        <?php elseif ($step === 3): ?>
        <?php
        // 回填「在一起的时间」分段输入
        $loveRaw    = isset($_POST['love_date']) ? trim((string) $_POST['love_date']) : '2025-12-13 17:20:52';
        $loveSeg    = ['year' => '', 'month' => '', 'day' => '', 'hour' => '', 'minute' => '', 'second' => ''];
        $loveHidden = '';
        if ($loveRaw !== '') {
            $loveDt = date_create(str_replace(' ', 'T', $loveRaw));
            if ($loveDt instanceof DateTime) {
                $loveSeg = [
                    'year'   => $loveDt->format('Y'),
                    'month'  => $loveDt->format('m'),
                    'day'    => $loveDt->format('d'),
                    'hour'   => $loveDt->format('H'),
                    'minute' => $loveDt->format('i'),
                    'second' => $loveDt->format('s'),
                ];
                $loveHidden = $loveDt->format('Y-m-d H:i:s');
            }
        }
        ?>
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
                          placeholder="简单介绍一下你们的故事～"><?php echo isset($_POST['site_description']) ? e($_POST['site_description']) : '从相遇到相守，把每一个平凡的日子都写成只属于我们的浪漫。'; ?></textarea>
            </div>
            <div class="form-group">
                <label><i class="fas fa-shield-halved"></i>网站备案号（选填）</label>
                <input type="text" name="icp_beian"
                       value="<?php echo isset($_POST['icp_beian']) ? e($_POST['icp_beian']) : ''; ?>"
                       placeholder="例如：粤ICP备2024000000号">
                <div class="hint">选填。填写后显示在网站页脚，支持换行或逗号分隔多个备案号。</div>
            </div>
            <div class="form-group">
                <label><i class="fas fa-calendar-days"></i>在一起的时间</label>
                <div class="datetime-seg" id="installLoveSeg">
                    <input type="text" class="seg-year" data-part="year" inputmode="numeric" maxlength="4" placeholder="YYYY" aria-label="年" value="<?php echo e($loveSeg['year']); ?>" autocomplete="off">
                    <span class="seg-sep">-</span>
                    <input type="text" data-part="month" inputmode="numeric" maxlength="2" placeholder="MM" aria-label="月" value="<?php echo e($loveSeg['month']); ?>" autocomplete="off">
                    <span class="seg-sep">-</span>
                    <input type="text" data-part="day" inputmode="numeric" maxlength="2" placeholder="DD" aria-label="日" value="<?php echo e($loveSeg['day']); ?>" autocomplete="off">
                    <span class="seg-sep seg-gap"></span>
                    <input type="text" data-part="hour" inputmode="numeric" maxlength="2" placeholder="HH" aria-label="时" value="<?php echo e($loveSeg['hour']); ?>" autocomplete="off">
                    <span class="seg-sep">:</span>
                    <input type="text" data-part="minute" inputmode="numeric" maxlength="2" placeholder="mm" aria-label="分" value="<?php echo e($loveSeg['minute']); ?>" autocomplete="off">
                    <span class="seg-sep">:</span>
                    <input type="text" data-part="second" inputmode="numeric" maxlength="2" placeholder="ss" aria-label="秒" value="<?php echo e($loveSeg['second']); ?>" autocomplete="off">
                </div>
                <input type="hidden" name="love_date" id="installLoveDateValue" value="<?php echo e($loveHidden); ?>">
                <div class="hint">用于计算在一起的天数，支持精确到秒（可留空，未设置时默认按当前日期开始计算）。</div>
            </div>
            <div class="btn-row">
                <a href="?step=<?php echo $installHasAdmin ? '1' : '2'; ?>" class="btn btn-ghost"><i class="fas fa-arrow-left"></i>上一步</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check-circle"></i>保存并完成安装</button>
            </div>
        </form>
        <script>
        (function () {
            var wrap = document.getElementById('installLoveSeg');
            var hidden = document.getElementById('installLoveDateValue');
            if (!wrap || !hidden) return;
            var parts = Array.prototype.slice.call(wrap.querySelectorAll('input[data-part]'));
            var max = { year: 4, month: 2, day: 2, hour: 2, minute: 2, second: 2 };

            function digits(v, n) { return (v || '').replace(/\D/g, '').slice(0, n); }
            function pad(v, n) { return v ? String(v).padStart(n, '0') : ''; }
            function sync() {
                var v = {};
                parts.forEach(function (el) { v[el.dataset.part] = digits(el.value, max[el.dataset.part]); });
                var out = '';
                if (v.year.length === 4 && v.month !== '' && v.day !== '') {
                    out = v.year + '-' + pad(v.month, 2) + '-' + pad(v.day, 2);
                    if (v.hour !== '' && v.minute !== '') {
                        out += ' ' + pad(v.hour, 2) + ':' + pad(v.minute, 2);
                        if (v.second !== '') out += ':' + pad(v.second, 2);
                    }
                }
                hidden.value = out;
            }

            parts.forEach(function (el, i) {
                el.addEventListener('input', function () {
                    var n = max[el.dataset.part];
                    var v = digits(this.value, n);
                    if (this.value !== v) this.value = v;
                    if (v.length >= n && parts[i + 1]) parts[i + 1].focus();
                    sync();
                });
                el.addEventListener('keydown', function (e) {
                    if (e.key === 'Backspace' && this.value === '' && i > 0) {
                        e.preventDefault();
                        parts[i - 1].focus();
                    }
                });
                el.addEventListener('blur', function () {
                    if (this.value !== '') this.value = pad(this.value, max[el.dataset.part]);
                    sync();
                });
                el.addEventListener('focus', function () { this.select(); });
            });

            // 支持整段粘贴：2024-05-20 13:14:00 / 2024-05-20T13:14
            wrap.addEventListener('paste', function (e) {
                var text = (e.clipboardData || window.clipboardData).getData('text') || '';
                var m = text.match(/(\d{4})\D*(\d{1,2})\D*(\d{1,2})(?:\D+(\d{1,2})\D*(\d{1,2})(?:\D*(\d{1,2}))?)?/);
                if (!m) return;
                e.preventDefault();
                parts[0].value = m[1];
                parts[1].value = pad(m[2], 2);
                parts[2].value = pad(m[3], 2);
                parts[3].value = m[4] ? pad(m[4], 2) : '';
                parts[4].value = m[5] ? pad(m[5], 2) : '';
                parts[5].value = m[6] ? pad(m[6], 2) : '';
                sync();
            });

            sync();
        })();
        </script>
        <?php elseif ($step === 4): ?>
        <div class="done-wrap">
            <div class="done-icon"><i class="fas fa-check"></i></div>
            <h3>安装完成！</h3>
            <p>系统已成功安装，现在可以开始使用了。</p>
            <?php if ($cleanupToken !== ''): ?>
            <div class="done-note">
                <form method="POST" action="?step=4">
                    <input type="hidden" name="step" value="4">
                    <input type="hidden" name="cleanup_token" value="<?php echo e($cleanupToken); ?>">
                    <label class="cleanup-option">
                        <input type="checkbox" name="delete_lock" value="1" checked>
                        <span>删除安装锁文件 <code>enable_install.lock</code>（推荐）</span>
                    </label>
                    <label class="cleanup-option">
                        <input type="checkbox" name="delete_installer" value="1">
                        <span>同时删除安装脚本 <code>install.php</code></span>
                    </label>
                    <div class="btn-row">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-check-circle"></i>完成</button>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="done-note">安装锁文件已自动处理，可以直接进入站点。</div>
            <div class="btn-row">
                <a href="/login.php" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i>前往登录</a>
            </div>
            <?php endif; ?>
        </div>
        <?php elseif ($step === 5): ?>
        <div class="done-wrap">
            <div class="done-icon"><i class="fas fa-check"></i></div>
            <h3>清理完成</h3>
            <p>安装已完成，站点可以开始使用了。</p>
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
