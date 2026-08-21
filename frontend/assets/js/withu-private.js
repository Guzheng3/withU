/**
 * withu-private.js — 加密页面交互逻辑（PJAX 兼容版）
 * 通过 AJAX 调用 EncryptCheck.php 校验密码
 */
(function () {
    'use strict';

    function initPrivatePage() {
        // 初始化 Lucide 图标
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        var accessForm = document.getElementById('privateAccessForm');
        var accessCode = document.getElementById('privateAccessCode');
        var submitBtn = document.getElementById('privateSubmitBtn');
        if (!accessForm || !accessCode || !submitBtn) return;

        // 幂等保护：防止 PJAX 重复导航时多次绑定事件
        if (accessForm._privateInit) return;
        accessForm._privateInit = true;

        var originalBtnText = '解锁时光';
        var isSubmitting = false;

        // 辅助：刷新 Lucide 图标
        function refreshIcons() {
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }

        // 辅助：按钮恢复到初始状态
        function resetBtn() {
            submitBtn.innerHTML =
                '<span class="withu-private-btn-text">' + originalBtnText + '</span>' +
                '<i data-lucide="chevron-right" class="withu-private-btn-icon"></i>' +
                '<div class="withu-private-btn-shimmer"></div>';
            refreshIcons();
            submitBtn.classList.remove('is-loading', 'is-success');
            isSubmitting = false;
        }

        // 辅助：显示 Toast（如果 Toastify 可用则使用，否则回退到 console）
        function showToast(type, text) {
            if (typeof Toastify !== 'undefined' && Toastify.showScenario) {
                Toastify.showScenario(type, { text: text });
            } else {
                console.log('[' + type + ']', text);
            }
        }

        // 表单提交 → AJAX 验证
        accessForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (isSubmitting) return;
            isSubmitting = true;

            var pagepwd = accessCode.value;
            if (!pagepwd) {
                isSubmitting = false;
                return;
            }

            // 按钮进入加载状态
            submitBtn.innerHTML =
                '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="withu-private-btn-icon withu-private-spin">' +
                '<path d="M12 2v4"/><path d="m16.2 7.8 2.9-2.9"/><path d="M18 12h4"/><path d="m16.2 16.2 2.9 2.9"/>' +
                '<path d="M12 18v4"/><path d="m4.9 19.1 2.9-2.9"/><path d="M2 12h4"/><path d="m4.9 4.9 2.9 2.9"/></svg>' +
                '<span class="withu-private-btn-text">核对中...</span>';
            submitBtn.classList.add('is-loading');

            accessForm.classList.remove('withu-private-shake');

            // 发起 AJAX 校验
            $.ajax({
                url: 'EncryptCheck.html',
                type: 'POST',
                data: { pagepwd: pagepwd },
                dataType: 'json',
                success: function (response) {
                    if (response && response.success) {
                        onSuccess();
                    } else {
                        onFail(response && response.message ? response.message : '暗号似乎不太对哦~');
                    }
                },
                error: function () {
                    onFail('请求失败，请稍后重试');
                }
            });
        });

        // 验证成功处理
        function onSuccess() {
            submitBtn.classList.remove('is-loading');

            // 滑动解锁光条效果
            submitBtn.classList.add('is-unlocking');
            showToast('success', '密码验证通过 正在跳转...');

            // 输入区域淡出
            var inputWrapper = accessForm.querySelector('.withu-private-input-wrapper');
            if (inputWrapper) {
                inputWrapper.style.transition = 'opacity 0.4s ease';
                inputWrapper.style.opacity = '0';
                inputWrapper.style.pointerEvents = 'none';
            }
            accessCode.disabled = true;

            // 光条扫过后，切换到「已解锁」状态
            setTimeout(function () {
                submitBtn.classList.remove('is-unlocking');
                submitBtn.classList.add('is-success');
                submitBtn.innerHTML =
                    '<i data-lucide="lock-open" class="withu-private-btn-icon"></i>' +
                    '<span class="withu-private-btn-text">已解锁</span>';
                refreshIcons();
            }, 550);

            // 整体退场动画
            setTimeout(function () {
                var wrapper = document.getElementById('withuPrivateWrapper');
                var capsule = document.getElementById('privateCapsule');

                if (capsule) {
                    capsule.style.transition = 'opacity 0.4s ease';
                    capsule.style.opacity = '0';
                }
                if (wrapper) {
                    wrapper.classList.add('is-exiting');
                }

                // 动画完成后通过 PJAX 跳转
                setTimeout(function () {
                    document.documentElement.classList.remove('withu-private-mode');

                    // 先将容器设为透明，防止新内容「弹」出来导致布局跳动
                    var container = document.getElementById('pjax-container');
                    if (container) {
                        container.style.transition = 'none';
                        container.style.opacity = '0';
                    }

                    // 监听 PJAX 加载完成，等布局稳定后淡入新内容
                    if (typeof $ !== 'undefined') {
                        $(document).one('pjax:end', function () {
                            var c = document.getElementById('pjax-container');
                            if (c) {
                                requestAnimationFrame(function () {
                                    requestAnimationFrame(function () {
                                        c.style.transition = 'opacity 0.35s cubic-bezier(0.22, 1, 0.36, 1)';
                                        c.style.opacity = '1';
                                        // 过渡结束后清理内联样式
                                        setTimeout(function () {
                                            c.style.transition = '';
                                            c.style.opacity = '';
                                        }, 400);
                                    });
                                });
                            }
                        });
                    }

                    var redirectLink = document.getElementById('privateRedirectLink');
                    if (redirectLink) {
                        redirectLink.click();
                    } else {
                        window.location.href = 'index.html';
                    }
                }, 1700);
            }, 1100);
        }

        // 验证失败处理
        function onFail(message) {
            resetBtn();

            // 触发震动反馈
            accessForm.classList.remove('withu-private-shake');
            void accessForm.offsetWidth;
            accessForm.classList.add('withu-private-shake');

            showToast('error', message);
        }

        // 密码可视切换
        var togglePasswordBtn = document.getElementById('privateTogglePassword');
        if (togglePasswordBtn) {
            togglePasswordBtn.addEventListener('click', function () {
                var type = accessCode.getAttribute('type') === 'password' ? 'text' : 'password';
                accessCode.setAttribute('type', type);

                var icon = this.querySelector('i, svg');
                if (icon) {
                    var newIcon = document.createElement('i');
                    newIcon.setAttribute('data-lucide', type === 'password' ? 'eye' : 'eye-off');
                    newIcon.className = 'withu-private-eye-icon';
                    this.replaceChild(newIcon, icon);
                    refreshIcons();
                }

                accessCode.focus();
            });
        }
    }

    // 暴露到全局，供 PJAX 重复导航时 inline script 直接调用
    window.initPrivatePage = initPrivatePage;

    // 支持 PJAX 和普通加载两种场景
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPrivatePage);
    } else {
        initPrivatePage();
    }
})();
