/**
 * withU 核心应用框架
 * @version 2.0.0
 * @description 统一管理配置、PJAX 生命周期、公共工具函数
 */

;(function(window, $) {
    'use strict';

    // ============================================
    // 全局配置对象（由 PHP 注入）
    // ============================================
    const WithUConfig = window.WITHU_CONFIG || {};
    const imageErrorFallback = WithUConfig.imageErrorFallback || ((WithUConfig.assetBase || '') + 'Style/img/file-placeholder.svg');

    // ============================================
    // 工具函数模块
    // ============================================
    const Utils = {
        /**
         * 防抖函数
         * @param {Function} func - 要执行的函数
         * @param {number} wait - 等待时间(ms)
         * @param {boolean} immediate - 是否立即执行
         * @returns {Function}
         */
        debounce(func, wait = 100, immediate = false) {
            let timeout = null;
            return function(...args) {
                const context = this;
                const later = () => {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                const callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        },

        /**
         * 节流函数
         * @param {Function} func - 要执行的函数
         * @param {number} limit - 时间间隔(ms)
         * @returns {Function}
         */
        throttle(func, limit = 100) {
            let inThrottle = false;
            return function(...args) {
                const context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        },

        /**
         * 安全的 requestAnimationFrame
         * @param {Function} callback
         * @returns {number}
         */
        raf(callback) {
            return (window.requestAnimationFrame || window.webkitRequestAnimationFrame || 
                    ((cb) => setTimeout(cb, 16)))(callback);
        },

        /**
         * 取消 requestAnimationFrame
         * @param {number} id
         */
        cancelRaf(id) {
            (window.cancelAnimationFrame || window.webkitCancelAnimationFrame || clearTimeout)(id);
        },

        /**
         * 检测是否为移动端
         * @returns {boolean}
         */
        isMobile() {
            return window.innerWidth <= 768;
        },

        /**
         * 安全获取 DOM 元素
         * @param {string} selector
         * @param {Element} context
         * @returns {Element|null}
         */
        $(selector, context = document) {
            return context.querySelector(selector);
        },

        /**
         * 安全获取多个 DOM 元素
         * @param {string} selector
         * @param {Element} context
         * @returns {NodeList}
         */
        $$(selector, context = document) {
            return context.querySelectorAll(selector);
        },

        /**
         * 数字补零
         * @param {number} num
         * @returns {string}
         */
        padZero(num) {
            return num < 10 ? '0' + num : String(num);
        }
    };

    // ============================================
    // 事件管理器（防止重复绑定）
    // ============================================
    const EventManager = {
        _handlers: new Map(),
        _delegatedEvents: new Set(),

        /**
         * 绑定事件（自动处理 PJAX 场景）
         * @param {string} event - 事件名
         * @param {string} selector - 选择器（事件委托）
         * @param {Function} handler - 处理函数
         * @param {string} namespace - 命名空间
         */
        on(event, selector, handler, namespace = 'lg') {
            const key = `${namespace}.${event}.${selector}`;
            
            // 如果已存在，先移除
            if (this._handlers.has(key)) {
                this.off(event, selector, namespace);
            }

            // 使用事件委托绑定到 document
            const delegatedHandler = (e) => {
                const target = e.target.closest(selector);
                if (target) {
                    handler.call(target, e);
                }
            };

            $(document).on(`${event}.${namespace}`, selector, handler);
            this._handlers.set(key, { event, selector, handler, namespace });
        },

        /**
         * 移除事件
         * @param {string} event
         * @param {string} selector
         * @param {string} namespace
         */
        off(event, selector, namespace = 'lg') {
            const key = `${namespace}.${event}.${selector}`;
            if (this._handlers.has(key)) {
                $(document).off(`${event}.${namespace}`, selector);
                this._handlers.delete(key);
            }
        },

        /**
         * 清除指定命名空间的所有事件
         * @param {string} namespace
         */
        clearNamespace(namespace) {
            this._handlers.forEach((value, key) => {
                if (key.startsWith(`${namespace}.`)) {
                    $(document).off(`${value.event}.${namespace}`, value.selector);
                    this._handlers.delete(key);
                }
            });
        }
    };

    // ============================================
    // 单人模式访客精确定位
    // ============================================
    const VisitorGeoManager = {
        _initialized: false,

        init() {
            if (this._initialized || !WithUConfig.soloMode) {
                return;
            }

            this._initialized = true;
            this.reapplyToDom();

            const cacheApi = this._getCacheApi();
            const cached = cacheApi.getCached();
            if (cached) {
                this._applyResolvedGeo(cached, 'cache');
                return;
            }

            this._requestPreciseLocation();
        },

        reapplyToDom() {
            if (!WithUConfig.soloMode) {
                return;
            }

            const cacheApi = this._getCacheApi();
            const cached = cacheApi.getCached();
            if (cached) {
                this._updateSoloUi(cached);
            }
        },

        _getCacheApi() {
            if (window.WithUVisitorGeoCache) {
                return window.WithUVisitorGeoCache;
            }

            return {
                getCached() { return null; },
                save(payload) { return payload; }
            };
        },

        async _requestPreciseLocation() {
            if (!navigator.geolocation) {
                return;
            }

            let permissionState = 'prompt';
            if (navigator.permissions && typeof navigator.permissions.query === 'function') {
                try {
                    const result = await navigator.permissions.query({ name: 'geolocation' });
                    permissionState = result && result.state ? result.state : 'prompt';
                } catch (err) {}
            }

            if (permissionState === 'denied') {
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this._applyResolvedGeo({
                        lat: position.coords.latitude,
                        lng: position.coords.longitude,
                        ts: Date.now(),
                        city: ''
                    }, 'browser');
                },
                () => {},
                {
                    enableHighAccuracy: true,
                    timeout: 12000,
                    maximumAge: 0
                }
            );
        },

        _applyResolvedGeo(payload, source) {
            const cacheApi = this._getCacheApi();
            const normalized = cacheApi.save(payload);
            if (!normalized) {
                return;
            }

            this._updateSoloUi(normalized);

            window.dispatchEvent(new CustomEvent('lg:visitor-geo-ready', {
                detail: Object.assign({ source: source }, normalized)
            }));

            if (window.WithUMapData && typeof window.WithUMapData.fetchAll === 'function') {
                window.WithUMapData.fetchAll().catch((err) => {
                    console.error('[VisitorGeo] 地图数据刷新失败:', err);
                });
            }
        },

        _updateSoloUi(payload) {
            const distanceResult = this._buildDistanceResult(payload);
            if (!distanceResult) {
                return;
            }

            this._updatePresenceCard(payload, distanceResult);
            this._updateDistanceBubble(distanceResult);
        },

        _buildDistanceResult(payload) {
            const owner = WithUConfig.soloOwnerGeo || {};
            const ownerLat = Number(owner.lat);
            const ownerLng = Number(owner.lng);
            const visitorLat = Number(payload.lat);
            const visitorLng = Number(payload.lng);

            if (
                !isFinite(ownerLat) || !isFinite(ownerLng) ||
                !isFinite(visitorLat) || !isFinite(visitorLng)
            ) {
                return null;
            }

            const distanceKm = this._calculateDistanceKm(ownerLat, ownerLng, visitorLat, visitorLng);
            const distanceMeters = distanceKm * 1000;

            if (distanceMeters <= 500) {
                return {
                    mode: 'nearby',
                    label: '就在身边',
                    value: '',
                    unit: '',
                    icon: 'ph-fill ph-star',
                    text: '就在身边'
                };
            }

            if (distanceMeters < 1000) {
                const meterValue = String(Math.round(distanceMeters));
                return {
                    mode: 'meter',
                    label: '相距',
                    value: meterValue,
                    unit: 'm',
                    icon: 'ph-fill ph-navigation-arrow',
                    text: meterValue + 'm'
                };
            }

            const kmValue = Math.round(distanceKm * 10) / 10;
            return {
                mode: 'km',
                label: '相距',
                value: String(kmValue),
                unit: 'km',
                icon: 'ph-fill ph-navigation-arrow',
                text: String(kmValue) + 'km'
            };
        },

        _calculateDistanceKm(lat1, lng1, lat2, lng2) {
            const toRad = (deg) => deg * Math.PI / 180;
            const earthRadius = 6371;
            const dLat = toRad(lat2 - lat1);
            const dLng = toRad(lng2 - lng1);
            const a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
                + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2))
                * Math.sin(dLng / 2) * Math.sin(dLng / 2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            return earthRadius * c;
        },

        _buildSoloNote(distanceResult) {
            if (distanceResult.mode === 'nearby') {
                return '你现在就在站点主人附近，也许已经和同一片街景擦肩而过。';
            }

            if (distanceResult.mode === 'meter') {
                return '你与站点主人相距' + distanceResult.value + distanceResult.unit + '，这一刻已经近到只差几步路。';
            }

            const distanceKmValue = parseFloat(distanceResult.value || '0');
            const distanceText = distanceResult.text;

            if (distanceKmValue < 20) {
                return '你与站点主人相距' + distanceText + '，隔着一段日常通勤，也算在同一座城市的生活半径里。';
            }

            if (distanceKmValue < 100) {
                return '你与站点主人相距' + distanceText + '，地图上隔着几段路程，也算一次刚刚好的来访。';
            }

            return '你与站点主人相距' + distanceText + '，跨过山海点开这张地图，也算一种难得的相遇。';
        },

        _updatePresenceCard(payload, distanceResult) {
            const noteEl = document.querySelector('[data-solo-visitor-note]');
            if (noteEl) {
                noteEl.textContent = this._buildSoloNote(distanceResult);
            }

            const cityEl = document.querySelector('[data-solo-visitor-city]');
            if (cityEl) {
                cityEl.textContent = payload.city || '已获取精确坐标';
            }

            const stateEl = document.querySelector('[data-solo-visitor-state]');
            if (stateEl) {
                stateEl.classList.remove('is-offline', 'is-recent');
                stateEl.classList.add('is-online');
            }

            const stateTextEl = document.querySelector('[data-solo-visitor-state-text]');
            if (stateTextEl) {
                stateTextEl.textContent = '已授权定位';
            }
        },

        _updateDistanceBubble(distanceResult) {
            const bubble = document.querySelector('.distance-bubble[data-solo-visitor-distance="1"]');
            if (!bubble) {
                return;
            }

            const iconEl = bubble.querySelector('.distance-icon-box i');
            if (iconEl) {
                iconEl.className = distanceResult.icon;
            }

            const textEl = bubble.querySelector('.distance-text');
            if (!textEl) {
                return;
            }

            if (distanceResult.mode === 'nearby') {
                textEl.innerHTML = "<span class=\"km-value\" style=\"font-family:'Noto Serif SC',serif\">就在身边</span>";
                return;
            }

            textEl.innerHTML = ''
                + '<span class="distance-text-sm">' + distanceResult.label + '</span>'
                + '<span class="km-value">' + distanceResult.value + '</span>'
                + '<span class="distance-text-sm">' + distanceResult.unit + '</span>';
        }
    };

    // ============================================
    // 页面模块管理器
    // ============================================
    const ModuleManager = {
        _modules: new Map(),
        _initialized: new Set(),

        /**
         * 注册模块
         * @param {string} name - 模块名
         * @param {Object} module - 模块对象
         */
        register(name, module) {
            if (this._modules.has(name)) {
                console.warn(`[WithUApp] Module "${name}" already registered, will be overwritten.`);
            }
            this._modules.set(name, module);
        },

        /**
         * 初始化模块
         * @param {string} name
         */
        init(name) {
            const module = this._modules.get(name);
            if (!module) {
                console.warn(`[WithUApp] Module "${name}" not found.`);
                return;
            }

            // 先销毁再初始化（防止重复）
            if (this._initialized.has(name)) {
                this.destroy(name);
            }

            if (typeof module.init === 'function') {
                try {
                    module.init();
                    this._initialized.add(name);
                } catch (e) {
                    console.error(`[WithUApp] Module "${name}" init error:`, e);
                }
            }
        },

        /**
         * 销毁模块
         * @param {string} name
         */
        destroy(name) {
            const module = this._modules.get(name);
            if (module && typeof module.destroy === 'function') {
                try {
                    module.destroy();
                } catch (e) {
                    console.error(`[WithUApp] Module "${name}" destroy error:`, e);
                }
            }
            this._initialized.delete(name);
            EventManager.clearNamespace(name);
        },

        /**
         * 销毁所有已初始化的模块
         */
        destroyAll() {
            this._initialized.forEach(name => this.destroy(name));
        },

        /**
         * 根据当前页面自动初始化对应模块
         */
        initByPage() {
            const path = window.location.pathname;
            const page = path.split('/').pop() || 'index.php';
            const pageName = page.replace(/\.(php|html)$/, '');

            // 页面与模块的映射
            const pageModuleMap = {
                'index': 'index',
                'list': 'list',
                'timeline': 'timeline',
                'about': 'about',
                'leaving': 'leaving',
                'loveImg': 'loveImg',
                'little': 'little',
                'page': 'little',      // 文章详情使用 little 模块
                'imglist': 'loveImg'   // 相册详情使用 loveImg 模块
            };

            const moduleName = pageModuleMap[pageName];
            if (moduleName && this._modules.has(moduleName)) {
                this.init(moduleName);
            }
        }
    };

    // ============================================
    // 定时器管理器（防止内存泄漏）
    // ============================================
    const TimerManager = {
        _timers: new Map(),
        _intervals: new Map(),
        _rafs: new Map(),

        setTimeout(name, callback, delay) {
            this.clearTimeout(name);
            const id = setTimeout(callback, delay);
            this._timers.set(name, id);
            return id;
        },

        clearTimeout(name) {
            if (this._timers.has(name)) {
                clearTimeout(this._timers.get(name));
                this._timers.delete(name);
            }
        },

        setInterval(name, callback, delay) {
            this.clearInterval(name);
            const id = setInterval(callback, delay);
            this._intervals.set(name, id);
            return id;
        },

        clearInterval(name) {
            if (this._intervals.has(name)) {
                clearInterval(this._intervals.get(name));
                this._intervals.delete(name);
            }
        },

        requestAnimationFrame(name, callback) {
            this.cancelAnimationFrame(name);
            const id = Utils.raf(callback);
            this._rafs.set(name, id);
            return id;
        },

        cancelAnimationFrame(name) {
            if (this._rafs.has(name)) {
                Utils.cancelRaf(this._rafs.get(name));
                this._rafs.delete(name);
            }
        },

        clearAll() {
            this._timers.forEach((id) => clearTimeout(id));
            this._intervals.forEach((id) => clearInterval(id));
            this._rafs.forEach((id) => Utils.cancelRaf(id));
            this._timers.clear();
            this._intervals.clear();
            this._rafs.clear();
        }
    };


    // ============================================
    // Toast 封装（基于 Toastify.js + showScenario）
    // ============================================
    const Toast = {
        init() {
            // Toastify 无需全局配置初始化
        },

        success(message, title = '') {
            if (typeof Toastify !== 'undefined' && Toastify.showScenario) {
                Toastify.showScenario('success', { text: message });
            }
        },

        error(message, title = '') {
            if (typeof Toastify !== 'undefined' && Toastify.showScenario) {
                Toastify.showScenario('error', { text: message });
            }
        },

        warning(message, title = '') {
            if (typeof Toastify !== 'undefined' && Toastify.showScenario) {
                Toastify.showScenario('warning', { text: message });
            }
        },

        info(message, title = '') {
            if (typeof Toastify !== 'undefined' && Toastify.showScenario) {
                Toastify.showScenario('info', { text: message });
            }
        }
    };

    // ============================================
    // 懒加载管理
    // ============================================
    const LazyLoadManager = {
        _instance: null,
        _selector: '.photo_style, .aiv_touxiang, .MessageCard img, .Item_OwO img, .leav_card img, .sm-x-block img, .CarouselImage, .ConventionPhoto img, .avatarArea .avatarFrame, .grid-gallery img, .lazy',

        init() {
            if (typeof LazyLoad === 'undefined') return;
            
            this.destroy();
            this._instance = new LazyLoad({
                threshold: 0,
                elements_selector: this._selector,
                callback_loaded: (el) => {
                    el.classList.add('loaded');
                    // 瀑布流场景：图片加载完后触发 Masonry 重排
                    if (window.ImglistApp && window.ImglistApp.relayout) {
                        window.ImglistApp.relayout();
                    }
                },
                callback_error: (el) => {}
            });
            window.lazyLoadInstance = this._instance;
        },

        update() {
            if (this._instance) {
                try {
                    this._instance.update();
                } catch (e) {
                    // 实例可能已失效，重新初始化
                    this.init();
                }
            }
        },

        destroy() {
            if (this._instance) {
                try {
                    this._instance.destroy();
                } catch (e) {
                    // 忽略销毁时的错误（DOM 可能已被 PJAX 移除）
                }
                this._instance = null;
                window.lazyLoadInstance = null;
            }
        }
    };

    // ============================================
    // 高度调整管理
    // ============================================
    const HeightManager = {
        _config: {
            pcCarouselHeight: '',
            mobileCarouselHeight: '',
            pcPhotoCoverHeight: '',
            mobilePhotoCoverHeight: '',
            pcImgMaxHeight: '',
            mobileImgMaxHeight: ''
        },

        setConfig(config) {
            Object.assign(this._config, config);
        },

        adjustCarouselHeight() {
            const height = Utils.isMobile() 
                ? this._config.mobileCarouselHeight 
                : this._config.pcCarouselHeight;
            if (height) {
                $('#homePage').css('height', height);
            }
        },

        adjustPhotoCoverHeight() {
            const height = Utils.isMobile() 
                ? this._config.mobilePhotoCoverHeight 
                : this._config.pcPhotoCoverHeight;
            if (height) {
                $('.photo_bg').css('height', height);
            }
        },

        adjustImgMinHeight() {
            const height = Utils.isMobile() 
                ? this._config.mobileImgMaxHeight 
                : this._config.pcImgMaxHeight;
            if (height) {
                $('.gallery img').css('min-height', height);
                $('.withu-new-photo .bg-img').css('min-height', height);
            }
        },

        adjustAll() {
            this.adjustCarouselHeight();
            this.adjustPhotoCoverHeight();
            this.adjustImgMinHeight();
        }
    };

    // ============================================
    // 公共功能函数
    // ============================================
    const CommonFunctions = {
        /**
         * 设置当前页面 Tab 激活状态
         */
        setActiveTab() {
            const currentPage = window.location.pathname;
            const navLinks = document.querySelectorAll('.WithU_Tab_Item a');
            
            navLinks.forEach(link => {
                const linkPage = link.getAttribute('href');
                if (currentPage.includes(linkPage)) {
                    link.classList.add('TabActive');
                } else {
                    link.classList.remove('TabActive');
                }
            });
        },

        /**
         * 处理返回按钮逻辑
         */
        handlePreviousPage() {
            const currentURL = window.location.pathname;
            const previousPage = sessionStorage.getItem('previousPage');
            const previousScroll = sessionStorage.getItem('previousScroll');
            const $capsule = $('.withu-capsule-back');

            const pageName = currentURL.split('/').pop() || '';
            // 子页面白名单：这些页面显示顶部返回胶囊
            if (['page.php', 'album-detail.php', 'watch.php'].includes(pageName)) {
                // 子页面：激活胶囊，设置返回链接
                $capsule.addClass('subpage-back-ready');
                const homeUrl = (window.WITHU_CONFIG && window.WITHU_CONFIG.siteBase || '') + 'index.php';
                $capsule.find('.withu-capsule-back__prev').attr('href', previousPage || homeUrl);

                $capsule.find('.withu-capsule-back__prev').off('click.lg').on('click.lg', function() {
                    if (previousScroll) {
                        sessionStorage.setItem('restoreScroll', previousScroll);
                    }
                });
            } else {
                // 非子页面：彻底清除胶囊状态
                $capsule.removeClass('subpage-back-ready scroll-back-visible');
                // 同时清除 logo 隐藏状态
                $('.withu-header-left-avatar').removeClass('scroll-logo-hidden');

                const restoreScroll = sessionStorage.getItem('restoreScroll');
                if (restoreScroll) {
                    TimerManager.setTimeout('restoreScroll', () => {
                        window.scrollTo(0, parseInt(restoreScroll, 10));
                        sessionStorage.removeItem('restoreScroll');
                    }, 150);
                }
            }
        },

        /**
         * 移除 md-view 中的 br 标签
         */
        removeBrFromMdView() {
            const mdView = document.getElementById('md-view');
            if (mdView) {
                $(mdView).find('p br').remove();
            }
        },

        /**
         * 初始化折叠列表
         * @param {Element} container
         */
        initToggleList(container = document) {
            const $container = $(container);
            $container.find('.content_a ul').hide();
            
            $container.find('.img_list .cike').off('click.lg').on('click.lg', function() {
                const $currentUl = $(this).next('ul');
                if ($currentUl.is(':visible')) {
                    $currentUl.slideUp();
                } else {
                    $container.find('.img_list .cike').next('ul').slideUp();
                    $currentUl.slideDown();
                }
            });
        },

        /**
         * 初始化 Tooltip
         */
        initTooltip() {
            $(document).off('mouseenter.withuTooltip mouseleave.withuTooltip', '.left_info, .Message_Wrap .MessageCard .MsgFooter .InfoItem');
            
            $(document).on('mouseenter.withuTooltip', '.left_info, .Message_Wrap .MessageCard .MsgFooter .InfoItem', function() {
                const title = $(this).data('title');
                if (!title) return;

                const nowrap = $(this).data('nowrap');
                const className = nowrap === 1 ? 'tooltip Textnowrap' : 'tooltip';
                
                const $tooltip = $('<span>').addClass(className).text(title);
                $tooltip.appendTo($(this)).fadeIn(130);
            });

            $(document).on('mouseleave.withuTooltip', '.left_info, .Message_Wrap .MessageCard .MsgFooter .InfoItem', function() {
                $(this).find('.tooltip').fadeOut(80, function() {
                    $(this).remove();
                });
            });
        },

        /**
         * 初始化图片错误处理
         */
        initImageErrorHandler() {
            $(document).off('error.withuImg', '.photo_content img');
            $(document).on('error.withuImg', '.photo_content img', function() {
                $(this).attr('src', imageErrorFallback);
                $(this).addClass('loaded');
            });
        },

        /**
         * 初始化 ViewImage
         */
        initViewImage() {
            if (window.ViewImage) {
                ViewImage.init('.ConventionPhoto img, .grid-gallery img, #md-view img, .leav_card .aiv_qq img, img.photo_style, .withu-media img, .view-image-media');
            }
        },

        /**
         * 调整 iframe 高度
         */
        adjustIframeHeight() {
            const iframes = document.getElementsByTagName('iframe');
            for (let i = 0; i < iframes.length; i++) {
                const iframe = iframes[i];
                iframe.style.height = iframe.scrollWidth * 0.76 + 'px';
            }
        }
    };

    // ============================================
    // 倒计时模块
    // ============================================
    const CountdownTimer = {
        _startTime: null,
        _running: false,

        setStartTime(time) {
            this._startTime = new Date(time);
        },

        start() {
            if (this._running || !this._startTime || isNaN(this._startTime.getTime())) {
                return;
            }
            this._running = true;
            this._tick();
        },

        stop() {
            this._running = false;
            TimerManager.clearTimeout('countdown');
        },

        _tick() {
            if (!this._running) return;

            const now = new Date();
            const diff = now.getTime() - this._startTime.getTime();

            if (diff < 0) {
                TimerManager.setTimeout('countdown', () => this._tick(), 1000);
                return;
            }

            const msPerDay = 24 * 60 * 60 * 1000;
            const days = Math.floor(diff / msPerDay);
            const hours = Math.floor((diff % msPerDay) / (60 * 60 * 1000));
            const minutes = Math.floor((diff % (60 * 60 * 1000)) / (60 * 1000));
            const seconds = Math.floor((diff % (60 * 1000)) / 1000);

            this._updateDisplay('tian', days);
            this._updateDisplay('shi', hours);
            this._updateDisplay('fen', minutes);
            this._updateDisplay('miao', seconds);

            TimerManager.setTimeout('countdown', () => this._tick(), 1000);
        },

        _updateDisplay(id, value) {
            const el = document.getElementById(id);
            if (el) {
                el.textContent = Utils.padZero(value);
            }
        }
    };


    // ============================================
    // AOS 动画管理模块
    // ============================================
    const AOSManager = {
        _initialized: false,
        _config: null,

        /**
         * 初始化 AOS
         * @param {Object} config - 配置对象（从 PHP 注入的 WITHU_AOS_CONFIG）
         */
        init(config = null) {
            if (typeof AOS === 'undefined') {
                console.warn('[WithUApp] AOS library not loaded');
                return;
            }

            if (this._initialized) {
                this.refresh();
                return;
            }

            // 使用传入配置或全局配置
            this._config = config || window.WITHU_AOS_CONFIG || {};

            // 如果未启用，直接返回
            if (this._config.enabled === false) {
                return;
            }

            // 初始化 AOS
            AOS.init({
                duration: this._config.duration || 800,
                easing: this._config.easing || 'ease-out-cubic',
                once: this._config.once !== false,
                offset: this._config.offset || 50,
                disable: false,
                startEvent: 'DOMContentLoaded'
            });

            this._initialized = true;
            window.aosInitialized = true;
        },

        /**
         * 刷新 AOS（用于 PJAX 后）
         */
        refresh() {
            if (typeof AOS !== 'undefined' && this._initialized) {
                AOS.refresh();
            }
        },

        /**
         * 获取当前配置
         */
        getConfig() {
            return this._config;
        },

        /**
         * 检查是否已初始化
         */
        isInitialized() {
            return this._initialized;
        }
    };

    // ============================================
    // 移动端「更多」面板管理模块
    // ============================================
    const HeaderMorePanel = {
        _initialized: false,
        _panel: null,
        _btn: null,

        /**
         * 初始化移动端更多面板
         */
        init() {
            if (this._initialized) return;

            this._panel = document.getElementById('withuHeaderMorePanel');
            this._btn = document.getElementById('withuHeaderMoreBtn');

            if (!this._panel || !this._btn) return;

            // 打开面板
            this._btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.open();
            });

            // 关闭面板（点击遮罩或关闭按钮）
            this._panel.querySelectorAll('[data-close-panel]').forEach(el => {
                el.addEventListener('click', (e) => {
                    // 如果是功能按钮，先执行功能再关闭
                    const actionItem = el.closest('.withu-header-more-action-item');
                    if (actionItem) {
                        const id = actionItem.id;
                        if (id === 'withuMorePanelWeather') {
                            // 触发天气弹窗
                            const weatherBtn = document.getElementById('withuHeaderVisitorWeather');
                            if (weatherBtn) weatherBtn.click();
                        } else if (id === 'withuMorePanelMap') {
                            // 触发地图弹窗
                            const mapBtn = document.getElementById('withuMapOpenBtn');
                            if (mapBtn) mapBtn.click();
                        }
                    }
                    this.close();
                });
            });

            // ESC 键关闭
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this._panel.classList.contains('is-open')) {
                    this.close();
                }
            });

            // 同步天气信息到面板
            this._syncWeatherInfo();

            this._initialized = true;
        },

        /**
         * 打开面板
         */
        open() {
            if (!this._panel) return;
            this._panel.classList.add('is-open');
            document.body.style.overflow = 'hidden';
            // 重新渲染 Lucide 图标
            if (typeof lucide !== 'undefined') lucide.createIcons();
            // 同步天气
            this._syncWeatherInfo();
        },

        /**
         * 关闭面板
         */
        close() {
            if (!this._panel) return;
            this._panel.classList.remove('is-open');
            document.body.style.overflow = '';
        },

        /**
         * 同步天气信息到面板
         */
        _syncWeatherInfo() {
            const headerIcon = document.getElementById('withuHeaderVisitorWeatherIcon');
            const headerText = document.getElementById('withuHeaderVisitorWeatherText');
            const panelIcon = document.getElementById('withuMorePanelWeatherIcon');
            const panelText = document.getElementById('withuMorePanelWeatherText');

            if (headerIcon && panelIcon) {
                panelIcon.className = headerIcon.className;
            }
            if (headerText && panelText && headerText.textContent) {
                panelText.textContent = headerText.textContent;
            }
        },

        /**
         * 刷新（PJAX 后调用）
         */
        refresh() {
            this._syncWeatherInfo();
        }
    };

    // ============================================
    // 滚动监听模块
    // ============================================
    const ScrollWatcher = {
        _callbacks: new Map(),
        _ticking: false,
        _bound: false,

        /**
         * 添加滚动回调
         * @param {string} name
         * @param {Function} callback
         */
        add(name, callback) {
            this._callbacks.set(name, callback);
            this._bindIfNeeded();
        },

        /**
         * 移除滚动回调
         * @param {string} name
         */
        remove(name) {
            this._callbacks.delete(name);
        },

        _bindIfNeeded() {
            if (this._bound) return;
            this._bound = true;

            window.addEventListener('scroll', () => {
                if (!this._ticking) {
                    Utils.raf(() => {
                        const scrollTop = document.documentElement.scrollTop || document.body.scrollTop;
                        this._callbacks.forEach(callback => {
                            try {
                                callback(scrollTop);
                            } catch (e) {
                                console.error('[ScrollWatcher] Callback error:', e);
                            }
                        });
                        this._ticking = false;
                    });
                    this._ticking = true;
                }
            }, { passive: true });
        }
    };

    // ============================================
    // PJAX 生命周期管理
    // ============================================
    const PjaxLifecycle = {
        _beforeCallbacks: [],
        _afterCallbacks: [],

        /**
         * 注册 PJAX 开始前的回调
         * @param {Function} callback
         */
        onBefore(callback) {
            this._beforeCallbacks.push(callback);
        },

        /**
         * 注册 PJAX 完成后的回调
         * @param {Function} callback
         */
        onAfter(callback) {
            this._afterCallbacks.push(callback);
        },

        /**
         * 初始化 PJAX 监听
         */
        init() {
            $(document).off('pjax:start.withuApp').on('pjax:start.withuApp', () => {
                // 清理定时器
                TimerManager.clearAll();
                // 销毁所有模块
                ModuleManager.destroyAll();
                // 执行回调
                this._beforeCallbacks.forEach(cb => {
                    try { cb(); } catch (e) { console.error(e); }
                });
            });

            $(document).off('pjax:complete.withuApp').on('pjax:complete.withuApp', () => {
                // 重新初始化公共功能
                this._initCommon();
                // 根据页面初始化模块
                ModuleManager.initByPage();
                // 执行回调
                this._afterCallbacks.forEach(cb => {
                    try { cb(); } catch (e) { console.error(e); }
                });
            });
        },

        /**
         * 初始化公共功能
         */
        _initCommon() {
            Toast.init();
            LazyLoadManager.init();
            HeightManager.adjustAll();
            CommonFunctions.setActiveTab();
            CommonFunctions.handlePreviousPage();
            CommonFunctions.removeBrFromMdView();
            CommonFunctions.initToggleList();
            CommonFunctions.initTooltip();
            CommonFunctions.initViewImage();
            CommonFunctions.adjustIframeHeight();
            VisitorGeoManager.reapplyToDom();

            // Logo 首页图标交互
            if (window.WithUComponents && window.WithUComponents.LogoHomeIcon) {
                window.WithUComponents.LogoHomeIcon.init();
            }

            // 刷新 AOS
            AOSManager.refresh();

            // 代码高亮
            if (typeof hljs !== 'undefined') {
                hljs.initHighlightingOnLoad();
            }
        }
    };

    // ============================================
    // 主应用对象
    // ============================================
    const WithUApp = {
        // 版本号
        version: '2.0.0',

        // 暴露子模块
        Utils,
        EventManager,
        ModuleManager,
        TimerManager,
        Toast,
        LazyLoadManager,
        HeightManager,
        CommonFunctions,
        CountdownTimer,
        ScrollWatcher,
        PjaxLifecycle,
        AOSManager,
        VisitorGeoManager,

        // 配置
        config: WithUConfig,

        /**
         * 设置配置
         * @param {Object} config
         */
        setConfig(config) {
            Object.assign(this.config, config);
            HeightManager.setConfig(config);
            if (config.startTime) {
                CountdownTimer.setStartTime(config.startTime);
            }
        },

        /**
         * 注册页面模块
         * @param {string} name
         * @param {Object} module
         */
        register(name, module) {
            ModuleManager.register(name, module);
        },

        /**
         * 初始化应用
         */
        init() {
            // 初始化 Toast
            Toast.init();

            // 初始化懒加载
            LazyLoadManager.init();

            // 初始化公共功能
            CommonFunctions.setActiveTab();
            CommonFunctions.handlePreviousPage();
            CommonFunctions.removeBrFromMdView();
            CommonFunctions.initToggleList();
            CommonFunctions.initTooltip();
            CommonFunctions.initImageErrorHandler();
            CommonFunctions.initViewImage();
            CommonFunctions.adjustIframeHeight();

            // 初始化高度调整
            HeightManager.adjustAll();

            // Logo 首页图标交互
            if (window.WithUComponents && window.WithUComponents.LogoHomeIcon) {
                window.WithUComponents.LogoHomeIcon.init();
            }

            // 初始化 AOS 动画
            AOSManager.init();

            // 单人模式访客定位
            VisitorGeoManager.init();

            // 移动端「更多」面板
            HeaderMorePanel.init();

            // 窗口调整时重新计算高度
            const debouncedAdjust = Utils.debounce(() => {
                HeightManager.adjustAll();
                AOSManager.refresh();
            }, 100);
            $(window).on('resize.withuApp', debouncedAdjust);

            // 启动倒计时
            CountdownTimer.start();

            // 初始化 PJAX 生命周期
            PjaxLifecycle.init();

            // 根据当前页面初始化模块
            ModuleManager.initByPage();

            // 处理浏览器后退/前进缓存
            window.addEventListener('pageshow', (event) => {
                if (event.persisted) {
                    AOSManager.refresh();
                    LazyLoadManager.init();
                }
            });

            // 页面可见性变化时的处理
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    // 页面隐藏时可以暂停某些操作
                } else {
                    // 页面显示时恢复
                    LazyLoadManager.update();
                }
            });

            // 控制台信息
            this._printConsoleInfo();
        },

        /**
         * 打印控制台信息
         */
        _printConsoleInfo() {
            const version = this.config.version || '2.0';
            console.log('%c未经授权 盗版必究 禁止抄袭 感谢配合', 'color:#fadfa3;background:#333;padding:8px 15px;');
            console.log('%c购买地址: https://blog.kikiw.cn/index.php/archives/65/', 'color:#fff;background-image:linear-gradient(to right,#1FA2FF 0%,#fdfdfd 21%,#fafafa 100%);padding:8px 15px;border-radius:5px;');
            console.log('%cContact Me: 3439780232 | mail@kikiw.cn', 'color:#fff;background:#000;padding:8px 15px;border-radius:10px');
            console.log(`%cwithU ${version} | Powered by Ki`, 'color:#fff;background:linear-gradient(to right,hsl(206.57deg 100% 61.11%) 0%,hsl(57deg 100% 85.15%) 100%);padding:8px 15px;border-radius:15px');
            console.log('%cDependencies: Plyr | PJAX | Masonry | QRCodeStyling | AOS | Fancybox', 'color:#fff;background:#475569;padding:8px 15px;border-radius:10px');
        }
    };

    // ============================================
    // 暴露到全局
    // ============================================
    window.WithUApp = WithUApp;

    // 兼容旧代码的全局函数
    window.adjustCarouselHeight = () => HeightManager.adjustCarouselHeight();
    window.adjustPhotoCoverHeight = () => HeightManager.adjustPhotoCoverHeight();
    window.adjustImgMinHeight = () => HeightManager.adjustImgMinHeight();
    window.IsCurrentURL = () => CommonFunctions.handlePreviousPage();
    window.setActiveTab = () => CommonFunctions.setActiveTab();
    window.removeBrFromMdView = () => CommonFunctions.removeBrFromMdView();
    window.initToggleList = (container) => CommonFunctions.initToggleList(container);
    window.show_date_time = () => CountdownTimer.start();

})(window, jQuery);
