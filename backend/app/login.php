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

// 登录后回跳目标：由未登录访问受保护页面时携带（如 /admin/），非法地址一律忽略
$redirectTo = withu_safe_redirect_path($_GET['redirect'] ?? $_POST['redirect'] ?? '');
$loginLanding = $redirectTo !== '' ? $redirectTo : '/';

// 已登录则直接回到目标页（默认首页）
if ($auth->isLoggedIn()) {
    redirect($loginLanding);
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
            "SELECT ci.id, ci.inviter_id, ci.status, ci.expires_at,
                    u.nickname AS inviter_name, u.avatar AS inviter_avatar
             FROM couple_invites ci
             LEFT JOIN users u ON u.id = ci.inviter_id
             WHERE ci.invite_token_hash = :token AND ci.status = 'pending' AND ci.expires_at >= :now LIMIT 1",
            ['token' => hash('sha256', $inviteToken), 'now' => date('Y-m-d H:i:s')]
        );
    } catch (Throwable $e) { $inviteRow = null; }
}
$needsSetup      = ($activeUserCount === 0);               // 首次部署：还没有任何账号
$isInvited       = ($activeUserCount === 1 && $inviteRow); // 已有 1 个账号且带有效邀请链接
$registerEnabled = $needsSetup || $isInvited;

// 邀请文案数据：邀请人昵称、站点标题与站点描述（浪漫语句）
$inviterName     = trim((string)($inviteRow['inviter_name'] ?? ''));
if ($inviterName === '') $inviterName = '对方';
$inviteSiteTitle = '';
$invitePoem      = '从相遇到相守，把每一个平凡的日子都写成只属于我们的浪漫。';
try {
    $inviteSiteTitle = trim((string)($db->fetch("SELECT value FROM settings WHERE `key`='site_title'")['value'] ?? ''));
    $invitePoem      = trim((string)($db->fetch("SELECT value FROM settings WHERE `key`='site_description'")['value'] ?? ''));
} catch (Throwable $e) { /* 设置读取失败时使用默认文案 */ }
if ($inviteSiteTitle === '') $inviteSiteTitle = SITE_NAME;
if ($invitePoem === '') $invitePoem = '从相遇到相守，把每一个平凡的日子都写成只属于我们的浪漫。';

