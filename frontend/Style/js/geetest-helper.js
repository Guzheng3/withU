/**
 * 极验验证码统一初始化助手
 * 支持留言、登录等多场景复用
 * 
 * @author Ki
 * @version 1.0
 * 
 * 使用方式：
 * 1. 引入 gt4.js: <script src="https://static.geetest.com/v4/gt4.js"></script>
 * 2. 引入本文件: <script src="Style/js/geetest-helper.js"></script>
 * 3. 初始化: GeetestHelper.init({ onSuccess: (result) => { ... } })
 */

const GeetestHelper = (function () {
    // captchaId 由后端通过 setCaptchaId() 注入，不再硬编码
    let _captchaId = '';

    const defaultConfig = {
        captchaId: '',
        product: 'bind',
        language: 'zho',
        mask: {
            bgColor: 'rgba(0,0,0,0.5)',
            outside: false
        },
        onReady: null,
        onSuccess: null,
        onError: null,
        onClose: null,
        toast: {
            success: (msg) => console.log('[Geetest Success]', msg),
            error: (msg) => console.error('[Geetest Error]', msg),
            warning: (msg) => console.warn('[Geetest Warning]', msg)
        }
    };

    let captchaObj = null;
    let config = {};
    let isInitialized = false;

    /**
     * 初始化极验验证码
     * @param {Object} options 配置选项
     */
    function init(options = {}) {
        config = Object.assign({}, defaultConfig, options);

        // 检查 gt4.js 是否已加载
        if (typeof initGeetest4 === 'undefined') {
            loadScript().then(() => {
                createCaptcha();
            }).catch((err) => {
                config.toast.error('极验脚本加载失败');
                if (config.onError) config.onError(err);
            });
        } else {
            createCaptcha();
        }
    }

    /**
     * 动态加载极验脚本
     */
    function loadScript() {
        return new Promise((resolve, reject) => {
            if (document.querySelector('script[src*="gt4.js"]')) {
                // 脚本标签已存在，等待加载完成
                const checkInterval = setInterval(() => {
                    if (typeof initGeetest4 !== 'undefined') {
                        clearInterval(checkInterval);
                        resolve();
                    }
                }, 100);
                // 超时处理
                setTimeout(() => {
                    clearInterval(checkInterval);
                    if (typeof initGeetest4 === 'undefined') {
                        reject(new Error('Script load timeout'));
                    }
                }, 5000);
                return;
            }

            const script = document.createElement('script');
            script.src = 'https://static.geetest.com/v4/gt4.js';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    /**
     * 设置 captchaId（由后端注入）
     */
    function setCaptchaId(id) {
        _captchaId = id;
    }

    /**
     * 创建验证码实例
     */
    function createCaptcha() {
        var resolvedId = config.captchaId || _captchaId;
        if (!resolvedId) {
            console.error('[Geetest] captchaId 未配置');
            return;
        }
        initGeetest4({
            captchaId: resolvedId,
            product: config.product,
            language: config.language,
            mask: config.mask
        }, (captcha) => {
            captchaObj = captcha;
            isInitialized = true;

            // 就绪回调
            captcha.onReady(() => {
                if (config.onReady) config.onReady();
            });

            // 关闭回调
            captcha.onClose(() => {
                if (config.onClose) config.onClose();
            });

            // 成功回调
            captcha.onSuccess(() => {
                const result = captcha.getValidate();
                if (!result) {
                    config.toast.error('验证未通过，请重新尝试');
                    if (config.onError) config.onError(new Error('Validate failed'));
                    return;
                }
                console.log('[Geetest] 验证成功');
                if (config.onSuccess) config.onSuccess(result);
            });

            // 错误回调
            captcha.onError((err) => {
                console.error('[Geetest] 验证错误', err);
                config.toast.error('验证出错，请刷新重试');
                if (config.onError) config.onError(err);
            });
        });
    }

    /**
     * 显示验证码弹窗
     */
    function show() {
        if (!captchaObj) {
            config.toast.warning('验证码未初始化，请稍后重试');
            return false;
        }
        captchaObj.showCaptcha();
        return true;
    }

    /**
     * 重置验证码
     */
    function reset() {
        if (captchaObj) {
            captchaObj.reset();
        }
    }

    /**
     * 销毁验证码实例
     */
    function destroy() {
        if (captchaObj) {
            captchaObj.destroy();
            captchaObj = null;
            isInitialized = false;
        }
    }

    /**
     * 检查是否已初始化
     */
    function ready() {
        return isInitialized && captchaObj !== null;
    }

    /**
     * 重新初始化（用于PJAX场景）
     */
    function reinit(options = {}) {
        destroy();
        init(options);
    }

    /**
     * 绑定按钮点击事件
     * @param {string} selector 按钮选择器
     * @param {Function} beforeShow 显示前的校验函数，返回false则不显示验证码
     */
    function bindButton(selector, beforeShow = null) {
        const btn = document.querySelector(selector);
        if (!btn) return;

        btn.addEventListener('click', (e) => {
            e.preventDefault();
            
            // 执行前置校验
            if (beforeShow && beforeShow() === false) {
                return;
            }

            show();
        });
    }

    return {
        init,
        show,
        reset,
        destroy,
        ready,
        reinit,
        bindButton,
        setCaptchaId,
        getConfig: () => config,
        getCaptcha: () => captchaObj
    };
})();

// 支持 AMD/CommonJS/全局变量
if (typeof module !== 'undefined' && module.exports) {
    module.exports = GeetestHelper;
} else if (typeof define === 'function' && define.amd) {
    define([], function () { return GeetestHelper; });
}
