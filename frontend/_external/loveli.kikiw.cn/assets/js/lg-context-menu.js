/**
 * LGContextMenu — LGNewUi 自定义右键菜单
 * 支持多级嵌套、智能边缘定位、上下文感知分组、Toastify 反馈、音乐联动
 */
(function(win, doc) {
    'use strict';

    var _panel = null;
    var _bindList = [];
    var _active = false;
    var _shown = false;
    var _busy = false;
    var _popping = false;
    var _ts = 0;
    var _fadeId = null;
    var _scrollId = null;
    var _touchY0 = 0;
    var _preFocus = null;
    var _schemes = {};

    var GAP = 8;
    var DURATION = 135;

    /* ═══════════════════ 提示反馈 ═══════════════════ */

    function toast(type, text) {
        if (typeof Toastify !== 'undefined' && Toastify.showScenario) {
            Toastify.showScenario(type, { text: text, duration: 1800 });
        }
    }

    /* ═══════════════════ 工具函数 ═══════════════════ */

    function findLink(el) {
        if (!el || !el.closest) return null;
        var a = el.closest('a');
        if (a && a.href) return a.href;
        var oc = el.getAttribute ? el.getAttribute('onclick') : null;
        if (oc) {
            var m = oc.match(/window\.open\s*\(\s*['"](.+?)['"]/i) ||
                    oc.match(/location\.href\s*=\s*['"](.+?)['"]/i);
            if (m) return m[1];
        }
        return null;
    }

    function findImage(el) {
        if (!el || !el.closest) return null;
        var img = el.closest('img');
        if (img && img.src) return img.src;
        try {
            var bg = getComputedStyle(el).backgroundImage;
            if (bg && bg !== 'none') {
                var m = bg.match(/url\(["']?(.+?)["']?\)/i);
                if (m) return m[1];
            }
        } catch (e) {}
        return null;
    }

    /* ═══════════════════ 音乐辅助 ═══════════════════ */

    function getMusicPlayer() {
        var meting = doc.querySelector('#nav-music meting-js');
        return meting && meting.aplayer ? meting.aplayer : null;
    }

    function isMusicAvailable() {
        return !!doc.getElementById('nav-music');
    }

    function isMobile() {
        if (win.matchMedia && win.matchMedia('(hover: none) and (pointer: coarse)').matches) return true;
        return win.innerWidth <= 768;
    }

    function isMusicPlaying() {
        var el = doc.getElementById('nav-music');
        return el && el.classList.contains('playing');
    }

    function getMusicTitle() {
        var ap = getMusicPlayer();
        if (ap && ap.list && ap.list.audios && ap.list.audios[ap.list.index]) {
            return ap.list.audios[ap.list.index].name || '';
        }
        return '';
    }

    /* ═══════════════════ 剪贴板 ═══════════════════ */

    function writeClipboard(text, feedback) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                if (feedback) toast('success', feedback);
            }).catch(function() {
                writeClipboardLegacy(text);
                if (feedback) toast('success', feedback);
            });
        } else {
            writeClipboardLegacy(text);
            if (feedback) toast('success', feedback);
        }
    }

    function writeClipboardLegacy(text) {
        var t = doc.createElement('textarea');
        t.value = text;
        t.style.cssText = 'position:fixed;opacity:0;pointer-events:none;left:-9999px';
        doc.body.appendChild(t);
        t.select();
        try { doc.execCommand('copy'); } catch (e) {}
        doc.body.removeChild(t);
    }

    function readClipboardInto(el) {
        if (!el) return;
        if (doc.activeElement !== el) el.focus();
        if (navigator.clipboard && navigator.clipboard.readText) {
            navigator.clipboard.readText().then(function(text) {
                typeAtCaret(el, text);
                toast('success', '已粘贴');
            }).catch(function() {
                try { doc.execCommand('paste'); } catch (e) {}
            });
        } else {
            try { doc.execCommand('paste'); } catch (e) {}
        }
    }

    function typeAtCaret(el, text) {
        if (el.isContentEditable) {
            doc.execCommand('insertText', false, text);
            return;
        }
        if (typeof el.setRangeText === 'function') {
            var s = el.selectionStart || 0;
            var e = el.selectionEnd || 0;
            el.setRangeText(text, s, e, 'end');
        }
    }

    /* ═══════════════════ 面板 DOM ═══════════════════ */

    function ensurePanel() {
        if (_panel && doc.body.contains(_panel)) return;
        _panel = doc.createElement('div');
        _panel.className = 'lgcm-overlay';
        doc.body.appendChild(_panel);
    }

    function purgeNests() {
        var nests = doc.querySelectorAll('.lgcm-nest');
        for (var i = 0; i < nests.length; i++) nests[i].parentNode.removeChild(nests[i]);
    }

    function killTimers() {
        if (_fadeId) { clearTimeout(_fadeId); _fadeId = null; }
        if (_scrollId) { clearTimeout(_scrollId); _scrollId = null; }
    }

    /* ═══════════════════ 渲染 ═══════════════════ */

    function paintLayer(items, box, ctx) {
        for (var n = 0; n < items.length; n++) {
            var item = items[n];
            if (typeof item.when === 'function' && !item.when(ctx)) continue;

            if (item.separator) {
                var hr = doc.createElement('div');
                hr.className = 'lgcm-divider';
                box.appendChild(hr);
                continue;
            }

            var row = doc.createElement('div');
            row.className = 'lgcm-row';
            row.setAttribute('data-rid', item.id);

            var iconVal = typeof item.icon === 'function' ? item.icon() : item.icon;
            if (iconVal) {
                var glyph = doc.createElement('span');
                glyph.className = 'lgcm-glyph';
                var ic = doc.createElement('i');
                ic.className = iconVal;
                glyph.appendChild(ic);
                row.appendChild(glyph);
            }

            var span = doc.createElement('span');
            span.textContent = typeof item.label === 'function' ? item.label() : item.label;
            row.appendChild(span);

            if (Array.isArray(item.children) && item.children.length > 0) {
                attachNest(row, item, box, ctx);
            } else {
                attachAction(row, item, ctx);
            }

            box.appendChild(row);
        }
    }

    function attachAction(row, item, ctx) {
        row.addEventListener('click', function(e) {
            e.stopPropagation();
            if (typeof item.action === 'function') item.action(ctx);
            dismiss();
        });
    }

    function attachNest(row, item, parentBox, ctx) {
        var arrow = doc.createElement('span');
        arrow.className = 'lgcm-arrow';
        var ai = doc.createElement('i');
        ai.className = 'ri-arrow-right-s-line';
        arrow.appendChild(ai);
        row.appendChild(arrow);

        var nestEl = null;
        var nestTimer = null;

        row.addEventListener('mouseenter', function() {
            if (nestTimer) { clearTimeout(nestTimer); nestTimer = null; }
            row.classList.add('lgcm-held');

            var siblings = parentBox.querySelectorAll('.lgcm-row');
            for (var i = 0; i < siblings.length; i++) {
                var sib = siblings[i];
                if (sib !== row) {
                    sib.classList.remove('lgcm-held');
                    var old = doc.querySelector('.lgcm-nest[data-owner="' + sib.getAttribute('data-rid') + '"]');
                    if (old) old.parentNode.removeChild(old);
                }
            }

            if (!nestEl || !doc.body.contains(nestEl)) {
                nestEl = doc.createElement('div');
                nestEl.className = 'lgcm-nest';
                nestEl.setAttribute('data-owner', item.id);
                doc.body.appendChild(nestEl);

                paintLayer(item.children, nestEl, ctx);

                if (!nestEl.hasChildNodes()) {
                    nestEl.parentNode.removeChild(nestEl);
                    nestEl = null;
                    return;
                }

                placeNest(row, nestEl);

                nestEl.addEventListener('mouseenter', function() {
                    if (nestTimer) { clearTimeout(nestTimer); nestTimer = null; }
                    nestEl.classList.add('lgcm-pop');
                    row.classList.add('lgcm-held');
                });

                nestEl.addEventListener('mouseleave', function(ev) {
                    if (ev.relatedTarget === row || row.contains(ev.relatedTarget)) return;
                    row.classList.remove('lgcm-held');
                    nestEl.classList.remove('lgcm-pop');
                    nestTimer = setTimeout(function() {
                        if (nestEl && !nestEl.classList.contains('lgcm-pop')) {
                            nestEl.parentNode.removeChild(nestEl);
                            nestEl = null;
                        }
                    }, 190);
                });
            }

            nestEl.style.visibility = 'visible';
            nestEl.classList.add('lgcm-pop');
        });

        row.addEventListener('mouseleave', function(ev) {
            if (nestEl && (nestEl === ev.relatedTarget || nestEl.contains(ev.relatedTarget))) return;
            row.classList.remove('lgcm-held');
            if (nestEl) {
                nestEl.classList.remove('lgcm-pop');
                nestTimer = setTimeout(function() {
                    if (nestEl && !nestEl.classList.contains('lgcm-pop')) {
                        nestEl.parentNode.removeChild(nestEl);
                        nestEl = null;
                    }
                }, 190);
            }
        });
    }

    function placeNest(anchor, nest) {
        var r = anchor.getBoundingClientRect();
        nest.style.display = 'block';
        nest.style.visibility = 'hidden';

        var nw = nest.offsetWidth, nh = nest.offsetHeight;
        var vw = win.innerWidth, vh = win.innerHeight;
        var left, top, origin = 'top left';

        var canRight = r.right + nw + GAP < vw;
        var canLeft = r.left - nw - GAP > 0;

        if (canRight) {
            left = r.right - 4;
            origin = 'top left';
        } else if (canLeft) {
            left = r.left - nw + 4;
            origin = 'top right';
        } else {
            left = Math.max(GAP, r.left);
            origin = (r.bottom + nh + GAP < vh) ? 'top center' : 'bottom center';
        }

        top = r.top - 6;
        if (top + nh + GAP > vh) top = vh - nh - GAP;
        if (top < GAP) top = GAP;

        nest.style.left = left + 'px';
        nest.style.top = top + 'px';
        nest.style.transformOrigin = origin;
    }

    function compose(groups, ctx) {
        _panel.innerHTML = '';
        purgeNests();

        var visible = [];
        for (var g = 0; g < groups.length; g++) {
            var grp = groups[g];
            var kept = [];
            for (var i = 0; i < grp.items.length; i++) {
                var it = grp.items[i];
                if (typeof it.when === 'function' ? it.when(ctx) : true) kept.push(it);
            }
            if (kept.length > 0) {
                visible.push({ id: grp.id, name: grp.name, order: grp.order || 0, items: kept });
            }
        }

        visible.sort(function(a, b) { return a.order - b.order; });

        for (var v = 0; v < visible.length; v++) {
            if (v > 0) {
                var sep = doc.createElement('div');
                sep.className = 'lgcm-divider';
                _panel.appendChild(sep);
            }
            if (visible[v].name) {
                var hd = doc.createElement('div');
                hd.className = 'lgcm-heading';
                hd.textContent = visible[v].name;
                _panel.appendChild(hd);
            }
            paintLayer(visible[v].items, _panel, ctx);
        }

        return visible.length > 0;
    }

    /* ═══════════════════ 显示 / 隐藏 ═══════════════════ */

    function reveal(x, y) {
        if (_popping) return;
        _popping = true;

        _panel.style.display = 'block';
        _panel.style.visibility = 'hidden';
        _panel.style.transition = 'none';

        var pw = _panel.offsetWidth, ph = _panel.offsetHeight;
        var vw = win.innerWidth, vh = win.innerHeight;

        _panel.style.visibility = '';
        _panel.style.transition = '';

        var left = x, top = y, ox = 'left', oy = 'top';
        if (left + pw + GAP > vw) { left = x - pw; ox = 'right'; }
        if (left < GAP) left = GAP;
        if (top + ph + GAP > vh) { top = y - ph; oy = 'bottom'; }
        if (top < GAP) top = GAP;

        _panel.style.transformOrigin = ox + ' ' + oy;
        _panel.style.left = left + 'px';
        _panel.style.top = top + 'px';
        _panel.classList.remove('lgcm-out');

        requestAnimationFrame(function() {
            _panel.classList.add('lgcm-in');
            setTimeout(function() {
                _busy = false;
                _popping = false;
                _shown = true;
            }, DURATION);
        });
    }

    function slide(x, y) {
        var pw = _panel.offsetWidth, ph = _panel.offsetHeight;
        var vw = win.innerWidth, vh = win.innerHeight;
        var left = x, top = y, ox = 'left', oy = 'top';
        if (left + pw + GAP > vw) { left = x - pw; ox = 'right'; }
        if (left < GAP) left = GAP;
        if (top + ph + GAP > vh) { top = y - ph; oy = 'bottom'; }
        if (top < GAP) top = GAP;

        _panel.style.transformOrigin = ox + ' ' + oy;
        requestAnimationFrame(function() {
            _panel.style.left = left + 'px';
            _panel.style.top = top + 'px';
        });
    }

    function dismiss() {
        if (_busy || !_panel) return;
        killTimers();
        _busy = true;
        _popping = false;

        purgeNests();
        _panel.classList.remove('lgcm-in');
        _panel.classList.add('lgcm-out');

        _fadeId = setTimeout(function() {
            if (!_panel) return;
            _panel.style.display = 'none';
            _panel.classList.remove('lgcm-out');
            _busy = false;
            _shown = false;
            _fadeId = null;
        }, DURATION);
    }

    /* ═══════════════════ 事件处理 ═══════════════════ */

    function onRightClick(e) {
        if (isMobile()) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        _preFocus = doc.activeElement;

        var sel = win.getSelection ? win.getSelection().toString() : '';
        var link = findLink(e.target);
        var img = findImage(e.target);
        var isEditable = _preFocus && (
            _preFocus.tagName === 'INPUT' ||
            _preFocus.tagName === 'TEXTAREA' ||
            _preFocus.isContentEditable
        );

        var ctx = {
            text: sel,
            link: link,
            image: img,
            isEditable: isEditable,
            target: _preFocus,
            raw: e
        };

        var matched = _schemes['default'] || null;
        var keys = Object.keys(_schemes);
        for (var k = 0; k < keys.length; k++) {
            if (keys[k] !== 'default' && e.target.closest && e.target.closest(keys[k])) {
                matched = _schemes[keys[k]];
                break;
            }
        }
        if (!matched) return;

        ensurePanel();
        var has = compose(matched, ctx);
        if (!has) { dismiss(); return; }

        var isNew = !_shown && !_popping;
        if (isNew) {
            reveal(e.clientX, e.clientY);
        } else {
            slide(e.clientX, e.clientY);
        }
        _ts = Date.now();
    }

    function onDocClick(e) {
        if (!_shown) return;
        var path = e.composedPath ? e.composedPath() : [];
        if (path.indexOf(_panel) !== -1) return;
        var nests = doc.querySelectorAll('.lgcm-nest');
        for (var i = 0; i < nests.length; i++) {
            if (path.indexOf(nests[i]) !== -1) return;
        }
        dismiss();
    }

    function onScrollAway() {
        if (_shown) dismiss();
    }

    function onEscape(e) {
        if (e.key === 'Escape' && _shown) dismiss();
    }

    function onTouchDown(e) {
        if (_shown && e.touches.length > 0) _touchY0 = e.touches[0].clientY;
    }

    function onTouchSlide(e) {
        if (_shown && e.touches.length > 0 && Math.abs(e.touches[0].clientY - _touchY0) > 6) dismiss();
    }

    /* ═══════════════════ 挂载 / 卸载 ═══════════════════ */

    function bind() {
        if (_active) return;
        ensurePanel();

        _bindList = [
            [win, 'contextmenu', onRightClick, { capture: true }],
            [doc, 'click', onDocClick, false],
            [doc, 'wheel', onScrollAway, { passive: true, capture: true }],
            [win, 'scroll', onScrollAway, { passive: true }],
            [doc, 'keydown', onEscape, false],
            [doc, 'touchstart', onTouchDown, { passive: true }],
            [doc, 'touchmove', onTouchSlide, { passive: true }]
        ];

        for (var i = 0; i < _bindList.length; i++) {
            var b = _bindList[i];
            b[0].addEventListener(b[1], b[2], b[3]);
        }
        _active = true;
    }

    function unbind() {
        if (!_active) return;
        for (var i = 0; i < _bindList.length; i++) {
            var b = _bindList[i];
            b[0].removeEventListener(b[1], b[2], b[3]);
        }
        _bindList = [];
        killTimers();
        if (_shown) dismiss();
        if (_panel && _panel.parentNode) _panel.parentNode.removeChild(_panel);
        _panel = null;
        _active = false;
    }

    /* ═══════════════════ PJAX 兼容 ═══════════════════ */

    function hasPjax() {
        return typeof $ !== 'undefined' && typeof $.pjax === 'function';
    }

    function pjaxNavigate(url) {
        if (hasPjax()) {
            var a = doc.createElement('a');
            a.href = url;
            a.style.display = 'none';
            doc.body.appendChild(a);
            a.click();
            doc.body.removeChild(a);
            return;
        }
        win.location.href = url;
    }

    /* ═══════════════════ 导航历史栈 ═══════════════════ */

    var _navBack = [];
    var _navForward = [];
    var _navSkip = false;

    function navTrack() {
        if (_navSkip) { _navSkip = false; return; }
        var url = win.location.href;
        if (_navBack.length && _navBack[_navBack.length - 1] === url) return;
        _navBack.push(url);
        if (_navBack.length > 30) _navBack.shift();
        _navForward = [];
    }

    function navCanBack() { return _navBack.length >= 2; }
    function navCanForward() { return _navForward.length > 0; }

    function navGoBack() {
        if (!navCanBack()) { win.history.back(); return; }
        var current = _navBack.pop();
        _navForward.push(current);
        _navSkip = true;
        pjaxNavigate(_navBack[_navBack.length - 1]);
    }

    function navGoForward() {
        if (!navCanForward()) { win.history.forward(); return; }
        var next = _navForward.pop();
        _navBack.push(next);
        _navSkip = true;
        pjaxNavigate(next);
    }

    // 记录当前页
    navTrack();
    // PJAX 导航时追踪
    if (typeof $ !== 'undefined') {
        $(doc).on('pjax:complete.lgcm', navTrack);
    }


    /* ═══════════════════ 默认菜单方案 ═══════════════════ */

    function loadDefaults() {
        var homeUrl = (win.LG_CONFIG && win.LG_CONFIG.siteBase) || '/';

        var hasText = function(ctx) { return ctx.text && ctx.text.trim().length > 0; };
        var hasShortText = function(ctx) { return hasText(ctx) && ctx.text.trim().length < 200; };
        var hasLink = function(ctx) { return !!ctx.link && ctx.link.indexOf('javascript:') !== 0; };
        var hasImage = function(ctx) { return !!ctx.image && ctx.image.indexOf('data:') !== 0; };

        _schemes['default'] = [
            {
                id: 'edit',
                order: 10,
                items: [
                    {
                        id: 'do-copy',
                        label: '复制',
                        icon: 'ri-file-copy-line',
                        action: function(ctx) { writeClipboard(ctx.text, '已复制到剪贴板'); },
                        when: hasText
                    },
                    {
                        id: 'do-paste',
                        label: '粘贴',
                        icon: 'ri-clipboard-line',
                        action: function(ctx) { readClipboardInto(ctx.target); },
                        when: function(ctx) {
                            return ctx.isEditable && ctx.target &&
                                (ctx.target.tagName === 'INPUT' || ctx.target.tagName === 'TEXTAREA' || ctx.target.isContentEditable);
                        }
                    },
                    {
                        id: 'do-search',
                        label: '搜索选中文本',
                        icon: 'ri-search-line',
                        when: hasShortText,
                        children: [
                            {
                                id: 'search-bing',
                                label: 'Bing',
                                icon: 'ri-search-line',
                                action: function(ctx) { win.open('https://www.bing.com/search?q=' + encodeURIComponent(ctx.text), '_blank'); }
                            },
                            {
                                id: 'search-google',
                                label: 'Google',
                                icon: 'ri-google-line',
                                action: function(ctx) { win.open('https://www.google.com/search?q=' + encodeURIComponent(ctx.text), '_blank'); }
                            },
                            {
                                id: 'search-baidu',
                                label: '百度',
                                icon: 'ri-baidu-line',
                                action: function(ctx) { win.open('https://www.baidu.com/s?wd=' + encodeURIComponent(ctx.text), '_blank'); }
                            }
                        ]
                    }
                ]
            },
            {
                id: 'anchor',
                order: 20,
                items: [
                    {
                        id: 'link-new',
                        label: '新标签页打开',
                        icon: 'ri-external-link-line',
                        action: function(ctx) { win.open(ctx.link, '_blank'); },
                        when: hasLink
                    },
                    {
                        id: 'link-url',
                        label: '复制链接地址',
                        icon: 'ri-link',
                        action: function(ctx) { writeClipboard(ctx.link, '链接已复制'); },
                        when: hasLink
                    }
                ]
            },
            {
                id: 'media',
                order: 30,
                items: [
                    {
                        id: 'img-view',
                        label: '新标签页查看',
                        icon: 'ri-image-line',
                        action: function(ctx) { win.open(ctx.image, '_blank'); },
                        when: hasImage
                    },
                    {
                        id: 'img-url',
                        label: '复制图片地址',
                        icon: 'ri-links-line',
                        action: function(ctx) { writeClipboard(ctx.image, '图片地址已复制'); },
                        when: hasImage
                    }
                ]
            },
            {
                id: 'music',
                order: 40,
                items: [
                    {
                        id: 'music-toggle',
                        label: function() { return isMusicPlaying() ? '暂停播放' : '播放音乐'; },
                        icon: function() { return isMusicPlaying() ? 'ri-pause-line' : 'ri-play-line'; },
                        action: function() {
                            if (!win.lg_love) return;
                            var playing = isMusicPlaying();
                            win.lg_love.musicToggle(true);
                            setTimeout(function() {
                                var title = getMusicTitle();
                                toast('info', playing ? '已暂停' : (title ? '正在播放：' + title : '开始播放'));
                            }, 150);
                        },
                        when: function() { return isMusicAvailable(); }
                    },
                    {
                        id: 'music-prev',
                        label: '上一首',
                        icon: 'ri-skip-back-line',
                        action: function() {
                            if (!win.lg_love) return;
                            win.lg_love.musicSkipBack();
                            setTimeout(function() { var t = getMusicTitle(); if (t) toast('info', t); }, 300);
                        },
                        when: function() { return isMusicPlaying(); }
                    },
                    {
                        id: 'music-next',
                        label: '下一首',
                        icon: 'ri-skip-forward-line',
                        action: function() {
                            if (!win.lg_love) return;
                            win.lg_love.musicSkipForward();
                            setTimeout(function() { var t = getMusicTitle(); if (t) toast('info', t); }, 300);
                        },
                        when: function() { return isMusicPlaying(); }
                    }
                ]
            },
            (function() {
                var goBack = {
                    id: 'go-back',
                    label: '返回上页',
                    icon: 'ri-arrow-left-line',
                    action: function() {
                        toast('info', '正在返回…');
                        navGoBack();
                    },
                    when: function() { return navCanBack(); }
                };

                var moreItems = [
                    {
                        id: 'go-forward',
                        label: '前进',
                        icon: 'ri-arrow-right-line',
                        action: function() {
                            toast('info', '正在前进…');
                            navGoForward();
                        },
                        when: function() { return navCanForward(); }
                    },
                    {
                        id: 'do-reload',
                        label: '刷新页面',
                        icon: 'ri-refresh-line',
                        action: function() {
                            toast('info', '正在刷新…');
                            location.reload();
                        }
                    },
                    { separator: true },
                    {
                        id: 'page-copy',
                        label: '复制页面链接',
                        icon: 'ri-earth-line',
                        action: function() { writeClipboard(win.location.href, '页面链接已复制'); }
                    },
                    {
                        id: 'goto-top',
                        label: '回到顶部',
                        icon: 'ri-arrow-up-double-line',
                        action: function() { win.scrollTo({ top: 0, behavior: 'smooth' }); },
                        when: function() { return win.scrollY > 300; }
                    },
                    {
                        id: 'goto-bottom',
                        label: '滚到底部',
                        icon: 'ri-arrow-down-double-line',
                        action: function() { win.scrollTo({ top: doc.body.scrollHeight, behavior: 'smooth' }); }
                    },
                    { separator: true },
                    {
                        id: 'go-home',
                        label: '返回首页',
                        icon: 'ri-home-5-line',
                        action: function() {
                            toast('info', '正在跳转…');
                            pjaxNavigate(homeUrl);
                        },
                        when: function() { return win.location.pathname !== '/' && win.location.pathname !== homeUrl; }
                    }
                ];

                var navItems = [goBack];
                if (isMusicAvailable()) {
                    navItems.push({
                        id: 'nav-more',
                        label: '更多操作',
                        icon: 'ri-apps-line',
                        children: moreItems
                    });
                } else {
                    for (var i = 0; i < moreItems.length; i++) navItems.push(moreItems[i]);
                }

                return { id: 'nav', order: 50, items: navItems };
            })()
        ];
    }

    /* ═══════════════════ 公共 API ═══════════════════ */

    win.LGContextMenu = {
        init: function() {
            if (_active) return;
            loadDefaults();
            bind();
        },
        destroy: function() {
            unbind();
            _schemes = {};
        },
        setScheme: function(selector, groups) {
            _schemes[selector] = groups;
        },
        removeScheme: function(selector) {
            delete _schemes[selector];
        },
        dismiss: dismiss
    };

    /* ═══════════════════ 自动初始化 ═══════════════════ */

    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', function() { win.LGContextMenu.init(); });
    } else {
        win.LGContextMenu.init();
    }

})(window, document);
