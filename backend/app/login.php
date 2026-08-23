<?php
// 登录 / 注册页面（UTF-8）
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/helpers.php';

$auth = new Auth();
$db   = Database::getInstance();

// Turnstile 当前配置
$turnstileEnabled = (string) get_setting('turnstile_enabled', '0') === '1';
$turnstileSiteKey = '';
if ($turnstileEnabled) {
    $turnstileSiteKey = (string) get_setting('turnstile_site_key', '');
    if ($turnstileSiteKey === '') {
        $turnstileEnabled = false;
    }
}

// 已登录则直接回到首页
if ($auth->isLoggedIn()) {
    redirect('/');
}

$error   = '';
$success = '';

// 统计当前活跃用户数量，用于控制是否开放注册入口
$userCountRow   = $db->fetch("SELECT COUNT(*) AS c FROM users WHERE status = 'active'");
$activeUserCount = $userCountRow ? (int) $userCountRow['c'] : 0;
$inviteToken = trim((string)($_GET['invite'] ?? $_POST['invite_token'] ?? ''));
$inviteRow = null;
if ($inviteToken !== '') {
    try {
        $inviteRow = $db->fetch(
            "SELECT id, inviter_id, status, expires_at FROM couple_invites
             WHERE invite_token_hash = :token AND status = 'pending' AND expires_at >= :now LIMIT 1",
            ['token' => hash('sha256', $inviteToken), 'now' => date('Y-m-d H:i:s')]
        );
    } catch (Throwable $e) { $inviteRow = null; }
}
$registerEnabled = ($activeUserCount === 0) || ($activeUserCount === 1 && $inviteRow);

// 重定向后的成功提示
if (isset($_GET['success']) && $_GET['success'] === 'register') {
    $success = '注册成功，请使用新账号登录';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 统一使用 CSRF 校验
    $token = $_POST['_token'] ?? '';
    if (!csrf_verify($token)) {
        $error = '表单已过期，请刷新页面后重试';
    } elseif ($turnstileEnabled) {
        $tsToken = $_POST['cf-turnstile-response'] ?? '';
        if (!verify_turnstile((string)$tsToken)) {
            $error = '验证未通过，请完成安全验证后再试';
        }
    }

    if (!$error) {
        $action = $_POST['action'] ?? 'login';

        if ($action === 'login') {
            $username = trim($_POST['username'] ?? '');
            $password = (string) ($_POST['password'] ?? '');

            if ($username === '' || $password === '') {
                $error = '请输入用户名和密码';
            } else {
                if ($auth->login($username, $password)) {
                    // 登录成功后直接重定向到首页，防止刷新重复提交
                    redirect('/');
                } else {
                    // 统一错误提示，避免暴露具体原因
                    $error = '用户名或密码错误，或尝试次数过多，请稍后再试';
                }
            }
        } elseif ($action === 'register') {
            if (!$registerEnabled) {
                $error = $activeUserCount === 1 ? '请使用情侣邀请链接注册第二个账号' : '当前已注册满两位用户，已关闭注册';
            } else {
                $username = trim($_POST['username'] ?? '');
                $password = (string) ($_POST['password'] ?? '');
                $nickname = trim($_POST['nickname'] ?? '');
                $role     = $activeUserCount === 0 ? 'user1' : 'user2';

                if ($activeUserCount === 1 && !$inviteRow) {
                    $error = '邀请链接无效或已过期';
                }

                if (!$error) {
                    $result = $auth->register($username, $password, $nickname, $role);
                    if (!empty($result['success'])) {
                        if ($activeUserCount === 1 && $inviteRow) {
                            try {
                                $db->update('couple_invites', [
                                    'status'      => 'accepted',
                                    'invitee_id'  => (int)$result['user_id'],
                                    'accepted_at' => date('Y-m-d H:i:s'),
                                ], 'id = :id AND status = \'pending\'', ['id' => (int)$inviteRow['id']]);
                            } catch (Throwable $e) { /* 注册成功不因邀请状态写入失败而回滚 */ }
                        }
                        header('Location: /login.php?success=register');
                        exit;
                    } else {
                        $error = $result['message'] ?? '注册失败，请稍后重试';
                    }
                }
            }
        }
    }
}
$themeConfig = withu_theme_config();
$themeInlineStyle = '';
foreach (($themeConfig['colors'] ?? []) as $themeName => $themeValue) $themeInlineStyle .= '--withu-custom-' . $themeName . ':' . $themeValue . ';';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>登录 - <?php echo e(SITE_NAME); ?></title>
<link href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"
      onerror="this.onerror=null;this.href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';">
