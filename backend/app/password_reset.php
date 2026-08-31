<?php
// 专属改密链接落地页：/password_reset.php?token=xxx（链接 5 分钟有效，由后台个人资料页生成）
// 独立于登录态：无论浏览器是否已登录都可打开，已登录用户改完密码也不强制重新登录。
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/helpers.php';

$auth = new Auth(); // 仅初始化会话，不要求登录、不重定向已登录用户
$db   = Database::getInstance();

$error   = '';
$success = '';
$done    = false; // 改密成功后置 true，展示成功态

$rawToken = trim((string) ($_GET['token'] ?? $_POST['reset_token'] ?? ''));
$resetRow = null;
if ($rawToken !== '') {
    try {
        $resetRow = $db->fetch(
            "SELECT t.id AS token_id, t.user_id, t.expires_at, u.username, u.nickname
             FROM password_reset_tokens t
             JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = :token
               AND t.status = 'pending'
               AND t.expires_at >= :now
               AND u.status = 'active'
             LIMIT 1",
            ['token' => hash('sha256', $rawToken), 'now' => date('Y-m-d H:i:s')]
        );
    } catch (Throwable $e) {
        $resetRow = null; // 表尚未迁移或查询异常时按无效链接兜底
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$resetRow) {
        $error = '改密链接无效或已过期，请回到后台重新生成';
    } elseif (!csrf_verify($_POST['_token'] ?? '')) {
        $error = '表单已过期，请刷新页面后重试';
    } else {
        $newPassword     = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['password_confirm'] ?? '');

        if (strlen($newPassword) < 8) {
            $error = '密码长度不能少于 8 位';
        } elseif ($newPassword !== $confirmPassword) {
            $error = '两次输入的密码不一致';
        } else {
            try {
                $db->update(
                    'users',
                    ['password' => password_hash($newPassword, PASSWORD_DEFAULT)],
                    'id = :id',
                    ['id' => (int)$resetRow['user_id']]
                );
                // 当前链接标记已用，并把该账号其余 pending 链接一并作废
                $db->update(
                    'password_reset_tokens',
                    ['status' => 'used', 'used_at' => date('Y-m-d H:i:s')],
                    'id = :id AND status = \'pending\'',
                    ['id' => (int)$resetRow['token_id']]
                );
                $db->query(
                    "UPDATE password_reset_tokens SET status = 'revoked' WHERE user_id = :uid AND status = 'pending'",
                    ['uid' => (int)$resetRow['user_id']]
                );
                $success = '密码修改成功，请使用新密码登录';
                $done    = true;
            } catch (Throwable $e) {
                $error = '密码保存失败，请稍后重试';
            }
        }
    }
}

