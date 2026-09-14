/**
 * LG_NewUI 首页模块
 * @version 2.0.0
 * @description index.php 页面专属 JS 逻辑
 */

;(function(window, $) {
    'use strict';

    const { Utils, TimerManager } = window.LGApp || {};
    const config = window.LG_CONFIG || {};

    // ============================================
    // 倒计时模块
    // ============================================
    const CountdownModule = {
        _els: null,
        _startDate: null,
        _timerId: null,

        init() {
            this._els = {
                days: document.getElementById('lgnewui-day-counter-days'),
                hours: document.getElementById('lgnewui-day-counter-hours'),
                minutes: document.getElementById('lgnewui-day-counter-minutes'),
                seconds: document.getElementById('lgnewui-day-counter-seconds')
            };

            if (!this._els.days) return;

            // 从全局配置获取开始时间
            const startTime = config.startTime;
            if (startTime) {
                this._startDate = new Date(startTime);
                this._tick();
                TimerManager.setInterval('indexCountdown', () => this._tick(), 1000);
            }
        },

        destroy() {
            TimerManager.clearInterval('indexCountdown');
        },

        _tick() {
            if (!this._startDate || !this._els.days) return;

            const now = new Date();
            const diff = now - this._startDate;

            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff / (1000 * 60 * 60)) % 24);
            const minutes = Math.floor((diff / 1000 / 60) % 60);
            const seconds = Math.floor((diff / 1000) % 60);

            // 天/时/分：只更新文字，不触发动画（减少 DOM 操作）
            this._setText(this._els.days, days);
            this._setText(this._els.hours, hours.toString().padStart(2, '0'));
            this._setText(this._els.minutes, minutes.toString().padStart(2, '0'));
            // 秒：带动画更新
            this._updateValue(this._els.seconds, seconds.toString().padStart(2, '0'));
        },

        /**
         * 静默更新文字（无动画）
         */
        _setText(el, newValue) {
            if (!el) return;
            const newStr = newValue.toString();
            if (el.innerText !== newStr) {
                el.innerText = newStr;
            }
        },

        _updateValue(el, newValue) {
            if (!el) return;
            const newStr = newValue.toString();
            if (el.innerText !== newStr) {
                el.innerText = newStr;
                // 用 rAF 双帧技巧重置动画，避免 void el.offsetWidth 强制回流
                el.classList.remove('lgnewui-day-anim-active');
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        el.classList.add('lgnewui-day-anim-active');
                    });
                });
            }
        }
    };

    // ============================================
    // Love Day 筛选模块
    // ============================================
    const LoveDayFilter = {
        init() {
            this._initSliderPosition();
            this._bindEvents();
        },

        _bindEvents() {
            // 使用事件委托
            $(document).off('click.loveDayFilter', '.lgnewui-ios-tab');
            $(document).on('click.loveDayFilter', '.lgnewui-ios-tab', (e) => {
                const btn = e.currentTarget;
                const type = btn.dataset.filter;
                this.filter(type, btn);
            });
        },

        _initSliderPosition() {
            const activeTab = document.querySelector('.lgnewui-ios-tab.active');
            const slider = document.querySelector('.lgnewui-ios-tabs-slider');
            if (activeTab && slider) {
                slider.style.width = activeTab.offsetWidth + 'px';
                slider.style.transform = 'translateX(' + activeTab.offsetLeft + 'px)';
            }
        },

        filter(type, btn) {
            const tabs = document.querySelectorAll('.lgnewui-ios-tab');
            const slider = document.querySelector('.lgnewui-ios-tabs-slider');

            // 更新激活状态
            tabs.forEach(tab => tab.classList.remove('active'));
            btn.classList.add('active');

            // 移动滑块（transform 配合 CSS 弹性曲线）
            if (slider) {
                slider.style.width = btn.offsetWidth + 'px';
                slider.style.transform = 'translateX(' + btn.offsetLeft + 'px)';
            }

            // 筛选项目（骨架卡片过渡）
            const items = document.querySelectorAll('.lgnewui-widget--loveday-vibrant');
            let visibleIndex = 0;

            items.forEach(item => {
                const isPast = item.classList.contains('lgnewui-widget--loveday-past');
                const isFuture = item.classList.contains('lgnewui-widget--loveday-future');
                const wrapper = item.closest('[data-aos]') || item;

                let show = false;
                if (type === 'all') show = true;
                else if (type === 'past' && isPast) show = true;
                else if (type === 'future' && isFuture) show = true;

                if (show) {
                    wrapper.style.display = '';
                    // 先显示骨架态
                    item.classList.add('lgnewui-skeleton-card');
                    // 插入圆角骨架条
                    var bars = item.querySelector('.lgnewui-skeleton-bars');
                    if (!bars) {
                        bars = document.createElement('div');
                        bars.className = 'lgnewui-skeleton-bars';
                        bars.innerHTML = '<div class="sk-left"><div class="sk-bar sk-title"></div><div class="sk-bar sk-date"></div></div><div class="sk-right"><div class="sk-bar sk-num"></div><div class="sk-bar sk-unit"></div></div>';
                        item.appendChild(bars);
                    }
                    wrapper.classList.add('aos-animate');
                    // 延迟后移除骨架，恢复真实内容（保证最低可感知时间）
                    setTimeout(() => {
                        item.classList.remove('lgnewui-skeleton-card');
                        var b = item.querySelector('.lgnewui-skeleton-bars');
                        if (b) b.remove();
                    }, 450 + visibleIndex * 80);
                    visibleIndex++;
                } else {
                    wrapper.style.display = 'none';
                }
            });
        }
    };


    // ============================================
    // 数字计数动画模块
    // ============================================
    const CountUpAnimation = {
        _observer: null,
        // 开关：window.LG_COUNTUP_ENABLED = false 可关闭，默认开启
        _enabled: false,

        init() {
            // 读取全局开关
            if (typeof window.LG_COUNTUP_ENABLED !== 'undefined') {
                this._enabled = !!window.LG_COUNTUP_ENABLED;
            }
            if (!this._enabled) {
                return;
            }

            this._observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const el = entry.target;
                        const targetText = el.textContent.replace(/,/g, '');
                        const target = parseInt(targetText);

                        if (!isNaN(target) && !el.classList.contains('counted')) {
                            this._animate(el, 0, target, 2000);
                            el.classList.add('counted');
                        }
                    }
                });
            }, { threshold: 0.5 });

            // 观察所有统计数字元素
            document.querySelectorAll('.lgnewui-stats-num, .lgnewui-runtime-num, .lgnewui-num-huge span:first-child').forEach(el => {
                this._observer.observe(el);
            });
        },

        destroy() {
            if (this._observer) {
                this._observer.disconnect();
                this._observer = null;
            }
        },

        _animate(obj, start, end, duration) {
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                obj.textContent = Math.floor(progress * (end - start) + start).toLocaleString();
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                } else {
                    obj.textContent = end.toLocaleString();
                }
            };
            window.requestAnimationFrame(step);
        }
    };

    // ============================================
    // 时光碎片卡片 (MomentCard)
    // ============================================
    class MomentCard {
        constructor(containerId, data = []) {
            this.container = document.getElementById(containerId);
            if (!this.container) return;

            this.data = data;
            this.pool = [];
            this.currentId = null;
            this.isAnimating = false;

            // DOM 元素缓存
            this.mediaContainer = this.container.querySelector('.lgnewui-smart-card__media');
            this.switchBtn = this.container.querySelector('.lgnewui-smart-card__switch-btn');
            this.elAvatar = this.container.querySelector('.lgnewui-smart-card__avatar');
            this.elName = this.container.querySelector('.lgnewui-smart-card__name');
            this.elTime = this.container.querySelector('.lgnewui-smart-card__time');
            this.elLocPill = this.container.querySelector('.lgnewui-smart-card__location-pill');
            this.elLocText = this.container.querySelector('.lgnewui-smart-card__location-text');
            this.elTitle = this.container.querySelector('.lgnewui-smart-card__title');
            this.elDate = this.container.querySelector('.lgnewui-smart-card__date');
            this.elDesc = this.container.querySelector('.lgnewui-smart-card__desc');
            this.elAlbumLink = this.container.querySelector('.lgnewui-smart-card__album-link');

            this.animDuration = 1200;

            this._initPool();
            this._bindEvents();
            this.next();

            // 少于 2 条时禁用刷新按钮
            if (this.data.length < 2 && this.switchBtn) {
                this.switchBtn.disabled = true;
                this.switchBtn.style.opacity = '0.3';
                this.switchBtn.style.cursor = 'not-allowed';
                this.switchBtn.title = '仅有一条记录';
            }

            if (window.lazyLoadInstance) {
                window.lazyLoadInstance.update();
            }
        }

        _initPool() {
            this.pool = this.data.map(item => item.id);
        }

        _bindEvents() {
            if (this.switchBtn) {
                this.switchBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.next();
                });
            }
            // 视频播放按钮：事件委托
            if (this.mediaContainer) {
                this.mediaContainer.addEventListener('click', (e) => {
                    const playBtn = e.target.closest('.lgnewui-smart-card__play-btn');
                    if (playBtn && window.VideoModal) {
                        e.preventDefault();
                        e.stopPropagation();
                        window.VideoModal.open(
                            playBtn.getAttribute('data-video-url'),
                            playBtn.getAttribute('data-video-cover') || ''
                        );
                    }
                });
            }
        }

        next() {
            if (this.isAnimating || this.data.length === 0) return;

            if (this.pool.length === 0) {
                this._initPool();
                if (this.pool.length > 1 && this.currentId) {
                    this.pool = this.pool.filter(id => id !== this.currentId);
                }
            }

            const randomIndex = Math.floor(Math.random() * this.pool.length);
            const nextId = this.pool[randomIndex];
            this.pool.splice(randomIndex, 1);

            const item = this.data.find(d => d.id === nextId);
            if (!item) return;

            this.currentId = nextId;
            this._animateSwitch(item);
        }

        _animateSwitch(item) {
            this.isAnimating = true;
            if (this.switchBtn) this.switchBtn.classList.add('loading');

            const newLayer = document.createElement('div');
            newLayer.className = 'lgnewui-smart-card__media-layer lgnewui-smart-card__anim-enter';

            if (item.type === 'video') {
                const coverSrc = item.video_cover || item.url || '';
                newLayer.innerHTML = `<img data-src="${coverSrc}" alt="${item.title || ''}" class="lgnewui-smart-card__media-layer lazy">` +
                    `<button class="lgnewui-smart-card__play-btn" data-video-url="${item.original || ''}" data-video-cover="${coverSrc}">` +
                    `<i class="ph-fill ph-play"></i></button>`;
            } else {
                newLayer.innerHTML = `<img data-src="${item.url}" data-original="${item.original}" alt="${item.title || ''}" class="lgnewui-smart-card__media-layer lazy view-image-media">`;
            }

            const oldLayer = this.mediaContainer.lastElementChild;
            if (oldLayer) {
                oldLayer.className = 'lgnewui-smart-card__media-layer lgnewui-smart-card__anim-exit';
            }

            this.mediaContainer.appendChild(newLayer);

            if (window.lazyLoadInstance) {
                window.lazyLoadInstance.update();
            }

            this._updateText(item);

            setTimeout(() => {
                if (oldLayer) oldLayer.remove();
                this.isAnimating = false;
                if (this.switchBtn) this.switchBtn.classList.remove('loading');
            }, this.animDuration);
        }

        _updateText(item) {
            if (this.elAvatar && item.publisher?.avatar) {
                this.elAvatar.src = item.publisher.avatar;
                this.elAvatar.classList.add('loaded');
            }
            if (this.elName && item.publisher?.name) {
                this.elName.textContent = item.publisher.name;
            }
            if (this.elTime) {
                this.elTime.textContent = item.publishTime || '刚刚';
            }
            // 更新相册链接
            if (this.elAlbumLink && item.img_code) {
                this.elAlbumLink.href = 'album-detail.php?code=' + item.img_code;
            }

            const animElements = [this.elTitle, this.elDate, this.elDesc];
            animElements.forEach(el => {
                if (!el) return;
                el.classList.remove('lgnewui-smart-card__text-stagger');
                el.style.opacity = '0';
            });

            // 重置 location pill 动画
            if (this.elLocPill) {
                this.elLocPill.classList.remove('lgnewui-smart-card__loc-anim');
                this.elLocPill.style.opacity = '0';
                this.elLocPill.style.transform = 'scale(0.8)';
            }

            if (this.elTitle) this.elTitle.textContent = item.title || '';

            if (item.location) {
                if (this.elLocText) this.elLocText.textContent = item.location;
                if (this.elLocPill) this.elLocPill.classList.add('active');
            } else {
                if (this.elLocPill) this.elLocPill.classList.remove('active');
            }

            if (item.date) {
                if (this.elDate) this.elDate.textContent = item.date;
                if (this.elDate) this.elDate.classList.add('active');
            } else {
                if (this.elDate) this.elDate.classList.remove('active');
            }

            if (item.description) {
                if (this.elDesc) this.elDesc.textContent = item.description;
                if (this.elDesc) this.elDesc.style.display = 'block';
            } else {
                if (this.elDesc) this.elDesc.style.display = 'none';
            }

            // 顺序动画：title → location → date → desc
            if (this.elTitle) {
                setTimeout(() => {
                    this.elTitle.style.opacity = '';
                    this.elTitle.classList.add('lgnewui-smart-card__text-stagger');
                }, 50);
            }

            if (item.location && this.elLocPill) {
                setTimeout(() => {
                    this.elLocPill.style.opacity = '';
                    this.elLocPill.style.transform = '';
                    this.elLocPill.classList.add('lgnewui-smart-card__loc-anim');
                }, 120);
            }

            if (item.date && this.elDate) {
                setTimeout(() => {
                    this.elDate.style.opacity = '';
                    this.elDate.classList.add('lgnewui-smart-card__text-stagger');
                }, 180);
            }

            if (item.description && this.elDesc) {
                setTimeout(() => {
                    this.elDesc.style.opacity = '';
                    this.elDesc.classList.add('lgnewui-smart-card__text-stagger');
                }, 240);
            }
        }

        setData(newData) {
            this.data = newData;
            this._initPool();
        }
    }


    // ============================================
    // Epilogue 结语模块
    // ============================================
    const EpilogueModule = {
        _quotes: [],
        _currentIndex: -1,
        _quoteEl: null,
        _apiEndpoint: 'services/random_quote.php',
        _loading: false,

        init() {
            this._quoteEl = document.getElementById('epilogue-quote-text');
            const btnRefresh = document.getElementById('epilogue-btn-refresh');
            const btnCopy = document.getElementById('epilogue-btn-copy');
            const btnRandomAlbum = document.getElementById('epilogue-random-album');
            const btnRandomArticle = document.getElementById('epilogue-random-article');

            if (!this._quoteEl) return;

            // 从 API 加载文案
            this._fetchQuote(true);

            // 绑定事件
            if (btnRefresh) {
                btnRefresh.addEventListener('click', () => this._fetchQuote(false));
            }
            if (btnCopy) {
                btnCopy.addEventListener('click', () => this._copyText());
            }
            if (btnRandomAlbum) {
                btnRandomAlbum.addEventListener('click', () => this._randomAlbum());
            }
            if (btnRandomArticle) {
                btnRandomArticle.addEventListener('click', () => this._randomArticle());
            }
            const btnLeaving = document.getElementById('epilogue-leaving-btn');
            if (btnLeaving) {
                btnLeaving.addEventListener('click', () => this._openLeavingModal());
            }
        },

        async _fetchQuote(isInitial = false) {
            if (this._loading || !this._quoteEl) return;
            this._loading = true;

            try {
                const res = await fetch(this._apiEndpoint);
                const data = await res.json();
                const text = (data && data.text) ? data.text : '未完待续，敬请期待下一章的精彩。';
                this._applyQuote(text, isInitial);
            } catch (e) {
                this._applyQuote('未完待续，敬请期待下一章的精彩。', isInitial);
            } finally {
                this._loading = false;
            }
        },

        _applyQuote(text, isInitial = false) {
            if (!this._quoteEl) return;

            if (isInitial) {
                this._quoteEl.textContent = text;
                return;
            }

            this._quoteEl.classList.add('switching');
            setTimeout(() => {
                this._quoteEl.textContent = text;
                this._quoteEl.classList.remove('switching');
            }, 600);
        },

        _openLeavingModal() {
            const mesBtn = document.getElementById('mes');
            if (mesBtn) {
                mesBtn.click();
            } else {
                this._pjaxNavigate((window.LG_CONFIG && window.LG_CONFIG.siteBase || '') + 'messages.php');
            }
        },

        _copyText() {
            if (!this._quoteEl) return;
            if (window.LGClipboard) {
                LGClipboard.copy(this._quoteEl.textContent, { success: '已复制到剪贴板' });
            }
        },

        async _randomAlbum() {
            try {
                const base = (window.LG_CONFIG && window.LG_CONFIG.siteBase) || '';
                const res = await fetch(base + 'services/random_album.php');
                const data = await res.json();
                if (data && data.code === 200 && data.img_code) {
                    this._pjaxNavigate(base + 'album-detail.php?code=' + encodeURIComponent(data.img_code));
                } else {
                    if (typeof Toastify !== 'undefined') {
                        Toastify.showScenario('info', { text: data.msg || '暂无可用相册' });
                    }
                }
            } catch (e) {
                this._pjaxNavigate((window.LG_CONFIG && window.LG_CONFIG.siteBase || '') + 'albums.php');
            }
        },

        _pjaxNavigate(url) {
            const a = document.createElement('a');
            a.href = url;
            a.style.display = 'none';
            document.body.appendChild(a);
            a.click();
            a.remove();
        },

        async _randomArticle() {
            try {
                const base = (window.LG_CONFIG && window.LG_CONFIG.siteBase) || '';
                const res = await fetch(base + 'services/random_article.php');
                const data = await res.json();
                if (data && data.code === 200 && data.id) {
                    this._pjaxNavigate(base + 'page.php?id=' + data.id);
                } else {
                    if (typeof Toastify !== 'undefined') {
                        Toastify.showScenario('info', { text: data.msg || '暂无可用文章' });
                    }
                }
            } catch (e) {
                this._pjaxNavigate((window.LG_CONFIG && window.LG_CONFIG.siteBase || '') + 'articles.php');
            }
        }
    };

    // ============================================
    // 天气卡片模块
    // ============================================
    const WeatherModule = {
        _config: {
            apiBase: 'services/weather.php'
        },
        _refreshTimer: null,

        init() {
            if (config.weatherEnabled === false) {
                return;
            }

            const weatherCards = document.querySelectorAll('.lgnewui-home-weather-card');
            if (!weatherCards.length) {
                return;
            }

            this._refreshAll();
            // 每5分钟自动刷新
            TimerManager.setInterval('weatherRefresh', () => this._refreshAll(), 5 * 60 * 1000);
        },

        destroy() {
            TimerManager.clearInterval('weatherRefresh');
        },

        _buildUrl(cardEl) {
            const base = (window.LG_CONFIG && window.LG_CONFIG.siteBase) || '';
            const u = new URL(base + this._config.apiBase, window.location.origin);
            const slot = cardEl.getAttribute('data-weather-slot') || '1';
            u.searchParams.set('mode', 'couple');
            u.searchParams.set('slot', slot);
            const wt = (window.LG_CONFIG && window.LG_CONFIG.weatherToken) || '';
            if (wt) u.searchParams.set('_wt', wt);
            return u.toString();
        },

        _getRelativeTime(iso) {
            if (!iso) return '--';
            const now = new Date();
            const updateTime = new Date(iso);
            const diffMs = now - updateTime;
            const diffMins = Math.floor(diffMs / 60000);

            if (diffMins < 1) return '刚刚';
            if (diffMins < 60) return `${diffMins}分钟前`;

            const hours = updateTime.getHours().toString().padStart(2, '0');
            const mins = updateTime.getMinutes().toString().padStart(2, '0');
            return `${hours}:${mins}`;
        },

        async _fetchNow(cardEl) {
            const url = this._buildUrl(cardEl);
            const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
            const data = await resp.json().catch(() => null);
            if (!data || data.code !== 200 || !data.data) throw new Error(data ? `Err: ${data.code}` : 'No Data');
            return data.data;
        },

        _renderCard(cardEl, payload) {
            const timeTag = cardEl.querySelector('.lgnewui-home-weather-time-tag');
            if (timeTag) timeTag.textContent = this._getRelativeTime(payload.obsTime);

            const tempEl = cardEl.querySelector('.lgnewui-home-weather-text-temp');
            if (tempEl) tempEl.textContent = (payload.temp ?? '--') + '°';

            const cityName = payload.city || cardEl.getAttribute('data-location-name') || '--';
            const statusText = payload.desc || '--';
            const cityEl = cardEl.querySelector('.lgnewui-home-weather-text-city');
            const statusEl = cardEl.querySelector('.lgnewui-home-weather-text-status');
            if (cityEl) cityEl.textContent = cityName;
            if (statusEl) statusEl.textContent = statusText;

            const iconEl = cardEl.querySelector('.lgnewui-home-weather-icon-main');
            const iconCode = payload.icon || '999';
            if (iconEl) {
                iconEl.className = `qi-${iconCode}-fill lgnewui-home-weather-icon-main`;
            }

            const humidityEl = cardEl.querySelector('.stat-humidity');
            const visEl = cardEl.querySelector('.stat-vis');
            const feelsEl = cardEl.querySelector('.stat-feels');

            if (humidityEl) humidityEl.textContent = (payload.humidity ?? '--') + '%';
            if (visEl) visEl.textContent = (payload.vis ?? '--') + 'km';
            if (feelsEl) feelsEl.textContent = (payload.feelsLike ?? '--') + '°';
        },

        async _updateCard(cardEl) {
            if (!cardEl) return false;

            try {
                const data = await this._fetchNow(cardEl);
                this._renderCard(cardEl, data);
                return true;
            } catch (e) {
                console.error('Weather update failed:', e);
                const statusEl = cardEl.querySelector('.lgnewui-home-weather-text-status');
                if (statusEl) statusEl.textContent = '离线';
                return false;
            }
        },

        async _refreshAll() {
            const weatherCards = document.querySelectorAll('.lgnewui-home-weather-card');
            await Promise.all(Array.from(weatherCards).map(card => this._updateCard(card)));
        }
    };


    // ============================================
    // 首页主模块
    // ============================================
    const IndexModule = {
        _initialized: false,
        _momentCard: null,

        init() {
            if (this._initialized) return;

            // 初始化倒计时
            CountdownModule.init();

            // 初始化 Love Day 筛选
            LoveDayFilter.init();

            // 初始化数字动画
            CountUpAnimation.init();

            // 初始化结语模块
            EpilogueModule.init();

            // 初始化天气卡片
            WeatherModule.init();

            // 初始化时光碎片卡片
            this._initMomentCard();

            // 锁定卡片随机弥散光斑
            this._initLockedCardGlow();

            // 留言滚动卡片
            this._messageScroller = new LgNewUiHomeMessageScroller('#messageCarousel', { speed: 0.8 });

            this._initialized = true;
        },

        destroy() {
            CountdownModule.destroy();
            CountUpAnimation.destroy();
            WeatherModule.destroy();
            this._initialized = false;
        },

        _initLockedCardGlow() {
            var cards = document.querySelectorAll('.lgnewui-event-card--locked');
            var colors = [
                'rgba(196, 181, 253, 0.22)', // 淡紫
                'rgba(147, 197, 253, 0.22)',  // 淡蓝
                'rgba(249, 168, 212, 0.20)',  // 淡粉
                'rgba(253, 186, 116, 0.18)',  // 淡橙
                'rgba(134, 239, 172, 0.18)',  // 淡绿
                'rgba(165, 180, 252, 0.20)',  // 靛蓝
            ];
            function pick() { return colors[Math.floor(Math.random() * colors.length)]; }
            cards.forEach(function(card) {
                var c1 = pick(), c2 = pick();
                while (c2 === c1) c2 = pick();
                card.style.setProperty('--glow-tl', c1);
                card.style.setProperty('--glow-br', c2);
            });
        },

        _initMomentCard() {
            // 从 API 加载相册数据
            const base = (config && config.siteBase) || '';
            fetch(base + 'services/moments.php')
                .then(response => {
                    if (!response.ok) throw new Error('网络响应失败');
                    return response.json();
                })
                .then(data => {
                    if (!Array.isArray(data) || data.length === 0) {
                        const placeholderData = [{
                            id: 1,
                            type: 'image',
                            url: config.femaleAvatar || config.maleAvatar || '',
                            original: '',
                            img_code: '',
                            publisher: {
                                name: config.maleName || '\u6211\u4eec',
                                avatar: config.maleAvatar || ''
                            },
                            publishTime: '\u7b49\u5f85\u56de\u5fc6...',
                            location: '',
                            date: '',
                            title: '\u65f6\u5149\u788e\u7247',
                            description: '\u8fd9\u91cc\u5c06\u8bb0\u5f55\u4f60\u4eec\u7684\u7f8e\u597d\u77ac\u95f4'
                        }];
                        this._momentCard = new MomentCard('moment-card', placeholderData);
                        return;
                    }
                    this._momentCard = new MomentCard('moment-card', data);
                })
                .catch(error => {
                    console.error('\u52a0\u8f7d\u65f6\u5149\u788e\u7247\u5931\u8d25:', error);
                    const fallbackData = [{
                        id: 1,
                        type: 'image',
                        url: config.maleAvatar || '',
                        original: '',
                        img_code: '',
                        publisher: { name: config.maleName || '\u6211\u4eec', avatar: config.maleAvatar || '' },
                        publishTime: '\u56de\u5fc6\u4e2d...',
                        location: '',
                        date: '',
                        title: '\u65f6\u5149\u788e\u7247',
                        description: '\u7f51\u7edc\u5c0f\u5dee\uff0c\u8bf7\u7a0d\u540e\u91cd\u8bd5'
                    }];
                    this._momentCard = new MomentCard('moment-card', fallbackData);
                });
        }
    };

    // ============================================
    // 留言无缝滚动组件
    // ============================================
    class LgNewUiHomeMessageScroller {
        constructor(containerSelector, options) {
            options = options || {};
            this.container = document.querySelector(containerSelector);
            if (!this.container) return;
            this.track = this.container.querySelector('.lgnewui-home-message-track');
            if (!this.track) return;

            this.speed = options.speed || 0.8;
            this.originalCards = Array.from(this.track.children);
            this.clonedCards = [];
            this.progress = 0;
            this.isHovering = false;
            this.isDragging = false;
            this.startX = 0;
            this.startProgress = 0;
            this.dragDistance = 0;
            this.shiftDistance = 0;
            this.animationFrameId = null;
            this._paused = false;

            // 用箭头函数绑定 this，避免每帧创建新函数
            this._animateBound = () => {
                if (this._paused) return;
                if (!this.isDragging && !this.isHovering) {
                    this.progress += this.speed;
                }
                if (this.shiftDistance > 0) {
                    if (this.progress >= this.shiftDistance) {
                        this.progress -= this.shiftDistance;
                    } else if (this.progress < 0) {
                        this.progress += this.shiftDistance;
                    }
                }
                this.track.style.transform = 'translate3d(' + (-this.progress) + 'px,0,0)';
                this.animationFrameId = requestAnimationFrame(this._animateBound);
            };

            // 具名引用，便于 destroy 时移除
            this._onPointerMove = (e) => {
                if (!this.isDragging) return;
                var dx = this.startX - e.clientX;
                this.dragDistance = Math.abs(dx);
                this.progress = this.startProgress + dx;
            };
            this._onPointerUp = () => { if (this.isDragging) this.isDragging = false; };
            this._isInViewport = true;
            this._onVisibilityChange = () => {
                this._updatePaused();
            };

            this._init();
        }

        _updatePaused() {
            var shouldPause = document.hidden || !this._isInViewport;
            if (shouldPause && !this._paused) {
                this._paused = true;
                if (this.animationFrameId) { cancelAnimationFrame(this.animationFrameId); this.animationFrameId = null; }
            } else if (!shouldPause && this._paused) {
                this._paused = false;
                this.animationFrameId = requestAnimationFrame(this._animateBound);
            }
        }

        _init() {
            this.originalCards.forEach(card => {
                const clone = card.cloneNode(true);
                clone.setAttribute('aria-hidden', 'true');
                this.track.appendChild(clone);
                this.clonedCards.push(clone);
            });

            this.resizeObserver = new ResizeObserver(() => {
                if (this.originalCards.length > 0 && this.clonedCards.length > 0) {
                    this.shiftDistance = this.clonedCards[0].offsetLeft - this.originalCards[0].offsetLeft;
                }
            });
            this.resizeObserver.observe(this.track);

            // 视口检测：离开视口暂停 RAF
            this._intersectionObserver = new IntersectionObserver((entries) => {
                this._isInViewport = entries[0].isIntersecting;
                this._updatePaused();
            }, { threshold: 0 });
            this._intersectionObserver.observe(this.container);

            this._bindEvents();
            this.animationFrameId = requestAnimationFrame(this._animateBound);
        }

        _bindEvents() {
            this.container.addEventListener('pointerenter', () => { this.isHovering = true; });
            this.container.addEventListener('pointerleave', () => { this.isHovering = false; });

            this.container.addEventListener('pointerdown', (e) => {
                if (e.pointerType === 'mouse' && e.button !== 0) return;
                this.isDragging = true;
                this.dragDistance = 0;
                this.startX = e.clientX;
                this.startProgress = this.progress;
            });

            window.addEventListener('pointermove', this._onPointerMove);
            window.addEventListener('pointerup', this._onPointerUp);
            window.addEventListener('pointercancel', this._onPointerUp);
            document.addEventListener('visibilitychange', this._onVisibilityChange);

            this.track.addEventListener('click', (e) => {
                if (this.dragDistance > 5) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            });
        }

        destroy() {
            this._paused = true;
            if (this.animationFrameId) { cancelAnimationFrame(this.animationFrameId); this.animationFrameId = null; }
            if (this.resizeObserver) this.resizeObserver.disconnect();
            if (this._intersectionObserver) this._intersectionObserver.disconnect();
            window.removeEventListener('pointermove', this._onPointerMove);
            window.removeEventListener('pointerup', this._onPointerUp);
            window.removeEventListener('pointercancel', this._onPointerUp);
            document.removeEventListener('visibilitychange', this._onVisibilityChange);
        }
    }

    // ============================================
    // 注册到 LGApp
    // ============================================
    if (window.LGApp) {
        window.LGApp.register('index', IndexModule);
    }

    // 暴露到全局
    window.LGIndexModule = IndexModule;

    // 兼容旧代码的全局函数
    window.filterLoveDays = (type, btn) => {
        LoveDayFilter.filter(type, btn);
    };

    window.initLoveDaySlider = () => {
        LoveDayFilter._initSliderPosition();
    };

})(window, jQuery);
