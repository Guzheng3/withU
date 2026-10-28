/* ============================================================
   withU v3 — 渐进增强脚本
   ------------------------------------------------------------
   - 分区标题下划线绘制（scroll reveal）
   - 顶部栏滚动状态
   - 回到顶部按钮的环状阅读进度
   - PJAX 后自动重绑
   JS 失效时页面保持完整，不隐藏任何内容。
   ============================================================ */
(function () {
    'use strict';

    var doc = document.documentElement;
    var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    doc.classList.add('wu3-js');

    function each(list, fn) {
        Array.prototype.forEach.call(list || [], fn);
    }

    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn, { once: true });
        } else {
            fn();
        }
    }

    /* ---------- 分区标题 / 页头下划线 ---------- */
    var revealObserver = null;

    function ensureRevealObserver() {
        if (reducedMotion || typeof IntersectionObserver === 'undefined') return null;
        if (revealObserver) return revealObserver;

        revealObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('wu3-inview');
                revealObserver.unobserve(entry.target);
            });
        }, { threshold: 0.15, rootMargin: '0px 0px -8% 0px' });

        return revealObserver;
    }

    function scanSectionReveal() {
        if (reducedMotion) {
            each(document.querySelectorAll('.withu-page-header, .withu-section-header'), function (el) {
                el.classList.add('wu3-inview');
            });
            return;
        }
        var observer = ensureRevealObserver();
        if (!observer) return;

        var targets = document.querySelectorAll('.withu-page-header, .withu-section-header');
        each(targets, function (el) {
            if (el.dataset.wu3Reveal === '1') return;
            el.dataset.wu3Reveal = '1';
            observer.observe(el);
        });
    }

    /* ---------- 顶部栏滚动状态 ---------- */
    var headerBound = false;

    function initHeaderState() {
        if (headerBound) return;
        headerBound = true;

        var header = document.querySelector('.header-wrap');
        if (!header) return;

        var ticking = false;
        function update() {
            ticking = false;
            header.classList.toggle('wu3-scrolled', window.scrollY > 24);
        }
        function onScroll() {
            if (ticking) return;
            ticking = true;
            window.requestAnimationFrame(update);
        }

        window.addEventListener('scroll', onScroll, { passive: true });
        update();
    }

    /* ---------- 回到顶部按钮的环状进度 ---------- */
    var progressBound = false;

    function initScrollTopProgress() {
        if (progressBound) return;
        progressBound = true;

        var ticking = false;
        function update() {
            ticking = false;
            var btn = document.getElementById('scrollTopBtn');
            if (!btn) return;
            var height = doc.scrollHeight - window.innerHeight;
            var ratio = height > 0 ? Math.min(1, Math.max(0, window.scrollY / height)) : 0;
            btn.style.setProperty('--wu3-progress', (ratio * 100).toFixed(2));
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

    /* ---------- PJAX 重绑 ---------- */
    var refreshTimer = null;

    function refresh() {
        window.clearTimeout(refreshTimer);
        refreshTimer = window.setTimeout(function () {
            initHeaderState();
            initScrollTopProgress();
            scanSectionReveal();
        }, 40);
    }

    onReady(function () {
        initHeaderState();
        initScrollTopProgress();
        scanSectionReveal();
    });

    if (window.jQuery) {
        window.jQuery(document).on('pjax:end.withuV3 pjax:complete.withuV3', refresh);
    }
    document.addEventListener('withu:pjax-after', refresh);
    document.addEventListener('pjax:complete', refresh);
    window.withuV3Refresh = refresh;
})();
