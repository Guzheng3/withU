<?php
// 内联片段：安全审核记录。
// 依赖外部已注入的 $db / $currentUser（独立页与 settings.php 均会先完成鉴权）。
// 返回渲染后的 HTML（不含页面标题），可被独立页或 settings.php 高级设置面板复用。
if (!function_exists('withu_advanced_moderation_panel')) {
    function withu_advanced_moderation_panel(): string {
        global $db, $currentUser;

        $message = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require_csrf();
            $id     = (int)($_POST['id'] ?? 0);
            $action = $_POST['action'] ?? '';
            if ($id > 0 && in_array($action, ['approved', 'blocked', 'ignored'], true)) {
                $db->update('moderation_events', [
                    'review_status' => $action,
                    'reviewed_by'   => (int)($currentUser['id'] ?? 0),
                    'reviewed_at'   => withu_now(),
                    'review_note'   => trim((string)($_POST['note'] ?? '')),
                ], 'id = :id', ['id' => $id]);
                $message = '审核状态已更新';
            }
        }

        $rows = $db->fetchAll('SELECT * FROM moderation_events ORDER BY created_at DESC LIMIT 200');

        ob_start();
        ?>
        <?php if ($message): ?><div class="admin-card" style="color:#15803d;margin-bottom:1rem"><?php echo e($message); ?></div><?php endif; ?>
        <section class="admin-card">
            <table class="admin-table">
                <thead><tr><th>时间</th><th>类型</th><th>风险</th><th>原因</th><th>内容</th><th>状态</th><th>处理</th></tr></thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="7" style="text-align:center;color:var(--v3-text-3);padding:1.25rem 0;">暂无审核记录，规则拦截结果产生后会展示在这里。</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo e($row['created_at']); ?></td>
                        <td><?php echo e($row['target_type']); ?></td>
                        <td><?php echo e($row['risk_score']); ?></td>
                        <td><?php echo e(implode('、', (array)json_decode((string)$row['reasons'], true))); ?></td>
                        <td style="max-width:300px;word-break:break-all"><?php echo e($row['content']); ?></td>
                        <td><?php echo e($row['review_status']); ?></td>
                        <td>
                            <form method="post" style="display:flex;gap:.25rem;flex-wrap:wrap">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                <button name="action" value="approved" class="btn btn-secondary">通过</button>
                                <button name="action" value="blocked" class="btn btn-secondary">拦截</button>
                                <button name="action" value="ignored" class="btn btn-secondary">忽略</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
        <?php
        return ob_get_clean();
    }
}
