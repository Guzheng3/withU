/**
 * 全局视频弹窗播放器
 * 使用方式：给元素添加 data-video-url 和 data-video-cover 属性
 * 例如：<div data-video-url="xxx.mp4" data-video-cover="xxx.jpg"></div>
 *
 * 两步动画：
 *   1) 点击 → 遮罩淡入 + 居中 loading（内容面板不可见）
 *   2) loadedmetadata → 确定横/竖屏 → 内容面板以正确尺寸弹入 → loading 淡出
 *   保底：3 秒超时后按横屏 16:9 强制显示
 */
(function() {
    var dp = null;
    var modal = null;
    var modalContent = null;
    var loadingEl = null;
    var _isOpen = false;
    var _isClosing = false;
    var _shown = false;      // 内容面板是否已弹入
    var _fallbackTimer = null;
    var _loadingStartTime = 0;
    var _minLoadingMs = 700;  // loading 最少显示时长

    function createModal() {
        if (document.getElementById('videoModal')) return;

        var html =
            '<div id="videoModal" class="video-modal">' +
                // loading 挂在遮罩层，内容面板出现前可见
                '<div class="video-modal-loading" id="videoModalLoading">' +
                    '<i class="ph ph-spinner-gap video-modal-spinner"></i>' +
                    '<span>视频加载中…</span>' +
                '</div>' +
                '<div class="video-modal-content">' +
                    '<button class="video-modal-close">' +
                        '<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none">' +
                            '<path d="M18 6L6 18M6 6l12 12"/>' +
                        '</svg>' +
                    '</button>' +
                    '<div class="video-modal-player">' +
                        '<div id="dplayer"></div>' +
                    '</div>' +
                '</div>' +
            '</div>';
        document.body.insertAdjacentHTML('beforeend', html);
    }

    // 内容面板弹入（方向已确定，不会跳）
    function showContent(isPortrait) {
        if (_shown || !modal || !_isOpen) return;
        _shown = true;

        if (_fallbackTimer) { clearTimeout(_fallbackTimer); _fallbackTimer = null; }

        // 先设方向（此时面板还不可见，不会跳）
        if (isPortrait && modalContent) {
            modalContent.classList.add('vm-portrait');
        }

        // 计算 loading 已显示多久，不足最少时长则延迟
        var elapsed = Date.now() - _loadingStartTime;
        var delay = Math.max(0, _minLoadingMs - elapsed);

        setTimeout(function() {
            if (!_isOpen) return;
            // 先淡出 loading
            if (loadingEl) loadingEl.classList.add('hidden');
            // 等 loading 淡出动画完成（200ms）后再弹入内容面板
            setTimeout(function() {
                if (!_isOpen) return;
                if (modalContent) modalContent.classList.add('vm-show');
            }, 220);
        }, delay);
    }

    function openVideo(url, cover) {
        if (_isOpen || _isClosing) return;

        createModal();

        modal = document.getElementById('videoModal');
        modalContent = modal.querySelector('.video-modal-content');
        loadingEl = document.getElementById('videoModalLoading');
        _shown = false;

        // 重置状态
        modal.classList.remove('active', 'closing');
        modalContent.classList.remove('vm-portrait', 'vm-show');
        modalContent.style.removeProperty('--vm-aspect');
        if (loadingEl) loadingEl.classList.remove('hidden');
        if (_fallbackTimer) { clearTimeout(_fallbackTimer); _fallbackTimer = null; }

        // 销毁旧播放器
        if (dp) {
            try { dp.destroy(); } catch(e) {}
            dp = null;
        }

        // 步骤 1：遮罩淡入 + loading 显示
        modal.style.display = 'block';
        _isOpen = true;
        _loadingStartTime = Date.now();
        if (window.lgScrollLock) lgScrollLock();

        requestAnimationFrame(function() {
            modal.classList.add('active');
        });

        // 初始化 DPlayer（面板不可见，不影响视觉）
        dp = new DPlayer({
            autoplay: true,
            theme: "#6e8bff",
            container: document.getElementById('dplayer'),
            video: { url: url, pic: cover }
        });

        // 步骤 2：元数据就绪 → 确定方向 → 动态比例 → 面板弹入
        dp.on('loadedmetadata', function() {
            var vw = dp.video.videoWidth;
            var vh = dp.video.videoHeight;
            // 动态设置容器宽高比，精确匹配视频，消除黑块
            if (vw > 0 && vh > 0 && modalContent) {
                modalContent.style.setProperty('--vm-aspect', (vh / vw * 100).toFixed(4) + '%');
            }
            showContent(vh > vw);
        });

        // 保底 3 秒超时：按横屏强制显示
        _fallbackTimer = setTimeout(function() {
            _fallbackTimer = null;
            showContent(false);
        }, 3000);
    }

    function closeVideo() {
        if (!modal || !_isOpen || _isClosing) return;
        _isClosing = true;

        if (_fallbackTimer) { clearTimeout(_fallbackTimer); _fallbackTimer = null; }

        modal.classList.remove('active');
        modalContent.classList.remove('vm-show');
        modal.classList.add('closing');
        if (dp) dp.pause();

        setTimeout(function() {
            modal.style.display = 'none';
            modal.classList.remove('closing');
            if (window.lgScrollUnlock) lgScrollUnlock();
            if (dp) {
                try { dp.destroy(); } catch(e) {}
                dp = null;
            }
            _isOpen = false;
            _isClosing = false;
            _shown = false;
        }, 250);
    }

    function init() {
        createModal();

        document.addEventListener('click', function(e) {
            var trigger = e.target.closest('[data-video-url]');
            if (trigger) {
                e.preventDefault();
                e.stopPropagation();
                openVideo(
                    trigger.getAttribute('data-video-url'),
                    trigger.getAttribute('data-video-cover') || ''
                );
            }
            if (e.target.closest('.video-modal-close')) closeVideo();
            if (e.target.classList.contains('video-modal')) closeVideo();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && _isOpen) closeVideo();
        });
    }

    window.VideoModal = { open: openVideo, close: closeVideo, init: init };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
