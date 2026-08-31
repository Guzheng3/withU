<?php
// 新版后台 - 个人资料（移动端优先）
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
$db = Database::getInstance();

$sessionUser = $auth->getCurrentUser();
if (!$sessionUser) {
    $auth->logout();
    header('Location: /login.php');
    exit;
}

$currentUser = $db->fetch("SELECT * FROM users WHERE id = :id LIMIT 1", ['id' => $sessionUser['id']]);
if (!$currentUser) {
    $auth->logout();
    header('Location: /login.php');
    exit;
}

// 伴侣信息：对方注册后展示资料，未注册时展示邀请入口
$partnerRole = $currentUser['role'] === 'user1' ? 'user2' : 'user1';
$partner = $db->fetch(
    "SELECT id, username, nickname, avatar, created_at FROM users WHERE role = :role AND status = 'active' LIMIT 1",
    ['role' => $partnerRole]
);

$error   = '';
$success = '';

if (isset($_GET['success']) && $_GET['success'] === '1') {
    $success = '资料更新成功';
}

// 读取一次性展示的改密链接 flash（PRG 回跳携带），过期或缺失时给出兜底提示
$resetFlash = null;
if (isset($_GET['reset']) && $_GET['reset'] === '1' && !empty($_SESSION['password_reset_flash'])) {
    $resetFlash = $_SESSION['password_reset_flash'];
    unset($_SESSION['password_reset_flash']);
    if (empty($resetFlash['link']) || (int)($resetFlash['expires_at'] ?? 0) < time()) {
        $resetFlash = null;
        $error = '改密链接已失效，请重新生成';
    }
}