// 重定向后的成功提示
if (isset($_GET['success']) && $_GET['success'] === 'register') {
    $success = '注册成功，请使用新账号登录';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 统一使用 CSRF 校验
    $token = $_POST['_token'] ?? '';
    if (!csrf_verify($token)) {
        $error = '表单已过期，请刷新页面后重试';
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
                    // 登录成功后重定向回来源页（默认首页），防止刷新重复提交
                    redirect($loginLanding);
                } else {
                    // 统一错误提示，避免暴露具体原因
                    $error = '用户名或密码错误，或尝试次数过多，请稍后再试';
                }
            }
        } elseif ($action === 'register') {
            if (!$registerEnabled) {
                $error = $activeUserCount === 1 ? '请使用情侣邀请链接注册第二个账号' : '当前已注册满两位用户，已关闭注册';
            } else {
                $qq       = trim((string) ($_POST['qq'] ?? '')); // QQ 号即登录账号
                $password = (string) ($_POST['password'] ?? '');
                $confirm  = (string) ($_POST['password_confirm'] ?? '');
                $nickname = trim((string) ($_POST['nickname'] ?? ''));

                if ($activeUserCount === 1 && !$inviteRow) {
                    $error = '邀请链接无效或已过期';
                } elseif ($qq === '' || $password === '' || $confirm === '' || $nickname === '') {
                    $error = '请填写所有必填项';
                } elseif (!preg_match('/^[1-9][0-9]{4,10}$/', $qq)) {
                    $error = 'QQ 号格式不正确（应为 5~11 位数字）';
                } elseif ($password !== $confirm) {
                    $error = '两次输入的密码不一致';
                }

                $gender = '';
                $role   = '';
                if (!$error) {
                    migrate_schema_if_needed();
                    if ($activeUserCount === 0) {
                        // 第一个账号：注册时必须选择性别，性别决定角色槽位（男=user1，女=user2）
                        $gender = trim((string) ($_POST['gender'] ?? ''));
                        if (!in_array($gender, ['male', 'female'], true)) {
                            $error = '请选择性别';
                        } else {
                            $role = $gender === 'male' ? 'user1' : 'user2';
                        }
                    } else {
                        // 受邀注册的第二个账号：性别自动设为对方的相反性别，不可修改
                        $inviter = $db->fetch(
                            "SELECT role, gender FROM users WHERE id = ? AND status = 'active'",
                            [(int)($inviteRow['inviter_id'] ?? 0)]
                        );
                        $inviterRole   = ($inviter['role'] ?? '') === 'user2' ? 'user2' : 'user1';
                        $inviterGender = in_array(($inviter['gender'] ?? ''), ['male', 'female'], true)
                            ? $inviter['gender']
                            : ($inviterRole === 'user2' ? 'female' : 'male');
                        $gender = $inviterGender === 'male' ? 'female' : 'male';
                        $role   = $inviterRole === 'user1' ? 'user2' : 'user1';
                    }
                }

                if (!$error) {
                    // QQ 号直接作为 username，与安装向导保持一致
                    $username = $qq;
                    $result = $auth->register($username, $password, $nickname, $role, $gender, $qq);
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
                        header('Location: /login.php?success=register' . ($redirectTo !== '' ? '&redirect=' . rawurlencode($redirectTo) : ''));
                        exit;
                    } else {
                        $error = $result['message'] ?? '注册失败，请稍后重试';
                    }
                }
            }
        }
    }
}
// 提交失败后停留在原表单并回填已填内容（密码不回填）
$lastAction   = (string) ($_POST['action'] ?? '');
$oldLoginName = $lastAction === 'login' ? trim((string) ($_POST['username'] ?? '')) : '';
$oldQq        = $lastAction === 'register' ? trim((string) ($_POST['qq'] ?? '')) : '';
$oldNickname  = $lastAction === 'register' ? trim((string) ($_POST['nickname'] ?? '')) : '';
// 默认落在哪个表单：首装（还没账号）或凭邀请注册时直接显示注册，其余只显示登录。
// 提交失败时停留在出错的那个表单，不把用户甩到另一个 tab。
if ($lastAction === 'register' && $error !== '') {
    $activeTab = 'register';
} elseif ($lastAction === 'login' && $error !== '') {
    $activeTab = 'login';
} else {
    $activeTab = $registerEnabled ? 'register' : 'login';
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
<title><?php echo $activeTab === 'register' ? '注册' : '登录'; ?> - <?php echo e(SITE_NAME); ?></title>
<link rel="stylesheet" href="/admin-assets/vendor/fontawesome/css/all.min.css">

<style>
:root{--brand:#d94f6d;--brand-dark:#be3a58;--ink:#2b2327;--ink-2:#6d6167;--ink-3:#9b8e94;--line:#eadfe2;--surface:#fffdfd;--soft:#fbf7f8;}
*{box-sizing:border-box;}
body{
  margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:28px 18px;
  color:var(--ink);font-family:"Inter","HarmonyOS Sans SC","PingFang SC","Microsoft YaHei",system-ui,sans-serif;
  background-color:#f6f2f2;
  background-image:
    radial-gradient(820px 520px at 10% -8%,rgba(217,79,109,.10),transparent 60%),
    radial-gradient(760px 520px at 100% 0%,rgba(185,133,95,.10),transparent 62%),
    radial-gradient(900px 680px at 50% 118%,rgba(63,143,109,.07),transparent 66%);
  background-attachment:fixed;
  -webkit-font-smoothing:antialiased;
}
body::before{
  content:"";position:fixed;inset:0;pointer-events:none;z-index:-1;
  background-image:radial-gradient(rgba(43,35,39,.05) 1px,transparent 1px);
  background-size:22px 22px;
  -webkit-mask-image:radial-gradient(circle at 50% 40%,#000 0%,transparent 74%);
  mask-image:radial-gradient(circle at 50% 40%,#000 0%,transparent 74%);
}
.login-card{
  width:880px;max-width:100%;display:grid;grid-template-columns:0.82fr 1fr;
  background:var(--surface);border:1px solid var(--line);border-radius:26px;overflow:hidden;
  box-shadow:0 40px 90px -50px rgba(43,35,39,.55),0 2px 6px rgba(43,35,39,.04);
  position:relative;
}
.login-card::before{
  content:"";position:absolute;top:0;left:0;right:0;height:3px;z-index:2;
  background:linear-gradient(90deg,#e9708b,#d94f6d 45%,#b9855f);
}
.login-head{
  position:relative;display:flex;flex-direction:column;align-items:flex-start;justify-content:center;
  padding:54px 46px;color:#fff;overflow:hidden;
  background:linear-gradient(155deg,#412a32 0%,#6b3746 46%,#a84a63 100%);
}
.login-head::before{
  content:"♥";display:flex;align-items:center;justify-content:center;
  width:46px;height:46px;border-radius:16px;margin-bottom:26px;
  background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.22);
  color:#ffd9e2;font-size:18px;backdrop-filter:blur(6px);
}
.login-head::after{
  content:"把平凡的日子，收进只属于我们的地方。";
  margin-top:26px;font-size:12.5px;line-height:1.9;color:rgba(255,255,255,.62);
  letter-spacing:.06em;max-width:15em;
}
.login-head h3{
  margin:0 0 10px;font-family:"Noto Serif SC","Noto Serif CJK SC","Songti SC",serif;
  font-size:30px;font-weight:650;letter-spacing:.04em;line-height:1.2;
}
.login-head p{margin:0;max-width:17em;color:rgba(255,255,255,.78);font-size:13px;line-height:1.8;}
.login-body{padding:52px 48px 44px;background:var(--surface);}
.login-body .form-group{margin-bottom:18px;}
.login-body label{font-size:12.5px;color:var(--ink-2);font-weight:600;margin-bottom:7px;display:block;letter-spacing:.01em;}
.login-body label i{color:var(--brand);margin-right:5px;}
.login-body input[type="text"],.login-body input[type="password"],.login-body input[type="email"]{
  width:100%;height:46px;padding:0 15px;border:1px solid #ddccd1;border-radius:12px;
  background:var(--soft);font-size:14px;color:var(--ink);outline:none;transition:.2s;font-family:inherit;
}
.login-body input::placeholder{color:var(--ink-3);}
.login-body input:focus{background:#fff;border-color:rgba(217,79,109,.5);box-shadow:0 0 0 4px rgba(217,79,109,.09);}
.login-body .btn{
  width:100%;height:48px;border:none;border-radius:12px;cursor:pointer;font-family:inherit;
  background:linear-gradient(135deg,#e0607c,#d94f6d 55%,#be3a58);color:#fff;
  font-size:14.5px;font-weight:650;letter-spacing:.04em;transition:.2s;
  box-shadow:0 12px 26px -14px rgba(217,79,109,.8);
}
.login-body .btn:hover{filter:brightness(1.04);transform:translateY(-1px);box-shadow:0 16px 30px -14px rgba(217,79,109,.85);}
.login-body .btn:active{transform:translateY(0) scale(.99);}
.login-body .btn i{margin-right:7px;}
.login-tip{margin-top:16px;font-size:12px;color:var(--ink-3);text-align:center;line-height:1.75;}
.login-err{background:#fdeeee;color:#a33a3a;border:1px solid rgba(196,69,69,.2);padding:10px 13px;border-radius:11px;font-size:12.5px;margin-bottom:16px;display:block;line-height:1.6;}
.login-success{background:#eaf5f0;color:#276b52;border:1px solid rgba(63,143,109,.2);padding:10px 13px;border-radius:11px;font-size:12.5px;margin-bottom:16px;display:block;line-height:1.6;}
.auth-tabs{display:flex;gap:4px;margin-bottom:22px;background:#f4eeee;padding:4px;border-radius:999px;border:1px solid var(--line);}
.auth-tabs .tab-btn{flex:1;min-height:38px;border:none;border-radius:999px;background:transparent;cursor:pointer;font-size:13px;color:var(--ink-2);transition:.2s;font-family:inherit;font-weight:600;}
.auth-tabs .tab-btn:hover:not(.active){color:var(--brand-dark);}
.auth-tabs .tab-btn.active{background:#fff;color:var(--brand-dark);box-shadow:0 4px 12px -8px rgba(43,35,39,.4);border:1px solid var(--line);}
.label-row{display:flex;align-items:center;justify-content:space-between;}
.qq-avatar-preview{width:24px;height:24px;border-radius:50%;object-fit:cover;border:2px solid #fff;box-shadow:0 0 0 1px var(--line);display:none;}
.field-error{color:#a33a3a;font-size:11.5px;margin-top:5px;display:none;}
.input-error{border-color:#c44545 !important;box-shadow:0 0 0 4px rgba(196,69,69,.08) !important;}
.role-toggle{display:flex;gap:10px;}
.role-toggle .role-option{flex:1;cursor:pointer;}
.role-toggle .role-option input{display:none;}
.role-toggle .role-option-inner{
  display:flex;flex-direction:column;align-items:center;padding:12px 10px;border:1px solid #ddccd1;
  border-radius:12px;transition:.2s;text-align:center;background:var(--soft);
}
.role-toggle .role-option input:checked+.role-option-inner{border-color:rgba(217,79,109,.45);background:#fdeef2;color:var(--brand-dark);box-shadow:0 0 0 3px rgba(217,79,109,.06);}
.role-toggle .role-option-inner i{font-size:21px;margin-bottom:5px;}
.role-toggle .role-option-inner .role-text-main{font-size:13px;font-weight:600;}
.role-toggle .role-option-inner .role-text-sub{font-size:10.5px;color:var(--ink-3);margin-top:1px;}
.role-male-icon{color:#4f7fa8;}
.role-female-icon{color:var(--brand);}
.form-hint{font-size:12px;color:var(--ink-3);text-align:center;margin:13px 0;line-height:1.7;}
.invite-hero{
  background:linear-gradient(135deg,#fdf5f7,#f8f2ea);border:1px solid #f0dde2;
  border-radius:14px;padding:18px 18px;margin-bottom:20px;text-align:center;
}
.invite-hero .invite-poem{margin:0 0 10px;font-size:13px;color:#8a5a68;line-height:1.85;}
.invite-hero .invite-line{margin:0 0 6px;font-size:14px;color:var(--ink);font-weight:600;line-height:1.75;}
.invite-hero .invite-line strong{color:var(--brand);}
.invite-hero .invite-expire{margin:0;font-size:11.5px;color:var(--ink-3);}
.auth-footer{text-align:center;margin-top:20px;padding-top:18px;border-top:1px solid #f2eaec;}
.auth-footer a{color:var(--ink-3);text-decoration:none;font-size:12.5px;transition:.2s;}
.auth-footer a:hover{color:var(--brand-dark);}
.form-group-hidden{display:none;}
@media (max-width:820px){
  body{padding:18px 14px;align-items:flex-start;}
  .login-card{grid-template-columns:1fr;width:440px;border-radius:22px;margin-top:4vh;}
  .login-head{padding:30px 26px 26px;align-items:center;text-align:center;}
  .login-head::before{margin-bottom:16px;width:40px;height:40px;border-radius:14px;}
  .login-head::after{display:none;}
  .login-head h3{font-size:25px;}
  .login-head p{max-width:22em;font-size:12.5px;}
  .login-body{padding:28px 24px 30px;}
}
@media (prefers-reduced-motion:reduce){
  *{transition:none !important;animation:none !important;}
}
</style>
</head>
<body>
<div class="login-card">
  <div class="login-head">
    <h3>withU</h3>
    <p id="authSubtitle"><?php echo $activeTab === 'register' ? ($isInvited ? '接受邀请，开启你们的浪漫时光' : '创建你们的账号，开启情侣空间') : '登录你的账号，记录你们的点点滴滴'; ?></p>
  </div>
  <div class="login-body">

    <?php if ($error): ?>
    <div class="login-err"><i class="fas fa-exclamation-circle"></i> <?php echo e($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="login-success"><i class="fas fa-check-circle"></i> <?php echo e($success); ?></div>
    <?php endif; ?>

    <?php if ($isInvited): ?>
    <div class="invite-hero">
      <p class="invite-poem"><?php echo e($invitePoem); ?></p>
      <p class="invite-line"><strong><?php echo e($inviterName); ?></strong> 邀请你加入 <strong><?php echo e($inviteSiteTitle); ?></strong></p>
      <p class="invite-expire">邀请有效期至 <?php echo e(date('m月d日 H:i', strtotime((string)($inviteRow['expires_at'] ?? '')))); ?></p>
    </div>
    <?php endif; ?>

    <?php if ($registerEnabled && !$needsSetup): ?>
    <div class="auth-tabs" role="tablist">
      <button type="button" class="tab-btn<?php echo $activeTab === 'login' ? ' active' : ''; ?>" data-tab="login" onclick="toggleForm('login')">登 录</button>
      <button type="button" class="tab-btn<?php echo $activeTab === 'register' ? ' active' : ''; ?>" data-tab="register" onclick="toggleForm('register')"><?php echo $isInvited ? '接受邀请' : '注 册'; ?></button>
    </div>
    <?php endif; ?>

    <!-- 登录表单 -->
    <form method="POST" action="/login.php" id="loginForm" style="display:<?php echo $activeTab === 'login' ? 'block' : 'none'; ?>;">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="login">
      <input type="hidden" name="redirect" value="<?php echo e($redirectTo); ?>">

      <div class="form-group">
        <label><i class="fas fa-user"></i> QQ号</label>
        <input type="text" name="username" maxlength="32" autocomplete="username" value="<?php echo e($oldLoginName); ?>" autofocus>
      </div>

      <div class="form-group">
        <label><i class="fas fa-lock"></i> 密码</label>
        <input type="password" name="password" autocomplete="current-password">
      </div>

      <button type="submit" class="btn"><i class="fas fa-sign-in-alt"></i> 登 录</button>
    </form>

    <?php if ($registerEnabled): ?>
    <form method="POST" action="/login.php" id="registerForm" style="display:<?php echo $activeTab === 'register' ? 'block' : 'none'; ?>;">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="register">
      <input type="hidden" name="invite_token" value="<?php echo e($inviteToken); ?>">
      <input type="hidden" name="redirect" value="<?php echo e($redirectTo); ?>">

      <div class="form-group">
        <label class="label-row">
          <span><i class="fab fa-qq"></i> QQ号（登录账号）</span>
          <img class="qq-avatar-preview" id="qqAvatarPreview" alt="QQ 头像预览">
        </label>
        <input type="text" name="qq" inputmode="numeric" maxlength="11" placeholder="输入 5~11 位 QQ 号，自动获取头像和昵称" value="<?php echo e($oldQq); ?>" required>
      </div>
      <div class="form-group">
        <label><i class="fas fa-user-tag"></i> 昵称</label>
        <input type="text" name="nickname" maxlength="32" placeholder="填写 QQ 号后可自动获取" value="<?php echo e($oldNickname); ?>" required>
      </div>
      <div class="form-group">
        <label><i class="fas fa-lock"></i> 密码</label>
        <input type="password" name="password" minlength="8" placeholder="至少 8 位" autocomplete="new-password" required>
      </div>
      <div class="form-group">
        <label><i class="fas fa-check-double"></i> 确认密码</label>
        <input type="password" name="password_confirm" minlength="8" placeholder="再输入一次密码" autocomplete="new-password" id="passwordConfirm" required>
        <div class="field-error" id="confirmError">两次输入的密码不一致</div>
      </div>

      <?php if ($activeUserCount === 0): ?>
      <div class="form-group">
        <label><i class="fas fa-user-friends"></i> 性别（注册后不可修改）</label>
        <div class="role-toggle">
          <label class="role-option">
            <input type="radio" name="gender" value="male" required>
            <span class="role-option-inner">
              <i class="fas fa-mars role-male-icon"></i>
              <span class="role-text-main">男生</span>
              <span class="role-text-sub">（他）</span>
            </span>
          </label>
          <label class="role-option">
            <input type="radio" name="gender" value="female">
            <span class="role-option-inner">
              <i class="fas fa-venus role-female-icon"></i>
              <span class="role-text-main">女生</span>
              <span class="role-text-sub">（她）</span>
            </span>
          </label>
        </div>
      </div>
      <?php else: ?>
      <p class="form-hint">这是邀请注册，账号会自动加入情侣空间。你的性别会自动设为对方的相反性别，无需选择。</p>
      <?php endif; ?>

      <button type="submit" class="btn"><i class="fas fa-user-plus"></i> <?php echo $isInvited ? '接受邀请并注册' : '注 册'; ?></button>
    </form>
    <?php endif; ?>

    <div class="auth-footer">
      <a href="/"><i class="fas fa-arrow-left"></i> 返回首页</a>
    </div>
  </div>
</div>

<script>
var AUTH_SUBTITLES = {
  login: '登录你的账号，记录你们的点点滴滴',
  register: '<?php echo $isInvited ? '接受邀请，开启你们的浪漫时光' : '创建你们的账号，开启情侣空间'; ?>'
};

function toggleForm(tab) {
  var registerForm = document.getElementById('registerForm');
  if (!registerForm) return; // 注册关闭时只有登录表单
  document.querySelectorAll('.tab-btn').forEach(function (b) {
    b.classList.toggle('active', b.dataset.tab === tab);
  });
  document.getElementById('loginForm').style.display = tab === 'login' ? 'block' : 'none';
  registerForm.style.display = tab === 'register' ? 'block' : 'none';
  var sub = document.getElementById('authSubtitle');
  if (sub && AUTH_SUBTITLES[tab]) sub.textContent = AUTH_SUBTITLES[tab];
}

(function () {
  var registerForm = document.getElementById('registerForm');
  if (!registerForm) return;
  var qqInput      = registerForm.querySelector('input[name="qq"]');
  var nicknameInput = registerForm.querySelector('input[name="nickname"]');
  var preview      = document.getElementById('qqAvatarPreview');
  var confirmInput = document.getElementById('passwordConfirm');
  var confirmError = document.getElementById('confirmError');
  var fetchedQq    = '';
  var fetchTimer   = null;
  var nicknamePlaceholder = '填写 QQ 号后可自动获取';

  // QQ 号输入时立即预览头像（本地代理，无外部依赖）
  function showAvatar(qq) {
    if (!preview) return;
    if (!/^[1-9][0-9]{4,10}$/.test(qq)) {
      preview.style.display = 'none';
      return;
    }
    preview.src = '/_qqavatar.php?qq=' + encodeURIComponent(qq) + '&s=100';
    preview.style.display = 'inline-block';
  }

  // 拉取昵称：能取到就自动填；取不到则提示手动填写（不阻塞注册）
  function fetchProfile(qq) {
    if (!/^[1-9][0-9]{4,10}$/.test(qq) || qq === fetchedQq) return;
    fetchedQq = qq;
    fetch('/api/qq_profile.php?qq=' + encodeURIComponent(qq), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.success) return;
        if (d.avatar_url && preview) {
          preview.src = d.avatar_url;
          preview.style.display = 'inline-block';
        }
        if (nicknameInput && !nicknameInput.value.trim()) {
          if (d.nickname) {
            nicknameInput.value = d.nickname;
            nicknameInput.placeholder = nicknamePlaceholder;
          } else {
            nicknameInput.placeholder = '未能自动获取昵称，请手动填写';
          }
        }
      })
      .catch(function () { /* 获取失败不影响注册 */ });
  }

  // 输入即预览头像 + 防抖 500ms 拉取昵称；失焦时再兜底拉一次
  qqInput.addEventListener('input', function () {
    var qq = this.value.replace(/\D/g, '').slice(0, 11);
    if (this.value !== qq) this.value = qq;
    showAvatar(qq);
    if (nicknameInput && !nicknameInput.value.trim()) {
      nicknameInput.placeholder = nicknamePlaceholder;
    }
    clearTimeout(fetchTimer);
    fetchTimer = setTimeout(function () { fetchProfile(qqInput.value.trim()); }, 500);
  });
  qqInput.addEventListener('change', function () {
    showAvatar(this.value.trim());
    fetchProfile(this.value.trim());
  });

  function validateConfirm() {
    var mismatch = confirmInput.value !== '' && confirmInput.value !== registerForm.querySelector('input[name="password"]').value;
    confirmInput.classList.toggle('input-error', mismatch);
    confirmError.style.display = mismatch ? 'block' : 'none';
    return !mismatch;
  }
  confirmInput.addEventListener('input', validateConfirm);
  registerForm.querySelector('input[name="password"]').addEventListener('input', function () {
    if (confirmInput.value !== '') validateConfirm();
  });
  registerForm.addEventListener('submit', function (ev) {
    if (!validateConfirm()) ev.preventDefault();
  });
})();
</script>
</body>
</html>