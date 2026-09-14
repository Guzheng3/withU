/**
 * LGNewUi Mobile Navigation - lg-mobile-nav.js
 * 移动端底部 Tab 栏交互逻辑（6套方案）
 * 根据页面中存在的 DOM 结构自动初始化，支持 pjax 重新初始化
 */
(function () {
    'use strict';

    /**
     * 路径匹配映射：详情页 -> 父级页面
     * 例如：/page.php?id=1 -> /articles.php (文章详情 -> 文章列表)
     */
    var pathMappings = [
        { pattern: /^\/page\.php/, target: '/articles.php' },      // 文章详情 -> 文章
        { pattern: /^\/album-detail\.php/, target: '/albums.php' }, // 相册详情 -> 相册
        { pattern: /^\/lovelist\.php/, target: '/lovelist.php' }   // 清单详情 -> 清单
    ];

    /**
     * 标准化路径：统一处理首页和末尾斜杠
     */
    function normalizePath(path) {
        // 移除末尾斜杠
        path = path.replace(/\/$/, '') || '/';
        // 首页统一为 /index.php
        if (path === '/' || path === '') {
            return '/index.php';
        }
        return path;
    }

    /**
     * 根据当前 URL 设置高亮
     */
    function setActiveByUrl(items, activeClass) {
        var currentPath = normalizePath(window.location.pathname);
        
        // 检查是否是详情页，需要匹配父级
        var mappedPath = currentPath;
        for (var i = 0; i < pathMappings.length; i++) {
            if (pathMappings[i].pattern.test(currentPath)) {
                mappedPath = pathMappings[i].target;
                break;
            }
        }
        
        items.forEach(function (item) {
            var href = item.getAttribute('href');
            if (href) {
                // 解析相对路径为绝对路径
                var link = document.createElement('a');
                link.href = href;
                var itemPath = normalizePath(link.pathname);
                
                // 精确匹配或映射匹配
                var isActive = (itemPath === currentPath) || (itemPath === mappedPath);
                item.classList.toggle(activeClass || 'active', isActive);
            }
        });
    }

    /**
     * 检查高亮逻辑：
     * - 如果当前页面在一级dock中有对应项，则一级dock高亮，更多按钮不高亮
     * - 如果当前页面不在一级dock中，则更多按钮高亮
     */
    function updateMoreBtnHighlight(dockItems, gridItems, moreBtn) {
        if (!moreBtn) return;
        var hasActiveInDock = false;
        
        dockItems.forEach(function (item) {
            if (item.classList.contains('active')) hasActiveInDock = true;
        });
        
        // 如果dock中没有高亮项，则更多按钮高亮
        if (!hasActiveInDock) {
            moreBtn.classList.add('active');
        } else {
            moreBtn.classList.remove('active');
        }
    }

    /* ================================================================
       方案 1：灵动伸缩栏
       ================================================================ */
    function initV1() {
        var wrapper = document.getElementById('lgnewui-mobile-nav-v1');
        if (!wrapper) return;

        var nav = wrapper.querySelector('.lgnewui-tab-template-v1-nav');
        var items = Array.from(wrapper.querySelectorAll('.js-lgnewui-v1-item'));
        var indicator = wrapper.querySelector('.lgnewui-tab-template-v1-indicator');
        var baseItemWidth = 0;

        function updateIndicator(target, instant) {
            if (!target) return;
            if (baseItemWidth === 0 && items.length > 0) {
                var testItem = items[0];
                if (testItem.offsetWidth > 0) {
                    var tw = testItem.querySelector('.lgnewui-tab-template-v1-text-wrap');
                    var oldMax = tw.style.maxWidth;
                    tw.style.maxWidth = '0px';
                    baseItemWidth = testItem.offsetWidth || 48;
                    tw.style.maxWidth = oldMax;
                } else {
                    baseItemWidth = 48;
                }
            }

            var targetIndex = items.indexOf(target);
            var currentLeft = 6;
            var targetWidth = 0;

            items.forEach(function (item, idx) {
                var tw = item.querySelector('.lgnewui-tab-template-v1-text-wrap');
                var text = item.querySelector('.lgnewui-tab-template-v1-text');
                var exactTextWidth = text ? text.scrollWidth : 0;
                if (idx === targetIndex) {
                    tw.style.maxWidth = exactTextWidth + 'px';
                    tw.style.opacity = '1';
                    targetWidth = baseItemWidth + exactTextWidth;
                } else {
                    tw.style.maxWidth = '0px';
                    tw.style.opacity = '0';
                }
                if (idx < targetIndex) currentLeft += baseItemWidth;
            });

            if (instant) {
                indicator.style.transition = 'none';
                indicator.style.left = currentLeft + 'px';
                indicator.style.width = targetWidth + 'px';
                void indicator.offsetWidth;
                indicator.style.transition = '';
            } else {
                indicator.style.left = currentLeft + 'px';
                indicator.style.width = targetWidth + 'px';
            }

            var scrollTarget = currentLeft - (nav.clientWidth / 2) + (targetWidth / 2);
            nav.scrollTo({ left: scrollTarget, behavior: instant ? 'auto' : 'smooth' });
        }

        items.forEach(function (item) {
            item.addEventListener('click', function (e) {
                if (item.tagName === 'A') return; // 允许链接导航，不阻断
                items.forEach(function (i) { i.classList.remove('active'); });
                item.classList.add('active');
                updateIndicator(item, false);
            });
            // 链接类型：点击后更新 active 状态（视觉反馈）
            item.addEventListener('mousedown', function () {
                items.forEach(function (i) { i.classList.remove('active'); });
                item.classList.add('active');
                updateIndicator(item, false);
            });
        });

        // 根据 URL 设置高亮
        setActiveByUrl(items, 'active');
        
        setTimeout(function () {
            var active = wrapper.querySelector('.js-lgnewui-v1-item.active');
            if (active) updateIndicator(active, true);
        }, 100);

        window.recalcLgnewuiV1 = function () {
            baseItemWidth = 0;
            var active = wrapper.querySelector('.js-lgnewui-v1-item.active');
            if (active) updateIndicator(active, true);
        };
    }

    /* ================================================================
       方案 2：全息展开层
       ================================================================ */
    function initV2() {
        var wrapper = document.getElementById('lgnewui-mobile-nav-v2');
        if (!wrapper) return;

        var overlay = wrapper.querySelector('.js-lgnewui-v2-overlay');
        var toggleBtns = wrapper.querySelectorAll('.js-lgnewui-v2-toggle');
        var dockItems = wrapper.querySelectorAll('.js-lgnewui-v2-dock-item');
        var gridItems = wrapper.querySelectorAll('.js-lgnewui-v2-grid-item');

        function toggleOverlay() {
            if (overlay) overlay.classList.toggle('open');
        }

        toggleBtns.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                toggleOverlay();
            });
        });

        // 选择dock中的更多按钮（排除sheet中的关闭按钮）
        var moreBtn = wrapper.querySelector('.lgnewui-tab-template-v2-dock .js-lgnewui-v2-toggle');
        
        // 根据 URL 设置高亮
        setActiveByUrl(Array.from(dockItems), 'active');
        setActiveByUrl(Array.from(gridItems), 'active');
        updateMoreBtnHighlight(Array.from(dockItems), Array.from(gridItems), moreBtn);
        
        dockItems.forEach(function (item) {
            item.addEventListener('mousedown', function () {
                dockItems.forEach(function (i) { i.classList.remove('active'); });
                item.classList.add('active');
                if (moreBtn) moreBtn.classList.remove('active');
            });
        });

        gridItems.forEach(function (item) {
            item.addEventListener('mousedown', function () {
                gridItems.forEach(function (i) { i.classList.remove('active'); });
                item.classList.add('active');
                
                // 检查一级dock中是否有对应的项（通过href匹配）
                var clickedHref = item.getAttribute('href');
                var foundInDock = false;
                dockItems.forEach(function (dockItem) {
                    dockItem.classList.remove('active');
                    if (dockItem.getAttribute('href') === clickedHref) {
                        dockItem.classList.add('active');
                        foundInDock = true;
                    }
                });
                
                // 如果一级dock中没有对应项，则更多按钮高亮
                if (moreBtn) {
                    moreBtn.classList.toggle('active', !foundInDock);
                }
                
                setTimeout(function () {
                    if (overlay) overlay.classList.remove('open');
                }, 200);
            });
        });
    }

    /* ================================================================
       方案 3：沉浸网格拉伸
       ================================================================ */
    function initV3() {
        var wrapper = document.getElementById('lgnewui-mobile-nav-v3');
        if (!wrapper) return;

        var inner = wrapper.querySelector('.lgnewui-tab-template-v3-container');
        var toggleBtn = wrapper.querySelector('.js-lgnewui-v3-toggle');
        var toggleIcon = toggleBtn ? toggleBtn.querySelector('i') : null;
        var items = wrapper.querySelectorAll('.js-lgnewui-v3-item');
        var menuItems = wrapper.querySelectorAll('.js-lgnewui-v3-menu-item');
        var overlay = document.querySelector('.js-lgnewui-v3-overlay');

        function toggleGrid() {
            var isExpanded = wrapper.classList.toggle('is-expanded');
            if (inner) inner.classList.toggle('is-expanded', isExpanded);
            if (overlay) overlay.classList.toggle('active', isExpanded);
            if (toggleIcon) {
                if (isExpanded) {
                    toggleIcon.className = toggleIcon.className.replace('ph-list', 'ph-caret-down');
                } else {
                    toggleIcon.className = toggleIcon.className.replace('ph-caret-down', 'ph-list');
                }
            }
        }

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function (e) {
                e.preventDefault();
                toggleGrid();
            });
        }

        if (overlay) {
            overlay.addEventListener('click', function () {
                if (wrapper.classList.contains('is-expanded')) toggleGrid();
            });
        }

        var toggleBtn = wrapper.querySelector('.js-lgnewui-v3-toggle');
        
        // 根据 URL 设置高亮
        setActiveByUrl(Array.from(items), 'active');
        setActiveByUrl(Array.from(menuItems), 'active');
        updateMoreBtnHighlight(Array.from(items), Array.from(menuItems), toggleBtn);
        
        items.forEach(function (item) {
            item.addEventListener('mousedown', function () {
                items.forEach(function (i) { i.classList.remove('active'); });
                menuItems.forEach(function (i) { i.classList.remove('active'); });
                item.classList.add('active');
                if (toggleBtn) toggleBtn.classList.remove('active');
            });
        });

        menuItems.forEach(function (item) {
            item.addEventListener('mousedown', function () {
                menuItems.forEach(function (i) { i.classList.remove('active'); });
                item.classList.add('active');
                
                // 检查一级dock中是否有对应的项（通过href匹配）
                var clickedHref = item.getAttribute('href');
                var foundInDock = false;
                items.forEach(function (dockItem) {
                    dockItem.classList.remove('active');
                    if (dockItem.getAttribute('href') === clickedHref) {
                        dockItem.classList.add('active');
                        foundInDock = true;
                    }
                });
                
                // 如果一级dock中没有对应项，则更多按钮高亮
                if (toggleBtn) {
                    toggleBtn.classList.toggle('active', !foundInDock);
                }
                
                setTimeout(toggleGrid, 250);
            });
        });
    }

    /* ================================================================
       方案 4：横向无级滑动
       ================================================================ */
    function initV4() {
        var wrapper = document.getElementById('lgnewui-mobile-nav-v4');
        if (!wrapper) return;

        var items = wrapper.querySelectorAll('.js-lgnewui-v4-item');
        
        // 根据 URL 设置高亮
        setActiveByUrl(Array.from(items), 'active');
        
        items.forEach(function (item) {
            item.addEventListener('mousedown', function () {
                items.forEach(function (i) { i.classList.remove('active'); });
                item.classList.add('active');
                item.scrollIntoView({ behavior: 'smooth', inline: 'center' });
            });
        });
    }

    /* ================================================================
       方案 5：极简包裹点阵
       ================================================================ */
    function initV5() {
        var track = document.getElementById('lgnewui-mobile-nav-v5');
        if (!track) return;

        var items = Array.from(track.querySelectorAll('.js-lgnewui-v5-item'));
        var indicator = track.querySelector('.lgnewui-tab-template-v5-indicator');

        function setActive(el, noAnim) {
            items.forEach(function (i) { i.classList.remove('active'); });
            el.classList.add('active');
            if (indicator) {
                indicator.style.transition = noAnim ? 'none' : 'left 0.4s cubic-bezier(0.34, 1.56, 0.64, 1)';
                indicator.style.left = el.offsetLeft + 'px';
            }
            var containerWidth = track.clientWidth;
            var scrollTarget = el.offsetLeft - (containerWidth / 2) + (el.offsetWidth / 2);
            track.scrollTo({ left: scrollTarget, behavior: noAnim ? 'auto' : 'smooth' });
        }

        // 根据 URL 设置高亮
        setActiveByUrl(items, 'active');
        
        items.forEach(function (item) {
            item.addEventListener('mousedown', function () { setActive(this, false); });
        });

        window.recalcLgnewuiV5 = function () {
            var active = track.querySelector('.js-lgnewui-v5-item.active');
            if (active) setActive(active, true);
        };

        setTimeout(function () {
            window.recalcLgnewuiV5();
        }, 100);
    }

    /* ================================================================
       方案 6：玻璃全抽屉
       ================================================================ */
    function initV6() {
        var wrapper = document.getElementById('lgnewui-mobile-nav-v6');
        if (!wrapper) return;

        var toggleBtns = wrapper.querySelectorAll('.js-lgnewui-v6-toggle');
        var barItems = wrapper.querySelectorAll('.js-lgnewui-v6-bar-item');
        var gridItems = wrapper.querySelectorAll('.js-lgnewui-v6-grid-item');
        var gridContainer = wrapper.querySelector('.lgnewui-tab-template-v6-grid');

        if (gridContainer && gridItems.length <= 4) {
            gridContainer.classList.add('is-few');
        }

        toggleBtns.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                wrapper.classList.toggle('is-open');
            });
        });

        // 选择bar中的更多按钮（排除panel中的关闭按钮）
        var moreBtn = wrapper.querySelector('.lgnewui-tab-template-v6-bar .js-lgnewui-v6-toggle');
        
        // 根据 URL 设置高亮
        setActiveByUrl(Array.from(barItems), 'active');
        setActiveByUrl(Array.from(gridItems), 'active');
        updateMoreBtnHighlight(Array.from(barItems), Array.from(gridItems), moreBtn);
        
        barItems.forEach(function (item) {
            item.addEventListener('mousedown', function () {
                barItems.forEach(function (i) { i.classList.remove('active'); });
                gridItems.forEach(function (i) { i.classList.remove('active'); });
                item.classList.add('active');
                if (moreBtn) moreBtn.classList.remove('active');
            });
        });

        gridItems.forEach(function (item) {
            item.addEventListener('mousedown', function () {
                gridItems.forEach(function (i) { i.classList.remove('active'); });
                item.classList.add('active');
                
                // 检查一级bar中是否有对应的项（通过href匹配）
                var clickedHref = item.getAttribute('href');
                var foundInBar = false;
                barItems.forEach(function (barItem) {
                    barItem.classList.remove('active');
                    if (barItem.getAttribute('href') === clickedHref) {
                        barItem.classList.add('active');
                        foundInBar = true;
                    }
                });
                
                // 如果一级bar中没有对应项，则更多按钮高亮
                if (moreBtn) {
                    moreBtn.classList.toggle('active', !foundInBar);
                }
                
                setTimeout(function () { wrapper.classList.remove('is-open'); }, 150);
            });
        });
    }

    /* ================================================================
       自动初始化入口
       ================================================================ */
    function init() {
        initV1();
        initV2();
        initV3();
        initV4();
        initV5();
        initV6();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    /**
     * PJAX后刷新高亮状态（不重新绑定事件）
     */
    function refreshHighlight() {
        // 方案1
        var v1 = document.getElementById('lgnewui-mobile-nav-v1');
        if (v1) {
            var v1Items = Array.from(v1.querySelectorAll('.js-lgnewui-v1-item'));
            setActiveByUrl(v1Items, 'active');
            if (window.recalcLgnewuiV1) window.recalcLgnewuiV1();
        }
        
        // 方案2
        var v2 = document.getElementById('lgnewui-mobile-nav-v2');
        if (v2) {
            var v2DockItems = Array.from(v2.querySelectorAll('.js-lgnewui-v2-dock-item'));
            var v2GridItems = Array.from(v2.querySelectorAll('.js-lgnewui-v2-grid-item'));
            var v2MoreBtn = v2.querySelector('.lgnewui-tab-template-v2-dock .js-lgnewui-v2-toggle');
            setActiveByUrl(v2DockItems, 'active');
            setActiveByUrl(v2GridItems, 'active');
            updateMoreBtnHighlight(v2DockItems, v2GridItems, v2MoreBtn);
        }
        
        // 方案3
        var v3 = document.getElementById('lgnewui-mobile-nav-v3');
        if (v3) {
            var v3Items = Array.from(v3.querySelectorAll('.js-lgnewui-v3-item'));
            var v3MenuItems = Array.from(v3.querySelectorAll('.js-lgnewui-v3-menu-item'));
            var v3ToggleBtn = v3.querySelector('.lgnewui-tab-template-v3-dock .js-lgnewui-v3-toggle');
            setActiveByUrl(v3Items, 'active');
            setActiveByUrl(v3MenuItems, 'active');
            updateMoreBtnHighlight(v3Items, v3MenuItems, v3ToggleBtn);
        }
        
        // 方案4
        var v4 = document.getElementById('lgnewui-mobile-nav-v4');
        if (v4) {
            var v4Items = Array.from(v4.querySelectorAll('.js-lgnewui-v4-item'));
            setActiveByUrl(v4Items, 'active');
        }
        
        // 方案5
        var v5 = document.getElementById('lgnewui-mobile-nav-v5');
        if (v5) {
            var v5Items = Array.from(v5.querySelectorAll('.js-lgnewui-v5-item'));
            setActiveByUrl(v5Items, 'active');
            if (window.recalcLgnewuiV5) window.recalcLgnewuiV5();
        }
        
        // 方案6
        var v6 = document.getElementById('lgnewui-mobile-nav-v6');
        if (v6) {
            var v6BarItems = Array.from(v6.querySelectorAll('.js-lgnewui-v6-bar-item'));
            var v6GridItems = Array.from(v6.querySelectorAll('.js-lgnewui-v6-grid-item'));
            var v6MoreBtn = v6.querySelector('.lgnewui-tab-template-v6-bar .js-lgnewui-v6-toggle');
            setActiveByUrl(v6BarItems, 'active');
            setActiveByUrl(v6GridItems, 'active');
            updateMoreBtnHighlight(v6BarItems, v6GridItems, v6MoreBtn);
        }
    }

    // 支持 pjax 无刷新跳转后刷新高亮
    document.addEventListener('pjax:complete', function () {
        // 确保移动端tab显示（详情页可能隐藏了）
        var mobileNavRoot = document.querySelector('.lgnewui-mobile-nav-root');
        if (mobileNavRoot) {
            mobileNavRoot.style.display = '';
        }
        refreshHighlight();
    });

    // 监听浏览器前进/后退按钮
    window.addEventListener('popstate', function () {
        setTimeout(function () {
            refreshHighlight();
        }, 50);
    });

    // 监听所有链接点击，在PJAX环境下刷新高亮
    document.addEventListener('click', function (e) {
        var link = e.target.closest('a[href]');
        if (link && !link.getAttribute('target')) {
            // 延迟刷新高亮，等待PJAX完成
            setTimeout(function () {
                refreshHighlight();
            }, 100);
        }
    });

})();
