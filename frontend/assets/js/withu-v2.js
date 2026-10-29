/* ============================================================
   withU v2 — 交互与动效增强
   ------------------------------------------------------------
   - 内页识别（Hero 与首页同高）
   - 滚动进度条
   - 滚动揭示 / 卡片微倾斜 / 按钮磁吸
   - 点击波纹 / 点赞心跳粒子
   - 数字滚动 / 图片兜底 / PJAX 重绑定
   全部为渐进增强：JS 失败时页面与内容仍完整可用。
   ============================================================ */
(function () {
    'use strict';

    var doc = document.documentElement;
    var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var finePointer = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;

    function updatePageMode() {
        var current = (location.pathname || '/').replace(/\/+$/, '');
        var home = current === '' || current === '/' || /\/index\.php$/.test(current);
        doc.classList.toggle('withu-subpage', !home);
    }

    doc.classList.add('withu-v2');
    updatePageMode();

    var observer = null;
    var revealSelector = [
        '.withu-card',
        '.withu-article-masonry-item',
        '.withu-time-line-card',
        '.MessageCard',
        '.love-card',
        '.withu-no-data'
    ].join(',');

    function ensureObserver() {
        if (observer || typeof IntersectionObserver === 'undefined') return observer;
        observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var el = entry.target;
                el.classList.add('wu-in');
                observer.unobserve(el);
                window.setTimeout(function () {
                    el.style.transitionDelay = '';
                }, 900);
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -6% 0px' });
        return observer;
    }

    function scanReveal(root) {
        if (reducedMotion) return;
        var scope = root && root.querySelectorAll ? root : document;
        var obs = ensureObserver();
        if (!obs) return;

        var nodes = scope.querySelectorAll(revealSelector);
        Array.prototype.forEach.call(nodes, function (el, index) {
            if (el.dataset.wuReveal === '1' || el.classList.contains('wu-reveal')) return;
            // AOS 已负责的元素不再叠加第二套揭示动画
            if (el.hasAttribute('data-aos') || el.closest('[data-aos]')) return;
            var rect = el.getBoundingClientRect();
            // 首屏内容交给页面入场动画，避免重复闪烁
            if (rect.top < window.innerHeight * 0.82) return;
            el.dataset.wuReveal = '1';
            el.classList.add('wu-reveal');
            el.style.transitionDelay = ((index % 6) * 45) + 'ms';
            obs.observe(el);
        });
    }

    /* ---------- 滚动进度条 ---------- */
    function initScrollProgress() {
        if (document.querySelector('.withu-scroll-progress')) return;
        var wrap = document.createElement('div');
        wrap.className = 'withu-scroll-progress';
        wrap.setAttribute('aria-hidden', 'true');
        var bar = document.createElement('div');
        bar.className = 'withu-scroll-progress__bar';
        wrap.appendChild(bar);
        document.body.appendChild(wrap);

        var ticking = false;
        function update() {
            ticking = false;
            var height = document.documentElement.scrollHeight - window.innerHeight;
            var ratio = height > 0 ? Math.min(1, Math.max(0, window.scrollY / height)) : 0;
            bar.style.width = (ratio * 100).toFixed(2) + '%';
        }
        function onScroll() {
            if (ticking) return;
            ticking = true;
            window.requestAnimationFrame(update);
        }
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll, { passive: true });
        update();
    }

    /* ---------- 横幅图片兜底 ---------- */
    var fallbackBanners = [
        '/assets/images/home-banners/01-camping-starry-night.webp',
        '/assets/images/home-banners/02-alpine-lake-village.webp',
        '/assets/images/home-banners/03-sunset-friends.webp'
    ];

    function initHeroFallback() {
        var images = document.querySelectorAll('#homePage img.CarouselImage');
        Array.prototype.forEach.call(images, function (img) {
            if (img.dataset.wuFallbackBound === '1') return;
            img.dataset.wuFallbackBound = '1';
            img.addEventListener('error', function () {
                var current = img.getAttribute('src') || img.getAttribute('data-src') || '';
                for (var i = 0; i < fallbackBanners.length; i++) {
                    if (current.indexOf(fallbackBanners[i]) === -1) {
                        img.setAttribute('src', fallbackBanners[i]);
                        img.setAttribute('data-src', fallbackBanners[i]);
                        return;
                    }
                }
            });
        });
    }

    /* ---------- 点击波纹 ---------- */
    var rippleSelector = [
        '.withu-link-more',
        '.withu-header-action',
        '.withu-message-submit-btn',
        '.withumsg-load-more-btn',
        '.withu-no-data-btn',
        '.withu-nav-island-item',
        '#scrollTopBtn'
    ].join(',');

    function createRipple(el, event) {
        if (reducedMotion || el.dataset.wuNoRipple === '1') return;
        var rect = el.getBoundingClientRect();
        var size = Math.max(rect.width, rect.height);
        var x = (event.clientX || rect.left + rect.width / 2) - rect.left;
        var y = (event.clientY || rect.top + rect.height / 2) - rect.top;

        el.classList.add('wu-ripple-host');
        var ripple = document.createElement('span');
        ripple.className = 'wu-ripple';
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = (x - size / 2) + 'px';
        ripple.style.top = (y - size / 2) + 'px';
        el.appendChild(ripple);
        window.setTimeout(function () {
            ripple.remove();
        }, 700);
    }

    function initRipple() {
        document.addEventListener('click', function (event) {
            var target = event.target.closest ? event.target.closest(rippleSelector) : null;
            if (!target) return;
            createRipple(target, event);
        }, true);
    }

    /* ---------- 点赞心跳粒子 ---------- */
    function initLikeBurst() {
        document.addEventListener('click', function (event) {
            var trigger = event.target.closest ? event.target.closest('[data-like-target], .withu-article-like, .withu-message-like-btn') : null;
            if (!trigger || reducedMotion || typeof trigger.animate !== 'function') return;

            var x = event.clientX || 0;
            var y = event.clientY || 0;
            var colors = ['#f87171', '#fca5a5', '#c94f4f', '#c9a26b'];

            for (var i = 0; i < 8; i++) {
                var particle = document.createElement('span');
                particle.textContent = '♥';
                particle.setAttribute('aria-hidden', 'true');
                particle.style.cssText = [
                    'position:fixed',
                    'left:' + x + 'px',
                    'top:' + y + 'px',
                    'font-size:' + (10 + Math.random() * 8) + 'px',
                    'color:' + colors[i % colors.length],
                    'pointer-events:none',
                    'z-index:100001',
                    'will-change:transform,opacity',
                    'line-height:1'
                ].join(';');
                document.body.appendChild(particle);

                var angle = (Math.PI * 2 * i) / 8 + Math.random() * 0.5;
                var distance = 34 + Math.random() * 34;
                var dx = Math.cos(angle) * distance;
                var dy = Math.sin(angle) * distance - 22;

                if (typeof particle.animate === 'function') {
                    var anim = particle.animate([
                        { transform: 'translate(-50%, -50%) scale(0.6)', opacity: 1 },
                        { transform: 'translate(calc(-50% + ' + dx + 'px), calc(-50% + ' + dy + 'px)) scale(1.08) rotate(' + (Math.random() * 90 - 45) + 'deg)', opacity: 0 }
                    ], {
                        duration: 620 + Math.random() * 220,
                        easing: 'cubic-bezier(0.22, 1, 0.36, 1)'
                    });
                    anim.onfinish = function () { particle.remove(); };
                } else {
                    window.setTimeout(function () { particle.remove(); }, 800);
                }
            }
        }, true);
    }

    /* ---------- 数字滚动 ---------- */
    function animateNumber(el) {
        var target = parseInt((el.textContent || '').replace(/[^\d-]/g, ''), 10);
        if (!isFinite(target) || target <= 0 || target > 99999) return;
        var start = performance.now();
        var duration = 1200;
        function frame(now) {
            var t = Math.min(1, (now - start) / duration);
            var eased = 1 - Math.pow(1 - t, 3);
            el.textContent = String(Math.round(target * eased));
            if (t < 1) window.requestAnimationFrame(frame);
            else el.textContent = String(target);
        }
        el.textContent = '0';
        window.requestAnimationFrame(frame);
    }

    function initCountUp() {
        if (reducedMotion || typeof requestAnimationFrame === 'undefined') return;
        var nodes = document.querySelectorAll('.withu-day-main-days-number:not([data-wu-counted]), [data-countup]:not([data-wu-counted])');
        if (!nodes.length) return;

        if (typeof IntersectionObserver === 'undefined') {
            Array.prototype.forEach.call(nodes, function (el) {
                el.dataset.wuCounted = '1';
                animateNumber(el);
            });
            return;
        }

        var numberObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var el = entry.target;
                el.dataset.wuCounted = '1';
                animateNumber(el);
                numberObserver.unobserve(el);
            });
        }, { threshold: 0.4 });

        Array.prototype.forEach.call(nodes, function (el) {
            numberObserver.observe(el);
        });
    }

    /* ---------- 卡片微倾斜 ---------- */
    function initTilt() {
        if (!finePointer || reducedMotion) return;
        var cards = document.querySelectorAll('.withu-card, .withu-article-card-base');

        Array.prototype.forEach.call(cards, function (card) {
            if (card.dataset.wuTiltBound === '1') return;
            card.dataset.wuTiltBound = '1';

            card.addEventListener('mousemove', function (event) {
                if (window.innerWidth < 900) return;
                var rect = card.getBoundingClientRect();
                var px = (event.clientX - rect.left) / rect.width - 0.5;
                var py = (event.clientY - rect.top) / rect.height - 0.5;
                card.classList.add('wu-tilting');
                card.style.transform =
                    'perspective(900px) rotateX(' + (-py * 3.2).toFixed(2) + 'deg) rotateY(' + (px * 3.2).toFixed(2) + 'deg) translateY(-5px)';
            });

            card.addEventListener('mouseleave', function () {
                card.classList.remove('wu-tilting');
                card.style.transform = '';
            });
        });
    }

    /* ---------- 磁吸按钮 ---------- */
    function initMagnetic() {
        if (!finePointer || reducedMotion) return;
        var targets = document.querySelectorAll('.withu-link-more, #scrollTopBtn');

        Array.prototype.forEach.call(targets, function (el) {
            if (el.dataset.wuMagneticBound === '1') return;
            el.dataset.wuMagneticBound = '1';

            el.addEventListener('mousemove', function (event) {
                var rect = el.getBoundingClientRect();
                var dx = event.clientX - (rect.left + rect.width / 2);
                var dy = event.clientY - (rect.top + rect.height / 2);
                el.style.transform = 'translate(' + (dx * 0.16).toFixed(1) + 'px, ' + (dy * 0.16 - 2).toFixed(1) + 'px) scale(1.04)';
            });

            el.addEventListener('mouseleave', function () {
                el.style.transform = '';
            });
        });
    }

    /* ---------- 图片解码优化 ---------- */
    function initImageDecoding() {
        Array.prototype.forEach.call(document.images, function (img) {
            if (!img.hasAttribute('decoding')) img.setAttribute('decoding', 'async');
        });
    }

    /* ---------- 页面内搜索（相册 / 点滴） ---------- */
    function initPageSearch() {
        var existing = document.getElementById('wuPageSearch');
        if (existing) return;

        var host = null;
        var target = null;
        var itemSelector = '';
        var placeholder = '';

        var articleHost = document.querySelector('.withu-article-grid');
        var articleTarget = document.querySelector('.withu-article-masonry');
        if (articleHost && articleTarget && !articleTarget.querySelector('.withu-no-data')) {
            host = articleHost;
            target = articleTarget;
            itemSelector = '.withu-article-masonry-item';
            placeholder = '搜索点滴标题或内容…';
        } else {
            var messageHost = document.querySelector('.Width_limit_10rem.leav');
            var messageTarget = document.getElementById('withumsgCardGrid');
            if (messageHost && messageTarget) {
                host = messageHost;
                target = messageTarget;
                itemSelector = '.MessageCard';
                placeholder = '搜索留言内容或昵称…';
            } else {
                var albumHost = document.querySelector('.withu-page-container');
                var albumTarget = document.querySelector('.withu-masonry-grid');
                if (albumHost && albumTarget && !albumTarget.querySelector('.withu-no-data')) {
                    host = albumHost;
                    target = albumTarget;
                    itemSelector = '.withu-masonry-col';
                    placeholder = '搜索相册名称或地点…';
                }
            }
        }
        if (!host || !target) return;

        var bar = document.createElement('div');
        bar.className = 'wu-page-search';
        bar.id = 'wuPageSearch';
        bar.innerHTML =
            '<span class="wu-page-search__icon" aria-hidden="true"><i class="ph ph-magnifying-glass"></i></span>' +
            '<input type="search" class="wu-page-search__input" placeholder="' + placeholder + '" aria-label="' + placeholder + '" autocomplete="off">' +
            '<span class="wu-page-search__count" aria-live="polite"></span>' +
            '<button type="button" class="wu-page-search__clear" aria-label="清除搜索" hidden>×</button>' +
            '<kbd class="wu-page-search__hint">/</kbd>';

        if (target.parentNode) {
            target.parentNode.insertBefore(bar, target);
        } else {
            host.appendChild(bar);
        }

        var input = bar.querySelector('.wu-page-search__input');
        var count = bar.querySelector('.wu-page-search__count');
        var clear = bar.querySelector('.wu-page-search__clear');

        function applyFilter() {
            var query = (input.value || '').trim().toLowerCase();
            var cards = target.querySelectorAll(itemSelector);
            var visible = 0;

            // 列表为空或展示空状态时不显示搜索条
            bar.style.display = (!cards.length || target.querySelector('.withu-no-data, .withumsg-empty')) ? 'none' : '';

            Array.prototype.forEach.call(cards, function (card) {
                var haystack = (card.textContent || '').toLowerCase();
                var matched = query === '' || haystack.indexOf(query) !== -1;
                card.style.display = matched ? '' : 'none';
                if (matched) visible++;
            });

            clear.hidden = query === '';
            if (query === '') {
                count.textContent = '';
            } else {
                count.textContent = visible > 0 ? ('找到 ' + visible + ' 条') : '没有匹配的内容';
            }
        }

        input.addEventListener('input', applyFilter);
        clear.addEventListener('click', function () {
            input.value = '';
            applyFilter();
            input.focus();
        });
        applyFilter();

        // 动态渲染完成后再次应用当前关键词
        if (typeof MutationObserver !== 'undefined') {
            if (window.__wuSearchObserver) {
                try { window.__wuSearchObserver.disconnect(); } catch (e) {}
            }
            var mo = new MutationObserver(function () {
                applyFilter();
            });
            mo.observe(target, { childList: true });
            window.__wuSearchObserver = mo;
        }

        // 按 / 快速聚焦搜索（只绑定一次）
        if (!window.__wuSearchShortcutBound) {
            window.__wuSearchShortcutBound = true;
            document.addEventListener('keydown', function (event) {
                if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey) return;
                var tag = (document.activeElement && document.activeElement.tagName) || '';
                if (/^(INPUT|TEXTAREA|SELECT)$/.test(tag) || (document.activeElement && document.activeElement.isContentEditable)) return;
                var searchInput = document.querySelector('.wu-page-search__input');
                if (!searchInput) return;
                event.preventDefault();
                searchInput.focus();
            });
        }
    }

    /* ---------- 页面入场 ---------- */
    function playPageEnter() {
        if (reducedMotion) return;
        var container = document.getElementById('pjax-container');
        if (!container) return;
        container.classList.remove('wu-page-enter');
        void container.offsetWidth;
        container.classList.add('wu-page-enter');
        window.setTimeout(function () {
            container.classList.remove('wu-page-enter');
        }, 800);
    }

    /* ---------- 单张相册卡居中 ---------- */
    function initAlbumLayout() {
        var grid = document.querySelector('.withu-masonry-grid');
        if (!grid) return;

        function apply() {
            var count = grid.querySelectorAll(':scope > .withu-masonry-col').length;
            grid.classList.toggle('wu-single-card', count === 1);
        }

        apply();
        if (typeof MutationObserver !== 'undefined') {
            if (window.__wuAlbumObserver) {
                try { window.__wuAlbumObserver.disconnect(); } catch (e) {}
            }
            var mo = new MutationObserver(apply);
            mo.observe(grid, { childList: true });
            window.__wuAlbumObserver = mo;
        }
    }

    /* ---------- 单条留言居中 ---------- */
    function initMessageLayout() {
        var grid = document.getElementById('withumsgCardGrid');
        if (!grid) return;

        function apply() {
            var count = grid.querySelectorAll('.MessageCard').length;
            grid.classList.toggle('wu-single-message', count === 1);
        }

        apply();
        if (typeof MutationObserver !== 'undefined') {
            if (window.__wuMessageObserver) {
                try { window.__wuMessageObserver.disconnect(); } catch (e) {}
            }
            var mo = new MutationObserver(apply);
            mo.observe(grid, { childList: true });
            window.__wuMessageObserver = mo;
        }
    }

    /* ---------- 跳转主内容锚点 ---------- */
    function ensureSkipTarget() {
        if (document.getElementById('main-content')) return;
        var container = document.getElementById('pjax-container');
        if (!container || !container.parentNode) return;
        var anchor = document.createElement('span');
        anchor.id = 'main-content';
        anchor.tabIndex = -1;
        anchor.setAttribute('aria-hidden', 'true');
        anchor.style.cssText = 'display:block;width:0;height:0;overflow:hidden;';
        container.parentNode.insertBefore(anchor, container);
    }

    /* ---------- 初始化与 PJAX 重绑定 ---------- */
    function initPage() {
        updatePageMode();
        ensureSkipTarget();
        playPageEnter();
        initHeroFallback();
        initScrollProgress();
        scanReveal(document);
        initCountUp();
        initTilt();
        initMagnetic();
        initImageDecoding();
        initPageSearch();
        initMessageLayout();
        initAlbumLayout();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPage, { once: true });
    } else {
        initPage();
    }

    initRipple();
    initLikeBurst();

    function reinit() {
        initPage();
        scanReveal(document);
    }

    if (window.jQuery) {
        window.jQuery(document).on('pjax:end.withuV2 pjax:complete.withuV2', reinit);
    }
    document.addEventListener('withu:pjax-after', reinit);
    document.addEventListener('pjax:complete', reinit);
    window.withuV2Refresh = reinit;
})();
