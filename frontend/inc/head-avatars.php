<?php
/**
 * 头像区状态徽标：性别与上线时间同步后台用户数据（users.gender / users.last_login_at）
 */
if (!function_exists('withu_head_online_state')) {
    function withu_head_online_state($lastLoginAt) {
        if (empty($lastLoginAt)) {
            return ['offline', '离线'];
        }
        $diff = time() - strtotime((string)$lastLoginAt);
        if ($diff < 600) {
            return ['online', '在线'];
        }
        if ($diff < 3600) {
            return ['away', floor($diff / 60) . ' 分钟前'];
        }
        if ($diff < 86400) {
            return ['away', floor($diff / 3600) . ' 小时前'];
        }
        if ($diff < 2592000) {
            return ['away', floor($diff / 86400) . ' 天前'];
        }
        return ['offline', '离线'];
    }
}

// 性别缺失时按头像区默认（左男右女）兜底
$withuBoyGender = ($boyGender ?? null) === 'female' ? 'female' : 'male';
$withuGirlGender = ($girlGender ?? null) === 'male' ? 'male' : 'female';
list($withuBoyState, $withuBoyStateText) = withu_head_online_state($boyLastLogin ?? null);
list($withuGirlState, $withuGirlStateText) = withu_head_online_state($girlLastLogin ?? null);
$withuStateIcons = ['online' => 'wifi', 'away' => 'clock', 'offline' => 'wifi-off'];
?>
<div class="bg-wrap central limg" data-avatar-swap="1">
    <div class="bg-img">
        <div class="middle Blurkg">
            <div class="img-male">
                <div class="avatarArea withu-head-avatar-boy">
                    <img draggable="false" class="avatarFrame lazy" data-src='https://s1.locimg.com/2024/10/18/db01c99842e69.png' style='transform: scale(1.6);top: 2px;left: 2px;'>
                    <img draggable="false" class="aiv_touxiang" data-src="<?php echo $boyAvatar ?? '/assets/images/default-avatar.svg'; ?>">
                    <div class="withu-head-avatar-mask">
                        <div class="withu-head-avatar-top withu-head-avatar-anim-item">
                            <div class="withu-head-avatar-gender-icon" data-gender="<?php echo $withuBoyGender; ?>"><i data-lucide="<?php echo $withuBoyGender === 'female' ? 'venus' : 'mars'; ?>"></i></div>
                        </div>
                        <div class="withu-head-avatar-middle withu-head-avatar-anim-item">
                            <div class="withu-head-avatar-status-text withu-head-avatar-status-<?php echo $withuBoyState; ?>">
                                <i data-lucide="<?php echo $withuStateIcons[$withuBoyState]; ?>" class="withu-head-avatar-icon-<?php echo $withuBoyState; ?>"></i>
                                <em><?php echo htmlspecialchars($withuBoyStateText, ENT_QUOTES, 'UTF-8'); ?></em>
                            </div>
                            <div class="withu-head-avatar-divider"></div>
                        </div>
                        <div class="withu-head-avatar-bottom withu-head-avatar-anim-item">
                            <div class="withu-head-avatar-location" data-location-slot="1">
                                <i data-lucide="map-pin"></i>
                                <em>加载中...</em>
                            </div>
                        </div>
                    </div>
                </div>
                <span class="shadow-blur"><?php echo htmlspecialchars($boyName ?? 'Ki.', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="love-icon">
                <div class="love-info-wrapper">
                    <div class="distance-bubble" onclick="if(window.WithUMap)WithUMap.open({mode:'lovers'})" style="cursor:pointer">
                        <div class="distance-icon-box">
                            <i class="ph-fill ph-navigation-arrow"></i>
                        </div>
                        <div class="distance-text">
                            <span class="distance-text-sm">相距</span>
                            <span class="km-value">546.9</span>
                            <span class="distance-text-sm">km</span>
                        </div>
                    </div>
                </div>
                <img draggable="false" src="../Style/img/like.svg">
            </div>
            <div class="img-female">
                <div class="avatarArea withu-head-avatar-girl">
                    <img draggable="false" class="avatarFrame lazy" data-src='https://s1.locimg.com/2024/10/18/db01c99842e69.png' style='transform: scale(1.6);top: 2px;left: 2px;'>
                    <img draggable="false" class="aiv_touxiang" data-src="<?php echo $girlAvatar ?? '/assets/images/default-avatar.svg'; ?>">
                    <div class="withu-head-avatar-mask">
                        <div class="withu-head-avatar-top withu-head-avatar-anim-item">
                            <div class="withu-head-avatar-gender-icon" data-gender="<?php echo $withuGirlGender; ?>"><i data-lucide="<?php echo $withuGirlGender === 'male' ? 'mars' : 'venus'; ?>"></i></div>
                        </div>
                        <div class="withu-head-avatar-middle withu-head-avatar-anim-item">
                            <div class="withu-head-avatar-status-text withu-head-avatar-status-<?php echo $withuGirlState; ?>">
                                <i data-lucide="<?php echo $withuStateIcons[$withuGirlState]; ?>" class="withu-head-avatar-icon-<?php echo $withuGirlState; ?>"></i>
                                <em><?php echo htmlspecialchars($withuGirlStateText, ENT_QUOTES, 'UTF-8'); ?></em>
                            </div>
                            <div class="withu-head-avatar-divider"></div>
                        </div>
                        <div class="withu-head-avatar-bottom withu-head-avatar-anim-item">
                            <div class="withu-head-avatar-location" data-location-slot="2">
                                <i data-lucide="map-pin"></i>
                                <em>加载中...</em>
                            </div>
                        </div>
                    </div>
                </div>
                <span class="shadow-blur"><?php echo htmlspecialchars($girlName ?? 'Really', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
    </div>
</div>