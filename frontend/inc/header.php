<div class="header-wrap">
    <div class="header">
        <div class="logo" style="margin-right:auto;margin-left:3%">
                        <h1><a class="alogo" href="index.php" title="withU" style="display:inline-flex;align-items:center;gap:4px"><img src="assets/images/withu-logo.png" alt="withU" style="height:2.2rem;width:auto;object-fit:contain;vertical-align:middle"></a></h1>
        </div>
        <!-- 吸顶时显示的右侧区域: 情侣头像 + 功能入口 -->
        <div class="withu-header-actions" id="withuHeaderActions">
                            
            <?php if (!empty($withuHeaderSearchBeforePoem)): ?>
            <label class="search-box withu-header-search"><span class="s-icon">⌕</span><input id="watchSearch" placeholder="搜索媒体库"></label>
            <?php endif; ?>
            <div class="withu-header-poem" id="withuHeaderPoem" aria-hidden="true">
                        <span class="withu-header-poem-line">树是梧桐树，城是南京城，一句梧桐美，种满南京城</span>
                    </div>
<div class="withu-couple-avatars-right">
                <div class="withu-avatar-group">
                    <img src="<?php echo htmlspecialchars($girlAvatar ?? '/assets/images/default-avatar.svg', ENT_QUOTES, 'UTF-8'); ?>"
                        class="avatar-male" alt="She">
                                        <img src="<?php echo htmlspecialchars($boyAvatar ?? '/assets/images/default-avatar.svg', ENT_QUOTES, 'UTF-8'); ?>"
                        class="avatar-female" alt="He">
                                    </div>
                                <span class="withu-right-heart"></span>
                            
</div>

                            <?php if ($loggedIn): ?>
                <a href="/watch.php" class="withu-header-map" data-entry="media" title="观影">
                    <span class="withu-header-map-icon-wrap">
                        <i class="ph-fill ph-film-slate"></i>
                    </span>
                    <span class="withu-header-map-text">观影</span>
                </a>
<a href="/admin/" class="withu-header-map" data-entry="admin" title="管理">
                    <span class="withu-header-map-icon-wrap">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    </span>
                    <span class="withu-header-map-text">管理</span>
                </a>
                <?php else: ?>
                <a href="/login.php" class="withu-header-map" data-entry="login" title="登录">
                    <span class="withu-header-map-icon-wrap">
                        <i class="ph-fill ph-user"></i>
                    </span>
                    <span class="withu-header-map-text">登录</span>
                </a>
                <?php endif; ?>

            <!-- 移动端更多按钮 -->
            <button type="button" class="withu-header-more-btn" id="withuHeaderMoreBtn" aria-label="更多信息">
                <i data-lucide="ellipsis"></i>
            </button>

                        
</div>
    </div>
</div>

<!-- 移动端更多面板（毛玻璃磨砂效果） -->
<div class="withu-header-more-panel" id="withuHeaderMorePanel">
    <div class="withu-header-more-overlay" data-close-panel></div>
    <div class="withu-header-more-sheet">
        <button type="button" class="withu-header-more-close" data-close-panel aria-label="关闭">
            <i data-lucide="x"></i>
        </button>

        <!-- stuck-logo 展示 -->
        <div class="withu-header-more-identity">
                                <div class="stuck-logo stuck-logo--en-v7">
                        <span class="stuck-logo__name" data-withu-tip="<?php echo htmlspecialchars($boyName ?? '他', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($boyName ?? '他', ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="stuck-logo__redline-l"></span>
                        <span class="stuck-logo__heart"><svg width="20" height="20" viewBox="0 0 256 256" fill="currentColor"><path d="M240,94c0,70-103.79,126.66-108.21,129a8,8,0,0,1-7.58,0C119.79,220.66,16,164,16,94A62.07,62.07,0,0,1,78,32c20.65,0,38.73,8.88,50,23.89C139.27,40.88,157.35,32,178,32A62.07,62.07,0,0,1,240,94Z"/></svg></span>
                        <span class="stuck-logo__redline-r"></span>
                        <span class="stuck-logo__name" data-withu-tip="<?php echo htmlspecialchars($girlName ?? '她', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($girlName ?? '她', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                        </div>

        <!-- 功能入口 -->
        <div class="withu-header-more-actions">
                        <?php if ($loggedIn): ?>
                        <a href="/watch.php" class="withu-header-more-action-item">
                <span class="withu-header-more-action-icon">
                    <i class="ph-fill ph-film-slate"></i>
                </span>
                <span class="withu-header-more-action-label">观影</span>
            </a>
                        <a href="/admin/" class="withu-header-more-action-item">
                <span class="withu-header-more-action-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                </span>
                <span class="withu-header-more-action-label">管理</span>
            </a>
                        <?php else: ?>
                        <a href="/login.php" class="withu-header-more-action-item">
                <span class="withu-header-more-action-icon">
                    <i class="ph-fill ph-user"></i>
                </span>
                <span class="withu-header-more-action-label">登录</span>
            </a>
                        <?php endif; ?>
                    </div>
    </div>
</div>


<!-- 移动端更多面板（毛玻璃磨砂效果） -->
<div class="withu-header-more-panel" id="withuHeaderMorePanel">
    <div class="withu-header-more-overlay" data-close-panel></div>
    <div class="withu-header-more-sheet">
        <button type="button" class="withu-header-more-close" data-close-panel aria-label="关闭">
            <i data-lucide="x"></i>
        </button>

        <!-- stuck-logo 展示 -->
        <div class="withu-header-more-identity">
                                <div class="stuck-logo stuck-logo--en-v7">
                        <span class="stuck-logo__name" data-withu-tip="<?php echo htmlspecialchars($boyName ?? '他', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($boyName ?? '他', ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="stuck-logo__redline-l"></span>
                        <span class="stuck-logo__heart"><svg width="20" height="20" viewBox="0 0 256 256" fill="currentColor"><path d="M240,94c0,70-103.79,126.66-108.21,129a8,8,0,0,1-7.58,0C119.79,220.66,16,164,16,94A62.07,62.07,0,0,1,78,32c20.65,0,38.73,8.88,50,23.89C139.27,40.88,157.35,32,178,32A62.07,62.07,0,0,1,240,94Z"/></svg></span>
                        <span class="stuck-logo__redline-r"></span>
                        <span class="stuck-logo__name" data-withu-tip="<?php echo htmlspecialchars($girlName ?? '她', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($girlName ?? '她', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                        </div>

        <!-- 功能入口 -->
        <div class="withu-header-more-actions">
                        <?php if ($loggedIn): ?>
                        <a href="/watch.php" class="withu-header-more-action-item">
                <span class="withu-header-more-action-icon">
                    <i class="ph-fill ph-film-slate"></i>
                </span>
                <span class="withu-header-more-action-label">观影</span>
            </a>
                        <a href="/admin/" class="withu-header-more-action-item">
                <span class="withu-header-more-action-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                </span>
                <span class="withu-header-more-action-label">管理</span>
            </a>
                        <?php else: ?>
                        <a href="/login.php" class="withu-header-more-action-item">
                <span class="withu-header-more-action-icon">
                    <i class="ph-fill ph-user"></i>
                </span>
                <span class="withu-header-more-action-label">登录</span>
            </a>
                        <?php endif; ?>
                    </div>
    </div>
</div>

