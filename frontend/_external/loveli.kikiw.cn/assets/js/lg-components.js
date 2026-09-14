/**
 * LG_NewUI 可复用组件模块
 * @version 2.0.0
 * @description 轮播图、礼花效果、头像交互、Masonry 等组件
 */

; (function (window, $) {
    'use strict';

    const { Utils, TimerManager } = window.LGApp || {};

    // ============================================
    // 礼花效果模块
    // ============================================
    const ConfettiEffect = {
        _canvas: null,
        _confettiInstance: null,
        _shapes: null,
        _initialized: false,

        // 颜色配置
        COLORS: {
            ROSE: ['#fc5d79', '#ffb3c1', '#ff85a1', '#fff5f7', '#ffccd5'],
            SPARKLE: ['#ffd700', '#ffed4e', '#fff9c4', '#ffe082', '#ffc107'],
            MIXED: ['#fc5d79', '#ffb3c1', '#ffd700', '#fff5f7', '#ffe082', '#ff85a1']
        },

        // 形状路径
        PATHS: {
            HEART: 'M16 28s-13-7.5-13-16c0-4.5 3.5-8 8-8 2.5 0 4.5 1.5 5 3.5 0.5-2 2.5-3.5 5-3.5 4.5 0 8 3.5 8 8 0 8.5-13 16-13 16z',
            FLOWER: 'M16 8a4 4 0 1 1-4 4 4 4 0 0 1 4-4zm0 0l2-6h-4l2 6zm6 6l6-2v-4l-6 6zm-12 0l-6-2v-4l6 6zm6 6l-2 6h4l-2-6zm6-6l6 2v4l-6-6zm-12 0l-6 2v4l6-6z',
            STAR: 'M16 4l3 9h9l-7 6 3 9-8-6-8 6 3-9-7-6h9z',
            SPARKLE: 'M16 2l2 6 6 2-6 2-2 6-2-6-6-2 6-2z',
            CIRCLE: 'M16 8a8 8 0 1 1 0 16 8 8 0 0 1 0-16z'
        },

        /**
         * 初始化礼花效果
         */
        init() {
            if (this._initialized || typeof confetti === 'undefined') return;

            // 创建专用 canvas
            this._canvas = document.createElement('canvas');
            this._canvas.id = 'confetti-canvas';
            this._canvas.style.cssText = 'position:fixed!important;top:0!important;left:0!important;width:100%!important;height:100%!important;z-index:999999!important;pointer-events:none!important;';
            document.body.appendChild(this._canvas);

            // 创建 confetti 实例
            this._confettiInstance = confetti.create(this._canvas, {
                resize: true,
                useWorker: true
            });

            // 创建形状
            this._shapes = {
                heart: confetti.shapeFromPath({ path: this.PATHS.HEART }),
                flower: confetti.shapeFromPath({ path: this.PATHS.FLOWER }),
                star: confetti.shapeFromPath({ path: this.PATHS.STAR }),
                sparkle: confetti.shapeFromPath({ path: this.PATHS.SPARKLE }),
                circle: confetti.shapeFromPath({ path: this.PATHS.CIRCLE })
            };

            this._initialized = true;

            // 暴露全局方法（兼容旧代码）
            window.myConfetti = this._confettiInstance;
            window.loveWingEffect = () => this.loveWingEffect();
            window.heartBurstEffect = (el) => this.heartBurstEffect(el);
        },

        /**
         * 如影随形效果 - 页面加载时使用
         */
        loveWingEffect() {
            if (!this._confettiInstance || !this._shapes) return;

            const wing = {
                particleCount: 80,
                spread: 70,
                origin: { y: 0.15 },
                shapes: [this._shapes.heart, this._shapes.flower],
                colors: this.COLORS.ROSE,
                scalar: 1.8
            };

            this._confettiInstance({ ...wing, angle: 315, origin: { x: 0, y: 0 } });
            this._confettiInstance({ ...wing, angle: 225, origin: { x: 1, y: 0 } });
        },

        /**
         * 心动炸裂效果 - 点击爱心时使用
         * @param {Element} element
         */
        heartBurstEffect(element) {
            if (!this._confettiInstance || !this._shapes || !element) return;

            const rect = element.getBoundingClientRect();
            const x = (rect.left + rect.width / 2) / window.innerWidth;
            const y = (rect.top + rect.height / 2) / window.innerHeight;

            // 第一波：爱心炸裂
            this._confettiInstance({
                particleCount: 120,
                spread: 140,
                origin: { x, y },
                shapes: [this._shapes.heart],
                colors: this.COLORS.ROSE,
                scalar: 2.5,
                gravity: 0.8,
                ticks: 400,
                startVelocity: 45
            });

            // 第二波：星星和闪光
            TimerManager.setTimeout('confetti2', () => {
                this._confettiInstance({
                    particleCount: 80,
                    spread: 120,
                    origin: { x, y },
                    shapes: [this._shapes.star, this._shapes.sparkle],
                    colors: this.COLORS.SPARKLE,
                    scalar: 1.8,
                    gravity: 0.6,
                    ticks: 350,
                    startVelocity: 40
                });
            }, 50);

            // 第三波：花朵和圆点
            TimerManager.setTimeout('confetti3', () => {
                this._confettiInstance({
                    particleCount: 60,
                    spread: 100,
                    origin: { x, y },
                    shapes: [this._shapes.flower, this._shapes.circle],
                    colors: this.COLORS.MIXED,
                    scalar: 1.5,
                    gravity: 0.7,
                    ticks: 300,
                    startVelocity: 35
                });
            }, 100);

            // 第四波：环绕星星
            TimerManager.setTimeout('confetti4', () => {
                for (let i = 0; i < 8; i++) {
                    const angle = (i / 8) * 360;
                    this._confettiInstance({
                        particleCount: 5,
                        angle,
                        spread: 30,
                        origin: { x, y },
                        shapes: [this._shapes.star, this._shapes.sparkle],
                        colors: this.COLORS.SPARKLE,
                        scalar: 1.2,
                        gravity: 0.5,
                        ticks: 250,
                        startVelocity: 25
                    });
                }
            }, 150);
        },

        /**
         * 销毁
         */
        destroy() {
            if (this._canvas && this._canvas.parentNode) {
                this._canvas.parentNode.removeChild(this._canvas);
            }
            this._canvas = null;
            this._confettiInstance = null;
            this._shapes = null;
            this._initialized = false;
        }
    };

    // ============================================
    // 轮播图模块
    // ============================================
    const Carousel = {
        _$items: null,
        _$points: null,
        _$wrap: null,
        _total: 0,
        _index: 0,
        _timer: null,
        _startX: 0,
        _initialized: false,

        /**
         * 初始化轮播图
         * @param {Object} options
         */
        init(options = {}) {
            const {
                itemSelector = '.item',
                pointSelector = '.point',
                wrapSelector = '.wrap',
                interval = 5000
            } = options;

            this._$items = $(itemSelector);
            this._$points = $(pointSelector);
            this._$wrap = $(wrapSelector);
            this._total = this._$items.length;
            this._interval = interval;

            if (this._total === 0) return;

            this._index = 0;
            this._bindEvents();
            this._startTimer();
            this._initialized = true;
        },

        _updateView() {
            this._$items.removeClass('active').eq(this._index).addClass('active');
            this._$points.removeClass('active').eq(this._index).addClass('active');
        },

        _goLeft() {
            this._index = this._index === 0 ? this._total - 1 : this._index - 1;
            this._updateView();
        },

        _goRight() {
            this._index = this._index < this._total - 1 ? this._index + 1 : 0;
            this._updateView();
        },

        _startTimer() {
            this._stopTimer();
            TimerManager.setInterval('lgCarouselAutoPlay', () => this._goRight(), this._interval);
        },

        _stopTimer() {
            TimerManager.clearInterval('lgCarouselAutoPlay');
        },

        _resetTimer() {
            this._stopTimer();
            this._startTimer();
        },

        _bindEvents() {
            // 点击指示点
            this._$points.off('click.lgCarousel').on('click.lgCarousel', (e) => {
                this._index = $(e.currentTarget).data('index');
                this._updateView();
                this._resetTimer();
            });

            // 鼠标悬停暂停
            this._$wrap.off('mousemove.lgCarousel mouseleave.lgCarousel')
                .on('mousemove.lgCarousel', () => this._stopTimer())
                .on('mouseleave.lgCarousel', () => this._startTimer());

            // 页面可见性
            document.removeEventListener('visibilitychange', this._visibilityHandler);
            this._visibilityHandler = () => {
                if (document.hidden) {
                    this._stopTimer();
                } else {
                    this._startTimer();
                }
            };
            document.addEventListener('visibilitychange', this._visibilityHandler);

            // 触摸滑动
            this._$wrap.off('touchstart.lgCarousel mousedown.lgCarousel touchend.lgCarousel mouseup.lgCarousel')
                .on('touchstart.lgCarousel mousedown.lgCarousel', (e) => {
                    this._startX = e.type === 'touchstart'
                        ? e.originalEvent.touches[0].clientX
                        : e.clientX;
                })
                .on('touchend.lgCarousel mouseup.lgCarousel', (e) => {
                    const endX = e.type === 'touchend'
                        ? e.originalEvent.changedTouches[0].clientX
                        : e.clientX;
                    const deltaX = endX - this._startX;

                    if (Math.abs(deltaX) > 50) {
                        this._stopTimer();
                        deltaX > 0 ? this._goLeft() : this._goRight();
                        this._startTimer();
                    }
                });
        },

        /**
         * 销毁轮播图
         */
        destroy() {
            TimerManager.clearInterval('lgCarouselAutoPlay');
            if (this._$points) this._$points.off('.lgCarousel');
            if (this._$wrap) this._$wrap.off('.lgCarousel');
            document.removeEventListener('visibilitychange', this._visibilityHandler);
            this._initialized = false;
        }
    };


    // ============================================
    // 头像交互模块
    // ============================================
    const AvatarInteraction = {
        _initialized: false,

        /**
         * 初始化头像点击效果
         */
        init() {
            if (this._initialized) return;

            // 爱心图标点击
            this._bindHeartClick();

            // 情侣头像点击（右上角）
            this._bindCoupleAvatarsClick();

            this._initialized = true;
        },

        _bindHeartClick() {
            const loveIcon = document.querySelector('.love-icon');
            const heartImg = loveIcon ? loveIcon.querySelector('img') : null;

            if (!heartImg) {
                TimerManager.setTimeout('heartClick', () => this._bindHeartClick(), 500);
                return;
            }

            // 克隆移除旧事件
            const newHeartImg = heartImg.cloneNode(true);
            heartImg.parentNode.replaceChild(newHeartImg, heartImg);

            newHeartImg.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                ConfettiEffect.heartBurstEffect(newHeartImg.parentElement);
            });
        },

        _bindCoupleAvatarsClick() {
            const coupleAvatars = document.querySelector('.lgnewui-couple-avatars-right');
            if (!coupleAvatars) return;

            coupleAvatars.style.cursor = 'pointer';

            // 移除旧事件
            const newCoupleAvatars = coupleAvatars.cloneNode(true);
            coupleAvatars.parentNode.replaceChild(newCoupleAvatars, coupleAvatars);

            newCoupleAvatars.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();

                // 添加弹弓动画
                newCoupleAvatars.classList.add('slingshot-bounce');
                setTimeout(() => {
                    newCoupleAvatars.classList.remove('slingshot-bounce');
                }, 600);

                // 触发两侧飞入礼花效果（如影随形）
                ConfettiEffect.loveWingEffect();
            });
        },

        destroy() {
            this._initialized = false;
        }
    };

    // ============================================
    // Masonry 瀑布流模块
    // ============================================
    const MasonryGrid = {
        _instances: new Map(),

        /**
         * 初始化 Masonry
         * @param {string} selector - 容器选择器
         * @param {Object} options - 配置选项
         */
        init(selector, options = {}) {
            if (typeof Masonry === 'undefined') return null;

            const $grid = $(selector);
            if ($grid.length === 0) return null;

            const defaultOptions = {
                itemSelector: '.lg-masonry-col',
                percentPosition: true
            };

            const mergedOptions = { ...defaultOptions, ...options };

            // 如果有 imagesLoaded，等待图片加载
            if (typeof $.fn.imagesLoaded === 'function') {
                $grid.imagesLoaded(() => {
                    const instance = new Masonry($grid[0], mergedOptions);
                    this._instances.set(selector, instance);
                });
            } else {
                const instance = new Masonry($grid[0], mergedOptions);
                this._instances.set(selector, instance);
            }

            return this._instances.get(selector);
        },

        /**
         * 刷新布局
         * @param {string} selector
         */
        layout(selector) {
            const instance = this._instances.get(selector);
            if (instance) {
                instance.layout();
            }
        },

        /**
         * 销毁实例
         * @param {string} selector
         */
        destroy(selector) {
            const instance = this._instances.get(selector);
            if (instance) {
                instance.destroy();
                this._instances.delete(selector);
            }
        },

        /**
         * 销毁所有实例
         */
        destroyAll() {
            this._instances.forEach((instance) => {
                instance.destroy();
            });
            this._instances.clear();
        }
    };

    // ============================================
    // 视频播放器模块
    // ============================================
    const VideoPlayer = {
        _instances: new Map(),

        /**
         * 初始化 Plyr 播放器
         * @param {string} selector
         * @param {Object} options
         */
        init(selector, options = {}) {
            if (typeof Plyr === 'undefined') return null;

            const element = document.querySelector(selector);
            if (!element) return null;

            const defaultOptions = {
                i18n: {
                    speed: '速度',
                    normal: '正常'
                },
                keyboard: { global: true },
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
            };

            const mergedOptions = { ...defaultOptions, ...options };
            const player = new Plyr(selector, mergedOptions);
            this._instances.set(selector, player);

            return player;
        },

        /**
         * 获取播放器实例
         * @param {string} selector
         */
        get(selector) {
            return this._instances.get(selector);
        },

        /**
         * 销毁播放器
         * @param {string} selector
         */
        destroy(selector) {
            const player = this._instances.get(selector);
            if (player) {
                player.destroy();
                this._instances.delete(selector);
            }
        },

        /**
         * 销毁所有播放器
         */
        destroyAll() {
            this._instances.forEach((player) => {
                player.destroy();
            });
            this._instances.clear();
        }
    };

    // ============================================
    // 导航栏模块
    // ============================================
    const Navigation = {
        _indicator: null,
        _items: null,
        _heroTitle: null,
        _metaTag: null,
        _metaText: null,
        _metaLine: null,
        _navWrapper: null,
        _navPlaceholder: null,
        _navIsland: null,
        _navOriginalTop: null,
        _navHeight: null,
        _rafId: null,
        _scrollRafId: null,
        _isStuck: false,
        _initialized: false,
        _lastScrollY: 0,
        _scrollDir: 'down',
        _backBtnState: null,

        // 子页面到父页面的映射
        PAGE_MAPPING: {
            'page.php': 'articles.php',
            'album-detail.php': 'albums.php'
        },

        // 隐藏描述区域的页面列表（详情页等）
        HIDE_HEADER_PAGES: ['page.php', 'album-detail.php'],

        /**
         * 初始化导航栏
         */
        init() {
            this._indicator = document.getElementById('lgnewuiNavIndicator');
            this._items = document.querySelectorAll('.lgnewui-nav-island-item');
            this._heroTitle = document.getElementById('lgnewuiHeroTitle');
            this._metaTag = document.getElementById('lgnewuiMetaTag');
            this._metaText = document.getElementById('lgnewuiMetaText');
            this._metaLine = document.getElementById('lgnewuiMetaLine');
            this._navWrapper = document.getElementById('lgnewuiNavWrapper');
            this._navPlaceholder = document.getElementById('lgnewuiNavPlaceholder');
            this._navIsland = document.getElementById('lgnewuiNavIsland');
            this._pageHeader = document.querySelector('.lgnewui-page-header');

            if (!this._indicator || !this._items.length || !this._heroTitle || !this._navWrapper) {
                return;
            }

            this._setActiveByPath();
            this._updateHeaderVisibility();
            this._bindEvents();
            this._initNav();
            TimerManager.setTimeout('navPosition', () => this._updateNavPosition(), 100);

            this._initialized = true;
        },

        /**
         * 根据当前页面控制描述区域显示/隐藏
         */
        _updateHeaderVisibility() {
            const currentPath = window.location.pathname;
            const currentPage = currentPath.split('/').pop() || 'index.php';
            const shouldHide = this.HIDE_HEADER_PAGES.includes(currentPage);

            if (this._pageHeader) {
                this._pageHeader.style.display = shouldHide ? 'none' : '';
            }
            if (this._navPlaceholder) {
                this._navPlaceholder.style.display = shouldHide ? 'none' : '';
            }
        },

        _setActiveByPath() {
            const currentPath = window.location.pathname;
            const currentPage = currentPath.split('/').pop() || 'index.php';
            const targetPage = this.PAGE_MAPPING[currentPage] || currentPage;

            this._items.forEach(item => {
                item.classList.remove('active');
                const href = item.getAttribute('href');

                if (href === targetPage ||
                    currentPage === href ||
                    (href === 'index.php' && (currentPath === '/' || currentPath.endsWith('/') || currentPath.endsWith('index.php')))) {
                    item.classList.add('active');
                }
            });

            // 默认首页
            if (!document.querySelector('.lgnewui-nav-island-item.active')) {
                const homeItem = document.querySelector('.lgnewui-nav-island-item.nav-home');
                if (homeItem) homeItem.classList.add('active');
            }
        },

        _updateNavPosition() {
            if (this._navOriginalTop === null) {
                this._navOriginalTop = this._navWrapper.getBoundingClientRect().top + window.scrollY;
                this._navHeight = this._navWrapper.offsetHeight;
            }

            const scrollTop = window.scrollY;
            const headerHeight = 72;
            const triggerPoint = this._navOriginalTop - headerHeight;
            const shouldStick = scrollTop > triggerPoint;

            // 滚动方向跟踪
            const delta = scrollTop - this._lastScrollY;
            if (Math.abs(delta) > 3) {
                this._scrollDir = delta > 0 ? 'down' : 'up';
            }
            this._lastScrollY = scrollTop;

            const stuckChanged = shouldStick !== this._isStuck;
            if (stuckChanged) {
                this._isStuck = shouldStick;
            }

            // 缓存 DOM 查询
            const logoEl = this._logoEl || (this._logoEl = document.querySelector('.alogo'));
            const actionsEl = this._actionsEl || (this._actionsEl = document.querySelector('.lgnewui-header-actions'));
            const leftAvatarEl = this._leftAvatarEl || (this._leftAvatarEl = document.querySelector('.lgnewui-header-left-avatar'));

            // 缓存header-wrap元素
            const headerWrap = this._headerWrap || (this._headerWrap = document.querySelector('.header-wrap'));

            if (stuckChanged) {
                if (shouldStick) {
                    this._navWrapper.classList.add('is-fixed');
                    this._navPlaceholder.classList.add('is-active');
                    this._navPlaceholder.style.height = this._navHeight + 'px';
                    this._navIsland.classList.add('lgnewui-is-stuck');
                    if (headerWrap) headerWrap.classList.add('is-stuck');

                    if (logoEl) logoEl.classList.add('lgnewui-logo-faded');
                    if (actionsEl) actionsEl.classList.add('lgnewui-actions-visible');
                    if (leftAvatarEl) leftAvatarEl.classList.add('lgnewui-avatar-visible');
                } else {
                    this._navIsland.classList.remove('lgnewui-is-stuck');
                    this._navWrapper.classList.remove('is-fixed');
                    this._navPlaceholder.classList.remove('is-active');
                    if (headerWrap) headerWrap.classList.remove('is-stuck');

                    if (logoEl) logoEl.classList.remove('lgnewui-logo-faded');
                    if (actionsEl) actionsEl.classList.remove('lgnewui-actions-visible');
                    if (leftAvatarEl) leftAvatarEl.classList.remove('lgnewui-avatar-visible');
                }
                this._snapIndicator(true);
            }

            // 子页面吸顶状态下：根据滚动方向切换 logo 和返回按钮
            this._toggleBackBtn(shouldStick);
        },

        _toggleBackBtn(isStuck) {
            const capsule = this._backBtnEl || (this._backBtnEl = document.querySelector('.lg-capsule-back'));
            if (!capsule || !capsule.classList.contains('subpage-back-ready')) return;

            const leftAvatarEl = this._leftAvatarEl || (this._leftAvatarEl = document.querySelector('.lgnewui-header-left-avatar'));
            // 不缓存titleLogoEl，每次重新查询（PJAX后DOM可能变化）
            const titleLogoEl = document.querySelector('.header .logo');
            const isMobile = window.innerWidth <= 768;
            const dir = this._scrollDir;
            const showBack = isStuck && dir === 'up';
            const showLogo = isStuck && dir === 'down';

            if (showBack && this._backBtnState !== 'back') {
                this._backBtnState = 'back';
                capsule.classList.add('scroll-back-visible');
                if (isMobile) {
                    if (titleLogoEl) titleLogoEl.classList.add('scroll-logo-hidden');
                } else {
                    if (leftAvatarEl) leftAvatarEl.classList.add('scroll-logo-hidden');
                }
            } else if (showLogo && this._backBtnState !== 'logo') {
                this._backBtnState = 'logo';
                capsule.classList.remove('scroll-back-visible');
                if (isMobile) {
                    if (titleLogoEl) titleLogoEl.classList.remove('scroll-logo-hidden');
                } else {
                    if (leftAvatarEl) leftAvatarEl.classList.remove('scroll-logo-hidden');
                }
            } else if (!isStuck && this._backBtnState !== null) {
                this._backBtnState = null;
                capsule.classList.remove('scroll-back-visible');
                if (titleLogoEl) titleLogoEl.classList.remove('scroll-logo-hidden');
                if (leftAvatarEl) leftAvatarEl.classList.remove('scroll-logo-hidden');
            }
        },

        /**
         * 更新 indicator 位置
         * @param {boolean} animate - true: 带过渡动画滑过去(tab切换/吸附切换); false: 无过渡直接跳(初始化)
         */
        _snapIndicator(animate) {
            const active = document.querySelector('.lgnewui-nav-island-item.active');
            if (!active) return;

            if (this._rafId) cancelAnimationFrame(this._rafId);
            TimerManager.clearTimeout('snapFinal');

            if (animate) {
                // 带动画：等导航项 CSS 过渡完成后，indicator 带过渡滑到新位置
                // 导航项 padding 过渡 0.25s，等它结束后读取最终位置
                TimerManager.setTimeout('snapFinal', () => {
                    this._indicator.classList.remove('no-transition');
                    this._rafId = requestAnimationFrame(() => {
                        const left = active.offsetLeft;
                        const width = active.offsetWidth;
                        this._indicator.style.width = `${width}px`;
                        this._indicator.style.transform = `translateX(${left}px)`;
                    });
                }, 260);
            } else {
                // 无动画：立即跳到位置（初始化场景）
                this._indicator.classList.add('no-transition');
                this._rafId = requestAnimationFrame(() => {
                    const left = active.offsetLeft;
                    const width = active.offsetWidth;
                    this._indicator.style.width = `${width}px`;
                    this._indicator.style.transform = `translateX(${left}px)`;
                    requestAnimationFrame(() => {
                        this._indicator.classList.remove('no-transition');
                    });
                });
            }
        },

        _renderText(desc) {
            if (!desc || !this._heroTitle) return;

            this._heroTitle.innerHTML = desc.split('').map((char, i) => {
                return `<span class="char" style="transition-delay: ${i * 30}ms">${char === ' ' ? '&nbsp;' : char}</span>`;
            }).join('');

            TimerManager.setTimeout('heroText', () => {
                this._heroTitle.querySelectorAll('.char').forEach(span => span.classList.add('in'));
            }, 50);
        },

        _updateState(activeElement) {
            if (!activeElement) return;

            this._indicator.classList.remove('no-transition');

            requestAnimationFrame(() => {
                const left = activeElement.offsetLeft;
                const width = activeElement.offsetWidth;
                this._indicator.style.width = `${width}px`;
                this._indicator.style.transform = `translateX(${left}px)`;
            });

            const desc = activeElement.getAttribute('data-desc');
            const meta = activeElement.getAttribute('data-meta');

            if (this._heroTitle.textContent.replace(/\s/g, '') !== (desc || '').replace(/\s/g, '')) {
                this._metaTag.classList.remove('in');
                if (this._metaLine) this._metaLine.classList.remove('in');

                TimerManager.setTimeout('metaUpdate', () => {
                    if (this._metaText) this._metaText.innerText = meta || '';
                    this._metaTag.classList.add('in');
                    if (this._metaLine) this._metaLine.classList.add('in');
                }, 300);

                this._renderText(desc);
            }
        },

        _initNav() {
            const active = document.querySelector('.lgnewui-nav-island-item.active');
            if (active) {
                const desc = active.getAttribute('data-desc');
                const meta = active.getAttribute('data-meta');
                if (this._metaText) this._metaText.innerText = meta || '';
                TimerManager.setTimeout('metaInit', () => {
                    this._metaTag.classList.add('in');
                    if (this._metaLine) this._metaLine.classList.add('in');
                }, 100);
                this._renderText(desc);
                TimerManager.setTimeout('stateInit', () => this._updateState(active), 100);
            }
        },

        _bindEvents() {
            // 滚动监听 - 使用 RAF 节流，存储引用以便 destroy 时移除
            this._onScroll = () => {
                if (this._scrollRafId) return;
                this._scrollRafId = requestAnimationFrame(() => {
                    this._updateNavPosition();
                    this._scrollRafId = null;
                });
            };
            window.addEventListener('scroll', this._onScroll, { passive: true });

            // 窗口调整 - 存储引用以便 destroy 时移除
            this._onResize = () => {
                this._navOriginalTop = null;
                this._isStuck = false;
                this._updateNavPosition();

                this._indicator.classList.add('no-transition');
                const active = document.querySelector('.lgnewui-nav-island-item.active');
                if (active) {
                    const left = active.offsetLeft;
                    const width = active.offsetWidth;
                    this._indicator.style.width = `${width}px`;
                    this._indicator.style.transform = `translateX(${left}px)`;
                }
                requestAnimationFrame(() => {
                    TimerManager.setTimeout('indicatorTransition', () => {
                        this._indicator.classList.remove('no-transition');
                    }, 100);
                });
            };
            window.addEventListener('resize', this._onResize);

            // 点击切换
            this._items.forEach(item => {
                item.addEventListener('click', (e) => {
                    this._items.forEach(i => i.classList.remove('active'));
                    item.classList.add('active');
                    this._updateState(item);
                });
            });

            // PJAX 完成后重新初始化
            $(document).off('pjax:complete.lgNav').on('pjax:complete.lgNav', () => {
                // 重新获取 DOM 引用（确保引用有效）
                this._heroTitle = document.getElementById('lgnewuiHeroTitle');
                this._metaTag = document.getElementById('lgnewuiMetaTag');
                this._metaText = document.getElementById('lgnewuiMetaText');
                this._metaLine = document.getElementById('lgnewuiMetaLine');
                this._pageHeader = document.querySelector('.lgnewui-page-header');

                this._setActiveByPath();
                this._updateHeaderVisibility();

                const active = document.querySelector('.lgnewui-nav-island-item.active');
                if (active) {
                    // 确保移除 no-transition 类，让 indicator 有滑动动画
                    if (this._indicator) {
                        this._indicator.classList.remove('no-transition');
                    }

                    requestAnimationFrame(() => {
                        const left = active.offsetLeft;
                        const width = active.offsetWidth;
                        if (this._indicator) {
                            this._indicator.style.width = `${width}px`;
                            this._indicator.style.transform = `translateX(${left}px)`;
                        }
                    });

                    const desc = active.getAttribute('data-desc');
                    const meta = active.getAttribute('data-meta');

                    // 重置动画状态
                    if (this._metaTag) {
                        this._metaTag.classList.remove('in');
                    }
                    if (this._metaLine) {
                        this._metaLine.classList.remove('in');
                    }

                    // 清空 heroTitle 内容，强制重新渲染
                    if (this._heroTitle) {
                        this._heroTitle.innerHTML = '';
                    }

                    // 延迟触发动画
                    TimerManager.setTimeout('metaInitPjax', () => {
                        if (this._metaText) this._metaText.innerText = meta || '';
                        if (this._metaTag) this._metaTag.classList.add('in');
                        if (this._metaLine) this._metaLine.classList.add('in');
                        this._renderText(desc);
                    }, 100);
                }

                this._navOriginalTop = null;
                this._isStuck = false;
                this._backBtnState = null;
                this._backBtnEl = null;
                this._lastScrollY = window.scrollY;
                this._scrollDir = 'down';

                // PJAX后清理logo上的scroll-logo-hidden类
                const titleLogoEl = document.querySelector('.header .logo');
                const leftAvatarEl = document.querySelector('.lgnewui-header-left-avatar');
                if (titleLogoEl) titleLogoEl.classList.remove('scroll-logo-hidden');
                if (leftAvatarEl) leftAvatarEl.classList.remove('scroll-logo-hidden');

                TimerManager.setTimeout('navPositionPjax', () => this._updateNavPosition(), 50);
            });
        },

        destroy() {
            if (this._rafId) cancelAnimationFrame(this._rafId);
            if (this._scrollRafId) cancelAnimationFrame(this._scrollRafId);
            // 移除原生事件监听器（使用存储的具名引用）
            if (this._onScroll) {
                window.removeEventListener('scroll', this._onScroll);
                this._onScroll = null;
            }
            if (this._onResize) {
                window.removeEventListener('resize', this._onResize);
                this._onResize = null;
            }
            $(document).off('pjax:complete.lgNav');
            this._isStuck = false;
            this._initialized = false;
            this._backBtnState = null;
            this._backBtnEl = null;
            this._lastScrollY = 0;
            this._scrollDir = 'down';
        }
    };

    // ============================================
    // 滚动按钮模块
    // ============================================
    const ScrollButtons = {
        _$scrollTopBtn: null,
        _initialized: false,
        _scrollTicking: false,

        init() {
            this._$scrollTopBtn = $('#scrollTopBtn');

            if (this._$scrollTopBtn.length === 0) return;

            this._$scrollTopBtn.removeClass('lgnewui-fab-visible');

            // 滚动显示/隐藏 - 使用 RAF 节流
            $(window).off('scroll.lgScrollBtn').on('scroll.lgScrollBtn', () => {
                if (this._scrollTicking) return;
                this._scrollTicking = true;
                requestAnimationFrame(() => {
                    if (window.scrollY > 1000) {
                        this._$scrollTopBtn.addClass('lgnewui-fab-visible');
                    } else {
                        this._$scrollTopBtn.removeClass('lgnewui-fab-visible');
                    }
                    this._scrollTicking = false;
                });
            });

            // 回到顶部
            this._$scrollTopBtn.off('click.lgScrollBtn').on('click.lgScrollBtn', () => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });

            this._initialized = true;
        },

        destroy() {
            $(window).off('scroll.lgScrollBtn');
            if (this._$scrollTopBtn) this._$scrollTopBtn.off('click.lgScrollBtn');
            this._initialized = false;
        }
    };

    // ============================================
    // 吸顶栏访客天气
    // ============================================
    const HeaderVisitorWeather = {
        _initialized: false,
        _timerId: null,
        _rootEl: null,
        _iconEl: null,
        _textEl: null,
        _panelEl: null,
        _pendingRequest: null,
        _cachePayload: null,
        _cacheAt: 0,
        _handleDocumentClick: null,
        _handleToggleClick: null,
        _handleKeydown: null,
        _handleScroll: null,
        _handleResize: null,

        init() {
            if (window.LG_CONFIG && window.LG_CONFIG.weatherEnabled === false) {
                var _wRoot = document.getElementById('lgHeaderVisitorWeather');
                if (_wRoot) _wRoot.style.display = 'none';
                return;
            }

            this._rootEl = document.getElementById('lgHeaderVisitorWeather');
            this._iconEl = document.getElementById('lgHeaderVisitorWeatherIcon');
            this._textEl = document.getElementById('lgHeaderVisitorWeatherText');
            this._panelEl = this._createPanel();
            if (!this._rootEl || !this._iconEl || !this._textEl || !this._panelEl || this._initialized) {
                return;
            }

            this._bindEvents();
            this._setLoading(true);
            this._refresh();
            this._timerId = setInterval(() => this._refresh(), 10 * 60 * 1000);
            this._initialized = true;
        },

        destroy() {
            if (this._timerId) {
                clearInterval(this._timerId);
                this._timerId = null;
            }
            this._unbindEvents();
            this._initialized = false;
        },

        async _refresh() {
            const now = Date.now();
            if (this._cachePayload && (now - this._cacheAt) < 60 * 1000) {
                this._applyPayload(this._cachePayload);
                return;
            }
            if (this._pendingRequest) {
                await this._pendingRequest;
                return;
            }

            try {
                this._setLoading(true);
                const _wtParam = (window.LG_CONFIG && window.LG_CONFIG.weatherToken) ? '&_wt=' + encodeURIComponent(window.LG_CONFIG.weatherToken) : '';
                var _siteBase = (window.LG_CONFIG && window.LG_CONFIG.siteBase) || '';
                this._pendingRequest = fetch(_siteBase + 'services/weather.php?mode=ip' + _wtParam, {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'default'
                })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('HTTP_' + response.status);
                        }
                        return response.json();
                    })
                    .then(payload => {
                        const data = payload && payload.code === 200 ? payload.data : null;
                        if (!data || typeof data !== 'object') {
                            throw new Error('INVALID_PAYLOAD');
                        }
                        this._cachePayload = data;
                        this._cacheAt = Date.now();
                        window.__lgVisitorWeatherCache = { data: data, at: this._cacheAt };
                        this._applyPayload(data);
                    })
                    .finally(() => {
                        this._pendingRequest = null;
                    });

                await this._pendingRequest;
            } catch (err) {
                this._setLoading(false);
                this._iconEl.className = 'qi-999-fill lgnewui-header-weather-icon';
                this._textEl.textContent = '暂无天气';
                this._renderPanel({
                    city: '暂无天气',
                    temp: '--',
                    desc: '请稍后重试',
                    feelsLike: '--',
                    humidity: '--',
                    windDir: '--',
                    windScale: '',
                    vis: '--'
                });
            }
        },

        _applyPayload(data) {
            this._setLoading(false);
            const iconCodeRaw = String(data.icon || '999').replace(/[^\d]/g, '');
            const iconCode = iconCodeRaw !== '' ? iconCodeRaw : '999';
            this._iconEl.className = `qi-${iconCode}-fill lgnewui-header-weather-icon`;

            const tempText = (data.temp !== undefined && data.temp !== null && String(data.temp) !== '')
                ? `${data.temp}°`
                : '--';
            const descText = String(data.desc || '天气');
            this._textEl.textContent = `${descText} ${tempText}`;
            this._renderPanel(data);
        },

        _setLoading(isLoading) {
            if (!this._rootEl) {
                return;
            }
            this._rootEl.classList.toggle('is-loading', !!isLoading);
        },

        _renderPanel(data) {
            if (!this._panelEl) {
                return;
            }

            const iconCodeRaw = String(data.icon || '999').replace(/[^\d]/g, '');
            const iconCode = iconCodeRaw !== '' ? iconCodeRaw : '999';
            const tempText = (data.temp !== undefined && data.temp !== null && String(data.temp) !== '')
                ? `${this._escapeHtml(String(data.temp))}°`
                : '--';
            const descText = this._escapeHtml(String(data.desc || '天气信息暂不可用'));
            const cityText = this._escapeHtml(String(data.city || '未知位置'));
            const feelsText = `${this._escapeHtml(String(data.feelsLike || '--'))}°`;
            const humidityText = `${this._escapeHtml(String(data.humidity || '--'))}%`;
            const windDir = this._escapeHtml(String(data.windDir || '--'));
            const windScale = this._escapeHtml(String(data.windScale || ''));
            const windText = windScale !== '' ? `${windDir} ${windScale}级` : windDir;
            const visText = `${this._escapeHtml(String(data.vis || '--'))} km`;
            const updatedText = this._escapeHtml(this._formatUpdateTime(String(data.obsTime || '')));

            this._panelEl.innerHTML = `
                <div class="lgnewui-header-weather-sheet">
                    <div class="lgnewui-header-weather-hero">
                        <div class="lgnewui-header-weather-hero-icon">
                            <i class="qi-${iconCode}-fill"></i>
                        </div>
                        <div class="lgnewui-header-weather-hero-main">
                            <div class="lgnewui-header-weather-hero-title">
                                <span class="lgnewui-header-weather-hero-desc">${descText}</span>
                            </div>
                            <div class="lgnewui-header-weather-hero-sub">
                                <i class="ph ph-map-pin-area"></i>
                                <span>${cityText}</span>
                            </div>
                        </div>
                        <span class="lgnewui-header-weather-hero-temp">${tempText}</span>
                    </div>
                    <div class="lgnewui-header-weather-meta">
                        <div class="lgnewui-header-weather-meta-item">
                            <span class="lgnewui-header-weather-meta-icon"><i class="ph ph-thermometer-simple"></i></span>
                            <span class="lgnewui-header-weather-meta-copy">
                                <span class="lgnewui-header-weather-meta-label">体感</span>
                                <span class="lgnewui-header-weather-meta-value">${feelsText}</span>
                            </span>
                        </div>
                        <div class="lgnewui-header-weather-meta-item">
                            <span class="lgnewui-header-weather-meta-icon"><i class="ph ph-drop"></i></span>
                            <span class="lgnewui-header-weather-meta-copy">
                                <span class="lgnewui-header-weather-meta-label">湿度</span>
                                <span class="lgnewui-header-weather-meta-value">${humidityText}</span>
                            </span>
                        </div>
                        <div class="lgnewui-header-weather-meta-item">
                            <span class="lgnewui-header-weather-meta-icon"><i class="ph ph-wind"></i></span>
                            <span class="lgnewui-header-weather-meta-copy">
                                <span class="lgnewui-header-weather-meta-label">风向</span>
                                <span class="lgnewui-header-weather-meta-value">${windText}</span>
                            </span>
                        </div>
                        <div class="lgnewui-header-weather-meta-item">
                            <span class="lgnewui-header-weather-meta-icon"><i class="ph ph-eye"></i></span>
                            <span class="lgnewui-header-weather-meta-copy">
                                <span class="lgnewui-header-weather-meta-label">能见度</span>
                                <span class="lgnewui-header-weather-meta-value">${visText}</span>
                            </span>
                        </div>
                    </div>
                    <div class="lgnewui-header-weather-foot">
                        <span class="lgnewui-header-weather-updated">
                            <i class="ph ph-clock-countdown"></i>
                            <span>${updatedText}</span>
                        </span>
                        <span class="lgnewui-header-weather-note">
                            <i class="ph ph-cloud"></i>
                            <span>当前天气</span>
                        </span>
                    </div>
                </div>
            `;
        },

        _bindEvents() {
            this._handleToggleClick = (event) => {
                if (this._panelEl && this._panelEl.contains(event.target)) {
                    return;
                }
                event.stopPropagation();
                this._togglePanel();
            };
            this._handleDocumentClick = (event) => {
                if (!this._rootEl) {
                    return;
                }
                if (this._rootEl.contains(event.target) || (this._panelEl && this._panelEl.contains(event.target))) {
                    return;
                }
                this._setOpen(false);
            };
            this._handleKeydown = (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    this._togglePanel();
                } else if (event.key === 'Escape') {
                    this._setOpen(false);
                }
            };
            this._handleScroll = () => {
                this._setOpen(false);
            };
            this._handleResize = () => {
                this._setOpen(false);
            };

            this._rootEl.addEventListener('click', this._handleToggleClick);
            this._rootEl.addEventListener('keydown', this._handleKeydown);
            document.addEventListener('click', this._handleDocumentClick);
            window.addEventListener('scroll', this._handleScroll, { passive: true });
            window.addEventListener('resize', this._handleResize);
        },

        _unbindEvents() {
            if (this._rootEl && this._handleToggleClick) {
                this._rootEl.removeEventListener('click', this._handleToggleClick);
            }
            if (this._rootEl && this._handleKeydown) {
                this._rootEl.removeEventListener('keydown', this._handleKeydown);
            }
            if (this._handleDocumentClick) {
                document.removeEventListener('click', this._handleDocumentClick);
            }
            if (this._handleScroll) {
                window.removeEventListener('scroll', this._handleScroll);
            }
            if (this._handleResize) {
                window.removeEventListener('resize', this._handleResize);
            }
        },

        _togglePanel() {
            const isOpen = this._rootEl && this._rootEl.classList.contains('is-open');
            this._setOpen(!isOpen);
        },

        _setOpen(isOpen) {
            if (!this._rootEl || !this._panelEl) {
                return;
            }
            if (isOpen) {
                this._positionPanel();
            }
            this._rootEl.classList.toggle('is-open', !!isOpen);
            this._panelEl.classList.toggle('is-open', !!isOpen);
            this._rootEl.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            this._panelEl.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        },

        _positionPanel() {
            if (!this._rootEl || !this._panelEl) {
                return;
            }
            const rect = this._rootEl.getBoundingClientRect();
            const panelWidth = this._panelEl.offsetWidth || 288;
            const viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
            const maxLeft = Math.max(12, viewportWidth - panelWidth - 12);
            const left = Math.min(maxLeft, Math.max(12, rect.right - panelWidth));
            const top = rect.bottom + 18;

            this._panelEl.style.left = `${left}px`;
            this._panelEl.style.top = `${top}px`;
        },

        _createPanel() {
            let panel = document.getElementById('lgHeaderVisitorWeatherPanel');
            if (panel) {
                return panel;
            }

            panel = document.createElement('div');
            panel.id = 'lgHeaderVisitorWeatherPanel';
            panel.className = 'lgnewui-header-weather-panel';
            panel.setAttribute('aria-hidden', 'true');
            document.body.appendChild(panel);
            return panel;
        },

        _formatUpdateTime(isoText) {
            if (!isoText) {
                return '刚刚更新';
            }
            const match = isoText.match(/T(\d{2}:\d{2})/);
            if (match) {
                return `更新于 ${match[1]}`;
            }
            return '刚刚更新';
        },

        _escapeHtml(text) {
            return text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    };

    // ============================================
    // 暴露组件到 LGApp
    // ============================================
    if (window.LGApp) {
        window.LGApp.Components = {
            ConfettiEffect,
            Carousel,
                AvatarInteraction,
                MasonryGrid,
                VideoPlayer,
                Navigation,
                ScrollButtons,
                HeaderVisitorWeather
            };
        }

    // ============================================
    // Logo 首页图标交互
    // ============================================
    const LogoHomeIcon = {
        _el: null,
        _bound: false,

        init() {
            this._el = document.querySelector('.alogo');
            if (!this._el || this._bound) return;
            this._bound = true;

            const el = this._el;

            el.addEventListener('mouseenter', function () {
                el.classList.add('alogo--home-show');
            });

            el.addEventListener('mouseleave', function () {
                el.classList.remove('alogo--home-show', 'alogo--home-press');
            });

            el.addEventListener('mousedown', function (e) {
                if (e.button !== 0) return;
                el.classList.add('alogo--home-press');
            });

            el.addEventListener('mouseup', function () {
                el.classList.remove('alogo--home-press');
            });

            // 触摸设备
            el.addEventListener('touchstart', function () {
                el.classList.add('alogo--home-show');
            }, { passive: true });

            el.addEventListener('touchend', function () {
                setTimeout(function () {
                    el.classList.remove('alogo--home-show', 'alogo--home-press');
                }, 300);
            }, { passive: true });
        }
    };

    // 暴露到全局（兼容）
    window.LGComponents = {
        ConfettiEffect,
        Carousel,
        AvatarInteraction,
        MasonryGrid,
        VideoPlayer,
        Navigation,
        ScrollButtons,
        HeaderVisitorWeather,
        LogoHomeIcon
    };

})(window, jQuery);
