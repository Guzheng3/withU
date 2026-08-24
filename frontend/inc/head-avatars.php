<div class="bg-wrap central limg" data-avatar-swap="1">
    <div class="bg-img">
        <div class="middle Blurkg">
            <div class="img-male">
                <div class="avatarArea withu-head-avatar-boy">
                    <img draggable="false" class="avatarFrame lazy" data-src='https://s1.locimg.com/2024/10/18/db01c99842e69.png' style='transform: scale(1.6);top: 2px;left: 2px;'>
                    <img draggable="false" class="aiv_touxiang" data-src="<?php echo $boyAvatar ?? '/assets/images/default-avatar.svg'; ?>">
                    <div class="withu-head-avatar-mask">
                        <div class="withu-head-avatar-top withu-head-avatar-anim-item">
                            <div class="withu-head-avatar-gender-icon" data-gender="male"><i data-lucide="mars"></i></div>
                        </div>
                        <div class="withu-head-avatar-middle withu-head-avatar-anim-item">
                            <div class="withu-head-avatar-status-text withu-head-avatar-status-away">
                                <i data-lucide="clock" class="withu-head-avatar-icon-away"></i>
                                <em>2小时前</em>
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
                <span class="shadow-blur"><?php echo htmlspecialchars($boyName ?? '他', ENT_QUOTES, 'UTF-8'); ?></span>
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
                            <div class="withu-head-avatar-gender-icon" data-gender="female"><i data-lucide="venus"></i></div>
                        </div>
                        <div class="withu-head-avatar-middle withu-head-avatar-anim-item">
                            <div class="withu-head-avatar-status-text withu-head-avatar-status-offline">
                                <i data-lucide="wifi-off" class="withu-head-avatar-icon-offline"></i>
                                <em>离线</em>
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
                <span class="shadow-blur"><?php echo htmlspecialchars($girlName ?? '她', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
    </div>
</div>