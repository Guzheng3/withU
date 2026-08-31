/**
 * withU 点点滴滴页面模块
 * @version 3.0.0
 * @description articles.php 页面的 JS 逻辑（文章/日记列表动态加载 + 点赞系统）
 * @note 卡片列表数据来自 services/article-list.php（后台 articles 表），
 *       瀑布流和弥散光效果复用 pjax.js 的 MasonryManager 和 AuroraEffect
 */

;(function(window, $) {
    'use strict';

    // ============================================
    // 文章列表动态加载模块
    // ============================================
    const ArticleList = {
        _page: 1,
        _perPage: 12,
        _loading: false,
        _hasMore: false,
        _apiUrl: 'services/article-list.php',

        /**
         * 初始化（页面上没有静态卡片时拉取数据渲染）
         */
        init() {
            const grid = document.getElementById('withu-article-masonry');
            if (!grid) return;

            // 已有服务端直出的卡片时跳过动态加载
            if (grid.querySelector('.withu-article-masonry-item')) {
                this._afterRender(true);
                return;
            }

            this._page = 1;
            this._hasMore = false;
            this.load(1, false);
        },

        /**
         * 拉取指定页并渲染
         */
        load(page, append) {
            if (this._loading) return;
            this._loading = true;
            const self = this;

            $.ajax({
                url: `${this._apiUrl}?page=${page}&per_page=${this._perPage}`,
                dataType: 'json',
                success(res) {
                    self._loading = false;
                    if (!res || res.code !== 200 || !res.data) {
                        if (!append) self.renderEmpty('加载失败，请刷新重试');
                        return;
                    }

                    const grid = document.getElementById('withu-article-masonry');
                    if (!grid) return;

                    const loadingEl = document.getElementById('withu-article-list-loading');
                    if (loadingEl) loadingEl.remove();

                    const articles = res.data.articles || [];
                    const html = articles.map((a, i) => self.buildCard(a, i)).join('');

                    if (append) {
                        grid.insertAdjacentHTML('beforeend', html);
                    } else {
                        grid.innerHTML = html;
                    }

                    self._hasMore = !!(res.data.pagination && res.data.pagination.has_more);
                    self._page = page;
                    self._bindLoadMore(append);

                    if (!articles.length && !append) {
                        self.renderEmpty();
                    }

                    self._afterRender(!append);
                },
                error() {
                    self._loading = false;
                    if (!append) self.renderEmpty('加载失败，请刷新重试');
                }
            });
        },

        /**
         * 渲染后处理：瀑布流、弥散光、懒加载、点赞与计数
         */
        _afterRender(firstPage) {
            if (window.WithUPjax && window.WithUPjax.MasonryManager) {
                window.WithUPjax.MasonryManager.initArticleGrid();
            }
            if (window.WithUPjax && window.WithUPjax.AuroraEffect) {
                window.WithUPjax.AuroraEffect.init();
            }
            if (window.lazyLoadInstance && window.lazyLoadInstance.update) {
                window.lazyLoadInstance.update();
            }
            if (window.WithUWebpDefault) {
                window.WithUWebpDefault.rescan();
            }
            // 点赞状态与浏览/点赞计数
            if (window.WithUInteraction && window.WithUInteraction._loadStatuses) {
                window.WithUInteraction._loadStatuses();
            }
            if (typeof AOS !== 'undefined') {
                AOS.refresh();
            }
        },

        /**
         * 「加载更多」按钮
         */
        _bindLoadMore(append) {
            const grid = document.getElementById('withu-article-masonry');
            if (!grid) return;

            let wrap = document.getElementById('withu-article-load-more');
            if (!wrap) {
                wrap = document.createElement('div');
                wrap.id = 'withu-article-load-more';
                wrap.style.cssText = 'text-align:center;margin:1.75rem 0 .5rem;';
                wrap.innerHTML = '<button type="button" id="withu-article-load-more-btn" style="padding:.6rem 1.7rem;border:1px solid rgba(225,150,180,.45);border-radius:999px;background:rgba(255,255,255,.85);color:#c76b8f;font-size:.85rem;letter-spacing:.08em;cursor:pointer;transition:all .2s;">加载更多</button>';
                wrap.addEventListener('mouseenter', () => {
                    const btn = wrap.querySelector('button');
                    if (btn) btn.style.background = 'rgba(255,240,246,.95)';
                }, true);
                wrap.addEventListener('mouseleave', () => {
                    const btn = wrap.querySelector('button');
                    if (btn) btn.style.background = 'rgba(255,255,255,.85)';
                }, true);
                wrap.addEventListener('click', (e) => {
                    if (e.target.closest('#withu-article-load-more-btn')) {
                        this.load(this._page + 1, true);
                    }
                });
                grid.parentNode.insertBefore(wrap, grid.nextSibling);
            }
            wrap.style.display = this._hasMore ? '' : 'none';
        },

        /**
         * 空状态 / 错误提示
         */
        renderEmpty(msg) {
            const grid = document.getElementById('withu-article-masonry');
            if (!grid) return;
            const loadingEl = document.getElementById('withu-article-list-loading');
            if (loadingEl) loadingEl.remove();
            grid.innerHTML = `
                <div style="text-align:center;padding:4.5rem 1rem 5rem;color:#b08fa4;">
                    <i class="ph ph-notebook" style="font-size:2.2rem;"></i>
                    <p style="margin:.9rem 0 .3rem;font-size:.95rem;">${msg || '这里还没有文章或日记'}</p>
                    <span style="font-size:.8rem;opacity:.75;">发布的内容会实时显示在这里</span>
                </div>`;
        },

        /**
         * 转义工具
         */
        _escHtml(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        },

        /**
         * 构造单张卡片（与原静态模板结构一致）
         */
        buildCard(a, index) {
            const esc = (s) => this._escHtml(s);
            const delay = Math.min((index + 1) * 50, 300);
            const id = esc(a.id);

            const dayNoBadge = a.day_no
                ? `<div class="withu-article-badge-serial"><span class="withu-article-label">DAY</span><span class="withu-article-num">${esc(a.day_no)}</span></div>`
                : '';

            const gender = a.author && a.author.gender ? a.author.gender : '';
            const genderBadge = gender
                ? `<div class="withu-author__badge ${esc(gender)}"><i class="ph-bold ph-gender-${esc(gender)}"></i></div>`
                : '';
            const avatar = a.author && a.author.avatar
                ? `<div class="withu-author__avatar" style="background-image: url(${esc(a.author.avatar)})"></div>`
                : '<div class="withu-author__avatar"></div>';
            const authorName = a.author && a.author.name ? esc(a.author.name) : '';
            const authorBlock = `
                <div class="withu-author${gender ? ' show-gender' : ''}">
                    <div class="withu-author__ring">
                        ${avatar}
                        ${genderBadge}
                    </div>
                    <div class="withu-author__text">
                        <span class="withu-author__name">${authorName || '&nbsp;'}</span>
                    </div>
                </div>`;

            const titleHtml = a.title
                ? `<h3 class="withu-article-card-title">${esc(a.title)}</h3>`
                : '';
            const descText = a.encrypted ? '该内容已加密，点击输入密码查看' : (a.excerpt || '');
            const descHtml = descText
                ? `<p class="withu-article-card-desc">${esc(descText)}</p>`
                : '';

            return `
                <div class="withu-article-masonry-item" data-aos="fade-up" data-aos-delay="${delay}">
                    <div data-href="page.php?id=${id}"
                       class="withu-article-card-base withu-article-theme-light withu-article-aurora-spot"
                       style="cursor:pointer;">

                        <header class="withu-article-card-header">
                            <div class="withu-article-date-group">
                                <div class="withu-article-big-day">${esc(a.day)}</div>
                                <div class="withu-article-date-divider"></div>
                                <div class="withu-article-month-year-group">
                                    <span class="withu-article-month-chinese">${esc(a.month_cn)}</span>
                                    <span class="withu-article-year-text">${esc(a.year)}</span>
                                </div>
                            </div>
                            ${dayNoBadge}
                        </header>

                        <main class="withu-article-card-content">
                            <div class="withu-article-meta-info">
                                <div class="withu-article-meta-item">
                                    <i class="ph-duotone ph-clock"></i>
                                    <span>${esc(a.time)}</span>
                                </div>
                            </div>

                            ${titleHtml}
                            ${descHtml}
                        </main>

                        <footer class="withu-article-card-footer">
                            ${authorBlock}
                            <div class="withu-article-interact-box">
                                <div class="withu-article-action-btn">
                                    <i class="ph ph-eye"></i>
                                    <span data-view-count="article:${id}">${esc(a.views)}</span>
                                </div>
                                <div class="withu-article-action-btn" data-like-target="article" data-like-id="${id}">
                                    <i class="ph ph-heart"></i>
                                    <span class="withu-interaction-like-num" data-like-count="article:${id}">0</span>
                                </div>
                            </div>
                        </footer>
                    </div>
                </div>`;
        }
    };

    // ============================================
    // 点赞系统模块
    // ============================================
    const LikeSystem = {
        _storageKey: 'withu_article_likes',
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
            $('.withu-article-like').each(function() {
                const $btn = $(this);
                const id = $btn.attr('data-id');
                if (id && self._isLiked(id)) {
                    $btn.addClass('active');
                }
            });
        },

        /**
         * 显示 Toast 提示
         */
        _showToast(msg) {
            const $toast = $('#withu-toast');
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
            $(document).off('click.withuLike', '.withu-article-like').on('click.withuLike', '.withu-article-like', function(e) {
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
            // PJAX 反复进入页面时需要重新加载数据，这里不做一次性拦截
            LikeSystem.init();

            const grid = document.getElementById('withu-article-masonry');
            const hasStaticItems = grid && grid.querySelector('.withu-article-masonry-item');

            if (hasStaticItems) {
                // 服务端直出的静态卡片：沿用原有初始化顺序
                if (window.WithUPjax && window.WithUPjax.MasonryManager) {
                    window.WithUPjax.MasonryManager.initArticleGrid();
                }
                if (window.WithUPjax && window.WithUPjax.AuroraEffect) {
                    window.WithUPjax.AuroraEffect.init();
                }
            } else {
                ArticleList.init();
            }

            this._initialized = true;
        },

        /**
         * 销毁模块
         */
        destroy() {
            $(document).off('.withuLike');
            this._initialized = false;
        },

        /**
         * 刷新页面状态（PJAX 切换后调用）
         */
        refresh() {
            LikeSystem._render();

            const grid = document.getElementById('withu-article-masonry');
            if (grid && !grid.querySelector('.withu-article-masonry-item')) {
                ArticleList.init();
            }
        }
    };

    // ============================================
    // 自动初始化
    // ============================================
    $(function() {
        // 检测是否在点点滴滴页面
        if ($('#withu-article-masonry').length > 0) {
            LittleModule.init();
        }
    });

    // PJAX 完成后重新初始化
    $(document).on('pjax:end.withuLittle', function() {
        if ($('#withu-article-masonry').length > 0) {
            LittleModule.refresh();
        }
    });

    // ============================================
    // 注册到 WithUApp
    // ============================================
    if (window.WithUApp) {
        window.WithUApp.register('little', LittleModule);
    }

    // 暴露到全局
    window.WithULittleModule = LittleModule;
    window.WithUArticleList = ArticleList;

})(window, jQuery);
