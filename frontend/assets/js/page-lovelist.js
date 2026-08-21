/**
 * LG_NewUI 爱情清单页面模块
 * @version 2.0.0
 * @description lovelist.php 页面的 JS 逻辑（PJAX 兼容）
 */

;(function(window, $) {
    'use strict';

    // ============================================
    // 爱情清单模块
    // ============================================
    const ListModule = {
        _carouselInstances: new Map(),
        _initialized: false,
        _loadingToast: null,
        _activeLoadingToken: null,
        _requestSeq: 0,
        _skeletonTimers: [],
        _previousVisibleSelector: '#list_data',

        /**
         * 初始化模块
         */
        init() {
            // 检查页面是否存在
            if ($('#list_container').length === 0) return;

            // ── 锚点预处理：在 AOS 动画跑之前就把目标卡片及其前面的卡片动画全部跳过 ──
            this._prepareHashTarget();
            
            // 清除旧的轮播实例标记（PJAX 场景下 DOM 是新的，但可能有残留标记）
            document.querySelectorAll('#list_container .ConventionPhoto, .query_data .ConventionPhoto').forEach(el => {
                delete el.carouselInstance;
                delete el.Carousel;
                delete el.dataset.carouselInit;
            });
            
            if (!this._initialized) {
                // 首次初始化：绑定事件
                this._bindCardToggle();
                this._bindTabSwitch();
                this._bindScopeTag();
                this._bindSearch();
                this._initialized = true;
            }
            
            // 初始化 iOS 风格滑块位置
            this._initTabSlider();
            
            // 每次都重新初始化轮播（延迟确保 DOM 完全渲染）
            setTimeout(() => {
                this._initCarousels('#list_data .ConventionPhoto');
                // 检测 URL 参数，自动定位并展开对应卡片
                this._handleUrlParam();
            }, 100);
        },

        /**
         * 锚点预处理（同步调用，在 AOS 初始化前执行）
         * 跳过目标卡片及前方所有卡片的 AOS 动画 + 即时定位到目标附近
         */
        _prepareHashTarget() {
            const hash = window.location.hash;
            if (!hash || !hash.startsWith('#event-')) return;

            const eventId = hash.replace('#event-', '');
            if (!eventId) return;

            const target = document.getElementById('event-' + eventId);
            if (!target) return;

            // 同步跳过所有卡片的 AOS 动画（目标及其前面的兄弟）
            let el = target;
            while (el) {
                if (el.classList && el.classList.contains('love-card')) {
                    el.removeAttribute('data-aos');
                    el.removeAttribute('data-aos-delay');
                    el.classList.add('aos-animate');
                }
                el = el.previousElementSibling;
            }

            // 即时跳到目标附近（无动画），消除"先看到顶部再跳下去"的卡顿
            target.scrollIntoView({ behavior: 'instant', block: 'center' });

            this._hashTargetId = eventId;
        },

        /**
         * 处理 URL 参数，展开目标卡片并精确定位
         */
        _handleUrlParam() {
            if (!this._hashTargetId) return;
            const eventId = this._hashTargetId;
            this._hashTargetId = null;

            const $targetCard = $('#event-' + eventId);
            if ($targetCard.length === 0) return;

            // 刷新 AOS（前面已经跳过了动画，这里确保状态一致）
            if (typeof AOS !== 'undefined') AOS.refresh();

            // 计算导航栏遮挡高度
            const getNavOffset = () => {
                const navIsland = document.querySelector('.lgnewui-nav-island-container');
                if (navIsland) {
                    const rect = navIsland.getBoundingClientRect();
                    return rect.bottom + 50;
                }
                return 120;
            };

            // 精确滚动到目标卡片
            const scrollToCard = (smooth) => {
                const navOffset = getNavOffset();
                const cardRect = $targetCard[0].getBoundingClientRect();
                const scrollY = window.pageYOffset + cardRect.top - navOffset;
                window.scrollTo({ top: Math.max(0, scrollY), behavior: smooth ? 'smooth' : 'instant' });
            };

            // 即时定位一次（无动画），确保视口已在目标附近
            scrollToCard(false);

            // 展开卡片
            const $cardBody = $targetCard.find('.card-body');
            $('.love-card').not($targetCard).removeClass('active').find('.card-body').slideUp(300);
            $targetCard.addClass('active');
            $cardBody.slideDown(300, function() {
                if (window.lazyLoadInstance) {
                    window.lazyLoadInstance.update();
                }
                window.dispatchEvent(new Event('resize'));
                // 展开完成后，平滑微调到精确位置
                requestAnimationFrame(() => scrollToCard(true));
            });

            // 高亮效果
            $targetCard.addClass('lgnewui-highlight');
            setTimeout(() => {
                $targetCard.removeClass('lgnewui-highlight lgnewui-highlight-fade');
            }, 4500);
        },

        /**
         * 销毁模块
         */
        destroy() {
            // 销毁轮播实例
            this._carouselInstances.forEach((instance, container) => {
                if (instance && typeof instance.destroy === 'function') {
                    try {
                        instance.destroy();
                    } catch (e) {
                        // 忽略销毁错误
                    }
                }
                // 清除标记
                if (container) {
                    delete container.carouselInstance;
                    delete container.Carousel;
                    delete container.dataset.carouselInit;
                }
            });
            this._carouselInstances.clear();
            this._clearCardsSkeleton();
            this._activeLoadingToken = null;
            if (this._loadingToast) {
                this._loadingToast.hideToast();
                this._loadingToast = null;
            }
            
            // 清除页面上所有轮播标记（防止遗漏）
            document.querySelectorAll('[data-carousel-init]').forEach(el => {
                delete el.dataset.carouselInit;
                delete el.carouselInstance;
            });

            this._initialized = false;
        },

        /**
         * 重新初始化（PJAX 切换后调用）
         */
        reinit() {
            // 先彻底销毁
            this.destroy();
            this._initialized = false;
            
            // 延迟初始化，确保 DOM 完全更新
            setTimeout(() => {
                this.init();
            }, 50);
        },

        /**
         * 初始化轮播图
         * @param {string} selector
         */
        _initCarousels(selector) {
            if (typeof Carousel === 'undefined') return;
            
            const self = this;

            document.querySelectorAll(selector).forEach(container => {
                // 跳过已初始化的轮播（检查多种标记）
                if (container.carouselInstance || container.Carousel || container.dataset.carouselInit) return;
                
                // 标记为已初始化
                container.dataset.carouselInit = 'true';
                
                // 获取对应的 counter 元素（在初始化时就确定，避免闭包问题）
                const gallery = container.closest('.body-gallery');
                const counter = gallery ? gallery.querySelector('.img-counter') : null;

                try {
                    const options = {
                        Dots: false,
                        Navigation: false,
                        infinite: true,
                        on: {
                            change: (carousel, to) => {
                                // 直接使用预先获取的 counter 引用
                                if (counter) {
                                    const newIndex = typeof to === 'number' ? to : carousel.page;
                                    counter.textContent = `${newIndex + 1} / ${carousel.slides.length}`;
                                }
                            }
                        }
                    };
                    
                    // 如果 Thumbs 存在则传入（支持滑动）
                    const plugins = typeof Thumbs !== 'undefined' ? { Thumbs } : {};
                    const instance = new Carousel(container, options, plugins);

                    container.carouselInstance = instance;
                    self._carouselInstances.set(container, instance);
                } catch (e) {
                    console.warn('[ListModule] Carousel init error:', e);
                }
            });
        },

        /**
         * 绑定卡片展开/收起
         */
        _bindCardToggle() {
            $(document).off('click.lgListCard', '.card-header');
            
            $(document).on('click.lgListCard', '.card-header', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const $thisHeader = $(this);
                const $thisCard = $thisHeader.closest('.love-card');
                const $thisBody = $thisCard.find('.card-body');
                const isVisible = $thisBody.is(':visible');

                // 收起其他卡片
                const $otherCards = $('.love-card').not($thisCard);
                $otherCards.removeClass('active');
                $otherCards.find('.card-body').slideUp(300);

                if (!isVisible) {
                    $thisCard.addClass('active');
                    $thisBody.slideDown(300, () => {
                        // 更新懒加载
                        if (window.lazyLoadInstance) {
                            window.lazyLoadInstance.update();
                        }
                        // 触发 resize 以更新轮播
                        window.dispatchEvent(new Event('resize'));
                    });
                } else {
                    $thisCard.removeClass('active');
                    $thisBody.slideUp(300);
                }
            });
        },

        /**
         * 初始化 iOS 风格 Tab 滑块位置
         */
        _initTabSlider() {
            const activeTab = document.querySelector('.LgLoveList-tab.LgLoveList-tab-active');
            const slider = document.querySelector('.LgLoveList-tab-slider');
            if (activeTab && slider) {
                slider.style.width = activeTab.offsetWidth + 'px';
                slider.style.transform = 'translateX(' + activeTab.offsetLeft + 'px)';
            }
        },

        /**
         * 移动滑块到指定 Tab
         */
        _moveSliderTo(tabEl) {
            const slider = document.querySelector('.LgLoveList-tab-slider');
            if (slider && tabEl) {
                slider.style.width = tabEl.offsetWidth + 'px';
                slider.style.transform = 'translateX(' + tabEl.offsetLeft + 'px)';
            }
        },

        /**
         * 给所有可见卡片添加骨架屏效果，然后延迟移除
         */
        _clearCardsSkeleton() {
            this._skeletonTimers.forEach((timerId) => clearTimeout(timerId));
            this._skeletonTimers = [];
            $('#lgListSkeleton').removeClass('is-active');
        },

        _freezeContainerHeight() {
            const container = document.getElementById('list_container');
            if (!container) return;

            const currentHeight = Math.ceil(container.getBoundingClientRect().height);
            if (currentHeight > 0) {
                container.style.minHeight = `${currentHeight}px`;
            }
        },

        _expandContainerHeightForSkeleton() {
            const container = document.getElementById('list_container');
            const skeleton = document.getElementById('lgListSkeleton');
            if (!container || !skeleton) return;

            const currentMinHeight = parseInt(container.style.minHeight || '0', 10) || 0;
            const skeletonHeight = Math.ceil(skeleton.scrollHeight);
            const targetHeight = Math.max(currentMinHeight, skeletonHeight);
            if (targetHeight > 0) {
                container.style.minHeight = `${targetHeight}px`;
            }
        },

        _releaseContainerHeight(delay = 280) {
            const container = document.getElementById('list_container');
            if (!container) return;

            window.setTimeout(() => {
                container.style.minHeight = '';
            }, delay);
        },

        _revealContent($target) {
            if (!$target || $target.length === 0) {
                this._clearCardsSkeleton();
                this._releaseContainerHeight(0);
                return;
            }

            $('#list_data, .query_data').hide().removeClass('lg-list-loading-stage');
            $target.show().addClass('lg-list-fade-in');

            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    this._clearCardsSkeleton();
                    // 释放容器高度限制
                    this._releaseContainerHeight(400);
                    // 移除淡入动画类（动画结束后）
                    setTimeout(() => {
                        $target.removeClass('lg-list-fade-in');
                    }, 400);
                });
            });
        },

        _restorePreviousContent() {
            const selector = this._previousVisibleSelector || '#list_data';
            this._revealContent($(selector));
        },

        _showCardsSkeleton() {
            this._clearCardsSkeleton();
            this._previousVisibleSelector = $('.query_data:visible').length > 0 ? '.query_data' : '#list_data';
            // 先冻结当前高度，防止页面跳动
            this._freezeContainerHeight();
            // 直接隐藏原始数据
            $('#list_data, .query_data').hide();
            $('#lgListSkeleton').addClass('is-active');
            // 扩展到骨架屏高度
            this._expandContainerHeightForSkeleton();
        },

        _startLoadingState(type) {
            const isSearch = type === 4;
            const minDuration = isSearch ? 360 : 480;
            const token = {};

            if (this._loadingToast) {
                this._loadingToast.hideToast();
                this._loadingToast = null;
            }

            this._showCardsSkeleton();

            if (typeof Toastify !== 'undefined' && Toastify.showScenario) {
                this._loadingToast = Toastify.showScenario('loading', {
                    text: isSearch ? '正在查询...' : '正在切换...'
                });
            }

            this._activeLoadingToken = token;

            return {
                startedAt: Date.now(),
                minDuration: minDuration,
                token: token
            };
        },

        _finishLoadingState(loadingState, callback) {
            const elapsed = Date.now() - loadingState.startedAt;
            const remaining = Math.max(0, loadingState.minDuration - elapsed);

            setTimeout(() => {
                if (this._activeLoadingToken !== loadingState.token) {
                    return;
                }

                this._activeLoadingToken = null;
                if (this._loadingToast) {
                    this._loadingToast.hideToast();
                    this._loadingToast = null;
                }
                if (typeof callback === 'function') {
                    callback();
                }
            }, remaining);
        },

        /**
         * 绑定 Tab 切换
         */
        _bindTabSwitch() {
            const self = this;

            $(document).off('click.lgListTab', '.LgLoveList-tab');
            $(document).on('click.lgListTab', '.LgLoveList-tab', function() {
                const screenCode = $(this).data('id');
                
                // 更新 Tab 状态
                $('.LgLoveList-tab').removeClass('LgLoveList-tab-active');
                $(this).addClass('LgLoveList-tab-active');

                // 移动 iOS 弹性滑块
                self._moveSliderTo(this);

                // 确定查询代码
                let code;
                switch (screenCode) {
                    case 1: code = 'Success'; break;
                    case 3: code = 'Fail'; break;
                    default: code = 'All';
                }

                // 联动逻辑：切到「全部」Tab 时，重置搜索范围
                if (screenCode === 2) {
                    const $scopeTag = $('#scopeTag');
                    $scopeTag.data('scope', 'all');
                    $scopeTag.find('.scope-tag-text').text('全部');
                    $scopeTag.find('i:first').removeClass('fa-filter').addClass('fa-heart');
                    $scopeTag.removeClass('active');
                }

                self._queryList(screenCode, code, null);
            });
        },

        /**
         * 绑定搜索范围胶囊
         */
        _bindScopeTag() {
            $(document).off('click.lgListScope', '#scopeTag');
            $(document).on('click.lgListScope', '#scopeTag', function() {
                const currentScope = $(this).data('scope');
                const currentTab = $('.LgLoveList-tab-active').data('id');

                // 如果当前是「全部」Tab，点击无效
                if (currentTab === 2) {
                    Toastify.showScenario('info', { text: '请先选择「已完成」或「未完成」分类' });
                    return;
                }

                const newScope = currentScope === 'all' ? 'current' : 'all';
                const newText = newScope === 'all' ? '全部' : '分类';
                const newIcon = newScope === 'all' ? 'fa-heart' : 'fa-filter';

                $(this).data('scope', newScope);
                $(this).find('.scope-tag-text').text(newText);
                $(this).find('i:first').removeClass('fa-heart fa-filter').addClass(newIcon);
                $(this).toggleClass('active', newScope === 'current');
            });
        },

        /**
         * 绑定搜索按钮
         */
        _bindSearch() {
            const self = this;

            $(document).off('click.lgListSearch', '#search_btn');
            $(document).on('click.lgListSearch', '#search_btn', function() {
                const searchInfo = $("input[name='search']").val();
                
                if (!searchInfo) {
                    Toastify.showScenario('warning', { text: '请输入关键词' });
                    return;
                }

                // 获取搜索范围
                const selectedScope = $('#scopeTag').data('scope');
                const currentTab = $('.LgLoveList-tab-active').data('id');
                let statusFilter = null;

                // 如果选择"当前分类"且不在"全部"Tab
                if (selectedScope === 'current' && currentTab !== 2) {
                    statusFilter = currentTab === 1 ? 'Success' : 'Fail';
                }

                self._queryList(4, searchInfo, statusFilter);
            });

            // 回车搜索
            $(document).off('keypress.lgListSearch', "input[name='search']");
            $(document).on('keypress.lgListSearch', "input[name='search']", function(e) {
                if (e.which === 13) {
                    $('#search_btn').trigger('click');
                }
            });
        },

        /**
         * 查询列表
         * @param {number} type - 查询类型
         * @param {string} code - 查询代码
         * @param {string|null} statusFilter - 状态过滤
         */
        _queryList(type, code, statusFilter) {
            const self = this;
            const $btn = $('#search_btn');
            const requestSeq = ++self._requestSeq;
            const loadingState = self._startLoadingState(type);

            // 搜索时显示 loading
            if (type === 4) {
                $btn.html('<i class="fa-solid fa-spinner fa-spin"></i><span>查询中</span>').prop('disabled', true);
            }

            // 构建请求数据
            const requestData = { search_info: code };
            if (statusFilter) {
                requestData.status_filter = statusFilter;
            }

            $.ajax({
                url: 'services/lovelist-search.php',
                data: requestData,
                type: 'POST',
                dataType: 'json',
                success(res) {
                    self._finishLoadingState(loadingState, () => {
                        if (requestSeq !== self._requestSeq) {
                            return;
                        }

                        // 恢复按钮状态
                        if (type === 4) {
                            setTimeout(() => {
                                $btn.html('<i class="fa-solid fa-magnifying-glass"></i><span>查询</span>').prop('disabled', false);
                            }, 800);
                        }

                        if (res && res.length > 0) {
                            if (type === 4) {
                                Toastify.showScenario('success', { text: '查询成功' });
                            }

                            $('#list_data').hide();
                            $('.query_data').empty();

                            // 排序
                            if (res[0].id) {
                                res.sort((a, b) => b.id - a.id);
                            } else {
                                res.reverse();
                            }

                            // 渲染结果
                            const html = self._renderCards(res);
                            $('.query_data').html(html);
                            self._revealContent($('.query_data'));

                            // 初始化轮播
                            self._initCarousels('.query_data .ConventionPhoto');

                            // 更新懒加载
                            if (window.lazyLoadInstance) {
                                window.lazyLoadInstance.update();
                            }

                            // 渲染 Lucide 图标
                            if (typeof lucide !== 'undefined') {
                                lucide.createIcons();
                            }

                            // 刷新 AOS 动画
                            if (typeof AOS !== 'undefined') {
                                AOS.refresh();
                            }

                        } else {
                            $('#list_data').hide();
                            $('.query_data').html(
                                '<div class="lgnewui-no-data lgnewui-no-data--search">' +
                                '<div class="lgnewui-no-data-wrap"><div class="lgnewui-no-data-content">' +
                                '<div class="lgnewui-no-data-icon lgnewui-no-data-icon--search">' +
                                '<svg viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="5.8"/><path d="m19 19-3.6-3.6"/><path d="M9.6 11h2.8"/></svg>' +
                                '</div>' +
                                '<h3 class="lgnewui-no-data-title">\u6ca1\u6709\u627e\u5230\u5339\u914d\u7684\u5185\u5bb9</h3>' +
                                '<p class="lgnewui-no-data-desc">\u6362\u4e00\u4e2a\u5173\u952e\u8bcd\u3001\u51cf\u5c11\u7b5b\u9009\u6761\u4ef6\uff0c\u6216\u8005\u76f4\u63a5\u8fd4\u56de\u67e5\u770b\u5168\u90e8\u5185\u5bb9\u3002</p>' +
                                '<div class="lgnewui-no-data-actions">' +
                                '<a class="lgnewui-no-data-btn lgnewui-no-data-btn-primary" href="javascript:;" onclick="$(\'.LgLoveList-tab[data-id=2]\').trigger(\'click\');"><i class="ph ph-arrow-counter-clockwise"></i> \u67e5\u770b\u5168\u90e8</a>' +
                                '</div>' +
                                '</div></div></div>'
                            );
                            self._revealContent($('.query_data'));
                            Toastify.showScenario('info', { text: '未找到相关记录' });
                        }
                    });
                },
                error() {
                    self._finishLoadingState(loadingState, () => {
                        if (requestSeq !== self._requestSeq) {
                            return;
                        }

                        $btn.html('<i class="fa-solid fa-magnifying-glass"></i><span>查询</span>').prop('disabled', false);
                        self._restorePreviousContent();
                        Toastify.showScenario('error', { text: '查询失败，请重试' });
                    });
                }
            });
        },

        /**
         * 渲染卡片 HTML
         * @param {Array} items
         * @returns {string}
         */
        _renderCards(items) {
            return items.map((item) => {
                const statusClass = item.icon ? 'com' : 'air';
                // 兼容两种字段名：搜索API返回finish_date，首页API返回time
                const finishTime = item.finish_date || item.time || '---';
                // 兼容两种字段名：搜索API返回city，首页API返回place
                const place = item.city || item.place || '---';
                const remark = item.remark || item.note || '';
                
                const remarkHtml = remark 
                    ? `<div class="info-item remark-item"><span class="info-label">甜蜜备注 / NOTE</span><span class="info-value">${this._escapeHtml(remark)}</span></div>` 
                    : '';

                // 有经纬度且已完成时，地点可点击打开地图（图标在前）
                const hasCoords = item.lng && item.lat && isFinite(item.lng) && isFinite(item.lat);
                const hasImg = item.imgurl && item.imgurl.length > 0 && item.imgurl[0] !== '0';
                let placeHtml;
                if (hasCoords && item.icon) {
                    placeHtml = `<span class="info-value lovelist-location-link has-coords" onclick="event.stopPropagation(); if(window.LGMap) LGMap.open({ mode:'events', coords:[${item.lng},${item.lat}], zoom:15 });" data-tooltip="${this._escapeHtml(place)}"><i class="ph-fill ph-map-pin"></i><span>${this._escapeHtml(place)}</span></span>`;
                } else {
                    placeHtml = `<span class="info-value">${this._escapeHtml(place)}</span>`;
                }

                // 标题后标识图标
                let tagsHtml = '<span class="event-tags">';
                if (hasImg) tagsHtml += '<span class="etag"><i data-lucide="image" title="有照片"></i></span>';
                if (hasCoords) tagsHtml += '<span class="etag"><i data-lucide="map-pin" title="有定位"></i></span>';
                if (remark) tagsHtml += '<span class="etag"><i data-lucide="notebook-pen" title="有备注"></i></span>';
                tagsHtml += '</span>';

                // 成就水印（已完成项）
                const watermarkHtml = item.icon
                    ? '<div class="achievement-watermark"><i class="ph-fill ph-seal-check"></i></div>'
                    : '<div class="achievement-watermark wm-pending"><i class="ph-fill ph-seal-check"></i></div>';

                let imgHtml = '';
                if (hasImg) {
                    const validUrls = item.imgurl.filter(url => url && url !== '0');
                    const slides = validUrls.map(url => 
                        `<div class="f-carousel__slide"><img class="lazy" draggable="false" data-src="${this._escapeHtml(url)}" /></div>`
                    ).join('');
                    
                    imgHtml = `
                        <div class="img-counter">1 / ${validUrls.length}</div>
                        <div class="f-carousel ConventionPhoto" view-image>${slides}</div>
                    `;
                } else {
                    imgHtml = `
                        <div class="no-img-placeholder">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
                            </svg>
                            <span>暂无影像</span>
                        </div>
                    `;
                }

                return `
                    <div class="love-card">
                        <div class="card-header">
                            <div class="header-left">
                                <span class="status-dot ${statusClass}"></span>
                                <span class="event-name">${this._escapeHtml(item.eventname)}</span>
                                ${tagsHtml}
                            </div>
                            <div class="header-right">
                                <span class="toggle-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M6 9l6 6 6-6"/>
                                    </svg>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="body-content">
                                ${watermarkHtml}
                                <div class="body-gallery">${imgHtml}</div>
                                <div class="body-info">
                                    <div class="body-full-title">${this._escapeHtml(item.eventname)}</div>
                                    <div class="info-item">
                                        <span class="info-label">完成时间 / TIME</span>
                                        <span class="info-value">${this._escapeHtml(finishTime)}</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">达成地点 / LOCATION</span>
                                        ${placeHtml}
                                    </div>
                                    ${remarkHtml}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        },

        /**
         * HTML 转义
         * @param {string} str
         * @returns {string}
         */
        _escapeHtml(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    };

    // ============================================
    // 暴露到全局
    // ============================================
    window.LGListModule = ListModule;
    window.initListPage = () => ListModule.init();

    // ============================================
    // 自动初始化（仅首次加载）
    // ============================================
    
    // DOM Ready 时初始化（首次加载 / 直接刷新页面）
    $(function() {
        if ($('#list_container').length > 0) {
            ListModule.init();
        }
    });

    // 注意：PJAX 场景由 lg-pjax.js 调用 window.initListPage() 处理
    // 不要在这里重复监听 pjax:complete，否则会导致重复初始化

})(window, jQuery);
