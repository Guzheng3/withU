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
                $qq       = trim((string) ($_POST['qq'] ?? ''));
                $username = $qq; // QQ 号即登录账号
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
// 提交失败后停留在原表单并回填已填内容（密码不回填）
$lastAction   = (string) ($_POST['action'] ?? '');
$oldLoginName = $lastAction === 'login' ? trim((string) ($_POST['username'] ?? '')) : '';
$oldQq        = $lastAction === 'register' ? trim((string) ($_POST['qq'] ?? '')) : '';
$oldNickname  = $lastAction === 'register' ? trim((string) ($_POST['nickname'] ?? '')) : '';
$activeTab    = ($lastAction === 'register' && $error !== '') ? 'register' : 'login';

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
<link rel="stylesheet" href="/admin-assets/vendor/fontawesome/css/all.min.css">
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
.auth-tabs{display:flex;gap:4px;margin-bottom:18px;background:#f6f7fb;padding:4px;border-radius:12px;}
.auth-tabs .tab-btn{flex:1;padding:9px 0;border:none;border-radius:9px;background:transparent;cursor:pointer;font-size:14px;color:#888;transition:.2s;font-family:inherit;font-weight:600;}
.auth-tabs .tab-btn:hover:not(.active){color:var(--brand);}
.auth-tabs .tab-btn.active{background:linear-gradient(135deg,var(--brand),var(--brand-dark));color:#fff;box-shadow:0 3px 10px rgba(231,84,128,.3);}
.label-row{display:flex;align-items:center;justify-content:space-between;}
.qq-avatar-preview{width:22px;height:22px;border-radius:50%;object-fit:cover;border:1.5px solid #f3c1d3;display:none;}
.field-error{color:#c0392b;font-size:12px;margin-top:5px;display:none;}
.input-error{border-color:#e74c3c !important;box-shadow:0 0 0 3px rgba(231,76,60,.1) !important;}
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
.form-group-hidden{display:none;}
</style>
</head>
<body>
<div class="login-card">
  <div class="login-head">
    <h3>💗 withU</h3>
    <p id="authSubtitle">登录你的账号，记录你们的点点滴滴</p>
  </div>
  <div class="login-body">

    <?php if ($error): ?>
    <div class="login-err"><i class="fas fa-exclamation-circle"></i> <?php echo e($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="login-success"><i class="fas fa-check-circle"></i> <?php echo e($success); ?></div>
    <?php endif; ?>

    <?php if ($registerEnabled): ?>
    <div class="auth-tabs" role="tablist">
      <button type="button" class="tab-btn<?php echo $activeTab === 'login' ? ' active' : ''; ?>" data-tab="login" onclick="toggleForm('login')">登 录</button>
      <button type="button" class="tab-btn<?php echo $activeTab === 'register' ? ' active' : ''; ?>" data-tab="register" onclick="toggleForm('register')">注 册</button>
    </div>
    <?php endif; ?>

    <!-- 登录表单 -->
    <form method="POST" action="/login.php" id="loginForm" style="display:<?php echo $activeTab === 'login' ? 'block' : 'none'; ?>;">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="login">

      <div class="form-group">
        <label><i class="fab fa-qq"></i> QQ号</label>
        <input type="text" name="username" inputmode="numeric" maxlength="11" autocomplete="username" value="<?php echo e($oldLoginName); ?>" autofocus>
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

      <div class="form-group">
        <label class="label-row">
          <span><i class="fab fa-qq"></i> QQ号</span>
          <img class="qq-avatar-preview" id="qqAvatarPreview" alt="QQ 头像预览">
        </label>
        <input type="text" name="qq" inputmode="numeric" maxlength="11" placeholder="QQ 号即登录账号" value="<?php echo e($oldQq); ?>" required>
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
      <p class="form-hint">这是邀请注册，账号会自动加入情侣空间。你的性别由对方决定（自动设为相反性别），无需选择。</p>
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
var AUTH_SUBTITLES = {
  login: '登录你的账号，记录你们的点点滴滴',
  register: '创建你们的账号，开启情侣空间'
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

  // 填完 QQ 号后自动带出头像与昵称（昵称仅在未填写时填充）
  qqInput.addEventListener('change', function () {
    var qq = qqInput.value.trim();
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
        if (d.nickname && nicknameInput && !nicknameInput.value.trim()) {
          nicknameInput.value = d.nickname;
        }
      })
      .catch(function () { /* 获取失败不影响注册 */ });
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