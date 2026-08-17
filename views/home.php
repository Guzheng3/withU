<section class="love-day-section">
    <div class="section-header">
        <h2><i class="fas fa-heart"></i> withU · 一起看</h2>
    </div>

    <?php if (isset($stats)): ?>
    <div class="overview-grid">
        <div class="stat-card glass-card">
            <div class="stat-icon stat-articles">
                <i class="fas fa-book"></i>
            </div>
            <div class="stat-info">
                <h3 class="stat-number" data-target="<?php echo $stats['articles']; ?>"><?php echo $stats['articles']; ?></h3>
                <p>文章 / 日记</p>
            </div>
        </div>
        <div class="stat-card glass-card">
            <div class="stat-icon stat-albums">
                <i class="fas fa-images"></i>
            </div>
            <div class="stat-info">
                <h3 class="stat-number" data-target="<?php echo $stats['albums']; ?>"><?php echo $stats['albums']; ?></h3>
                <p>爱情相册</p>
            </div>
        </div>
        <div class="stat-card glass-card">
            <div class="stat-icon stat-events">
                <i class="fas fa-calendar-days"></i>
            </div>
            <div class="stat-info">
                <h3 class="stat-number" data-target="<?php echo $stats['events']; ?>"><?php echo $stats['events']; ?></h3>
                <p>纪念事件</p>
            </div>
        </div>
        <div class="stat-card glass-card">
            <div class="stat-icon stat-messages">
                <i class="fas fa-comment-dots"></i>
            </div>
            <div class="stat-info">
                <h3 class="stat-number" data-target="<?php echo $stats['messages']; ?>"><?php echo $stats['messages']; ?></h3>
                <p>留言数量</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="love-counter love-counter-pro glass-card">
        <div class="love-counter-window">
            <span class="window-dot window-dot-red"></span>
            <span class="window-dot window-dot-yellow"></span>
            <span class="window-dot window-dot-green"></span>
        </div>
        <div class="love-counter-body">
            <?php if (!empty($loveStartDate)): ?>
            <p class="love-counter-title-gradient">这是我们一起走过的</p>
            <div class="love-timer" id="love-timer" data-start="<?php echo e($loveStartDate); ?>">
                <span class="love-timer-number" data-unit="days"><?php echo $daysTogether; ?></span>
                <span class="love-timer-label">天</span>
                <span class="love-timer-number" data-unit="hours">00</span>
                <span class="love-timer-label">小时</span>
                <span class="love-timer-number" data-unit="minutes">00</span>
                <span class="love-timer-label">分钟</span>
                <span class="love-timer-number" data-unit="seconds">00</span>
                <span class="love-timer-label">秒</span>
            </div>
            <?php else: ?>
            <p class="love-counter-title-gradient">还没有设置一起走过的开始日期</p>
            <p style="margin-top: 0.75rem; color: var(--text-light); font-size: 0.95rem;">
                可以在后台「系统设置」中设置开始日期，我们会帮你自动计算在一起的天数。
            </p>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (!empty($events)): ?>
    <?php
    $eventCardColors = [
        ['#ff6b81', '#ff9aa2'],
        ['#4ecdc4', '#7ee8df'],
        ['#3b82f6', '#60a5fa'],
        ['#fb923c', '#fed7aa'],
        ['#a855f7', '#c4b5fd'],
        ['#14b8a6', '#5eead4'],
        ['#f97373', '#fecaca'],
        ['#0ea5e9', '#38bdf8'],
    ];

    // 创建颜色索引数组并打乱，用于按顺序分配颜色
    $colorIndices = range(0, count($eventCardColors) - 1);
    shuffle($colorIndices);
    $colorIndex = 0; // 当前使用的颜色索引

    // 分类计数：过去(纪念日) / 未来(倒计时) / 每年今日
    $loveDayPastCount   = 0;
    $loveDayFutureCount = 0;
    foreach ($events as $ev) {
        $evIsFuture = false;
        if (!empty($ev['is_recurring'])) {
            $evIsFuture = true;
        } else {
            $evIsFuture = strtotime($ev['event_date']) > time();
        }
        if ($evIsFuture) { $loveDayFutureCount++; } else { $loveDayPastCount++; }
    }
    ?>
    <div class="withustrm-loveday-tabs-wrap">
        <div class="withustrm-ios-tabs" data-withustrm-loveday-tabs role="tablist" aria-label="纪念日筛选">
            <div class="withustrm-ios-tabs-slider"></div>
            <button type="button" class="withustrm-ios-tab active" data-filter="all" role="tab" aria-selected="true"><i class="fas fa-layer-group"></i><span>全部</span></button>
            <button type="button" class="withustrm-ios-tab" data-filter="past" role="tab" aria-selected="false"><i class="fas fa-heart"></i><span>纪念日</span></button>
            <button type="button" class="withustrm-ios-tab" data-filter="future" role="tab" aria-selected="false"><i class="fas fa-hourglass-half"></i><span>倒计时</span></button>
        </div>
    </div>
    <div class="events-list withustrm-loveday-grid" data-withustrm-loveday-grid>
        <?php foreach ($events as $event): ?>
        <?php
        $todayStr = date('Y-m-d');
        $isFutureEvent = false;
        if (!empty($event['is_recurring'])) {
            $eventDate = new DateTime($event['event_date']);
            $today     = new DateTime($todayStr);
            $currentYearDate = DateTime::createFromFormat(
                'Y-m-d',
                $today->format('Y') . '-' . $eventDate->format('m-d')
            );
            if ($currentYearDate < $today) {
                $currentYearDate->modify('+1 year');
            }
            $displayDate = $currentYearDate->format('Y-m-d');
            $daysUntil   = daysBetween($today->format('Y-m-d'), $displayDate);
            $isFutureEvent = true;
        } else {
            $daysUntil = null;
            $daysAgo   = daysBetween($event['event_date'], $todayStr);
            $displayDate = $event['event_date'];
            $isFutureEvent = strtotime($event['event_date']) > time();
        }

        // 按顺序从打乱的颜色索引中选择颜色，确保不重复
        $palette = $eventCardColors[$colorIndices[$colorIndex]];
        $colorIndex++;

        // 当所有颜色都用完后，重新打乱并从头开始
        if ($colorIndex >= count($colorIndices)) {
            shuffle($colorIndices);
            $colorIndex = 0;
        }

        $bgStart = $palette[0];
        $bgEnd   = $palette[1];
        $filterClass = $isFutureEvent ? 'future' : 'past';
        ?>
        <div class="event-pill withustrm-loveday-widget withustrm-loveday-widget--<?php echo $filterClass; ?> withustrm-loveday-item" data-withustrm-filter="<?php echo $filterClass; ?>" style="background: linear-gradient(135deg, <?php echo $bgStart; ?> 0%, <?php echo $bgEnd; ?> 100%);">
            <?php if (!empty($event['is_important'])): ?>
            <div class="withustrm-loveday-sup-label"><i class="fas fa-star"></i>&nbsp;重要</div>
            <?php elseif ($isFutureEvent && $daysUntil !== null && $daysUntil >= 0): ?>
            <div class="withustrm-loveday-sup-label">还有 <?php echo $daysUntil; ?> 天</div>
            <?php else: ?>
            <div class="withustrm-loveday-sup-label">已走过</div>
            <?php endif; ?>
            <div class="withustrm-loveday-content">
                <div class="withustrm-loveday-left">
                    <div class="withustrm-loveday-icon">
                        <i class="fas fa-<?php echo e(event_icon_class($event['icon'])); ?>"></i>
                    </div>
                    <div class="withustrm-loveday-copy">
                        <div class="withustrm-loveday-title">
                            <?php echo e($event['title']); ?>
                        </div>
                        <div class="withustrm-loveday-date">
                            <i class="fas fa-calendar-day"></i> <?php echo formatDate($displayDate, 'Y-m-d'); ?>
                        </div>
                    </div>
                </div>
                <div class="withustrm-loveday-count">
                    <?php if (!empty($event['is_recurring'])): ?>
                        <?php if ($daysUntil === 0): ?>
                            今天
                        <?php else: ?>
                            <?php echo $daysUntil; ?><span class="withustrm-loveday-unit">天</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if ($daysAgo === 0): ?>
                            今天
                        <?php elseif ($isFutureEvent): ?>
                            <?php echo $daysAgo; ?><span class="withustrm-loveday-unit">天</span>
                        <?php else: ?>
                            <span class="withustrm-loveday-unit">已</span><?php echo $daysAgo; ?><span class="withustrm-loveday-unit">天</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<?php if (!empty($currentUser) && (!empty($watchGroups) || !empty($watchRoomSummary))): ?>
