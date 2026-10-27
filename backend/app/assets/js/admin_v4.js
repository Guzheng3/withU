/* ============================================================
   withU Admin v4 — 交互增强
   - 桌面侧栏折叠（记忆状态）
   - Ctrl / ⌘ + K 命令面板
   - 列表页表格即时筛选
   - 移动端抽屉细节、键盘可达性
   ============================================================ */
(function () {
    'use strict';

    var body = document.body;
    if (!body || !body.classList.contains('admin-v4')) return;

    /* ------------------------------------------------------------
       1. 侧栏折叠
       ------------------------------------------------------------ */
    var sidebarToggle = document.getElementById('adminSidebarToggle');
    var storageKey = 'withu_admin_sidebar_collapsed';

    function isDesktop() {
        return window.matchMedia && window.matchMedia('(min-width: 1100px)').matches;
    }

    function applySidebarState(collapsed) {
        body.classList.toggle('admin-sidebar-collapsed', !!collapsed);
        if (sidebarToggle) {
            sidebarToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            sidebarToggle.setAttribute('title', collapsed ? '展开侧栏' : '折叠侧栏');
            var icon = sidebarToggle.querySelector('i');
            if (icon) {
                icon.className = collapsed
                    ? 'ti ti-layout-sidebar-left-expand'
                    : 'ti ti-layout-sidebar-left-collapse';
            }
        }
    }

    try {
        applySidebarState(localStorage.getItem(storageKey) === '1');
    } catch (e) {
        applySidebarState(false);
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function () {
            if (!isDesktop()) return;
            var next = !body.classList.contains('admin-sidebar-collapsed');
            applySidebarState(next);
            try {
                localStorage.setItem(storageKey, next ? '1' : '0');
            } catch (e) {}
        });
    }

    // 折叠时用 title 提供悬停提示
    Array.prototype.forEach.call(document.querySelectorAll('.admin-drawer-link'), function (link) {
        var label = link.querySelector('span');
        if (label && label.textContent.trim()) {
            link.setAttribute('title', label.textContent.trim());
        }
    });

    /* ------------------------------------------------------------
       2. 命令面板
       ------------------------------------------------------------ */
    var trigger = document.getElementById('adminCommandTrigger');
    var palette = null;
    var paletteInput = null;
    var paletteList = null;
    var activeIndex = 0;
    var items = [];
    var lastFocused = null;

    function collectItems() {
        var list = [];
        Array.prototype.forEach.call(document.querySelectorAll('.admin-drawer-link'), function (link) {
            var label = link.querySelector('span');
            var icon = link.querySelector('i');
            if (!label || !link.getAttribute('href')) return;
            list.push({
                label: label.textContent.trim(),
                href: link.getAttribute('href'),
                icon: icon ? icon.className : 'ti ti-circle',
                section: link.previousElementSibling && link.previousElementSibling.classList.contains('admin-drawer-section-title')
                    ? link.previousElementSibling.textContent.trim()
                    : ''
            });
        });
        list.push({ label: '访问前台', href: '/', icon: 'ti ti-world', section: '快捷' });
        list.push({ label: '退出登录', href: '/logout.php', icon: 'ti ti-logout', section: '快捷' });
        return list;
    }

    function renderItems(query) {
        if (!paletteList) return;
        var q = (query || '').trim().toLowerCase();
        var filtered = items.filter(function (item) {
            if (!q) return true;
            return (item.label + ' ' + item.section).toLowerCase().indexOf(q) !== -1;
        });
        if (!filtered.length) {
            paletteList.innerHTML = '<div class="admin-palette-empty">没有找到匹配的页面</div>';
            activeIndex = 0;
            return;
        }
        paletteList.innerHTML = filtered.map(function (item, index) {
            return '<a class="admin-palette-item' + (index === activeIndex ? ' is-active' : '') + '" href="' +
                item.href.replace(/"/g, '&quot;') + '" data-index="' + index + '">' +
                '<i class="' + item.icon + '"></i><span>' + escapeHtml(item.label) + '</span>' +
                (item.section ? '<span style="margin-left:auto;font-size:.68rem;color:var(--a-ink-3)">' + escapeHtml(item.section) + '</span>' : '') +
                '</a>';
        }).join('');
        paletteList._filtered = filtered;
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function buildPalette() {
        if (palette) return;
        palette = document.createElement('div');
        palette.className = 'admin-palette-backdrop';
        palette.id = 'adminPalette';
        palette.setAttribute('aria-hidden', 'true');
        palette.innerHTML =
            '<div class="admin-palette" role="dialog" aria-modal="true" aria-label="快速跳转">' +
                '<div class="admin-palette-search">' +
                    '<i class="ti ti-search"></i>' +
                    '<input type="text" id="adminPaletteInput" placeholder="搜索后台页面或操作…" autocomplete="off" spellcheck="false">' +
                    '<kbd>ESC</kbd>' +
                '</div>' +
                '<div class="admin-palette-list" id="adminPaletteList"></div>' +
                '<div class="admin-palette-footer">' +
                    '<span><kbd>↑</kbd><kbd>↓</kbd> 选择</span>' +
                    '<span><kbd>↵</kbd> 打开</span>' +
                    '<span style="margin-left:auto">Ctrl / ⌘ + K</span>' +
                '</div>' +
            '</div>';
        document.body.appendChild(palette);
        paletteInput = palette.querySelector('#adminPaletteInput');
        paletteList = palette.querySelector('#adminPaletteList');

        palette.addEventListener('mousedown', function (event) {
            if (event.target === palette) closePalette();
        });

        paletteInput.addEventListener('input', function () {
            activeIndex = 0;
            renderItems(paletteInput.value);
        });

        paletteInput.addEventListener('keydown', function (event) {
            var filtered = paletteList._filtered || [];
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                activeIndex = Math.min(filtered.length - 1, activeIndex + 1);
                renderItems(paletteInput.value);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                activeIndex = Math.max(0, activeIndex - 1);
                renderItems(paletteInput.value);
            } else if (event.key === 'Enter') {
                event.preventDefault();
                var target = filtered[activeIndex];
                if (target) window.location.href = target.href;
            } else if (event.key === 'Escape') {
                event.preventDefault();
                closePalette();
            }
        });

        paletteList.addEventListener('click', function (event) {
            var item = event.target.closest ? event.target.closest('.admin-palette-item') : null;
            if (item && item.getAttribute('href')) {
                // 交给浏览器默认跳转
                closePalette();
            }
        });
    }

    function openPalette() {
        buildPalette();
        items = collectItems();
        activeIndex = 0;
        lastFocused = document.activeElement;
        palette.classList.add('is-open');
        palette.setAttribute('aria-hidden', 'false');
        if (paletteInput) {
            paletteInput.value = '';
            renderItems('');
            window.setTimeout(function () { paletteInput.focus(); }, 60);
        }
        document.documentElement.classList.add('admin-palette-open');
    }

    function closePalette() {
        if (!palette) return;
        palette.classList.remove('is-open');
        palette.setAttribute('aria-hidden', 'true');
        document.documentElement.classList.remove('admin-palette-open');
        if (lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus();
        }
    }

    if (trigger) {
        trigger.addEventListener('click', openPalette);
    }

    document.addEventListener('keydown', function (event) {
        var key = (event.key || '').toLowerCase();
        if ((event.ctrlKey || event.metaKey) && key === 'k') {
            event.preventDefault();
            if (palette && palette.classList.contains('is-open')) closePalette();
            else openPalette();
        }
    });

    document.documentElement.classList.add('admin-palette-ready');

    /* ------------------------------------------------------------
       3. 列表页表格筛选（页面自带表格时自动出现）
       ------------------------------------------------------------ */
    function initTableFilter() {
        var table = document.querySelector('.admin-card table, .admin-table');
        if (!table || table.dataset.a4FilterReady === '1') return;
        table.dataset.a4FilterReady = '1';

        var card = table.closest('.admin-card');
        if (!card) return;

        var tbody = table.tBodies[0];
        // 行数太少时筛选条反而是干扰
        if (!tbody || tbody.rows.length < 4) return;

        var bar = document.createElement('div');
        bar.className = 'a4-table-filter';
        bar.innerHTML =
            '<span class="a4-table-filter-icon"><i class="ti ti-search"></i></span>' +
            '<input type="search" placeholder="筛选当前列表…" aria-label="筛选当前列表">' +
            '<span class="a4-table-filter-count"></span>';
        card.insertBefore(bar, card.firstChild);

        var input = bar.querySelector('input');
        var count = bar.querySelector('.a4-table-filter-count');

        function apply() {
            if (!tbody) return;
            var q = (input.value || '').trim().toLowerCase();
            var rows = tbody.rows;
            var visible = 0;
            Array.prototype.forEach.call(rows, function (row) {
                var text = (row.textContent || '').toLowerCase();
                var show = !q || text.indexOf(q) !== -1;
                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            count.textContent = q ? (visible ? visible + ' 条' : '无匹配') : '';
        }

        input.addEventListener('input', apply);
        apply();
    }

    initTableFilter();

    /* ------------------------------------------------------------
       4. 移动端抽屉：打开时锁定滚动
       ------------------------------------------------------------ */
    var drawer = document.getElementById('admin-drawer');
    if (drawer && window.MutationObserver) {
        new MutationObserver(function () {
            var open = body.classList.contains('admin-drawer-open');
            document.documentElement.classList.toggle('admin-drawer-lock', open);
        }).observe(body, { attributes: true, attributeFilter: ['class'] });
    }

    /* ------------------------------------------------------------
       5. 轻微的按钮波纹（仅后台按钮，尊重 reduced-motion）
       ------------------------------------------------------------ */
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (!reduced) {
        document.addEventListener('click', function (event) {
            var btn = event.target.closest ? event.target.closest('.btn, .admin-icon-btn') : null;
            if (!btn || btn.dataset.a4Ripple === '1') return;
            var rect = btn.getBoundingClientRect();
            var size = Math.max(rect.width, rect.height);
            var ripple = document.createElement('span');
            ripple.className = 'a4-ripple';
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = (event.clientX - rect.left - size / 2) + 'px';
            ripple.style.top = (event.clientY - rect.top - size / 2) + 'px';
            btn.appendChild(ripple);
            window.setTimeout(function () { ripple.remove(); }, 620);
        });
    }
})();
