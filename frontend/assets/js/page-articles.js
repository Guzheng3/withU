/**
 * LG_NewUI 点点滴滴页面模块
 * @version 2.0.0
 * @description articles.php 页面的 JS 逻辑（点赞系统）
 * @note 瀑布流和弥散光效果已迁移到 lg-pjax.js 的 MasonryManager 和 AuroraEffect
 */

;(function(window, $) {
    'use strict';

    // ============================================
    // 点赞系统模块
    // ============================================
    const LikeSystem = {
        _storageKey: 'lgnewui_article_likes',
        _toastTimer: null,

        /**
         * 初始化点赞系统
         */
        init() {
            this._render();
            this._bindEvents();
        },

        /**
         * 获取所有点赞记录
         * @returns {Object} 点赞记录对象
         */
        _getLikes() {
            const data = localStorage.getItem(this._storageKey);
            return data ? JSON.parse(data) : {};
        },

        /**
         * 检查是否已点赞
         * @param {string} id - 文章 ID
         * @returns {boolean}
         */
        _isLiked(id) {
            const likes = this._getLikes();
            return !!likes[id];
        },

        /**
         * 保存点赞记录
         * @param {string} id - 文章 ID
         */
        _saveLike(id) {
            const likes = this._getLikes();
            likes[id] = Date.now();
            localStorage.setItem(this._storageKey, JSON.stringify(likes));
        },

        /**
         * 渲染已点赞状态
         */
        _render() {
            const self = this;
            $('.lgnewui-article-like').each(function() {
                const $btn = $(this);
                const id = $btn.attr('data-id');
                if (id && self._isLiked(id)) {
                    $btn.addClass('active');
                }
            });
        },

        /**
         * 显示 Toast 提示
         * @param {string} msg - 提示消息
         */
        _showToast(msg) {
            const $toast = $('#lgnewui-toast');
            $toast.text(msg).addClass('show');

            if (this._toastTimer) clearTimeout(this._toastTimer);
            this._toastTimer = setTimeout(() => {
                $toast.removeClass('show');
            }, 2000);
        },

        /**
         * 绑定点赞事件
         */
        _bindEvents() {
            const self = this;

            // 使用事件委托，支持 PJAX 动态加载
            $(document).off('click.lgLike', '.lgnewui-article-like').on('click.lgLike', '.lgnewui-article-like', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const $btn = $(this);
                const id = $btn.attr('data-id');

                if (!id) {
                    console.warn('[LikeSystem] Like button missing data-id');
                    return;
                }

                // 触发动画
                $btn.removeClass('animating');
                void $btn[0].offsetWidth; // 强制重绘
                $btn.addClass('animating');

                // 清除旧的动画定时器
                if ($btn.data('animTimer')) clearTimeout($btn.data('animTimer'));
                const timer = setTimeout(() => {
                    $btn.removeClass('animating');
                }, 600);
                $btn.data('animTimer', timer);

                // 检查是否已点赞
                if (self._isLiked(id)) {
                    self._showToast('已经点赞过了哦 ~');
                    $btn.addClass('active');
                    return;
                }

                // 保存点赞
                self._saveLike(id);
                $btn.addClass('active');

                // 更新计数
                const $count = $btn.find('span');
                let count = parseInt($btn.attr('data-count') || 0) + 1;
                $btn.attr('data-count', count);
                $count.text(count);

                self._showToast('点赞成功！');
            });
        }
    };

    // ============================================
    // 点点滴滴页面主模块
    // ============================================
    const LittleModule = {
        _initialized: false,

        /**
         * 初始化模块
         */
        init() {
            if (this._initialized) return;

            // 初始化点赞系统
            LikeSystem.init();

            this._initialized = true;
        },

        /**
         * 销毁模块
         */
        destroy() {
            $(document).off('.lgLike');
            this._initialized = false;
        },

        /**
         * 刷新页面状态（PJAX 切换后调用）
         */
        refresh() {
            LikeSystem._render();
        }
    };

    // ============================================
    // 自动初始化
    // ============================================
    $(function() {
        // 检测是否在点点滴滴页面
        if ($('#lgnewui-article-masonry').length > 0) {
            LittleModule.init();

            // 初始化 Masonry 瀑布流
            if (window.LGPjax && window.LGPjax.MasonryManager) {
                window.LGPjax.MasonryManager.initArticleGrid();
            }

            // 初始化弥散光效果
            if (window.LGPjax && window.LGPjax.AuroraEffect) {
                window.LGPjax.AuroraEffect.init();
            }
        }
    });

    // PJAX 完成后重新初始化
    $(document).on('pjax:end.lgLittle', function() {
        if ($('#lgnewui-article-masonry').length > 0) {
            LittleModule.refresh();
        }
    });

    // ============================================
    // 注册到 LGApp
    // ============================================
    if (window.LGApp) {
        window.LGApp.register('little', LittleModule);
    }

    // 暴露到全局
    window.LGLittleModule = LittleModule;

})(window, jQuery);
