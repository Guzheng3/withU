/**
 * LG_NewUI PJAX 管理模块
 * @version 2.0.0
 * @description 统一管理 PJAX 配置、生命周期事件、页面组件初始化
 * @requires jQuery, jquery.pjax, LGApp
 */

; (function (window, $) {
    'use strict';

    // ============================================
    // 依赖检查
    // ============================================
    if (typeof $ === 'undefined') {
        console.error('[LGPjax] jQuery is required');
        return;
    }

    // 获取 LGApp 模块引用
    const LGApp = window.LGApp || {};
    const LGConfig = window.LG_CONFIG || {};
    const { TimerManager, LazyLoadManager, HeightManager, CommonFunctions, AOSManager, Toast } = LGApp;
    const imageErrorFallback = LGConfig.imageErrorFallback || ((LGConfig.assetBase || '') + '/Style/img/file-placeholder.svg');

    // ============================================
    // Loading 指示器模块
    // ============================================
    const LoadingIndicator = {
        _isVisible: false,

        // Loading HTML 模板 - 固定点位追逐效果
        _template: `
            <div class="pjax-loader-overlay">
                <div class="pjax-loader-content">
                    <div class="pjax-loader-grid">
                        <div class="pjax-dot" style="--r:0deg;--i:12;"></div>
                        <div class="pjax-dot" style="--r:30deg;--i:11;"></div>
                        <div class="pjax-dot" style="--r:60deg;--i:10;"></div>
                        <div class="pjax-dot" style="--r:90deg;--i:9;"></div>
                        <div class="pjax-dot" style="--r:120deg;--i:8;"></div>
                        <div class="pjax-dot" style="--r:150deg;--i:7;"></div>
                        <div class="pjax-dot" style="--r:180deg;--i:6;"></div>
                        <div class="pjax-dot" style="--r:210deg;--i:5;"></div>
                        <div class="pjax-dot" style="--r:240deg;--i:4;"></div>
                        <div class="pjax-dot" style="--r:270deg;--i:3;"></div>
                        <div class="pjax-dot" style="--r:300deg;--i:2;"></div>
                        <div class="pjax-dot" style="--r:330deg;--i:1;"></div>
                    </div>
                    <div class="pjax-loader-label">Loading</div>
                </div>
            </div>
        `,

        /**
         * 显示 Loading 指示器
         */
        show() {
            if (this._isVisible) return;

            $('#pjax-container').addClass('pjax-loading');
            if (!$('body').find('.pjax-loader-overlay').length) {
                $('body').append(this._template);
            }
            this._isVisible = true;
        },

        /**
         * 隐藏 Loading 指示器
         */
        hide() {
            $('#pjax-container').removeClass('pjax-loading');
            $('.pjax-loader-overlay').remove();
            this._isVisible = false;
        }
    };

    // ============================================
    // Masonry 瀑布流管理模块
    // ============================================
    const MasonryManager = {
        // 追踪所有 Masonry 实例，便于 PJAX 切换时销毁
        _instances: [],

        /**
         * 创建并追踪 Masonry 实例
         */
        _create(element, options) {
            if (!element || typeof Masonry === 'undefined') return null;
            const instance = new Masonry(element, options);
            this._instances.push(instance);
            return instance;
        },

        /**
         * 销毁所有 Masonry 实例（PJAX 切换时调用）
         */
        destroyAll() {
            this._instances.forEach(instance => {
                try { instance.destroy(); } catch (e) { /* DOM 可能已被 PJAX 移除 */ }
            });
            this._instances = [];
        },

        /**
         * 初始化相册列表页 Masonry
         */
        initAlbumGrid() {
            const albumGrid = document.querySelector('.masonry-grid');
            if (albumGrid && typeof Masonry !== 'undefined') {
                this._create(albumGrid, {
                    itemSelector: '.album-col',
                    percentPosition: true
                });

                // Masonry 布局完成后刷新 AOS，确保触发点计算正确
                if (typeof AOS !== 'undefined') {
                    AOS.refresh();
                }
            }
        },

        /**
         * 初始化 LG 相册页 Masonry
         */
        initLGGrid() {
            const $grid = $('.lg-masonry-grid');
            if ($grid.length === 0 || typeof Masonry === 'undefined') return;

            // 添加 loading 状态，防止高度塌陷导致 pjax 滚动失焦
            $grid.css('min-height', '100vh');

            const initMasonry = () => {
                this._create($grid[0], {
                    itemSelector: '.lg-masonry-col',
                    percentPosition: true
                });
                $grid.css('min-height', '');

                // Masonry 布局完成后刷新 AOS，确保触发点计算正确
                if (typeof AOS !== 'undefined') {
                    AOS.refresh();
                }
            };

            if (typeof $.fn.imagesLoaded === 'function') {
                $grid.imagesLoaded(initMasonry);
            } else {
                TimerManager.setTimeout('lgMasonry', initMasonry, 100);
            }
        },

        /**
         * 初始化点点滴滴页面瀑布流
         */
        initArticleGrid() {
            const $articleGrid = $('#lgnewui-article-masonry');
            if ($articleGrid.length === 0 || typeof Masonry === 'undefined') return;

            // 添加 loading 状态，防止底部闪烁
            $articleGrid.css('min-height', '600px');

            const initMasonry = () => {
                this._create($articleGrid[0], {
                    itemSelector: '.lgnewui-article-masonry-item',
                    percentPosition: true,
                    horizontalOrder: true
                });
                $articleGrid.css('min-height', '');

                // Masonry 布局完成后刷新 AOS，确保触发点计算正确
                if (typeof AOS !== 'undefined') {
                    AOS.refresh();
                }
            };

            if (typeof $.fn.imagesLoaded === 'function') {
                $articleGrid.imagesLoaded(initMasonry);
            } else {
                TimerManager.setTimeout('articleMasonry', initMasonry, 100);
            }
        },

        /**
         * 初始化所有 Masonry 布局
         */
        initAll() {
            this.initAlbumGrid();
            this.initLGGrid();
            this.initArticleGrid();
        }
    };

    // ============================================
    // 懒加载管理模块（PJAX 专用）
    // ============================================
    const PjaxLazyLoad = {
        _selector: '.photo_style, .aiv_touxiang, .MessageCard img, .Item_OwO img, .leav_card img, .sm-x-block img, .CarouselImage, .ConventionPhoto img, .avatarArea .avatarFrame, .grid-gallery img, .lazy',

        /**
         * 重置并初始化懒加载
         */
        init() {
            // 销毁旧实例
            if (window.lazyLoadInstance) {
                window.lazyLoadInstance.destroy();
            }

            // 重置 pjax-container 内的懒加载图片状态
            document.querySelectorAll('#pjax-container [data-src]').forEach(el => {
                el.removeAttribute('data-ll-status');
                el.classList.remove('loaded', 'entered', 'error', 'applied');
            });

            // 重新初始化
            if (typeof LazyLoad !== 'undefined') {
                window.lazyLoadInstance = new LazyLoad({
                    threshold: 200,
                    elements_selector: this._selector,
                    callback_loaded: () => {
                        // 图片加载完成后触发 Masonry 重新布局
                        MasonryManager.initAlbumGrid();
                    }
                });
            }
        }
    };

    // ============================================
    // ScrollReveal 动画管理模块
    // ============================================
    const ScrollRevealManager = {
        _instance: null,
        _config: {
            distance: '80px',
            origin: 'bottom',
            opacity: 0,
            duration: 1000,
            scale: 0.9,
            easing: 'cubic-bezier(0.5, 0, 0, 1)',
            mobile: true,
            reset: false,
            viewFactor: 0.2
        },

        /**
         * 初始化 ScrollReveal
         */
        init() {
            if (typeof ScrollReveal === 'undefined') return;

            // 创建或复用实例
            if (!this._instance) {
                this._instance = ScrollReveal(this._config);
                window.srInstance = this._instance;
            }

            // 延迟执行 reveal
            TimerManager.setTimeout('scrollReveal', () => {
                this._instance.reveal('[data-sr]', { interval: 100 });
            }, 50);
        },

        /**
         * 仅处理新元素（避免重复初始化）
         */
        revealNewElements() {
            if (!this._instance) return;

            const newElements = document.querySelectorAll('[data-sr]:not([data-sr-init])');
            if (newElements.length > 0) {
                newElements.forEach(el => el.setAttribute('data-sr-init', 'true'));
                this._instance.reveal(newElements, { interval: 100 });
            }
        }
    };

    // ============================================
    // 轮播图管理模块
    // ============================================
    const CarouselManager = {
        // 追踪由本模块创建的轮播实例
        _instances: [],

        /**
         * 初始化 Fancybox 轮播图
         * 注意：lovelist.php 页面的轮播由 page-lovelist.js 单独处理
         */
        init() {
            if (typeof Carousel === 'undefined') return;

            const containers = document.getElementsByClassName('ConventionPhoto');
            Array.from(containers).forEach(container => {
                // 跳过已初始化的轮播
                if (container.Carousel || container.carouselInstance || container.dataset.carouselInit) return;

                // 跳过 list 页面的轮播（由 page-list.js 处理）
                if (container.closest('#list_container') || container.closest('.query_data')) return;

                try {
                    const instance = new Carousel(container, {
                        Dots: false
                    });
                    this._instances.push(instance);
                } catch (e) {
                    console.warn('[CarouselManager] Init error:', e);
                }
            });
        },

        /**
         * 销毁所有轮播实例（PJAX 切换时调用）
         */
        destroyAll() {
            this._instances.forEach(instance => {
                try {
                    if (instance && typeof instance.destroy === 'function') {
                        instance.destroy();
                    }
                } catch (e) { /* DOM 可能已被 PJAX 移除 */ }
            });
            this._instances = [];
        }
    };

    // ============================================
    // 视频播放器管理模块
    // ============================================
    const VideoPlayerManager = {
        _instance: null,

        /**
         * 初始化 Plyr 播放器
         */
        init() {
            if (typeof Plyr === 'undefined') return;

            const videoEl = document.getElementById('LGNewUiPlayerVideo');
            if (!videoEl) return;

            this._instance = new Plyr('#LGNewUiPlayerVideo', {
                i18n: {
                    speed: '速度',
                    normal: '正常'
                },
                keyboard: {
                    global: true
                },
                seekTime: 2,
                controls: [
                    'play-large',
                    'play',
                    'mute',
                    'volume',
                    'progress',
                    'current-time',
                    'settings',
                    'fullscreen'
                ]
            });
        },

        /**
         * 销毁播放器
         */
        destroy() {
            if (this._instance) {
                this._instance.destroy();
                this._instance = null;
            }
        }
    };

    // ============================================
    // 极验验证管理模块
    // ============================================
    const GeetestManager = {
        /**
         * 初始化留言页极验（当极验未加载时直接提交，不影响留言功能）
         */
        initLeaving() {
            const siteTitle = LGConfig.title || '';
            const geetestAvailable = typeof GeetestHelper !== 'undefined';

            if (geetestAvailable) {
                GeetestHelper.reinit({
                    toast: {
                        success: (msg) => Toast.success(msg, siteTitle),
                        error: (msg) => Toast.error(msg, siteTitle),
                        warning: (msg) => Toast.warning(msg, siteTitle)
                    },
                    onClose: () => {
                        $('#leavingPost').removeAttr('disabled').text('提交留言');
                    },
                    onSuccess: (result) => {
                        if (typeof submitMessage === 'function') {
                            submitMessage(result);
                        }
                    }
                });
            }

            $('#leavingPost').off('click.lgGeetest').on('click.lgGeetest', function () {
                const qq = $("input[name='qq']").val();
                const name = $("input[name='name']").val();
                const text = $("textarea[name='text']").val();

                if (!qq || !name || !text) {
                    Toast.warning('留言提交失败 表单输入不完整！', siteTitle);
                    return false;
                }

                if (geetestAvailable) {
                    $('#leavingPost').text('请完成验证...').attr('disabled', 'disabled');
                    GeetestHelper.show();
                } else {
                    if (typeof submitMessage === 'function') {
                        submitMessage({});
                    }
                }
            });
        },

        /**
         * PJAX 完成后检查并初始化极验
         */
        initOnPjax() {
            if ($('#leavingPost').length > 0) {
                this.initLeaving();
            }
        }
    };

    // ============================================
    // 点点滴滴页面弥散光效果
    // ============================================
    const AuroraEffect = {
        _colors: [
            'rgba(253, 186, 116, 0.25)',
            'rgba(252, 165, 165, 0.25)',
            'rgba(147, 197, 253, 0.25)',
            'rgba(167, 243, 208, 0.25)',
            'rgba(216, 180, 254, 0.25)'
        ],

        /**
         * 初始化弥散光效果
         */
        init() {
            const cards = document.querySelectorAll('.lgnewui-article-card-base');
            if (cards.length === 0) return;

            const rand = (min, max) => Math.floor(Math.random() * (max - min + 1)) + min;
            const randColor = () => this._colors[Math.floor(Math.random() * this._colors.length)];

            cards.forEach(card => {
                card.style.setProperty('--aurora-1-x', rand(-40, 0) + '%');
                card.style.setProperty('--aurora-1-y', rand(-40, 0) + '%');
                card.style.setProperty('--aurora-1-w', rand(60, 90) + '%');
                card.style.setProperty('--aurora-1-h', rand(60, 90) + '%');
                card.style.setProperty('--aurora-1-color', randColor());
                card.style.setProperty('--aurora-2-x', rand(-40, 0) + '%');
                card.style.setProperty('--aurora-2-y', rand(-40, 0) + '%');
                card.style.setProperty('--aurora-2-w', rand(50, 80) + '%');
                card.style.setProperty('--aurora-2-h', rand(50, 80) + '%');
                card.style.setProperty('--aurora-2-color', randColor());
            });
        }
    };

    // ============================================
    // 页面组件统一初始化
    // ============================================
    const PageComponents = {
        /**
         * 初始化所有页面组件
         */
        initAll() {
            // Masonry 瀑布流
            MasonryManager.initAll();

            // 懒加载
            PjaxLazyLoad.init();

            // ViewImage 图片预览
            if (CommonFunctions && CommonFunctions.initViewImage) {
                CommonFunctions.initViewImage();
            } else if (window.ViewImage) {
                ViewImage.init('.grid-gallery img, .ConventionPhoto img, .lg-media img, #md-view img, .leav_card .aiv_qq img, img.photo_style');
            }

            // ScrollReveal 动画
            ScrollRevealManager.revealNewElements();

            // 剪贴板声明式绑定
            if (window.LGClipboard) LGClipboard.init();
        }
    };

    // ============================================
    // 滚动位置管理
    // ============================================
    const ScrollPositionManager = {
        /**
         * 滚动到页面描述区域
         */
        scrollToPageHeader() {
            const pageHeader = document.querySelector('.lgnewui-page-header');
            if (!pageHeader) return;

            // 先滚动到顶部，确保计算准确
            window.scrollTo(0, 0);

            // 使用 requestAnimationFrame 确保 DOM 已更新
            requestAnimationFrame(() => {
                const headerHeight = 72;
                // 使用 offsetTop 获取元素相对于 offsetParent 的位置
                // 递归计算到 body 的绝对位置
                let offsetTop = 0;
                let element = pageHeader;
                while (element) {
                    offsetTop += element.offsetTop;
                    element = element.offsetParent;
                }
                const targetTop = Math.max(0, offsetTop - headerHeight - 60);
                window.scrollTo({ top: targetTop, behavior: 'instant' });
            });
        },

        /**
         * 恢复滚动位置
         */
        restoreScrollPosition() {
            const restoreScroll = sessionStorage.getItem('restoreScroll');
            if (restoreScroll) {
                TimerManager.setTimeout('restoreScroll', () => {
                    window.scrollTo(0, parseInt(restoreScroll, 10));
                    sessionStorage.removeItem('restoreScroll');
                }, 200);
            }
        }
    };

    // ============================================
    // PJAX 核心管理器
    // ============================================
    const LGPjax = {
        // 版本号
        version: '2.0.0',

        // 暴露子模块
        LoadingIndicator,
        MasonryManager,
        PjaxLazyLoad,
        ScrollRevealManager,
        CarouselManager,
        VideoPlayerManager,
        GeetestManager,
        AuroraEffect,
        PageComponents,
        ScrollPositionManager,

        /**
         * 初始化 PJAX
         */
        init() {
            // 配置 PJAX
            $(document).pjax('a[target!=_blank]', '#pjax-container', {
                fragment: '#pjax-container',
                timeout: 15000,
                scrollTo: false
            });

            // 绑定生命周期事件
            this._bindEvents();

            // 全局卡片跳转事件委托（data-href 属性）
            this._bindCardNavigation();
        },

        /**
         * 绑定卡片跳转事件（全局委托，排除点赞按钮）
         */
        _bindCardNavigation() {
            $(document).off('click.lgCardNav').on('click.lgCardNav', '[data-href]', function(e) {
                // 点赞按钮不触发跳转
                if ($(e.target).closest('[data-like-target]').length) return;

                const href = $(this).attr('data-href');
                if (!href) return;

                e.preventDefault();
                e.stopPropagation();

                // 模拟 a 标签点击（触发 PJAX）
                const a = document.createElement('a');
                a.href = href;
                a.style.display = 'none';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            });
        },

        /**
         * 绑定 PJAX 生命周期事件
         */
        _bindEvents() {
            // ========== pjax:start ==========
            $(document).on('pjax:start.lgPjax', () => {
                $('html').css('scroll-behavior', 'auto');
                LoadingIndicator.show();

                // ---- 统一清理：防止内存泄漏 ----
                // 0. 强制释放滚动锁（弹窗打开时切页会导致 lg-scroll-locked 残留）
                if (window.lgScrollReset) lgScrollReset();

                // 1. 销毁 Masonry 实例
                MasonryManager.destroyAll();

                // 1.5 销毁 imglist 瀑布流
                if (window.ImglistApp) ImglistApp.destroy();

                // 2. 销毁轮播实例（lg-pjax 管理的）
                CarouselManager.destroyAll();

                // 3. 销毁视频播放器
                VideoPlayerManager.destroy();

                // 4. 销毁页面模块（首页倒计时/天气定时器、详情页 Observer 等）
                if (window.LGIndexModule && typeof window.LGIndexModule.destroy === 'function') {
                    window.LGIndexModule.destroy();
                }
                if (window.LGListModule && typeof window.LGListModule.destroy === 'function') {
                    window.LGListModule.destroy();
                }
                if (window.LGPageDetailModule && typeof window.LGPageDetailModule.destroy === 'function') {
                    window.LGPageDetailModule.destroy();
                }
                if (window.LGLeavingModule && typeof window.LGLeavingModule.destroy === 'function') {
                    window.LGLeavingModule.destroy();
                }
                if (window.LGChatModule && typeof window.LGChatModule.destroy === 'function') {
                    window.LGChatModule.destroy();
                }
                if (window.LGMap) {
                    try {
                        if (typeof window.LGMap.close === 'function') {
                            window.LGMap.close();
                        }
                        if (typeof window.LGMap.destroy === 'function') {
                            window.LGMap.destroy();
                        }
                    } catch (e) { /* PJAX 切页时地图 DOM 可能处于销毁中 */ }
                }
                if (window.LGMiniMap) {
                    try {
                        if (typeof window.LGMiniMap.closeFullscreen === 'function') {
                            window.LGMiniMap.closeFullscreen();
                        }
                        if (typeof window.LGMiniMap.destroyAll === 'function') {
                            window.LGMiniMap.destroyAll();
                        }
                    } catch (e) { /* 迷你地图实例可能已被移除 */ }
                }

                // 5. 销毁懒加载实例（避免旧实例引用已移除的 DOM）
                if (window.lazyLoadInstance && typeof window.lazyLoadInstance.destroy === 'function') {
                    window.lazyLoadInstance.destroy();
                    window.lazyLoadInstance = null;
                }
            });

            // ========== pjax:send ==========
            $(document).on('pjax:send.lgPjax', () => {
                if (typeof NProgress !== 'undefined') {
                    NProgress.start();
                }
                // 导航前保存当前页面的真实滚动位置
                // 仅在非子页面时保存，防止从子页面返回时覆盖父页面信息
                const curPage = window.location.pathname.split('/').pop() || '';
                if (curPage !== 'page.php' && curPage !== 'album-detail.php') {
                    sessionStorage.setItem('previousPage', window.location.href);
                    sessionStorage.setItem('previousScroll', String(window.scrollY));
                }
            });

            // ========== pjax:complete ==========
            $(document).on('pjax:complete.lgPjax', () => {
                $('html').css('scroll-behavior', 'smooth');

                // 如果是 lovelist.php#event-xxx 或 messages.php#comment_xxx 跳转，跳过通用滚动
                const hash = window.location.hash;
                const isListJump = $('#list_container').length > 0 && hash.startsWith('#event-');
                const isLeavingJump = ($('.Message_Wrap').length > 0 || $('#leavingPost').length > 0) && hash.startsWith('#comment_');
                const isScrollRestore = sessionStorage.getItem('restoreScroll') !== null;
                if (!isListJump && !isLeavingJump && !isScrollRestore) {
                    ScrollPositionManager.scrollToPageHeader();
                }

                // 隐藏 Loading
                LoadingIndicator.hide();

                // 重新初始化 AOS 动画（PJAX 加载新内容后需要重新初始化）
                if (typeof AOS !== 'undefined') {
                    // 先刷新，让 AOS 识别新元素
                    AOS.refreshHard();
                }

                // 渲染 Lucide 图标（PJAX 加载新内容后需要重新初始化）
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }

                // 重新初始化交互模块（点赞/浏览量）
                if (typeof LGInteraction !== 'undefined') {
                    LGInteraction.reinit();
                }

                // 动态加载留言页专用 CSS（PJAX 不会加载新页面 <head> 中的样式）
                if ($('#lgmsgCardGrid').length > 0) {
                    const cssId = 'lgmsg-dynamic-css';
                    if (!document.getElementById(cssId)) {
                        const link = document.createElement('link');
                        link.id = cssId;
                        link.rel = 'stylesheet';
                        link.href = ((window.LG_CONFIG && window.LG_CONFIG.assetBase) || '') + '/Style/css/lg-message.css';
                        document.head.appendChild(link);
                    }
                }

                // 初始化页面标题动画（lgnewui-page-header）
                if (typeof initPageHeaderAnimation === 'function') {
                    initPageHeaderAnimation();
                }

                // 触发 LGApp 的 CommonFunctions 初始化
                if (CommonFunctions && CommonFunctions.initPageHeader) {
                    CommonFunctions.initPageHeader();
                }

                // 动态加载 WaveSurfer（时间轴页面需要）
                if ($('.timeline-container').length > 0 && typeof WaveSurfer === 'undefined') {
                    const script = document.createElement('script');
                    script.src = ((window.LG_CONFIG && window.LG_CONFIG.assetBase) || '') + '/Style/js/wavesurfer.min.js';
                    script.onload = () => {
                        // WaveSurfer 加载完成后初始化音频播放器
                        if (typeof initTimelineAudio === 'function') {
                            initTimelineAudio();
                        }
                    };
                    document.head.appendChild(script);
                } else if ($('.timeline-container').length > 0 && typeof initTimelineAudio === 'function') {
                    // WaveSurfer 已加载，直接初始化
                    initTimelineAudio();
                }

                // 初始化极验
                GeetestManager.initOnPjax();

                // 初始化爱情清单页面
                if ($('#list_container').length > 0 && typeof window.initListPage === 'function') {
                    window.initListPage();
                }

                // 留言页锚点跳转（PJAX 场景）
                if (isLeavingJump) {
                    const commentEl = document.getElementById(hash.substring(1));
                    if (commentEl) {
                        // 移除目标卡片及其前面所有卡片的 AOS 动画，避免 transform 干扰定位
                        const allCards = document.querySelectorAll('.MessageCard');
                        let found = false;
                        allCards.forEach(card => {
                            if (found) return;
                            card.removeAttribute('data-aos');
                            card.removeAttribute('data-aos-delay');
                            card.removeAttribute('data-aos-duration');
                            card.classList.add('aos-animate');
                            if (card === commentEl) found = true;
                        });

                        // 触发懒加载
                        if (window.lazyLoadInstance) {
                            window.lazyLoadInstance.update();
                        }

                        setTimeout(() => {
                            // 使用 offsetTop 递归计算绝对位置，不受 transform 影响
                            let offsetTop = 0;
                            let el = commentEl;
                            while (el) {
                                offsetTop += el.offsetTop;
                                el = el.offsetParent;
                            }
                            // 导航吸顶后的固定偏移量（fixed top:14px + 导航高度约56px + 间距20px）
                            const NAV_FIXED_OFFSET = 110;
                            const scrollY = Math.max(0, offsetTop - NAV_FIXED_OFFSET);
                            window.scrollTo({ top: scrollY, behavior: 'smooth' });

                            // 平滑滚动完成后，强制刷新导航吸附状态
                            setTimeout(() => {
                                const nav = window.LGComponents && window.LGComponents.Navigation;
                                if (nav) {
                                    nav._navOriginalTop = null;
                                    nav._isStuck = false;
                                    nav._updateNavPosition();
                                }
                            }, 800);

                            // 流光高亮
                            setTimeout(() => {
                                const ring = document.createElement('div');
                                ring.className = 'msg-highlight-ring';
                                commentEl.appendChild(ring);
                                setTimeout(() => {
                                    if (ring.parentNode) ring.parentNode.removeChild(ring);
                                }, 4500);
                            }, 600);
                        }, 400);
                    }
                }

                // 初始化聊天模块（关于页）
                if (document.getElementById('chatBox') && window.LGChatModule) {
                    window.LGChatModule.init();
                }

                // 初始化首页
                if ($('#lgnewui-day-counter-days').length > 0 && window.LGIndexModule) {
                    window.LGIndexModule.destroy();
                    window.LGIndexModule.init();
                    // 初始化 LoveDay 滑块位置
                    if (typeof window.initLoveDaySlider === 'function') {
                        window.initLoveDaySlider();
                    }
                }

                // 代码高亮
                if (typeof hljs !== 'undefined') {
                    hljs.highlightAll();
                }

                // NProgress 完成
                if (typeof NProgress !== 'undefined') {
                    NProgress.done();
                }

                // 添加样式类
                $('quote').addClass('shadow-blur');

                // 调用全局函数（如果存在）
                if (typeof GetEm === 'function') GetEm();
                if (typeof getMusic === 'function') getMusic();

                // 使用 LGApp 的公共函数（如果可用）
                if (CommonFunctions) {
                    CommonFunctions.handlePreviousPage();
                    CommonFunctions.setActiveTab();
                    CommonFunctions.removeBrFromMdView();
                    CommonFunctions.initToggleList();
                    CommonFunctions.initTooltip();
                    CommonFunctions.adjustIframeHeight();
                } else {
                    // 兼容旧代码
                    if (typeof IsCurrentURL === 'function') IsCurrentURL();
                    if (typeof setActiveTab === 'function') setActiveTab();
                    if (typeof removeBrFromMdView === 'function') removeBrFromMdView();
                    if (typeof initToggleList === 'function') initToggleList();
                }

                // 轮播图
                CarouselManager.init();

                // Masonry 瀑布流
                MasonryManager.initLGGrid();

                // 延迟初始化页面组件（含懒加载重建）
                TimerManager.setTimeout('pageComponents', () => {
                    PageComponents.initAll();

                    // imglist 瀑布流（pjax 重入时重新初始化）
                    if (window.ImglistApp && document.getElementById('imglist-grid')) {
                        ImglistApp.init();
                    }
                }, 50);

                // 高度调整
                if (HeightManager) {
                    HeightManager.adjustAll();
                } else {
                    if (typeof adjustCarouselHeight === 'function') adjustCarouselHeight();
                    if (typeof adjustPhotoCoverHeight === 'function') adjustPhotoCoverHeight();
                    if (typeof adjustImgMinHeight === 'function') adjustImgMinHeight();
                }

                // 图片错误处理
                $('.photo_content img').off('error.lgPjax').on('error.lgPjax', function () {
                    $(this).attr('src', imageErrorFallback);
                    $(this).addClass('loaded');
                });

                // 视频播放器
                VideoPlayerManager.init();
            });

            // ========== pjax:end ==========
            $(document).on('pjax:end.lgPjax', () => {
                // 确保隐藏 Loading
                LoadingIndicator.hide();

                // 处理返回按钮
                if (CommonFunctions) {
                    CommonFunctions.handlePreviousPage();
                } else if (typeof IsCurrentURL === 'function') {
                    IsCurrentURL();
                }

                // leaving hash 跳转时跳过恢复滚动位置（由 page-leaving.js 自行处理）
                const hash = window.location.hash;
                const isLeavingJump = ($('.Message_Wrap').length > 0 || $('#leavingPost').length > 0) && hash.startsWith('#comment_');
                const isListJump = $('#list_container').length > 0 && hash.startsWith('#event-');
                if (!isLeavingJump && !isListJump) {
                    ScrollPositionManager.restoreScrollPosition();
                }

                // ScrollReveal 初始化
                ScrollRevealManager.init();

                // 点点滴滴页面瀑布流
                MasonryManager.initArticleGrid();

                // 弥散光效果
                AuroraEffect.init();

                // 初始化点点滴滴页面（如果存在）
                if (typeof initArticlePage === 'function') {
                    initArticlePage();
                }
            });

            // ========== pjax:popstate（浏览器后退/前进）==========
            $(document).on('pjax:popstate.lgPjax', () => {
                LoadingIndicator.hide();

                // 刷新 AOS 动画
                if (typeof AOS !== 'undefined') {
                    AOS.refreshHard();
                }

                // 渲染 Lucide 图标
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }

                // NProgress 完成
                if (typeof NProgress !== 'undefined') {
                    NProgress.done();
                }

                // 重新初始化交互模块（点赞/浏览量）
                if (typeof LGInteraction !== 'undefined') {
                    LGInteraction.reinit();
                }

                // 页面标题动画
                if (typeof initPageHeaderAnimation === 'function') {
                    initPageHeaderAnimation();
                }

                // Tab 高亮 + 公共函数
                if (CommonFunctions) {
                    CommonFunctions.handlePreviousPage();
                    CommonFunctions.setActiveTab();
                    CommonFunctions.initPageHeader();
                    CommonFunctions.initTooltip();
                    CommonFunctions.removeBrFromMdView();
                    CommonFunctions.initToggleList();
                } else {
                    if (typeof IsCurrentURL === 'function') IsCurrentURL();
                    if (typeof setActiveTab === 'function') setActiveTab();
                }

                // 轮播图
                CarouselManager.init();

                // Masonry 瀑布流
                MasonryManager.initLGGrid();

                // 高度调整
                if (HeightManager) {
                    HeightManager.adjustAll();
                }

                // 视频播放器
                VideoPlayerManager.init();

                TimerManager.setTimeout('popstateInit', () => {
                    PageComponents.initAll();
                }, 100);
            });

            // ========== 原生 popstate（兜底处理）==========
            $(window).off('popstate.lgPjaxFallback').on('popstate.lgPjaxFallback', () => {
                TimerManager.setTimeout('popstateFallback', () => {
                    // 刷新 AOS 动画
                    if (typeof AOS !== 'undefined') {
                        AOS.refreshHard();
                    }
                    // 刷新懒加载
                    PjaxLazyLoad.init();
                }, 150);
            });

            // ========== pjax:error / pjax:timeout ==========
            $(document).on('pjax:error.lgPjax pjax:timeout.lgPjax', () => {
                LoadingIndicator.hide();
                if (typeof NProgress !== 'undefined') {
                    NProgress.done();
                }
            });
        },

        /**
         * 销毁 PJAX 事件绑定
         */
        destroy() {
            $(document).off('.lgPjax');
        }
    };

    // ============================================
    // 暴露到全局
    // ============================================
    window.LGPjax = LGPjax;

    // 兼容旧代码
    window.initPageComponents = () => PageComponents.initAll();
    window.initLeavingGeetest = () => GeetestManager.initLeaving();
    window.initGeetestOnPjax = () => GeetestManager.initOnPjax();

})(window, jQuery);