// 生成 5 分钟有效的伴侣改密链接：对方密码遗失时由本人代为重置；与资料更新共用 POST 入口，按 action 分流
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_password_reset') {
    require_csrf();

    if (!$partner) {
        $error = '还没有伴侣账号，无法生成改密链接';
    } else {
        try {
            $rawToken = withu_token(24);
            // 同一时间只保留一条可用链接：生成新链接前把对方的旧 pending 链接全部作废
            $db->query(
                "UPDATE password_reset_tokens SET status = 'revoked' WHERE user_id = :uid AND status = 'pending'",
                ['uid' => (int)$partner['id']]
            );
            $db->insert('password_reset_tokens', [
                'user_id'      => (int)$partner['id'],
                'token_hash'   => hash('sha256', $rawToken),
                'status'       => 'pending',
                'expires_at'   => date('Y-m-d H:i:s', time() + 300),
                'requested_ip' => getClientIp(),
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
            $_SESSION['password_reset_flash'] = [
                'link'       => BASE_URL . '/password_reset.php?token=' . urlencode($rawToken),
                'expires_at' => time() + 300,
            ];
            header('Location: profile.php?reset=1#password-reset');
            exit;
        } catch (Throwable $e) {
            $error = '改密链接生成失败，请稍后重试';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $nickname        = trim($_POST['nickname'] ?? '');
    $qq              = trim($_POST['qq'] ?? '');
    $avatarSource    = $_POST['avatar_source'] ?? 'qq';
    $newPassword     = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($nickname === '') {
        $error = '昵称不能为空';
    } elseif ($newPassword !== '' && $newPassword !== $confirmPassword) {
        $error = '两次输入的新密码不一致';
    } else {
        $data = [
            'nickname'      => $nickname,
            'qq'            => $qq,
            'avatar_source' => $avatarSource,
        ];

        $newAvatarUrl = null;

        if ($avatarSource === 'qq') {
            if ($qq === '') {
                $error = '请选择 QQ 头像时，请先填写 QQ 号';
            } else {
                // 官方 QQ 头像接口：直接按 QQ 号生成头像 URL
                // https://q1.qlogo.cn/g?b=qq&nk=QQ号码&s=640
                $newAvatarUrl   = 'https://q1.qlogo.cn/g?b=qq&nk=' . urlencode($qq) . '&s=640';
                $data['avatar'] = $newAvatarUrl;
            }
        } elseif ($avatarSource === 'upload') {
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $upload = uploadFile($_FILES['avatar'], 'avatars');
                if (!empty($upload['success'])) {
                    $newAvatarUrl   = $upload['url'];
                    $data['avatar'] = $newAvatarUrl;
                } else {
                    $error = $upload['message'] ?? '头像上传失败';
                }
            }
        }

        if ($error === '') {
            if ($newAvatarUrl && !empty($currentUser['avatar']) && strpos($currentUser['avatar'], '/uploads/') === 0) {
                deleteFile(str_replace(UPLOAD_URL, '', $currentUser['avatar']));
            }

            if ($newPassword !== '') {
                $data['new_password'] = $newPassword;
            }

            $auth->updateProfile($data);

            header('Location: profile.php?success=1');
            exit;
        } else {
            $currentUser['nickname']      = $nickname;
            $currentUser['qq']            = $qq;
            $currentUser['avatar_source'] = $avatarSource;
        }
    }
}

$adminPage = 'profile';

include __DIR__ . '/header.php';
?>

    <section class="admin-page-title">
        <h1>个人资料</h1>
        <p>修改你的昵称、头像和登录密码</p>
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

    <section class="admin-card" style="margin-bottom:0.75rem;">
        <div class="admin-card-header">
            <div>
                <div class="admin-card-title">
                    <?php if ($partner): ?>
                        <i class="ti ti-heart-filled" style="color:#d6336c;"></i>我的伴侣
                    <?php else: ?>
                        <i class="ti ti-user-plus" style="color:#d6336c;"></i>邀请伴侣
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php if ($partner): ?>
            <div style="display:flex;align-items:center;gap:0.85rem;">
                <img src="<?php echo e($partner['avatar']); ?>" alt="伴侣头像"
                     style="width:56px;height:56px;border-radius:50%;object-fit:cover;box-shadow:var(--shadow-md);flex:0 0 auto;">
                <div style="min-width:0;">
                    <div style="font-weight:600;font-size:0.95rem;"><?php echo e($partner['nickname'] ?: $partner['username']); ?></div>
                    <div style="margin-top:0.15rem;font-size:0.78rem;color:var(--text-light);">
                        @<?php echo e($partner['username']); ?><?php echo !empty($partner['created_at']) ? ' · ' . e(date('Y-m-d', strtotime($partner['created_at']))) . ' 加入' : ''; ?>
                    </div>
                </div>
            </div>

            <div id="password-reset" style="margin-top:1rem;padding-top:0.9rem;border-top:1px dashed rgba(148,163,184,0.45);">
                <div style="display:flex;align-items:center;gap:0.4rem;font-weight:600;font-size:0.9rem;">
                    <i class="ti ti-lock" style="color:#d6336c;"></i>修改伴侣密码
                </div>
                <p style="margin:0.3rem 0 0.65rem;font-size:0.8rem;line-height:1.6;color:var(--text-light);">
                    生成一条 5 分钟内有效的专属改密链接，复制后发给对方或在其设备上打开，即可为「<?php echo e($partner['nickname'] ?: $partner['username']); ?>（<?php echo e($partner['username']); ?>）」设置新登录密码，适合对方忘记密码时使用。再次生成时，旧链接会立即失效。
                </p>
                <form method="post" style="margin:0;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="create_password_reset">
                    <button type="submit" class="btn btn-secondary btn-sm">
                        <i class="fas fa-key"></i>
                        <span>生成改密链接</span>
                    </button>
                </form>
                <?php if ($resetFlash): ?>
                    <div id="resetLinkArea" data-expires-at="<?php echo (int)$resetFlash['expires_at']; ?>" style="margin-top:0.85rem;">
                        <div style="display:flex;gap:0.5rem;align-items:stretch;flex-wrap:wrap;">
                            <input id="resetLinkInput" readonly
                                   value="<?php echo e($resetFlash['link']); ?>"
                                   style="flex:1 1 auto;min-width:0;padding:0.55rem 0.75rem;border-radius:0.75rem;border:1px solid rgba(148,163,184,0.7);font-size:0.82rem;color:var(--text);">
                            <button type="button" id="resetCopyBtn" class="btn btn-primary btn-sm" style="flex:0 0 auto;white-space:nowrap;">
                                <i class="fas fa-copy"></i>
                                <span>一键复制</span>
                            </button>
                            <a id="resetOpenLink" href="<?php echo e($resetFlash['link']); ?>" target="_blank" rel="noopener"
                               class="btn btn-secondary btn-sm" style="flex:0 0 auto;white-space:nowrap;">
                                <i class="ti ti-external-link"></i>
                                <span>打开</span>
                            </a>
                        </div>
                        <p id="resetLinkHint" style="margin:0.35rem 0 0;font-size:0.78rem;color:var(--text-light);">
                            链接仅显示一次，5 分钟内有效，可直接发给对方。<span id="resetCountdown"></span>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div style="display:flex;align-items:center;gap:0.85rem;">
                <span style="flex:0 0 auto;display:flex;align-items:center;justify-content:center;width:44px;height:44px;border-radius:12px;background:rgba(244,114,182,0.14);color:#d6336c;font-size:1.25rem;"><i class="ti ti-user-plus"></i></span>
                <div style="min-width:0;flex:1 1 auto;">
                    <div style="font-weight:600;font-size:0.92rem;">对方还没有加入</div>
                    <div style="margin-top:0.1rem;font-size:0.78rem;color:var(--text-light);">生成一次性邀请链接，对方注册后即可加入你们的小站</div>
                </div>
                <a href="/admin/invites.php" class="btn btn-secondary btn-sm" style="flex:0 0 auto;white-space:nowrap;">
                    <span>去邀请</span><i class="ti ti-chevron-right"></i>
                </a>
            </div>
        <?php endif; ?>
    </section>

    <form method="POST" enctype="multipart/form-data" class="admin-card" novalidate>
        <?php echo csrf_field(); ?>

        <div class="form-group" style="margin-bottom:0.75rem;">
            <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">用户名</label>
            <input
                type="text"
                value="<?php echo e($currentUser['username']); ?>"
                disabled
                style="width:100%;padding:0.55rem 0.75rem;border-radius:0.75rem;border:1px dashed rgba(148,163,184,0.7);background:#f9fafb;font-size:0.9rem;">
            <div style="margin-top:0.2rem;font-size:0.78rem;color:var(--text-light);">
                用户名暂不支持修改。
            </div>
        </div>

        <div class="form-group" style="margin-bottom:0.75rem;">
            <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">昵称 *</label>
            <input
                type="text"
                name="nickname"
                value="<?php echo e($currentUser['nickname'] ?? ''); ?>"
                style="width:100%;padding:0.55rem 0.75rem;border-radius:0.75rem;border:1px solid rgba(148,163,184,0.7);font-size:0.9rem;">
        </div>

        <div class="form-group" style="margin-bottom:0.75rem;">
            <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">QQ 号</label>
            <input
                type="text"
                name="qq"
                value="<?php echo e($currentUser['qq'] ?? ''); ?>"
                placeholder="填写后自动获取该 QQ 号的头像"
                style="width:100%;padding:0.55rem 0.75rem;border-radius:0.75rem;border:1px solid rgba(148,163,184,0.7);font-size:0.9rem;">
            <div id="qqAvatarAutoHint" style="margin-top:0.2rem;font-size:0.78rem;color:var(--text-light);">
                头像默认使用 QQ 头像：填写 QQ 号后自动获取并预览，保存资料后生效。
            </div>
        </div>

        <div class="form-group" style="margin-bottom:0.75rem;">
            <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">头像来源</label>
            <?php $avatarSource = $currentUser['avatar_source'] ?? 'qq'; ?>
            <div style="display:flex;gap:1rem;flex-wrap:wrap;font-size:0.85rem;">
                <label style="display:flex;align-items:center;gap:0.35rem;">
                    <input type="radio" name="avatar_source" value="upload" <?php echo $avatarSource === 'upload' ? 'checked' : ''; ?>>
                    <span>上传头像</span>
                </label>
                <label style="display:flex;align-items:center;gap:0.35rem;">
                    <input type="radio" name="avatar_source" value="qq" <?php echo $avatarSource === 'qq' ? 'checked' : ''; ?>>
                    <span>使用 QQ 头像</span>
                </label>
            </div>
        </div>

        <div class="form-group" style="margin-bottom:0.75rem;">
            <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">当前头像</label>
            <div style="margin-bottom:0.75rem;">
                <img src="<?php echo e($currentUser['avatar']); ?>" alt="头像"
                     style="width:80px;height:80px;border-radius:50%;object-fit:cover;box-shadow:var(--shadow-md);">
            </div>
            <input type="file" name="avatar" accept="image/*" style="font-size:0.85rem;">
            <?php
            $maxUploadBytes = get_max_upload_size_bytes();
            $maxUploadMb    = round($maxUploadBytes / 1024 / 1024, 1);
            ?>
            <div style="margin-top:0.2rem;font-size:0.78rem;color:var(--text-light);">
                支持 JPG / PNG / GIF / WebP，建议尺寸不小于 200×200，单文件最大约 <?php echo $maxUploadMb; ?>MB。
            </div>
        </div>

        <div class="form-group" style="margin-bottom:0.75rem;">
            <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">新密码</label>
            <input
                type="password"
                name="new_password"
                placeholder="留空则不修改密码"
                style="width:100%;padding:0.55rem 0.75rem;border-radius:0.75rem;border:1px solid rgba(148,163,184,0.7);font-size:0.9rem;">
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
            <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">确认新密码</label>
            <input
                type="password"
                name="confirm_password"
                placeholder="再次输入新密码"
                style="width:100%;padding:0.55rem 0.75rem;border-radius:0.75rem;border:1px solid rgba(148,163,184,0.7);font-size:0.9rem;">
        </div>

        <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i>
                <span>保存修改</span>
            </button>
        </div>
    </form>

<?php include __DIR__ . '/footer.php'; ?>

<script>
(function () {
    var qqInput = document.querySelector('input[name="qq"]');
    var avatarImg = document.querySelector('img[alt="头像"]');
    var avatarSourceRadios = document.querySelectorAll('input[name="avatar_source"]');
    var hint = document.getElementById('qqAvatarAutoHint');
    if (!qqInput || !avatarImg) return;

    var hintText = hint ? hint.textContent : '';
    var debounceTimer = null;

    function setHint(text, isError) {
        if (!hint) return;
        hint.textContent = text;
        hint.style.color = isError ? '#b91c1c' : 'var(--text-light)';
    }

    function fetchQQAvatar() {
        var qq = qqInput.value.trim();
        if (!qq) {
            setHint(hintText, false);
            return;
        }
        // 官方 QQ 头像地址：按 QQ 号直接生成，无需额外接口
        var avatarUrl = 'https://q1.qlogo.cn/g?b=qq&nk=' + encodeURIComponent(qq) + '&s=640';
        avatarImg.src = avatarUrl;
        // 头像来源自动切换为 QQ 头像
        avatarSourceRadios.forEach(function (radio) {
            if (radio.value === 'qq') radio.checked = true;
        });
        setHint('已自动获取 QQ ' + qq + ' 的头像预览，保存资料后生效。', false);
    }

    // 输入 QQ 号后自动获取头像预览，无需手动点击
    qqInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(fetchQQAvatar, 500);
    });

    avatarSourceRadios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (radio.value === 'upload' && radio.checked) {
                setHint('保存后将使用上传的头像文件。', false);
            }
        });
    });
})();

