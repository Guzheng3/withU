/**
 * withU 前台 WebP 默认加载模块
 * @description 页面上的本站 /uploads/ 图片（含懒加载 data-src、灯箱 data-original），
 *              若存在上传时生成的同名 .webp 副本，则默认加载 WebP；原始地址保留在
 *              模块映射中，灯箱内提供「查看原图」入口。
 *              可用性由后台设置「前台默认加载 WebP 副本」(front_webp_default) 控制，
 *              开关关闭或图片没有副本时自动回落原图。
 */
;(function (window, document) {
    'use strict';

    var API = '/services/webp-default.php';

    // webp 地址 pathname -> 原始地址（「查看原图」用）
    var rawMap = {};
    // 原始地址 pathname -> webp 地址（已确认存在副本）
    var swapMap = {};
    // 已向服务端查询过的原始路径（无论结果，避免重复请求）
    var checkedPaths = {};
    var pendingPaths = {};
    var pendingList = [];
    var fetchTimer = null;
    var rescanTimer = null;
    var disabled = false;
    var lightboxObserver = null;

    // ── 工具 ────────────────────────────────────────────────

    function normPath(url) {
        if (!url || url.indexOf('data:') === 0 || url.indexOf('blob:') === 0) return '';
        try {
            return new URL(url, window.location.origin).pathname;
        } catch (e) {
            return '';
        }
    }

    function isSameOrigin(url) {
        if (!url) return false;
        if (url.charAt(0) === '/') return true;
        try {
            return new URL(url, window.location.origin).origin === window.location.origin;
        } catch (e) {
            return false;
        }
    }

    function isImagePath(path) {
        return /\.(jpe?g|png)$/i.test(path || '');
    }

    // ── 扫描与替换 ──────────────────────────────────────────

    function collectCandidates() {
        var els = document.querySelectorAll('img[src], img[data-src], [data-original]');
        var found = {};
        for (var i = 0; i < els.length; i++) {
            var attrs = ['data-src', 'data-original', 'src'];
            for (var a = 0; a < attrs.length; a++) {
                var val = els[i].getAttribute(attrs[a]);
                if (!val || !isSameOrigin(val)) continue;
                var path = normPath(val);
                if (path.indexOf('/uploads/') !== 0) continue;
                // 已经是 WebP 的不再处理（含已替换过的）
                if (!isImagePath(path)) continue;
                if (checkedPaths[path] || pendingPaths[path] || swapMap[path]) continue;
                found[path] = true;
            }
        }
        return Object.keys(found);
    }

    function applySwaps(root) {
        var scope = root || document;
        var els = scope.querySelectorAll('img[data-src], img[src], [data-original]');
        var lazyTouched = false;
        for (var i = 0; i < els.length; i++) {
            var el = els[i];
            var attrs = ['data-src', 'data-original', 'src'];
            for (var a = 0; a < attrs.length; a++) {
                var attr = attrs[a];
                var val = el.getAttribute(attr);
                if (!val) continue;
                var webp = swapMap[normPath(val)];
                if (!webp) continue;
                // 保留原地址上的查询参数（缓存戳等）
                var suffix = '';
                var qIdx = val.indexOf('?');
                if (qIdx > -1) suffix = val.slice(qIdx);
                el.setAttribute(attr, webp + suffix);
                el.setAttribute('data-has-webp', '1');
                // 懒加载已完成的情况下，data-src 换了也要同步 src
                if (attr === 'data-src' && el.tagName === 'IMG' && el.getAttribute('src')) {
                    el.setAttribute('src', webp + suffix);
                }
                if (attr === 'data-src') lazyTouched = true;
            }
        }
        if (lazyTouched && window.lazyLoadInstance && window.lazyLoadInstance.update) {
            window.lazyLoadInstance.update();
        }
    }

    function scheduleFetch() {
        if (fetchTimer) return;
        fetchTimer = setTimeout(function () {
            fetchTimer = null;
            fetchSwaps();
        }, 120);
    }

    function fetchSwaps() {
        if (disabled) return;
        var paths = collectCandidates();
        if (!paths.length) return;

        paths.forEach(function (p) {
            checkedPaths[p] = true;
            pendingPaths[p] = true;
            pendingList.push(p);
        });

        var payload = JSON.stringify({ paths: pendingList });
        pendingList = [];

        fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: payload
        }).then(function (r) { return r.json(); }).then(function (res) {
            pendingPaths = {};
            if (!res || res.enabled === false) {
                // 开关关闭：本页不再请求，保持原图加载
                disabled = true;
                return;
            }
            var map = (res && res.map) || {};
            var changed = false;
            Object.keys(map).forEach(function (origin) {
                var webp = map[origin];
                if (!swapMap[origin]) {
                    swapMap[origin] = webp;
                    rawMap[webp] = origin;
                    changed = true;
                }
            });
            if (changed) {
                applySwaps(document);
                syncLightbox();
            }
        }).catch(function () {
            pendingPaths = {};
        });
    }

    // ── 灯箱「查看原图」 ────────────────────────────────────

    var STYLE_SEED = 'withu-webp-default-style';
    function ensureStyle() {
        if (document.getElementById(STYLE_SEED)) return;
        var style = document.createElement('style');
        style.id = STYLE_SEED;
        style.textContent = [
            '.withu-view-original-btn{position:absolute;left:50%;bottom:1.4rem;transform:translateX(-50%);',
            'z-index:12;display:inline-flex;align-items:center;gap:.45rem;padding:.5rem 1.15rem;border:none;',
            'border-radius:999px;background:rgba(255,255,255,.16);backdrop-filter:blur(14px);',
            '-webkit-backdrop-filter:blur(14px);color:#fff;font-size:.82rem;letter-spacing:.05em;cursor:pointer;',
            'transition:background .2s ease;user-select:none;}',
            '.withu-view-original-btn:hover{background:rgba(255,255,255,.3);}',
            '.withu-view-original-btn[hidden]{display:none;}'
        ].join('');
        document.head.appendChild(style);
    }

    function getLightboxBtn(container) {
        var btn = container.querySelector('.withu-view-original-btn');
        if (!btn) {
            ensureStyle();
            btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'withu-view-original-btn';
            btn.innerHTML = '<i class="ph ph-image" aria-hidden="true"></i><span>查看原图</span>';
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var leadImg = container.querySelector('.view-image-lead img');
                var raw = btn.getAttribute('data-raw-url');
                if (leadImg && raw) {
                    leadImg.setAttribute('src', raw);
                }
                btn.hidden = true;
            });
            container.appendChild(btn);
        }
        return btn;
    }

    function syncLightbox() {
        var container = document.querySelector('.view-image');
        if (!container) return;
        var leadImg = container.querySelector('.view-image-lead img');
        var btn = getLightboxBtn(container);
        var raw = null;
        if (leadImg) {
            raw = rawMap[normPath(leadImg.getAttribute('src'))] || null;
        }
        if (raw) {
            btn.setAttribute('data-raw-url', raw);
            btn.hidden = false;
        } else {
            btn.hidden = true;
        }
    }

    function watchLightbox() {
        if (lightboxObserver) return;
        lightboxObserver = new MutationObserver(function () {
            syncLightbox();
        });
        // 灯箱容器由 ViewImage 动态插入 body，观察 body 即可
        lightboxObserver.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['src']
        });
    }

    function patchViewImage() {
        if (!window.ViewImage || window.ViewImage.__withuWebpPatched) return;
        window.ViewImage.__withuWebpPatched = true;
        var rawDisplay = window.ViewImage.display.bind(window.ViewImage);
        window.ViewImage.display = function (list, current) {
            rawDisplay(list, current);
            // 灯箱 DOM 为异步插入，交给观察器同步按钮状态
            setTimeout(syncLightbox, 80);
        };
    }

    // ── 动态内容监听（pjax / 相册异步渲染等） ───────────────

    function scheduleRescan() {
        if (rescanTimer) return;
        rescanTimer = setTimeout(function () {
            rescanTimer = null;
            // 先用已知映射处理新增元素（pjax / 异步渲染的卡片）
            if (!disabled && Object.keys(swapMap).length) {
                applySwaps(document);
            }
            if (!disabled) {
                fetchSwaps();
            }
            syncLightbox();
        }, 200);
    }

    function init() {
        patchViewImage();
        watchLightbox();
        if (!disabled) {
            fetchSwaps();
        }

        var bodyObserver = new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                if (mutations[i].addedNodes && mutations[i].addedNodes.length) {
                    scheduleRescan();
                    return;
                }
            }
        });
        bodyObserver.observe(document.body, { childList: true, subtree: true });

        document.addEventListener('pjax:end', scheduleRescan);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // 暴露给其他模块在动态渲染后手动触发
    window.WithUWebpDefault = {
        rescan: scheduleRescan,
        syncLightbox: syncLightbox
    };

})(window, document);
