/**
 * LG_NewUI 前台剪贴板工具
 * @version 1.0.0
 * @description 基于 ClipboardJS 的前台统一复制封装，支持 data 属性声明式 + JS 编程式调用
 *
 * 使用方式一：data 属性声明式
 *   <button data-lg-copy="要复制的文本">复制</button>
 *   <button data-lg-copy-target="#inputId">复制输入框内容</button>
 *   <button data-lg-copy-attr="data-url">复制同元素上指定属性的值</button>
 *
 * 使用方式二：JS 编程式
 *   LGClipboard.copy('要复制的文本')
 *   LGClipboard.copy('文本', { success: '已复制!', error: '复制失败' })
 *   LGClipboard.copy('文本', { silent: true })
 */
;(function(window) {
    'use strict';

    var _instance = null;
    var _attrInstance = null;
    var _targetInstance = null;

    /**
     * 显示复制结果 Toast
     */
    function _showToast(type, text) {
        if (typeof Toastify !== 'undefined' && Toastify.showScenario) {
            Toastify.showScenario(type, { text: text });
        } else if (typeof Toastify !== 'undefined') {
            Toastify({ text: text, duration: 2000, gravity: 'top', position: 'center' }).showToast();
        }
    }

    /**
     * 核心复制方法（编程式）
     * 优先使用 navigator.clipboard API，降级到 ClipboardJS 的 execCommand
     */
    function copy(text, opts) {
        opts = opts || {};
        var successMsg = opts.success || '已复制到剪贴板';
        var errorMsg   = opts.error   || '复制失败';
        var silent     = opts.silent  || false;
        var onSuccess  = opts.onSuccess;
        var onError    = opts.onError;

        if (!text && text !== '') {
            if (!silent) _showToast('error', errorMsg);
            if (onError) onError();
            return Promise.reject(new Error('empty text'));
        }

        // 优先 navigator.clipboard（安全上下文）
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text).then(function() {
                if (!silent) _showToast('success', successMsg);
                if (onSuccess) onSuccess(text);
            }).catch(function() {
                // 降级到 execCommand
                return _fallbackCopy(text, successMsg, errorMsg, silent, onSuccess, onError);
            });
        }

        // 降级方案
        return _fallbackCopy(text, successMsg, errorMsg, silent, onSuccess, onError);
    }

    /**
     * execCommand 降级复制
     */
    function _fallbackCopy(text, successMsg, errorMsg, silent, onSuccess, onError) {
        return new Promise(function(resolve, reject) {
            var textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.cssText = 'position:fixed;left:-9999px;top:-9999px;opacity:0';
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();
            try {
                var ok = document.execCommand('copy');
                document.body.removeChild(textarea);
                if (ok) {
                    if (!silent) _showToast('success', successMsg);
                    if (onSuccess) onSuccess(text);
                    resolve();
                } else {
                    if (!silent) _showToast('error', errorMsg);
                    if (onError) onError();
                    reject(new Error('execCommand failed'));
                }
            } catch (e) {
                document.body.removeChild(textarea);
                if (!silent) _showToast('error', errorMsg);
                if (onError) onError();
                reject(e);
            }
        });
    }

    /**
     * 初始化 data 属性声明式绑定
     * 支持 PJAX 重复调用（会先销毁旧实例）
     */
    function init() {
        destroy();

        if (typeof ClipboardJS === 'undefined') return;

        // data-lg-copy="文本" — 直接复制指定文本
        _instance = new ClipboardJS('[data-lg-copy]', {
            text: function(trigger) {
                return trigger.getAttribute('data-lg-copy') || '';
            }
        });
        _instance.on('success', function(e) {
            var msg = e.trigger.getAttribute('data-lg-copy-msg') || '已复制到剪贴板';
            _showToast('success', msg);
            e.clearSelection();
        });
        _instance.on('error', function() {
            _showToast('error', '复制失败，请手动复制');
        });

        // data-lg-copy-target="#selector" — 复制目标元素的文本/值
        _targetInstance = new ClipboardJS('[data-lg-copy-target]', {
            text: function(trigger) {
                var sel = trigger.getAttribute('data-lg-copy-target');
                var el = sel ? document.querySelector(sel) : null;
                if (!el) return '';
                return el.value !== undefined ? el.value : (el.textContent || el.innerText || '');
            }
        });
        _targetInstance.on('success', function(e) {
            var msg = e.trigger.getAttribute('data-lg-copy-msg') || '已复制到剪贴板';
            _showToast('success', msg);
            e.clearSelection();
        });
        _targetInstance.on('error', function() {
            _showToast('error', '复制失败，请手动复制');
        });

        // data-lg-copy-attr="data-xxx" — 复制触发元素自身某个属性值
        _attrInstance = new ClipboardJS('[data-lg-copy-attr]', {
            text: function(trigger) {
                var attr = trigger.getAttribute('data-lg-copy-attr');
                return attr ? (trigger.getAttribute(attr) || '') : '';
            }
        });
        _attrInstance.on('success', function(e) {
            var msg = e.trigger.getAttribute('data-lg-copy-msg') || '已复制到剪贴板';
            _showToast('success', msg);
            e.clearSelection();
        });
        _attrInstance.on('error', function() {
            _showToast('error', '复制失败，请手动复制');
        });
    }

    /**
     * 销毁所有 ClipboardJS 实例
     */
    function destroy() {
        if (_instance) { _instance.destroy(); _instance = null; }
        if (_attrInstance) { _attrInstance.destroy(); _attrInstance = null; }
        if (_targetInstance) { _targetInstance.destroy(); _targetInstance = null; }
    }

    // 导出
    window.LGClipboard = {
        copy: copy,
        init: init,
        destroy: destroy
    };

})(window);