// 改密链接：一键复制（含旧浏览器兜底）+ 5 分钟过期倒计时
(function () {
    var area = document.getElementById('resetLinkArea');
    if (!area) return;

    var input    = document.getElementById('resetLinkInput');
    var btn      = document.getElementById('resetCopyBtn');
    var openLink = document.getElementById('resetOpenLink');
    var hint     = document.getElementById('resetLinkHint');
    var countdown= document.getElementById('resetCountdown');
    var expiresAtMs = parseInt(area.dataset.expiresAt || '0', 10) * 1000;
    var timer = null;

    function say(text, ok) {
        if (!hint) return;
        hint.textContent = text;
        hint.style.color = ok ? '#15803d' : '#b91c1c';
    }

    function selectAll() {
        input.focus();
        input.select();
        try { input.setSelectionRange(0, input.value.length); } catch (e) {}
    }

    // 无 Clipboard API 时的旧式复制兜底（无用户手势时通常会失败）
    function legacyCopy() {
        selectAll();
        try { return document.execCommand('copy'); } catch (e) { return false; }
    }

    function copyLink(fromUser) {
        var okText  = '已复制到剪贴板，可直接发给对方或自己';
        var failText = '复制失败，已全选链接，请按 Ctrl+C 手动复制';
        if (navigator.clipboard && navigator.clipboard.writeText && window.isSecureContext) {
            navigator.clipboard.writeText(input.value).then(function () {
                say(okText, true);
            }).catch(function () {
                var ok = legacyCopy();
                say(ok ? okText : failText, ok);
            });
        } else {
            var ok = legacyCopy();
            say(ok ? okText : failText, ok);
        }
    }

    btn.addEventListener('click', function () { copyLink(true); });
    // 刚生成链接的这次加载：自动复制一次
    copyLink(false);

    // 过期倒计时：到点后禁用复制与打开入口，避免拿到已失效的链接
    function renderCountdown() {
        if (!countdown) return;
        var remainSec = Math.floor((expiresAtMs - Date.now()) / 1000);
        if (remainSec <= 0) {
            countdown.textContent = '链接已过期，请重新生成。';
            countdown.style.color = '#b91c1c';
            btn.disabled = true;
            btn.style.opacity = '0.55';
            if (openLink) {
                openLink.removeAttribute('href');
                openLink.style.opacity = '0.55';
                openLink.style.pointerEvents = 'none';
            }
            clearInterval(timer);
            return;
        }
        var m = Math.floor(remainSec / 60);
        var s = remainSec % 60;
        countdown.textContent = '剩余 ' + m + ':' + (s < 10 ? '0' : '') + s;
    }

    renderCountdown();
    var timer = setInterval(renderCountdown, 1000);
})();
</script>
