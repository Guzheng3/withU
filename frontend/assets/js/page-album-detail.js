/**
 * imglist 瀑布流模块 — 支持 pjax 重新初始化
 * 懒加载由全局 LazyLoadManager (app.js) 统一管理
 * callback_loaded 中自动添加 loaded class + 调用 relayout()
 */
window.ImglistApp = (function() {
    'use strict';

    let page = 0;
    let loading = false;
    let hasMore = true;
    let msnry = null;
    let wallClickBound = false;
    let grid, loadBtn, loadMoreWrap, loadDone, loadingEl, totalCountEl;
    let CODE = '';
    let ALBUM_NAME = '';
    let cardMinHeight = '';
    let cardMaxHeight = '';

    const API  = 'services/photo-list.php';

    function escHtml(s) {
        if (!s) return '';
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        const parts = dateStr.split('-');
        if (parts.length >= 3) {
            return `${parts[0]}年${parseInt(parts[1])}月${parseInt(parts[2])}日`;
        }
        return dateStr;
    }

    function buildCard(item) {
        const isVideo = item.photo_type === 1;
        const thumbSrc = isVideo
            ? (item.VideoCover || item.photo_thumb || item.photo_url)
            : (item.photo_thumb || item.photo_url);
        const showInfo = item.photo_text || item.photo_byname || item.photo_date;
        const showInfoClass = (showInfo && grid.dataset.showInfo === 'true') ? ' show-info-default' : '';

        const videoAttrs = isVideo
            ? ` data-video-url="${escHtml(item.photo_url)}" data-video-cover="${escHtml(item.VideoCover)}"`
            : '';

        // card 高度限制内联样式
        let cardStyle = '';
        const styleParts = [];
        if (cardMinHeight) styleParts.push('min-height:' + cardMinHeight);
        if (cardMaxHeight) styleParts.push('max-height:' + cardMaxHeight);
        if (styleParts.length) cardStyle = ` style="${styleParts.join(';')}"`;

        const typeTag = isVideo
            ? '<div class="tag type-tag"><i class="ph ph-video-camera"></i> 视频</div>'
            : '<div class="tag type-tag"><i class="ph ph-image"></i> 图文</div>';
        const locationTag = item.photo_location
            ? (item.photo_lng && item.photo_lat && item.photo_lng !== '0' && item.photo_lat !== '0'
                ? `<div class="tag location-tag has-coords" data-lng="${escHtml(item.photo_lng)}" data-lat="${escHtml(item.photo_lat)}"><i class="ph ph-map-pin"></i><span>${escHtml(item.photo_location)}</span></div>`
                : `<div class="tag location-tag"><i class="ph ph-map-pin"></i><span>${escHtml(item.photo_location)}</span></div>`)
            : '';
        const playBtn = isVideo ? '<button class="play-btn"><i class="ph-fill ph-play"></i></button>' : '';
        const infoBtn = showInfo ? '<button class="info-toggle-btn"><i class="ph ph-info"></i></button>' : '';
        const titleHtml = item.photo_text ? `<p class="title">${escHtml(item.photo_text)}</p>` : '';

        // 头像
        const avatarHtml = item.up_avatar
            ? `<img src="${escHtml(item.up_avatar)}" class="avatar" referrerpolicy="no-referrer" onerror="this.style.display='none'">`
            : '';
        // 性别图标 + 昵称
        const genderIcon = item.up_gender
            ? `<i class="ph-bold ${item.up_gender === 'male' ? 'ph-gender-male' : 'ph-gender-female'} gender-icon ${escHtml(item.up_gender)}"></i>`
            : '';
        const nameHtml = item.photo_byname
            ? `<span class="author-name">${genderIcon}${escHtml(item.photo_byname)}</span>`
            : '';

        // 日期：年月日 + 相对时间
        let dateHtml = '';
        if (item.photo_date) {
            const formatted = formatDate(item.photo_date);
            const ago = item.photo_date_ago ? ` · ${escHtml(item.photo_date_ago)}` : '';
            dateHtml = `<span class="date-text">${escHtml(formatted)}${ago}</span>`;
        }

        return `<div class="imglist-masonry-col">
            <div class="card${showInfoClass}"${videoAttrs}${cardStyle} data-original="${escHtml(item.photo_url)}">
                <img class="bg-img lazy"${cardMinHeight ? ` style="min-height:${cardMinHeight}"` : ''} data-src="${escHtml(thumbSrc)}" data-original="${escHtml(item.photo_url)}" alt="">
                <div class="tags-group">${typeTag}${locationTag}</div>
                ${playBtn}
                ${infoBtn}
                <div class="overlay-blur"></div>
                <div class="overlay">
                    ${titleHtml}
                    <div class="footer">
                        <div class="user-info">${avatarHtml}${nameHtml}</div>
                        ${dateHtml}
                    </div>
                </div>
            </div>
        </div>`;
    }

    function setLoadingState(isLoading) {
        if (!loadBtn) return;
        const btnText = loadBtn.querySelector('.btn-text');
        if (isLoading) {
            btnText.innerHTML = '<i class="ph ph-spinner-gap imglist-spin"></i> 加载中...';
            loadBtn.disabled = true;
        } else {
            btnText.innerHTML = '<i class="ph ph-arrow-down"></i> 加载更多';
            loadBtn.disabled = false;
        }
    }

    /** 渲染登录权限墙（相册仅登录可见，photo-list 返回 403 + locked） */
    function renderWall() {
        loading = false;
        if (loadingEl) loadingEl.style.display = 'none';
        if (loadMoreWrap) loadMoreWrap.style.display = 'none';
        if (loadDone) loadDone.style.display = 'none';
        if (totalCountEl) totalCountEl.style.display = 'none';
        if (!grid) return;
        grid.innerHTML = `
            <div style="text-align:center;padding:4rem 1rem 5rem;color:#a58ba0;">
                <i class="ph ph-lock-key" style="font-size:2.4rem;color:#e75480;"></i>
                <p style="margin:1rem 0 .4rem;font-size:1rem;color:#7d6470;">这个相册仅登录后可见</p>
                <p style="margin:0 0 1.4rem;font-size:.82rem;opacity:.8;">登录后即可查看这里的全部照片与视频</p>
                <button type="button" data-withu-wall-login style="padding:.6rem 1.8rem;border:none;border-radius:999px;background:linear-gradient(135deg,#f78fb3,#e75480);color:#fff;font-size:.9rem;cursor:pointer;">登录后查看</button>
            </div>`;
    }

    function loadPage() {
        if (loading || !hasMore) return;
        loading = true;
        page++;

        const isFirstPage = page === 1;
        const loadStart = Date.now();

        if (isFirstPage) {
            loadingEl.style.display = '';
        } else {
            setLoadingState(true);
        }

        $.ajax({
            url: `${API}?code=${encodeURIComponent(CODE)}&page=${page}&per_page=20`,
            dataType: 'json',
            success(res) {
                // 登录权限墙：相册仅登录可见（photo-list 返回 403 + locked，兼容旧 403 文案）
                if (res && (res.locked || res.code === 403)) {
                    renderWall();
                    return;
                }

                if (res.code !== 200 || !res.data) {
                    loading = false;
                    loadingEl.style.display = 'none';
                    setLoadingState(false);
                    return;
                }

                const { data } = res;

                // 非首页：保证至少 600ms spinner 缓冲
                const elapsed = Date.now() - loadStart;
                const bufferDelay = isFirstPage ? 0 : Math.max(0, 600 - elapsed);

                setTimeout(() => {
                    if (isFirstPage && data.counts) {
                        const chipText = totalCountEl.querySelector('.withu-new-photo-head-chip-text');
                        if (chipText) chipText.textContent = `共 ${data.counts.total} 项`;
                        totalCountEl.style.display = '';
                    }

                    // 记录渲染前的最后一个卡片，用于滚动定位
                    const existingCols = grid.querySelectorAll('.imglist-masonry-col');
                    const scrollTarget = !isFirstPage && existingCols.length > 0 ? existingCols[existingCols.length - 1] : null;

                    const temp = document.createElement('div');
                    temp.innerHTML = data.photos.map(item => buildCard(item)).join('');
                    const newItems = [...temp.children];
                    newItems.forEach(el => grid.appendChild(el));

                    if (isFirstPage) {
                        loadingEl.style.display = 'none';
                        if (typeof Masonry !== 'undefined') {
                            msnry = new Masonry(grid, {
                                itemSelector: '.imglist-masonry-col',
                                columnWidth: '.imglist-masonry-col',
                                percentPosition: true
                            });
                        }
                        if (typeof adjustImgMinHeight === 'function') adjustImgMinHeight();
                    } else {
                        if (msnry) msnry.appended(newItems);
                        setLoadingState(false);
                    }

                    // 每张图片加载完成后触发 Masonry 重排
                    newItems.forEach(el => {
                        const img = el.querySelector('.bg-img');
                        if (img) {
                            img.addEventListener('load', () => relayout(), { once: true });
                        }
                    });

                    // 通知全局懒加载扫描新元素
                    if (window.lazyLoadInstance) window.lazyLoadInstance.update();

                    hasMore = data.pagination.has_more;
                    loadMoreWrap.style.display = hasMore ? '' : 'none';
                    loadDone.style.display = hasMore ? 'none' : '';
                    loading = false;

                    // 非首页 → 自动滚动到新内容区域（留 80px 顶部空间）
                    if (!isFirstPage && scrollTarget) {
                        const nextEl = scrollTarget.nextElementSibling;
                        if (nextEl) {
                            setTimeout(() => {
                                const rect = nextEl.getBoundingClientRect();
                                const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                                window.scrollTo({ top: scrollTop + rect.top - 80, behavior: 'smooth' });
                            }, 100);
                        }
                    }
                }, bufferDelay);
            },
            error() {
                loading = false;
                loadingEl.style.display = 'none';
                setLoadingState(false);
            }
        });
    }

    function handleGridClick(e) {
        // 定位标签点击：打开地图并自动展开相册照片
        const locTag = e.target.closest('.location-tag.has-coords');
        if (locTag) {
            e.stopPropagation();
            const lng = parseFloat(locTag.dataset.lng);
            const lat = parseFloat(locTag.dataset.lat);
            if (!isNaN(lng) && !isNaN(lat) && window.WithUMap) {
                WithUMap.open({ mode: 'albums', coords: [lng, lat], zoom: 18, albumCode: CODE, albumName: ALBUM_NAME });
            }
            return;
        }

        const card = e.target.closest('.card');
        if (!card) return;

        // info 按钮：toggle overlay 显示
        if (e.target.closest('.info-toggle-btn')) {
            e.stopPropagation();
            // 先收起其他所有卡片
            grid.querySelectorAll('.card.show-info-default').forEach(c => {
                if (c !== card) c.classList.remove('show-info-default');
            });
            card.classList.toggle('show-info-default');
            return;
        }

        // 移动端：overlay 已展开时，点击卡片任意位置收起（非播放按钮）
        if (card.classList.contains('show-info-default') && !e.target.closest('.play-btn')) {
            const isMobile = window.matchMedia('(max-width: 768px)').matches;
            if (isMobile) {
                e.stopPropagation();
                card.classList.remove('show-info-default');
                return;
            }
        }

        // 视频播放按钮
        if (e.target.closest('.play-btn')) {
            e.stopPropagation();
            const videoUrl = card.getAttribute('data-video-url');
            const videoCover = card.getAttribute('data-video-cover');
            if (videoUrl && window.VideoModal) window.VideoModal.open(videoUrl, videoCover);
            return;
        }

        // 非视频卡片 → 打开灯箱预览原图
        if (!card.hasAttribute('data-video-url')) {
            const allImgs = [...grid.querySelectorAll('.card:not([data-video-url]) .bg-img')];
            const urls = allImgs.map(img => img.getAttribute('data-original') || img.getAttribute('data-src') || img.src);
            const clickedImg = card.querySelector('.bg-img');
            const currentUrl = clickedImg ? (clickedImg.getAttribute('data-original') || clickedImg.getAttribute('data-src') || clickedImg.src) : '';
            if (window.ViewImage && urls.length) {
                ViewImage.display(urls, currentUrl);
            }
        }
    }

    /** Masonry 重排（由全局 LazyLoad callback_loaded 调用） */
    function relayout() {
        if (msnry) {
            msnry.layout();
        }
    }

    function init() {
        grid = document.getElementById('imglist-grid');
        if (!grid) return;

        // 权限墙登录按钮：捕获阶段原生跳转，绕过 pjax 的链接拦截
        if (!wallClickBound) {
            wallClickBound = true;
            document.addEventListener('click', (e) => {
                const btn = e.target && e.target.closest ? e.target.closest('[data-withu-wall-login]') : null;
                if (!btn) return;
                e.preventDefault();
                e.stopPropagation();
                const back = encodeURIComponent(window.location.pathname + window.location.search);
                window.location.href = 'login.php?redirect=' + back;
            }, true);
        }

        CODE = grid.dataset.code || '';
        ALBUM_NAME = grid.dataset.albumName || '';

        // 根据屏幕宽度读取对应的卡片高度限制
        const isMobile = window.matchMedia('(max-width: 768px)').matches;
        const prefix = isMobile ? 'mobile' : 'pc';
        const minH = grid.dataset[prefix + 'MinHeight'];
        const maxH = grid.dataset[prefix + 'MaxHeight'];
        cardMinHeight = minH ? minH + 'px' : '';
        cardMaxHeight = maxH ? maxH + 'px' : '';

        loadBtn = document.getElementById('imglist-load-btn');
        loadMoreWrap = document.getElementById('imglist-load-more');
        loadDone = document.getElementById('imglist-load-done');
        loadingEl = document.getElementById('imglist-loading');
        totalCountEl = document.getElementById('imglist-total-count');

        page = 0; loading = false; hasMore = true;
        if (msnry) { msnry.destroy(); msnry = null; }
        grid.innerHTML = '';
        loadMoreWrap.style.display = 'none';
        loadDone.style.display = 'none';

        loadBtn.addEventListener('click', loadPage);
        grid.addEventListener('click', handleGridClick);
        loadPage();
    }

    function destroy() {
        if (msnry) { msnry.destroy(); msnry = null; }
        page = 0; loading = false; hasMore = true;
    }

    return { init, destroy, relayout };
})();

// 首次加载（非 pjax）时自动初始化
$(function() {
    if (document.getElementById('imglist-grid')) {
        ImglistApp.init();
    }
});
