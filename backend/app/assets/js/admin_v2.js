/**
 * 新版后台（admin）交互脚本
 */

document.addEventListener('DOMContentLoaded', function () {
    const body = document.body;
    const drawer = document.querySelector('.admin-drawer');
    const drawerToggles = Array.from(document.querySelectorAll('[data-admin-toggle="drawer"]'));
    const drawerBackdrop = document.querySelector('.admin-drawer-backdrop');
    let lastDrawerTrigger = null;

    function setDrawerOpen(open, restoreFocus) {
        body.classList.toggle('admin-drawer-open', open);
        body.classList.toggle('admin-drawer-scroll-locked', open);
        drawerToggles.forEach(function (toggle) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        if (drawer) {
            drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
        }
        if (drawerBackdrop) {
            drawerBackdrop.setAttribute('aria-hidden', open ? 'false' : 'true');
        }
        if (open && drawer) {
            const firstControl = drawer.querySelector('button, a, input, select, textarea');
            if (firstControl) {
                window.requestAnimationFrame(function () { firstControl.focus(); });
            }
        } else if (restoreFocus && lastDrawerTrigger && document.contains(lastDrawerTrigger)) {
            lastDrawerTrigger.focus();
        }
    }

    if (drawer) {
        drawer.setAttribute('aria-hidden', window.innerWidth >= 1100 ? 'false' : 'true');
    }
    if (drawerBackdrop) {
        drawerBackdrop.setAttribute('aria-hidden', 'true');
    }

    document.querySelectorAll('.admin-drawer-link-active').forEach(function (link) {
        link.setAttribute('aria-current', 'page');
    });

    const requestedSection = new URLSearchParams(window.location.search).get('section');
    if (requestedSection === 'theme') {
        const themeSection = document.getElementById('theme-settings');
        if (themeSection) {
            window.requestAnimationFrame(function () {
                themeSection.scrollIntoView({ block: 'start', behavior: 'smooth' });
            });
        }
    }

    drawerToggles.forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            const open = !body.classList.contains('admin-drawer-open');
            if (open) lastDrawerTrigger = toggle;
            setDrawerOpen(open, !open);
        });
    });

    if (drawerBackdrop) {
        drawerBackdrop.addEventListener('click', function () {
            setDrawerOpen(false, true);
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && body.classList.contains('admin-drawer-open')) {
            event.preventDefault();
            setDrawerOpen(false, true);
            return;
        }
        if (event.key === 'Tab' && body.classList.contains('admin-drawer-open') && drawer) {
            const focusable = Array.from(drawer.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled])'))
                .filter(function (element) { return element.offsetParent !== null; });
            if (!focusable.length) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth >= 1100) {
            setDrawerOpen(false, false);
        } else if (!body.classList.contains('admin-drawer-open') && drawer) {
            drawer.setAttribute('aria-hidden', 'true');
        }
    });

    // 统一确认弹窗
    (function initAdminConfirm() {
        const backdrop = document.getElementById('adminConfirmBackdrop');
        const msgEl = document.getElementById('adminConfirmMessage');
        if (!backdrop || !msgEl) {
            return;
        }

        let confirmCallback = null;
        const okBtn = backdrop.querySelector('[data-admin-confirm="ok"]');
        const cancelBtn = backdrop.querySelector('[data-admin-confirm="cancel"]');

        function hideModal() {
            backdrop.classList.remove('active');
            confirmCallback = null;
        }

        window.adminConfirm = function (message, onConfirm) {
            msgEl.textContent = message || '确认执行该操作？';
            confirmCallback = typeof onConfirm === 'function' ? onConfirm : null;
            backdrop.classList.add('active');
        };

        if (okBtn) {
            okBtn.addEventListener('click', function () {
                if (confirmCallback) {
                    confirmCallback();
                }
                hideModal();
            });
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', function () {
                hideModal();
            });
        }

        backdrop.addEventListener('click', function (e) {
            if (e.target === backdrop) {
                hideModal();
            }
        });
    })();

    // data-confirm 表单和链接统一拦截
    (function initDataConfirm() {
        const forms = document.querySelectorAll('form[data-confirm]');
        forms.forEach(function (form) {
            form.addEventListener('submit', function (e) {
                if (form.dataset.confirmed === '1') {
                    form.dataset.confirmed = '';
                    return;
                }
                e.preventDefault();
                const message = form.getAttribute('data-confirm') || '确认执行该操作？';
                if (typeof window.adminConfirm === 'function') {
                    window.adminConfirm(message, function () {
                        form.dataset.confirmed = '1';
                        form.submit();
                    });
                } else {
                    // 若未提供自定义确认弹窗，则直接执行，避免使用浏览器原生 confirm
                    form.dataset.confirmed = '1';
                    form.submit();
                }
            });
        });

        const links = document.querySelectorAll('a[data-confirm]');
        links.forEach(function (link) {
            link.addEventListener('click', function (e) {
                const href = link.getAttribute('href');
                if (!href) {
                    return;
                }
                e.preventDefault();
                const message = link.getAttribute('data-confirm') || '确认执行该操作？';
                if (typeof window.adminConfirm === 'function') {
                    window.adminConfirm(message, function () {
                        window.location.href = href;
                    });
                } else {
                    // 未提供自定义确认弹窗时，默认直接执行跳转，避免使用浏览器原生 confirm
                    window.location.href = href;
                }
            });
        });
    })();

    // ffmpeg 详情弹窗
    (function initFfmpegDetailsModal() {
        const backdrop = document.getElementById('ffmpegDetailBackdrop');
        const openBtn = document.querySelector('[data-ffmpeg-details="open"]');
        if (!backdrop || !openBtn) {
            return;
        }

        const closeBtn = backdrop.querySelector('[data-ffmpeg-details="close"]');

        function openModal() {
            backdrop.classList.add('active');
        }

        function closeModal() {
            backdrop.classList.remove('active');
        }

        openBtn.addEventListener('click', function () {
            openModal();
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                closeModal();
            });
        }

        backdrop.addEventListener('click', function (e) {
            if (e.target === backdrop) {
                closeModal();
            }
        });
    })();
});
