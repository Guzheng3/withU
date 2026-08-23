<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/withu.php';

$auth = new Auth();
$user = withu_require_couple_user($auth);
$db = Database::getInstance();
$error = '';
$success = '';
$createdLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? 'create';
    if ($action === 'revoke') {
        $id = (int)($_POST['id'] ?? 0);
        $db->update('couple_invites', ['status' => 'revoked'], 'id = :id AND inviter_id = :uid AND status = \'pending\'', ['id' => $id, 'uid' => $user['id']]);
        $success = '邀请已撤销';
    } else {
        $count = (int)($db->fetch("SELECT COUNT(*) c FROM users WHERE status = 'active'")['c'] ?? 0);
        if ($count >= 2) {
            $error = '情侣空间已经有两个账号';
        } else {
            $rawToken = withu_token(24);
            $code = strtoupper(substr($rawToken, 0, 8));
            $db->query("UPDATE couple_invites SET status = 'revoked' WHERE inviter_id = :uid AND status = 'pending'", ['uid' => $user['id']]);
            $db->insert('couple_invites', [
                'inviter_id' => $user['id'], 'invite_code' => $code,
                'invite_token_hash' => hash('sha256', $rawToken), 'status' => 'pending',
                'expires_at' => date('Y-m-d H:i:s', time() + 7 * 86400), 'created_at' => withu_now(),
            ]);
            $createdLink = BASE_URL . '/login.php?invite=' . urlencode($rawToken);
            $success = '邀请链接已生成，7 天内有效';
        }
    }
}

$invites = $db->fetchAll("SELECT * FROM couple_invites WHERE inviter_id = :uid ORDER BY created_at DESC", ['uid' => $user['id']]);
$adminPage = 'invites';
include __DIR__ . '/header.php';
?>
<section class="admin-page-title"><h1>邀请另一半</h1><p>你先创建账号，再把一次性邀请链接发给对方。</p></section>
<?php if ($error): ?><div class="admin-card" style="color:#b91c1c;margin-bottom:1rem;"><?php echo e($error); ?></div><?php endif; ?>
<?php if ($success): ?><div class="admin-card" style="color:#15803d;margin-bottom:1rem;"><?php echo e($success); ?></div><?php endif; ?>
<section class="admin-card" style="margin-bottom:1rem;">
  <form method="post"><?php echo csrf_field(); ?><input type="hidden" name="action" value="create"><button class="btn btn-primary" type="submit"><i class="fas fa-link"></i> 生成新邀请链接</button></form>
  <?php if ($createdLink): ?><label style="display:block;margin-top:1rem;">邀请链接</label><input style="width:100%;padding:.65rem;" readonly value="<?php echo e($createdLink); ?>"><p style="font-size:.85rem;color:var(--text-light);">复制这个链接发送给对方。链接只显示一次，请妥善保存。</p><?php endif; ?>
</section>
<section class="admin-card"><h2 style="margin-top:0;">邀请记录</h2><table class="admin-table"><thead><tr><th>邀请码</th><th>状态</th><th>有效期</th><th>操作</th></tr></thead><tbody>
<?php foreach ($invites as $invite): ?><tr><td><?php echo e($invite['invite_code']); ?></td><td><?php echo e($invite['status']); ?></td><td><?php echo e($invite['expires_at']); ?></td><td><?php if ($invite['status'] === 'pending'): ?><form method="post"><?php echo csrf_field(); ?><input type="hidden" name="action" value="revoke"><input type="hidden" name="id" value="<?php echo (int)$invite['id']; ?>"><button type="submit" class="btn btn-secondary">撤销</button></form><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></section>
<?php include __DIR__ . '/footer.php'; ?>
