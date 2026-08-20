/**
 * LG Visitor Hash — 轻量级浏览器指纹生成器
 *
 * 采集不涉及隐私的浏览器特征（canvas/屏幕/时区/语言/WebGL renderer等），
 * 组合后生成 SHA-256 hash，写入 cookie `_vh`，供后端辅助识别换IP的同一访客。
 *
 * 注意：这不是专业的反欺诈指纹库，无法抵御刻意伪造，但可以有效识别
 * 普通用户换IP、换代理、清cookie后的回访行为。
 *
 * 使用方式：在页面底部引入即可，自动执行。
 */
(function() {
    'use strict';

    var COOKIE_NAME = '_vh';
    var COOKIE_DAYS = 365;

    // 已有有效 cookie 则跳过
    if (getCookie(COOKIE_NAME)) return;

    collectFingerprint().then(function(hash) {
        if (hash) setCookie(COOKIE_NAME, hash, COOKIE_DAYS);
    }).catch(function() {});

    /**
     * 采集浏览器特征并生成 hash
     */
    function collectFingerprint() {
        var components = [];

        // 1. 屏幕分辨率 + 色深
        components.push('scr:' + screen.width + 'x' + screen.height + 'x' + (screen.colorDepth || 0));

        // 2. 时区偏移
        components.push('tz:' + new Date().getTimezoneOffset());

        // 3. 语言
        components.push('lang:' + (navigator.language || navigator.userLanguage || ''));

        // 4. 平台
        components.push('plat:' + (navigator.platform || ''));

        // 5. 硬件并发数
        components.push('cores:' + (navigator.hardwareConcurrency || 0));

        // 6. 设备内存（Chrome）
        components.push('mem:' + (navigator.deviceMemory || 0));

        // 7. 触控支持
        components.push('touch:' + (navigator.maxTouchPoints || 0));

        // 8. Canvas 指纹
        try {
            var canvas = document.createElement('canvas');
            canvas.width = 200;
            canvas.height = 50;
            var ctx = canvas.getContext('2d');
            if (ctx) {
                ctx.textBaseline = 'top';
                ctx.font = '14px Arial';
                ctx.fillStyle = '#f60';
                ctx.fillRect(0, 0, 100, 50);
                ctx.fillStyle = '#069';
                ctx.fillText('LGFingerprint', 2, 15);
                ctx.fillStyle = 'rgba(102, 204, 0, 0.7)';
                ctx.fillText('LGFingerprint', 4, 17);
                components.push('cvs:' + canvas.toDataURL().slice(-50));
            }
        } catch (e) {
            components.push('cvs:err');
        }

        // 9. WebGL Renderer
        try {
            var gl = document.createElement('canvas').getContext('webgl') ||
                     document.createElement('canvas').getContext('experimental-webgl');
            if (gl) {
                var ext = gl.getExtension('WEBGL_debug_renderer_info');
                if (ext) {
                    components.push('wgl:' + gl.getParameter(ext.UNMASKED_RENDERER_WEBGL));
                }
            }
        } catch (e) {
            components.push('wgl:err');
        }

        // 10. 已安装字体检测（简易版）
        try {
            var testFonts = ['monospace', 'sans-serif', 'serif'];
            var checkFonts = ['Arial', 'Courier New', 'Georgia', 'Helvetica', 'Times New Roman', 'Verdana', 'Microsoft YaHei', 'PingFang SC'];
            var span = document.createElement('span');
            span.style.cssText = 'position:absolute;left:-9999px;font-size:72px';
            span.textContent = 'mmmmmmmmmmlli';
            document.body.appendChild(span);
            var baseWidths = {};
            testFonts.forEach(function(f) {
                span.style.fontFamily = f;
                baseWidths[f] = span.offsetWidth;
            });
            var detected = [];
            checkFonts.forEach(function(font) {
                for (var i = 0; i < testFonts.length; i++) {
                    span.style.fontFamily = '"' + font + '",' + testFonts[i];
                    if (span.offsetWidth !== baseWidths[testFonts[i]]) {
                        detected.push(font);
                        break;
                    }
                }
            });
            document.body.removeChild(span);
            components.push('fonts:' + detected.join(','));
        } catch (e) {
            components.push('fonts:err');
        }

        var raw = components.join('|');
        return sha256(raw);
    }

    /**
     * SHA-256 hash（使用 Web Crypto API，兼容性好）
     */
    function sha256(str) {
        if (window.crypto && window.crypto.subtle) {
            var buf = new TextEncoder().encode(str);
            return window.crypto.subtle.digest('SHA-256', buf).then(function(hash) {
                return Array.from(new Uint8Array(hash)).map(function(b) {
                    return b.toString(16).padStart(2, '0');
                }).join('');
            });
        }
        // 降级：简单 hash（djb2 变体，不如 SHA-256 但总比没有好）
        return Promise.resolve(simpleHash(str));
    }

    function simpleHash(str) {
        var hash = 5381;
        for (var i = 0; i < str.length; i++) {
            hash = ((hash << 5) + hash + str.charCodeAt(i)) & 0xFFFFFFFF;
        }
        return 'djb2_' + (hash >>> 0).toString(16).padStart(8, '0');
    }

    function setCookie(name, val, days) {
        var d = new Date();
        d.setTime(d.getTime() + days * 86400000);
        var secure = location.protocol === 'https:' ? ';Secure' : '';
        document.cookie = name + '=' + encodeURIComponent(val) +
            ';expires=' + d.toUTCString() +
            ';path=/' + secure + ';SameSite=Lax';
    }

    function getCookie(name) {
        var m = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return m ? decodeURIComponent(m[2]) : '';
    }
})();
