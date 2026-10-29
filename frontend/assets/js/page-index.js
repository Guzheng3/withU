/**
 * withU 首页模块
 * @version 2.0.0
 * @description index.php 页面专属 JS 逻辑
 */

;(function(window, $) {
    'use strict';

    const { Utils, TimerManager } = window.WithUApp || {};
    const config = window.WITHU_CONFIG || {};

    // ============================================
    // 倒计时模块
    // ============================================
    const CountdownModule = {
        _els: null,
        _startDate: null,
        _timerId: null,

        init() {
            this._els = {
                days: document.getElementById('withu-day-counter-days'),
                hours: document.getElementById('withu-day-counter-hours'),
                minutes: document.getElementById('withu-day-counter-minutes'),
                seconds: document.getElementById('withu-day-counter-seconds'),
                startDate: document.getElementById('withu-day-start-date-display')
            };

            if (!this._els.days) return;

            // 从全局配置获取开始时间
            const startTime = config.startTime;
            if (startTime) {
                this._startDate = new Date(startTime);
                this._syncStartDateLabel();
                this._tick();
                TimerManager.setInterval('indexCountdown', () => this._tick(), 1000);
            }
        },

        /**
         * 让「Together Since」起始日文案与倒计时使用同一后端时间源，
         * 避免后端修改 love_date 后卡片日期与倒计时不一致
         */
        _syncStartDateLabel() {
            if (!this._els.startDate || !this._startDate) return;
            const pad = (n) => String(n).padStart(2, '0');
            const d = this._startDate;
            const text = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
            if (this._els.startDate.innerText !== text) {
                this._els.startDate.innerText = text;
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
            if (el.textContent === newStr) return;

            // 用户偏好减少动态：直接静默更新，不做滚动动画
            if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                this._setText(el, newStr);
                return;
            }

            // 首次包裹滚动容器（overflow: hidden 裁剪从下方滚入的数字）
            let wrap = el.parentElement;
            if (!wrap || !wrap.classList.contains('withu-day-timer-roll')) {
                wrap = document.createElement('span');
                wrap.className = 'withu-day-timer-roll';
                el.parentNode.insertBefore(wrap, el);
                wrap.appendChild(el);
            }

            // 旧数字向上滚出（绝对定位叠加，不占布局流）
            const exitEl = el.cloneNode(false);
            exitEl.removeAttribute('id');
            exitEl.setAttribute('aria-hidden', 'true');
            exitEl.textContent = el.textContent;
            exitEl.classList.add('withu-day-roll-exit');
            wrap.appendChild(exitEl);

            // 新数字从下方滚入
            el.textContent = newStr;
            el.classList.remove('withu-day-roll-in');
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    el.classList.add('withu-day-roll-in');
                });
            });

            // 动画结束后清理离场元素（不依赖 animationend）
            window.setTimeout(() => {
                if (exitEl.parentNode) exitEl.parentNode.removeChild(exitEl);
                el.classList.remove('withu-day-roll-in');
            }, 420);
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
            $(document).off('click.loveDayFilter', '.withu-ios-tab');
            $(document).on('click.loveDayFilter', '.withu-ios-tab', (e) => {
                const btn = e.currentTarget;
                const type = btn.dataset.filter;
                this.filter(type, btn);
            });
        },

        _initSliderPosition() {
            const activeTab = document.querySelector('.withu-ios-tab.active');
            const slider = document.querySelector('.withu-ios-tabs-slider');
            if (activeTab && slider) {
                slider.style.width = activeTab.offsetWidth + 'px';
                slider.style.transform = 'translateX(' + activeTab.offsetLeft + 'px)';
            }
        },

        filter(type, btn) {
            const tabs = document.querySelectorAll('.withu-ios-tab');
            const slider = document.querySelector('.withu-ios-tabs-slider');

            // 更新激活状态
            tabs.forEach(tab => tab.classList.remove('active'));
            btn.classList.add('active');

            // 移动滑块（transform 配合 CSS 弹性曲线）
            if (slider) {
                slider.style.width = btn.offsetWidth + 'px';
                slider.style.transform = 'translateX(' + btn.offsetLeft + 'px)';
            }

            // 筛选项目（骨架卡片过渡）
            const items = document.querySelectorAll('.withu-widget--loveday-vibrant');
            let visibleIndex = 0;

            items.forEach(item => {
                const isPast = item.classList.contains('withu-widget--loveday-past');
                const isFuture = item.classList.contains('withu-widget--loveday-future');
                const wrapper = item.closest('[data-aos]') || item;

                let show = false;
                if (type === 'all') show = true;
                else if (type === 'past' && isPast) show = true;
                else if (type === 'future' && isFuture) show = true;

                if (show) {
                    wrapper.style.display = '';
                    // 先显示骨架态
                    item.classList.add('withu-skeleton-card');
                    // 插入圆角骨架条
                    var bars = item.querySelector('.withu-skeleton-bars');
                    if (!bars) {
                        bars = document.createElement('div');
                        bars.className = 'withu-skeleton-bars';
                        bars.innerHTML = '<div class="sk-left"><div class="sk-bar sk-title"></div><div class="sk-bar sk-date"></div></div><div class="sk-right"><div class="sk-bar sk-num"></div><div class="sk-bar sk-unit"></div></div>';
                        item.appendChild(bars);
                    }
                    wrapper.classList.add('aos-animate');
                    // 延迟后移除骨架，恢复真实内容（保证最低可感知时间）
                    setTimeout(() => {
                        item.classList.remove('withu-skeleton-card');
                        var b = item.querySelector('.withu-skeleton-bars');
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
        // 开关：window.WITHU_COUNTUP_ENABLED = false 可关闭，默认开启
        _enabled: false,

        init() {
            // 读取全局开关
            if (typeof window.WITHU_COUNTUP_ENABLED !== 'undefined') {
                this._enabled = !!window.WITHU_COUNTUP_ENABLED;
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
            document.querySelectorAll('.withu-stats-num, .withu-runtime-num, .withu-num-huge span:first-child').forEach(el => {
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
            this.mediaContainer = this.container.querySelector('.withu-smart-card__media');
            this.switchBtn = this.container.querySelector('.withu-smart-card__switch-btn');
            this.elAvatar = this.container.querySelector('.withu-smart-card__avatar');
            this.elName = this.container.querySelector('.withu-smart-card__name');
            this.elTime = this.container.querySelector('.withu-smart-card__time');
            this.elLocPill = this.container.querySelector('.withu-smart-card__location-pill');
            this.elLocText = this.container.querySelector('.withu-smart-card__location-text');
            this.elTitle = this.container.querySelector('.withu-smart-card__title');
            this.elDate = this.container.querySelector('.withu-smart-card__date');
            this.elDesc = this.container.querySelector('.withu-smart-card__desc');
            this.elAlbumLink = this.container.querySelector('.withu-smart-card__album-link');

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
                    const playBtn = e.target.closest('.withu-smart-card__play-btn');
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
            newLayer.className = 'withu-smart-card__media-layer withu-smart-card__anim-enter';

            if (item.type === 'video') {
                const coverSrc = item.video_cover || item.url || '';
                newLayer.innerHTML = `<img data-src="${coverSrc}" alt="${item.title || ''}" class="withu-smart-card__media-layer lazy">` +
                    `<button class="withu-smart-card__play-btn" data-video-url="${item.original || ''}" data-video-cover="${coverSrc}">` +
                    `<i class="ph-fill ph-play"></i></button>`;
            } else {
                newLayer.innerHTML = `<img data-src="${item.url}" data-original="${item.original}" alt="${item.title || ''}" class="withu-smart-card__media-layer lazy view-image-media">`;
            }

            const oldLayer = this.mediaContainer.lastElementChild;
            if (oldLayer) {
                oldLayer.className = 'withu-smart-card__media-layer withu-smart-card__anim-exit';
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
                el.classList.remove('withu-smart-card__text-stagger');
                el.style.opacity = '0';
            });

            // 重置 location pill 动画
            if (this.elLocPill) {
                this.elLocPill.classList.remove('withu-smart-card__loc-anim');
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
                    this.elTitle.classList.add('withu-smart-card__text-stagger');
                }, 50);
            }

            if (item.location && this.elLocPill) {
                setTimeout(() => {
                    this.elLocPill.style.opacity = '';
                    this.elLocPill.style.transform = '';
                    this.elLocPill.classList.add('withu-smart-card__loc-anim');
                }, 120);
            }

            if (item.date && this.elDate) {
                setTimeout(() => {
                    this.elDate.style.opacity = '';
                    this.elDate.classList.add('withu-smart-card__text-stagger');
                }, 180);
            }

            if (item.description && this.elDesc) {
                setTimeout(() => {
                    this.elDesc.style.opacity = '';
                    this.elDesc.classList.add('withu-smart-card__text-stagger');
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
                this._renderQuote(text);
                return;
            }

            this._quoteEl.classList.add('switching');
            setTimeout(() => {
                this._renderQuote(text);
                this._quoteEl.classList.remove('switching');
            }, 600);
        },

        // 正文与署名分成两段：正文居中，署名单独成行右对齐，避免署名被折行断开。
        _renderQuote(text) {
            const el = this._quoteEl;
            if (!el) return;
            el.textContent = '';

            const dash = text.lastIndexOf('——');
            const author = dash > 0 ? text.slice(dash + 2) : '';
            // 仅当结尾是「——短署名」时才拆行，避免把正文中的破折号误判为署名
            const hasAuthor = dash > 0 && author.length > 0 && author.length <= 30;

            const body = document.createElement('span');
            body.className = 'withu-quote__body';
            body.textContent = hasAuthor ? text.slice(0, dash) : text;
            el.appendChild(body);

            if (hasAuthor) {
                const cite = document.createElement('span');
                cite.className = 'withu-quote__author';
                cite.textContent = '——' + author;
                el.appendChild(cite);
            }
        },

        _openLeavingModal() {
            const mesBtn = document.getElementById('mes');
            if (mesBtn) {
                mesBtn.click();
            } else {
                this._pjaxNavigate((window.WITHU_CONFIG && window.WITHU_CONFIG.siteBase || '') + 'messages.php');
            }
        },

        _copyText() {
            if (!this._quoteEl) return;
            if (window.WithUClipboard) {
                WithUClipboard.copy(this._quoteEl.textContent, { success: '已复制到剪贴板' });
            }
        },

        async _randomAlbum() {
            try {
                const base = (window.WITHU_CONFIG && window.WITHU_CONFIG.siteBase) || '';
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
                this._pjaxNavigate((window.WITHU_CONFIG && window.WITHU_CONFIG.siteBase || '') + 'albums.php');
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
                const base = (window.WITHU_CONFIG && window.WITHU_CONFIG.siteBase) || '';
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
                this._pjaxNavigate((window.WITHU_CONFIG && window.WITHU_CONFIG.siteBase || '') + 'articles.php');
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
        _cardEventsBound: false,
        _modalEl: null,
        _sheetEl: null,
        _activeCard: null,
        _lastFocus: null,
        _forecastRequestId: 0,
        _cloudMapState: null,

        init() {
            if (config.weatherEnabled === false) {
                return;
            }

            const weatherCards = document.querySelectorAll('.withu-home-weather-card');
            if (!weatherCards.length) {
                return;
            }

            this._bindCards(weatherCards);
            this._refreshAll();
            // 每5分钟自动刷新
            TimerManager.setInterval('weatherRefresh', () => this._refreshAll(), 5 * 60 * 1000);

            // 双方实时 GPS 上报是异步的：上报成功后强制重拉位置并刷新天气
            this._geoReportedHandler = () => {
                if (!window.WithULiveGeo) {
                    this._refreshAll();
                    return;
                }
                window.WithULiveGeo.refresh(true)
                    .then(() => this._refreshAll())
                    .catch(() => this._refreshAll());
            };
            window.addEventListener('withu:geo-reported', this._geoReportedHandler);

            // 首次加载时若已有实时位置上报，先拉一次再渲染
            if (window.WithULiveGeo) {
                window.WithULiveGeo.refresh()
                    .then(() => this._refreshAll())
                    .catch(() => {});
            }
        },

        destroy() {
            TimerManager.clearInterval('weatherRefresh');
            this.closeDetail();
            this._forecastRequestId++;
            if (this._geoReportedHandler) {
                window.removeEventListener('withu:geo-reported', this._geoReportedHandler);
                this._geoReportedHandler = null;
            }
        },

        _buildUrl(cardEl) {
            const cfg = window.WITHU_CONFIG || {};
            const base = cfg.siteBase || '';
            const u = new URL(base + this._config.apiBase, window.location.origin);
            const slot = cardEl.getAttribute('data-weather-slot') || '1';
            u.searchParams.set('mode', 'couple');
            u.searchParams.set('slot', slot);

            // 坐标优先级：双方实时 GPS 上报 > 地图情侣坐标 > 后台「位置」兜底
            const liveCoords = (window.WithULiveGeo && window.WithULiveGeo.slotCoords(slot)) || null;
            const lover = this._getCardLover(cardEl);
            const mapCoords = (lover && Array.isArray(lover.coords) && lover.coords.length >= 2)
                ? lover.coords : null;
            const adminCoords = (liveCoords || mapCoords) ? null : this._getAdminCoords();
            const coords = liveCoords || mapCoords || adminCoords;
            if (coords && coords.length >= 2) {
                u.searchParams.set('lng', coords[0]);
                u.searchParams.set('lat', coords[1]);
            }

            // 实时 GPS 坐标交给服务端按坐标反查真实城市；固定坐标才使用预设地名
            if (!liveCoords) {
                const fixedName = (mapCoords && lover && lover.label)
                    || (adminCoords && typeof cfg.weatherLocName === 'string' ? cfg.weatherLocName.trim() : '')
                    || '';
                if (fixedName) u.searchParams.set('label', fixedName);
            }
            return u.toString();
        },

        _getAdminCoords() {
            const cfg = window.WITHU_CONFIG || {};
            const hasCoords = cfg.weatherLocLat !== null && cfg.weatherLocLat !== undefined && cfg.weatherLocLat !== ''
                && cfg.weatherLocLng !== null && cfg.weatherLocLng !== undefined && cfg.weatherLocLng !== '';
            if (!hasCoords) return null;
            const lat = Number(cfg.weatherLocLat);
            const lng = Number(cfg.weatherLocLng);
            if (!isFinite(lat) || !isFinite(lng)) return null;
            return [lng, lat];
        },

        // 通过卡片头像文件名匹配 map-all.json 中的lover（头像与坐标一一对应，不受昵称影响）
        _getCardLover(cardEl) {
            const mapCfg = window.WITHU_MAP_CONFIG || {};
            const lovers = Array.isArray(mapCfg.lovers) ? mapCfg.lovers : [];
            if (!lovers.length) return null;
            const img = cardEl.querySelector('.withu-home-weather-avatar');
            const src = img ? (img.getAttribute('src') || '') : '';
            const file = src.split('/').pop().split('?')[0];
            if (!file) return null;
            return lovers.find(l => String(l.avatar || '').split('/').pop().split('?')[0] === file) || null;
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
            const resp = await fetch(url, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
            if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
            const data = await resp.json().catch(() => null);
            if (!data || data.code !== 200 || !data.data) throw new Error(data ? `Err: ${data.code}` : 'No Data');
            return data.data;
        },

        // 天气未获取到时卡片保留在原位，只重置为诚实的空态（--），不编造数据
        _renderUnavailable(cardEl) {
            if (!cardEl) return;
            cardEl._wuWeatherData = null;
            const timeTag = cardEl.querySelector('.withu-home-weather-time-tag');
            if (timeTag) timeTag.textContent = '--';

            const tempEl = cardEl.querySelector('.withu-home-weather-text-temp');
            if (tempEl) tempEl.textContent = '--°';

            const statusEl = cardEl.querySelector('.withu-home-weather-text-status');
            if (statusEl) statusEl.textContent = '未获取';

            const iconEl = cardEl.querySelector('.withu-home-weather-icon-main');
            if (iconEl) iconEl.className = 'qi-999-fill withu-home-weather-icon-main';

            const humidityEl = cardEl.querySelector('.stat-humidity');
            const visEl = cardEl.querySelector('.stat-vis');
            const feelsEl = cardEl.querySelector('.stat-feels');
            if (humidityEl) humidityEl.textContent = '--';
            if (visEl) visEl.textContent = '--';
            if (feelsEl) feelsEl.textContent = '--';

            const cityEl = cardEl.querySelector('.withu-home-weather-text-city');
            if (cityEl) cityEl.textContent = '--';
        },

        _renderCard(cardEl, payload) {
            if (cardEl) cardEl._wuWeatherData = payload || null;
            if (!payload || payload.available === false || payload.temp === '--') {
                this._renderUnavailable(cardEl);
                return;
            }
            const timeTag = cardEl.querySelector('.withu-home-weather-time-tag');
            if (timeTag) timeTag.textContent = this._getRelativeTime(payload.obsTime);

            const tempEl = cardEl.querySelector('.withu-home-weather-text-temp');
            if (tempEl) tempEl.textContent = (payload.temp ?? '--') + '°';

            const statusText = payload.desc || '--';
            const statusEl = cardEl.querySelector('.withu-home-weather-text-status');
            if (statusEl) statusEl.textContent = statusText;

            const iconEl = cardEl.querySelector('.withu-home-weather-icon-main');
            const iconCode = payload.icon || '999';
            if (iconEl) {
                iconEl.className = `qi-${iconCode}-fill withu-home-weather-icon-main`;
            }

            const humidityEl = cardEl.querySelector('.stat-humidity');
            const visEl = cardEl.querySelector('.stat-vis');
            const feelsEl = cardEl.querySelector('.stat-feels');

            if (humidityEl) humidityEl.textContent = (payload.humidity ?? '--') + '%';
            if (visEl) visEl.textContent = (payload.vis ?? '--') + 'km';
            if (feelsEl) feelsEl.textContent = (payload.feelsLike ?? '--') + '°';

            // 城市名：天气接口返回的地名优先（后台固定位置已作为 label 传入），否则回退后台位置名
            const cityEl = cardEl.querySelector('.withu-home-weather-text-city');
            if (cityEl) {
                const fixedName = (window.WITHU_CONFIG && typeof window.WITHU_CONFIG.weatherLocName === 'string')
                    ? window.WITHU_CONFIG.weatherLocName.trim() : '';
                cityEl.textContent = payload.city || fixedName || '--';
            }
        },

        async _updateCard(cardEl) {
            if (!cardEl) return false;

            try {
                const data = await this._fetchNow(cardEl);
                this._renderCard(cardEl, data);
                return true;
            } catch (e) {
                console.error('Weather update failed:', e);
                // 请求失败：卡片保留，重置为诚实空态
                this._renderUnavailable(cardEl);
                return false;
            }
        },

        /* ---------- 天气详情弹层 ---------- */
        _escapeHtml(text) {
            return String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        },

        _bindCards(cards) {
            Array.prototype.forEach.call(cards, (card) => {
                card.setAttribute('role', 'button');
                card.setAttribute('tabindex', '0');
                card.setAttribute('aria-haspopup', 'dialog');
                const nameEl = card.querySelector('.withu-home-weather-username');
                const name = nameEl ? nameEl.textContent.trim() : '';
                card.setAttribute('aria-label', `查看${name ? name + '的' : ''}天气详情`);
            });

            if (this._cardEventsBound) return;
            this._cardEventsBound = true;

            this._onCardActivate = (event) => {
                const card = event.target && event.target.closest
                    ? event.target.closest('.withu-home-weather-card')
                    : null;
                if (!card) return;
                if (event.type === 'keydown') {
                    if (event.key !== 'Enter' && event.key !== ' ') return;
                    event.preventDefault();
                }
                this.openDetail(card);
            };

            document.addEventListener('click', this._onCardActivate);
            document.addEventListener('keydown', this._onCardActivate);
        },

        _ensureModal() {
            if (this._modalEl) return this._modalEl;

            const modal = document.createElement('div');
            modal.className = 'wu-weather-modal';
            modal.id = 'wuWeatherModal';
            modal.setAttribute('aria-hidden', 'true');
            modal.innerHTML = `
                <div class="wu-weather-modal__backdrop" data-wu-weather-close></div>
                <div class="wu-weather-modal__sheet" role="dialog" aria-modal="true" aria-label="天气详情" tabindex="-1"></div>
            `;
            document.body.appendChild(modal);

            this._modalEl = modal;
            this._sheetEl = modal.querySelector('.wu-weather-modal__sheet');

            modal.addEventListener('click', (event) => {
                if (event.target.closest('[data-wu-weather-close]')) {
                    this.closeDetail();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (!this._modalEl || !this._modalEl.classList.contains('is-open')) return;
                if (event.key === 'Escape') {
                    event.preventDefault();
                    this.closeDetail();
                    return;
                }
                if (event.key !== 'Tab') return;
                const focusables = this._sheetEl.querySelectorAll(
                    'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
                );
                if (!focusables.length) return;
                const first = focusables[0];
                const last = focusables[focusables.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            });

            return modal;
        },

        openDetail(card) {
            if (!card) return;
            const modal = this._ensureModal();
            this._activeCard = card;
            this._lastFocus = document.activeElement;

            const current = this._readCardData(card);
            const isOrange = card.classList.contains('orange');
            this._sheetEl.classList.toggle('wu-weather-modal__sheet--orange', isOrange);
            this._sheetEl.classList.toggle('wu-weather-modal__sheet--blue', !isOrange);
            this._renderShell(current);

            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.documentElement.classList.add('wu-weather-modal-open');

            const closeBtn = this._sheetEl.querySelector('.wu-weather-modal__close');
            if (closeBtn) closeBtn.focus({ preventScroll: true });

            this._initCloudMap(current);
            this._loadForecast(card, current);
            this._loadHistory(card);
        },

        closeDetail() {
            if (!this._modalEl) return;
            this._stopCloudMap();
            this._forecastRequestId++;
            this._modalEl.classList.remove('is-open');
            this._modalEl.setAttribute('aria-hidden', 'true');
            document.documentElement.classList.remove('wu-weather-modal-open');

            const focusTarget = this._activeCard || this._lastFocus;
            this._activeCard = null;
            if (focusTarget && document.contains(focusTarget)) {
                try { focusTarget.focus({ preventScroll: true }); } catch (e) { /* ignore */ }
            }
        },

        _readCardData(card) {
            const payload = card._wuWeatherData || {};
            const text = (sel) => {
                const el = card.querySelector(sel);
                return el ? el.textContent.trim() : '';
            };
            const iconEl = card.querySelector('.withu-home-weather-icon-main');
            const iconMatch = iconEl ? String(iconEl.className).match(/qi-[0-9]+-fill/) : null;
            const dash = (value) => value === undefined || value === null || value === '' || value === '--';
            const num = (value) => (dash(value) || isNaN(Number(value))) ? null : Number(value);

            return {
                city: !dash(payload.city) ? payload.city : (text('.withu-home-weather-text-city') || card.getAttribute('data-location-name') || '当前位置'),
                temp: !dash(payload.temp) ? `${payload.temp}°` : (text('.withu-home-weather-text-temp') || '--°'),
                desc: !dash(payload.desc) ? payload.desc : (text('.withu-home-weather-text-status') || '天气数据暂不可用'),
                icon: iconMatch ? iconMatch[0] : 'qi-999-fill',
                feels: !dash(payload.feelsLike) ? `${payload.feelsLike}°` : (text('.stat-feels') || '--'),
                humidity: !dash(payload.humidity) ? `${payload.humidity}%` : (text('.stat-humidity') || '--'),
                vis: !dash(payload.vis) ? `${payload.vis} km` : (text('.stat-vis') || '--'),
                wind: !dash(payload.windDir)
                    ? `${payload.windDir}${!dash(payload.windScale) ? ' ' + payload.windScale + '级' : ''}`
                    : '—',
                obsTime: payload.obsTime || '',
                updated: text('.withu-home-weather-time-tag') || '--',
                cloud: num(payload.cloud),
                precip: num(payload.precip)
            };
        },

        _renderShell(current) {
            this._stopCloudMap();
            const dateText = this._formatModalDate(current.obsTime, current.updated);
            const tempValue = String(current.temp).replace('°', '');
            const icon = this._escapeHtml(current.icon);

            this._sheetEl.innerHTML = `
                <header class="wu-weather-modal__top">
                    <div>
                        <p class="wu-weather-modal__city"><i class="ph-fill ph-map-pin"></i><span>${this._escapeHtml(current.city)}</span></p>
                        <p class="wu-weather-modal__date">${this._escapeHtml(dateText)}</p>
                    </div>
                    <button type="button" class="wu-weather-modal__close" data-wu-weather-close aria-label="关闭天气详情"><i class="ph ph-x"></i></button>
                </header>
                <section class="wu-weather-modal__now">
                    <div class="wu-weather-modal__now-main">
                        <div class="wu-weather-modal__temp">${this._escapeHtml(tempValue)}<sup>°</sup></div>
                        <div class="wu-weather-modal__desc"><i class="${icon}"></i><span>${this._escapeHtml(current.desc)}</span></div>
                        <div class="wu-weather-modal__range" data-wu-weather-range>预报加载中…</div>
                    </div>
                    <div class="wu-weather-modal__now-icon"><i class="${icon}"></i></div>
                </section>
                <section class="wu-weather-modal__section">
                    <div class="wu-weather-modal__section-head"><span>云图</span><span data-wu-weather-cloud-label>实时云图</span></div>
                    <div class="wu-weather-cloudmap">
                        <canvas class="wu-weather-cloudmap__canvas" data-wu-weather-cloudmap role="img" aria-label="实时云量示意图"></canvas>
                        <div class="wu-weather-cloudmap__pin"><i class="ph-fill ph-map-pin"></i><span>当前位置</span></div>
                        <div class="wu-weather-cloudmap__legend"><span>少云</span><span class="wu-weather-cloudmap__bar"></span><span>多云</span></div>
                    </div>
                </section>
                <section class="wu-weather-modal__section">
                    <div class="wu-weather-modal__section-head"><span>逐小时预报</span><span>未来 24 小时</span></div>
                    <div class="wu-weather-modal__hourly" data-wu-weather-hourly>
                        <div class="wu-weather-modal__loading"><span class="wu-weather-spinner"></span><span>正在获取逐小时天气…</span></div>
                    </div>
                </section>
                <section class="wu-weather-modal__section">
                    <div class="wu-weather-modal__section-head"><span>未来 7 天</span><span>每日预报</span></div>
                    <div class="wu-weather-modal__daily" data-wu-weather-daily>
                        <div class="wu-weather-modal__loading"><span class="wu-weather-spinner"></span><span>正在获取每日预报…</span></div>
                    </div>
                </section>
                <section class="wu-weather-modal__section">
                    <div class="wu-weather-modal__section-head"><span>过去 7 天</span><span>历史天气</span></div>
                    <div class="wu-weather-modal__daily" data-wu-weather-past>
                        <div class="wu-weather-modal__loading"><span class="wu-weather-spinner"></span><span>正在获取历史天气…</span></div>
                    </div>
                </section>
                <section class="wu-weather-modal__section">
                    <div class="wu-weather-modal__section-head"><span>天气详情</span></div>
                    <div class="wu-weather-modal__details" data-wu-weather-details>
                        ${this._detailItem('ph-thermometer-simple', '体感温度', current.feels)}
                        ${this._detailItem('ph-drop', '湿度', current.humidity)}
                        ${this._detailItem('ph-wind', '风力', current.wind)}
                        ${this._detailItem('ph-eye', '能见度', current.vis)}
                    </div>
                </section>
                <p class="wu-weather-modal__foot">数据来自和风天气 · 云图为云量示意 · 仅供日常参考</p>
                <button type="button" class="wu-weather-modal__done" data-wu-weather-close>完成</button>
            `;
        },

        async _loadForecast(card, current) {
            const requestId = ++this._forecastRequestId;
            const controller = new AbortController();
            const timer = window.setTimeout(() => controller.abort(), 10000);
            try {
                const url = this._buildUrl(card) + '&include=forecast';
                const resp = await fetch(url, {
                    headers: { 'Accept': 'application/json' },
                    cache: 'no-store',
                    signal: controller.signal
                });
                if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
                const json = await resp.json().catch(() => null);
                if (requestId !== this._forecastRequestId) return;
                if (!json || json.code !== 200 || !json.data) throw new Error('No Data');
                this._hydrateForecast(json.data, current);
            } catch (e) {
                if (requestId !== this._forecastRequestId) return;
                this._showForecastEmpty();
            } finally {
                window.clearTimeout(timer);
            }
        },

        async _loadHistory(card) {
            const requestId = this._forecastRequestId;
            const controller = new AbortController();
            const timer = window.setTimeout(() => controller.abort(), 12000);
            try {
                const url = this._buildUrl(card) + '&include=history';
                const resp = await fetch(url, {
                    headers: { 'Accept': 'application/json' },
                    cache: 'no-store',
                    signal: controller.signal
                });
                if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
                const json = await resp.json().catch(() => null);
                if (requestId !== this._forecastRequestId) return;
                if (!json || json.code !== 200 || !json.data) throw new Error('No Data');
                this._hydrateHistory(Array.isArray(json.data.past) ? json.data.past : []);
            } catch (e) {
                if (requestId !== this._forecastRequestId) return;
                this._hydrateHistory([]);
            } finally {
                window.clearTimeout(timer);
            }
        },

        _hydrateForecast(data, current) {
            const daily = Array.isArray(data.daily) ? data.daily.slice(0, 7) : [];
            const hourly = data.hourly && Array.isArray(data.hourly.time) ? data.hourly : null;
            const today = daily[0] || null;
            const dash = (value) => value === undefined || value === null || value === '' || value === '--';

            if (!dash(data.temp)) {
                const tempEl = this._sheetEl.querySelector('.wu-weather-modal__temp');
                if (tempEl) tempEl.innerHTML = `${this._escapeHtml(String(data.temp))}<sup>°</sup>`;
            }
            if (!dash(data.desc)) {
                const descEl = this._sheetEl.querySelector('.wu-weather-modal__desc span');
                if (descEl) descEl.textContent = data.desc;
            }
            if (!dash(data.icon)) {
                const iconClass = `qi-${String(data.icon).replace(/[^\d]/g, '')}-fill`;
                const iconEls = this._sheetEl.querySelectorAll('.wu-weather-modal__desc i, .wu-weather-modal__now-icon i');
                Array.prototype.forEach.call(iconEls, (el) => { el.className = iconClass; });
            }
            if (!dash(data.city)) {
                const cityEl = this._sheetEl.querySelector('.wu-weather-modal__city span');
                if (cityEl) cityEl.textContent = data.city;
            }
            if (!dash(data.obsTime)) {
                const dateEl = this._sheetEl.querySelector('.wu-weather-modal__date');
                if (dateEl) dateEl.textContent = this._formatModalDate(data.obsTime, current.updated);
            }

            this._updateCloudMap(data);

            const rangeEl = this._sheetEl.querySelector('[data-wu-weather-range]');
            if (rangeEl) {
                rangeEl.textContent = (today && !dash(today.tempMax) && !dash(today.tempMin))
                    ? `最高 ${today.tempMax}° · 最低 ${today.tempMin}°`
                    : '当前天气';
            }

            const hourlyEl = this._sheetEl.querySelector('[data-wu-weather-hourly]');
            if (hourlyEl) {
                if (hourly && hourly.time.length) {
                    const times = hourly.time.slice(0, 24);
                    hourlyEl.innerHTML = times.map((time, index) => {
                        const temp = hourly.temperature_2m ? hourly.temperature_2m[index] : null;
                        const code = hourly.weather_code ? hourly.weather_code[index] : null;
                        const pop = hourly.precipitation_probability ? hourly.precipitation_probability[index] : null;
                        const popText = !dash(pop) && Number(pop) > 0 ? `${pop}%` : '';
                        return `<div class="wu-weather-modal__hour${index === 0 ? ' is-now' : ''}" style="--i:${index}">
                            <span class="wu-weather-modal__hour-time">${this._escapeHtml(this._formatHour(time, index))}</span>
                            <i class="ph-fill ${this._iconForWmo(code)}"></i>
                            <span class="wu-weather-modal__hour-temp">${dash(temp) ? '--°' : Math.round(Number(temp)) + '°'}</span>
                            <span class="wu-weather-modal__hour-pop">${popText}</span>
                        </div>`;
                    }).join('');
                } else {
                    hourlyEl.innerHTML = '<div class="wu-weather-modal__empty">暂无逐小时预报数据</div>';
                }
            }

            const dailyEl = this._sheetEl.querySelector('[data-wu-weather-daily]');
            if (dailyEl) {
                if (daily.length) {
                    dailyEl.innerHTML = daily.map((item, index) => {
                        const iconCode = String(item.iconDay || '').replace(/[^\d]/g, '');
                        const iconClass = iconCode ? `qi-${iconCode}-fill` : 'qi-999-fill';
                        const max = dash(item.tempMax) ? '--°' : `${item.tempMax}°`;
                        const min = dash(item.tempMin) ? '--°' : `${item.tempMin}°`;
                        return `<div class="wu-weather-modal__day" style="--i:${index}">
                            <span class="wu-weather-modal__day-name">${this._escapeHtml(this._formatDay(item.fxDate, index))}</span>
                            <i class="${iconClass}"></i>
                            <span class="wu-weather-modal__day-desc">${this._escapeHtml(item.textDay || '')}</span>
                            <span class="wu-weather-modal__day-temp"><b>${max}</b><em>${min}</em></span>
                        </div>`;
                    }).join('');
                } else {
                    dailyEl.innerHTML = '<div class="wu-weather-modal__empty">暂无每日预报数据</div>';
                }
            }

            const detailsEl = this._sheetEl.querySelector('[data-wu-weather-details]');
            if (detailsEl) {
                const items = [
                    this._detailItem('ph-thermometer-simple', '体感温度', !dash(data.feelsLike) ? `${data.feelsLike}°` : current.feels),
                    this._detailItem('ph-drop', '湿度', !dash(data.humidity) ? `${data.humidity}%` : current.humidity),
                    this._detailItem('ph-wind', '风力', !dash(data.windDir)
                        ? `${data.windDir}${!dash(data.windScale) ? ' ' + data.windScale + '级' : ''}`
                        : current.wind),
                    this._detailItem('ph-eye', '能见度', !dash(data.vis) ? `${data.vis} km` : current.vis)
                ];
                if (today && !dash(today.pressure)) items.push(this._detailItem('ph-gauge', '气压', `${today.pressure} hPa`));
                if (today && !dash(today.uvIndex)) items.push(this._detailItem('ph-sun', '紫外线', today.uvIndex));
                detailsEl.innerHTML = items.join('');
            }
        },

        _showForecastEmpty() {
            const hourlyEl = this._sheetEl.querySelector('[data-wu-weather-hourly]');
            const dailyEl = this._sheetEl.querySelector('[data-wu-weather-daily]');
            const rangeEl = this._sheetEl.querySelector('[data-wu-weather-range]');
            if (hourlyEl) hourlyEl.innerHTML = '<div class="wu-weather-modal__empty">暂无逐小时预报数据</div>';
            if (dailyEl) dailyEl.innerHTML = '<div class="wu-weather-modal__empty">暂无每日预报数据</div>';
            if (rangeEl) rangeEl.textContent = '当前天气';
        },

        _hydrateHistory(past) {
            const pastEl = this._sheetEl.querySelector('[data-wu-weather-past]');
            if (!pastEl) return;
            const dash = (value) => value === undefined || value === null || value === '' || value === '--';

            if (!Array.isArray(past) || !past.length) {
                pastEl.innerHTML = '<div class="wu-weather-modal__empty">暂无历史天气数据</div>';
                return;
            }

            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const rows = past.slice().reverse();
            pastEl.innerHTML = rows.map((item, index) => {
                const iconCode = String(item.iconDay || '').replace(/[^\d]/g, '');
                const iconClass = iconCode ? `qi-${iconCode}-fill` : 'qi-999-fill';
                const date = new Date(`${item.fxDate}T00:00:00`);
                let label = '—';
                if (!isNaN(date.getTime())) {
                    const diff = Math.round((today - date) / 86400000);
                    if (diff === 1) label = '昨天';
                    else if (diff === 2) label = '前天';
                    else if (diff > 0) label = `${diff}天前`;
                    else label = this._formatDay(item.fxDate, index);
                }
                const max = dash(item.tempMax) ? '--°' : `${item.tempMax}°`;
                const min = dash(item.tempMin) ? '--°' : `${item.tempMin}°`;
                return `<div class="wu-weather-modal__day" style="--i:${index}">
                    <span class="wu-weather-modal__day-name">${this._escapeHtml(label)}</span>
                    <i class="${iconClass}"></i>
                    <span class="wu-weather-modal__day-desc">${this._escapeHtml(item.textDay || '')}</span>
                    <span class="wu-weather-modal__day-temp"><b>${max}</b><em>${min}</em></span>
                </div>`;
            }).join('');
        },

        /* ---------- 云图 Canvas ---------- */
        _cloudCoverFrom(data) {
            const raw = data && data.cloud;
            if (raw !== undefined && raw !== null && raw !== '' && !isNaN(Number(raw))) {
                return Math.max(0, Math.min(100, Math.round(Number(raw))));
            }
            const desc = String((data && data.desc) || '');
            if (!desc || desc === '未知' || desc === '天气数据暂不可用' || desc === '--') return null;
            if (/晴/.test(desc)) return 15;
            if (/多云|少云/.test(desc)) return 55;
            if (/阴/.test(desc)) return 85;
            if (/雨|雪|雷/.test(desc)) return 95;
            return 60;
        },

        _updateCloudMapLabel(cover) {
            const label = this._sheetEl.querySelector('[data-wu-weather-cloud-label]');
            if (!label) return;
            label.textContent = (cover !== null && cover !== undefined && !isNaN(Number(cover)))
                ? `实时云量 ${Math.round(Number(cover))}%`
                : '实时云图';
        },

        _initCloudMap(current) {
            this._stopCloudMap();
            const canvas = this._sheetEl.querySelector('[data-wu-weather-cloudmap]');
            const ctx = canvas ? canvas.getContext('2d') : null;
            if (!canvas || !ctx) return;

            const reduced = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
            const state = {
                canvas,
                ctx,
                dpr: Math.min(window.devicePixelRatio || 1, 2),
                width: 1,
                height: 1,
                clouds: [],
                particles: [],
                cloudCover: this._cloudCoverFrom(current),
                desc: String((current && current.desc) || ''),
                rain: /雨|雷/.test(String((current && current.desc) || '')),
                snow: /雪/.test(String((current && current.desc) || '')),
                t: 0,
                last: 0,
                raf: 0,
                observer: null,
                reduced
            };
            this._cloudMapState = state;

            this._resizeCloudMap(state);
            this._buildCloudParticles(state);
            this._updateCloudMapLabel(state.cloudCover);

            if (reduced) {
                this._drawCloudMap(state, 0);
            } else {
                const loop = (ts) => {
                    if (!this._cloudMapState || this._cloudMapState !== state) return;
                    if (!state.last) state.last = ts;
                    const dt = Math.min(0.05, (ts - state.last) / 1000);
                    state.last = ts;
                    state.t += dt;
                    this._drawCloudMap(state, dt);
                    state.raf = window.requestAnimationFrame(loop);
                };
                state.raf = window.requestAnimationFrame(loop);
            }

            if (typeof ResizeObserver !== 'undefined') {
                state.observer = new ResizeObserver(() => {
                    if (!this._cloudMapState || this._cloudMapState !== state) return;
                    this._resizeCloudMap(state);
                    this._buildCloudParticles(state);
                    if (state.reduced) this._drawCloudMap(state, 0);
                });
                state.observer.observe(canvas);
            }
        },

        _updateCloudMap(data) {
            const state = this._cloudMapState;
            if (!state) return;
            const cover = this._cloudCoverFrom(data);
            const desc = String((data && data.desc) || state.desc || '');
            state.cloudCover = cover;
            state.desc = desc;
            state.rain = /雨|雷/.test(desc);
            state.snow = /雪/.test(desc);
            this._updateCloudMapLabel(cover);
            if (state.reduced) this._drawCloudMap(state, 0);
        },

        _resizeCloudMap(state) {
            const rect = state.canvas.getBoundingClientRect();
            const width = Math.max(1, Math.round(rect.width || state.canvas.clientWidth || 0));
            const height = Math.max(1, Math.round(rect.height || state.canvas.clientHeight || 0));
            state.width = width;
            state.height = height;
            state.canvas.width = Math.round(width * state.dpr);
            state.canvas.height = Math.round(height * state.dpr);
            state.ctx.setTransform(state.dpr, 0, 0, state.dpr, 0, 0);
        },

        _buildCloudParticles(state) {
            const w = state.width;
            const h = state.height;
            state.clouds = Array.from({ length: 20 }, () => ({
                x: Math.random() * w,
                y: Math.random() * h * 0.82 + h * 0.04,
                rx: 16 + Math.random() * 34,
                ry: 7 + Math.random() * 15,
                alpha: 0.1 + Math.random() * 0.22,
                speed: 3 + Math.random() * 7,
                phase: Math.random() * Math.PI * 2
            }));
            state.particles = Array.from({ length: 36 }, () => ({
                x: Math.random() * w,
                y: Math.random() * h,
                speed: 34 + Math.random() * 55,
                len: 5 + Math.random() * 10,
                alpha: 0.14 + Math.random() * 0.26,
                size: 0.8 + Math.random() * 1.8
            }));
        },

        _drawCloudMap(state, dt) {
            const ctx = state.ctx;
            const w = state.width;
            const h = state.height;
            if (!w || !h) return;
            const cover = (state.cloudCover === null || state.cloudCover === undefined) ? 55 : state.cloudCover;

            ctx.clearRect(0, 0, w, h);

            const bg = ctx.createLinearGradient(0, 0, w, h);
            bg.addColorStop(0, '#123e68');
            bg.addColorStop(0.55, '#0d2b4d');
            bg.addColorStop(1, '#071a30');
            ctx.fillStyle = bg;
            ctx.fillRect(0, 0, w, h);

            // 抽象地图网格与陆地
            ctx.save();
            ctx.strokeStyle = 'rgba(173, 216, 255, 0.10)';
            ctx.lineWidth = 1;
            const stepX = Math.max(34, w / 12);
            const stepY = Math.max(28, h / 6);
            for (let x = -h; x < w + h; x += stepX) {
                ctx.beginPath();
                ctx.moveTo(x, 0);
                ctx.lineTo(x + h * 0.38, h);
                ctx.stroke();
            }
            for (let y = 0; y <= h; y += stepY) {
                ctx.beginPath();
                ctx.moveTo(0, y);
                ctx.lineTo(w, y);
                ctx.stroke();
            }
            ctx.fillStyle = 'rgba(126, 200, 227, 0.07)';
            ctx.beginPath();
            ctx.ellipse(w * 0.16, h * 0.72, w * 0.3, h * 0.32, -0.3, 0, Math.PI * 2);
            ctx.fill();
            ctx.beginPath();
            ctx.ellipse(w * 0.8, h * 0.32, w * 0.22, h * 0.25, 0.4, 0, Math.PI * 2);
            ctx.fill();
            ctx.restore();

            // 晴朗时的柔和日光
            if (cover < 45) {
                const sun = ctx.createRadialGradient(w * 0.82, h * 0.18, 0, w * 0.82, h * 0.18, w * 0.42);
                sun.addColorStop(0, 'rgba(255, 226, 176, 0.28)');
                sun.addColorStop(0.45, 'rgba(255, 226, 176, 0.08)');
                sun.addColorStop(1, 'rgba(255, 226, 176, 0)');
                ctx.fillStyle = sun;
                ctx.fillRect(0, 0, w, h);
            }

            // 云层
            const count = Math.max(1, Math.min(state.clouds.length, Math.round(1 + (cover / 100) * 17)));
            for (let i = 0; i < count; i++) {
                const cloud = state.clouds[i];
                if (dt) {
                    cloud.x += cloud.speed * dt;
                    if (cloud.x - cloud.rx * 2 > w) cloud.x = -cloud.rx * 2;
                }
                const cy = cloud.y + Math.sin(state.t * 0.35 + cloud.phase) * 3;
                const g = ctx.createRadialGradient(cloud.x, cy, 0, cloud.x, cy, cloud.rx);
                g.addColorStop(0, `rgba(255, 255, 255, ${cloud.alpha})`);
                g.addColorStop(0.55, `rgba(236, 246, 255, ${cloud.alpha * 0.6})`);
                g.addColorStop(1, 'rgba(255, 255, 255, 0)');
                ctx.fillStyle = g;
                ctx.beginPath();
                ctx.ellipse(cloud.x, cy, cloud.rx, cloud.ry, 0, 0, Math.PI * 2);
                ctx.fill();
                ctx.beginPath();
                ctx.ellipse(cloud.x - cloud.rx * 0.55, cy + cloud.ry * 0.2, cloud.rx * 0.6, cloud.ry * 0.7, -0.2, 0, Math.PI * 2);
                ctx.fill();
                ctx.beginPath();
                ctx.ellipse(cloud.x + cloud.rx * 0.5, cy + cloud.ry * 0.15, cloud.rx * 0.55, cloud.ry * 0.65, 0.2, 0, Math.PI * 2);
                ctx.fill();
            }

            // 雨雪粒子
            if (state.rain || state.snow) {
                for (const p of state.particles) {
                    if (dt) {
                        p.y += p.speed * dt;
                        if (p.y > h + 20) {
                            p.y = -20;
                            p.x = Math.random() * w;
                        }
                    }
                    if (state.snow) {
                        ctx.fillStyle = `rgba(255, 255, 255, ${p.alpha})`;
                        ctx.beginPath();
                        ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
                        ctx.fill();
                    } else {
                        ctx.strokeStyle = `rgba(180, 225, 255, ${p.alpha})`;
                        ctx.lineWidth = 1;
                        ctx.beginPath();
                        ctx.moveTo(p.x, p.y);
                        ctx.lineTo(p.x - 2, p.y + p.len);
                        ctx.stroke();
                    }
                }
            }

            // 定位点脉冲
            const cx = w * 0.5;
            const cy = h * 0.52;
            const pulse = 0.5 + 0.5 * Math.sin(state.t * 2);
            ctx.strokeStyle = `rgba(255, 190, 210, ${0.25 + pulse * 0.35})`;
            ctx.lineWidth = 1.5;
            ctx.beginPath();
            ctx.arc(cx, cy, 8 + pulse * 10, 0, Math.PI * 2);
            ctx.stroke();
            ctx.fillStyle = '#ffd7e2';
            ctx.beginPath();
            ctx.arc(cx, cy, 2.5, 0, Math.PI * 2);
            ctx.fill();

            // 轻微暗角
            const vignette = ctx.createRadialGradient(w * 0.5, h * 0.5, h * 0.2, w * 0.5, h * 0.5, h * 0.9);
            vignette.addColorStop(0, 'rgba(0, 0, 0, 0)');
            vignette.addColorStop(1, 'rgba(0, 0, 0, 0.35)');
            ctx.fillStyle = vignette;
            ctx.fillRect(0, 0, w, h);
        },

        _stopCloudMap() {
            const state = this._cloudMapState;
            if (!state) return;
            if (state.raf) window.cancelAnimationFrame(state.raf);
            if (state.observer) state.observer.disconnect();
            this._cloudMapState = null;
        },

        _detailItem(icon, label, value) {
            return `<div class="wu-weather-modal__detail">
                <span class="wu-weather-modal__detail-label"><i class="ph ${icon}"></i>${this._escapeHtml(label)}</span>
                <span class="wu-weather-modal__detail-value">${this._escapeHtml(String(value))}</span>
            </div>`;
        },

        _formatModalDate(obsTime, fallback) {
            const date = obsTime ? new Date(obsTime) : new Date();
            if (isNaN(date.getTime())) {
                return fallback && fallback !== '--' ? `更新于 ${fallback}` : '刚刚更新';
            }
            const week = ['周日', '周一', '周二', '周三', '周四', '周五', '周六'][date.getDay()];
            const day = `${date.getMonth() + 1}月${date.getDate()}日 ${week}`;
            const hh = String(date.getHours()).padStart(2, '0');
            const mm = String(date.getMinutes()).padStart(2, '0');
            return `${day} · ${hh}:${mm} 更新`;
        },

        _formatHour(time, index) {
            if (index === 0) return '现在';
            const date = new Date(time);
            if (isNaN(date.getTime())) return '--';
            return `${String(date.getHours()).padStart(2, '0')}:00`;
        },

        _formatDay(fxDate, index) {
            if (index === 0) return '今天';
            if (index === 1) return '明天';
            const date = new Date(`${fxDate}T00:00:00`);
            if (isNaN(date.getTime())) return fxDate || '--';
            return ['周日', '周一', '周二', '周三', '周四', '周五', '周六'][date.getDay()];
        },

        _iconForWmo(code) {
            const value = Number(code);
            if (!isFinite(value)) return 'ph-cloud';
            if (value === 0) return 'ph-sun';
            if (value <= 2) return 'ph-cloud-sun';
            if (value === 3) return 'ph-cloud';
            if (value === 45 || value === 48) return 'ph-cloud-fog';
            if (value >= 51 && value <= 57) return 'ph-cloud-drizzle';
            if (value >= 61 && value <= 67) return 'ph-cloud-rain';
            if (value >= 71 && value <= 77) return 'ph-snowflake';
            if (value >= 80 && value <= 82) return 'ph-cloud-rain';
            if (value >= 85 && value <= 86) return 'ph-snowflake';
            if (value >= 95) return 'ph-cloud-lightning';
            return 'ph-cloud';
        },

        async _refreshAll() {
            // 等待地图数据（lovers 坐标/头像）就绪后再请求，最多等 5 秒
            if (window.WITHU_MAP_DATA_READY && !this._mapDataWaited) {
                this._mapDataWaited = true;
                try {
                    await Promise.race([
                        window.WITHU_MAP_DATA_READY,
                        new Promise(resolve => setTimeout(resolve, 5000)),
                    ]);
                } catch (e) { /* 地图数据失败时仍按配置坐标请求 */ }
            }
            // 位置统一由后台设置提供，不再刷新 GPS / IP 实时定位
            const weatherCards = document.querySelectorAll('.withu-home-weather-card');
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
            this._messageScroller = new WithUHomeMessageScroller('#messageCarousel', { speed: 0.8 });

            this._initialized = true;
        },

        destroy() {
            CountdownModule.destroy();
            CountUpAnimation.destroy();
            WeatherModule.destroy();
            this._initialized = false;
        },

        _initLockedCardGlow() {
            var cards = document.querySelectorAll('.withu-event-card--locked');
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
    class WithUHomeMessageScroller {
        constructor(containerSelector, options) {
            options = options || {};
            this.container = document.querySelector(containerSelector);
            if (!this.container) return;
            this.track = this.container.querySelector('.withu-home-message-track');
            if (!this.track) return;

            this.speed = options.speed || 0.8;
            this.originalCards = Array.from(this.track.children);
            // 空数据状态（仅含 .withu-no-data）不克隆、不滚动，由 CSS 铺满居中展示
            this._emptyState = this.originalCards.length === 1 && this.originalCards[0].classList.contains('withu-no-data');
            if (this._emptyState) this.originalCards = [];
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
            // 空数据状态不滚动：进入视口也不恢复动画
            if (this._emptyState) return;
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
            // 空数据状态不启动滚动动画，保持静止
            if (!this._emptyState) {
                this.animationFrameId = requestAnimationFrame(this._animateBound);
            }
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
    // 注册到 WithUApp
    // ============================================
    if (window.WithUApp) {
        window.WithUApp.register('index', IndexModule);
    }

    // 暴露到全局
    window.WithUIndexModule = IndexModule;

    // 兼容旧代码的全局函数
    window.filterLoveDays = (type, btn) => {
        LoveDayFilter.filter(type, btn);
    };

    window.initLoveDaySlider = () => {
        LoveDayFilter._initSliderPosition();
    };

})(window, jQuery);