<section class="content-section withu-home-watch">
    <style>
        .withu-home-watch .watch-home-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:1rem}.withu-home-watch .watch-home-card{display:block;overflow:hidden;border:1px solid var(--withu-border,#e5e7eb);border-radius:8px;background:var(--withu-surface,#fff);color:inherit;text-decoration:none;box-shadow:var(--withu-shadow,0 7px 22px #0f172a0b);transition:transform .2s,border-color .2s}.withu-home-watch .watch-home-card:hover{transform:translateY(-3px);border-color:var(--withu-primary,#ec4899)}.withu-home-watch .watch-home-cover{aspect-ratio:2/3;background:#111827;overflow:hidden;position:relative}.withu-home-watch .watch-home-cover img{width:100%;height:100%;object-fit:cover}.withu-home-watch .watch-home-badge{position:absolute;left:.5rem;bottom:.5rem;background:#111827d9;color:#fff;border-radius:4px;padding:.2rem .4rem;font-size:.72rem}.withu-home-watch .watch-home-body{padding:.65rem}.withu-home-watch .watch-home-title{font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.withu-home-watch .watch-home-meta{margin-top:.3rem;color:var(--withu-text-muted,#64748b);font-size:.78rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.withu-home-watch .watch-home-subtitle{margin:1.3rem 0 .65rem;font-size:1rem}.withu-home-watch .watch-home-empty{padding:1rem;background:var(--withu-surface,#fff);border:1px dashed var(--withu-border,#cbd5e1);border-radius:8px;color:var(--withu-text-muted,#64748b)}.withu-together-card{display:flex;align-items:center;gap:1rem;margin:.4rem 0 1.25rem;padding:1rem 1.1rem;border:1px solid var(--withu-secondary-soft,#b9e3d0);border-radius:12px;background:var(--withu-surface-soft,#f2f8f5)}.withu-together-poster{width:58px;height:82px;flex:0 0 auto;object-fit:cover;border-radius:6px}.withu-together-copy{min-width:0;flex:1}.withu-together-kicker{font-size:.75rem;color:var(--withu-secondary,#4f9c78);font-weight:700}.withu-together-title{margin:.2rem 0;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.withu-together-meta{color:var(--withu-text-muted,#64748b);font-size:.8rem}.withu-together-action{flex:0 0 auto}.withu-together-action a{display:inline-flex;align-items:center;gap:.35rem;padding:.55rem .8rem;border-radius:8px;background:var(--withu-primary,#e486a4);color:#fff;text-decoration:none}.withu-together-empty{padding:1rem;border:1px dashed var(--withu-border,#cbd5e1);border-radius:10px;color:var(--withu-text-muted,#64748b)}@media(max-width:640px){.withu-home-watch .watch-home-row{grid-template-columns:repeat(2,minmax(0,1fr));gap:.7rem}.withu-together-card{align-items:flex-start;flex-wrap:wrap}.withu-together-action{width:100%}.withu-together-action a{width:100%;justify-content:center}}
    </style>
    <div class="section-header"><h2><i class="fas fa-heart"></i> 一起看</h2><a href="/watch.php" class="btn-view" title="打开影视库"><i class="fas fa-arrow-right"></i></a></div>
    <?php if (!empty($watchRoomSummary)): ?>
        <?php $roomTitle = $watchRoomSummary['series_name'] ?: $watchRoomSummary['file_name']; $roomEpisode = !empty($watchRoomSummary['episode_number']) ? '第 ' . (int)$watchRoomSummary['episode_number'] . ' 集' : '当前影片'; $roomState = $watchRoomSummary['playback_state'] === 'playing' ? '正在播放' : '已暂停'; ?>
        <div class="withu-together-card">
            <img class="withu-together-poster" src="/api/media_cover.php?id=<?php echo (int)$watchRoomSummary['media_id']; ?>" alt="">
            <div class="withu-together-copy"><div class="withu-together-kicker">一起看 · <?php echo $watchRoomSummary['online_members'] > 1 ? '两个人都在线' : ($watchRoomSummary['joined'] ? '你已进入房间' : '等待加入'); ?></div><div class="withu-together-title"><?php echo e($roomTitle); ?></div><div class="withu-together-meta"><?php echo e($roomEpisode); ?> · <?php echo e($roomState); ?> · 已同步 <?php echo (int)$watchRoomSummary['online_members']; ?> 人</div></div>
            <div class="withu-together-action"><a href="/watch_play.php?media_id=<?php echo (int)$watchRoomSummary['media_id']; ?>"><i class="fas fa-play"></i> <?php echo $watchRoomSummary['joined'] ? '继续一起看' : '加入一起看'; ?></a></div>
        </div>
    <?php else: ?>
        <div class="withu-together-empty">还没有正在进行的共看，选择一部影片开始你们的下一场一起看。</div>
    <?php endif; ?>
    <?php if (!empty($watchRecent)): ?><h3 class="withu-home-watch watch-home-subtitle">近期观看</h3><div class="watch-home-row">
        <?php foreach ($watchRecent as $item): $isStrm = (string)($item['history_source'] ?? 'library') === 'strm'; $recentUrl = $isStrm ? '/watch_play.php?source=strm&id=' . (int)$item['id'] . ((int)($item['history_source_episode'] ?? 0) > 0 ? '&episode=' . (int)($item['history_source_episode']) : '') : '/watch_play.php?media_id=' . (int)$item['id']; ?><a class="watch-home-card" href="<?php echo e($recentUrl); ?>"><div class="watch-home-cover"><img src="<?php echo e($item['cover_url'] ?: '/assets/images/Coverloaderror.jpg'); ?>" alt=""></div><div class="watch-home-body"><div class="watch-home-title"><?php echo e($item['series_name']); ?></div><div class="watch-home-meta"><?php echo e($item['episode_number'] ? '上次观看 · 第 ' . $item['episode_number'] . ' 集' : $item['file_name']); ?></div></div></a><?php endforeach; ?>
    </div><?php endif; ?>
    <?php if (!empty($watchNew)): ?><h3 class="withu-home-watch watch-home-subtitle">最新添加</h3><div class="watch-home-row">
        <?php foreach ($watchNew as $item): ?><a class="watch-home-card" href="/watch_play.php?media_id=<?php echo (int)$item['id']; ?>"><div class="watch-home-cover"><img src="<?php echo e($item['cover_url'] ?: '/assets/images/Coverloaderror.jpg'); ?>" alt=""></div><div class="watch-home-body"><div class="watch-home-title"><?php echo e($item['series_name']); ?></div><div class="watch-home-meta"><?php echo e($item['episode_number'] ? '第 ' . $item['episode_number'] . ' 集' : ($item['resolution'] ?: '待识别')); ?></div></div></a><?php endforeach; ?>
    </div><?php endif; ?>
    <h3 class="withu-home-watch watch-home-subtitle">全部影片</h3><div class="watch-home-row">
        <?php foreach ($watchGroups as $watchGroup): $first = $watchGroup['items'][0]; ?><a class="watch-home-card" href="/watch_play.php?media_id=<?php echo (int)$first['id']; ?>"><div class="watch-home-cover"><img src="<?php echo e($watchGroup['cover_url']); ?>" alt=""></div><div class="watch-home-body"><div class="watch-home-title"><?php echo e($watchGroup['name']); ?></div><div class="watch-home-meta"><?php echo e($first['rating'] ? '评分 ' . $first['rating'] . ' · ' : ''); ?><?php echo e($first['resolution'] ?: '分辨率未知'); ?></div></div></a><?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="content-section">
    <div class="section-header card-header-row">
        <h2><i class="fas fa-book"></i> 最新动态</h2>
    </div>
    
    <?php
    // 文章共创标记：情侣双方各自贡献字数都达到阈值（例如 10 字）才算共创
    $articleCoCreated        = [];
    $articleDisplayAuthors   = [];
    $articleSecondAvatars    = [];
    $articleCreatorCharsMap  = [];
    $articleOtherCharsMap    = [];
    $articleOtherNamesMap    = [];

    if (!empty($articles) && isset($db) && $db instanceof Database) {
        // 获取情侣双方信息（不依赖登录状态）
        $couple = get_couple_users();
        $user1 = $couple['user1'];
        $user2 = $couple['user2'];
        
        if ($user1 && $user2) {
            try {
                $db->query("
                    CREATE TABLE IF NOT EXISTS `article_contributions` (
                        `article_id` int(11) NOT NULL COMMENT '文章ID',
                        `user_id` int(11) NOT NULL COMMENT '用户ID',
                        `contributed_chars` int(11) NOT NULL DEFAULT 0 COMMENT '累计贡献字数',
                        `last_updated_at` datetime NOT NULL COMMENT '最后更新时间',
                        PRIMARY KEY (`article_id`, `user_id`),
                        KEY `user_id` (`user_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文章贡献统计';
                ");

                $articleIds = array_column($articles, 'id');
                $articleIds = array_map('intval', $articleIds);

                if ($articleIds) {
                    $placeholders = implode(',', array_fill(0, count($articleIds), '?'));
                    $rows = $db->fetchAll(
                        "SELECT article_id, user_id, contributed_chars
                         FROM article_contributions
                         WHERE article_id IN ($placeholders)",
                        $articleIds
                    );

                    $contributors = [];
                    foreach ($rows as $row) {
                        $aid   = (int) $row['article_id'];
                        $uid   = (int) $row['user_id'];
                        $chars = (int) $row['contributed_chars'];
                        if (!isset($contributors[$aid])) {
                            $contributors[$aid] = [];
                        }
                        $contributors[$aid][$uid] = $chars;
                    }

                    $user1Id   = (int) $user1['id'];
                    $user2Id   = (int) $user2['id'];
                    $threshold = 10;

                    foreach ($articles as $article) {
                        $aid          = (int) $article['id'];
                        $creatorId    = isset($article['user_id']) ? (int) $article['user_id'] : 0;
                        $creatorName  = !empty($article['nickname']) ? $article['nickname'] : '匿名用户';
                        $displayName  = $creatorName;
                        $secondAvatar = '';
                        $stats        = $contributors[$aid] ?? [];

                        $user1Chars = $stats[$user1Id] ?? 0;
                        $user2Chars = $stats[$user2Id] ?? 0;
                        $isCo       = ($user1Chars >= $threshold && $user2Chars >= $threshold);

                        $creatorChars = 0;
                        $otherChars   = 0;
                        $otherName    = '';

                        if ($creatorId === $user1Id) {
                            $creatorChars = $user1Chars;
                            $otherChars   = $user2Chars;
                            $otherName    = !empty($user2['nickname']) ? $user2['nickname'] : '';
                            if ($isCo) {
                                $secondAvatar = !empty($user2['avatar']) ? $user2['avatar'] : '/assets/images/default-avatar.svg';
                            }
                        } elseif ($creatorId === $user2Id) {
                            $creatorChars = $user2Chars;
                            $otherChars   = $user1Chars;
                            $otherName    = !empty($user1['nickname']) ? $user1['nickname'] : '';
                            if ($isCo) {
                                $secondAvatar = !empty($user1['avatar']) ? $user1['avatar'] : '/assets/images/default-avatar.svg';
                            }
                        } else {
                            $creatorChars = $user1Chars;
                            $otherChars   = $user2Chars;
                        }

                        if ($isCo) {
                            $secondName = $otherName;
                            if ($secondName === '') {
                                $secondName = '另一半';
                            }
                            $displayName = $creatorName . ' & ' . $secondName;
                        }

                        $articleCoCreated[$aid]        = $isCo;
                        $articleDisplayAuthors[$aid]   = $displayName;
                        $articleSecondAvatars[$aid]    = $secondAvatar;
                        $articleCreatorCharsMap[$aid]  = $creatorChars;
                        $articleOtherCharsMap[$aid]    = $otherChars;
                        $articleOtherNamesMap[$aid]    = $otherName;
                    }
                }
            } catch (Throwable $e) {
                $articleCoCreated        = [];
                $articleDisplayAuthors   = [];
                $articleSecondAvatars    = [];
                $articleCreatorCharsMap  = [];
                $articleOtherCharsMap    = [];
                $articleOtherNamesMap    = [];
            }
        }
    }
    ?>

    <div class="article-list-large">
        <?php foreach ($articles as $article): ?>
        <?php
            $aid = (int) $article['id'];
            $isCoCreated = !empty($articleCoCreated[$aid]);
            $creatorAvatar = !empty($article['avatar']) ? $article['avatar'] : '/assets/images/default-avatar.svg';
            $displayAuthor = $articleDisplayAuthors[$aid] ?? (!empty($article['nickname']) ? $article['nickname'] : '匿名用户');
            $secondAvatar = $articleSecondAvatars[$aid] ?? '';
            $stackClass = 'album-avatar-stack' . ($isCoCreated && $secondAvatar ? ' album-avatar-stack-co' : '');
            $cardClass = 'article-card-large' . ($isCoCreated ? ' article-card-large-gradient' : '');
        ?>
        <div class="<?php echo $cardClass; ?>">
            <div class="article-card-window">
                <span class="article-card-window-dot red"></span>
                <span class="article-card-window-dot yellow"></span>
                <span class="article-card-window-dot green"></span>
            </div>
            <div class="article-card-header">
                <div class="<?php echo $stackClass; ?>">
                    <img src="<?php echo e($creatorAvatar); ?>" alt="<?php echo e($displayAuthor); ?>" class="album-avatar album-avatar-main">
                    <?php if ($isCoCreated && $secondAvatar): ?>
                        <img src="<?php echo e($secondAvatar); ?>" alt="<?php echo e($displayAuthor); ?>" class="album-avatar-secondary">
                    <?php endif; ?>
                </div>
                <div class="article-card-header-meta article-card-header-meta-with-co">
                    <div>
                        <h3><?php echo e($displayAuthor); ?></h3>
                        <span class="article-card-time"><?php echo formatDate($article['created_at'], 'Y-m-d H:i'); ?></span>
                    </div>
                    <div class="article-card-right-meta">
                        <?php if ($isCoCreated): ?>
                            <span class="album-meta-co">
                                <i class="fas fa-heart"></i>
                                共创
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="article-content">
                <?php if (!empty($article['is_encrypted']) && empty($currentUser)): ?>
                <div class="encrypted-content">
                    <i class="fas fa-lock"></i>
                    <p>当前内容已加密，请登录后查看</p>
                </div>
                <?php else: ?>
                <h4 class="article-card-title"><?php echo e($article['title']); ?></h4>
                <p class="article-card-excerpt"><?php echo mb_substr(strip_tags($article['content']), 0, 140); ?>...</p>
                <?php endif; ?>
            </div>
            <div class="article-footer">
                <a href="/article.php?id=<?php echo $article['id']; ?>" class="btn-view">
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($articles)): ?>
        <div class="glass-card">
            <p>还没有任何动态，快去后台发布第一篇文章吧～</p>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php if (!empty($albums)): ?>
<?php
// 共创标记：判断当前情侣双方是否都参与了相册（创建或上传）
$albumCoCreated = [];
if (!empty($albums)) {
    // 获取情侣双方信息（不依赖登录状态）
    $couple = get_couple_users();
    $user1 = $couple['user1'];
    $user2 = $couple['user2'];
    
    if ($user1 && $user2) {
        try {
            // 确保图片上传者映射表存在
            if ($db instanceof Database) {
                $db->query("
                    CREATE TABLE IF NOT EXISTS `album_image_uploads` (
                        `image_id` int(11) NOT NULL COMMENT '图片ID',
                        `user_id` int(11) NOT NULL COMMENT '上传用户ID',
                        `created_at` datetime NOT NULL COMMENT '记录创建时间',
                        PRIMARY KEY (`image_id`),
                        KEY `user_id` (`user_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='相册图片上传者映射';
                ");
            }

            $albumIds = array_column($albums, 'id');
            $albumIds = array_map('intval', $albumIds);

            if ($albumIds) {
                $placeholders = implode(',', array_fill(0, count($albumIds), '?'));

                // 图片上传者：通过 album_image_uploads 映射表统计
                $rows = $db->fetchAll(
                    "SELECT ai.album_id, au.user_id
                     FROM album_images ai
                     JOIN album_image_uploads au ON au.image_id = ai.id
                     WHERE ai.album_id IN ($placeholders)",
                    $albumIds
                );

                $contributors = [];
                foreach ($rows as $row) {
                    $aid = (int) $row['album_id'];
                    $uid = (int) $row['user_id'];
                    if (!isset($contributors[$aid])) {
                        $contributors[$aid] = [];
                    }
                    if (!in_array($uid, $contributors[$aid], true)) {
                        $contributors[$aid][] = $uid;
                    }
                }

                // 视频上传者：直接使用 album_videos.uploader_id
                try {
                    $rowsVideo = $db->fetchAll(
                        "SELECT album_id, uploader_id AS user_id
                         FROM album_videos
                         WHERE album_id IN ($placeholders) AND uploader_id IS NOT NULL",
                        $albumIds
                    );
                    foreach ($rowsVideo as $row) {
                        $aid = (int) $row['album_id'];
                        $uid = (int) $row['user_id'];
                        if (!isset($contributors[$aid])) {
                            $contributors[$aid] = [];
                        }
                        if ($uid > 0 && !in_array($uid, $contributors[$aid], true)) {
                            $contributors[$aid][] = $uid;
                        }
                    }
                } catch (Throwable $e) {
                    // 忽略视频上传者统计失败，保持已有图片逻辑
                }

                $user1Id = (int) $user1['id'];
                $user2Id = (int) $user2['id'];

                foreach ($albums as $album) {
                    $aid       = (int) $album['id'];
                    $creatorId = isset($album['user_id']) ? (int) $album['user_id'] : 0;
                    $list      = $contributors[$aid] ?? [];

                    $user1Contributed = in_array($user1Id, $list, true);
                    $user2Contributed = in_array($user2Id, $list, true);

                    // 创建者本身也视为"参与"
                    if ($creatorId === $user1Id) {
                        $user1Contributed = true;
                    }
                    if ($creatorId === $user2Id) {
                        $user2Contributed = true;
                    }

                    $albumCoCreated[$aid] = $user1Contributed && $user2Contributed;
                }
            }
        } catch (Throwable $e) {
            $albumCoCreated = [];
        }
    }
}
?>
<section class="albums-section">
    <div class="section-header card-header-row">
        <h2><i class="fas fa-images"></i> 爱情相册</h2>
    </div>
    
    <div class="albums-masonry">
        <?php foreach ($albums as $album): ?>
        <?php
            $aid = (int) $album['id'];
            $isCoCreated = !empty($albumCoCreated[$aid]);

            // 计算双头像：主头像为相册创建者，次头像为另一半（若存在）
            $creatorAvatar   = !empty($album['avatar']) ? $album['avatar'] : '/assets/images/default-avatar.svg';
            $creatorNickname = !empty($album['nickname']) ? $album['nickname'] : '匿名用户';
            $secondAvatar    = '';
            $secondNickname  = '';

            if ($isCoCreated) {
                // 获取情侣双方信息
                $couple = get_couple_users();
                $user1 = $couple['user1'];
                $user2 = $couple['user2'];
                
                if ($user1 && $user2) {
                    $creatorId = isset($album['user_id']) ? (int) $album['user_id'] : 0;
                    $user1Id = (int) $user1['id'];
                    $user2Id = (int) $user2['id'];

                    if ($creatorId === $user1Id) {
                        $secondAvatar   = !empty($user2['avatar']) ? $user2['avatar'] : '/assets/images/default-avatar.svg';
                        $secondNickname = !empty($user2['nickname']) ? $user2['nickname'] : '';
                    } elseif ($creatorId === $user2Id) {
                        $secondAvatar   = !empty($user1['avatar']) ? $user1['avatar'] : '/assets/images/default-avatar.svg';
                        $secondNickname = !empty($user1['nickname']) ? $user1['nickname'] : '';
                    }
                }
            }

            $stackClass = 'album-avatar-stack' . ($isCoCreated && $secondAvatar ? ' album-avatar-stack-co' : '');
        ?>
        <div class="album-card glass-card album-card-grid<?php echo $isCoCreated ? ' album-card-co' : ''; ?>">
            <div class="album-header">
                <div class="<?php echo $stackClass; ?>">
                    <img src="<?php echo e($creatorAvatar); ?>" alt="<?php echo e($creatorNickname); ?>" class="album-avatar album-avatar-main">
                    <?php if ($secondAvatar): ?>
                        <img src="<?php echo e($secondAvatar); ?>" alt="<?php echo e($secondNickname ?: $creatorNickname); ?>" class="album-avatar-secondary">
                    <?php endif; ?>
                </div>
                <div class="album-user-info">
                    <span class="album-nickname">
                        <?php echo (!empty($album['is_encrypted']) && empty($currentUser)) ? '加密相册' : e($album['name']); ?>
                    </span>
                    <div class="album-user-meta">
                        <span class="album-meta-time"><?php echo formatDate($album['created_at'], 'Y-m-d'); ?></span>
                        <?php if ($isCoCreated): ?>
                            <span class="album-meta-co">
                                <i class="fas fa-heart"></i>
                                共创
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="album-grid-preview">
                <?php if (!empty($album['is_encrypted']) && empty($currentUser)): ?>
                    <div class="encrypted-content">
                        <i class="fas fa-lock"></i>
                        <p>当前相册已加密，请登录后查看</p>
                    </div>
                <?php else: ?>
                    <?php
                    // 首页面板相册预览：混排最近的图片缩略图与视频封面缩略图，最多 9 张
                    $previewItems = [];
                    try {
                        $rowsImg = $db->fetchAll(
                            "SELECT COALESCE(thumbnail_path, image_path) AS path, created_at 
                             FROM album_images 
                             WHERE album_id = :id",
                            ['id' => $album['id']]
                        );
                    } catch (Throwable $e) {
                        $rowsImg = [];
                    }

                    foreach ($rowsImg as $row) {
                        if (empty($row['path'])) {
                            continue;
                        }
                        $previewItems[] = [
                            'type'       => 'image',
                            'path'       => $row['path'],
                            'created_at' => $row['created_at'] ?? $album['created_at'],
                        ];
                    }

                    try {
                        $rowsVid = $db->fetchAll(
                            "SELECT poster_path, created_at 
                             FROM album_videos 
                             WHERE album_id = :id",
                            ['id' => $album['id']]
                        );
                    } catch (Throwable $e) {
                        $rowsVid = [];
                    }

                    foreach ($rowsVid as $row) {
                        $posterPath = trim($row['poster_path'] ?? '');
                        if ($posterPath === '') {
                            $finalPath = '/assets/images/Coverloaderror.jpg';
                        } else {
                            $finalPath = $posterPath;
                            $pi = pathinfo($posterPath);
                            if (!empty($pi['dirname']) && !empty($pi['basename'])) {
                                $thumbRelative = rtrim($pi['dirname'], '/\\') . '/thumbs/' . $pi['basename'];
                                $thumbAbs = rtrim(UPLOAD_DIR, '/\\') . '/' . ltrim($thumbRelative, '/\\');
                                if (is_file($thumbAbs)) {
                                    $finalPath = $thumbRelative;
                                }
                            }
                        }

                        $previewItems[] = [
                            'type'       => 'video',
                            'path'       => $finalPath,
                            'created_at' => $row['created_at'] ?? $album['created_at'],
                        ];
                    }

                    if (!empty($previewItems)) {
                        usort($previewItems, function ($a, $b) {
                            $ta = strtotime($a['created_at'] ?? '') ?: 0;
                            $tb = strtotime($b['created_at'] ?? '') ?: 0;
                            if ($ta === $tb) {
                                return 0;
                            }
                            return ($ta > $tb) ? -1 : 1;
                        });
                        $previewItems = array_slice($previewItems, 0, 9);
                    }
                    ?>
                    <?php if (!empty($previewItems)): ?>
                        <?php foreach ($previewItems as $item): ?>
                        <?php
                            $previewPath = $item['path'];
                            $isVideo     = isset($item['type']) && $item['type'] === 'video';
                            if (strpos($previewPath, '/assets/') === 0) {
                                $dataSrc = $previewPath;
                            } else {
                                $dataSrc = upload_url($previewPath);
                            }
                            $dataSrcWebp = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $dataSrc);
                        ?>
                        <div class="album-grid-item<?php echo $isVideo ? ' album-grid-item-video' : ''; ?>">
                            <img src="/assets/images/image-placeholder.svg"
                                 data-src="<?php echo $dataSrc; ?>"
                                 data-src-webp="<?php echo $dataSrcWebp; ?>"
                                 alt="<?php echo e($album['name']); ?>">
                            <div class="photo-loader">
                                <div class="reverse-spinner"></div>
                            </div>
                            <?php if ($isVideo): ?>
                                <div class="album-grid-video-play">
                                    <div class="album-grid-video-play-circle">
                                        <i class="fas fa-play"></i>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="album-grid-empty">
                            <i class="fas fa-images"></i>
                            <span>还没有照片</span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php if (!empty($album['description']) && empty($album['is_encrypted'])): ?>
            <div class="album-description" title="<?php echo e($album['description']); ?>">
                <?php echo e($album['description']); ?>
            </div>
            <?php endif; ?>
            <div class="album-card-footer-row">
                <div class="album-card-footer-left">
                    <div class="album-photo-count">
                        <?php if (!empty($album['is_encrypted']) && empty($currentUser)): ?>
                            <i class="fas fa-lock"></i>
                        <?php else: ?>
                            <i class="fas fa-image"></i>
                            <span><?php echo $album['image_count']; ?> 张照片</span>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="/album.php?id=<?php echo $album['id']; ?>" class="btn-view">
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($latestMessages)): ?>
<section class="content-section home-messages-section">
    <div class="section-header">
        <h2><i class="fas fa-comment-dots"></i> 温馨留言墙</h2>
    </div>
    <div class="withustrm-msg-container" id="withustrm-msg-container" aria-label="留言滚动">
        <div class="withustrm-msg-track">
            <?php foreach ($latestMessages as $msg): ?>
            <?php
            $contentText   = trim(strip_tags($msg['content']));
            $locationText  = isset($msg['location']) && $msg['location'] !== '' ? $msg['location'] : '';
            ?>
            <div class="withustrm-msg-card">
                <div class="withustrm-msg-header">
                    <img class="withustrm-msg-avatar" src="<?php echo e($msg['avatar'] ?: '/assets/images/default-avatar.svg'); ?>" alt="<?php echo e($msg['nickname']); ?>">
                    <div class="withustrm-msg-user-info">
                        <div class="withustrm-msg-name-row">
                            <span class="withustrm-msg-user-name"><?php echo e($msg['nickname']); ?></span>
                        </div>
                        <span class="withustrm-msg-post-time"><?php echo timeAgo($msg['created_at']); ?></span>
                    </div>
                </div>
                <div class="withustrm-msg-content"><?php echo e(mb_substr($contentText, 0, 90)); ?></div>
                <div class="withustrm-msg-divider"></div>
                <div class="withustrm-msg-footer">
                    <span class="withustrm-msg-chip"><i class="fas fa-heart"></i> 来自 withU</span>
                    <?php if ($locationText): ?>
                    <span class="withustrm-msg-loc"><i class="fas fa-location-dot"></i> <?php echo e($locationText); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <a class="withustrm-msg-more" href="/messages.php" aria-label="查看全部寄语">
                <span class="withustrm-msg-more__icon"><i class="fas fa-arrow-right"></i></span>
                <span class="withustrm-msg-more__text">查看全部寄语</span>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<?php
$withuEpilogueQuotes = [
    '爱是把平凡的日子，过成只属于我们的诗。',
    '从相遇到相守，每一天都值得纪念。',
    '山河远阔，人间烟火，无一是你，无一不是你。',
    '愿我们朝暮与年岁并往，然后与你行至天光。',
    '你是藏在时光褶皱里的温柔。',
    '所有美好都恰逢其时。',
    '慢慢来，谁不是翻山越岭去相爱。',
    '一屋两人，三餐四季，把日子过成喜欢的样子。',
    '今天也是被爱意包围的一天。',
    '世界很大，幸而有你相伴。'
];
?>
<section class="withustrm-epilogue" data-withustrm-epilogue data-withustrm-quotes="<?php echo e(json_encode($withuEpilogueQuotes, JSON_UNESCAPED_UNICODE)); ?>" aria-label="结语">
    <div class="withustrm-epilogue__card">
        <div class="withustrm-epilogue__holes" aria-hidden="true">
            <div class="withustrm-epilogue__hole"></div>
            <div class="withustrm-epilogue__hole"></div>
            <div class="withustrm-epilogue__hole"></div>
            <div class="withustrm-epilogue__hole"></div>
            <div class="withustrm-epilogue__hole"></div>
            <div class="withustrm-epilogue__hole"></div>
            <div class="withustrm-epilogue__hole"></div>
            <div class="withustrm-epilogue__hole"></div>
        </div>
        <div class="withustrm-epilogue__header">
            <div class="withustrm-epilogue__subtitle">OUR STORY CONTINUES</div>
            <div class="withustrm-epilogue__title">未完 · 待续</div>
        </div>
        <div class="withustrm-epilogue__quote-container">
            <p class="withustrm-epilogue__quote" aria-live="polite"></p>
        </div>
        <div class="withustrm-epilogue__actions">
            <button type="button" class="withustrm-epilogue__btn" data-epilogue-refresh><i class="fas fa-rotate-right"></i> 换一句</button>
            <button type="button" class="withustrm-epilogue__btn" data-epilogue-copy><i class="fas fa-copy"></i> 复制</button>
        </div>
    </div>
</section>

<script>
(function() {
    var timer = document.getElementById('love-timer');
    if (!timer) return;

    var startStr = timer.getAttribute('data-start');
    if (!startStr) return;

    var normalized = startStr.trim();
    if (/^\d{4}-\d{2}-\d{2}$/.test(normalized)) {
        // 仅日期：按照 00:00:00 处理（兼容旧数据）
        normalized += 'T00:00:00';
    } else {
        // 兼容 "Y-m-d H:i:s" / "Y-m-dTH:i[:s]" 等格式
        normalized = normalized.replace(' ', 'T');
    }

    var startDate = new Date(normalized);
    if (isNaN(startDate.getTime())) return;

    function pad2(n) {
        return n < 10 ? '0' + n : '' + n;
    }

    function render() {
        var now = new Date();
        var diff = now.getTime() - startDate.getTime();
        if (diff < 0) diff = 0;

        var dayMs = 24 * 60 * 60 * 1000;
        var hourMs = 60 * 60 * 1000;
        var minuteMs = 60 * 1000;

        var days = Math.floor(diff / dayMs);
        diff -= days * dayMs;
        var hours = Math.floor(diff / hourMs);
        diff -= hours * hourMs;
        var minutes = Math.floor(diff / minuteMs);
        diff -= minutes * minuteMs;
        var seconds = Math.floor(diff / 1000);

        var daysEl = timer.querySelector('[data-unit="days"]');
        var hoursEl = timer.querySelector('[data-unit="hours"]');
        var minutesEl = timer.querySelector('[data-unit="minutes"]');
        var secondsEl = timer.querySelector('[data-unit="seconds"]');

        if (daysEl) daysEl.textContent = days;
        if (hoursEl) hoursEl.textContent = pad2(hours);
        if (minutesEl) minutesEl.textContent = pad2(minutes);
        if (secondsEl) secondsEl.textContent = pad2(seconds);
    }

    render();
    setInterval(render, 1000);
})();
</script>