<?php if ($turnstileEnabled && $turnstileSiteKey): ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>
<style>
:root{--brand:#e75480;--brand-dark:#c23b64;}
*{box-sizing:border-box;}
body{background:linear-gradient(135deg,#ffeef5 0%,#f4f6fb 60%,#eef2ff 100%);
  font-family:"Nunito","Microsoft YaHei","PingFang SC",sans-serif;margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;}
.login-card{width:420px;max-width:92vw;background:#fff;border-radius:18px;box-shadow:0 18px 50px rgba(180,60,110,.15);overflow:hidden;position:relative;}
.login-head{background:linear-gradient(135deg,#e75480,#c23b64);color:#fff;padding:36px 30px 28px;text-align:center;}
.login-head h3{margin:0 0 4px;font-weight:800;font-size:22px;letter-spacing:.5px;}
.login-head p{margin:0;opacity:.85;font-size:13px;line-height:1.6;}
.login-body{padding:28px 30px 30px;}
.login-body .form-group{margin-bottom:16px;}
.login-body label{font-size:13px;color:#555;font-weight:600;margin-bottom:6px;display:block;}
.login-body label i{color:var(--brand);margin-right:4px;}
.login-body input[type="text"],.login-body input[type="password"],.login-body input[type="email"]{width:100%;padding:11px 14px;border:1.5px solid #e3e6f0;border-radius:10px;font-size:14px;outline:none;transition:.2s;font-family:inherit;}
.login-body input:focus{border-color:var(--brand);box-shadow:0 0 0 3px rgba(231,84,128,.12);}
.login-body .btn{width:100%;padding:12px;border:none;border-radius:10px;background:linear-gradient(135deg,#e75480,#c23b64);color:#fff;font-size:15px;font-weight:700;cursor:pointer;transition:.2s;font-family:inherit;}
.login-body .btn:hover{filter:brightness(1.08);transform:translateY(-1px);box-shadow:0 4px 14px rgba(231,84,128,.3);}
.login-body .btn i{margin-right:6px;}
.login-tip{margin-top:14px;font-size:12px;color:#999;text-align:center;line-height:1.7;}
.login-err{background:#fff1f1;color:#c0392b;border:1px solid #ffd6d6;padding:9px 12px;border-radius:8px;font-size:13px;margin-bottom:14px;display:block;}
.login-success{background:#f0faf0;color:#27ae60;border:1px solid #d5f5d5;padding:9px 12px;border-radius:8px;font-size:13px;margin-bottom:14px;display:block;}
.auth-divider{display:flex;align-items:center;margin:20px 0;color:#bbb;font-size:13px;gap:12px;}
.auth-divider::before,.auth-divider::after{content:'';flex:1;height:1px;background:#e8e8e8;}
.auth-tabs{display:flex;gap:8px;margin-bottom:16px;}
.auth-tabs .tab-btn{flex:1;padding:8px;border:1.5px solid #e3e6f0;border-radius:10px;background:#fff;cursor:pointer;font-size:13px;color:#888;transition:.2s;font-family:inherit;}
.auth-tabs .tab-btn.active{border-color:var(--brand);color:var(--brand);background:#fff5f8;font-weight:600;}
.auth-tabs .tab-btn:hover{border-color:var(--brand);}
.role-toggle{display:flex;gap:10px;}
.role-toggle .role-option{flex:1;cursor:pointer;}
.role-toggle .role-option input{display:none;}
.role-toggle .role-option-inner{display:flex;flex-direction:column;align-items:center;padding:10px;border:1.5px solid #e3e6f0;border-radius:10px;transition:.2s;text-align:center;}
.role-toggle .role-option input:checked+.role-option-inner{border-color:var(--brand);background:#fff5f8;color:var(--brand);}
.role-toggle .role-option-inner i{font-size:22px;margin-bottom:4px;}
.role-toggle .role-option-inner .role-text-main{font-size:14px;font-weight:600;}
.role-toggle .role-option-inner .role-text-sub{font-size:11px;color:#999;}
.role-male-icon{color:#5dade2;}
.role-female-icon{color:var(--brand);}
.form-hint{font-size:13px;color:#999;text-align:center;margin:12px 0;}
.auth-footer{text-align:center;margin-top:16px;padding-top:16px;border-top:1px solid #f0f0f0;}
.auth-footer a{color:#999;text-decoration:none;font-size:13px;transition:.2s;}
.auth-footer a:hover{color:var(--brand);}
.turnstile-group{display:flex;justify-content:center;}
.form-group-hidden{display:none;}
</style>
</head>
<body>
<div class="login-card">
  <div class="login-head">
    <h3>💗 withU</h3>
    <p>登录你的账号，记录你们的点点滴滴</p>
  </div>
  <div class="login-body">

    <?php if ($error): ?>
    <div class="login-err"><i class="fas fa-exclamation-circle"></i> <?php echo e($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="login-success"><i class="fas fa-check-circle"></i> <?php echo e($success); ?></div>
    <?php endif; ?>

    <!-- 登录表单 -->
    <form method="POST" action="/login.php" id="loginForm" novalidate>
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="login">

      <div class="form-group">
        <label><i class="fas fa-user"></i> 用户名</label>
        <input type="text" name="username" autofocus>
      </div>

      <div class="form-group">
        <label><i class="fas fa-lock"></i> 密码</label>
        <input type="password" name="password">
      </div>

      <?php if ($turnstileEnabled && $turnstileSiteKey): ?>
      <div class="form-group turnstile-group">
        <div class="cf-turnstile" data-sitekey="<?php echo e($turnstileSiteKey); ?>"></div>
      </div>
      <?php endif; ?>

      <button type="submit" class="btn"><i class="fas fa-sign-in-alt"></i> 登 录</button>
    </form>

    <?php if ($registerEnabled): ?>
    <div class="auth-divider"><span>或</span></div>

    <div class="auth-tabs">
      <button class="tab-btn active" data-tab="register" onclick="toggleForm('register')">注册新账号</button>
    </div>

    <form method="POST" action="/login.php" id="registerForm" style="display:none;" novalidate>
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="register">
      <input type="hidden" name="invite_token" value="<?php echo e($inviteToken); ?>">

      <?php if ($activeUserCount === 0): ?>
      <div class="form-group">
        <label><i class="fas fa-user"></i> 用户名</label>
        <input type="text" name="username">
      </div>
      <div class="form-group">
        <label><i class="fas fa-user-tag"></i> 昵称</label>
        <input type="text" name="nickname">
      </div>
      <div class="form-group">
        <label><i class="fas fa-lock"></i> 密码</label>
        <input type="password" name="password">
      </div>
      <?php if ($turnstileEnabled && $turnstileSiteKey): ?>
      <div class="form-group turnstile-group">
        <div class="cf-turnstile" data-sitekey="<?php echo e($turnstileSiteKey); ?>"></div>
      </div>
      <?php endif; ?>
      <div class="form-group">
        <label><i class="fas fa-user-friends"></i> 角色</label>
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
      </div>
      <?php else: ?>
      <p class="form-hint">这是邀请注册，账号会自动加入情侣空间。</p>
      <?php endif; ?>

      <button type="submit" class="btn"><i class="fas fa-user-plus"></i> 注 册</button>
    </form>
    <?php endif; ?>

    <div class="auth-footer">
      <a href="/"><i class="fas fa-arrow-left"></i> 返回首页</a>
    </div>
  </div>
</div>

<script>
function toggleForm(tab) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelector('[data-tab="'+tab+'"]').classList.add('active');
  document.getElementById('loginForm').style.display = tab === 'login' ? 'block' : 'none';
  document.getElementById('registerForm').style.display = tab === 'register' ? 'block' : 'none';
}
</script>
</body>
</html>