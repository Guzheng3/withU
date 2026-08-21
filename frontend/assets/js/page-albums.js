/**
 * withU 相册页面模块
 * @version 2.0.0
 * @description albums.php 和 album-detail.php 页面的 JS 逻辑
 * @note Masonry 瀑布流初始化已迁移到 pjax.js 的 MasonryManager.initLGGrid()
 */

;(function(window, $) {
    'use strict';

    // ============================================
    // 相册模块
    // ============================================
    const LoveImgModule = {
        _initialized: false,

        /**
         * 初始化模块
         */
        init() {
            if (this._initialized) return;

            this._bindEvents();

            this._initialized = true;
        },

        /**
         * 销毁模块
         */
        destroy() {
            $(document).off('.withuLoveImg');
            this._initialized = false;
        },

        /**
         * 绑定事件
         */
        _bindEvents() {
            // 视频卡片点击
            $(document).off('click.withuLoveImg', '.withu-photo-box.is-video').on('click.withuLoveImg', '.withu-photo-box.is-video', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const videoUrl = $(this).data('video-url');
                const videoCover = $(this).data('video-cover');

                if (videoUrl && window.VideoModal) {
                    window.VideoModal.open(videoUrl, videoCover);
                }
            });
        },

        /**
         * 刷新布局
         */
        refresh() {
            // 调用 pjax.js 的 MasonryManager
            if (window.WithUPjax && window.WithUPjax.MasonryManager) {
                window.WithUPjax.MasonryManager.initLGGrid();
            }
            if (window.lazyLoadInstance) {
                window.lazyLoadInstance.update();
            }
        }
    };

    // ============================================
    // 自动初始化
    // ============================================
    $(function() {
        // 检测是否在相册页面
        if ($('.withu-masonry-grid').length > 0) {
            LoveImgModule.init();
        }
    });

    // PJAX 完成后重新初始化
    $(document).on('pjax:end.withuLoveImg', function() {
        if ($('.withu-masonry-grid').length > 0) {
            LoveImgModule.init();
        }
    });

    // ============================================
    // 注册到 WithUApp
    // ============================================
    if (window.WithUApp) {
        window.WithUApp.register('loveImg', LoveImgModule);
    }

    // 暴露到全局
    window.WithULoveImgModule = LoveImgModule;

})(window, jQuery);
