/**
 * LG_NewUI 文章详情页模块
 * @version 1.0.0
 * @description page.php 文章详情页的 JS 逻辑
 * @author KiKi
 */

;(function(window, $) {
    'use strict';

    // ============================================
    // Plyr 视频播放器模块
    // ============================================
    const PlyrManager = {
        _instances: [],

        /**
         * 初始化所有视频播放器
         */
        init() {
            // 检查 Plyr 是否可用
            if (typeof Plyr === 'undefined') {
                console.warn('[PlyrManager] Plyr library not loaded');
                return;
            }

            this._initVideos();
        },

        /**
         * 初始化视频元素
         */
        _initVideos() {
            const self = this;
            const videos = document.querySelectorAll('video.lgnewui-player-video');

            if (videos.length === 0) return;

            videos.forEach(function(video) {
                // 检查是否已初始化
                if (video.plyr) return;

                // 移除默认的 width 和 height 属性
                video.removeAttribute('width');
                video.removeAttribute('height');

                // 初始化 Plyr
                const player = new Plyr(video, {
                    controls: [
                        'play-large',
                        'play',
                        'progress',
                        'current-time',
                        'duration',
                        'mute',
                        'volume',
                        'settings',
                        'pip',
                        'airplay',
                        'fullscreen'
                    ],
                    settings: ['quality', 'speed'],
                    speed: { selected: 1, options: [0.5, 0.75, 1, 1.25, 1.5, 2] },
                    quality: { default: 720, options: [1080, 720, 480, 360] }
                });

                self._instances.push(player);
            });
        },

        /**
         * 销毁所有播放器实例
         */
        destroy() {
            this._instances.forEach(function(player) {
                if (player && typeof player.destroy === 'function') {
                    player.destroy();
                }
            });
            this._instances = [];
        }
    };


    // ============================================
    // 目录导航模块 (TOC)
    // ============================================
    const TocManager = {
        _mode: 'none',
        _observer: null,
        _headings: [],

        /**
         * 初始化目录
         */
        init() {
            this._generateToc();
            this._initMode();
            this._bindEvents();
            this._initScrollSpy();
        },

        /**
         * 生成目录
         */
        _generateToc() {
            const content = document.getElementById('article-content');
            const tocDesktop = document.getElementById('toc-desktop');
            const tocMobile = document.getElementById('toc-mobile');

            if (!content) return;

            const headings = content.querySelectorAll('h2, h3, h4');
            
            // 无标题时显示骨架屏空状态
            if (headings.length === 0) {
                const emptyHtml = `
                    <div class="lgnewui-toc-empty">
                        <div class="lgnewui-toc-skeleton">
                            <div class="lgnewui-toc-skeleton-line" style="width: 75%"></div>
                            <div class="lgnewui-toc-skeleton-line" style="width: 90%"></div>
                            <div class="lgnewui-toc-skeleton-line" style="width: 60%"></div>
                            <div class="lgnewui-toc-skeleton-line" style="width: 80%"></div>
                        </div>
                        <div class="lgnewui-toc-empty-badge">
                            <i class="ph-duotone ph-paragraph"></i>
                            <span>本文尚未设置章节标题</span>
                        </div>
                    </div>
                `;
                if (tocDesktop) tocDesktop.innerHTML = emptyHtml;
                if (tocMobile) tocMobile.innerHTML = emptyHtml;
                return;
            }

            this._headings = Array.from(headings);

            let tocHtml = '';
            let h2Index = 0;
            let h3Index = 0;
            let h4Index = 0;

            headings.forEach(function(heading, index) {
                // 确保标题有 ID
                if (!heading.id) {
                    heading.id = heading.innerText.replace(/[^\w\s\u4e00-\u9fa5]/gi, '').split(/\s+/).join('-').toLowerCase() + '-' + Math.random().toString(36).substr(2, 5);
                }

                const level = heading.tagName.toLowerCase();
                const text = heading.textContent;
                let number = '';
                let tag = '';
                let nestClass = '';
                let iconClass = '';

                if (level === 'h2') {
                    h2Index++;
                    h3Index = 0;
                    h4Index = 0;
                    number = String(h2Index).padStart(2, '0');
                    tag = 'H2';
                    nestClass = '';
                    iconClass = 'ph-fill ph-star-four';
                } else if (level === 'h3') {
                    h3Index++;
                    h4Index = 0;
                    number = String(h2Index).padStart(2, '0') + '-' + h3Index;
                    tag = 'H3';
                    nestClass = 'lgnewui-detail-toc-link-nested';
                    iconClass = 'ph-fill ph-square';
                } else if (level === 'h4') {
                    h4Index++;
                    number = String(h2Index).padStart(2, '0') + '-' + h3Index + '-' + h4Index;
                    tag = 'H4';
                    nestClass = 'lgnewui-detail-toc-link-nested-2';
                    iconClass = 'ph-fill ph-circle';
                }

                tocHtml += '<a href="#' + heading.id + '" class="lgnewui-detail-toc-link ' + nestClass + '" data-target="' + heading.id + '">';
                tocHtml += '<i class="' + iconClass + ' lgnewui-detail-toc-icon-default"></i>';
                tocHtml += '<span class="lgnewui-toc-prefix" data-number="' + number + '" data-tag="' + tag + '" style="display: none;"></span>';
                tocHtml += '<span class="lgnewui-detail-toc-link-text">' + text + '</span>';
                tocHtml += '</a>';
            });

            if (tocDesktop) tocDesktop.innerHTML = tocHtml;
            if (tocMobile) tocMobile.innerHTML = tocHtml;
        },

        /**
         * 初始化滚动高亮
         */
        _initScrollSpy() {
            const self = this;
            if (this._headings.length === 0) return;

            this._observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        const targetId = entry.target.id;
                        document.querySelectorAll('.lgnewui-detail-toc-link').forEach(function(link) {
                            const isActive = link.getAttribute('data-target') === targetId;
                            link.classList.toggle('active', isActive);
                            const icon = link.querySelector('i');
                            if (icon) {
                                if (isActive) {
                                    icon.classList.remove('lgnewui-detail-toc-icon-default');
                                    icon.classList.add('lgnewui-detail-toc-icon-active');
                                } else {
                                    icon.classList.remove('lgnewui-detail-toc-icon-active');
                                    icon.classList.add('lgnewui-detail-toc-icon-default');
                                }
                            }
                        });
                    }
                });
            }, {
                rootMargin: '-10% 0px -60% 0px'
            });

            this._headings.forEach(function(heading) {
                self._observer.observe(heading);
            });
        },

        /**
         * 初始化显示模式
         */
        _initMode() {
            const savedMode = localStorage.getItem('tocDisplayMode') || 'none';
            this._mode = savedMode;
            this._updateIndicator(savedMode);
            if (savedMode !== 'none') {
                this._switchMode(savedMode);
            }
        },

        /**
         * 切换显示模式
         * @param {string} mode - none | number | tag
         */
        _switchMode(mode) {
            this._mode = mode;

            // 更新按钮状态
            const tabs = document.querySelectorAll('.lgnewui-toc-tab-mini');
            tabs.forEach(function(tab) {
                tab.classList.remove('active');
                if (tab.getAttribute('data-mode') === mode) {
                    tab.classList.add('active');
                }
            });

            // 更新指示器
            this._updateIndicator(mode);

            // 更新前缀显示
            const prefixes = document.querySelectorAll('.lgnewui-toc-prefix');
            prefixes.forEach(function(prefix) {
                if (mode === 'none') {
                    prefix.style.display = 'none';
                    prefix.textContent = '';
                } else if (mode === 'number') {
                    prefix.style.display = 'inline-flex';
                    prefix.textContent = prefix.getAttribute('data-number');
                } else if (mode === 'tag') {
                    prefix.style.display = 'inline-flex';
                    prefix.textContent = prefix.getAttribute('data-tag');
                }
            });

            localStorage.setItem('tocDisplayMode', mode);
        },

        /**
         * 更新指示器位置
         * @param {string} mode
         */
        _updateIndicator(mode) {
            const indicators = document.querySelectorAll('.lgnewui-toc-tab-indicator-mini');
            indicators.forEach(function(indicator) {
                const container = indicator.closest('.lgnewui-toc-switcher-mini');
                if (!container) return;

                const tabs = container.querySelectorAll('.lgnewui-toc-tab-mini');
                const activeTab = container.querySelector('.lgnewui-toc-tab-mini[data-mode="' + mode + '"]');

                if (activeTab && tabs.length > 0) {
                    const tabIndex = Array.from(tabs).indexOf(activeTab);
                    const tabWidth = activeTab.offsetWidth;
                    const gap = 4;
                    const offset = tabIndex * (tabWidth + gap);
                    indicator.style.width = tabWidth + 'px';
                    indicator.style.transform = 'translateX(' + offset + 'px)';
                }
            });
        },

        /**
         * 绑定事件
         */
        _bindEvents() {
            const self = this;

            // 目录模式切换
            $(document).off('click.tocMode', '.lgnewui-toc-tab-mini').on('click.tocMode', '.lgnewui-toc-tab-mini', function() {
                const mode = $(this).attr('data-mode');
                self._switchMode(mode);
            });

            // 目录链接点击
            $(document).off('click.tocLink', '.lgnewui-detail-toc-link').on('click.tocLink', '.lgnewui-detail-toc-link', function(e) {
                e.preventDefault();
                const targetId = $(this).attr('data-target');
                const target = document.getElementById(targetId);
                if (target) {
                    // 计算偏移量，预留导航栏高度（80px）
                    const headerOffset = 80;
                    const elementPosition = target.getBoundingClientRect().top;
                    const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
                    window.scrollTo({
                        top: offsetPosition,
                        behavior: 'smooth'
                    });
                }
                // 关闭移动端目录
                self._closeMobileToc();
            });

            // 移动端目录按钮
            $(document).off('click.mobileTocBtn', '#mobile-toc-btn').on('click.mobileTocBtn', '#mobile-toc-btn', function() {
                self._openMobileToc();
            });

            // 关闭移动端目录
            $(document).off('click.mobileTocClose', '#mobile-toc-close, #mobile-toc-overlay').on('click.mobileTocClose', '#mobile-toc-close, #mobile-toc-overlay', function() {
                self._closeMobileToc();
            });
        },

        /**
         * 打开移动端目录
         */
        _openMobileToc() {
            $('#mobile-toc-overlay').addClass('open');
            $('#mobile-toc-sheet').addClass('open');
            if (window.lgScrollLock) lgScrollLock();
        },

        /**
         * 关闭移动端目录
         */
        _closeMobileToc() {
            $('#mobile-toc-overlay').removeClass('open');
            $('#mobile-toc-sheet').removeClass('open');
            if (window.lgScrollUnlock) lgScrollUnlock();
        }
    };


    // ============================================
    // 字体切换模块
    // ============================================
    const FontSwitcher = {
        /**
         * 初始化字体切换
         */
        init() {
            this._loadSavedFont();
            this._bindEvents();
            this._initIndicator();
        },

        /**
         * 加载保存的字体设置
         */
        _loadSavedFont() {
            const savedFont = localStorage.getItem('preferredFont') || 'default';
            this._switchFont(savedFont);
        },

        /**
         * 初始化指示器位置
         */
        _initIndicator() {
            const savedFont = localStorage.getItem('preferredFont') || 'default';
            const indicator = document.querySelector('.lgnewui-font-tab-indicator-mini');
            const activeTab = document.querySelector('.lgnewui-font-tab-mini[data-font="' + savedFont + '"]');
            const tabs = document.querySelectorAll('.lgnewui-font-tab-mini');

            if (indicator && activeTab && tabs.length > 0) {
                const tabIndex = Array.from(tabs).indexOf(activeTab);
                const tabWidth = activeTab.offsetWidth;
                const gap = 4;
                const offset = tabIndex * (tabWidth + gap);
                indicator.style.width = tabWidth + 'px';
                indicator.style.transform = 'translateX(' + offset + 'px)';
            }
        },

        /**
         * 切换字体
         * @param {string} fontType - default | noto | harmony
         */
        _switchFont(fontType) {
            const articleBody = document.getElementById('article-content');
            if (!articleBody) return;

            // 更新按钮状态
            const tabs = document.querySelectorAll('.lgnewui-font-tab-mini');
            tabs.forEach(function(tab) {
                tab.classList.remove('active');
                if (tab.getAttribute('data-font') === fontType) {
                    tab.classList.add('active');
                }
            });

            // 更新指示器
            const indicator = document.querySelector('.lgnewui-font-tab-indicator-mini');
            const activeTab = document.querySelector('.lgnewui-font-tab-mini[data-font="' + fontType + '"]');

            if (indicator && activeTab && tabs.length > 0) {
                const tabIndex = Array.from(tabs).indexOf(activeTab);
                const tabWidth = activeTab.offsetWidth;
                const gap = 4;
                const offset = tabIndex * (tabWidth + gap);
                indicator.style.width = tabWidth + 'px';
                indicator.style.transform = 'translateX(' + offset + 'px)';
            }

            // 切换字体类
            articleBody.classList.remove('font-noto', 'font-harmony');
            if (fontType === 'noto') {
                articleBody.classList.add('font-noto');
            } else if (fontType === 'harmony') {
                articleBody.classList.add('font-harmony');
            }

            localStorage.setItem('preferredFont', fontType);
        },

        /**
         * 绑定事件
         */
        _bindEvents() {
            const self = this;
            $(document).off('click.fontSwitch', '.lgnewui-font-tab-mini').on('click.fontSwitch', '.lgnewui-font-tab-mini', function() {
                const fontType = $(this).attr('data-font');
                self._switchFont(fontType);
            });
        }
    };

    // ============================================
    // 图片增强模块
    // ============================================
    const ImageEnhancer = {
        /**
         * 初始化图片增强
         */
        init() {
            this._enhanceImages();
        },

        /**
         * 增强图片：统一包裹 figure，有 alt 时加 figcaption，接入灯箱
         */
        _enhanceImages() {
            const content = document.getElementById('lgnewui-detail-content');
            if (!content) return;

            // 给容器加 view-image 属性，让 ViewImage 以此为分组容器
            content.setAttribute('view-image', '');

            const images = content.querySelectorAll('img');
            if (images.length === 0) return;

            images.forEach(function(img) {
                // 跳过已在 figure 里的
                if (img.closest('figure')) return;
                // 跳过音乐卡片中的图片
                if (img.closest('.music_card') || img.closest('.mian_music')) return;
                // 跳过表情、小图标等（宽度 <= 64 的跳过）
                if (img.naturalWidth > 0 && img.naturalWidth <= 64) return;
                if (img.width > 0 && img.width <= 64) return;

                // 统一包裹 figure
                var figure = document.createElement('figure');
                img.parentNode.insertBefore(figure, img);
                figure.appendChild(img);

                // 有 alt 文本时加 figcaption
                var alt = img.getAttribute('alt');
                if (alt && alt.trim() !== '') {
                    var figcaption = document.createElement('figcaption');
                    figcaption.textContent = alt;
                    figure.appendChild(figcaption);
                }
            });

            // 为所有文章图片添加灯箱标记（全局 ViewImage 已绑定 .view-image-media 选择器）
            var allImgs = content.querySelectorAll('figure img');
            allImgs.forEach(function(img) {
                img.classList.add('view-image-media');
                img.style.cursor = 'pointer';
            });
        }
    };

    // ============================================
    // 表格增强模块
    // ============================================
    const TableEnhancer = {
        /**
         * 初始化表格增强
         */
        init() {
            this._enhanceTables();
            this._bindResizeEvent();
        },

        /**
         * 增强所有表格
         */
        _enhanceTables() {
            const self = this;
            const content = document.getElementById('article-content');
            if (!content) return;

            const tables = content.querySelectorAll('table');

            tables.forEach(function(table, index) {
                // 检查是否已增强
                if (table.closest('.lgnewui-table-wrapper')) return;

                table.classList.add('lgnewui-main');

                const firstRow = table.querySelector('tr');
                const columnCount = firstRow ? firstRow.children.length : 0;

                // 创建包装器
                const wrapper = document.createElement('div');
                wrapper.className = 'lgnewui-table-wrapper';

                const container = document.createElement('div');
                container.className = 'lgnewui-table-container';
                container.id = 'tableContainer-' + index;

                const scrollbar = document.createElement('div');
                scrollbar.className = 'lgnewui-table-scrollbar';

                const containerWrapper = document.createElement('div');
                containerWrapper.className = 'lgnewui-table-container-wrapper';
                containerWrapper.id = 'tableContainerWrapper-' + index;

                // 左右按钮
                const leftBtn = document.createElement('button');
                leftBtn.className = 'lgnewui-table-nav-btn lgnewui-table-nav-left';
                leftBtn.id = 'tableNavLeft-' + index;
                leftBtn.innerHTML = '<i class="ph-bold ph-caret-left"></i>';
                leftBtn.onclick = function() { self._scrollTable(index, 'left', columnCount); };

                const rightBtn = document.createElement('button');
                rightBtn.className = 'lgnewui-table-nav-btn lgnewui-table-nav-right';
                rightBtn.id = 'tableNavRight-' + index;
                rightBtn.innerHTML = '<i class="ph-bold ph-caret-right"></i>';
                rightBtn.onclick = function() { self._scrollTable(index, 'right', columnCount); };

                // 包装表格
                table.parentNode.insertBefore(wrapper, table);
                container.appendChild(table);
                containerWrapper.appendChild(leftBtn);
                containerWrapper.appendChild(container);
                containerWrapper.appendChild(rightBtn);
                wrapper.appendChild(containerWrapper);
                wrapper.appendChild(scrollbar);

                // 延迟检测是否可滚动
                setTimeout(function() {
                    const tableWidth = table.scrollWidth;
                    const containerWidth = container.clientWidth;
                    const isScrollable = tableWidth > containerWidth;

                    if (isScrollable) {
                        wrapper.classList.add('is-scrollable');
                        const progressTrack = document.createElement('div');
                        progressTrack.className = 'lgnewui-table-progress-track';
                        const progressBar = document.createElement('div');
                        progressBar.className = 'lgnewui-table-progress-bar';
                        progressBar.id = 'tableProgress-' + index;
                        progressTrack.appendChild(progressBar);
                        scrollbar.appendChild(progressTrack);
                    }
                    self._updateTableButtons(index);
                    self._updateTableProgress(index);
                }, 100);

                // 滚动事件
                container.addEventListener('scroll', function() {
                    self._updateTableButtons(index);
                    self._updateTableProgress(index);
                });
            });
        },

        /**
         * 滚动表格
         */
        _scrollTable(index, direction, columnCount) {
            const self = this;
            const container = document.getElementById('tableContainer-' + index);
            if (!container) return;
            const table = container.querySelector('table');
            const columnWidth = table.offsetWidth / columnCount;
            container.scrollBy({
                left: direction === 'left' ? -columnWidth : columnWidth,
                behavior: 'smooth'
            });
            setTimeout(function() {
                self._updateTableButtons(index);
                self._updateTableProgress(index);
            }, 300);
        },

        /**
         * 更新按钮状态
         */
        _updateTableButtons(index) {
            const container = document.getElementById('tableContainer-' + index);
            const leftBtn = document.getElementById('tableNavLeft-' + index);
            const rightBtn = document.getElementById('tableNavRight-' + index);
            if (!container || !leftBtn || !rightBtn) return;
            const scrollLeft = container.scrollLeft;
            const maxScroll = container.scrollWidth - container.clientWidth;
            leftBtn.disabled = scrollLeft <= 0;
            rightBtn.disabled = scrollLeft >= maxScroll - 1;
        },

        /**
         * 更新进度条
         */
        _updateTableProgress(index) {
            const container = document.getElementById('tableContainer-' + index);
            const progressBar = document.getElementById('tableProgress-' + index);
            if (!container || !progressBar) return;
            const scrollLeft = container.scrollLeft;
            const maxScroll = container.scrollWidth - container.clientWidth;
            if (maxScroll <= 0) return;
            const progress = (scrollLeft / maxScroll) * 100;
            progressBar.style.width = progress + '%';
        },

        /**
         * 绑定窗口 resize 事件
         */
        _bindResizeEvent() {
            const self = this;
            $(window).off('resize.tableEnhancer').on('resize.tableEnhancer', function() {
                const wrappers = document.querySelectorAll('.lgnewui-table-wrapper');
                wrappers.forEach(function(wrapper, index) {
                    self._updateTableButtons(index);
                    self._updateTableProgress(index);
                });
            });
        }
    };

    // ============================================
    // Sidebar 吸附模块
    // ============================================
    const SidebarSticky = {
        _sidebar: null,
        _stickyContent: null,
        _mainContent: null,
        _headerOffset: 80,
        _state: 'normal', // normal | sticky | bottom
        _sidebarOffsetTop: 0,
        _cachedWidth: 0,

        /**
         * 初始化
         */
        init() {
            if (window.innerWidth < 1024) return;

            this._sidebar = document.querySelector('.lgnewui-detail-sidebar');
            this._stickyContent = document.querySelector('.lgnewui-detail-sidebar-sticky');
            this._mainContent = document.querySelector('.lgnewui-detail-main-content');

            if (!this._sidebar || !this._stickyContent || !this._mainContent) return;

            this._calcSidebarOffset();
            this._bindEvents();
            // 延迟首次更新，避免页面加载时闪烁
            var self = this;
            requestAnimationFrame(function() { self._update(); });
        },

        /**
         * 计算 sidebar 初始位置
         */
        _calcSidebarOffset() {
            // 临时重置以获取准确的自然位置
            var el = this._stickyContent;
            el.classList.remove('is-sticky', 'is-bottom');
            el.style.cssText = '';
            this._state = 'normal';

            void this._sidebar.offsetHeight;
            var rect = this._sidebar.getBoundingClientRect();
            this._sidebarOffsetTop = rect.top + window.pageYOffset;
            this._cachedWidth = this._sidebar.offsetWidth;
        },

        /**
         * 绑定事件
         */
        _bindEvents() {
            var self = this;
            var ticking = false;

            $(window).off('scroll.sidebarSticky').on('scroll.sidebarSticky', function() {
                if (!ticking) {
                    requestAnimationFrame(function() {
                        self._update();
                        ticking = false;
                    });
                    ticking = true;
                }
            });

            $(window).off('resize.sidebarSticky').on('resize.sidebarSticky', function() {
                if (window.innerWidth < 1024) {
                    self._reset();
                } else {
                    self._calcSidebarOffset();
                    self._update();
                }
            });
        },

        /**
         * 重新计算位置（供外部调用）
         */
        recalc() {
            if (!this._sidebar) return;
            this._calcSidebarOffset();
            this._update();
        },

        /**
         * 更新吸附状态（class 切换 + 最少内联样式）
         */
        _update() {
            if (!this._sidebar || !this._stickyContent || !this._mainContent) return;
            if (window.innerWidth < 1024) return;

            var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            var stickyHeight = this._stickyContent.offsetHeight;
            var viewportHeight = window.innerHeight;
            var sidebarWidth = this._cachedWidth || this._sidebar.offsetWidth;

            // 主内容区底部绝对位置
            var mainRect = this._mainContent.getBoundingClientRect();
            var mainBottom = mainRect.bottom + scrollTop;

            var triggerPoint = this._sidebarOffsetTop - this._headerOffset;
            var maxH = viewportHeight - this._headerOffset - 20;
            var stopPoint = mainBottom - Math.min(stickyHeight, maxH) - this._headerOffset;

            var el = this._stickyContent;

            if (scrollTop < triggerPoint) {
                // 正常状态
                if (this._state !== 'normal') {
                    el.classList.remove('is-sticky', 'is-bottom');
                    el.style.cssText = '';
                    this._state = 'normal';
                }
            } else if (scrollTop < stopPoint) {
                // 吸附状态
                if (this._state !== 'sticky') {
                    el.classList.remove('is-bottom');
                    el.classList.add('is-sticky');
                    this._state = 'sticky';
                }
                el.style.top = this._headerOffset + 'px';
                el.style.width = sidebarWidth + 'px';
                el.style.maxHeight = maxH + 'px';
                el.style.transform = '';
            } else {
                // 到底，用 absolute 定位到 sidebar 底部
                if (this._state !== 'bottom') {
                    el.classList.remove('is-sticky');
                    el.classList.add('is-bottom');
                    this._state = 'bottom';
                }
                el.style.width = sidebarWidth + 'px';
                el.style.maxHeight = maxH + 'px';
                el.style.top = '';
                el.style.transform = '';
            }
        },

        /**
         * 重置样式
         */
        _reset() {
            if (!this._stickyContent) return;
            this._stickyContent.classList.remove('is-sticky', 'is-bottom');
            this._stickyContent.style.cssText = '';
            this._state = 'normal';
        },

        /**
         * 销毁
         */
        destroy() {
            $(window).off('scroll.sidebarSticky resize.sidebarSticky');
            this._reset();
        }
    };

    // ============================================
    // 文章详情页主模块
    // ============================================
    const PageDetailModule = {
        _initialized: false,

        /**
         * 初始化模块
         */
        init() {
            if (this._initialized) return;

            // 检测是否在文章详情页
            if (!this._isDetailPage()) return;

            // 初始化各子模块
            PlyrManager.init();
            TocManager.init();
            FontSwitcher.init();
            ImageEnhancer.init();
            TableEnhancer.init();
            SidebarSticky.init();
            RailManager.init();

            this._initialized = true;
        },

        /**
         * 检测是否在文章详情页
         * @returns {boolean}
         */
        _isDetailPage() {
            return $('#lgnewui-detail-content').length > 0 || 
                   $('.lgnewui-detail-main-grid').length > 0;
        },

        /**
         * 销毁模块
         */
        destroy() {
            PlyrManager.destroy();
            SidebarSticky.destroy();
            RailManager.destroy();

            // 断开 TocManager 的 IntersectionObserver
            if (TocManager._observer) {
                TocManager._observer.disconnect();
                TocManager._observer = null;
            }
            TocManager._headings = [];

            // 清理 TableEnhancer 的 resize 事件
            $(window).off('resize.tableEnhancer');

            // 清理 TocManager 和 FontSwitcher 的事件委托
            $(document).off('.tocMode .tocLink .mobileTocBtn .mobileTocClose .fontSwitch');
            this._initialized = false;
        },

        /**
         * 刷新模块（PJAX 切换后调用）
         */
        refresh() {
            this.destroy();
            this._initialized = false;
            this.init();
        }
    };

    // ============================================
    // 侧边栏模块（复制链接、二维码、分享）
    // ============================================
    const RailManager = {
        _bindDone: false,

        init() {
            if (!this._isDetailPage()) return;
            this._bindEvents();
        },

        _isDetailPage() {
            return $('#rail-qr-btn').length > 0 || $('#rail-copy-btn').length > 0;
        },

        _bindEvents() {
            // 避免重复绑定
            if (this._bindDone) return;
            this._bindDone = true;

            const self = this;

            // 二维码按钮悬停 - 延迟等待 Tooltip 渲染
            $(document).on('mouseenter.railQr', '#rail-qr-btn', function() {
                self._tryInitQRCode(0);
            });

            // 复制链接按钮
            $(document).on('click.railCopy', '#rail-copy-btn', function() {
                self._copyLink();
            });
        },

        _tryInitQRCode(attempt) {
            const self = this;
            const maxAttempts = 5;

            setTimeout(function() {
                const container = document.getElementById('qrcode-rail');
                if (!container) {
                    // 容器还不存在，重试
                    if (attempt < maxAttempts) {
                        self._tryInitQRCode(attempt + 1);
                    }
                    return;
                }

                // 已渲染过则跳过（检查是否有二维码canvas，排除spinner图标）
                if (container.querySelector('canvas')) return;

                // 获取 spinner 元素
                var spinner = container.querySelector('.ph-spinner-gap');

                // 延迟生成，让 spinner 至少显示 600ms
                setTimeout(function() {
                    // 创建二维码容器
                    var qrWrapper = document.createElement('div');
                    qrWrapper.style.cssText = 'opacity:0;transition:opacity 0.3s ease;';

                    // 优先使用 QRCodeStyling（美化二维码）
                    if (typeof QRCodeStyling !== 'undefined') {
                        try {
                            var qrCode = new QRCodeStyling({
                                width: 200,
                                height: 200,
                                data: window.location.href,
                                margin: 0,
                                dotsOptions: {
                                    color: '#ffffff',
                                    type: 'extra-rounded'
                                },
                                cornersSquareOptions: {
                                    color: '#ffffff',
                                    type: 'extra-rounded'
                                },
                                cornersDotOptions: {
                                    color: '#ffffff',
                                    type: 'dot'
                                },
                                backgroundOptions: {
                                    color: 'transparent'
                                }
                            });
                            qrCode.append(qrWrapper);
                            // 缩放到100px显示，保持清晰
                            var canvas = qrWrapper.querySelector('canvas');
                            if (canvas) {
                                canvas.style.width = '100px';
                                canvas.style.height = '100px';
                            }
                        } catch (e) {
                            console.warn('[RailManager] QRCodeStyling failed:', e);
                            return;
                        }
                    } else {
                        return;
                    }

                    // spinner 淡出
                    if (spinner) {
                        spinner.style.transition = 'opacity 0.2s ease';
                        spinner.style.opacity = '0';
                    }

                    // 等待 spinner 淡出后，替换为二维码并淡入
                    setTimeout(function() {
                        container.innerHTML = '';
                        container.appendChild(qrWrapper);
                        requestAnimationFrame(function() {
                            qrWrapper.style.opacity = '1';
                        });
                    }, 200);
                }, 600);
            }, 150);
        },

        _copyLink() {
            if (window.LGClipboard) {
                LGClipboard.copy(window.location.href, { success: '链接已复制到剪贴板' });
            } else if (navigator.clipboard) {
                navigator.clipboard.writeText(window.location.href).then(() => {
                    if (typeof Toastify !== 'undefined' && Toastify.showScenario) {
                        Toastify.showScenario('success', { text: '链接已复制到剪贴板' });
                    }
                });
            }
        },

        refresh() {
            // Tooltip 每次显示都会重新渲染，无需额外处理
        },

        destroy() {
            // 事件委托无需清理
        }
    };

    // ============================================
    // 全局函数（供 HTML onclick 调用）
    // ============================================
    window.switchTocMode = function(mode) {
        TocManager._switchMode(mode);
    };

    window.switchFont = function(fontType) {
        FontSwitcher._switchFont(fontType);
    };

    window.handleShare = function() {
        if (window.innerWidth < 1025) {
            if (navigator.share) {
                navigator.share({ title: document.title, url: window.location.href });
            } else {
                RailManager._copyLink();
            }
        }
    };

    window.copyLink = function() {
        RailManager._copyLink();
    };

    // ============================================
    // 自动初始化
    // ============================================
    $(function() {
        PageDetailModule.init();
        // RailManager 独立初始化（支持相册详情页等）
        RailManager.init();
    });

    // PJAX 完成后重新初始化
    $(document).on('pjax:end.lgDetail', function() {
        PageDetailModule.refresh();
        // RailManager 独立刷新
        RailManager.init();
    });

    // ============================================
    // 注册到 LGApp
    // ============================================
    if (window.LGApp) {
        window.LGApp.register('pageDetail', PageDetailModule);
    }

    // 暴露到全局
    window.LGPageDetailModule = PageDetailModule;

})(window, jQuery);
