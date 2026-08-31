/**
 * withU 留言板页面模块
 * @version 3.0.0
 * @description messages.php 页面的 JS 逻辑
 *   - AJAX 分页加载留言卡片（骨架屏 + 动画）
 *   - 侧边抽屉查看/提交回复（二级评论）
 *   - 抽屉内表情面板（复用 OwO/emoji.json）
 *   - 极验验证（一级留言由 footer.php 全局处理，二级回复由本模块处理）
 *   - 懒加载兼容 PJAX
 */

;(function(window, $) {
    'use strict';

    var WithUConfig = window.WITHU_CONFIG || {};
    var siteTitle = WithUConfig.title || 'withU';
    var endpoints = WithUConfig.endpoints || {};

    // ============================================
    // 工具函数
    // ============================================
    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    /**
     * 前端违禁词校验（剥离表情标记后逐字检查）
     * @param {string} text 待检查文本（可拼接昵称+内容）
     * @return {boolean} true=包含违禁内容
     */
    function containsBannedChar(text) {
        var banned = (WithUConfig.bannedChars || '').trim();
        if (!banned) return false;
        var clean = text.replace(/([:@]{1,2})\(.*?\)/g, '');
        for (var i = 0; i < banned.length; i++) {
            var ch = banned[i];
            if (ch.trim() && clean.indexOf(ch) !== -1) return true;
        }
        return false;
    }

    function displayName(name, qq) {
        if (name === '匿名' && qq && qq !== 'anon' && /\d{4}$/.test(qq)) {
            return '朋友(' + qq.slice(-4) + ')';
        }
        return name;
    }

    var _defaultAvatar = '/_qqavatar.php?qq=10000&s=100';

    function getAvatarUrl(qq) {
        if (qq === 'anon') {
            var list = WithUConfig.anonAvatars || [];
            return list.length ? list[Math.floor(Math.random() * list.length)] : _defaultAvatar;
        }
        return '/_qqavatar.php?qq=' + qq + '&s=100';
    }

    // 滚动锁定（复用 head.php 中定义的全局 withuScrollLock/withuScrollUnlock）
    function lockBodyScroll() { if (window.withuScrollLock) withuScrollLock(); }
    function unlockBodyScroll() { if (window.withuScrollUnlock) withuScrollUnlock(); }

    function updateLazyLoad() {
        if (window.lazyLoadInstance) window.lazyLoadInstance.update();
    }

    function relativeTime(timestamp) {
        if (!timestamp) return '';
        var now = Math.floor(Date.now() / 1000);
        var diff = now - timestamp;
        if (diff < 60) return '刚刚';
        if (diff < 3600) return Math.floor(diff / 60) + ' 分钟前';
        if (diff < 86400) return Math.floor(diff / 3600) + ' 小时前';
        if (diff < 2592000) return Math.floor(diff / 86400) + ' 天前';
        if (diff < 31536000) return Math.floor(diff / 2592000) + ' 个月前';
        return Math.floor(diff / 31536000) + ' 年前';
    }

    /**
     * 渲染用户徽章 HTML
     * @param {object|null} badge  API 返回的 {type, level, label}
     * @return {string} HTML
     */
    function renderBadge(badge) {
        if (!badge) return '';
        var type = badge.type;
        var level = badge.level || 0;
        var label = escapeHtml(badge.label || '');

        var cls = 'withu-message-badge';
        var iconHtml = '';

        if (type === 'dev') {
            cls += ' withu-message-badge-dev';
            iconHtml = '<i class="ph-fill ph-seal-check"></i>';
        } else if (type === 'admin') {
            cls += ' withu-message-badge-admin withu-message-badge-shine';
            iconHtml = '<i class="ph-fill ph-crown"></i>';
        } else {
            cls += ' withu-message-badge-lv withu-message-badge-lv' + level;
            if (level >= 6) cls += ' withu-message-badge-shine';
            iconHtml = '<i class="ph-fill ph-star-four"></i>';
        }

        return '<span class="' + cls + '">' +
            '<span class="withu-message-badge-icon-box">' + iconHtml + '</span>' +
            '<span class="withu-message-badge-text">' + label + '</span>' +
        '</span>';
    }

    /**
     * 渲染"待审核"徽章 HTML
     */
    function renderPendingBadge() {
        return '<span class="withu-message-badge withu-message-badge-pending">' +
            '<span class="withu-message-badge-icon-box"><i class="ph-fill ph-hourglass"></i></span>' +
            '<span class="withu-message-badge-text">待审核</span>' +
        '</span>';
    }

    /**
     * 在一级卡片列表顶部插入一个待审核临时卡片
     */
    function _insertPendingCard(qq, name, contentHtml) {
        var grid = document.getElementById('withumsgCardGrid');
        if (!grid) return;
        var avatarUrl = localStorage.getItem('withu_comment_avatar') || getAvatarUrl(qq);
        var now = new Date();
        var pad = function(n) { return n < 10 ? '0' + n : '' + n; };
        var timeStr = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()) + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes());

        var wrap = document.createElement('div');
        wrap.className = 'MessageCard col-withu-6 col-md-6 col-sm-12 col-sm-x-12 is-pending';
        wrap.setAttribute('data-aos', 'fade-up');

        wrap.innerHTML =
            '<div class="MsgTime"><span>' + escapeHtml(timeStr) + '</span></div>' +
            '<div class="UserAvatar">' +
                '<img src="' + escapeHtml(avatarUrl) + '" alt="' + escapeHtml(name) + '" draggable="false">' +
            '</div>' +
            '<div class="UserName"><h1><span class="withumsg-card-name">' + escapeHtml(name) + '</span>' + renderPendingBadge() + '</h1></div>' +
            '<div class="HeightCalc">' +
                '<div class="MsgContent"><p>' + contentHtml + '</p></div>' +
                '<div class="MsgFooter">' +
                    '<div class="UserInfo">' +
                        '<span class="InfoItem"><i data-lucide="clock" class="withumsg-ico"></i><span class="withumsg-info-text">刚刚</span></span>' +
                    '</div>' +
                '</div>' +
            '</div>';

        // 移除"还没有留言"的空提示
        var emptyEl = grid.querySelector('.withumsg-empty');
        if (emptyEl) emptyEl.remove();

        grid.insertBefore(wrap, grid.firstChild);
        if (typeof lucide !== 'undefined') lucide.createIcons();
        if (typeof AOS !== 'undefined') AOS.refreshHard();
    }

    /**
     * 弹出开发者身份验证弹窗（复用后台安全码验证弹窗风格）
     * @param {function} onConfirm  回调 (password, showSuccess, showError)
     */
    function _promptDevPassword(onConfirm) {
        var old = document.getElementById('withumsgSecurityOverlay');
        if (old) old.remove();

        var overlay = document.createElement('div');
        overlay.id = 'withumsgSecurityOverlay';
        overlay.className = 'withumsg-security-overlay';

        overlay.innerHTML =
            '<div class="withumsg-security-base" id="withumsgSecurityBase">' +

                // 表单内容层（验证成功时景深退场）
                '<div class="withumsg-security-form-content">' +
                    '<button class="withumsg-security-close" id="withumsgSecurityClose" aria-label="关闭"><i class="ph-bold ph-x"></i></button>' +
                    '<h3 class="withumsg-security-title">身份验证</h3>' +
                    '<p class="withumsg-security-desc">该 QQ 号为开发者专属号码，请输入开发者密码以继续操作。</p>' +
                    '<label class="withumsg-security-input-label">开发者密码</label>' +
                    '<div class="withumsg-security-input-group" id="withumsgSecurityWrap">' +
                        '<span class="withumsg-security-input-icon"><i class="ph-bold ph-lock-key"></i></span>' +
                        '<div class="withumsg-security-pw-wrap">' +
                            '<input type="password" class="withumsg-security-pw-input" id="withumsgSecurityInput" placeholder="输入开发者密码" autocomplete="off" maxlength="64" readonly>' +
                            '<button type="button" class="withumsg-security-pw-toggle" id="withumsgSecurityToggle"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg></button>' +
                        '</div>' +
                    '</div>' +
                    '<div class="withumsg-security-error" id="withumsgSecurityError">密码错误，请重新输入</div>' +
                    '<div class="withumsg-security-hash-info">' +
                        '<div class="withumsg-security-hash-icon"><i class="ph-fill ph-info"></i></div>' +
                        '<p class="withumsg-security-hash-text">开发者密码用于验证开发者身份。如果你不是开发者，请更换 QQ 号码后重新留言。</p>' +
                    '</div>' +
                    '<div class="withumsg-security-actions">' +
                        '<button class="withumsg-security-btn" id="withumsgSecurityConfirm">' +
                            '<i class="ph-bold ph-spinner-gap withumsg-security-btn-spinner" id="withumsgSecuritySpinner"></i>' +
                            '<span class="withumsg-security-btn-text" id="withumsgSecurityBtnText">验证身份</span>' +
                            '<i class="ph-bold ph-arrow-right withumsg-security-btn-icon" id="withumsgSecurityBtnIcon"></i>' +
                        '</button>' +
                    '</div>' +
                '</div>' +

                // 成功动画面板
                '<div class="withumsg-security-success-panel">' +
                    '<div class="withumsg-security-success-aura"></div>' +
                    '<div class="withumsg-security-success-content">' +
                        '<svg class="withumsg-security-success-svg" viewBox="0 0 60 60">' +
                            '<circle class="withumsg-security-success-circle" cx="30" cy="30" r="24" fill="none" stroke="currentColor" stroke-width="4.5" stroke-linecap="round"/>' +
                            '<path class="withumsg-security-success-check" fill="none" stroke="currentColor" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round" d="M18 31.5 l 8.5 8.5 l 17.5 -17.5" />' +
                        '</svg>' +
                        '<div class="withumsg-security-success-text">身份验证成功</div>' +
                    '</div>' +
                '</div>' +

            '</div>';
        document.body.appendChild(overlay);

        var _base = document.getElementById('withumsgSecurityBase');
        var _input = document.getElementById('withumsgSecurityInput');
        var _wrap = document.getElementById('withumsgSecurityWrap');
        var _errorMsg = document.getElementById('withumsgSecurityError');
        var _btn = document.getElementById('withumsgSecurityConfirm');
        var _btnText = document.getElementById('withumsgSecurityBtnText');
        var _btnSpinner = document.getElementById('withumsgSecuritySpinner');
        var _btnIcon = document.getElementById('withumsgSecurityBtnIcon');
        var _closeTimer = null;
        var _isActive = false;

        // 密码明文切换
        var _toggle = document.getElementById('withumsgSecurityToggle');
        if (_toggle) {
            _toggle.addEventListener('click', function() {
                if (!_input) return;
                var isPassword = _input.type === 'password';
                _input.type = isPassword ? 'text' : 'password';
                _toggle.innerHTML = isPassword
                    ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'
                    : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
            });
        }

        // 打开弹窗
        void overlay.offsetWidth;
        if (_base) void _base.offsetWidth;
        overlay.classList.add('withumsg-security-is-open');
        _isActive = true;
        lockBodyScroll();

        // 延迟解锁 readonly 防自动填充
        setTimeout(function() {
            if (_input) { _input.removeAttribute('readonly'); _input.focus(); }
        }, 400);

        function _closeModal(cb) {
            overlay.classList.remove('withumsg-security-is-open');
            overlay.classList.add('withumsg-security-is-closing');
            _isActive = false;
            unlockBodyScroll();
            if (_closeTimer) clearTimeout(_closeTimer);
            _closeTimer = setTimeout(function() {
                overlay.classList.remove('withumsg-security-is-closing');
                if (overlay.parentNode) overlay.remove();
                if (cb) cb();
            }, 500);
        }

        function _triggerError(msg) {
            if (_wrap) {
                void _wrap.offsetWidth;
                _wrap.classList.remove('withumsg-security-input-error-anim');
                void _wrap.offsetWidth;
                _wrap.classList.add('withumsg-security-input-error-anim');
            }
            if (_errorMsg) {
                if (msg) _errorMsg.textContent = msg;
                _errorMsg.style.display = 'block';
            }
        }

        function _resetLoading() {
            if (_btn) _btn.classList.remove('withumsg-security-is-loading');
            if (_btnSpinner) _btnSpinner.style.display = 'none';
            if (_btnIcon) _btnIcon.style.display = '';
            if (_btnText) _btnText.textContent = '验证身份';
        }

        // 成功动画 → 延迟关闭
        function showSuccess(cb) {
            if (_base) {
                _base.classList.add('withumsg-security-is-success');
                setTimeout(function() {
                    _base.classList.add('withumsg-security-trigger-bump');
                }, 500);
            }
            setTimeout(function() { _closeModal(cb); }, 2200);
        }

        // 失败：抖动 + 错误提示
        function showError(errMsg) {
            _resetLoading();
            _triggerError(errMsg || '密码错误，请重新输入');
            if (_input) {
                _input.disabled = false;
                _input.value = '';
                _input.focus();
            }
        }

        function _doConfirm() {
            var pw = (_input ? _input.value : '').trim();
            if (_wrap) _wrap.classList.remove('withumsg-security-input-error-anim');
            if (_errorMsg) _errorMsg.style.display = 'none';

            if (pw === '') {
                _triggerError('请输入开发者密码');
                return;
            }

            // loading 状态
            if (_btn) _btn.classList.add('withumsg-security-is-loading');
            if (_btnSpinner) _btnSpinner.style.display = 'inline-block';
            if (_btnIcon) _btnIcon.style.display = 'none';
            if (_btnText) _btnText.textContent = '正在验证...';
            if (_input) _input.disabled = true;

            onConfirm(pw, showSuccess, showError);
        }

        // 事件绑定
        document.getElementById('withumsgSecurityClose').onclick = function() { _closeModal(); };
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay && _base && !_base.classList.contains('withumsg-security-is-success')) {
                _closeModal();
            }
        });
        if (_base) _base.addEventListener('click', function(e) { e.stopPropagation(); });
        if (_btn) _btn.addEventListener('click', _doConfirm);
        if (_input) _input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); _doConfirm(); }
        });
        document.addEventListener('keydown', function _escHandler(e) {
            if (e.key === 'Escape' && _isActive && _base && !_base.classList.contains('withumsg-security-is-success')) {
                _closeModal();
                document.removeEventListener('keydown', _escHandler);
            }
        });
    }

    /**
     * QQ 风格时间格式化（用于抽屉消息时间分隔）
     * - 几秒前 / 几分钟前 → 当天很近的消息
     * - 今天 HH:MM
     * - 昨天 HH:MM
     * - 周X HH:MM（一周内）
     * - 超过一周 → YYYY-MM-DD HH:MM
     *
     * @param {number} timestamp 秒级时间戳
     * @param {boolean} isGroupFirst 是否为该日期组的第一条（显示完整标签）
     * @return {string}
     */
    var _weekDays = ['周日', '周一', '周二', '周三', '周四', '周五', '周六'];
    function formatMsgTime(timestamp, isGroupFirst) {
        if (!timestamp) return '';
        var now = new Date();
        var d = new Date(timestamp * 1000);
        var diffSec = Math.floor((now - d) / 1000);
        var pad = function(n) { return n < 10 ? '0' + n : '' + n; };
        var hm = pad(d.getHours()) + ':' + pad(d.getMinutes());

        // 同一天
        var isToday = (d.getFullYear() === now.getFullYear() && d.getMonth() === now.getMonth() && d.getDate() === now.getDate());
        var yesterday = new Date(now); yesterday.setDate(yesterday.getDate() - 1);
        var isYesterday = (d.getFullYear() === yesterday.getFullYear() && d.getMonth() === yesterday.getMonth() && d.getDate() === yesterday.getDate());

        if (isGroupFirst) {
            // 第一条显示完整标签
            if (isToday) {
                if (diffSec < 60) return '刚刚';
                if (diffSec < 3600) return Math.floor(diffSec / 60) + ' 分钟前';
                return '今天 ' + hm;
            }
            if (isYesterday) return '昨天 ' + hm;
            // 一周内
            if (diffSec < 7 * 86400) {
                var daysAgo = Math.floor(diffSec / 86400);
                return (daysAgo > 0 ? daysAgo + ' 天前 ' : '') + _weekDays[d.getDay()] + ' ' + hm;
            }
            // 超过一周
            return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + ' ' + hm;
        } else {
            // 非首条：只显示时间
            return hm;
        }
    }

    /**
     * 判断两个时间戳是否为同一天
     */
    function isSameDay(ts1, ts2) {
        var d1 = new Date(ts1 * 1000);
        var d2 = new Date(ts2 * 1000);
        return d1.getFullYear() === d2.getFullYear() && d1.getMonth() === d2.getMonth() && d1.getDate() === d2.getDate();
    }

    // ============================================
    // 留言列表加载模块
    // ============================================
    var MessageList = {
        _offset: 0,
        _limit: 20,
        _loadMoreLimit: 10,
        _loading: false,
        _hasMore: true,
        _firstLoad: true,

        init: function() {
            this._offset = 0;
            this._loading = false;
            this._hasMore = true;
            this._firstLoad = true;
            this._bindEvents();
            this.load();
        },

        destroy: function() {
            $('#withumsgLoadMoreBtn').off('click.withumsg');
        },

        _bindEvents: function() {
            var self = this;
            $('#withumsgLoadMoreBtn').off('click.withumsg').on('click.withumsg', function() {
                if (!self._loading && self._hasMore) self.load();
            });
        },

        load: function() {
            if (this._loading) return;
            this._loading = true;
            var self = this;
            var limit = this._firstLoad ? this._limit : this._loadMoreLimit;
            var isFirstLoad = this._firstLoad;

            var $btn = $('#withumsgLoadMoreBtn');
            $btn.prop('disabled', true).html('<span class="btn-text"><i class="ph ph-spinner-gap withumsg-spinner"></i> 加载中...</span>');
            var loadStart = Date.now();

            $.ajax({
                url: endpoints.messageList || 'services/message-list.php',
                type: 'GET',
                dataType: 'json',
                data: { action: 'list', offset: this._offset, limit: limit },
                success: function(res) {
                    if (res.code === 200 && res.data) {
                        var items = res.data.items || [];
                        var pagination = res.data.pagination || {};

                        // 非首次加载：保证至少 600ms 的 spinner 缓冲
                        var elapsed = Date.now() - loadStart;
                        var bufferDelay = isFirstLoad ? 0 : Math.max(0, 600 - elapsed);

                        setTimeout(function() {
                            if (isFirstLoad) {
                                $('#withumsgCardGrid .withumsg-skeleton-wrap').remove();
                                self._firstLoad = false;
                            }

                            // 记录渲染前的最后一个卡片，用于滚动定位
                            var $existingCards = $('#withumsgCardGrid .MessageCard');
                            var scrollTarget = !isFirstLoad && $existingCards.length > 0 ? $existingCards.last() : null;

                            self._renderCards(items);
                            if (typeof WithUInteraction !== 'undefined') WithUInteraction.reinit();
                            self._hasMore = !!pagination.has_more;
                            self._offset += items.length;

                            if (self._hasMore) {
                                $('#withumsgLoadMoreWrap').show();
                                $('#withumsgNoMore').hide();
                            } else {
                                $('#withumsgLoadMoreWrap').hide();
                                if (items.length > 0 || $('#withumsgCardGrid .MessageCard').length > 0) {
                                    $('#withumsgNoMore').show();
                                }
                            }

                            if ($('#withumsgCardGrid .MessageCard').length === 0) {
                                var _ab = (window.WITHU_CONFIG && window.WITHU_CONFIG.assetBase) || '';
                                var _sb = (window.WITHU_CONFIG && window.WITHU_CONFIG.siteBase) || '';
                                $('#withumsgCardGrid').html(
                                    '<div style="grid-column:1/-1;">' +
                                    '<div class="withu-no-data withu-no-data--pink">' +
                                    '<div class="withu-no-data-wrap"><div class="withu-no-data-content">' +
                                    '<div class="withu-no-data-icon withu-no-data-icon--pink"><svg viewBox="0 0 24 24" fill="none" stroke="#ec4899" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>' +
                                    '<h3 class="withu-no-data-title">\u8fd8\u6ca1\u6709\u4eba\u7559\u4e0b\u75d5\u8ff9</h3>' +
                                    '<p class="withu-no-data-desc">\u8fd9\u91cc\u5f88\u5b89\u9759\uff0c\u6210\u4e3a\u7b2c\u4e00\u4e2a\u7559\u8a00\u7684\u4eba\u5427\u3002</p>' +
                                    '<div class="withu-no-data-actions"><a class="withu-no-data-btn withu-no-data-btn-primary" href="javascript:;" id="withumsgEmptyWriteBtn"><i class="ph ph-pencil-simple"></i> \u7559\u4e0b\u75d5\u8ff9</a></div>' +
                                    '</div></div></div></div>'
                                );
                                $('#withumsgEmptyWriteBtn').on('click', function(e) {
                                    e.preventDefault();
                                    if (typeof CommentModal !== 'undefined') CommentModal.open();
                                });
                            }

                            updateLazyLoad();

                            // 非首次加载 → 自动滚动到新内容区域（留 80px 顶部空间）
                            if (!isFirstLoad && scrollTarget && scrollTarget.length) {
                                var nextCard = scrollTarget.next('.MessageCard');
                                if (nextCard.length) {
                                    setTimeout(function() {
                                        var rect = nextCard[0].getBoundingClientRect();
                                        var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                                        window.scrollTo({ top: scrollTop + rect.top - 130, behavior: 'smooth' });
                                    }, 100);
                                }
                            }

                            // 首次加载完成 → hash 锚点定位
                            if (isFirstLoad) {
                                self._scrollToHashTarget();
                            }

                            // 恢复按钮
                            self._loading = false;
                            $btn.prop('disabled', false).html('<span class="btn-text"><i class="ph ph-arrow-down"></i> 加载更多</span>');
                        }, bufferDelay);
                    } else {
                        if (typeof Toastify !== 'undefined') Toastify.showScenario('error', { text: res.msg || '加载失败' });
                        self._loading = false;
                        $btn.prop('disabled', false).html('<span class="btn-text"><i class="ph ph-arrow-down"></i> 加载更多</span>');
                    }
                },
                error: function() {
                    if (typeof Toastify !== 'undefined') Toastify.showScenario('error', { text: '网络错误，请稍后重试' });
                    self._loading = false;
                    $btn.prop('disabled', false).html('<span class="btn-text"><i class="ph ph-arrow-down"></i> 加载更多</span>');
                }
            });
        },

        _scrollToHashTarget: function() {
            var hash = window.location.hash;
            if (!hash) return;
            var self = this;

            // ── 高亮卡片工具函数 ──
            function highlightCard(el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                el.style.position = 'relative';
                var ring = document.createElement('div');
                ring.className = 'msg-highlight-ring';
                el.appendChild(ring);
                setTimeout(function() { if (ring.parentNode) ring.parentNode.removeChild(ring); }, 5000);
            }

            // ── 自动加载直到目标出现 ──
            function loadUntilFound(targetDomId, callback, maxRetries) {
                var retries = maxRetries || 50;
                (function attempt() {
                    var el = document.getElementById(targetDomId);
                    if (el) { callback(el); return; }
                    if (!self._hasMore || retries-- <= 0) return;
                    var origOnLoad = null;
                    var origSuccess = null;
                    // 等加载完再检测
                    var check = setInterval(function() {
                        if (!self._loading) {
                            clearInterval(check);
                            self.load();
                            // 等待本次加载完成后递归
                            var poll = setInterval(function() {
                                if (!self._loading) { clearInterval(poll); setTimeout(attempt, 50); }
                            }, 100);
                        }
                    }, 100);
                })();
            }

            // ── 格式1: #comment_{id} → 一级评论定位 ──
            if (hash.indexOf('#comment_') === 0) {
                var targetId = hash.substring(1);
                setTimeout(function() {
                    var el = document.getElementById(targetId);
                    if (el) { highlightCard(el); return; }
                    loadUntilFound(targetId, function(el) { setTimeout(function() { highlightCard(el); }, 100); });
                }, 300);
                return;
            }

            // ── 格式2: #reply_{parentId}_{replyId} → 二级评论：定位父卡片→打开抽屉→滚动到回复并高亮 ──
            var replyMatch = hash.match(/^#reply_(\d+)_(\d+)$/);
            if (!replyMatch) return;
            var parentId = replyMatch[1];
            var replyId = replyMatch[2];

            function openDrawerForReply(parentEl) {
                highlightCard(parentEl);
                setTimeout(function() {
                    if (typeof Drawer === 'undefined') return;
                    var origRender = Drawer._renderDrawerContent;
                    Drawer._renderDrawerContent = function(parent, replies) {
                        Drawer._renderDrawerContent = origRender;
                        origRender.call(Drawer, parent, replies);
                        setTimeout(function() {
                            var replyEl = document.getElementById('withumsg-' + replyId);
                            if (!replyEl) return;
                            replyEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            var $bubble = $(replyEl).find('.withu-message-msg-bubble').first();
                            if ($bubble.length) {
                                $bubble.removeClass('withu-message-bubble-highlight');
                                void $bubble[0].offsetWidth;
                                $bubble.addClass('withu-message-bubble-highlight');
                            }
                        }, 200);
                    };
                    Drawer.open(parseInt(parentId, 10));
                }, 600);
            }

            setTimeout(function() {
                var parentEl = document.getElementById('comment_' + parentId);
                if (parentEl) { openDrawerForReply(parentEl); return; }
                loadUntilFound('comment_' + parentId, function(el) { setTimeout(function() { openDrawerForReply(el); }, 100); });
            }, 300);
        },

        _renderCards: function(items) {
            var grid = document.getElementById('withumsgCardGrid');
            if (!grid) return;
            var fragment = document.createDocumentFragment();

            for (var i = 0; i < items.length; i++) {
                var msg = items[i];
                var wrap = document.createElement('div');
                wrap.id = 'comment_' + msg.id;
                wrap.className = 'MessageCard col-withu-6 col-md-6 col-sm-12 col-sm-x-12';
                wrap.setAttribute('data-msg-id', msg.id);
                // AOS 动画：使用全局配置
                var aosCfg = window.WITHU_AOS_CONFIG || {};
                if (aosCfg.enabled !== false) {
                    var aosDelay = Math.min((aosCfg.delay || 0) + i * (aosCfg.interval || 50), aosCfg.maxDelay || 300);
                    wrap.setAttribute('data-aos', aosCfg.animation || 'fade-up');
                    wrap.setAttribute('data-aos-delay', aosDelay);
                }

                var avatarUrl = msg.avatar || _defaultAvatar;
                var cityDisplay = msg.city ? escapeHtml(msg.city) : '中国';
                var replyCount = parseInt(msg.replyCount) || 0;
                var replyTextFull = replyCount > 0 ? (replyCount + ' 条回复') : '回复';
                var replyTextShort = replyCount;
                var timeAgo = relativeTime(msg.timestamp);
                var osDisplay = msg.os ? escapeHtml(msg.os) : '';
                var browserDisplay = msg.browser ? escapeHtml(msg.browser) : '';

                var infoItemsHtml = '';
                // ① 回复（文字始终可见）
                infoItemsHtml += '<span class="InfoItem withumsg-reply-badge" data-msg-id="' + msg.id + '"><i data-lucide="message-square" class="withumsg-ico"></i><span class="withumsg-info-text withumsg-info-text-full">' + replyTextFull + '</span><span class="withumsg-info-text withumsg-info-text-short">' + replyTextShort + '</span></span>';
                // ② 归属地（文字始终可见，溢出才显示 tooltip）
                if (cityDisplay) {
                    var hasCoords = msg.lng && msg.lat && isFinite(msg.lng) && isFinite(msg.lat);
                    if (hasCoords) {
                        infoItemsHtml += '<span class="InfoItem withumsg-location-link withumsg-location-has-coords" data-lng="' + msg.lng + '" data-lat="' + msg.lat + '" data-marker-id="' + msg.id + '" data-withu-tip="' + cityDisplay + '"><i data-lucide="map-pin" class="withumsg-ico"></i><span class="withumsg-info-text">' + cityDisplay + '</span></span>';
                    } else {
                        infoItemsHtml += '<span class="InfoItem" data-withu-tip="' + cityDisplay + '"><i data-lucide="map-pin" class="withumsg-ico"></i><span class="withumsg-info-text">' + cityDisplay + '</span></span>';
                    }
                }
                // ③ 相对时间（移动端仅图标，点击 tooltip 显示）
                if (timeAgo) {
                    infoItemsHtml += '<span class="InfoItem withumsg-info-hideable" data-withu-tip="' + escapeHtml(timeAgo) + '" data-withu-tip-force="true"><i data-lucide="clock" class="withumsg-ico"></i><span class="withumsg-info-text">' + escapeHtml(timeAgo) + '</span></span>';
                }
                // ④ 天气（移动端仅图标，点击 tooltip 显示）
                var weatherDisplay = msg.weather ? escapeHtml(msg.weather) : '';
                if (weatherDisplay) {
                    var wiCode = msg.weather_icon || '';
                    var wiClass = wiCode ? 'qi-' + wiCode + '-fill' : 'qi-999-fill';
                    infoItemsHtml += '<span class="InfoItem withumsg-info-weather withumsg-info-hideable" data-withu-tip="' + weatherDisplay + '" data-withu-tip-force="true"><i class="' + wiClass + ' withumsg-ico"></i><span class="withumsg-info-text">' + weatherDisplay + '</span></span>';
                }
                // ⑤ 设备 + 浏览器（移动端仅图标，点击 tooltip 显示）
                var deviceParts = [];
                if (osDisplay) deviceParts.push(osDisplay);
                if (browserDisplay) deviceParts.push(browserDisplay);
                if (deviceParts.length) {
                    var deviceFull = deviceParts.join(' · ');
                    infoItemsHtml += '<span class="InfoItem withumsg-info-device withumsg-info-hideable" data-withu-tip="' + deviceFull + '" data-withu-tip-force="true"><i data-lucide="monitor" class="withumsg-ico"></i><span class="withumsg-info-text">' + deviceFull + '</span></span>';
                }

                // ⑥ 点赞按钮
                var likeCount = parseInt(msg.like_count) || 0;
                infoItemsHtml += '<span class="InfoItem withu-interaction-inline-btn" data-like-target="message" data-like-id="' + msg.id + '"><i data-lucide="heart" class="withumsg-ico"></i><span class="withumsg-info-text withu-interaction-like-num">' + likeCount + '</span></span>';

                wrap.innerHTML =
                    '<div class="MsgTime"><span>' + escapeHtml(msg.timeStr) + '</span></div>' +
                    '<div class="UserAvatar">' +
                        '<img class="lazy" data-src="' + escapeHtml(avatarUrl) + '" alt="' + escapeHtml(msg.name) + '" draggable="false">' +
                    '</div>' +
                    '<div class="UserName"><h1><span class="withumsg-card-name">' + escapeHtml(msg.name) + '</span>' + renderBadge(msg.badge) + '</h1></div>' +
                    '<div class="HeightCalc">' +
                        '<div class="MsgContent"><p>' + (msg.textHtml || escapeHtml(msg.text)) + '</p></div>' +
                        '<div class="MsgFooter">' +
                            '<div class="UserInfo">' + infoItemsHtml + '</div>' +
                        '</div>' +
                    '</div>';

                fragment.appendChild(wrap);
            }

            grid.appendChild(fragment);
            if (typeof lucide !== 'undefined') lucide.createIcons();

            // AOS：刷新以识别新插入的 [data-aos] 元素
            if (typeof AOS !== 'undefined') {
                AOS.refreshHard();
            }
        }
    };

    // ============================================
    // 抽屉模块（查看回复 + 提交回复）
    // ============================================
    var Drawer = {
        _parentId: null,
        _parentMsg: null,
        _replyToId: null,
        _replyToName: null,
        _isOpen: false,
        _activeCtxItem: null,
        _activeCtxBubble: null,

        init: function() {
            this._bindEvents();
            this._checkAutoAuth();
        },

        destroy: function() {
            $(document).off('click.withumsgCard');
            $('#withumsgDrawerClose').off('click.withumsg');
            $('#withumsgDrawer').off('click.withumsg');
            $('#withumsgReplySendBtn').off('click.withumsg');
            $('#withumsgDrawerCollapsed').off('click.withumsg');
            $('#withumsgDrawerCollapseBtn').off('click.withumsg');
            $('#withumsgDrawerIdentityClickArea').off('click.withumsg');
            this.close();
        },

        _bindEvents: function() {
            var self = this;

            // 点击卡片打开抽屉（整张卡片可点）
            $(document).off('click.withumsgCard').on('click.withumsgCard', '.MessageCard', function(e) {
                // 不拦截链接、按钮、地图定位、信息胶囊等可交互元素的点击
                if ($(e.target).closest('a, button, input, textarea, .withumsg-location-link, .withumsg-info-hideable, [data-like-target]').length) return;
                var $card = $(this);
                var msgId = $card.data('msg-id');
                if (!msgId) return;

                // 头像 @ 效果
                var $avatar = $card.find('.UserAvatar');
                $avatar.addClass('withumsg-avatar-tap');
                setTimeout(function() { $avatar.removeClass('withumsg-avatar-tap'); }, 600);

                self.open(msgId);
            });
            // 点击归属地定位打开地图（mousedown + click 双重拦截）
            $(document).off('mousedown.withumsgLocation').on('mousedown.withumsgLocation', '.withumsg-location-link', function(e) {
                e.stopImmediatePropagation();
                e.stopPropagation();
            });
            $(document).off('click.withumsgLocation').on('click.withumsgLocation', '.withumsg-location-link', function(e) {
                e.stopImmediatePropagation();
                e.stopPropagation();
                e.preventDefault();
                var $el = $(this);
                var lng = parseFloat($el.attr('data-lng'));
                var lat = parseFloat($el.attr('data-lat'));
                var markerId = parseInt($el.attr('data-marker-id'), 10);
                if (window.WithUMap && !isNaN(lng) && !isNaN(lat) && lng !== 0 && lat !== 0) {
                    WithUMap.open({ mode: 'messages', coords: [lng, lat], zoom: 15, markerId: markerId });
                }
            });

            // 点击回复徽章也打开抽屉
            $(document).off('click.withumsgReplyBadge').on('click.withumsgReplyBadge', '.withumsg-reply-badge', function(e) {
                e.stopPropagation();
                var msgId = $(this).data('msg-id');
                if (msgId) Drawer.open(msgId);
            });

            // 点赞按钮（抽屉内二级评论）- 复用 WithUInteraction
            $(document).off('click.withumsgLikeBtn').on('click.withumsgLikeBtn', '.withu-message-like-btn', function(e) {
                e.stopPropagation();
                // 直接调用 WithUInteraction 的处理逻辑
                if (typeof WithUInteraction !== 'undefined' && WithUInteraction._handleLikeClick) {
                    WithUInteraction._handleLikeClick(this);
                }
            });

            // 关闭抽屉
            $('#withumsgDrawerClose').off('click.withumsg').on('click.withumsg', function() { self.close(); });
            $('#withumsgDrawer').off('click.withumsg').on('click.withumsg', function(e) {
                if (e.target === this || $(e.target).hasClass('withu-message-drawer-overlay')) self.close();
            });

            // 展开/收起底部输入区
            $('#withumsgDrawerCollapsed').off('click.withumsg').on('click.withumsg', function() { self._expandFooter(); });
            $('#withumsgDrawerCollapseBtn').off('click.withumsg').on('click.withumsg', function() { self._collapseFooter(true); });

            // 点击消息区域（非底部输入区）时收起输入面板
            $('#withumsgDrawerBody').off('click.withumsgCollapse').on('click.withumsgCollapse', function(e) {
                if (!$(e.target).closest('.withu-message-msg-bubble, .withu-message-avatar-wrap, .withu-message-at-tag, blockquote, .withu-message-context-menu, .withumsg-location-link').length) {
                    self._collapseFooter();
                }
            });

            // 身份栏点击 → 打开身份认证弹窗
            $('#withumsgDrawerIdentityClickArea').off('click.withumsg').on('click.withumsg', function() {
                AuthModal.open();
            });

            // 发送回复
            $('#withumsgReplySendBtn').off('click.withumsg').on('click.withumsg', function() { self._handleSendReply(); });

            // 点击回复消息的名字 → @回复（与头像点击逻辑一致）
            $(document).off('click.withumsgReplyName', '.withu-message-msg-name').on('click.withumsgReplyName', '.withu-message-msg-name', function(e) {
                e.stopPropagation();
                var $item = $(this).closest('.withu-message-msg-item');
                if ($item.hasClass('is-me')) return;
                var name = $(this).text();
                var id = $item.attr('id') ? $item.attr('id').replace('withumsg-', '') : '';
                if (!name) return;
                self._expandFooter();
                var editor = document.getElementById('withumsgDrawerEditor');
                if (!editor) return;
                if (id && editor.querySelector('.withu-message-at-tag[data-target="' + id + '"]')) {
                    editor.focus();
                    return;
                }
                // 插入目标：优先 inputDiv，否则 editor
                var target = editor.querySelector('.withumsg-editor-input') || editor;
                // 清理空占位
                if (target.innerHTML === '<br>' || target.innerHTML === '<div><br></div>') target.innerHTML = '';
                target.focus();
                var atElem = document.createElement('span');
                atElem.className = 'withu-message-at-tag';
                atElem.contentEditable = 'false';
                atElem.setAttribute('data-target', id);
                atElem.textContent = '@' + name;
                var spaceNode = document.createTextNode(' ');
                target.appendChild(atElem);
                target.appendChild(spaceNode);
                var r = document.createRange();
                r.setStartAfter(spaceNode);
                r.collapse(true);
                var sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(r);
            });

            // 点击 @标签 或 blockquote[data-target] → 滚动到对应消息并高亮
            $(document).off('click.withumsgScrollTarget').on('click.withumsgScrollTarget', '.withu-message-at-tag[data-target], blockquote[data-target], .withu-msg-at[data-id]', function() {
                var targetId = $(this).attr('data-target') || $(this).attr('data-id');
                if (!targetId) return;
                var $msgEl = $('#withumsg-' + targetId);
                if ($msgEl.length) {
                    $msgEl[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                    var $bubble = $msgEl.find('.withu-message-msg-bubble').first();
                    if ($bubble.length) {
                        $bubble.removeClass('withu-message-bubble-highlight');
                        void $bubble[0].offsetWidth;
                        $bubble.addClass('withu-message-bubble-highlight');
                    }
                }
            });

            // 头像点击 → @回复（匹配demo的atUser：插入span.withu-message-at-tag到编辑器）
            $(document).off('click.withumsgAvatarAt', '.withu-message-avatar-wrap').on('click.withumsgAvatarAt', '.withu-message-avatar-wrap', function(e) {
                e.stopPropagation();
                var $item = $(this).closest('.withu-message-msg-item');
                if ($item.hasClass('is-me')) return;
                var name = $item.find('.withu-message-msg-name').first().text() || '';
                var id = $item.attr('id') ? $item.attr('id').replace('withumsg-', '') : '';
                if (!name) return;
                self._expandFooter();
                var editor = document.getElementById('withumsgDrawerEditor');
                if (!editor) return;
                // 防重复
                if (id && editor.querySelector('.withu-message-at-tag[data-target="' + id + '"]')) {
                    editor.focus();
                    return;
                }
                // 插入目标：优先 inputDiv，否则 editor
                var target = editor.querySelector('.withumsg-editor-input') || editor;
                // 清理空占位
                if (target.innerHTML === '<br>' || target.innerHTML === '<div><br></div>') target.innerHTML = '';
                target.focus();
                var atElem = document.createElement('span');
                atElem.className = 'withu-message-at-tag';
                atElem.contentEditable = 'false';
                atElem.setAttribute('data-target', id);
                atElem.textContent = '@' + name;
                var spaceNode = document.createTextNode(' ');
                target.appendChild(atElem);
                target.appendChild(spaceNode);
                var r = document.createRange();
                r.setStartAfter(spaceNode);
                r.collapse(true);
                var sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(r);
            });

            // 消息气泡点击 → 显示上下文菜单
            $(document).off('click.withumsgBubbleCtx', '.withu-message-msg-bubble').on('click.withumsgBubbleCtx', '.withu-message-msg-bubble', function(e) {
                e.stopPropagation();
                var $bubble = $(this);
                var $item = $bubble.closest('.withu-message-msg-item');
                self._activeCtxItem = $item;
                self._activeCtxBubble = $bubble;
                var rect = this.getBoundingClientRect();
                var $menu = $('#withumsgContextMenu');
                // 回复类型消息（含blockquote）隐藏+1按钮
                var hasBq = !!$bubble[0].querySelector('blockquote');
                $menu.find('[data-action="plusone"]').toggle(!hasBq);
                var menuW = $menu.outerWidth() || 90;
                var left = rect.left + (rect.width / 2) - (menuW / 2);
                var top = rect.top - ($menu.outerHeight() || 40) - 8;
                if (top < 10) top = rect.bottom + 8;
                if (left < 10) left = 10;
                if (left + menuW > window.innerWidth - 10) left = window.innerWidth - menuW - 10;
                $menu.css({ left: left + 'px', top: top + 'px' });
                requestAnimationFrame(function() { $menu.addClass('active'); });
            });

            // 上下文菜单动作
            $(document).off('click.withumsgCtxAction', '.withu-message-cm-item').on('click.withumsgCtxAction', '.withu-message-cm-item', function() {
                var action = $(this).data('action');
                self._handleContextAction(action);
            });

            // 点击其他区域关闭上下文菜单
            $(document).off('click.withumsgCtxClose').on('click.withumsgCtxClose', function(e) {
                var $menu = $('#withumsgContextMenu');
                if ($menu.hasClass('active') && !$menu[0].contains(e.target) && !$(e.target).closest('.withu-message-msg-bubble').length) {
                    $menu.removeClass('active');
                }
            });

            // ESC 关闭
            $(document).off('keydown.withumsgDrawer').on('keydown.withumsgDrawer', function(e) {
                if (e.key === 'Escape') {
                    var $menu = $('#withumsgContextMenu');
                    if ($menu.hasClass('active')) { $menu.removeClass('active'); return; }
                    if (self._isOpen) self.close();
                }
            });

            // 滚动时关闭上下文菜单（带动画）— 绑定到真正的滚动容器
            $('#withumsgDrawerScroll').off('scroll.withumsgCtx').on('scroll.withumsgCtx', function() {
                var $menu = $('#withumsgContextMenu');
                if ($menu.hasClass('active')) $menu.removeClass('active');
            });

            // contenteditable 编辑器粘贴（自动解析表情 shortcode）
            $('#withumsgDrawerEditor').off('paste.withumsg').on('paste.withumsg', function(e) {
                e.preventDefault();
                var text = (e.originalEvent || e).clipboardData.getData('text/plain');
                EmojiPanel.pasteWithEmoji(this, text);
                this.dispatchEvent(new Event('input', { bubbles: true }));
            });

            // 字数统计
            $('#withumsgDrawerEditor').off('input.withumsg').on('input.withumsg', function() {
                self._updateCharCounter();
            });

            // 随机一言按钮（复用公共 _showQuoteConfirm + _doFetchQuote）
            $('#withumsgBtnDrawerQuote').off('click.withumsg').on('click.withumsg', function() {
                var editor = document.getElementById('withumsgDrawerEditor');
                if (!editor) return;
                var $btn = $(this);
                if ($btn.data('quoteLoading')) return;
                var hasContent = (editor.textContent || '').trim().length > 0 || editor.querySelector('img.emoji') || editor.querySelector('blockquote');
                if (hasContent && _needShowQuoteConfirm()) {
                    // 有内容且首次提醒 → 弹出确认弹窗
                    _showQuoteConfirm(function() { _doFetchQuote($btn, editor, true); });
                } else {
                    // 无内容或已确认过 → 直接替换
                    _doFetchQuote($btn, editor, hasContent);
                }
            });

            // Enter 发送开关
            var enterToSend = localStorage.getItem('withu_enter_to_send') === 'true';
            if (enterToSend) $('#withumsgDrawerEnterSwitch').addClass('active');
            $('#withumsgDrawerEnterSwitch').off('click.withumsg').on('click.withumsg', function() {
                enterToSend = !enterToSend;
                localStorage.setItem('withu_enter_to_send', enterToSend);
                $(this).toggleClass('active', enterToSend);
                // 同步弹窗内的开关
                $('.withu-message-switch-wrap').each(function() {
                    if (enterToSend) $(this).addClass('active');
                    else $(this).removeClass('active');
                });
            });

            // Enter 快捷发送
            $('#withumsgDrawerEditor').off('keydown.withumsgEnter').on('keydown.withumsgEnter', function(e) {
                var ets = localStorage.getItem('withu_enter_to_send') === 'true';
                if (ets && e.key === 'Enter' && !e.shiftKey && !e.ctrlKey && !e.metaKey) {
                    e.preventDefault();
                    self._handleSendReply();
                } else if (!ets && (e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    e.preventDefault();
                    self._handleSendReply();
                }
            });
        },

        _checkAutoAuth: function() {
            var cachedQQ = localStorage.getItem('withu_comment_qq');
            var cachedName = localStorage.getItem('withu_comment_anon_name') || '';
            var confirmed = !!localStorage.getItem('withu_comment_auth_confirmed');

            // 清理旧元素
            $('#withumsgDrawerQQHint').remove();
            $('#withumsgDrawerQQSub').remove();

            if (confirmed) {
                // 已确认身份 → 填充完整身份信息
                if (cachedQQ && cachedQQ.length >= 5 && cachedName) {
                    var avatarUrl = localStorage.getItem('withu_comment_avatar') || _defaultAvatar;
                    var maskedQQ = cachedQQ.slice(0, 3) + '**' + cachedQQ.slice(-2);
                    $('#withumsgDrawerIdentityAvatar').attr('src', avatarUrl);
                    $('#withumsgDrawerIdentityName').text(cachedName).css('color', 'var(--withumsg-text-main)');
                    var $qqSub = $('<small id="withumsgDrawerQQSub" class="withu-message-drawer-qq-sub"></small>');
                    $('#withumsgDrawerIdentityName').after($qqSub);
                    $qqSub.text(maskedQQ).show();
                } else if (cachedName) {
                    $('#withumsgDrawerIdentityName').text(cachedName).css('color', 'var(--withumsg-text-main)');
                }
            } else if (cachedQQ && cachedQQ.length >= 5) {
                // 未确认但有缓存 QQ（来自一级留言弹窗）→ 显示可点击的快捷身份徽章
                var hintAvatar = localStorage.getItem('withu_comment_avatar') || _defaultAvatar;
                var hintMasked = cachedQQ.slice(0, 3) + '**' + cachedQQ.slice(-2);
                var $hint = $('<div id="withumsgDrawerQQHint" style="display:flex;align-items:center;gap:4px;padding:3px 8px 3px 3px;border-radius:16px;background:var(--withumsg-bg-secondary,#f2f2f7);cursor:pointer;flex-shrink:0;font-size:12px;color:var(--withumsg-text-muted,#8e8e93);white-space:nowrap;transition:opacity .3s;">' +
                    '<img src="' + escapeHtml(hintAvatar) + '" style="width:20px;height:20px;border-radius:50%;object-fit:cover;flex-shrink:0;" onerror="this.src=\'' + escapeHtml(_defaultAvatar) + '\'">' +
                    '<span>' + escapeHtml(hintMasked) + '</span>' +
                '</div>');
                $('#withumsgDrawerCollapseBtn').before($hint);
                $hint.on('click', function(e) {
                    e.stopPropagation();
                    if (cachedName) {
                        // QQ + 昵称都有 → 直接确认身份
                        localStorage.setItem('withu_comment_auth_confirmed', '1');
                        Drawer._checkAutoAuth();
                        if (typeof Toastify !== 'undefined') Toastify.showScenario('success', { text: '身份已确认' });
                    } else {
                        // 有 QQ 但缺昵称 → 打开 AuthModal（QQ 会自动填入）
                        AuthModal.open();
                    }
                });
            }
        },

        _expandFooter: function() {
            var $footer = $('#withumsgDrawerFooter');
            if ($footer.hasClass('is-expanded')) return;
            $footer.addClass('is-expanded');
            setTimeout(function() {
                var editor = document.getElementById('withumsgDrawerEditor');
                if (editor) editor.focus();
                var scroller = document.getElementById('withumsgDrawerScroll');
                if (scroller) scroller.scrollTo({ top: scroller.scrollHeight, behavior: 'smooth' });
            }, 50);
        },

        _collapseFooter: function(force) {
            var editor = document.getElementById('withumsgDrawerEditor');
            var $footer = $('#withumsgDrawerFooter');
            if (force || (editor && !editor.textContent.trim() && !editor.querySelector('img.emoji') && !editor.querySelector('blockquote'))) {
                $footer.removeClass('is-expanded');
                EmojiPanel.hide();
            }
        },

        _updateCharCounter: function() {
            var editor = document.getElementById('withumsgDrawerEditor');
            var $counter = $('#withumsgDrawerCharCounter');
            if (!editor || !$counter.length) return;
            var len = (editor.textContent || '').length + (editor.querySelectorAll('img.emoji') || []).length;
            var max = 500;
            $counter.text(len + '/' + max);
            $counter.removeClass('warning danger');
            if (len > max * 0.8) $counter.addClass('warning');
            if (len > max) $counter.addClass('danger');
        },

        open: function(parentId) {
            this._parentId = parentId;
            this._clearReplyTo();
            this._isOpen = true;

            var $overlay = $('#withumsgDrawer');
            $overlay.removeClass('closing').addClass('active');
            lockBodyScroll();

            // 副标题重置为骨架屏 loading
            var subtitle = document.getElementById('withumsgDrawerSubtitle');
            if (subtitle) {
                subtitle.style.opacity = '1';
                subtitle.innerHTML = '<span style="display:inline-block;width:120px;height:12px;border-radius:6px;background:linear-gradient(90deg,#d1d1d6 25%,#e5e5ea 50%,#d1d1d6 75%);background-size:200% 100%;animation:shimmer 1.2s ease-in-out infinite;vertical-align:middle;"></span>';
            }

            // 收起底部输入区
            this._collapseFooter(true);

            this._loadReplies(parentId);
            this._populateVisitorTags();
        },

        close: function() {
            if (!this._isOpen) return;
            this._isOpen = false;
            var $overlay = $('#withumsgDrawer');
            $overlay.addClass('closing');

            setTimeout(function() {
                $overlay.removeClass('active closing');
                unlockBodyScroll();
            }, 350);

            EmojiPanel.hide();
        },

        _populateVisitorTags: function() {
            var $tags = $('#withumsgDrawerVisitorTags');
            if (!$tags.length) return;
            VisitorDetect.detect();
            var os = VisitorDetect.os || '--';
            var browser = VisitorDetect.browser || '--';
            var loc = VisitorDetect.location || '--';

            $tags.html(
                '<div class="withu-message-v-tag">' +
                    '<div class="withu-message-v-tag-icon"><i data-lucide="monitor"></i></div>' +
                    '<span id="withumsgTagOS">' + escapeHtml(os) + '</span>' +
                '</div>' +
                '<div class="withu-message-v-tag">' +
                    '<div class="withu-message-v-tag-icon"><i data-lucide="globe"></i></div>' +
                    '<span id="withumsgTagBrowser">' + escapeHtml(browser) + '</span>' +
                '</div>' +
                '<div class="withu-message-v-tag">' +
                    '<div class="withu-message-v-tag-icon"><i data-lucide="map-pin"></i></div>' +
                    '<span id="withumsgTagLocation">' + escapeHtml(loc) + '</span>' +
                '</div>' +
                '<div class="withu-message-v-tag">' +
                    '<div class="withu-message-v-tag-icon withu-message-icon-weather"><i class="qi-100-fill" id="withumsgWeatherIcon"></i></div>' +
                    '<span id="withumsgTagWeather">--</span>' +
                '</div>'
            );
            if (typeof lucide !== 'undefined') lucide.createIcons();
            // 异步获取天气
            VisitorDetect._fetchWeather();
        },

        _loadReplies: function(parentId) {
            var body = document.getElementById('withumsgDrawerBody');
            if (!body) return;

            // 居中 spinner + 文案（与 demo 一致）
            body.innerHTML =
                '<div class="withu-message-drawer-loading">' +
                    '<svg class="withu-message-lucide-loader" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                        '<line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line>' +
                        '<line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line>' +
                        '<line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line>' +
                        '<line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line>' +
                    '</svg>' +
                    '<span>数据加载中...</span>' +
                '</div>';

            var self = this;
            var skeletonStart = Date.now();
            $.ajax({
                url: endpoints.messageList || 'services/message-list.php',
                type: 'GET',
                dataType: 'json',
                data: { action: 'replies', parent_id: parentId },
                success: function(res) {
                    var elapsed = Date.now() - skeletonStart;
                    var minDelay = Math.max(0, 400 - elapsed);
                    setTimeout(function() {
                        if (res.code === 200 && res.data) {
                            self._parentMsg = res.data.parent;
                            self._renderDrawerContent(res.data.parent, res.data.replies || []);
                            updateLazyLoad();
                        } else {
                            body.innerHTML = '<div class="withu-message-drawer-empty">加载失败</div>';
                        }
                    }, minDelay);
                },
                error: function() {
                    var elapsed = Date.now() - skeletonStart;
                    var minDelay = Math.max(0, 400 - elapsed);
                    setTimeout(function() {
                        body.innerHTML = '<div class="withu-message-drawer-empty">网络错误，请稍后重试</div>';
                    }, minDelay);
                }
            });
        },

        _buildMsgMeta: function(msg) {
            var metaHtml = '';
            // OS
            var os = msg.os || '';
            if (os) {
                metaHtml += '<span><i data-lucide="monitor" class="withumsg-ico-sm"></i>' + escapeHtml(os) + '</span>';
            }
            // 浏览器
            var browser = msg.browser || '';
            if (browser) {
                metaHtml += '<span><i data-lucide="globe" class="withumsg-ico-sm"></i>' + escapeHtml(browser) + '</span>';
            }
            // 城市（有坐标时可点击打开地图）
            var city = msg.city || '';
            if (city) {
                var hasCoords = msg.lng && msg.lat && isFinite(msg.lng) && isFinite(msg.lat);
                if (hasCoords) {
                    metaHtml += '<span class="withumsg-location-link withumsg-location-has-coords" data-lng="' + msg.lng + '" data-lat="' + msg.lat + '" data-marker-id="' + msg.id + '"><i data-lucide="map-pin" class="withumsg-ico-sm"></i>' + escapeHtml(city) + '</span>';
                } else {
                    metaHtml += '<span><i data-lucide="map-pin" class="withumsg-ico-sm"></i>' + escapeHtml(city) + '</span>';
                }
            }
            // 天气
            var weather = msg.weather || '';
            if (weather) {
                var wiCode = msg.weather_icon || '';
                var wiClass = wiCode ? 'qi-' + wiCode + '-fill' : 'qi-999-fill';
                metaHtml += '<span><i class="' + wiClass + ' withumsg-ico-sm"></i>' + escapeHtml(weather) + '</span>';
            }
            return metaHtml;
        },

        _renderMsgItem: function(msg, opts) {
            opts = opts || {};
            var avatarUrl = msg.avatar || _defaultAvatar;
            var msgDisplayName = displayName(msg.name, msg.qq);
            var isMe = opts.isMe || false;
            var popIn = opts.popIn || false;
            var delay = opts.delay || 0;

            var cls = 'withu-message-msg-item';
            if (isMe) cls += ' is-me';
            if (popIn) cls += ' withu-message-msg-pop-in';
            var styleAttr = delay ? ' style="animation-delay:' + delay + 'ms;"' : '';

            var metaHtml = this._buildMsgMeta(msg);

            var overlayHtml = isMe ? '' : '<div class="withu-message-avatar-overlay"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"></circle><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4.5 8.5"></path></svg></div>';
            var avatarCursor = isMe ? '' : ' style="cursor:pointer;"';

            // 处理引用回复：基于 reply_to_id 生成 blockquote 引用块
            var bubbleContent = msg.textHtml || escapeHtml(msg.text);
            bubbleContent = this._buildReplyQuote(msg) + bubbleContent;

            // 点赞按钮（二级评论也支持）- 始终渲染数量元素
            var likeCount = msg.like_count || 0;
            var likeHtml = '<button class="withu-message-like-btn" data-like-target="message" data-like-id="' + msg.id + '" title="点赞">' +
                '<i class="ph-fill ph-heart"></i>' +
            '</button><span class="withu-message-like-count">' + likeCount + '</span>';

            return '<div class="' + cls + '" id="withumsg-' + msg.id + '" data-reply-id="' + msg.id + '"' + styleAttr + '>' +
                '<div class="withu-message-avatar-wrap"' + avatarCursor + '>' +
                    '<img class="withu-message-msg-avatar" src="' + escapeHtml(avatarUrl) + '" alt="' + escapeHtml(msgDisplayName) + '" loading="lazy" decoding="async">' +
                    overlayHtml +
                '</div>' +
                '<div class="withu-message-msg-main">' +
                    '<div class="withu-message-msg-info">' +
                        '<span class="withu-message-msg-name"' + avatarCursor + ' data-tooltip="' + escapeHtml(msgDisplayName) + '">' + escapeHtml(msgDisplayName) + '</span>' +
                        renderBadge(msg.badge) +
                    '</div>' +
                    '<div class="withu-message-msg-bubble">' + bubbleContent + '<div class="withu-message-like-wrap">' + likeHtml + '</div></div>' +
                    (metaHtml ? '<div class="withu-message-msg-meta">' + metaHtml + '</div>' : '') +
                '</div>' +
            '</div>';
        },

        /**
         * 根据 msg.reply_to_id / replyToName / replyToText 生成 blockquote 引用块
         * 如果没有 reply_to_id 则返回空字符串
         */
        _buildReplyQuote: function(msg) {
            if (!msg.reply_to_id || !msg.replyToName) return '';
            // 优先使用带表情的 replyToHtml，回退到纯文本
            var snippet = msg.replyToHtml || (msg.replyToText ? escapeHtml(msg.replyToText) : '');
            return '<blockquote class="withumsg-reply-quote" data-target="' + msg.reply_to_id + '">' +
                '<span class="withumsg-reply-quote-name">回复 @' + escapeHtml(msg.replyToName) + '</span>' +
                (snippet ? '<span class="withumsg-reply-quote-text">' + snippet + '</span>' : '') +
            '</blockquote>';
        },

        _renderDrawerContent: function(parent, replies) {
            var body = document.getElementById('withumsgDrawerBody');
            if (!body) return;
            var self = this;
            var html = '';
            var myQQHash = localStorage.getItem('withu_comment_qq_hash') || '';

            // 更新副标题回复数（骨架屏 → 渐显文字）
            var subtitle = document.getElementById('withumsgDrawerSubtitle');
            if (subtitle) {
                subtitle.style.transition = 'opacity 0.3s ease';
                subtitle.style.opacity = '0';
                setTimeout(function() {
                    subtitle.textContent = replies.length > 0
                        ? '共 ' + replies.length + ' 条回复 · 一起聊聊'
                        : '还没有回复，来说点什么吧';
                    subtitle.style.opacity = '1';
                }, 300);
            }

            // 父留言（带时间）
            var parentTimeLabel = formatMsgTime(parent.timestamp, true);
            if (parentTimeLabel) {
                html += '<div class="withu-message-time-divider">' + escapeHtml(parentTimeLabel) + '</div>';
            }
            html += self._renderMsgItem(parent, { isMe: false, popIn: false });

            // 分隔线
            html += '<div class="withu-message-drawer-divider"></div>';

            if (replies.length === 0) {
                html += '<div class="withu-message-drawer-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg><div>还没有人回复这条留言，在下方说点什么吧</div></div>';
            } else {
                var prevTs = parent.timestamp;
                for (var i = 0; i < replies.length; i++) {
                    var r = replies[i];
                    var isGroupFirst = !isSameDay(prevTs, r.timestamp);
                    var timeLabel = formatMsgTime(r.timestamp, isGroupFirst);
                    if (timeLabel) {
                        html += '<div class="withu-message-time-divider">' + escapeHtml(timeLabel) + '</div>';
                    }
                    var isMe = myQQHash && r.qq_hash === myQQHash;
                    html += self._renderMsgItem(r, { isMe: isMe, popIn: true, delay: i * 50 });
                    prevTs = r.timestamp;
                }
            }

            body.innerHTML = html;
            if (typeof lucide !== 'undefined') lucide.createIcons();

            // 查询点赞状态（高亮已点赞的）
            if (typeof WithUInteraction !== 'undefined' && WithUInteraction._loadStatuses) {
                WithUInteraction._loadStatuses();
            }

            // 滚动到顶部
            var scroll = document.getElementById('withumsgDrawerScroll');
            if (scroll) {
                setTimeout(function() { scroll.scrollTop = 0; }, 50);
            }
        },

        _setReplyTo: function(id, name) {
            this._replyToId = id;
            this._replyToName = name;
            // 在编辑器里插入 @ 标签
            var editor = document.getElementById('withumsgDrawerEditor');
            if (editor) {
                editor.focus();
                var placeholder = editor.getAttribute('data-placeholder');
                editor.setAttribute('data-placeholder', '回复 ' + name + '...');
            }
            this._expandFooter();
        },

        _handleContextAction: function(action) {
            var $menu = $('#withumsgContextMenu');
            $menu.removeClass('active');

            var $item = this._activeCtxItem;
            var $bubble = this._activeCtxBubble;
            if (!$item || !$bubble) return;

            var self = this;
            var msgId = $item.attr('id') ? $item.attr('id').replace('withumsg-', '') : '';
            var replyName = $item.find('.withu-message-msg-name').first().text() || '匿名';

            if (action === 'like') {
                // 点赞功能 - 触发消息气泡旁的点赞按钮
                var likeBtn = $item.find('.withu-message-like-btn')[0];
                if (likeBtn && typeof WithUInteraction !== 'undefined') {
                    WithUInteraction._handleLikeClick(likeBtn);
                }
            } else if (action === 'copy') {
                // 提取纯文本（emoji → [表情]，@标签保留文字）
                var clone = $bubble[0].cloneNode(true);
                var bq = clone.querySelector('blockquote');
                if (bq) bq.remove();
                var plainText = '';
                clone.childNodes.forEach(function(child) {
                    if (child.nodeType === 3) { plainText += child.textContent; }
                    else if (child.nodeType === 1) {
                        if (child.tagName === 'IMG' && child.classList.contains('emoji')) {
                            plainText += child.getAttribute('data-value') || child.getAttribute('data-emoji') || '[表情]';
                        } else if (child.tagName === 'BR') { plainText += '\n'; }
                        else { plainText += child.innerText || child.textContent; }
                    }
                });
                plainText = plainText.trim();
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(plainText).then(function() {
                        if (typeof Toastify !== 'undefined') Toastify.showScenario('success', { text: '已复制' });
                    }).catch(function() {
                        if (typeof Toastify !== 'undefined') Toastify.showScenario('error', { text: '复制失败' });
                    });
                } else {
                    var ta = document.createElement('textarea');
                    ta.value = plainText;
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                    if (typeof Toastify !== 'undefined') Toastify.showScenario('success', { text: '已复制' });
                }
            } else if (action === 'quote') {
                // 设置引用回复ID（关键！用于 reply_to_id 字段）
                self._replyToId = msgId;
                self._replyToName = replyName;

                self._expandFooter();
                var editor = document.getElementById('withumsgDrawerEditor');
                if (editor) {
                    // 提取引用富文本（递归保留表情图片）
                    var clone2 = $bubble[0].cloneNode(true);
                    var existBq = clone2.querySelector('blockquote');
                    if (existBq) existBq.remove();
                    function extractQuoteHtml(el) {
                        var out = '';
                        el.childNodes.forEach(function(child) {
                            if (child.nodeType === 3) { out += child.textContent; }
                            else if (child.nodeType === 1) {
                                if (child.tagName === 'IMG') {
                                    // 兼容 lazy（data-src）和 emoji（src）两种格式
                                    var imgSrc = child.getAttribute('src') || child.getAttribute('data-src') || '';
                                    if (imgSrc) {
                                        out += '<img class="emoji" src="' + escapeHtml(imgSrc) + '" style="height:18px;width:18px;vertical-align:text-bottom;margin:0 1px;">';
                                    }
                                } else if (child.classList && (child.classList.contains('withu-message-at-tag') || child.classList.contains('withu-msg-at'))) {
                                    out += child.outerHTML;
                                } else if (child.tagName === 'BR') { out += ' '; }
                                else { out += extractQuoteHtml(child); }
                            }
                        });
                        return out;
                    }
                    var quoteHtml = extractQuoteHtml(clone2);

                    // 移除已有 blockquote
                    var oldBq = editor.querySelector('blockquote');
                    if (oldBq) oldBq.remove();

                    var bq = document.createElement('blockquote');
                    bq.contentEditable = 'false';
                    bq.setAttribute('data-target', msgId);
                    bq.className = 'withumsg-editor-quote';
                    bq.innerHTML = '<span class="withumsg-editor-quote-label">回复 @' + escapeHtml(replyName) + ':</span> ' + quoteHtml +
                        '<span class="withumsg-editor-quote-close" onclick="(function(el){var d=el.closest(\'.withumsg-editor-quote\');if(d)d.remove();var D=window._LGDrawerRef;if(D){D._replyToId=null;D._replyToName=null;}})( this)">&times;</span>';

                    // 重建编辑器内容：blockquote + 输入区
                    // 保留已有的非blockquote内容
                    var existingContent = '';
                    var oldNodes = [];
                    for (var ci = 0; ci < editor.childNodes.length; ci++) {
                        var cn = editor.childNodes[ci];
                        if (cn.tagName === 'BLOCKQUOTE') continue;
                        oldNodes.push(cn);
                    }
                    editor.innerHTML = '';
                    editor.appendChild(bq);

                    // 创建输入div隔离blockquote
                    var inputDiv = document.createElement('div');
                    inputDiv.className = 'withumsg-editor-input';
                    if (oldNodes.length > 0) {
                        oldNodes.forEach(function(n) { inputDiv.appendChild(n); });
                    } else {
                        inputDiv.innerHTML = '<br>';
                    }
                    editor.appendChild(inputDiv);

                    // 光标放到输入区
                    editor.focus();
                    var sel = window.getSelection();
                    var range = document.createRange();
                    range.setStart(inputDiv, 0);
                    range.collapse(true);
                    sel.removeAllRanges();
                    sel.addRange(range);
                }
            } else if (action === 'plusone') {
                // +1：将气泡富文本内容（emoji + @标签）填充到编辑器
                self._expandFooter();
                var editor = document.getElementById('withumsgDrawerEditor');
                if (!editor) return;
                var target = editor.querySelector('.withumsg-editor-input') || editor;
                if (target.innerHTML === '<br>' || target.innerHTML === '<div><br></div>') target.innerHTML = '';
                target.focus();

                var clone3 = $bubble[0].cloneNode(true);
                var bq3 = clone3.querySelector('blockquote');
                if (bq3) bq3.remove();

                // 递归遍历气泡内容，逐节点插入编辑器
                function insertBubbleNodes(container) {
                    container.childNodes.forEach(function(child) {
                        if (child.nodeType === 3) {
                            // 文本节点
                            if (child.textContent) document.execCommand('insertText', false, child.textContent);
                        } else if (child.nodeType === 1) {
                            if (child.tagName === 'IMG') {
                                // emoji 图片 → 编辑器格式
                                var emojiCode = child.getAttribute('data-emoji') || child.getAttribute('data-value') || '';
                                var imgSrc = child.getAttribute('src') || child.getAttribute('data-src') || '';
                                if (emojiCode && imgSrc) {
                                    var eImg = document.createElement('img');
                                    eImg.src = imgSrc;
                                    eImg.alt = emojiCode;
                                    eImg.title = emojiCode;
                                    eImg.className = 'emoji';
                                    eImg.setAttribute('data-emoji', emojiCode);
                                    eImg.style.cssText = 'height:20px;width:20px;vertical-align:text-bottom;margin:0 1px;';
                                    var sel = window.getSelection();
                                    if (sel.rangeCount > 0) {
                                        var r = sel.getRangeAt(0);
                                        r.deleteContents();
                                        r.insertNode(eImg);
                                        r.setStartAfter(eImg);
                                        r.collapse(true);
                                        sel.removeAllRanges();
                                        sel.addRange(r);
                                    }
                                } else if (emojiCode) {
                                    document.execCommand('insertText', false, emojiCode);
                                }
                            } else if (child.classList && (child.classList.contains('withu-msg-at') || child.classList.contains('withu-message-at-tag'))) {
                                // @标签 → 编辑器 at-tag 格式
                                var atId = child.getAttribute('data-id') || child.getAttribute('data-target') || '';
                                var atText = child.textContent || '';
                                var atSpan = document.createElement('span');
                                atSpan.className = 'withu-message-at-tag';
                                atSpan.contentEditable = 'false';
                                atSpan.setAttribute('data-target', atId);
                                atSpan.textContent = atText;
                                var sel = window.getSelection();
                                if (sel.rangeCount > 0) {
                                    var r = sel.getRangeAt(0);
                                    r.deleteContents();
                                    r.insertNode(atSpan);
                                    var sp = document.createTextNode(' ');
                                    r.setStartAfter(atSpan);
                                    r.insertNode(sp);
                                    r.setStartAfter(sp);
                                    r.collapse(true);
                                    sel.removeAllRanges();
                                    sel.addRange(r);
                                }
                            } else if (child.tagName === 'BR') {
                                document.execCommand('insertLineBreak');
                            } else {
                                insertBubbleNodes(child);
                            }
                        }
                    });
                }
                insertBubbleNodes(clone3);
                if (typeof Toastify !== 'undefined') Toastify.showScenario('success', { text: '+1 已填入' });
            }

            // 高亮气泡
            $bubble.removeClass('withu-message-bubble-highlight');
            void $bubble[0].offsetWidth;
            $bubble.addClass('withu-message-bubble-highlight');
        },

        _clearReplyTo: function() {
            this._replyToId = null;
            this._replyToName = null;
            var editor = document.getElementById('withumsgDrawerEditor');
            if (editor) {
                editor.setAttribute('data-placeholder', '友善的留言是交流的起点...');
                // 移除编辑器内的引用 blockquote
                var bq = editor.querySelector('.withumsg-editor-quote');
                if (bq) bq.remove();
            }
        },

        _getEditorText: function() {
            var editor = document.getElementById('withumsgDrawerEditor');
            if (!editor) return '';

            function extractNodes(parent) {
                var result = '';
                var nodes = parent.childNodes;
                for (var i = 0; i < nodes.length; i++) {
                    var node = nodes[i];
                    if (node.nodeType === 3) {
                        result += node.textContent;
                    } else if (node.nodeType === 1) {
                        if (node.tagName === 'BLOCKQUOTE') continue;
                        if (node.tagName === 'IMG' && node.classList.contains('emoji')) {
                            result += node.getAttribute('data-emoji') || node.getAttribute('data-value') || '';
                        } else if (node.classList && node.classList.contains('withu-message-at-tag')) {
                            var atTarget = node.getAttribute('data-target') || '';
                            var atName = (node.textContent || '').replace(/^@/, '');
                            result += '@[' + atName + '#' + atTarget + ']';
                        } else if (node.tagName === 'BR') {
                            result += '\n';
                        } else if (node.tagName === 'DIV') {
                            var inner = extractNodes(node);
                            if (inner) result += '\n' + inner;
                        } else {
                            result += extractNodes(node);
                        }
                    }
                }
                return result;
            }

            return extractNodes(editor).trim();
        },

        _clearEditor: function() {
            var editor = document.getElementById('withumsgDrawerEditor');
            if (editor) editor.innerHTML = '';
            this._updateCharCounter();
        },

        _handleSendReply: function() {
            var text = this._getEditorText();
            if (!text) {
                if (typeof Toastify !== 'undefined') Toastify.showScenario('warning', { text: '请输入回复内容' });
                return;
            }

            if (containsBannedChar(text)) {
                if (typeof Toastify !== 'undefined') Toastify.showScenario('warning', { text: '回复包含违禁内容，请修改后重试' });
                return;
            }

            // 未确认身份时，强制弹出身份选择弹窗
            if (!localStorage.getItem('withu_comment_auth_confirmed')) {
                if (typeof Toastify !== 'undefined') Toastify.showScenario('warning', { text: '请先设置身份后再回复' });
                AuthModal.open();
                return;
            }

            var self = this;
            var parentId = this._parentId;
            var geetestAvailable = typeof GeetestHelper !== 'undefined' && GeetestHelper.ready();

            if (geetestAvailable) {
                var $sendBtn = $('#withumsgReplySendBtn');
                $sendBtn.prop('disabled', true).html('<i data-lucide="loader" class="withu-message-lucide-loader" style="width:16px;height:16px;"></i>');
                if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [$sendBtn[0]] });
                // 存储待提交数据，供 submitMessage 全局回调使用
                self._pendingReply = { text: text, parentId: parentId };
                // 临时包装 onClose 以重置按钮状态
                var _cfg = GeetestHelper.getConfig();
                var _origClose = _cfg.onClose;
                _cfg.onClose = function() {
                    if (self._pendingReply) {
                        self._pendingReply = null;
                        $sendBtn.prop('disabled', false).html('<i data-lucide="send" style="width:16px;height:16px;"></i>');
                        if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [$sendBtn[0]] });
                    }
                    _cfg.onClose = _origClose;
                    if (typeof _origClose === 'function') _origClose();
                };
                GeetestHelper.show();
            } else {
                // 极验未开启，直接提交
                this._submitReply(text, parentId, {});
            }
        },

        _submitReply: function(text, parentId, geetestResult) {
            var self = this;
            var $btn = $('#withumsgReplySendBtn');
            $btn.prop('disabled', true).html('<i data-lucide="loader" class="withu-message-lucide-loader" style="width:16px;height:16px;"></i>');
            if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [$btn[0]] });

            var qq = localStorage.getItem('withu_comment_qq') || 'anon';
            var name = localStorage.getItem('withu_comment_anon_name') || '匿名';

            var postData = {
                qq: qq,
                name: name,
                text: text,
                parent_id: parentId
            };
            // 引用回复ID
            if (self._replyToId) postData.reply_to_id = self._replyToId;
            if (VisitorDetect.weather) postData.weather = VisitorDetect.weather;
            if (VisitorDetect.weatherIcon) postData.weather_icon = VisitorDetect.weatherIcon;

            if (geetestResult && geetestResult.lot_number) {
                postData.lot_number = geetestResult.lot_number;
                postData.captcha_output = geetestResult.captcha_output;
                postData.pass_token = geetestResult.pass_token;
                postData.gen_time = geetestResult.gen_time;
            }

            $.ajax({
                url: endpoints.messageSubmit || 'services/message.php',
                type: 'POST',
                dataType: 'json',
                data: postData,
                success: function(res) {
                    if (res.Status) {
                        if (typeof Toastify !== 'undefined') Toastify.showScenario('success', { text: res.message || '回复成功' });

                        // 即时插入回复到 drawer（含待审核标识）
                        var body = document.getElementById('withumsgDrawerBody');
                        var drawerEditor = document.getElementById('withumsgDrawerEditor');
                        if (body) {
                            // 移除空状态提示
                            var emptyEl = body.querySelector('.withu-message-drawer-empty');
                            if (emptyEl) emptyEl.remove();

                            var myQQ = localStorage.getItem('withu_comment_qq') || 'anon';
                            var myName = localStorage.getItem('withu_comment_anon_name') || '匿名';
                            var myAvatar = localStorage.getItem('withu_comment_avatar') || getAvatarUrl(myQQ);
                            var contentStr = drawerEditor ? drawerEditor.innerHTML : '';

                            var metaHtml = self._buildMsgMeta({
                                os: VisitorDetect.os || '',
                                browser: VisitorDetect.browser || '',
                                city: VisitorDetect.location || '',
                                weather: VisitorDetect.weather || '',
                                weather_icon: VisitorDetect.weatherIcon || ''
                            });

                            // 处理引用回复（基于 _replyToId / _replyToName）
                            var quoteHtml = '';
                            if (self._replyToId && self._replyToName) {
                                // 查找被引用消息的内容（带表情HTML）
                                var refBubble = document.querySelector('#withumsg-' + self._replyToId + ' .withu-message-msg-bubble');
                                var refHtml = '';
                                if (refBubble) {
                                    var refClone = refBubble.cloneNode(true);
                                    var refBq = refClone.querySelector('blockquote');
                                    if (refBq) refBq.remove();
                                    refHtml = refClone.innerHTML.substring(0, 200);
                                }
                                quoteHtml = self._buildReplyQuote({
                                    reply_to_id: self._replyToId,
                                    replyToName: self._replyToName,
                                    replyToHtml: refHtml
                                });
                            }
                            contentStr = quoteHtml + contentStr;

                            // 插入时间标签
                            var nowTs = Math.floor(Date.now() / 1000);
                            var timeLabel = formatMsgTime(nowTs, true);
                            if (timeLabel) {
                                var timeDivider = document.createElement('div');
                                timeDivider.className = 'withu-message-time-divider';
                                timeDivider.textContent = timeLabel;
                                body.appendChild(timeDivider);
                            }

                            var newMsgId = 'local-' + Date.now();
                            var isPendingReply = !!res.pending;
                            var item = document.createElement('div');
                            item.className = 'withu-message-msg-item is-me withu-message-msg-pop-in' + (isPendingReply ? ' is-pending' : '');
                            item.id = 'withumsg-' + newMsgId;
                            item.innerHTML =
                                '<div class="withu-message-avatar-wrap">' +
                                    '<img class="withu-message-msg-avatar" src="' + escapeHtml(myAvatar) + '" loading="lazy" decoding="async">' +
                                '</div>' +
                                '<div class="withu-message-msg-main">' +
                                    '<div class="withu-message-msg-info">' +
                                        '<span class="withu-message-msg-name">' + escapeHtml(myName) + '</span>' +
                                        (isPendingReply ? renderPendingBadge() : '') +
                                    '</div>' +
                                    '<div class="withu-message-msg-bubble">' + contentStr + '</div>' +
                                    (metaHtml ? '<div class="withu-message-msg-meta">' + metaHtml + '</div>' : '') +
                                '</div>';
                            body.appendChild(item);
                            if (typeof lucide !== 'undefined') lucide.createIcons();

                            // 滚动到底部
                            var scroller = document.getElementById('withumsgDrawerScroll');
                            if (scroller) setTimeout(function() { scroller.scrollTop = scroller.scrollHeight; }, 50);

                            // 小礼花（匹配demo）
                            if (typeof confetti === 'function') {
                                confetti({ particleCount: 50, spread: 60, origin: { y: 0.8 }, colors: ['#007AFF', '#34C759', '#5856D6'], zIndex: 100003 });
                            }
                        }

                        self._clearEditor();
                        self._clearReplyTo();
                        EmojiPanel.hide();

                        // 更新主列表中卡片的回复数
                        var $card = $('.MessageCard[data-msg-id="' + parentId + '"]');
                        var $badge = $card.find('.withumsg-reply-badge');
                        var oldCount = parseInt($badge.text()) || 0;
                        $badge.html('<i data-lucide="message-square" class="withumsg-ico"></i>' + (oldCount + 1) + ' 条回复');
                        if (typeof lucide !== 'undefined') lucide.createIcons();
                    } else if (res.need_dev_password) {
                        $btn.prop('disabled', false).html('<i data-lucide="send" style="width:16px;height:16px;"></i>');
                        if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [$btn[0]] });
                        _promptDevPassword(function(pw, showSuccess, showError) {
                            delete postData.lot_number; delete postData.captcha_output; delete postData.pass_token; delete postData.gen_time;
                            postData.dev_password = pw;
                            // 在弹窗关闭前保存编辑器内容，用于插入气泡
                            var drawerEditorDev = document.getElementById('withumsgDrawerEditor');
                            var devContentStr = drawerEditorDev ? drawerEditorDev.innerHTML : '';
                            var devReplyToId = self._replyToId;
                            var devReplyToName = self._replyToName;
                            $.ajax({
                                url: endpoints.messageSubmit || 'services/message.php',
                                type: 'POST', dataType: 'json', data: postData,
                                success: function(r2) {
                                    if (r2.Status) {
                                        showSuccess(function() {
                                            if (typeof Toastify !== 'undefined') Toastify.showScenario('success', { text: r2.message || '回复成功' });
                                            // 插入气泡到抽屉
                                            var body = document.getElementById('withumsgDrawerBody');
                                            if (body) {
                                                var emptyEl = body.querySelector('.withu-message-drawer-empty');
                                                if (emptyEl) emptyEl.remove();
                                                var myQQ = localStorage.getItem('withu_comment_qq') || 'anon';
                                                var myName = localStorage.getItem('withu_comment_anon_name') || '匿名';
                                                var myAvatar = localStorage.getItem('withu_comment_avatar') || getAvatarUrl(myQQ);
                                                var quoteHtml2 = '';
                                                if (devReplyToId && devReplyToName) {
                                                    var refB = document.querySelector('#withumsg-' + devReplyToId + ' .withu-message-msg-bubble');
                                                    var refH = refB ? refB.cloneNode(true).innerHTML.substring(0, 200) : '';
                                                    quoteHtml2 = self._buildReplyQuote({ reply_to_id: devReplyToId, replyToName: devReplyToName, replyToHtml: refH });
                                                }
                                                var finalContent = quoteHtml2 + devContentStr;
                                                var devPending = !!r2.pending;
                                                var devItem = document.createElement('div');
                                                devItem.className = 'withu-message-msg-item is-me withu-message-msg-pop-in' + (devPending ? ' is-pending' : '');
                                                devItem.id = 'withumsg-local-' + Date.now();
                                                devItem.innerHTML =
                                                    '<div class="withu-message-avatar-wrap"><img class="withu-message-msg-avatar" src="' + escapeHtml(myAvatar) + '" loading="lazy" decoding="async"></div>' +
                                                    '<div class="withu-message-msg-main"><div class="withu-message-msg-info"><span class="withu-message-msg-name">' + escapeHtml(myName) + '</span>' + (devPending ? renderPendingBadge() : '') + '</div>' +
                                                    '<div class="withu-message-msg-bubble">' + finalContent + '</div></div>';
                                                body.appendChild(devItem);
                                                if (typeof lucide !== 'undefined') lucide.createIcons();
                                                var scroller = document.getElementById('withumsgDrawerScroll');
                                                if (scroller) setTimeout(function() { scroller.scrollTop = scroller.scrollHeight; }, 50);
                                            }
                                            self._clearEditor(); self._clearReplyTo(); EmojiPanel.hide();
                                        });
                                    } else {
                                        showError(r2.message || '开发者密码错误');
                                    }
                                },
                                error: function() { showError('网络错误'); },
                                complete: function() {
                                    $btn.prop('disabled', false).html('<i data-lucide="send" style="width:16px;height:16px;"></i>');
                                    if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [$btn[0]] });
                                }
                            });
                        });
                    } else {
                        if (typeof GeetestHelper !== 'undefined') GeetestHelper.reset();
                        if (typeof Toastify !== 'undefined') Toastify.showScenario('error', { text: res.message || '回复失败' });
                    }
                },
                error: function() {
                    if (typeof GeetestHelper !== 'undefined') GeetestHelper.reset();
                    if (typeof Toastify !== 'undefined') Toastify.showScenario('error', { text: '网络错误，请稍后重试' });
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<i data-lucide="send" style="width:16px;height:16px;"></i>');
                    if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [$btn[0]] });
                    // 恢复全局极验实例（被 reinit 覆盖后需要还原给 footer 的一级留言提交）
                    if (typeof window.initLeavingGeetest === 'function') {
                        window.initLeavingGeetest();
                    }
                }
            });
        }
    };

    // ============================================
    // 表情面板模块（抽屉内使用）
    // ============================================
    var EmojiPanel = {
        _data: null,
        _keys: null,
        _loaded: false,
        _activeCategory: 0,
        _visible: false,
        _savedRange: null,
        _targetEditor: 'withumsgDrawerEditor',
        _shortcodeMap: null,

        init: function() {
            this._bindEvents();
            // 预加载表情数据，让快速插入气泡在弹窗打开时立即显示
            this._loadData();
        },

        destroy: function() {
            $('#withumsgBtnDrawerEmoji').off('click.withumsg');
            $('#withumsgBtnEmoji').off('click.withumsg');
            $(document).off('click.withumsgEmojiOutside');
        },

        _bindEvents: function() {
            var self = this;

            // 抽屉内表情按钮
            $('#withumsgBtnDrawerEmoji').off('click.withumsg').on('click.withumsg', function(e) {
                e.stopPropagation();
                self._targetEditor = 'withumsgDrawerEditor';
                if (self._visible) {
                    self.hide();
                } else {
                    self.show(this);
                }
            });

            // 点击外部关闭
            $(document).off('click.withumsgEmojiOutside').on('click.withumsgEmojiOutside', function(e) {
                if (!self._visible) return;
                var $panel = $('#withumsgEmojiPanel');
                if (!$panel.length || !$panel[0]) return;
                var $btnDrawer = $('#withumsgBtnDrawerEmoji');
                var $btnModal = $('#withumsgBtnEmoji');
                var isInsidePanel = $panel[0].contains(e.target);
                var isInsideBtn = ($btnDrawer.length && $btnDrawer[0].contains(e.target)) ||
                                  ($btnModal.length && $btnModal[0].contains(e.target));
                if (!isInsidePanel && !isInsideBtn) {
                    self.hide();
                }
            });

            // 编辑器光标追踪（抽屉 + 弹窗）— 含 blur 保存
            $('#withumsgDrawerEditor, #withumsgEditor').off('keyup.withumsgCursor mouseup.withumsgCursor blur.withumsgCursor').on('keyup.withumsgCursor mouseup.withumsgCursor blur.withumsgCursor', function() {
                var sel = window.getSelection();
                if (sel.rangeCount > 0) {
                    self._savedRange = sel.getRangeAt(0).cloneRange();
                }
            });

            // 表情面板内 mousedown 阻止默认行为，防止编辑器失焦
            // 仅对鼠标事件生效，触摸事件不阻止（否则移动端首次点击无法触发 click）
            $('#withumsgEmojiPanel').off('mousedown.withumsgPreventBlur').on('mousedown.withumsgPreventBlur', function(e) {
                if (e.originalEvent && e.originalEvent.sourceCapabilities && e.originalEvent.sourceCapabilities.firesTouchEvents) return;
                if (window.TouchEvent && e.originalEvent instanceof TouchEvent) return;
                e.preventDefault();
            });
        },

        show: function(triggerBtn) {
            if (!this._loaded) {
                this._loadData();
            }

            var $panel = $('#withumsgEmojiPanel');

            // 定位到按钮上方
            if (triggerBtn) {
                var rect = triggerBtn.getBoundingClientRect();
                $panel.css({
                    bottom: (window.innerHeight - rect.top + 8) + 'px',
                    left: Math.max(12, rect.left) + 'px',
                    right: 'auto',
                    top: 'auto'
                });
            }

            $panel.removeClass('hide').addClass('show');
            this._visible = true;
        },

        hide: function() {
            var $panel = $('#withumsgEmojiPanel');
            if (!$panel.hasClass('show') && !this._visible) return;
            $panel.removeClass('show').addClass('hide');
            this._visible = false;
            setTimeout(function() {
                $panel.removeClass('hide');
            }, 250);
        },

        /**
         * 粘贴文本时自动将表情 shortcode 解析为 emoji 图片
         * 如果文本中不含 shortcode，退化为普通纯文本插入
         */
        pasteWithEmoji: function(editor, text) {
            if (!text) return;
            var map = this._shortcodeMap;
            // shortcode 格式: :@(xxx) 或 ::(xxx)
            var regex = /:@\([^)]+\)|::\([^)]+\)/g;
            if (!map || !regex.test(text)) {
                document.execCommand('insertText', false, text);
                return;
            }
            // 有 shortcode，逐段解析插入
            regex.lastIndex = 0;
            var sel = window.getSelection();
            if (!sel.rangeCount) { document.execCommand('insertText', false, text); return; }
            var range = sel.getRangeAt(0);
            range.deleteContents();
            var frag = document.createDocumentFragment();
            var lastIndex = 0;
            var match;
            var lastNode = null;
            while ((match = regex.exec(text)) !== null) {
                // 匹配前的纯文本
                if (match.index > lastIndex) {
                    var tn = document.createTextNode(text.substring(lastIndex, match.index));
                    frag.appendChild(tn);
                    lastNode = tn;
                }
                var code = match[0];
                var iconUrl = map[code];
                if (iconUrl) {
                    var img = document.createElement('img');
                    img.src = iconUrl;
                    img.alt = code;
                    img.title = code;
                    img.className = 'emoji';
                    img.setAttribute('data-emoji', code);
                    frag.appendChild(img);
                    lastNode = img;
                } else {
                    var tn2 = document.createTextNode(code);
                    frag.appendChild(tn2);
                    lastNode = tn2;
                }
                lastIndex = regex.lastIndex;
            }
            // 尾部纯文本
            if (lastIndex < text.length) {
                var tail = document.createTextNode(text.substring(lastIndex));
                frag.appendChild(tail);
                lastNode = tail;
            }
            range.insertNode(frag);
            // 光标移到末尾
            if (lastNode) {
                var newRange = document.createRange();
                newRange.setStartAfter(lastNode);
                newRange.collapse(true);
                sel.removeAllRanges();
                sel.addRange(newRange);
            }
        },

        _loadData: function() {
            var self = this;
            var owoBase = (WithUConfig.owoBase || 'OwO');

            // 显示骨架屏
            var $grid = $('#withumsgEmojiGrid');
            var skeletonHtml = '';
            for (var s = 0; s < 20; s++) {
                skeletonHtml += '<div class="withu-message-emoji-skeleton"></div>';
            }
            $grid.html(skeletonHtml);

            $.ajax({
                url: owoBase + '/emoji.json',
                type: 'GET',
                dataType: 'json',
                cache: true,
                success: function(data) {
                    self._data = data;
                    self._keys = Object.keys(data).reverse();
                    self._loaded = true;
                    // 构建 shortcode → icon URL 映射表（用于粘贴时自动解析）
                    var owoImg = (WithUConfig.owoBase || 'OwO') + '/images';
                    var map = {};
                    var keys = Object.keys(data);
                    for (var k = 0; k < keys.length; k++) {
                        var cat = data[keys[k]];
                        if (!cat || !cat.container) continue;
                        for (var j = 0; j < cat.container.length; j++) {
                            var em = cat.container[j];
                            if (em.data && em.icon) map[em.data] = owoImg + '/' + em.icon;
                        }
                    }
                    self._shortcodeMap = map;
                    self._renderTabs();
                    self._renderGrid(0);
                    self._renderBubbles();
                },
                error: function() {
                    console.warn('[EmojiPanel] Failed to load emoji data');
                }
            });
        },

        _renderTabs: function() {
            if (!this._data || !this._keys) return;
            var owoBase = (WithUConfig.owoBase || 'OwO') + '/images';
            var $tabs = $('#withumsgEmojiTabs');
            var html = '';
            for (var i = 0; i < this._keys.length; i++) {
                var catKey = this._keys[i];
                var cat = this._data[catKey];
                var firstIcon = (cat && cat.container && cat.container[0]) ? cat.container[0].icon : '';
                var tabContent = firstIcon
                    ? '<img class="lazy" data-src="' + escapeHtml(owoBase + '/' + firstIcon) + '" alt="' + escapeHtml(catKey) + '">'
                    : escapeHtml(catKey);
                html += '<div class="withu-message-e-tab' + (i === 0 ? ' active' : '') + '" data-cat-index="' + i + '">' + tabContent + '</div>';
            }
            $tabs.html(html);
            updateLazyLoad();

            var self = this;
            $tabs.off('click.withumsg', '.withu-message-e-tab').on('click.withumsg', '.withu-message-e-tab', function() {
                var idx = parseInt($(this).data('cat-index'), 10);
                $tabs.find('.withu-message-e-tab').removeClass('active');
                $(this).addClass('active');
                self._renderGrid(idx);
            });
        },

        _emojiRenderTimer: null,

        _renderGrid: function(catIndex) {
            if (!this._data || !this._keys || !this._keys[catIndex]) return;
            var catKey = this._keys[catIndex];
            var cat = this._data[catKey];
            if (!cat) return;
            var owoBase = (WithUConfig.owoBase || 'OwO') + '/images';
            var items = cat.container || [];
            var $grid = $('#withumsgEmojiGrid');
            var $catTitle = $('#withumsgEmojiCatTitle');

            if (!$grid.length || !$grid[0]) return;

            // 分类标题
            if ($catTitle.length) $catTitle.text(catKey);

            // 清除上一次延迟渲染
            if (this._emojiRenderTimer) { clearTimeout(this._emojiRenderTimer); this._emojiRenderTimer = null; }

            // 先显示骨架屏
            $grid.removeClass('fade-in');
            var cols = Math.floor($grid[0].clientWidth / 46) || 8;
            var skeletonCount = cols * 5;
            var skHtml = '';
            for (var s = 0; s < skeletonCount; s++) skHtml += '<div class="withu-message-emoji-skeleton"></div>';
            $grid.html(skHtml);

            var startTime = performance.now();
            var self = this;

            // 预构建 DOM（不阻塞骨架屏显示）
            requestAnimationFrame(function() {
                var frag = document.createDocumentFragment();
                for (var i = 0; i < items.length; i++) {
                    var em = items[i];
                    var div = document.createElement('div');
                    div.className = 'withu-message-emoji-item';
                    div.setAttribute('data-emoji-data', em.data);
                    div.setAttribute('data-emoji-icon', owoBase + '/' + em.icon);
                    div.setAttribute('data-emoji-text', em.text || em.data);
                    div.title = em.text || em.data;
                    var img = document.createElement('img');
                    img.className = 'lazy';
                    img.setAttribute('data-src', owoBase + '/' + em.icon);
                    img.alt = em.text || em.data;
                    img.decoding = 'async';
                    div.appendChild(img);
                    frag.appendChild(div);
                }

                // 保证骨架屏至少显示 150ms
                var elapsed = performance.now() - startTime;
                var remaining = Math.max(0, 150 - elapsed);

                self._emojiRenderTimer = setTimeout(function() {
                    $grid[0].innerHTML = '';
                    $grid[0].appendChild(frag);
                    $grid.addClass('fade-in');
                    self._emojiRenderTimer = null;
                    updateLazyLoad();
                }, remaining);
            });

            // 短时间内刚被 touchend 处理过的 item：抑制浏览器合成的 click，避免双插入
            var recentlyTouchedSet = null;
            function markRecentlyTouched(el) {
                if (!recentlyTouchedSet) recentlyTouchedSet = new WeakSet();
                recentlyTouchedSet.add(el);
                setTimeout(function() {
                    try { recentlyTouchedSet.delete(el); } catch (_) {}
                }, 500);
            }

            // 点击表情 → 插入到当前目标 contenteditable 编辑器
            $grid.off('click.withumsg', '.withu-message-emoji-item').on('click.withumsg', '.withu-message-emoji-item', function(e) {
                // 触发来源为 touchend 手动 trigger('click.withumsg') 时 e.originalEvent 为空，正常执行
                // 来源为浏览器合成 click 时 e.originalEvent 存在且该 item 已被标记 → 拦截
                if (e && e.originalEvent && recentlyTouchedSet && recentlyTouchedSet.has(this)) {
                    return;
                }
                var emojiData = $(this).data('emoji-data');
                var emojiIcon = $(this).data('emoji-icon');
                if (!emojiData) return;

                var editor = document.getElementById(self._targetEditor || 'withumsgDrawerEditor');
                if (!editor) return;

                editor.focus();

                // 恢复光标位置
                if (self._savedRange) {
                    var sel = window.getSelection();
                    sel.removeAllRanges();
                    sel.addRange(self._savedRange);
                }

                // 插入表情图片
                var img = document.createElement('img');
                img.src = emojiIcon;
                img.alt = emojiData;
                img.title = emojiData;
                img.className = 'emoji';
                img.setAttribute('data-emoji', emojiData);

                var sel2 = window.getSelection();
                if (sel2.rangeCount > 0) {
                    var range = sel2.getRangeAt(0);
                    range.deleteContents();
                    range.insertNode(img);
                    range.setStartAfter(img);
                    range.collapse(true);
                    sel2.removeAllRanges();
                    sel2.addRange(range);
                    self._savedRange = range.cloneRange();
                } else {
                    editor.appendChild(img);
                }

                // 触发 input 事件更新字数统计
                editor.dispatchEvent(new Event('input', { bubbles: true }));

                // 插入后关闭表情面板
                self.hide();
            });

            // 表情预览（hover）— PC 端 mouseenter 显示, 30ms延迟hide实现丝滑平移
            var previewHoverTimer = null;
            function showEmojiPreview(item) {
                var icon = $(item).data('emoji-icon');
                var textName = $(item).data('emoji-text') || $(item).data('emoji-data');
                var preview = document.getElementById('withumsgEmojiPreview');
                if (!preview) return;
                document.getElementById('withumsgPreviewImg').src = icon;
                document.getElementById('withumsgPreviewText').innerText = textName;
                var rect = item.getBoundingClientRect();
                var previewHeight = 90;
                var left = rect.left + (rect.width / 2);
                var top = rect.top > previewHeight + 30 ? rect.top - previewHeight - 8 : rect.bottom + 18;
                preview.style.left = left + 'px';
                preview.style.top = top + 'px';
                preview.style.bottom = 'auto';
                preview.classList.add('active');
            }
            function hideEmojiPreview() {
                var p = document.getElementById('withumsgEmojiPreview');
                if (p) p.classList.remove('active');
            }

            $grid.off('mouseenter.withumsgPreview mouseleave.withumsgPreview', '.withu-message-emoji-item')
                .on('mouseenter.withumsgPreview', '.withu-message-emoji-item', function() {
                    clearTimeout(previewHoverTimer);
                    showEmojiPreview(this);
                })
                .on('mouseleave.withumsgPreview', '.withu-message-emoji-item', function() {
                    previewHoverTimer = setTimeout(hideEmojiPreview, 30);
                });

            // 移动端触摸处理：
            //   - 点一下（短按 < 400ms 且未移动）  → 插入表情
            //   - 长按 ≥ 400ms 且未移动             → 只显示预览，松开不插入
            //   - 滑动 > 10px（页面/面板滚动）       → 不预览也不插入
            // 事件末尾 preventDefault 以避免浏览器默认的 click 再次触发（防双插入）
            var touchLongTimer = null;
            var touchIsLong = false;
            var touchMoved = false;
            var touchStartX = 0, touchStartY = 0;
            var TOUCH_MOVE_THRESHOLD = 10; // px

            $grid.off('touchstart.withumsgTouch touchmove.withumsgTouch touchend.withumsgTouch touchcancel.withumsgTouch', '.withu-message-emoji-item')
                .on('touchstart.withumsgTouch', '.withu-message-emoji-item', function(e) {
                    var item = this;
                    touchIsLong = false;
                    touchMoved = false;
                    var t = e.originalEvent && e.originalEvent.touches && e.originalEvent.touches[0];
                    touchStartX = t ? t.clientX : 0;
                    touchStartY = t ? t.clientY : 0;
                    clearTimeout(touchLongTimer);
                    touchLongTimer = setTimeout(function() {
                        if (touchMoved) return; // 滑动中不再触发预览
                        touchIsLong = true;
                        showEmojiPreview(item);
                    }, 400);
                })
                .on('touchmove.withumsgTouch', '.withu-message-emoji-item', function(e) {
                    var t = e.originalEvent && e.originalEvent.touches && e.originalEvent.touches[0];
                    if (!t) return;
                    var dx = Math.abs(t.clientX - touchStartX);
                    var dy = Math.abs(t.clientY - touchStartY);
                    if (!touchMoved && (dx > TOUCH_MOVE_THRESHOLD || dy > TOUCH_MOVE_THRESHOLD)) {
                        touchMoved = true;
                        clearTimeout(touchLongTimer);
                        touchLongTimer = null;
                        if (touchIsLong) {
                            hideEmojiPreview();
                            touchIsLong = false;
                        }
                    }
                })
                .on('touchend.withumsgTouch', '.withu-message-emoji-item', function(e) {
                    clearTimeout(touchLongTimer);
                    touchLongTimer = null;
                    if (touchMoved) {
                        // 滑动/滚动 → 不插入、不阻止默认（让后续 click 不被触发：浏览器自身滑动时就不会再 dispatch click）
                        touchMoved = false;
                        return;
                    }
                    if (touchIsLong) {
                        // 长按结束 → 仅收起预览，不插入；阻止随后的 click
                        hideEmojiPreview();
                        touchIsLong = false;
                        e.preventDefault();
                        return;
                    }
                    // 真正的短点击 → 标记 + 手动触发插入；preventDefault 尝试阻止合成 click；
                    // 若合成 click 仍漏过来，会被上方 recentlyTouched 拦截，避免双插入
                    e.preventDefault();
                    markRecentlyTouched(this);
                    $(this).trigger('click.withumsg');
                })
                .on('touchcancel.withumsgTouch', '.withu-message-emoji-item', function() {
                    clearTimeout(touchLongTimer);
                    touchLongTimer = null;
                    if (touchIsLong) hideEmojiPreview();
                    touchIsLong = false;
                    touchMoved = false;
                });

        },

        _renderBubbles: function() {
            if (!this._data || !this._keys) return;
            var $container = $('#withumsgEmojiBubbles');
            if (!$container.length) return;
            var owoBase = (WithUConfig.owoBase || 'OwO') + '/images';
            var keys = this._keys.slice().reverse();
            var self = this;
            var frag = document.createDocumentFragment();

            for (var i = 0; i < keys.length; i++) {
                var catKey = keys[i];
                var items = this._data[catKey].container;
                if (!items || items.length === 0) continue;
                var item = items[Math.floor(Math.random() * items.length)];
                var div = document.createElement('div');
                div.className = 'withu-message-emoji-bubble';
                div.setAttribute('data-category', catKey);
                div.setAttribute('data-data', item.data);
                div.innerHTML = '<img class="lazy" data-src="' + escapeHtml(owoBase + '/' + item.icon) + '"><span>' + escapeHtml(item.text || item.data) + '</span>';
                frag.appendChild(div);
            }

            $container.empty().append(frag);
            updateLazyLoad();

            // 点击气泡 → 插入3个表情 + 动画替换
            $container.off('click.withumsgBubble').on('click.withumsgBubble', '.withu-message-emoji-bubble', function() {
                var $bubble = $(this);
                var $img = $bubble.find('img');
                var imgSrc = $img.attr('src') || $img.attr('data-src');
                var dataVal = $bubble.data('data');
                var catKey = $bubble.data('category');
                if (!imgSrc || !dataVal) return;

                // 插入3个表情到编辑器
                var editor = document.getElementById('withumsgEditor');
                if (editor) {
                    editor.focus();
                    for (var j = 0; j < 3; j++) {
                        var img = document.createElement('img');
                        img.src = imgSrc;
                        img.alt = dataVal;
                        img.title = dataVal;
                        img.className = 'emoji';
                        img.setAttribute('data-emoji', dataVal);
                        var sel = window.getSelection();
                        if (sel.rangeCount > 0) {
                            var range = sel.getRangeAt(0);
                            range.deleteContents();
                            range.insertNode(img);
                            range.setStartAfter(img);
                            range.collapse(true);
                            sel.removeAllRanges();
                            sel.addRange(range);
                        } else {
                            editor.appendChild(img);
                        }
                    }
                    editor.dispatchEvent(new Event('input', { bubbles: true }));
                }

                // 动画：淡出当前气泡
                $bubble.addClass('withu-message-bubble-out').css('pointer-events', 'none');
                $bubble.one('animationend', function() {
                    var container = $bubble.parent();
                    $bubble.remove();

                    // 从同一分类随机选一个新的替换
                    var catItems = self._data[catKey] ? self._data[catKey].container : [];
                    if (!catItems || catItems.length === 0) return;
                    var filtered = catItems.filter(function(it) { return it.data !== dataVal; });
                    var newItem = filtered.length > 0 ? filtered[Math.floor(Math.random() * filtered.length)] : catItems[0];

                    var newDiv = document.createElement('div');
                    newDiv.className = 'withu-message-emoji-bubble withu-message-bubble-in';
                    newDiv.setAttribute('data-category', catKey);
                    newDiv.setAttribute('data-data', newItem.data);
                    newDiv.innerHTML = '<img class="lazy" data-src="' + escapeHtml(owoBase + '/' + newItem.icon) + '"><span>' + escapeHtml(newItem.text || newItem.data) + '</span>';
                    container.append(newDiv);
                    updateLazyLoad();
                    $(newDiv).one('animationend', function() { $(this).removeClass('withu-message-bubble-in'); });
                });
            });
        }
    };

    // ============================================
    // 访客信息检测
    // ============================================
    var VisitorDetect = {
        _detected: false,
        os: '--',
        browser: '--',
        location: '--',

        detect: function() {
            if (this._detected) return;
            this._detected = true;

            var ua = navigator.userAgent;
            var m;
            // OS - 仅操作系统名称，不带版本号
            // iPhone/iPad UA 包含 "Mac OS X"，必须优先判断移动设备
            if (/iPhone|iPod/.test(ua)) this.os = 'iOS';
            else if (/iPad/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)) this.os = 'iPadOS';
            else if (/HarmonyOS/.test(ua)) this.os = 'HarmonyOS';
            else if (/Android/.test(ua)) this.os = 'Android';
            else if (/Mac OS X/.test(ua)) this.os = 'macOS';
            else if (/Windows/.test(ua)) this.os = 'Windows';
            else if (/Linux/.test(ua)) this.os = 'Linux';

            // Browser - 微信/QQ等内置浏览器UA含Chrome和Safari，必须优先判断
            if (/MicroMessenger/i.test(ua)) this.browser = '微信';
            else if (/QQ\//i.test(ua) || /MQQBrowser/i.test(ua)) this.browser = 'QQ浏览器';
            else if (/UCBrowser/i.test(ua)) this.browser = 'UC';
            else if (/Edg\//.test(ua)) this.browser = 'Edge';
            else if (/Chrome\//.test(ua) && !/Edg\//.test(ua)) this.browser = 'Chrome';
            else if (/Safari\//.test(ua) && !/Chrome\//.test(ua)) this.browser = 'Safari';
            else if (/Firefox\//.test(ua)) this.browser = 'Firefox';

            // Update tags on page
            this._updateTags();

            // Location - 异步获取
            this._fetchLocation();
        },

        _fetchLocation: function() {
            var self = this;
            // 优先复用 WithUVisitorGeoCache（head.php 中缓存的 IP 定位）
            if (window.WithUVisitorGeoCache) {
                var cached = window.WithUVisitorGeoCache.getCached();
                if (cached && cached.city) {
                    self.location = cached.city;
                    self.lat = cached.lat || null;
                    self.lng = cached.lng || null;
                    $('#withumsgTagLocation').text(self.location);
                    return;
                }
            }
            $('#withumsgTagLocation').text('定位中...');
            $.ajax({
                url: endpoints.infoService || 'services/info-service.php',
                type: 'POST',
                dataType: 'json',
                data: { action: 'geo' },
                timeout: 5000,
                success: function(data) {
                    var city = data.city || '';
                    self.location = city || '未知';
                    self.lat = data.lat || null;
                    self.lng = data.lng || null;
                    $('#withumsgTagLocation').text(self.location);
                },
                error: function() {
                    self.location = '未知';
                    $('#withumsgTagLocation').text('未知');
                }
            });
        },

        _fetchWeather: function() {
            if (window.WITHU_CONFIG && window.WITHU_CONFIG.weatherEnabled === false) {
                $('#withumsgTagWeather').parent('.withu-message-v-tag').hide();
                return;
            }
            // 优先复用 header 天气组件的全局缓存（60秒内有效），避免重复请求
            var cached = window.__lgVisitorWeatherCache;
            if (cached && cached.data && (Date.now() - cached.at) < 60000) {
                this._applyWeatherData(cached.data);
                return;
            }
            $('#withumsgTagWeather').text('获取中...');
            var self = this;
            var _siteBase = (window.WITHU_CONFIG && window.WITHU_CONFIG.siteBase) || '';
            var weatherUrl = (endpoints.weatherApi || (_siteBase + 'services/weather.php')) + '&mode=ip';
            var wToken = (window.WITHU_CONFIG && window.WITHU_CONFIG.weatherToken) || '';
            if (wToken) weatherUrl += '&_wt=' + encodeURIComponent(wToken);
            $.ajax({
                url: weatherUrl,
                type: 'GET',
                dataType: 'json',
                timeout: 8000,
                success: function(res) {
                    if (res.code === 200 && res.data) {
                        var temp = res.data.temp || '--';
                        var desc = res.data.desc || '';
                        self.weather = (temp !== '--' ? temp + '°C' : '') + (desc ? ' ' + desc : '');
                        self.weatherIcon = res.data.icon || '';
                        $('#withumsgTagWeather').text(self.weather || '暂无');
                        // 更新 QWeather 图标
                        var iconCode = String(res.data.icon || '999').replace(/[^\d]/g, '') || '999';
                        $('#withumsgWeatherIcon').attr('class', 'qi-' + iconCode + '-fill');
                    } else {
                        self.weather = '';
                        self.weatherIcon = '';
                        $('#withumsgTagWeather').parent('.withu-message-v-tag').hide();
                    }
                },
                error: function() {
                    self.weather = '';
                    self.weatherIcon = '';
                    $('#withumsgTagWeather').parent('.withu-message-v-tag').hide();
                }
            });
        },

        _applyWeatherData: function(data) {
            var temp = data.temp || '--';
            var desc = data.desc || '';
            this.weather = (temp !== '--' ? temp + '°C' : '') + (desc ? ' ' + desc : '');
            this.weatherIcon = data.icon || '';
            $('#withumsgTagWeather').text(this.weather || '暂无');
            var iconCode = String(data.icon || '999').replace(/[^\d]/g, '') || '999';
            $('#withumsgWeatherIcon').attr('class', 'qi-' + iconCode + '-fill');
        },

        _updateTags: function() {
            $('#withumsgTagOS').text(this.os);
            $('#withumsgTagBrowser').text(this.browser);
        }
    };

    // ============================================
    // 确认弹窗 & 随机一言公共函数
    // ============================================
    var _quoteConfirmCallback = null;
    var QUOTE_CONFIRM_KEY = 'withu_quote_confirm_shown';
    var QUOTE_CONFIRM_EXPIRE = 24 * 60 * 60 * 1000; // 1天过期

    // 检查是否需要显示确认弹窗（首次提醒，1天内不再提醒）
    function _needShowQuoteConfirm() {
        try {
            var data = localStorage.getItem(QUOTE_CONFIRM_KEY);
            if (!data) return true;
            var parsed = JSON.parse(data);
            var now = Date.now();
            // 过期检查
            if (now - parsed.time > QUOTE_CONFIRM_EXPIRE) {
                localStorage.removeItem(QUOTE_CONFIRM_KEY);
                return true;
            }
            return false;
        } catch (e) {
            return true;
        }
    }

    // 标记已显示过确认弹窗
    function _markQuoteConfirmShown() {
        try {
            localStorage.setItem(QUOTE_CONFIRM_KEY, JSON.stringify({ time: Date.now() }));
        } catch (e) {}
    }

    // 清除确认弹窗标记（留言提交后调用）
    function _clearQuoteConfirmMark() {
        try {
            localStorage.removeItem(QUOTE_CONFIRM_KEY);
        } catch (e) {}
    }

    function _showQuoteConfirm(onConfirm) {
        _quoteConfirmCallback = onConfirm;
        var $overlay = $('#withumsgConfirmOverlay');
        if (!$overlay.length) { onConfirm(); return; }
        lockBodyScroll();
        requestAnimationFrame(function() { $overlay.addClass('active'); });
    }

    function _hideQuoteConfirm() {
        var $overlay = $('#withumsgConfirmOverlay');
        $overlay.removeClass('active');
        _quoteConfirmCallback = null;
        unlockBodyScroll();
    }

    $(document).off('click.withumsgConfirmOk').on('click.withumsgConfirmOk', '#withumsgConfirmOk', function() {
        var cb = _quoteConfirmCallback;
        _hideQuoteConfirm();
        _markQuoteConfirmShown(); // 确认后标记
        if (typeof cb === 'function') cb();
    });
    $(document).off('click.withumsgConfirmCancel').on('click.withumsgConfirmCancel', '#withumsgConfirmCancel', function() {
        _hideQuoteConfirm();
    });
    $(document).off('click.withumsgConfirmClose').on('click.withumsgConfirmClose', '#withumsgConfirmClose', function() {
        _hideQuoteConfirm();
    });
    $(document).off('click.withumsgConfirmBg').on('click.withumsgConfirmBg', '#withumsgConfirmOverlay', function(e) {
        if (e.target === this) _hideQuoteConfirm();
    });

    function _doFetchQuote($btn, editor, clearFirst) {
        $btn.data('quoteLoading', true);
        var svgSparkles = '<svg viewBox="0 0 24 24"><path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.582a.5.5 0 0 1 0 .962L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/><path d="M20 3v4"/><path d="M22 5h-4"/></svg>';
        var svgLoader = '<svg class="withu-message-lucide-loader" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/></svg>';
        $btn.html(svgLoader).css('pointer-events', 'none');
        var startTime = Date.now();
        var minDisplayMs = 800;

        $.ajax({
            url: endpoints.infoService || 'services/info-service.php',
            type: 'POST',
            dataType: 'json',
            success: function(result) {
                if (result.Status && result.randomContent) {
                    var text = result.randomContent;
                    $(editor).css({ transition: 'opacity 0.2s ease, transform 0.2s ease', opacity: 0, transform: 'translateY(6px)' });
                    setTimeout(function() {
                        if (clearFirst) editor.innerHTML = '';
                        editor.focus();
                        document.execCommand('insertText', false, text);
                        editor.dispatchEvent(new Event('input', { bubbles: true }));
                        $(editor).css({ opacity: 1, transform: 'translateY(0)' });
                    }, 220);
                } else {
                    if (typeof Toastify !== 'undefined') Toastify.showScenario('error', { text: result.message || '获取失败' });
                }
            },
            error: function() {
                if (typeof Toastify !== 'undefined') Toastify.showScenario('error', { text: '网络错误' });
            },
            complete: function() {
                var elapsed = Date.now() - startTime;
                var remaining = Math.max(0, minDisplayMs - elapsed);
                setTimeout(function() {
                    $btn.html(svgSparkles).css('pointer-events', '');
                    $btn.data('quoteLoading', false);
                }, remaining);
            }
        });
    }

    // ============================================
    // 留言弹窗模块（替代旧的 #show_mes 弹窗）
    // ============================================
    var CommentModal = {
        _currentMode: 'qq',
        _isOpen: false,
        _enterToSend: false,
        _savedRange: null,

        init: function() {
            this._enterToSend = localStorage.getItem('withu_enter_to_send') === 'true';
            this._bindEvents();
            this._initSlider();
            this._renderInputs();
            this._updateEnterToSendUI();
            VisitorDetect.detect();
            // 访客标签（OS/浏览器/归属地）在 init 时填充，天气延迟到弹窗打开时
            this._populateModalTags();
            this._weatherFetched = false;
        },

        _populateModalTags: function() {
            var os = VisitorDetect.os || '--';
            var browser = VisitorDetect.browser || '--';
            var loc = VisitorDetect.location || '--';
            $('#withumsgTagOS').text(os);
            $('#withumsgTagBrowser').text(browser);
            $('#withumsgTagLocation').text(loc);
            // 天气延迟到弹窗打开时获取，避免与 header 天气组件重复请求
        },

        _bindEvents: function() {
            var self = this;

            // 写留言按钮打开新弹窗（全局 message_btn #mes + 旧入口 #click_leav）
            $('#mes, #click_leav').off('click.withumsg').on('click.withumsg', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.open();
                return false;
            });

            // 关闭弹窗
            $('#withumsgModalCloseBtn').off('click.withumsg').on('click.withumsg', function() { self.close(); });
            $('#withumsgCommentModal').off('click.withumsg').on('click.withumsg', function(e) {
                if (e.target === this) self.close();
            });

            // Tab 切换
            $('#withumsgTabContainer').off('click.withumsg', '.withu-message-ios-tab').on('click.withumsg', '.withu-message-ios-tab', function() {
                var mode = $(this).data('mode');
                if (mode && mode !== self._currentMode) {
                    self._currentMode = mode;
                    $(this).addClass('active').siblings('.withu-message-ios-tab').removeClass('active');
                    self._moveSlider(this);
                    self._renderInputs();
                }
            });

            // 随机一言
            $('#withumsgBtnQuote').off('click.withumsg').on('click.withumsg', function() { self._insertRandomQuote(this); });

            // Enter 发送切换
            $('#withumsgEnterToSendWrap').off('click.withumsg').on('click.withumsg', function() {
                self._enterToSend = !self._enterToSend;
                localStorage.setItem('withu_enter_to_send', self._enterToSend);
                self._updateEnterToSendUI();
            });

            // 主编辑器事件（粘贴自动解析表情 shortcode）
            var $editor = $('#withumsgEditor');
            $editor.off('paste.withumsg').on('paste.withumsg', function(e) {
                e.preventDefault();
                var text = (e.originalEvent || e).clipboardData.getData('text/plain');
                EmojiPanel.pasteWithEmoji(this, text);
                this.dispatchEvent(new Event('input', { bubbles: true }));
            });
            $editor.off('input.withumsg').on('input.withumsg', function() { self._updateCharCounter(); });
            $editor.off('keyup.withumsgModalCursor mouseup.withumsgModalCursor blur.withumsgModalCursor').on('keyup.withumsgModalCursor mouseup.withumsgModalCursor blur.withumsgModalCursor', function() {
                var sel = window.getSelection();
                if (sel.rangeCount > 0) {
                    self._savedRange = sel.getRangeAt(0).cloneRange();
                    EmojiPanel._savedRange = self._savedRange;
                }
            });
            $editor.off('keydown.withumsg').on('keydown.withumsg', function(e) {
                if (self._enterToSend && e.key === 'Enter' && !e.shiftKey && !e.ctrlKey && !e.metaKey) {
                    e.preventDefault();
                    self._handleSubmit();
                } else if (!self._enterToSend && (e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    e.preventDefault();
                    self._handleSubmit();
                }
            });

            // 主表情按钮（弹窗内）
            $('#withumsgBtnEmoji').off('click.withumsg').on('click.withumsg', function(e) {
                e.stopPropagation();
                // 切换表情面板为弹窗编辑器模式
                EmojiPanel._targetEditor = 'withumsgEditor';
                if (EmojiPanel._visible) {
                    EmojiPanel.hide();
                } else {
                    EmojiPanel.show(this);
                }
            });

            // 提交按钮
            $('#withumsgSubmitBtn').off('click.withumsg').on('click.withumsg', function() { self._handleSubmit(); });

            // ESC 关闭
            $(document).off('keydown.withumsgModal').on('keydown.withumsgModal', function(e) {
                if (e.key === 'Escape' && self._isOpen) self.close();
            });
        },

        _initSlider: function() {
            var $active = $('#withumsgTabContainer .withu-message-ios-tab.active');
            if ($active.length) this._moveSlider($active[0]);
        },

        _moveSlider: function(tab) {
            var $slider = $('#withumsgTabSlider');
            if (!$slider.length || !tab) return;
            var container = $(tab).closest('.withu-message-ios-tabs')[0];
            if (!container) return;
            var cRect = container.getBoundingClientRect();
            var tRect = tab.getBoundingClientRect();
            $slider.css({
                width: tRect.width + 'px',
                transform: 'translateX(' + (tRect.left - cRect.left) + 'px)'
            });
        },

        _renderInputs: function() {
            var $row = $('#withumsgInputRow');
            if (!$row.length) return;
            var self = this;
            var svgUser = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>';
            var svgLoader = '<svg class="withu-message-lucide-loader" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/></svg>';

            $row.css('opacity', '0');
            var $privacyHint = $('#withumsgPrivacyHint');
            setTimeout(function() {
                if (self._currentMode === 'qq') {
                    $privacyHint.slideDown(200, function() { if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [$privacyHint[0]] }); });
                    var cachedQQ = localStorage.getItem('withu_comment_qq') || '';
                    var cacheHint = '';
                    if (cachedQQ.length >= 5) {
                        cacheHint = '<div class="withu-message-qq-cache-hint" id="withumsgQQCacheHint">' +
                            '<img src="' + escapeHtml(localStorage.getItem('withu_comment_avatar') || _defaultAvatar) + '" onerror="this.style.display=\'none\'">' +
                            '<span>QQ ' + escapeHtml(cachedQQ.slice(0, 3)) + '**' + escapeHtml(cachedQQ.slice(-2)) + '</span></div>';
                    }
                    $row.html(
                        '<div class="withu-message-input-wrapper">' +
                            '<div class="withu-message-i-icon" id="withumsgQQIcon"><img class="withu-message-input-avatar" src="' + _defaultAvatar + '"></div>' +
                            '<input type="text" id="withumsgQQInput" inputmode="numeric" placeholder="输入 QQ 号"' + (cacheHint ? ' style="padding-right:150px"' : '') + '>' +
                            cacheHint +
                        '</div>' +
                        '<div class="withu-message-input-wrapper">' +
                            '<div class="withu-message-i-icon">' + svgUser + '</div>' +
                            '<input type="text" id="withumsgNicknameInput" placeholder="昵称 (必填)">' +
                        '</div>'
                    );
                    // QQ 输入监听（防抖 + 过期响应校验）
                    var qqTimer, _qqSeq = 0;
                    $('#withumsgQQInput').off('input.withumsg').on('input.withumsg', function() {
                        clearTimeout(qqTimer);
                        var val = $(this).val().trim();
                        var $icon = $('#withumsgQQIcon');
                        var $nick = $('#withumsgNicknameInput');
                        var $hint = $('#withumsgQQCacheHint');
                        if ($hint.length) {
                            if (val.length > 0) { $hint.addClass('withu-message-hint-hidden'); $(this).css('padding-right', ''); }
                            else { $hint.removeClass('withu-message-hint-hidden'); $(this).css('padding-right', '150px'); }
                        }
                        if (val.length >= 5) {
                            $icon.html(svgLoader);
                            var seq = ++_qqSeq;
                            qqTimer = setTimeout(function() {
                                localStorage.setItem('withu_comment_qq', val);
                                $.ajax({
                                    url: endpoints.infoService || 'services/info-service.php',
                                    type: 'POST',
                                    dataType: 'json',
                                    data: { action: 'qq', qq: val },
                                    success: function(res) {
                                        if (seq !== _qqSeq) return;
                                        if (res.Status && res.data) {
                                            if (res.data.qq_hash) localStorage.setItem('withu_comment_qq_hash', res.data.qq_hash);
                                            var avatar = res.data.avatar || _defaultAvatar;
                                            localStorage.setItem('withu_comment_avatar', avatar);
                                            $icon.html('<img src="' + escapeHtml(avatar) + '" class="withu-message-input-avatar" style="opacity:0;transition:opacity 0.4s ease" onload="this.style.opacity=1" onerror="this.onerror=null;this.src=\'' + _defaultAvatar.replace(/'/g, '%27') + '\';this.style.opacity=1;">');
                                            var nick = res.data.nickname || res.data.nick || '';
                                            if (nick) $nick.val(nick);
                                        } else {
                                            $icon.html('<img src="' + _defaultAvatar + '" class="withu-message-input-avatar">');
                                            if (!$nick.val() || $nick.val().indexOf('\u670b\u53cb(') === 0) {
                                                $nick.val('\u670b\u53cb(' + val.slice(-2) + ')');
                                            }
                                        }
                                    },
                                    error: function() {
                                        if (seq !== _qqSeq) return;
                                        $icon.html('<img src="' + _defaultAvatar + '" class="withu-message-input-avatar">');
                                        if (!$nick.val() || $nick.val().indexOf('\u670b\u53cb(') === 0) {
                                            $nick.val('\u670b\u53cb(' + val.slice(-2) + ')');
                                        }
                                    }
                                });
                            }, 600);
                        } else {
                            ++_qqSeq;
                            $icon.html('<img class="withu-message-input-avatar" src="' + _defaultAvatar + '">');
                            $nick.val('');
                        }
                    });
                    // QQ 缓存快捷填入（点击hint badge才填入）
                    $(document).off('click.withumsgCacheHint').on('click.withumsgCacheHint', '#withumsgQQCacheHint', function() {
                        var qq = cachedQQ;
                        $('#withumsgQQInput').val(qq).css('padding-right', '').trigger('input');
                        $(this).css({ opacity: 0, transform: 'translateY(-50%) scale(0.9)' });
                        var hint = this;
                        setTimeout(function() { $(hint).remove(); }, 300);
                    });
                } else {
                    $privacyHint.slideUp(200);
                    $row.html(
                        '<div class="withu-message-input-wrapper">' +
                            '<div class="withu-message-i-icon">' + svgUser + '</div>' +
                            '<input type="text" id="withumsgNicknameInput" placeholder="昵称 (必填)">' +
                        '</div>'
                    );
                }
                $row.css('opacity', '1');
            }, 150);
        },

        _updateEnterToSendUI: function() {
            var $wrap = $('#withumsgEnterToSendWrap');
            if (this._enterToSend) $wrap.addClass('active');
            else $wrap.removeClass('active');
            // 同步到抽屉的 switch
            $('.withu-message-switch-wrap').each(function() {
                if (CommentModal._enterToSend) $(this).addClass('active');
                else $(this).removeClass('active');
            });
        },

        _updateCharCounter: function() {
            var editor = document.getElementById('withumsgEditor');
            var $counter = $('#withumsgCharCounter');
            if (!editor || !$counter.length) return;
            var len = (editor.textContent || '').length + (editor.querySelectorAll('img.emoji') || []).length;
            var max = 500;
            $counter.text(len + '/' + max);
            $counter.removeClass('warning danger');
            if (len > max * 0.8) $counter.addClass('warning');
            if (len > max) $counter.addClass('danger');
        },

        _insertRandomQuote: function(btn) {
            var $btn = $(btn);
            if ($btn.data('quoteLoading')) return;
            var editor = document.getElementById('withumsgEditor');
            if (!editor) return;
            var hasContent = (editor.textContent || '').trim().length > 0 || editor.querySelector('img.emoji');

            if (hasContent && _needShowQuoteConfirm()) {
                // 有内容且首次提醒 → 弹出确认弹窗
                _showQuoteConfirm(function() {
                    _doFetchQuote($btn, editor, true);
                });
            } else {
                // 无内容或已确认过 → 直接替换
                _doFetchQuote($btn, editor, hasContent);
            }
        },

        open: function() {
            this._isOpen = true;
            var self = this;
            var $modal = $('#withumsgCommentModal');
            requestAnimationFrame(function() {
                requestAnimationFrame(function() {
                    $modal.addClass('active');
                });
            });
            lockBodyScroll();
            // 首次打开时才获取天气，避免与 header 天气组件重复请求
            if (!this._weatherFetched) {
                this._weatherFetched = true;
                VisitorDetect._fetchWeather();
            }
            // 初始化 slider + 输入框 + 表情气泡
            setTimeout(function() {
                self._initSlider();
                if (window.innerWidth > 768) {
                    var editor = document.getElementById('withumsgEditor');
                    if (editor) editor.focus();
                }
            }, 300);
            this._renderInputs();
            EmojiPanel._renderBubbles();
        },

        close: function() {
            if (!this._isOpen) return;
            this._isOpen = false;
            var $modal = $('#withumsgCommentModal');
            $modal.addClass('closing');
            EmojiPanel.hide();
            setTimeout(function() {
                $modal.removeClass('active closing');
                unlockBodyScroll();
            }, 400);
        },

        _getEditorText: function() {
            var editor = document.getElementById('withumsgEditor');
            if (!editor) return '';

            function extractNodes(parent) {
                var result = '';
                var nodes = parent.childNodes;
                for (var i = 0; i < nodes.length; i++) {
                    var node = nodes[i];
                    if (node.nodeType === 3) {
                        result += node.textContent;
                    } else if (node.nodeType === 1) {
                        if (node.tagName === 'BLOCKQUOTE') continue;
                        if (node.tagName === 'IMG' && node.classList.contains('emoji')) {
                            result += node.getAttribute('data-emoji') || node.getAttribute('data-value') || '';
                        } else if (node.classList && node.classList.contains('withu-message-at-tag')) {
                            var atTarget = node.getAttribute('data-target') || '';
                            var atName = (node.textContent || '').replace(/^@/, '');
                            result += '@[' + atName + '#' + atTarget + ']';
                        } else if (node.tagName === 'BR') {
                            result += '\n';
                        } else if (node.tagName === 'DIV') {
                            var inner = extractNodes(node);
                            if (inner) result += '\n' + inner;
                        } else {
                            result += extractNodes(node);
                        }
                    }
                }
                return result;
            }

            return extractNodes(editor).trim();
        },

        _clearEditor: function() {
            var editor = document.getElementById('withumsgEditor');
            if (editor) editor.innerHTML = '';
            this._updateCharCounter();
        },

        _handleSubmit: function() {
            var text = this._getEditorText();
            if (!text) {
                if (typeof Toastify !== 'undefined') Toastify.showScenario('warning', { text: '请输入留言内容' });
                return;
            }

            var qq, name;
            if (this._currentMode === 'qq') {
                qq = ($('#withumsgQQInput').val() || '').trim();
                if (!qq || qq.length < 5) {
                    if (typeof Toastify !== 'undefined') Toastify.showScenario('warning', { text: '请输入有效的QQ号' });
                    return;
                }
                name = ($('#withumsgNicknameInput').val() || '').trim();
                if (!name) {
                    if (typeof Toastify !== 'undefined') Toastify.showScenario('warning', { text: '请输入昵称' });
                    return;
                }
            } else {
                qq = 'anon';
                name = ($('#withumsgNicknameInput').val() || '').trim();
                if (!name) {
                    if (typeof Toastify !== 'undefined') Toastify.showScenario('warning', { text: '请输入昵称' });
                    return;
                }
            }

            if (containsBannedChar(name + ' ' + text)) {
                if (typeof Toastify !== 'undefined') Toastify.showScenario('warning', { text: '留言包含违禁内容，请修改后重试' });
                return;
            }

            var self = this;
            var geetestAvailable = typeof GeetestHelper !== 'undefined' && GeetestHelper.ready();

            if (geetestAvailable) {
                var $btn = $('#withumsgSubmitBtn');
                $btn.addClass('is-loading');
                // 存储待提交数据，供 submitMessage 全局回调使用
                self._pendingSubmit = { qq: qq, name: name, text: text };
                // 临时包装 onClose 以重置按钮状态
                var _cfg = GeetestHelper.getConfig();
                var _origClose = _cfg.onClose;
                _cfg.onClose = function() {
                    if (self._pendingSubmit) {
                        self._pendingSubmit = null;
                        $btn.removeClass('is-loading');
                    }
                    _cfg.onClose = _origClose;
                    if (typeof _origClose === 'function') _origClose();
                };
                GeetestHelper.show();
            } else {
                this._doSubmit(qq, name, text, {});
            }
        },

        _doSubmit: function(qq, name, text, geetestResult) {
            var self = this;
            var $btn = $('#withumsgSubmitBtn');
            $btn.addClass('is-loading');

            var postData = { qq: qq, name: name, text: text };
            if (VisitorDetect.weather) postData.weather = VisitorDetect.weather;
            if (VisitorDetect.weatherIcon) postData.weather_icon = VisitorDetect.weatherIcon;
            if (geetestResult && geetestResult.lot_number) {
                postData.lot_number = geetestResult.lot_number;
                postData.captcha_output = geetestResult.captcha_output;
                postData.pass_token = geetestResult.pass_token;
                postData.gen_time = geetestResult.gen_time;
            }

            $.ajax({
                url: endpoints.messageSubmit || 'services/message.php',
                type: 'POST',
                dataType: 'json',
                data: postData,
                success: function(res) {
                    if (res.Status) {
                        $btn.removeClass('is-loading').addClass('is-success');
                        $btn.find('.withu-message-submit-label').text('发送成功');

                        // 编辑器收缩 → 纸条打包 → 纸飞机飞走
                        var $editorWrap = $('.withu-message-editor-wrap');
                        $editorWrap.addClass('withu-message-fly-shrink');
                        launchPackedPlane($editorWrap[0]);

                        // 庆祝礼花
                        setTimeout(function() { celebrateConfetti(); }, 500);

                        if (typeof Toastify !== 'undefined') Toastify.showScenario('success', { text: res.message || '留言成功' });
                        self._clearEditor();
                        _clearQuoteConfirmMark(); // 清除随机一言确认标记
                        var isPending = !!res.pending;
                        setTimeout(function() {
                            $btn.removeClass('is-success');
                            $btn.find('.withu-message-submit-label').text('发送留言');
                            $editorWrap.removeClass('withu-message-fly-shrink');
                            self.close();
                            if ($('#withumsgCardGrid').length > 0) {
                                if (isPending) {
                                    // 待审核：在顶部插入本地临时卡片（不重新加载，因为 switch=0 不会出现在列表中）
                                    _insertPendingCard(qq, name, res.MsgHtml || text);
                                    $('html, body').animate({ scrollTop: 0 }, 800);
                                } else {
                                    // 已通过：重新加载列表
                                    $('html, body').animate({ scrollTop: 0 }, 800);
                                    $('#withumsgCardGrid').empty();
                                    MessageList._page = 1;
                                    MessageList._firstLoad = true;
                                    MessageList._hasMore = true;
                                    MessageList.load();
                                }
                            }
                        }, 1800);
                    } else if (res.need_dev_password) {
                        $btn.removeClass('is-loading');
                        _promptDevPassword(function(pw, showSuccess, showError) {
                            delete postData.lot_number; delete postData.captcha_output; delete postData.pass_token; delete postData.gen_time;
                            postData.dev_password = pw;
                            $.ajax({
                                url: endpoints.messageSubmit || 'services/message.php',
                                type: 'POST', dataType: 'json', data: postData,
                                success: function(r2) {
                                    if (r2.Status) {
                                        var r2Pending = !!r2.pending;
                                        showSuccess(function() {
                                            $btn.removeClass('is-loading').addClass('is-success');
                                            $btn.find('.withu-message-submit-label').text('发送成功');
                                            var $editorWrap = $('.withu-message-editor-wrap');
                                            $editorWrap.addClass('withu-message-fly-shrink');
                                            launchPackedPlane($editorWrap[0]);
                                            setTimeout(function() { celebrateConfetti(); }, 500);
                                            if (typeof Toastify !== 'undefined') Toastify.showScenario('success', { text: r2.message || '留言成功' });
                                            self._clearEditor();
                                            _clearQuoteConfirmMark(); // 清除随机一言确认标记
                                            setTimeout(function() {
                                                $btn.removeClass('is-success');
                                                $btn.find('.withu-message-submit-label').text('发送留言');
                                                $editorWrap.removeClass('withu-message-fly-shrink');
                                                self.close();
                                                if ($('#withumsgCardGrid').length > 0) {
                                                    if (r2Pending) {
                                                        _insertPendingCard(qq, name, r2.MsgHtml || text);
                                                        $('html, body').animate({ scrollTop: 0 }, 800);
                                                    } else {
                                                        $('html, body').animate({ scrollTop: 0 }, 800);
                                                        $('#withumsgCardGrid').empty();
                                                        MessageList._page = 1; MessageList._firstLoad = true; MessageList._hasMore = true;
                                                        MessageList.load();
                                                    }
                                                }
                                            }, 1800);
                                        });
                                    } else {
                                        showError(r2.message || '开发者密码错误');
                                    }
                                },
                                error: function() { showError('网络错误'); },
                                complete: function() { $btn.removeClass('is-loading'); }
                            });
                        });
                    } else {
                        if (typeof GeetestHelper !== 'undefined') GeetestHelper.reset();
                        if (typeof Toastify !== 'undefined') Toastify.showScenario('error', { text: res.message || '留言失败' });
                    }
                },
                error: function() {
                    if (typeof GeetestHelper !== 'undefined') GeetestHelper.reset();
                    if (typeof Toastify !== 'undefined') Toastify.showScenario('error', { text: '网络错误，请稍后重试' });
                },
                complete: function() {
                    $btn.removeClass('is-loading');
                    if (typeof window.initLeavingGeetest === 'function') window.initLeavingGeetest();
                }
            });
        }
    };

    // ============================================
    // 身份认证弹窗模块（抽屉用）
    // ============================================
    var AuthModal = {
        _currentMode: 'qq',

        init: function() {
            this._bindEvents();
            this._initSlider();
            this._renderInputs();
        },

        _bindEvents: function() {
            var self = this;

            // 关闭
            $('#withumsgAuthCloseBtn').off('click.withumsg').on('click.withumsg', function() { self.close(); });
            $('#withumsgAuthModal').off('click.withumsg').on('click.withumsg', function(e) {
                if (e.target === this) self.close();
            });

            // Tab 切换
            $('#withumsgAuthTabContainer').off('click.withumsg', '.withu-message-ios-tab').on('click.withumsg', '.withu-message-ios-tab', function() {
                var mode = $(this).data('mode');
                if (mode && mode !== self._currentMode) {
                    self._currentMode = mode;
                    $(this).addClass('active').siblings('.withu-message-ios-tab').removeClass('active');
                    self._moveSlider(this);
                    self._renderInputs();
                }
            });

            // 保存
            $('#withumsgAuthSaveBtn').off('click.withumsg').on('click.withumsg', function() { self._save(); });
        },

        _initSlider: function() {
            var $active = $('#withumsgAuthTabContainer .withu-message-ios-tab.active');
            if ($active.length) this._moveSlider($active[0]);
        },

        _moveSlider: function(tab) {
            var $slider = $('#withumsgAuthTabSlider');
            if (!$slider.length || !tab) return;
            var $tab = $(tab);
            $slider.css({
                width: $tab.outerWidth() + 'px',
                transform: 'translateX(' + ($tab.position().left) + 'px)'
            });
        },

        _renderInputs: function() {
            var $row = $('#withumsgAuthInputRow');
            if (!$row.length) return;
            var svgUser = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>';
            var svgLoader = '<svg class="withu-message-lucide-loader" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/></svg>';
            var self = this;

            $row.css('opacity', '0');
            var $authPrivacyHint = $('#withumsgAuthPrivacyHint');
            setTimeout(function() {
                if (self._currentMode === 'qq') {
                    $authPrivacyHint.slideDown(200, function() { if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [$authPrivacyHint[0]] }); });
                    var cachedQQ = localStorage.getItem('withu_comment_qq') || '';
                    $row.html(
                        '<div class="withu-message-input-wrapper">' +
                            '<div class="withu-message-i-icon" id="withumsgAuthQQIcon"><img class="withu-message-input-avatar" src="' + _defaultAvatar + '"></div>' +
                            '<input type="text" id="withumsgAuthQQInput" inputmode="numeric" placeholder="输入 QQ 号">' +
                        '</div>' +
                        '<div class="withu-message-input-wrapper">' +
                            '<div class="withu-message-i-icon">' + svgUser + '</div>' +
                            '<input type="text" id="withumsgAuthNicknameInput" placeholder="昵称 (必填)">' +
                        '</div>'
                    );
                    var qqTimer, _authQQSeq = 0;
                    $('#withumsgAuthQQInput').off('input.withumsg').on('input.withumsg', function() {
                        clearTimeout(qqTimer);
                        var val = $(this).val().trim();
                        var $icon = $('#withumsgAuthQQIcon');
                        var $nick = $('#withumsgAuthNicknameInput');
                        if (val.length >= 5) {
                            $icon.html(svgLoader);
                            var seq = ++_authQQSeq;
                            qqTimer = setTimeout(function() {
                                localStorage.setItem('withu_comment_qq', val);
                                $.ajax({
                                    url: endpoints.infoService || 'services/info-service.php',
                                    type: 'POST',
                                    dataType: 'json',
                                    data: { action: 'qq', qq: val },
                                    success: function(res) {
                                        if (seq !== _authQQSeq) return;
                                        if (res.Status && res.data) {
                                            if (res.data.qq_hash) localStorage.setItem('withu_comment_qq_hash', res.data.qq_hash);
                                            var avatar = res.data.avatar || _defaultAvatar;
                                            localStorage.setItem('withu_comment_avatar', avatar);
                                            $icon.html('<img src="' + escapeHtml(avatar) + '" class="withu-message-input-avatar" style="opacity:0;transition:opacity 0.4s ease" onload="this.style.opacity=1" onerror="this.onerror=null;this.src=\'' + _defaultAvatar.replace(/'/g, '%27') + '\';this.style.opacity=1;">');
                                            var nick = res.data.nickname || res.data.nick || '';
                                            if (nick) $nick.val(nick);
                                        } else {
                                            $icon.html('<img src="' + _defaultAvatar + '" class="withu-message-input-avatar">');
                                            if (!$nick.val() || $nick.val().indexOf('\u670b\u53cb(') === 0) {
                                                $nick.val('\u670b\u53cb(' + val.slice(-2) + ')');
                                            }
                                        }
                                    },
                                    error: function() {
                                        if (seq !== _authQQSeq) return;
                                        $icon.html('<img src="' + _defaultAvatar + '" class="withu-message-input-avatar">');
                                        if (!$nick.val() || $nick.val().indexOf('\u670b\u53cb(') === 0) {
                                            $nick.val('\u670b\u53cb(' + val.slice(-2) + ')');
                                        }
                                    }
                                });
                            }, 600);
                        } else {
                            ++_authQQSeq;
                            $icon.html('<img class="withu-message-input-avatar" src="' + _defaultAvatar + '">');
                            $nick.val('');
                        }
                    });
                    if (cachedQQ.length >= 5) {
                        $('#withumsgAuthQQInput').val(cachedQQ).trigger('input');
                    }
                } else {
                    $authPrivacyHint.slideUp(200);
                    $row.html(
                        '<div class="withu-message-input-wrapper">' +
                            '<div class="withu-message-i-icon">' + svgUser + '</div>' +
                            '<input type="text" id="withumsgAuthNicknameInput" placeholder="昵称 (必填)">' +
                        '</div>'
                    );
                }
                $row.css('opacity', '1');
            }, 150);
        },

        open: function() {
            var self = this;
            var $modal = $('#withumsgAuthModal');
            lockBodyScroll();
            requestAnimationFrame(function() {
                requestAnimationFrame(function() {
                    $modal.addClass('active');
                    // 初始化 slider
                    var $activeTab = $('#withumsgAuthTabContainer .withu-message-ios-tab.active');
                    if ($activeTab.length) {
                        var $slider = $('#withumsgAuthTabSlider');
                        $slider.css({
                            width: $activeTab.outerWidth() + 'px',
                            transform: 'translateX(' + ($activeTab.position().left) + 'px)',
                            opacity: '1'
                        });
                    }
                });
            });
            this._renderInputs();
        },

        close: function() {
            var $modal = $('#withumsgAuthModal');
            $modal.addClass('closing');
            setTimeout(function() {
                $modal.removeClass('active closing');
                unlockBodyScroll();
            }, 400);
        },

        _save: function() {
            var self = this;
            var nickInput = $('#withumsgAuthNicknameInput');
            var nickname = (nickInput.val() || '').trim();

            if (!nickname) {
                if (typeof Toastify !== 'undefined') Toastify.showScenario('warning', { text: '请先填写昵称' });
                nickInput.focus();
                return;
            }

            if (this._currentMode === 'qq') {
                var qq = ($('#withumsgAuthQQInput').val() || '').trim();
                if (!qq || qq.length < 5) {
                    if (typeof Toastify !== 'undefined') Toastify.showScenario('warning', { text: '请输入有效的QQ号' });
                    return;
                }
                localStorage.setItem('withu_comment_qq', qq);
                localStorage.setItem('withu_comment_anon_name', nickname);
                var avatarUrl = localStorage.getItem('withu_comment_avatar') || _defaultAvatar;
                $('#withumsgDrawerIdentityAvatar').attr('src', avatarUrl);
                $('#withumsgDrawerIdentityName').text(nickname).css('color', 'var(--withumsg-text-main)');
                window.currentUserAuth = { mode: 'qq', qq: qq, name: nickname, avatar: avatarUrl };
            } else {
                localStorage.setItem('withu_comment_anon_name', nickname);
                var anonList = (window.WITHU_CONFIG && window.WITHU_CONFIG.anonAvatars) || [];
                var anonAvatar = anonList.length ? anonList[Math.floor(Math.random() * anonList.length)] : _defaultAvatar;
                $('#withumsgDrawerIdentityAvatar').attr('src', anonAvatar);
                $('#withumsgDrawerIdentityName').text(nickname).css('color', 'var(--withumsg-text-main)');
                window.currentUserAuth = { mode: 'anonymous', qq: 'anon', name: nickname, avatar: anonAvatar };
            }
            localStorage.setItem('withu_comment_auth_confirmed', '1');
            if (typeof Toastify !== 'undefined') Toastify.showScenario('success', { text: '身份已保存' });
            Drawer._checkAutoAuth();
            this.close();
        }
    };

    // ============================================
    // 留言板主模块
    // ============================================
    var LeavingModule = {
        _initialized: false,

        init: function() {
            if (this._initialized) return;

            // 留言弹窗 & 表情面板：全局可用（弹窗 HTML 在 footer.php）
            EmojiPanel.init();
            CommentModal.init();

            // 仅在留言页初始化列表+抽屉+身份认证弹窗
            if ($('#withumsgCardGrid').length > 0) {
                MessageList.init();
                Drawer.init();
                window._LGDrawerRef = Drawer;
                AuthModal.init();
            }

            this._initialized = true;
        },

        destroy: function() {
            $(document).off('.withumsgCard .withumsgReplyName .withumsgDrawer .withumsgModal');
            $('#mes, #click_leav').off('.withumsg');
            MessageList.destroy();
            Drawer.destroy();
            EmojiPanel.destroy();
            this._initialized = false;
        }
    };

    // ============================================
    // 自动初始化
    // ============================================
    $(function() {
        LeavingModule.init();
    });

    // PJAX 完成后重新初始化
    $(document).on('pjax:end.withuLeaving', function() {
        LeavingModule._initialized = false;
        LeavingModule.init();
    });

    // ============================================
    // 暴露到全局（供 PHP 页面 + 极验回调）
    // ============================================
    window.containsBannedChar = containsBannedChar;
    window.submitMessage = function(result) {
        // 极验验证成功回调（从 footer.php GeetestHelper.onSuccess 触发）
        if (CommentModal._isOpen && CommentModal._pendingSubmit) {
            var p = CommentModal._pendingSubmit;
            CommentModal._pendingSubmit = null;
            $('#withumsgSubmitBtn').removeClass('is-loading');
            CommentModal._doSubmit(p.qq, p.name, p.text, result || {});
        } else if (Drawer._isOpen && Drawer._pendingReply) {
            var r = Drawer._pendingReply;
            Drawer._pendingReply = null;
            Drawer._submitReply(r.text, r.parentId, result || {});
        }
    };
    window.openAnonymous = function() {};
    window.loadingname = function() {};

    // ============================================
    // 纸条打包 + 纸飞机飞走（留言成功动画）
    // ============================================
    function launchPackedPlane(fromEl) {
        if (!fromEl) return;
        var rect = fromEl.getBoundingClientRect();
        var cx = rect.left + rect.width / 2;
        var cy = rect.top + rect.height / 2;

        var editor = document.getElementById('withumsgEditor');
        var previewText = (editor ? (editor.innerText || '') : '').trim().slice(0, 18) || '留言';

        // Phase 1: 小纸条出现
        var note = document.createElement('div');
        note.className = 'withu-message-flying-note';
        note.textContent = previewText + (previewText.length >= 18 ? '...' : '');
        note.style.left = cx + 'px';
        note.style.top = cy + 'px';
        document.body.appendChild(note);
        note.addEventListener('animationend', function() { note.remove(); }, { once: true });

        // Phase 2: 纸飞机
        setTimeout(function() {
            var plane = document.createElement('div');
            plane.className = 'withu-message-paper-plane-fly';
            plane.innerHTML = '<svg viewBox="0 0 24 24"><path d="M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11z"/><path d="m21.854 2.147-10.94 10.939"/></svg>';
            plane.style.left = (cx - 18) + 'px';
            plane.style.top = (cy - 18) + 'px';
            document.body.appendChild(plane);
            plane.addEventListener('animationend', function() { plane.remove(); }, { once: true });
        }, 620);
    }

    // ============================================
    // 庆祝礼花
    // ============================================
    function celebrateConfetti() {
        if (typeof confetti !== 'function') return;
        var colors = ['#34c759', '#30d158', '#ffd60a', '#ff9f0a', '#ff375f', '#bf5af2', '#64d2ff', '#5ac8fa'];

        // 中心爆发
        confetti({ particleCount: 80, spread: 70, origin: { y: 0.6 }, colors: colors, zIndex: 100003 });

        // 两侧喷射
        setTimeout(function() {
            confetti({ particleCount: 45, angle: 60, spread: 55, origin: { x: 0, y: 0.65 }, colors: colors, zIndex: 100003 });
            confetti({ particleCount: 45, angle: 120, spread: 55, origin: { x: 1, y: 0.65 }, colors: colors, zIndex: 100003 });
        }, 250);

        // 顶部散落
        setTimeout(function() {
            confetti({ particleCount: 40, spread: 120, origin: { y: 0.3 }, gravity: 0.7, scalar: 1.1, colors: colors, zIndex: 100003 });
        }, 550);
    }

    if (window.WithUApp) {
        window.WithUApp.register('leaving', LeavingModule);
    }

    window.WithULeavingModule = LeavingModule;

})(window, jQuery);
