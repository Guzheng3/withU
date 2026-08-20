/**
 * LG_NewUI 前台全局 Tooltip 组件
 *
 * 使用方式：
 *   <span data-lg-tip="提示文本">内容</span>
 *   <span data-lg-tip="<b>HTML</b>" data-lg-tip-html="true">内容</span>
 *   <span data-lg-tip="强制显示" data-lg-tip-force="true">不检查溢出</span>
 *
 * 动画：
 *   - 首次出现：scale(0.92) → scale(1) + 淡入（is-entering）
 *   - 元素间跳转：位置丝滑平移（is-moving），不闪烁
 *   - 消失：淡出 + 微缩（is-leaving）
 */
;(function(window, document) {
    'use strict';

    var tipEl, tipTail, tipText;
    var _initialized = false;
    var _currentTarget = null;   // 当前激活的触发元素
    var _isVisible = false;      // tooltip 当前是否可见
    var _hideTimer = null;
    var _leaveTimer = null;
    var GAP = 8;                 // tooltip 与目标间距
    var PAD = 12;                // 视口边距

    // 圆弧气泡尾巴 SVG（微信风格）
    var TAIL_SVG = '<svg viewBox="0 0 16 8" xmlns="http://www.w3.org/2000/svg">' +
        '<path d="M0 0 C4 0 6 8 8 8 C10 8 12 0 16 0 Z"/></svg>';

    function _ensureDOM() {
        if (tipEl) return;
        tipEl = document.createElement('div');
        tipEl.className = 'lg-tooltip';
        tipEl.innerHTML =
            '<span class="lg-tooltip-text"></span>' +
            '<span class="lg-tooltip-tail is-bottom">' + TAIL_SVG + '</span>';
        document.body.appendChild(tipEl);
        tipText = tipEl.querySelector('.lg-tooltip-text');
        tipTail = tipEl.querySelector('.lg-tooltip-tail');
    }

    /**
     * 计算 tooltip 位置
     */
    function _calcPosition(target) {
        var rect = target.getBoundingClientRect();
        var vw = window.innerWidth;
        var vh = window.innerHeight;
        var tWidth = tipEl.offsetWidth;
        var tHeight = tipEl.offsetHeight;

        // 检查是否指定了方向
        var dir = target.dataset.lgTipDir || 'auto';

        // 左侧方向
        if (dir === 'left') {
            var top = rect.top + (rect.height / 2) - (tHeight / 2);
            var left = rect.left - tWidth - GAP;
            if (left < PAD) {
                left = rect.right + GAP;
                dir = 'right';
            }
            if (top < PAD) top = PAD;
            if (top + tHeight > vh - PAD) top = vh - tHeight - PAD;
            var tailTop = rect.top + rect.height / 2 - top;
            tailTop = Math.max(14, Math.min(tailTop, tHeight - 14));
            return { top: top, left: left, dir: dir, tailTop: tailTop };
        }

        // 右侧方向
        if (dir === 'right') {
            var top = rect.top + (rect.height / 2) - (tHeight / 2);
            var left = rect.right + GAP;
            if (left + tWidth > vw - PAD) {
                left = rect.left - tWidth - GAP;
                dir = 'left';
            }
            if (top < PAD) top = PAD;
            if (top + tHeight > vh - PAD) top = vh - tHeight - PAD;
            var tailTop = rect.top + rect.height / 2 - top;
            tailTop = Math.max(14, Math.min(tailTop, tHeight - 14));
            return { top: top, left: left, dir: dir, tailTop: tailTop };
        }

        // 默认：优先放上方
        var isAbove = true;
        var top = rect.top - tHeight - GAP;
        if (top < PAD) {
            top = rect.bottom + GAP;
            isAbove = false;
        }
        if (!isAbove && top + tHeight > vh - PAD) {
            top = vh - tHeight - PAD;
        }

        // 水平居中于目标
        var left = rect.left + (rect.width / 2) - (tWidth / 2);
        if (left < PAD) left = PAD;
        if (left + tWidth > vw - PAD) left = vw - tWidth - PAD;

        // 尾巴位置：跟随目标中心
        var targetCenterX = rect.left + rect.width / 2;
        var tailLeft = targetCenterX - left;
        tailLeft = Math.max(14, Math.min(tailLeft, tWidth - 14));

        return { top: top, left: left, isAbove: isAbove, tailLeft: tailLeft, dir: 'auto' };
    }

    /**
     * 应用位置 + 尾巴
     */
    function _applyPosition(pos) {
        tipEl.style.top = pos.top + 'px';
        tipEl.style.left = pos.left + 'px';

        // 左右方向
        if (pos.dir === 'left' || pos.dir === 'right') {
            tipEl.style.setProperty('--lg-tip-origin', (pos.dir === 'left' ? 'right' : 'left') + ' ' + pos.tailTop + 'px');
            tipTail.style.left = '';
            tipTail.style.top = pos.tailTop + 'px';
            tipTail.style.transform = 'translateY(-50%)';
            tipTail.className = 'lg-tooltip-tail is-' + (pos.dir === 'left' ? 'right' : 'left');
            return;
        }

        // 上下方向
        tipEl.style.setProperty('--lg-tip-origin', pos.tailLeft + 'px ' + (pos.isAbove ? 'bottom' : 'top'));
        tipTail.style.top = '';
        tipTail.style.left = pos.tailLeft + 'px';
        tipTail.style.transform = 'translateX(-50%)';

        if (pos.isAbove) {
            tipTail.className = 'lg-tooltip-tail is-bottom';
        } else {
            tipTail.className = 'lg-tooltip-tail is-top';
        }
    }

    /**
     * 清除所有动画态 class
     */
    function _clearAnimClasses() {
        tipEl.classList.remove('is-entering', 'is-leaving', 'is-visible');
    }

    function _setContent(target) {
        var tipContent = target.dataset.lgTip;
        if (target.dataset.lgTipHtml === 'true') {
            tipText.innerHTML = tipContent;
        } else {
            tipText.textContent = tipContent;
        }
    }

    function showFor(target) {
        if (!target) return;
        _ensureDOM();

        // 溢出检测：优先检查内部文本子元素（父容器overflow:hidden时自身不报溢出）
        if (target.dataset.lgTipForce !== 'true') {
            var textChild = target.querySelector('.lgmsg-info-text');
            var checkEl = textChild || target;
            if (checkEl.scrollWidth <= checkEl.clientWidth) return;
        }

        // 同一个元素不重复触发
        if (_currentTarget === target && _isVisible) {
            clearTimeout(_hideTimer);
            clearTimeout(_leaveTimer);
            return;
        }

        clearTimeout(_hideTimer);
        clearTimeout(_leaveTimer);

        _currentTarget = target;

        // 统一流程：瞬间重置 → 定位 → 弹入
        _clearAnimClasses();
        tipEl.style.transition = 'none';
        tipEl.style.display = 'block';

        _setContent(target);

        var pos = _calcPosition(target);
        _applyPosition(pos);

        // 强制重排：确保 scale(0.92) + opacity:0 被渲染
        void tipEl.offsetWidth;

        // 启动弹入动画
        tipEl.style.transition = '';
        tipEl.classList.add('is-entering');
        void tipEl.offsetWidth;
        tipEl.classList.add('is-visible');

        _isVisible = true;
    }

    function hide() {
        if (!tipEl || !_isVisible) return;
        _hideTimer = setTimeout(function() {
            _currentTarget = null;
            _isVisible = false;

            // 切换到离开动画
            _clearAnimClasses();
            tipEl.classList.add('is-leaving');

            _leaveTimer = setTimeout(function() {
                if (!_isVisible) {
                    tipEl.style.display = 'none';
                    _clearAnimClasses();
                }
            }, 150);
        }, 50);
    }

    function hideNow() {
        if (!tipEl) return;
        clearTimeout(_hideTimer);
        clearTimeout(_leaveTimer);
        _currentTarget = null;
        _isVisible = false;
        _clearAnimClasses();
        tipEl.style.display = 'none';
    }

    function init() {
        if (_initialized) return;
        _initialized = true;
        _ensureDOM();

        document.body.addEventListener('mouseover', function(e) {
            var target = e.target.closest('[data-lg-tip]');
            if (target) showFor(target);
        });

        document.body.addEventListener('mouseout', function(e) {
            var target = e.target.closest('[data-lg-tip]');
            if (target) hide();
        });

        // 移动端
        document.body.addEventListener('touchstart', function(e) {
            var target = e.target.closest('[data-lg-tip]');
            if (target) {
                if (_isVisible && _currentTarget === target) {
                    hide();
                } else {
                    showFor(target);
                }
            } else if (_isVisible) {
                hide();
            }
        }, { passive: true });

        // 滚动时立即隐藏
        document.addEventListener('scroll', function() {
            if (_isVisible) hideNow();
        }, true);
    }

    // 自动初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.LGTooltip = { init: init, show: showFor, hide: hide, hideNow: hideNow };

})(window, document);
