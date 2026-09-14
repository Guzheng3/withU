<?php
/** 内容安全：规则拦截，所有拦截都保留后台记录。 */
function withu_moderate_text($db, string $targetType, int $targetId, string $content): array
{
    $normalized = trim(preg_replace('/\s+/u', ' ', $content));
    $reasons = [];
    $score = 0.0;
    if (preg_match('/(?:加微信|加v|加V|博彩|赌博|刷单|贷款|色情|诈骗|裸聊|傻逼|操你|fuck|sex)/iu', $normalized)) {
        $reasons[] = '敏感或高风险词';
        $score = 0.95;
    }
    if (preg_match_all('#https?://|www\.#iu', $normalized, $matches) >= 2) {
        $reasons[] = '多个外部链接';
        $score = max($score, 0.85);
    }
    if (preg_match('/(.)\1{9,}/u', $normalized)) {
        $reasons[] = '重复刷屏';
        $score = max($score, 0.8);
    }
    if (mb_strlen($normalized, 'UTF-8') > 1000) {
        $reasons[] = '超长内容';
        $score = max($score, 0.7);
    }
    $ruleResult = $score >= 0.9 ? 'block' : ($score >= 0.7 ? 'review' : 'allow');
    $reviewStatus = $ruleResult === 'block' ? 'blocked' : ($ruleResult === 'review' ? 'pending' : 'approved');
    $logId = 0;
    try {
        $logId = (int)$db->insert('moderation_events', [
            'target_type' => $targetType, 'target_id' => $targetId ?: null, 'content' => $normalized,
            'rule_result' => $ruleResult, 'risk_score' => $score,
            'reasons' => json_encode($reasons, JSON_UNESCAPED_UNICODE), 'review_status' => $reviewStatus, 'created_at' => withu_now(),
        ]);
    } catch (Throwable $e) {}
    return ['blocked' => $ruleResult === 'block', 'review' => $ruleResult === 'review', 'score' => $score, 'reasons' => $reasons, 'log_id' => $logId];
}
function withu_finish_moderation($db, int $logId, int $targetId): void
{
    if ($logId <= 0 || $targetId <= 0) return;
    try { $db->update('moderation_events', ['target_id' => $targetId], 'id = :id', ['id' => $logId]); } catch (Throwable $e) {}
}