// 校验失败、改密成功后不再渲染表单
$showForm = ($resetRow && !$done);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>修改登录密码 - <?php echo e(SITE_NAME); ?></title>
<link rel="stylesheet" href="/admin-assets/vendor/fontawesome/css/all.min.css">
<style>
:root{--brand:#e75480;--brand-dark:#c23b64;}
*{box-sizing:border-box;}
body{background:linear-gradient(135deg,#ffeef5 0%,#f4f6fb 60%,#eef2ff 100%);
  font-family:"Nunito","Microsoft YaHei","PingFang SC",sans-serif;margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;}
.reset-card{width:420px;max-width:92vw;background:#fff;border-radius:18px;box-shadow:0 18px 50px rgba(180,60,110,.15);overflow:hidden;}
.reset-head{background:linear-gradient(135deg,#e75480,#c23b64);color:#fff;padding:32px 30px 26px;text-align:center;}
.reset-head h3{margin:0 0 4px;font-weight:800;font-size:22px;letter-spacing:.5px;}
.reset-head p{margin:0;opacity:.85;font-size:13px;line-height:1.6;}
.reset-body{padding:26px 30px 30px;}
.reset-body label{font-size:13px;color:#555;font-weight:600;margin-bottom:6px;display:block;}
.reset-body label i{color:var(--brand);margin-right:4px;}
.reset-body input[type="password"]{width:100%;padding:11px 14px;border:1.5px solid #e3e6f0;border-radius:10px;font-size:14px;outline:none;transition:.2s;font-family:inherit;}
.reset-body input[type="password"]:focus{border-color:var(--brand);box-shadow:0 0 0 3px rgba(231,84,128,.12);}
.reset-body .account-box{width:100%;padding:11px 14px;border:1.5px dashed #e3e6f0;border-radius:10px;font-size:14px;background:#f9fafb;color:#888;}
.reset-body .btn{width:100%;padding:12px;border:none;border-radius:10px;background:linear-gradient(135deg,#e75480,#c23b64);color:#fff;font-size:15px;font-weight:700;cursor:pointer;transition:.2s;font-family:inherit;}
.reset-body .btn:hover{filter:brightness(1.08);transform:translateY(-1px);box-shadow:0 4px 14px rgba(231,84,128,.3);}
.reset-body .btn i{margin-right:6px;}
.reset-tip{margin-top:14px;font-size:12px;color:#999;text-align:center;line-height:1.7;}
.reset-err{background:#fff1f1;color:#c0392b;border:1px solid #ffd6d6;padding:9px 12px;border-radius:8px;font-size:13px;margin-bottom:14px;display:flex;align-items:flex-start;gap:6px;}
.reset-success{background:#f0faf0;color:#27ae60;border:1px solid #d5f5d5;padding:12px;border-radius:10px;font-size:14px;margin-bottom:16px;text-align:center;}
.reset-footer{text-align:center;margin-top:16px;padding-top:16px;border-top:1px solid #f0f0f0;}
.reset-footer a{color:#999;text-decoration:none;font-size:13px;transition:.2s;}
.reset-footer a:hover{color:var(--brand);}
.state-icon{font-size:40px;margin-bottom:10px;}
</style>
</head>
<body>
<div class="reset-card">
  <div class="reset-head">
    <h3>💗 withU</h3>
    <p>专属改密链接，5 分钟内有效</p>
  </div>
  <div class="reset-body">

    <?php if ($error): ?>
    <div class="reset-err"><i class="fas fa-exclamation-circle"></i> <span><?php echo e($error); ?></span></div>
    <?php endif; ?>

    <?php if ($done): ?>
    <div class="reset-success">
      <div class="state-icon"><i class="fas fa-check-circle"></i></div>
      <?php echo e($success); ?>
    </div>
    <a class="btn" style="display:block;text-align:center;text-decoration:none;" href="/login.php"><i class="fas fa-sign-in-alt"></i> 去登录</a>
    <?php elseif (!$showForm): ?>
    <div class="reset-err" style="margin-bottom:0;"><i class="fas fa-unlink"></i> <span>改密链接无效或已过期。<br>请登录后台，在「个人资料 → 修改登录密码」重新生成一条链接。</span></div>
    <?php else: ?>
    <form method="POST" action="/password_reset.php" novalidate>
      <?php echo csrf_field(); ?>
      <input type="hidden" name="reset_token" value="<?php echo e($rawToken); ?>">

      <div class="form-group" style="margin-bottom:14px;">
        <label><i class="fas fa-user"></i> 账号</label>
        <div class="account-box"><?php echo e($resetRow['nickname'] ?: $resetRow['username']); ?>（<?php echo e($resetRow['username']); ?>）</div>
      </div>
      <div class="form-group" style="margin-bottom:14px;">
        <label><i class="fas fa-lock"></i> 新密码</label>
        <input type="password" name="password" minlength="8" placeholder="至少 8 位" autocomplete="new-password" required autofocus>
      </div>
      <div class="form-group" style="margin-bottom:18px;">
        <label><i class="fas fa-check-double"></i> 确认新密码</label>
        <input type="password" name="password_confirm" minlength="8" placeholder="再输入一次新密码" autocomplete="new-password" required>
      </div>

      <button type="submit" class="btn"><i class="fas fa-key"></i> 确认修改</button>
    </form>
    <?php endif; ?>

    <div class="reset-footer">
      <a href="/login.php"><i class="fas fa-arrow-left"></i> 返回登录</a>
    </div>
  </div>
</div>
</body>
</html>
