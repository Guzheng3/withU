/**
 * LG Interaction — 公共点赞 / 浏览量交互模块
 *
 * 使用方式：
 *   1. 在页面中引入此 JS
 *   2. 点赞按钮添加属性：data-like-target="article" data-like-id="123"
 *   3. 浏览量容器添加属性：data-view-count="article:123"
 *   4. 点赞计数容器添加属性：data-like-count="article:123"
 *   5. 页面加载后调用 LGInteraction.init() 或自动初始化
 *
 * 自动行为：
 *   - 页面加载时自动记录浏览量（需要 <body data-view-target="article" data-view-id="123">）
 *   - 自动批量查询所有点赞按钮的状态
 *   - 点赞按钮点击时自动 toggle + 动画
 */
;(function () {
    'use strict';

    function getApiUrl() {
        return (window.LG_CONFIG && window.LG_CONFIG.endpoints && window.LG_CONFIG.endpoints.interaction)
            || ((window.LG_CONFIG && window.LG_CONFIG.siteBase) || '') + 'services/interaction.php';
    }
    var _initialized = false;
    var _pendingViews = [];

    var LGInteraction = {

        /**
         * 初始化：查询状态 + 注册浏览
         */
        init: function () {
            if (_initialized) return;
            _initialized = true;

            this._bindLikeButtons();
            this._loadStatuses();
            this._recordPageView();
        },

        /**
         * 重新初始化（PJAX 切换页面后调用）
         */
        reinit: function () {
            _initialized = false;
            this.init();
        },

        // ============================================
        // 点赞按钮绑定（事件委托，只绑一次）
        // ============================================
        _bindLikeButtons: function () {
            if (LGInteraction._delegated) return;
            LGInteraction._delegated = true;

            document.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-like-target]');
                if (!btn) return;
                e.preventDefault();
                e.stopPropagation();
                LGInteraction._handleLikeClick(btn);
            });
        },

        _handleLikeClick: function (btn) {
            if (btn.classList.contains('lg-interaction-loading')) return;
            btn.classList.add('lg-interaction-loading');

            var targetType = btn.getAttribute('data-like-target');
            var targetId = btn.getAttribute('data-like-id');

            var self = this;
            this._post('like', {
                target_type: targetType,
                target_id: targetId
            }, function (res) {
                btn.classList.remove('lg-interaction-loading');
                if (res.code === 200 && res.data) {
                    var liked = res.data.liked;
                    var count = res.data.like_count;

                    // 更新页面上所有相同目标的按钮状态（联动）
                    var allBtns = document.querySelectorAll(
                        '[data-like-target="' + targetType + '"][data-like-id="' + targetId + '"]'
                    );
                    for (var i = 0; i < allBtns.length; i++) {
                        self._setLikeState(allBtns[i], liked, count);
                    }

                    // 更新页面上所有相同目标的计数
                    self._updateCountDisplays('like', targetType + ':' + targetId, count);

                    // 动画反馈（仅当前按钮）
                    if (liked) {
                        self._animateLike(btn);
                        self._showFeedback(btn, '感谢你的喜欢，已收藏到心里啦~');
                    } else {
                        self._showFeedback(btn, '已取消喜欢');
                    }
                } else {
                    self._showFeedback(btn, res.msg || '操作失败');
                }
            }, function (err) {
                console.error('[LGInteraction] like failed', err);
                btn.classList.remove('lg-interaction-loading');
                self._showFeedback(btn, '网络错误');
            });
        },

        /**
         * 设置点赞按钮的视觉状态
         */
        _setLikeState: function (btn, liked, count) {
            var icon = btn.querySelector('i.ph-heart, i.ph, i.ph-fill');
            if (liked) {
                btn.classList.add('lg-interaction-liked');
                btn.classList.add('is-liked');
                if (icon) {
                    icon.className = icon.className.replace(/\bph\b(?!\-)/, 'ph-fill');
                }
            } else {
                btn.classList.remove('lg-interaction-liked');
                btn.classList.remove('is-liked');
                if (icon) {
                    icon.className = icon.className.replace(/\bph-fill\b/, 'ph');
                }
            }

            // 兼容多种类名的计数元素
            var countEl = btn.querySelector('.lg-interaction-like-num');
            if (!countEl && btn.nextElementSibling && btn.nextElementSibling.classList.contains('lgnewui-message-like-count')) {
                countEl = btn.nextElementSibling;
            }
            if (countEl) {
                var formatted = this._formatCount(count);
                // 一级卡片始终显示数字，二级评论始终显示数字
                countEl.textContent = formatted;
            }
        },

        // ============================================
        // 爱心点赞动画
        // ============================================
        _animateLike: function (btn) {
            // 爱心粒子爆发效果
            var rect = btn.getBoundingClientRect();
            var centerX = rect.left + rect.width / 2;
            var centerY = rect.top + rect.height / 2;

            for (var i = 0; i < 6; i++) {
                this._createHeartParticle(centerX, centerY, i);
            }

            // 按钮弹跳
            btn.classList.add('lg-interaction-bounce');
            setTimeout(function () {
                btn.classList.remove('lg-interaction-bounce');
            }, 600);
        },

        _createHeartParticle: function (x, y, index) {
            var particle = document.createElement('div');
            particle.className = 'lg-interaction-heart-particle';
            particle.innerHTML = '<i class="ph-fill ph-heart"></i>';

            var angle = (index / 6) * Math.PI * 2 + (Math.random() - 0.5) * 0.8;
            var distance = 30 + Math.random() * 25;
            var tx = Math.cos(angle) * distance;
            var ty = Math.sin(angle) * distance - 20;
            var scale = 0.5 + Math.random() * 0.5;

            particle.style.cssText =
                'position:fixed;z-index:99999;pointer-events:none;' +
                'left:' + x + 'px;top:' + y + 'px;' +
                'color:#ef4444;font-size:14px;opacity:1;' +
                'transform:translate(-50%,-50%) scale(' + scale + ');' +
                'transition:all 0.6s cubic-bezier(.2,.8,.3,1);';

            document.body.appendChild(particle);

            requestAnimationFrame(function () {
                particle.style.transform =
                    'translate(calc(-50% + ' + tx + 'px), calc(-50% + ' + ty + 'px)) scale(0)';
                particle.style.opacity = '0';
            });

            setTimeout(function () {
                if (particle.parentNode) particle.parentNode.removeChild(particle);
            }, 700);
        },

        // ============================================
        // 反馈提示（轻量 toast）
        // ============================================
        _showFeedback: function (anchor, msg) {
            if (typeof Toastify !== 'undefined' && Toastify.showScenario) {
                Toastify.showScenario('info', { text: msg });
            } else if (typeof Toastify !== 'undefined') {
                Toastify({ text: msg, duration: 1500, gravity: 'top', position: 'center' }).showToast();
            }
        },

        // ============================================
        // 批量查询状态
        // ============================================
        _loadStatuses: function () {
            var buttons = document.querySelectorAll('[data-like-target]');
            var viewEls = document.querySelectorAll('[data-view-count]');
            var likeCountEls = document.querySelectorAll('[data-like-count]');

            var itemsSet = {};

            for (var i = 0; i < buttons.length; i++) {
                var t = buttons[i].getAttribute('data-like-target');
                var id = buttons[i].getAttribute('data-like-id');
                if (t && id) itemsSet[t + ':' + id] = true;
            }
            for (var j = 0; j < viewEls.length; j++) {
                var key = viewEls[j].getAttribute('data-view-count');
                if (key) itemsSet[key] = true;
            }
            for (var k = 0; k < likeCountEls.length; k++) {
                var lkey = likeCountEls[k].getAttribute('data-like-count');
                if (lkey) itemsSet[lkey] = true;
            }

            var items = Object.keys(itemsSet);
            if (items.length === 0) return;

            var self = this;
            this._get('status', { items: items.join(',') }, function (res) {
                if (res.code === 200 && res.data && res.data.items) {
                    res.data.items.forEach(function (item) {
                        var key = item.target_type + ':' + item.target_id;

                        // 更新点赞按钮状态
                        var btns = document.querySelectorAll(
                            '[data-like-target="' + item.target_type + '"][data-like-id="' + item.target_id + '"]'
                        );
                        for (var b = 0; b < btns.length; b++) {
                            self._setLikeState(btns[b], item.liked, item.like_count);
                        }

                        // 更新浏览量显示
                        self._updateCountDisplays('view', key, item.view_count);
                        self._updateCountDisplays('like', key, item.like_count);
                    });
                }
            });
        },

        // ============================================
        // 记录页面浏览
        // ============================================
        _recordPageView: function () {
            // 优先从 #lg-view-meta 读取（PJAX 模式下内容会被替换）
            var meta = document.getElementById('lg-view-meta');
            var vt, vid;
            if (meta) {
                vt = meta.getAttribute('data-view-target');
                vid = meta.getAttribute('data-view-id');
            }
            // 降级：从 #pjax-container 或 body 读取
            if (!vt || !vid) {
                var container = document.getElementById('pjax-container') || document.body;
                vt = container.getAttribute('data-view-target');
                vid = container.getAttribute('data-view-id');
            }
            if (!vt || !vid) return;

            this._post('view', {
                target_type: vt,
                target_id: vid
            }, function (res) {
                if (res.code === 200 && res.data) {
                    LGInteraction._updateCountDisplays('view', vt + ':' + vid, res.data.view_count);
                }
            });
        },

        // ============================================
        // 更新页面上的计数显示
        // ============================================
        _updateCountDisplays: function (type, key, count) {
            var attr = type === 'like' ? 'data-like-count' : 'data-view-count';
            var els = document.querySelectorAll('[' + attr + '="' + key + '"]');
            var formatted = this._formatCount(count);
            for (var i = 0; i < els.length; i++) {
                els[i].textContent = formatted;
                // 数字变化动画
                els[i].classList.add('lg-interaction-count-update');
                (function (el) {
                    setTimeout(function () {
                        el.classList.remove('lg-interaction-count-update');
                    }, 300);
                })(els[i]);
            }

            // 更新包含该计数的 Tooltip (data-lg-tip 属性)
            if (type === 'like') {
                var tipBtns = document.querySelectorAll('[data-lg-tip*="' + attr + "='" + key + "'" + '"]');
                for (var j = 0; j < tipBtns.length; j++) {
                    var tipHtml = tipBtns[j].getAttribute('data-lg-tip');
                    // 替换 span 内的数字
                    var newTip = tipHtml.replace(
                        new RegExp("(<span[^>]*" + attr + "=['\"]" + key.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + "['\"][^>]*>)\\d*(<\\/span>)"),
                        '$1' + formatted + '$2'
                    );
                    tipBtns[j].setAttribute('data-lg-tip', newTip);
                }
            }
        },

        /**
         * 数字格式化（1234 → 1.2k）
         */
        _formatCount: function (n) {
            n = parseInt(n, 10) || 0;
            if (n >= 10000) return (n / 10000).toFixed(1) + 'w';
            if (n >= 1000) return (n / 1000).toFixed(1) + 'k';
            return String(n);
        },

        // ============================================
        // HTTP 封装
        // ============================================
        _post: function (action, params, onSuccess, onError) {
            var url = getApiUrl();
            var body = 'action=' + encodeURIComponent(action);
            for (var k in params) {
                if (params.hasOwnProperty(k)) {
                    body += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
                }
            }

            var xhr = new XMLHttpRequest();
            xhr.open('POST', url, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.timeout = 8000;
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) return;
                if (xhr.status >= 200 && xhr.status < 500) {
                    try {
                        var res = JSON.parse(xhr.responseText);
                        if (onSuccess) onSuccess(res);
                    } catch (e) {
                        console.error('[LGInteraction] POST parse error', e);
                        if (onError) onError(e);
                    }
                } else {
                    console.error('[LGInteraction] POST HTTP error', xhr.status);
                    if (onError) onError(new Error('HTTP ' + xhr.status));
                }
            };
            xhr.onerror = function () { console.error('[LGInteraction] POST onerror', url); if (onError) onError(new Error('Network error')); };
            xhr.ontimeout = function () { console.error('[LGInteraction] POST timeout', url); if (onError) onError(new Error('Timeout')); };
            xhr.send(body);
        },

        _get: function (action, params, onSuccess, onError) {
            var qs = 'action=' + encodeURIComponent(action);
            for (var k in params) {
                if (params.hasOwnProperty(k)) {
                    qs += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
                }
            }

            var url = getApiUrl() + '?' + qs;
            var xhr = new XMLHttpRequest();
            xhr.open('GET', url, true);
            xhr.timeout = 8000;
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) return;
                if (xhr.status >= 200 && xhr.status < 500) {
                    try {
                        var res = JSON.parse(xhr.responseText);
                        if (onSuccess) onSuccess(res);
                    } catch (e) {
                        console.error('[LGInteraction] GET parse error', e, xhr.responseText.substring(0, 200));
                        if (onError) onError(e);
                    }
                } else {
                    console.error('[LGInteraction] GET HTTP error', xhr.status);
                    if (onError) onError(new Error('HTTP ' + xhr.status));
                }
            };
            xhr.onerror = function () { console.error('[LGInteraction] GET onerror'); if (onError) onError(new Error('Network error')); };
            xhr.ontimeout = function () { console.error('[LGInteraction] GET timeout'); if (onError) onError(new Error('Timeout')); };
            xhr.send();
        }
    };

    // 挂载到全局
    window.LGInteraction = LGInteraction;

    // 自动初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { LGInteraction.init(); });
    } else {
        LGInteraction.init();
    }
})();
