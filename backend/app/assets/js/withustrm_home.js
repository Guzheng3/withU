/* WithU · LG-home interaction layer (vanilla JS)
   Ported from lg-site homepage (index.php) interactions:
   - split-text hero title
   - iOS tabs filter for Love Day / events
   - horizontal message carousel (auto-scroll + drag)
   - epilogue notebook card (quotes)
   No jQuery dependency, scoped to elements with explicit data attributes. */

(function () {
    'use strict';

    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduceMotion) return;

    /* ── 1. Hero split-text title ─────────────────────────── */
    function initHeroSplitTitle() {
        var title = document.getElementById('withustrm-hero-title');
        if (!title || title.getAttribute('data-hero-split') == null) return;
        if (title.getAttribute('data-hero-split-done') === '1') return;

        var text = title.textContent.trim();
        title.setAttribute('data-hero-split-done', '1');
        title.textContent = '';

        var fragment = document.createDocumentFragment();
        Array.prototype.forEach.call(text, function (ch) {
            var span = document.createElement('span');
            span.className = 'withustrm-char';
            span.textContent = ch === ' ' ? '\u00A0' : ch;
            fragment.appendChild(span);
        });
        title.appendChild(fragment);

        var chars = title.querySelectorAll('.withustrm-char');
        Array.prototype.forEach.call(chars, function (el, i) {
            el.style.transitionDelay = (0.25 + i * 0.05) + 's';
            (function (node) {
                window.setTimeout(function () {
                    node.classList.add('is-in');
                }, 120 + i * 40);
            })(el);
        });
    }

    /* ── 2. iOS tabs filter (Love Day) ────────────────────── */
    function initIosTabs() {
        var container = document.querySelector('[data-withustrm-loveday-tabs]');
        if (!container) return;

        var tabs = Array.prototype.slice.call(container.querySelectorAll('.withustrm-ios-tab'));
        var slider = container.querySelector('.withustrm-ios-tabs-slider');
        var grid = document.querySelector('[data-withustrm-loveday-grid]');
        if (!tabs.length || !slider || !grid) return;

        function moveSlider(activeTab) {
            var w = activeTab.offsetWidth;
            var x = activeTab.offsetLeft;
            slider.style.width = w + 'px';
            slider.style.transform = 'translateX(' + x + 'px)';
        }

        function setActive(filter, activeTab) {
            tabs.forEach(function (t) {
                t.classList.toggle('active', t === activeTab);
            });
            moveSlider(activeTab);

            var items = grid.querySelectorAll('.withustrm-loveday-item');
            Array.prototype.forEach.call(items, function (item) {
                var show = filter === 'all' || item.getAttribute('data-withustrm-filter') === filter;
                item.classList.toggle('is-filtered', !show);
            });
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                setActive(tab.getAttribute('data-filter'), tab);
            });
        });

        // initial state: active tab
        var active = container.querySelector('.withustrm-ios-tab.active') || tabs[0];
        setActive(active.getAttribute('data-filter') || 'all', active);

        // recalc on resize
        var t;
        window.addEventListener('resize', function () {
            clearTimeout(t);
            t = setTimeout(function () {
                var cur = container.querySelector('.withustrm-ios-tab.active');
                if (cur) moveSlider(cur);
            }, 120);
        });
    }

    /* ── 3. Horizontal message carousel ───────────────────── */
    function initMessageCarousel() {
        var container = document.getElementById('withustrm-msg-container');
        if (!container) return;

        var track = container.querySelector('.withustrm-msg-track');
        if (!track) return;

        var auto = true;
        var speed = 0.6; // px per frame @60fps
        var pos = 0;
        var maxScroll = 0;
        var dragging = false;
        var startX = 0;
        var startPos = 0;
        var rafId = null;
        var animId = null;

        function measure() {
            maxScroll = track.scrollWidth - container.clientWidth;
            if (maxScroll <= 0) {
                auto = false;
            }
        }

        function frame() {
            if (auto && !dragging && maxScroll > 0) {
                pos += speed;
                if (pos >= maxScroll) pos = 0;
                track.style.transform = 'translateX(-' + pos + 'px)';
            }
            rafId = window.requestAnimationFrame(frame);
        }

        function stop() {
            if (rafId) window.cancelAnimationFrame(rafId);
            rafId = null;
        }

        function start() {
            if (rafId) return;
            rafId = window.requestAnimationFrame(frame);
        }

        // pointer drag
        container.addEventListener('pointerdown', function (e) {
            dragging = true;
            auto = false;
            startX = e.clientX;
            startPos = pos;
            container.classList.add('is-dragging');
            stop();
            try { container.setPointerCapture(e.pointerId); } catch (err) {}
        });

        container.addEventListener('pointermove', function (e) {
            if (!dragging) return;
            var dx = startX - e.clientX;
            pos = startPos + dx;
            if (pos < 0) pos = 0;
            if (pos > maxScroll) pos = maxScroll;
            track.style.transform = 'translateX(-' + pos + 'px)';
        });

        function endDrag() {
            if (!dragging) return;
            dragging = false;
            container.classList.remove('is-dragging');
            // resume auto after a beat
            window.setTimeout(function () {
                auto = true;
                start();
            }, 2500);
        }

        container.addEventListener('pointerup', endDrag);
        container.addEventListener('pointercancel', endDrag);
        container.addEventListener('mouseleave', function () {
            if (dragging) endDrag();
        });

        // pause on hover
        container.addEventListener('mouseenter', function () { auto = false; });
        container.addEventListener('mouseleave', function () {
            auto = true;
            start();
        });

        // pause when off-screen
        var t;
        window.addEventListener('resize', function () {
            clearTimeout(t);
            t = setTimeout(measure, 150);
        });

        measure();
        start();
    }

    /* ── 4. Epilogue notebook quotes ──────────────────────── */
    function initEpilogue() {
        var card = document.querySelector('[data-withustrm-epilogue]');
        if (!card) return;

        var quoteEl = card.querySelector('.withustrm-epilogue__quote');
        var refreshBtn = card.querySelector('[data-epilogue-refresh]');
        var copyBtn = card.querySelector('[data-epilogue-copy]');
        if (!quoteEl) return;

        var quotes = [];
        try {
            var raw = card.getAttribute('data-withustrm-quotes');
            if (raw) {
                quotes = JSON.parse(raw);
            }
        } catch (e) {
            quotes = [];
        }
        if (!quotes.length) {
            quotes = [
                '爱是把平凡的日子，过成只属于我们的诗。',
                '从相遇到相守，每一天都值得纪念。',
                '山河远阔，人间烟火，无一是你，无一不是你。',
                '愿我们朝暮与年岁并往，然后与你行至天光。',
                '你是藏在时光褶皱里的温柔。',
                '所有美好都恰逢其时。',
                '慢慢来，谁不是翻山越岭去相爱。'
            ];
        }

        var index = Math.floor(Math.random() * quotes.length);
        var switching = false;

        function setQuote() {
            quoteEl.textContent = quotes[index];
        }

        function switchQuote() {
            if (switching) return;
            switching = true;
            quoteEl.classList.add('is-switching');
            window.setTimeout(function () {
                index = (index + 1) % quotes.length;
                setQuote();
                quoteEl.classList.remove('is-switching');
                switching = false;
            }, 320);
        }

        function copyQuote() {
            var text = quoteEl.textContent || '';
            if (!navigator.clipboard) return;
            navigator.clipboard.writeText(text).then(function () {
                var btn = copyBtn;
                if (!btn) return;
                var old = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> 已复制';
                window.setTimeout(function () { btn.innerHTML = old; }, 1600);
            });
        }

        if (refreshBtn) refreshBtn.addEventListener('click', switchQuote);
        if (copyBtn) copyBtn.addEventListener('click', copyQuote);

        setQuote();
    }

    /* ── boot ─────────────────────────────────────────────── */
    function boot() {
        initHeroSplitTitle();
        initIosTabs();
        initMessageCarousel();
        initEpilogue();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
